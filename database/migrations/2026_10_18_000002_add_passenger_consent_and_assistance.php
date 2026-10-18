<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('passenger_profiles', 'created_by')) {
            Schema::table('passenger_profiles', fn (Blueprint $table) => $table->foreignUuid('created_by')->nullable()->constrained('users')->restrictOnDelete());
        }
        Schema::create('passenger_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('passenger_id')->constrained('passenger_profiles')->restrictOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('purpose', 30);
            $table->string('policy_version', 30);
            $table->boolean('granted');
            $table->dateTime('recorded_at_utc');
            $table->index(['passenger_id', 'purpose', 'id']);
        });
        Schema::create('assistance_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('passenger_id')->constrained('passenger_profiles')->restrictOnDelete();
            $table->foreignUuid('flight_id')->nullable()->constrained('operational_flights')->restrictOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('category', 30);
            $table->text('details');
            $table->string('department', 30);
            $table->string('status', 20)->default('ROUTED');
            $table->text('resolution')->nullable();
            $table->timestamps();
            $table->index(['department', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_requests');
        Schema::dropIfExists('passenger_consents');
        // Preserve created_by on existing profiles; use forward-fix for live upgrades.
    }
};
