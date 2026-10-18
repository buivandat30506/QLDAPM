<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\BaseApiRequest;
use App\Services\Core\AircraftService;

class UpdateAircraftRequest extends BaseApiRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['tail_number', 'base_airport_code'] as $field) {
            if (is_string($this->input($field))) { $this->merge([$field => strtoupper(trim($this->input($field)))]); }
        }
    }
    public function rules(): array { return AircraftService::rules(true); }
}
