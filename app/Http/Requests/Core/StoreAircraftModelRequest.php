<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\BaseApiRequest;

class StoreAircraftModelRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'model_code'               => ['required', 'string', 'max:30', 'unique:aircraft_models,model_code'],
            'manufacturer'             => ['required', 'string', 'max:50'],
            'seat_capacity'            => ['required', 'integer', 'min:1', 'max:1000'],
            'turnaround_time_minutes'  => ['required', 'integer', 'min:15', 'max:1440'],
        ];
    }
}
