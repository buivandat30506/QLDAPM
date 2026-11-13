<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Operations\ScheduleConflicts;
use App\Support\SchedulingDisplay;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class M02PlanningTest extends TestCase
{
    private function user(string $role = 'data_admin', string $airport = 'HAN'): User
    {
        return User::factory()->create(['role' => $role, 'airport_code' => $airport]);
    }

    private function planData(): array
    {
        return ['code' => 'PLAN-TEST', 'number' => 'AS9801', 'route_id' => DB::connection('ops')->table('routes')->where('origin', 'HAN')->where('destination', 'SGN')->value('id'), 'aircraft_id' => 1, 'from_date' => now()->addDays(40)->toDateString(), 'to_date' => now()->addDays(160)->toDateString(), 'weekdays' => [1, 2, 3, 4, 5, 6, 7], 'local_departure' => '10:00', 'duration_minutes' => 120, 'sale_price' => 1500000, 'gate' => 'A2', 'status' => 'DRAFT'];
    }

    private function plan(User $u): array
    {
        return $this->actingAs($u)->postJson('/workspace/planning/plans', $this->planData())->assertCreated()->json();
    }

    private function draft(User $u, string $number = 'AS9802', int $days = 50): int
    {
        $d = $this->planData();
        $start = now()->addDays($days)->startOfDay()->addHours(10);

        return $this->actingAs($u)->postJson('/api/v1/actions/schedule', ['number' => $number, 'route_id' => $d['route_id'], 'aircraft_id' => 1, 'departure' => $start->toIso8601String(), 'arrival' => $start->copy()->addHours(2)->toIso8601String(), 'sale_price' => 1500000, 'gate' => 'A2'])->assertOk()->json('id');
    }

    private function crew(int $id, User $u): void
    {
        DB::connection('ops')->table('aircraft')->where('id', 1)->update(['type' => 'A320']);
        foreach ([1001, 1002] as $crew) {
            $this->actingAs($u)->postJson('/api/v1/actions/assign_crew', ['flight_id' => $id, 'crew_member_id' => $crew])->assertOk();
        }
    }

    public function test_route_detail_search_create_and_independent_approval(): void
    {
        $u = $this->user();
        $reviewer = $this->user();
        $this->actingAs($u)->get('/workspace/catalog?flow=routes&q=Nội+Bài')->assertOk()->assertSee('HAN')->assertViewIs('m02.routes');
        $id = $this->postJson('/workspace/catalog/routes', ['origin' => 'SGN', 'destination' => 'DAD', 'duration_minutes' => 100, 'distance_km' => 600])->assertCreated()->json('id');
        $this->postJson('/workspace/catalog/routes', ['origin' => 'SGN', 'destination' => 'DAD', 'duration_minutes' => 100, 'distance_km' => 600])->assertConflict();
        $this->get('/workspace/catalog/routes/'.$id)->assertOk()->assertSee('value="100"', false);
        $change = $this->postJson('/workspace/catalog/routes/'.$id.'/changes', ['duration_minutes' => 110, 'distance_km' => 601, 'active' => 1, 'reason' => 'Điều chỉnh thời lượng'])->assertCreated()->json('id');
        $this->postJson('/workspace/catalog/routes/'.$id.'/changes/'.$change.'/approve')->assertConflict();
        $this->actingAs($reviewer)->postJson('/workspace/catalog/routes/'.$id.'/changes/'.$change.'/approve')->assertOk();
        $this->assertDatabaseHas('routes', ['id' => $id, 'duration_minutes' => 110], 'ops');
        $this->actingAs($this->user('operations'))->get('/workspace/catalog?flow=routes')->assertForbidden();
    }

    public function test_long_plan_preview_generation_source_and_idempotency(): void
    {
        $u = $this->user();
        $p = $this->plan($u);
        $path = '/workspace/planning/plans/'.$p['id'];
        $this->get($path)->assertOk()->assertSee('Xem trước');
        $this->get($path.'/edit')->assertOk()->assertSee('name="weekdays[]"', false);
        $this->postJson($path.'/preview', ['from' => $p['from_date'], 'to' => Carbon::parse($p['from_date'])->addDays(31)->toDateString()])->assertUnprocessable();
        $window = ['from' => $p['from_date'], 'to' => $p['from_date']];
        $preview = $this->postJson($path.'/preview', $window)->assertOk()->assertJsonPath('rows.0.status', 'READY');
        $this->postJson($path.'/preview',['from'=>$p['from_date'],'to'=>Carbon::parse($p['from_date'])->addDays(30)->toDateString()])->assertOk()->assertJsonCount(31,'rows');
        $this->post($path.'/preview', $window)->assertOk()->assertViewIs('m02.generation-preview');
        $r = $this->postJson($path.'/generate', $window + ['version' => $p['version']])->assertOk()->json();
        $flight = $r['created'][0];
        $this->assertDatabaseHas('flights', ['id' => $flight, 'status' => 'DRAFT', 'published_at' => null, 'sale_price' => 1500000], 'ops');
        $this->assertDatabaseHas('flight_season_occurrences', ['season_id' => $p['id'], 'flight_id' => $flight, 'plan_version' => 1], 'ops');
        $audits = DB::connection('ops')->table('audit_logs')->count();
        $this->postJson($path.'/generate', $window + ['version' => 1])->assertOk()->assertJsonPath('created', [])->assertJsonPath('skipped.0', $flight);
        $this->assertSame($audits, DB::connection('ops')->table('audit_logs')->count());
        $this->assertFalse(DB::table('inventory')->where('flight_id', $flight)->exists());
        $this->postJson($path, array_merge($this->planData(), ['version' => 1, 'local_departure' => '11:00']))->assertConflict();
        $this->postJson('/api/v1/actions/season_rule', array_merge($this->planData(), ['id' => $p['id']]))->assertForbidden();
        $this->actingAs($this->user('operations'))->postJson('/api/v1/actions/season_rule', array_merge($this->planData(), ['id' => $p['id']]))->assertConflict();
        $this->get('/workspace/planning/flights/'.$flight)->assertOk()->assertSee('PLAN-TEST');
        $this->actingAs($u)->postJson($path,array_merge($this->planData(),['version'=>1,'status'=>'ARCHIVED']))->assertOk()->assertJsonPath('version',2);
        $this->postJson($path.'/preview',$window)->assertConflict();
    }

    public function test_weekdays_timezones_and_stale_versions_are_validated(): void
    {
        $u = $this->user();
        $db = DB::connection('ops');
        $db->table('airports')->where('code', 'SGN')->update(['timezone' => 'Asia/Tokyo']);
        $p = $this->plan($u);
        $path = '/workspace/planning/plans/'.$p['id'];
        $row = $this->postJson($path.'/preview', ['from' => $p['from_date'], 'to' => $p['from_date']])->assertOk()->json('rows.0');
        $this->assertStringContainsString('03:00:00', $row['departure']);
        $this->assertStringContainsString('05:00:00', $row['arrival']);
        $this->assertSame('Asia/Tokyo', $row['destination_zone']);
        $this->postJson($path.'/generate', ['from' => $p['from_date'], 'to' => $p['from_date'], 'version' => 999])->assertConflict();
        $bad = $this->planData();
        $bad['code'] = 'BAD';
        $bad['weekdays'] = [0, 8];
        $this->postJson('/workspace/planning/plans', $bad)->assertUnprocessable();
        $bad['weekdays'] = [1];
        $bad['to_date'] = '2000-01-01';
        $this->postJson('/workspace/planning/plans', $bad)->assertUnprocessable();
        $this->assertSame('2027-02-01 15:00:00', SchedulingDisplay::local('2027-02-02', '00:00', 'Asia/Tokyo')->toDateTimeString());
        try {
            SchedulingDisplay::local('2027-03-14', '02:30', 'America/New_York');
            $this->fail('DST gap accepted');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        try {
            SchedulingDisplay::local('2027-11-07', '01:30', 'America/New_York');
            $this->fail('DST ambiguity accepted');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_conflicts_are_real_and_rechecked_at_submit_and_publish(): void
    {
        $u = $this->user('operations');
        $manager = $this->user('ops_manager');
        $id = $this->draft($u);
        $other = $this->draft($u, 'AS9803');
        $db = DB::connection('ops');
        $issues = app(ScheduleConflicts::class)->inspect($db->table('flights')->find($id));
        $this->assertSame('warning', $issues[0]['severity']);
        $this->actingAs($u)->get('/workspace/planning?tab=conflicts')->assertOk()->assertSee('Chưa chạy kiểm tra');
        $this->getJson('/workspace/planning?tab=conflicts&run=1&from='.now()->addDays(50)->toDateString().'&to='.now()->addDays(50)->toDateString())->assertOk()->assertJsonPath('checked.issues.0.related_id', $other);
        $db->table('flights')->where('id', $other)->update(['status' => 'SCHEDULED', 'published_at' => now()]);
        $this->postJson('/api/v1/actions/submit', ['flight_id' => $id])->assertConflict();
        $db->table('flights')->where('id', $other)->update(['status' => 'CANCELLED']);
        $this->postJson('/api/v1/actions/submit', ['flight_id' => $id])->assertOk();
        $db->table('flights')->where('id', $other)->update(['status' => 'SCHEDULED']);
        $this->actingAs($manager)->postJson('/api/v1/actions/publish', ['flight_id' => $id])->assertConflict();
        $this->assertDatabaseHas('flights', ['id' => $id, 'status' => 'PENDING'], 'ops');
    }

    public function test_batch_publication_atomicity_inventory_replay_and_m18(): void
    {
        $u = $this->user('operations');
        $manager = $this->user('ops_manager');
        $id = $this->draft($u);
        $second = $this->draft($u, 'AS9803', 52);
        $this->crew($id, $this->user('crew_scheduler'));
        $base = ['flight_ids' => [$id, $second], 'action' => 'submit'];
        $this->actingAs($u)->postJson('/workspace/planning/batch', $base)->assertOk()->assertJsonPath('applied', false);
        $this->assertDatabaseHas('flights', ['id' => $id, 'status' => 'DRAFT'], 'ops');
        $this->postJson('/workspace/planning/batch', $base + ['apply' => 1])->assertOk()->assertJsonPath('applied', true);
        $publish = ['flight_ids' => [$id, $second], 'action' => 'publish', 'apply' => 1];
        $this->actingAs($manager)->postJson('/workspace/planning/batch', $publish)->assertOk()->assertJsonPath('applied', false)->assertJsonPath('rows.0.result', fn ($v) => str_contains($v, 'hoàn tác'));
        $this->assertDatabaseHas('flights', ['id' => $id, 'status' => 'PENDING', 'published_at' => null], 'ops');
        $this->assertFalse(DB::connection('ops')->table('resource_allocations')->where('flight_id', $id)->exists());
        $this->postJson('/workspace/planning/batch', ['flight_ids' => [$id], 'action' => 'publish', 'apply' => 1])->assertOk()->assertJsonPath('applied', true);
        $this->assertDatabaseHas('inventory', ['flight_id' => $id, 'price' => 1500000]);
        $this->assertDatabaseHas('resource_allocations', ['flight_id' => $id], 'ops');
        $count = DB::connection('ops')->table('audit_logs')->count();
        $alloc = DB::connection('ops')->table('resource_allocations')->count();
        $this->postJson('/workspace/planning/batch', ['flight_ids' => [$id], 'action' => 'publish', 'apply' => 1])->assertOk()->assertJsonPath('applied', true);
        $this->assertSame($count, DB::connection('ops')->table('audit_logs')->count());
        $this->assertSame($alloc, DB::connection('ops')->table('resource_allocations')->count());
        $this->get('/workspace/planning/flights/'.$id)->assertOk()->assertSee('Đã công bố')->assertSee('điều hành gián đoạn');
    }

    public function test_edit_proposals_preserve_source_and_require_independent_current_approval(): void
    {
        $u = $this->user('operations');
        $id = $this->draft($u);
        $manager = $this->user('ops_manager');
        $f = DB::connection('ops')->table('flights')->find($id);
        $data = ['version'=>1,'aircraft_id' => 1, 'departure' => Carbon::parse($f->departure, 'UTC')->setTimezone('Asia/Ho_Chi_Minh')->addHours(3)->format('Y-m-d\TH:i'), 'arrival' => Carbon::parse($f->arrival, 'UTC')->setTimezone('Asia/Ho_Chi_Minh')->addHours(3)->format('Y-m-d\TH:i'), 'sale_price' => 1600000, 'gate' => 'A2', 'reason' => 'Điều chỉnh lịch nháp'];
        $this->actingAs($u)->postJson('/workspace/planning/flights/'.$id.'/changes', $data)->assertCreated();
        // Invalid gates must be rejected before writing history.
        $this->postJson('/workspace/planning/flights/'.$id.'/changes', array_merge($data, ['gate' => 'XXX']))->assertUnprocessable();
        $change = DB::connection('ops')->table('flight_schedule_changes')->first();
        $this->get('/workspace/planning/flights/'.$id)->assertOk()->assertSee('Điều chỉnh lịch nháp');
        $this->actingAs($manager)->postJson('/workspace/planning/changes/'.$change->id.'/approve')->assertOk();
        $this->assertDatabaseHas('flights', ['id' => $id, 'version' => 2, 'status' => 'DRAFT', 'sale_price' => 1600000], 'ops');
        $this->postJson('/workspace/planning/changes/'.$change->id.'/approve')->assertConflict();
        $this->actingAs($u);
        $this->postJson('/workspace/planning/flights/'.$id.'/changes',$data)->assertConflict();
        $data['version']=2;$data['sale_price']=1700000;
        $new = $this->postJson('/workspace/planning/flights/'.$id.'/changes', $data)->assertCreated()->json('id');
        DB::connection('ops')->table('flights')->where('id', $id)->increment('version');
        $this->actingAs($manager)->postJson('/workspace/planning/changes/'.$new.'/approve')->assertConflict();
        DB::connection('ops')->table('flights')->where('id', $id)->update(['published_at' => now(), 'status' => 'SCHEDULED']);
        $this->actingAs($u)->postJson('/workspace/planning/flights/'.$id.'/changes', $data)->assertConflict();
    }

    public function test_roles_scope_forms_and_single_bulk_actions(): void
    {
        $u = $this->user();
        $id = $this->draft($u);
        $this->get('/workspace/planning/plans/new')->assertOk()->assertSee('Thứ Hai')->assertSee('Chủ nhật');
        $this->get('/workspace/scheduling?flow=planning')->assertOk()->assertDontSee('Kiểm tra và cảnh báo');
        $this->get('/workspace/planning?tab=review')->assertForbidden();
        $this->postJson('/workspace/planning/batch', ['flight_ids' => [$id], 'action' => 'publish', 'apply' => 1])->assertForbidden();
        $this->actingAs($this->user('operations', 'SGN'))->get('/workspace/planning/flights/'.$id)->assertForbidden();
        $this->actingAs($this->user('operations'))->get('/workspace/planning?tab=review')->assertOk()->assertSee('name="flight_ids[]"',false);
        $this->post('/workspace/planning/batch',['flight_ids' => [$id], 'action' => 'submit'])->assertOk()->assertViewIs('m02.batch-review');
        $this->actingAs($u)->get('/workspace/planning/flights/new')->assertOk();
        $this->actingAs($this->user('passenger'))->get('/workspace/planning')->assertForbidden();
    }

    public function test_local_input_validation_and_self_approval_cannot_bypass_backend(): void
    {
        $u=$this->user('admin');$id=$this->draft($u);$db=DB::connection('ops');$f=$db->table('flights')->find($id);
        $input=['version'=>1,'aircraft_id'=>1,'departure'=>Carbon::parse($f->departure,'UTC')->setTimezone('Asia/Ho_Chi_Minh')->addHours(3)->format('Y-m-d\TH:i'),'arrival'=>Carbon::parse($f->arrival,'UTC')->setTimezone('Asia/Ho_Chi_Minh')->addHours(3)->format('Y-m-d\TH:i'),'sale_price'=>1600000,'gate'=>'A2','reason'=>'Sửa giờ kế hoạch'];
        $change=$this->postJson('/workspace/planning/flights/'.$id.'/changes',$input)->assertCreated()->json('id');
        $this->postJson('/workspace/planning/changes/'.$change.'/approve')->assertConflict();
        $this->from('/workspace/planning/flights/'.$id)->post('/workspace/planning/flights/'.$id.'/changes',array_merge($input,['version'=>999]))->assertRedirect('/workspace/planning/flights/'.$id)->assertSessionHasErrors('schedule');
        $this->assertDatabaseCount('flight_schedule_changes',1,'ops');
        $data=['number'=>'AS9900','route_id'=>$f->route_id,'aircraft_id'=>1,'departure'=>now()->addDays(80)->format('Y-m-d').'T10:00','arrival'=>now()->addDays(80)->format('Y-m-d').'T12:00','gate'=>'A2','sale_price'=>1800000];
        $new=$this->postJson('/workspace/planning/flights',$data)->assertCreated()->json('id');
        $this->assertDatabaseHas('flights',['id'=>$new,'departure'=>now()->addDays(80)->format('Y-m-d').' 03:00:00','arrival'=>now()->addDays(80)->format('Y-m-d').' 05:00:00'],'ops');
        $this->postJson('/workspace/planning/flights',$data)->assertConflict();
        $this->postJson('/workspace/planning/flights',array_merge($data,['gate'=>'UNKNOWN']))->assertUnprocessable();
        $this->postJson('/workspace/planning/batch',['flight_ids'=>[$id],'action'=>'submit','apply'=>1])->assertOk()->assertJsonPath('applied',true);
        $this->postJson('/workspace/planning/batch',['flight_ids'=>[$id],'action'=>'publish','apply'=>1])->assertOk()->assertJsonPath('applied',false);
        $this->assertDatabaseHas('flights',['id'=>$id,'status'=>'PENDING','published_at'=>null],'ops');
    }
}
