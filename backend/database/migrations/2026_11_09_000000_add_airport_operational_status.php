<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('ops')->table('airports', function (Blueprint $table) {
            $table->string('operational_status', 20)->nullable()->index();
            $table->string('operational_status_note', 500)->nullable();
            // Users belong to the commerce node; do not create a cross-node FK.
            $table->unsignedBigInteger('operational_status_updated_by')->nullable();
            $table->timestamp('operational_status_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('ops')->table('airports', function (Blueprint $table) {
            $table->dropColumn(['operational_status', 'operational_status_note', 'operational_status_updated_by', 'operational_status_updated_at']);
        });
    }
};
