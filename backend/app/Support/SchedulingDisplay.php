<?php

namespace App\Support;

use Carbon\Carbon;

final class SchedulingDisplay
{
    public static function time(?string $utc, ?string $zone): string
    {
        return $utc ? Carbon::parse($utc, 'UTC')->setTimezone($zone ?: 'UTC')->format('H:i d/m/Y').' · '.($zone ?: 'UTC') : 'Chưa ghi nhận';
    }

    public static function stage(object $flight): string
    {
        if ($flight->published_at) {
            return 'Đã phê duyệt';
        }

        return ['DRAFT' => 'Nháp', 'PENDING' => 'Chờ phê duyệt'][$flight->status] ?? 'Lịch khai thác';
    }

    public static function publication(object $flight): string
    {
        return $flight->published_at ? 'Đã công bố' : (in_array($flight->status, ['DRAFT', 'PENDING'], true) ? 'Chưa công bố' : 'Chưa ghi nhận thời điểm công bố');
    }

    public static function local(string $date, string $time, string $zone): Carbon
    {
        $text = $date.' '.substr($time, 0, 5);
        $naive = strtotime($text.' UTC');
        $tz = new \DateTimeZone($zone);
        $matches = [];
        foreach ($tz->getTransitions($naive - 86400, $naive + 86400) ?: [] as $transition) {
            $candidate = Carbon::createFromTimestampUTC($naive - $transition['offset']);
            if ($candidate->copy()->setTimezone($zone)->format('Y-m-d H:i') === $text) {
                $matches[$candidate->timestamp] = $candidate;
            }
        }
        abort_unless(count($matches) === 1, 422, 'Giờ địa phương không tồn tại hoặc bị lặp do đổi giờ theo mùa; cần chọn thời điểm rõ ràng.');

        return array_values($matches)[0];
    }
}
