<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InfrastructureImportTest extends TestCase
{
    private function preview(string $csv)
    {
        return $this->post('/workspace/catalog/infrastructure/import/preview', ['airport_code'=>'HAN','source_reference'=>'Hồ sơ do đơn vị quản lý cung cấp','file'=>UploadedFile::fake()->createWithContent('verified.csv',$csv)]);
    }

    public function test_preview_is_read_only_confirmation_imports_profiles_and_cannot_replay(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'data_admin']));
        $this->get('/workspace/catalog/infrastructure/import/template')->assertOk()->assertDownload('infrastructure-import.csv');
        $db = DB::connection('ops');
        $before = $db->table('airport_infrastructure')->count();
        $preview = $this->preview("code,type_code,name,baggage_flow,terminal_scope\nCSV-BELT,BELT,Băng chuyền xác nhận,ARRIVAL,\nCSV-TERM,TERMINAL,Nhà ga xác nhận,,DOMESTIC\n")->assertOk()->assertSee('Xác nhận 2 hạng mục')->assertSee('Quốc nội');
        $this->assertSame($before,$db->table('airport_infrastructure')->count());
        $token = $preview->viewData('token');
        $this->post('/workspace/catalog/infrastructure/import',['token'=>'tampered'])->assertConflict();
        $this->post('/workspace/catalog/infrastructure/import',['token'=>$token])->assertRedirect('/workspace/catalog?flow=infrastructure&airport=HAN');
        $this->assertSame($before+2,$db->table('airport_infrastructure')->count());
        $belt = $db->table('airport_infrastructure')->where('code','CSV-BELT')->first();
        $this->assertDatabaseHas('infrastructure_belts',['infrastructure_id'=>$belt->id,'baggage_flow'=>'ARRIVAL'],'ops');
        $this->assertDatabaseHas('airport_infrastructure',['id'=>$belt->id,'technical_state'=>null],'ops');
        $this->assertSame(2,$db->table('infrastructure_sources')->where('entry_method','CSV_IMPORT')->count());
        $this->post('/workspace/catalog/infrastructure/import',['token'=>$token])->assertConflict();
        $this->assertSame(2,$db->table('audit_logs')->where('action','infrastructure.created')->count());
    }

    public function test_import_revalidates_and_rolls_back_whole_file_on_concurrent_duplicate(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'data_admin']));
        $preview = $this->preview("code,type_code\nIMPORT-FIRST,COUNTER\nIMPORT-LAST,COUNTER\n")->assertOk();
        $this->postJson('/workspace/catalog/infrastructure/items',['airport_code'=>'HAN','code'=>'IMPORT-LAST','type_code'=>'COUNTER','active'=>true])->assertCreated();
        $db = DB::connection('ops');
        $before = [];
        foreach (['airport_infrastructure','airport_resources','infrastructure_sources','audit_logs'] as $table) {
            $before[$table] = $db->table($table)->orderBy('id')->get()->toJson();
        }
        $this->post('/workspace/catalog/infrastructure/import',['token'=>$preview->viewData('token')])->assertConflict();
        foreach ($before as $table=>$rows) {
            $this->assertSame($rows,$db->table($table)->orderBy('id')->get()->toJson());
        }
    }

    public function test_invalid_files_duplicates_and_unconfirmed_relations_do_not_create_data(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'data_admin']));
        foreach (["code,type_code\nREPEAT,COUNTER\nrepeat,COUNTER\n", "code,type_code,parent_code\nNEW,COUNTER,UNCONFIRMED\n", "code,type_code\nOLD,CHECKIN_AREA\n", "code,type_code,extra\nNEW,COUNTER,x\n", "code,type_code\n", "code,type_code\nNEW,COUNTER,extra\n", "code,type_code,asset_reference\nBRIDGE-A,JET_BRIDGE,ASSET-A\nBRIDGE-B,JET_BRIDGE,ASSET-A\n"] as $csv) {
            $this->preview($csv)->assertSessionHasErrors('file');
        }
        $this->assertSame(0,DB::connection('ops')->table('airport_infrastructure')->count());
        $this->assertSame(0,DB::connection('ops')->table('infrastructure_sources')->count());
        $this->actingAs(User::factory()->create(['role'=>'airport_agent','airport_code'=>'HAN']));
        $this->preview("code,type_code\nUNAUTHORIZED,COUNTER\n")->assertForbidden();
    }
}
