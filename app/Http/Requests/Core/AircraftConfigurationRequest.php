<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\BaseApiRequest;

class AircraftConfigurationRequest extends BaseApiRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('blocked_seats'))) {
            $this->merge(['blocked_seats' => preg_split('/[\s,;]+/', strtoupper(trim($this->input('blocked_seats'))), -1, PREG_SPLIT_NO_EMPTY)]);
        }
        if (is_array($this->input('sections'))) {
            $sections = array_values(array_filter($this->input('sections'), fn ($s) => ! is_array($s) || ! empty($s['from']) || ! empty($s['to'])));
            foreach ($sections as &$s) { if (is_array($s) && is_string($s['columns'] ?? null)) { $s['columns'] = strtoupper(trim($s['columns'])); } }
            $this->merge(['sections' => $sections]);
        }
    }
    public function rules(): array
    {
        return ['name' => 'required|string|max:120', 'reason' => 'required|string|max:500', 'expected_revision' => 'required|integer|min:1',
            'sections' => 'required|array|min:1|max:10', 'sections.*' => 'array:from,to,columns,cabin', 'blocked_seats' => 'nullable|array|max:1000'];
    }
}
