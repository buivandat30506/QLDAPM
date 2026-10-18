<?php

namespace App\Services\Core;

use App\Models\Aircraft;
use App\Models\AirportResource;
use App\Models\FlightSchedule;
use App\Models\OperationalFlight;
use App\Models\Route;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FlightManagementService
{
    /**
     * Lấy toàn bộ lịch bay mẫu (Flight Schedules - UC-05)
     */
    public function getAllSchedules(): Collection
    {
        return app(ScheduleService::class)->query()->get();
    }

    /**
     * Tạo lịch bay mẫu (Master Schedule Template - UC-05)
     */
    public function createFlightSchedule(array $data): FlightSchedule
    {
        return app(ScheduleService::class)->save($data);
    }

    /**
     * Khởi tạo chuyến bay khai thác kèm kiểm tra xung đột tàu bay & thời gian quay đầu (UC-06, UC-07)
     *
     * @throws ValidationException
     */
    public function createOperationalFlight(array $data): OperationalFlight
    {
        return DB::transaction(function () use ($data) {
            PlanningLock::acquire();
            if (empty($data['schedule_id'])) {
                throw ValidationException::withMessages(['schedule_id' => ['Chuyến bay phải được khởi tạo từ lịch đã công bố.']]);
            }
            $schedule = FlightSchedule::lockForUpdate()->findOrFail($data['schedule_id']);
            PlanningAccess::authorize('flight.write', $schedule->route->origin_airport_code);
            $day = Carbon::parse($data['service_date'] ?? $data['flight_date'] ?? $data['scheduled_dep_utc'] ?? 'now', 'UTC');
            if ($schedule->status !== 'PUBLISHED' || ! $schedule->is_active || ! $schedule->aircraft_tail || ! $schedule->resource_id
                || ! app(ScheduleService::class)->operates($schedule, $day)) {
                throw ValidationException::withMessages(['schedule_id' => ['Lịch chưa công bố, thiếu nguồn lực hoặc nằm ngoài ngày khai thác.']]);
            }
            [$dep, $arr] = app(ScheduleService::class)->times($schedule, $day);
            $derived = ['flight_number' => $schedule->flight_number, 'route_id' => $schedule->route_id,
                'aircraft_tail' => $schedule->aircraft_tail, 'resource_id' => $schedule->resource_id,
                'scheduled_dep_utc' => $dep->format('Y-m-d H:i:s'), 'scheduled_arr_utc' => $arr->format('Y-m-d H:i:s')];
            foreach ($derived as $field => $value) {
                if (isset($data[$field]) && (string) $data[$field] !== (string) $value) {
                    throw ValidationException::withMessages([$field => ['Dữ liệu chuyến bay phải khớp lịch đã công bố.']]);
                }
            }
            $data = array_replace($data, $derived);
            // Do not eager-load model here: a consistent read before the shared gate
            // mutex would freeze a stale MySQL REPEATABLE READ snapshot.
            $aircraft = Aircraft::lockForUpdate()->findOrFail($data['aircraft_tail']);
            $resource = AirportResource::lockForUpdate()->findOrFail($schedule->resource_id);
            app(ScheduleService::class)->checkWindow($schedule->route, $aircraft, $resource, $dep, $arr, null, $schedule->id);

            // 1. Kiểm tra trạng thái khả dụng của tàu bay
            if ($aircraft->operational_status !== Aircraft::STATUS_OPERATIONAL) {
                throw ValidationException::withMessages([
                    'aircraft_tail' => ["Tàu bay {$aircraft->tail_number} không ở trạng thái khai thác (OPERATIONAL)."],
                ]);
            }

            $route = Route::findOrFail($data['route_id']);
            if ($route->status !== 'ACTIVE' || $route->originAirport->status !== 'ACTIVE' || $route->destAirport->status !== 'ACTIVE') {
                throw ValidationException::withMessages(['route_id' => ['Đường bay và hai sân bay phải đang hoạt động.']]);
            }

            $scheduledDep = Carbon::parse($data['scheduled_dep_utc'], 'UTC');
            $scheduledArr = Carbon::parse($data['scheduled_arr_utc'], 'UTC');
            if (! empty($data['flight_date']) && $data['flight_date'] !== $scheduledDep->toDateString()) {
                throw ValidationException::withMessages(['flight_date' => ['Ngày bay phải khớp ngày cất cánh UTC.']]);
            }
            if (OperationalFlight::where('flight_number', strtoupper(trim($data['flight_number'])))
                ->whereDate('flight_date', $scheduledDep->toDateString())->exists()) {
                throw ValidationException::withMessages(['flight_number' => ['Số hiệu chuyến bay đã tồn tại trong ngày này.']]);
            }
            if (! empty($data['schedule_id'])) {
                $schedule = FlightSchedule::findOrFail($data['schedule_id']);
                if ($schedule->status !== 'PUBLISHED' || ! $schedule->is_active
                    || $schedule->route_id != $route->id || $schedule->model_id != $aircraft->model_id
                    || $schedule->flight_number !== strtoupper(trim($data['flight_number']))
                    || ! app(ScheduleService::class)->operates($schedule, $day)) {
                    throw ValidationException::withMessages(['schedule_id' => ['Lịch bay phải đã công bố, còn hoạt động và khớp tuyến, dòng tàu bay, số hiệu, ngày trong tuần.']]);
                }
            }

            if ($scheduledArr->lessThanOrEqualTo($scheduledDep)) {
                throw ValidationException::withMessages([
                    'scheduled_arr_utc' => ['Thời gian hạ cánh dự kiến phải sau thời gian cất cánh dự kiến.'],
                ]);
            }

            // 2. Kiểm tra xung đột lịch bay & Thời gian quay đầu tối thiểu (BR-005, BR-006, UC-06)
            $turnaroundMinutes = $aircraft->model->turnaround_time_minutes ?? 45;
            $this->validateAircraftScheduleConflict(
                $aircraft->tail_number,
                $scheduledDep,
                $scheduledArr,
                $turnaroundMinutes
            );

            // 3. Khởi tạo chuyến bay
            $flight = OperationalFlight::create([
                'schedule_id' => $data['schedule_id'] ?? null,
                'flight_number' => strtoupper(trim($data['flight_number'])),
                'flight_date' => $data['flight_date'] ?? $scheduledDep->toDateString(),
                'service_date' => $day->toDateString(),
                'route_id' => $route->id,
                'aircraft_tail' => $aircraft->tail_number,
                'aircraft_configuration_id' => $aircraft->current_configuration_id,
                'seat_capacity_snapshot' => $aircraft->configuration?->sellable_capacity ?? $aircraft->model->seat_capacity,
                'scheduled_dep_utc' => $scheduledDep,
                'scheduled_arr_utc' => $scheduledArr,
                'flight_status' => OperationalFlight::STATUS_SCHEDULED,
                'gate_number' => $resource->resource_code,
                'resource_id' => $schedule->resource_id,
            ]);
            FlightOperations::record($flight, 'CREATE', 'Khởi tạo từ lịch công bố '.$schedule->flight_number.' phiên bản '.$schedule->version);
            return $flight;
        });
    }

    /**
     * Kiểm tra tàu bay có bị trùng lịch hoặc vi phạm thời gian quay đầu (turnaround time) hay không (BR-005, BR-006)
     *
     * @throws ValidationException
     */
    public function validateAircraftScheduleConflict(
        string $tailNumber,
        Carbon $newDep,
        Carbon $newArr,
        int $turnaroundMinutes,
        ?string $ignoreFlightId = null
    ): void {
        $bufferStart = $newDep->copy()->subMinutes($turnaroundMinutes);
        $bufferEnd = $newArr->copy()->addMinutes($turnaroundMinutes);

        $conflicts = OperationalFlight::where('aircraft_tail', $tailNumber)
            ->whereNotIn('flight_status', [OperationalFlight::STATUS_CANCELLED])
            ->when($ignoreFlightId, fn ($q) => $q->where('id', '!=', $ignoreFlightId))
            ->where('scheduled_dep_utc', '<', $bufferEnd)
            ->where('scheduled_arr_utc', '>', $bufferStart)
            ->get();

        if ($conflicts->isNotEmpty()) {
            $conflictFlight = $conflicts->first();
            throw ValidationException::withMessages([
                'aircraft_tail' => [
                    "Xung đột lịch tàu bay {$tailNumber}! Tàu bay này đã được xếp chuyến {$conflictFlight->flight_number} ".
                    "({$conflictFlight->scheduled_dep_utc->format('H:i d/m')} - {$conflictFlight->scheduled_arr_utc->format('H:i d/m')} UTC). ".
                    "Yêu cầu tối thiểu {$turnaroundMinutes} phút quay đầu mặt đất (BR-005).",
                ],
            ]);
        }
    }

    /**
     * Tìm kiếm và lọc danh sách chuyến bay khai thác (UC-07, UC-08)
     */
    public function searchFlights(array $filters = []): Collection
    {
        $query = FlightAccess::query(OperationalFlight::with([
            'route.originAirport',
            'route.destAirport',
            'aircraft.model',
            'crews',
        ]));

        if (! empty($filters['origin']) && ! empty($filters['destination'])) {
            $query->byAirports($filters['origin'], $filters['destination']);
        }

        if (! empty($filters['date'])) {
            $query->onDate($filters['date']);
        }

        if (! empty($filters['flight_status'])) {
            $query->where('flight_status', $filters['flight_status']);
        }

        if (! empty($filters['aircraft_tail'])) {
            $query->where('aircraft_tail', $filters['aircraft_tail']);
        }

        return $query->orderBy('scheduled_dep_utc')->get();
    }

    /**
     * Lấy chi tiết chuyến bay theo UUID
     */
    public function getFlightById(string $id): OperationalFlight
    {
        $flight = OperationalFlight::with([
            'route.originAirport',
            'route.destAirport',
            'aircraft.model',
            'crewAssignments.crewMember',
        ])->findOrFail($id);
        FlightAccess::authorize('read', $flight);
        return $flight;
    }

    /**
     * Cập nhật trạng thái vòng đời chuyến bay & cảnh báo chậm chuyến (UC-08, UC-09)
     */
    public function updateFlightStatus(string $flightId, array $data): OperationalFlight
    {
        return DB::transaction(function () use ($flightId, $data) {
            PlanningLock::acquire();
            $flight = OperationalFlight::lockForUpdate()->findOrFail($flightId);
            FlightAccess::authorize('write', $flight);
            FlightOperations::revision($flight, $data);
            if ($flight->closed_at_utc) { throw ValidationException::withMessages(['status' => ['Chuyến đã đóng, không sửa dữ kiện khai thác.']]); }
            $status = $data['flight_status'];
            if (in_array($status, ['DELAYED', 'CANCELLED']) && empty($data['delay_reason']) && empty($flight->delay_reason)) {
                throw ValidationException::withMessages(['delay_reason' => ['Cần lưu lý do xử lý bất thường.']]);
            }
            $allowed = [
                'SCHEDULED' => ['BOARDING', 'DELAYED', 'CANCELLED'],
                'BOARDING' => ['DEPARTED', 'DELAYED', 'CANCELLED'],
                'DELAYED' => ['SCHEDULED', 'BOARDING', 'DEPARTED', 'CANCELLED'],
                'DEPARTED' => ['ARRIVED'], 'ARRIVED' => [], 'CANCELLED' => [],
            ];
            if ($status !== $flight->flight_status && ! in_array($status, $allowed[$flight->flight_status] ?? [])) {
                throw ValidationException::withMessages(['flight_status' => ['Chuyển trạng thái chuyến bay không hợp lệ.']]);
            }
            $dep = ! empty($data['actual_dep_utc']) ? Carbon::parse($data['actual_dep_utc'], 'UTC')->utc() : $flight->actual_dep_utc;
            $arr = ! empty($data['actual_arr_utc']) ? Carbon::parse($data['actual_arr_utc'], 'UTC')->utc() : $flight->actual_arr_utc;
            if (($dep && !in_array($status, ['DEPARTED','ARRIVED'])) || ($arr && $status !== 'ARRIVED')) {
                throw ValidationException::withMessages(['actual_dep_utc' => ['Mốc thực tế phải khớp trạng thái khởi hành/hạ cánh.']]);
            }
            if (isset($data['gate_number']) && $data['gate_number'] !== $flight->gate_number) {
                throw ValidationException::withMessages(['gate_number' => ['Đổi cửa bằng phương án phục hồi có kiểm tra xung đột.']]);
            }
            if ($status === 'DEPARTED' && ! $dep) {
                throw ValidationException::withMessages(['actual_dep_utc' => ['Cần nhập giờ cất cánh thực tế.']]);
            }
            if ($status === 'ARRIVED' && (! $dep || ! $arr || $arr->lessThanOrEqualTo($dep))) {
                throw ValidationException::withMessages(['actual_arr_utc' => ['Giờ hạ cánh thực tế phải sau giờ cất cánh thực tế.']]);
            }
            if ($dep && $arr && $arr->lessThanOrEqualTo($dep)) {
                throw ValidationException::withMessages(['actual_arr_utc' => ['Giờ hạ cánh phải sau giờ cất cánh.']]);
            }
            if ($status === 'DEPARTED' && $flight->flight_status !== 'DEPARTED') {
                $aircraft = Aircraft::lockForUpdate()->findOrFail($flight->aircraft_tail);
                if ($aircraft->operational_status !== 'OPERATIONAL' || $aircraft->needsMaintenance() || app(MaintenanceReadiness::class)->issues($aircraft)) {
                    throw ValidationException::withMessages(['aircraft_tail' => ['Tàu bay không khả dụng để cất cánh.']]);
                }
                if ($flight->resource_id) {
                    $resource = AirportResource::lockForUpdate()->findOrFail($flight->resource_id);
                    app(ScheduleService::class)->checkWindow($flight->route, $aircraft, $resource,
                        $flight->scheduled_dep_utc, $flight->scheduled_arr_utc, $flight->id, $flight->schedule_id);
                }
                $compliance = app(CrewService::class)->validateMinimumFlightCrew($flightId);
                if (! $compliance['is_valid']) {
                    throw ValidationException::withMessages(['crew' => [$compliance['message']]]);
                }
                foreach ($flight->crewAssignments()->where('status', 'CONFIRMED')->get() as $assignment) {
                    app(CrewRulesService::class)->assignment($flight, $assignment->crewMember, $assignment->assigned_position);
                }
            }
            if ($flight->flight_status === 'ARRIVED'
                && (($dep && ! $dep->equalTo($flight->actual_dep_utc)) || ($arr && ! $arr->equalTo($flight->actual_arr_utc)))) {
                throw ValidationException::withMessages(['actual_arr_utc' => ['Chuyến đã hoàn tất; không sửa mốc giờ đã cộng vào thống kê đội bay.']]);
            }
            $recordUsage = $status === 'ARRIVED' && $flight->flight_status !== 'ARRIVED';
            $flight->update([
                'flight_status' => $status, 'actual_dep_utc' => $dep, 'actual_arr_utc' => $arr,
                'gate_number' => $data['gate_number'] ?? $flight->gate_number,
                'delay_reason' => $data['delay_reason'] ?? $flight->delay_reason,
            ]);

            if ($flight->wasChanged()) { FlightOperations::record($flight, 'STATUS_'.$status, $data['delay_reason'] ?? 'Cập nhật trạng thái khai thác'); }

            if ($recordUsage) {
                app(AircraftService::class)->recordFlightHours($flight->aircraft_tail, $dep->diffInSeconds($arr) / 3600, 1, $flight->id);
            }

            return $flight->fresh(['route.originAirport', 'route.destAirport', 'aircraft.model']);
        });
    }
}
