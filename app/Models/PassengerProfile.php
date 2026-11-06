<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PassengerProfile extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'passenger_profiles';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'created_by',
        'id_card_passport',
        'full_name',
        'dob',
        'gender',
        'phone',
        'email',
    ];

    // Explicit profile edit views select fields; nested booking/baggage JSON must not leak PII.
    protected $hidden = ['id_card_passport', 'phone', 'email', 'dob'];

    protected function casts(): array
    {
        return [
            'dob' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'passenger_id');
    }

    public function checkinRecords(): HasMany
    {
        return $this->hasMany(CheckinRecord::class, 'passenger_id');
    }

    public function baggageTags(): HasMany
    {
        return $this->hasMany(BaggageTag::class, 'passenger_id');
    }
}
