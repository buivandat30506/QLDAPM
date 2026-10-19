<?php

namespace App\Http\Controllers\Api\Passenger;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Passenger\CreateBaggageTagRequest;
use App\Http\Requests\Passenger\TrackBaggageEventRequest;
use App\Services\Passenger\BaggageService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class BaggageController extends BaseApiController
{
    public function __construct(
        protected BaggageService $baggageService
    ) {}

    /**
     * Tạo thẻ hành lý mới tại quầy thủ tục
     */
    public function store(CreateBaggageTagRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $flight = \App\Models\OperationalFlight::findOrFail($validated['flight_id']);
        \Illuminate\Support\Facades\Gate::authorize('baggage.write', [['flight' => $flight->id, 'airport' => $flight->route->origin_airport_code]]);
        $tag = $this->baggageService->createBaggageTag(
            $validated['flight_id'],
            $validated['passenger_id'],
            (float)$validated['weight_kg'],
            $validated['location'] ?? 'Check-in Counter',
            $validated['custody_unit'] ?? 'Ground Handling Unit'
        );

        return $this->successResponse($tag, 'Cấp thẻ hành lý thành công', Response::HTTP_CREATED);
    }

    /**
     * Tra cứu hành trình kiện hành lý theo mã thẻ
     */
    public function show(string $tagCode): JsonResponse
    {
        $baggage = $this->baggageService->getBaggageTracking($tagCode);
        abort_unless(app(\App\Shared\Security\PassengerAccess::class)->allows(request()->user(), 'baggage.read', $baggage->passenger, $baggage->flight), 404);
        \App\Services\Core\AuditService::record('baggage_tags', $baggage->tag_code, 'SENSITIVE_READ');
        return $this->successResponse($baggage, 'Tra cứu hành lý thành công');
    }

    /**
     * Ghi nhận sự kiện quét thẻ hành lý (Checkpoint scan)
     */
    public function recordEvent(TrackBaggageEventRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tag = \App\Models\BaggageTag::with('flight.route')->findOrFail($validated['tag_code']);
        \Illuminate\Support\Facades\Gate::authorize('baggage.write', [['flight' => $tag->flight_id, 'airport' => $tag->flight->route->origin_airport_code]]);
        $event = $this->baggageService->recordTrackingEvent(
            $validated['tag_code'],
            $validated['event_type'],
            $validated['location'],
            $validated['custody_unit']
        );

        return $this->successResponse($event, 'Ghi nhận sự kiện hành lý thành công', Response::HTTP_CREATED);
    }
}
