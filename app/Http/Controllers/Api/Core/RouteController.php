<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Core\StoreRouteRequest;
use App\Http\Requests\Core\UpdateRouteRequest;
use App\Services\Core\RouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RouteController extends BaseApiController
{
    public function __construct(
        protected RouteService $routeService
    ) {}

    /**
     * Danh sách các đường bay (UC-04)
     */
    public function index(Request $request): JsonResponse
    {
        $routes = $this->routeService->query($request->query())->paginate((int) $request->input('per_page', 25));
        return $this->successResponse($routes, 'Lấy danh sách tuyến bay thành công');
    }

    /**
     * Chi tiết đường bay
     */
    public function show(int $id): JsonResponse
    {
        $route = $this->routeService->getRouteById($id);
        return $this->successResponse($route, 'Lấy chi tiết tuyến bay thành công');
    }

    /**
     * Tạo mới tuyến bay (UC-04)
     */
    public function store(StoreRouteRequest $request): JsonResponse
    {
        $route = $this->routeService->createRoute($request->validated());
        return $this->successResponse($route, 'Tạo mới tuyến bay thành công', Response::HTTP_CREATED);
    }

    /**
     * Cập nhật tuyến bay
     */
    public function update(UpdateRouteRequest $request, int $id): JsonResponse
    {
        $route = $this->routeService->updateRoute($id, $request->validated());
        return $this->successResponse($route, 'Cập nhật tuyến bay thành công');
    }

    /**
     * Xóa tuyến bay
     */
    public function destroy(int $id): JsonResponse
    {
        $this->routeService->deleteRoute($id);
        return $this->successResponse(null, 'Xóa tuyến bay thành công');
    }
}
