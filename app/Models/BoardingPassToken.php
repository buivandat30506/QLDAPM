<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardingPassToken extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'boarding_pass_tokens';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'checkin_id',
        'token_hash',
        'is_used',
        'valid_until',
        'boarded_at_utc',
    ];

    protected function casts(): array
    {
        return [
            'is_used'        => 'boolean',
            'valid_until'    => 'datetime',
            'boarded_at_utc' => 'datetime',
        ];
    }

    public function checkinRecord(): BelongsTo
    {
        return $this->belongsTo(CheckinRecord::class, 'checkin_id');
    }

    public function isValid(): bool
    {
        return !$this->is_used && Carbon::now('UTC')->lessThanOrEqualTo($this->valid_until);
    }
}
