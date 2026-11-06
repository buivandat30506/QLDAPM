<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Core\CreateOperationalFlightRequest;
use App\Http\Requests\Core\StoreFlightScheduleRequest;
use App\Http\Requests\Core\UpdateFlightStatusRequest;
use App\Models\Aircraft;
use App\Services\Core\FlightManagementService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FlightController extends BaseApiController
{
    public function __construct(
        protected FlightManagementService $flightManagementService
    ) {}

    /**
     * Danh sách lịch bay mẫu (Flight Schedules - UC-05)
     */
    public function getSchedules(Request $request): JsonResponse
    {
        $schedules = app(\App\Services\Core\ScheduleService::class)->query($request->query())->paginate((int) $request->input('per_page', 25));
        return $this->successResponse($schedules, 'Lấy danh sách lịch bay mẫu thành công');
    }

    /**
     * Tạo mới lịch bay mẫu (UC-05)
     */
    public function storeSchedule(StoreFlightScheduleRequest $request): JsonResponse
    {
        $schedule = $this->flightManagementService->createFlightSchedule($request->validated());
        return $this->successResponse($schedule, 'Tạo mới lịch bay mẫu thành công', Response::HTTP_CREATED);
    }

    /**
     * Tìm kiếm và liệt kê các chuyến bay khai thác (UC-07, UC-08)
     */
    public function index(Request $request): JsonResponse
    {
        $flights = $this->flightManagementService->searchFlights($request->all());
        return $this->successResponse($flights, 'Lấy danh sách chuyến bay thành công');
    }

    /**
     * Khởi tạo chuyến bay khai thác (kiểm tra quay đầu & xung đột tàu bay - UC-06, UC-07)
     */
    public function store(CreateOperationalFlightRequest $request): JsonResponse
    {
        $flight = $this->flightManagementService->createOperationalFlight($request->validated());
        return $this->successResponse($flight, 'Khởi tạo chuyến bay khai thác thành công', Response::HTTP_CREATED);
    }

    /**
     * Chi tiết chuyến bay khai thác
     */
    public function show(string $id): JsonResponse
    {
        $flight = $this->flightManagementService->getFlightById($id);
        return $this->successResponse($flight, 'Lấy chi tiết chuyến bay thành công');
    }

    /**
     * Cập nhật trạng thái vòng đời chuyến bay & cảnh báo chậm chuyến (UC-08, UC-09)
     */
    public function updateStatus(UpdateFlightStatusRequest $request, string $id): JsonResponse
    {
        $flight = $this->flightManagementService->updateFlightStatus($id, $request->validated());
        return $this->successResponse($flight, 'Cập nhật trạng thái chuyến bay thành công');
    }

    /**
     * Kiểm tra nhanh xung đột lịch tàu bay trước khi xếp lịch (UC-06, BR-005)
     */
    public function checkConflict(Request $request): JsonResponse
    {
        $request->validate([
            'aircraft_tail'     => 'required|string|exists:aircrafts,tail_number',
            'scheduled_dep_utc' => 'required|date',
            'scheduled_arr_utc' => 'required|date|after:scheduled_dep_utc',
        ]);

        $aircraft = Aircraft::with('model')->findOrFail($request->input('aircraft_tail'));
        $turnaround = $aircraft->model->turnaround_time_minutes ?? 45;

        try {
            $this->flightManagementService->validateAircraftScheduleConflict(
                $aircraft->tail_number,
                Carbon::parse($request->input('scheduled_dep_utc'), 'UTC'),
                Carbon::parse($request->input('scheduled_arr_utc'), 'UTC'),
                $turnaround,
                $request->input('ignore_flight_id')
            );

            return $this->successResponse([
                'has_conflict'             => false,
                'turnaround_time_minutes'  => $turnaround,
            ], 'Tàu bay sẵn sàng và không có xung đột lịch bay.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                'Phát hiện xung đột lịch bay của tàu bay!',
                $e->errors(),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }
}
