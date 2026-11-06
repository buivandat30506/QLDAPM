<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistanceRequest extends Model
{
    use HasUuids;

    protected $fillable = ['passenger_id', 'flight_id', 'created_by', 'category', 'details', 'department', 'status', 'resolution'];

    protected $hidden = ['details', 'resolution'];

    protected function casts(): array
    {
        return ['details' => 'encrypted', 'resolution' => 'encrypted'];
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(PassengerProfile::class, 'passenger_id');
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(OperationalFlight::class, 'flight_id');
    }
}
