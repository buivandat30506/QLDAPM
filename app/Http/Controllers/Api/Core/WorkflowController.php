<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Aircraft;
use App\Services\Core\AirportService;
use App\Services\Core\CrewService;
use App\Services\Core\FlightEventService;
use App\Services\Core\MaintenanceService;
use App\Services\Core\MasterDataService;
use App\Services\Core\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowController extends BaseApiController
{
    public function resourceUpdate(Request $r, int $id)
    {
        return $this->successResponse(app(AirportService::class)->updateAirportResource($id, $r->all()));
    }

    public function masterIndex(Request $r)
    {
        return $this->successResponse(app(MasterDataService::class)->query($r->query())->paginate((int) $r->input('per_page', 25)));
    }

    public function masterSave(Request $r, ?int $id = null)
    {
        return $this->successResponse(app(MasterDataService::class)->save($r->all(), $id), 'Đã lưu bản nháp.', $id ? 200 : 201);
    }

    public function masterAction(Request $r, int $id, string $action)
    {
        return $this->successResponse(app(MasterDataService::class)->transition($id, $action, $r->all()));
    }

    public function scheduleSave(Request $r, string $id)
    {
        return $this->successResponse(app(ScheduleService::class)->save($r->all(), $id));
    }

    public function scheduleAction(Request $r, string $id, string $action)
    {
        return $this->successResponse(app(ScheduleService::class)->transition($id, $action, $r->all()));
    }

    public function maintenanceIndex()
    {
        return $this->successResponse(\App\Services\Core\MaintenanceAccess::filter(DB::table('maintenance_orders as o')->join('aircrafts as a', 'a.tail_number', '=', 'o.aircraft_tail'))->select('o.*')->paginate(20));
    }

    public function maintenancePlan(Request $r)
    {
        return $this->successResponse(app(MaintenanceService::class)->plan($r->all()), 'Đã lập lịch bảo dưỡng.', 201);
    }

    public function maintenanceAction(Request $r, int $id, string $action)
    {
        return $this->successResponse(app(MaintenanceService::class)->act($id, $action, $r->all()));
    }

    public function eventIndex()
    {
        return $this->successResponse(app(FlightEventService::class)->query()->paginate(25));
    }

    public function eventReceive(Request $r)
    {
        $event = app(FlightEventService::class)->receive($r->all());

        return $event->status === 'FAILED' ? $this->errorResponse($event->message, ['event_id' => [$event->id]], 422) : $this->successResponse($event, $event->message ?? 'Đã nhận.', 202);
    }

    public function resolve(Request $r, string $id)
    {
        return $this->successResponse(app(FlightEventService::class)->resolve($id, $r->all()));
    }

    public function crewApprove(Request $r, string $id)
    {
        return $this->successResponse(app(CrewService::class)->approvePlan($id));
    }

    public function crewConfirm(Request $r, string $id)
    {
        return $this->successResponse(app(CrewService::class)->confirm($id));
    }

    public function crewReplace(Request $r, string $id)
    {
        return $this->successResponse(app(CrewService::class)->replace($id, $r->all()), 'Đã thay thế; cần duyệt và xác nhận lại.', 201);
    }

    public function history(Request $r)
    {
        return $this->successResponse(app(\App\Services\Administration\AuditQuery::class)->page($r->query()));
    }

    public function versions(Request $r)
    {
        $r->validate(['entity_type' => 'required|in:flight_schedules,master_data_records', 'entity_id' => 'required|string']);

        return $this->successResponse(DB::table('entity_versions')->where('entity_type', $r->entity_type)->where('entity_id', $r->entity_id)->orderBy('version')->get());
    }

    public function usage(string $tail)
    {
        Aircraft::findOrFail($tail);

        return $this->successResponse(DB::table('aircraft_usage_records')->where('aircraft_tail', $tail)->get());
    }
}
