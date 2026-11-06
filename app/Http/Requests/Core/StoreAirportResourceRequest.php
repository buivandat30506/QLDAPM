<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\BaseApiRequest;

class StoreAirportResourceRequest extends BaseApiRequest
{
    public function messages(): array { return \App\Shared\ValidationMessages::VI; }
    public function rules(): array
    {
        return [
            'resource_type' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Models\AirportResource::TYPES))],
            'resource_code' => ['required', 'string', 'max:50'],
            'status'        => ['nullable', 'string', 'in:AVAILABLE,MAINTENANCE,OCCUPIED'],
        ];
    }
}
