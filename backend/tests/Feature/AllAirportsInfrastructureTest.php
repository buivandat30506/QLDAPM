<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Operations\InfrastructureFixtures;
use App\Modules\Operations\InfrastructureNormalizer;
use App\Modules\Operations\InfrastructureService;
use App\Support\InfrastructureDisplay;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class AllAirportsInfrastructureTest extends TestCase
{
    private function actor(): User
    {
        config(['infrastructure.local_fixtures_enabled'=>true]);
        return User::factory()->create(['role'=>'data_admin']);
    }

    public function test_all_reads_dynamic_airports_fills_missing_only_and_cleans_only_its_own_records(): void
    {
        $actor=$this->actor();$db=DB::connection('ops');$fixture=app(InfrastructureFixtures::class);
        app(InfrastructureNormalizer::class)->apply();
        $initial=$db->table('airports')->count();
        foreach (['BMV','QAZ'] as $code) {
            $db->table('airports')->insert(['code'=>$code,'name'=>'Sân bay kiểm thử '.$code,'city'=>'Kiểm thử','timezone'=>'Asia/Ho_Chi_Minh']);
        }
        $bmv=$fixture->run('BMV',true,false,$actor)['ids'];
        $before=[];
        foreach (['airport_infrastructure','airport_resources','resource_allocations','flights','infrastructure_fixture_sets','infrastructure_sources'] as $table) {
            $key=$table==='infrastructure_fixture_sets'?'key':'id';
            $before[$table]=['key'=>$key,'rows'=>$db->table($table)->orderBy($key)->get()];
        }
        $missing=0;
        foreach ($db->table('airports')->pluck('code') as $airport) {
            $missing+=10-$db->table('airport_infrastructure')->where('airport_code',$airport)->distinct()->count('type_code');
        }
        $preview=$fixture->runAll();
        $this->assertSame($initial+2,$preview['airports']);
        $this->assertSame($missing,$preview['planned']);
        $this->assertSame(0,$preview['results']['BMV']['created']);
        $applied=$fixture->runAll(true,false,$actor);
        $this->assertSame($missing,$applied['created']);
        foreach ($db->table('airports')->pluck('code') as $airport) {
            $this->assertSame(10,$db->table('airport_infrastructure')->where('airport_code',$airport)->distinct()->count('type_code'));
        }
        foreach ($before as $table=>$snapshot) {
            $this->assertSame($snapshot['rows']->toJson(),$db->table($table)->whereIn($snapshot['key'],$snapshot['rows']->pluck($snapshot['key']))->orderBy($snapshot['key'])->get()->toJson());
        }
        $after=$db->table('airport_infrastructure')->orderBy('id')->get()->toJson();
        $this->assertSame(0,$fixture->runAll(true,false,$actor)['created']);
        $this->assertSame($after,$db->table('airport_infrastructure')->orderBy('id')->get()->toJson());
        $this->assertSame($missing,$fixture->runAll(true,true,$actor)['deleted']);
        $this->assertSame($before['airport_infrastructure']['rows']->toJson(),$db->table('airport_infrastructure')->orderBy('id')->get()->toJson());
        $this->assertSame(12,$db->table('airport_infrastructure')->whereIn('id',array_values($bmv))->count());
        $this->assertSame(0,$fixture->runAll(true,true,$actor)['deleted']);
    }

    public function test_minimal_relations_never_assign_old_endpoints_or_add_existing_types(): void
    {
        $actor=$this->actor();app(InfrastructureNormalizer::class)->apply();$db=DB::connection('ops');
        $old=$db->table('airport_infrastructure')->where('airport_code','HAN')->orderBy('id')->get();
        $resources=$db->table('airport_resources')->orderBy('id')->get()->toJson();
        $result=app(InfrastructureFixtures::class)->run('HAN',true,false,$actor,true);
        $this->assertSame(6,$result['created']);$ids=$result['ids'];
        $terminal=$old->firstWhere('type_code','TERMINAL');
        $this->assertDatabaseHas('airport_infrastructure',['id'=>$ids['claim'],'parent_id'=>$terminal->id],'ops');
        $this->assertDatabaseHas('airport_infrastructure',['id'=>$ids['belt'],'parent_id'=>$ids['claim']],'ops');
        $this->assertSame(0,$db->table('infrastructure_links')->where('source_id',$ids['bridge'])->count());
        $this->assertSame(0,$db->table('airport_infrastructure')->where('parent_id',$ids['apron'])->count());
        foreach (['runway','taxiway','apron'] as $alias) {
            $this->assertDatabaseHas('airport_infrastructure',['id'=>$ids[$alias],'parent_id'=>null],'ops');
        }
        $this->assertSame(0,$db->table('runway_directions')->where('runway_id',$ids['runway'])->count());
        $this->assertSame($resources,$db->table('airport_resources')->orderBy('id')->get()->toJson());
        $this->assertSame($old->toJson(),$db->table('airport_infrastructure')->whereIn('id',$old->pluck('id'))->orderBy('id')->get()->toJson());
    }

    public function test_all_preflight_refuses_conflicting_provided_data_without_partial_writes(): void
    {
        $actor=$this->actor();app(InfrastructureNormalizer::class)->apply();$db=DB::connection('ops');
        app(InfrastructureService::class)->create($actor,['airport_code'=>'SGN','code'=>'PROVIDED','type_code'=>'ZONE','active'=>true,'source_reference'=>'Tài liệu đơn vị']);
        $before=$db->table('airport_infrastructure')->orderBy('id')->get()->toJson();
        try {
            app(InfrastructureFixtures::class)->runAll(true,false,$actor);
            $this->fail('Expected source protection.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(409,$e->getStatusCode());
        }
        $this->assertSame($before,$db->table('airport_infrastructure')->orderBy('id')->get()->toJson());
        $this->assertSame(0,$db->table('infrastructure_fixture_sets')->count());
    }

    public function test_derived_names_search_and_two_line_identity_do_not_write_names_or_codes(): void
    {
        $actor=$this->actor();app(InfrastructureNormalizer::class)->apply();$db=DB::connection('ops');
        $record=$db->table('airport_infrastructure')->where('airport_code','HAN')->where('type_code','COUNTER')->first();
        $name=InfrastructureDisplay::name($record);$before=$db->table('airport_infrastructure')->orderBy('id')->get()->toJson();
        $this->actingAs($actor)->get('/workspace/catalog?flow=infrastructure&airport=HAN&q='.urlencode($name))->assertOk()->assertViewHas('items',fn($items)=>$items->total()===1 && $items->first()->canonical_id===$record->id)->assertSee('<strong>'.$name.'</strong>',false)->assertSee('infrastructure-code">'.$record->code.'</small>',false);
        $this->get('/workspace/catalog/infrastructure/items/'.$record->id)->assertOk()->assertSee($name);
        $this->get('/workspace/catalog?flow=infrastructure&airport=HAN&q=%25')->assertOk()->assertViewHas('items',fn($items)=>$items->total()===0);
        $this->assertSame($before,$db->table('airport_infrastructure')->orderBy('id')->get()->toJson());
        $this->assertSame('Nhà ga T1',InfrastructureDisplay::name((object)['type_code'=>'TERMINAL','code'=>'Nhà ga T1','name'=>null]));
        $this->assertSame('Quầy làm thủ tục C01',InfrastructureDisplay::name((object)['type_code'=>'COUNTER','code'=>'C01','name'=>'Quầy làm thủ tục C01']));
    }

    public function test_missing_mode_does_not_duplicate_unmapped_legacy_records(): void
    {
        $actor=$this->actor();
        try {
            app(InfrastructureFixtures::class)->runAll(true,false,$actor);
            $this->fail('Expected unresolved legacy guard.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(409,$e->getStatusCode());
        }
        $this->assertSame(0,DB::connection('ops')->table('airport_infrastructure')->count());
    }
}
