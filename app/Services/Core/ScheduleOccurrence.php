<?php

namespace App\Services\Core;

use App\Models\FlightSchedule;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ScheduleOccurrence
{
    public function operates(FlightSchedule $schedule, Carbon $day): bool
    {
        $date = $day->toDateString();

        return $schedule->effective_from && $schedule->effective_to
            && $date >= $schedule->effective_from && $date <= $schedule->effective_to
            && ! in_array($date, $schedule->excluded_dates ?? [], true)
            && (in_array($date, $schedule->extra_dates ?? [], true) || in_array($day->dayOfWeekIso, $schedule->days_of_week ?? []));
    }

    public function times(FlightSchedule $schedule, Carbon $day): array
    {
        if ($schedule->departure_time_local && $schedule->arrival_time_local) {
            $departure = $this->local($day->toDateString(), $schedule->departure_time_local, $schedule->source_timezone);
            $arrival = $this->local($day->copy()->addDays($schedule->arrival_day_offset)->toDateString(), $schedule->arrival_time_local, $schedule->source_timezone);
        } else {
            $departure = Carbon::parse($day->toDateString().' '.$schedule->departure_time_utc, 'UTC');
            $arrival = Carbon::parse($day->toDateString().' '.$schedule->arrival_time_utc, 'UTC');
            if ($arrival->lte($departure)) { $arrival->addDay(); }
        }
        if ($arrival->lte($departure)) {
            throw ValidationException::withMessages(['arrival_time_local' => ['Giờ đến phải sau giờ đi; chọn đúng số ngày cộng thêm.']]);
        }

        return [$departure, $arrival];
    }

    private function local(string $date, string $clock, string $zone): Carbon
    {
        $clock = strlen($clock) === 5 ? $clock.':00' : $clock;
        $wall = $date.' '.$clock;
        $naive = Carbon::parse($wall, 'UTC')->timestamp;
        $timezone = new \DateTimeZone($zone);
        $transitions = $timezone->getTransitions($naive - 172800, $naive + 172800);
        $offsets = $transitions ? array_unique(array_column($transitions, 'offset')) : [$timezone->getOffset(Carbon::createFromTimestampUTC($naive))];
        $matches = [];
        foreach ($offsets as $offset) {
            $utc = Carbon::createFromTimestampUTC($naive - $offset);
            if ($utc->copy()->setTimezone($zone)->format('Y-m-d H:i:s') === $wall) { $matches[] = $utc; }
        }
        if (count($matches) !== 1) {
            throw ValidationException::withMessages(['source_timezone' => ['Giờ địa phương '.$wall.' không tồn tại hoặc không duy nhất do DST. Nhập giờ UTC rõ ràng cho ngày này.']]);
        }

        return $matches[0];
    }
}
