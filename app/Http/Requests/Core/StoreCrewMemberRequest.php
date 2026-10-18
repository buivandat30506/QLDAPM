<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\BaseApiRequest;

class StoreCrewMemberRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'crew_code'          => ['required', 'string', 'max:20', 'unique:crew_members,crew_code'],
            'full_name'          => ['required', 'string', 'max:100'],
            'email'              => ['required', 'email', 'max:100', 'unique:crew_members,email'],
            'phone'              => ['required', 'string', 'max:20'],
            'rank'               => ['required', 'string', 'in:CAPTAIN,FIRST_OFFICER,PURSER,FLIGHT_ATTENDANT'],
            'base_airport_code'  => ['required', 'string', 'size:3', 'exists:airports,iata_code'],
            'license_number'     => ['required', 'string', 'max:50', 'unique:crew_members,license_number'],
            'status'             => ['nullable', 'string', 'in:AVAILABLE,ON_DUTY,RESTING,LEAVE'],
            'total_flight_hours' => ['nullable', 'numeric', 'min:0'],
            'license_expires_on' => ['required', 'date_format:Y-m-d'],
            'training_expires_on' => ['required', 'date_format:Y-m-d'],
            'qualified_model_ids' => ['required', 'array', 'min:1'],
            'qualified_model_ids.*' => ['integer', 'exists:aircraft_models,id'],
            'available_from_utc' => ['nullable', 'date'],
            'available_until_utc' => ['nullable', 'date', 'after:available_from_utc'],
        ];
    }
}
