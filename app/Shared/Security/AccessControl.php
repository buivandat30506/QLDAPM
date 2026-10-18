<?php

namespace App\Shared\Security;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AccessControl
{
    private function grants(User $user, string $permission): Builder
    {
        return DB::table('user_data_scopes as scopes')
            ->join('user_roles as ur', fn ($join) => $join->on('ur.user_id', '=', 'scopes.user_id')->on('ur.role_id', '=', 'scopes.role_id'))
            ->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('scopes.user_id', $user->id)->where('p.code', $permission);
    }

    public function allows(User $user, string $permission, array $context = []): bool
    {
        return $this->scopes($user, $permission)->contains(fn ($scope) => $this->matches($scope, $context));
    }

    public function scopes(User $user, string $permission): \Illuminate\Support\Collection
    {
        if ($user->status !== 'ACTIVE') {
            return collect();
        }
        $scopes = $this->grants($user, $permission)->get(['scopes.scope_type', 'scopes.scope_id']);
        $delegations = DB::table('delegations as d')->join('permissions as p', 'p.id', '=', 'd.permission_id')
            ->where('d.grantee_id', $user->id)->where('p.code', $permission)->whereNull('d.revoked_at')
            ->where('d.starts_at', '<=', now('UTC'))->where('d.ends_at', '>', now('UTC'))->select('d.*')->get();
        foreach ($delegations as $delegation) {
            $grantor = User::find($delegation->grantor_id);
            if ($grantor?->status === 'ACTIVE'
                && $this->direct($grantor, $permission, [$delegation->scope_type => $delegation->scope_id])) {
                $scopes->push((object) ['scope_type' => $delegation->scope_type, 'scope_id' => $delegation->scope_id]);
            }
        }

        return $scopes;
    }

    public function direct(User $user, string $permission, array $context): bool
    {
        if ($user->status !== 'ACTIVE') {
            return false;
        }
        return $this->grants($user, $permission)->get(['scopes.scope_type', 'scopes.scope_id'])
            ->contains(fn ($scope) => $this->matches($scope, $context));
    }

    private function matches(object $scope, array $context): bool
    {
        return $scope->scope_type === 'global'
            || (isset($context[$scope->scope_type]) && (string) $context[$scope->scope_type] === $scope->scope_id);
    }
}
