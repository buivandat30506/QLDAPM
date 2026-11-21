<?php

namespace App\Modules\Finance;

use App\Models\User;
use App\Modules\Booking\CommerceRepository;
use App\Modules\Booking\FareRules;
use App\Modules\Flights\FlightCatalog;
use App\Modules\Shared\Access;
use App\Modules\Shared\Audit;
use App\Modules\Shared\Events;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FinanceService
{
    public function requestRefund(User $u, string $id, string $reason): object
    {
        Access::role($u, 'passenger');
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:5|max:255'])->validate();
        $repo = app(CommerceRepository::class);
        $b = $repo->owned($id, $u->id);
        $f = app(FlightCatalog::class)->get($b->flight_id);
        abort_unless(now()->lt(Carbon::parse($f->departure)->subMinutes(30)), 409, 'Đã quá hạn yêu cầu hoàn vé (30 phút trước khởi hành).');

        return DB::transaction(function () use ($repo, $b, $u, $reason) {
            $repo->lockInventory($b->flight_id);
            $b = $repo->locked($b->id);
            if ($b->status === 'REFUND_PENDING') {
                return $b;
            }
            abort_unless(in_array($b->status, ['CONFIRMED', 'CHECKED_IN']), 409, 'Đặt chỗ không đủ điều kiện hoàn.');
            $quote = FareRules::refundQuote($b, app(FlightCatalog::class)->get($b->flight_id));
            abort_unless($quote['allowed'], 409, 'Hạng vé không cho hoàn hoặc đã quá thời hạn hoàn.');
            DB::table('bookings')->where('id', $b->id)->update(['status' => 'REFUND_PENDING', 'seat' => null, 'updated_at' => now()]);
            DB::table('boarding_passes')->where('booking_id', $b->id)->update(['revoked_at' => now()]);
            Events::emit('mysql', 'RefundRequested', ['booking_id' => $b->id, 'amount' => $quote['amount'], 'gross_amount' => $b->amount, 'fee' => $quote['fee'], 'reason' => $reason, 'requested_by' => $u->id, 'automatic' => false]);
            Audit::record('refund.requested', $b->id, $b->status, 'REFUND_PENDING', $u->id);

            return $repo->owned($b->id, $u->id);
        }, 3);
    }

    public function decide(User $u, int $id, bool $approve): object
    {
        Access::role($u, 'finance');
        $db = DB::connection('finance');

        return $db->transaction(function () use ($db, $u, $id, $approve) {
            $r = $db->table('refunds')->where('id', $id)->lockForUpdate()->first() ?? abort(404);
            abort_unless($r->status === 'REQUESTED' && $r->requested_by !== $u->id, 409, 'Cần yêu cầu chờ duyệt và người duyệt độc lập.');
            $payment = $db->table('payments')->where('booking_id', $r->booking_id)->lockForUpdate()->first();
            if ($approve && $payment?->method) {
                abort_unless($payment->status === 'CAPTURED' && $r->payout_details, 409, 'Cần thanh toán đã nhận và tài khoản nhận hoàn tiền trước khi duyệt.');
                $db->table('refunds')->where('id', $id)->update(['status' => 'APPROVED', 'approved_by' => $u->id, 'updated_at' => now()]);
                Audit::record('refund.approved', (string) $id, 'REQUESTED', 'APPROVED', $u->id, 'finance');

                return (object) ['id' => $id, 'status' => 'APPROVED'];
            }
            if ($approve) {
                abort_unless(config('payments.simulator_enabled') && ! app()->environment('production'), 409, 'Khoản thanh toán cũ cần được kiểm tra trước khi hoàn.');
            }
            $status = $approve ? 'REFUNDED' : 'REJECTED';
            $db->table('refunds')->where('id', $id)->update(['status' => $status, 'approved_by' => $u->id, 'updated_at' => now()]);
            if ($approve) {
                $payment = $db->table('payments')->where('booking_id', $r->booking_id)->lockForUpdate()->first();
                abort_unless($payment && $payment->status === 'CAPTURED', 409, 'Thanh toán chưa được capture.');
                $db->table('payments')->where('id', $payment->id)->update(['status' => 'REFUNDED', 'updated_at' => now()]);
                $db->table('ledger_entries')->insertOrIgnore(['booking_id' => $r->booking_id, 'kind' => 'REFUND', 'amount' => -$r->amount, 'created_at' => now()]);
            }
            Events::emit('finance', 'RefundResult', ['booking_id' => $r->booking_id, 'status' => $status]);
            Audit::record('refund.'.$status, (string) $id, 'REQUESTED', $status, $u->id, 'finance');

            return (object) ['id' => $id, 'status' => $status];
        }, 3);
    }

    public function destination(User $u, string $id, array $data): void
    {
        Access::role($u, 'passenger');
        $d = Validator::make($data, ['bank_name' => 'required|string|between:2,100', 'account_name' => 'required|string|between:2,100', 'account_number' => 'required|string|regex:/^[A-Z0-9 ]{6,34}$/'])->validate();
        $repo = app(CommerceRepository::class);
        $b = $repo->owned($id, $u->id);
        DB::transaction(function () use ($u, $b, $d, $repo) {
            $repo->lockInventory($b->flight_id);
            $b = $repo->locked($b->id);
            abort_unless($b->status === 'REFUND_PENDING' && $b->payment_method, 409, 'Đặt chỗ không chờ hoàn tiền qua ngân hàng.');
            ksort($d);
            $json = json_encode($d, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            Events::emit('mysql', 'RefundDestinationReported', ['booking_id' => $b->id, 'encrypted' => Crypt::encryptString($json), 'hash' => hash('sha256', $json), 'actor' => $u->id]);
            Audit::record('refund.destination_reported', $b->id, null, 'ACCOUNT_PROVIDED', $u->id);
        }, 3);
    }

    public function receiveDestination(string $event, array $p): void
    {
        $db = DB::connection('finance');
        $db->transaction(function () use ($db, $event, $p) {
            $r = $db->table('refunds')->where('booking_id', $p['booking_id'])->lockForUpdate()->first() ?? throw new \RuntimeException('Refund not prepared');
            if (! $db->table('inbox')->insertOrIgnore(['id' => $event, 'received_at' => now()])) {
                return;
            }
            if ($r->status === 'REFUNDED' || $r->destination_hash === $p['hash']) {
                return;
            }
            $plain = Crypt::decryptString($p['encrypted']);
            if (! hash_equals(hash('sha256', $plain), $p['hash'])) {
                throw new \RuntimeException('Refund destination integrity failure');
            }
            $db->table('refunds')->where('id', $r->id)->update(['payout_details' => $p['encrypted'], 'destination_hash' => $p['hash'], 'status' => 'REQUESTED', 'approved_by' => null, 'updated_at' => now()]);
            Audit::record('refund.destination_received', (string) $r->id, null, 'ACCOUNT_PROVIDED', $p['actor'], 'finance');
        }, 3);
    }

    public function settleRefund(User $u, int $id, string $reference): void
    {
        Access::role($u, 'finance');
        Validator::make(['reference' => $reference], ['reference' => 'required|string|between:6,120'])->validate();
        $db = DB::connection('finance');
        $db->transaction(function () use ($db, $u, $id, $reference) {
            $r = $db->table('refunds')->where('id', $id)->lockForUpdate()->first() ?? abort(404);
            if ($r->status === 'REFUNDED') {
                abort_unless($r->payout_reference === $reference, 409, 'Khoản hoàn đã có mã chi tiền khác.');

                return;
            }
            abort_unless($r->status === 'APPROVED' && $r->payout_details && $r->approved_by !== $u->id, 409, 'Cần khoản hoàn đã duyệt, tài khoản nhận tiền và người xác nhận chi độc lập.');
            abort_if($db->table('refunds')->where('payout_reference', $reference)->where('id', '!=', $id)->exists(), 409, 'Mã giao dịch chi đã được sử dụng.');
            $payment = $db->table('payments')->where('booking_id', $r->booking_id)->lockForUpdate()->first();
            abort_unless($payment && $payment->status === 'CAPTURED' && $payment->method, 409, 'Khoản thanh toán không đủ điều kiện hoàn.');
            $db->table('refunds')->where('id', $id)->update(['status' => 'REFUNDED', 'payout_reference' => $reference, 'settled_by' => $u->id, 'settled_at' => now(), 'updated_at' => now()]);
            $db->table('payments')->where('id', $payment->id)->update(['status' => 'REFUNDED', 'updated_at' => now()]);
            $db->table('ledger_entries')->insert(['booking_id' => $r->booking_id, 'kind' => 'REFUND', 'amount' => -$r->amount, 'created_at' => now()]);
            Events::emit('finance', 'RefundResult', ['booking_id' => $r->booking_id, 'status' => 'REFUNDED']);
            Audit::record('refund.payout_confirmed', (string) $id, 'APPROVED', 'REFUNDED', $u->id, 'finance');
        }, 3);
    }
}
