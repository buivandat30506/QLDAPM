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
        // 1. Check-in Records (UUID PK)
        Schema::create('checkin_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('flight_id')->constrained('operational_flights')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUuid('passenger_id')->constrained('passenger_profiles')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('seat_number', 10); // e.g. 12A, 14F
            $table->dateTime('checkin_time_utc');
            $table->timestamps();

            // Ràng buộc duy nhất: Một chuyến bay không thể có 2 hành khách cùng ghế
            $table->unique(['flight_id', 'seat_number'], 'unique_flight_seat');
            // Mỗi hành khách chỉ checkin 1 lần trên 1 chuyến bay
            $table->unique(['flight_id', 'passenger_id'], 'unique_flight_passenger_checkin');
            $table->index('flight_id');
        });

        // 2. Boarding Pass Tokens (UUID PK, Unique 1-1 with checkin_record)
        Schema::create('boarding_pass_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('checkin_id')->unique()->constrained('checkin_records')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique(); // SHA256 / JWT hash
            $table->boolean('is_used')->default(false);
            $table->dateTime('valid_until');
            $table->dateTime('boarded_at_utc')->nullable();
            $table->timestamps();

            $table->index(['is_used', 'valid_until']);
        });

        // 3. Baggage Tags (Natural PK: 10-char string, e.g. 'VN12345678')
        Schema::create('baggage_tags', function (Blueprint $table) {
            $table->string('tag_code', 10)->primary();
            $table->foreignUuid('flight_id')->constrained('operational_flights')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUuid('passenger_id')->constrained('passenger_profiles')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('weight_kg', 5, 2);
            $table->string('status', 20)->default('CHECKED_IN'); // CHECKED_IN, LOADED, TRANSIT, DELIVERED, LOST
            $table->timestamps();

            $table->index(['flight_id', 'status']);
            $table->index('passenger_id');
        });

        // 4. Baggage Tracking Events (BigInt PK, Immutable audit log)
        Schema::create('baggage_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->string('tag_code', 10);
            $table->string('event_type', 30); // CHECK_IN, LOADED_TO_AIRCRAFT, UNLOADED, CLAIMED
            $table->dateTime('scan_time_utc');
            $table->string('location', 100); // e.g. Gate 12, Terminal 1, Cargo Bay
            $table->string('custody_unit', 100); // e.g. Ground Handler VIAGS, Baggage Unit #3
            $table->timestamps();

            $table->foreign('tag_code')
                ->references('tag_code')
                ->on('baggage_tags')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->index(['tag_code', 'scan_time_utc'], 'idx_tag_scan_time');
            $table->index('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('baggage_tracking_events');
        Schema::dropIfExists('baggage_tags');
        Schema::dropIfExists('boarding_pass_tokens');
        Schema::dropIfExists('checkin_records');
    }
};
