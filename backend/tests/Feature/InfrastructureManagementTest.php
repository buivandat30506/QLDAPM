<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Operations\InfrastructureNormalizer;
use App\Support\InfrastructureCatalog;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InfrastructureManagementTest extends TestCase
{
    public function test_old_pending_terminal_snapshot_remains_approvable_after_nullable_schema_extension(): void
    {
        $author = User::factory()->create(['role'=>'data_admin']);
        $reviewer = User::factory()->create(['role'=>'data_admin']);
        $this->actingAs($author);
        $base = ['airport_code'=>'HAN','code'=>'OLD-PENDING','type_code'=>'TERMINAL','active'=>true];
        $id = $this->postJson('/workspace/catalog/infrastructure/items',$base)->assertCreated()->json('id');
        $change = $this->postJson('/workspace/catalog/infrastructure/items/'.$id.'/changes',$base+['name'=>'Nhà ga đã xác nhận','reason'=>'Đề nghị trước khi mở rộng'])->assertCreated()->json('id');
        $db = DB::connection('ops');
        $before = json_decode($db->table('master_changes')->where('id',$change)->value('before_data'),true);
        unset($before['profiles']['terminals']['terminal_scope']);
        $db->table('master_changes')->where('id',$change)->update(['before_data'=>json_encode($before)]);
        $this->actingAs($reviewer)->postJson('/workspace/actions/master_approve',['change_id'=>$change])->assertSuccessful();
        $this->assertDatabaseHas('airport_infrastructure',['id'=>$id,'name'=>'Nhà ga đã xác nhận'],'ops');
    }

    public function test_all_ten_types_create_show_filter_edit_and_approve_real_profiles(): void
    {
        app(InfrastructureNormalizer::class)->apply();
        $db = DB::connection('ops');
        $author = User::factory()->create(['role' => 'data_admin']);
        $reviewer = User::factory()->create(['role' => 'data_admin']);
        $oldResources = $db->table('airport_resources')->get()->keyBy('id');
        $allocations = $db->table('resource_allocations')->get()->toJson();
        $ids = [];
        foreach (['TERMINAL','ZONE','APRON','STAND','GATE','COUNTER','JET_BRIDGE','BELT','RUNWAY','TAXIWAY'] as $type) {
            $attributes = match ($type) {
                'TERMINAL' => ['terminal_kind'=>'PASSENGER', 'terminal_scope'=>'INTERNATIONAL', 'serves_departures'=>true, 'serves_arrivals'=>false],
                'ZONE' => ['parent_id'=>$ids['TERMINAL'], 'zone_function'=>'CHECKIN'],
                'STAND' => ['parent_id'=>$ids['APRON'], 'compatibility_notes'=>'Thông số do đơn vị quản lý cung cấp'],
                'GATE' => ['parent_id'=>$ids['ZONE'], 'serves_departures'=>true, 'serves_arrivals'=>true, 'stand_ids'=>[$ids['STAND']]],
                'COUNTER' => ['parent_id'=>$ids['ZONE'], 'counter_service'=>'COMBINED'],
                'JET_BRIDGE' => ['parent_id'=>$ids['ZONE'], 'asset_reference'=>'TEST-ASSET-1', 'gate_ids'=>[$ids['GATE']], 'stand_ids'=>[$ids['STAND']]],
                'BELT' => ['parent_id'=>$ids['ZONE'], 'baggage_flow'=>'BOTH'],
                'RUNWAY' => ['length_m'=>'1200.00', 'width_m'=>'30.00', 'directions'=>['01','19'], 'limitations'=>'Giới hạn được cung cấp'],
                'TAXIWAY' => ['taxiway_function'=>'GENERAL'],
                default => [],
            };
            $input = ['airport_code'=>'HAN', 'type_code'=>$type, 'code'=>'COMPLETE-'.$type, 'active'=>true, 'technical_state'=>'NORMAL', 'source_reference'=>'Tài liệu kiểm thử cô lập'] + $attributes;
            $this->actingAs($author)->get('/workspace/catalog/infrastructure/new?airport=HAN&type='.$type)->assertOk()->assertSee(InfrastructureCatalog::label($type));
            $id = $this->postJson('/workspace/catalog/infrastructure/items', $input)->assertCreated()->json('id');
            $ids[$type] = $id;
            $url = '/workspace/catalog/infrastructure/items/'.$id;
            $this->get($url)->assertOk()->assertSee('COMPLETE-'.$type)->assertDontSee('Dữ liệu demo')->assertDontSee('Dữ liệu mô phỏng');
            $this->get('/workspace/catalog?flow=infrastructure&airport=HAN&kind='.$type.'&q=COMPLETE-')->assertOk()->assertViewHas('items', fn ($items) => $items->total() === 1 && $items->first()->canonical_id === $id);
            if (isset(InfrastructureCatalog::PROFILE_TABLES[$type])) {
                $profile = array_diff_key($attributes, array_flip(['parent_id','stand_ids','gate_ids','directions']));
                $this->assertDatabaseHas('infrastructure_'.InfrastructureCatalog::PROFILE_TABLES[$type], ['infrastructure_id'=>$id]+$profile, 'ops');
            }
            $proposal = $this->postJson($url.'/changes', $input + ['name'=>'Tên đã xác nhận '.$type, 'reason'=>'Đơn vị quản lý xác nhận tên'])->assertCreated()->json('id');
            $this->get($url)->assertOk()->assertSee('Xem nội dung thay đổi')->assertSee('Tên đã xác nhận '.$type);
            $this->assertDatabaseHas('airport_infrastructure', ['id'=>$id, 'name'=>null], 'ops');
            $this->postJson('/workspace/actions/master_approve', ['change_id'=>$proposal])->assertConflict();
            $this->actingAs($reviewer)->postJson('/workspace/actions/master_approve', ['change_id'=>$proposal])->assertSuccessful();
            $this->assertDatabaseHas('airport_infrastructure', ['id'=>$id, 'name'=>'Tên đã xác nhận '.$type], 'ops');
        }
        $this->assertDatabaseHas('infrastructure_links', ['source_id'=>$ids['JET_BRIDGE'], 'target_id'=>$ids['GATE'], 'relation'=>'JET_BRIDGE_GATE'], 'ops');
        $this->assertDatabaseHas('infrastructure_links', ['source_id'=>$ids['JET_BRIDGE'], 'target_id'=>$ids['STAND'], 'relation'=>'JET_BRIDGE_STAND'], 'ops');
        $this->assertSame(10, $db->table('infrastructure_sources')->where('entry_method','AUTHORIZED_ENTRY')->count());
        foreach ($oldResources as $id=>$row) {
            $this->assertEquals($row, $db->table('airport_resources')->find($id));
        }
        $this->assertSame($allocations, $db->table('resource_allocations')->get()->toJson());
        $this->assertSame(10, $db->table('infrastructure_types')->count());
    }

    public function test_bridge_links_and_asset_identity_are_validated_and_permissions_are_preserved(): void
    {
        $author = User::factory()->create(['role'=>'data_admin']);
        $this->actingAs($author);
        $base = ['airport_code'=>'HAN', 'active'=>true];
        $gate = $this->postJson('/workspace/catalog/infrastructure/items', $base+['code'=>'LINK-GATE','type_code'=>'GATE'])->assertCreated()->json('id');
        $other = $this->postJson('/workspace/catalog/infrastructure/items', ['airport_code'=>'SGN','active'=>true,'code'=>'OTHER-GATE','type_code'=>'GATE'])->assertCreated()->json('id');
        $bridge = $base+['code'=>'BRIDGE-1','type_code'=>'JET_BRIDGE','asset_reference'=>'ASSET-1'];
        $this->postJson('/workspace/catalog/infrastructure/items', $bridge+['gate_ids'=>[$other]])->assertUnprocessable();
        $this->postJson('/workspace/catalog/infrastructure/items', $bridge+['stand_ids'=>[$gate]])->assertUnprocessable();
        $this->postJson('/workspace/catalog/infrastructure/items', $base+['code'=>'WRONG-ATTR','type_code'=>'COUNTER','gate_ids'=>[$gate]])->assertUnprocessable();
        $id = $this->postJson('/workspace/catalog/infrastructure/items', $bridge+['gate_ids'=>[$gate]])->assertCreated()->json('id');
        $this->postJson('/workspace/catalog/infrastructure/items', array_replace($bridge,['code'=>'BRIDGE-2']))->assertConflict();
        $this->postJson('/workspace/catalog/infrastructure/items', $bridge)->assertConflict();
        $this->actingAs(User::factory()->create(['role'=>'operations','airport_code'=>'HAN']));
        $this->postJson('/workspace/catalog/infrastructure/items', $base+['code'=>'FORBIDDEN','type_code'=>'BELT'])->assertForbidden();
        $this->postJson('/workspace/catalog/infrastructure/items/'.$id.'/changes', $bridge+['reason'=>'Không có thẩm quyền'])->assertForbidden();
        $this->get('/workspace/catalog/infrastructure/import?airport=HAN')->assertForbidden();
    }

    public function test_allocated_resource_cannot_become_unavailable_and_new_allocations_observe_state(): void
    {
        $db = DB::connection('ops');
        $author = User::factory()->create(['role'=>'data_admin']);
        $reviewer = User::factory()->create(['role'=>'data_admin']);
        $this->actingAs($author);
        $input = ['airport_code'=>'HAN','code'=>'GUARDED','type_code'=>'GATE','active'=>true,'technical_state'=>'NORMAL'];
        $id = $this->postJson('/workspace/catalog/infrastructure/items',$input)->assertCreated()->json('id');
        $resource = $db->table('airport_resources')->where('infrastructure_id',$id)->first();
        $db->table('resource_allocations')->insert(['resource_id'=>$resource->id,'flight_id'=>1,'starts_at'=>now(),'ends_at'=>now()->addHour(),'reason'=>'Fixture cô lập','actor_id'=>$author->id]);
        $before = $db->table('resource_allocations')->get()->toJson();
        foreach (['MAINTENANCE','OUT_OF_SERVICE'] as $state) {
            $change = $this->actingAs($author)->postJson('/workspace/catalog/infrastructure/items/'.$id.'/changes',array_replace($input,['technical_state'=>$state,'reason'=>'Đề nghị thay đổi trạng thái']))->assertCreated()->json('id');
            $this->actingAs($reviewer)->postJson('/workspace/actions/master_approve',['change_id'=>$change])->assertConflict();
        }
        $this->assertSame($before,$db->table('resource_allocations')->get()->toJson());
        $this->assertDatabaseHas('airport_infrastructure',['id'=>$id,'technical_state'=>'NORMAL'],'ops');
        // A separate unallocated resource can be placed under maintenance through independent approval.
        $input['code'] = 'UNALLOCATED';
        $id = $this->actingAs($author)->postJson('/workspace/catalog/infrastructure/items',$input)->assertCreated()->json('id');
        $change = $this->postJson('/workspace/catalog/infrastructure/items/'.$id.'/changes',array_replace($input,['technical_state'=>'MAINTENANCE','reason'=>'Bảo trì đã được xác nhận']))->assertCreated()->json('id');
        $this->actingAs($reviewer)->postJson('/workspace/actions/master_approve',['change_id'=>$change])->assertSuccessful();
        $resource = $db->table('airport_resources')->where('infrastructure_id',$id)->first();
        $this->actingAs(User::factory()->create(['role'=>'operations','airport_code'=>'HAN']))->postJson('/workspace/infrastructure-allocations',['flight_id'=>1,'resource_id'=>$resource->id,'starts_at'=>now()->toIso8601String(),'ends_at'=>now()->addHours(3)->toIso8601String(),'reason'=>'Không phân bổ hạ tầng bảo trì'])->assertConflict();
        $this->assertSame($before,$db->table('resource_allocations')->get()->toJson());
    }
}
