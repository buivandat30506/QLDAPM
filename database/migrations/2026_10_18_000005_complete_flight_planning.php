<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('domain_locks')->insertOrIgnore(['name' => 'flight-planning']);
        Schema::table('routes', function (Blueprint $table) {
            $table->date('operating_from')->nullable();
            $table->date('operating_to')->nullable();
            $table->json('allowed_model_ids')->nullable();
            $table->json('blocked_weekdays')->nullable();
            $table->text('restriction_notes')->nullable();
            $table->unsignedInteger('revision')->default(1);
        });
        Schema::table('flight_schedules', function (Blueprint $table) {
            $table->uuid('series_id')->nullable()->index();
            $table->foreignUuid('supersedes_id')->nullable()->constrained('flight_schedules')->restrictOnDelete();
            $table->uuid('open_series_id')->nullable()->unique();
            $table->unsignedInteger('revision')->default(1);
            $table->string('season_code', 30)->nullable();
            $table->json('excluded_dates')->nullable();
            $table->json('extra_dates')->nullable();
            $table->string('source_timezone', 64)->default('UTC');
            $table->time('departure_time_local')->nullable();
            $table->time('arrival_time_local')->nullable();
            $table->unsignedTinyInteger('arrival_day_offset')->default(0);
            $table->text('change_reason')->nullable();
            foreach (['created', 'submitted', 'approved', 'published'] as $step) {
                $table->foreignUuid($step.'_by')->nullable()->constrained('users')->restrictOnDelete();
                if ($step !== 'created') { $table->dateTime($step.'_at')->nullable(); }
            }
        });
        DB::table('flight_schedules')->orderBy('id')->each(function ($schedule) {
            DB::table('flight_schedules')->where('id', $schedule->id)->update([
                'series_id' => $schedule->id,
                'open_series_id' => in_array($schedule->status, ['DRAFT', 'PENDING', 'APPROVED']) ? $schedule->id : null,
            ]);
        });
        Schema::create('flight_schedule_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('schedule_id')->constrained('flight_schedules')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->foreignUuid('checked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('is_valid');
            $table->unsignedInteger('occurrence_count');
            $table->json('issues');
            $table->json('impacts');
            $table->dateTime('created_at');
        });
        Schema::table('operational_flights', fn (Blueprint $table) => $table->date('service_date')->nullable());
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE routes ADD CONSTRAINT routes_season_dates CHECK ((operating_from IS NULL AND operating_to IS NULL) OR (operating_from IS NOT NULL AND operating_to IS NOT NULL AND operating_to >= operating_from))');
            DB::statement('ALTER TABLE flight_schedules ADD CONSTRAINT schedules_arrival_offset CHECK (arrival_day_offset <= 2)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flight_schedule_checks');
        Schema::table('operational_flights', fn (Blueprint $table) => $table->dropColumn('service_date'));
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE routes DROP CHECK routes_season_dates');
            DB::statement('ALTER TABLE flight_schedules DROP CHECK schedules_arrival_offset');
        }
        Schema::table('flight_schedules', function (Blueprint $table) {
            foreach (['supersedes_id', 'created_by', 'submitted_by', 'approved_by', 'published_by'] as $column) { $table->dropForeign([$column]); }
            $table->dropColumn(['series_id', 'supersedes_id', 'open_series_id', 'revision', 'season_code', 'excluded_dates', 'extra_dates',
                'source_timezone', 'departure_time_local', 'arrival_time_local', 'arrival_day_offset', 'change_reason',
                'created_by', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'published_by', 'published_at']);
        });
        Schema::table('routes', fn (Blueprint $table) => $table->dropColumn(['operating_from', 'operating_to', 'allowed_model_ids', 'blocked_weekdays', 'restriction_notes', 'revision']));
        DB::table('domain_locks')->where('name', 'flight-planning')->delete();
    }
};
