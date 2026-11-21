<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Operations\InfrastructureNormalizer;
use App\Modules\Operations\InfrastructureService;
use App\Support\InfrastructureCatalog;
use App\Support\InfrastructureDisplay;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InfrastructureDisplayTest extends TestCase
{
    public function test_labels_are_derived_for_all_types_and_never_replace_stored_names(): void
    {
        foreach (InfrastructureCatalog::LABELS as $type => $label) {
            $record = (object) ['type_code' => $type, 'code' => 'C01', 'name' => null];
            $expected = match ($type) { 'GATE' => 'Cổng', 'STAND' => 'Vị trí đỗ', default => $label };
            $this->assertSame($expected.' C01', InfrastructureDisplay::name($record));
            $this->assertSame($expected.' C01', InfrastructureDisplay::tableName($record));
            $record->name = 'Tên riêng đã xác nhận';
            $this->assertSame($record->name, InfrastructureDisplay::name($record));
        }
    }

    public function test_locations_show_verified_chain_and_flag_invalid_relations(): void
    {
        $terminal = (object) ['id' => 1, 'code' => 'T1', 'name' => null, 'airport_code' => 'HAN', 'type_code' => 'TERMINAL', 'parent_id' => null];
        $zone = (object) ['id' => 2, 'code' => 'Z1', 'name' => 'Khu làm thủ tục', 'airport_code' => 'HAN', 'type_code' => 'ZONE', 'parent_id' => 1];
        $counter = (object) ['id' => 3, 'code' => 'C01', 'airport_code' => 'HAN', 'type_code' => 'COUNTER', 'parent_id' => 2];
        $parents = collect([$terminal, $zone])->keyBy('id');
        $this->assertSame('Nhà ga T1 / Khu làm thủ tục', InfrastructureDisplay::location($counter, $parents));
        $counter->parent_id = null;
        $this->assertSame('Sân bay HAN', InfrastructureDisplay::location($counter, $parents));
        $counter->airport_code = null;
        $this->assertStringContainsString('cần kiểm tra', InfrastructureDisplay::location($counter, $parents));
        $counter->airport_code = 'HAN';
        $counter->parent_id = 2;
        $zone->airport_code = 'SGN';
        $this->assertSame('Liên kết khu vực cần kiểm tra', InfrastructureDisplay::location($counter, $parents));
        $zone->airport_code = 'HAN';
        $zone->parent_id = 2;
        $this->assertSame('Liên kết khu vực cần kiểm tra', InfrastructureDisplay::location($counter, $parents));
        $zone->parent_id = 999;
        $this->assertSame('Liên kết khu vực cần kiểm tra', InfrastructureDisplay::location($counter, $parents));
    }

    public function test_display_is_read_only_and_location_changes_use_existing_independent_approval(): void
    {
        app(InfrastructureNormalizer::class)->apply();
        $db = DB::connection('ops');
        $user = User::factory()->create(['role' => 'data_admin']);
        $reviewer = User::factory()->create(['role' => 'data_admin']);
        $service = app(InfrastructureService::class);
        $terminal = $service->create($user, ['airport_code' => 'HAN', 'type_code' => 'TERMINAL', 'code' => 'DISPLAY-T1', 'active' => true])->id;
        $counter = $db->table('airport_infrastructure')->where('airport_code', 'HAN')->where('type_code', 'COUNTER')->first();
        $before = $db->table('airport_infrastructure')->orderBy('id')->get()->toJson();
        $resources = $db->table('airport_resources')->orderBy('id')->get()->toJson();
        $allocations = $db->table('resource_allocations')->orderBy('id')->get()->toJson();
        $this->actingAs($user)->get('/workspace/catalog?flow=infrastructure&airport=HAN')->assertOk()->assertSee('Sân bay HAN')->assertDontSee('Chưa khai báo tên')->assertDontSee('Chưa khai báo khu vực');
        $url = '/workspace/catalog/infrastructure/items/'.$counter->id;
        $this->get($url)->assertOk()->assertSee('Quầy làm thủ tục '.$counter->code)->assertSee('Cập nhật vị trí / hồ sơ');
        $this->assertSame($before, $db->table('airport_infrastructure')->orderBy('id')->get()->toJson());
        $proposal = $this->postJson($url.'/changes', ['active' => true, 'parent_id' => $terminal, 'reason' => 'Vị trí đã được xác minh'])->assertCreated()->json('id');
        $this->assertDatabaseHas('airport_infrastructure', ['id' => $counter->id, 'parent_id' => null, 'name' => null], 'ops');
        $this->postJson('/workspace/actions/master_approve', ['change_id' => $proposal])->assertConflict();
        $this->actingAs($reviewer)->postJson('/workspace/actions/master_approve', ['change_id' => $proposal])->assertSuccessful();
        $this->get($url)->assertOk()->assertSee('Nhà ga DISPLAY-T1');
        $this->assertDatabaseHas('airport_infrastructure', ['id' => $counter->id, 'parent_id' => $terminal, 'name' => null], 'ops');
        $this->assertSame($resources, $db->table('airport_resources')->orderBy('id')->get()->toJson());
        $this->assertSame($allocations, $db->table('resource_allocations')->orderBy('id')->get()->toJson());
        $this->assertSame(1, $db->table('audit_logs')->where('action', 'infrastructure.updated')->where('object_id', $counter->id)->count());
        $this->actingAs(User::factory()->create(['role' => 'operations', 'airport_code' => 'HAN']))->get($url)->assertForbidden();
        $this->postJson($url.'/changes', ['active' => true, 'parent_id' => $terminal, 'reason' => 'Không đủ thẩm quyền'])->assertForbidden();
    }
}
