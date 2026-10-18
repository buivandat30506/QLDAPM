<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 80);
                $table->string('entity_id', 80);
                $table->string('action', 80);
                $table->uuid('actor_id')->nullable();
                $table->json('before_data')->nullable();
                $table->json('after_data')->nullable();
                $table->text('reason')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['entity_type', 'entity_id']);
            });
        }
        if (! Schema::hasTable('entity_versions')) {
            Schema::create('entity_versions', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 80);
                $table->string('entity_id', 80);
                $table->unsignedInteger('version');
                $table->json('snapshot');
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['entity_type', 'entity_id', 'version'], 'unique_entity_version');
            });
        }
        if (! Schema::hasTable('master_data_records')) {
            Schema::create('master_data_records', function (Blueprint $table) {
                $table->id();
                $table->string('category', 50);
                $table->string('code', 50);
                $table->json('payload');
                $table->string('status', 20)->default('DRAFT');
                $table->unsignedInteger('version')->default(0);
                $table->timestamps();
                $table->unique(['category', 'code']);
            });
        }
        if (! Schema::hasColumn('flight_schedules', 'aircraft_tail')) {
            Schema::table('flight_schedules', function (Blueprint $table) {
                $table->string('aircraft_tail', 20)->nullable();
                $table->foreign('aircraft_tail')->references('tail_number')->on('aircrafts')->restrictOnDelete();
                $table->foreignId('resource_id')->nullable()->constrained('airport_resources')->restrictOnDelete();
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->unsignedInteger('version')->default(0);
                $table->text('validation_notes')->nullable();
            });
        }
        if (! Schema::hasColumn('operational_flights', 'resource_id')) {
            Schema::table('operational_flights', function (Blueprint $table) {
                $table->foreignId('resource_id')->nullable()->constrained('airport_resources')->restrictOnDelete();
            });
        }
        if (! Schema::hasColumn('crew_members', 'license_expires_on')) {
            Schema::table('crew_members', function (Blueprint $table) {
                $table->date('license_expires_on')->nullable();
                $table->date('training_expires_on')->nullable();
                $table->json('qualified_model_ids')->nullable();
                $table->dateTime('available_from_utc')->nullable();
                $table->dateTime('available_until_utc')->nullable();
                $table->uuid('user_id')->nullable()->index();
            });
        }
        if (! Schema::hasColumn('flight_crew_assignments', 'approved_at')) {
            Schema::table('flight_crew_assignments', function (Blueprint $table) {
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
            });
        }
        if (! Schema::hasColumn('aircrafts', 'maintenance_base_hours')) {
            Schema::table('aircrafts', function (Blueprint $table) {
                $table->decimal('maintenance_base_hours', 10, 2)->default(0);
                $table->unsignedInteger('maintenance_base_cycles')->default(0);
            });
        }
        if (! Schema::hasTable('aircraft_usage_records')) {
            Schema::create('aircraft_usage_records', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('flight_id')->unique()->constrained('operational_flights')->restrictOnDelete();
                $table->string('aircraft_tail', 20);
                $table->foreign('aircraft_tail')->references('tail_number')->on('aircrafts')->restrictOnDelete();
                $table->decimal('hours', 10, 2);
                $table->unsignedInteger('cycles')->default(1);
                $table->timestamp('created_at')->useCurrent();
            });
        }
        if (! Schema::hasTable('flight_events')) {
            Schema::create('flight_events', function (Blueprint $table) {
                $table->id();
                $table->string('source', 30);
                $table->string('external_id', 100);
                $table->uuid('flight_id')->nullable();
                $table->json('payload');
                $table->string('status', 30);
                $table->text('message')->nullable();
                $table->timestamps();
                $table->unique(['source', 'external_id']);
            });
        }
        if (! Schema::hasTable('maintenance_orders')) {
            Schema::create('maintenance_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_code', 50)->unique();
                $table->string('aircraft_tail', 20);
                $table->foreign('aircraft_tail')->references('tail_number')->on('aircrafts')->restrictOnDelete();
                $table->string('task', 200);
                $table->dateTime('starts_at_utc');
                $table->dateTime('ends_at_utc');
                $table->dateTime('due_at_utc');
                $table->string('technician', 100)->nullable();
                $table->string('location', 100)->nullable();
                $table->boolean('materials_ready')->default(false);
                $table->string('status', 30)->default('PLANNED');
                $table->text('result')->nullable();
                $table->string('evidence', 500)->nullable();
                $table->timestamp('technical_confirmed_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->uuid('approved_by')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('crew_notifications')) {
            Schema::create('crew_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('assignment_id')->constrained('flight_crew_assignments')->restrictOnDelete();
                $table->text('message');
                $table->timestamp('read_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_notifications');
        Schema::dropIfExists('maintenance_orders');
        Schema::dropIfExists('flight_events');
        Schema::dropIfExists('aircraft_usage_records');
        Schema::table('aircrafts', fn (Blueprint $t) => $t->dropColumn(['maintenance_base_hours', 'maintenance_base_cycles']));
        Schema::table('flight_crew_assignments', fn (Blueprint $t) => $t->dropColumn(['approved_at', 'confirmed_at']));
        Schema::table('crew_members', function (Blueprint $t) {
            $t->dropColumn('user_id');
            $t->dropColumn(['license_expires_on', 'training_expires_on', 'qualified_model_ids', 'available_from_utc', 'available_until_utc']);
        });
        Schema::table('operational_flights', fn (Blueprint $t) => $t->dropConstrainedForeignId('resource_id'));
        Schema::table('flight_schedules', function (Blueprint $t) {
            $t->dropConstrainedForeignId('resource_id');
            $t->dropForeign(['aircraft_tail']);
            $t->dropColumn(['aircraft_tail', 'effective_from', 'effective_to', 'version', 'validation_notes']);
        });
        Schema::dropIfExists('master_data_records');
        Schema::dropIfExists('entity_versions');
        Schema::dropIfExists('audit_logs');
    }
};
