<?php

namespace App\Services\Core;

use App\Models\Aircraft;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MaintenanceReadiness
{
    // Read synchronously at every planning/departure commit; scheduler alerts are not the safety gate.
    public function issues(Aircraft $aircraft, ?Carbon $at = null, ?int $ignorePlan = null): array
    {
        $at ??= now('UTC'); $issues = [];
        foreach (DB::table('maintenance_plans')->where('aircraft_tail', $aircraft->tail_number)->where('status', 'ACTIVE')->when($ignorePlan, fn ($q) => $q->where('id', '!=', $ignorePlan))->get() as $plan) {
            if ($this->due($plan, $aircraft, $at)) { $issues[] = 'Bảo dưỡng đến/quá hạn: '.$plan->plan_code; }
        }
        $orders = DB::table('maintenance_orders')->where('aircraft_tail', $aircraft->tail_number)->where('status', '!=', 'RELEASED')
            ->when($ignorePlan, fn ($q) => $q->where(fn ($w) => $w->whereNull('plan_id')->orWhere('plan_id', '!=', $ignorePlan)))
            ->where(fn ($q) => $q->where('due_at_utc', '<=', $at)->orWhereIn('status', ['WAITING_MATERIALS', 'IN_PROGRESS', 'COMPLETED', 'APPROVED']));
        foreach ($orders->get(['order_code']) as $order) { $issues[] = 'Lệnh quá hạn hoặc chưa giải phóng: '.$order->order_code; }
        return $issues;
    }

    public function due(object $plan, Aircraft $aircraft, ?Carbon $at = null): bool
    {
        $at ??= now('UTC');
        return Carbon::parse($plan->due_at_utc, 'UTC')->lte($at)
            || ($plan->trigger_type === 'HOURS' && ($aircraft->total_flight_hours === null || bccomp((string) $aircraft->total_flight_hours, (string) $plan->due_hours, 2) >= 0))
            || ($plan->trigger_type === 'CYCLES' && ($aircraft->total_flight_cycles === null || $aircraft->total_flight_cycles >= $plan->due_cycles))
            || ($plan->trigger_type === 'CONDITION' && $plan->condition_active);
    }
}
