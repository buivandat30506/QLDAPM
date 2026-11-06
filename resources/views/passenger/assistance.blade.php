@extends('layouts.portal')
@section('title', 'Yêu cầu hỗ trợ')
@section('content')
<form method="get" class="d-flex gap-2 mb-3"><select aria-label="Lọc trạng thái" name="status" class="form-select"><option value="">Mọi trạng thái</option>@foreach(['ROUTED' => 'Đã chuyển bộ phận', 'COMPLETED' => 'Đã hoàn tất', 'CANCELLED' => 'Đã hủy'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select><select aria-label="Lọc bộ phận" name="department" class="form-select"><option value="">Mọi bộ phận</option><option value="AIRPORT_SUPPORT" @selected(request('department') === 'AIRPORT_SUPPORT')>Hỗ trợ sân bay</option><option value="CUSTOMER_CARE" @selected(request('department') === 'CUSTOMER_CARE')>Chăm sóc khách hàng</option></select><button class="btn btn-primary">Lọc</button></form>
<div class="table-responsive"><table class="table bg-white"><thead><tr><th>Hành khách / chuyến</th><th>Loại</th><th>Bộ phận / trạng thái</th><th>Chi tiết</th></tr></thead><tbody>@forelse($requests as $item)<tr><td>{{ $item->passenger->full_name }} / {{ $item->flight?->flight_number ?? 'Hỗ trợ chung' }}</td><td>{{ ['WHEELCHAIR'=>'Xe lăn', 'MEDICAL'=>'Y tế', 'UNACCOMPANIED_MINOR'=>'Trẻ đi một mình', 'OTHER'=>'Khác'][$item->category] }}</td><td>{{ $item->department === 'AIRPORT_SUPPORT' ? 'Hỗ trợ sân bay' : 'Chăm sóc khách hàng' }} / {{ ['ROUTED'=>'Đã chuyển bộ phận', 'COMPLETED'=>'Đã hoàn tất', 'CANCELLED'=>'Đã hủy'][$item->status] }}</td><td><a href="{{ route('passenger.assistance.show', $item->id) }}">Xem yêu cầu</a></td></tr>@empty<tr><td colspan="4">Không có yêu cầu phù hợp trong phạm vi được cấp.</td></tr>@endforelse</tbody></table></div>{{ $requests->links() }}
@if($canCreate)
<h2 class="h5 mt-4">Gửi yêu cầu hỗ trợ</h2>
@if($profiles->isEmpty())<p>Hãy <a href="{{ route('passenger.profiles') }}">tạo hồ sơ hành khách</a> trước khi gửi yêu cầu.</p>@else
<form method="post" action="{{ route('passenger.assistance.store') }}" class="card p-4">@csrf
<label for="passenger_id">Hành khách</label><select id="passenger_id" name="passenger_id" class="form-select mb-2">@foreach($profiles as $profile)<option value="{{ $profile->id }}">{{ $profile->full_name }}</option>@endforeach</select>
<label for="flight_id">Chuyến bay</label><select id="flight_id" name="flight_id" class="form-select mb-2"><option value="">Hỗ trợ chung (chưa gắn chuyến)</option>@foreach($flights as $flight)<option value="{{ $flight->id }}">{{ $flight->flight_number }} / {{ $flight->flight_date->format('Y-m-d') }} / {{ $flight->route->route_code }}</option>@endforeach</select>
<label for="category">Loại hỗ trợ</label><select id="category" name="category" class="form-select mb-2">@foreach(['WHEELCHAIR'=>'Xe lăn', 'MEDICAL'=>'Y tế', 'UNACCOMPANIED_MINOR'=>'Trẻ đi một mình', 'OTHER'=>'Khác'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
<label for="details">Thông tin cần hỗ trợ</label><textarea id="details" name="details" class="form-control mb-3" maxlength="2000" required>{{ old('details') }}</textarea><button class="btn btn-primary">Xác nhận gửi yêu cầu</button></form>
@endif
@endif
@endsection
