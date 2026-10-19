<?php

namespace Tests\Feature;

use App\Models\OperationalFlight;
use App\Models\User;
use App\Services\Core\AircraftService;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MySqlBookingRaceTest extends TestCase
{
    public function test_two_connections_compete_for_last_seat_without_overbooking(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('IT-BOOK-RACE-01 requires real MySQL and separate connections; SQLite is not locking evidence.');
        }
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        RefreshDatabaseState::$migrated = false;
        $this->seed();
        $this->assertSame('+00:00', DB::selectOne('SELECT @@session.time_zone AS tz')->tz);
        $source = OperationalFlight::where('flight_number', 'DM102')->firstOrFail();
        $aircraft = app(AircraftService::class)->createAircraft(['tail_number' => 'VN-BOOK-RACE',
            'model_id' => $source->aircraft->model_id, 'manufacture_year' => 2020]);
        $configuration = app(AircraftService::class)->configure($aircraft->tail_number, [
            'expected_revision' => 1, 'name' => 'One-seat inventory race fixture', 'reason' => 'Synthetic test; physical configuration with exactly one sellable seat',
            'sections' => [['from' => 1, 'to' => 1, 'columns' => 'A', 'cabin' => 'ECONOMY']], 'blocked_seats' => [],
        ]);
        // Inventory consumes the flight's immutable capacity, not subsequent model catalogue edits.
        $flight = OperationalFlight::create(['flight_number' => 'BK-RACE', 'flight_date' => $source->flight_date->toDateString(),
            'route_id' => $source->route_id, 'aircraft_tail' => $aircraft->tail_number, 'scheduled_dep_utc' => $source->scheduled_dep_utc,
            'scheduled_arr_utc' => $source->scheduled_arr_utc, 'flight_status' => 'SCHEDULED',
            'aircraft_configuration_id' => $configuration->id, 'seat_capacity_snapshot' => $configuration->sellable_capacity]);
        $this->assertSame(1, $configuration->sellable_capacity);
        $this->assertSame(1, $flight->seat_capacity_snapshot);
        $this->assertGreaterThan(1, $aircraft->model->seat_capacity);
        $customer = User::where('username', 'nguyen_van_a')->firstOrFail();
        $workers = [];
        DB::beginTransaction();
        OperationalFlight::whereKey($flight->id)->lockForUpdate()->firstOrFail();
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = proc_open([PHP_BINARY, base_path('scripts/booking-race-worker.php'), $flight->id, $customer->id, (string) $i], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                $ready = json_decode(fgets($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
                $this->assertTrue($ready['ready']);
                $this->assertSame(DB::connection()->getDatabaseName(), $ready['database']);
                $workers[] = compact('process', 'pipes', 'ready');
            }
            foreach ($workers as $worker) {
                fclose($worker['pipes'][0]);
            }
            // Demonstrate both workers actually contend for the same locked InnoDB row.
            $ids = array_map(fn ($worker) => (int) $worker['ready']['connection_id'], $workers);
            $deadline = microtime(true) + 5;
            do {
                $waiting = DB::selectOne('SELECT COUNT(DISTINCT t.PROCESSLIST_ID) AS n FROM performance_schema.data_lock_waits w JOIN performance_schema.threads t ON t.THREAD_ID = w.REQUESTING_THREAD_ID WHERE t.PROCESSLIST_ID IN (?, ?)', $ids)->n;
                if ((int) $waiting === 2) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            DB::commit();
            $results = [];
            foreach ($workers as $worker) {
                $results[] = json_decode(stream_get_contents($worker['pipes'][1]), true, 512, JSON_THROW_ON_ERROR);
                $errors = stream_get_contents($worker['pipes'][2]);
                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);
                $this->assertSame(0, proc_close($worker['process']), $errors.json_encode(end($results)));
            }
            $this->assertEquals(2, $waiting, 'Both separate MySQL sessions must be observed waiting on the same row. Results: '.json_encode($results));
            $this->assertEqualsCanonicalizing(['created', 'rejected'], array_column($results, 'result'));
            $this->assertDatabaseCount('order_bookings', 1);
            $this->assertDatabaseCount('order_items', 1);
            $this->assertEquals(1, DB::table('order_items')->where('flight_id', $flight->id)->where('item_type', 'SEAT')->count());
            file_put_contents((getenv('TEST_EVIDENCE_DIR') ?: base_path('docs/testing/evidence/2026-10-18')).'/booking-race.json', json_encode(['test_id' => 'IT-BOOK-RACE-01', 'database' => DB::connection()->getDatabaseName(), 'engine' => DB::selectOne('SELECT VERSION() AS v')->v, 'separate_connections' => $ids, 'observed_lock_waiters' => (int) $waiting, 'capacity' => 1, 'results' => $results, 'executed_at_utc' => now('UTC')->toIso8601String()], JSON_PRETTY_PRINT));
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
