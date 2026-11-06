@extends('layouts.portal')
@section('title', 'Chi tiết yêu cầu hỗ trợ')
@section('content')
<p>Hành khách: {{ $assistance->passenger->full_name }} / Chuyến: {{ $assistance->flight?->flight_number ?? 'Hỗ trợ chung' }}</p>
<p>Bộ phận: {{ $assistance->department === 'AIRPORT_SUPPORT' ? 'Hỗ trợ sân bay' : 'Chăm sóc khách hàng' }}. Trạng thái: {{ ['ROUTED'=>'Đã chuyển bộ phận', 'COMPLETED'=>'Đã hoàn tất', 'CANCELLED'=>'Đã hủy'][$assistance->status] }}.</p>
<div class="card p-4 mb-3"><p>{{ $assistance->details }}</p>@if($assistance->resolution)<p>Kết quả: {{ $assistance->resolution }}</p>@endif</div>
@if(!in_array($assistance->status, ['COMPLETED','CANCELLED']))
@if($canEdit)
<form method="post" action="{{ route('passenger.assistance.act', $assistance->id) }}" class="card p-4 mb-3">@csrf<input type="hidden" name="action" value="update"><label for="updated_details">Thông tin cập nhật</label><textarea id="updated_details" name="details" class="form-control mb-3" maxlength="2000" required>{{ $assistance->details }}</textarea><button class="btn btn-primary">Lưu cập nhật</button></form>
<form method="post" action="{{ route('passenger.assistance.act', $assistance->id) }}" class="mb-3">@csrf<input type="hidden" name="action" value="cancel"><button class="btn btn-outline-danger">Xác nhận hủy yêu cầu</button></form>
@endif
@if($canManage)
<form method="post" action="{{ route('passenger.assistance.act', $assistance->id) }}" class="card p-4 mb-3">@csrf<input type="hidden" name="action" value="route"><label for="department">Chuyển bộ phận</label><select id="department" name="department" class="form-select mb-3"><option value="CUSTOMER_CARE">Chăm sóc khách hàng</option><option value="AIRPORT_SUPPORT">Hỗ trợ sân bay</option></select><button class="btn btn-primary">Xác nhận chuyển</button></form>
<form method="post" action="{{ route('passenger.assistance.act', $assistance->id) }}" class="card p-4">@csrf<input type="hidden" name="action" value="complete"><label for="resolution">Kết quả xử lý</label><textarea id="resolution" name="resolution" class="form-control mb-3" maxlength="2000" required></textarea><button class="btn btn-primary">Xác nhận hoàn tất</button></form>
@endif
@endif
@endsection
