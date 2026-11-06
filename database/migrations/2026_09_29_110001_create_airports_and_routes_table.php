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
        // 1. Airports table (Natural PK: IATA Code 3 chars, e.g. HAN, SGN, DAD, CXR, PQC)
        Schema::create('airports', function (Blueprint $table) {
            $table->string('iata_code', 3)->primary();
            $table->string('icao_code', 4)->nullable();
            $table->string('name', 150);
            $table->string('city', 100);
            $table->string('country', 100)->default('Vietnam');
            $table->string('timezone', 50)->default('Asia/Ho_Chi_Minh');
            $table->string('operating_hours', 100)->default('24/7');
            $table->string('status', 20)->default('ACTIVE'); // ACTIVE, INACTIVE, CLOSED
            $table->timestamps();

            $table->index('city');
            $table->index('status');
        });

        // 2. Airport Resources table (Tài nguyên sân bay: Nhà ga, Cửa, Đường băng, Băng chuyền)
        Schema::create('airport_resources', function (Blueprint $table) {
            $table->id();
            $table->string('airport_code', 3);
            $table->string('resource_type', 30); // TERMINAL, GATE, RUNWAY, BAGGAGE_CAROUSEL
            $table->string('resource_code', 50); // VD: T1, Gate 01, Runway 25L, Carousel 03
            $table->string('status', 20)->default('AVAILABLE'); // AVAILABLE, MAINTENANCE, OCCUPIED
            $table->timestamps();

            $table->foreign('airport_code')
                ->references('iata_code')
                ->on('airports')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->index(['airport_code', 'resource_type']);
            $table->index('status');
            $table->unique(['airport_code', 'resource_type', 'resource_code'], 'unique_airport_resource');
        });

        // 3. Routes table (Auto-increment PK, FKs to airports)
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('route_code', 20)->nullable()->unique(); // VD: HAN-SGN
            $table->string('origin_airport_code', 3);
            $table->string('dest_airport_code', 3);
            $table->decimal('distance_km', 8, 2);
            $table->unsignedInteger('standard_duration_minutes');
            $table->string('status', 20)->default('ACTIVE'); // ACTIVE, SUSPENDED
            $table->timestamps();

            // Foreign keys
            $table->foreign('origin_airport_code')
                ->references('iata_code')
                ->on('airports')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('dest_airport_code')
                ->references('iata_code')
                ->on('airports')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Unique constraint: Không thể tạo duplicate route cùng cặp sân bay
            $table->unique(['origin_airport_code', 'dest_airport_code'], 'unique_origin_dest_route');
            $table->index(['origin_airport_code', 'status']);
            $table->index(['dest_airport_code', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routes');
        Schema::dropIfExists('airport_resources');
        Schema::dropIfExists('airports');
    }
};
