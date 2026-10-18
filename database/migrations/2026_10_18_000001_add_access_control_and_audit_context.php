<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('uc_refs', 100);
            $table->string('action', 20);
        });
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
        Schema::create('user_data_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type', 20);
            $table->string('scope_id', 80)->default('*');
            $table->unique(['user_id', 'role_id', 'scope_type', 'scope_id'], 'unique_role_scope');
        });
        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('grantor_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('grantee_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('permission_id')->constrained()->restrictOnDelete();
            $table->string('scope_type', 20);
            $table->string('scope_id', 80);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('revoked_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamps();
            $table->index(['grantee_id', 'permission_id', 'ends_at']);
        });
        if (! Schema::hasColumn('audit_logs', 'correlation_id')) {
            Schema::table('audit_logs', fn (Blueprint $table) => $table->uuid('correlation_id')->nullable()->index());
        }
        if (! Schema::hasColumn('audit_logs', 'outcome')) {
            Schema::table('audit_logs', fn (Blueprint $table) => $table->string('outcome', 20)->default('SUCCESS'));
        }
    }

    public function down(): void
    {
        // Preserve audit context/history on rollback; forward-fix for deployed DBs.
        Schema::dropIfExists('delegations');
        Schema::dropIfExists('user_data_scopes');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
    }
};
