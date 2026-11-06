<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\CrewMember;
use App\Models\OperationalFlight;
use App\Models\Role;
use App\Models\Route;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private CrewMember $captain;

    private CrewMember $officer;

    private OperationalFlight $flight;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-18 02:30:00', 'UTC'));
        $this->seed();
        $this->actingAs(User::where('username', 'crew_ops_demo')->firstOrFail());
        $this->captain = $this->member('CW-CAP', 'CAPTAIN');
        $this->officer = $this->member('CW-FO', 'FIRST_OFFICER');
        $this->flight = OperationalFlight::create(['flight_number' => 'CW901', 'flight_date' => '2026-11-15', 'route_id' => Route::where('route_code', 'HAN-DAD')->value('id'),
            'aircraft_tail' => 'VN-D104', 'flight_status' => 'SCHEDULED', 'scheduled_dep_utc' => '2026-11-15 12:00:00', 'scheduled_arr_utc' => '2026-11-15 14:00:00']);
    }

    private function member(string $code, string $rank): CrewMember
    {
        $id = $this->postJson('/api/v1/core/crew-members', ['crew_code' => $code, 'full_name' => 'Nhân sự kiểm thử '.$code, 'email' => strtolower($code).'@example.test',
            'phone' => 'SYNTHETIC', 'rank' => $rank, 'base_airport_code' => 'HAN', 'license_number' => 'LIC-'.$code, 'license_expires_on' => '2030-01-01',
            'training_expires_on' => '2030-01-01', 'qualified_model_ids' => [Aircraft::findOrFail('VN-D104')->model_id]])->assertCreated()->json('data.id');

        return CrewMember::findOrFail($id);
    }

    private function assign(CrewMember $crew, string $position): string
    {
        return $this->postJson('/api/v1/core/flights/'.$this->flight->id.'/crew/assign', ['crew_id' => $crew->id, 'assigned_position' => $position])->assertCreated()->json('data.id');
    }

    private function approve(): void
    {
        $this->postJson('/api/v1/core/flights/'.$this->flight->id.'/crew/approve')->assertOk();
    }

    private function window(CrewMember $crew, string $type = 'LEAVE'): array
    {
        return ['type' => $type, 'starts_at_utc' => '2026-11-15 10:00:00', 'ends_at_utc' => '2026-11-15 16:00:00', 'reason' => 'Lịch tổng hợp kiểm thử', 'expected_revision' => $crew->fresh()->revision];
    }

    public function test_profile_updates_validate_qualifications_identity_and_revision(): void
    {
        $url = '/api/v1/core/crew-members/'.$this->captain->id;
        $this->putJson($url, ['qualified_model_ids' => [999999]])->assertUnprocessable();
        $this->putJson($url, ['qualified_model_ids' => [$this->captain->qualified_model_ids[0], $this->captain->qualified_model_ids[0]]])->assertUnprocessable();
        $this->putJson($url, ['license_expires_on' => 'not-date'])->assertUnprocessable();
        $this->putJson($url, ['license_number' => $this->officer->license_number])->assertUnprocessable();
        $this->putJson($url, ['expected_revision' => 1, 'full_name' => 'Hồ sơ đã sửa'])->assertOk()->assertJsonPath('data.revision', 2);
        $this->putJson($url, ['expected_revision' => 1, 'full_name' => 'Không ghi đè'])->assertConflict();
        $this->putJson($url, ['total_flight_hours' => 999])->assertUnprocessable();
        $this->get('/admin/crew/'.$this->captain->id)->assertOk()->assertSee('Hồ sơ đã sửa');
    }

    public function test_leave_blocks_assignment_and_cancellation_keeps_history(): void
    {
        $url = '/api/v1/core/crew-members/'.$this->captain->id.'/availability';
        $id = $this->postJson($url, $this->window($this->captain))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/core/flights/'.$this->flight->id.'/crew/assign', ['crew_id' => $this->captain->id, 'assigned_position' => 'CAPTAIN'])->assertUnprocessable();
        $this->postJson($url.'/'.$id.'/cancel', ['reason' => 'Đã đổi lịch nghỉ'])->assertOk();
        $this->assertDatabaseHas('crew_availability_windows', ['id' => $id, 'cancel_reason' => 'Đã đổi lịch nghỉ']);
        $this->assign($this->captain, 'CAPTAIN');
        $this->deleteJson('/api/v1/core/crew-members/'.$this->captain->id)->assertUnprocessable();
        $this->get($url)->assertOk();
    }

    public function test_suggestions_include_qualified_standby_and_warn_when_none_are_eligible(): void
    {
        $this->postJson('/api/v1/core/crew-members/'.$this->captain->id.'/availability', $this->window($this->captain, 'STANDBY'))->assertCreated();
        $url = '/api/v1/core/flights/'.$this->flight->id.'/crew/suggestions?position=CAPTAIN';
        $before = DB::table('flight_crew_assignments')->count();
        $data = $this->getJson($url)->assertOk()->assertJsonPath('data.method', 'DETERMINISTIC_RULE_FILTER')->json('data');
        $this->assertSame($this->captain->id, $data['eligible'][0]['id']);
        $this->assertTrue($data['eligible'][0]['standby']);
        $this->assertSame('local-crew-v1', $data['profile']['code']);
        $this->assertDatabaseCount('flight_crew_assignments', $before);
        $this->assign($this->captain, 'CAPTAIN');
        $this->getJson($url)->assertOk()->assertJsonPath('data.eligible', [])->assertJsonPath('data.warning', 'Không đủ nhân sự hợp lệ cho vị trí đã chọn; chưa tự phân công.');
    }

    public function test_approval_confirmation_and_replacement_have_actor_policy_and_immutable_events(): void
    {
        $capt = $this->assign($this->captain, 'CAPTAIN');
        $this->assign($this->officer, 'FIRST_OFFICER');
        $this->postJson('/api/v1/core/crew-assignments/'.$capt.'/confirm')->assertUnprocessable();
        $this->approve();
        $this->assertDatabaseHas('flight_crew_assignments', ['id' => $capt, 'approved_by' => auth()->id(), 'rule_version_code' => 'local-crew-v1']);
        $this->assertDatabaseHas('crew_notifications', ['assignment_id' => $capt]);
        $this->postJson('/api/v1/core/crew-assignments/'.$capt.'/confirm')->assertOk()->assertJsonPath('data.confirmation_mode', 'OPERATOR');
        $this->postJson('/api/v1/core/crew-assignments/'.$capt.'/confirm')->assertOk();
        $replacement = $this->member('CW-REPLACE', 'CAPTAIN');
        $this->postJson('/api/v1/core/crew-members/'.$replacement->id.'/availability', $this->window($replacement, 'STANDBY'))->assertCreated();
        $this->getJson('/api/v1/core/flights/'.$this->flight->id.'/crew/suggestions?position=CAPTAIN&replace_id='.$capt)
            ->assertOk()->assertJsonPath('data.eligible.0.id', $replacement->id)->assertJsonPath('data.eligible.0.standby', true);
        $before = DB::table('crew_assignment_events')->where('assignment_id', $capt)->orderBy('id')->get()->toArray();
        $new = $this->postJson('/api/v1/core/crew-assignments/'.$capt.'/replace', ['crew_id' => $replacement->id, 'reason' => 'Nhân sự dự bị thay thế'])->assertCreated()->assertJsonPath('data.status', 'ASSIGNED')->json('data.id');
        $this->assertDatabaseHas('flight_crew_assignments', ['id' => $capt, 'status' => 'REPLACED']);
        $this->assertDatabaseHas('crew_assignment_events', ['assignment_id' => $capt, 'action' => 'REPLACED', 'reason' => 'Nhân sự dự bị thay thế']);
        foreach ($before as $event) {
            $this->assertSame($event->snapshot, DB::table('crew_assignment_events')->where('id', $event->id)->value('snapshot'));
        }
        $this->postJson('/api/v1/core/crew-assignments/'.$new.'/confirm')->assertUnprocessable();
        $this->approve();
        $this->postJson('/api/v1/core/crew-assignments/'.$new.'/confirm')->assertOk();
        $this->get('/admin/crew-plans/'.$this->flight->id)->assertOk()->assertSee('OPERATOR_RECORDED_CONFIRMATION')->assertSee('Nhân sự dự bị thay thế');
    }

    public function test_ineligible_replacement_rolls_back_original_assignment_and_events(): void
    {
        $id = $this->assign($this->captain, 'CAPTAIN');
        $before = DB::table('crew_assignment_events')->count();
        $this->postJson('/api/v1/core/crew-assignments/'.$id.'/replace', ['crew_id' => $this->officer->id, 'reason' => 'Sai cấp bậc'])->assertUnprocessable();
        $this->assertDatabaseHas('flight_crew_assignments', ['id' => $id, 'status' => 'ASSIGNED']);
        $this->assertDatabaseCount('crew_assignment_events', $before);
        $this->postJson('/api/v1/core/flights/'.$this->flight->id.'/crew/approve')->assertUnprocessable();
    }

    public function test_leave_and_profile_changes_invalidate_prior_approval_before_departure(): void
    {
        $id = $this->assign($this->captain, 'CAPTAIN');
        $this->assign($this->officer, 'FIRST_OFFICER');
        $this->approve();
        $this->postJson('/api/v1/core/crew-assignments/'.$id.'/confirm')->assertOk();
        $this->putJson('/api/v1/core/crew-members/'.$this->captain->id, ['training_expires_on' => '2031-01-01'])->assertOk();
        $this->assertDatabaseHas('flight_crew_assignments', ['id' => $id, 'status' => 'ASSIGNED', 'approved_by' => null, 'confirmed_by' => null]);
        $this->assertDatabaseHas('crew_assignment_events', ['assignment_id' => $id, 'action' => 'PROFILE_INVALIDATED']);
        $this->approve();
        $this->postJson('/api/v1/core/crew-members/'.$this->captain->id.'/availability', $this->window($this->captain))->assertCreated();
        $this->assertDatabaseHas('flight_crew_assignments', ['id' => $id, 'status' => 'ASSIGNED']);
        $this->postJson('/api/v1/core/flights/'.$this->flight->id.'/crew/approve')->assertUnprocessable();
        $this->assertDatabaseHas('crew_assignment_events', ['assignment_id' => $id, 'action' => 'AVAILABILITY_INVALIDATED']);
    }

    public function test_only_linked_member_or_scoped_operator_can_confirm(): void
    {
        $id = $this->assign($this->captain, 'CAPTAIN');
        $this->assign($this->officer, 'FIRST_OFFICER');
        $this->approve();
        $user = User::factory()->create();
        $role = Role::where('role_code', 'CREW_MEMBER')->firstOrFail();
        $user->roles()->attach($role->id);
        DB::table('user_data_scopes')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => 'self', 'scope_id' => $user->id]);
        $this->actingAs($user);
        $this->postJson('/api/v1/core/crew-assignments/'.$id.'/confirm')->assertForbidden();
        $this->captain->update(['user_id' => $user->id]);
        $this->postJson('/api/v1/core/crew-assignments/'.$id.'/confirm')->assertOk()->assertJsonPath('data.confirmation_mode', 'SELF')->assertJsonPath('data.confirmed_by', $user->id);
        $this->postJson('/api/v1/core/crew-assignments/'.$id.'/replace', ['crew_id' => $this->officer->id, 'reason' => 'Không có quyền'])->assertForbidden();
    }

    public function test_backend_airport_and_flight_scopes_on_profiles_plans_and_actions(): void
    {
        $id = $this->assign($this->captain, 'CAPTAIN');
        $user = User::factory()->create();
        $role = Role::where('role_code', 'CREW_OPS')->firstOrFail();
        $user->roles()->attach($role->id);
        DB::table('user_data_scopes')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => 'airport', 'scope_id' => 'SGN']);
        $this->actingAs($user);
        foreach (['', '/availability'] as $suffix) {
            $this->getJson('/api/v1/core/crew-members/'.$this->captain->id.$suffix)->assertForbidden();
        }
        $this->putJson('/api/v1/core/crew-members/'.$this->captain->id, ['full_name' => []])->assertForbidden();
        $this->get('/admin/crew-plans/'.$this->flight->id)->assertForbidden();
        $this->getJson('/api/v1/core/flights/'.$this->flight->id.'/crew/suggestions?position=CAPTAIN')->assertForbidden();
        $this->postJson('/api/v1/core/crew-assignments/'.$id.'/confirm')->assertForbidden();
        $rows = $this->getJson('/api/v1/core/crew-members')->assertOk()->json('data');
        foreach ($rows as $row) {
            $this->assertSame('SGN', $row['base_airport_code']);
        }
        DB::table('user_data_scopes')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => 'flight', 'scope_id' => $this->flight->id]);
        $this->get('/admin/crew-plans/'.$this->flight->id)->assertOk();
        $this->get('/admin/crew-plans?search=CW901')->assertOk()->assertSee('CW901');
    }
}
