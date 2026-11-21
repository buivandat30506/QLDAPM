<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('ops')->table('airports', function (Blueprint $table) {
            $table->string('operational_status_source', 24)->nullable();
            $table->string('operational_status_simulation_key', 40)->nullable();
            $table->char('operational_status_simulation_fingerprint', 64)->nullable();
        });
        // A bounded permission assignment, independent of primary roles and Multi-role WIP.
        Schema::connection('mysql')->create('airport_status_authorities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('position', 32);
            $table->string('airport_scope', 3); // IATA code, or explicit '*' for system director.
            $table->boolean('active')->default(true);
            $table->string('reason', 500);
            $table->timestamps();
            $table->unique(['user_id', 'airport_scope']);
        });
    }

    public function down(): void
    {
        if (DB::connection('mysql')->table('airport_status_authorities')->exists()
            || DB::connection('ops')->table('airports')->whereNotNull('operational_status_source')->exists()) {
            throw new RuntimeException('Rollback refused: status provenance or authority data must be preserved.');
        }
        Schema::connection('mysql')->drop('airport_status_authorities');
        Schema::connection('ops')->table('airports', function (Blueprint $table) {
            $table->dropColumn(['operational_status_source', 'operational_status_simulation_key', 'operational_status_simulation_fingerprint']);
        });
    }
};
