<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\BaseApiRequest;

class UpdateAirportRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return \App\Services\Core\AirportData::rules(strtoupper($this->route('iataCode')));
    }

    protected function prepareForValidation(): void { $this->merge(\App\Services\Core\AirportData::normalize($this->all())); }
    public function messages(): array { return \App\Shared\ValidationMessages::VI; }
}
