<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderBooking extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_HOLDING   = 'HOLDING';
    public const STATUS_CONFIRMED = 'CONFIRMED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_REFUNDED  = 'REFUNDED';

    protected $table = 'order_bookings';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'order_code',
        'customer_user_id',
        'total_amount',
        'order_status',
        'hold_expires_at',
        'currency',
        'pricing_profile',
    ];

    protected function casts(): array
    {
        return [
            'total_amount'    => 'decimal:2',
            'hold_expires_at' => 'datetime',
        ];
    }

    /**
     * Customer who initiated the booking
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    /**
     * Order items (seats, baggage)
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    /**
     * Check if the 15-minute hold lock has expired
     */
    public function isExpired(): bool
    {
        return $this->order_status === self::STATUS_HOLDING &&
               Carbon::now('UTC')->isAfter($this->hold_expires_at);
    }

    /**
     * Check if booking is in valid holding state
     */
    public function isHolding(): bool
    {
        return $this->order_status === self::STATUS_HOLDING &&
               Carbon::now('UTC')->lessThanOrEqualTo($this->hold_expires_at);
    }

    /**
     * Scope for expired holding bookings
     */
    public function scopeExpiredHolding(Builder $query): Builder
    {
        return $query->where('order_status', self::STATUS_HOLDING)
                     ->where('hold_expires_at', '<', Carbon::now('UTC'));
    }

    /**
     * Scope for confirmed bookings
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('order_status', self::STATUS_CONFIRMED);
    }
}
