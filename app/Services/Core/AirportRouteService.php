<?php

namespace App\Services\Core;

use App\Models\Airport;
use App\Models\Route;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class AirportRouteService
{
    /**
     * Get all active airports
     */
    public function getAllAirports(): Collection
    {
        return Airport::orderBy('iata_code')->get();
    }

    /**
     * Find airport by IATA code
     */
    public function getAirportByIata(string $iataCode): Airport
    {
        return Airport::findOrFail(strtoupper($iataCode));
    }

    /**
     * Create a new airport
     */
    public function createAirport(array $data): Airport
    {
        $data['iata_code'] = strtoupper($data['iata_code']);
        if (!empty($data['icao_code'])) {
            $data['icao_code'] = strtoupper($data['icao_code']);
        }

        return Airport::create($data);
    }

    /**
     * Update existing airport
     */
    public function updateAirport(string $iataCode, array $data): Airport
    {
        $airport = $this->getAirportByIata($iataCode);
        $airport->update($data);

        return $airport->fresh();
    }

    /**
     * Get all routes with airport relations
     */
    public function getAllRoutes(): Collection
    {
        return Route::with(['originAirport', 'destAirport'])->get();
    }

    /**
     * Create a new route with business rule: origin != dest
     *
     * @throws ValidationException
     */
    public function createRoute(array $data): Route
    {
        $origin = strtoupper($data['origin_airport_code']);
        $dest   = strtoupper($data['dest_airport_code']);

        // Business Rule: Sân bay xuất phát không được trùng sân bay đích
        if ($origin === $dest) {
            throw ValidationException::withMessages([
                'dest_airport_code' => ['Sân bay điểm đến không được trùng với sân bay xuất phát.'],
            ]);
        }

        // Verify airports exist
        $this->getAirportByIata($origin);
        $this->getAirportByIata($dest);

        // Check duplicate route
        $exists = Route::where('origin_airport_code', $origin)
            ->where('dest_airport_code', $dest)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'route' => ["Đường bay từ {$origin} đến {$dest} đã tồn tại trong hệ thống."],
            ]);
        }

        return Route::create([
            'origin_airport_code'       => $origin,
            'dest_airport_code'         => $dest,
            'distance_km'               => $data['distance_km'],
            'standard_duration_minutes' => $data['standard_duration_minutes'],
            'status'                    => $data['status'] ?? 'ACTIVE',
        ]);
    }
}
