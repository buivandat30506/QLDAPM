@extends('layouts.admin')
@section('title', 'Máy bay '.$aircraft->tail_number)
@section('page_title', 'Máy bay '.$aircraft->tail_number)
@section('content')
<div class="sitemap-module-page aircraft-module-page">
<a href="{{ route('admin.aircrafts') }}">← Danh sách máy bay</a>
<h1 class="h3 mt-2">Máy bay {{ $aircraft->tail_number }}</h1>

<nav class="module-section-nav" aria-label="Chức năng máy bay">
<a href="#aircraft-information">Thông tin máy bay</a><a href="#aircraft-status">Tình trạng hoạt động</a><a href="#aircraft-activity">Lịch hoạt động</a><a href="#aircraft-maintenance">Lịch bảo dưỡng</a><a href="#aircraft-incidents">Sự cố kỹ thuật</a><a href="#aircraft-statistics">Thống kê hoạt động</a>
</nav>
<section id="aircraft-status" class="card module-panel module-tone-info"><div class="card-body">
<h2 class="module-section-title"><i class="bi bi-airplane module-icon" aria-hidden="true"></i>Tình trạng hoạt động</h2>
<span class="dashboard-status {{ match ($aircraft->operational_status) { 'OPERATIONAL' => 'is-positive', 'MAINTENANCE' => 'is-attention', 'GROUNDED' => 'is-danger', default => 'is-neutral' } }}">{{ ['OPERATIONAL' => 'Khả dụng', 'MAINTENANCE' => 'Bảo dưỡng', 'GROUNDED' => 'Ngừng khai thác'][$aircraft->operational_status] ?? $aircraft->operational_status }}</span>
@if($aircraft->needsMaintenance())<p class="text-danger mb-0 mt-2">Quá hạn — không được khai thác.</p>@endif
</div></section>
<section id="aircraft-information" class="module-section"><h2 class="module-section-title">Thông tin máy bay</h2>
<p>{{ $aircraft->model->model_code }} · {{ $aircraft->manufacture_year }} · Cơ sở {{ $aircraft->base_airport_code ?? 'chưa xác định' }} · Phiên bản hồ sơ {{ $aircraft->revision }}</p>
@can('aircraft.write', [['airport'=>$aircraft->base_airport_code]])
<details class="card mb-3 module-form-disclosure" @if($errors->any()) open @endif><summary>Cập nhật hồ sơ</summary><div class="card-body"><form method="post" action="{{ route('admin.aircrafts.update', $aircraft->tail_number) }}">@csrf @method('PUT') @include('fleet.fields')<p class="form-text">Khả dụng sau bảo dưỡng phải qua giải phóng kỹ thuật; số liệu sử dụng không được sửa trong hồ sơ.</p><button class="btn btn-primary">Lưu hồ sơ</button></form></div></details>
<details class="mb-3"><summary>Xóa hồ sơ chưa sử dụng</summary><p>Chỉ xóa được máy bay chưa có cấu hình, lịch bay, chuyến bay hoặc hồ sơ bảo dưỡng.</p><form method="post" action="{{ route('admin.aircrafts.destroy',$aircraft->tail_number) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger">Xóa hồ sơ máy bay chưa sử dụng</button></form></details>
@endcan
<section class="card mb-3"><div class="card-body"><h2 class="h5">Cấu hình ghế của máy bay</h2>
<h3 class="h6">Sơ đồ ghế hiện tại</h3>
@if($aircraft->configuration)
<p>{{ $aircraft->configuration->name }} · v{{ $aircraft->configuration->version }} · {{ $aircraft->configuration->seat_count }} ghế vật lý / {{ $aircraft->configuration->sellable_capacity }} ghế sử dụng. <span class="text-secondary">Màu xám: ghế khóa.</span></p>
<div aria-label="Sơ đồ ghế">@foreach(collect($aircraft->configuration->seats)->groupBy('row') as $row=>$seats)<div class="d-flex flex-wrap gap-2 mb-2" aria-label="Hàng {{ $row }}">@foreach($seats as $seat)<span class="badge {{ $seat['enabled'] ? 'bg-primary' : 'bg-secondary' }}" title="{{ $seat['cabin'] }} · {{ $seat['enabled'] ? 'Sử dụng được' : 'Đã khóa' }}">{{ $seat['label'] }}</span>@endforeach</div>@endforeach</div>
@else<div class="alert alert-warning">Chưa có cấu hình ghế đã xác nhận. Không thể chọn ghế check-in cho chuyến thiếu cấu hình. Sức chứa dòng máy bay không thay thế sơ đồ ghế vật lý.</div>@endif
</div></section>
@can('aircraft.write', [['airport'=>$aircraft->base_airport_code]])
<details class="card mb-3 module-form-disclosure" @if($errors->any()) open @endif><summary>Công bố cấu hình ghế mới</summary><div class="card-body"><p>Nhập theo hồ sơ cấu hình thực tế. Mỗi lần lưu tạo phiên bản mới và giữ nguyên lịch sử. Máy bay còn chuyến chưa kết thúc phải xử lý kế hoạch trước khi thay cấu hình.</p>
<form method="post" action="{{ route('admin.aircrafts.configurations', $aircraft->tail_number) }}" aria-label="Cấu hình ghế">@csrf<input type="hidden" name="expected_revision" value="{{ $aircraft->revision }}">
<label for="configuration_name" class="form-label">Tên cấu hình</label><input id="configuration_name" name="name" class="form-control mb-3" maxlength="120" required value="{{ old('name') }}">
@for($i=0;$i<4;$i++)<fieldset class="row g-2 mb-3"><legend class="fs-6">Vùng ghế {{ $i+1 }}{{ $i ? ' (tùy chọn)' : '' }}</legend>
<div class="col-6 col-md-2"><label for="section_{{ $i }}_from" class="form-label">Từ hàng</label><input id="section_{{ $i }}_from" name="sections[{{ $i }}][from]" type="number" min="1" max="200" class="form-control" @required($i===0) value="{{ old('sections.'.$i.'.from') }}"></div>
<div class="col-6 col-md-2"><label for="section_{{ $i }}_to" class="form-label">Đến hàng</label><input id="section_{{ $i }}_to" name="sections[{{ $i }}][to]" type="number" min="1" max="200" class="form-control" @required($i===0) value="{{ old('sections.'.$i.'.to') }}"></div>
<div class="col-md-4"><label for="section_{{ $i }}_columns" class="form-label">Cột (vd. ABCDEF)</label><input id="section_{{ $i }}_columns" name="sections[{{ $i }}][columns]" maxlength="12" class="form-control" @required($i===0) value="{{ old('sections.'.$i.'.columns') }}"></div>
<div class="col-md-4"><label for="section_{{ $i }}_cabin" class="form-label">Hạng khoang</label><select id="section_{{ $i }}_cabin" name="sections[{{ $i }}][cabin]" class="form-select">@foreach(['ECONOMY'=>'Phổ thông','PREMIUM_ECONOMY'=>'Phổ thông đặc biệt','BUSINESS'=>'Thương gia','FIRST'=>'Hạng nhất'] as $key=>$label)<option value="{{ $key }}" @selected(old('sections.'.$i.'.cabin')===$key)>{{ $label }}</option>@endforeach</select></div>
</fieldset>@endfor
<label for="blocked_seats" class="form-label">Ghế khóa (cách bằng dấu phẩy)</label><input id="blocked_seats" name="blocked_seats" class="form-control mb-3" value="{{ is_array(old('blocked_seats')) ? implode(', ',old('blocked_seats')) : old('blocked_seats') }}" placeholder="1A, 2B">
<label for="configuration_reason" class="form-label">Lý do / hồ sơ cấu hình</label><textarea id="configuration_reason" name="reason" class="form-control mb-3" maxlength="500" required>{{ old('reason') }}</textarea><button class="btn btn-primary">Công bố cấu hình</button></form>
</div></details>
@endcan
<details class="card mb-3 module-form-disclosure" @if($errors->any()) open @endif><summary>Lịch sử cấu hình được lưu nguyên trạng</summary><div class="card-body"><div class="table-responsive"><table class="table" aria-label="Lịch sử cấu hình"><thead><tr><th>Phiên bản</th><th>Tên / ghế</th><th>Người tạo / UTC</th><th>Lý do / vùng ghế</th></tr></thead><tbody>@forelse($configurations as $c)<tr><td>v{{ $c->version }}</td><td>{{ $c->name }} · {{ $c->sellable_capacity }}/{{ $c->seat_count }}</td><td>{{ $actors[$c->created_by] ?? 'Dữ liệu cũ / khởi tạo mô phỏng' }}<br>{{ $c->created_at }}</td><td>{{ $c->reason }}<details><summary>Xem vùng ghế</summary>@foreach($c->sections as $s)<p>Hàng {{ $s['from'] }}–{{ $s['to'] }} / {{ $s['columns'] }} / @include('partials.ui-label', ['uiGroup'=>'cabin', 'uiCode'=>$s['cabin']])</p>@endforeach<p>Ghế khóa: {{ collect($c->seats)->where('enabled',false)->pluck('label')->join(', ') ?: 'Không có' }}</p></details></td></tr>@empty<tr><td colspan="4">Chưa có phiên bản.</td></tr>@endforelse</tbody></table></div>{{ $configurations->links() }}</div></details>
<section class="card mb-3"><div class="card-body"><h2 class="h5">Lịch sử hồ sơ</h2><ul>@forelse($history as $h)<li>{{ $h->created_at }} UTC · {{ config('resource-labels.audit_action.'.$h->action,$h->action) }} · {{ $actors[$h->actor_id] ?? 'Dữ liệu cũ / khởi tạo mô phỏng' }} · {{ $h->reason }}</li>@empty<li>Chưa có thay đổi được ghi.</li>@endforelse</ul>{{ $history->links() }}</div></section>

