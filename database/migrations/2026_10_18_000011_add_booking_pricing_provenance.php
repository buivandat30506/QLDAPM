<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_bookings', function (Blueprint $table) {
            $table->char('currency', 3)->default('VND');
            $table->string('pricing_profile', 40)->default('legacy-v1');
        });
    }

    public function down(): void
    {
        Schema::table('order_bookings', function (Blueprint $table) {
            $table->dropColumn(['currency', 'pricing_profile']);
        });
    }
};
