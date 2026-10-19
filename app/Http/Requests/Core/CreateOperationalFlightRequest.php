<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\BaseApiRequest;

class CreateOperationalFlightRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'flight_number'     => ['sometimes', 'string', 'max:10'],
            'route_id'          => ['sometimes', 'integer', 'exists:routes,id'],
            'aircraft_tail'     => ['sometimes', 'string', 'exists:aircrafts,tail_number'],
            'scheduled_dep_utc' => ['sometimes', 'date_format:Y-m-d H:i:s'],
            'scheduled_arr_utc' => ['sometimes', 'date_format:Y-m-d H:i:s', 'after:scheduled_dep_utc'],
            'flight_date'       => ['required_without:service_date', 'date_format:Y-m-d'],
            'service_date'      => ['sometimes', 'date_format:Y-m-d'],
            'schedule_id'       => ['required', 'uuid', 'exists:flight_schedules,id'],
            'gate_number'       => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_arr_utc.after' => 'Thời gian hạ cánh phải diễn ra sau thời gian cất cánh.',
        ];
    }
}
