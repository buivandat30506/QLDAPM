<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Airport extends Model
{
    use HasFactory;

    protected $table = 'airports';
    protected $primaryKey = 'iata_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'iata_code',
        'icao_code',
        'name',
        'city',
        'country',
        'timezone',
        'operating_hours',
        'status',
        'latitude',
        'longitude',
    ];

    /**
     * Tài nguyên sân bay (Terminals, Gates, Runways, Baggage Carousels - UC-02)
     */
    public function resources(): HasMany
    {
        return $this->hasMany(AirportResource::class, 'airport_code', 'iata_code');
    }

    /**
     * Tuyến bay khởi hành từ sân bay này
     */
    public function originRoutes(): HasMany
    {
        return $this->hasMany(Route::class, 'origin_airport_code', 'iata_code');
    }

    /**
     * Tuyến bay hạ cánh tại sân bay này
     */
    public function destRoutes(): HasMany
    {
        return $this->hasMany(Route::class, 'dest_airport_code', 'iata_code');
    }

    /**
     * Phi hành đoàn có sân bay căn cứ (Base Airport) tại đây
     */
    public function baseCrewMembers(): HasMany
    {
        return $this->hasMany(CrewMember::class, 'base_airport_code', 'iata_code');
    }
}
