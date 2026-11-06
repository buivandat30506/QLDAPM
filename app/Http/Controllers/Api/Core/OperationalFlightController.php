<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Core\CreateOperationalFlightRequest;
use App\Services\Core\FlightManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OperationalFlightController extends BaseApiController
{
    public function __construct(
        protected FlightManagementService $flightManagementService
    ) {}

    /**
     * Tìm kiếm và liệt kê các chuyến bay khai thác
     */
    public function index(Request $request): JsonResponse
    {
        $flights = $this->flightManagementService->searchFlights($request->all());
        return $this->successResponse($flights, 'Lấy danh sách chuyến bay thành công');
    }

    /**
     * Tạo mới chuyến bay khai thác (kiểm tra quay đầu và xung đột tàu bay)
     */
    public function store(CreateOperationalFlightRequest $request): JsonResponse
    {
        $flight = $this->flightManagementService->createOperationalFlight($request->validated());
        return $this->successResponse($flight, 'Khởi tạo chuyến bay khai thác thành công', Response::HTTP_CREATED);
    }
}
