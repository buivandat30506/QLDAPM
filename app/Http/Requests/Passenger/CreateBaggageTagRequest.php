<?php

namespace App\Http\Requests\Passenger;

use App\Http\Requests\BaseApiRequest;

class CreateBaggageTagRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'flight_id'    => ['required', 'uuid', 'exists:operational_flights,id'],
            'passenger_id' => ['required', 'uuid', 'exists:passenger_profiles,id'],
            'weight_kg'    => ['required', 'numeric', 'min:0.5', 'max:50.0'],
            'location'     => ['nullable', 'string', 'max:100'],
            'custody_unit' => ['nullable', 'string', 'max:100'],
        ];
    }
}
