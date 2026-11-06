<?php

namespace App\Services\Core;

use App\Models\Aircraft;
use App\Models\AirportResource;
use App\Models\FlightSchedule;
use App\Models\OperationalFlight;
use App\Models\Route;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScheduleService
{
    public function query(array $filters = [])
    {
        Validator::make($filters, ['search' => 'nullable|string|max:100', 'status' => 'nullable|in:DRAFT,PENDING,APPROVED,PUBLISHED,SUPERSEDED,CANCELLED',
            'season_code' => 'nullable|string|max:30', 'per_page' => 'nullable|integer|between:1,100',
            'sort' => 'nullable|in:flight_number,effective_from,updated_at', 'direction' => 'nullable|in:asc,desc'])->validate();
        return FlightSchedule::with(['route.originAirport', 'route.destAirport', 'aircraftModel'])
            ->whereHas('route', fn ($q) => PlanningAccess::filter($q, 'schedule.read', 'origin_airport_code'))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where('flight_number', 'like', '%'.$v.'%'))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['season_code'] ?? null, fn ($q, $v) => $q->where('season_code', $v))
            ->orderBy($filters['sort'] ?? 'updated_at', $filters['direction'] ?? 'desc')->orderBy('id');
    }

    public function find(string $id, string $action = 'read'): FlightSchedule
    {
        $schedule = FlightSchedule::findOrFail($id);
        PlanningAccess::authorize('schedule.'.$action, $schedule->route->origin_airport_code);
        return $schedule->load(['route.originAirport', 'route.destAirport', 'aircraftModel']);
    }

    public function save(array $input, ?string $id = null): FlightSchedule
    {
        return DB::transaction(function () use ($input, $id) {
            PlanningLock::acquire();
            $schedule = $id ? FlightSchedule::lockForUpdate()->findOrFail($id) : new FlightSchedule;
            if ($id) {
                PlanningAccess::authorize('schedule.write', $schedule->route->origin_airport_code);
                $this->revision($schedule, $input);
                if ($schedule->status !== 'DRAFT') { $this->fail('status', 'Chỉ sửa lịch DRAFT. Tạo bản sửa đổi từ lịch đã công bố.'); }
            }
            if (isset($input['status']) && $input['status'] !== 'DRAFT') { $this->fail('status', 'Lịch mới phải là DRAFT; gửi duyệt và công bố bằng thao tác riêng.'); }
            $fields = ['flight_number', 'route_id', 'model_id', 'departure_time_utc', 'arrival_time_utc', 'days_of_week', 'is_active',
                'aircraft_tail', 'resource_id', 'effective_from', 'effective_to', 'season_code', 'excluded_dates', 'extra_dates',
                'source_timezone', 'departure_time_local', 'arrival_time_local', 'arrival_day_offset'];
            $data = array_intersect_key($input, array_flip($fields));
            foreach (['days_of_week', 'excluded_dates', 'extra_dates'] as $field) {
                if (isset($data[$field]) && is_string($data[$field])) {
                    $data[$field] = array_values(array_filter(array_map('trim', preg_split('/[,\s]+/', $data[$field])), fn ($v) => $v !== ''));
                }
            }
            $values = array_replace($schedule->toArray(), $data);
            Validator::make($values, ['route_id' => 'required|integer|exists:routes,id'])->validate();
            $route = Route::findOrFail($values['route_id']);
            PlanningAccess::authorize('schedule.write', $route->origin_airport_code);
            if ($schedule->supersedes_id && $route->origin_airport_code !== FlightSchedule::findOrFail($schedule->supersedes_id)->route->origin_airport_code) {
                $this->fail('route_id', 'Phiên bản sửa đổi phải giữ sân bay đi của chuỗi. Tạo lịch mới khi đổi sân bay đi.');
            }
            $local = ! empty($values['departure_time_local']) || ! empty($values['arrival_time_local']);
            $rules = ['flight_number' => 'required|string|max:10|regex:/^[a-zA-Z0-9-]+$/', 'route_id' => 'required|exists:routes,id',
                'model_id' => 'required|exists:aircraft_models,id', 'is_active' => 'sometimes|required|boolean',
                'days_of_week' => 'present|array|max:7', 'days_of_week.*' => 'integer|between:1,7|distinct',
                'aircraft_tail' => 'nullable|exists:aircrafts,tail_number', 'resource_id' => 'nullable|exists:airport_resources,id',
                'effective_from' => ($local ? 'required' : 'nullable').'|date_format:Y-m-d',
                'effective_to' => 'nullable|date_format:Y-m-d|after_or_equal:effective_from',
                'season_code' => 'nullable|string|max:30', 'source_timezone' => 'sometimes|required|timezone:all',
                'departure_time_utc' => ($local ? 'nullable' : 'required').'|date_format:H:i,H:i:s',
                'arrival_time_utc' => ($local ? 'nullable' : 'required').'|date_format:H:i,H:i:s',
                'departure_time_local' => ($local ? 'required' : 'nullable').'|date_format:H:i,H:i:s',
                'arrival_time_local' => ($local ? 'required' : 'nullable').'|date_format:H:i,H:i:s',
                'arrival_day_offset' => 'sometimes|required|integer|between:0,2',
                'excluded_dates' => 'nullable|array|max:366', 'excluded_dates.*' => 'required|date_format:Y-m-d|distinct',
                'extra_dates' => 'nullable|array|max:366', 'extra_dates.*' => 'required|date_format:Y-m-d|distinct'];
            Validator::make($values, $rules)->validate();
            foreach (['excluded_dates', 'extra_dates'] as $field) {
                foreach ($values[$field] ?? [] as $date) {
                    if (empty($values['effective_from']) || empty($values['effective_to']) || $date < $values['effective_from'] || $date > $values['effective_to']) { $this->fail($field, 'Ngày ngoại lệ phải nằm trong khoảng hiệu lực.'); }
                }
            }
            if (array_intersect($values['excluded_dates'] ?? [], $values['extra_dates'] ?? [])) { $this->fail('extra_dates', 'Một ngày không thể vừa thêm vừa loại khỏi lịch.'); }
            if (empty($values['days_of_week']) && empty($values['extra_dates'])) { $this->fail('days_of_week', 'Chọn ngày trong tuần hoặc ngày khai thác bổ sung.'); }
            $before = $id ? $schedule->toArray() : null;
            $schedule->fill($data);
            $schedule->source_timezone = $local ? ($values['source_timezone'] ?? 'UTC') : 'UTC';
            $schedule->arrival_day_offset = $values['arrival_day_offset'] ?? 0;
            if ($local) {
                [$dep, $arr] = $this->times($schedule, Carbon::parse($values['effective_from'], 'UTC'));
                $schedule->departure_time_utc = $dep->format('H:i:s');
                $schedule->arrival_time_utc = $arr->format('H:i:s');
            }
            $schedule->flight_number = strtoupper(trim($values['flight_number']));
            $schedule->status = 'DRAFT'; $schedule->validation_notes = null;
            $schedule->is_active = $values['is_active'] ?? true;
            if (! $id) {
                $schedule->id = (string) Str::uuid(); $schedule->series_id = $schedule->id;
                $schedule->created_by = Auth::id(); $schedule->revision = 1;
            } else { $schedule->revision++; }
            $schedule->series_id ??= $schedule->id;
            $schedule->open_series_id = $schedule->series_id;
            $schedule->save();
            AuditService::record('flight_schedules', $schedule->id, $id ? 'UPDATE_DRAFT' : 'CREATE_DRAFT', $before, $schedule->toArray());
            return $schedule->fresh();
        });
    }

    public function transition(string $id, string $action, array $data = []): FlightSchedule
    {
        $result = DB::transaction(function () use ($id, $action, $data) {
            PlanningLock::acquire();
            $schedule = FlightSchedule::lockForUpdate()->findOrFail($id);
            PlanningAccess::authorize('schedule.'.(in_array($action, ['approve', 'publish', 'reject', 'cancel']) ? 'approve' : 'write'), $schedule->route->origin_airport_code);
            $this->revision($schedule, $data);
            $before = $schedule->toArray();
            $schedule->series_id ??= $schedule->id;
            if (in_array($action, ['revise', 'reject', 'cancel'])) { Validator::make($data, ['reason' => 'required|string|min:5|max:1000'])->validate(); }
            if ($action === 'revise') {
                if ($schedule->status !== 'PUBLISHED' || ! $schedule->is_active) { $this->fail('status', 'Chỉ tạo phiên bản từ lịch đang công bố.'); }
                if (FlightSchedule::where('open_series_id', $schedule->series_id)->exists()) { $this->fail('status', 'Đã có một bản sửa đổi đang chờ xử lý.'); }
                $draft = $schedule->replicate(['id', 'version', 'revision', 'created_by', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'published_by', 'published_at']);
                $draft->id = (string) Str::uuid(); $draft->series_id = $schedule->series_id; $draft->open_series_id = $schedule->series_id;
                $draft->supersedes_id = $schedule->id; $draft->status = 'DRAFT'; $draft->version = 0; $draft->revision = 1;
                $draft->created_by = Auth::id(); $draft->change_reason = $data['reason']; $draft->validation_notes = null;
                $draft->save();
                AuditService::record('flight_schedules', $draft->id, 'REVISE', $before, $draft->toArray(), $data['reason']);
                return ['schedule' => $draft];
            }
            if ($action === 'reject') {
                if (! in_array($schedule->status, ['PENDING', 'APPROVED'])) { $this->fail('status', 'Chỉ trả về sửa lịch đang chờ duyệt hoặc đã duyệt.'); }
                $schedule->status = 'DRAFT'; $schedule->submitted_at = null; $schedule->submitted_by = null;
                $schedule->approved_at = null; $schedule->approved_by = null;
            } elseif ($action === 'cancel') {
                if (! in_array($schedule->status, ['DRAFT', 'PENDING', 'APPROVED', 'PUBLISHED'])) { $this->fail('status', 'Không thể hủy lịch ở trạng thái này.'); }
                if ($schedule->status === 'PUBLISHED' && FlightSchedule::where('open_series_id', $schedule->series_id)->exists()) { $this->fail('status', 'Hủy bản sửa đổi đang mở trước khi hủy lịch công bố.'); }
                $schedule->status = 'CANCELLED'; $schedule->is_active = false; $schedule->open_series_id = null;
            } else {
                $steps = ['submit' => ['DRAFT', 'PENDING'], 'approve' => ['PENDING', 'APPROVED'], 'publish' => ['APPROVED', 'PUBLISHED']];
                if (! isset($steps[$action]) || $schedule->status !== $steps[$action][0]) { $this->fail('status', 'Thứ tự bắt buộc: DRAFT → PENDING → APPROVED → PUBLISHED.'); }
                $report = $this->report($schedule);
                $this->storeCheck($schedule, $report);
                if (! $report['is_valid']) {
                    AuditService::record('flight_schedules', $id, 'VALIDATION_DENIED', null, $report, outcome: 'DENIED');
                    return ['errors' => $report['errors']];
                }
                $schedule->status = $steps[$action][1];
                $step = ['submit' => 'submitted', 'approve' => 'approved', 'publish' => 'published'][$action];
                $schedule->{$step.'_by'} = Auth::id(); $schedule->{$step.'_at'} = now('UTC');
                if ($action === 'publish') {
                    $old = $schedule->supersedes_id ? FlightSchedule::lockForUpdate()->findOrFail($schedule->supersedes_id) : null;
                    if ($old && ($old->status !== 'PUBLISHED' || ! $old->is_active)) { $this->fail('status', 'Phiên bản gốc không còn hiệu lực.'); }
                    $schedule->version = ($old?->version ?? $schedule->version) + 1;
                    $schedule->open_series_id = null;
                    if ($old) {
                        $oldBefore = $old->toArray(); $old->status = 'SUPERSEDED'; $old->is_active = false; $old->revision++; $old->save();
                        AuditService::record('flight_schedules', $old->id, 'SUPERSEDE', $oldBefore, $old->toArray(), $schedule->change_reason);
                    }
                }
            }
            $schedule->revision++; $schedule->save();
            if ($action === 'publish') { AuditService::version('flight_schedules', $id, $schedule->version, $schedule->toArray() + ['impacts' => $report['impacts']]); }
            AuditService::record('flight_schedules', $id, strtoupper($action), $before, $schedule->toArray() + ['impacts' => $this->impacts($schedule)], $data['reason'] ?? $schedule->change_reason);
            return ['schedule' => $schedule->fresh()];
        });
        // Persist diagnostics even when the transition is refused.
        if (isset($result['errors'])) { throw ValidationException::withMessages($result['errors']); }
        return $result['schedule'];
    }

    public function inspect(string $id): array
    {
        return DB::transaction(function () use ($id) {
            PlanningLock::acquire();
            $schedule = FlightSchedule::lockForUpdate()->findOrFail($id);
            PlanningAccess::authorize('schedule.write', $schedule->route->origin_airport_code);
            $report = $this->report($schedule); $this->storeCheck($schedule, $report);
            AuditService::record('flight_schedules', $id, 'CHECK_CONFLICTS', null, $report);
            return $report;
        });
    }

    public function validate(FlightSchedule $schedule): void
    {
        $report = $this->report($schedule);
        if (! $report['is_valid']) { throw ValidationException::withMessages($report['errors']); }
    }

    private function report(FlightSchedule $schedule): array
    {
        $errors = []; $issues = []; $count = 0; $windows = [];
        $add = function ($field, $message, $date = null) use (&$errors, &$issues) {
            $errors[$field][] = $message; $issues[] = ['field' => $field, 'date' => $date, 'message' => $message];
        };
        if (! $schedule->aircraft_tail || ! $schedule->resource_id || ! $schedule->effective_from || ! $schedule->effective_to) {
            $add('schedule', 'Thiếu tàu bay, cửa hoặc khoảng hiệu lực; không thể kiểm tra đầy đủ.');
        } else {
            $start = Carbon::parse($schedule->effective_from, 'UTC')->startOfDay(); $end = Carbon::parse($schedule->effective_to, 'UTC')->startOfDay();
            if ($start->gt($end) || $start->diffInDays($end) >= config('operations.schedule_horizon_days')) { $add('effective_to', 'Khoảng hiệu lực phải hợp lệ và tối đa '.config('operations.schedule_horizon_days').' ngày.'); }
            else {
                $aircraft = Aircraft::lockForUpdate()->findOrFail($schedule->aircraft_tail);
                $resource = AirportResource::lockForUpdate()->findOrFail($schedule->resource_id);
                if ((int) $aircraft->model_id !== (int) $schedule->model_id) { $add('model_id', 'Dòng tàu bay không khớp tàu được phân công.'); }
                if (! $schedule->is_active) { $add('is_active', 'Lịch tạm ngừng không thể gửi duyệt/công bố.'); }
                for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                    if (! $this->operates($schedule, $day)) { continue; }
                    $count++;
                    try {
                        [$dep, $arr] = $this->times($schedule, $day); $windows[] = [$dep, $arr];
                        foreach (app(ScheduleConflictChecker::class)->window($schedule->route, $aircraft, $resource, $dep, $arr, null, $schedule->id) as $field => $messages) {
                            foreach ($messages as $message) { $add($field, $message, $day->toDateString()); }
                        }
                    } catch (ValidationException $e) {
                        foreach ($e->errors() as $field => $messages) { foreach ($messages as $message) { $add($field, $message, $day->toDateString()); } }
                    }
                }
                for ($i = 1; $i < count($windows); $i++) {
                    if ($windows[$i][0]->lt($windows[$i - 1][1]->copy()->addMinutes($aircraft->model->turnaround_time_minutes))) { $add('aircraft_tail', 'Các lần lặp của chính lịch này xung đột tàu bay/quay đầu.'); }
                }
                if (! $count) { $add('days_of_week', 'Không có ngày khai thác trong khoảng hiệu lực.'); }
            }
        }
        $impacts = $this->impacts($schedule);
        if ($schedule->supersedes_id && collect($impacts)->contains('requires_resolution', true)) { $add('impacts', 'Còn chuyến khai thác bị ảnh hưởng. Xử lý qua điều hành trước khi công bố; không tự đổi chuyến đã tạo.'); }
        $series = $schedule->series_id ?: $schedule->id;
        $duplicates = FlightSchedule::where('flight_number', $schedule->flight_number)->where('id', '!=', $schedule->id)
            ->where(fn ($q) => $q->whereNull('series_id')->orWhere('series_id', '!=', $series))
            ->whereIn('status', ['PENDING', 'APPROVED', 'PUBLISHED'])->where('is_active', true)->get();
        foreach ($duplicates as $other) {
            foreach ($windows as [$dep]) {
                for ($day = $dep->copy()->startOfDay()->subDays(2); $day->lte($dep->copy()->startOfDay()->addDays(2)); $day->addDay()) {
                    if (! $this->operates($other, $day)) { continue; }
                    try { [$otherDep] = $this->times($other, $day); }
                    catch (ValidationException) { $add('source_timezone', 'Không kiểm tra được giờ nguồn của lịch cùng số hiệu.'); continue; }
                    if ($otherDep->toDateString() === $dep->toDateString()) { $add('flight_number', 'Số hiệu đã có phương án cùng ngày UTC.'); break 2; }
                }
            }
        }
        return ['schedule_id' => $schedule->id, 'revision' => $schedule->revision, 'is_valid' => ! $errors,
            'occurrence_count' => $count, 'errors' => array_map(fn ($m) => array_values(array_unique($m)), $errors), 'issues' => $issues, 'impacts' => $impacts];
    }

    public function impacts(FlightSchedule $schedule): array
    {
        $series = $schedule->series_id ?: $schedule->id;
        $ids = FlightSchedule::where('series_id', $series)->pluck('id')->push($schedule->id);
        return OperationalFlight::whereIn('schedule_id', $ids)->whereNotIn('flight_status', ['CANCELLED', 'ARRIVED'])
            ->orderBy('scheduled_dep_utc')->get()->map(function ($flight) use ($schedule) {
                $day = $flight->scheduled_dep_utc->copy()->setTimezone($schedule->source_timezone ?: 'UTC');
                $same = false;
                if ($this->operates($schedule, $day)) {
                    try {
                        [$dep, $arr] = $this->times($schedule, $day);
                        $same = $flight->scheduled_dep_utc->equalTo($dep) && $flight->scheduled_arr_utc->equalTo($arr)
                            && $flight->route_id == $schedule->route_id && $flight->aircraft_tail === $schedule->aircraft_tail
                            && $flight->resource_id == $schedule->resource_id && $flight->flight_number === $schedule->flight_number;
                    } catch (ValidationException) {}
                }
                return ['flight_id' => $flight->id, 'flight_number' => $flight->flight_number,
                    'scheduled_dep_utc' => $flight->scheduled_dep_utc->toIso8601String(), 'requires_resolution' => ! $same];
            })->all();
    }

    private function storeCheck(FlightSchedule $schedule, array $report): void
    {
        DB::table('flight_schedule_checks')->insert(['schedule_id' => $schedule->id, 'revision' => $schedule->revision,
            'checked_by' => Auth::id(), 'is_valid' => $report['is_valid'], 'occurrence_count' => $report['occurrence_count'],
            'issues' => json_encode($report['issues'], JSON_THROW_ON_ERROR), 'impacts' => json_encode($report['impacts'], JSON_THROW_ON_ERROR), 'created_at' => now('UTC')]);
    }

    public function operates(FlightSchedule $schedule, Carbon $day): bool { return app(ScheduleOccurrence::class)->operates($schedule, $day); }
    public function times(FlightSchedule $schedule, Carbon $day): array { return app(ScheduleOccurrence::class)->times($schedule, $day); }

    public function checkWindow(Route $route, Aircraft $aircraft, AirportResource $resource, Carbon $dep, Carbon $arr, ?string $ignoreFlight = null, ?string $ignoreSchedule = null): void
    {
        $errors = app(ScheduleConflictChecker::class)->window($route, $aircraft, $resource, $dep, $arr, $ignoreFlight, $ignoreSchedule);
        if ($errors) { throw ValidationException::withMessages($errors); }
    }

    private function revision(FlightSchedule $schedule, array $data): void
    {
        Validator::make($data, ['expected_revision' => 'sometimes|required|integer|min:1'])->validate();
        if (isset($data['expected_revision']) && (string) $data['expected_revision'] !== (string) $schedule->revision) { abort(409, 'Lịch đã thay đổi. Tải lại trước khi thao tác.'); }
    }

    private function fail(string $field, string $message): never { throw ValidationException::withMessages([$field => [$message]]); }
}
