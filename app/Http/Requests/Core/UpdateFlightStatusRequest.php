<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\BaseApiRequest;

class UpdateFlightStatusRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'expected_revision' => ['sometimes','integer','min:0'],
            'flight_status'  => ['required', 'string', 'in:SCHEDULED,BOARDING,DEPARTED,ARRIVED,DELAYED,CANCELLED'],
            'actual_dep_utc' => ['nullable', 'date'],
            'actual_arr_utc' => ['nullable', 'date'],
            'gate_number'    => ['nullable', 'string', 'max:20'],
            'delay_reason'   => ['nullable', 'string', 'max:500'],
        ];
    }
}
