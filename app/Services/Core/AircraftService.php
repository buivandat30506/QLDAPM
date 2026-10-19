<?php

namespace App\Services\Core;

use App\Models\Aircraft;
use App\Models\AircraftConfiguration;
use App\Models\AircraftModel;
use App\Models\OperationalFlight;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AircraftService
{
    public static function rules(bool $update = false): array
    {
        return [
            'tail_number' => $update ? 'prohibited' : ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/', 'unique:aircrafts,tail_number'],
            'model_id' => ($update ? 'sometimes|' : '').'required|integer|exists:aircraft_models,id',
            'manufacture_year' => ($update ? 'sometimes|' : '').'required|integer|min:1970|max:'.(now('UTC')->year + 1),
            'base_airport_code' => 'nullable|string|size:3|exists:airports,iata_code',
            'operational_status' => 'sometimes|required|in:OPERATIONAL,MAINTENANCE,GROUNDED',
            'total_flight_hours' => 'nullable|numeric|min:0|max:99999999.99|decimal:0,2',
            'total_flight_cycles' => 'nullable|integer|min:0|max:100000000',
            'opening_reason' => $update ? 'prohibited' : 'nullable|string|max:500',
            'expected_revision' => 'sometimes|required|integer|min:1',
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function getAllModels(): Collection
    {
        return AircraftModel::withCount('aircrafts')->orderBy('model_code')->get();
    }

    public function createModel(array $input): AircraftModel
    {
        FleetAccess::authorize('write'); // Model catalogue changes require fleet-wide scope.
        $input['model_code'] = is_string($input['model_code'] ?? null) ? strtoupper(trim($input['model_code'])) : null;
        $data = Validator::make($input, ['model_code' => 'required|string|max:30|unique:aircraft_models,model_code',
            'manufacturer' => 'required|string|max:50', 'seat_capacity' => 'required|integer|min:1|max:1000',
            'turnaround_time_minutes' => 'required|integer|min:15|max:1440'])->validate();

        return DB::transaction(fn () => AircraftModel::create($data));
    }

    public function query(array $filters = [])
    {
        $q = FleetAccess::filter(Aircraft::with(['model', 'configuration']));
        foreach (['status' => 'operational_status', 'model_id' => 'model_id', 'base' => 'base_airport_code'] as $key => $field) {
            if (is_scalar($filters[$key] ?? null) && (string) $filters[$key] !== '') {
                $q->where($field, $filters[$key]);
            }
        }
        if (is_string($filters['search'] ?? null)) {
            $q->where('tail_number', 'like', '%'.substr(trim($filters['search']), 0, 40).'%');
        }
        if (($filters['alert'] ?? '') === '1') {
            $q->where(fn ($q) => $q->whereRaw('total_flight_hours - maintenance_base_hours >= 2700')
                ->orWhereRaw('CAST(total_flight_cycles AS SIGNED) - CAST(maintenance_base_cycles AS SIGNED) >= 1350'));
        }
        $sort = in_array($filters['sort'] ?? '', ['tail_number', 'total_flight_hours', 'total_flight_cycles', 'operational_status']) ? $filters['sort'] : 'tail_number';

        return $q->orderBy($sort, ($filters['direction'] ?? '') === 'desc' ? 'desc' : 'asc')->orderBy('tail_number');
    }

    public function getAllAircrafts(array $filters = []): Collection
    {
        return $this->query($filters)->get();
    }

    public function page(array $filters = [])
    {
        return $this->query($filters)->paginate(20)->withQueryString();
    }

    public function getAircraftByTail(string $tailNumber): Aircraft
    {
        $aircraft = Aircraft::with(['model', 'configuration'])->findOrFail(strtoupper(trim($tailNumber)));
        FleetAccess::authorize('read', $aircraft->base_airport_code);

        return $aircraft;
    }

    public function createAircraft(array $input): Aircraft
    {
        $input['tail_number'] = is_string($input['tail_number'] ?? null) ? strtoupper(trim($input['tail_number'])) : null;
        FleetAccess::authorize('write', is_string($input['base_airport_code'] ?? null) ? $input['base_airport_code'] : null);

        return DB::transaction(function () use ($input) {
            PlanningLock::acquire();
            $data = Validator::make($input, self::rules())->validate();
            unset($data['expected_revision'], $data['reason']);
            $hours = $data['total_flight_hours'] ?? 0;
            $cycles = $data['total_flight_cycles'] ?? 0;
            $data['total_flight_hours'] = $hours;
            $data['total_flight_cycles'] = $cycles;
            if (($hours > 0 || $cycles > 0) && empty(trim($data['opening_reason'] ?? ''))) {
                throw ValidationException::withMessages(['opening_reason' => ['Cần nguồn/chứng từ cho số dư sử dụng khi tiếp nhận tàu bay.']]);
            }
            $data += ['total_flight_hours' => 0, 'total_flight_cycles' => 0, 'operational_status' => 'OPERATIONAL'];
            $data['opening_flight_hours'] = $hours;
            $data['opening_flight_cycles'] = $cycles;
            $aircraft = Aircraft::create($data);
            if ($aircraft->needsMaintenance() && $aircraft->operational_status === 'OPERATIONAL') {
                $aircraft->update(['operational_status' => 'MAINTENANCE']);
            }

            return $aircraft->fresh('model');
        });
    }

    public function updateAircraft(string $tailNumber, array $input): Aircraft
    {
        return DB::transaction(function () use ($tailNumber, $input) {
            PlanningLock::acquire();
            $a = Aircraft::lockForUpdate()->findOrFail(strtoupper($tailNumber));
            FleetAccess::authorize('write', $a->base_airport_code);
            if (array_key_exists('base_airport_code', $input)) {
                FleetAccess::authorize('write', is_string($input['base_airport_code']) ? $input['base_airport_code'] : null);
            }
            $data = Validator::make($input, self::rules(true))->validate();
            $this->revision($a, $data);
            foreach (['total_flight_hours', 'total_flight_cycles'] as $field) {
                if (isset($data[$field]) && bccomp((string) $data[$field], (string) $a->$field, 2) !== 0) {
                    throw ValidationException::withMessages([$field => ['Số liệu sử dụng chỉ được cộng từ chuyến bay hoàn tất, không sửa trực tiếp.']]);
                }
                unset($data[$field]);
            }
            if (($data['operational_status'] ?? $a->operational_status) !== $a->operational_status) {
                if (empty(trim($data['reason'] ?? ''))) {
                    throw ValidationException::withMessages(['reason' => ['Cần lý do đổi trạng thái khai thác.']]);
                }
                if ($data['operational_status'] === 'OPERATIONAL') {
                    throw ValidationException::withMessages(['operational_status' => ['Cần giải phóng kỹ thuật để đưa tàu về khai thác.']]);
                }
            }
            if (isset($data['model_id']) && $data['model_id'] != $a->model_id && ($a->configurations()->exists() || $a->operationalFlights()->exists() || DB::table('flight_schedules')->where('aircraft_tail', $a->tail_number)->exists())) {
                throw ValidationException::withMessages(['model_id' => ['Không đổi dòng tàu đã có cấu hình hoặc lịch/chuyến khai thác.']]);
            }
            if ($a->base_airport_code && array_key_exists('base_airport_code', $data) && $data['base_airport_code'] !== $a->base_airport_code
                && (DB::table('maintenance_plans')->where('aircraft_tail', $a->tail_number)->where('status', 'ACTIVE')->exists()
                    || DB::table('maintenance_orders')->where('aircraft_tail', $a->tail_number)->where('status', '!=', 'RELEASED')->exists())) {
                throw ValidationException::withMessages(['base_airport_code' => ['Còn kế hoạch/lệnh bảo dưỡng mở; chưa chuyển sân bay cơ sở.']]);
            }
            $reason = $data['reason'] ?? null;
            unset($data['expected_revision'], $data['reason']);
            $before = $a->toArray();
            $a->fill($data);
            $a->revision++;
            $a->save();
            AuditService::record('aircrafts', $a->tail_number, 'PROFILE_CHANGE', $before, $a->toArray(), $reason);

            return $a->fresh(['model', 'configuration']);
        });
    }

    private function revision(Aircraft $a, array $input): void
    {
        if (isset($input['expected_revision']) && (int) $input['expected_revision'] !== $a->revision) {
            abort(409, 'Hồ sơ đã thay đổi; tải lại trước khi lưu.');
        }
    }

    public function configure(string $tail, array $input): AircraftConfiguration
    {
        return DB::transaction(function () use ($tail, $input) {
            PlanningLock::acquire();
            $a = Aircraft::lockForUpdate()->findOrFail(strtoupper($tail));
            FleetAccess::authorize('write', $a->base_airport_code);
            $data = app(SeatLayout::class)->build($input, $a->model->seat_capacity);
            $this->revision($a, $input);
            if ($a->operationalFlights()->whereNotIn('flight_status', ['CANCELLED', 'ARRIVED'])->exists()) {
                throw ValidationException::withMessages(['configuration' => ['Tàu còn chuyến chưa kết thúc. Cần xử lý kế hoạch/chỗ đã bán trước khi đổi cấu hình.']]);
            }
            $config = AircraftConfiguration::create($data + ['aircraft_tail' => $a->tail_number,
                'version' => (int) $a->configurations()->max('version') + 1, 'created_by' => Auth::id(), 'created_at' => now('UTC')]);
            $a->update(['current_configuration_id' => $config->id, 'revision' => $a->revision + 1]);
            AuditService::record('aircraft_configurations', $config->id, 'PUBLISHED', null, $config->toArray(), $data['reason']);

            return $config;
        });
    }

    public function deleteAircraft(string $tailNumber): bool
    {
        return DB::transaction(function () use ($tailNumber) {
            PlanningLock::acquire();
            $a = Aircraft::lockForUpdate()->findOrFail(strtoupper($tailNumber));
            FleetAccess::authorize('write', $a->base_airport_code);
            if ($a->operationalFlights()->exists() || $a->configurations()->exists() || DB::table('flight_schedules')->where('aircraft_tail', $a->tail_number)->exists()
                || DB::table('maintenance_orders')->where('aircraft_tail', $a->tail_number)->exists()
                || DB::table('maintenance_plans')->where('aircraft_tail', $a->tail_number)->exists()) {
                throw ValidationException::withMessages(['aircraft_tail' => ['Tàu đã có lịch sử/cấu hình/lịch bay; giữ hồ sơ và chuyển ngừng khai thác.']]);
            }

            return (bool) $a->delete();
        });
    }

    /** Received operational facts, never client supplied hour/cycle totals. */
    public function receiveUsage(string $tail, array $input): object
    {
        return DB::transaction(function () use ($tail, $input) {
            PlanningLock::acquire();
            $data = Validator::make($input, ['flight_id' => 'required|uuid|exists:operational_flights,id',
                'source' => 'required|in:OPERATIONS,AODB,FIDS,DEMO', 'external_id' => 'required|string|max:100',
                'hours' => 'prohibited', 'cycles' => 'prohibited'])->validate();
            $a = Aircraft::lockForUpdate()->findOrFail(strtoupper($tail));
            FleetAccess::authorize('write', $a->base_airport_code);

            return $this->appendUsage($a, $data['flight_id'], $data['source'], $data['external_id']);
        });
    }

    private function appendUsage(Aircraft $a, string $flightId, string $source, string $externalId): object
    {
        $flight = OperationalFlight::findOrFail($flightId);
        if ($flight->aircraft_tail !== $a->tail_number || $flight->flight_status !== 'ARRIVED' || ! $flight->actual_dep_utc || ! $flight->actual_arr_utc || $flight->actual_arr_utc->lessThanOrEqualTo($flight->actual_dep_utc)) {
            throw ValidationException::withMessages(['flight_id' => ['Chỉ nhận số liệu của chuyến đã hạ cánh có mốc giờ thực tế hợp lệ và đúng tàu bay.']]);
        }
        $receipt = DB::table('aircraft_usage_receipts')->where('source', $source)->where('external_id', $externalId)->first();
        $duplicate = $receipt ? DB::table('aircraft_usage_records')->find($receipt->usage_record_id) : null;
        if ($duplicate && ($duplicate->flight_id !== $flightId || $duplicate->aircraft_tail !== $a->tail_number)) {
            abort(409, 'Mã sự kiện đã được dùng cho chuyến/tàu khác.');
        }
        $existing = DB::table('aircraft_usage_records')->where('flight_id', $flightId)->first();
        if ($existing) {
            if (! $receipt) {
                DB::table('aircraft_usage_receipts')->insert(['source' => $source, 'external_id' => $externalId, 'usage_record_id' => $existing->id, 'received_at' => now('UTC')]);
            }
            AuditService::record('aircraft_usage_records', $existing->id, 'DUPLICATE_IGNORED', null, ['source' => $source, 'external_id' => $externalId]);
            $existing->hours = number_format((float) $existing->hours, 2, '.', '');

            return $existing;
        }
        $hours = number_format($flight->actual_dep_utc->diffInSeconds($flight->actual_arr_utc) / 3600, 2, '.', '');
        if (bccomp($hours, '0', 2) <= 0) {
            throw ValidationException::withMessages(['flight_id' => ['Thời lượng quá nhỏ để ghi nhận với độ chính xác 0,01 giờ.']]);
        }
        $id = DB::table('aircraft_usage_records')->insertGetId(['flight_id' => $flightId, 'aircraft_tail' => $a->tail_number, 'hours' => $hours, 'cycles' => 1,
            'source' => $source, 'external_id' => $externalId, 'occurred_at' => $flight->actual_arr_utc, 'received_at' => now('UTC'), 'recorded_by' => Auth::id(), 'created_at' => now('UTC')]);
        $a->total_flight_hours = bcadd($a->total_flight_hours, $hours, 2);
        $a->total_flight_cycles++;
        $a->revision++;
        if ($a->needsMaintenance() && $a->operational_status === 'OPERATIONAL') {
            $a->operational_status = 'MAINTENANCE';
        }
        $a->save();
        $hoursSince = bcsub($a->total_flight_hours, (string) $a->maintenance_base_hours, 2);
        app(MaintenanceWorkflow::class)->refreshAircraft($a);
        $cyclesSince = $a->total_flight_cycles - $a->maintenance_base_cycles;
        if (bccomp($hoursSince, '2700', 2) >= 0 || $cyclesSince >= 1350) {
            DB::table('aircraft_usage_alerts')->insert(['aircraft_tail' => $a->tail_number, 'usage_record_id' => $id,
                'level' => $a->needsMaintenance() ? 'OVERDUE' : 'DUE_SOON', 'hours_since_maintenance' => $hoursSince,
                'cycles_since_maintenance' => $cyclesSince, 'created_at' => now('UTC')]);
        }
        $record = DB::table('aircraft_usage_records')->find($id);
        $record->hours = number_format((float) $record->hours, 2, '.', '');
        DB::table('aircraft_usage_receipts')->insert(['source' => $source, 'external_id' => $externalId, 'usage_record_id' => $id, 'received_at' => now('UTC')]);
        AuditService::record('aircraft_usage_records', $id, 'RECORDED', null, $record);

        return $record;
    }

    /** Compatibility for internal completion handler; supplied duration must match persisted facts. */
    public function recordFlightHours(string $tailNumber, float $hours, int $cycles = 1, ?string $flightId = null): Aircraft
    {
        return DB::transaction(function () use ($tailNumber, $hours, $cycles, $flightId) {
            PlanningLock::acquire();
            $a = Aircraft::lockForUpdate()->findOrFail($tailNumber);
            $flight = OperationalFlight::findOrFail($flightId);
            if ($cycles !== 1 || ! $flight->actual_dep_utc || ! $flight->actual_arr_utc || number_format($hours, 2, '.', '') !== number_format($flight->actual_dep_utc->diffInSeconds($flight->actual_arr_utc) / 3600, 2, '.', '')) {
                throw ValidationException::withMessages(['usage' => ['Thời lượng sử dụng phải tính từ mốc giờ thực tế của chuyến.']]);
            }
            $this->appendUsage($a, $flightId, 'OPERATIONS', 'arrival:'.$flightId);

            return $a->fresh();
        });
    }

    public function reconciliation(Aircraft $a): array
    {
        $ledger = DB::table('aircraft_usage_records')->where('aircraft_tail', $a->tail_number);
        $hours = bcadd((string) $a->opening_flight_hours, (string) (clone $ledger)->sum('hours'), 2);
        $cycles = $a->opening_flight_cycles + (clone $ledger)->sum('cycles');

        return ['expected_hours' => $hours, 'expected_cycles' => (int) $cycles,
            'hours_difference' => bcsub($a->total_flight_hours, $hours, 2), 'cycles_difference' => $a->total_flight_cycles - $cycles,
            'matched' => bccomp($a->total_flight_hours, $hours, 2) === 0 && $a->total_flight_cycles === (int) $cycles];
    }

    public function getMaintenanceAlerts(): Collection
    {
        return $this->query(['alert' => '1'])->get();
    }
}
