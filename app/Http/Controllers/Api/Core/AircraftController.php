<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Core\StoreAircraftModelRequest;
use App\Http\Requests\Core\StoreAircraftRequest;
use App\Http\Requests\Core\UpdateAircraftRequest;
use App\Services\Core\AircraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AircraftController extends BaseApiController
{
    public function __construct(
        protected AircraftService $aircraftService
    ) {}

    /**
     * Danh sách dòng máy bay (UC-10)
     */
    public function getModels(): JsonResponse
    {
        $models = $this->aircraftService->getAllModels();
        return $this->successResponse($models, 'Lấy danh sách dòng máy bay thành công');
    }

    /**
     * Tạo mới dòng máy bay (UC-10)
     */
    public function storeModel(StoreAircraftModelRequest $request): JsonResponse
    {
        $model = $this->aircraftService->createModel($request->validated());
        return $this->successResponse($model, 'Thêm mới dòng máy bay thành công', Response::HTTP_CREATED);
    }

    /**
     * Danh sách đội tàu bay (UC-10)
     */
    public function index(Request $request): JsonResponse
    {
        $aircrafts = $this->aircraftService->page($request->all());
        return $this->successResponse($aircrafts, 'Lấy danh sách tàu bay thành công');
    }

    /**
     * Thêm mới tàu bay (UC-10)
     */
    public function store(StoreAircraftRequest $request): JsonResponse
    {
        $aircraft = $this->aircraftService->createAircraft($request->validated());
        return $this->successResponse($aircraft, 'Thêm tàu bay vào đội bay thành công', Response::HTTP_CREATED);
    }

    /**
     * Chi tiết tàu bay
     */
    public function show(string $tailNumber): JsonResponse
    {
        $aircraft = $this->aircraftService->getAircraftByTail($tailNumber);
        $aircraft->setAttribute('reconciliation', $this->aircraftService->reconciliation($aircraft));
        return $this->successResponse($aircraft, 'Lấy chi tiết tàu bay thành công');
    }

    /**
     * Cập nhật thông tin tàu bay
     */
    public function update(UpdateAircraftRequest $request, string $tailNumber): JsonResponse
    {
        $aircraft = $this->aircraftService->updateAircraft($tailNumber, $request->validated());
        return $this->successResponse($aircraft, 'Cập nhật thông tin tàu bay thành công');
    }

    /**
     * Xóa tàu bay khỏi đội bay
     */
    public function destroy(string $tailNumber): JsonResponse
    {
        $this->aircraftService->deleteAircraft($tailNumber);
        return $this->successResponse(null, 'Xóa tàu bay thành công');
    }

    /**
     * Danh sách tàu bay cần cảnh báo bảo dưỡng kỹ thuật (UC-11)
     */
    public function maintenanceAlerts(): JsonResponse
    {
        $alerts = $this->aircraftService->getMaintenanceAlerts();
        return $this->successResponse($alerts, 'Lấy danh sách cảnh báo bảo dưỡng thành công');
    }

    public function configurations(string $tailNumber): JsonResponse
    {
        $a = $this->aircraftService->getAircraftByTail($tailNumber);
        return $this->successResponse($a->configurations()->orderByDesc('version')->paginate(10), 'Lịch sử cấu hình bất biến');
    }

    public function configure(\App\Http\Requests\Core\AircraftConfigurationRequest $request, string $tailNumber): JsonResponse
    {
        return $this->successResponse($this->aircraftService->configure($tailNumber, $request->validated()), 'Đã công bố cấu hình', 201);
    }

    public function usage(Request $request, string $tailNumber): JsonResponse
    {
        $a = $this->aircraftService->getAircraftByTail($tailNumber);
        $data = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'source' => 'nullable|in:legacy,OPERATIONS,AODB,FIDS,DEMO']);
        $q = \Illuminate\Support\Facades\DB::table('aircraft_usage_records')->where('aircraft_tail', $a->tail_number);
        if (! empty($data['from'])) { $q->where('created_at', '>=', $data['from'].' 00:00:00'); }
        if (! empty($data['to'])) { $q->where('created_at', '<', \Carbon\Carbon::parse($data['to'], 'UTC')->addDay()); }
        if (! empty($data['source'])) { $q->where('source', $data['source']); }
        $page=$q->orderByDesc('id')->paginate(20)->withQueryString();
        $page->through(function($r){$r->hours=number_format((float)$r->hours,2,'.','');return $r;});
        return $this->successResponse($page, 'Sổ giờ bay/chu kỳ');
    }

    public function receiveUsage(Request $request, string $tailNumber): JsonResponse
    {
        return $this->successResponse($this->aircraftService->receiveUsage($tailNumber, $request->all()), 'Đã tiếp nhận số liệu; bản ghi trùng không cộng lại');
    }
}
