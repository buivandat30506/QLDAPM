<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfrastructureType extends Model
{
    protected $connection = 'ops';

    protected $table = 'infrastructure_types';

    protected $primaryKey = 'code';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = ['code'];
}
