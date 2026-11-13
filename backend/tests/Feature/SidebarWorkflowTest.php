<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\StaffNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SidebarWorkflowTest extends TestCase
{
    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'airport_code' => 'HAN']);
    }

    public function test_catalog_flows_show_only_the_relevant_records_and_actions(): void
    {
        $this->actingAs($this->user('data_admin'));
        $this->get('/workspace/catalog?flow=airports')->assertOk()->assertViewIs('m01.index')->assertViewHas('airports')
            ->assertSee('name="flow" value="airports"', false);
        $this->get('/workspace/catalog?flow=infrastructure')->assertOk()->assertViewIs('m01.infrastructure')->assertViewHas('airport', null)->assertViewHas('items', null);
        $this->get('/workspace/catalog?flow=routes')->assertOk()
            ->assertViewIs('m02.routes')->assertViewHas('routes')->assertViewHas('pending')
            ->assertSee('Thêm đường bay')->assertDontSee('name="duration_minutes"',false);
        $this->get('/workspace/catalog?flow=missing')->assertNotFound();
        $this->get('/workspace/catalog?flow[]=routes')->assertSessionHasErrors('flow');
        $this->actingAs($this->user('ground_agent'))->get('/workspace/catalog?flow=routes')->assertForbidden();
    }

    public function test_crew_and_ground_flows_keep_profile_and_work_actions_separate(): void
    {
        $this->actingAs($this->user('crew_scheduler'));
        $this->get('/workspace/crew?flow=profiles')->assertOk()
            ->assertViewHas('forms', fn ($forms) => ! in_array('assign_crew', array_column($forms, 'action')));
        $this->get('/workspace/crew?flow=assignments')->assertOk()
            ->assertViewHas('tables', fn ($tables) => array_column($tables, 'title') === ['Phân công tổ bay'])
            ->assertViewHas('forms', fn ($forms) => array_column($forms, 'action') === ['assign_crew']);
        $this->actingAs($this->user('ground_agent'));
        $this->get('/workspace/ground?flow=tasks')->assertOk()->assertViewHas('forms', []);
        $this->get('/workspace/ground?flow=tracking')->assertOk()
            ->assertViewHas('forms', fn ($forms) => array_column($forms, 'action') === ['ground_state']);
    }

    public function test_approval_choices_stay_in_the_selected_catalog_and_exclude_the_author(): void
    {
        $author = $this->user('data_admin');
        $reviewer = $this->user('data_admin');
        $changes = [];
        foreach (['airports', 'routes', 'airport_resources'] as $entity) {
            $changes[$entity] = DB::connection('ops')->table('master_changes')->insertGetId([
                'entity' => $entity, 'record_id' => 'HAN', 'before_data' => '{}', 'after_data' => '{}',
                'reason' => 'Workflow review '.$entity, 'status' => 'PENDING', 'created_by' => $author->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->actingAs($reviewer)->get('/workspace/catalog/airports/HAN')->assertOk()
            ->assertViewHas('changes', fn ($rows) => $rows->pluck('id')->all() === [$changes['airports']])
            ->assertSee('name="change_id" value="'.$changes['airports'].'"', false);
        $this->actingAs($author)->get('/workspace/catalog/airports/HAN')->assertOk()
            ->assertDontSee('action="/workspace/actions/master_approve"', false);
    }

    public function test_planning_and_monitoring_have_different_actions(): void
    {
        $this->actingAs($this->user('admin'));
        $this->get('/workspace/scheduling?flow=planning')->assertOk()
            ->assertViewIs('m02.planning')->assertViewHas('tab','flights')
            ->assertSee('Phê duyệt và công bố')->assertDontSee('name="flight_state"',false);
        $this->get('/workspace/scheduling?flow=monitoring')->assertOk()
            ->assertViewIs('m02.operations')->assertViewHas('flights');
    }

    public function test_finance_flows_separate_payment_review_from_reconciliation(): void
    {
        $this->actingAs($this->user('finance'));
        $this->get('/workspace/finance?flow=transactions')->assertOk()
            ->assertViewHas('forms', fn ($forms) => array_column($forms, 'action') === ['exchange_decide', 'refund_decide'])
            ->assertViewHas('tables', fn ($tables) => ! in_array('Kỳ đối soát', array_column($tables, 'title')))
            ->assertSee('<h2>Đối chiếu chuyển khoản</h2>', false);
        $this->get('/workspace/finance?flow=reconciliation')->assertOk()
            ->assertViewHas('forms', fn ($forms) => array_column($forms, 'action') === ['reconcile', 'resolve', 'close_period'])
            ->assertViewHas('tables', fn ($tables) => ! in_array('Yêu cầu hoàn tiền', array_column($tables, 'title')))
            ->assertDontSee('<h2>Đối chiếu chuyển khoản</h2>', false);
    }

    public function test_specialized_module_pages_exclude_unrelated_forms(): void
    {
        $this->actingAs($this->user('admin'));
        $this->get('/modules/m2?flow=bookings')->assertOk()->assertViewIs('modules.bookings')
            ->assertDontSee('data-module-action="/api/v1/modules/m2/actions/bag_scan"', false);
        $this->get('/modules/m3?flow=alerts')->assertOk()->assertViewIs('modules.alerts')
            ->assertSee('action="/workspace/actions/forecast"', false)
            ->assertDontSee('data-module-action="/api/v1/modules/m3/actions/model_train"', false);
        $this->get('/modules/m3?flow=missing')->assertNotFound();
        $this->actingAs($this->user('integration_admin'))->get('/modules/m3?flow=alerts')->assertOk()
            ->assertDontSee('action="/workspace/actions/forecast"', false);
    }

    public function test_navigation_selects_one_flow_and_respects_alternative_role_access(): void
    {
        $user = $this->user('admin');
        foreach (['/workspace/catalog?flow=routes', '/workspace/scheduling?flow=monitoring', '/workspace/finance?flow=reconciliation', '/workspace/recovery'] as $url) {
            $request = Request::create($url);
            $active = [];
            foreach (StaffNavigation::groups($user) as $group) {
                foreach ($group['items'] as $item) {
                    foreach ($item['children'] ?? [$item] as $entry) {
                        if (StaffNavigation::isActive($entry, $request)) {
                            $active[] = $entry;
                        }
                    }
                }
            }
            $this->assertCount(1, $active, $url);
        }
        $analyst = $this->user('analyst');
        $context = StaffNavigation::context($analyst, Request::create('/workspace/revenue'));
        $this->assertSame('Đối soát doanh thu', $context['label']);
        $this->assertSame(['/workspace/revenue'], array_column($context['links'], 'href'));
        $crew = StaffNavigation::context($this->user('crew_scheduler'), Request::create('/workspace/recovery'));
        $this->assertSame('Gián đoạn và phục hồi', $crew['label']);
        $this->assertSame(['/workspace/recovery'], array_column($crew['links'], 'href'));
    }
}
