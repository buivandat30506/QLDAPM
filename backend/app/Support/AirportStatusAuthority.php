<?php

namespace App\Support;

use App\Models\User;
use App\Modules\Shared\Access;
use App\Modules\Shared\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class AirportStatusAuthority
{
    public const POSITIONS = ['airport_director' => 'Giám đốc sân bay', 'system_director' => 'Giám đốc cấp hệ thống'];

    public static function scopes(User $user): array
    {
        if ($user->disabled_at || ! in_array($user->role, array_diff(Access::ROLES, ['passenger']), true)
            || ! Schema::connection('mysql')->hasTable('airport_status_authorities')) {
            return [];
        }

        return DB::connection('mysql')->table('airport_status_authorities')->where('user_id', $user->id)->where('active', true)
            ->where(fn ($q) => $q->where(fn ($q) => $q->whereIn('position', array_keys(self::POSITIONS))->where('airport_scope', '!=', '*'))
                ->orWhere(fn ($q) => $q->where('position', 'system_director')->where('airport_scope', '*')))
            ->pluck('airport_scope')->all();
    }

    public static function canUpdate(User $user, string $airport): bool
    {
        $scopes = self::scopes($user);

        return in_array('*', $scopes, true) || in_array($airport, $scopes, true);
    }

    public static function assign(User $user, string $position, string $scope, string $reason, bool $active = true): void
    {
        Validator::make(compact('position', 'scope', 'reason'), [
            'position' => ['required', Rule::in(array_keys(self::POSITIONS))],
            'scope' => ['required', 'regex:/^(\*|[A-Z]{3})$/'], 'reason' => 'required|string|min:5|max:500',
        ])->validate();
        abort_unless(in_array($user->role, array_diff(Access::ROLES, ['passenger']), true) && ! $user->disabled_at, 422, 'Bổ nhiệm chỉ áp dụng cho tài khoản nhân viên đang hoạt động.');
        abort_if($scope === '*' && $position !== 'system_director', 422, 'Giám đốc sân bay phải có phạm vi sân bay cụ thể.');
        abort_unless($scope === '*' || DB::connection('ops')->table('airports')->where('code', $scope)->exists(), 422, 'Sân bay không tồn tại.');
        DB::connection('mysql')->transaction(function () use ($user, $position, $scope, $reason, $active) {
            $db = DB::connection('mysql');
            $db->table('users')->where('id', $user->id)->lockForUpdate()->first();
            $old = $db->table('airport_status_authorities')->where('user_id', $user->id)->where('airport_scope', $scope)->first();
            $db->table('airport_status_authorities')->updateOrInsert(['user_id' => $user->id, 'airport_scope' => $scope], ['position' => $position, 'active' => $active, 'reason' => $reason, 'updated_at' => now()] + ($old ? [] : ['created_at' => now()]));
            Audit::record('airport.status.authority', (string) $user->id, $old ? $old->position.':'.$scope.':'.(int) $old->active : null, $position.':'.$scope.':'.(int) $active, null, 'mysql');
        }, 3);
    }
}
