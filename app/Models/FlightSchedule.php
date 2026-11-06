<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlightSchedule extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_DRAFT     = 'DRAFT';
    public const STATUS_APPROVED  = 'APPROVED';
    public const STATUS_PUBLISHED = 'PUBLISHED';

    protected $table = 'flight_schedules';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'flight_number',
        'route_id',
        'model_id',
        'departure_time_utc',
        'arrival_time_utc',
        'days_of_week',
        'status',
        'is_active',
        'aircraft_tail', 'resource_id', 'effective_from', 'effective_to', 'version', 'validation_notes',
        'season_code', 'excluded_dates', 'extra_dates', 'source_timezone', 'departure_time_local', 'arrival_time_local', 'arrival_day_offset',
    ];

    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'is_active'    => 'boolean',
            'excluded_dates' => 'array', 'extra_dates' => 'array', 'arrival_day_offset' => 'integer',
            'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'published_at' => 'datetime',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    public function aircraftModel(): BelongsTo
    {
        return $this->belongsTo(AircraftModel::class, 'model_id');
    }

    public function operationalFlights(): HasMany
    {
        return $this->hasMany(OperationalFlight::class, 'schedule_id');
    }
}
