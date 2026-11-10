<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('ops')->create('infrastructure_fixture_sets', function (Blueprint $t) {
            $t->string('key', 100)->primary();
            $t->string('airport_code', 3);
            $t->string('status', 16);
            $t->longText('manifest');
            $t->unsignedBigInteger('created_by');
            $t->timestamp('created_at');
            $t->timestamp('cleared_at')->nullable();
            $t->foreign('airport_code')->references('code')->on('airports');
        });
    }

    public function down(): void
    {
        if (DB::connection('ops')->table('infrastructure_fixture_sets')->exists()) {
            throw new RuntimeException('Rollback refused: fixture manifests must be retained for provenance.');
        }
        Schema::connection('ops')->drop('infrastructure_fixture_sets');
    }
};
