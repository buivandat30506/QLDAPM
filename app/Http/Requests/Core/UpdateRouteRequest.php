<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\BaseApiRequest;

class UpdateRouteRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return \App\Services\Core\RouteService::rules(true);
    }
}
