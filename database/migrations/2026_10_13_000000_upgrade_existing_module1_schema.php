<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Upgrade installations created before the expanded Module 1 migrations.
    public function up(): void
    {
        foreach (['country' => ['Vietnam', 100], 'operating_hours' => ['24/7', 100]] as $column => [$default, $length]) {
            if (!Schema::hasColumn('airports', $column)) {
                Schema::table('airports', fn (Blueprint $table) => $table->string($column, $length)->default($default));
            }
        }
        if (!Schema::hasTable('airport_resources')) {
            Schema::create('airport_resources', function (Blueprint $table) {
                $table->id();
                $table->string('airport_code', 3);
                $table->string('resource_type', 30);
                $table->string('resource_code', 50);
                $table->string('status', 20)->default('AVAILABLE');
                $table->timestamps();
                $table->foreign('airport_code')->references('iata_code')->on('airports')->cascadeOnUpdate()->cascadeOnDelete();
                $table->index(['airport_code', 'resource_type']);
                $table->index('status');
                $table->unique(['airport_code', 'resource_type', 'resource_code'], 'unique_airport_resource');
            });
        }
        if (!Schema::hasColumn('routes', 'route_code')) {
            Schema::table('routes', fn (Blueprint $table) => $table->string('route_code', 20)->nullable()->unique());
            foreach (DB::table('routes')->get() as $route) {
                DB::table('routes')->where('id', $route->id)->update(['route_code' => $route->origin_airport_code.'-'.$route->dest_airport_code]);
            }
        }
        if (!Schema::hasColumn('aircrafts', 'total_flight_cycles')) {
            Schema::table('aircrafts', fn (Blueprint $table) => $table->unsignedInteger('total_flight_cycles')->default(0));
        }
        DB::table('aircrafts')->where('operational_status', 'ACTIVE')->update(['operational_status' => 'OPERATIONAL']);
        Schema::table('aircrafts', fn (Blueprint $table) => $table->string('operational_status', 20)->default('OPERATIONAL')->change());
        if (!Schema::hasColumn('flight_schedules', 'status')) {
            Schema::table('flight_schedules', function (Blueprint $table) {
                $table->string('status', 20)->default('PUBLISHED')->index();
            });
            foreach (DB::table('flight_schedules')->get() as $schedule) {
                $days = json_decode($schedule->days_of_week, true);
                if (!is_array($days)) {
                    DB::table('flight_schedules')->where('id', $schedule->id)->update([
                        'days_of_week' => json_encode(array_map('intval', explode(',', $schedule->days_of_week))),
                    ]);
                }
            }
            Schema::table('flight_schedules', fn (Blueprint $table) => $table->json('days_of_week')->nullable()->change());
        }
        if (!Schema::hasColumn('operational_flights', 'gate_number')) {
            Schema::table('operational_flights', fn (Blueprint $table) => $table->string('gate_number', 20)->nullable());
        }
        if (!Schema::hasColumn('operational_flights', 'delay_reason')) {
            Schema::table('operational_flights', fn (Blueprint $table) => $table->text('delay_reason')->nullable());
        }
    }

    public function down(): void
    {
        // Preserve data and columns: an upgrade cannot safely infer the old schema.
    }
};
