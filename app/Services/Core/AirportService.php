<?php

namespace App\Services\Core;

use App\Models\Airport;
use App\Models\AirportResource;
use App\Shared\Security\AccessControl;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AirportService
{
    /**
     * Lấy danh sách sân bay kèm bộ lọc (UC-01)
     */
    public function getAllAirports(array $filters = []): Collection
    {
        $query = $this->query($filters);

        return $query->get();
    }

    public function page(array $filters = [])
    {
        return $this->query($filters)->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();
    }

    private function authorize(string $action, string $iata): void
    {
        if (auth()->check()) {
            Gate::authorize('airport.'.$action, [['airport' => strtoupper($iata)]]);
        }
    }

    private function query(array $filters)
    {
        Validator::make($filters, ['search' => 'nullable|string|max:100', 'status' => 'nullable|in:ACTIVE,INACTIVE,CLOSED',
            'per_page' => 'nullable|integer|between:1,100', 'sort' => 'nullable|in:iata_code,name,city,status', 'direction' => 'nullable|in:asc,desc'])->validate();
        $query = Airport::withCount('resources')->orderBy($filters['sort'] ?? 'iata_code', $filters['direction'] ?? 'asc');
        if (auth()->check()) {
            $scopes = app(AccessControl::class)->scopes(auth()->user(), 'airport.read');
            if (! $scopes->contains('scope_type', 'global')) {
                $query->whereIn('iata_code', $scopes->where('scope_type', 'airport')->pluck('scope_id'));
            }
        }

        if (! empty($filters['search'])) {
            $s = trim($filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('iata_code', 'LIKE', "%{$s}%")
                    ->orWhere('name', 'LIKE', "%{$s}%")
                    ->orWhere('city', 'LIKE', "%{$s}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    /**
     * Lấy chi tiết sân bay theo IATA code
     */
    public function getAirportByIata(string $iataCode, bool $withResources = true): Airport
    {
        $this->authorize('read', $iataCode);

        return Airport::when($withResources, fn ($q) => $q->with('resources'))->findOrFail(strtoupper($iataCode));
    }

    /**
     * Tạo mới sân bay với kiểm tra ràng buộc duy nhất BR-001
     *
     * @throws ValidationException
     */
    public function createAirport(array $data): Airport
    {
        if (auth()->check()) {
            Gate::authorize('airport.write');
        }
        $data = AirportData::validate($data);
        $iata = strtoupper(trim($data['iata_code']));
        Validator::make(['iata_code' => $iata], [
            'iata_code' => ['required', 'regex:/^[A-Z]{3}$/'],
        ])->validate();

        if (Airport::where('iata_code', $iata)->exists()) {
            throw ValidationException::withMessages([
                'iata_code' => ["Mã sân bay {$iata} đã tồn tại trong hệ thống (BR-001)."],
            ]);
        }

        $data['iata_code'] = $iata;
        if (! empty($data['icao_code'])) {
            $data['icao_code'] = strtoupper(trim($data['icao_code']));
        }
        $data['country'] = $data['country'] ?? 'Vietnam';
        $data['timezone'] = $data['timezone'] ?? 'Asia/Ho_Chi_Minh';
        $data['operating_hours'] = $data['operating_hours'] ?? '24/7';
        $data['status'] = $data['status'] ?? 'ACTIVE';

        return DB::transaction(fn () => Airport::create($data));
    }

    /**
     * Cập nhật thông tin sân bay
     */
    public function updateAirport(string $iataCode, array $data): Airport
    {
        $this->authorize('write', $iataCode);
        $data = AirportData::validate($data, strtoupper($iataCode));
        if (isset($data['iata_code']) && $data['iata_code'] !== strtoupper($iataCode)) {
            throw ValidationException::withMessages(['iata_code' => ['Không đổi mã IATA của sân bay đã tạo.']]);
        }
        unset($data['iata_code']);

        return DB::transaction(function () use ($iataCode, $data) {
            PlanningLock::acquire();
            $airport = Airport::whereKey(strtoupper($iataCode))->lockForUpdate()->firstOrFail();
            foreach (['country', 'timezone', 'operating_hours', 'status'] as $field) {
                if (array_key_exists($field, $data) && $data[$field] === null) {
                    unset($data[$field]);
                }
            }

            if (! empty($data['icao_code'])) {
                $data['icao_code'] = strtoupper(trim($data['icao_code']));
            }

            $airport->update($data);

            return $airport->fresh('resources');
        });
    }

    /**
     * Xóa sân bay
     */
    public function deleteAirport(string $iataCode): bool
    {
        $this->authorize('write', $iataCode);

        return DB::transaction(function () use ($iataCode) {
            PlanningLock::acquire();
            $airport = Airport::whereKey(strtoupper($iataCode))->lockForUpdate()->firstOrFail();
            if ($airport->originRoutes()->exists() || $airport->destRoutes()->exists() || $airport->baseCrewMembers()->exists()) {
                throw ValidationException::withMessages(['airport' => ['Sân bay đang được sử dụng bởi đường bay hoặc tổ bay; hãy gỡ liên kết trước khi xóa.']]);
            }

            if ($airport->resources()->exists()) {
                throw ValidationException::withMessages(['airport' => ['Xóa hoặc chuyển tài nguyên trước khi xóa sân bay.']]);
            }

            return $airport->delete();
        });
    }

    /**
     * Lấy tài nguyên của một sân bay (UC-02)
     */
    public function getAirportResources(string $iataCode, ?string $type = null): Collection
    {
        $this->getAirportByIata($iataCode);
        $query = AirportResource::where('airport_code', strtoupper($iataCode));

        if ($type) {
            $query->where('resource_type', $type);
        }

        return $query->orderBy('resource_type')->orderBy('resource_code')->get();
    }

    public function pageResources(string $iataCode, array $filters = [])
    {
        $airport = $this->getAirportByIata($iataCode, false);
        Validator::make($filters, ['resource_type' => ['nullable', Rule::in(array_keys(AirportResource::TYPES))],
            'resource_status' => 'nullable|in:AVAILABLE,MAINTENANCE,OCCUPIED', 'resource_search' => 'nullable|string|max:50', 'per_page' => 'nullable|integer|between:1,100'])->validate();

        return AirportResource::where('airport_code', $airport->iata_code)
            ->when($filters['resource_type'] ?? null, fn ($q, $v) => $q->where('resource_type', $v))
            ->when($filters['resource_status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['resource_search'] ?? null, fn ($q, $v) => $q->where('resource_code', 'like', '%'.$v.'%'))
            ->orderBy('resource_type')->orderBy('resource_code')->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();
    }

    /**
     * Thêm tài nguyên mới cho sân bay (Terminal, Gate, Runway, Carousel)
     *
     * @throws ValidationException
     */
    public function createAirportResource(string $iataCode, array $data): AirportResource
    {
        $this->authorize('write', $iataCode);
        $data['resource_code'] = is_string($data['resource_code'] ?? null) ? trim($data['resource_code']) : ($data['resource_code'] ?? null);
        $data = Validator::make($data, ['resource_type' => ['required', Rule::in(array_keys(AirportResource::TYPES))],
            'resource_code' => 'required|string|max:50', 'status' => 'nullable|in:AVAILABLE,MAINTENANCE,OCCUPIED'])->validate();

        return DB::transaction(function () use ($iataCode, $data) {
            PlanningLock::acquire();
            $airport = Airport::whereKey(strtoupper($iataCode))->lockForUpdate()->firstOrFail();
            $data['resource_code'] = trim($data['resource_code']);

            $exists = AirportResource::where('airport_code', $airport->iata_code)
                ->where('resource_type', $data['resource_type'])
                ->where('resource_code', $data['resource_code'])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'resource_code' => ["Tài nguyên {$data['resource_code']} thuộc loại {$data['resource_type']} đã tồn tại tại sân bay {$airport->iata_code}."],
                ]);
            }

            return AirportResource::create([
                'airport_code' => $airport->iata_code,
                'resource_type' => $data['resource_type'],
                'resource_code' => trim($data['resource_code']),
                'status' => $data['status'] ?? AirportResource::STATUS_AVAILABLE,
            ]);
        });
    }

    /**
     * Xóa tài nguyên sân bay
     */
    public function deleteAirportResource(int $resourceId): bool
    {
        $resource = AirportResource::findOrFail($resourceId);
        $this->authorize('write', $resource->airport_code);

        return DB::transaction(function () use ($resource) {
            PlanningLock::acquire();
            Airport::whereKey($resource->airport_code)->lockForUpdate()->firstOrFail();
            $resource = AirportResource::whereKey($resource->id)->lockForUpdate()->firstOrFail();
            if (DB::table('flight_schedules')->where('resource_id', $resource->id)->exists()
                || DB::table('operational_flights')->where('resource_id', $resource->id)->exists()) {
                throw ValidationException::withMessages(['resource' => ['Tài nguyên có lịch/chuyến liên kết. Dùng tạm ngừng thay vì xóa lịch sử.']]);
            }

            return $resource->delete();
        });
    }

    public function updateAirportResource(int $resourceId, array $data): AirportResource
    {
        $resource = AirportResource::findOrFail($resourceId);
        $this->authorize('write', $resource->airport_code);
        if (isset($data['resource_code']) && is_string($data['resource_code'])) {
            $data['resource_code'] = trim($data['resource_code']);
        }

        return DB::transaction(function () use ($resource, $resourceId, $data) {
            PlanningLock::acquire();
            Airport::whereKey($resource->airport_code)->lockForUpdate()->firstOrFail();
            $resource = AirportResource::whereKey($resourceId)->lockForUpdate()->firstOrFail();
            Validator::make($data, ['resource_code' => 'sometimes|required|string|max:50',
                'status' => 'sometimes|required|in:AVAILABLE,MAINTENANCE,OCCUPIED'])->validate();
            $data = array_intersect_key($data, array_flip(['resource_code', 'status']));
            if (isset($data['resource_code'])) {
                $data['resource_code'] = trim($data['resource_code']);
                if (AirportResource::where('airport_code', $resource->airport_code)->where('resource_type', $resource->resource_type)
                    ->where('resource_code', $data['resource_code'])->where('id', '!=', $resourceId)->exists()) {
                    throw ValidationException::withMessages(['resource_code' => ['Mã tài nguyên đã tồn tại tại sân bay.']]);
                }
            }
            $resource->update($data);

            return $resource->fresh();
        });
    }
}
