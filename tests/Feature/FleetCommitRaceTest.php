<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\AirportResource;
use App\Models\CrewMember;
use App\Models\OperationalFlight;
use App\Models\Route;
use App\Services\Core\AircraftService;
use App\Services\Core\CrewService;
use App\Services\Core\PlanningLock;
use App\Services\Core\ScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FleetCommitRaceTest extends TestCase
{
    public static function scenarios(): array
    {
        return [['configure'], ['usage'], ['flight'], ['crew-leave'], ['crew-profile']];
    }

    #[DataProvider('scenarios')]
    public function test_configuration_and_usage_commit_with_two_independent_connections(string $scenario): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Independent MySQL processes required.');
        }
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        RefreshDatabaseState::$migrated = false;
        $this->seed();
        $a = app(AircraftService::class)->createAircraft(['tail_number' => 'VN-FRACE', 'model_id' => Aircraft::findOrFail('VN-D104')->model_id, 'manufacture_year' => 2020]);
        $configuration = ['expected_revision' => 1, 'name' => 'Race fixture', 'reason' => 'Synthetic configuration race',
            'sections' => [['from' => 1, 'to' => 2, 'columns' => 'AB', 'cabin' => 'ECONOMY']], 'blocked_seats' => []];
        if (str_starts_with($scenario, 'crew-')) {
            $day = now('UTC')->addDays(65)->toDateString();
            $flight = OperationalFlight::create(['flight_number' => 'FR903', 'flight_date' => $day, 'route_id' => Route::where('route_code', 'HAN-DAD')->value('id'),
                'aircraft_tail' => $a->tail_number, 'flight_status' => 'SCHEDULED', 'scheduled_dep_utc' => $day.' 12:00:00', 'scheduled_arr_utc' => $day.' 14:00:00']);
            $captain = CrewMember::where('crew_code', 'DEMO-CRW-05')->firstOrFail();
            $officer = CrewMember::where('crew_code', 'DEMO-CRW-06')->firstOrFail();
            foreach ([[$captain, 'CAPTAIN'], [$officer, 'FIRST_OFFICER']] as [$crew,$position]) {
                app(CrewService::class)->assignCrewToFlight($flight->id, ['crew_id' => $crew->id, 'assigned_position' => $position]);
            }
            $change = $scenario === 'crew-leave' ? ['type' => 'LEAVE', 'starts_at_utc' => $day.' 10:00:00', 'ends_at_utc' => $day.' 17:00:00', 'reason' => 'Synthetic leave/approval race', 'expected_revision' => $captain->revision]
                : ['license_expires_on' => '2000-01-01', 'expected_revision' => $captain->revision];
            $operations = [['crew-approve', $flight->id, []], [$scenario, $captain->id, $change]];
        } elseif ($scenario === 'usage') {
            $flight = OperationalFlight::create(['flight_number' => 'FR901', 'flight_date' => now('UTC')->toDateString(), 'route_id' => Route::where('route_code', 'HAN-DAD')->value('id'),
                'aircraft_tail' => $a->tail_number, 'flight_status' => 'ARRIVED', 'scheduled_dep_utc' => now('UTC')->subHours(3), 'scheduled_arr_utc' => now('UTC')->subHour(),
                'actual_dep_utc' => now('UTC')->startOfHour()->subHours(3), 'actual_arr_utc' => now('UTC')->startOfHour()->subHour()]);
            $usage = ['flight_id' => $flight->id, 'source' => 'DEMO', 'external_id' => 'fleet-race-arrival'];
            $operations = [['usage', $a->tail_number, $usage], ['usage', $a->tail_number, $usage]];
        } elseif ($scenario === 'flight') {
            $old = app(AircraftService::class)->configure($a->tail_number, $configuration);
            $configuration['expected_revision'] = 2;
            $gate = AirportResource::create(['airport_code' => 'HAN', 'resource_type' => 'GATE', 'resource_code' => 'F-RACE', 'status' => 'AVAILABLE']);
            $day = now('UTC')->addDays(55)->toDateString();
            $s = app(ScheduleService::class)->save(['flight_number' => 'FR902', 'route_id' => Route::where('route_code', 'HAN-DAD')->value('id'),
                'model_id' => $a->model_id, 'aircraft_tail' => $a->tail_number, 'resource_id' => $gate->id, 'departure_time_utc' => '12:00:00', 'arrival_time_utc' => '14:00:00',
                'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'effective_from' => $day, 'effective_to' => $day]);
            foreach (['submit', 'approve', 'publish'] as $action) {
                app(ScheduleService::class)->transition($s->id, $action);
            }
            $configuration['blocked_seats'] = ['2B'];
            $operations = [['flight', $s->id, ['schedule_id' => $s->id, 'flight_date' => $day]], ['configure', $a->tail_number, $configuration]];
        } else {
            $operations = [['configure', $a->tail_number, $configuration], ['configure', $a->tail_number, $configuration]];
        }
        $workers = [];
        DB::beginTransaction();
        PlanningLock::acquire();
        try {
            foreach ($operations as [$operation,$id,$data]) {
                $process = proc_open([PHP_BINARY, base_path('scripts/planning-race-worker.php'), $operation, $id, json_encode($data)],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                $ready = json_decode(fgets($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
                $this->assertTrue($ready['ready']);
                $this->assertSame(DB::connection()->getDatabaseName(), $ready['database']);
                fclose($pipes[0]);
                $workers[] = compact('process', 'pipes', 'ready');
            }
            $connections = array_column(array_column($workers, 'ready'), 'connection_id');
            $deadline = microtime(true) + 5;
            do {
                $waiters = (int) DB::selectOne('SELECT COUNT(DISTINCT t.PROCESSLIST_ID) AS n FROM performance_schema.data_lock_waits w JOIN performance_schema.threads t ON t.THREAD_ID=w.REQUESTING_THREAD_ID WHERE t.PROCESSLIST_ID IN (?,?)', $connections)->n;
                if ($waiters === 2) {
                    break;
                } usleep(20000);
            } while (microtime(true) < $deadline);
            DB::commit();
            $results = [];
            foreach ($workers as $worker) {
                $result = json_decode(stream_get_contents($worker['pipes'][1]), true, 512, JSON_THROW_ON_ERROR);
                $stderr = stream_get_contents($worker['pipes'][2]);
                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);
                $this->assertSame(0, proc_close($worker['process']), $stderr.json_encode($result));
                $results[] = $result;
            }
            $this->assertSame(2, $waiters);
            if (str_starts_with($scenario, 'crew-')) {
                $this->assertSame('committed', $results[1]['result']);
                $this->assertContains($results[0]['result'], ['committed', 'rejected']);
                $assignment = $flight->crewAssignments()->where('crew_id', $captain->id)->sole();
                $this->assertSame('ASSIGNED', $assignment->status);
                $this->assertNull($assignment->approved_by);
                $this->assertNull($assignment->confirmed_at);
                if ($scenario === 'crew-leave') {
                    $this->assertDatabaseHas('crew_availability_windows', ['crew_id' => $captain->id, 'type' => 'LEAVE']);
                } else {
                    $this->assertSame('2000-01-01', $captain->fresh()->license_expires_on);
                }
            } elseif ($scenario === 'usage') {
                $this->assertSame(['committed', 'committed'], array_column($results, 'result'));
                $this->assertSame($results[0]['id'], $results[1]['id']);
                $this->assertDatabaseHas('aircrafts', ['tail_number' => $a->tail_number, 'total_flight_hours' => '2.00', 'total_flight_cycles' => 1]);
                $this->assertSame(1, DB::table('aircraft_usage_records')->where('aircraft_tail', $a->tail_number)->count());
                $this->assertSame(1, DB::table('aircraft_usage_receipts')->where('external_id', 'fleet-race-arrival')->count());
            } elseif ($scenario === 'configure') {
                $this->assertEqualsCanonicalizing(['committed', 'rejected'], array_column($results, 'result'));
                $this->assertSame(1, $a->configurations()->count());
                $this->assertSame(2, $a->fresh()->revision);
            } else {
                $this->assertSame('committed', $results[0]['result']);
                $flight = OperationalFlight::where('schedule_id', $s->id)->sole();
                $current = $a->fresh()->configuration;
                $this->assertSame($current->id, $flight->aircraft_configuration_id);
                $this->assertSame($current->sellable_capacity, $flight->seat_capacity_snapshot);
                if ($results[1]['result'] === 'committed') {
                    $this->assertSame(2, $current->version);
                    $this->assertSame(3, $flight->seat_capacity_snapshot);
                } else {
                    $this->assertSame('rejected', $results[1]['result']);
                    $this->assertSame($old->id, $current->id);
                    $this->assertSame(4, $flight->seat_capacity_snapshot);
                }
            }
            $dir = getenv('TEST_EVIDENCE_DIR') ?: base_path('docs/testing/evidence/2026-10-18-fleet');
            file_put_contents($dir.'/fleet-'.$scenario.'-race.json', json_encode(['test_id' => 'IT-FLEET-'.strtoupper($scenario).'-RACE-01',
                'database' => DB::connection()->getDatabaseName(), 'engine' => DB::selectOne('SELECT VERSION() AS v')->v, 'observed_lock_waiters' => $waiters,
                'connections' => $connections, 'results' => $results, 'executed_at_utc' => gmdate('c')], JSON_PRETTY_PRINT));
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                if (is_resource($worker['process'])) {
                    proc_terminate($worker['process']);
                    proc_close($worker['process']);
                }
            }
        }
    }
}
