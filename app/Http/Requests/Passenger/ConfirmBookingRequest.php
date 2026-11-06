<?php

namespace App\Http\Requests\Passenger;

use App\Http\Requests\BaseApiRequest;

class ConfirmBookingRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'order_code' => ['required', 'string', 'size:6'],
        ];
    }
}
