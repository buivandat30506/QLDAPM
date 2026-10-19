<?php

namespace App\Services\Core;

use App\Models\CrewMember;
use App\Models\FlightCrewAssignment;
use App\Models\OperationalFlight;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CrewRulesService
{
    public function profile(array $data): void
    {
        Validator::make($data, [
            'crew_code' => ['required', 'string', 'max:20', Rule::unique('crew_members')->ignore($data['id'] ?? null)],
            'full_name' => 'required|string|max:100', 'email' => ['required', 'email', 'max:100', Rule::unique('crew_members')->ignore($data['id'] ?? null)],
            'license_number' => ['required', 'string', 'max:50', Rule::unique('crew_members')->ignore($data['id'] ?? null)],
            'phone' => 'required|string|max:20', 'rank' => 'required|in:CAPTAIN,FIRST_OFFICER,PURSER,FLIGHT_ATTENDANT',
            'base_airport_code' => 'required|string|size:3|exists:airports,iata_code', 'status' => 'sometimes|required|in:AVAILABLE,ON_DUTY,RESTING,LEAVE',
            'license_expires_on' => 'required|date_format:Y-m-d', 'training_expires_on' => 'required|date_format:Y-m-d',
            'qualified_model_ids' => 'required|array|min:1', 'qualified_model_ids.*' => 'integer|exists:aircraft_models,id|distinct',
            'available_from_utc' => 'nullable|date', 'available_until_utc' => 'nullable|date|after:available_from_utc',
        ])->validate();
    }

    public function assignment(OperationalFlight $flight, CrewMember $crew, string $position, ?string $excludeAssignmentId = null): array
    {
        $policy = $this->policy();
        $rules = $policy['rules'];
        if (! in_array($flight->flight_status, ['SCHEDULED', 'BOARDING', 'DELAYED']) || $crew->status !== 'AVAILABLE') {
            $this->fail('crew_id', 'Chuyến bay hoặc nhân sự không khả dụng.');
        }
        $allowed = ['CAPTAIN' => ['CAPTAIN'], 'FIRST_OFFICER' => ['CAPTAIN', 'FIRST_OFFICER'],
            'PURSER' => ['PURSER'], 'FLIGHT_ATTENDANT' => ['PURSER', 'FLIGHT_ATTENDANT']];
        if (! in_array($crew->rank, $allowed[$position] ?? [])) {
            $this->fail('assigned_position', 'Cấp bậc không phù hợp vị trí.');
        }
        $start = Carbon::parse($flight->scheduled_dep_utc, 'UTC')->subMinutes($rules['report_before_minutes']);
        $end = Carbon::parse($flight->scheduled_arr_utc, 'UTC')->addMinutes($rules['release_after_minutes']);
        if (! $crew->license_expires_on || ! $crew->training_expires_on
            || $crew->license_expires_on < $end->toDateString() || $crew->training_expires_on < $end->toDateString()
            || ! in_array((int) $flight->aircraft->model_id, $crew->qualified_model_ids ?? [], true)) {
            $this->fail('qualification', 'Thiếu/hết hạn giấy phép, đào tạo hoặc năng định không phù hợp dòng tàu bay.');
        }
        if (($crew->available_from_utc && $start->lt(Carbon::parse($crew->available_from_utc, 'UTC')))
            || ($crew->available_until_utc && $end->gt(Carbon::parse($crew->available_until_utc, 'UTC')))) {
            $this->fail('availability', 'Ca trực nằm ngoài khoảng khả dụng.');
        }
        if (DB::table('crew_availability_windows')->where('crew_id', $crew->id)->whereNull('cancelled_at')->whereIn('type', ['LEAVE', 'UNAVAILABLE'])
            ->where('starts_at_utc', '<', $end)->where('ends_at_utc', '>', $start)->exists()) {
            $this->fail('availability', 'Ca trực trùng khoảng nghỉ phép/không khả dụng.');
        }
        if ($start->diffInHours($end) > $rules['max_duty_hours']) {
            $this->fail('duty', 'Ca trực vượt giới hạn nhiệm vụ demo.');
        }
        $rest = $rules['min_rest_hours'];
        $others = FlightCrewAssignment::where('crew_id', $crew->id)->where('flight_id', '!=', $flight->id)
            ->where('status', '!=', 'REPLACED')->whereHas('flight', fn ($q) => $q->where('flight_status', '!=', 'CANCELLED'))
            ->where('duty_start_utc', '<', $end->copy()->addHours($rest))
            ->where('duty_end_utc', '>', $start->copy()->subHours($rest))->exists();
        if ($others) {
            $this->fail('rest', 'Trùng ca trực hoặc chưa đủ thời gian nghỉ giữa hai nhiệm vụ.');
        }
        if (in_array($position, ['CAPTAIN', 'FIRST_OFFICER', 'PURSER']) && FlightCrewAssignment::where('flight_id', $flight->id)
            ->when($excludeAssignmentId, fn ($q) => $q->where('id', '!=', $excludeAssignmentId))
            ->where('assigned_position', $position)->where('crew_id', '!=', $crew->id)->where('status', '!=', 'REPLACED')->exists()) {
            $this->fail('assigned_position', 'Vị trí này đã có nhân sự, hãy dùng chức năng thay thế.');
        }

        return [$start, $end];
    }

    public function policy(): array
    {
        $p = DB::table('crew_rule_versions')->where('code', 'local-crew-v1')->first();
        if (! $p) {
            $this->fail('policy', 'Chưa có phiên bản quy tắc tổ bay.');
        }

        return ['code' => $p->code, 'rules' => json_decode($p->rules, true, 512, JSON_THROW_ON_ERROR), 'provenance' => $p->provenance, 'approval_status' => $p->approval_status];
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
