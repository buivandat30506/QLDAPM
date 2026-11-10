<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Operations\InfrastructureService;
use App\Modules\Operations\InfrastructureSimplification;
use App\Support\InfrastructureCatalog;
use App\Support\InfrastructureLegacyMap;
use Database\Seeders\InfrastructureTypeSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InfrastructureSimplificationTest extends TestCase
{
    private function legacyTypes(): void
    {
        foreach (InfrastructureLegacyMap::archivedTypes() as $type) {
            DB::connection('ops')->table('infrastructure_types')->updateOrInsert(['code' => $type['code']], $type);
        }
    }

    private function legacy(string $type, string $code): int
    {
        return DB::connection('ops')->table('airport_infrastructure')->insertGetId(['airport_code' => 'HAN', 'code' => $code, 'type_code' => $type, 'active' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_all_exact_conversions_preserve_physical_ids_and_retain_original_classification_in_audit(): void
    {
        $this->legacyTypes();
        $db = DB::connection('ops');
        $ids = [];
        foreach (InfrastructureLegacyMap::CONVERSIONS as $old => $mapping) {
            $ids[$old] = $this->legacy($old, 'OLD-'.$old);
        }
        $resource = $db->table('airport_resources')->insertGetId(['airport_code' => 'HAN', 'code' => 'OLD-BAG_DROP', 'type' => 'BAG_DROP', 'active' => true, 'infrastructure_id' => $ids['BAG_DROP']]);
        $db->table('resource_allocations')->insert(['resource_id' => $resource, 'flight_id' => 1, 'starts_at' => now(), 'ends_at' => now()->addHour(), 'reason' => 'Existing allocation', 'actor_id' => 1]);
        $allocations = $db->table('resource_allocations')->get()->toJson();
        $service = app(InfrastructureSimplification::class);
        $service->apply();
        $this->assertSame(10, $db->table('infrastructure_types')->count());
        $this->assertCount(4, InfrastructureCatalog::GROUPS);
        $this->assertSame(count($ids), $db->table('airport_infrastructure')->count());
        $this->assertSame($allocations, $db->table('resource_allocations')->get()->toJson());
        $this->assertDatabaseHas('airport_resources', ['id' => $resource, 'infrastructure_id' => $ids['BAG_DROP'], 'type' => 'COUNTER'], 'ops');
        foreach (InfrastructureLegacyMap::CONVERSIONS as $old => [$target, $profile, $attributes]) {
            $this->assertDatabaseHas('airport_infrastructure', ['id' => $ids[$old], 'type_code' => $target, 'parent_id' => null], 'ops');
            $this->assertDatabaseHas('infrastructure_'.$profile, ['infrastructure_id' => $ids[$old]] + $attributes, 'ops');
            $audit = $db->table('audit_logs')->where('action', 'infrastructure.classification.normalized')->where('object_id', (string) $ids[$old])->first();
            $this->assertSame($old, json_decode($audit->before_state, true)['type_code']);
            $this->assertNotSame($old, InfrastructureCatalog::label($old));
        }
        $service->rollback();
        $this->assertSame(41, $db->table('infrastructure_types')->count());
        $this->assertDatabaseHas('airport_resources', ['id' => $resource, 'type' => 'BAG_DROP'], 'ops');
        $this->assertSame($allocations, $db->table('resource_allocations')->get()->toJson());
    }

    public function test_unmapped_physical_objects_block_deletion_without_partial_changes(): void
    {
        $this->legacyTypes();
        $id = $this->legacy('HANGAR', 'REAL-HANGAR');
        try {
            app(InfrastructureSimplification::class)->apply();
            $this->fail('Unmapped object accepted');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('HANGAR', $e->getMessage());
        }
        $this->assertSame(41, DB::connection('ops')->table('infrastructure_types')->count());
        $this->assertDatabaseHas('airport_infrastructure', ['id' => $id, 'type_code' => 'HANGAR'], 'ops');
        $this->assertSame(0, DB::connection('ops')->table('infrastructure_simplification_archive')->count());
    }

    public function test_rollback_refuses_to_discard_new_attributes_or_business_changes(): void
    {
        $service = app(InfrastructureSimplification::class);
        $service->apply();
        $id = $this->legacy('ZONE', 'NEW-ZONE');
        DB::connection('ops')->table('infrastructure_zones')->insert(['infrastructure_id' => $id, 'zone_function' => 'SECURITY']);
        try {
            $service->rollback();
            $this->fail('Destructive rollback accepted');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Rollback refused', $e->getMessage());
        }
        $this->assertDatabaseHas('infrastructure_zones', ['infrastructure_id' => $id, 'zone_function' => 'SECURITY'], 'ops');
    }

    public function test_pending_legacy_proposals_block_removal_and_seeders_never_restore_retired_types(): void
    {
        $this->legacyTypes();
        $db = DB::connection('ops');
        $id = $db->table('master_changes')->insertGetId(['entity' => 'airport_areas', 'record_id' => 'new', 'before_data' => 'null', 'after_data' => json_encode(['type' => 'CLAIM_AREA']), 'status' => 'PENDING', 'reason' => 'Pending legacy classification', 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
        try {
            app(InfrastructureSimplification::class)->apply();
            $this->fail('Pending proposal discarded');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('#'.$id, $e->getMessage());
        }
        $this->assertSame(41, $db->table('infrastructure_types')->count());
        $db->table('master_changes')->where('id', $id)->update(['status' => 'APPROVED']);
        app(InfrastructureSimplification::class)->apply();
        $this->seed(InfrastructureTypeSeeder::class);
        $this->assertSame(10, $db->table('infrastructure_types')->count());
        $this->assertSame(['type' => 'CLAIM_AREA'], json_decode($db->table('master_changes')->where('id', $id)->value('after_data'), true));
    }

    public function test_only_ten_types_are_accepted_and_attributes_follow_their_physical_type(): void
    {
        $author = User::factory()->create(['role' => 'data_admin', 'airport_code' => 'HAN']);
        $reviewer = User::factory()->create(['role' => 'data_admin', 'airport_code' => 'HAN']);
        $service = app(InfrastructureService::class);
        $this->actingAs($author);
        $base = ['airport_code' => 'HAN', 'active' => true];
        foreach (InfrastructureLegacyMap::retired() as $type) {
            $this->postJson('/workspace/catalog/infrastructure/items', $base + ['code' => 'REJECT-'.$type, 'type_code' => $type])->assertUnprocessable();
        }
        foreach (['TERMINAL' => ['terminal_kind' => 'MIXED', 'serves_departures' => true, 'serves_arrivals' => true], 'ZONE' => ['zone_function' => 'SECURITY'], 'COUNTER' => ['counter_service' => 'COMBINED'], 'BELT' => ['baggage_flow' => 'BOTH'], 'TAXIWAY' => ['taxiway_function' => 'STAND_ACCESS']] as $type => $attributes) {
            $input = $base + ['code' => 'ATTR-'.$type, 'type_code' => $type] + $attributes;
            $id = $service->create($author, $input)->id;
            $this->assertDatabaseHas('infrastructure_'.InfrastructureCatalog::PROFILE_TABLES[$type], ['infrastructure_id' => $id] + $attributes, 'ops');
            $this->get('/workspace/catalog/infrastructure/items/'.$id)->assertOk();
            $change = $service->propose($author, $id, $base + ['code' => 'ATTR-'.$type, 'type_code' => $type], 'Preserve omitted attributes')->id;
            // Pending snapshots from the previous additive schema have fewer NULL profile keys.
            $row = DB::connection('ops')->table('master_changes')->find($change);
            $before = json_decode($row->before_data, true);
            $before['profiles'] = array_filter($before['profiles'], fn ($value) => $value !== null);
            DB::connection('ops')->table('master_changes')->where('id', $change)->update(['before_data' => json_encode($before)]);
            $service->approve($reviewer, $change);
            $this->assertDatabaseHas('infrastructure_'.InfrastructureCatalog::PROFILE_TABLES[$type], ['infrastructure_id' => $id] + $attributes, 'ops');
        }
        $this->postJson('/workspace/catalog/infrastructure/items', $base + ['code' => 'BAD-ATTR', 'type_code' => 'ZONE', 'counter_service' => 'COMBINED'])->assertUnprocessable();
        $this->getJson('/api/v1/airport-infrastructure?airport=HAN')->assertOk()->assertJsonCount(10, 'types');
        $this->assertSame(0, DB::connection('ops')->table('resource_allocations')->count());
    }
}
