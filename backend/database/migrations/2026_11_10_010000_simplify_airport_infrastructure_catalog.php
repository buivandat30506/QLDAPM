<?php

use App\Modules\Operations\InfrastructureSimplification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        app(InfrastructureSimplification::class)->preflight();
        $schema = Schema::connection('ops');
        foreach (['terminals' => ['terminal_kind' => 24, 'serves_departures' => null, 'serves_arrivals' => null], 'zones' => ['zone_function' => 32], 'counters' => ['counter_service' => 24], 'taxiways' => ['taxiway_function' => 24]] as $suffix => $fields) {
            $schema->create('infrastructure_'.$suffix, function (Blueprint $t) use ($fields) {
                $t->unsignedBigInteger('infrastructure_id')->primary();
                $t->foreign('infrastructure_id')->references('id')->on('airport_infrastructure');
                foreach ($fields as $name => $length) {
                    if ($length === null) {
                        $t->boolean($name)->nullable();
                    } else {
                        $t->string($name, $length)->nullable();
                    }
                }
            });
        }
        $schema->create('infrastructure_simplification_archive', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
            $t->longText('payload');
            $t->char('after_hash', 64);
            $t->timestamp('created_at');
        });
        app(InfrastructureSimplification::class)->apply();
    }

    public function down(): void
    {
        app(InfrastructureSimplification::class)->rollback();
        foreach (InfrastructureSimplification::NEW_PROFILES as $suffix) {
            Schema::connection('ops')->drop('infrastructure_'.$suffix);
        }
        Schema::connection('ops')->drop('infrastructure_simplification_archive');
    }
};
