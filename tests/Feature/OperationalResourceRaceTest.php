<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\AirportResource;
use App\Models\CrewMember;
use App\Models\FlightSchedule;
use App\Models\OperationalFlight;
use App\Models\Route;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OperationalResourceRaceTest extends TestCase
{
    public static function resourceCases(): array
    {
        return [['aircraft'], ['gate'], ['crew']];
    }

    #[DataProvider('resourceCases')]
    public function test_shared_resource_allows_only_one_overlapping_operation(string $kind): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Aircraft/gate/crew contention requires independent MySQL connections.');
        }
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        RefreshDatabaseState::$migrated = false;
        $this->seed();
        $model = Aircraft::findOrFail('VN-D104')->model_id;
        $route = Route::where('route_code', 'HAN-DAD')->firstOrFail();
        $day = now('UTC')->addDays(20)->toDateString();
        $aircrafts = [];
        $gates = [];
        for ($i = 0; $i < 2; $i++) {
            $aircrafts[] = Aircraft::create(['tail_number' => 'VN-RACE'.$i, 'model_id' => $model, 'manufacture_year' => 2020, 'total_flight_hours' => 0, 'total_flight_cycles' => 0, 'operational_status' => 'OPERATIONAL']);
            $gates[] = AirportResource::create(['airport_code' => 'HAN', 'resource_type' => 'GATE', 'resource_code' => 'RACE-G'.$i, 'status' => 'AVAILABLE']);
        }
        $crew = null;
        $ids = [];
        if ($kind === 'crew') {
            $crew = CrewMember::create(['crew_code' => 'RACE-CREW', 'full_name' => 'Synthetic race crew', 'email' => 'race-crew@example.test', 'phone' => 'SYNTHETIC',
                'rank' => 'CAPTAIN', 'base_airport_code' => 'HAN', 'license_number' => 'RACE-LIC', 'license_expires_on' => now('UTC')->addYear()->toDateString(),
                'training_expires_on' => now('UTC')->addYear()->toDateString(), 'qualified_model_ids' => [$model], 'status' => 'AVAILABLE']);
        }
        for ($i = 0; $i < 2; $i++) {
            if ($kind === 'crew') {
                $ids[] = OperationalFlight::create(['flight_number' => 'RC'.$i, 'flight_date' => $day, 'route_id' => $route->id, 'aircraft_tail' => $aircrafts[$i]->tail_number,
                    'resource_id' => $gates[$i]->id, 'scheduled_dep_utc' => $day.' 12:00:00', 'scheduled_arr_utc' => $day.' 14:00:00', 'flight_status' => 'SCHEDULED'])->id;
            } else {
                $ids[] = FlightSchedule::create(['flight_number' => 'RS'.$i, 'route_id' => $route->id, 'model_id' => $model,
                    'aircraft_tail' => $aircrafts[$kind === 'aircraft' ? 0 : $i]->tail_number, 'resource_id' => $gates[$kind === 'gate' ? 0 : $i]->id,
                    'departure_time_utc' => '12:00:00', 'arrival_time_utc' => '14:00:00', 'effective_from' => $day, 'effective_to' => $day,
                    'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'status' => 'DRAFT', 'is_active' => true])->id;
            }
        }
        $table = match ($kind) {
            'aircraft' => 'aircrafts','gate' => 'airport_resources','crew' => 'crew_members'
        };
        $key = $kind === 'aircraft' ? 'tail_number' : 'id';
        $value = match ($kind) {
            'aircraft' => $aircrafts[0]->tail_number,'gate' => $gates[0]->id,'crew' => $crew->id
        };
        $workers = [];
        DB::beginTransaction();
        DB::table($table)->where($key, $value)->lockForUpdate()->firstOrFail();
        try {
            foreach ($ids as $id) {
                $process = proc_open([PHP_BINARY, base_path('scripts/operations-race-worker.php'), $kind === 'crew' ? 'crew' : 'schedule', $id, $crew?->id ?? ''],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                $ready = json_decode(fgets($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
                $this->assertTrue($ready['ready']);
                $this->assertSame(DB::connection()->getDatabaseName(), $ready['database']);
                $workers[] = compact('process', 'pipes', 'ready');
                fclose($pipes[0]);
            }
            $connections = array_column(array_column($workers, 'ready'), 'connection_id');
            $deadline = microtime(true) + 5;
            do {
                $waiters = (int) DB::selectOne('SELECT COUNT(DISTINCT t.PROCESSLIST_ID) AS n FROM performance_schema.data_lock_waits w JOIN performance_schema.threads t ON t.THREAD_ID=w.REQUESTING_THREAD_ID WHERE t.PROCESSLIST_ID IN (?, ?)', $connections)->n;
                if ($waiters === 2) {
                    break;
                }usleep(20000);
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
            $this->assertEqualsCanonicalizing(['committed', 'rejected'], array_column($results, 'result'));
            if ($kind === 'crew') {
                $this->assertSame(1, DB::table('flight_crew_assignments')->where('crew_id', $crew->id)->count());
            } else {
                $this->assertSame(1, FlightSchedule::whereIn('id', $ids)->where('status', 'PENDING')->count());
            }
            $evidence = getenv('TEST_EVIDENCE_DIR') ?: base_path('docs/testing/evidence/2026-10-18-g2');
            file_put_contents($evidence.'/operations-'.$kind.'-race.json', json_encode(['test_id' => 'IT-'.strtoupper($kind).'-RACE-01', 'engine' => DB::selectOne('SELECT VERSION() AS v')->v,
                'database' => DB::connection()->getDatabaseName(), 'connections' => $connections, 'observed_lock_waiters' => $waiters, 'results' => $results, 'executed_at_utc' => gmdate('c')], JSON_PRETTY_PRINT));
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
