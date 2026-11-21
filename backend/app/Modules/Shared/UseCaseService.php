<?php

namespace App\Modules\Shared;

use App\Models\User;
use App\Modules\Operations\InfrastructureCompatibility;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Transactional handlers for the data and workflow objects introduced by the
 * Use Case specification.  The existing domain services remain the source of
 * truth for booking and flight state machines; this service owns the smaller
 * master-data, configuration and evidence records around them.
 */
class UseCaseService
{
    private const OPS_ROLES = ['operations', 'ops_manager'];

    public function execute(User $user, string $action, array $input): object
    {
        $method = match ($action) {
            'airport_update' => 'airportUpdate',
            'airport_area' => 'airportArea',
            'season_rule' => 'seasonRule',
            'actual_time' => 'actualTime',
            default => abort(403, 'Thao tác chưa được hỗ trợ trong phiên bản hiện tại.'),
        };

        return DB::connection('ops')->transaction(fn () => $this->{$method}($user, $input), 3);
    }

    private function validate(array $input, array $rules): array
    {
        return Validator::make($input, $rules)->validate();
    }

    private function ops(User $u): Connection
    {
        Access::role($u, ...self::OPS_ROLES);

        return DB::connection('ops');
    }

    private function audit(string $action, int|string $id, User $u, ?string $before = null, ?string $after = null, string $node = 'mysql'): void
    {
        Audit::record($action, (string) $id, $before, $after, $u->id, $node);
    }

    private function result(int|string $id, string $status = 'ACTIVE', array $extra = []): object
    {
        return (object) array_merge(['id' => $id, 'status' => $status], $extra);
    }

    private function airportUpdate(User $u, array $input): object
    {
        Access::role($u, 'data_admin');
        $d = $this->validate($input, ['code' => 'required|regex:/^[A-Z]{3}$/', 'name' => 'required|string|max:120', 'city' => 'required|string|max:100', 'timezone' => 'required|timezone', 'icao' => 'nullable|regex:/^[A-Z0-9]{4}$/', 'country_code' => 'nullable|regex:/^[A-Z]{2}$/', 'latitude' => 'nullable|numeric|between:-90,90', 'longitude' => 'nullable|numeric|between:-180,180', 'operating_hours' => 'nullable|array', 'active' => 'sometimes|boolean']);
        $db = DB::connection('ops');
        $row = $db->table('airports')->where('code', $d['code'])->lockForUpdate()->first();
        abort_unless($row, 404, 'Sân bay không tồn tại.');
        $before = json_encode($row, JSON_THROW_ON_ERROR);
        $d['active'] = $d['active'] ?? ($row->active ?? true);
        if (isset($d['operating_hours'])) {
            $d['operating_hours'] = json_encode($d['operating_hours'], JSON_THROW_ON_ERROR);
        }
        $db->table('airports')->where('code', $d['code'])->update($d);
        $this->audit('airport.updated', $d['code'], $u, $before, json_encode($d, JSON_THROW_ON_ERROR), 'ops');

        return $this->result($d['code']);
    }

    private function airportArea(User $u, array $input): object
    {
        Access::role($u, 'data_admin');
        $d = $this->validate($input, ['id' => 'nullable|integer|min:1', 'airport_code' => 'required|size:3', 'parent_id' => 'nullable|integer|min:1', 'code' => 'required|string|max:40', 'name' => 'required|string|max:160', 'type' => 'required|in:TERMINAL,ZONE,GATE,STAND,COUNTER,BELT', 'active' => 'sometimes|boolean', 'metadata' => 'nullable|array']);
        $db = DB::connection('ops');
        abort_unless($db->table('airports')->where('code', $d['airport_code'])->exists(), 422, 'Sân bay không tồn tại.');
        if (! empty($d['parent_id'])) {
            abort_unless($db->table('airport_areas')->where('id', $d['parent_id'])->where('airport_code', $d['airport_code'])->exists(), 422, 'Khu vực cha không cùng sân bay.');
        }
        $id = $d['id'] ?? null;
        $created = ! $id;
        unset($d['id']);
        $d['active'] = $d['active'] ?? true;
        if ($id) {
            $old = $db->table('airport_areas')->where('id', $id)->lockForUpdate()->first() ?? abort(404);
            app(InfrastructureCompatibility::class)->assertAreaIdentity($old, $d);
            $db->table('airport_areas')->where('id', $id)->update($d);
        } else {
            abort_if($db->table('airport_areas')->where('airport_code', $d['airport_code'])->where('code', $d['code'])->exists(), 409, 'Mã khu vực đã tồn tại.');
            $id = $db->table('airport_areas')->insertGetId($d + ['created_at' => now(), 'updated_at' => now()]);
            $old = null;
        }
        app(InfrastructureCompatibility::class)->areaWritten((int) $id, $u);
        $this->audit($created ? 'airport_area.created' : 'airport_area.updated', $id, $u, $old ? json_encode($old, JSON_THROW_ON_ERROR) : null, json_encode($d, JSON_THROW_ON_ERROR), 'ops');

        return $this->result($id);
    }

