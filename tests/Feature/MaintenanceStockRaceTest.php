<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\AirportResource;
use App\Models\User;
use App\Services\Core\MaintenanceWorkflow;
use App\Services\Core\PlanningLock;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaintenanceStockRaceTest extends TestCase
{
    public function test_two_independent_orders_cannot_consume_the_same_material_stock(): void
    {
        if (config('database.default') !== 'mysql') { $this->markTestSkipped('Two independent MySQL connections required.'); }
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0); RefreshDatabaseState::$migrated = false; $this->seed();
        $service = app(MaintenanceWorkflow::class);
        $m = $service->receiveMaterial(['airport_code' => 'HAN', 'code' => 'RACE', 'name' => 'Vật tư tranh chấp', 'unit' => 'bộ', 'quantity' => 1, 'reference' => 'RACE-RECEIPT', 'reason' => 'Dữ liệu kiểm thử']);
        $orders = [];
        foreach (['tech_demo', 'toan_lead'] as $i => $username) {
            $a = Aircraft::create(['tail_number' => 'VN-MXR'.$i, 'model_id' => Aircraft::findOrFail('VN-D104')->model_id, 'manufacture_year' => 2020,
                'total_flight_hours' => 0, 'total_flight_cycles' => 0, 'base_airport_code' => 'HAN', 'operational_status' => 'OPERATIONAL']);
            $stand = AirportResource::create(['airport_code' => 'HAN', 'resource_type' => 'STAND', 'resource_code' => 'MXR-'.$i, 'status' => 'AVAILABLE']);
            $p = $service->savePlan(['plan_code' => 'MXR-P'.$i, 'aircraft_tail' => $a->tail_number, 'task' => 'Kiểm tra tranh chấp', 'trigger_type' => 'DATE',
                'starts_at_utc' => now('UTC')->subHour()->toDateTimeString(), 'ends_at_utc' => now('UTC')->addHour()->toDateTimeString(), 'due_at_utc' => now('UTC')->addDay()->toDateTimeString(), 'tasks' => ['Thực hiện kiểm tra'], 'reason' => 'Fixture concurrency']);
            $order = $service->createOrder($p->id, ['order_code' => 'MXR-O'.$i, 'technician_id' => User::where('username', $username)->value('id'), 'location_id' => $stand->id, 'materials' => [['material_id' => $m->id, 'quantity' => 1]]]);
            $this->actingAs(User::where('username', 'tech_demo')->firstOrFail());
            $service->action($order->id, 'authorize', ['expected_revision' => $order->revision, 'reason' => 'Phê duyệt nguồn lực fixture trước thực hiện']);
            auth()->logout(); $orders[] = $order->id;
        }
        $workers = []; DB::beginTransaction(); PlanningLock::acquire();
        try {
            foreach ($orders as $id) {
                $process = proc_open([PHP_BINARY, base_path('scripts/planning-race-worker.php'), 'maintenance-start', (string) $id, json_encode(['expected_revision' => 2, 'reason' => 'Concurrent material issue'])],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                $ready = json_decode(fgets($pipes[1]), true, 512, JSON_THROW_ON_ERROR); $this->assertTrue($ready['ready']); $this->assertSame(DB::connection()->getDatabaseName(), $ready['database']);
                fclose($pipes[0]); $workers[] = compact('process', 'pipes', 'ready');
            }
            $connections = array_column(array_column($workers, 'ready'), 'connection_id'); $deadline = microtime(true) + 5;
            do {
                $waiters = (int) DB::selectOne('SELECT COUNT(DISTINCT t.PROCESSLIST_ID) AS n FROM performance_schema.data_lock_waits w JOIN performance_schema.threads t ON t.THREAD_ID=w.REQUESTING_THREAD_ID WHERE t.PROCESSLIST_ID IN (?,?)', $connections)->n;
                if ($waiters === 2) { break; } usleep(20000);
            } while (microtime(true) < $deadline);
            DB::commit(); $results = [];
            foreach ($workers as $w) {
                $result = json_decode(stream_get_contents($w['pipes'][1]), true, 512, JSON_THROW_ON_ERROR); $stderr = stream_get_contents($w['pipes'][2]);
                fclose($w['pipes'][1]); fclose($w['pipes'][2]); $this->assertSame(0, proc_close($w['process']), $stderr.json_encode($result)); $results[] = $result;
            }
            $this->assertSame(2, $waiters); $this->assertSame(['committed', 'committed'], array_column($results, 'result'));
            $states = DB::table('maintenance_orders')->whereIn('id', $orders)->pluck('status')->all(); $this->assertEqualsCanonicalizing(['IN_PROGRESS', 'WAITING_MATERIALS'], $states);
            $this->assertSame(0, (int) DB::table('maintenance_materials')->where('id', $m->id)->value('quantity'));
            $this->assertSame(1, DB::table('maintenance_stock_moves')->where('material_id', $m->id)->whereNotNull('order_id')->count());
            $this->assertSame(1, DB::table('maintenance_order_materials')->where('material_id', $m->id)->where('consumed', true)->count());
            $dir = getenv('TEST_EVIDENCE_DIR') ?: base_path('docs/testing/evidence/2026-10-18-maintenance');
            file_put_contents($dir.'/maintenance-stock-race.json', json_encode(['test_id' => 'IT-MAINTENANCE-STOCK-RACE-01', 'database' => DB::connection()->getDatabaseName(),
                'engine' => DB::selectOne('SELECT VERSION() AS v')->v, 'observed_lock_waiters' => $waiters, 'connections' => $connections, 'results' => $results, 'states' => $states, 'stock' => 0, 'executed_at_utc' => gmdate('c')], JSON_PRETTY_PRINT));
        } finally {
            if (DB::transactionLevel() > 0) { DB::rollBack(); }
            foreach ($workers as $w) { if (is_resource($w['process'])) { proc_terminate($w['process']); proc_close($w['process']); } }
        }
    }
}
