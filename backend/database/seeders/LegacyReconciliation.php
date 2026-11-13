<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Shared\Access;
use App\Modules\Shared\Audit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LegacyReconciliation
{
    public function reconcile(User $u, string $start, string $end): object
    {
        Access::role($u, 'finance');
        Validator::make(compact('start', 'end'), ['start' => 'required|date_format:Y-m-d', 'end' => 'required|date_format:Y-m-d|after_or_equal:start'])->validate();
        $from = Carbon::parse($start)->startOfDay();
        $to = Carbon::parse($end)->endOfDay();
        abort_if($from->diffInDays($to) > 93, 422, 'Kỳ đối soát tối đa 93 ngày.');
        // Read both sources BEFORE persisting results; outage cannot produce an apparently matched run.
        $bookings = DB::table('bookings')->whereBetween('created_at', [$from, $to])->whereNotIn('status', ['HELD', 'EXPIRED'])->get()->keyBy('id');
        $payments = DB::connection('finance')->table('payments')->whereBetween('created_at', [$from, $to])->get()->keyBy('booking_id');
        $tickets = DB::table('tickets')->whereIn('booking_id', $bookings->keys()->merge($payments->keys()))->get()->keyBy('booking_id');
        $db = DB::connection('finance');

        return $db->transaction(function () use ($u, $db, $start, $end, $bookings, $payments, $tickets) {
            $id = $db->table('reconciliation_runs')->insertGetId(['period_start' => $start, 'period_end' => $end, 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($bookings->keys()->merge($payments->keys())->unique() as $bid) {
                $b = $bookings->get($bid);
                $p = $payments->get($bid);
                $ticket = $tickets->get($bid);
                $matched = ($b && ! $p && ! $ticket && $b->status === 'CANCELLED') || ($b && $p && (int) $b->amount === (int) $p->amount && (($p->status === 'CAPTURED' && $ticket && in_array($b->status, ['CONFIRMED', 'CHECKED_IN', 'BOARDED'])) || ($p->status === 'REFUNDED' && $b->status === 'REFUNDED') || ($p->status === 'DECLINED' && $b->status === 'CANCELLED')));
                $db->table('reconciliation_entries')->insert(['run_id' => $id, 'booking_id' => $bid, 'result' => $matched ? 'MATCHED' : 'MISMATCH', 'detail' => json_encode(['booking' => $b?->status, 'payment' => $p?->status, 'booking_amount' => $b?->amount, 'payment_amount' => $p?->amount, 'ticket' => (bool) $ticket])]);
            }
            Audit::record('reconciliation.created', (string) $id, null, 'OPEN', $u->id, 'finance');

            return (object) ['id' => $id];
        });
    }

    public function resolve(User $u, int $id, string $note): object
    {
        Access::role($u, 'finance');
        Validator::make(['note' => $note], ['note' => 'required|string|min:10|max:255'])->validate();
        $db = DB::connection('finance');

        return $db->transaction(function () use ($u, $db, $id, $note) {
            $base = $db->table('reconciliation_entries')->find($id) ?? abort(404);
            $run = $db->table('reconciliation_runs')->where('id', $base->run_id)->lockForUpdate()->first();
            abort_unless($run->status === 'OPEN', 409, 'Kỳ đã khóa.');
            $entry = $db->table('reconciliation_entries')->where('id', $id)->lockForUpdate()->first();
            abort_unless($entry->result === 'MISMATCH', 409, 'Không có sai lệch cần xử lý.');
            $db->table('reconciliation_entries')->where('id', $id)->update(['result' => 'EXCEPTION', 'resolution' => $note, 'resolved_by' => $u->id]);
            Audit::record('reconciliation.exception', (string) $id, 'MISMATCH', $note, $u->id, 'finance');

            return (object) ['id' => $id];
        });
    }

    public function close(User $u, int $id): object
    {
        Access::role($u, 'finance');
        $db = DB::connection('finance');

        return $db->transaction(function () use ($u, $db, $id) {
            $run = $db->table('reconciliation_runs')->where('id', $id)->lockForUpdate()->first() ?? abort(404);
            abort_unless($run->status === 'OPEN' && $run->created_by !== $u->id, 409, 'Cần người khóa kỳ độc lập.');
            abort_if($db->table('reconciliation_entries')->where('run_id', $id)->where('result', 'MISMATCH')->exists(), 409, 'Còn sai lệch chưa xử lý.');
            abort_if($db->table('financial_adjustments')->whereIn('entry_id', $db->table('reconciliation_entries')->where('run_id', $id)->select('id'))->where('status', 'PENDING')->exists(), 409, 'Còn phiếu điều chỉnh chờ duyệt trong kỳ.');
            $db->table('reconciliation_runs')->where('id', $id)->update(['status' => 'CLOSED', 'closed_by' => $u->id, 'updated_at' => now()]);
            Audit::record('reconciliation.closed', (string) $id, 'OPEN', 'CLOSED', $u->id, 'finance');

            return (object) ['id' => $id];
        });
    }
}
