@extends('layouts.portal')
@section('title', 'Hồ sơ: '.$profile->full_name)
@section('content')
<p>Giấy tờ: ****{{ substr($profile->id_card_passport, -4) }}. Thông tin đầy đủ chỉ sử dụng trong thao tác được cấp quyền.</p>
@if($canWrite)
<form method="post" action="{{ route('passenger.profiles.update', $profile->id) }}" class="card p-4 mb-4">@csrf @method('PUT')
<div class="row g-3">@include('passenger.profile-fields')</div>
<button class="btn btn-primary mt-3">Xác nhận cập nhật hồ sơ</button></form>
<h2 class="h5">Lựa chọn xử lý dữ liệu</h2>
<form method="post" action="{{ route('passenger.profiles.consent', $profile->id) }}" class="card p-4 mb-4">@csrf
<label for="purpose">Mục đích</label><select class="form-select mb-2" name="purpose" id="purpose"><option value="SERVICE">Cung cấp dịch vụ</option><option value="MARKETING">Thông tin tiếp thị</option></select>
<label for="granted">Lựa chọn</label><select class="form-select mb-3" name="granted" id="granted"><option value="1">Đồng ý</option><option value="0">Rút lại sự đồng ý</option></select><button class="btn btn-primary">Xác nhận lựa chọn</button></form>
@else
<p>Bạn có quyền xem hồ sơ trong phạm vi được cấp. Việc chỉnh sửa và lựa chọn consent cần quyền quản lý hồ sơ.</p>
@endif
<table class="table bg-white"><thead><tr><th>Mục đích</th><th>Lựa chọn</th><th>Thời gian UTC</th><th>Phiên bản</th></tr></thead><tbody>@forelse($consents as $consent)<tr><td>{{ $consent->purpose === 'SERVICE' ? 'Dịch vụ' : 'Tiếp thị' }}</td><td>{{ $consent->granted ? 'Đồng ý' : 'Đã rút lại' }}</td><td>{{ $consent->recorded_at_utc }}</td><td>{{ $consent->policy_version }}</td></tr>@empty<tr><td colspan="4">Hồ sơ cũ chưa có lịch sử consent.</td></tr>@endforelse</tbody></table>
<a href="{{ route('passenger.assistance') }}">Gửi và theo dõi yêu cầu hỗ trợ</a>
@endsection
