<?php

namespace App\Services\Core;

use App\Models\Aircraft;
use App\Models\AirportResource;
use App\Models\FlightSchedule;
use App\Models\OperationalFlight;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MaintenanceWorkflow
{
    public function plans(array $filters = [])
    {
        $q = MaintenanceAccess::filter(DB::table('maintenance_plans as p')->join('aircrafts as a', 'a.tail_number', '=', 'p.aircraft_tail'));
        if (! empty($filters['q'])) { $q->where(fn ($w) => $w->where('p.plan_code', 'like', '%'.mb_substr($filters['q'], 0, 50).'%')->orWhere('p.aircraft_tail', 'like', '%'.mb_substr($filters['q'], 0, 50).'%')); }
        if (in_array($filters['status'] ?? '', ['ACTIVE', 'RESOLVED'])) { $q->where('p.status', $filters['status']); }
        return $q->select('p.*', 'a.base_airport_code')->orderByDesc('p.id')->paginate(15)->withQueryString();
    }

    public function detail(int $id): array
    {
        $p = DB::table('maintenance_plans')->find($id); abort_unless($p, 404);
        $a = Aircraft::findOrFail($p->aircraft_tail); MaintenanceAccess::authorize('read', $a->base_airport_code);
        $o = DB::table('maintenance_orders')->where('plan_id', $id)->first();
        $events = DB::table('maintenance_events as e')->leftJoin('users as u', 'u.id', '=', 'e.actor_id')->where('e.plan_id', $id)
            ->select('e.*', 'u.username')->orderByDesc('e.id')->paginate(15, ['*'], 'history_page')->withQueryString();
        return ['plan' => $p, 'aircraft' => $a, 'order' => $o, 'events' => $events,
            'tasks' => $o ? DB::table('maintenance_order_tasks')->where('order_id', $o->id)->get() : collect(),
            'materials' => $o ? DB::table('maintenance_order_materials as om')->join('maintenance_materials as m', 'm.id', '=', 'om.material_id')->where('om.order_id', $o->id)->select('om.*', 'm.code', 'm.unit', 'm.quantity as stock')->get() : collect(),
            'evidence' => $o ? DB::table('maintenance_evidence')->where('order_id', $o->id)->select('id', 'order_id', 'mime', 'bytes', 'sha256', 'actor_id', 'created_at')->get() : collect(),
            'alerts' => DB::table('maintenance_alerts')->where('plan_id', $id)->whereNull('resolved_at')->get(),
            'due' => $p->status === 'ACTIVE' && app(MaintenanceReadiness::class)->due($p, $a)];
    }

    public function savePlan(array $data, ?int $id = null): object
    {
        return DB::transaction(function () use ($data, $id) {
            PlanningLock::acquire();
            $before = $id ? DB::table('maintenance_plans')->lockForUpdate()->find($id) : null;
            abort_if($id && ! $before, 404);
            $tail = $before?->aircraft_tail ?? (is_string($data['aircraft_tail'] ?? null) ? strtoupper(trim($data['aircraft_tail'])) : '');
            $a = Aircraft::lockForUpdate()->findOrFail($tail); MaintenanceAccess::authorize('write', $a->base_airport_code);
            $data['aircraft_tail'] = $tail; $data['plan_code'] = strtoupper(trim(is_string($data['plan_code'] ?? null) ? $data['plan_code'] : ''));
            $v = Validator::make($data, [
                'plan_code' => 'required|regex:/^[A-Z0-9_-]{1,50}$/', 'task' => 'required|string|max:200',
                'trigger_type' => 'required|in:DATE,HOURS,CYCLES,CONDITION', 'starts_at_utc' => 'required|date',
                'ends_at_utc' => 'required|date|after:starts_at_utc', 'due_at_utc' => 'required|date|after_or_equal:ends_at_utc',
                'due_hours' => 'nullable|required_if:trigger_type,HOURS|decimal:0,2|min:0|max:9999999999.99',
                'due_cycles' => 'nullable|required_if:trigger_type,CYCLES|integer|min:0|max:4294967295',
                'condition' => 'nullable|required_if:trigger_type,CONDITION|string|max:2000', 'condition_active' => 'sometimes|boolean',
                'resets_usage_basis' => 'sometimes|boolean', 'tasks' => 'required|array|min:1|max:50', 'tasks.*' => 'required|string|max:200|distinct',
                'reason' => 'required|string|max:2000', 'expected_revision' => $id ? 'required|integer|min:1' : 'sometimes|integer',
            ])->validate();
            if ($a->total_flight_hours === null || $a->total_flight_cycles === null) { $this->fail('usage', 'Thiếu số liệu sử dụng. Bổ sung hồ sơ bàn giao/ledger trước khi lập lịch.'); }
            if (str_starts_with($v['plan_code'], 'LEGACY-')) { $this->fail('plan_code', 'Tiền tố LEGACY- dành cho hồ sơ được chuyển từ quy trình cũ.'); }
            if (! app(AircraftService::class)->reconciliation($a)['matched']) { $this->fail('usage', 'Số liệu sử dụng không khớp hồ sơ bàn giao/ledger. Cần đối chiếu trước khi lập lịch.'); }
            if (DB::table('maintenance_plans')->where('plan_code', $v['plan_code'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) { $this->fail('plan_code', 'Mã kế hoạch đã tồn tại.'); }
            if ($before) {
                abort_unless((int) $v['expected_revision'] === (int) $before->revision, 409, 'Kế hoạch đã thay đổi. Tải lại hồ sơ.');
                if ($before->status !== 'ACTIVE' || DB::table('maintenance_orders')->where('plan_id', $id)->exists()) { $this->fail('status', 'Kế hoạch đã có lệnh hoặc đã giải quyết; không sửa hồ sơ đã chốt.'); }
                // A schedule change must not defer an overdue requirement or change its trigger to evade it.
                if (app(MaintenanceReadiness::class)->due($before, $a) && ($v['trigger_type'] !== $before->trigger_type
                    || Carbon::parse($v['due_at_utc'])->gt(Carbon::parse($before->due_at_utc))
                    || bccomp((string) ($v['due_hours'] ?? 0), (string) ($before->due_hours ?? 0), 2) > 0
                    || ($v['due_cycles'] ?? 0) > ($before->due_cycles ?? 0)
                    || ($before->condition_active && ! ($v['condition_active'] ?? false)))) { $this->fail('due_at_utc', 'Không được dời hạn hoặc xóa điều kiện đã đến hạn để bỏ chặn khai thác.'); }
            }
            $this->checkWindow($a, Carbon::parse($v['starts_at_utc'], 'UTC')->utc(), Carbon::parse($v['ends_at_utc'], 'UTC')->utc(), $id);
            $values = ['plan_code' => $v['plan_code'], 'aircraft_tail' => $tail, 'task' => $v['task'], 'trigger_type' => $v['trigger_type'],
                'starts_at_utc' => Carbon::parse($v['starts_at_utc'], 'UTC')->utc(), 'ends_at_utc' => Carbon::parse($v['ends_at_utc'], 'UTC')->utc(), 'due_at_utc' => Carbon::parse($v['due_at_utc'], 'UTC')->utc(),
                'due_hours' => $v['trigger_type'] === 'HOURS' ? $v['due_hours'] : null, 'due_cycles' => $v['trigger_type'] === 'CYCLES' ? $v['due_cycles'] : null,
                'condition' => $v['trigger_type'] === 'CONDITION' ? $v['condition'] : null, 'condition_active' => $v['trigger_type'] === 'CONDITION' && ($v['condition_active'] ?? false),
                'resets_usage_basis' => $v['resets_usage_basis'] ?? false, 'tasks' => json_encode(array_values($v['tasks']), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'reason' => $v['reason'], 'revision' => ($before?->revision ?? 0) + 1, 'updated_at' => now('UTC')];
            if ($id) { DB::table('maintenance_plans')->where('id', $id)->update($values); }
            else { $id = DB::table('maintenance_plans')->insertGetId($values + ['created_by' => auth()->id(), 'created_at' => now('UTC')]); }
            $p = DB::table('maintenance_plans')->find($id); $this->event($p, null, $before ? 'PLAN_REVISED' : 'PLAN_CREATED', $v['reason']);
            AuditService::record('maintenance_plans', $id, $before ? 'REVISE' : 'CREATE', $before, $p, $v['reason']);
            $this->refreshOne($p, $a); return $p;
        });
    }

    public function createOrder(int $id, array $data): object
    {
        return DB::transaction(function () use ($id, $data) {
            PlanningLock::acquire(); $p = DB::table('maintenance_plans')->lockForUpdate()->find($id); abort_unless($p, 404);
            $a = Aircraft::lockForUpdate()->findOrFail($p->aircraft_tail); MaintenanceAccess::authorize('write', $a->base_airport_code);
            $v = Validator::make($data, ['order_code' => 'required|regex:/^[A-Za-z0-9_-]{1,50}$/', 'technician_id' => 'required|uuid|exists:users,id',
                'location_id' => 'required|integer|exists:airport_resources,id', 'materials' => 'sometimes|array|max:30',
                'materials.*.material_id' => 'required|integer|distinct|exists:maintenance_materials,id', 'materials.*.quantity' => 'required|integer|min:1|max:1000000'])->validate();
            if ($p->status !== 'ACTIVE' || DB::table('maintenance_orders')->where('plan_id', $id)->exists()) { $this->fail('plan_id', 'Kế hoạch không hoạt động hoặc đã có lệnh.'); }
            if (DB::table('maintenance_orders')->where('order_code', strtoupper($v['order_code']))->exists()) { $this->fail('order_code', 'Mã lệnh đã tồn tại.'); }
            $this->resources($a, $v['technician_id'], $v['location_id']);
            $oid = DB::table('maintenance_orders')->insertGetId(['plan_id' => $id, 'order_code' => strtoupper($v['order_code']), 'aircraft_tail' => $a->tail_number,
                'task' => $p->task, 'starts_at_utc' => $p->starts_at_utc, 'ends_at_utc' => $p->ends_at_utc, 'due_at_utc' => $p->due_at_utc,
                'technician_id' => $v['technician_id'], 'technician' => User::findOrFail($v['technician_id'])->username,
                'location_id' => $v['location_id'], 'location' => AirportResource::findOrFail($v['location_id'])->resource_code,
                'status' => 'PLANNED', 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
            foreach (json_decode($p->tasks, true, 512, JSON_THROW_ON_ERROR) as $task) { DB::table('maintenance_order_tasks')->insert(['order_id' => $oid, 'description' => $task]); }
            foreach ($v['materials'] ?? [] as $m) {
                $material = DB::table('maintenance_materials')->find($m['material_id']);
                if ($material->airport_code !== $a->base_airport_code) { $this->fail('materials', 'Vật tư phải thuộc kho của sân bay cơ sở tàu bay.'); }
                DB::table('maintenance_order_materials')->insert(['order_id' => $oid, 'material_id' => $m['material_id'], 'quantity' => $m['quantity']]);
            }
            $o = DB::table('maintenance_orders')->find($oid); $this->event($p, $o, 'ORDER_CREATED', 'Phân công nhân lực, vị trí và định mức vật tư'); return $o;
        });
    }

    public function adopt(int $id, array $data): object
    {
        return DB::transaction(function () use ($id, $data) {
            PlanningLock::acquire(); $o = DB::table('maintenance_orders')->lockForUpdate()->find($id); abort_unless($o, 404);
            $a = Aircraft::lockForUpdate()->findOrFail($o->aircraft_tail); MaintenanceAccess::authorize('write', $a->base_airport_code);
            if ($o->plan_id || ! in_array($o->status, ['PLANNED', 'WAITING_MATERIALS', 'IN_PROGRESS'])) { $this->fail('status', 'Hồ sơ đã có kế hoạch hoặc đã ký theo quy trình cũ; cần rà soát hồ sơ gốc.'); }
            if (! $a->base_airport_code) { $this->fail('aircraft_tail', 'Bộ phận đội tàu cần bổ sung sân bay cơ sở trước khi chuyển hồ sơ.'); }
            $v = Validator::make($data, ['technician_id' => 'required|uuid|exists:users,id', 'location_id' => 'required|integer|exists:airport_resources,id', 'reason' => 'required|string|max:2000'])->validate();
            $this->resources($a, $v['technician_id'], $v['location_id'], $id);
            $pid = DB::table('maintenance_plans')->insertGetId(['plan_code' => 'LEGACY-'.$id, 'aircraft_tail' => $o->aircraft_tail, 'task' => $o->task,
                'trigger_type' => 'DATE', 'starts_at_utc' => $o->starts_at_utc, 'ends_at_utc' => $o->ends_at_utc, 'due_at_utc' => $o->due_at_utc,
                'tasks' => json_encode([$o->task], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'reason' => $v['reason'], 'created_by' => auth()->id(), 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
            // Preserve all original facts in the event and audit. The legacy checkbox is never taken as stock/signature evidence.
            $this->event(DB::table('maintenance_plans')->find($pid), $o, 'LEGACY_REVIEW', $v['reason']);
            DB::table('maintenance_orders')->where('id', $id)->update(['plan_id' => $pid, 'status' => 'PLANNED', 'materials_ready' => false,
                'technician_id' => $v['technician_id'], 'technician' => User::findOrFail($v['technician_id'])->username,
                'location_id' => $v['location_id'], 'location' => AirportResource::findOrFail($v['location_id'])->resource_code, 'revision' => $o->revision + 1, 'updated_at' => now('UTC')]);
            DB::table('maintenance_order_tasks')->insert(['order_id' => $id, 'description' => $o->task]);
            $after = DB::table('maintenance_orders')->find($id); AuditService::record('maintenance_orders', $id, 'ADOPT', $o, $after, $v['reason']);
            $this->event(DB::table('maintenance_plans')->find($pid), $after, 'ORDER_ADOPTED', $v['reason']); return $after;
        });
    }

    public function action(int $id, string $action, array $data): object
    {
        return DB::transaction(function () use ($id, $action, $data) {
            PlanningLock::acquire(); $o = DB::table('maintenance_orders')->lockForUpdate()->find($id); abort_unless($o && $o->plan_id, 404);
            $a = Aircraft::lockForUpdate()->findOrFail($o->aircraft_tail); $p = DB::table('maintenance_plans')->lockForUpdate()->find($o->plan_id);
            MaintenanceAccess::authorize(in_array($action, ['authorize', 'approve', 'release', 'keep-unavailable']) ? 'approve' : 'write', $a->base_airport_code);
            $v = Validator::make($data, ['expected_revision' => 'required|integer|min:1', 'reason' => 'required|string|max:2000'])->validate();
            abort_unless((int) $v['expected_revision'] === (int) $o->revision, 409, 'Lệnh đã thay đổi. Tải lại hồ sơ.');
            $values = ['updated_at' => now('UTC'), 'revision' => $o->revision + 1];
            if ($action === 'authorize') {
                if (! in_array($o->status, ['PLANNED', 'WAITING_MATERIALS']) || $o->authorized_by) { $this->fail('status', 'Chỉ duyệt lệnh chờ thực hiện và chưa có phê duyệt nguồn lực hiện tại.'); }
                abort_unless(auth()->check(), 403, 'Cần tài khoản chịu trách nhiệm phê duyệt trước khi thực hiện.');
                $this->resources($a, $o->technician_id, $o->location_id, $id);
                $values += ['authorized_by' => auth()->id(), 'authorized_at' => now('UTC')];
            } elseif ($action === 'assign') {
                if (! in_array($o->status, ['PLANNED', 'WAITING_MATERIALS'])) { $this->fail('status', 'Chỉ điều chỉnh nguồn lực trước khi bắt đầu.'); }
                $rv = Validator::make($data, ['technician_id' => 'required|uuid|exists:users,id', 'location_id' => 'required|integer|exists:airport_resources,id',
                    'materials' => 'sometimes|array|max:30', 'materials.*.material_id' => 'required|integer|distinct|exists:maintenance_materials,id', 'materials.*.quantity' => 'required|integer|min:1|max:1000000'])->validate();
                $this->resources($a, $rv['technician_id'], $rv['location_id'], $id);
                if (DB::table('maintenance_order_materials')->where('order_id', $id)->where('consumed', true)->exists()) { $this->fail('materials', 'Đã có xuất kho; không được sửa định mức cũ.'); }
                $this->event($p, $o, 'RESOURCES_BEFORE_CHANGE', $v['reason']);
                DB::table('maintenance_order_materials')->where('order_id', $id)->delete();
                foreach ($rv['materials'] ?? [] as $m) {
                    if (DB::table('maintenance_materials')->where('id', $m['material_id'])->value('airport_code') !== $a->base_airport_code) { $this->fail('materials', 'Vật tư không thuộc kho sân bay cơ sở.'); }
                    DB::table('maintenance_order_materials')->insert(['order_id' => $id, 'material_id' => $m['material_id'], 'quantity' => $m['quantity']]);
                }
                $values += ['technician_id' => $rv['technician_id'], 'technician' => User::findOrFail($rv['technician_id'])->username, 'location_id' => $rv['location_id'], 'location' => AirportResource::findOrFail($rv['location_id'])->resource_code, 'authorized_by' => null, 'authorized_at' => null];
            } elseif ($action === 'start') {
                if (! in_array($o->status, ['PLANNED', 'WAITING_MATERIALS'])) { $this->fail('status', 'Lệnh không chờ bắt đầu.'); }
                if (! $o->authorized_by || ! $o->authorized_at) { $this->fail('authorization', 'Cần phê duyệt lệnh và nguồn lực trước khi thực hiện.'); }
                if (Carbon::parse($o->starts_at_utc, 'UTC')->gt(now('UTC'))) { $this->fail('starts_at_utc', 'Chưa đến lịch bảo dưỡng.'); }
                if (OperationalFlight::where('aircraft_tail', $a->tail_number)->where('flight_status', 'DEPARTED')->exists()) { $this->fail('aircraft_tail', 'Tàu bay còn chuyến đang bay; giờ đến dự kiến không thay xác nhận hạ cánh.'); }
                $this->resources($a, $o->technician_id, $o->location_id, $id);
                $this->checkWindow($a, now('UTC'), Carbon::parse($o->ends_at_utc, 'UTC')->max(now('UTC')->addMinute()), $p->id, $id);
                $requirements = DB::table('maintenance_order_materials')->where('order_id', $id)->orderBy('material_id')->get(); $short = false;
                foreach ($requirements as $m) {
                    $stock = DB::table('maintenance_materials')->lockForUpdate()->find($m->material_id);
                    if ($stock->quantity < $m->quantity) { $short = true; }
                }
                if ($short) {
                    $values += ['status' => 'WAITING_MATERIALS', 'materials_ready' => false];
                    $this->alert($p->id, 'MATERIAL_SHORTAGE', 'Thiếu vật tư; chưa trừ kho, lệnh chờ bổ sung.');
                } else {
                    foreach ($requirements as $m) {
                        DB::table('maintenance_materials')->where('id', $m->material_id)->decrement('quantity', $m->quantity);
                        DB::table('maintenance_order_materials')->where('id', $m->id)->update(['consumed' => true]);
                        DB::table('maintenance_stock_moves')->insert(['material_id' => $m->material_id, 'order_id' => $id, 'reference' => 'ORDER-'.$id,
                            'delta' => -$m->quantity, 'reason' => $v['reason'], 'actor_id' => auth()->id(), 'created_at' => now('UTC')]);
                    }
                    DB::table('maintenance_alerts')->where('plan_id', $p->id)->where('kind', 'MATERIAL_SHORTAGE')->update(['resolved_at' => now('UTC')]);
                    $values += ['status' => 'IN_PROGRESS', 'materials_ready' => true];
                }
                $a->update(['operational_status' => 'MAINTENANCE']);
            } elseif ($action === 'task') {
                if ($o->status !== 'IN_PROGRESS') { $this->fail('status', 'Chỉ ghi kết quả khi đang thực hiện.'); }
                $tv = Validator::make($data, ['task_id' => 'required|integer', 'result' => 'required|string|max:4000', 'passed' => 'required|boolean'])->validate();
                $task = DB::table('maintenance_order_tasks')->where('id', $tv['task_id'])->where('order_id', $id)->first(); abort_unless($task, 404);
                DB::table('maintenance_order_tasks')->where('id', $task->id)->update(['result' => $tv['result'], 'passed' => $tv['passed'], 'actor_id' => auth()->id(), 'recorded_at' => now('UTC')]);
                $values['result'] = $tv['result']; // Task facts are retained in the immutable event snapshot below.
            } elseif ($action === 'complete') {
                if ($o->status !== 'IN_PROGRESS' || ! $o->materials_ready) { $this->fail('status', 'Lệnh chưa thực hiện hoặc thiếu vật tư.'); }
                $cv = Validator::make($data, ['result' => 'required|string|max:8000', 'technical_confirmed' => 'required|accepted'])->validate();
                if (DB::table('maintenance_order_tasks')->where('order_id', $id)->where(fn ($q) => $q->where('passed', false)->orWhereNull('result'))->exists()) { $this->fail('tasks', 'Chưa hoàn tất mọi hạng mục; lệnh tiếp tục đang thực hiện.'); }
                $files = DB::table('maintenance_evidence')->where('order_id', $id)->get();
                if ($files->isEmpty()) { $this->fail('evidence', 'Cần tài liệu đính kèm trước khi ký kỹ thuật.'); }
                foreach ($files as $file) { $this->verifyFile($file); }
                $values += ['status' => 'COMPLETED', 'result' => $cv['result'], 'technical_confirmed_at' => now('UTC'), 'technical_confirmed_by' => auth()->id(), 'evidence' => 'PRIVATE_ATTACHMENTS'];
            } elseif ($action === 'approve') {
                if ($o->status !== 'COMPLETED' || ! $o->technical_confirmed_by || ! $o->technical_confirmed_at) { $this->fail('status', 'Thiếu kết quả hoặc chữ ký kỹ thuật.'); }
                $values += ['status' => 'APPROVED', 'approved_at' => now('UTC'), 'approved_by' => auth()->id()];
            } elseif ($action === 'keep-unavailable') {
                if ($o->status === 'RELEASED') { $this->fail('status', 'Hồ sơ đã giải phóng; lập kế hoạch mới để ghi nhận bất thường.'); }
                $a->update(['operational_status' => 'MAINTENANCE']);
            } elseif ($action === 'release') {
                if ($o->status !== 'APPROVED' || ! $o->approved_by || ! $o->technical_confirmed_by || ! $o->technical_confirmed_at || ! $o->result) { $this->fail('status', 'Thiếu hoàn tất, chữ ký kỹ thuật hoặc phê duyệt.'); }
                if (Carbon::parse($o->due_at_utc, 'UTC')->lte(now('UTC'))) { $this->fail('due_at_utc', 'Lệnh quá hạn; chưa được giải phóng kỹ thuật.'); }
                if (app(MaintenanceReadiness::class)->issues($a, ignorePlan: $p->id)
                    || DB::table('maintenance_orders')->where('aircraft_tail', $a->tail_number)->where('id', '!=', $id)->where('status', '!=', 'RELEASED')
                        ->where(fn ($q) => $q->where('due_at_utc', '<=', now('UTC'))->orWhereIn('status', ['WAITING_MATERIALS', 'IN_PROGRESS', 'COMPLETED', 'APPROVED']))->exists()) { $this->fail('aircraft_tail', 'Còn yêu cầu bảo dưỡng/lệnh kỹ thuật chưa giải quyết.'); }
                $files = DB::table('maintenance_evidence')->where('order_id', $id)->get(); if ($files->isEmpty()) { $this->fail('evidence', 'Thiếu tài liệu kỹ thuật.'); }
                foreach ($files as $file) { $this->verifyFile($file); }
                if ($a->needsMaintenance() && ! $p->resets_usage_basis) { $this->fail('usage', 'Bảo dưỡng hạng mục này không giải quyết hạn kiểm tra giờ bay/chu kỳ tổng.'); }
                $av = ['operational_status' => 'OPERATIONAL'];
                if ($p->resets_usage_basis) { $av += ['maintenance_base_hours' => $a->total_flight_hours, 'maintenance_base_cycles' => $a->total_flight_cycles]; }
                $a->update($av); DB::table('maintenance_plans')->where('id', $p->id)->update(['status' => 'RESOLVED', 'updated_at' => now('UTC')]);
                DB::table('maintenance_alerts')->where('plan_id', $p->id)->whereNull('resolved_at')->update(['resolved_at' => now('UTC')]);
                $values += ['status' => 'RELEASED', 'released_at' => now('UTC')];
            } else { $this->fail('action', 'Thao tác không hợp lệ.'); }
            DB::table('maintenance_orders')->where('id', $id)->update($values); $after = DB::table('maintenance_orders')->find($id);
            $this->event($p, $after, strtoupper(str_replace('-', '_', $action)), $v['reason']);
            AuditService::record('maintenance_orders', $id, strtoupper($action), $o, $after, $v['reason']); return $after;
        });
    }

    public function upload(int $id, UploadedFile $file): object
    {
        $o = DB::table('maintenance_orders')->find($id); abort_unless($o && $o->plan_id, 404);
        MaintenanceAccess::authorize('write', Aircraft::findOrFail($o->aircraft_tail)->base_airport_code);
        Validator::make(['file' => $file], ['file' => 'required|file|mimetypes:application/pdf,image/png,image/jpeg|max:10240'])->validate();
        $path = $file->store('maintenance', 'local');
        try {
            return DB::transaction(function () use ($id, $file, $path) {
                PlanningLock::acquire(); $o = DB::table('maintenance_orders')->lockForUpdate()->find($id);
                MaintenanceAccess::authorize('write', Aircraft::findOrFail($o->aircraft_tail)->base_airport_code);
                if ($o->status !== 'IN_PROGRESS') { $this->fail('status', 'Chỉ đính kèm tài liệu khi đang thực hiện.'); }
                $eid = DB::table('maintenance_evidence')->insertGetId(['order_id' => $id, 'path' => $path, 'mime' => $file->getMimeType(), 'bytes' => $file->getSize(),
                    'sha256' => hash_file('sha256', Storage::disk('local')->path($path)), 'actor_id' => auth()->id(), 'created_at' => now('UTC')]);
                DB::table('maintenance_orders')->where('id', $id)->increment('revision');
                $e = DB::table('maintenance_evidence')->find($eid); $this->event(DB::table('maintenance_plans')->find($o->plan_id), $o, 'EVIDENCE_UPLOADED', 'SHA256: '.$e->sha256);
                unset($e->path); return $e;
            });
        } catch (\Throwable $e) { Storage::disk('local')->delete($path); throw $e; }
    }

    public function download(int $id)
    {
        $e = DB::table('maintenance_evidence')->find($id); abort_unless($e, 404);
        $o = DB::table('maintenance_orders')->find($e->order_id); MaintenanceAccess::authorize('read', Aircraft::findOrFail($o->aircraft_tail)->base_airport_code);
        $this->verifyFile($e);
        return Storage::disk('local')->download($e->path, 'maintenance-evidence-'.$id.'.'.match ($e->mime) { 'application/pdf' => 'pdf', 'image/png' => 'png', default => 'jpg' }, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function receiveMaterial(array $data): object
    {
        MaintenanceAccess::authorize('write', is_string($data['airport_code'] ?? null) ? $data['airport_code'] : null);
        $v = Validator::make($data, ['airport_code' => 'required|exists:airports,iata_code', 'code' => 'required|regex:/^[A-Z0-9_-]{1,50}$/', 'name' => 'required|string|max:200',
            'unit' => 'required|string|max:30', 'quantity' => 'required|integer|min:1|max:1000000', 'reference' => 'required|string|max:100', 'reason' => 'required|string|max:2000'])->validate();
        if (str_starts_with($v['reference'], 'ORDER-')) { $this->fail('reference', 'Tiền tố ORDER- dành cho phiếu xuất lệnh bảo dưỡng.'); }
        return DB::transaction(function () use ($v) {
            PlanningLock::acquire(); MaintenanceAccess::authorize('write', $v['airport_code']);
            $m = DB::table('maintenance_materials')->where('airport_code', $v['airport_code'])->where('code', $v['code'])->lockForUpdate()->first();
            if (! $m) { $mid = DB::table('maintenance_materials')->insertGetId(['airport_code' => $v['airport_code'], 'code' => $v['code'], 'name' => $v['name'], 'unit' => $v['unit'], 'created_at' => now('UTC'), 'updated_at' => now('UTC')]); $m = DB::table('maintenance_materials')->find($mid); }
            if ($m->unit !== $v['unit'] || $m->name !== $v['name']) { $this->fail('code', 'Mã vật tư đã tồn tại với tên/đơn vị khác.'); }
            $move = DB::table('maintenance_stock_moves')->where('material_id', $m->id)->where('reference', $v['reference'])->first();
            if ($move) { if ($move->order_id !== null || $move->delta !== (int) $v['quantity'] || $move->reason !== $v['reason']) { $this->fail('reference', 'Tham chiếu nhập kho đã dùng cho nội dung khác.'); } return $m; }
            if ($m->quantity > 4294967295 - $v['quantity']) { $this->fail('quantity', 'Vượt giới hạn tồn kho.'); }
            DB::table('maintenance_materials')->where('id', $m->id)->increment('quantity', $v['quantity']);
            DB::table('maintenance_stock_moves')->insert(['material_id' => $m->id, 'reference' => $v['reference'], 'delta' => $v['quantity'], 'reason' => $v['reason'], 'actor_id' => auth()->id(), 'created_at' => now('UTC')]);
            AuditService::record('maintenance_materials', $m->id, 'RECEIPT', $m, DB::table('maintenance_materials')->find($m->id), $v['reason']); return DB::table('maintenance_materials')->find($m->id);
        });
    }

    public function refreshAlerts(): int
    {
        return DB::transaction(function () {
            PlanningLock::acquire(); $count = 0;
            foreach (DB::table('maintenance_plans')->where('status', 'ACTIVE')->get() as $p) { $this->refreshOne($p, Aircraft::findOrFail($p->aircraft_tail)); $count++; }
            return $count;
        });
    }

    /** Called by the usage ledger in its existing planning transaction. */
    public function refreshAircraft(Aircraft $a): void
    {
        PlanningLock::acquire();
        foreach (DB::table('maintenance_plans')->where('aircraft_tail', $a->tail_number)->where('status', 'ACTIVE')->get() as $p) { $this->refreshOne($p, $a); }
    }

    private function refreshOne(object $p, Aircraft $a): void
    {
        $due = app(MaintenanceReadiness::class)->due($p, $a);
        if ($due) {
            $this->alert($p->id, 'DUE', 'Đến/quá hạn hoặc điều kiện kỹ thuật đang kích hoạt; chặn khai thác.');
            if ($a->operational_status === 'OPERATIONAL') { $a->update(['operational_status' => 'MAINTENANCE']); }
        }
        else {
            DB::table('maintenance_alerts')->where('plan_id', $p->id)->where('kind', 'DUE')->whereNull('resolved_at')->update(['resolved_at' => now('UTC')]);
            if (Carbon::parse($p->due_at_utc, 'UTC')->lte(now('UTC')->addDay())) { $this->alert($p->id, 'DUE_SOON', 'Còn tối đa 24 giờ đến hạn lịch. Ngưỡng hiển thị local, không phải chuẩn kỹ thuật.'); }
        }
        if ($due || Carbon::parse($p->due_at_utc, 'UTC')->gt(now('UTC')->addDay())) { DB::table('maintenance_alerts')->where('plan_id', $p->id)->where('kind', 'DUE_SOON')->whereNull('resolved_at')->update(['resolved_at' => now('UTC')]); }
    }

    private function alert(int $id, string $kind, string $message): void
    {
        DB::table('maintenance_alerts')->updateOrInsert(['plan_id' => $id, 'kind' => $kind], ['message' => $message, 'observed_at' => now('UTC'), 'resolved_at' => null]);
    }

    private function resources(Aircraft $a, string $technician, int $location, ?int $ignore = null): void
    {
        $u = User::findOrFail($technician); $l = AirportResource::findOrFail($location);
        if ($u->status !== 'ACTIVE' || ! Gate::forUser($u)->allows('maintenance.write', $a->base_airport_code ? [['airport' => $a->base_airport_code]] : [])) { $this->fail('technician_id', 'Nhân sự không hoạt động hoặc thiếu quyền kỹ thuật tại sân bay cơ sở.'); }
        if ($l->resource_type !== 'STAND' || $l->status !== 'AVAILABLE' || $l->airport_code !== $a->base_airport_code) { $this->fail('location_id', 'Vị trí bảo dưỡng phải là vị trí đỗ khả dụng tại sân bay cơ sở.'); }
        if (DB::table('maintenance_orders')->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->whereIn('status', ['IN_PROGRESS', 'COMPLETED', 'APPROVED'])
            ->where(fn ($q) => $q->where('technician_id', $technician)->orWhere('location_id', $location))->exists()) { $this->fail('location_id', 'Nhân lực hoặc vị trí đang phục vụ một lệnh chưa giải phóng.'); }
    }

    private function checkWindow(Aircraft $a, Carbon $start, Carbon $end, ?int $ignorePlan = null, ?int $ignoreOrder = null): void
    {
        if (OperationalFlight::where('aircraft_tail', $a->tail_number)->whereNotIn('flight_status', ['CANCELLED', 'ARRIVED'])->where('scheduled_dep_utc', '<', $end)->where('scheduled_arr_utc', '>', $start)->exists()
            || DB::table('maintenance_plans')->where('aircraft_tail', $a->tail_number)->where('status', 'ACTIVE')->when($ignorePlan, fn ($q) => $q->where('id', '!=', $ignorePlan))->where('starts_at_utc', '<', $end)->where('ends_at_utc', '>', $start)->exists()
            || DB::table('maintenance_orders')->where('aircraft_tail', $a->tail_number)->where('status', '!=', 'RELEASED')->when($ignoreOrder, fn ($q) => $q->where('id', '!=', $ignoreOrder))->where('starts_at_utc', '<', $end)->where('ends_at_utc', '>', $start)->exists()) { $this->fail('starts_at_utc', 'Xung đột chuyến bay hoặc lịch/lệnh bảo dưỡng.'); }
        foreach (FlightSchedule::where('aircraft_tail', $a->tail_number)->whereIn('status', ['PENDING', 'APPROVED', 'PUBLISHED'])->where('is_active', true)->get() as $schedule) {
            $last = $end->copy()->setTimezone($schedule->source_timezone ?: 'UTC')->startOfDay()->addDays(3);
            for ($day = $start->copy()->setTimezone($schedule->source_timezone ?: 'UTC')->startOfDay()->subDays(3); $day->lte($last); $day->addDay()) {
                if (! app(ScheduleOccurrence::class)->operates($schedule, $day)) { continue; }
                [$dep, $arr] = app(ScheduleOccurrence::class)->times($schedule, $day);
                if ($dep->lt($end) && $arr->gt($start)) { $this->fail('starts_at_utc', 'Xung đột lịch bay đã gửi duyệt/công bố.'); }
            }
        }
    }

    private function event(object $p, ?object $o, string $action, string $reason): void
    {
        $snapshot = ['plan' => (array) DB::table('maintenance_plans')->find($p->id), 'order' => $o,
            'materials' => $o ? DB::table('maintenance_order_materials')->where('order_id', $o->id)->get()->all() : [],
            'tasks' => $o ? DB::table('maintenance_order_tasks')->where('order_id', $o->id)->get()->all() : []];
        DB::table('maintenance_events')->insert(['plan_id' => $p->id, 'order_id' => $o?->id, 'action' => $action, 'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'reason' => $reason, 'actor_id' => auth()->id(), 'created_at' => now('UTC')]);
    }

    private function verifyFile(object $e): void
    {
        if (! Storage::disk('local')->exists($e->path) || ! hash_equals($e->sha256, hash_file('sha256', Storage::disk('local')->path($e->path)))) { $this->fail('evidence', 'Tài liệu bị thiếu hoặc không khớp SHA256; cần khôi phục, chưa được ký/giải phóng.'); }
    }

    private function fail(string $field, string $message): never { throw ValidationException::withMessages([$field => [$message]]); }
}
