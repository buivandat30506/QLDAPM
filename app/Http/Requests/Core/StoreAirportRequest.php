<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\BaseApiRequest;

class StoreAirportRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return \App\Services\Core\AirportData::rules();
    }

    protected function prepareForValidation(): void { $this->merge(\App\Services\Core\AirportData::normalize($this->all())); }
    public function messages(): array { return \App\Shared\ValidationMessages::VI; }
}
