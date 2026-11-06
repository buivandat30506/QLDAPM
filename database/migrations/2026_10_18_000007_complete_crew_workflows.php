<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_members', fn (Blueprint $t) => $t->unsignedInteger('revision')->default(1));
        Schema::create('crew_availability_windows', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('crew_id')->constrained('crew_members')->restrictOnDelete();
            $t->string('type', 20);
            $t->dateTime('starts_at_utc');
            $t->dateTime('ends_at_utc');
            $t->string('reason', 500);
            $t->foreignUuid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('created_at');
            $t->timestamp('cancelled_at')->nullable();
            $t->foreignUuid('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('cancel_reason', 500)->nullable();
            $t->index(['crew_id', 'starts_at_utc', 'ends_at_utc'], 'crew_availability_lookup');
        });
        Schema::create('crew_rule_versions', function (Blueprint $t) {
            $t->string('code', 50)->primary();
            $t->json('rules');
            $t->string('provenance', 500);
            $t->string('approval_status', 30);
            $t->timestamp('created_at');
        });
        DB::table('crew_rule_versions')->insert(['code' => 'local-crew-v1', 'rules' => json_encode(['max_duty_hours' => config('operations.max_duty_hours'),
            'min_rest_hours' => config('operations.min_rest_hours'), 'report_before_minutes' => 60, 'release_after_minutes' => 30, 'minimum_captain' => 1, 'minimum_first_officer' => 1]),
            'provenance' => 'Profile local lấy từ operations.php; giới hạn nguồn chưa có số được chuyên gia phê duyệt.', 'approval_status' => 'LOCAL_ONLY', 'created_at' => now('UTC')]);
        Schema::table('flight_crew_assignments', function (Blueprint $t) {
            $t->unsignedInteger('revision')->default(1);
            $t->foreignUuid('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignUuid('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('confirmation_mode', 20)->nullable();
            $t->string('rule_version_code', 50)->nullable();
            $t->foreign('rule_version_code')->references('code')->on('crew_rule_versions')->restrictOnDelete();
        });
        Schema::create('crew_assignment_events', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('assignment_id')->constrained('flight_crew_assignments')->restrictOnDelete();
            $t->string('action', 40);
            $t->json('snapshot');
            $t->string('reason', 500)->nullable();
            $t->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_assignment_events');
        Schema::table('flight_crew_assignments', function (Blueprint $t) {
            $t->dropForeign(['approved_by']);
            $t->dropForeign(['confirmed_by']);
            $t->dropForeign(['rule_version_code']);
            $t->dropColumn(['revision', 'approved_by', 'confirmed_by', 'confirmation_mode', 'rule_version_code']);
        });
        Schema::dropIfExists('crew_rule_versions');
        Schema::dropIfExists('crew_availability_windows');
        Schema::table('crew_members', fn (Blueprint $t) => $t->dropColumn('revision'));
    }
};
