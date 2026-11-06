<?php

namespace App\Http\Controllers\Api\Passenger;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Passenger\CheckInRequest;
use App\Services\Passenger\CheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckInController extends BaseApiController
{
    public function __construct(
        protected CheckInService $checkInService
    ) {}

    /**
     * Thực hiện Check-in trực tuyến và nhận Boarding Pass Token
     */
    public function checkIn(CheckInRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $passenger = \App\Models\PassengerProfile::findOrFail($validated['passenger_id']);
        $flight = \App\Models\OperationalFlight::findOrFail($validated['flight_id']);
        abort_unless(app(\App\Shared\Security\PassengerAccess::class)->allows($request->user(), 'checkin.write', $passenger, $flight), 404);
        $record = $this->checkInService->processCheckIn(
            $validated['flight_id'],
            $validated['passenger_id'],
            $validated['seat_number']
        );

        return $this->successResponse(
            $record,
            'Check-in trực tuyến thành công. Thẻ lên máy bay đã được kích hoạt.',
            Response::HTTP_CREATED
        );
    }

    /**
     * Quét thẻ lên máy bay tại cửa khởi hành (Boarding Gate verification)
     */
    public function boardPassenger(Request $request): JsonResponse
    {
        $request->validate(['token_hash' => 'required|string|size:64', 'flight_id' => 'required|uuid|exists:operational_flights,id']);
        $flight = \App\Models\OperationalFlight::findOrFail($request->input('flight_id'));
        \Illuminate\Support\Facades\Gate::authorize('boarding.write', [['flight' => $flight->id, 'airport' => $flight->route->origin_airport_code]]);
        // Fail closed until the G3 opaque-token issue/reissue contract replaces legacy hashes.
        abort(409, 'Cần nâng cấp token boarding theo contract G3 trước khi nhận lượt quét.');

        $token = $this->checkInService->verifyAndBoard($request->input('token_hash'));

        return $this->successResponse(
            $token,
            'Hành khách đã qua cửa lên máy bay thành công.'
        );
    }
}
