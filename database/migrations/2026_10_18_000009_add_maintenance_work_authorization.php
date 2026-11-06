<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $t) {
            $t->foreignUuid('authorized_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('authorized_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $t) { $t->dropConstrainedForeignId('authorized_by'); $t->dropColumn('authorized_at'); });
    }
};
