<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\Core\AuditService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BootstrapAdministrator extends Command
{
    protected $signature = 'access:bootstrap {username} {email} {--existing : Grant ADMIN scope to an existing account without changing its password}';
    protected $description = 'Bootstrap an explicit local/testing system administrator; never grant domain approval rights';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) { $this->error('Bootstrap is restricted to local/testing.'); return self::FAILURE; }
        $data = ['username' => $this->argument('username'), 'email' => $this->argument('email')];
        Validator::make($data, ['username' => 'required|string|max:50', 'email' => 'required|email|max:100'])->validate();
        $password = null;
        if (! $this->option('existing')) {
            $password = getenv('BOOTSTRAP_ADMIN_PASSWORD') ?: ($this->input->isInteractive() ? $this->secret('Mật khẩu mới (ít nhất 12 ký tự)') : null);
            Validator::make(['password' => $password], ['password' => 'required|string|min:12|max:128'])->validate();
        }
        (new AccessControlSeeder)->run();
        DB::transaction(function () use ($data, $password) {
            if ($this->option('existing')) {
                $user = User::where('username', $data['username'])->where('email', $data['email'])->where('status', 'ACTIVE')->lockForUpdate()->firstOrFail();
            } else {
                $user = User::create($data + ['full_name' => $data['username'], 'password' => $password, 'status' => 'ACTIVE']);
            }
            $roleId = Role::where('role_code', 'ADMIN')->value('id');
            $user->roles()->syncWithoutDetaching([$roleId]);
            DB::table('user_data_scopes')->insertOrIgnore(['user_id' => $user->id, 'role_id' => $roleId, 'scope_type' => 'global', 'scope_id' => '*']);
            AuditService::record('users', $user->id, 'CLI_ADMIN_BOOTSTRAPPED', null, ['role' => 'ADMIN', 'scope' => 'global']);
        });
        $this->info('Đã cấp ADMIN/global. Quyền kỹ thuật và tài chính vẫn cần vai trò riêng. Không thay mật khẩu tài khoản có sẵn.');

        return self::SUCCESS;
    }
}
