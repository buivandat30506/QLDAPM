<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\AirportResource;
use App\Models\FlightSchedule;
use App\Models\OperationalFlight;
use App\Models\Route;
use App\Services\Core\PlanningLock;
use App\Services\Core\ScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlanningCommitRaceTest extends TestCase
{
    public static function scenarios(): array { return [['publish'], ['flight'], ['maintenance'], ['maintenance-plan']]; }

    #[DataProvider('scenarios')]
    public function test_commit_revalidates_after_two_connections_wait_on_shared_mutex(string $scenario): void
    {
        if (config('database.default') !== 'mysql') { $this->markTestSkipped('Independent MySQL processes required.'); }
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0); RefreshDatabaseState::$migrated = false; $this->seed();
        $aircraft = Aircraft::create(['tail_number' => 'VN-PRACE', 'model_id' => Aircraft::findOrFail('VN-D104')->model_id, 'manufacture_year' => 2020,
            'total_flight_hours' => 0, 'total_flight_cycles' => 0, 'operational_status' => 'OPERATIONAL']);
        $gate = AirportResource::create(['airport_code' => 'HAN', 'resource_type' => 'GATE', 'resource_code' => 'P-RACE', 'status' => 'AVAILABLE']);
        $day = now('UTC')->addDays(45)->toDateString();
        $schedule = app(ScheduleService::class)->save(['flight_number' => 'PR901', 'route_id' => Route::where('route_code', 'HAN-DAD')->value('id'),
            'model_id' => $aircraft->model_id, 'aircraft_tail' => $aircraft->tail_number, 'resource_id' => $gate->id,
            'departure_time_utc' => '12:00:00', 'arrival_time_utc' => '14:00:00', 'days_of_week' => [1,2,3,4,5,6,7], 'effective_from' => $day, 'effective_to' => $day]);
        if (! in_array($scenario, ['maintenance', 'maintenance-plan'])) {
            foreach (['submit', 'approve'] as $action) { app(ScheduleService::class)->transition($schedule->id, $action); }
            if ($scenario === 'flight') { app(ScheduleService::class)->transition($schedule->id, 'publish'); }
        }
        $operations = match ($scenario) {
            'publish' => [['publish', []], ['publish', []]],
            'flight' => [['flight', ['schedule_id' => $schedule->id, 'flight_date' => $day]], ['flight', ['schedule_id' => $schedule->id, 'flight_date' => $day]]],
            'maintenance' => [['submit', []], ['maintenance', ['order_code' => 'PR-MAINT', 'aircraft_tail' => $aircraft->tail_number,
                'task' => 'Synthetic concurrent maintenance', 'starts_at_utc' => $day.' 12:30:00', 'ends_at_utc' => $day.' 13:30:00', 'due_at_utc' => $day.' 14:30:00']]],
            'maintenance-plan' => [['submit', []], ['maintenance-plan', ['plan_code' => 'PR-MPLAN', 'aircraft_tail' => $aircraft->tail_number,
                'task' => 'Synthetic modern plan race', 'trigger_type' => 'DATE', 'tasks' => ['Inspect'], 'reason' => 'Synthetic concurrent planning',
                'starts_at_utc' => $day.' 12:30:00', 'ends_at_utc' => $day.' 13:30:00', 'due_at_utc' => $day.' 14:30:00']]],
        };
        $workers = []; DB::beginTransaction(); PlanningLock::acquire();
        try {
            foreach ($operations as [$operation, $data]) {
                $process = proc_open([PHP_BINARY, base_path('scripts/planning-race-worker.php'), $operation, $schedule->id, json_encode($data)],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                $ready = json_decode(fgets($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
                $this->assertTrue($ready['ready']); $this->assertSame(DB::connection()->getDatabaseName(), $ready['database']);
                fclose($pipes[0]); $workers[] = compact('process', 'pipes', 'ready');
            }
            $connections = array_column(array_column($workers, 'ready'), 'connection_id'); $deadline = microtime(true) + 5;
            do {
                $waiters = (int) DB::selectOne('SELECT COUNT(DISTINCT t.PROCESSLIST_ID) AS n FROM performance_schema.data_lock_waits w JOIN performance_schema.threads t ON t.THREAD_ID=w.REQUESTING_THREAD_ID WHERE t.PROCESSLIST_ID IN (?,?)', $connections)->n;
                if ($waiters === 2) { break; } usleep(20000);
            } while (microtime(true) < $deadline);
            DB::commit(); $results = [];
            foreach ($workers as $worker) {
                $result = json_decode(stream_get_contents($worker['pipes'][1]), true, 512, JSON_THROW_ON_ERROR); $stderr = stream_get_contents($worker['pipes'][2]);
                fclose($worker['pipes'][1]); fclose($worker['pipes'][2]);
                $this->assertSame(0, proc_close($worker['process']), $stderr.json_encode($result)); $results[] = $result;
            }
            $this->assertSame(2, $waiters); $this->assertEqualsCanonicalizing(['committed', 'rejected'], array_column($results, 'result'));
            if ($scenario === 'publish') {
                $this->assertDatabaseHas('flight_schedules', ['id' => $schedule->id, 'status' => 'PUBLISHED', 'version' => 1]);
                $this->assertSame(1, DB::table('entity_versions')->where('entity_id', $schedule->id)->count());
            } elseif ($scenario === 'flight') {
                $this->assertSame(1, OperationalFlight::where('schedule_id', $schedule->id)->count());
            } elseif ($scenario === 'maintenance-plan') {
                $this->assertSame(1, (int) (FlightSchedule::findOrFail($schedule->id)->status === 'PENDING') + DB::table('maintenance_plans')->where('plan_code', 'PR-MPLAN')->count());
            } else {
                $this->assertSame(1, (int) (FlightSchedule::findOrFail($schedule->id)->status === 'PENDING') + DB::table('maintenance_orders')->where('order_code', 'PR-MAINT')->count());
            }
            $evidence = getenv('TEST_EVIDENCE_DIR') ?: base_path('docs/testing/evidence/2026-10-18-planning');
            file_put_contents($evidence.'/planning-'.$scenario.'-race.json', json_encode(['test_id' => 'IT-PLANNING-'.strtoupper($scenario).'-RACE-01',
                'engine' => DB::selectOne('SELECT VERSION() AS v')->v, 'database' => DB::connection()->getDatabaseName(), 'connections' => $connections,
                'observed_lock_waiters' => $waiters, 'results' => $results, 'executed_at_utc' => gmdate('c')], JSON_PRETTY_PRINT));
        } finally {
            if (DB::transactionLevel() > 0) { DB::rollBack(); }
            foreach ($workers as $worker) { if (is_resource($worker['process'])) { proc_terminate($worker['process']); proc_close($worker['process']); } }
        }
    }
}
