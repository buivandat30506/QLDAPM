<?php

namespace App\Http\Controllers;

use App\Modules\Shared\Access;
use App\Support\InfrastructureCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InfrastructureScheduleController extends Controller
{
    public function store(Request $request)
    {
        Access::role($request->user(), 'operations');
        $data = $request->validate(['resource_id' => 'required|integer|min:1']);
        $resource = DB::connection('ops')->table('airport_resources')->find($data['resource_id']) ?? abort(404);
        abort_unless(in_array($resource->type, InfrastructureCatalog::SCHEDULED_TYPES, true), 422, 'Loại này chưa có quy tắc tạo lịch sử dụng.');
        if ($resource->infrastructure_id) {
            $record = DB::connection('ops')->table('airport_infrastructure')->find($resource->infrastructure_id);
            abort_unless($record && $record->active && $record->airport_code === $resource->airport_code && $record->type_code === $resource->type, 409, 'Tham chiếu hạ tầng không còn phù hợp.');
        }

        return app(WorkspaceController::class)->dispatch($request, 'allocate');
    }
}
