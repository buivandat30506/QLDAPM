<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('airports')->select('icao_code')->whereNotNull('icao_code')
            ->groupBy('icao_code')->havingRaw('COUNT(*) > 1')->exists();
        if ($duplicate) {
            throw new RuntimeException('Duplicate airport ICAO identifiers. Review existing airports before this migration; no data was rewritten.');
        }
        Schema::table('airports', function (Blueprint $table) {
            $table->unique('icao_code');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });
        Schema::create('domain_locks', function (Blueprint $table) {
            $table->string('name', 80)->primary();
        });
        DB::table('domain_locks')->insert(['name' => 'master-data']);
        Schema::table('master_data_records', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(0);
            foreach (['submitted', 'approved', 'published'] as $step) {
                $table->uuid($step.'_by')->nullable();
                $table->foreign($step.'_by')->references('id')->on('users')->restrictOnDelete();
                $table->dateTime($step.'_at')->nullable();
            }
            $table->index(['category', 'status']);
        });
        Schema::create('master_import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('created_by');
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->string('filename', 180);
            $table->char('sha256', 64);
            $table->string('status', 20);
            $table->unsignedInteger('row_count');
            $table->unsignedInteger('error_count');
            $table->dateTime('committed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
        Schema::create('master_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('batch_id')->constrained('master_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('line');
            $table->json('input');
            $table->json('errors');
            $table->foreignId('record_id')->nullable()->constrained('master_data_records')->restrictOnDelete();
            $table->unique(['batch_id', 'line']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_import_rows');
        Schema::dropIfExists('master_import_batches');
        Schema::table('master_data_records', function (Blueprint $table) {
            foreach (['submitted', 'approved', 'published'] as $step) {
                $table->dropForeign([$step.'_by']);
                $table->dropColumn([$step.'_by', $step.'_at']);
            }
            $table->dropIndex(['category', 'status']);
            $table->dropColumn('revision');
        });
        Schema::dropIfExists('domain_locks');
        Schema::table('airports', function (Blueprint $table) {
            $table->dropUnique(['icao_code']);
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
