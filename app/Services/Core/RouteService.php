<?php

namespace App\Services\Core;

use App\Models\Route;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RouteService
{
    public static function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';
        return ['route_code' => 'nullable|string|max:20|regex:/^[A-Z0-9-]+$/',
            'origin_airport_code' => $required.'|string|size:3|exists:airports,iata_code',
            'dest_airport_code' => $required.'|string|size:3|exists:airports,iata_code',
            'distance_km' => $required.'|numeric|between:1,50000', 'standard_duration_minutes' => $required.'|integer|between:1,2880',
            'status' => 'nullable|in:ACTIVE,SUSPENDED', 'operating_from' => 'nullable|required_with:operating_to|date_format:Y-m-d',
            'operating_to' => 'nullable|required_with:operating_from|date_format:Y-m-d|after_or_equal:operating_from',
            'allowed_model_ids' => 'nullable|array|max:100', 'allowed_model_ids.*' => 'required|integer|distinct|exists:aircraft_models,id',
            'blocked_weekdays' => 'nullable|array|max:7', 'blocked_weekdays.*' => 'required|integer|between:1,7|distinct',
            'restriction_notes' => 'nullable|string|max:2000', 'expected_revision' => 'sometimes|integer|min:1'];
    }

    public function query(array $filters = [])
    {
        Validator::make($filters, ['search' => 'nullable|string|max:100', 'status' => 'nullable|in:ACTIVE,SUSPENDED',
            'origin' => 'nullable|string|size:3', 'dest' => 'nullable|string|size:3',
            'per_page' => 'nullable|integer|between:1,100', 'sort' => 'nullable|in:route_code,distance_km,updated_at',
            'direction' => 'nullable|in:asc,desc'])->validate();
        return PlanningAccess::filter(Route::query(), 'route.read', 'origin_airport_code')->with(['originAirport', 'destAirport'])
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('route_code', 'like', '%'.$s.'%'))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['origin'] ?? null, fn ($q, $s) => $q->where('origin_airport_code', strtoupper($s)))
            ->when($filters['dest'] ?? null, fn ($q, $s) => $q->where('dest_airport_code', strtoupper($s)))
            ->orderBy($filters['sort'] ?? 'route_code', $filters['direction'] ?? 'asc')->orderBy('id');
    }

    public function getAllRoutes(array $filters = []): Collection { return $this->query($filters)->get(); }

    public function getRouteById(int $id, string $action = 'read'): Route
    {
        $route = Route::findOrFail($id);
        PlanningAccess::authorize('route.'.$action, $route->origin_airport_code);
        return $route->load(['originAirport', 'destAirport']);
    }

    public function createRoute(array $data): Route { return $this->save($data); }
    public function updateRoute(int $id, array $data): Route { return $this->save($data, $id); }

    private function save(array $data, ?int $id = null): Route
    {
        return DB::transaction(function () use ($data, $id) {
            PlanningLock::acquire();
            $route = $id ? Route::lockForUpdate()->findOrFail($id) : new Route;
            if ($id) { PlanningAccess::authorize('route.write', $route->origin_airport_code); }
            $fields = array_diff(array_keys(self::rules()), ['expected_revision', 'allowed_model_ids.*', 'blocked_weekdays.*']);
            $data = array_intersect_key($data, array_flip([...$fields, 'expected_revision']));
            if (array_key_exists('status', $data) && $data['status'] === null) { unset($data['status']); }
            foreach (['origin_airport_code', 'dest_airport_code', 'route_code'] as $field) {
                if (isset($data[$field]) && is_string($data[$field])) { $data[$field] = strtoupper(trim($data[$field])); }
            }
            $values = array_replace($route->toArray(), $data);
            PlanningAccess::authorize('route.write', is_string($values['origin_airport_code'] ?? null) ? $values['origin_airport_code'] : '');
            Validator::make($values, self::rules(), [
                'origin_airport_code.exists' => 'Sân bay đi không tồn tại.', 'dest_airport_code.exists' => 'Sân bay đến không tồn tại.',
                'operating_to.after_or_equal' => 'Ngày kết thúc mùa phải từ ngày bắt đầu trở đi.',
            ])->validate();
            if ($id && isset($data['expected_revision']) && (int) $data['expected_revision'] !== $route->revision) { abort(409, 'Đường bay đã thay đổi; tải lại trước khi lưu.'); }
            if ($values['origin_airport_code'] === $values['dest_airport_code']) { $this->fail('dest_airport_code', 'Sân bay đến phải khác sân bay đi.'); }
            if ($id && ($route->origin_airport_code !== $values['origin_airport_code'] || $route->dest_airport_code !== $values['dest_airport_code'])
                && ($route->flightSchedules()->exists() || $route->operationalFlights()->exists())) { $this->fail('route', 'Không đổi hai đầu đường bay đã có lịch hoặc chuyến bay.'); }
            if (Route::where('origin_airport_code', $values['origin_airport_code'])->where('dest_airport_code', $values['dest_airport_code'])
                ->when($id, fn ($q) => $q->whereKeyNot($id))->exists()) { $this->fail('route', 'Cặp sân bay đã có đường bay.'); }
            $values['route_code'] = $values['route_code'] ?? $values['origin_airport_code'].'-'.$values['dest_airport_code'];
            Validator::make($values, ['route_code' => [Rule::unique('routes', 'route_code')->ignore($id)]])->validate();
            $before = $id ? $route->toArray() : null;
            $route->fill(array_intersect_key($values, array_flip($fields)));
            $route->revision = $id ? $route->revision + 1 : 1;
            $route->save();
            AuditService::record('routes', $route->id, $id ? 'UPDATE' : 'CREATE', $before, $route->toArray());
            return $route->fresh(['originAirport', 'destAirport']);
        });
    }

    public function deleteRoute(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            PlanningLock::acquire();
            $route = Route::lockForUpdate()->findOrFail($id);
            PlanningAccess::authorize('route.write', $route->origin_airport_code);
            if ($route->flightSchedules()->exists() || $route->operationalFlights()->exists()) { $this->fail('route', 'Đường bay đang có lịch/chuyến bay; không thể xóa.'); }
            $before = $route->toArray(); $route->delete();
            AuditService::record('routes', $id, 'DELETE', $before);
            return true;
        });
    }

    private function fail(string $field, string $message): never { throw ValidationException::withMessages([$field => [$message]]); }
}
