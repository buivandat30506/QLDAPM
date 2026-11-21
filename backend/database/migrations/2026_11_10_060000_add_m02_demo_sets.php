<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('ops')->create('m02_demo_sets', function (Blueprint $t) {
            $t->string('key', 60)->primary();
            $t->date('starts_on');
            $t->string('status', 20);
            $t->json('actors');
            $t->longText('manifest');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::connection('ops')->table('m02_demo_sets')->exists()) {
            throw new RuntimeException('Keep M02 scenario provenance.');
        }Schema::connection('ops')->drop('m02_demo_sets');
    }
};
