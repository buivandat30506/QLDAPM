<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponse
{
    /**
     * Send standard success response
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Thành công',
        int $statusCode = Response::HTTP_OK
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'errors'  => null,
        ], $statusCode);
    }

    /**
     * Send standard error response
     */
    protected function errorResponse(
        string $message = 'Đã có lỗi xảy ra',
        mixed $errors = null,
        int $statusCode = Response::HTTP_BAD_REQUEST
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ], $statusCode);
    }
}
