<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    use HasFactory;

    protected $table = 'routes';

    protected $fillable = [
        'route_code',
        'origin_airport_code',
        'dest_airport_code',
        'distance_km',
        'standard_duration_minutes',
        'status',
        'operating_from', 'operating_to', 'allowed_model_ids', 'blocked_weekdays', 'restriction_notes',
    ];

    protected function casts(): array
    {
        return [
            'distance_km' => 'decimal:2',
            'standard_duration_minutes' => 'integer',
            'allowed_model_ids' => 'array', 'blocked_weekdays' => 'array',
        ];
    }

    public function originAirport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'origin_airport_code', 'iata_code');
    }

    public function destAirport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'dest_airport_code', 'iata_code');
    }

    public function flightSchedules(): HasMany
    {
        return $this->hasMany(FlightSchedule::class, 'route_id');
    }

    public function operationalFlights(): HasMany
    {
        return $this->hasMany(OperationalFlight::class, 'route_id');
    }
}
