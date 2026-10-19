<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\AirportResource;
use App\Models\OperationalFlight;
use App\Models\Role;
use App\Models\Route;
use App\Models\User;
use App\Services\Core\MaintenanceReadiness;
use App\Services\Core\MaintenanceWorkflow;
use App\Services\Core\ScheduleConflictChecker;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Aircraft $a;
    private User $tech;
    private AirportResource $stand;
    private string $url = '/api/v1/core/maintenance';

    protected function setUp(): void
    {
        parent::setUp(); $this->travelTo(Carbon::parse('2026-10-18 02:30:00', 'UTC')); $this->seed(); Storage::fake('local');
        $this->tech = User::where('username', 'tech_demo')->firstOrFail(); $this->actingAs($this->tech);
        $this->a = Aircraft::create(['tail_number' => 'VN-MX01', 'model_id' => Aircraft::findOrFail('VN-D104')->model_id, 'manufacture_year' => 2020,
            'total_flight_hours' => 100, 'total_flight_cycles' => 20, 'opening_flight_hours' => 100, 'opening_flight_cycles' => 20, 'opening_reason' => 'Bàn giao kiểm thử',
            'maintenance_base_hours' => 0, 'maintenance_base_cycles' => 0, 'base_airport_code' => 'HAN', 'operational_status' => 'OPERATIONAL']);
        $this->stand = AirportResource::create(['airport_code' => 'HAN', 'resource_type' => 'STAND', 'resource_code' => 'MX-01', 'status' => 'AVAILABLE']);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['plan_code' => 'MX-P01', 'aircraft_tail' => $this->a->tail_number, 'task' => 'Kiểm tra đầu nối', 'trigger_type' => 'DATE',
            'starts_at_utc' => '2026-10-18 01:00:00', 'ends_at_utc' => '2026-10-18 03:00:00', 'due_at_utc' => '2026-10-19 03:00:00',
            'tasks' => ['Kiểm tra đầu nối', 'Đo điện trở'], 'reason' => 'Hồ sơ kỹ thuật local MX-01'], $overrides);
    }

    private function plan(array $overrides = []): int { return $this->postJson($this->url.'/plans', $this->payload($overrides))->assertCreated()->json('data.id'); }
    private function order(int $p, array $materials = []): int
    {
        $id = $this->postJson($this->url.'/plans/'.$p.'/orders', ['order_code' => 'MX-O'.$p, 'technician_id' => $this->tech->id, 'location_id' => $this->stand->id, 'materials' => $materials])->assertCreated()->json('data.id');
        $this->act($id, 'authorize')->assertOk(); return $id;
    }
    private function act(int $o, string $action, array $data = [])
    {
        return $this->postJson($this->url.'/orders/'.$o.'/'.$action, array_replace(['expected_revision' => DB::table('maintenance_orders')->where('id', $o)->value('revision'), 'reason' => 'Kiểm tra local có trách nhiệm'], $data));
    }
    private function receipt(string $ref = 'R01', int $qty = 1)
    {
        return $this->postJson($this->url.'/materials/receipts', ['airport_code' => 'HAN', 'code' => 'BOLT', 'name' => 'Bộ đầu nối', 'unit' => 'bộ', 'quantity' => $qty, 'reference' => $ref, 'reason' => 'Phiếu bàn giao kiểm thử']);
    }
    private function complete(int $o): void
    {
        foreach (DB::table('maintenance_order_tasks')->where('order_id', $o)->get() as $t) { $this->act($o, 'task', ['task_id' => $t->id, 'result' => 'Đo kiểm đạt yêu cầu hồ sơ local', 'passed' => true])->assertOk(); }
        $this->post($this->url.'/orders/'.$o.'/evidence', ['file' => $this->proof()], ['Accept' => 'application/json'])->assertCreated();
        $this->act($o, 'complete', ['result' => 'Hoàn tất kiểm tra', 'technical_confirmed' => true])->assertOk();
    }

    private function proof(): UploadedFile
    {
        // A real one-pixel PNG; fileinfo validates its bytes, no optional GD extension needed.
        return UploadedFile::fake()->createWithContent('evidence.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aI1sAAAAASUVORK5CYII='));
    }

    public function test_plan_date_hours_cycles_condition_and_due_alerts_block_planning_synchronously(): void
    {
        $p = $this->plan(['trigger_type' => 'HOURS', 'due_hours' => '100.00']);
        $this->assertNotEmpty(app(MaintenanceReadiness::class)->issues($this->a));
        $this->assertDatabaseHas('maintenance_alerts', ['plan_id' => $p, 'kind' => 'DUE', 'resolved_at' => null]);
        $route = Route::where('route_code', 'HAN-DAD')->firstOrFail(); $gate = AirportResource::where('airport_code', 'HAN')->where('resource_type', 'GATE')->firstOrFail();
        $issues = app(ScheduleConflictChecker::class)->window($route, $this->a, $gate, Carbon::parse('2026-11-20 08:00:00'), Carbon::parse('2026-11-20 10:00:00'));
        $this->assertStringContainsString('MX-P01', implode(' ', $issues['aircraft_tail']));
        DB::table('maintenance_plans')->where('id', $p)->update(['trigger_type' => 'CYCLES', 'due_cycles' => 20]);
        $this->assertNotEmpty(app(MaintenanceReadiness::class)->issues($this->a));
        DB::table('maintenance_plans')->where('id', $p)->update(['trigger_type' => 'CONDITION', 'condition_active' => true]);
        $this->assertNotEmpty(app(MaintenanceReadiness::class)->issues($this->a));
        DB::table('maintenance_plans')->where('id', $p)->update(['trigger_type' => 'DATE', 'due_at_utc' => now()->subSecond(), 'condition_active' => false]);
        $this->assertNotEmpty(app(MaintenanceReadiness::class)->issues($this->a));
        $this->artisan('maintenance:alerts')->assertExitCode(0);
    }

    public function test_plan_revisions_history_validation_and_overdue_deferral_are_enforced(): void
    {
        $p = $this->plan(); $data = $this->payload(['expected_revision' => 1, 'ends_at_utc' => '2026-10-18 04:00:00', 'reason' => 'Điều hành đổi thời điểm']);
        $this->putJson($this->url.'/plans/'.$p, $data)->assertOk()->assertJsonPath('data.revision', 2);
        $this->putJson($this->url.'/plans/'.$p, $data)->assertConflict();
        $this->assertDatabaseHas('maintenance_events', ['plan_id' => $p, 'action' => 'PLAN_REVISED', 'actor_id' => $this->tech->id]);
        DB::table('maintenance_plans')->where('id', $p)->update(['trigger_type' => 'HOURS', 'due_hours' => 100]);
        $this->putJson($this->url.'/plans/'.$p, $this->payload(['expected_revision' => 2, 'trigger_type' => 'HOURS', 'due_hours' => 101]))->assertUnprocessable();
        $this->postJson($this->url.'/plans', $this->payload(['plan_code' => 'MX-X', 'trigger_type' => 'HOURS']))->assertUnprocessable()->assertJsonValidationErrors('due_hours');
        $this->postJson($this->url.'/plans', $this->payload(['plan_code' => 'LEGACY-1']))->assertUnprocessable()->assertJsonValidationErrors('plan_code');
    }

    public function test_offset_timestamps_are_stored_as_utc_and_bad_filters_are_rejected(): void
    {
        $p = $this->plan(['starts_at_utc' => '2026-10-18T08:00:00+07:00', 'ends_at_utc' => '2026-10-18T10:00:00+07:00', 'due_at_utc' => '2026-10-19T10:00:00+07:00']);
        $this->assertDatabaseHas('maintenance_plans', ['id' => $p, 'starts_at_utc' => '2026-10-18 01:00:00', 'ends_at_utc' => '2026-10-18 03:00:00', 'due_at_utc' => '2026-10-19 03:00:00']);
        $this->getJson($this->url.'/plans?q[]=invalid')->assertUnprocessable();
        $this->getJson($this->url.'/plans?status=UNKNOWN')->assertUnprocessable();
    }

    public function test_flight_and_maintenance_window_conflicts_reject_without_partial_writes(): void
    {
        $this->plan(); $this->postJson($this->url.'/plans', $this->payload(['plan_code' => 'MX-OVER']))->assertUnprocessable();
        $this->assertDatabaseMissing('maintenance_plans', ['plan_code' => 'MX-OVER']);
        OperationalFlight::create(['flight_number' => 'MX901', 'flight_date' => '2026-10-20', 'route_id' => Route::first()->id, 'aircraft_tail' => $this->a->tail_number,
            'flight_status' => 'SCHEDULED', 'scheduled_dep_utc' => '2026-10-20 05:00:00', 'scheduled_arr_utc' => '2026-10-20 07:00:00']);
        $this->postJson($this->url.'/plans', $this->payload(['plan_code' => 'MX-FLIGHT', 'starts_at_utc' => '2026-10-20 06:00:00', 'ends_at_utc' => '2026-10-20 08:00:00', 'due_at_utc' => '2026-10-21 01:00:00']))->assertUnprocessable();
    }

    public function test_material_shortage_persists_alert_receipt_is_idempotent_and_start_consumes_once(): void
    {
        $mid = $this->receipt()->assertOk()->json('data.id'); $this->receipt()->assertOk()->assertJsonPath('data.quantity', 1);
        $this->receipt('R01', 2)->assertUnprocessable();
        $p = $this->plan(); $o = $this->order($p, [['material_id' => $mid, 'quantity' => 2]]);
        $this->act($o, 'start')->assertOk()->assertJsonPath('data.status', 'WAITING_MATERIALS');
        $this->assertDatabaseHas('maintenance_materials', ['id' => $mid, 'quantity' => 1]);
        $this->assertDatabaseHas('maintenance_alerts', ['plan_id' => $p, 'kind' => 'MATERIAL_SHORTAGE', 'resolved_at' => null]);
        $this->assertSame('MAINTENANCE', $this->a->fresh()->operational_status);
        $this->act($o, 'complete', ['result' => 'Không được đóng', 'technical_confirmed' => true])->assertUnprocessable();
        $this->receipt('R02')->assertOk(); $this->act($o, 'start')->assertOk()->assertJsonPath('data.status', 'IN_PROGRESS');
        $this->act($o, 'start')->assertUnprocessable();
        $this->assertDatabaseHas('maintenance_materials', ['id' => $mid, 'quantity' => 0]);
        $this->assertSame(1, DB::table('maintenance_stock_moves')->where('order_id', $o)->count());
    }

    public function test_incomplete_tasks_missing_evidence_or_signature_keep_work_in_progress(): void
    {
        $o = $this->order($this->plan()); $this->act($o, 'start')->assertOk();
        $this->act($o, 'complete', ['result' => 'Kết quả', 'technical_confirmed' => true])->assertUnprocessable()->assertJsonValidationErrors('tasks');
        foreach (DB::table('maintenance_order_tasks')->where('order_id', $o)->get() as $t) { $this->act($o, 'task', ['task_id' => $t->id, 'result' => 'Đạt', 'passed' => true])->assertOk(); }
        $this->act($o, 'complete', ['result' => 'Kết quả', 'technical_confirmed' => true])->assertUnprocessable()->assertJsonValidationErrors('evidence');
        $this->post($this->url.'/orders/'.$o.'/evidence', ['file' => $this->proof()], ['Accept' => 'application/json'])->assertCreated();
        $this->act($o, 'complete', ['result' => 'Kết quả'])->assertUnprocessable()->assertJsonValidationErrors('technical_confirmed');
        $this->assertDatabaseHas('maintenance_orders', ['id' => $o, 'status' => 'IN_PROGRESS', 'technical_confirmed_by' => null]);
        $this->act($o, 'approve')->assertUnprocessable(); $this->act($o, 'release')->assertUnprocessable();
    }

    public function test_completion_approval_and_release_record_responsibility_without_resetting_unrelated_usage(): void
    {
        $p = $this->plan(); $o = $this->order($p); $this->act($o, 'start')->assertOk(); $this->complete($o);
        $this->act($o, 'release')->assertUnprocessable();
        $this->act($o, 'approve')->assertOk();
        $this->act($o, 'keep-unavailable')->assertOk(); $this->assertSame('MAINTENANCE', $this->a->fresh()->operational_status);
        $this->act($o, 'release')->assertOk()->assertJsonPath('data.status', 'RELEASED');
        $this->assertDatabaseHas('maintenance_plans', ['id' => $p, 'status' => 'RESOLVED']);
        $a = $this->a->fresh(); $this->assertSame('OPERATIONAL', $a->operational_status); $this->assertEquals(0, $a->maintenance_base_hours);
        $this->assertDatabaseHas('maintenance_orders', ['id' => $o, 'technical_confirmed_by' => $this->tech->id, 'approved_by' => $this->tech->id]);
        $this->assertDatabaseHas('maintenance_events', ['order_id' => $o, 'action' => 'RELEASE', 'actor_id' => $this->tech->id]);
        $this->act($o, 'release')->assertUnprocessable();
    }

    public function test_overdue_other_plan_and_tampered_evidence_block_technical_release(): void
    {
        $p = $this->plan(); $o = $this->order($p); $this->act($o, 'start')->assertOk(); $this->complete($o); $this->act($o, 'approve')->assertOk();
        DB::table('maintenance_orders')->where('id', $o)->update(['due_at_utc' => now()->subMinute()]);
        $this->act($o, 'release')->assertUnprocessable()->assertJsonValidationErrors('due_at_utc');
        DB::table('maintenance_orders')->where('id', $o)->update(['due_at_utc' => now()->addDay()]);
        $other = $this->plan(['plan_code' => 'MX-P02', 'starts_at_utc' => '2026-10-20 01:00:00', 'ends_at_utc' => '2026-10-20 03:00:00', 'due_at_utc' => '2026-10-21 01:00:00', 'trigger_type' => 'CONDITION', 'condition' => 'Bất thường mới', 'condition_active' => true]);
        $this->act($o, 'release')->assertUnprocessable()->assertJsonValidationErrors('aircraft_tail');
        DB::table('maintenance_plans')->where('id', $other)->update(['condition_active' => false]);
        $file = DB::table('maintenance_evidence')->where('order_id', $o)->first(); Storage::disk('local')->put($file->path, 'tampered');
        $this->act($o, 'release')->assertUnprocessable()->assertJsonValidationErrors('evidence');
        $this->assertSame('MAINTENANCE', $this->a->fresh()->operational_status);
        $this->get($this->url.'/evidence/'.$file->id, ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_resources_inactive_technician_wrong_base_and_stale_order_are_rejected(): void
    {
        $p = $this->plan(); $this->tech->update(['status' => 'INACTIVE']); $this->actingAs(User::where('username', 'toan_lead')->firstOrFail());
        $body = ['order_code' => 'BAD', 'technician_id' => $this->tech->id, 'location_id' => $this->stand->id];
        $this->postJson($this->url.'/plans/'.$p.'/orders', $body)->assertUnprocessable();
        $this->tech->update(['status' => 'ACTIVE']); $this->stand->update(['airport_code' => 'DAD']);
        $this->postJson($this->url.'/plans/'.$p.'/orders', $body)->assertUnprocessable();
        $this->stand->update(['airport_code' => 'HAN']); $o = $this->order($p); $this->act($o, 'start')->assertOk();
        $this->act($o, 'task', ['expected_revision' => 1, 'task_id' => DB::table('maintenance_order_tasks')->where('order_id', $o)->value('id'), 'passed' => true, 'result' => 'Stale'])->assertConflict();
        $this->putJson($this->url.'/plans/'.$p, $this->payload(['expected_revision' => 1]))->assertUnprocessable();
    }

    public function test_missing_usage_provenance_invalid_upload_and_resource_contention_fail_closed(): void
    {
        $this->a->update(['total_flight_hours' => 101]);
        $this->postJson($this->url.'/plans', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('usage');
        $this->a->update(['total_flight_hours' => 100]);
        $p = $this->plan(); $o = $this->order($p); $this->act($o, 'start')->assertOk();
        $this->post($this->url.'/orders/'.$o.'/evidence', ['file' => UploadedFile::fake()->createWithContent('malware.php', '<?php echo 1;')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame(0, DB::table('maintenance_evidence')->count());
        $p2 = $this->plan(['plan_code' => 'MX-RES', 'starts_at_utc' => '2026-10-20 01:00:00', 'ends_at_utc' => '2026-10-20 03:00:00', 'due_at_utc' => '2026-10-21 01:00:00']);
        $this->postJson($this->url.'/plans/'.$p2.'/orders', ['order_code' => 'MX-RES', 'technician_id' => $this->tech->id, 'location_id' => $this->stand->id])->assertUnprocessable()->assertJsonValidationErrors('location_id');
    }

    public function test_resource_adjustment_is_versioned_before_stock_issue_and_frozen_after_start(): void
    {
        $m = $this->receipt('RA01', 2)->assertOk()->json('data.id'); $o = $this->order($this->plan());
        $data = ['technician_id' => $this->tech->id, 'location_id' => $this->stand->id, 'materials' => [['material_id' => $m, 'quantity' => 2]]];
        $this->act($o, 'assign', $data)->assertOk();
        $this->assertDatabaseHas('maintenance_order_materials', ['order_id' => $o, 'material_id' => $m, 'quantity' => 2, 'consumed' => false]);
        $this->assertDatabaseHas('maintenance_events', ['order_id' => $o, 'action' => 'RESOURCES_BEFORE_CHANGE']);
        $this->assertDatabaseHas('maintenance_orders', ['id' => $o, 'authorized_by' => null, 'authorized_at' => null]);
        $this->act($o, 'start')->assertUnprocessable()->assertJsonValidationErrors('authorization'); $this->act($o, 'authorize')->assertOk();
        $this->act($o, 'start')->assertOk(); $this->act($o, 'assign', $data)->assertUnprocessable();
        $this->assertDatabaseHas('maintenance_order_materials', ['order_id' => $o, 'material_id' => $m, 'quantity' => 2, 'consumed' => true]);
    }

    public function test_airborne_aircraft_cannot_start_maintenance_even_after_scheduled_arrival(): void
    {
        $o = $this->order($this->plan());
        OperationalFlight::create(['flight_number' => 'MX903', 'flight_date' => '2026-10-17', 'route_id' => Route::first()->id, 'aircraft_tail' => $this->a->tail_number,
            'flight_status' => 'DEPARTED', 'scheduled_dep_utc' => '2026-10-17 01:00:00', 'scheduled_arr_utc' => '2026-10-17 03:00:00', 'actual_dep_utc' => '2026-10-17 01:00:00']);
        $this->act($o, 'start')->assertUnprocessable()->assertJsonValidationErrors('aircraft_tail');
        $this->assertDatabaseHas('maintenance_orders', ['id' => $o, 'status' => 'PLANNED', 'materials_ready' => false]);
        $this->assertSame(0, DB::table('maintenance_stock_moves')->where('order_id', $o)->count());
    }

    public function test_projected_usage_blocks_a_flight_and_actual_usage_refreshes_plan_alert_atomically(): void
    {
        $p = $this->plan(['trigger_type' => 'HOURS', 'due_hours' => '101.00', 'starts_at_utc' => '2026-10-19 01:00:00', 'ends_at_utc' => '2026-10-19 03:00:00', 'due_at_utc' => '2026-10-20 01:00:00']);
        $route = Route::where('route_code', 'HAN-DAD')->firstOrFail(); $gate = AirportResource::where('airport_code', 'HAN')->where('resource_type', 'GATE')->firstOrFail();
        $issues = app(ScheduleConflictChecker::class)->window($route, $this->a, $gate, Carbon::parse('2026-10-18 05:00:00'), Carbon::parse('2026-10-18 07:00:00'));
        $this->assertStringContainsString('MX-P01', implode(' ', $issues['aircraft_tail'])); $this->assertSame('100.00', $this->a->fresh()->total_flight_hours);
        $f = OperationalFlight::create(['flight_number' => 'MX904', 'flight_date' => '2026-10-17', 'route_id' => $route->id, 'aircraft_tail' => $this->a->tail_number,
            'flight_status' => 'ARRIVED', 'scheduled_dep_utc' => '2026-10-17 01:00:00', 'scheduled_arr_utc' => '2026-10-17 03:00:00', 'actual_dep_utc' => '2026-10-17 01:00:00', 'actual_arr_utc' => '2026-10-17 03:00:00']);
        $this->actingAs(User::where('username', 'fleet_demo')->firstOrFail());
        $this->postJson('/api/v1/core/aircrafts/'.$this->a->tail_number.'/usage', ['flight_id' => $f->id, 'source' => 'DEMO', 'external_id' => 'mx-usage-1'])->assertOk();
        $this->assertSame('MAINTENANCE', $this->a->fresh()->operational_status);
        $this->assertDatabaseHas('maintenance_alerts', ['plan_id' => $p, 'kind' => 'DUE', 'resolved_at' => null]);
        $this->assertDatabaseHas('aircrafts', ['tail_number' => $this->a->tail_number, 'total_flight_hours' => '102.00']);
    }

    public function test_fleet_cannot_move_or_delete_aircraft_with_an_open_plan(): void
    {
        $this->plan(); $this->actingAs(User::where('username', 'fleet_demo')->firstOrFail());
        $this->putJson('/api/v1/core/aircrafts/'.$this->a->tail_number, ['expected_revision' => 1, 'base_airport_code' => 'DAD'])->assertUnprocessable()->assertJsonValidationErrors('base_airport_code');
        $this->deleteJson('/api/v1/core/aircrafts/'.$this->a->tail_number)->assertUnprocessable()->assertJsonValidationErrors('aircraft_tail');
        $this->assertDatabaseHas('aircrafts', ['tail_number' => $this->a->tail_number, 'base_airport_code' => 'HAN']);
    }

    public function test_work_requires_explicit_prior_approval_with_actor_and_revision(): void
    {
        $p = $this->plan();
        $o = $this->postJson($this->url.'/plans/'.$p.'/orders', ['order_code' => 'MX-NO-AUTH', 'technician_id' => $this->tech->id, 'location_id' => $this->stand->id])->assertCreated()->json('data.id');
        $this->act($o, 'start')->assertUnprocessable()->assertJsonValidationErrors('authorization');
        $this->act($o, 'authorize')->assertOk();
        $this->assertDatabaseHas('maintenance_orders', ['id' => $o, 'authorized_by' => $this->tech->id]);
        $this->assertDatabaseHas('maintenance_events', ['order_id' => $o, 'action' => 'AUTHORIZE', 'actor_id' => $this->tech->id]);
        $this->act($o, 'authorize')->assertUnprocessable(); $this->act($o, 'start')->assertOk();
    }

    public static function departureRequirements(): array { return [['plan'], ['legacy-order']]; }

    #[\PHPUnit\Framework\Attributes\DataProvider('departureRequirements')]
    public function test_departure_checks_overdue_maintenance_even_without_resource_or_current_status_flag(string $requirement): void
    {
        if ($requirement === 'plan') { $this->plan(['trigger_type' => 'HOURS', 'due_hours' => '100.00']); }
        else {
            $this->postJson($this->url, ['order_code' => 'MX-OLD-DUE', 'aircraft_tail' => $this->a->tail_number, 'task' => 'Hồ sơ quá hạn chưa chuyển',
                'starts_at_utc' => '2026-10-17 01:00:00', 'ends_at_utc' => '2026-10-17 03:00:00', 'due_at_utc' => '2026-10-17 04:00:00'])->assertCreated();
        }
        // Imported flags can be stale. A missing legacy gate must never disable the maintenance gate.
        DB::table('aircrafts')->where('tail_number', $this->a->tail_number)->update(['operational_status' => 'OPERATIONAL']);
        $f = OperationalFlight::create(['flight_number' => 'MX905', 'flight_date' => '2026-10-18', 'route_id' => Route::where('route_code', 'HAN-DAD')->value('id'),
            'aircraft_tail' => $this->a->tail_number, 'flight_status' => 'BOARDING', 'scheduled_dep_utc' => '2026-10-18 02:00:00', 'scheduled_arr_utc' => '2026-10-18 04:00:00']);
        $this->assertNull($f->resource_id); $this->actingAs(User::where('username', 'toan_lead')->firstOrFail());
        $this->putJson('/api/v1/core/flights/'.$f->id.'/status', ['flight_status' => 'DEPARTED', 'actual_dep_utc' => '2026-10-18 02:29:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('aircraft_tail');
        $this->assertDatabaseHas('operational_flights', ['id' => $f->id, 'flight_status' => 'BOARDING', 'actual_dep_utc' => null]);
    }

    public function test_only_explicit_full_usage_inspection_resets_aggregate_maintenance_basis(): void
    {
        $this->a->update(['total_flight_hours' => 3000, 'opening_flight_hours' => 3000]);
        $p = $this->plan(['trigger_type' => 'HOURS', 'due_hours' => 3000, 'resets_usage_basis' => true]);
        $o = $this->order($p); $this->act($o, 'start')->assertOk(); $this->complete($o); $this->act($o, 'approve')->assertOk(); $this->act($o, 'release')->assertOk();
        $a = $this->a->fresh(); $this->assertEquals(3000, $a->maintenance_base_hours); $this->assertEquals(20, $a->maintenance_base_cycles); $this->assertFalse($a->needsMaintenance());
        $this->assertSame([], app(MaintenanceReadiness::class)->issues($a));
    }

    public function test_airport_scope_protects_collections_orders_files_stock_and_admin_has_no_bypass(): void
    {
        $p = $this->plan(); $o = $this->order($p); $this->act($o, 'start')->assertOk();
        $eid = $this->post($this->url.'/orders/'.$o.'/evidence', ['file' => $this->proof()], ['Accept' => 'application/json'])->assertCreated()->assertJsonMissingPath('data.path')->json('data.id');
        $this->get($this->url.'/evidence/'.$eid)->assertOk()->assertHeader('cache-control', 'no-store, private');
        $u = User::factory()->create(); $role = Role::where('role_code', 'TECHNICIAN')->firstOrFail(); $u->roles()->attach($role->id);
        DB::table('user_data_scopes')->insert(['user_id' => $u->id, 'role_id' => $role->id, 'scope_type' => 'airport', 'scope_id' => 'DAD']); $this->actingAs($u);
        $this->getJson($this->url.'/plans')->assertOk()->assertJsonPath('data.total', 0); $this->getJson($this->url)->assertOk()->assertJsonMissing(['order_code' => 'MX-O'.$p]);
        $this->getJson($this->url.'/plans/'.$p)->assertForbidden(); $this->get($this->url.'/evidence/'.$eid)->assertForbidden();
        $this->act($o, 'release')->assertForbidden(); $this->receipt()->assertForbidden();
        $admin = User::factory()->create(); $ar = Role::where('role_code', 'ADMIN')->firstOrFail(); $admin->roles()->attach($ar->id);
        DB::table('user_data_scopes')->insert(['user_id' => $admin->id, 'role_id' => $ar->id, 'scope_type' => 'global', 'scope_id' => '*']);
        $this->actingAs($admin); $this->act($o, 'approve')->assertForbidden();
    }
}
