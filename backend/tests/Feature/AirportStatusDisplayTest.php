<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Operations\AirportStatusSimulation;
use App\Modules\Operations\InfrastructureService;
use App\Support\AirportStatusAuthority;
use App\Support\InfrastructureCatalog;
use App\Support\InfrastructureDisplay;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AirportStatusDisplayTest extends TestCase
{
    private function staff(string $role = 'data_admin'): User
    {
        return User::factory()->create(['role' => $role, 'airport_code' => 'HAN']);
    }

    private function payload(): array
    {
        return ['operational_status' => 'HIGH', 'note' => 'Giám đốc xác nhận tình hình', 'confirmed' => true];
    }

    public function test_compact_status_views_hide_source_copy_without_changing_provenance_or_edit_permissions(): void
    {
        $db = DB::connection('ops');
        $this->actingAs($this->staff());
        foreach (['NORMAL' => 'Normal', 'HIGH' => 'High', 'OVERLOAD' => 'Overload', 'MAINTENANCE' => 'Maintenance'] as $status => $label) {
            $db->table('airports')->where('code', 'HAN')->update(['operational_status' => $status, 'operational_status_source' => 'SIMULATION', 'operational_status_note' => 'Dữ liệu mô phỏng', 'operational_status_updated_at' => '2026-11-10 03:15:00', 'operational_status_simulation_key' => 'retained-origin']);
            $before = $db->table('airports')->where('code', 'HAN')->first();
            foreach (['/workspace/catalog?flow=airports&q=HAN', '/workspace/catalog/airports/HAN'] as $path) {
                $response = $this->get($path)->assertOk()->assertSee($label)->assertSee('10:15 10/11/2026');
                foreach (['Dữ liệu demo', 'Dữ liệu mô phỏng', 'Dữ liệu trình bày', 'Trạng thái phục vụ trình bày chức năng', 'Nguồn cập nhật'] as $text) {
                    $response->assertDontSee($text);
                }
                $response->assertDontSee('Đổi trạng thái');
            }
            $this->assertEquals($before, $db->table('airports')->where('code', 'HAN')->first());
        }
        $director = $this->staff('operations');
        AirportStatusAuthority::assign($director, 'airport_director', 'HAN', 'Phạm vi kiểm tra giao diện');
        $this->actingAs($director)->get('/workspace/catalog/airports/HAN')->assertOk()->assertSee('Đổi trạng thái')->assertSee('data-airport-status-form', false);
        $this->get('/workspace/catalog?flow=airports')->assertOk()->assertSee('edit_status=1#airport-operation', false);
        $this->assertDatabaseHas('airports', ['code' => 'HAN', 'operational_status_source' => 'SIMULATION', 'operational_status_simulation_key' => 'retained-origin'], 'ops');
    }

    public function test_unappointed_roles_including_technical_admin_cannot_update_directly(): void
    {
        foreach (['data_admin', 'admin', 'operations', 'ops_manager', 'airport_agent', 'ground_agent'] as $role) {
            $this->actingAs($this->staff($role))->postJson('/workspace/catalog/airports/HAN/status', $this->payload())->assertForbidden();
        }
        $this->assertSame(0, DB::connection('ops')->table('airports')->whereNotNull('operational_status')->count());
        $this->actingAs($this->staff())->get('/workspace/catalog/airports/HAN')->assertOk()->assertDontSee('data-airport-status-form', false);
    }

    public function test_airport_director_scope_read_only_catalog_and_confirmation_are_enforced(): void
    {
        $director = $this->staff('operations');
        AirportStatusAuthority::assign($director, 'airport_director', 'HAN', 'Bổ nhiệm giám đốc HAN');
        $this->actingAs($director)->getJson('/api/v1/workspace/catalog?flow=airports')->assertOk()->assertJsonPath('airports.total', 1);
        $this->get('/workspace/catalog/airports/HAN')->assertOk()->assertSee('data-airport-status-form', false)->assertDontSee('id="airport-create"', false)->assertDontSee('id="airport-edit"', false);
        $this->get('/workspace/catalog/airports/SGN')->assertNotFound();
        $this->get('/workspace/catalog?flow=infrastructure&airport=HAN')->assertForbidden();
        $this->postJson('/workspace/catalog/airports/SGN/status', $this->payload())->assertForbidden();
        $without = $this->payload();
        unset($without['confirmed']);
        $this->postJson('/workspace/catalog/airports/HAN/status', $without)->assertUnprocessable();
        $this->postJson('/workspace/catalog/airports/HAN/status', $this->payload())->assertOk();
        $this->assertDatabaseHas('airports', ['code' => 'HAN', 'operational_status' => 'HIGH', 'operational_status_source' => 'MANUAL', 'operational_status_updated_by' => $director->id], 'ops');
        $event = DB::connection('ops')->table('audit_logs')->where('action', 'airport.operational_status.updated')->first();
        $this->assertSame('MANUAL', json_decode($event->after_state, true)['operational_status_source']);
    }

    public function test_system_director_explicit_scopes_and_revocation(): void
    {
        $director = $this->staff('analyst');
        AirportStatusAuthority::assign($director, 'system_director', 'HAN', 'Phạm vi được giao HAN');
        $this->actingAs($director)->postJson('/workspace/catalog/airports/SGN/status', $this->payload())->assertForbidden();
        AirportStatusAuthority::assign($director, 'system_director', '*', 'Quyết định quản lý toàn hệ thống');
        $this->postJson('/workspace/catalog/airports/SGN/status', $this->payload())->assertOk();
        AirportStatusAuthority::assign($director, 'system_director', '*', 'Thu hồi quyền toàn hệ thống', false);
        $this->postJson('/workspace/catalog/airports/SGN/status', $this->payload())->assertForbidden();
        $this->postJson('/workspace/catalog/airports/HAN/status', $this->payload())->assertOk();
        $this->assertSame('analyst', $director->fresh()->role);
    }

    public function test_command_assigns_only_explicit_staff_scope_without_mutating_primary_role(): void
    {
        $user = $this->staff('operations');
        $this->artisan('airline:airport-status-authority', ['user' => $user->id, 'position' => 'airport_director', '--airport' => ['HAN'], '--reason' => 'Quyết định bổ nhiệm thử'])->assertSuccessful();
        $this->assertTrue(AirportStatusAuthority::canUpdate($user, 'HAN'));
        $this->assertFalse(AirportStatusAuthority::canUpdate($user, 'SGN'));
        $this->assertSame('operations', $user->fresh()->role);
    }

    public function test_simulation_is_explicit_deterministic_idempotent_and_has_four_statuses(): void
    {
        $db = DB::connection('ops');
        foreach (['BMV', 'CAH', 'CXR'] as $code) {
            $db->table('airports')->insert(['code' => $code, 'name' => 'Test '.$code, 'city' => 'Test', 'timezone' => 'Asia/Ho_Chi_Minh']);
        }
        $service = app(AirportStatusSimulation::class);
        $service->run(false);
        $this->assertSame(0, $db->table('airports')->whereNotNull('operational_status')->count());
        $flights = $db->table('flights')->orderBy('id')->get()->toJson();
        $service->run(true);
        $first = $db->table('airports')->orderBy('code')->get()->toJson();
        $this->assertCount(4, $db->table('airports')->distinct()->pluck('operational_status')->all());
        $this->assertSame([], $service->run(true)['changed']);
        $this->assertSame($first, $db->table('airports')->orderBy('code')->get()->toJson());
        $this->assertSame($flights, $db->table('flights')->orderBy('id')->get()->toJson());
        $this->actingAs($this->staff())->get('/workspace/catalog?flow=airports')->assertOk()->assertSee('Normal')->assertDontSee('>Demo<', false);
    }

    public function test_simulation_preserves_prior_manual_data_and_cleanup_preserves_later_confirmed_or_edited_data(): void
    {
        $db = DB::connection('ops');
        $user = $this->staff();
        $db->table('airports')->where('code', 'HAN')->update(['operational_status' => 'NORMAL', 'operational_status_note' => 'Xác nhận trước migration', 'operational_status_updated_by' => $user->id, 'operational_status_updated_at' => now()]);
        $before = $db->table('airports')->where('code', 'HAN')->first();
        $service = app(AirportStatusSimulation::class);
        $service->run(true);
        $this->assertEquals($before, $db->table('airports')->where('code', 'HAN')->first());
        AirportStatusAuthority::assign($user, 'airport_director', 'SGN', 'Quyết định giám đốc SGN');
        $this->actingAs($user)->postJson('/workspace/catalog/airports/SGN/status', $this->payload())->assertOk();
        $db->table('airports')->where('code', 'DAD')->update(['operational_status_note' => 'Có chỉnh sửa ngoài luồng']);
        $result = $service->run(true, true);
        $this->assertContains('DAD', $result['skipped']);
        $this->assertDatabaseHas('airports', ['code' => 'SGN', 'operational_status_source' => 'MANUAL', 'operational_status' => 'HIGH'], 'ops');
        $this->assertSame('NORMAL', $db->table('airports')->where('code', 'HAN')->value('operational_status'));
        $this->assertSame('Có chỉnh sửa ngoài luồng', $db->table('airports')->where('code', 'DAD')->value('operational_status_note'));
    }

    public function test_unchanged_simulation_can_be_cleared_and_production_is_rejected(): void
    {
        $service = app(AirportStatusSimulation::class);
        $service->run(true);
        $service->run(true, true);
        $this->assertSame(0, DB::connection('ops')->table('airports')->whereNotNull('operational_status')->count());
        $this->assertSame(3, DB::connection('ops')->table('audit_logs')->where('action', 'airport.operational_status.simulation.cleared')->count());
        $this->app['env'] = 'production';
        try {
            $service->run(true);
            $this->fail('Production simulation allowed');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_infrastructure_badge_priority_keeps_backend_fields_separate(): void
    {
        foreach ([null, 'NORMAL', 'RESTRICTED', 'MAINTENANCE', 'OUT_OF_SERVICE'] as $condition) {
            $this->assertSame('Ngừng hiệu lực', InfrastructureDisplay::state(false, $condition)['label']);
        }
        $this->assertSame('Chưa xác định', InfrastructureDisplay::state(true, null)['label']);
        foreach (['NORMAL' => 'Bình thường', 'RESTRICTED' => 'Hạn chế sử dụng', 'MAINTENANCE' => 'Đang bảo trì', 'OUT_OF_SERVICE' => 'Tạm ngừng hoạt động'] as $code => $label) {
            $this->assertSame($label, InfrastructureDisplay::state(true, $code)['label']);
        }
        $this->actingAs($this->staff())->get('/workspace/catalog?flow=infrastructure&airport=HAN')->assertOk()->assertSee('<th>Trạng thái</th>', false)->assertDontSee('<th>Hiệu lực</th>', false)->assertDontSee('<th>Tình trạng vận hành</th>', false);
    }

    public function test_location_dropdown_and_backend_use_the_same_compatible_parent_graph(): void
    {
        $user = $this->staff();
        $service = app(InfrastructureService::class);
        $create = fn ($code, $type, $extra = []) => $service->create($user, $extra + ['airport_code' => 'HAN', 'code' => $code, 'type_code' => $type, 'active' => true])->id;
        $terminal = $create('LOC-T1', 'TERMINAL', ['terminal_kind' => 'PASSENGER']);
        $level = $create('LOC-L1', 'ZONE', ['parent_id' => $terminal, 'zone_function' => 'LEVEL']);
        $apron = $create('LOC-APRON', 'APRON');
        $runway = $create('LOC-RWY', 'RUNWAY');
        $zone = $create('LOC-ZONE', 'ZONE');
        $child = $create('LOC-CHILD', 'ZONE', ['parent_id' => $zone]);
        $this->actingAs($user)->get('/workspace/catalog/infrastructure/new?airport=HAN&type=STAND')->assertOk()->assertViewHas('parentChoices', fn ($rows) => $rows->pluck('id')->all() === [$apron]);
        $this->get('/workspace/catalog/infrastructure/new?airport=HAN&type=RUNWAY')->assertOk()->assertDontSee('<select class="form-select" name="parent_id">', false);
        $this->get('/workspace/catalog/infrastructure/items/'.$zone)->assertOk()->assertViewHas('parentChoices', fn ($rows) => ! $rows->contains('id', $zone) && ! $rows->contains('id', $child));
        $gate = $create('LOC-GATE', 'GATE', ['parent_id' => $level]);
        $this->get('/workspace/catalog/infrastructure/items/'.$gate)->assertOk()->assertSee('LOC-T1')->assertSee('LOC-L1');
        $this->postJson('/workspace/catalog/infrastructure/items', ['airport_code' => 'HAN', 'code' => 'BAD-GATE', 'type_code' => 'GATE', 'active' => true, 'parent_id' => $runway])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    }

    public function test_all_ten_type_filters_remain_available_and_group_filter_is_database_scoped(): void
    {
        $this->actingAs($this->staff());
        foreach (InfrastructureCatalog::types() as $code => $type) {
            $this->get('/workspace/catalog?flow=infrastructure&airport=HAN&group='.$type['group_code'].'&kind='.$code)->assertOk()->assertViewHas('types', fn ($types) => $types->contains('code', $code))->assertViewHas('items', fn ($items) => $items->every(fn ($item) => $item->type_code === $code));
        }
    }
}
