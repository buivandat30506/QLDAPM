<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestContext
{
    public function handle(Request $request, Closure $next)
    {
        $id = (string) Str::uuid();
        $request->attributes->set('correlation_id', $id);
        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $id);
        if ($request->is('api/*') && $response->getStatusCode() >= 400 && $response instanceof \Illuminate\Http\JsonResponse) {
            $data = $response->getData(true);
            $data['correlation_id'] = $id;
            $data['code'] ??= 'HTTP_'.$response->getStatusCode();
            $data['success'] = false;
            $data['data'] = null;
            $data['errors'] ??= null;
            $data['message'] ??= 'Không thể xử lý yêu cầu.';
            unset($data['exception'], $data['file'], $data['line'], $data['trace']);
            if ($response->getStatusCode() >= 500) {
                $data = ['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Không thể xử lý yêu cầu. Vui lòng cung cấp mã truy vết.', 'errors' => null, 'data' => null, 'correlation_id' => $id];
            }
            $response->setData($data);
        }

        return $response;
    }
}
