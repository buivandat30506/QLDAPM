<?php

namespace App\Services\Core;

use App\Models\Aircraft;
use App\Models\FlightSchedule;
use App\Models\OperationalFlight;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MaintenanceService
{
    public function plan(array $data): object
    {
        Validator::make($data, [
            'order_code' => 'required|string|max:50|unique:maintenance_orders,order_code',
            'aircraft_tail' => 'required|exists:aircrafts,tail_number', 'task' => 'required|string|max:200',
            'starts_at_utc' => 'required|date', 'ends_at_utc' => 'required|date|after:starts_at_utc',
            'due_at_utc' => 'required|date|after_or_equal:ends_at_utc',
        ])->validate();

        return DB::transaction(function () use ($data) {
            PlanningLock::acquire();
            $aircraft = Aircraft::lockForUpdate()->findOrFail($data['aircraft_tail']);
            MaintenanceAccess::authorize('write', $aircraft->base_airport_code);
            $start = Carbon::parse($data['starts_at_utc'], 'UTC')->utc();
            $end = Carbon::parse($data['ends_at_utc'], 'UTC')->utc();
            if ($aircraft->total_flight_hours === null || $aircraft->total_flight_cycles === null) {
                $this->fail('usage', 'Thiếu số liệu giờ bay/chu kỳ.');
            }
            if (DB::table('maintenance_plans')->where('aircraft_tail', $aircraft->tail_number)->where('status', 'ACTIVE')->where('starts_at_utc', '<', $end)->where('ends_at_utc', '>', $start)->exists()) { $this->fail('starts_at_utc', 'Lịch trùng kế hoạch bảo dưỡng hiện hành.'); }
            if (OperationalFlight::where('aircraft_tail', $aircraft->tail_number)->where('flight_status', '!=', 'CANCELLED')
                ->where('scheduled_dep_utc', '<', $end)->where('scheduled_arr_utc', '>', $start)->exists()
                || DB::table('maintenance_orders')->where('aircraft_tail', $aircraft->tail_number)->where('status', '!=', 'RELEASED')
                    ->where('starts_at_utc', '<', $end)->where('ends_at_utc', '>', $start)->exists()) {
                $this->fail('starts_at_utc', 'Lịch bảo dưỡng trùng chuyến bay hoặc lệnh bảo dưỡng khác.');
            }
            foreach (FlightSchedule::where('aircraft_tail', $aircraft->tail_number)->whereIn('status', ['PENDING', 'APPROVED', 'PUBLISHED'])->where('is_active', true)->get() as $schedule) {
                $sourceEnd = $end->copy()->setTimezone($schedule->source_timezone ?: 'UTC')->startOfDay()->addDays(3);
                for ($day = $start->copy()->setTimezone($schedule->source_timezone ?: 'UTC')->startOfDay()->subDays(3); $day->lte($sourceEnd); $day->addDay()) {
                    if (! app(ScheduleService::class)->operates($schedule, $day)) {
                        continue;
                    }
                    [$dep, $arr] = app(ScheduleService::class)->times($schedule, $day);
                    if ($dep->lt($end) && $arr->gt($start)) {
                        $this->fail('starts_at_utc', 'Lịch bảo dưỡng trùng lịch bay đã gửi duyệt/công bố.');
                    }
                }
            }
            $id = DB::table('maintenance_orders')->insertGetId([
                'order_code' => strtoupper(trim($data['order_code'])), 'aircraft_tail' => $aircraft->tail_number,
                'task' => $data['task'], 'starts_at_utc' => $start, 'ends_at_utc' => $end,
                'due_at_utc' => Carbon::parse($data['due_at_utc'], 'UTC')->utc(), 'status' => 'PLANNED', 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
            $record = DB::table('maintenance_orders')->find($id);
            AuditService::record('maintenance_orders', $id, 'PLANNED', null, $record);

            return $record;
        });
    }

    public function act(int $id, string $action, array $data): object
    {
        return DB::transaction(function () use ($id, $action, $data) {
            PlanningLock::acquire();
            $order = DB::table('maintenance_orders')->lockForUpdate()->find($id);
            abort_unless($order, 404);
            $aircraft = Aircraft::lockForUpdate()->findOrFail($order->aircraft_tail);
            MaintenanceAccess::authorize(in_array($action, ['approve', 'release']) ? 'approve' : 'write', $aircraft->base_airport_code);
            if ($order->plan_id) { return app(MaintenanceWorkflow::class)->action($id, $action, $data); }
            $values = ['updated_at' => now('UTC')];
            if ($action === 'start') {
                abort_if(auth()->check(), 409, 'Hồ sơ tương thích cần chuyển, rà soát nguồn lực và phê duyệt trước khi thực hiện.');
                if (! in_array($order->status, ['PLANNED', 'WAITING_MATERIALS'])) {
                    $this->fail('status', 'Lệnh không ở trạng thái chờ thực hiện.');
                }
                Validator::make($data, ['technician' => 'required|string|max:100', 'location' => 'required|string|max:100', 'materials_ready' => 'required|boolean'])->validate();
                if (Carbon::parse($order->starts_at_utc)->gt(now('UTC'))) {
                    $this->fail('starts_at_utc', 'Chưa đến thời điểm thực hiện bảo dưỡng.');
                }
                if (DB::table('maintenance_orders')->where('id', '!=', $id)->where('status', 'IN_PROGRESS')
                    ->where(fn ($q) => $q->where('technician', $data['technician'])->orWhere('location', $data['location']))->exists()) {
                    $this->fail('technician', 'Nhân lực hoặc vị trí đang được sử dụng.');
                }
                $values += ['technician' => $data['technician'], 'location' => $data['location'], 'materials_ready' => (bool) $data['materials_ready'],
                    'status' => $data['materials_ready'] ? 'IN_PROGRESS' : 'WAITING_MATERIALS'];
                $aircraft->update(['operational_status' => 'MAINTENANCE']);
            } elseif ($action === 'complete') {
                if ($order->status !== 'IN_PROGRESS' || ! $order->materials_ready) {
                    $this->fail('status', 'Lệnh chưa thực hiện hoặc chưa đủ vật tư.');
                }
                Validator::make($data, ['result' => 'required|string', 'evidence' => 'required|string|max:500', 'technical_confirmed' => 'required|accepted'])->validate();
                abort(409, 'Hồ sơ tương thích chỉ có bằng chứng dạng chữ. Chuyển sang kế hoạch và đính kèm tài liệu thật trước khi ký.');
            } elseif ($action === 'approve') {
                if ($order->status !== 'COMPLETED' || ! $order->technical_confirmed_at) {
                    $this->fail('status', 'Chỉ phê duyệt khi công việc hoàn tất và có xác nhận kỹ thuật.');
                }
                abort(409, 'Chuyển hồ sơ tương thích sang quy trình có chữ ký và tài liệu trước khi phê duyệt.');
            } elseif ($action === 'release') {
                if (app(MaintenanceReadiness::class)->issues($aircraft)) { $this->fail('aircraft_tail', 'Còn kế hoạch bảo dưỡng đến/quá hạn; không thể giải phóng qua API tương thích.'); }
                if ($order->status !== 'APPROVED' || ! $order->technical_confirmed_at || ! $order->approved_at || ! $order->result || ! $order->evidence) {
                    $this->fail('status', 'Thiếu hoàn tất, bằng chứng, xác nhận kỹ thuật hoặc phê duyệt.');
                }
                abort(409, 'Hồ sơ tương thích chưa có tài liệu và chữ ký đã kiểm chứng; cần rà soát trước khi giải phóng.');
            } else {
                $this->fail('action', 'Thao tác bảo dưỡng không hợp lệ.');
            }
            DB::table('maintenance_orders')->where('id', $id)->update($values);
            $after = DB::table('maintenance_orders')->find($id);
            AuditService::record('maintenance_orders', $id, strtoupper($action), $order, $after);

            return $after;
        });
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
