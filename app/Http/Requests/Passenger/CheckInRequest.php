<?php

namespace App\Http\Requests\Passenger;

use App\Http\Requests\BaseApiRequest;

class CheckInRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'flight_id'    => ['required', 'uuid', 'exists:operational_flights,id'],
            'passenger_id' => ['required', 'uuid', 'exists:passenger_profiles,id'],
            'seat_number'  => ['required', 'string', 'max:5', 'regex:/^[0-9]{1,2}[A-F]$/i'],
        ];
    }

    public function messages(): array
    {
        return [
            'seat_number.regex' => 'Số ghế không đúng định dạng hàng không (Ví dụ: 12A, 4F, 28C).',
        ];
    }
}
