<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Operations\InfrastructureFixtures;
use App\Modules\Operations\InfrastructureNormalizer;
use App\Modules\Operations\InfrastructureService;
use App\Support\InfrastructureCatalog;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class InfrastructureFixturesTest extends TestCase
{
    private function setupAirport(): User
    {
        config(['infrastructure.local_fixtures_enabled'=>true]);
        $db = DB::connection('ops');
        $db->table('airports')->insert(['code'=>'BMV','name'=>'Buôn Ma Thuột','city'=>'Buôn Ma Thuột','timezone'=>'Asia/Ho_Chi_Minh']);
        foreach (['T1','T2'] as $code) {
            $db->table('airport_resources')->insert(['airport_code'=>'BMV','code'=>$code,'type'=>'TERMINAL','active'=>true]);
        }
        foreach (['GATE'=>'A','COUNTER'=>'C','STAND'=>'P'] as $type=>$prefix) {
            for ($i=1;$i<=8;$i++) {
                $db->table('airport_resources')->insert(['airport_code'=>'BMV','code'=>$prefix.str_pad((string)$i,2,'0',STR_PAD_LEFT),'type'=>$type,'active'=>true]);
            }
        }
        app(InfrastructureNormalizer::class)->apply();
        return User::factory()->create(['role'=>'data_admin']);
    }

    private function rejected(callable $action, int $status): void
    {
        try {
            $action();
            $this->fail('Expected a guarded rejection.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame($status,$e->getStatusCode());
        }
    }

    public function test_full_bmv_lifecycle_is_opt_in_idempotent_and_preserves_legacy_data(): void
    {
        $actor=$this->setupAirport();$service=app(InfrastructureFixtures::class);$db=DB::connection('ops');
        $baseline=[];
        foreach (['airport_infrastructure','airport_resources','resource_allocations','flights'] as $table) {
            $baseline[$table]=$db->table($table)->orderBy('id')->get()->toJson();
        }
        $this->assertSame(12,$service->run('BMV')['planned']);
        $this->assertSame(0,$db->table('infrastructure_fixture_sets')->count());
        $result=$service->run('BMV',true,false,$actor);$ids=$result['ids'];
        $this->assertSame(12,$result['created']);
        $this->assertSame(38,$db->table('airport_infrastructure')->where('airport_code','BMV')->count());
        $this->assertSame(10,$db->table('airport_infrastructure')->where('airport_code','BMV')->distinct()->count('type_code'));
        $this->assertSame(12,$db->table('infrastructure_sources')->where('entry_method','LOCAL_FIXTURE')->count());
        $this->actingAs($actor);
        foreach ($ids as $id) {
            $row=$db->table('airport_infrastructure')->find($id);
            $this->get('/workspace/catalog/infrastructure/items/'.$id)->assertOk()->assertSee($row->name)->assertDontSee('Dữ liệu demo')->assertDontSee('Dữ liệu mô phỏng');
        }
        foreach (InfrastructureCatalog::LABELS as $type=>$label) {
            $this->get('/workspace/catalog?flow=infrastructure&airport=BMV&kind='.$type)->assertOk()->assertViewHas('items',fn($items)=>$items->total()>0 && $items->every(fn($item)=>$item->type_code===$type));
        }
        $this->get('/workspace/catalog?flow=infrastructure&airport=BMV&q=LCL-&page=1')->assertOk()->assertViewHas('items',fn($items)=>$items->total()===12);
        $this->get('/workspace/catalog?flow=infrastructure&airport=BMV&page=2')->assertOk()->assertViewHas('items',fn($items)=>$items->currentPage()===2);
        $this->assertDatabaseHas('airport_infrastructure',['id'=>$ids['claim'],'parent_id'=>$ids['terminal']],'ops');
        $this->assertDatabaseHas('airport_infrastructure',['id'=>$ids['belt'],'parent_id'=>$ids['claim']],'ops');
        $this->assertDatabaseHas('airport_infrastructure',['id'=>$ids['stand'],'parent_id'=>$ids['apron']],'ops');
        foreach (['runway','taxiway','apron','terminal'] as $alias) {
            $this->assertDatabaseHas('airport_infrastructure',['id'=>$ids[$alias],'parent_id'=>null],'ops');
        }
        $this->assertDatabaseHas('infrastructure_links',['source_id'=>$ids['bridge'],'target_id'=>$ids['gate'],'relation'=>'JET_BRIDGE_GATE'],'ops');
        $this->assertDatabaseHas('infrastructure_links',['source_id'=>$ids['bridge'],'target_id'=>$ids['stand'],'relation'=>'JET_BRIDGE_STAND'],'ops');
        $this->assertDatabaseHas('infrastructure_runways',['infrastructure_id'=>$ids['runway'],'length_m'=>null,'width_m'=>null],'ops');
        $again=$service->run('BMV',true,false,$actor);
        $this->assertSame(0,$again['created']);$this->assertSame($ids,$again['ids']);
        $this->assertSame(12,$service->run('BMV',false,true)['eligible']);
        $this->assertSame(12,$service->run('BMV',true,true,$actor)['deleted']);
        foreach ($baseline as $table=>$json) {
            $this->assertSame($json,$db->table($table)->orderBy('id')->get()->toJson());
        }
        $this->assertDatabaseHas('infrastructure_fixture_sets',['key'=>InfrastructureFixtures::VERSION.':BMV','status'=>'CLEARED'],'ops');
        $this->assertSame(0,$service->run('BMV',true,true,$actor)['deleted']);
    }

    public function test_edits_are_never_overwritten_or_removed(): void
    {
        $actor=$this->setupAirport();$service=app(InfrastructureFixtures::class);$db=DB::connection('ops');
        $ids=$service->run('BMV',true,false,$actor)['ids'];
        $bridge=$db->table('airport_infrastructure')->find($ids['bridge']);
        $proposal=app(InfrastructureService::class)->propose($actor,$bridge->id,['airport_code'=>'BMV','code'=>$bridge->code,'type_code'=>$bridge->type_code,'active'=>true,'name'=>'Tên do người dùng cập nhật'],'Người dùng đã bổ sung tên')->id;
        $reviewer=User::factory()->create(['role'=>'data_admin']);
        app(InfrastructureService::class)->approve($reviewer,$proposal);
        $this->assertSame(0,$service->run('BMV',true,false,$actor)['created']);
        $clear=$service->run('BMV',true,true,$actor);
        $this->assertSame(0,$clear['deleted']);$this->assertNotEmpty($clear['blocked']);
        $this->assertDatabaseHas('airport_infrastructure',['id'=>$bridge->id,'name'=>'Tên do người dùng cập nhật'],'ops');
        $this->assertSame(12,$db->table('airport_infrastructure')->whereIn('id',array_values($ids))->count());
    }

    public function test_outside_children_and_pending_references_prevent_cleanup(): void
    {
        $actor=$this->setupAirport();$fixture=app(InfrastructureFixtures::class);$service=app(InfrastructureService::class);
        $ids=$fixture->run('BMV',true,false,$actor)['ids'];
        $base=['airport_code'=>'BMV','code'=>'USER-ZONE','type_code'=>'ZONE','active'=>true,'zone_function'=>'WAITING'];
        $external=$service->create($actor,$base)->id;
        $service->propose($actor,$external,$base+['parent_id'=>$ids['terminal']],'Vị trí người dùng đề nghị');
        $result=$fixture->run('BMV',true,true,$actor);
        $this->assertContains('Đề nghị đang tham chiếu bộ dữ liệu',$result['blocked']);
        DB::connection('ops')->table('airport_infrastructure')->where('id',$external)->update(['parent_id'=>$ids['terminal']]);
        $result=$fixture->run('BMV',true,true,$actor);
        $this->assertContains('Hạng mục bên ngoài trực thuộc',$result['blocked']);
        $this->assertSame(0,$result['deleted']);
    }

    public function test_allocation_use_remains_protected_even_after_release(): void
    {
        $actor=$this->setupAirport();$fixture=app(InfrastructureFixtures::class);$db=DB::connection('ops');
        $ids=$fixture->run('HAN',true,false,$actor)['ids'];
        $resource=$db->table('airport_resources')->where('infrastructure_id',$ids['gate'])->first();
        $this->actingAs(User::factory()->create(['role'=>'operations','airport_code'=>'HAN']));
        $id=$this->postJson('/workspace/infrastructure-allocations',['flight_id'=>1,'resource_id'=>$resource->id,'starts_at'=>now()->toIso8601String(),'ends_at'=>now()->addHours(3)->toIso8601String(),'reason'=>'Sử dụng tài nguyên trong test cô lập'])->assertOk()->json('id');
        $blocked=$fixture->run('HAN',false,true);
        $this->assertContains('Phân bổ M18',$blocked['blocked']);
        $this->postJson('/workspace/actions/unallocate',['allocation_id'=>$id,'reason'=>'Kết thúc kiểm tra phân bổ'])->assertOk();
        $blocked=$fixture->run('HAN',true,true,$actor);
        $this->assertSame(0,$blocked['deleted']);
        $this->assertContains('Lịch sử sử dụng tài nguyên',$blocked['blocked']);
    }

    public function test_guards_reject_production_remote_shared_and_unprivileged_runs(): void
    {
        $actor=$this->setupAirport();$fixture=app(InfrastructureFixtures::class);
        config(['infrastructure.local_fixtures_enabled'=>false]);
        $this->rejected(fn()=>$fixture->run('BMV',true,false,$actor),403);
        config(['infrastructure.local_fixtures_enabled'=>true]);
        $this->app->detectEnvironment(fn()=>'production');
        $this->rejected(fn()=>$fixture->run('BMV',true,false,$actor),403);
        $this->app->detectEnvironment(fn()=>'testing');
        config(['database.connections.ops.host'=>'shared.example']);
        $this->rejected(fn()=>$fixture->run('BMV'),403);
        config(['database.connections.ops.host'=>'db1','database.connections.ops.database'=>'shared_ops']);
        $this->rejected(fn()=>$fixture->run('BMV'),403);
        config(['database.connections.ops.database'=>'airline_test']);
        $this->rejected(fn()=>$fixture->run('BMV',true,false,User::factory()->create(['role'=>'operations','airport_code'=>'BMV'])),403);
        $this->rejected(fn()=>$fixture->run('BMV',true),422);
        $this->assertSame(0,DB::connection('ops')->table('infrastructure_fixture_sets')->count());
    }

    public function test_user_codes_and_provided_datasets_are_not_adopted(): void
    {
        $actor=$this->setupAirport();$fixture=app(InfrastructureFixtures::class);$db=DB::connection('ops');
        $id=app(InfrastructureService::class)->create($actor,['airport_code'=>'BMV','code'=>'LCL-T1','name'=>'Nhà ga do người dùng tạo','type_code'=>'TERMINAL','active'=>true,'source_reference'=>'Nguồn được đơn vị cung cấp'])->id;
        $this->rejected(fn()=>$fixture->run('BMV',true,false,$actor),409);
        $this->assertDatabaseHas('airport_infrastructure',['id'=>$id,'name'=>'Nhà ga do người dùng tạo'],'ops');
        $this->assertSame(0,$db->table('infrastructure_fixture_sets')->count());
    }
}
