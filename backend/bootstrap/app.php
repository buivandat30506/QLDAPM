<?php

use App\Http\Middleware\AccountAccess;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [AccountAccess::class]);
        $middleware->validateCsrfTokens(except: ['payments/momo/ipn']);
        $middleware->alias(['role' => RequireRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error, \Illuminate\Http\Request $request) {
            if ($request->isMethod('POST') && !$request->expectsJson() && $request->is('workspace/planning/*','workspace/catalog/routes','workspace/catalog/routes/*') && in_array($error->getStatusCode(),[409,422],true)) {
                return back()->withErrors(['schedule'=>$error->getMessage() ?: 'Thông tin chưa hợp lệ. Vui lòng kiểm tra lại.'])->withInput($request->except('_token'));
            }
        });
        $exceptions->render(function (\Throwable $error, \Illuminate\Http\Request $request) {
            if (! $request->isMethod('POST') || $request->expectsJson()
                || ! $request->is('workspace/infrastructure-allocations', 'workspace/actions/allocate', 'workspace/actions/unallocate')) {
                return null;
            }
            if ($error instanceof \Illuminate\Validation\ValidationException) {
                $messages = $error->errors();
            } elseif ($error instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface && in_array($error->getStatusCode(), [409, 422], true)) {
                $messages = ['resource_action'=>$error->getMessage() ?: 'Chưa thực hiện được thao tác. Vui lòng kiểm tra lại thông tin.'];
            } else {
                return null;
            }
            $action = $request->is('workspace/actions/unallocate') ? 'unallocate' : 'allocate';
            return redirect('/workspace/resources#resource-action-'.$action)
                ->withErrors($messages)->withInput(array_merge($request->except('_token'), ['_resource_action'=>$action]));
        });
        $exceptions->render(function (\Illuminate\Database\QueryException $error, \Illuminate\Http\Request $request) {
            $m01 = ($request->is('workspace/catalog') && in_array($request->query('flow', 'airports'), ['airports', 'infrastructure'], true))
                || $request->is('workspace/catalog/airports/*', 'workspace/catalog/infrastructure/*');
            if ($request->isMethod('GET') && $m01) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Chưa tải được dữ liệu sân bay. Vui lòng thử lại.'], 503)
                    : response()->view('m01.load-error', [], 503);
            }
        });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $error, \Illuminate\Http\Request $request) {
            if ($request->isMethod('GET') && $request->is('workspace/catalog/airports/*', 'workspace/catalog/infrastructure/*') && ! $request->expectsJson()) {
                return response()->view('m01.not-found', [], 404);
            }
        });
    })->create();
