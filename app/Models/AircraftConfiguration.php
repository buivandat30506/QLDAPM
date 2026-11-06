<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AircraftConfiguration extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sections' => 'array', 'seats' => 'array', 'created_at' => 'datetime', 'version' => 'integer',
            'seat_count' => 'integer', 'sellable_capacity' => 'integer'];
    }
}
