<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (config('access.roles') as $code => $name) {
                Role::firstOrCreate(['role_code' => $code], ['role_name' => $name]);
            }
            foreach (config('access.permissions') as $code => [$uc, $action, $roles]) {
                DB::table('permissions')->updateOrInsert(['code' => $code], ['uc_refs' => $uc, 'action' => $action]);
                $permissionId = DB::table('permissions')->where('code', $code)->value('id');
                foreach ($roles as $role) {
                    DB::table('role_permissions')->insertOrIgnore(['role_id' => Role::where('role_code', $role)->value('id'), 'permission_id' => $permissionId]);
                }
            }
        });
    }
}
