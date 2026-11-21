<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AirportInfrastructure extends Model
{
    protected $connection = 'ops';

    protected $table = 'airport_infrastructure';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean'];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function type()
    {
        return $this->belongsTo(InfrastructureType::class, 'type_code', 'code');
    }
}
