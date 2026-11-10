<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Shared\Access;
use App\Modules\Shared\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProvisionQaAccounts extends Command
{
    protected $signature = 'airline:qa-account {--all-roles : Assign every existing role to qa@airline.test} {--reviewers : Also provision independent reviewer accounts for two-person workflows}';

    protected $description = 'Provision opt-in local/test QA accounts without changing existing users';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing']) || ! config('airline.qa_enabled')) {
            $this->info('QA account provisioning disabled. Set QA_ACCOUNTS_ENABLED=true in a local/test environment first.');

            return self::SUCCESS;
        }
        $password = config('airline.qa_password');
        if (! is_string($password) || strlen($password) < 16) {
            $this->error('Set QA_ACCOUNT_PASSWORD in the local environment to a password of at least 16 characters.');

            return self::FAILURE;
        }
        $accounts = $this->accounts();
        try {
            $this->validateAccounts($accounts);
            DB::transaction(function () use ($accounts, $password): void {
                foreach ($accounts as $account) {
                    $user = User::where('email', $account['email'])->lockForUpdate()->first();
                    if ($user && $user->role !== $account['primary_role']) {
                        throw new \RuntimeException('Existing account '.$account['email'].' has primary role '.$user->role.'; no role was changed.');
                    }
                    $created = ! $user;
                    $user ??= new User(['email' => $account['email']]);
                    $user->forceFill([
                        'name' => $account['name'], 'email' => $account['email'],
                        'password' => Hash::make($password), 'role' => $account['primary_role'],
                        'airport_code' => $account['airport_code'], 'email_verified_at' => now(),
                        'consented_at' => now(), 'disabled_at' => null, 'must_change_password' => false,
                    ])->save();
                    DB::table('user_roles')->where('user_id', $user->id)->delete();
                    foreach ($account['roles'] as $role) {
                        DB::table('user_roles')->insert([
                            'user_id' => $user->id, 'role' => $role,
                            'airport_code' => in_array($role, Access::SCOPED, true) ? $account['airport_code'] : null,
                            'scope_all' => $account['scope_all'] && in_array($role, Access::SCOPED, true),
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                    Audit::record($created ? 'user.qa_created' : 'user.qa_reprovisioned', (string) $user->id, null, implode(',', $account['roles']), null);
                }
            }, 3);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        foreach ($accounts as $account) {
            $this->line($account['email'].' · '.implode(',', $account['roles']).($account['scope_all'] ? ' · ALL_AIRPORTS' : ($account['airport_code'] ? ' · '.$account['airport_code'] : '')));
        }
        $this->info('QA accounts provisioned. Passwords are read from QA_ACCOUNT_PASSWORD and are never printed.');

        return self::SUCCESS;
    }

    /** @return list<array{email:string,name:string,primary_role:string,roles:list<string>,airport_code:?string,scope_all:bool}> */
    private function accounts(): array
    {
        $primary = (string) config('airline.qa_role');
        $allRoles = $this->option('all-roles');
        $roles = $allRoles ? Access::ROLES : [$primary];
        $accounts = [[
            'email' => (string) config('airline.qa_email'), 'name' => (string) config('airline.qa_name'),
            'primary_role' => $primary, 'roles' => $roles,
            'airport_code' => config('airline.qa_airport'), 'scope_all' => (bool) $allRoles,
        ]];
        if ($this->option('reviewers')) {
            foreach (['data_admin', 'maintenance', 'finance', 'customer_service'] as $role) {
                $accounts[] = [
                    'email' => 'qa.'.$role.'.reviewer@airline.test',
                    'name' => 'Kiểm thử toàn hệ thống · reviewer '.$role,
                    'primary_role' => $role, 'roles' => [$role], 'airport_code' => null, 'scope_all' => false,
                ];
            }
        }

        return $accounts;
    }

    /** @param list<array{email:string,name:string,primary_role:string,roles:list<string>,airport_code:?string,scope_all:bool}> $accounts */
    private function validateAccounts(array $accounts): void
    {
        $ops = DB::connection('ops');
        foreach ($accounts as $account) {
            if (! filter_var($account['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Invalid QA email: '.$account['email']);
            }
            if (! in_array($account['primary_role'], Access::ROLES, true) || array_diff($account['roles'], Access::ROLES)) {
                throw new \InvalidArgumentException('Invalid QA role configuration.');
            }
            $scoped = array_intersect($account['roles'], Access::SCOPED);
            if ($scoped && ! $account['scope_all']) {
                if (! is_string($account['airport_code']) || ! preg_match('/^[A-Z]{3}$/', $account['airport_code'])) {
                    throw new \InvalidArgumentException('QA_ACCOUNT_AIRPORT is required for scoped roles.');
                }
                if (! $ops->table('airports')->where('code', $account['airport_code'])->exists()) {
                    throw new \InvalidArgumentException('QA airport does not exist: '.$account['airport_code']);
                }
            }
        }
    }
}
