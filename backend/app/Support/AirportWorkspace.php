<?php

namespace App\Support;

use App\Models\User;
use App\Modules\Shared\Access;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class AirportWorkspace
{
    public const STATUSES = [
        'NORMAL' => ['label' => 'Normal', 'tone' => 'normal', 'description' => 'Sân bay ở trạng thái khai thác bình thường.'],
        'HIGH' => ['label' => 'High', 'tone' => 'high', 'description' => 'Sân bay ở trạng thái tải cao.'],
        'OVERLOAD' => ['label' => 'Overload', 'tone' => 'overload', 'description' => 'Sân bay ở trạng thái quá tải.'],
        'MAINTENANCE' => ['label' => 'Maintenance', 'tone' => 'maintenance', 'description' => 'Sân bay ở trạng thái bảo trì.'],
    ];

    public const TYPES = InfrastructureCatalog::LABELS;

    public static function status(?string $status): array
    {
        return self::STATUSES[$status ?? ''] ?? ['label' => 'Chưa xác định', 'tone' => 'unknown', 'description' => 'Chưa có trạng thái vận hành được xác nhận.'];
    }

    public static function airports(User $user, bool $statusDirectory = false): Builder
    {
        $scopes = $statusDirectory ? AirportStatusAuthority::scopes($user) : [];
        if ($statusDirectory && in_array($user->role, Access::SCOPED, true) && $user->airport_code) {
            $scopes[] = $user->airport_code;
        }
        if ($scopes && ! Access::allows($user, 'data_admin')) {
            $query = DB::connection('ops')->table('airports');

            return in_array('*', $scopes, true) ? $query : $query->whereIn('code', $scopes);
        }
        // Use the current central permission policy, including its admin handling.
        Access::role($user, 'data_admin');
        $query = DB::connection('ops')->table('airports');
        if (! Access::isAdmin($user) && in_array($user->role, Access::SCOPED, true)) {
            $query->where('code', $user->airport_code);
        }

        return $query;
    }

    public static function airport(User $user, string $code, bool $statusDirectory = false): object
    {
        return self::airports($user, $statusDirectory)->where('code', $code)->first() ?? abort(404, 'Không tìm thấy sân bay trong phạm vi truy cập.');
    }

    public static function url(array $query = []): string
    {
        return '/workspace/catalog?'.http_build_query(array_filter($query, fn ($value) => $value !== null && $value !== ''));
    }
}
