<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResourceActionDisclosureTest extends TestCase
{
    public function test_invalid_form_reopens_only_the_submitted_action_and_preserves_input(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'operations','airport_code'=>'HAN']));
        $this->post('/workspace/infrastructure-allocations',['flight_id'=>1,'reason'=>'Nội dung đang nhập'])->assertRedirect('/workspace/resources#resource-action-allocate')->assertSessionHasInput('_resource_action','allocate')->assertSessionHasInput('reason','Nội dung đang nhập');
        $response=$this->get('/workspace/resources')->assertOk();
        $this->assertMatchesRegularExpression('/id="resource-action-allocate"[^>]*\bopen\b/',$response->getContent());
        $this->assertDoesNotMatchRegularExpression('/id="resource-action-unallocate"[^>]*\bopen\b/',$response->getContent());
        $this->assertSame(1,substr_count($response->getContent(),'value="Nội dung đang nhập"'));
        $this->post('/workspace/actions/unallocate',['reason'=>'Lý do gỡ đang nhập'])->assertRedirect('/workspace/resources#resource-action-unallocate')->assertSessionHasInput('_resource_action','unallocate');
        $response=$this->get('/workspace/resources')->assertOk();
        $this->assertMatchesRegularExpression('/id="resource-action-unallocate"[^>]*\bopen\b/',$response->getContent());
        $this->assertDoesNotMatchRegularExpression('/id="resource-action-allocate"[^>]*\bopen\b/',$response->getContent());
    }

    public function test_html_conflict_returns_to_form_but_json_conflict_keeps_its_status(): void
    {
        $actor=User::factory()->create(['role'=>'operations','airport_code'=>'HAN']);$this->actingAs($actor);
        $db=DB::connection('ops');$resource=$db->table('airport_resources')->where('airport_code','HAN')->where('type','GATE')->first();
        $window=['flight_id'=>1,'resource_id'=>$resource->id,'starts_at'=>now()->subMinutes(5)->format('Y-m-d\TH:i'),'ends_at'=>now()->addHours(3)->format('Y-m-d\TH:i'),'reason'=>'Kiểm tra xung đột lịch'];
        $db->table('resource_allocations')->insert(['flight_id'=>1,'resource_id'=>$resource->id,'starts_at'=>now()->subHour(),'ends_at'=>now()->addHours(4),'reason'=>'Lịch trong DB test','actor_id'=>$actor->id]);
        $before=$db->table('resource_allocations')->get()->toJson();
        // Local wall time is interpreted as Vietnam time by the HTML endpoint.
        $window['starts_at']=now()->setTimezone('Asia/Ho_Chi_Minh')->subMinutes(5)->format('Y-m-d\TH:i');
        $window['ends_at']=now()->setTimezone('Asia/Ho_Chi_Minh')->addHours(3)->format('Y-m-d\TH:i');
        $this->post('/workspace/infrastructure-allocations',$window)->assertRedirect('/workspace/resources#resource-action-allocate')->assertSessionHasErrors('resource_action')->assertSessionHasInput('reason',$window['reason']);
        $this->postJson('/workspace/infrastructure-allocations',array_replace($window,['starts_at'=>now()->subMinutes(5)->toIso8601String(),'ends_at'=>now()->addHours(3)->toIso8601String()]))->assertConflict();
        $this->assertSame($before,$db->table('resource_allocations')->get()->toJson());
    }
}
