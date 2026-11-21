<?php

namespace App\Http\Controllers;

use App\Modules\Shared\Access;
use App\Support\ReleaseScope;
use Illuminate\Http\Request;

/** Legacy aggregate URLs remain available without loading retired module tables. */
class ModuleController extends Controller
{
    private const ROLES = [
        'm1' => ['admin', 'data_admin', 'operations', 'ops_manager', 'fleet_manager', 'maintenance', 'crew_scheduler', 'ground_agent', 'integration_admin'],
        'm2' => ['admin', 'passenger', 'airport_agent', 'customer_service', 'operations'],
        'm3' => ['admin', 'analyst', 'data_admin', 'operations', 'ops_manager', 'maintenance', 'integration_admin'],
    ];

    public function index(Request $request, string $module)
    {
        Access::role($request->user(), ...(self::ROLES[$module] ?? abort(404)));
        $title = ['m1' => 'Quản lý cốt lõi', 'm2' => 'Đặt chỗ và vé', 'm3' => 'Cảnh báo thông minh'][$module];

        return $request->expectsJson() ? response()->json(['title' => $title, 'data' => [], 'status' => 'unavailable']) : view('scope-empty', compact('title'));
    }

    public function action(Request $request, string $module, string $action)
    {
        Access::role($request->user(), ...(self::ROLES[$module] ?? abort(404)));
        abort_unless(ReleaseScope::allowsAction($request, $action), 403);

        return app(WorkspaceController::class)->dispatch($request, $action);
    }
}
