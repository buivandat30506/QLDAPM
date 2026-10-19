<?php

namespace Tests\Feature;

use App\Integrations\Operations\FlightChangeDelivery;
use App\Models\Aircraft;
use App\Models\AirportResource;
use App\Models\OperationalFlight;
use App\Models\Role;
use App\Models\User;
use App\Services\Core\FlightOutbox;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FlightOperationsTest extends TestCase
{
    use RefreshDatabase;
    private OperationalFlight $flight;
    private string $url;

    protected function setUp(): void
    {
        parent::setUp(); $this->travelTo(Carbon::parse('2026-10-18 02:30:00','UTC')); $this->seed();
        $this->actingAs(User::where('username','ops_demo')->firstOrFail());
        $this->flight=OperationalFlight::where('flight_number','DM102')->firstOrFail();
        $this->url='/api/v1/core/flights/'.$this->flight->id;
    }

    private function event(array $data=[])
    {
        return $this->postJson('/api/v1/core/events',array_replace(['schema'=>'flight-event.v1','source'=>'DEMO','external_id'=>'EV-01',
            'flight_id'=>$this->flight->id,'flight_status'=>'DELAYED','reason'=>'Thời tiết cần Điều hành quyết định',
            'sequence'=>1,'source_time_utc'=>'2026-10-18T02:29:00Z','source_timezone'=>'UTC'],$data));
    }

    private function proposal(array $data=[]): int
    {
        $event=$this->event()->assertStatus(202)->json('data.id');
        return $this->postJson($this->url.'/operations/proposals',array_replace(['event_id'=>$event,
            'expected_revision'=>$this->flight->fresh()->revision,'action'=>'CANCEL','reason'=>'Phương án do Điều hành lập'],$data))->assertOk()->json('data.id');
    }

    public function test_event_estimates_offsets_duplicate_sequence_and_late_delivery_do_not_overwrite(): void
    {
        $dep=$this->flight->scheduled_dep_utc->copy()->addMinutes(20)->toIso8601String();
        $arr=$this->flight->scheduled_arr_utc->copy()->addMinutes(20)->toIso8601String();
        $data=['estimated_dep_utc'=>$dep,'estimated_arr_utc'=>$arr,'source_time_utc'=>'2026-10-18T09:29:00+07:00','source_timezone'=>'Asia/Bangkok'];
        $this->event($data)->assertStatus(202)->assertJsonPath('data.status','ALERT');
        $revision=$this->flight->fresh()->revision;
        $this->event($data)->assertStatus(202); $this->assertSame($revision,$this->flight->fresh()->revision);
        $this->event($data+['external_id'=>'EV-OLD','sequence'=>1])->assertStatus(202)->assertJsonPath('data.status','IGNORED');
        $this->event(['external_id'=>'EV-LATE','sequence'=>2,'source_time_utc'=>'2026-10-18T02:28:00Z'])->assertStatus(202)->assertJsonPath('data.status','IGNORED');
        $this->assertSame($revision,$this->flight->fresh()->revision);
        $this->assertSame('SCHEDULED',$this->flight->fresh()->flight_status);
        $this->assertTrue($this->flight->fresh()->estimated_dep_utc->equalTo(Carbon::parse($dep)));
        $this->event(['reason'=>'Khác nội dung'])->assertUnprocessable();
    }

    public function test_unknown_event_future_event_and_retry_are_visible_without_advancing_watermark(): void
    {
        $this->event(['source_time_utc'=>'2026-10-19T02:29:00Z'])->assertUnprocessable();
        $this->assertNull($this->flight->fresh()->latest_event_at_utc);
        $key=DB::table('flight_events')->where('external_id','EV-01')->value('id');
        $this->postJson('/api/v1/core/events/'.$key.'/retry')->assertUnprocessable();
        $this->assertSame(2,(int)DB::table('flight_events')->where('id',$key)->value('attempts'));
        $this->event(['external_id'=>'EV-NORMAL','flight_status'=>'BOARDING'])->assertStatus(202)->assertJsonPath('data.status','PROCESSED');
        $this->assertSame('BOARDING',$this->flight->fresh()->flight_status);
    }

    public function test_human_proposal_preview_and_decision_cancel_crew_and_resolve_only_selected_alert(): void
    {
        $p=$this->proposal();
        $this->assertSame('SCHEDULED',$this->flight->fresh()->flight_status);
        $this->getJson($this->url.'/operations')->assertOk()->assertJsonPath('data.proposals.0.status','PROPOSED');
        $this->postJson($this->url.'/proposals/'.$p.'/decision',['decision'=>'APPLY','reason'=>'Quyết định có trách nhiệm'])->assertOk();
        $this->assertSame('CANCELLED',$this->flight->fresh()->flight_status);
        $this->assertDatabaseHas('flight_recovery_proposals',['id'=>$p,'status'=>'APPLIED','decided_by'=>auth()->id()]);
        $this->assertSame(0,$this->flight->crewAssignments()->where('status','!=','REPLACED')->count());
        $this->assertDatabaseHas('flight_events',['external_id'=>'EV-01','status'=>'RESOLVED']);
        $this->postJson($this->url.'/proposals/'.$p.'/decision',['decision'=>'APPLY','reason'=>'Gửi lặp'])->assertUnprocessable();
    }

    public function test_stale_proposal_and_rejected_proposal_cannot_change_flight(): void
    {
        $p=$this->proposal();
        $this->postJson($this->url.'/operations/checkin',['mode'=>'CLOSED','expected_revision'=>$this->flight->fresh()->revision,'reason'=>'Tạm đóng quầy'])->assertOk();
        $this->postJson($this->url.'/proposals/'.$p.'/decision',['decision'=>'APPLY','reason'=>'Dùng phương án cũ'])->assertUnprocessable();
        $this->postJson($this->url.'/proposals/'.$p.'/decision',['decision'=>'REJECT','reason'=>'Lập lại phương án mới'])->assertOk();
        $this->assertSame('SCHEDULED',$this->flight->fresh()->flight_status);
    }

    public function test_gate_change_rechecks_availability_and_keeps_crew_approval(): void
    {
        $gate=AirportResource::create(['airport_code'=>$this->flight->route->origin_airport_code,'resource_type'=>'GATE','resource_code'=>'OPS-GATE','status'=>'AVAILABLE']);
        $p=$this->proposal(['action'=>'GATE','resource_id'=>$gate->id]);
        $gate->update(['status'=>'MAINTENANCE']);
        $this->postJson($this->url.'/proposals/'.$p.'/decision',['decision'=>'APPLY','reason'=>'Kiểm tra cửa trước commit'])->assertUnprocessable();
        $gate->update(['status'=>'AVAILABLE']);
        $this->postJson($this->url.'/proposals/'.$p.'/decision',['decision'=>'APPLY','reason'=>'Cửa đã được giải phóng'])->assertOk();
        $this->assertDatabaseHas('operational_flights',['id'=>$this->flight->id,'resource_id'=>$gate->id,'gate_number'=>'OPS-GATE']);
    }

    public function test_checkin_window_revision_and_closure_mandatory_milestone(): void
    {
        $f=OperationalFlight::where('flight_number','DM105')->firstOrFail(); $url='/api/v1/core/flights/'.$f->id.'/operations/';
        $this->postJson($url.'milestone',['expected_revision'=>$f->revision,'code'=>'GROUND_REPORT','label'=>'Báo cáo mặt đất','status'=>'PENDING','evidence'=>'Chưa nộp báo cáo'])->assertOk();
        $this->postJson($url.'close',['expected_revision'=>$f->fresh()->revision,'reason'=>'Thử đóng thiếu mốc'])->assertUnprocessable();
        $this->postJson($url.'milestone',['expected_revision'=>$f->fresh()->revision,'code'=>'GROUND_REPORT','label'=>'Báo cáo mặt đất','status'=>'EXEMPT','evidence'=>'Điều hành ghi ngoại lệ có trách nhiệm'])->assertOk();
        $this->postJson($url.'close',['expected_revision'=>$f->fresh()->revision,'reason'=>'Đã đối chiếu dữ liệu'])->assertOk();
        $this->assertNotNull($f->fresh()->closed_at_utc); $this->assertFalse($f->fresh()->isCheckInOpen());
        $this->putJson('/api/v1/core/flights/'.$f->id.'/status',['flight_status'=>'ARRIVED'])->assertUnprocessable();
        $this->assertDatabaseHas('flight_operation_history',['flight_id'=>$f->id,'action'=>'CLOSE']);
        $this->assertDatabaseHas('flight_outbox',['flight_id'=>$f->id,'topic'=>'FLIGHT_CHANGED']);
    }

    public function test_airport_and_flight_scope_apply_to_collections_objects_and_invalid_input(): void
    {
        $user=User::factory()->create(); $role=Role::where('role_code','FLIGHT_OPS')->firstOrFail(); $user->roles()->attach($role->id);
        DB::table('user_data_scopes')->insert(['user_id'=>$user->id,'role_id'=>$role->id,'scope_type'=>'flight','scope_id'=>$this->flight->id]);
        $this->actingAs($user);
        $this->getJson('/api/v1/core/flights')->assertOk()->assertJsonCount(1,'data');
        $this->get('/admin/flights/'.$this->flight->id)->assertOk();
        $other=OperationalFlight::where('id','!=',$this->flight->id)->firstOrFail();
        $this->getJson('/api/v1/core/flights/'.$other->id)->assertForbidden();
        $this->postJson('/api/v1/core/flights/'.$other->id.'/operations/checkin',[])->assertForbidden();
        $this->event()->assertStatus(202);
        $this->getJson('/api/v1/core/events')->assertOk()->assertJsonCount(1,'data.data');
        $this->actingAs(User::where('username','nguyen_van_a')->firstOrFail());
        $this->get('/admin/flights')->assertForbidden();
    }

    public function test_outbox_provider_failure_retry_and_duplicate_delivery_are_idempotent(): void
    {
        $this->event()->assertStatus(202);
        $this->app->bind(FlightChangeDelivery::class,fn()=>new class implements FlightChangeDelivery { public function deliver(int $id,array $payload): void { throw new \RuntimeException('synthetic provider outage'); } });
        $this->assertSame(0,app(FlightOutbox::class)->drain());
        $o=DB::table('flight_outbox')->where('flight_id',$this->flight->id)->latest('id')->first();
        $this->assertSame('RETRY',$o->status); $this->assertSame('RuntimeException',$o->last_error);
        $this->app->bind(FlightChangeDelivery::class,\App\Integrations\Operations\LocalFlightChangeDelivery::class);
        $this->postJson($this->url.'/outbox/'.$o->id.'/retry')->assertOk();
        $this->assertSame(1,app(FlightOutbox::class)->drain());
        app(FlightChangeDelivery::class)->deliver($o->id,json_decode($o->payload,true));
        $this->assertSame(1,DB::table('flight_delivery_receipts')->where('outbox_id',$o->id)->count());
        $this->assertSame(0,app(FlightOutbox::class)->drain());
    }
}
