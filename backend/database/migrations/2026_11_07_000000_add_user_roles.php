<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 40);
            $table->string('airport_code', 3)->nullable();
            $table->boolean('scope_all')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'role']);
            $table->index(['role', 'airport_code', 'scope_all']);
        });

        foreach (DB::table('users')->select(['id', 'role', 'airport_code'])->cursor() as $user) {
            DB::table('user_roles')->insert([
                'user_id' => $user->id,
                'role' => $user->role,
                'airport_code' => $user->airport_code,
                'scope_all' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
