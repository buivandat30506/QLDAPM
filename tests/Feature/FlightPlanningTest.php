<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\AirportResource;
use App\Models\FlightSchedule;
use App\Models\OperationalFlight;
use App\Models\Role;
use App\Models\Route;
use App\Models\User;
use App\Services\Core\ScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FlightPlanningTest extends TestCase
{
    use RefreshDatabase;

    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-18 02:30:00', 'UTC'));
        $this->seed();
        $aircraft = Aircraft::create(['tail_number' => 'VN-PLAN01', 'model_id' => Aircraft::findOrFail('VN-D104')->model_id,
            'manufacture_year' => 2020, 'total_flight_hours' => 0, 'total_flight_cycles' => 0, 'operational_status' => 'OPERATIONAL']);
        $gate = AirportResource::create(['airport_code' => 'HAN', 'resource_type' => 'GATE', 'resource_code' => 'PLAN-GATE', 'status' => 'AVAILABLE']);
        $this->payload = ['flight_number' => 'PL901', 'route_id' => Route::where('route_code', 'HAN-DAD')->value('id'), 'model_id' => $aircraft->model_id,
            'aircraft_tail' => $aircraft->tail_number, 'resource_id' => $gate->id, 'departure_time_utc' => '12:00:00', 'arrival_time_utc' => '14:00:00',
            'effective_from' => '2026-11-09', 'effective_to' => '2026-11-09', 'days_of_week' => [1], 'season_code' => 'W26', 'is_active' => true];
        $this->actingAs(User::where('username', 'ops_demo')->firstOrFail());
    }

    private function create(array $overrides = []): string
    {
        return $this->postJson('/api/v1/core/schedules', array_replace($this->payload, $overrides))->assertCreated()->json('data.id');
    }

    private function publish(string $id): void
    {
        foreach (['submit', 'approve', 'publish'] as $action) { $this->postJson('/api/v1/core/schedules/'.$id.'/'.$action)->assertOk(); }
    }

    public function test_route_season_restrictions_validation_and_audit(): void
    {
        $this->actingAs(User::where('username', 'data_demo')->firstOrFail());
        $payload = ['origin_airport_code' => 'HAN', 'dest_airport_code' => 'PQC', 'distance_km' => 1250, 'standard_duration_minutes' => 130,
            'operating_from' => '2026-11-01', 'operating_to' => '2027-03-31', 'allowed_model_ids' => [$this->payload['model_id']],
            'blocked_weekdays' => [7], 'restriction_notes' => 'Không khai thác chủ nhật'];
        $id = $this->postJson('/api/v1/core/routes', $payload)->assertCreated()->assertJsonPath('data.route_code', 'HAN-PQC')->json('data.id');
        $this->postJson('/api/v1/core/routes', array_replace($payload, ['origin_airport_code' => 'ZZZ']))->assertUnprocessable()->assertJsonValidationErrors('origin_airport_code');
        $this->postJson('/api/v1/core/routes', array_replace($payload, ['distance_km' => -1]))->assertUnprocessable()->assertJsonValidationErrors('distance_km');
        $this->postJson('/api/v1/core/routes', $payload)->assertUnprocessable();
        $this->putJson('/api/v1/core/routes/'.$id, ['operating_to' => '2025-01-01'])->assertUnprocessable();
        $this->putJson('/api/v1/core/routes/'.$id, ['blocked_weekdays' => [8]])->assertUnprocessable();
        $this->putJson('/api/v1/core/routes/'.$id, ['allowed_model_ids' => [999999]])->assertUnprocessable();
        $this->putJson('/api/v1/core/routes/'.$id, ['expected_revision' => 1, 'restriction_notes' => 'Điều chỉnh hạn chế'])->assertOk()->assertJsonPath('data.revision', 2);
        $this->putJson('/api/v1/core/routes/'.$id, ['expected_revision' => 1, 'distance_km' => 2])->assertConflict();
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'routes', 'entity_id' => (string) $id, 'action' => 'UPDATE', 'actor_id' => auth()->id()]);
        $this->get('/admin/routes/'.$id)->assertOk()->assertSee('Điều chỉnh hạn chế');
        $this->deleteJson('/api/v1/core/routes/'.$id)->assertOk();
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'routes', 'entity_id' => (string) $id, 'action' => 'DELETE']);
    }

    public function test_airport_scoped_route_and_schedule_collections_objects_and_writes(): void
    {
        $id = $this->create();
        $user = User::factory()->create();
        foreach (['DATA_ADMIN', 'FLIGHT_OPS'] as $code) {
            $role = Role::where('role_code', $code)->firstOrFail(); $user->roles()->attach($role->id);
            DB::table('user_data_scopes')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => 'airport', 'scope_id' => 'HAN']);
        }
        $this->actingAs($user);
        $routes = $this->getJson('/api/v1/core/routes')->assertOk()->json('data.data');
        $this->assertNotEmpty($routes); $this->assertSame(['HAN'], array_values(array_unique(array_column($routes, 'origin_airport_code'))));
        $this->getJson('/api/v1/core/schedules/'.$id)->assertOk();
        $this->get('/admin/schedules')->assertOk()->assertSee('PL901')->assertSee('Lập và công bố lịch');
        $foreignRoute = Route::where('route_code', 'SGN-HAN')->firstOrFail();
        $this->putJson('/api/v1/core/routes/'.$foreignRoute->id, ['distance_km' => -1])->assertForbidden();
        $foreignSchedule = FlightSchedule::where('route_id', $foreignRoute->id)->firstOrFail();
        $this->getJson('/api/v1/core/schedules/'.$foreignSchedule->id)->assertForbidden();
        $this->putJson('/api/v1/core/schedules/'.$foreignSchedule->id, [])->assertForbidden();
        $this->getJson('/api/v1/core/versions?entity_type=flight_schedules&entity_id='.$foreignSchedule->id)->assertForbidden();
        $this->postJson('/api/v1/core/schedules', array_replace($this->payload, ['route_id' => $foreignRoute->id]))->assertForbidden();
    }

    public function test_full_schedule_workflow_records_actors_checks_and_immutable_snapshot(): void
    {
        $id = $this->create();
        $this->postJson('/api/v1/core/schedules/'.$id.'/check')->assertOk()->assertJsonPath('data.is_valid', true)->assertJsonPath('data.occurrence_count', 1);
        $this->assertDatabaseHas('flight_schedules', ['id' => $id, 'status' => 'DRAFT']);
        $this->publish($id);
        $schedule = FlightSchedule::findOrFail($id);
        foreach (['created_by', 'submitted_by', 'approved_by', 'published_by'] as $field) { $this->assertSame(auth()->id(), $schedule->$field); }
        $this->assertSame(1, $schedule->version); $this->assertNull($schedule->open_series_id);
        $snapshot = json_decode(DB::table('entity_versions')->where('entity_id', $id)->value('snapshot'), true);
        $this->assertSame(auth()->id(), $snapshot['approved_by']); $this->assertSame([], $snapshot['impacts']);
        $this->putJson('/api/v1/core/schedules/'.$id, ['flight_number' => 'OTHER'])->assertUnprocessable();
        $this->get('/admin/schedules/'.$id)->assertOk()->assertSee('Các bản công bố được lưu')->assertSee('Đã công bố');
    }

    public function test_revision_keeps_published_schedule_until_new_publication(): void
    {
        $id = $this->create(); $this->publish($id);
        $flightId = $this->postJson('/api/v1/core/flights', ['schedule_id' => $id, 'flight_date' => '2026-11-09'])->assertCreated()->json('data.id');
        $snapshot = DB::table('entity_versions')->where('entity_id', $id)->value('snapshot');
        $draft = $this->postJson('/api/v1/core/schedules/'.$id.'/revise', ['reason' => 'Điều chỉnh tên mùa lịch'])->assertOk()->json('data.id');
        $this->assertNotSame($id, $draft);
        $this->assertDatabaseHas('flight_schedules', ['id' => $id, 'status' => 'PUBLISHED', 'is_active' => 1]);
        $this->postJson('/api/v1/core/schedules/'.$id.'/revise', ['reason' => 'Bản trùng không hợp lệ'])->assertUnprocessable();
        $this->putJson('/api/v1/core/schedules/'.$draft, ['route_id' => Route::where('route_code', 'SGN-HAN')->value('id')])->assertUnprocessable()->assertJsonValidationErrors('route_id');
        $this->putJson('/api/v1/core/schedules/'.$draft, ['season_code' => 'W26-REV', 'expected_revision' => 1])->assertOk();
        $this->publish($draft);
        $this->assertDatabaseHas('flight_schedules', ['id' => $id, 'status' => 'SUPERSEDED', 'is_active' => 0]);
        $this->assertDatabaseHas('flight_schedules', ['id' => $draft, 'status' => 'PUBLISHED', 'version' => 2, 'change_reason' => 'Điều chỉnh tên mùa lịch']);
        $this->assertSame($snapshot, DB::table('entity_versions')->where('entity_id', $id)->value('snapshot'));
        $this->assertDatabaseHas('operational_flights', ['id' => $flightId, 'schedule_id' => $id, 'flight_status' => 'SCHEDULED']);
        $newSnapshot = json_decode(DB::table('entity_versions')->where('entity_id', $draft)->value('snapshot'), true);
        $this->assertSame($flightId, $newSnapshot['impacts'][0]['flight_id']);
        $this->assertFalse($newSnapshot['impacts'][0]['requires_resolution']);
        $this->get('/admin/schedules/'.$draft)->assertOk()->assertSee('W26-REV')->assertSee('Phiên bản 2');
    }

    public function test_stale_edits_and_transitions_reject_without_overwriting(): void
    {
        $id = $this->create();
        $this->putJson('/api/v1/core/schedules/'.$id, ['expected_revision' => 1, 'season_code' => 'W26-A'])->assertOk()->assertJsonPath('data.revision', 2);
        $this->putJson('/api/v1/core/schedules/'.$id, ['expected_revision' => 1, 'season_code' => 'STALE'])->assertConflict();
        $this->postJson('/api/v1/core/schedules/'.$id.'/submit', ['expected_revision' => 1])->assertConflict();
        $this->postJson('/api/v1/core/schedules/'.$id.'/submit', ['expected_revision' => ['bad']])->assertUnprocessable();
        $this->postJson('/api/v1/core/schedules', array_replace($this->payload, ['route_id' => ['bad']]))->assertUnprocessable();
        $this->assertDatabaseHas('flight_schedules', ['id' => $id, 'status' => 'DRAFT', 'season_code' => 'W26-A']);
    }

    public function test_reject_and_cancel_require_reason_and_keep_audit(): void
    {
        $id = $this->create();
        $this->postJson('/api/v1/core/schedules/'.$id.'/submit')->assertOk();
        $this->postJson('/api/v1/core/schedules/'.$id.'/reject')->assertUnprocessable();
        $this->postJson('/api/v1/core/schedules/'.$id.'/reject', ['reason' => 'Cần kiểm tra lại phương án'])->assertOk()->assertJsonPath('data.status', 'DRAFT');
        $this->postJson('/api/v1/core/schedules/'.$id.'/cancel', ['reason' => 'Dừng phương án chưa khai thác'])->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->postJson('/api/v1/core/schedules/'.$id.'/submit')->assertUnprocessable();
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'flight_schedules', 'entity_id' => $id, 'action' => 'CANCEL', 'reason' => 'Dừng phương án chưa khai thác']);
    }

    public function test_weekly_recurrence_exclusions_and_extra_only_dates(): void
    {
        $id = $this->create(['effective_to' => '2026-11-23', 'excluded_dates' => ['2026-11-16'], 'extra_dates' => ['2026-11-17']]);
        $this->postJson('/api/v1/core/schedules/'.$id.'/check')->assertOk()->assertJsonPath('data.occurrence_count', 3)->assertJsonPath('data.is_valid', true);
        $this->publish($id);
        $this->postJson('/api/v1/core/flights', ['schedule_id' => $id, 'flight_date' => '2026-11-16'])->assertUnprocessable();
        $this->postJson('/api/v1/core/flights', ['schedule_id' => $id, 'flight_date' => '2026-11-17'])->assertCreated();
        $extra = $this->create(['flight_number' => 'PL902', 'effective_from' => '2026-12-01', 'effective_to' => '2026-12-04', 'days_of_week' => [], 'extra_dates' => ['2026-12-03']]);
        $this->postJson('/api/v1/core/schedules/'.$extra.'/check')->assertOk()->assertJsonPath('data.occurrence_count', 1);
        $this->putJson('/api/v1/core/schedules/'.$extra, ['extra_dates' => ['2026-12-05']])->assertUnprocessable();
        $this->putJson('/api/v1/core/schedules/'.$extra, ['excluded_dates' => ['2026-12-03']])->assertUnprocessable();
    }

    public function test_missing_resources_and_multiple_conflicts_return_persisted_diagnostics(): void
    {
        $missing = $this->create(['aircraft_tail' => null]);
        $this->postJson('/api/v1/core/schedules/'.$missing.'/check')->assertOk()->assertJsonPath('data.is_valid', false)->assertJsonPath('data.occurrence_count', 0);
        $id = $this->create(['flight_number' => 'PL903']);
        Airport::whereKey('HAN')->update(['status' => 'INACTIVE']);
        Aircraft::whereKey($this->payload['aircraft_tail'])->update(['operational_status' => 'GROUNDED']);
        AirportResource::whereKey($this->payload['resource_id'])->update(['status' => 'MAINTENANCE']);
        $response = $this->postJson('/api/v1/core/schedules/'.$id.'/check')->assertOk()->assertJsonPath('data.is_valid', false);
        foreach (['route_id', 'aircraft_tail', 'resource_id'] as $field) { $this->assertArrayHasKey($field, $response->json('data.errors')); }
        $this->postJson('/api/v1/core/schedules/'.$id.'/submit')->assertUnprocessable();
        $this->assertDatabaseHas('flight_schedules', ['id' => $id, 'status' => 'DRAFT']);
        $this->assertSame(2, DB::table('flight_schedule_checks')->where('schedule_id', $id)->where('is_valid', false)->count());
    }

    public function test_publish_rechecks_route_season_restrictions_and_does_not_advance(): void
    {
        $id = $this->create();
        foreach (['submit', 'approve'] as $action) { $this->postJson('/api/v1/core/schedules/'.$id.'/'.$action)->assertOk(); }
        $this->actingAs(User::where('username', 'data_demo')->firstOrFail());
        $this->putJson('/api/v1/core/routes/'.$this->payload['route_id'], ['operating_from' => '2026-12-01', 'operating_to' => '2027-01-01', 'blocked_weekdays' => [1]])->assertOk();
        $this->actingAs(User::where('username', 'ops_demo')->firstOrFail());
        $this->postJson('/api/v1/core/schedules/'.$id.'/publish')->assertUnprocessable()->assertJsonValidationErrors('route_id');
        $this->assertDatabaseHas('flight_schedules', ['id' => $id, 'status' => 'APPROVED', 'version' => 0]);
        $this->assertDatabaseHas('flight_schedule_checks', ['schedule_id' => $id, 'is_valid' => false]);
    }

    public function test_airport_hours_use_airport_timezone_and_unknown_hours_fail_closed(): void
    {
        $id = $this->create();
        Airport::whereKey('HAN')->update(['operating_hours' => '06:00 - 18:00']); // departure 19:00 local
        $this->postJson('/api/v1/core/schedules/'.$id.'/check')->assertOk()->assertJsonPath('data.is_valid', false);
        Airport::whereKey('HAN')->update(['operating_hours' => '18:00-06:00']);
        $this->postJson('/api/v1/core/schedules/'.$id.'/check')->assertOk()->assertJsonPath('data.is_valid', true);
        Airport::whereKey('HAN')->update(['operating_hours' => 'Không rõ']);
        $this->postJson('/api/v1/core/schedules/'.$id.'/submit')->assertUnprocessable()->assertJsonValidationErrors('route_id');
    }

    public function test_changed_revision_lists_impacted_flights_and_blocks_silent_replan(): void
    {
        $id = $this->create(); $this->publish($id);
        $flightId = $this->postJson('/api/v1/core/flights', ['schedule_id' => $id, 'flight_date' => '2026-11-09'])->assertCreated()->json('data.id');
        $draft = $this->postJson('/api/v1/core/schedules/'.$id.'/revise', ['reason' => 'Thay giờ khởi hành sau công bố'])->assertOk()->json('data.id');
        $this->putJson('/api/v1/core/schedules/'.$draft, ['departure_time_utc' => '13:00:00', 'arrival_time_utc' => '15:00:00'])->assertOk();
        $this->postJson('/api/v1/core/schedules/'.$draft.'/check')->assertOk()->assertJsonPath('data.is_valid', false)->assertJsonPath('data.impacts.0.flight_id', $flightId)->assertJsonPath('data.impacts.0.requires_resolution', true);
        $this->postJson('/api/v1/core/schedules/'.$draft.'/submit')->assertUnprocessable()->assertJsonValidationErrors('impacts');
        $this->assertSame('2026-11-09 12:00:00', OperationalFlight::findOrFail($flightId)->scheduled_dep_utc->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('flight_schedules', ['id' => $id, 'status' => 'PUBLISHED']);
        $this->postJson('/api/v1/core/events', ['schema' => 'flight-event.v1', 'source' => 'DEMO', 'external_id' => 'PLAN-IMPACT-'.$flightId,
            'flight_id' => $flightId, 'flight_status' => 'DELAYED', 'reason' => 'Lịch nguồn thay đổi, cần điều hành xác nhận',
            'sequence' => 1, 'source_time_utc' => '2026-10-18T02:30:00Z', 'source_timezone' => 'UTC'])->assertStatus(202)->assertJsonPath('data.status', 'ALERT');
        $this->postJson('/api/v1/core/flights/'.$flightId.'/resolve', ['action' => 'DELAY', 'reason' => 'Điều hành xác nhận đổi giờ cho phiên bản lịch mới',
            'scheduled_dep_utc' => '2026-11-09 13:00:00', 'scheduled_arr_utc' => '2026-11-09 15:00:00'])->assertOk();
        $this->postJson('/api/v1/core/schedules/'.$draft.'/check')->assertOk()->assertJsonPath('data.is_valid', true)->assertJsonPath('data.impacts.0.requires_resolution', false);
        $this->publish($draft);
        $this->assertDatabaseHas('flight_operation_history', ['flight_id' => $flightId, 'action' => 'RECOVERY_DELAY', 'reason' => 'Điều hành xác nhận đổi giờ cho phiên bản lịch mới']);
    }

    public function test_local_schedule_materializes_previous_utc_date_and_preserves_source_date(): void
    {
        $id = $this->create(['departure_time_utc' => null, 'arrival_time_utc' => null, 'departure_time_local' => '01:00:00',
            'arrival_time_local' => '03:00:00', 'source_timezone' => 'Asia/Ho_Chi_Minh', 'arrival_day_offset' => 0]);
        $this->publish($id);
        $this->postJson('/api/v1/core/flights', ['schedule_id' => $id, 'service_date' => '2026-11-09'])->assertCreated()
            ->assertJsonPath('data.flight_date', '2026-11-08');
        $flight = OperationalFlight::where('schedule_id', $id)->firstOrFail();
        $this->assertSame('2026-11-09', $flight->service_date);
        $this->assertSame('2026-11-08 18:00:00', $flight->scheduled_dep_utc->format('Y-m-d H:i:s'));
    }

    public function test_repeated_publication_and_cancellation_do_not_duplicate_snapshots_or_cancel_flights(): void
    {
        $id = $this->create(); $this->publish($id);
        $flightId = $this->postJson('/api/v1/core/flights', ['schedule_id' => $id, 'flight_date' => '2026-11-09'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/core/schedules/'.$id.'/publish')->assertUnprocessable();
        $this->assertSame(1, DB::table('entity_versions')->where('entity_id', $id)->count());
        $this->postJson('/api/v1/core/schedules/'.$id.'/cancel', ['reason' => 'Dừng tạo chuyến mới cho mùa này'])->assertOk();
        $this->assertDatabaseHas('operational_flights', ['id' => $flightId, 'flight_status' => 'SCHEDULED']);
        $this->postJson('/api/v1/core/flights', ['schedule_id' => $id, 'flight_date' => '2026-11-09'])->assertUnprocessable();
    }

    public function test_season_horizon_no_occurrences_and_restricted_model_are_rejected(): void
    {
        $id = $this->create(['effective_to' => '2028-11-09']);
        $this->postJson('/api/v1/core/schedules/'.$id.'/submit')->assertUnprocessable()->assertJsonValidationErrors('effective_to');
        $empty = $this->create(['flight_number' => 'PL904', 'days_of_week' => [2]]);
        $this->postJson('/api/v1/core/schedules/'.$empty.'/submit')->assertUnprocessable()->assertJsonValidationErrors('days_of_week');
        Route::findOrFail($this->payload['route_id'])->update(['allowed_model_ids' => [999999]]);
        $valid = $this->create(['flight_number' => 'PL905']);
        $this->postJson('/api/v1/core/schedules/'.$valid.'/submit')->assertUnprocessable()->assertJsonValidationErrors('model_id');
    }

    public function test_mysql_check_constraints_reject_invalid_dates_and_arrival_offset(): void
    {
        if (DB::getDriverName() !== 'mysql') { $this->markTestSkipped('MySQL CHECK constraint verification.'); }
        $id = $this->create();
        foreach ([fn () => DB::table('routes')->where('id', $this->payload['route_id'])->update(['operating_from' => '2026-12-01', 'operating_to' => null]),
            fn () => DB::table('flight_schedules')->where('id', $id)->update(['arrival_day_offset' => 3])] as $mutation) {
            try { $mutation(); $this->fail('Invalid direct SQL must fail at the MySQL constraint.'); }
            catch (\Illuminate\Database\QueryException $error) { $this->assertSame(3819, $error->errorInfo[1]); }
        }
    }
}
