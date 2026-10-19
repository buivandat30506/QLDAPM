<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrewMember extends Model
{
    use HasFactory, HasUuids;

    public const RANK_CAPTAIN          = 'CAPTAIN';
    public const RANK_FIRST_OFFICER    = 'FIRST_OFFICER';
    public const RANK_PURSER           = 'PURSER';
    public const RANK_FLIGHT_ATTENDANT = 'FLIGHT_ATTENDANT';

    public const STATUS_AVAILABLE = 'AVAILABLE';
    public const STATUS_ON_DUTY   = 'ON_DUTY';
    public const STATUS_RESTING   = 'RESTING';
    public const STATUS_LEAVE     = 'LEAVE';

    protected $table = 'crew_members';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'crew_code',
        'full_name',
        'email',
        'phone',
        'rank',
        'base_airport_code',
        'license_number',
        'status',
        'total_flight_hours',
        'license_expires_on', 'training_expires_on', 'qualified_model_ids',
        'available_from_utc', 'available_until_utc', 'user_id',
        'revision',
    ];

    protected function casts(): array
    {
        return [
            'total_flight_hours' => 'decimal:2',
            'qualified_model_ids' => 'array',
            'revision' => 'integer',
        ];
    }

    /**
     * Sân bay căn cứ (Base Airport)
     */
    public function baseAirport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'base_airport_code', 'iata_code');
    }

    /**
     * Lịch sử phân công ca bay
     */
    public function crewAssignments(): HasMany
    {
        return $this->hasMany(FlightCrewAssignment::class, 'crew_id');
    }

    /**
     * Các chuyến bay đã được phân công
     */
    public function flights(): BelongsToMany
    {
        return $this->belongsToMany(OperationalFlight::class, 'flight_crew_assignments', 'crew_id', 'flight_id')
                    ->withPivot('assigned_position', 'duty_start_utc', 'duty_end_utc', 'status')
                    ->withTimestamps();
    }
}
