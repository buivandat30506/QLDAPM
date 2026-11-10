<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('ops')->create('demo_installations', function (Blueprint $t) {
            $t->string('id', 60)->primary();
            $t->uuid('run_id')->unique();
            $t->string('status', 20);
            $t->json('summary')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::connection('ops')->table('demo_installations')->exists()) {
            throw new RuntimeException('Demo installation provenance must be preserved.');
        }
        Schema::connection('ops')->drop('demo_installations');
    }
};
