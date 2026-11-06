<?php

namespace App\Http\Controllers\Api\Passenger;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Passenger\ConfirmBookingRequest;
use App\Http\Requests\Passenger\CreateBookingRequest;
use App\Services\Passenger\BookingService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class BookingController extends BaseApiController
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    /**
     * Tạo đơn đặt chỗ mới (Áp dụng transaction và khóa giữ chỗ 15 phút - BR-011)
     */
    public function store(CreateBookingRequest $request): JsonResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('booking.write', [['self' => $request->validated('customer_user_id')]]);
        $booking = $this->bookingService->createBooking($request->validated());

        return $this->successResponse(
            $booking,
            'Giữ chỗ thành công. Vui lòng thanh toán trong vòng 15 phút.',
            Response::HTTP_CREATED
        );
    }

    /**
     * Tra cứu thông tin đặt chỗ theo mã PNR (6 ký tự)
     */
    public function show(string $pnr): JsonResponse
    {
        $booking = $this->bookingService->getBookingByPnr($pnr);
        abort_unless(\Illuminate\Support\Facades\Gate::allows('booking.read', [['self' => $booking->customer_user_id]]), 404);
        \App\Services\Core\AuditService::record('order_bookings', $booking->id, 'SENSITIVE_READ');

        return $this->successResponse(
            $booking,
            'Lấy thông tin đặt chỗ thành công'
        );
    }

    /**
     * Xác nhận thanh toán đơn đặt vé (HOLDING -> CONFIRMED)
     */
    public function confirm(ConfirmBookingRequest $request): JsonResponse
    {
        // A browser confirmation is not evidence that money was received.
        abort(409, 'Cần kết quả thanh toán đã xác minh. Luồng payment adapter sẽ được triển khai ở G3.');
    }
}
