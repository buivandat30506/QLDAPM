<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Operations\M02Fixtures;
use App\Modules\Operations\SchedulePlanning;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class M02CompleteWorkflowTest extends TestCase
{
    private function actors(): array
    {
        config(['m02.local_fixtures_enabled' => true, 'payments.mode' => 'mock', 'payments.bank.mode' => 'mock', 'mail.mailers.smtp.host' => 'mailpit']);
        $actors = [];
        foreach (['creator' => 'data_admin', 'operator' => 'operations', 'reviewer' => 'ops_manager', 'fleet' => 'fleet_manager', 'crew' => 'crew_scheduler', 'technician' => 'maintenance'] as $key => $role) {
            $actors[$key] = User::factory()->create(['role' => $role, 'airport_code' => 'HAN']);
        }

        return $actors;
    }

    public function test_fixture_has_linked_real_workflows_and_m03_inventory_allocations(): void
    {
        $actors = $this->actors();
        $result = app(M02Fixtures::class)->run('test-complete', now()->addDays(4)->toDateString(), $actors);
        $this->assertCount(4, $result['plan_ids']);
        $this->assertCount(19, $result['flight_ids']);
        $this->assertSame(3, $result['published']);
        $this->assertSame(4, $result['states']['PENDING']);
        $this->assertSame(12, $result['states']['DRAFT']);
        $this->assertSame(3, $result['groups']['conflict']);
        $this->assertSame(2, $result['groups']['condition']);
        $this->assertGreaterThanOrEqual(2, $result['groups']['warning']);
        $db = DB::connection('ops');
        $published = $db->table('flights')->whereIn('id', $result['flight_ids'])->whereNotNull('published_at')->pluck('id');
        $this->assertSame(3, DB::table('inventory')->whereIn('flight_id', $published)->count());
        $this->assertSame(3, $db->table('resource_allocations')->whereIn('flight_id', $published)->count());
        $this->assertSame(3, $db->table('audit_logs')->whereIn('object_id', $published->map(fn ($id) => (string) $id))->where('action', 'schedule.published')->count());
        $this->assertSame(12, $db->table('flight_season_occurrences')->whereIn('season_id', $result['plan_ids'])->count());
        $this->assertSame(1, $db->table('flight_schedule_changes')->where('status', 'APPROVED')->count());
        $id = $published->first();
        $f = $db->table('flights')->find($id);
        $this->actingAs($actors['reviewer'])->getJson('/workspace/operations/flights/'.$id)->assertOk()->assertJsonPath('flight.number', $f->number)->assertJsonPath('flight.gate', $f->gate)->assertJsonCount(2, 'crew')->assertJsonCount(1, 'allocations');
        $this->get('/workspace/scheduling?flow=monitoring&flight_id='.$id)->assertOk()->assertSee($f->number);
        $this->get('/workspace/planning?tab=review&lane=published')->assertOk()->assertSee($f->number)->assertDontSee('name="flight_ids[]"', false);
        $this->actingAs($actors['creator'])->get('/workspace/operations/flights/'.$id)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'operations', 'airport_code' => 'SGN']))->get('/workspace/operations/flights/'.$id)->assertNotFound();
        $draft = $db->table('flights')->whereIn('id', $result['flight_ids'])->where('status', 'DRAFT')->first();
        $this->actingAs($actors['operator'])->get('/workspace/operations/flights/'.$draft->id)->assertNotFound();
        $this->postJson('/api/v1/actions/flight_state', ['flight_id' => $draft->id, 'status' => 'BOARDING', 'reason' => 'Kiểm tra trạng thái'])->assertConflict();
        $this->actingAs($actors['reviewer'])->postJson('/workspace/planning/batch', ['action' => 'publish', 'flight_ids' => [$id], 'apply' => 1])->assertOk()->assertJsonPath('applied', true);
    }

    public function test_fixture_replay_preserves_edits_and_archive_keeps_published_history(): void
    {
        $actors = $this->actors();
        $service = app(M02Fixtures::class);
        $result = $service->run('test-replay', now()->addDays(4)->toDateString(), $actors);
        $db = DB::connection('ops');
        $manual = $db->table('flight_seasons')->find($result['manifest']['plan.manual']);
        $input = (array) $manual;
        $input['local_departure'] = '09:00';
        $input['weekdays'] = json_decode($manual->weekdays, true);
        app(SchedulePlanning::class)->save($actors['creator'], $input, $manual->id);
        $audit = $db->table('audit_logs')->count();
        $flights = $db->table('flights')->orderBy('id')->get()->toJson();
        $again = $service->run('test-replay', null, $actors);
        $this->assertSame($result['flight_ids'], $again['flight_ids']);
        $this->assertSame($flights, $db->table('flights')->orderBy('id')->get()->toJson());
        $this->assertSame($audit, $db->table('audit_logs')->count());
        $this->assertSame('09:00:00', $db->table('flight_seasons')->find($manual->id)->local_departure);
        $archived = $service->archive('test-replay', $actors['creator']);
        $this->assertSame([$manual->id], $archived['preserved_user_plans']);
        $this->assertSame(3, $archived['published']);
        $this->assertSame(19, count($archived['flight_ids']));
        $this->assertSame($flights, $db->table('flights')->orderBy('id')->get()->toJson());
    }

    public function test_progressive_checks_cover_311_unique_flights_and_are_private(): void
    {
        $actors = $this->actors();
        $rows = [];
        $route = DB::connection('ops')->table('routes')->first();
        $start = now()->addDays(100)->startOfDay();
        for ($i = 0; $i < 311; $i++) {
            $rows[] = ['number' => 'AS'.(20000 + $i), 'origin' => 'HAN', 'destination' => $route->destination, 'route_id' => $route->id, 'aircraft_id' => 1, 'departure' => $start->copy()->addDays($i), 'arrival' => $start->copy()->addDays($i)->addMinutes(120), 'status' => 'DRAFT', 'gate' => 'A2', 'sale_price' => 1590000, 'created_by' => $actors['operator']->id];
        }
        DB::connection('ops')->table('flights')->insert($rows);
        $response = $this->actingAs($actors['operator'])->getJson('/workspace/planning?tab=conflicts&run=1&q=AS2&from='.$start->toDateString().'&to='.$start->copy()->addDays(400)->toDateString())->assertOk()->assertJsonPath('checked.total', 311)->assertJsonPath('checked.count', 100)->assertJsonPath('checked.remaining', 211);
        $token = $response->json('checked.token');
        foreach ([200, 300, 311] as $n) {
            $this->postJson('/workspace/planning/checks/'.$token.'/continue')->assertOk()->assertJsonPath('count', $n)->assertJsonPath('remaining', 311 - $n)->assertJsonPath('metrics.valid', $n);
        }
        $this->postJson('/workspace/planning/checks/'.$token.'/continue')->assertOk()->assertJsonPath('count', 311)->assertJsonPath('remaining', 0);
        $this->get('/workspace/planning?tab=conflicts&check='.$token.'&group=valid')->assertOk()->assertSee('Hợp lệ trong phạm vi');
        $other = User::factory()->create(['role' => 'operations', 'airport_code' => 'HAN']);
        $this->actingAs($other)->getJson('/workspace/planning?tab=conflicts&check='.$token)->assertForbidden();
    }

    public function test_fixture_requires_opt_in_and_rejects_production_and_remote_database(): void
    {
        $actors = $this->actors();
        config(['m02.local_fixtures_enabled' => false]);
        $this->artisan('airline:m02-demo')->assertFailed();
        $this->assertSame(0, DB::connection('ops')->table('m02_demo_sets')->count());
        config(['m02.local_fixtures_enabled' => true, 'database.connections.ops.host' => 'shared.example.com']);
        $this->artisan('airline:m02-demo')->assertFailed();
        config(['database.connections.ops.host' => 'db1']);
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('airline:m02-demo')->assertFailed();
    }
}
