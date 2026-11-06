<?php

namespace App\Services\Passenger;

use App\Models\BaggageTag;
use App\Models\BaggageTrackingEvent;
use App\Models\OperationalFlight;
use App\Models\OrderItem;
use App\Models\PassengerProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BaggageService
{
    /**
     * Tạo mã thẻ hành lý 10 ký tự chuẩn IATA (2 ký tự hãng/mã + 8 số ngẫu nhiên)
     */
    public function generateTagCode(string $prefix = 'VN'): string
    {
        $prefix = strtoupper(substr($prefix, 0, 2));

        for ($i = 0; $i < 10; $i++) {
            $code = $prefix . str_pad((string)random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);
            if (!BaggageTag::where('tag_code', $code)->exists()) {
                return $code;
            }
        }

        return $prefix . substr(uniqid(), -8);
    }

    /**
     * Cấp thẻ hành lý ký gửi (Baggage Tag) tại quầy check-in sân bay
     *
     * @throws ValidationException
     */
    public function createBaggageTag(
        string $flightId,
        string $passengerId,
        float $weightKg,
        string $location = 'Check-in Counter',
        string $custodyUnit = 'Ground Handling Staff'
    ): BaggageTag {
        return DB::transaction(function () use ($flightId, $passengerId, $weightKg, $location, $custodyUnit) {
            $flight = OperationalFlight::findOrFail($flightId);
            $passenger = PassengerProfile::findOrFail($passengerId);

            // Kiểm tra hành khách có vé trên chuyến bay
            $hasTicket = OrderItem::where('flight_id', $flight->id)
                ->where('passenger_id', $passenger->id)
                ->whereHas('order', fn ($q) => $q->whereIn('order_status', ['HOLDING', 'CONFIRMED']))
                ->exists();

            if (!$hasTicket) {
                throw ValidationException::withMessages([
                    'passenger_id' => ["Hành khách {$passenger->full_name} không có vé trên chuyến bay {$flight->flight_number}."],
                ]);
            }

            if ($weightKg <= 0 || $weightKg > 50) {
                throw ValidationException::withMessages([
                    'weight_kg' => ['Trọng lượng kiện hành lý phải từ 0.1kg đến 50.0kg.'],
                ]);
            }

            // Tạo thẻ hành lý
            $tagCode = $this->generateTagCode(substr($flight->flight_number, 0, 2));

            $baggageTag = BaggageTag::create([
                'tag_code'     => $tagCode,
                'flight_id'    => $flight->id,
                'passenger_id' => $passenger->id,
                'weight_kg'    => $weightKg,
                'status'       => BaggageTag::STATUS_CHECKED_IN,
            ]);

            // Ghi nhận sự kiện quét thẻ đầu tiên: CHECK_IN
            BaggageTrackingEvent::create([
                'tag_code'      => $baggageTag->tag_code,
                'event_type'    => BaggageTrackingEvent::EVENT_CHECK_IN,
                'scan_time_utc' => Carbon::now('UTC'),
                'location'      => $location,
                'custody_unit'  => $custodyUnit,
            ]);

            return $baggageTag->load(['trackingEvents', 'passenger', 'flight']);
        });
    }

    /**
     * Ghi nhận sự kiện quét thẻ hành lý (Checkpoint scan event)
     *
     * @throws ValidationException
     */
    public function recordTrackingEvent(
        string $tagCode,
        string $eventType,
        string $location,
        string $custodyUnit
    ): BaggageTrackingEvent {
        return DB::transaction(function () use ($tagCode, $eventType, $location, $custodyUnit) {
            $tag = BaggageTag::where('tag_code', strtoupper($tagCode))
                ->lockForUpdate()
                ->firstOrFail();

            // Cập nhật trạng thái tổng quan của thẻ hành lý tương ứng với sự kiện
            $statusMap = [
                BaggageTrackingEvent::EVENT_CHECK_IN           => BaggageTag::STATUS_CHECKED_IN,
                BaggageTrackingEvent::EVENT_LOADED_TO_AIRCRAFT => BaggageTag::STATUS_LOADED,
                BaggageTrackingEvent::EVENT_UNLOADED           => BaggageTag::STATUS_TRANSIT,
                BaggageTrackingEvent::EVENT_CLAIMED            => BaggageTag::STATUS_DELIVERED,
            ];

            if (isset($statusMap[$eventType])) {
                $tag->update(['status' => $statusMap[$eventType]]);
            }

            // Ghi nhận sự kiện kiểm soát
            return BaggageTrackingEvent::create([
                'tag_code'      => $tag->tag_code,
                'event_type'    => $eventType,
                'scan_time_utc' => Carbon::now('UTC'),
                'location'      => $location,
                'custody_unit'  => $custodyUnit,
            ]);
        });
    }

    /**
     * Tra cứu lịch trình di chuyển của kiện hành lý theo mã thẻ
     */
    public function getBaggageTracking(string $tagCode): BaggageTag
    {
        return BaggageTag::where('tag_code', strtoupper($tagCode))
            ->with(['passenger', 'flight.route', 'trackingEvents'])
            ->firstOrFail();
    }
}
