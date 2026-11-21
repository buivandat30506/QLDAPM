<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AirportProfileWorkflowTest extends TestCase
{
    private function input(): array
    {
        return ['name'=>'Nội Bài cập nhật','city'=>'Hà Nội','timezone'=>'Asia/Ho_Chi_Minh','reason'=>'Đối chiếu hồ sơ do đơn vị cung cấp'];
    }

    public function test_preview_is_read_only_confirmation_uses_server_values_and_needs_independent_approval(): void
    {
        $actor=User::factory()->create(['role'=>'data_admin']);
        $reviewer=User::factory()->create(['role'=>'data_admin']);
        $db=DB::connection('ops');$before=$db->table('airports')->where('code','HAN')->first();
        $this->actingAs($actor);
        $preview=$this->post('/workspace/catalog/airports/HAN/changes/preview',$this->input())->assertOk()->assertSee('Xem trước đề nghị sửa hồ sơ')->assertSee('Nội Bài cập nhật')->assertSee('Xác nhận gửi đề nghị');
        $token=$preview->viewData('token');
        $this->assertSame(0,$db->table('master_changes')->count());
        $this->assertEquals($before,$db->table('airports')->where('code','HAN')->first());
        $this->get('/workspace/catalog/airports/HAN?edit_proposal=1')->assertOk()->assertSee('value="Nội Bài cập nhật"',false);
        $this->postJson('/workspace/catalog/airports/HAN/changes',['preview_token'=>'invalid'])->assertConflict();
        $response=$this->postJson('/workspace/catalog/airports/HAN/changes',['preview_token'=>$token,'name'=>'Tampered client value'])->assertCreated();
        $change=$response->json('id');
        $after=json_decode($db->table('master_changes')->where('id',$change)->value('after_data'),true);
        $this->assertSame($this->input()['name'],$after['name']);
        $this->assertDatabaseHas('airports',['code'=>'HAN','name'=>$before->name],'ops');
        $this->postJson('/workspace/catalog/airports/HAN/changes',['preview_token'=>$token])->assertConflict();
        $this->get('/workspace/catalog/airports/HAN')->assertOk()->assertSee('Chờ duyệt')->assertSee('Nội Bài cập nhật')->assertDontSee('>Duyệt đề nghị<',false);
        $this->postJson('/workspace/actions/master_approve',['change_id'=>$change])->assertConflict();
        $this->actingAs($reviewer)->get('/workspace/catalog/airports/HAN')->assertOk()->assertSee('Duyệt đề nghị');
        $this->postJson('/workspace/actions/master_approve',['change_id'=>$change])->assertOk();
        $this->get('/workspace/catalog/airports/HAN')->assertOk()->assertSee('Đã duyệt');
        $this->assertDatabaseHas('airports',['code'=>'HAN','name'=>'Nội Bài cập nhật','icao'=>$before->icao],'ops');
    }

    public function test_validation_stale_preview_and_expiration_do_not_create_proposals(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'data_admin']));$db=DB::connection('ops');
        $current=$db->table('airports')->where('code','HAN')->first();
        $unchanged=['name'=>$current->name,'city'=>$current->city,'timezone'=>$current->timezone,'reason'=>'Không có thay đổi hồ sơ'];
        $this->postJson('/workspace/catalog/airports/HAN/changes/preview',$unchanged)->assertUnprocessable()->assertJsonValidationErrors('changes');
        $this->postJson('/workspace/catalog/airports/HAN/changes/preview',array_replace($this->input(),['timezone'=>'invalid-zone']))->assertUnprocessable()->assertJsonValidationErrors('timezone');
        $preview=$this->post('/workspace/catalog/airports/HAN/changes/preview',$this->input())->assertOk();
        $db->table('airports')->where('code','HAN')->update(['city'=>'Thành phố đã cập nhật']);
        $this->post('/workspace/catalog/airports/HAN/changes',['preview_token'=>$preview->viewData('token')])->assertRedirect('/workspace/catalog/airports/HAN?edit_proposal=1#airport-edit')->assertSessionHasErrors('changes');
        $preview=$this->post('/workspace/catalog/airports/HAN/changes/preview',$this->input())->assertOk();
        $this->travel(31)->minutes();
        $this->postJson('/workspace/catalog/airports/HAN/changes',['preview_token'=>$preview->viewData('token')])->assertConflict();
        $this->travelBack();
        $this->assertSame(0,$db->table('master_changes')->count());
        $this->actingAs(User::factory()->create(['role'=>'operations','airport_code'=>'HAN']))->postJson('/workspace/catalog/airports/HAN/changes/preview',$this->input())->assertForbidden();
    }

    public function test_history_is_paginated_and_system_events_are_collapsed_without_deleting_audit(): void
    {
        $actor=User::factory()->create(['role'=>'data_admin']);$db=DB::connection('ops');
        for($i=0;$i<7;$i++) {
            $db->table('audit_logs')->insert(['correlation_id'=>(string)\Illuminate\Support\Str::uuid(),'object_id'=>'HAN','action'=>'airport.identifiers.imported','actor_id'=>null,'before_state'=>'null','after_state'=>json_encode(['icao'=>'VVNB','country_code'=>'VN']),'created_at'=>now()->subMinutes($i)]);
        }
        for($i=0;$i<8;$i++) {
            $db->table('audit_logs')->insert(['correlation_id'=>(string)\Illuminate\Support\Str::uuid(),'object_id'=>'HAN','action'=>'airport.operational_status.simulated','actor_id'=>null,'before_state'=>'null','after_state'=>json_encode(['operational_status'=>'NORMAL','operational_status_note'=>'Internal fixture provenance']),'created_at'=>now()->subHours($i)]);
        }
        $before=$db->table('audit_logs')->count();
        $this->actingAs($actor)->get('/workspace/catalog/airports/HAN')->assertOk()->assertViewHas('history',fn($history)=>$history->total()===7&&$history->count()===5)->assertViewHas('systemHistory',fn($history)=>$history->total()===8&&$history->count()===5)->assertSee('Hoạt động hệ thống (8)')->assertDontSee('Internal fixture provenance');
        $this->get('/workspace/catalog/airports/HAN?history_page=2')->assertOk()->assertViewHas('history',fn($history)=>$history->count()===2);
        $this->get('/workspace/catalog/airports/HAN?system_page=2')->assertOk()->assertViewHas('systemHistory',fn($history)=>$history->count()===3);
        $this->get('/workspace/catalog/airports/HAN?history_page=0')->assertSessionHasErrors('history_page');
        $this->assertSame($before,$db->table('audit_logs')->count());
    }
}
