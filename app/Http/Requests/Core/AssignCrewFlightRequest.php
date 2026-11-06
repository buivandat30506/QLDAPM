<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\BaseApiRequest;

class AssignCrewFlightRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'crew_id'           => ['required', 'uuid', 'exists:crew_members,id'],
            'assigned_position' => ['required', 'string', 'in:CAPTAIN,FIRST_OFFICER,PURSER,FLIGHT_ATTENDANT'],
            'duty_start_utc'    => ['nullable', 'date'],
            'duty_end_utc'      => ['nullable', 'date'],
            'notes'             => ['nullable', 'string', 'max:255'],
        ];
    }
}
