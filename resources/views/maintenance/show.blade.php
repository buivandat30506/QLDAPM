@extends('layouts.admin')
@section('page_title', 'Hồ sơ bảo dưỡng máy bay '.$plan->plan_code)
@section('content')
<div class="sitemap-module-page maintenance-module-page">
@php($context = $aircraft->base_airport_code ? [['airport'=>$aircraft->base_airport_code]] : [])
@php($labels = ['PLANNED'=>'Chờ thực hiện','WAITING_MATERIALS'=>'Chờ vật tư','IN_PROGRESS'=>'Đang thực hiện','COMPLETED'=>'Đã ký kỹ thuật','APPROVED'=>'Đã phê duyệt','RELEASED'=>'Đã giải phóng'])
<a href="{{ route('admin.maintenance') }}">Lịch bảo dưỡng máy bay</a>
<h1 class="h4 mt-3">{{ $plan->plan_code }} — {{ $aircraft->tail_number }}</h1>
<p>{{ $plan->task }} · Phiên bản {{ $plan->revision }} · {{ $plan->status==='ACTIVE' ? 'Đang theo dõi' : 'Đã giải quyết' }}</p>
<p>Sử dụng: {{ $aircraft->total_flight_hours }} giờ / {{ $aircraft->total_flight_cycles }} chu kỳ. Lịch UTC: {{ $plan->starts_at_utc }} → {{ $plan->ends_at_utc }}. Hạn: {{ $plan->due_at_utc }} UTC.</p>
<p>Căn cứ xác định hạn: {{ ['DATE'=>'Ngày giờ','HOURS'=>'Giờ bay','CYCLES'=>'Chu kỳ','CONDITION'=>'Điều kiện'][$plan->trigger_type] }} {{ $plan->due_hours }} {{ $plan->due_cycles }} {{ $plan->condition }}.</p>
@if($due)<div class="alert alert-danger" role="alert">Đến/quá hạn hoặc điều kiện đang kích hoạt: chặn khai thác cho đến khi giải quyết bảo dưỡng.</div>@endif
@foreach($alerts as $alert)<div class="alert alert-warning" role="alert">{{ $alert->message }} ({{ $alert->observed_at }} UTC)</div>@endforeach
@if(!$order)
@can('maintenance.write', $context)
<section class="card mb-3"><h2 class="card-header h5">Điều chỉnh kế hoạch</h2><form class="card-body" method="POST" action="{{ route('admin.maintenance.update', $plan->id) }}" aria-label="Điều chỉnh kế hoạch">@csrf @method('PUT') @include('maintenance.plan-form')</form></section>
<section class="card"><h2 class="card-header h5">Tạo lệnh bảo dưỡng</h2><form method="POST" action="{{ route('admin.maintenance.order', $plan->id) }}" class="card-body row g-3" aria-label="Tạo lệnh bảo dưỡng">@csrf
<div class="col-md-4"><label for="order_code" class="form-label">Mã lệnh</label><input id="order_code" name="order_code" class="form-control" required maxlength="50"></div>
<div class="col-md-4"><label for="technician_id" class="form-label">Kỹ thuật viên phụ trách</label><select id="technician_id" name="technician_id" class="form-select" required>@foreach($technicians as $u)<option value="{{ $u->id }}">{{ $u->username }}</option>@endforeach</select></div>
<div class="col-md-4"><label for="location_id" class="form-label">Vị trí đỗ bảo dưỡng máy bay</label><select id="location_id" name="location_id" class="form-select" required>@foreach($locations as $l)<option value="{{ $l->id }}">{{ $l->airport_code }} / {{ $l->resource_code }}</option>@endforeach</select></div>
@if($locations->isEmpty())<p role="alert">Không có vị trí đỗ khả dụng tại sân bay cơ sở. Liên hệ người quản lý tài nguyên để bổ sung trước khi tạo lệnh.</p>@endif
@foreach($stock as $i=>$m)<div class="col-md-4"><input type="hidden" name="materials[{{ $i }}][material_id]" value="{{ $m->id }}"><label for="material_{{ $m->id }}" class="form-label">{{ $m->code }} — tồn {{ $m->quantity }} {{ $m->unit }}</label><input id="material_{{ $m->id }}" name="materials[{{ $i }}][quantity]" type="number" min="0" value="0" class="form-control"></div>@endforeach
<p>Nhập 0 nếu hạng mục không dùng vật tư đó. Khi bắt đầu, hệ thống kiểm tra và xuất kho trong cùng một lần xử lý.</p><div><button class="btn btn-primary">Tạo lệnh</button></div></form></section>
@endcan
@else
<p>Phê duyệt trước thực hiện: {{ $order->authorized_at ? $order->authorized_at.' UTC (người duyệt trong lịch sử)' : 'Chưa duyệt nguồn lực hiện tại' }}.</p>
<h2 class="h5">Lệnh {{ $order->order_code }}: {{ $labels[$order->status] ?? $order->status }}</h2>
<p>Phụ trách {{ $order->technician }} · Vị trí {{ $order->location }} · Phiên bản {{ $order->revision }}. {{ $order->result }}</p>
@if(in_array($order->status,['PLANNED','WAITING_MATERIALS'])) @can('maintenance.write',$context)
<form method="POST" action="{{ route('admin.maintenance.action',[$order->id,'assign']) }}" class="card card-body mb-3" aria-label="Điều chỉnh nguồn lực">@csrf <h3 class="h6">Điều chỉnh nguồn lực trước khi bắt đầu</h3><input type="hidden" name="expected_revision" value="{{ $order->revision }}">
<label for="assign_technician">Kỹ thuật viên</label><select id="assign_technician" name="technician_id" class="form-select">@foreach($technicians as $u)<option value="{{ $u->id }}" @selected($u->id===$order->technician_id)>{{ $u->username }}</option>@endforeach</select>
<label for="assign_location">Vị trí</label><select id="assign_location" name="location_id" class="form-select">@foreach($locations as $l)<option value="{{ $l->id }}" @selected($l->id===$order->location_id)>{{ $l->resource_code }}</option>@endforeach</select>
@foreach($stock as $i=>$m)<input type="hidden" name="materials[{{ $i }}][material_id]" value="{{ $m->id }}"><label for="assign_material_{{ $m->id }}">{{ $m->code }} ({{ $m->unit }})</label><input id="assign_material_{{ $m->id }}" name="materials[{{ $i }}][quantity]" class="form-control" type="number" min="0" value="{{ $materials->firstWhere('material_id',$m->id)?->quantity ?? 0 }}">@endforeach
<label for="assign_reason">Lý do điều chỉnh nguồn lực</label><input id="assign_reason" name="reason" class="form-control" required><div><button class="btn btn-outline-primary mt-2">Lưu nguồn lực</button></div></form>
@endcan @endif
<div class="table-responsive"><table class="table" aria-label="Vật tư của lệnh"><thead><tr><th>Vật tư</th><th>Yêu cầu</th><th>Đã xuất</th><th>Tồn</th></tr></thead><tbody>@forelse($materials as $m)<tr><td>{{ $m->code }}</td><td>{{ $m->quantity }} {{ $m->unit }}</td><td>{{ $m->consumed ? 'Đã xuất' : 'Chưa xuất' }}</td><td>{{ $m->stock }}</td></tr>@empty<tr><td colspan="4">Lệnh không yêu cầu vật tư.</td></tr>@endforelse</tbody></table></div>
@foreach($tasks as $task)
<section class="card mb-3"><h3 class="card-header h6">{{ $task->description }} — {{ $task->passed ? 'Đạt' : 'Chưa đạt' }}</h3><div class="card-body"><p>{{ $task->result }}</p>
@if($order->status==='IN_PROGRESS') @can('maintenance.write', $context)
<form method="POST" action="{{ route('admin.maintenance.action', [$order->id, 'task']) }}" aria-label="Kết quả {{ $task->description }}">@csrf <input type="hidden" name="expected_revision" value="{{ $order->revision }}"><input type="hidden" name="task_id" value="{{ $task->id }}">
<label for="result_{{ $task->id }}" class="form-label">Kết quả hạng mục</label><textarea id="result_{{ $task->id }}" name="result" class="form-control" required>{{ $task->result }}</textarea>
<label for="passed_{{ $task->id }}">Đánh giá</label><select id="passed_{{ $task->id }}" name="passed" class="form-select"><option value="0">Chưa đạt / tiếp tục thực hiện</option><option value="1">Đạt</option></select>
<label for="reason_{{ $task->id }}">Căn cứ ghi kết quả</label><input id="reason_{{ $task->id }}" name="reason" class="form-control" required><button class="btn btn-outline-primary mt-2">Lưu kết quả hạng mục</button></form>
@endcan @endif</div></section>
@endforeach
<section class="card mb-3"><h2 class="card-header h5">Tài liệu kỹ thuật</h2><div class="card-body">
@forelse($evidence as $e)<p class="text-break"><a href="{{ route('admin.maintenance.evidence', $e->id) }}">Tải tài liệu #{{ $e->id }}</a> · {{ $e->mime }} · {{ $e->bytes }} bytes · SHA256 {{ $e->sha256 }}</p>@empty<p>Chưa có tài liệu đính kèm.</p>@endforelse
@if($order->status==='IN_PROGRESS') @can('maintenance.write', $context)<form method="POST" enctype="multipart/form-data" action="{{ route('admin.maintenance.upload', $order->id) }}" aria-label="Đính kèm tài liệu">@csrf <label for="evidence_file" class="form-label">Tài liệu PDF, PNG hoặc JPEG (tối đa 10 MB)</label><input type="file" id="evidence_file" name="file" class="form-control" accept="application/pdf,image/png,image/jpeg" required><button class="btn btn-outline-primary mt-2">Lưu tài liệu</button></form>@endcan @endif
</div></section>
@foreach(['authorize'=>'Duyệt cho phép thực hiện','start'=>'Bắt đầu / kiểm tra lại vật tư','complete'=>'Hoàn tất và ký kỹ thuật','approve'=>'Phê duyệt kết quả','release'=>'Giải phóng kỹ thuật','keep-unavailable'=>'Tiếp tục không khả dụng'] as $action=>$title)
@php($visible = match($action) {'authorize'=>in_array($order->status,['PLANNED','WAITING_MATERIALS']) && !$order->authorized_by,'start'=>in_array($order->status,['PLANNED','WAITING_MATERIALS']) && $order->authorized_by && $order->authorized_at, 'complete'=>$order->status==='IN_PROGRESS','approve'=>$order->status==='COMPLETED','release'=>$order->status==='APPROVED',default=>$order->status!=='RELEASED'})
@if($visible && Gate::allows(in_array($action,['authorize','approve','release','keep-unavailable']) ? 'maintenance.approve' : 'maintenance.write', $context))
<form method="POST" action="{{ route('admin.maintenance.action', [$order->id,$action]) }}" class="card card-body mb-3" aria-label="{{ $title }}">@csrf <h3 class="h6">{{ $title }}</h3><input type="hidden" name="expected_revision" value="{{ $order->revision }}">
@if($action==='complete')<label for="completion_result">Kết luận kỹ thuật</label><textarea id="completion_result" name="result" class="form-control" required></textarea><label><input type="checkbox" name="technical_confirmed" value="1" required> Tôi xác nhận kỹ thuật và chịu trách nhiệm về kết quả</label>@endif
<label for="action_reason_{{ $action }}">Lý do / căn cứ quyết định</label><input id="action_reason_{{ $action }}" name="reason" class="form-control" required><div><button class="btn btn-primary mt-2">{{ $title }}</button></div></form>
@endif @endforeach
@endif
<h2 class="h5 mt-4">Lịch sử trách nhiệm</h2><div class="table-responsive"><table class="table" aria-label="Lịch sử bảo dưỡng"><thead><tr><th>UTC</th><th>Người thực hiện</th><th>Hành động</th><th>Căn cứ</th></tr></thead><tbody>@forelse($events as $e)<tr><td>{{ $e->created_at }}</td><td>{{ $e->username ?? 'Dịch vụ nội bộ' }}</td><td>{{ ['AUTHORIZE'=>'Duyệt cho phép thực hiện','LEGACY_REVIEW'=>'Rà soát hồ sơ cũ','ORDER_ADOPTED'=>'Chuyển hồ sơ đầy đủ','ASSIGN'=>'Điều chỉnh nguồn lực','RESOURCES_BEFORE_CHANGE'=>'Nguồn lực trước điều chỉnh','PLAN_CREATED'=>'Lập kế hoạch','PLAN_REVISED'=>'Điều chỉnh kế hoạch','ORDER_CREATED'=>'Tạo lệnh','START'=>'Bắt đầu / kiểm tra vật tư','TASK'=>'Ghi kết quả','EVIDENCE_UPLOADED'=>'Đính kèm tài liệu','COMPLETE'=>'Ký kỹ thuật','APPROVE'=>'Phê duyệt','RELEASE'=>'Giải phóng','KEEP_UNAVAILABLE'=>'Giữ không khả dụng'][$e->action] ?? $e->action }}</td><td class="text-break">{{ $e->reason }}</td></tr>@empty<tr><td colspan="4">Chưa có lịch sử.</td></tr>@endforelse</tbody></table></div>{{ $events->links() }}
</div>
@endsection
