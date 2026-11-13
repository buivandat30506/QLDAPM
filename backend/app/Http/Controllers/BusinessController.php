<?php

namespace App\Http\Controllers;

use App\Modules\Boarding\BoardingOperations;
use App\Modules\Booking\BaggagePurchase;
use App\Modules\Booking\ExchangeService;
use App\Modules\Booking\ReservationEdits;
use App\Modules\Booking\ReservationService;
use App\Modules\Booking\TripDetails;
use App\Modules\Flights\FlightCatalog;
use App\Modules\Operations\PassengerRecovery;
use App\Modules\Shared\Access;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessController extends Controller
{
    public const AREAS = [
        'fare-rules' => ['Điều kiện vé & hành lý', ['data_admin']],
        'bank-statements' => ['Nhập sao kê & đối chiếu', ['finance']],
        'financial-adjustments' => ['Điều chỉnh tài chính', ['finance']],
        'revenue' => ['Báo cáo doanh thu', ['finance', 'analyst', 'admin']],
        'boarding-control' => ['Check-in & danh sách lên tàu', ['airport_agent', 'operations', 'ops_manager']],
        'support-desk' => ['Phân công & hạn hỗ trợ', ['customer_service']],
        'passenger-revisions' => ['Duyệt sửa thông tin khách', ['customer_service']],
        'passenger-recovery' => ['Hành khách bị gián đoạn', ['operations', 'ops_manager']],
    ];

    public function index(Request $r, string $section)
    {
        $meta = self::AREAS[$section] ?? abort(404);
        Access::role($r->user(), ...$meta[1]);
        if ($section !== 'passenger-recovery') {
            return view('scope-empty', ['title' => $meta[0]]);
        }
        $r->validate(['flight' => 'nullable|integer|min:1']);
        $data = ['title' => $meta[0], 'section' => $section];
        $data['flights'] = DB::connection('ops')->table('flights')->where('origin', $r->user()->airport_code)->whereNotIn('status', ['DRAFT', 'PENDING'])->where('departure', '>', now()->subDays(2))->orderBy('departure')->limit(100)->get();
        $id = $r->integer('flight') ?: $data['flights']->first()?->id;
        $data['flight'] = $id ? app(FlightCatalog::class)->get($id) : null;
        if ($data['flight']) {
            Access::airport($r->user(), $data['flight']->origin);
            $data += app(PassengerRecovery::class)->impact($r->user(), $id);
        }

        return $r->expectsJson() ? response()->json($data) : view('business.passenger-recovery', $data);
    }

    public function edit(Request $r, string $id)
    {
        $reservation = app(ReservationService::class)->owned($r->user(), $id);
        $people = DB::table('reservation_passengers')->where('reservation_id', $id)->orderBy('position')->get();
        $person = $r->query('passenger') ? $people->firstWhere('id', $r->query('passenger')) : $people->first();
        abort_unless($person, 404);
        $details = TripDetails::decode($person->details);
        $words = preg_split('/\s+/', trim($person->name), 2);
        $details += ['surname' => $words[0] ?? '', 'given_name' => $words[1] ?? '', 'nationality' => 'VN', 'gender' => 'X', 'date_of_birth' => $person->date_of_birth, 'document_number' => '', 'document_expiry' => ''];
        $contact = TripDetails::decode($reservation->contact_details) + ['name' => $r->user()->name, 'email' => $r->user()->email, 'phone' => $r->user()->phone ?? ''];
        $history = DB::table('passenger_revisions')->where('reservation_id', $id)->orderByDesc('created_at')->limit(30)->get(['id', 'status', 'reason', 'decision_note', 'created_at']);
        $data = compact('reservation', 'people', 'person', 'details', 'contact', 'history') + ['countries' => TripDetails::COUNTRIES, 'version' => ReservationEdits::fingerprint($reservation, $person)];

        return $r->expectsJson() ? response()->json($data) : view('business.reservation-edit', $data);
    }

    public function saveEdit(Request $r, string $id)
    {
        $change = app(ReservationEdits::class)->submit($r->user(), $id, $r->all());

        return $r->expectsJson() ? response()->json(['id' => $change->id, 'status' => $change->status], 201) : redirect('/reservations/'.$id.'/details?passenger='.$change->passenger_id)->with('message', $change->status === 'APPLIED' ? 'Đã cập nhật thông tin; hạn giữ chỗ giữ nguyên.' : 'Đã gửi yêu cầu sửa thông tin để nhân viên duyệt.');
    }

    public function baggage(Request $r, string $id)
    {
        $d = $r->validate(['package' => 'required|regex:/^\d{1,2}:\d{1,12}$/']);
        [$kg,$amount] = array_map('intval', explode(':', $d['package']));
        $b = app(BaggagePurchase::class)->update($r->user(), ['booking_id' => $id, 'kg' => $kg, 'expected_amount' => $amount]);

        return $r->expectsJson() ? response()->json($b) : redirect($b->reservation_id ? '/reservations/'.$b->reservation_id : '/journeys/'.$id)->with('message', 'Đã cập nhật hành lý và tổng tiền trước thanh toán.');
    }

    public function revision(Request $r, string $id)
    {
        Access::role($r->user(), 'customer_service');
        $change = DB::table('passenger_revisions')->find($id) ?? abort(404);
        $before = TripDetails::decode($change->before_data);
        $after = TripDetails::decode($change->after_data);
        unset($change->before_data,$change->after_data);

        return $r->expectsJson() ? response()->json(compact('change', 'before', 'after')) : view('business.passenger-revision', compact('change', 'before', 'after'));
    }

    public function manifest(Request $r, string $id)
    {
        $manifest = app(BoardingOperations::class)->read($r->user(), $id);
        if ($r->query('export') === 'csv') {
            return Csv::download('danh-sach-len-tau-v'.$manifest->version.'.csv', ['Phiên bản', 'Còn khớp dữ liệu', 'Mã vé', 'Họ tên', 'Ghế', 'Hạng', 'Trạng thái'], collect($manifest->data['rows'])->map(fn ($x) => [$manifest->version, $manifest->current ? 'Có' : 'Không', $x['reference'], $x['name'], $x['seat'], $x['fare_class'], $x['status']]));
        }

        return $r->expectsJson() ? response()->json($manifest) : view('business.manifest', compact('manifest'));
    }

    public function recoveryOffer(Request $r, string $id)
    {
        Access::role($r->user(), 'passenger');
        $existing = DB::table('exchange_requests as e')->join('bookings as b', 'b.id', '=', 'e.source_booking_id')->where('e.id', $id)->where('b.user_id', $r->user()->id)->select('e.target_booking_id')->first();
        if ($existing) {
            return redirect('/journeys/'.$existing->target_booking_id);
        }
        $data = app(PassengerRecovery::class)->customer($r->user(), $id);

        return $r->expectsJson() ? response()->json($data) : view('business.recovery-offer', $data);
    }

    public function recoveryExchange(Request $r, string $id)
    {
        Access::role($r->user(), 'passenger');
        $existing = DB::table('exchange_requests as e')->join('bookings as b', 'b.id', '=', 'e.source_booking_id')->where('e.id', $id)->where('b.user_id', $r->user()->id)->select('e.*')->first();
        if ($existing) {
            return $r->expectsJson() ? response()->json($existing) : redirect('/journeys/'.$existing->target_booking_id);
        }
        $data = app(PassengerRecovery::class)->customer($r->user(), $id);
        abort_unless($data['fare']->code === $data['booking']->fare_class, 409, 'Chọn luồng đặt vé mới khi đổi sang hạng khác.');
        $exchange = app(ExchangeService::class)->request($r->user(), ['request_id' => $id, 'booking_id' => $data['booking']->id, 'flight_id' => $data['target']->id, 'reason' => 'Đổi theo phương án đã duyệt '.substr($id, 0, 8)]);

        return $r->expectsJson() ? response()->json($exchange, 201) : redirect('/journeys/'.$exchange->target_booking_id)->with('message', 'Đã gửi yêu cầu đổi theo phương án. Tài chính sẽ duyệt trước khi bạn thanh toán vé mới.');
    }

    public function action(Request $r, string $action)
    {
        abort_unless(in_array($action, ['recovery_propose', 'recovery_decide'], true), 403);
        $d = $r->all();
        if ($action === 'recovery_propose' && $r->filled('choice')) {
            $r->validate(['choice' => 'required|regex:/^\d+:[A-Z0-9_]{1,20}$/']);
            [$d['target_flight_id'], $d['fare_class']] = explode(':', $r->input('choice'));
        }
        if ($action === 'recovery_decide') {
            $r->validate(['id' => 'required|uuid', 'approve' => 'required|boolean']);
        }
        $result = $action === 'recovery_propose'
            ? app(PassengerRecovery::class)->propose($r->user(), $d)
            : app(PassengerRecovery::class)->decide($r->user(), $d['id'], $r->boolean('approve'));

        return $r->expectsJson() ? response()->json($result) : back()->with('message', 'Đã ghi nhận thao tác.');
    }
}
