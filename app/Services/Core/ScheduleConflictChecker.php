<?php

namespace App\Services\Core;

use App\Models\Aircraft;
use App\Models\AirportResource;
use App\Models\FlightSchedule;
use App\Models\OperationalFlight;
use App\Models\Route;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleConflictChecker
{
    public function window(Route $route, Aircraft $aircraft, AirportResource $resource, Carbon $dep, Carbon $arr, ?string $ignoreFlight = null, ?string $ignoreSchedule = null): array
    {
        $issues = [];
        $add = function ($field, $message) use (&$issues) { $issues[$field][] = $message; };
        if ($route->status !== 'ACTIVE') { $add('route_id', 'Đường bay đang tạm ngừng.'); }
        foreach ([[$route->originAirport, $dep], [$route->destAirport, $arr]] as [$airport, $time]) {
            if ($airport->status !== 'ACTIVE') { $add('route_id', 'Sân bay '.$airport->iata_code.' không hoạt động.'); }
            $hours = trim($airport->operating_hours ?? '');
            if ($hours !== '24/7') {
                if (! preg_match('/^(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})$/D', $hours, $matches)
                    || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $matches[1]) || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $matches[2])) {
                    $add('route_id', 'Không thể kiểm tra đầy đủ giờ hoạt động sân bay '.$airport->iata_code.'. Dùng 24/7 hoặc HH:mm-HH:mm.');
                } else {
                    $clock = $time->copy()->setTimezone($airport->timezone)->format('H:i');
                    $open = $matches[1] <= $matches[2] ? $clock >= $matches[1] && $clock <= $matches[2] : $clock >= $matches[1] || $clock <= $matches[2];
                    if (! $open) { $add('route_id', 'Ngoài giờ hoạt động sân bay '.$airport->iata_code.' ('.$airport->timezone.').'); }
                }
            }
        }
        $localDay = $dep->copy()->setTimezone($route->originAirport->timezone);
        if (($route->operating_from && $localDay->toDateString() < $route->operating_from)
            || ($route->operating_to && $localDay->toDateString() > $route->operating_to)) { $add('route_id', 'Ngoài mùa khai thác đường bay.'); }
        if (in_array($localDay->dayOfWeekIso, $route->blocked_weekdays ?? [])) { $add('route_id', 'Đường bay hạn chế khai thác vào ngày trong tuần này.'); }
        if ($route->allowed_model_ids && ! in_array((int) $aircraft->model_id, array_map('intval', $route->allowed_model_ids))) { $add('model_id', 'Dòng tàu bay không nằm trong danh sách được phép của đường bay.'); }
        if ($aircraft->operational_status !== 'OPERATIONAL' || $aircraft->needsMaintenance()) { $add('aircraft_tail', 'Tàu bay không khả dụng hoặc đã đến hạn bảo dưỡng.'); }
        $projected = clone $aircraft;
        $projected->total_flight_hours = bcadd((string) $aircraft->total_flight_hours, number_format(max(0, $dep->diffInSeconds($arr, false)) / 3600, 2, '.', ''), 2);
        $projected->total_flight_cycles = $aircraft->total_flight_cycles + 1;
        if ($projected->needsMaintenance()) { $add('aircraft_tail', 'Giờ bay/chu kỳ dự kiến đến cuối chuyến chạm hạn kiểm tra tổng.'); }
        foreach (app(MaintenanceReadiness::class)->issues($projected, $arr) as $issue) { $add('aircraft_tail', $issue); }
        if (DB::table('maintenance_plans')->where('aircraft_tail', $aircraft->tail_number)->where('status', 'ACTIVE')
            ->where('starts_at_utc', '<', $arr)->where('ends_at_utc', '>', $dep)->exists()) { $add('aircraft_tail', 'Chuyến bay trùng kế hoạch bảo dưỡng.'); }
        if ($resource->airport_code !== $route->origin_airport_code || $resource->resource_type !== 'GATE' || $resource->status !== 'AVAILABLE') { $add('resource_id', 'Cửa khởi hành phải thuộc sân bay đi và đang AVAILABLE.'); }
        if (! $arr->gt($dep)) { $add('scheduled_arr_utc', 'Giờ hạ cánh phải sau giờ cất cánh.'); }
        $buffer = $aircraft->model->turnaround_time_minutes;
        $gateBuffer = config('operations.gate_separation_minutes');
        $series = $ignoreSchedule ? FlightSchedule::whereKey($ignoreSchedule)->value('series_id') : null;
        $familyIds = $series ? FlightSchedule::where('series_id', $series)->pluck('id')->all() : array_filter([$ignoreSchedule]);
        $flights = OperationalFlight::where('flight_status', '!=', 'CANCELLED')
            ->when($ignoreFlight, fn ($q) => $q->whereKeyNot($ignoreFlight))
            ->when($familyIds, fn ($q) => $q->where(fn ($w) => $w->whereNull('schedule_id')->orWhereNotIn('schedule_id', $familyIds)
                ->orWhere('scheduled_dep_utc', '!=', $dep)->orWhere('scheduled_arr_utc', '!=', $arr)
                ->orWhere('aircraft_tail', '!=', $aircraft->tail_number)->orWhere('resource_id', '!=', $resource->id)->orWhereNull('resource_id')));
        foreach ((clone $flights)->where('aircraft_tail', $aircraft->tail_number)->where('scheduled_dep_utc', '<', $arr->copy()->addMinutes($buffer))
            ->where('scheduled_arr_utc', '>', $dep->copy()->subMinutes($buffer))->get() as $flight) { $add('aircraft_tail', 'Xung đột tàu bay/quay đầu với chuyến '.$flight->flight_number.'.'); }
        foreach ((clone $flights)->where('resource_id', $resource->id)->where('scheduled_dep_utc', '<', $dep->copy()->addMinutes($gateBuffer))
            ->where('scheduled_dep_utc', '>', $dep->copy()->subMinutes($gateBuffer))->get() as $flight) { $add('resource_id', 'Cửa đã được sử dụng bởi chuyến '.$flight->flight_number.'.'); }
        if (DB::table('maintenance_orders')->where('aircraft_tail', $aircraft->tail_number)->where('status', '!=', 'RELEASED')
            ->where('starts_at_utc', '<', $arr)->where('ends_at_utc', '>', $dep)->exists()) { $add('aircraft_tail', 'Chuyến bay trùng lịch bảo dưỡng.'); }
        if (DB::table('maintenance_orders')->where('aircraft_tail', $aircraft->tail_number)->where('status', '!=', 'RELEASED')
            ->where(fn ($q) => $q->where('due_at_utc', '<=', $arr)->orWhereIn('status', ['WAITING_MATERIALS', 'IN_PROGRESS', 'COMPLETED', 'APPROVED']))->exists()) { $add('aircraft_tail', 'Còn lệnh bảo dưỡng quá hạn hoặc chưa giải phóng kỹ thuật.'); }
        $others = FlightSchedule::whereIn('status', ['PENDING', 'APPROVED', 'PUBLISHED'])->where('is_active', true)
            ->when($familyIds, fn ($q) => $q->whereNotIn('id', $familyIds))
            ->where(fn ($q) => $q->where('aircraft_tail', $aircraft->tail_number)->orWhere('resource_id', $resource->id))->get();
        foreach ($others as $other) {
            // The source service date can be two days away from UTC (timezone + overnight arrival).
            $sourceStart = $dep->copy()->setTimezone($other->source_timezone ?: 'UTC')->startOfDay()->subDays(3);
            $sourceEnd = $arr->copy()->setTimezone($other->source_timezone ?: 'UTC')->startOfDay()->addDays(3);
            for ($day = $sourceStart; $day->lte($sourceEnd); $day->addDay()) {
                if (! app(ScheduleOccurrence::class)->operates($other, $day)) { continue; }
                try { [$otherDep, $otherArr] = app(ScheduleOccurrence::class)->times($other, $day); }
                catch (ValidationException) { $add('source_timezone', 'Lịch liên quan '.$other->flight_number.' có giờ nguồn chưa kiểm tra được.'); continue; }
                if ($other->aircraft_tail === $aircraft->tail_number && $otherDep->lt($arr->copy()->addMinutes($buffer)) && $otherArr->gt($dep->copy()->subMinutes($buffer))) { $add('aircraft_tail', 'Xung đột tàu bay/quay đầu với phương án '.$other->flight_number.'.'); }
                if ($other->resource_id == $resource->id && abs($otherDep->diffInMinutes($dep, false)) < $gateBuffer) { $add('resource_id', 'Cửa trùng phương án '.$other->flight_number.'.'); }
            }
        }

        return array_map(fn ($messages) => array_values(array_unique($messages)), $issues);
    }
}
