<?php

namespace App\Services\Core;

use App\Shared\ValidationMessages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MasterDataQuality
{
    public const CATEGORIES = ['CABIN' => 'Hạng khoang', 'SERVICE' => 'Dịch vụ', 'CURRENCY' => 'Tiền tệ'];

    public function normalize(array $input): array
    {
        foreach (['category', 'code'] as $key) {
            if (is_string($input[$key] ?? null)) {
                $input[$key] = strtoupper(trim($input[$key]));
            }
        }
        if (is_array($input['payload'] ?? null) && is_string($input['payload']['cabin_code'] ?? null)) {
            $input['payload']['cabin_code'] = strtoupper(trim($input['payload']['cabin_code']));
        }

        return array_intersect_key($input, array_flip(['category', 'code', 'payload']));
    }

    public function errors(array $input, ?int $id = null): array
    {
        $category = $input['category'] ?? '';
        $keys = match ($category) {
            'CABIN' => 'name,timezone,demo', 'SERVICE' => 'name,timezone,cabin_code',
            'CURRENCY' => 'name,timezone,minor_units', default => 'name',
        };
        $rules = [
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9_.-]*$/D'],
            'payload' => ['required', 'array:'.$keys],
            'payload.name' => ['required', 'string', 'max:150', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'payload.timezone' => ['sometimes', 'required', 'timezone:all'],
            'payload.demo' => ['sometimes', 'boolean'],
            'payload.cabin_code' => ['sometimes', 'required', 'string', 'max:50'],
            'payload.minor_units' => [$category === 'CURRENCY' ? 'required' : 'sometimes', 'integer', 'between:0,4'],
        ];
        $errors = Validator::make($input, $rules, ValidationMessages::VI)->errors()->toArray();
        if (is_string($category) && is_string($input['code'] ?? null)
            && DB::table('master_data_records')->where('category', $category)->where('code', $input['code'])
                ->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
            $errors['code'][] = 'Mã đã tồn tại trong nhóm dữ liệu.';
        }
        $reference = $input['payload']['cabin_code'] ?? null;
        if ($category === 'SERVICE' && is_string($reference)
            && ! DB::table('master_data_records')->where('category', 'CABIN')->where('code', $reference)->where('version', '>', 0)->exists()) {
            $errors['payload.cabin_code'][] = 'Hạng khoang tham chiếu phải có phiên bản đã công bố.';
        }

        return $errors;
    }
}
