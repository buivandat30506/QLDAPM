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
        // 1. Crew Members (Hồ sơ nhân sự tổ bay - UC-15)
        Schema::create('crew_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('crew_code', 20)->unique(); // VD: CRW-001
            $table->string('full_name', 100);
            $table->string('email', 100)->unique();
            $table->string('phone', 20);
            $table->string('rank', 30); // CAPTAIN, FIRST_OFFICER, PURSER, FLIGHT_ATTENDANT
            $table->string('base_airport_code', 3); // FK -> airports.iata_code
            $table->string('license_number', 50)->unique();
            $table->string('status', 20)->default('AVAILABLE'); // AVAILABLE, ON_DUTY, RESTING, LEAVE
            $table->decimal('total_flight_hours', 10, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('base_airport_code')
                ->references('iata_code')
                ->on('airports')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->index('rank');
            $table->index('status');
            $table->index('base_airport_code');
        });

        // 2. Flight Crew Assignments (Phân công tổ bay cho chuyến bay - UC-16, UC-17)
        Schema::create('flight_crew_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('flight_id')->constrained('operational_flights')->cascadeOnDelete();
            $table->foreignUuid('crew_id')->constrained('crew_members')->cascadeOnDelete();
            $table->string('assigned_position', 30); // CAPTAIN, FIRST_OFFICER, PURSER, FLIGHT_ATTENDANT
            $table->dateTime('duty_start_utc');
            $table->dateTime('duty_end_utc');
            $table->string('status', 20)->default('ASSIGNED'); // ASSIGNED, CONFIRMED, REPLACED
            $table->text('notes')->nullable();
            $table->timestamps();

            // Ràng buộc duy nhất: Một nhân sự không thể phân công 2 lần trên cùng một chuyến bay
            $table->unique(['flight_id', 'crew_id'], 'unique_flight_crew_assignment');
            $table->index(['crew_id', 'duty_start_utc'], 'idx_crew_duty_start');
            $table->index(['crew_id', 'duty_end_utc'], 'idx_crew_duty_end');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flight_crew_assignments');
        Schema::dropIfExists('crew_members');
    }
};
