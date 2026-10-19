<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->status === 'ACTIVE', 403, 'Tài khoản không khả dụng.');

        return $next($request);
    }
}
