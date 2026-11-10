<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('ops')->table('flight_seasons', function (Blueprint $t) {
            $t->string('number', 12)->nullable();
            $t->string('gate', 20)->nullable();
        });
        Schema::connection('ops')->create('flight_season_occurrences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('season_id')->constrained('flight_seasons');
            $t->date('local_date');
            $t->foreignId('flight_id')->unique()->constrained('flights');
            $t->unsignedInteger('plan_version');
            $t->longText('source_snapshot');
            $t->timestamp('created_at');
            $t->unique(['season_id', 'local_date']);
        });
        Schema::connection('ops')->create('flight_schedule_changes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('flight_id')->constrained('flights');
            $t->unsignedInteger('source_version');
            $t->longText('before_data');
            $t->longText('after_data');
            $t->string('reason', 255);
            $t->string('status', 20)->default('PENDING');
            $t->unsignedBigInteger('created_by');
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::connection('ops')->table('flight_season_occurrences')->exists() || DB::connection('ops')->table('flight_schedule_changes')->exists()
            || DB::connection('ops')->table('flight_seasons')->whereNotNull('number')->orWhereNotNull('gate')->exists()) {
            throw new RuntimeException('Rollback refused: schedule sources and history must be preserved.');
        }
        Schema::connection('ops')->drop('flight_schedule_changes');
        Schema::connection('ops')->drop('flight_season_occurrences');
        Schema::connection('ops')->table('flight_seasons', fn (Blueprint $t) => $t->dropColumn(['number', 'gate']));
    }
};
