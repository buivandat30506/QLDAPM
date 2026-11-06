<?php

namespace App\Services\Passenger;

use App\Models\OperationalFlight;
use App\Models\OrderBooking;
use App\Models\OrderItem;
use App\Models\PassengerProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    /**
     * Thời gian giữ chỗ mặc định theo quy tắc nghiệp vụ BR-011 (15 phút)
     */
    public const HOLD_DURATION_MINUTES = 15;

    /**
     * Tạo mã PNR duy nhất 6 ký tự Alphanumeric (loại bỏ ký tự dễ nhầm lẫn như 0, O, 1, I)
     */
    public function generateUniquePnr(): string
    {
        $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $maxAttempts = 10;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = '';
            for ($c = 0; $c < 6; $c++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }

            if (!OrderBooking::where('order_code', $code)->exists()) {
                return $code;
            }
        }

        // Fallback using Str random
        return strtoupper(Str::random(6));
    }

    /**
     * Tạo đơn đặt chỗ với giao dịch DB và khóa giữ chỗ (Holding lock 15 phút)
     *
     * @param array $payload Bao gồm customer_user_id, flight_id, passengers; giá được tính từ profile cấu hình server-side.
     * @return OrderBooking
     * @throws ValidationException
     */
    public function createBooking(array $payload): OrderBooking
    {
        return DB::transaction(function () use ($payload) {
            // 1. Khóa chuyến bay với SELECT ... FOR UPDATE để bảo vệ tải chỗ đồng thời (ACID)
            $flight = OperationalFlight::where('id', $payload['flight_id'])
                ->lockForUpdate()
                ->with('aircraft.model')
                ->firstOrFail();

            // Start consistent reads only after acquiring the inventory mutex.
            // Under MySQL REPEATABLE READ an earlier customer read creates a stale snapshot.
            $customer = User::findOrFail($payload['customer_user_id']);

            if (!in_array($flight->flight_status, ['SCHEDULED'])) {
                throw ValidationException::withMessages([
                    'flight_id' => ["Chuyến bay {$flight->flight_number} không ở trạng thái mở bán (hiện tại: {$flight->flight_status})."],
                ]);
            }

            // Freeze capacity on first use for legacy flights. Configuration versions are immutable.
            if ($flight->seat_capacity_snapshot === null) {
                $flight->update(['aircraft_configuration_id' => $flight->aircraft->current_configuration_id,
                    'seat_capacity_snapshot' => $flight->aircraft->configuration?->sellable_capacity ?? $flight->aircraft->model->seat_capacity]);
            }
            $seatCapacity = $flight->seat_capacity_snapshot;

            // 2. Tính số ghế đã được giữ hoặc đã xác nhận (loại bỏ đơn hết hạn)
            $activeBookedSeats = OrderItem::where('flight_id', $flight->id)
                ->where('item_type', OrderItem::TYPE_SEAT)
                ->whereHas('order', function ($query) {
                    $query->where(function ($q) {
                        $q->where('order_status', OrderBooking::STATUS_CONFIRMED)
                          ->orWhere(function ($holdingQ) {
                              $holdingQ->where('order_status', OrderBooking::STATUS_HOLDING)
                                       ->where('hold_expires_at', '>', Carbon::now('UTC'));
                          });
                    });
                })
                ->count();

            $passengersData = $payload['passengers'];
            $requestedSeats = count($passengersData);

            if (($activeBookedSeats + $requestedSeats) > $seatCapacity) {
                $remaining = max(0, $seatCapacity - $activeBookedSeats);
                throw ValidationException::withMessages([
                    'flight_id' => ["Chuyến bay không đủ chỗ trống. Số chỗ còn lại: {$remaining}, số chỗ yêu cầu: {$requestedSeats}."],
                ]);
            }

            // 3. Khởi tạo mã PNR và thời hạn giữ chỗ BR-011
            $orderCode     = $this->generateUniquePnr();
            $holdExpiresAt = Carbon::now('UTC')->addMinutes(self::HOLD_DURATION_MINUTES);

            $orderBooking = OrderBooking::create([
                'order_code'       => $orderCode,
                'customer_user_id' => $customer->id,
                'total_amount'     => 0.00,
                'order_status'     => OrderBooking::STATUS_HOLDING,
                'hold_expires_at'  => $holdExpiresAt,
                'currency'         => config('booking.currency', 'VND'),
                'pricing_profile'  => config('booking.pricing_profile', 'local-v1'),
            ]);

            $totalMinor = 0;
            $baseFareMinor = (int) config('booking.base_fare_minor');
            $checkedBaggageMinor = (int) config('booking.checked_baggage_minor');
            if ($baseFareMinor < 0 || $checkedBaggageMinor < 0) {
                throw new \LogicException('Booking pricing profile contains a negative amount.');
            }

            // 4. Lưu thông tin hồ sơ hành khách và chi tiết đơn hàng (OrderItems)
            foreach ($passengersData as $pData) {
                $existing = PassengerProfile::where('id_card_passport', strtoupper(trim($pData['id_card_passport'])))->first();
                if ($existing && ! app(\App\Shared\Security\PassengerAccess::class)->allows($customer, 'passenger.write', $existing)) {
                    throw ValidationException::withMessages(['passengers' => ['Thông tin hành khách cần được chủ hồ sơ xác nhận qua kênh hỗ trợ.']]);
                }
                // Tạo hoặc cập nhật hồ sơ hành khách theo số CCCD/Passport
                $passenger = PassengerProfile::updateOrCreate(
                    ['id_card_passport' => strtoupper(trim($pData['id_card_passport']))],
                    [
                        'user_id'   => $existing?->user_id,
                        'full_name' => strtoupper(trim($pData['full_name'])),
                        'dob'       => $pData['dob'],
                        'gender'    => $pData['gender'],
                        'phone'     => $pData['phone'] ?? null,
                        'email'     => $pData['email'] ?? null,
                    ]
                );

                // Ghế máy bay
                OrderItem::create([
                    'order_id'     => $orderBooking->id,
                    'flight_id'    => $flight->id,
                    'passenger_id' => $passenger->id,
                    'item_type'    => OrderItem::TYPE_SEAT,
                    'amount'       => self::minorToDecimal($baseFareMinor),
                ]);
                $totalMinor += $baseFareMinor;

                // Hành lý ký gửi thêm (nếu có)
                if (!empty($pData['checked_baggage'])) {
                    OrderItem::create([
                        'order_id'     => $orderBooking->id,
                        'flight_id'    => $flight->id,
                        'passenger_id' => $passenger->id,
                        'item_type'    => OrderItem::TYPE_BAGGAGE_EXTRA,
                        'amount'       => self::minorToDecimal($checkedBaggageMinor),
                    ]);
                    $totalMinor += $checkedBaggageMinor;
                }
            }

            // Cập nhật tổng tiền đơn hàng
            $orderBooking->update(['total_amount' => self::minorToDecimal($totalMinor)]);

            return $orderBooking->load(['orderItems.passenger', 'orderItems.flight.route']);
        });
    }

    private static function minorToDecimal(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Xác nhận đơn đặt chỗ (Thanh toán thành công chuyển từ HOLDING -> CONFIRMED)
     *
     * @throws ValidationException
     */
    public function confirmBooking(string $orderCode): OrderBooking
    {
        return DB::transaction(function () use ($orderCode) {
            $booking = OrderBooking::where('order_code', strtoupper($orderCode))
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->order_status === OrderBooking::STATUS_CONFIRMED) {
                return $booking;
            }

            if ($booking->order_status !== OrderBooking::STATUS_HOLDING) {
                throw ValidationException::withMessages([
                    'order_code' => ["Đơn đặt chỗ {$orderCode} đang ở trạng thái [{$booking->order_status}], không thể xác nhận."],
                ]);
            }

            // Kiểm tra thời hạn 15 phút (BR-011)
            if ($booking->isExpired()) {
                $booking->update(['order_status' => OrderBooking::STATUS_CANCELLED]);
                throw ValidationException::withMessages([
                    'order_code' => ["Đơn đặt chỗ {$orderCode} đã quá hạn giữ chỗ 15 phút và đã bị huỷ."],
                ]);
            }

            $booking->update([
                'order_status' => OrderBooking::STATUS_CONFIRMED,
            ]);

            return $booking->fresh(['orderItems.passenger', 'orderItems.flight']);
        });
    }

    /**
     * Lấy thông tin đơn đặt vé bằng mã PNR
     */
    public function getBookingByPnr(string $pnr): OrderBooking
    {
        return OrderBooking::where('order_code', strtoupper($pnr))
            ->with(['customer', 'orderItems.passenger', 'orderItems.flight.route.originAirport', 'orderItems.flight.route.destAirport'])
            ->firstOrFail();
    }
}
