@extends('layouts.admin')
@section('title', 'Luồng nghiệp vụ M01–M06')
@section('page_title', 'Luồng nghiệp vụ và kịch bản demo')
@section('content')
<p>Giờ hiển thị theo UTC. Dữ liệu giả lập có tiền tố DM / DEMO. Đăng nhập vai trò tương ứng để thao tác.</p>
<div class="card mb-3"><div class="card-header">M01 · Dữ liệu chủ và phiên bản</div><div class="card-body">
<table class="table"><thead><tr><th>Nhóm / Mã</th><th>Trạng thái</th><th>Phiên bản</th><th>Thao tác</th></tr></thead><tbody>
@foreach($masters as $item)<tr><td>{{ $item->category }} / {{ $item->code }}</td><td>{{ $item->status }}</td><td>{{ $item->version }}</td><td>
@foreach(['DRAFT'=>['submit','Gửi duyệt'],'PENDING'=>['approve','Phê duyệt'],'APPROVED'=>['publish','Công bố']] as $status => [$action,$label])
@if($item->status === $status) @include('admin.partials.workflow-action',['kind'=>'master','id'=>$item->id,'action'=>$action,'label'=>$label]) @endif
@endforeach</td></tr>@endforeach</tbody></table>
<details><summary>Thêm dữ liệu chủ</summary><form method="POST" action="{{ route('admin.workflows.act') }}" class="row g-2 mt-2">@csrf
<input type="hidden" name="kind" value="master"><input type="hidden" name="action" value="create">
<label class="col">Nhóm<input class="form-control" name="category" required placeholder="CABIN"></label><label class="col">Mã<input class="form-control" name="code" required></label><label class="col">Tên<input class="form-control" name="name" required></label><div class="col"><button class="btn btn-primary mt-4">Lưu nháp</button></div></form></details>
</div></div>
<div class="card mb-3"><div class="card-header">M02 · Kiểm tra, phê duyệt và công bố lịch bay</div><div class="card-body table-responsive">
<table class="table"><thead><tr><th>Số hiệu</th><th>Nguồn lực</th><th>Ngày hiệu lực</th><th>Trạng thái / phiên bản</th><th>Thao tác</th></tr></thead><tbody>
@foreach($schedules as $item)<tr><td>{{ $item->flight_number }}<small class="d-block">{{ $item->route->route_code }}</small></td><td>{{ $item->aircraft_tail ?? 'Thiếu máy bay' }} / {{ $item->resource_id ? 'Cửa #'.$item->resource_id : 'Thiếu cửa' }}</td><td>{{ $item->effective_from }} — {{ $item->effective_to }}</td><td>{{ $item->status }} / v{{ $item->version }}</td><td>
@foreach(['DRAFT'=>['submit','Kiểm tra & gửi duyệt'],'PENDING'=>['approve','Phê duyệt'],'APPROVED'=>['publish','Công bố']] as $status => [$action,$label])
@if($item->status === $status) @include('admin.partials.workflow-action',['kind'=>'schedule','id'=>$item->id,'action'=>$action,'label'=>$label]) @endif
@endforeach
@if($item->status === 'DRAFT')
@include('admin.partials.edit-form', ['action'=>route('admin.schedules.update',$item->id),'record'=>$item,'fields'=>[
    'flight_number'=>['label'=>'Số hiệu'],
    'route_id'=>['label'=>'Đường bay','options'=>\App\Models\Route::pluck('route_code','id')],
    'model_id'=>['label'=>'Dòng máy bay','options'=>\App\Models\AircraftModel::pluck('model_code','id')],
    'aircraft_tail'=>['label'=>'Máy bay','options'=>\App\Models\Aircraft::pluck('tail_number','tail_number')],
    'resource_id'=>['label'=>'Cửa khởi hành','options'=>\App\Models\AirportResource::where('resource_type','GATE')->get()->mapWithKeys(fn($gate)=>[$gate->id=>$gate->airport_code.' / '.$gate->resource_code])],
    'departure_time_utc'=>['label'=>'Giờ cất cánh UTC','type'=>'time'], 'arrival_time_utc'=>['label'=>'Giờ hạ cánh UTC','type'=>'time'],
    'days_of_week'=>['label'=>'Ngày ISO (phân cách dấu phẩy)','format'=>'csv'],
    'effective_from'=>['label'=>'Hiệu lực từ','type'=>'date'], 'effective_to'=>['label'=>'Hiệu lực đến','type'=>'date'],
]])
@endif
</td></tr>@endforeach</tbody></table><a href="{{ route('admin.flights') }}">Tạo lịch nháp / Khởi tạo chuyến từ lịch đã công bố</a>
</div></div>
<div class="card mb-3"><div class="card-header">M03 · Sự kiện và phương án điều hành</div><div class="card-body">
<table class="table"><thead><tr><th>Nguồn</th><th>Mã sự kiện</th><th>Kết quả</th><th>Thông báo</th></tr></thead><tbody>@foreach($events as $item)<tr><td>{{ $item->source }}</td><td>{{ $item->external_id }}</td><td>{{ $item->status }}</td><td>{{ $item->message }}</td></tr>@endforeach</tbody></table>
<details><summary>Nhận sự kiện giả lập</summary><form method="POST" action="{{ route('admin.workflows.act') }}" class="row g-2 mt-2">@csrf
<input type="hidden" name="kind" value="event"><input type="hidden" name="source" value="DEMO">
<label class="col">Chuyến bay<select name="flight_id" class="form-select">@foreach($flights as $flight)<option value="{{ $flight->id }}">{{ $flight->flight_number }} / {{ $flight->flight_date->format('Y-m-d') }}</option>@endforeach</select></label>
<label class="col">Mã sự kiện<input name="external_id" class="form-control" value="demo-{{ now()->format('His') }}" required></label><label class="col">Trạng thái<select name="flight_status" class="form-select"><option>DELAYED</option><option>CANCELLED</option><option>BOARDING</option></select></label>
<label class="col">Lý do<input name="reason" class="form-control" required></label><div><button class="btn btn-primary">Nhận sự kiện</button></div></form></details>
<details class="mt-3"><summary>Chọn phương án xử lý bất thường</summary><form method="POST" action="{{ route('admin.workflows.act') }}" class="row g-2 mt-2">@csrf
<input type="hidden" name="kind" value="resolve"><label class="col-md-4">Chuyến bay<select name="id" class="form-select">@foreach($flights->whereIn('flight_status',['SCHEDULED','BOARDING','DELAYED']) as $flight)<option value="{{ $flight->id }}">{{ $flight->flight_number }} / {{ $flight->flight_date->format('Y-m-d') }}</option>@endforeach</select></label>
<label class="col-md-4">Phương án<select name="action" class="form-select"><option value="DELAY">Đổi giờ</option><option value="CANCEL">Hủy chuyến</option><option value="REASSIGN">Đổi máy bay và giờ</option></select></label>
<label class="col-md-4">Máy bay (khi đổi máy bay)<input name="aircraft_tail" class="form-control" placeholder="VN-D104"></label>
<label class="col-md-4">Cất cánh mới (UTC)<input type="datetime-local" name="scheduled_dep_utc" class="form-control"></label><label class="col-md-4">Hạ cánh mới (UTC)<input type="datetime-local" name="scheduled_arr_utc" class="form-control"></label><label class="col-md-4">Lý do<input name="reason" class="form-control" required></label><div><button class="btn btn-primary">Kiểm tra và áp dụng</button></div></form></details>
</div></div>
<div class="card mb-3"><div class="card-header">M04 · Đội máy bay</div><div class="card-body"><a href="{{ route('admin.aircrafts') }}">Xem hồ sơ và cảnh báo đội bay</a><p class="mt-2 mb-0">Giờ bay/chu kỳ được ghi thành bản ghi sử dụng có khóa chuyến bay duy nhất khi ARRIVED; gửi lại không cộng trùng.</p></div></div>
<div class="card mb-3"><div class="card-header">M05 · Lệnh bảo dưỡng và giải phóng kỹ thuật</div><div class="card-body">
@foreach($orders as $item)<details class="border rounded p-2 mb-2"><summary>{{ $item->order_code }} · {{ $item->aircraft_tail }} · {{ $item->status }}</summary><p class="mt-2">{{ $item->task }} · Hạn: {{ $item->due_at_utc }}</p>
<form method="POST" action="{{ route('admin.workflows.act') }}" class="row g-2">@csrf<input type="hidden" name="kind" value="maintenance"><input type="hidden" name="id" value="{{ $item->id }}"><input type="hidden" name="action" value="start">
<label class="col">Kỹ thuật viên<input name="technician" class="form-control" value="{{ $item->technician }}" required></label><label class="col">Vị trí<input name="location" class="form-control" value="{{ $item->location }}" required></label><label class="col">Vật tư<select name="materials_ready" class="form-select"><option value="1">Đủ</option><option value="0">Thiếu</option></select></label><div><button class="btn btn-sm btn-outline-primary">Bắt đầu / bổ sung vật tư</button></div></form>
<form method="POST" action="{{ route('admin.workflows.act') }}" class="row g-2 mt-2">@csrf<input type="hidden" name="kind" value="maintenance"><input type="hidden" name="id" value="{{ $item->id }}"><input type="hidden" name="action" value="complete">
<label class="col">Kết quả<input name="result" class="form-control" required></label><label class="col">Mã hồ sơ/bằng chứng<input name="evidence" class="form-control" required></label><label class="col">Xác nhận kỹ thuật<input type="checkbox" name="technical_confirmed" value="1" required></label><div><button class="btn btn-sm btn-outline-primary">Hoàn tất công việc</button></div></form>
@include('admin.partials.workflow-action',['kind'=>'maintenance','id'=>$item->id,'action'=>'approve','label'=>'Phê duyệt kỹ thuật'])
@include('admin.partials.workflow-action',['kind'=>'maintenance','id'=>$item->id,'action'=>'release','label'=>'Giải phóng máy bay'])
</details>@endforeach
<details><summary>Lập lịch bảo dưỡng</summary><form method="POST" action="{{ route('admin.workflows.act') }}" class="row g-2 mt-2">@csrf<input type="hidden" name="kind" value="maintenance"><input type="hidden" name="action" value="plan">
<label class="col-md-4">Mã lệnh<input name="order_code" class="form-control" required></label><label class="col-md-4">Máy bay<input name="aircraft_tail" class="form-control" required></label><label class="col-md-4">Hạng mục<input name="task" class="form-control" required></label>
<label class="col-md-4">Bắt đầu UTC<input name="starts_at_utc" type="datetime-local" class="form-control" required></label><label class="col-md-4">Kết thúc UTC<input name="ends_at_utc" type="datetime-local" class="form-control" required></label><label class="col-md-4">Hạn UTC<input name="due_at_utc" type="datetime-local" class="form-control" required></label><div><button class="btn btn-primary">Kiểm tra & lập lịch</button></div></form></details>
</div></div>
<div class="card mb-3"><div class="card-header">M06 · Phê duyệt, xác nhận và thay thế tổ bay</div><div class="card-body table-responsive">
<table class="table"><thead><tr><th>Chuyến bay</th><th>Nhân sự / vị trí</th><th>Trạng thái / thông báo nội bộ</th><th>Thao tác</th></tr></thead><tbody>@foreach($assignments as $item)<tr><td>{{ $item->flight->flight_number }}</td><td>{{ $item->crewMember->full_name }} / {{ $item->assigned_position }}</td><td>{{ config('resource-labels.crew_status.'.$item->status,$item->status) }} @foreach($crewNotifications->where('assignment_id',$item->id) as $notification)<p class="small mb-1">{{ $notification->message }} · {{ $notification->created_at }} UTC · {{ $notification->read_at ? 'Đã xác nhận' : 'Chưa xác nhận' }}</p>@endforeach</td><td>
@if($item->status === 'ASSIGNED') @include('admin.partials.workflow-action',['kind'=>'crew-approve','id'=>$item->flight_id,'action'=>'approve','label'=>'Duyệt phương án chuyến']) @endif
@if($item->status === 'APPROVED') @include('admin.partials.workflow-action',['kind'=>'crew-confirm','id'=>$item->id,'action'=>'confirm','label'=>'Xác nhận phân công']) @endif
@can('crew.write')
@if($item->status !== 'REPLACED')<details><summary>Thay thế nhân sự</summary><form method="POST" action="{{ route('admin.workflows.act') }}">@csrf<input type="hidden" name="kind" value="crew-replace"><input type="hidden" name="id" value="{{ $item->id }}"><label>Người thay thế<select name="crew_id" class="form-select">@foreach(\App\Models\CrewMember::where('status','AVAILABLE')->get() as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->crew_code }} / {{ $candidate->full_name }}</option>@endforeach</select></label><label>Lý do<input name="reason" class="form-control" required></label><button class="btn btn-sm btn-primary mt-2">Kiểm tra người thay thế</button></form></details>@endif
@endcan
</td></tr>@endforeach</tbody></table><a href="{{ route('admin.crew') }}">Hồ sơ, năng định và phân công mới</a></div></div>
<div class="card mb-3"><div class="card-header">Nhật ký gần đây</div><div class="card-body"><table class="table"><thead><tr><th>Thời gian UTC</th><th>Đối tượng</th><th>Thao tác</th><th>Lý do</th></tr></thead><tbody>@foreach($history as $item)<tr><td>{{ $item->created_at }}</td><td>{{ $item->entity_type }} / {{ $item->entity_id }}</td><td>{{ $item->action }}</td><td>{{ $item->reason }}</td></tr>@endforeach</tbody></table></div></div>
@endsection
