<?php

namespace App\Services\Core;

use App\Models\CrewMember;
use App\Models\FlightCrewAssignment;
use App\Models\OperationalFlight;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CrewService
{
    /**
     * Danh sách nhân sự tổ bay kèm bộ lọc (UC-15)
     */
    public function query(array $filters = [])
    {
        $query = CrewAccess::profiles(CrewMember::with('baseAirport'))->orderBy('crew_code');

        if (is_scalar($filters['rank'] ?? null) && (string) $filters['rank'] !== '') {
            $query->where('rank', $filters['rank']);
        }

        if (is_scalar($filters['status'] ?? null) && (string) $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (is_string($filters['base_airport'] ?? null) && $filters['base_airport'] !== '') {
            $query->where('base_airport_code', strtoupper($filters['base_airport']));
        }

        if (is_string($filters['search'] ?? null) && $filters['search'] !== '') {
            $s = trim($filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('crew_code', 'LIKE', "%{$s}%")
                    ->orWhere('full_name', 'LIKE', "%{$s}%")
                    ->orWhere('license_number', 'LIKE', "%{$s}%");
            });
        }

        return $query;
    }

    public function getAllCrewMembers(array $filters = []): Collection
    {
        return $this->query($filters)->get();
    }

    /**
     * Chi tiết nhân sự tổ bay
     */
    public function getCrewMemberById(string $id): CrewMember
    {
        $crew = CrewMember::with(['baseAirport', 'crewAssignments.flight.route'])->findOrFail($id);
        CrewAccess::member('read', $crew);

        return $crew;
    }

    /**
     * Thêm mới hồ sơ tổ bay (UC-15)
     *
     * @throws ValidationException
     */
    public function createCrewMember(array $data): CrewMember
    {
        return DB::transaction(function () use ($data) {
            PlanningLock::acquire();
            if (auth()->check()) {
                Gate::authorize('crew.write', [['airport' => is_string($data['base_airport_code'] ?? null) ? $data['base_airport_code'] : '']]);
            }
            $data = Arr::only($data, (new CrewMember)->getFillable());
            unset($data['user_id'],$data['revision']);
            if (is_string($data['crew_code'] ?? null)) {
                $data['crew_code'] = strtoupper(trim($data['crew_code']));
            }
            app(CrewRulesService::class)->profile($data);
            $data['qualified_model_ids'] = array_map('intval', $data['qualified_model_ids']);
            $crewCode = strtoupper(trim($data['crew_code']));

            if (CrewMember::where('crew_code', $crewCode)->exists()) {
                throw ValidationException::withMessages([
                    'crew_code' => ["Mã nhân sự {$crewCode} đã tồn tại trong hệ thống (BR-001)."],
                ]);
            }

            $data['crew_code'] = $crewCode;
            $data['base_airport_code'] = strtoupper(trim($data['base_airport_code']));
            $data['status'] = $data['status'] ?? CrewMember::STATUS_AVAILABLE;

            return CrewMember::create($data);
        });
    }

    /**
     * Cập nhật hồ sơ tổ bay
     */
    public function updateCrewMember(string $id, array $data): CrewMember
    {
        return DB::transaction(function () use ($id, $data) {
            PlanningLock::acquire();
            $crew = CrewMember::lockForUpdate()->findOrFail($id);
            CrewAccess::member('write', $crew);
            Validator::make($data, ['expected_revision' => 'sometimes|required|integer|min:1'])->validate();
            abort_if(isset($data['expected_revision']) && (int) $data['expected_revision'] !== $crew->revision, 409, 'Hồ sơ đã thay đổi; tải lại.');
            if (isset($data['base_airport_code']) && auth()->check()) {
                Gate::authorize('crew.write', [['airport' => is_string($data['base_airport_code']) ? $data['base_airport_code'] : '']]);
            }
            Validator::make($data, ['total_flight_hours' => 'nullable|numeric|min:0', 'qualified_model_ids' => 'sometimes|required|array'])->validate();
            if (isset($data['total_flight_hours']) && bccomp((string) $data['total_flight_hours'], (string) $crew->total_flight_hours, 2) !== 0) {
                throw ValidationException::withMessages(['total_flight_hours' => ['Không sửa giờ bay tích lũy trong hồ sơ.']]);
            }
            $data = Arr::only($data, (new CrewMember)->getFillable());
            unset($data['user_id'],$data['revision'],$data['total_flight_hours']);
            $data = array_filter($data, fn ($value, $key) => $value !== null || in_array($key, ['available_from_utc', 'available_until_utc']), ARRAY_FILTER_USE_BOTH);
            if (isset($data['qualified_model_ids'])) {
                $data['qualified_model_ids'] = array_map('intval', $data['qualified_model_ids']);
            }
            app(CrewRulesService::class)->profile(array_replace($crew->toArray(), $data));
            if (isset($data['email']) && CrewMember::where('email', $data['email'])->where('id', '!=', $id)->exists()) {
                throw ValidationException::withMessages(['email' => ['Email đã được sử dụng.']]);
            }

            if (! empty($data['base_airport_code'])) {
                $data['base_airport_code'] = strtoupper(trim($data['base_airport_code']));
            }

            $crew->fill($data);
            $critical = $crew->isDirty(['rank', 'base_airport_code', 'status', 'license_number', 'license_expires_on', 'training_expires_on', 'qualified_model_ids', 'available_from_utc', 'available_until_utc']);
            $crew->revision++;
            $crew->save();
            if ($critical) {
                $this->invalidateAssignments($crew, 'Hồ sơ điều kiện/khả dụng đã thay đổi; cần kiểm tra lại.');
            }

            return $crew->fresh('baseAirport');
        });
    }

    /**
     * Xóa hồ sơ tổ bay
     */
    public function deleteCrewMember(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            PlanningLock::acquire();
            $crew = $this->getCrewMemberById($id);
            CrewAccess::member('write', $crew);
            if ($crew->crewAssignments()->exists() || DB::table('crew_availability_windows')->where('crew_id', $id)->exists()) {
                throw ValidationException::withMessages(['crew_id' => ['Nhân sự còn phân công chuyến bay; hãy hủy phân công trước khi xóa.']]);
            }

            return $crew->delete();
        });
    }

    /**
     * Lấy danh sách phân công tổ bay của một chuyến bay (UC-16)
     */
    public function getFlightCrewAssignments(string $flightId): Collection
    {
        CrewAccess::flight('read', OperationalFlight::findOrFail($flightId));

        return FlightCrewAssignment::with('crewMember:id,crew_code,full_name,rank,base_airport_code')
            ->where('flight_id', $flightId)
            ->get();
    }

    /**
     * Phân công nhân sự tổ bay cho chuyến bay (UC-16, UC-17)
     *
     * @throws ValidationException
     */
    public function assignCrewToFlight(string $flightId, array $data): FlightCrewAssignment
    {
        return DB::transaction(function () use ($flightId, $data) {
            PlanningLock::acquire();
            Validator::make($data, ['crew_id' => 'required|uuid|exists:crew_members,id', 'assigned_position' => 'required|in:CAPTAIN,FIRST_OFFICER,PURSER,FLIGHT_ATTENDANT', 'notes' => 'nullable|string|max:500'])->validate();
            $flight = OperationalFlight::lockForUpdate()->findOrFail($flightId);
            CrewAccess::flight('write', $flight);
            $crew = CrewMember::lockForUpdate()->findOrFail($data['crew_id']);
            CrewAccess::member('write', $crew);
            if (in_array($flight->flight_status, ['CANCELLED', 'DEPARTED', 'ARRIVED'])) {
                throw ValidationException::withMessages(['flight_id' => ['Chỉ phân công tổ bay trước khi chuyến bay cất cánh và chưa bị hủy.']]);
            }

            $assignedPos = $data['assigned_position'];
            [$dutyStart, $dutyEnd] = app(CrewRulesService::class)->assignment($flight, $crew, $assignedPos);
            $existing = FlightCrewAssignment::where('flight_id', $flightId)->where('crew_id', $crew->id)->first();
            if ($existing && $existing->status !== 'REPLACED') {
                throw ValidationException::withMessages(['crew_id' => ['Crew member already assigned; use replacement workflow.']]);
            }

            // 5. Lưu phân công tổ bay
            $assignment = FlightCrewAssignment::updateOrCreate(
                [
                    'flight_id' => $flight->id,
                    'crew_id' => $crew->id,
                ],
                [
                    'assigned_position' => $assignedPos,
                    'duty_start_utc' => $dutyStart,
                    'duty_end_utc' => $dutyEnd,
                    'status' => FlightCrewAssignment::STATUS_ASSIGNED,
                    'approved_at' => null, 'confirmed_at' => null,
                    'approved_by' => null, 'confirmed_by' => null, 'confirmation_mode' => null, 'rule_version_code' => app(CrewRulesService::class)->policy()['code'],
                    'revision' => $existing ? $existing->revision + 1 : 1,
                    'notes' => $data['notes'] ?? null,
                ]
            );
            $this->recordEvent($assignment, 'ASSIGNED');

            return $assignment;
        });
    }

    /**
     * Hủy phân công tổ bay
     */
    public function removeCrewAssignment(string $assignmentId): bool
    {
        $assignment = FlightCrewAssignment::findOrFail($assignmentId);

        return DB::transaction(function () use ($assignment) {
            PlanningLock::acquire();
            $flight = OperationalFlight::lockForUpdate()->findOrFail($assignment->flight_id);
            CrewAccess::flight('write', $flight);
            $assignment = FlightCrewAssignment::lockForUpdate()->findOrFail($assignment->id);
            if (in_array($flight->flight_status, ['DEPARTED', 'ARRIVED'])) {
                throw ValidationException::withMessages(['status' => ['Cannot remove crew after departure.']]);
            }

            $assignment->update(['status' => 'REPLACED', 'revision' => $assignment->revision + 1]);
            $this->recordEvent($assignment, 'REMOVED');

            return true;
        });
    }

    /**
     * Kiểm tra điều kiện tối thiểu tổ bay cho chuyến bay (Tối thiểu 1 Captain và 1 First Officer)
     */
    public function validateMinimumFlightCrew(string $flightId): array
    {
        $assignments = FlightCrewAssignment::where('flight_id', $flightId)->where('status', 'CONFIRMED')->get();

        $hasCaptain = $assignments->contains('assigned_position', FlightCrewAssignment::POSITION_CAPTAIN);
        $hasFirstOfficer = $assignments->contains('assigned_position', FlightCrewAssignment::POSITION_FIRST_OFFICER);
        $totalCrew = $assignments->count();

        $isValid = $hasCaptain && $hasFirstOfficer;

        return [
            'is_valid' => $isValid,
            'has_captain' => $hasCaptain,
            'has_first_officer' => $hasFirstOfficer,
            'total_crew' => $totalCrew,
            'message' => $isValid
                ? 'Tổ bay đáp ứng đầy đủ tiêu chuẩn khai thác.'
                : 'Chuyến bay chưa đủ điều kiện cất cánh: Cần tối thiểu 1 Cơ trưởng (Captain) và 1 Cơ phó (First Officer).',
        ];
    }

    public function approvePlan(string $flightId): array
    {
        return DB::transaction(function () use ($flightId) {
            PlanningLock::acquire();
            $flight = OperationalFlight::lockForUpdate()->findOrFail($flightId);
            CrewAccess::flight('approve', $flight);
            $assignments = FlightCrewAssignment::where('flight_id', $flightId)->where('status', '!=', 'REPLACED')->lockForUpdate()->get();
            if (! $assignments->contains('assigned_position', 'CAPTAIN') || ! $assignments->contains('assigned_position', 'FIRST_OFFICER')) {
                throw ValidationException::withMessages(['crew' => ['Chưa đủ Cơ trưởng và Cơ phó để phê duyệt phương án.']]);
            }
            foreach ($assignments as $assignment) {
                $crew = CrewMember::lockForUpdate()->findOrFail($assignment->crew_id);
                app(CrewRulesService::class)->assignment($flight, $crew, $assignment->assigned_position);
                if ($assignment->status === 'ASSIGNED') {
                    $assignment->update(['status' => 'APPROVED', 'approved_at' => now('UTC'), 'approved_by' => auth()->id(),
                        'rule_version_code' => app(CrewRulesService::class)->policy()['code'], 'revision' => $assignment->revision + 1]);
                    $this->recordEvent($assignment, 'APPROVED');
                    DB::table('crew_notifications')->insert(['assignment_id' => $assignment->id,
                        'message' => 'Phân công chuyến '.$flight->flight_number.' đã được phê duyệt; vui lòng xác nhận.', 'created_at' => now('UTC')]);
                }
            }
            AuditService::record('operational_flights', $flightId, 'CREW_PLAN_APPROVED', null, $assignments->toArray());

            return $assignments->fresh()->toArray();
        });
    }

    public function confirm(string $assignmentId): FlightCrewAssignment
    {
        return DB::transaction(function () use ($assignmentId) {
            PlanningLock::acquire();
            $assignment = FlightCrewAssignment::lockForUpdate()->findOrFail($assignmentId);
            $crew = CrewMember::findOrFail($assignment->crew_id);
            $flight = OperationalFlight::lockForUpdate()->findOrFail($assignment->flight_id);
            $operator = Gate::allows('crew.approve', [['airport' => $flight->route->origin_airport_code, 'flight' => $flight->id]]);
            $self = $crew->user_id && $crew->user_id === auth()->id() && Gate::allows('crew.confirm', [['self' => auth()->id()]]);
            abort_unless($operator || $self, 403, 'Chỉ thành viên được phân công hoặc điều hành có phạm vi phù hợp được xác nhận.');
            if ($assignment->status === 'CONFIRMED') {
                return $assignment;
            }
            if ($assignment->status !== 'APPROVED') {
                throw ValidationException::withMessages(['status' => ['Phân công phải được phê duyệt trước khi xác nhận.']]);
            }
            app(CrewRulesService::class)->assignment($flight, $crew, $assignment->assigned_position);
            $assignment->update(['status' => 'CONFIRMED', 'confirmed_at' => now('UTC'), 'confirmed_by' => auth()->id(),
                'confirmation_mode' => $self ? 'SELF' : 'OPERATOR', 'revision' => $assignment->revision + 1]);
            $this->recordEvent($assignment, $self ? 'MEMBER_CONFIRMED' : 'OPERATOR_RECORDED_CONFIRMATION');
            DB::table('crew_notifications')->where('assignment_id', $assignmentId)->update(['read_at' => now('UTC')]);

            return $assignment;
        });
    }

    public function replace(string $assignmentId, array $data): FlightCrewAssignment
    {
        return DB::transaction(function () use ($assignmentId, $data) {
            PlanningLock::acquire();
            $old = FlightCrewAssignment::lockForUpdate()->findOrFail($assignmentId);
            CrewAccess::flight('write', $old->flight);
            if ($old->status === 'REPLACED' || $old->crew_id === ($data['crew_id'] ?? null)) {
                throw ValidationException::withMessages(['crew_id' => ['Chọn người thay thế khác cho phân công còn hiệu lực.']]);
            }
            Validator::make($data, ['crew_id' => 'required|uuid|exists:crew_members,id', 'reason' => 'required|string|max:500'])->validate();
            $old->update(['status' => 'REPLACED', 'revision' => $old->revision + 1]);
            $this->recordEvent($old, 'REPLACED', $data['reason']);
            $new = $this->assignCrewToFlight($old->flight_id, ['crew_id' => $data['crew_id'], 'assigned_position' => $old->assigned_position, 'notes' => $data['reason']]);
            AuditService::record('flight_crew_assignments', $old->id, 'REPLACED', $old->toArray(), $new->toArray(), $data['reason']);

            return $new;
        });
    }

    public function recordEvent(FlightCrewAssignment $assignment, string $action, ?string $reason = null): void
    {
        DB::table('crew_assignment_events')->insert(['assignment_id' => $assignment->id, 'action' => $action,
            'snapshot' => json_encode($assignment->attributesToArray(), JSON_THROW_ON_ERROR), 'reason' => $reason, 'actor_id' => auth()->id(), 'created_at' => now('UTC')]);
    }

    public function invalidateAssignments(CrewMember $crew, string $reason): void
    {
        foreach ($crew->crewAssignments()->whereIn('status', ['APPROVED', 'CONFIRMED'])->whereHas('flight', fn ($q) => $q->whereIn('flight_status', ['SCHEDULED', 'BOARDING', 'DELAYED']))->get() as $a) {
            $this->recordEvent($a, 'PROFILE_INVALIDATED', $reason);
            $a->update(['status' => 'ASSIGNED', 'approved_at' => null, 'confirmed_at' => null, 'approved_by' => null, 'confirmed_by' => null, 'confirmation_mode' => null, 'revision' => $a->revision + 1]);
            DB::table('crew_notifications')->insert(['assignment_id' => $a->id, 'message' => $reason, 'created_at' => now('UTC')]);
        }
    }

    public function suggestions(string $flightId, string $position, ?string $replaceId = null): array
    {
        Validator::make(['position' => $position], ['position' => 'required|in:CAPTAIN,FIRST_OFFICER,PURSER,FLIGHT_ATTENDANT'])->validate();
        $flight = OperationalFlight::findOrFail($flightId);
        CrewAccess::flight('read', $flight);
        $eligible = [];
        $rejected = 0;
        $old = $replaceId ? FlightCrewAssignment::where('flight_id', $flightId)->where('status', '!=', 'REPLACED')->findOrFail($replaceId) : null;
        if ($old && $old->assigned_position !== $position) {
            throw ValidationException::withMessages(['position' => ['Vị trí phải khớp phân công cần thay thế.']]);
        }
        foreach (CrewAccess::profiles(CrewMember::query())->where('status', 'AVAILABLE')->orderBy('crew_code')->get() as $crew) {
            try {
                if ($old && $old->crew_id === $crew->id) {
                    continue;
                }
                if (FlightCrewAssignment::where('flight_id', $flightId)->where('crew_id', $crew->id)->where('status', '!=', 'REPLACED')->exists()) {
                    continue;
                }
                app(CrewRulesService::class)->assignment($flight, $crew, $position, $replaceId);
                $standby = DB::table('crew_availability_windows')->where('crew_id', $crew->id)->where('type', 'STANDBY')->whereNull('cancelled_at')
                    ->where('starts_at_utc', '<=', $flight->scheduled_dep_utc->copy()->subHour())->where('ends_at_utc', '>=', $flight->scheduled_arr_utc->copy()->addMinutes(30))->exists();
                $eligible[] = ['id' => $crew->id, 'crew_code' => $crew->crew_code, 'full_name' => $crew->full_name, 'rank' => $crew->rank, 'standby' => $standby];
            } catch (ValidationException) {
                $rejected++;
            }
        }
        usort($eligible, fn ($a, $b) => ($b['standby'] <=> $a['standby']) ?: strcmp($a['crew_code'],$b['crew_code']));

        return ['profile' => app(CrewRulesService::class)->policy(), 'method' => 'DETERMINISTIC_RULE_FILTER', 'eligible' => $eligible,
            'rejected_count' => $rejected, 'warning' => $eligible ? null : 'Không đủ nhân sự hợp lệ cho vị trí đã chọn; chưa tự phân công.'];
    }
}
