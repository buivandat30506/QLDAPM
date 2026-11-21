<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Operations\AirportStatusSimulation;
use App\Modules\Operations\InfrastructureNormalizer;
use App\Modules\Operations\InfrastructureService;
use App\Modules\Operations\InfrastructureStatusInitialization;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InfrastructureProductStatusTest extends TestCase
{
    public function test_initialization_only_updates_active_unknown_conditions_and_is_idempotent(): void
    {
        app(InfrastructureNormalizer::class)->apply();
        $db = DB::connection('ops');
        $rows = $db->table('airport_infrastructure')->orderBy('id')->get();
        $db->table('airport_infrastructure')->where('id', $rows[0]->id)->update(['technical_state' => 'MAINTENANCE']);
        $db->table('airport_infrastructure')->where('id', $rows[1]->id)->update(['active' => false]);
        $legacy = $db->table('airport_resources')->orderBy('id')->get()->toJson();
        $flights = $db->table('flights')->orderBy('id')->get()->toJson();
        $service = app(InfrastructureStatusInitialization::class);
        $this->assertSame(10, $service->run()['eligible']);
        $this->assertSame(1, $db->table('airport_infrastructure')->whereNotNull('technical_state')->count());
        $this->assertSame(10, $service->run(true)['updated']);
        $after = $db->table('airport_infrastructure')->orderBy('id')->get()->toJson();
        $this->assertSame(0, $service->run(true)['updated']);
        $this->assertSame($after, $db->table('airport_infrastructure')->orderBy('id')->get()->toJson());
        $this->assertSame($legacy, $db->table('airport_resources')->orderBy('id')->get()->toJson());
        $this->assertSame($flights, $db->table('flights')->orderBy('id')->get()->toJson());
        $this->assertDatabaseHas('airport_infrastructure', ['id' => $rows[0]->id, 'technical_state' => 'MAINTENANCE'], 'ops');
        $this->assertDatabaseHas('airport_infrastructure', ['id' => $rows[1]->id, 'active' => false, 'technical_state' => null], 'ops');
        $this->assertSame(10, $db->table('audit_logs')->where('action', 'infrastructure.status.initialized')->count());
    }

    public function test_new_records_default_to_normal_and_keep_explicit_condition(): void
    {
        $user = User::factory()->create(['role' => 'data_admin']);
        $service = app(InfrastructureService::class);
        $base = ['airport_code' => 'HAN', 'type_code' => 'GATE', 'active' => true];
        $first = $service->create($user, $base + ['code' => 'PRODUCT-GATE']);
        $second = $service->create($user, $base + ['code' => 'MAINT-GATE', 'technical_state' => 'MAINTENANCE']);
        $this->assertDatabaseHas('airport_infrastructure', ['id' => $first->id, 'technical_state' => 'NORMAL'], 'ops');
        $this->assertDatabaseHas('airport_infrastructure', ['id' => $second->id, 'technical_state' => 'MAINTENANCE'], 'ops');
        $this->actingAs($user)->get('/workspace/catalog/infrastructure/new?airport=HAN&type=GATE')->assertOk()->assertSee('value="NORMAL" selected', false);
    }

    public function test_product_pages_omit_test_copy_and_keep_underlying_provenance(): void
    {
        app(AirportStatusSimulation::class)->run(true);
        app(InfrastructureNormalizer::class)->apply();
        app(InfrastructureStatusInitialization::class)->run(true);
        $this->actingAs(User::factory()->create(['role' => 'data_admin']));
        $hanStatus = DB::connection('ops')->table('airports')->where('code', 'HAN')->value('operational_status');
        foreach (['/workspace/catalog?flow=airports', '/workspace/catalog/airports/HAN'] as $url) {
            $this->get($url)->assertOk()->assertSee(\App\Support\AirportWorkspace::status($hanStatus)['label'])->assertDontSee('>Demo<', false);
        }
        $this->get('/workspace/catalog?flow=infrastructure&airport=HAN')->assertOk()->assertSee('Bình thường')->assertDontSee('mô phỏng');
        $this->assertDatabaseHas('airports', ['code' => 'HAN', 'operational_status_source' => 'SIMULATION'], 'ops');
        $this->assertSame(3, DB::connection('ops')->table('audit_logs')->where('action','airport.operational_status.simulated')->count());
    }
}
