<?php

namespace App\Services\Passenger;

use App\Models\BoardingPassToken;
use App\Models\CheckinRecord;
use App\Models\OperationalFlight;
use App\Models\OrderItem;
use App\Models\PassengerProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInService
{
    /**
     * Thực hiện thủ tục Check-in, chọn ghế và phát hành Boarding Pass Token
     *
     * @param string $flightId
     * @param string $passengerId
     * @param string $seatNumber Ví dụ: '12A', '15F'
     * @return CheckinRecord
     * @throws ValidationException
     */
    public function processCheckIn(string $flightId, string $passengerId, string $seatNumber): CheckinRecord
    {
        $seatNumber = strtoupper(trim($seatNumber));

        return DB::transaction(function () use ($flightId, $passengerId, $seatNumber) {
            // 1. Kiểm tra tồn tại và khóa chuyến bay
            $flight = OperationalFlight::where('id', $flightId)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($flight->flight_status, ['CANCELLED', 'DEPARTED', 'ARRIVED'])) {
                throw ValidationException::withMessages([
                    'flight_id' => ["Không thể làm thủ tục: Chuyến bay đang ở trạng thái [{$flight->flight_status}]."],
                ]);
            }

            // Kiểm tra khung thời gian check-in (từ 24h trước giờ bay đến 45 phút trước giờ bay)
            $now = Carbon::now('UTC');
            $depTime = Carbon::parse($flight->scheduled_dep_utc);
            $checkInOpens = $depTime->copy()->subHours(24);
            $checkInCloses = $depTime->copy()->subMinutes(45);

            if ($now->isBefore($checkInOpens)) {
                throw ValidationException::withMessages([
                    'flight_id' => ["Quầy check-in trực tuyến chỉ mở trước giờ bay 24 tiếng (mở lúc: {$checkInOpens->toDateTimeString()} UTC)."],
                ]);
            }

            if ($now->isAfter($checkInCloses)) {
                throw ValidationException::withMessages([
                    'flight_id' => ["Quầy check-in trực tuyến đã đóng trước giờ bay 45 phút."],
                ]);
            }

            // 2. Kiểm tra hành khách có vé xác nhận trên chuyến bay này không
            $passenger = PassengerProfile::findOrFail($passengerId);

            $hasConfirmedTicket = OrderItem::where('flight_id', $flight->id)
                ->where('passenger_id', $passenger->id)
                ->where('item_type', OrderItem::TYPE_SEAT)
                ->whereHas('order', fn ($q) => $q->where('order_status', 'CONFIRMED'))
                ->exists();

            if (!$hasConfirmedTicket) {
                throw ValidationException::withMessages([
                    'passenger_id' => ["Hành khách {$passenger->full_name} chưa có vé máy bay ở trạng thái CONFIRMED trên chuyến bay này."],
                ]);
            }

            // 3. Kiểm tra xem hành khách đã check-in chuyến này chưa
            $alreadyCheckedIn = CheckinRecord::where('flight_id', $flight->id)
                ->where('passenger_id', $passenger->id)
                ->exists();

            if ($alreadyCheckedIn) {
                throw ValidationException::withMessages([
                    'passenger_id' => ["Hành khách này đã hoàn thành thủ tục check-in trên chuyến bay."],
                ]);
            }

            // 4. Kiểm tra ghế đã có người chọn chưa (Chống Duplicate Seat)
            if ($flight->configuration) {
                $seat = collect($flight->configuration->seats)->firstWhere('label', $seatNumber);
                if (! $seat || ! $seat['enabled']) {
                    throw ValidationException::withMessages(['seat_number' => ['Ghế không tồn tại hoặc đang bị khóa trong cấu hình của chuyến.']]);
                }
            } else {
                throw ValidationException::withMessages(['seat_number' => ['Chuyến chưa có sơ đồ ghế đã xác nhận. Cần hoàn tất cấu hình trước khi làm thủ tục.']]);
            }
            $seatOccupied = CheckinRecord::where('flight_id', $flight->id)
                ->where('seat_number', $seatNumber)
                ->lockForUpdate()
                ->exists();

            if ($seatOccupied) {
                throw ValidationException::withMessages([
                    'seat_number' => ["Ghế {$seatNumber} đã được hành khách khác lựa chọn. Vui lòng chọn ghế khác."],
                ]);
            }

            // 5. Tạo bản ghi Check-in
            $checkinRecord = CheckinRecord::create([
                'flight_id'        => $flight->id,
                'passenger_id'     => $passenger->id,
                'seat_number'      => $seatNumber,
                'checkin_time_utc' => Carbon::now('UTC'),
            ]);

            // 6. Phát hành mã Boarding Pass Token an toàn (SHA-256 kèm salt ngẫu nhiên)
            $rawToken = sprintf(
                '%s|%s|%s|%s|%s',
                $checkinRecord->id,
                $flight->id,
                $seatNumber,
                microtime(true),
                bin2hex(random_bytes(16))
            );
            $tokenHash = hash('sha256', $rawToken);

            BoardingPassToken::create([
                'checkin_id'  => $checkinRecord->id,
                'token_hash'  => $tokenHash,
                'is_used'     => false,
                'valid_until' => $depTime->copy()->addHours(2), // Hiệu lực đến 2h sau giờ bay dự kiến
            ]);

            return $checkinRecord->load(['boardingPassToken', 'passenger', 'flight.route']);
        });
    }

    /**
     * Xác thực thẻ lên máy bay tại cửa khởi hành (Boarding Gate Scan)
     *
     * @throws ValidationException
     */
    public function verifyAndBoard(string $tokenHash): BoardingPassToken
    {
        return DB::transaction(function () use ($tokenHash) {
            $token = BoardingPassToken::where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->with(['checkinRecord.passenger', 'checkinRecord.flight'])
                ->firstOrFail();

            if ($token->is_used) {
                throw ValidationException::withMessages([
                    'token_hash' => ["Thẻ lên máy bay đã được sử dụng vào lúc: {$token->boarded_at_utc} UTC."],
                ]);
            }

            if (!$token->isValid()) {
                throw ValidationException::withMessages([
                    'token_hash' => ['Thẻ lên máy bay đã hết hạn hiệu lực.'],
                ]);
            }

            $token->update([
                'is_used'        => true,
                'boarded_at_utc' => Carbon::now('UTC'),
            ]);

            return $token;
        });
    }
}
