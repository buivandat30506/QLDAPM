<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('operational_flights', function (Blueprint $t) {
            $t->unsignedInteger('revision')->default(0);
            $t->dateTime('estimated_dep_utc')->nullable();
            $t->dateTime('estimated_arr_utc')->nullable();
            $t->dateTime('latest_event_at_utc')->nullable();
            $t->string('checkin_mode', 10)->default('AUTO');
            $t->dateTime('closed_at_utc')->nullable();
            $t->foreignUuid('closed_by')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::table('flight_events', function (Blueprint $t) {
            $t->dateTime('source_time_utc')->nullable();
            $t->string('source_timezone', 64)->nullable();
            $t->unsignedBigInteger('sequence')->nullable();
            $t->string('profile', 30)->default('legacy-local');
            $t->foreignUuid('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('attempts')->default(0);
            $t->index(['flight_id', 'source', 'sequence'], 'flight_event_stream_idx');
        });
        Schema::create('flight_operation_history', function (Blueprint $t) {
            $t->id(); $t->foreignUuid('flight_id')->constrained('operational_flights')->restrictOnDelete();
            $t->unsignedInteger('revision'); $t->string('action', 40);
            $t->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('reason'); $t->json('snapshot'); $t->dateTime('created_at');
            $t->unique(['flight_id', 'revision']);
        });
        Schema::create('flight_recovery_proposals', function (Blueprint $t) {
            $t->id(); $t->foreignUuid('flight_id')->constrained('operational_flights')->restrictOnDelete();
            $t->foreignId('event_id')->constrained('flight_events')->restrictOnDelete();
            $t->unsignedInteger('flight_revision'); $t->string('action', 30); $t->string('status', 20)->default('PROPOSED');
            $t->json('plan'); $t->json('impact'); $t->string('impact_hash', 64); $t->text('reason');
            $t->foreignUuid('proposed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignUuid('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('decision_reason')->nullable(); $t->dateTime('decided_at')->nullable(); $t->timestamps();
        });
        Schema::create('flight_milestones', function (Blueprint $t) {
            $t->id(); $t->foreignUuid('flight_id')->constrained('operational_flights')->restrictOnDelete();
            $t->string('code', 40); $t->string('label', 150); $t->boolean('mandatory')->default(true);
            $t->string('status', 15)->default('PENDING'); $t->text('evidence')->nullable();
            $t->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete(); $t->dateTime('completed_at')->nullable();
            $t->unique(['flight_id', 'code']);
        });
        Schema::create('flight_outbox', function (Blueprint $t) {
            $t->id(); $t->foreignUuid('flight_id')->constrained('operational_flights')->restrictOnDelete();
            $t->unsignedInteger('revision'); $t->string('topic', 40); $t->json('payload');
            $t->string('status', 15)->default('PENDING'); $t->unsignedInteger('attempts')->default(0);
            $t->text('last_error')->nullable(); $t->dateTime('available_at'); $t->dateTime('delivered_at')->nullable(); $t->timestamps();
            $t->unique(['flight_id', 'revision', 'topic']);
        });
        Schema::create('flight_delivery_receipts', function (Blueprint $t) {
            $t->id(); $t->foreignId('outbox_id')->unique()->constrained('flight_outbox')->restrictOnDelete();
            $t->string('adapter', 40); $t->string('payload_hash', 64); $t->dateTime('delivered_at');
        });
    }

    public function down(): void
    {
        foreach (['flight_delivery_receipts', 'flight_outbox', 'flight_milestones', 'flight_recovery_proposals', 'flight_operation_history'] as $table) { Schema::dropIfExists($table); }
        Schema::table('flight_events', function (Blueprint $t) {
            $t->dropIndex('flight_event_stream_idx'); $t->dropConstrainedForeignId('received_by');
            $t->dropColumn(['source_time_utc', 'source_timezone', 'sequence', 'profile', 'attempts']);
        });
        Schema::table('operational_flights', function (Blueprint $t) {
            $t->dropConstrainedForeignId('closed_by');
            $t->dropColumn(['revision', 'estimated_dep_utc', 'estimated_arr_utc', 'latest_event_at_utc', 'checkin_mode', 'closed_at_utc']);
        });
    }
};
