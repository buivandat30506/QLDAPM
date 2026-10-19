<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\AircraftModel;
use App\Models\AirportResource;
use App\Models\CrewMember;
use App\Models\FlightCrewAssignment;
use App\Models\FlightSchedule;
use App\Models\OperationalFlight;
use App\Models\Route;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DiagramWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): void
    {
        $this->actingAs(User::where('username', 'toan_lead')->firstOrFail());
    }

    public function test_authentication_and_roles_stop_writes_before_validation(): void
    {
        $this->postJson('/api/v1/core/airports', [])->assertUnauthorized()->assertJsonPath('success', false);
        $this->get('/admin/workflows')->assertRedirect('/login');
        $this->actingAs(User::where('username', 'nguyen_van_a')->firstOrFail());
        $this->postJson('/api/v1/core/airports', [])->assertForbidden();
        $this->get('/admin/workflows')->assertForbidden();
        $this->actingAs(User::where('username', 'ops_demo')->firstOrFail());
        $this->postJson('/api/v1/core/airports', [])->assertForbidden();
        $this->postJson('/admin/workflows', ['kind' => 'master', 'action' => 'create'])->assertForbidden();
        $this->postJson('/api/v1/auth/login', ['username' => 'ops_demo', 'password' => 'Demo@12345'])->assertOk()->assertJsonStructure(['data' => ['token']]);
    }

    public function test_master_data_approval_versions_and_audit(): void
    {
        $this->admin();
        $id = $this->postJson('/api/v1/core/master-data', ['category' => 'SERVICE', 'code' => 'MEAL', 'payload' => ['name' => 'Suất ăn']])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/core/master-data/{$id}/publish")->assertUnprocessable();
        foreach (['submit', 'approve', 'publish'] as $action) {
            $this->postJson("/api/v1/core/master-data/{$id}/{$action}")->assertOk();
        }
        $this->assertDatabaseHas('master_data_records', ['id' => $id, 'status' => 'PUBLISHED', 'version' => 1]);
        $this->getJson('/api/v1/core/versions?entity_type=master_data_records&entity_id='.$id)->assertOk()->assertJsonCount(1, 'data');
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'master_data_records', 'entity_id' => (string) $id, 'action' => 'PUBLISH']);
        $this->putJson("/api/v1/core/master-data/{$id}", ['category' => 'SERVICE', 'code' => 'MEAL', 'payload' => ['name' => 'Overwrite']])->assertUnprocessable();
    }

    public function test_schedule_missing_resources_and_conflicts_keep_draft(): void
    {
        $this->admin();
        foreach (['DM901', 'DM902'] as $number) {
            $schedule = FlightSchedule::where('flight_number', $number)->firstOrFail();
            $this->postJson('/api/v1/core/schedules/'.$schedule->id.'/submit')->assertUnprocessable();
            $this->assertDatabaseHas('flight_schedules', ['id' => $schedule->id, 'status' => 'DRAFT']);
        }
        $payload = $this->schedulePayload();
        $this->postJson('/api/v1/core/schedules', $payload + ['status' => 'PUBLISHED'])->assertUnprocessable();
    }

    public function test_schedule_rechecks_resources_before_publication_and_flights_require_source(): void
    {
        $this->admin();
        $payload = $this->schedulePayload();
        $id = $this->postJson('/api/v1/core/schedules', $payload)->assertCreated()->assertJsonPath('data.status', 'DRAFT')->json('data.id');
        $flight = ['schedule_id' => $id, 'flight_date' => $payload['effective_from']];
        $this->postJson('/api/v1/core/flights', $flight)->assertUnprocessable();
        $this->postJson("/api/v1/core/schedules/{$id}/submit")->assertOk();
        $this->postJson("/api/v1/core/schedules/{$id}/approve")->assertOk();
        AirportResource::findOrFail($payload['resource_id'])->update(['status' => 'MAINTENANCE']);
        $this->postJson("/api/v1/core/schedules/{$id}/publish")->assertUnprocessable();
        $this->assertDatabaseHas('flight_schedules', ['id' => $id, 'status' => 'APPROVED', 'version' => 0]);
        AirportResource::findOrFail($payload['resource_id'])->update(['status' => 'AVAILABLE']);
        $this->postJson("/api/v1/core/schedules/{$id}/publish")->assertOk()->assertJsonPath('data.version', 1);
        $this->postJson('/api/v1/core/flights', $flight)->assertCreated()->assertJsonPath('data.flight_number', 'TEST900');
        $this->postJson('/api/v1/core/flights', $flight)->assertUnprocessable();
        $this->postJson('/api/v1/core/flights', ['flight_number' => 'ANY'])->assertUnprocessable();
        $this->assertDatabaseHas('entity_versions', ['entity_type' => 'flight_schedules', 'entity_id' => $id, 'version' => 1]);
    }

    public function test_gate_conflict_and_wrong_aircraft_model_are_rejected(): void
    {
        $this->admin();
        $published = FlightSchedule::where('flight_number', 'DM101')->firstOrFail();
        $copy = $published->toArray();
        unset($copy['id']);
        $copy['flight_number'] = 'TESTGATE';
        $copy['status'] = 'DRAFT';
        $copy['aircraft_tail'] = 'VN-D104';
        $id = $this->postJson('/api/v1/core/schedules', $copy)->assertCreated()->json('data.id');
        $this->postJson("/api/v1/core/schedules/{$id}/submit")->assertUnprocessable()->assertJsonStructure(['errors' => ['resource_id']]);
        $schedule = FlightSchedule::findOrFail($id);
        $schedule->update(['resource_id' => AirportResource::where('resource_code', 'DEMO-G3')->where('airport_code', 'HAN')->value('id'),
            'model_id' => AircraftModel::where('model_code', 'B787-9')->value('id')]);
        $this->postJson("/api/v1/core/schedules/{$id}/submit")->assertUnprocessable()->assertJsonStructure(['errors' => ['model_id']]);
    }

    public function test_event_unknown_flight_is_logged_and_alert_waits_for_operator(): void
    {
        $this->admin();
        $unknown = ['source' => 'DEMO', 'external_id' => 'unknown', 'flight_id' => (string) Str::uuid(), 'flight_status' => 'BOARDING'];
        $this->postJson('/api/v1/core/events', $unknown)->assertUnprocessable();
        $this->assertDatabaseHas('flight_events', ['external_id' => 'unknown', 'status' => 'FAILED']);
        $flight = OperationalFlight::where('flight_number', 'DM102')->firstOrFail();
        $event = ['source' => 'DEMO', 'external_id' => 'wx-1', 'flight_id' => $flight->id, 'flight_status' => 'DELAYED', 'reason' => 'Weather'];
        $this->postJson('/api/v1/core/events', $event)->assertStatus(202)->assertJsonPath('data.status', 'ALERT');
        $this->postJson('/api/v1/core/events', $event)->assertStatus(202);
        $this->assertEquals(1, DB::table('flight_events')->where('external_id', 'wx-1')->count());
        $this->assertEquals('SCHEDULED', $flight->fresh()->flight_status);
        $this->postJson('/api/v1/core/events', array_replace($event, ['reason' => 'Different']))->assertUnprocessable();
        $this->postJson('/api/v1/core/flights/'.$flight->id.'/resolve', ['action' => 'CANCEL', 'reason' => 'Điều hành xác nhận hủy do thời tiết'])->assertOk();
        $this->assertDatabaseHas('flight_events', ['external_id' => 'wx-1', 'status' => 'RESOLVED']);
    }

    public function test_invalid_disruption_plan_rolls_back_flight_and_crew(): void
    {
        $this->admin();
        $flight = OperationalFlight::where('flight_number', 'DM101')->firstOrFail();
        $before = $flight->scheduled_dep_utc->format('Y-m-d H:i:s');
        $day = $flight->flight_date->format('Y-m-d');
        $this->postJson('/api/v1/core/flights/'.$flight->id.'/resolve', ['action' => 'DELAY', 'reason' => 'Giả lập trùng chuyến kế tiếp',
            'scheduled_dep_utc' => $day.' 05:30:00', 'scheduled_arr_utc' => $day.' 07:40:00'])->assertUnprocessable();
        $this->assertEquals($before, $flight->fresh()->scheduled_dep_utc->format('Y-m-d H:i:s'));
        $this->assertEquals('CONFIRMED', $flight->crewAssignments()->first()->status);
    }

    public function test_usage_history_is_idempotent_and_maintenance_cannot_be_bypassed(): void
    {
        $this->admin();
        $flight = OperationalFlight::where('flight_number', 'DM105')->firstOrFail();
        $before = Aircraft::findOrFail($flight->aircraft_tail)->total_flight_hours;
        $this->putJson('/api/v1/core/flights/'.$flight->id.'/status', ['flight_status' => 'ARRIVED'])->assertOk();
        $this->assertEquals($before, Aircraft::findOrFail($flight->aircraft_tail)->total_flight_hours);
        $this->assertEquals(1, DB::table('aircraft_usage_records')->where('flight_id', $flight->id)->count());
        $this->putJson('/api/v1/core/aircrafts/VN-D201', ['operational_status' => 'OPERATIONAL'])->assertUnprocessable();
    }

    public function test_maintenance_requires_resources_completion_approval_and_non_overdue_release(): void
    {
        $this->admin();
        $order = DB::table('maintenance_orders')->where('aircraft_tail', 'VN-D202')->first();
        $url = '/api/v1/core/maintenance/'.$order->id;
        $this->postJson($url.'/release')->assertUnprocessable();
        $this->postJson($url.'/complete', ['result' => 'Done', 'evidence' => 'SIM-001', 'technical_confirmed' => true])->assertUnprocessable();
        $this->assertDatabaseHas('maintenance_orders', ['id' => $order->id, 'status' => 'WAITING_MATERIALS']);
        $this->postJson($url.'/start', ['technician' => 'SIM-TECH-03', 'location' => 'SIM-HANGAR-C', 'materials_ready' => true])->assertConflict();
        $running = DB::table('maintenance_orders')->where('aircraft_tail', 'VN-D201')->first();
        $this->postJson('/api/v1/core/maintenance/'.$running->id.'/complete', ['result' => 'Done', 'evidence' => 'SIM-001', 'technical_confirmed' => true])->assertConflict();
        Aircraft::findOrFail('VN-D202')->update(['base_airport_code' => 'HAN']);
        $stand = AirportResource::create(['airport_code'=>'HAN', 'resource_type'=>'STAND', 'resource_code'=>'ADOPT-MX', 'status'=>'AVAILABLE']);
        $this->postJson('/api/v1/core/maintenance/orders/'.$order->id.'/adopt', ['technician_id'=>User::where('username','tech_demo')->value('id'), 'location_id'=>$stand->id, 'reason'=>'Rà soát và chuyển hồ sơ demo'])->assertOk();
        $modern = '/api/v1/core/maintenance/orders/'.$order->id;
        $act = fn ($action, $data=[]) => $this->postJson($modern.'/'.$action, $data + ['expected_revision'=>DB::table('maintenance_orders')->where('id',$order->id)->value('revision'), 'reason'=>'Kiểm tra quy trình đầy đủ']);
        $act('authorize')->assertOk(); $act('start')->assertOk();
        $act('task', ['task_id'=>DB::table('maintenance_order_tasks')->where('order_id',$order->id)->value('id'), 'result'=>'Đạt', 'passed'=>true])->assertOk();
        $file=\Illuminate\Http\UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
        $this->post($modern.'/evidence', ['file'=>$file], ['Accept'=>'application/json'])->assertCreated();
        $act('complete', ['result'=>'Hoàn tất hồ sơ', 'technical_confirmed'=>true])->assertOk();
        $act('release')->assertUnprocessable();
        $act('approve')->assertOk();
        DB::table('maintenance_orders')->where('id', $order->id)->update(['due_at_utc' => now('UTC')->subHour()]);
        $act('release')->assertUnprocessable();
        $this->assertEquals('MAINTENANCE', Aircraft::findOrFail('VN-D202')->operational_status);
        DB::table('maintenance_orders')->where('id', $order->id)->update(['due_at_utc' => now('UTC')->addDay()]);
        $act('release')->assertOk();
        $this->assertEquals('OPERATIONAL', Aircraft::findOrFail('VN-D202')->operational_status);
    }

    public function test_crew_license_availability_rest_and_replacement_rollback(): void
    {
        $this->admin();
        $flight = OperationalFlight::where('flight_number', 'DM102')->firstOrFail();
        $expired = CrewMember::where('crew_code', 'DEMO-CRW-07')->firstOrFail();
        $resting = CrewMember::where('crew_code', 'DEMO-CRW-01')->firstOrFail();
        foreach ([$expired, $resting] as $crew) {
            $this->postJson('/api/v1/core/flights/'.$flight->id.'/crew/assign', ['crew_id' => $crew->id, 'assigned_position' => 'CAPTAIN'])->assertUnprocessable();
        }
        $assignment = FlightCrewAssignment::whereHas('flight', fn ($q) => $q->where('flight_number', 'DM101'))->where('assigned_position', 'CAPTAIN')->firstOrFail();
        $this->postJson('/api/v1/core/crew-assignments/'.$assignment->id.'/replace', ['crew_id' => $expired->id, 'reason' => 'Thử người hết hạn'])->assertUnprocessable();
        $this->assertEquals('CONFIRMED', $assignment->fresh()->status);
        $candidate = CrewMember::where('crew_code', 'CRW-002')->firstOrFail();
        $newId = $this->postJson('/api/v1/core/crew-assignments/'.$assignment->id.'/replace', ['crew_id' => $candidate->id, 'reason' => 'Đổi người dự bị hợp lệ'])->assertCreated()->json('data.id');
        $this->assertEquals('REPLACED', $assignment->fresh()->status);
        $this->assertDatabaseHas('flight_crew_assignments', ['id' => $newId, 'status' => 'ASSIGNED']);
    }

    public function test_crew_plan_needs_approval_member_acknowledgement_and_ownership(): void
    {
        $this->admin();
        $flight = OperationalFlight::where('flight_number', 'DM103')->firstOrFail();
        $assignment = $flight->crewAssignments()->firstOrFail();
        $this->postJson('/api/v1/core/crew-assignments/'.$assignment->id.'/confirm')->assertUnprocessable();
        $this->postJson('/api/v1/core/flights/'.$flight->id.'/crew/approve')->assertOk();
        $this->assertDatabaseHas('crew_notifications', ['assignment_id' => $assignment->id]);
        $this->actingAs(User::where('username', 'captain_demo')->firstOrFail());
        $this->postJson('/api/v1/core/crew-assignments/'.$assignment->id.'/confirm')->assertForbidden();
        $this->admin();
        foreach ($flight->crewAssignments()->get() as $crewAssignment) {
            $this->postJson('/api/v1/core/crew-assignments/'.$crewAssignment->id.'/confirm')->assertOk();
        }
        $this->getJson('/api/v1/core/flights/'.$flight->id.'/crew')->assertOk()->assertJsonPath('data.compliance.is_valid', true);
    }

    public function test_demo_seeder_is_repeatable_without_duplicate_usage_or_events(): void
    {
        $this->admin();
        $counts = [];
        foreach (['operational_flights', 'aircraft_usage_records', 'flight_events', 'maintenance_orders', 'entity_versions'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }
        $this->seed();
        foreach ($counts as $table => $count) {
            $this->assertEquals($count, DB::table($table)->count(), $table);
        }
    }

    public function test_crew_profile_from_form_has_usable_qualifications_and_checks_training_and_availability(): void
    {
        $this->admin();
        $model = Aircraft::findOrFail('VN-D101')->model_id;
        $payload = ['crew_code' => 'TEST-CRW', 'full_name' => 'Demo Test', 'email' => 'test-crew@example.test',
            'phone' => 'DEMO-TEST', 'rank' => 'CAPTAIN', 'base_airport_code' => 'SGN', 'license_number' => 'TEST-LIC',
            'status' => 'AVAILABLE', 'license_expires_on' => '2035-12-31', 'training_expires_on' => '2035-12-31',
            'qualified_model_ids' => [(string) $model]];
        $id = $this->postJson('/api/v1/core/crew-members', $payload)->assertCreated()->json('data.id');
        $crew = CrewMember::findOrFail($id);
        $flight = OperationalFlight::where('flight_number', 'DM102')->firstOrFail();
        $url = '/api/v1/core/flights/'.$flight->id.'/crew/assign';
        $crew->update(['training_expires_on' => now('UTC')->subDay()->toDateString()]);
        $this->postJson($url, ['crew_id' => $id, 'assigned_position' => 'CAPTAIN'])->assertUnprocessable();
        $crew->update(['training_expires_on' => '2035-12-31', 'available_from_utc' => $flight->scheduled_dep_utc->copy()->addDay()]);
        $this->postJson($url, ['crew_id' => $id, 'assigned_position' => 'CAPTAIN'])->assertUnprocessable();
        $this->putJson('/api/v1/core/crew-members/'.$id, ['available_from_utc' => null, 'available_until_utc' => null])->assertOk();
        $this->postJson($url, ['crew_id' => $id, 'assigned_position' => 'CAPTAIN'])->assertCreated();
    }

    public function test_long_duty_and_maintenance_overlap_are_rejected(): void
    {
        $this->admin();
        $flight = OperationalFlight::where('flight_number', 'DM102')->firstOrFail();
        $flight->update(['scheduled_arr_utc' => $flight->scheduled_dep_utc->copy()->addHours(13)]);
        $crew = CrewMember::where('crew_code', 'CRW-002')->firstOrFail();
        $this->postJson('/api/v1/core/flights/'.$flight->id.'/crew/assign', ['crew_id' => $crew->id, 'assigned_position' => 'CAPTAIN'])->assertUnprocessable()->assertJsonStructure(['errors' => ['duty']]);
        $other = OperationalFlight::where('flight_number', 'DM101')->firstOrFail();
        $this->postJson('/api/v1/core/maintenance', ['order_code' => 'TEST-MX-OVERLAP', 'aircraft_tail' => $other->aircraft_tail,
            'task' => 'Test', 'starts_at_utc' => $other->scheduled_dep_utc->format('Y-m-d H:i:s'),
            'ends_at_utc' => $other->scheduled_arr_utc->format('Y-m-d H:i:s'), 'due_at_utc' => $other->scheduled_arr_utc->copy()->addDay()->format('Y-m-d H:i:s')])->assertUnprocessable();
        $this->assertDatabaseMissing('maintenance_orders', ['order_code' => 'TEST-MX-OVERLAP']);
    }

    private function schedulePayload(): array
    {
        $day = now('UTC')->addDay()->toDateString();

        return ['flight_number' => 'TEST900', 'route_id' => Route::where('route_code', 'HAN-DAD')->value('id'),
            'model_id' => Aircraft::findOrFail('VN-D104')->model_id, 'aircraft_tail' => 'VN-D104',
            'resource_id' => AirportResource::where('airport_code', 'HAN')->where('resource_code', 'DEMO-G3')->value('id'),
            'departure_time_utc' => '12:00:00', 'arrival_time_utc' => '13:20:00', 'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
            'effective_from' => $day, 'effective_to' => $day];
    }
}
