<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Operations\AirportStatusSimulation;
use App\Support\AirportWorkspace;
use App\Support\InfrastructureCatalog;
use App\Support\InfrastructureDisplay;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AirportStatusResetTest extends TestCase
{
    private function twentyTwo(): void
    {
        foreach (['BMV', 'CAH', 'CXR', 'DIN', 'DLI', 'HPH', 'HUI', 'PQC', 'PXU', 'TBB', 'THD', 'UIH', 'VCA', 'VCL', 'VCS', 'VDH', 'VDO', 'VII', 'VKG'] as $code) {
            DB::connection('ops')->table('airports')->insert(['code' => $code, 'name' => $code, 'city' => 'Test', 'timezone' => 'Asia/Ho_Chi_Minh']);
        }
    }

    public function test_distribution_replay_and_upgrade_of_legacy_demo(): void
    {
        $this->twentyTwo();
        $s = app(AirportStatusSimulation::class);
        $db = DB::connection('ops');
        $s->run(false);
        $this->assertSame(0, $db->table('airports')->whereNotNull('operational_status')->count());
        $s->run(true);
        $this->assertEquals(['NORMAL' => 15, 'HIGH' => 4, 'OVERLOAD' => 2, 'MAINTENANCE' => 1], $db->table('airports')->select('operational_status')->selectRaw('count(*) as total')->groupBy('operational_status')->pluck('total', 'operational_status')->all());
        $before = $db->table('airports')->orderBy('code')->get()->toJson();
        $s->run(true, false, true);
        $this->assertSame($before, $db->table('airports')->orderBy('code')->get()->toJson());
        $row = $db->table('airports')->where('code', 'BMV')->first();
        $row->operational_status = 'OVERLOAD';
        $row->operational_status_simulation_key = 'airport-status-local-v1';
        $db->table('airports')->where('code', 'BMV')->update(['operational_status' => $row->operational_status, 'operational_status_simulation_key' => $row->operational_status_simulation_key, 'operational_status_simulation_fingerprint' => $s::fingerprint($row)]);
        $s->run(true, false, true);
        $this->assertDatabaseHas('airports', ['code' => 'BMV', 'operational_status' => 'NORMAL', 'operational_status_simulation_key' => $s::KEY], 'ops');
        $this->assertSame(22, count($s->run(true, true)['changed']));
    }

    public function test_manual_and_modified_demo_are_protected(): void
    {
        $this->twentyTwo();
        $s = app(AirportStatusSimulation::class);
        $s->run(true);
        $db = DB::connection('ops');
        $db->table('airports')->where('code', 'HAN')->update(['operational_status' => 'MAINTENANCE', 'operational_status_source' => 'MANUAL', 'operational_status_updated_by' => 1, 'operational_status_note' => 'Director confirmed', 'operational_status_simulation_key' => null, 'operational_status_simulation_fingerprint' => null]);
        $db->table('airports')->where('code', 'SGN')->update(['operational_status_note' => 'Changed after setup']);
        $before = $db->table('airports')->whereIn('code', ['HAN', 'SGN'])->orderBy('code')->get()->toJson();
        $s->run(true, false, true);
        $s->run(true, true);
        $this->assertSame($before, $db->table('airports')->whereIn('code', ['HAN', 'SGN'])->orderBy('code')->get()->toJson());
    }

    public function test_adaptive_quotas(): void
    {
        $s = app(AirportStatusSimulation::class);
        foreach ([0, 1, 2, 3, 4, 5, 7, 22, 25, 100] as $n) {
            $q = $s->targets($n);
            $this->assertSame($n, array_sum($q));
            if ($n >= 4) {
                $this->assertTrue(min($q) >= 1);
            }
        }
        $this->assertSame(['NORMAL' => 15, 'HIGH' => 4, 'OVERLOAD' => 2, 'MAINTENANCE' => 1], $s->targets(22));
    }

    public function test_randomized_setup_is_reproducible_persistent_and_keeps_the_quotas(): void
    {
        $this->twentyTwo();
        $s = app(AirportStatusSimulation::class);
        $db = DB::connection('ops');
        $this->assertSame(0, $db->table('airports')->whereNotNull('operational_status')->count());
        $s->run(false, false, false, 'review-seed-a');
        $this->assertSame(0, $db->table('airports')->whereNotNull('operational_status')->count());
        $s->run(true, false, false, 'review-seed-a');
        $this->assertEquals(['NORMAL' => 15, 'HIGH' => 4, 'OVERLOAD' => 2, 'MAINTENANCE' => 1], $db->table('airports')->select('operational_status')->selectRaw('count(*) as total')->groupBy('operational_status')->pluck('total', 'operational_status')->all());
        $before = $db->table('airports')->orderBy('code')->get()->toJson();
        $this->assertSame([], $s->run(true, false, false, 'review-seed-a')['changed']);
        $this->assertSame([], $s->run(true)['changed']);
        $this->assertSame($before, $db->table('airports')->orderBy('code')->get()->toJson());
        $map = $db->table('airports')->orderBy('code')->pluck('operational_status', 'code')->all();
        $s->run(true, false, false, 'review-seed-b');
        $this->assertNotSame($map, $db->table('airports')->orderBy('code')->pluck('operational_status', 'code')->all());
        $this->assertNotNull(json_decode($db->table('audit_logs')->where('action', 'airport.operational_status.simulated')->first()->after_state, true)['random_seed']);
    }

    public function test_randomization_protects_confirmed_and_modified_records(): void
    {
        $this->twentyTwo();
        $s = app(AirportStatusSimulation::class);
        $s->run(true);
        $db = DB::connection('ops');
        $db->table('airports')->where('code', 'HAN')->update(['operational_status_source' => 'MANUAL', 'operational_status_note' => 'Confirmed by director']);
        $db->table('airports')->where('code', 'SGN')->update(['operational_status_note' => 'Edited after setup']);
        $before = $db->table('airports')->whereIn('code', ['HAN', 'SGN'])->orderBy('code')->get()->toJson();
        $s->run(true, false, false, 'protected-seed');
        $this->assertSame($before, $db->table('airports')->whereIn('code', ['HAN', 'SGN'])->orderBy('code')->get()->toJson());
    }

    public function test_labels_and_infrastructure_independence(): void
    {
        $this->assertSame(['Normal', 'High', 'Overload', 'Maintenance'], array_column(AirportWorkspace::STATUSES, 'label'));
        $this->twentyTwo();
        app(AirportStatusSimulation::class)->run(true);
        $this->actingAs(User::factory()->create(['role' => 'data_admin']))->get('/workspace/catalog?flow=airports')->assertOk()->assertSee('Normal')->assertSee('High')->assertSee('Overload')->assertSee('Maintenance')->assertDontSee('>Demo<', false);
        $this->assertSame('Bình thường', InfrastructureDisplay::state(true, 'NORMAL')['label']);
        $this->assertSame('Đang bảo trì',InfrastructureCatalog::CONDITIONS['MAINTENANCE']);
    }
}
