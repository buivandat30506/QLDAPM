<?php

namespace App\Http\Middleware;

use App\Modules\Shared\Access;
use App\Support\ReleaseScope;
use Closure;
use Illuminate\Http\Request;

final class EnforceReleaseScope
{
    public function handle(Request $r, Closure $next)
    {
        if (! $r->user()) {
            return $next($r);
        }
        if (! $r->user()->hasVerifiedEmail()) {
            // These legacy auth-only routes do not carry the verified middleware.
            abort_if(! $r->isMethod('GET') && $r->is('staff/boarding', 'api/v1/boarding'), 403, 'Thao tác chưa được hỗ trợ trong phiên bản hiện tại.');
            return $next($r);
        }
        if ($r->isMethod('GET') || $r->isMethod('HEAD')) {
            if ($page = ReleaseScope::page($r)) {
                [$title, $roles] = $page;
                Access::role($r->user(), ...$roles);

                return $r->expectsJson() || $r->is('api/*')
                    ? response()->json(['title' => $title, 'status' => 'unavailable', 'data' => [], 'message' => 'Chưa có thông tin hiển thị'])
                    : response()->view('scope-empty', compact('title'));
            }
        } else {
            $dispatch = $r->is('workspace/actions/*', 'api/v1/actions/*', 'modules/*/actions/*', 'api/v1/modules/*/actions/*')
                || ($r->route()?->getActionMethod() === 'dispatch');
            if ($dispatch) {
                abort_unless(ReleaseScope::allowsAction($r, (string) $r->route('action')), 403, 'Thao tác chưa được hỗ trợ trong phiên bản hiện tại.');
            }
            if ($r->is('workspace/business/*', 'api/v1/business/*')) {
                abort_unless(in_array($r->route('action'), ['recovery_propose', 'recovery_decide'], true), 403, 'Thao tác chưa được hỗ trợ trong phiên bản hiện tại.');
            }
            abort_if($r->is('workspace/fleet-cabin', 'workspace/ground-equipment/*', 'staff/boarding', 'api/v1/boarding', 'staff/passengers/*'), 403, 'Thao tác chưa được hỗ trợ trong phiên bản hiện tại.');
        }

        return $next($r);
    }
}
