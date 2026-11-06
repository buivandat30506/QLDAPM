<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        Gate::authorize($permission);

        return $next($request);
    }
}
