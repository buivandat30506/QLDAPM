<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlightCrewAssignment extends Model
{
    use HasFactory, HasUuids;

    public const POSITION_CAPTAIN          = 'CAPTAIN';
    public const POSITION_FIRST_OFFICER    = 'FIRST_OFFICER';
    public const POSITION_PURSER           = 'PURSER';
    public const POSITION_FLIGHT_ATTENDANT = 'FLIGHT_ATTENDANT';

    public const STATUS_ASSIGNED  = 'ASSIGNED';
    public const STATUS_CONFIRMED = 'CONFIRMED';
    public const STATUS_REPLACED  = 'REPLACED';

    protected $table = 'flight_crew_assignments';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'flight_id',
        'crew_id',
        'assigned_position',
        'duty_start_utc',
        'duty_end_utc',
        'status',
        'notes',
        'approved_at', 'confirmed_at',
        'approved_by', 'confirmed_by', 'confirmation_mode', 'rule_version_code', 'revision',
    ];

    protected function casts(): array
    {
        return [
            'duty_start_utc' => 'datetime',
            'duty_end_utc'   => 'datetime',
            'revision' => 'integer',
        ];
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(OperationalFlight::class, 'flight_id');
    }

    public function crewMember(): BelongsTo
    {
        return $this->belongsTo(CrewMember::class, 'crew_id');
    }
}
