<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL is not transactional: permit resuming after an interrupted schema update.
        if (! Schema::connection('ops')->hasColumn('infrastructure_terminals', 'terminal_scope')) {
            Schema::connection('ops')->table('infrastructure_terminals', function (Blueprint $t) {
                $t->string('terminal_scope', 24)->nullable();
            });
        }
        if (! Schema::connection('ops')->hasTable('infrastructure_sources')) {
            Schema::connection('ops')->create('infrastructure_sources', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('infrastructure_id');
                $t->string('entry_method', 24);
                $t->string('source_reference', 500)->nullable();
                $t->unsignedBigInteger('recorded_by');
                $t->timestamp('created_at');
                $t->foreign('infrastructure_id')->references('id')->on('airport_infrastructure');
            });
        }
    }

    public function down(): void
    {
        if (DB::connection('ops')->table('infrastructure_sources')->exists()
            || DB::connection('ops')->table('infrastructure_terminals')->whereNotNull('terminal_scope')->exists()
            || DB::connection('ops')->table('infrastructure_links')->whereIn('relation', ['JET_BRIDGE_GATE', 'JET_BRIDGE_STAND'])->exists()) {
            throw new RuntimeException('Rollback refused: management data requires a reviewed data migration.');
        }
        Schema::connection('ops')->drop('infrastructure_sources');
        Schema::connection('ops')->table('infrastructure_terminals', fn (Blueprint $t) => $t->dropColumn('terminal_scope'));
    }
};
