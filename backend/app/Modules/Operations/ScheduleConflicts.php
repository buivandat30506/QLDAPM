<?php

namespace App\Modules\Operations;

use App\Models\User;
use App\Modules\Shared\Access;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class ScheduleConflicts
{
    public function inspect(object $flight, bool $includeDrafts = true, ?User $user = null): array
    {
        $db = DB::connection('ops');
        $issues = [];
        $plane = $db->table('aircraft')->find($flight->aircraft_id);
        $route = isset($flight->route_id) ? $db->table('routes')->find($flight->route_id) : $db->table('routes')->where('origin', $flight->origin)->where('destination', $flight->destination)->first();
        $add = function (string $reason, string $severity = 'blocking', ?object $other = null, string $category = 'condition', string $code = 'eligibility') use (&$issues, $plane, $flight, $user) {
            $canRead = ! $other || ! $user || ! in_array($user->role, Access::SCOPED, true) || $other->origin === $user->airport_code;
            $issues[] = ['category' => $category, 'code' => $code, 'reason' => $reason, 'severity' => $severity, 'aircraft' => $plane->registration ?? 'Không tìm thấy tàu bay', 'flight_id' => $flight->id ?? null, 'related_id' => $canRead ? ($other->id ?? null) : null, 'related_number' => $other ? ($canRead ? $other->number : 'Lịch ngoài phạm vi sân bay') : null, 'starts_at' => $other->departure ?? $flight->departure, 'ends_at' => $other->arrival ?? $flight->arrival];
        };
        if (! $plane || $plane->status !== 'AVAILABLE') {
            $add('Tàu bay không khả dụng.');
        }
        if (! $route || ! $route->active) {
            $add('Đường bay không còn khai thác.');
        }
        $start = Carbon::parse($flight->departure, 'UTC');
        $end = Carbon::parse($flight->arrival, 'UTC');
        if (! $end->gt($start) || ($route && $start->diffInMinutes($end) < $route->duration_minutes)) {
            $add('Khoảng thời gian hoặc thời lượng chuyến bay không hợp lệ.');
        }
        if (! $start->isFuture()) {
            $add('Chuyến đã qua thời gian khởi hành.');
        }
        $query = $db->table('flights')->where('aircraft_id', $flight->aircraft_id)->where('id', '!=', $flight->id ?? 0)->where('status', '!=', 'CANCELLED')
            ->where('departure', '<', $end->copy()->addMinutes(30))->where('arrival', '>', $start->copy()->subMinutes(30));
        if (! $includeDrafts) {
            $query->whereNotIn('status', ['DRAFT', 'PENDING']);
        }
        foreach ($query->orderBy('departure')->get() as $other) {
            $proposal = in_array($other->status, ['DRAFT', 'PENDING'], true);
            $add('Trùng lịch tàu bay hoặc khoảng quay đầu 30 phút.', $proposal ? 'warning' : 'blocking', $other, $proposal ? 'warning' : 'conflict', 'aircraft_overlap');
        }
        if ($db->table('change_reservations')->where('aircraft_id', $flight->aircraft_id)->where('starts_at', '<', $end->copy()->addMinutes(30))->where('ends_at', '>', $start->copy()->subMinutes(30))->exists()) {
            $add('Tàu bay đang được giữ cho phương án đổi tàu.', 'blocking', null, 'conflict', 'aircraft_reservation');
        }

        return $issues;
    }

    public function assertValid(object $flight): void
    {
        $issues = $this->inspect($flight, false);
        abort_if($issues !== [], 409, 'Chuyến '.$flight->number.': '.implode(' ', array_column($issues, 'reason')));
    }
}
