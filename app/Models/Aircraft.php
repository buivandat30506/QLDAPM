<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aircraft extends Model
{
    use HasFactory;

    public const STATUS_OPERATIONAL = 'OPERATIONAL';
    public const STATUS_MAINTENANCE = 'MAINTENANCE';
    public const STATUS_GROUNDED    = 'GROUNDED';

    protected $table = 'aircrafts';
    protected $primaryKey = 'tail_number';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tail_number',
        'model_id',
        'manufacture_year',
        'total_flight_hours',
        'total_flight_cycles',
        'operational_status',
        'maintenance_base_hours', 'maintenance_base_cycles',
        'base_airport_code', 'revision', 'opening_flight_hours', 'opening_flight_cycles', 'opening_reason', 'current_configuration_id',
    ];

    protected function casts(): array
    {
        return [
            'manufacture_year'    => 'integer',
            'total_flight_hours'  => 'decimal:2',
            'total_flight_cycles' => 'integer',
            'revision' => 'integer', 'opening_flight_hours' => 'decimal:2', 'opening_flight_cycles' => 'integer',
        ];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AircraftModel::class, 'model_id');
    }

    public function configuration(): BelongsTo { return $this->belongsTo(AircraftConfiguration::class, 'current_configuration_id'); }
    public function configurations(): HasMany { return $this->hasMany(AircraftConfiguration::class, 'aircraft_tail', 'tail_number'); }

    public function operationalFlights(): HasMany
    {
        return $this->hasMany(OperationalFlight::class, 'aircraft_tail', 'tail_number');
    }

    /**
     * Cảnh báo bảo dưỡng kỹ thuật khi vượt ngưỡng giờ bay hoặc chu kỳ (UC-11)
     */
    public function needsMaintenance(): bool
    {
        if ($this->total_flight_hours === null || $this->total_flight_cycles === null) { return true; }
        return bccomp(bcsub((string) $this->total_flight_hours, (string) ($this->maintenance_base_hours ?? 0), 2), '3000.00', 2) >= 0
            || ($this->total_flight_cycles - $this->maintenance_base_cycles) >= 1500;
    }
}
