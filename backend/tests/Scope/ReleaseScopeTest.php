<?php

namespace Tests\Scope;

use App\Models\User;
use App\Modules\Booking\ReservationService;
use App\Modules\Finance\OutboxRelay;
use App\Modules\Operations\OperationsService;
use App\Support\CorePruning;
use App\Support\ScopeData;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Uses restored data with per-test rollback. Never truncates or disables constraints. */
class ReleaseScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['ops', 'mysql', 'finance'] as $node) {
            if (! in_array(DB::connection($node)->getDatabaseName(), ['airline_scope_copy', CorePruning::TARGET], true)) {
                throw new \RuntimeException('Restored copy required');
            }
            DB::connection($node)->beginTransaction();
        }
        config(['mail.default' => 'array', 'queue.default' => 'sync', 'payments.mode' => 'mock', 'payments.simulator_enabled' => true, 'payments.bank.mode' => 'mock', 'payments.vnpay.enabled' => true, 'payments.vnpay.tmn_code' => 'SCOPE', 'payments.vnpay.hash_secret' => 'test-only-secret']);
    }

    protected function tearDown(): void
    {
        foreach (['ops', 'mysql', 'finance'] as $node) {
            while (DB::connection($node)->transactionLevel()) {
                DB::connection($node)->rollBack();
            }
        }
        parent::tearDown();
    }

    private function actor(string $role): User
    {
        return User::factory()->create(['role' => $role, 'airport_code' => 'HAN', 'email_verified_at' => now(), 'must_change_password' => false]);
    }

    public function test_suspended_navigation_and_legacy_writes_preserve_authorization(): void
    {
        $this->actingAs($this->actor('admin'));
        foreach (['fleet', 'maintenance', 'crew', 'ground', 'baggage', 'customer', 'finance', 'analytics', 'iot', 'fare', 'fare-rules', 'bank-statements', 'financial-adjustments', 'revenue', 'boarding-control', 'support-desk', 'passenger-revisions'] as $section) {
            $this->get('/workspace/'.$section)->assertOk()->assertViewIs('scope-empty')->assertSee('Chưa có thông tin hiển thị')->assertDontSee('<table', false);
            $this->getJson('/api/v1/workspace/'.$section)->assertOk()->assertJsonPath('data', []);
        }
        foreach (['/modules/m1', '/modules/m2?flow=bookings', '/modules/m3?flow=alerts', '/staff/passengers', '/workspace/fleet-cabin', '/workspace/reports/999/export', '/workspace/passenger-revisions/00000000-0000-0000-0000-000000000001'] as $url) {
            $this->get($url)->assertOk()->assertViewIs('scope-empty');
        }
        foreach (['aircraft', 'work_order', 'fare', 'fare_class', 'telemetry', 'dataset', 'reconcile', 'support'] as $action) {
            $this->postJson('/api/v1/actions/'.$action, [])->assertForbidden();
        }
        $this->postJson('/api/v1/aircraft', [])->assertForbidden();
        $this->postJson('/workspace/business/statement_confirm', [])->assertForbidden();
        $this->postJson('/modules/m3/actions/telemetry', [])->assertForbidden();
        $this->postJson('/api/v1/actions/master_propose', ['entity' => 'aircraft'])->assertForbidden();
        $this->actingAs($this->actor('passenger'))->get('/workspace/fleet')->assertForbidden();
        $unverified = $this->actor('airport_agent');
        $unverified->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($unverified)->postJson('/staff/boarding', [])->assertForbidden();
        $this->postJson('/api/v1/boarding', [])->assertForbidden();
    }

    public function test_m01_preview_approval_infrastructure_and_history_still_work(): void
    {
        $creator = $this->actor('data_admin');
        $reviewer = $this->actor('data_admin');
        $this->actingAs($creator)->get('/workspace/catalog?flow=airports&q=HAN')->assertOk()->assertSee('HAN');
        $this->get('/workspace/catalog?flow=infrastructure&airport=HAN')->assertOk();
        $this->get('/workspace/catalog/airports/HAN')->assertOk();
        $preview = $this->post('/workspace/catalog/airports/HAN/changes/preview', ['name' => 'Nội Bài nghiệm thu phạm vi', 'city' => 'Hà Nội', 'timezone' => 'Asia/Ho_Chi_Minh', 'reason' => 'Kiểm thử hồi quy trên bản sao riêng'])->assertOk();
        $change = $this->postJson('/workspace/catalog/airports/HAN/changes', ['preview_token' => $preview->viewData('token')])->assertCreated()->json('id');
        $this->postJson('/workspace/actions/master_approve', ['change_id' => $change])->assertConflict();
        $this->actingAs($reviewer)->postJson('/workspace/actions/master_approve', ['change_id' => $change])->assertOk();
        $this->get('/workspace/catalog/airports/HAN')->assertOk()->assertSee('Nội Bài nghiệm thu phạm vi')->assertSee('Đã duyệt');
        $this->assertSame(10, DB::connection('ops')->table('infrastructure_types')->count());
    }

    public function test_m02_publication_m03_coordination_and_real_booking_checkout(): void
    {
        $db = DB::connection('ops');
        $svc = app(OperationsService::class);
        $creator = $this->actor('data_admin');
        $operator = $this->actor('operations');
        $reviewer = $this->actor('ops_manager');
        $scheduler = $this->actor('crew_scheduler');
        $fleet = $this->actor('fleet_manager');
        $plane = $svc->execute($fleet, 'aircraft', ['registration' => 'VN-SC'.Str::upper(Str::random(6)), 'type' => 'A320', 'capacity' => 60])->id;
        $route = $db->table('routes')->where('origin', 'HAN')->where('destination', 'SGN')->first();
        $date = now()->addDays(180)->toDateString();
        $gate = $db->table('airport_resources')->where('airport_code', 'HAN')->where('type', 'GATE')->where('active', true)->first()->code;
        $data = ['code' => 'SCOPE-'.Str::upper(Str::random(6)), 'number' => 'AS'.random_int(90000, 99999), 'route_id' => $route->id, 'aircraft_id' => $plane, 'from_date' => $date, 'to_date' => $date, 'weekdays' => [1, 2, 3, 4, 5, 6, 7], 'local_departure' => '10:00', 'duration_minutes' => max(120, (int) $route->duration_minutes), 'sale_price' => 1500000, 'gate' => $gate, 'status' => 'DRAFT'];
        $plan = $this->actingAs($creator)->postJson('/workspace/planning/plans', $data)->assertCreated()->json();
        $path = '/workspace/planning/plans/'.$plan['id'];
        $this->get($path)->assertOk();
        $this->postJson($path.'/preview', ['from' => $date, 'to' => $date])->assertOk()->assertJsonPath('rows.0.status', 'READY');
        $flight = $this->postJson($path.'/generate', ['from' => $date, 'to' => $date, 'version' => $plan['version']])->assertOk()->json('created.0');
        foreach (['PILOT', 'CABIN'] as $position) {
            $crew = $svc->execute($scheduler, 'crew', ['code' => 'SC'.Str::upper(Str::random(8)), 'name' => 'Scope crew '.$position, 'position' => $position, 'qualification' => 'A320', 'license_expires' => now()->addYears(2)->toDateString()]);
            $svc->execute($scheduler, 'assign_crew', ['flight_id' => $flight, 'crew_member_id' => $crew->id]);
        }
        $this->actingAs($operator)->postJson('/workspace/planning/batch', ['action' => 'submit', 'flight_ids' => [$flight], 'apply' => 1])->assertOk()->assertJsonPath('applied', true);
        $this->actingAs($reviewer)->postJson('/workspace/planning/batch', ['action' => 'publish', 'flight_ids' => [$flight], 'apply' => 1])->assertOk()->assertJsonPath('applied', true);
        $this->assertNotNull($db->table('flights')->find($flight)->published_at);
        $this->assertTrue(DB::table('inventory')->where('flight_id', $flight)->exists());
        $this->getJson('/workspace/operations/flights/'.$flight)->assertOk()->assertJsonCount(2, 'crew')->assertJsonCount(1, 'allocations');
        $this->get('/workspace/operations/flights?q='.$data['number'])->assertOk()->assertSee($data['number']);
        // Change of aircraft uses its real prepare/commit saga and existing dependency checks.
        $replacement = $svc->execute($fleet, 'aircraft', ['registration' => 'VN-SR'.Str::upper(Str::random(6)), 'type' => 'A320', 'capacity' => 60])->id;
        $change = $this->actingAs($operator)->postJson('/workspace/actions/resource_propose', ['flight_id' => $flight, 'kind' => 'AIRCRAFT', 'target_aircraft_id' => $replacement, 'reason' => 'Điều phối tàu dự phòng trên bản sao'])->assertOk()->json('id');
        $this->actingAs($reviewer)->postJson('/workspace/actions/resource_decide', ['change_id' => $change, 'approve' => true])->assertOk();
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(0, app(OutboxRelay::class)->drain());
        }
        $this->assertSame($replacement, $db->table('flights')->find($flight)->aircraft_id);
        $this->actingAs($operator)->postJson('/workspace/actions/flight_state', ['flight_id' => $flight, 'status' => 'BOARDING', 'reason' => 'Mở đón khách nghiệm thu'])->assertOk();
        $this->get('/workspace/operations/flights/'.$flight)->assertOk()->assertSee('Đang đón khách');
        $this->assertTrue($db->table('audit_logs')->where('object_id', (string) $flight)->where('action', 'flight.BOARDING')->exists());
        $this->postJson('/workspace/actions/actual_time', ['flight_id' => $flight, 'type' => 'DEPARTURE', 'event_time' => now()->toIso8601String(), 'reason' => 'Ghi nhận giờ thực tế trên bản sao'])->assertOk();
        $this->getJson('/workspace/operations/flights/'.$flight)->assertOk()->assertJsonCount(1, 'actualEvents');
        $this->assertTrue($db->table('audit_logs')->where('object_id', (string) $flight)->where('action', 'flight.actual_time.updated')->exists());
        // Booking uses the published flight and the inventory produced by the real outbox.
        $passenger = $this->actor('passenger');
        $input = ['idempotency_key' => (string) Str::uuid(), 'trip_type' => 'ONE_WAY', 'passengers' => [['name' => 'Nguyen Scope', 'date_of_birth' => '1990-01-01']], 'segments' => [['flight_id' => $flight, 'fare_class' => 'ECONOMY']]];
        $reservation = $this->actingAs($passenger)->postJson('/api/v1/reservations', $input)->assertCreated()->json();
        $this->get('/reservations/'.$reservation['id'])->assertOk()->assertSee('Nguyen Scope');
        $session = app(ReservationService::class)->pay($passenger, $reservation['id'], 'VNPAY', '127.0.0.1');
        $this->assertNotEmpty($session->intent_id);
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(0, app(OutboxRelay::class)->drain());
        }
        $this->post('/payments/mock/'.$session->intent_id, ['result' => 'approve'])->assertRedirect('/reservations/'.$reservation['id']);
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(0, app(OutboxRelay::class)->drain());
        }
        $this->assertSame(['CONFIRMED'], DB::table('bookings')->where('reservation_id', $reservation['id'])->pluck('status')->unique()->all());
        $this->get('/journeys')->assertOk()->assertSee($reservation['reference']);
    }

    public function test_dashboard_has_only_authorized_core_tiles_and_scope_pruning_is_exact_and_replayable(): void
    {
        $dashboard = $this->actingAs($this->actor('admin'))->get('/staff')->assertOk()->assertSee('Kế hoạch lịch bay')->assertDontSee('aria-label="Tài chính và đối soát"', false);
        $this->assertCount(9, $dashboard->viewData('tiles'));
        $plan = Schema::connection('ops')->hasTable('telemetry') ? ScopeData::plan() : ['rows' => []];
        if ($plan['rows']) {
            $id = $plan['rows'][0]['id'];
            $changed = $plan;
            $changed['rows'][0]['sha256'] = 'stale';
            try {
                ScopeData::apply($changed);
                $this->fail('Changed candidate accepted');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Candidate changed', $e->getMessage());
            }
            $count = ScopeData::apply($plan);
            $this->assertCount($count, $plan['rows']);
            $this->assertFalse(DB::connection('ops')->table('telemetry')->where('id', $id)->exists());
            $this->assertSame(0, ScopeData::apply($plan));
        }
    }

    public function test_dependency_screens_keep_existing_roles_and_assigned_ground_work(): void
    {
        $this->actingAs($this->actor('crew_scheduler'))->get('/workspace/planning/crew-assignments')->assertOk()->assertSee('Phân công tổ bay cho lịch chuyến');
        $ground = $this->actor('ground_agent');
        $ops = $this->actor('operations');
        $f = DB::connection('ops')->table('flights')->where('origin', 'HAN')->where('departure', '>', now())->where('status', 'SCHEDULED')->first();
        $task = app(OperationsService::class)->execute($ops, 'ground_task', ['flight_id' => $f->id, 'title' => 'Kiểm tra phục vụ M03', 'assigned_to' => $ground->id, 'due_at' => $f->departure, 'mandatory' => true]);
        $this->actingAs($ground)->get('/workspace/operations/ground-tasks?flight_id='.$f->id)->assertOk()->assertSee('Kiểm tra phục vụ M03');
        $this->postJson('/workspace/actions/ground_state', ['task_id' => $task->id, 'status' => 'IN_PROGRESS'])->assertOk();
        $this->postJson('/workspace/actions/ground_state', ['task_id' => $task->id, 'status' => 'COMPLETED', 'evidence' => 'Đã kiểm tra và hoàn tất phục vụ chuyến'])->assertOk();
        $this->assertSame('COMPLETED', DB::connection('ops')->table('ground_tasks')->where('id', $task->id)->value('status'));
        $this->actingAs($this->actor('passenger'))->get('/workspace/planning/crew-assignments')->assertForbidden();
        $this->get('/workspace/operations/ground-tasks')->assertForbidden();
        $this->get('/staff/payments')->assertForbidden();
        $this->actingAs($this->actor('finance'))->get('/staff/payments')->assertOk()->assertSee('Đối chiếu chuyển khoản');
    }
}
