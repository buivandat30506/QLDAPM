<?php

namespace App\Services\Core;

use App\Shared\ValidationMessages;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AirportData
{
    public static function rules(?string $iata = null): array
    {
        return [
            'iata_code' => [$iata ? 'sometimes' : 'required', 'string', 'regex:/^[A-Z]{3}$/D', Rule::unique('airports', 'iata_code')->ignore($iata, 'iata_code')],
            'icao_code' => ['nullable', 'string', 'regex:/^[A-Z]{4}$/D', Rule::unique('airports', 'icao_code')->ignore($iata, 'iata_code')],
            'name' => [$iata ? 'sometimes' : 'required', 'required', 'string', 'max:150'],
            'city' => [$iata ? 'sometimes' : 'required', 'required', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'timezone' => ['nullable', 'timezone:all'], 'operating_hours' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['ACTIVE', 'INACTIVE', 'CLOSED'])],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ];
    }

    public static function normalize(array $input): array
    {
        foreach (['iata_code', 'icao_code'] as $field) {
            if (is_string($input[$field] ?? null)) {
                $input[$field] = strtoupper(trim($input[$field]));
            }
        }
        foreach (['name', 'city', 'country', 'timezone', 'operating_hours'] as $field) {
            if (is_string($input[$field] ?? null)) {
                $input[$field] = trim($input[$field]);
            }
        }
        if (isset($input['icao_code']) && $input['icao_code'] === '') {
            $input['icao_code'] = null;
        }

        return $input;
    }

    public static function validate(array $input, ?string $iata = null): array
    {
        return Validator::make(self::normalize($input), self::rules($iata), ValidationMessages::VI)->validate();
    }
}
