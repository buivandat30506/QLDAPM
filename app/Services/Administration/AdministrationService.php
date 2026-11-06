<?php

namespace App\Services\Administration;

use App\Models\User;
use App\Services\Core\AuditService;
use App\Shared\Security\AccessControl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdministrationService
{
    public function saveUser(array $data, ?string $id = null): User
    {
        Gate::authorize('users.manage');
        Validator::make($data, [
            'username' => [$id ? 'sometimes' : 'required', 'string', 'max:50', Rule::unique('users')->ignore($id)],
            'email' => [$id ? 'sometimes' : 'required', 'email', 'max:100', Rule::unique('users')->ignore($id)],
            'full_name' => 'required|string|max:100', 'status' => 'required|in:ACTIVE,INACTIVE,SUSPENDED',
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:12', 'max:128'],
            'assignments' => 'present|array|max:30', 'assignments.*.role_id' => 'required|integer|exists:roles,id',
            'assignments.*.scope_type' => 'required|in:global,self,airport,flight',
            'assignments.*.scope_id' => 'nullable|string|max:80',
        ])->validate();

        return DB::transaction(function () use ($data, $id) {
            $user = $id ? User::lockForUpdate()->findOrFail($id) : new User;
            $before = $user->exists ? $user->toArray() : null;
            $roleIds = array_column($data['assignments'], 'role_id');
            $adminRole = DB::table('roles')->where('role_code', 'ADMIN')->value('id');
            if ($id === auth()->id() && ($data['status'] !== 'ACTIVE' || ! in_array($adminRole, $roleIds))) {
                throw ValidationException::withMessages(['assignments' => ['Không thể thu hồi quyền quản trị hoặc khóa chính tài khoản đang thao tác.']]);
            }
            $user->fill(array_intersect_key($data, array_flip(['username', 'email', 'full_name', 'status'])));
            if (! empty($data['password'])) { $user->password = $data['password']; }
            $user->save();
            $user->roles()->sync($roleIds);
            DB::table('user_data_scopes')->where('user_id', $user->id)->delete();
            foreach ($data['assignments'] as $assignment) {
                $roleCode = DB::table('roles')->where('id', $assignment['role_id'])->value('role_code');
                $type = $assignment['scope_type'];
                $scope = match ($type) { 'global' => '*', 'self' => $user->id, default => $assignment['scope_id'] ?? '' };
                if (in_array($roleCode, ['CUSTOMER', 'CREW_MEMBER']) && $type !== 'self') {
                    throw ValidationException::withMessages(['assignments' => ['Hành khách và thành viên tổ bay chỉ nhận phạm vi hồ sơ của mình.']]);
                }
                if (($type === 'airport' && ! DB::table('airports')->where('iata_code', $scope)->exists())
                    || ($type === 'flight' && ! DB::table('operational_flights')->where('id', $scope)->exists())) {
                    throw ValidationException::withMessages(['assignments' => ['Phạm vi sân bay/chuyến bay không tồn tại.']]);
                }
                DB::table('user_data_scopes')->insertOrIgnore(['user_id' => $user->id, 'role_id' => $assignment['role_id'], 'scope_type' => $type, 'scope_id' => $scope]);
            }
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            AuditService::record('users', $user->id, $id ? 'ACCESS_UPDATED' : 'USER_CREATED', $before, ['user' => $user->toArray(), 'assignments' => $data['assignments']]);

            return $user->load('roles');
        });
    }

    public function delegate(array $data): int
    {
        Gate::authorize('users.manage');
        Validator::make($data, [
            'grantor_id' => 'required|uuid|exists:users,id', 'grantee_id' => 'required|uuid|exists:users,id|different:grantor_id',
            'permission_id' => 'required|integer|exists:permissions,id', 'scope_type' => 'required|in:global,airport,flight',
            'scope_id' => 'required|string|max:80', 'starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at',
            'reason' => 'required|string|max:500',
        ])->validate();

        return DB::transaction(function () use ($data) {
            $grantor = User::lockForUpdate()->findOrFail($data['grantor_id']);
            $permission = DB::table('permissions')->where('id', $data['permission_id'])->value('code');
            $scopeId = $data['scope_type'] === 'global' ? '*' : $data['scope_id'];
            if (! app(AccessControl::class)->direct($grantor, $permission, [$data['scope_type'] => $scopeId])) {
                throw ValidationException::withMessages(['permission_id' => ['Người cấp không có quyền trực tiếp trong phạm vi này. Không cho ủy quyền dây chuyền.']]);
            }
            if (! User::whereKey($data['grantee_id'])->where('status', 'ACTIVE')->exists()) {
                throw ValidationException::withMessages(['grantee_id' => ['Người nhận phải có tài khoản đang hoạt động.']]);
            }
            $id = DB::table('delegations')->insertGetId([
                ...array_intersect_key($data, array_flip(['grantor_id', 'grantee_id', 'permission_id', 'scope_type', 'reason'])),
                'scope_id' => $scopeId, 'starts_at' => \Carbon\Carbon::parse($data['starts_at'], 'UTC')->utc(),
                'ends_at' => \Carbon\Carbon::parse($data['ends_at'], 'UTC')->utc(),
                'created_by' => auth()->id(), 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
            AuditService::record('delegations', $id, 'DELEGATION_GRANTED', null, ['permission' => $permission, 'scope_type' => $data['scope_type'], 'scope_id' => $scopeId], $data['reason']);

            return $id;
        });
    }

    public function revoke(int $id): void
    {
        Gate::authorize('users.manage');
        DB::transaction(function () use ($id) {
            $delegation = DB::table('delegations')->lockForUpdate()->find($id);
            abort_unless($delegation, 404);
            if ($delegation->revoked_at === null) {
                DB::table('delegations')->where('id', $id)->update(['revoked_at' => now('UTC'), 'updated_at' => now('UTC')]);
                AuditService::record('delegations', $id, 'DELEGATION_REVOKED');
            }
        });
    }
}
