<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Aircraft Models (Auto-increment PK, e.g., A321-200, B787-9)
        Schema::create('aircraft_models', function (Blueprint $table) {
            $table->id();
            $table->string('model_code', 30)->unique();
            $table->string('manufacturer', 50); // Airbus, Boeing
            $table->unsignedInteger('seat_capacity');
            $table->unsignedInteger('turnaround_time_minutes')->default(45);
            $table->timestamps();
        });

        // 2. Aircrafts (Natural PK: tail_number, e.g. VN-A601, VN-A861)
        Schema::create('aircrafts', function (Blueprint $table) {
            $table->string('tail_number', 20)->primary();
            $table->foreignId('model_id')->constrained('aircraft_models')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedSmallInteger('manufacture_year');
            $table->decimal('total_flight_hours', 10, 2)->default(0.00);
            $table->unsignedInteger('total_flight_cycles')->default(0); // Chu kỳ cất/hạ cánh
            $table->string('operational_status', 20)->default('OPERATIONAL'); // OPERATIONAL, MAINTENANCE, GROUNDED
            $table->timestamps();

            $table->index('operational_status');
            $table->index('model_id');
        });

        // 3. Flight Schedules (UUID PK, Master Schedule Template)
        Schema::create('flight_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('flight_number', 10); // e.g. VN123
            $table->foreignId('route_id')->constrained('routes')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('model_id')->constrained('aircraft_models')->cascadeOnUpdate()->restrictOnDelete();
            $table->time('departure_time_utc');
            $table->time('arrival_time_utc');
            $table->json('days_of_week')->nullable(); // JSON Array: [1,2,3,4,5,6,7]
            $table->string('status', 20)->default('PUBLISHED'); // DRAFT, APPROVED, PUBLISHED
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['flight_number', 'is_active']);
            $table->index('route_id');
            $table->index('status');
        });

        // 4. Operational Flights (UUID PK, Daily Flight Instance)
        Schema::create('operational_flights', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('schedule_id')->nullable()->constrained('flight_schedules')->cascadeOnUpdate()->nullOnDelete();
            $table->string('flight_number', 10);
            $table->date('flight_date');
            $table->foreignId('route_id')->constrained('routes')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('aircraft_tail', 20);
            $table->dateTime('scheduled_dep_utc');
            $table->dateTime('scheduled_arr_utc');
            $table->dateTime('actual_dep_utc')->nullable();
            $table->dateTime('actual_arr_utc')->nullable();
            $table->string('flight_status', 20)->default('SCHEDULED'); // SCHEDULED, BOARDING, DEPARTED, ARRIVED, DELAYED, CANCELLED
            $table->string('gate_number', 20)->nullable();
            $table->text('delay_reason')->nullable();
            $table->timestamps();

            // Foreign Key for aircraft tail
            $table->foreign('aircraft_tail')
                ->references('tail_number')
                ->on('aircrafts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Indexes for optimal scheduling & conflict queries
            $table->unique(['flight_number', 'flight_date'], 'unique_flight_number_per_date');
            $table->index(['aircraft_tail', 'scheduled_dep_utc'], 'idx_aircraft_departure');
            $table->index(['aircraft_tail', 'scheduled_arr_utc'], 'idx_aircraft_arrival');
            $table->index(['route_id', 'flight_date'], 'idx_route_flight_date');
            $table->index('flight_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_flights');
        Schema::dropIfExists('flight_schedules');
        Schema::dropIfExists('aircrafts');
        Schema::dropIfExists('aircraft_models');
    }
};
