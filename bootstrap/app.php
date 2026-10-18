<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\RequestContext::class);
        $middleware->alias(['permission' => \App\Http\Middleware\RequirePermission::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn ($request, $e) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Cần đăng nhập.', 'data' => null, 'errors' => null], 401);
            }
        });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null, 'errors' => null], 403);
            }
        });
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Dữ liệu yêu cầu không hợp lệ.', 'data' => null, 'errors' => $e->errors()], 422);
            }
        });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy dữ liệu.', 'data' => null, 'errors' => null], 404);
            }
        });
        $exceptions->render(function (\Illuminate\Database\QueryException $e, \Illuminate\Http\Request $request) {
            if (in_array((string) $e->getCode(), ['23000', '23505'])) {
                $message = 'Dữ liệu trùng hoặc còn liên kết với dữ liệu khác.';
                if ($request->is('api/*')) {
                    return response()->json(['success' => false, 'message' => $message, 'data' => null, 'errors' => ['database' => [$message]]], 409);
                }
                return back()->withErrors(['database' => $message])->withInput();
            }
        });
    })->create();
