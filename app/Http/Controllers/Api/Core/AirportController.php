<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Core\StoreAirportRequest;
use App\Http\Requests\Core\StoreAirportResourceRequest;
use App\Http\Requests\Core\UpdateAirportRequest;
use App\Services\Core\AirportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AirportController extends BaseApiController
{
    public function __construct(
        protected AirportService $airportService
    ) {}

    /**
     * Danh sách tất cả sân bay (UC-01)
     */
    public function index(Request $request): JsonResponse
    {
        $airports = $this->airportService->page($request->query());
        return $this->successResponse($airports, 'Lấy danh sách sân bay thành công');
    }

    /**
     * Thêm mới sân bay (UC-01)
     */
    public function store(StoreAirportRequest $request): JsonResponse
    {
        $airport = $this->airportService->createAirport($request->validated());
        return $this->successResponse($airport, 'Tạo mới sân bay thành công', Response::HTTP_CREATED);
    }

    /**
     * Chi tiết sân bay theo mã IATA (UC-01)
     */
    public function show(string $iataCode): JsonResponse
    {
        $airport = $this->airportService->getAirportByIata($iataCode);
        return $this->successResponse($airport, 'Lấy chi tiết sân bay thành công');
    }

    /**
     * Cập nhật thông tin sân bay (UC-01)
     */
    public function update(UpdateAirportRequest $request, string $iataCode): JsonResponse
    {
        $airport = $this->airportService->updateAirport($iataCode, $request->validated());
        return $this->successResponse($airport, 'Cập nhật sân bay thành công');
    }

    /**
     * Xóa sân bay (UC-01)
     */
    public function destroy(string $iataCode): JsonResponse
    {
        $this->airportService->deleteAirport($iataCode);
        return $this->successResponse(null, 'Xóa sân bay thành công');
    }

    /**
     * Lấy danh sách tài nguyên của sân bay (Terminals, Gates, Runways, Carousels - UC-02)
     */
    public function getResources(string $iataCode, Request $request): JsonResponse
    {
        $resources = $this->airportService->pageResources($iataCode, array_merge($request->query(), ['resource_type'=>$request->query('type', $request->query('resource_type'))]));
        return $this->successResponse($resources, 'Lấy tài nguyên sân bay thành công');
    }

    /**
     * Thêm tài nguyên mới cho sân bay (UC-02)
     */
    public function storeResource(StoreAirportResourceRequest $request, string $iataCode): JsonResponse
    {
        $resource = $this->airportService->createAirportResource($iataCode, $request->validated());
        return $this->successResponse($resource, 'Tạo mới tài nguyên sân bay thành công', Response::HTTP_CREATED);
    }

    /**
     * Xóa tài nguyên sân bay (UC-02)
     */
    public function destroyResource(int $resourceId): JsonResponse
    {
        $this->airportService->deleteAirportResource($resourceId);
        return $this->successResponse(null, 'Xóa tài nguyên sân bay thành công');
    }
}
