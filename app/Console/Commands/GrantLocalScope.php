<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\Core\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GrantLocalScope extends Command
{
    protected $signature = 'access:scope {username} {role} {--type=global} {--id=*}';
    protected $description = 'Grant an explicit role scope on local/testing during safe upgrades';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) { $this->error('Local/testing only.'); return self::FAILURE; }
        $user = User::where('username', $this->argument('username'))->firstOrFail();
        $role = Role::where('role_code', $this->argument('role'))->firstOrFail();
        $type = $this->option('type');
        $scope = match ($type) { 'global' => '*', 'self' => $user->id, default => $this->option('id') };
        Validator::make(['type' => $type, 'scope' => $scope], ['type' => 'required|in:global,self,airport,flight', 'scope' => match ($type) { 'airport' => 'required|exists:airports,iata_code', 'flight' => 'required|uuid|exists:operational_flights,id', default => 'required|string' }])->validate();
        if (in_array($role->role_code, ['CUSTOMER', 'CREW_MEMBER']) && $type !== 'self') { $this->error('This role requires self scope.'); return self::FAILURE; }
        DB::transaction(function () use ($user, $role, $type, $scope) {
            $user->roles()->syncWithoutDetaching([$role->id]);
            DB::table('user_data_scopes')->insertOrIgnore(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => $type, 'scope_id' => $scope]);
            AuditService::record('users', $user->id, 'CLI_SCOPE_GRANTED', null, ['role' => $role->role_code, 'scope_type' => $type, 'scope_id' => $scope]);
        });
        $this->info('Đã cấp vai trò và phạm vi có lưu audit.');

        return self::SUCCESS;
    }
}
