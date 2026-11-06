<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\BaseApiRequest;

class StoreFlightScheduleRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'flight_number'      => ['required', 'string', 'max:10'],
            'route_id'           => ['required', 'integer', 'exists:routes,id'],
            'model_id'           => ['required', 'integer', 'exists:aircraft_models,id'],
            'departure_time_utc' => ['nullable', 'required_without:departure_time_local'],
            'arrival_time_utc'   => ['nullable', 'required_without:arrival_time_local'],
            'days_of_week'       => ['nullable'],
            'status'             => ['nullable', 'string', 'in:DRAFT,APPROVED,PUBLISHED'],
            'is_active'          => ['nullable', 'boolean'],
            'aircraft_tail' => ['nullable', 'string', 'exists:aircrafts,tail_number'],
            'resource_id' => ['nullable', 'integer', 'exists:airport_resources,id'],
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'season_code' => ['nullable', 'string', 'max:30'],
            'source_timezone' => ['sometimes', 'required', 'timezone:all'],
            'departure_time_local' => ['nullable', 'date_format:H:i,H:i:s'],
            'arrival_time_local' => ['nullable', 'date_format:H:i,H:i:s'],
            'arrival_day_offset' => ['sometimes', 'integer', 'between:0,2'],
            'excluded_dates' => ['nullable'], 'extra_dates' => ['nullable'],
        ];
    }
}
