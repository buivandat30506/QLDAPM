<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\BaseApiRequest;

class StoreRouteRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return \App\Services\Core\RouteService::rules(false);
    }

    public function messages(): array
    {
        return [
            'dest_airport_code.different' => 'Sân bay điểm đến phải khác sân bay xuất phát.',
            'origin_airport_code.exists'  => 'Sân bay xuất phát không tồn tại trong hệ thống.',
            'dest_airport_code.exists'    => 'Sân bay điểm đến không tồn tại trong hệ thống.',
        ];
    }
}
