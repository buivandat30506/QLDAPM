<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator)
    {
        if (!$this->is('api/*') && !$this->expectsJson()) {
            parent::failedValidation($validator);
        }
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Dữ liệu yêu cầu không hợp lệ.',
            'data'    => null,
            'errors'  => $validator->errors(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
