<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\AircraftConfiguration;
use App\Models\AirportResource;
use App\Models\OperationalFlight;
use App\Models\Role;
use App\Models\Route;
use App\Models\User;
use App\Services\Core\ScheduleService;
use App\Services\Passenger\BookingService;
use App\Services\Passenger\CheckInService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FleetManagementTest extends TestCase
{
    use RefreshDatabase;

    private Aircraft $aircraft;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-18 02:30:00', 'UTC'));
        $this->seed();
        $this->actingAs(User::where('username', 'fleet_demo')->firstOrFail());
        $id = $this->postJson('/api/v1/core/aircrafts', ['tail_number' => 'vn-fleet1', 'model_id' => Aircraft::findOrFail('VN-D104')->model_id,
            'manufacture_year' => 2020, 'base_airport_code' => 'HAN'])->assertCreated()->assertJsonPath('data.tail_number', 'VN-FLEET1')->json('data.tail_number');
        $this->aircraft = Aircraft::findOrFail($id);
    }

    private function configuration(array $overrides = []): array
    {
        return array_replace(['expected_revision' => $this->aircraft->fresh()->revision, 'name' => 'Cấu hình hồ sơ F-01', 'reason' => 'Tiếp nhận cấu hình có xác nhận',
            'sections' => [['from' => 1, 'to' => 2, 'columns' => 'AB', 'cabin' => 'ECONOMY']], 'blocked_seats' => ['2B']], $overrides);
    }

    private function flight(string $number = 'FL901', string $status = 'ARRIVED'): OperationalFlight
    {
        return OperationalFlight::create(['flight_number' => $number, 'flight_date' => '2026-10-18', 'route_id' => Route::where('route_code', 'HAN-DAD')->value('id'),
            'aircraft_tail' => $this->aircraft->tail_number, 'flight_status' => $status, 'scheduled_dep_utc' => '2026-10-18 04:30:00',
            'scheduled_arr_utc' => '2026-10-18 06:30:00', 'actual_dep_utc' => $status === 'ARRIVED' ? '2026-10-17 04:30:00' : null,
            'actual_arr_utc' => $status === 'ARRIVED' ? '2026-10-17 06:30:00' : null,
            'aircraft_configuration_id' => $this->aircraft->fresh()->current_configuration_id,
            'seat_capacity_snapshot' => $this->aircraft->fresh()->configuration?->sellable_capacity]);
    }

    public function test_aircraft_crud_normalization_revision_and_noneditable_usage(): void
    {
        $url = '/api/v1/core/aircrafts/VN-FLEET1';
        $this->postJson('/api/v1/core/aircrafts', ['tail_number' => ' vn-fleet1 ', 'model_id' => $this->aircraft->model_id, 'manufacture_year' => 2020])->assertUnprocessable();
        $this->putJson($url, ['manufacture_year' => 2030])->assertUnprocessable();
        $this->putJson($url, ['total_flight_hours' => 2])->assertUnprocessable();
        $this->putJson($url, ['expected_revision' => 1, 'manufacture_year' => 2021])->assertOk()->assertJsonPath('data.revision', 2);
        $this->putJson($url, ['expected_revision' => 1, 'manufacture_year' => 2022])->assertConflict();
        $this->putJson($url, ['model_id' => null])->assertUnprocessable();
        $this->putJson($url, ['operational_status' => 'GROUNDED'])->assertUnprocessable();
        $this->putJson($url, ['operational_status' => 'GROUNDED', 'reason' => 'Kiểm tra bất thường'])->assertOk();
        $this->putJson($url, ['operational_status' => 'OPERATIONAL', 'reason' => 'Không được bỏ kiểm tra kỹ thuật'])->assertUnprocessable();
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'aircrafts', 'entity_id' => 'VN-FLEET1', 'action' => 'PROFILE_CHANGE', 'reason' => 'Kiểm tra bất thường']);
        $this->deleteJson($url)->assertOk();
        $this->assertDatabaseMissing('aircrafts', ['tail_number' => 'VN-FLEET1']);
    }

    public function test_opening_balances_require_provenance_and_overdue_aircraft_is_unavailable(): void
    {
        $data = ['tail_number' => 'VN-OPEN', 'model_id' => $this->aircraft->model_id, 'manufacture_year' => 2018, 'total_flight_hours' => '3000.00', 'total_flight_cycles' => 10];
        $this->postJson('/api/v1/core/aircrafts', $data)->assertUnprocessable()->assertJsonValidationErrors('opening_reason');
        $this->postJson('/api/v1/core/aircrafts', $data + ['opening_reason' => 'Hồ sơ bàn giao số 12'])->assertCreated()->assertJsonPath('data.operational_status', 'MAINTENANCE');
        $this->getJson('/api/v1/core/aircrafts/VN-OPEN')->assertOk()->assertJsonPath('data.reconciliation.matched', true)->assertJsonPath('data.reconciliation.expected_hours', '3000.00');
        $this->postJson('/api/v1/core/aircrafts', array_replace($data, ['tail_number' => 'VN-HACK', 'total_flight_hours' => -1]))->assertUnprocessable();
    }

    public function test_configuration_versions_are_immutable_with_actual_seats_and_actor(): void
    {
        $url = '/api/v1/core/aircrafts/VN-FLEET1/configurations';
        $id = $this->postJson($url, $this->configuration())->assertCreated()->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.seat_count', 4)->assertJsonPath('data.sellable_capacity', 3)->json('data.id');
        $old = AircraftConfiguration::findOrFail($id)->toArray();
        $this->postJson($url, $this->configuration(['name' => 'Bản 2', 'blocked_seats' => []]))->assertCreated()->assertJsonPath('data.version', 2)->assertJsonPath('data.sellable_capacity', 4);
        $this->assertSame($old, AircraftConfiguration::findOrFail($id)->toArray());
        $this->assertSame(auth()->id(), $old['created_by']);
        $this->getJson($url)->assertOk()->assertJsonPath('data.total', 2);
        $this->deleteJson('/api/v1/core/aircrafts/VN-FLEET1')->assertUnprocessable();
        $this->get('/admin/aircrafts/VN-FLEET1')->assertOk()->assertSee('Sơ đồ ghế hiện tại')->assertSee('Lịch sử cấu hình được lưu nguyên trạng')->assertSee('Bản 2');
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'aircraft_configurations', 'entity_id' => (string) $id, 'action' => 'PUBLISHED']);
    }

    public function test_invalid_or_stale_configuration_never_replaces_published_version(): void
    {
        $url = '/api/v1/core/aircrafts/VN-FLEET1/configurations';
        $this->postJson($url, $this->configuration())->assertCreated();
        $current = $this->aircraft->fresh()->current_configuration_id;
        $this->postJson($url, $this->configuration(['expected_revision' => 1]))->assertConflict();
        foreach ([
            ['sections' => [['from' => 2, 'to' => 1, 'columns' => 'AB', 'cabin' => 'ECONOMY']]],
            ['sections' => [['from' => 1, 'to' => 2, 'columns' => 'AA', 'cabin' => 'ECONOMY']]],
            ['sections' => [['from' => 1, 'to' => 200, 'columns' => 'ABCDEF', 'cabin' => 'ECONOMY']]],
            ['sections' => [['from' => 1, 'to' => 2, 'columns' => 'AB', 'cabin' => 'INVALID']]],
            ['sections' => [['from' => 1, 'to' => 2, 'columns' => 'AB', 'cabin' => 'ECONOMY'], ['from' => 2, 'to' => 3, 'columns' => 'AB', 'cabin' => 'BUSINESS']]],
            ['blocked_seats' => ['99A']], ['blocked_seats' => ['1A', '1B', '2A', '2B']], ['sections' => ['malformed']],
        ] as $invalid) {
            $this->postJson($url, $this->configuration($invalid))->assertUnprocessable();
        }
        $this->assertSame($current, $this->aircraft->fresh()->current_configuration_id);
        $this->assertDatabaseCount('aircraft_configurations', 1);
    }

    public function test_materialized_flight_pins_capacity_and_blocks_reconfiguration(): void
    {
        $this->postJson('/api/v1/core/aircrafts/VN-FLEET1/configurations', $this->configuration())->assertCreated();
        $this->actingAs(User::where('username', 'ops_demo')->firstOrFail());
        $gate = AirportResource::create(['airport_code' => 'HAN', 'resource_type' => 'GATE', 'resource_code' => 'FLEET-G', 'status' => 'AVAILABLE']);
        $s = app(ScheduleService::class)->save(['flight_number' => 'FL902', 'route_id' => Route::where('route_code', 'HAN-DAD')->value('id'), 'model_id' => $this->aircraft->model_id,
            'aircraft_tail' => 'VN-FLEET1', 'resource_id' => $gate->id, 'departure_time_utc' => '12:00:00', 'arrival_time_utc' => '14:00:00',
            'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'effective_from' => '2026-11-01', 'effective_to' => '2026-11-01']);
        foreach (['submit', 'approve', 'publish'] as $action) {
            app(ScheduleService::class)->transition($s->id, $action);
        }
        $id = $this->postJson('/api/v1/core/flights', ['schedule_id' => $s->id, 'flight_date' => '2026-11-01'])->assertCreated()
            ->assertJsonPath('data.seat_capacity_snapshot', 3)->json('data.id');
        $this->actingAs(User::where('username', 'fleet_demo')->firstOrFail());
        $this->postJson('/api/v1/core/aircrafts/VN-FLEET1/configurations', $this->configuration(['blocked_seats' => []]))->assertUnprocessable();
        $this->assertSame($this->aircraft->fresh()->current_configuration_id, OperationalFlight::findOrFail($id)->aircraft_configuration_id);
        $this->assertDatabaseCount('aircraft_configurations', 1);
    }

    public function test_booking_and_checkin_use_sellable_seats_and_reject_missing_or_blocked_seat(): void
    {
        $this->postJson('/api/v1/core/aircrafts/VN-FLEET1/configurations', $this->configuration())->assertCreated();
        $flight = $this->flight('FL903', 'SCHEDULED');
        $customer = User::factory()->create();
        $passenger = ['full_name' => 'Hành khách kiểm thử', 'id_card_passport' => 'FLEET-P01', 'dob' => '1990-01-01', 'gender' => 'MALE', 'seat_price' => 0, 'baggage_price' => 999999999];
        $order = app(BookingService::class)->createBooking(['flight_id' => $flight->id, 'customer_user_id' => $customer->id, 'passengers' => [$passenger]]);
        $this->assertSame('1500000.00', $order->orderItems->firstWhere('item_type', 'SEAT')->amount);
        $this->assertSame('1500000.00', $order->total_amount);
        $this->assertSame('local-v1', $order->pricing_profile);
        $this->assertSame('VND', $order->currency);
        $this->assertSame(1, $order->orderItems->where('item_type', 'SEAT')->count());
        $this->assertSame(0, $order->orderItems->where('item_type', 'BAGGAGE_EXTRA')->count());
        // Confirmed ticket fixture: payment is outside this UC and remains gated in G3.
        $order->update(['order_status' => 'CONFIRMED']);
        $id = $order->orderItems()->firstOrFail()->passenger_id;
        foreach (['2B', '99A'] as $seat) {
            try {
                app(CheckInService::class)->processCheckIn($flight->id, $id, $seat);
                $this->fail('Invalid seat accepted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('seat_number', $e->errors());
            }
        }
        app(CheckInService::class)->processCheckIn($flight->id, $id, '1A');
        $this->assertDatabaseHas('checkin_records', ['flight_id' => $flight->id, 'seat_number' => '1A']);
        $legacy = $this->flight('FL904', 'SCHEDULED');
        $legacy->update(['aircraft_configuration_id' => null]);
        $order2 = app(BookingService::class)->createBooking(['flight_id' => $legacy->id, 'customer_user_id' => $customer->id, 'passengers' => [array_replace($passenger, ['id_card_passport' => 'FLEET-P02'])]]);
        $order2->update(['order_status' => 'CONFIRMED']);
        $id2 = $order2->orderItems()->firstOrFail()->passenger_id;
        try {
            app(CheckInService::class)->processCheckIn($legacy->id, $id2, '1A');
            $this->fail('Missing map accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('seat_number', $e->errors());
        }
        $payload = ['flight_id' => $flight->id, 'customer_user_id' => $customer->id, 'passengers' => array_map(fn ($i) => array_replace($passenger, ['id_card_passport' => 'FLEET-OVER'.$i]), range(1, 3))];
        try {
            app(BookingService::class)->createBooking($payload);
            $this->fail('Capacity exceeded');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_usage_from_completed_facts_is_idempotent_with_durable_alias_receipts(): void
    {
        $f = $this->flight();
        $url = '/api/v1/core/aircrafts/VN-FLEET1/usage';
        $payload = ['flight_id' => $f->id, 'source' => 'DEMO', 'external_id' => 'usage-1'];
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('data.hours', '2.00');
        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, array_replace($payload, ['external_id' => 'usage-alias']))->assertOk();
        $other = $this->flight('FL905');
        $this->postJson($url, array_replace($payload, ['flight_id' => $other->id, 'external_id' => 'usage-alias']))->assertConflict();
        $a = $this->aircraft->fresh();
        $this->assertSame('2.00', $a->total_flight_hours);
        $this->assertSame(1, $a->total_flight_cycles);
        $this->assertSame(1, DB::table('aircraft_usage_records')->where('aircraft_tail', $a->tail_number)->count());
        $this->assertSame(2, DB::table('aircraft_usage_receipts')->where('usage_record_id', DB::table('aircraft_usage_records')->where('flight_id', $f->id)->value('id'))->count());
        $this->getJson($url.'?source=DEMO&from=2026-10-18&to=2026-10-18')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/v1/core/aircrafts/VN-FLEET1')->assertOk()->assertJsonPath('data.reconciliation.matched', true);
        $this->get('/admin/aircrafts/VN-FLEET1?source=DEMO')->assertOk()->assertSee('usage-1')->assertSee('FL901');
    }

    public function test_usage_rejects_wrong_aircraft_unfinished_flight_and_client_totals(): void
    {
        $f = $this->flight('FL906', 'SCHEDULED');
        $payload = ['flight_id' => $f->id, 'source' => 'DEMO', 'external_id' => 'bad'];
        $url = '/api/v1/core/aircrafts/VN-FLEET1/usage';
        $this->postJson($url, $payload)->assertUnprocessable();
        $f->update(['flight_status' => 'ARRIVED', 'actual_dep_utc' => '2026-10-17 04:30:00', 'actual_arr_utc' => '2026-10-17 06:30:00']);
        $this->postJson($url, $payload + ['hours' => 999])->assertUnprocessable();
        $this->postJson('/api/v1/core/aircrafts/VN-D104/usage', $payload)->assertUnprocessable();
        $this->assertSame('0.00', $this->aircraft->fresh()->total_flight_hours);
        $this->getJson($url.'?from=invalid')->assertUnprocessable();
    }

    public function test_threshold_alert_blocks_operations_and_reconciliation_exposes_discrepancy(): void
    {
        $a = $this->aircraft;
        $a->update(['total_flight_hours' => '2999.00', 'opening_flight_hours' => '2999.00', 'total_flight_cycles' => 1499, 'opening_flight_cycles' => 1499]);
        $f = $this->flight();
        $this->postJson('/api/v1/core/aircrafts/VN-FLEET1/usage', ['flight_id' => $f->id, 'source' => 'DEMO', 'external_id' => 'threshold'])->assertOk();
        $this->assertDatabaseHas('aircrafts', ['tail_number' => $a->tail_number, 'operational_status' => 'MAINTENANCE', 'total_flight_hours' => '3001.00', 'total_flight_cycles' => 1500]);
        $this->assertDatabaseHas('aircraft_usage_alerts', ['aircraft_tail' => $a->tail_number, 'level' => 'OVERDUE']);
        $this->get('/admin/aircrafts/VN-FLEET1')->assertOk()->assertSee('Quá hạn')->assertSee('Khớp');
        DB::table('aircrafts')->where('tail_number', $a->tail_number)->update(['total_flight_cycles' => 1501]);
        $this->getJson('/api/v1/core/aircrafts/VN-FLEET1')->assertOk()->assertJsonPath('data.reconciliation.matched', false)->assertJsonPath('data.reconciliation.cycles_difference', 1);
    }

    public function test_airport_scopes_filter_collections_and_protect_objects_and_configuration_history(): void
    {
        $user = User::factory()->create();
        $role = Role::where('role_code', 'FLEET_MANAGER')->firstOrFail();
        $user->roles()->attach($role->id);
        DB::table('user_data_scopes')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => 'airport', 'scope_id' => 'HAN']);
        $this->actingAs($user);
        $rows = $this->getJson('/api/v1/core/aircrafts')->assertOk()->json('data.data');
        $this->assertCount(1, $rows);
        $this->assertSame('VN-FLEET1', $rows[0]['tail_number']);
        $this->get('/admin/aircrafts/VN-FLEET1')->assertOk();
        foreach (['', '/configurations', '/usage'] as $suffix) {
            $this->getJson('/api/v1/core/aircrafts/VN-D104'.$suffix)->assertForbidden();
        }
        $this->postJson('/api/v1/core/aircrafts/VN-D104/configurations', [])->assertForbidden();
        $this->putJson('/api/v1/core/aircrafts/VN-FLEET1', ['base_airport_code' => 'SGN'])->assertForbidden();
        $this->postJson('/api/v1/core/aircraft-models', [])->assertForbidden();
        $this->postJson('/api/v1/core/aircrafts', ['tail_number' => 'VN-SCOPE', 'base_airport_code' => 'SGN'])->assertForbidden();
        $this->actingAs(User::where('username','admin_demo')->first() ?? User::factory()->create());
        $this->getJson('/api/v1/core/aircrafts/VN-FLEET1')->assertForbidden();
    }
}
