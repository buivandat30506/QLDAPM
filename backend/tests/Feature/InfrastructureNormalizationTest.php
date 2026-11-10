<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Operations\InfrastructureNormalizer;
use App\Modules\Operations\InfrastructureService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InfrastructureNormalizationTest extends TestCase
{
    private function user(string $role = 'data_admin', string $airport = 'HAN'): User
    {
        return User::factory()->create(['role' => $role, 'airport_code' => $airport]);
    }

    private function input(string $code, string $type, array $extra = []): array
    {
        return $extra + ['airport_code' => 'HAN', 'code' => $code, 'name' => 'Hạ tầng '.$code, 'type_code' => $type, 'active' => true];
    }

    private function create(string $code, string $type, array $extra = []): int
    {
        return app(InfrastructureService::class)->create($this->user(), $this->input($code, $type, $extra))->id;
    }

    public function test_mapping_preserves_legacy_ids_and_is_idempotent_without_inventing_parents(): void
    {
        $db = DB::connection('ops');
        $before = $db->table('airport_resources')->orderBy('id')->get(['id', 'airport_code', 'code', 'type', 'active'])->toJson();
        $normalizer = app(InfrastructureNormalizer::class);
        $this->assertSame([], $normalizer->inspect()['conflicts']);
        $normalizer->apply();
        $this->assertSame($before, $db->table('airport_resources')->orderBy('id')->get(['id', 'airport_code', 'code', 'type', 'active'])->toJson());
        $this->assertSame(12, $db->table('airport_infrastructure')->count());
        $this->assertSame(12, $db->table('airport_infrastructure')->whereNull('parent_id')->whereNull('technical_state')->count());
        $canonical = $db->table('airport_infrastructure')->orderBy('id')->get()->toJson();
        $this->assertSame(0, $normalizer->apply()['pending']);
        $this->assertSame($canonical, $db->table('airport_infrastructure')->orderBy('id')->get()->toJson());
    }

    public function test_ambiguous_terminal_sources_are_not_automatically_merged_or_duplicated(): void
    {
        DB::connection('ops')->table('airport_areas')->insert(['airport_code' => 'HAN', 'code' => 'T1', 'name' => 'Nhà ga từ nguồn khác', 'type' => 'TERMINAL', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertNotEmpty(app(InfrastructureNormalizer::class)->inspect()['conflicts']);
        try {
            app(InfrastructureNormalizer::class)->apply();
            $this->fail('Ambiguous sources accepted');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame(0, DB::connection('ops')->table('airport_infrastructure')->count());
        $this->actingAs($this->user())->get('/workspace/catalog?flow=infrastructure&airport=HAN')->assertOk()->assertViewHas('items', fn ($items) => $items->where('code', 'T1')->count() === 0);
    }

    public function test_one_gate_can_serve_both_flows_and_links_to_a_real_stand(): void
    {
        $apron = $this->create('NEW-APRON', 'APRON');
        $stand = $this->create('NEW-STAND', 'STAND', ['parent_id' => $apron]);
        $gate = $this->create('NEW-GATE', 'GATE', ['serves_departures' => true, 'serves_arrivals' => true, 'stand_ids' => [$stand]]);
        $this->assertDatabaseHas('infrastructure_gates', ['infrastructure_id' => $gate, 'serves_departures' => true, 'serves_arrivals' => true], 'ops');
        $this->assertSame(1, DB::connection('ops')->table('airport_infrastructure')->where('code', 'NEW-GATE')->count());
        $this->assertSame(1, DB::connection('ops')->table('airport_resources')->where('infrastructure_id', $gate)->count());
        $this->assertDatabaseHas('infrastructure_links', ['source_id' => $gate, 'target_id' => $stand, 'relation' => 'GATE_STAND'], 'ops');
        $this->actingAs($this->user())->get('/workspace/catalog/infrastructure/items/'.$gate)->assertOk()->assertSee('NEW-STAND');
    }

    public function test_runway_directions_do_not_create_physical_runways_or_schedules(): void
    {
        $author = $this->user();
        $service = app(InfrastructureService::class);
        $id = $service->create($author, $this->input('RWY-ONE', 'RUNWAY', ['length_m' => 3000, 'width_m' => 45, 'directions' => ['09']]))->id;
        $directionId = DB::connection('ops')->table('runway_directions')->where('runway_id', $id)->value('id');
        $this->assertSame(1, DB::connection('ops')->table('runway_directions')->count());
        $change = $service->propose($author, $id, $this->input('RWY-ONE', 'RUNWAY', ['length_m' => 3000, 'width_m' => 45, 'directions' => ['09', '27']]), 'Bổ sung hướng đã xác nhận')->id;
        $service->approve($this->user(), $change);
        $this->assertSame(1, DB::connection('ops')->table('airport_infrastructure')->where('type_code', 'RUNWAY')->count());
        $this->assertSame($directionId, DB::connection('ops')->table('runway_directions')->where('runway_id', $id)->where('designator', '09')->value('id'));
        $this->assertSame(2, DB::connection('ops')->table('runway_directions')->where('runway_id', $id)->count());
        $this->assertSame(0, DB::connection('ops')->table('airport_resources')->where('infrastructure_id', $id)->count());
        $this->assertSame(0, DB::connection('ops')->table('resource_allocations')->count());
    }

    public function test_parent_cycles_cross_airport_links_and_invalid_profiles_are_rejected(): void
    {
        $zone = $this->create('ZONE-A', 'ZONE');
        $child = $this->create('ZONE-B', 'ZONE', ['parent_id' => $zone]);
        $this->actingAs($this->user())->postJson('/workspace/catalog/infrastructure/items/'.$zone.'/changes', $this->input('ZONE-A', 'ZONE', ['parent_id' => $child, 'reason' => 'Tạo chu trình không hợp lệ']))->assertUnprocessable();
        $this->postJson('/workspace/catalog/infrastructure/items', $this->input('BAD-RWY', 'RUNWAY', ['serves_arrivals' => true]))->assertUnprocessable();
        $other = app(InfrastructureService::class)->create($this->user(), $this->input('SGN-ZONE', 'ZONE', ['airport_code' => 'SGN']))->id;
        $this->postJson('/workspace/catalog/infrastructure/items', $this->input('BAD-PARENT', 'ZONE', ['parent_id' => $other]))->assertUnprocessable();
        try {
            DB::connection('ops')->table('airport_infrastructure')->where('id', $zone)->update(['parent_id' => $other]);
            $this->fail('Cross-airport FK allowed');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->getCode());
        }
        $this->postJson('/workspace/catalog/infrastructure/items', $this->input('ZONE-A', 'GATE'))->assertConflict();
    }

    public function test_changes_need_independent_approval_and_keep_history(): void
    {
        $author = $this->user();
        $service = app(InfrastructureService::class);
        $id = $service->create($author, $this->input('CHG-ZONE', 'ZONE'))->id;
        $change = $service->propose($author, $id, $this->input('CHG-ZONE', 'ZONE', ['name' => 'Tên đã duyệt', 'technical_state' => 'MAINTENANCE']), 'Cập nhật thông tin thực tế')->id;
        $this->actingAs($author)->postJson('/api/v1/actions/master_approve', ['change_id' => $change])->assertConflict();
        $this->actingAs($this->user())->postJson('/api/v1/actions/master_approve', ['change_id' => $change])->assertOk();
        $this->assertDatabaseHas('airport_infrastructure', ['id' => $id, 'name' => 'Tên đã duyệt', 'technical_state' => 'MAINTENANCE', 'version' => 2], 'ops');
        $this->assertDatabaseHas('audit_logs', ['action' => 'infrastructure.updated', 'object_id' => (string) $id], 'ops');
        $this->assertSame(0, DB::connection('ops')->table('airports')->whereNotNull('operational_status')->count());
        $stale = $service->propose($author, $id, $this->input('CHG-ZONE', 'ZONE', ['name' => 'Không được ghi đè']), 'Đề nghị trước thay đổi khác')->id;
        DB::connection('ops')->table('airport_infrastructure')->where('id', $id)->increment('version');
        $this->actingAs($this->user())->postJson('/api/v1/actions/master_approve', ['change_id' => $stale])->assertConflict();
    }

    public function test_legacy_resource_and_area_apis_keep_ids_and_enforce_consistent_identity(): void
    {
        $this->actingAs($this->user());
        $resource = $this->postJson('/api/v1/actions/resource', ['airport_code' => 'HAN', 'code' => 'LEG-GATE', 'type' => 'GATE'])->assertOk()->json('id');
        $this->assertNotNull(DB::connection('ops')->table('airport_resources')->where('id', $resource)->value('infrastructure_id'));
        $this->postJson('/api/v1/actions/airport_area', ['airport_code' => 'HAN', 'code' => 'LEG-GATE', 'name' => 'Trùng đối tượng', 'type' => 'GATE'])->assertConflict();
        $area = $this->postJson('/api/v1/actions/airport_area', ['airport_code' => 'HAN', 'code' => 'LEG-ZONE', 'name' => 'Khu thực tế', 'type' => 'ZONE'])->assertOk()->json('id');
        $this->postJson('/api/v1/actions/airport_area', ['id' => $area, 'airport_code' => 'HAN', 'parent_id' => $area, 'code' => 'LEG-ZONE', 'name' => 'Sai quan hệ', 'type' => 'ZONE'])->assertUnprocessable();
        $this->assertDatabaseHas('airport_areas', ['id' => $area, 'parent_id' => null], 'ops');
        $this->getJson('/api/v1/workspace/catalog?flow=infrastructure&airport=HAN')->assertOk()->assertJsonStructure(['choices', 'airport', 'source', 'items', 'parents', 'types', 'context']);
    }

    public function test_new_schedule_path_uses_supported_legacy_ids_and_existing_overlap_rules(): void
    {
        app(InfrastructureNormalizer::class)->apply();
        $db = DB::connection('ops');
        $this->actingAs($this->user('operations'));
        $flight = $db->table('flights')->where('id', 1)->first();
        $window = ['flight_id' => 1, 'starts_at' => now()->subMinutes(5)->toIso8601String(), 'ends_at' => now()->addHours(3)->toIso8601String(), 'reason' => 'Phân bổ theo quy tắc cũ'];
        foreach (['GATE', 'COUNTER', 'STAND'] as $type) {
            $resource = $db->table('airport_resources')->where('airport_code', 'HAN')->where('type', $type)->first();
            $this->postJson('/workspace/infrastructure-allocations', $window + ['resource_id' => $resource->id])->assertOk();
            $this->postJson('/workspace/infrastructure-allocations', $window + ['resource_id' => $resource->id])->assertConflict();
        }
        $wrong = $db->table('airport_resources')->where('airport_code', 'SGN')->where('type', 'GATE')->first();
        $this->postJson('/workspace/infrastructure-allocations', $window + ['resource_id' => $wrong->id])->assertConflict();
        $terminal = $db->table('airport_resources')->where('airport_code', 'HAN')->where('type', 'TERMINAL')->first();
        $this->postJson('/workspace/infrastructure-allocations', $window + ['resource_id' => $terminal->id])->assertUnprocessable();
        $this->assertSame(3, $db->table('resource_allocations')->count());
    }

    public function test_read_only_baggage_references_are_scoped_and_do_not_assign_belts(): void
    {
        $zone = $this->create('BAG-ZONE', 'ZONE', ['zone_function' => 'BAGGAGE_CLAIM']);
        $belt = $this->create('ARR-BELT', 'BELT', ['parent_id' => $zone, 'baggage_flow' => 'ARRIVAL']);
        $this->actingAs($this->user('airport_agent'))->getJson('/api/v1/infrastructure-reference/baggage?airport=HAN')->assertOk()->assertJsonPath('items.total', 2);
        $this->get('/workspace/infrastructure-reference/baggage?airport=SGN')->assertNotFound();
        $this->assertSame(0, DB::connection('ops')->table('resource_allocations')->count());
        $this->assertDatabaseHas('infrastructure_belts', ['infrastructure_id' => $belt, 'baggage_flow' => 'ARRIVAL'], 'ops');
        $this->actingAs($this->user('finance'))->get('/workspace/infrastructure-reference/baggage?airport=HAN')->assertForbidden();
    }

    public function test_ground_location_reference_does_not_duplicate_or_assign_equipment(): void
    {
        $location = $this->create('GROUND-APRON', 'APRON');
        $this->actingAs($this->user('operations'));
        $id = $this->postJson('/api/v1/actions/ground_equipment', ['airport_code' => 'HAN', 'code' => 'TRUCK-REF', 'name' => 'Thiết bị đang có', 'type' => 'TRUCK'])->assertOk()->json('id');
        $this->postJson('/workspace/ground-equipment/'.$id.'/location', ['location_infrastructure_id' => $location])->assertOk();
        $this->assertDatabaseHas('ground_equipment', ['id' => $id, 'status' => 'AVAILABLE', 'location_infrastructure_id' => $location], 'ops');
        $this->assertSame(1, DB::connection('ops')->table('ground_equipment')->count());
        $this->assertSame(0, DB::connection('ops')->table('ground_task_equipment')->count());
        $migration = require database_path('migrations/2026_11_09_010000_normalize_airport_infrastructure.php');
        try {
            $migration->down();
            $this->fail('Rollback would discard equipment location');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('equipment location references', $e->getMessage());
        }
        $this->actingAs($this->user('ground_agent', 'SGN'))->postJson('/workspace/ground-equipment/'.$id.'/location', ['location_infrastructure_id' => $location])->assertForbidden();
    }

    public function test_partial_profile_changes_preserve_omitted_values_and_allow_explicit_clear(): void
    {
        $author = $this->user();
        $reviewer = $this->user();
        $service = app(InfrastructureService::class);
        foreach (['STAND' => ['compatibility_notes', 'stands'], 'JET_BRIDGE' => ['asset_reference', 'assets']] as $type => [$field, $profile]) {
            $input = $this->input('PARTIAL-'.$type, $type);
            $id = $service->create($author, $input + [$field => 'Keep this reference'])->id;
            $change = $service->propose($author, $id, $input, 'Only common fields submitted')->id;
            $service->approve($reviewer, $change);
            $this->assertDatabaseHas('infrastructure_'.$profile, ['infrastructure_id' => $id, $field => 'Keep this reference'], 'ops');
            $clear = $service->propose($author, $id, $input + [$field => null], 'Explicitly clear optional reference')->id;
            $service->approve($reviewer, $clear);
            $this->assertDatabaseHas('infrastructure_'.$profile, ['infrastructure_id' => $id, $field => null], 'ops');
        }
    }

    public function test_rollback_refuses_to_destroy_new_business_records(): void
    {
        $id = $this->create('KEEP-RWY', 'RUNWAY');
        $migration = require database_path('migrations/2026_11_09_010000_normalize_airport_infrastructure.php');
        try {
            $migration->down();
            $this->fail('Unsafe rollback accepted');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Rollback refused', $e->getMessage());
        }
        $this->assertDatabaseHas('airport_infrastructure', ['id' => $id, 'code' => 'KEEP-RWY'], 'ops');
    }
}