</section>
<section id="aircraft-activity" class="card module-panel module-tone-info"><div class="card-body"><h2 class="module-section-title"><i class="bi bi-calendar3 module-icon" aria-hidden="true"></i>Lịch hoạt động</h2><p class="module-empty">Chưa có dữ liệu lịch hoạt động hiển thị.</p></div></section>
<section id="aircraft-maintenance" class="card module-panel module-tone-warning"><div class="card-body module-page-heading">
<h2 class="module-section-title mb-0"><i class="bi bi-tools module-icon" aria-hidden="true"></i>Lịch bảo dưỡng máy bay</h2>
@can('maintenance.read', $aircraft->base_airport_code ? [['airport' => $aircraft->base_airport_code]] : [])
<a class="btn btn-outline-primary" href="{{ route('admin.maintenance', ['q' => $aircraft->tail_number]) }}">Xem lịch bảo dưỡng</a>
@else<span class="module-empty">Bạn chưa có quyền xem lịch bảo dưỡng.</span>@endcan
</div></section>
<section id="aircraft-incidents" class="card module-panel module-tone-danger"><div class="card-body"><h2 class="module-section-title"><i class="bi bi-exclamation-triangle module-icon" aria-hidden="true"></i>Theo dõi sự cố kỹ thuật</h2><p class="module-empty">Chưa có chức năng theo dõi sự cố kỹ thuật riêng.</p></div></section>
<details id="aircraft-statistics" class="card module-disclosure module-tone-info" open><summary><span><i class="bi bi-bar-chart module-icon" aria-hidden="true"></i>Thống kê hoạt động máy bay</span></summary><div class="card-body">
<div class="row g-3 mb-3"><div class="col-md-6"><div class="card card-body"><strong>Giờ bay: {{ $aircraft->total_flight_hours }} · Chu kỳ: {{ $aircraft->total_flight_cycles }}</strong><span>Sau bảo dưỡng: {{ number_format($aircraft->total_flight_hours - $aircraft->maintenance_base_hours, 2) }} giờ / {{ $aircraft->total_flight_cycles - $aircraft->maintenance_base_cycles }} chu kỳ.</span><span>Ngưỡng trong môi trường mô phỏng: chú ý 2.700 giờ / 1.350 chu kỳ; chặn khai thác 3.000 giờ / 1.500 chu kỳ.</span>@if($aircraft->needsMaintenance())<strong class="text-danger">Quá hạn — không được khai thác.</strong>@endif</div></div>
<div class="col-md-6"><div class="card card-body"><strong>Đối chiếu số dư và sổ sử dụng: {{ $reconciliation['matched'] ? 'Khớp' : 'Có chênh lệch' }}</strong><span>Số dư tiếp nhận: {{ $aircraft->opening_flight_hours }} giờ / {{ $aircraft->opening_flight_cycles }} chu kỳ.</span><span>{{ $aircraft->opening_reason ?? 'Chưa có nguồn số dư tiếp nhận.' }}</span><span>Chênh lệch: {{ $reconciliation['hours_difference'] }} giờ / {{ $reconciliation['cycles_difference'] }} chu kỳ.</span></div></div></div>
<section class="card mb-3"><div class="card-body"><h2 class="h5">Sổ sử dụng máy bay</h2><p>Giờ bay tính từ mốc cất/hạ cánh thực tế (UTC), làm tròn 0,01 giờ cho từng chuyến; mỗi chuyến một chu kỳ. Nguồn DEMO là mô phỏng tích hợp.</p>
<form method="get" class="row g-2 mb-3"><div class="col-md-3"><label for="from" class="form-label">Nhận từ ngày (UTC)</label><input id="from" name="from" type="date" class="form-control" value="{{ request('from') }}"></div><div class="col-md-3"><label for="to" class="form-label">Đến ngày UTC</label><input id="to" name="to" type="date" class="form-control" value="{{ request('to') }}"></div><div class="col-md-3"><label for="source" class="form-label">Nguồn</label><select id="source" name="source" class="form-select"><option value="">Tất cả</option>@foreach(['legacy','OPERATIONS','AODB','FIDS','DEMO'] as $source)<option value="{{ $source }}" @selected(request('source')===$source)>@include('partials.ui-label', ['uiGroup'=>'source', 'uiCode'=>$source])</option>@endforeach</select></div><div class="col-md-3 align-self-end"><button class="btn btn-primary">Lọc lịch sử</button></div></form>
<div class="table-responsive"><table class="table" aria-label="Sổ sử dụng"><thead><tr><th>Chuyến</th><th>Giờ / chu kỳ</th><th>Nguồn / mã sự kiện</th><th>Phát sinh / nhận (UTC)</th></tr></thead><tbody>@forelse($usage as $u)<tr><td>{{ $u->flight_number }}</td><td>{{ $u->hours }} / {{ $u->cycles }}</td><td>@include('partials.ui-label', ['uiGroup'=>'source', 'uiCode'=>$u->source])<br>{{ $u->external_id }}</td><td>{{ $u->occurred_at ?? 'Chưa có (dữ liệu cũ)' }}<br>{{ $u->received_at ?? $u->created_at }}</td></tr>@empty<tr><td colspan="4">Chưa có số liệu chuyến bay trong bộ lọc.</td></tr>@endforelse</tbody></table></div>{{ $usage->links() }}</div></section>
<section class="card mb-3"><div class="card-body"><h2 class="h5">Cảnh báo đã ghi nhận</h2><ul>@forelse($alerts as $alert)<li>{{ $alert->created_at }} UTC · {{ $alert->level === 'OVERDUE' ? 'Quá hạn' : 'Đến ngưỡng chú ý' }} · {{ $alert->hours_since_maintenance }} giờ / {{ $alert->cycles_since_maintenance }} chu kỳ kể từ bảo dưỡng.</li>@empty<li>Chưa có cảnh báo từ sổ sử dụng.</li>@endforelse</ul>{{ $alerts->links() }}</div></section>

</div></details>
</div>
@endsection
