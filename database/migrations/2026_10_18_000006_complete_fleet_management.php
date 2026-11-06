<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aircrafts', function (Blueprint $t) {
            $t->string('base_airport_code', 3)->nullable();
            $t->foreign('base_airport_code')->references('iata_code')->on('airports')->restrictOnDelete();
            $t->unsignedInteger('revision')->default(1);
            $t->decimal('opening_flight_hours', 10, 2)->default(0);
            $t->unsignedInteger('opening_flight_cycles')->default(0);
            $t->string('opening_reason', 500)->nullable();
        });
        foreach (DB::table('aircrafts')->get() as $aircraft) {
            $hours = DB::table('aircraft_usage_records')->where('aircraft_tail', $aircraft->tail_number)->sum('hours');
            $cycles = DB::table('aircraft_usage_records')->where('aircraft_tail', $aircraft->tail_number)->sum('cycles');
            // Preserve imported totals, including discrepancies; reconciliation exposes them.
            DB::table('aircrafts')->where('tail_number', $aircraft->tail_number)->update([
                'opening_flight_hours' => bcsub((string) $aircraft->total_flight_hours, (string) $hours, 2),
                'opening_flight_cycles' => max(0, $aircraft->total_flight_cycles - $cycles),
                'opening_reason' => 'Số dư chuyển tiếp trước sổ sử dụng M04; cần đối chiếu hồ sơ gốc.',
            ]);
        }
        Schema::create('aircraft_configurations', function (Blueprint $t) {
            $t->id();
            $t->string('aircraft_tail', 20);
            $t->foreign('aircraft_tail')->references('tail_number')->on('aircrafts')->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->string('name', 120);
            $t->json('sections');
            $t->json('seats');
            $t->unsignedInteger('seat_count');
            $t->unsignedInteger('sellable_capacity');
            $t->string('reason', 500);
            $t->foreignUuid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('created_at');
            $t->unique(['aircraft_tail', 'version']);
        });
        Schema::table('aircrafts', fn (Blueprint $t) => $t->foreignId('current_configuration_id')->nullable()->constrained('aircraft_configurations')->restrictOnDelete());
        Schema::table('operational_flights', function (Blueprint $t) {
            $t->foreignId('aircraft_configuration_id')->nullable()->constrained('aircraft_configurations')->restrictOnDelete();
            $t->unsignedInteger('seat_capacity_snapshot')->nullable();
        });
        Schema::table('aircraft_usage_records', function (Blueprint $t) {
            $t->string('source', 40)->default('legacy');
            $t->string('external_id', 100)->nullable();
            $t->timestamp('occurred_at')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->foreignUuid('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->unique(['source', 'external_id'], 'aircraft_usage_source_event_unique');
        });
        Schema::create('aircraft_usage_alerts', function (Blueprint $t) {
            $t->id();
            $t->string('aircraft_tail', 20);
            $t->foreign('aircraft_tail')->references('tail_number')->on('aircrafts')->restrictOnDelete();
            $t->foreignId('usage_record_id')->constrained('aircraft_usage_records')->restrictOnDelete();
            $t->string('level', 20);
            $t->decimal('hours_since_maintenance', 10, 2);
            $t->unsignedInteger('cycles_since_maintenance');
            $t->timestamp('created_at');
            $t->unique('usage_record_id');
        });
        Schema::create('aircraft_usage_receipts', function (Blueprint $t) {
            $t->id();
            $t->string('source', 40);
            $t->string('external_id', 100);
            $t->foreignId('usage_record_id')->constrained('aircraft_usage_records')->restrictOnDelete();
            $t->timestamp('received_at');
            $t->unique(['source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aircraft_usage_receipts');
        Schema::dropIfExists('aircraft_usage_alerts');
        Schema::table('aircraft_usage_records', function (Blueprint $t) {
            $t->dropForeign(['recorded_by']);
            $t->dropUnique('aircraft_usage_source_event_unique');
            $t->dropColumn(['source', 'external_id', 'occurred_at', 'received_at', 'recorded_by']);
        });
        Schema::table('operational_flights', function (Blueprint $t) {
            $t->dropConstrainedForeignId('aircraft_configuration_id');
            $t->dropColumn('seat_capacity_snapshot');
        });
        Schema::table('aircrafts', fn (Blueprint $t) => $t->dropConstrainedForeignId('current_configuration_id'));
        Schema::dropIfExists('aircraft_configurations');
        Schema::table('aircrafts', function (Blueprint $t) {
            $t->dropForeign(['base_airport_code']);
            $t->dropColumn(['base_airport_code', 'revision', 'opening_flight_hours', 'opening_flight_cycles', 'opening_reason']);
        });
    }
};
