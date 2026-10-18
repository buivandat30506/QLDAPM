<?php

namespace App\Http\Requests\Passenger;

use App\Http\Requests\BaseApiRequest;

class CreateBookingRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'customer_user_id'              => ['required', 'uuid', 'exists:users,id'],
            'flight_id'                     => ['required', 'uuid', 'exists:operational_flights,id'],
            'passengers'                    => ['required', 'array', 'min:1'],
            'passengers.*.id_card_passport' => ['required', 'string', 'max:50'],
            'passengers.*.full_name'        => ['required', 'string', 'max:100'],
            'passengers.*.dob'              => ['required', 'date_format:Y-m-d'],
            'passengers.*.gender'           => ['required', 'string', 'in:MALE,FEMALE,OTHER'],
            'passengers.*.phone'            => ['nullable', 'string', 'max:20'],
            'passengers.*.email'            => ['nullable', 'email', 'max:100'],
            'passengers.*.checked_baggage'  => ['sometimes', 'boolean'],
        ];
    }
}