    private function seasonRule(User $u, array $input): object
    {
        $db = $this->ops($u);
        if (! empty($input['id'])) {
            $existing = $db->table('flight_seasons')->where('id', (int) $input['id'])->lockForUpdate()->first() ?? abort(404);
            $existingRoute = $db->table('routes')->find($existing->route_id) ?? abort(404);
            Access::airport($u, $existingRoute->origin);
            abort_if($existing->number || $db->table('flight_season_occurrences')->where('season_id', $existing->id)->exists(), 409, 'Quản lý kế hoạch tại Lịch bay định kỳ để kiểm tra phiên bản và bảo toàn nguồn lịch.');
        }
        $d = $this->validate($input, ['id' => 'nullable|integer|min:1', 'code' => 'required|string|max:60', 'route_id' => 'required|integer', 'aircraft_id' => 'nullable|integer', 'from_date' => 'required|date', 'to_date' => 'required|date|after_or_equal:from_date', 'weekdays' => 'required|array|min:1|max:7', 'weekdays.*' => 'integer|between:1,7', 'local_departure' => 'required|date_format:H:i', 'duration_minutes' => 'required|integer|between:15,1440', 'sale_price' => 'required|integer|min:0', 'status' => 'sometimes|in:DRAFT,APPROVED,PUBLISHED,ARCHIVED']);
        $route = $db->table('routes')->find($d['route_id']) ?? abort(422, 'Đường bay không tồn tại.');
        Access::airport($u, $route->origin);
        if (! empty($d['aircraft_id'])) {
            abort_unless($db->table('aircraft')->where('id', $d['aircraft_id'])->exists(), 422, 'Tàu bay không tồn tại.');
        }
        $id = $d['id'] ?? null;
        $created = ! $id;
        unset($d['id']);
        $d['weekdays'] = json_encode(array_values(array_unique($d['weekdays'])), JSON_THROW_ON_ERROR);
        $d['status'] = $d['status'] ?? 'DRAFT';
        if ($id) {
            $old = $db->table('flight_seasons')->where('id', $id)->lockForUpdate()->first() ?? abort(404);
            $db->table('flight_seasons')->where('id', $id)->update($d + ['version' => $old->version + 1, 'updated_at' => now()]);
        } else {
            abort_if($db->table('flight_seasons')->where('code', $d['code'])->exists(), 409, 'Mã mùa bay đã tồn tại.');
            $id = $db->table('flight_seasons')->insertGetId($d + ['created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
            $old = null;
        }
        $this->audit($created ? 'season.created' : 'season.updated', $id, $u, $old ? json_encode($old, JSON_THROW_ON_ERROR) : null, json_encode($d, JSON_THROW_ON_ERROR), 'ops');

        return $this->result($id, $d['status']);
    }

    private function actualTime(User $u, array $input): object
    {
        Access::role($u, 'operations', 'ops_manager');
        $d = $this->validate($input, ['flight_id' => 'required|integer', 'type' => 'required|in:DEPARTURE,ARRIVAL', 'event_time' => 'required|date', 'source' => 'sometimes|in:MANUAL,IOT,INTEGRATION', 'reason' => 'nullable|string|max:255']);
        $db = DB::connection('ops');
        $f = $db->table('flights')->where('id', $d['flight_id'])->lockForUpdate()->first() ?? abort(404);
        Access::airport($u, $f->origin);
        $time = date('Y-m-d H:i:s', strtotime($d['event_time']));
        if ($d['type'] === 'ARRIVAL') {
            abort_unless($f->actual_departure || $f->status === 'DEPARTED', 409, 'Cần ghi nhận giờ khởi hành trước.');
        } $column = $d['type'] === 'DEPARTURE' ? 'actual_departure' : 'actual_arrival';
        $db->table('flights')->where('id', $f->id)->update([$column => $time, 'version' => $f->version + 1]);
        $id = $db->table('flight_actual_events')->insertGetId(['flight_id' => $f->id, 'type' => $d['type'], 'event_time' => $time, 'source' => $d['source'] ?? 'MANUAL', 'reason' => $d['reason'] ?? null, 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit('flight.actual_time.updated', $f->id, $u, (string) ($f->{$column} ?? ''), $time, 'ops');

        return $this->result($id, 'RECORDED', ['flight_id' => $f->id, 'field' => $column, 'event_time' => $time]);
    }
}
