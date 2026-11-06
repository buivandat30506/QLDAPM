<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaggageTrackingEvent extends Model
{
    use HasFactory;

    public const EVENT_CHECK_IN           = 'CHECK_IN';
    public const EVENT_LOADED_TO_AIRCRAFT = 'LOADED_TO_AIRCRAFT';
    public const EVENT_UNLOADED           = 'UNLOADED';
    public const EVENT_CLAIMED            = 'CLAIMED';

    protected $table = 'baggage_tracking_events';

    protected $fillable = [
        'tag_code',
        'event_type',
        'scan_time_utc',
        'location',
        'custody_unit',
    ];

    protected function casts(): array
    {
        return [
            'scan_time_utc' => 'datetime',
        ];
    }

    public function baggageTag(): BelongsTo
    {
        return $this->belongsTo(BaggageTag::class, 'tag_code', 'tag_code');
    }
}
