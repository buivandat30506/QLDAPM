<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plans', function (Blueprint $t) {
            $t->id(); $t->string('plan_code', 50)->unique();
            $t->string('aircraft_tail', 20); $t->foreign('aircraft_tail')->references('tail_number')->on('aircrafts')->restrictOnDelete();
            $t->string('task', 200); $t->string('trigger_type', 20);
            $t->dateTime('starts_at_utc'); $t->dateTime('ends_at_utc'); $t->dateTime('due_at_utc');
            $t->decimal('due_hours', 12, 2)->nullable(); $t->unsignedInteger('due_cycles')->nullable();
            $t->text('condition')->nullable(); $t->boolean('condition_active')->default(false);
            $t->boolean('resets_usage_basis')->default(false); $t->json('tasks');
            $t->unsignedInteger('revision')->default(1); $t->string('status', 20)->default('ACTIVE');
            $t->text('reason'); $t->foreignUuid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamps(); $t->index(['aircraft_tail', 'status']);
        });
        Schema::table('maintenance_orders', function (Blueprint $t) {
            // Existing orders remain legacy; no fabricated plan, signature or attachment is backfilled.
            $t->foreignId('plan_id')->nullable()->unique()->constrained('maintenance_plans')->restrictOnDelete();
            $t->foreignUuid('technician_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('location_id')->nullable()->constrained('airport_resources')->restrictOnDelete();
            $t->foreignUuid('technical_confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('revision')->default(1);
        });
        Schema::create('maintenance_materials', function (Blueprint $t) {
            $t->id(); $t->string('airport_code', 3); $t->foreign('airport_code')->references('iata_code')->on('airports')->restrictOnDelete();
            $t->string('code', 50); $t->string('name', 200); $t->string('unit', 30);
            $t->unsignedInteger('quantity')->default(0); $t->timestamps(); $t->unique(['airport_code', 'code']);
        });
        Schema::create('maintenance_order_materials', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained('maintenance_orders')->restrictOnDelete();
            $t->foreignId('material_id')->constrained('maintenance_materials')->restrictOnDelete();
            $t->unsignedInteger('quantity'); $t->boolean('consumed')->default(false); $t->unique(['order_id', 'material_id']);
        });
        Schema::create('maintenance_stock_moves', function (Blueprint $t) {
            $t->id(); $t->foreignId('material_id')->constrained('maintenance_materials')->restrictOnDelete();
            $t->foreignId('order_id')->nullable()->constrained('maintenance_orders')->restrictOnDelete();
            $t->string('reference', 100); $t->integer('delta'); $t->text('reason');
            $t->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete(); $t->timestamp('created_at');
            $t->unique(['material_id', 'reference']);
        });
        Schema::create('maintenance_order_tasks', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained('maintenance_orders')->restrictOnDelete();
            $t->string('description', 200); $t->text('result')->nullable(); $t->boolean('passed')->default(false);
            $t->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete(); $t->timestamp('recorded_at')->nullable();
        });
        Schema::create('maintenance_evidence', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained('maintenance_orders')->restrictOnDelete();
            $t->string('path'); $t->string('mime', 100); $t->unsignedInteger('bytes'); $t->string('sha256', 64);
            $t->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete(); $t->timestamp('created_at');
        });
        Schema::create('maintenance_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('plan_id')->constrained('maintenance_plans')->restrictOnDelete();
            $t->foreignId('order_id')->nullable()->constrained('maintenance_orders')->restrictOnDelete();
            $t->string('action', 40); $t->json('snapshot'); $t->text('reason');
            $t->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete(); $t->timestamp('created_at');
        });
        Schema::create('maintenance_alerts', function (Blueprint $t) {
            $t->id(); $t->foreignId('plan_id')->constrained('maintenance_plans')->restrictOnDelete();
            $t->string('kind', 30); $t->text('message'); $t->timestamp('observed_at'); $t->timestamp('resolved_at')->nullable();
            $t->unique(['plan_id', 'kind']);
        });
    }

    public function down(): void
    {
        foreach (['maintenance_alerts', 'maintenance_events', 'maintenance_evidence', 'maintenance_order_tasks', 'maintenance_stock_moves', 'maintenance_order_materials', 'maintenance_materials'] as $name) { Schema::dropIfExists($name); }
        Schema::table('maintenance_orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('plan_id'); $t->dropConstrainedForeignId('technician_id');
            $t->dropConstrainedForeignId('location_id'); $t->dropConstrainedForeignId('technical_confirmed_by'); $t->dropColumn('revision');
        });
        Schema::dropIfExists('maintenance_plans');
    }
};
