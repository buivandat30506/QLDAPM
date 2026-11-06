<?php

namespace App\Services\Core;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SeatLayout
{
    public const CABINS = ['ECONOMY', 'PREMIUM_ECONOMY', 'BUSINESS', 'FIRST'];

    /** Explicitly entered physical layout; no inferred layout for imported aircraft. */
    public function build(array $input, int $maximum): array
    {
        $data = Validator::make($input, [
            'name' => 'required|string|max:120', 'reason' => 'required|string|max:500',
            'sections' => 'required|array|min:1|max:10', 'sections.*' => 'array:from,to,columns,cabin',
            'sections.*.from' => 'required|integer|min:1|max:200', 'sections.*.to' => 'required|integer|min:1|max:200',
            'sections.*.columns' => ['required', 'string', 'regex:/^[A-HJ-Z]{1,12}$/'],
            'sections.*.cabin' => ['required', Rule::in(self::CABINS)],
            'blocked_seats' => 'nullable|array|max:1000', 'blocked_seats.*' => ['required', 'string', 'regex:/^[1-9][0-9]{0,2}[A-HJ-Z]$/'],
            'expected_revision' => 'required|integer|min:1',
        ])->validate();
        $seats = [];
        $blocked = $data['blocked_seats'] ?? [];
        foreach ($data['sections'] as $i => $section) {
            if ($section['to'] < $section['from'] || count(array_unique(str_split($section['columns']))) !== strlen($section['columns'])) {
                throw ValidationException::withMessages(['sections.'.$i => ['Dải hàng phải tăng và cột ghế không được lặp.']]);
            }
            for ($row = (int) $section['from']; $row <= (int) $section['to']; $row++) {
                foreach (str_split($section['columns']) as $column) {
                    $label = $row.$column;
                    if (isset($seats[$label])) {
                        throw ValidationException::withMessages(['sections' => ['Các vùng cấu hình có ghế trùng nhau: '.$label]]);
                    }
                    $seats[$label] = ['label' => $label, 'row' => $row, 'column' => $column, 'cabin' => $section['cabin'], 'enabled' => ! in_array($label, $blocked, true)];
                    if (count($seats) > $maximum) {
                        throw ValidationException::withMessages(['sections' => ['Số ghế vật lý vượt sức chứa tối đa của dòng tàu bay.']]);
                    }
                }
            }
        }
        if (array_diff($blocked, array_keys($seats))) {
            throw ValidationException::withMessages(['blocked_seats' => ['Ghế khóa phải tồn tại trong cấu hình.']]);
        }
        $capacity = count(array_filter($seats, fn ($s) => $s['enabled']));
        if (! $capacity) {
            throw ValidationException::withMessages(['blocked_seats' => ['Phải còn ít nhất một ghế sử dụng được.']]);
        }

        return ['name' => trim($data['name']), 'reason' => trim($data['reason']), 'sections' => $data['sections'],
            'seats' => array_values($seats), 'seat_count' => count($seats), 'sellable_capacity' => $capacity];
    }
}
