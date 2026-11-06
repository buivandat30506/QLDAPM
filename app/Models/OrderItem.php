<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory, HasUuids;

    public const TYPE_SEAT          = 'SEAT';
    public const TYPE_BAGGAGE_EXTRA = 'BAGGAGE_EXTRA';

    protected $table = 'order_items';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'order_id',
        'flight_id',
        'passenger_id',
        'item_type',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderBooking::class, 'order_id');
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(OperationalFlight::class, 'flight_id');
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(PassengerProfile::class, 'passenger_id');
    }
}
