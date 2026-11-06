<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BaggageTag extends Model
{
    use HasFactory;

    public const STATUS_CHECKED_IN = 'CHECKED_IN';
    public const STATUS_LOADED     = 'LOADED';
    public const STATUS_TRANSIT    = 'TRANSIT';
    public const STATUS_DELIVERED  = 'DELIVERED';
    public const STATUS_LOST       = 'LOST';

    protected $table = 'baggage_tags';
    protected $primaryKey = 'tag_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tag_code',
        'flight_id',
        'passenger_id',
        'weight_kg',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
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

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(BaggageTrackingEvent::class, 'tag_code', 'tag_code')
                    ->orderBy('scan_time_utc', 'asc');
    }
}
