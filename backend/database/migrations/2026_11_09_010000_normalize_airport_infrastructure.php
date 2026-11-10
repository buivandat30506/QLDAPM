<?php

use App\Support\InfrastructureLegacyMap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('ops');
        $schema->table('audit_logs', function (Blueprint $t) {
            $t->longText('before_state')->nullable()->change();
            $t->longText('after_state')->nullable()->change();
        });
        $schema->create('infrastructure_types', function (Blueprint $t) {
            $t->string('code', 40)->primary();
            $t->string('label', 160);
            $t->string('group_code', 24)->index();
            $t->string('category', 16);
            $t->string('allocation_policy', 32)->nullable();
            $t->string('basis', 24);
        });
        // Freeze the original migration; the later simplification migration removes retired metadata.
        DB::connection('ops')->table('infrastructure_types')->insert(InfrastructureLegacyMap::archivedTypes());
        $schema->create('airport_infrastructure', function (Blueprint $t) {
            $t->id();
            $t->string('airport_code', 3);
            $t->string('code', 60);
            $t->string('name', 160)->nullable();
            $t->string('type_code', 40);
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->boolean('active')->default(true);
            $t->string('technical_state', 24)->nullable();
            $t->text('description')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['airport_code', 'code'], 'infra_airport_code_unique');
            $t->unique(['id', 'airport_code'], 'infra_id_airport_unique');
            $t->foreign('airport_code')->references('code')->on('airports');
            $t->foreign('type_code')->references('code')->on('infrastructure_types');
            $t->foreign(['parent_id', 'airport_code'], 'infra_parent_same_airport')->references(['id', 'airport_code'])->on('airport_infrastructure');
        });
        foreach (['airport_resources' => 'resources', 'airport_areas' => 'areas'] as $table => $suffix) {
            $schema->table($table, function (Blueprint $t) use ($suffix) {
                $t->unsignedBigInteger('infrastructure_id')->nullable();
                $t->unique('infrastructure_id', 'infra_'.$suffix.'_unique');
                $t->foreign(['infrastructure_id', 'airport_code'], 'infra_'.$suffix.'_airport_fk')->references(['id', 'airport_code'])->on('airport_infrastructure');
            });
        }
        $schema->create('infrastructure_gates', function (Blueprint $t) {
            $t->unsignedBigInteger('infrastructure_id')->primary();
            $t->boolean('serves_departures')->nullable();
            $t->boolean('serves_arrivals')->nullable();
            $t->foreign('infrastructure_id')->references('id')->on('airport_infrastructure');
        });
        $schema->create('infrastructure_runways', function (Blueprint $t) {
            $t->unsignedBigInteger('infrastructure_id')->primary();
            $t->decimal('length_m', 10, 2)->nullable();
            $t->decimal('width_m', 10, 2)->nullable();
            $t->text('limitations')->nullable();
            $t->foreign('infrastructure_id')->references('id')->on('airport_infrastructure');
        });
        $schema->create('runway_directions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('runway_id');
            $t->string('designator', 12);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['runway_id', 'designator']);
            $t->foreign('runway_id')->references('infrastructure_id')->on('infrastructure_runways');
        });
        $schema->create('infrastructure_belts', function (Blueprint $t) {
            $t->unsignedBigInteger('infrastructure_id')->primary();
            $t->string('baggage_flow', 16)->nullable();
            $t->foreign('infrastructure_id')->references('id')->on('airport_infrastructure');
        });
        $schema->create('infrastructure_stands', function (Blueprint $t) {
            $t->unsignedBigInteger('infrastructure_id')->primary();
            $t->text('compatibility_notes')->nullable();
            $t->foreign('infrastructure_id')->references('id')->on('airport_infrastructure');
        });
        $schema->create('infrastructure_assets', function (Blueprint $t) {
            $t->unsignedBigInteger('infrastructure_id')->primary();
            $t->string('asset_reference', 120)->nullable();
            $t->foreign('infrastructure_id')->references('id')->on('airport_infrastructure');
        });
        $schema->create('infrastructure_links', function (Blueprint $t) {
            $t->id();
            $t->string('airport_code', 3);
            $t->unsignedBigInteger('source_id');
            $t->unsignedBigInteger('target_id');
            $t->string('relation', 24);
            $t->timestamps();
            $t->unique(['source_id', 'target_id', 'relation'], 'infra_link_unique');
            $t->foreign(['source_id', 'airport_code'], 'infra_link_source_fk')->references(['id', 'airport_code'])->on('airport_infrastructure');
            $t->foreign(['target_id', 'airport_code'], 'infra_link_target_fk')->references(['id', 'airport_code'])->on('airport_infrastructure');
        });
        $schema->table('ground_equipment', function (Blueprint $t) {
            $t->unsignedBigInteger('location_infrastructure_id')->nullable();
            $t->foreign(['location_infrastructure_id', 'airport_code'], 'ground_equipment_location_fk')->references(['id', 'airport_code'])->on('airport_infrastructure');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('ops');
        if (DB::connection('ops')->table('ground_equipment')->whereNotNull('location_infrastructure_id')->exists()) {
            throw new RuntimeException('Rollback refused: equipment location references require a reviewed data migration.');
        }
        if (DB::connection('ops')->table('audit_logs')->whereIn('action', ['infrastructure.created', 'infrastructure.updated'])->exists()) {
            throw new RuntimeException('Rollback refused: new infrastructure data requires a reviewed data migration.');
        }
        if (DB::connection('ops')->table('audit_logs')->whereRaw('CHAR_LENGTH(before_state) > 255 OR CHAR_LENGTH(after_state) > 255')->exists()) {
            throw new RuntimeException('Rollback refused: audit data exceeds legacy capacity.');
        }
        $schema->table('ground_equipment', function (Blueprint $t) {
            $t->dropForeign('ground_equipment_location_fk');
            $t->dropColumn('location_infrastructure_id');
        });
        foreach (['airport_resources' => 'resources', 'airport_areas' => 'areas'] as $table => $suffix) {
            $schema->table($table, function (Blueprint $t) use ($suffix) {
                $t->dropForeign('infra_'.$suffix.'_airport_fk');
                $t->dropUnique('infra_'.$suffix.'_unique');
                $t->dropColumn('infrastructure_id');
            });
        }
        foreach (['infrastructure_links', 'runway_directions', 'infrastructure_assets', 'infrastructure_stands', 'infrastructure_belts', 'infrastructure_runways', 'infrastructure_gates', 'airport_infrastructure', 'infrastructure_types'] as $table) {
            $schema->drop($table);
        }
        $schema->table('audit_logs', function (Blueprint $t) {
            $t->string('before_state', 255)->nullable()->change();
            $t->string('after_state', 255)->nullable()->change();
        });
    }
};
