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
        // 1. Passenger Profiles (UUID PK)
        Schema::create('passenger_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('id_card_passport', 50)->unique();
            $table->string('full_name', 100);
            $table->date('dob');
            $table->string('gender', 10); // MALE, FEMALE, OTHER
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->timestamps();

            $table->index('full_name');
            $table->index('phone');
        });

        // 2. Order Bookings (UUID PK, 6-character PNR code)
        Schema::create('order_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_code', 6)->unique(); // PNR code, e.g. 'VJ889A'
            $table->foreignUuid('customer_user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->string('order_status', 20)->default('HOLDING'); // HOLDING, CONFIRMED, CANCELLED, REFUNDED
            $table->dateTime('hold_expires_at')->comment('BR-011: Hold lock for maximum 15 minutes');
            $table->timestamps();

            $table->index(['order_status', 'hold_expires_at'], 'idx_order_status_hold_expires');
            $table->index('customer_user_id');
        });

        // 3. Order Items (UUID PK, line items per flight & passenger)
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('order_bookings')->cascadeOnDelete();
            $table->foreignUuid('flight_id')->constrained('operational_flights')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUuid('passenger_id')->constrained('passenger_profiles')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('item_type', 20); // SEAT, BAGGAGE_EXTRA
            $table->decimal('amount', 12, 2)->default(0.00);
            $table->timestamps();

            $table->index(['flight_id', 'passenger_id']);
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('order_bookings');
        Schema::dropIfExists('passenger_profiles');
    }
};
