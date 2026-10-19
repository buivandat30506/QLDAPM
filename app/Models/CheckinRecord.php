<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CheckinRecord extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'checkin_records';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'flight_id',
        'passenger_id',
        'seat_number',
        'checkin_time_utc',
    ];

    protected function casts(): array
    {
        return [
            'checkin_time_utc' => 'datetime',
        ];
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(OperationalFlight::class, 'flight_id');
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(PassengerProfile::class, 'passenger_id');
    }

    public function boardingPassToken(): HasOne
    {
        return $this->hasOne(BoardingPassToken::class, 'checkin_id');
    }
}
