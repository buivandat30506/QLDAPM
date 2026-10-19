<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\BaseApiRequest;

class UpdateCrewMemberRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'expected_revision'=>['sometimes','required','integer','min:1'],
            'license_number'=>['sometimes','required','string','max:50'],
            'full_name'          => ['sometimes', 'required', 'string', 'max:100'],
            'email'              => ['sometimes', 'required', 'email', 'max:100'],
            'phone'              => ['sometimes', 'required', 'string', 'max:20'],
            'rank'               => ['sometimes', 'required', 'string', 'in:CAPTAIN,FIRST_OFFICER,PURSER,FLIGHT_ATTENDANT'],
            'base_airport_code'  => ['sometimes', 'required', 'string', 'size:3', 'exists:airports,iata_code'],
            'status'             => ['nullable', 'string', 'in:AVAILABLE,ON_DUTY,RESTING,LEAVE'],
            'total_flight_hours' => ['nullable', 'numeric', 'min:0'],
            'license_expires_on' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'training_expires_on' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'qualified_model_ids' => ['sometimes', 'required', 'array', 'min:1'],
            'qualified_model_ids.*' => ['integer', 'exists:aircraft_models,id'],
            'available_from_utc' => ['nullable', 'date'],
            'available_until_utc' => ['nullable', 'date', 'after:available_from_utc'],
        ];
    }
}
