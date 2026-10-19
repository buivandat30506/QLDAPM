<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AircraftModel extends Model
{
    use HasFactory;

    protected $table = 'aircraft_models';

    protected $fillable = [
        'model_code',
        'manufacturer',
        'seat_capacity',
        'turnaround_time_minutes',
    ];

    protected function casts(): array
    {
        return [
            'seat_capacity' => 'integer',
            'turnaround_time_minutes' => 'integer',
        ];
    }

    public function aircrafts(): HasMany
    {
        return $this->hasMany(Aircraft::class, 'model_id');
    }

    public function flightSchedules(): HasMany
    {
        return $this->hasMany(FlightSchedule::class, 'model_id');
    }
}
