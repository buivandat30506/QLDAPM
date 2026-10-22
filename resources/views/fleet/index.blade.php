@extends('layouts.admin')
@section('title', 'Quản lý máy bay')
@section('page_title', 'Quản lý máy bay')
@section('content')
<div class="sitemap-module-page aircraft-module-page">

<div class="module-page-heading"><h1 class="h3">Danh sách máy bay</h1>
@if(app(\App\Shared\Security\AccessControl::class)->scopes(auth()->user(), 'maintenance.read')->contains(fn ($scope) => in_array($scope->scope_type, ['global', 'airport'])))
<a class="btn btn-outline-primary" href="{{ route('admin.maintenance') }}"><i class="bi bi-tools me-2" aria-hidden="true"></i>Lịch bảo dưỡng máy bay</a>
@endif
</div>
<form method="get" class="row g-2 mb-3" aria-label="Lọc đội máy bay">
<div class="col-md-3"><label for="search" class="form-label">Số đăng ký</label><input id="search" name="search" class="form-control" value="{{ request('search') }}"></div>
<div class="col-md-3"><label for="status_filter" class="form-label">Trạng thái</label><select id="status_filter" name="status" class="form-select"><option value="">Tất cả</option>@foreach(['OPERATIONAL'=>'Khả dụng','MAINTENANCE'=>'Bảo dưỡng','GROUNDED'=>'Ngừng khai thác'] as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-3"><label for="base_filter" class="form-label">Cơ sở</label><select id="base_filter" name="base" class="form-select"><option value="">Tất cả cơ sở</option>@foreach($airports as $airport)<option @selected(request('base')===$airport->iata_code)>{{ $airport->iata_code }}</option>@endforeach</select></div>
<div class="col-md-3"><label for="sort_filter" class="form-label">Sắp xếp</label><select id="sort_filter" name="sort" class="form-select">@foreach(['tail_number'=>'Số đăng ký','total_flight_hours'=>'Giờ bay','total_flight_cycles'=>'Chu kỳ'] as $key=>$label)<option value="{{ $key }}" @selected(request('sort')===$key)>{{ $label }}</option>@endforeach</select></div>
<div class="col-12 d-flex gap-2 align-items-center"><label><input type="checkbox" name="alert" value="1" @checked(request('alert')==='1')> Đến ngưỡng chú ý</label><button class="btn btn-primary">Lọc</button><a href="{{ route('admin.aircrafts') }}" class="btn btn-outline-secondary">Xóa lọc</a></div>
</form>
<div class="card module-table-wrap table-responsive"><table class="table bg-white"><thead><tr><th>Số đăng ký</th><th>Dòng / cơ sở</th><th>Cấu hình hiện tại</th><th>Giờ / chu kỳ</th><th>Khả dụng</th></tr></thead><tbody>
@forelse($aircrafts as $a)<tr><td><a href="{{ route('admin.aircrafts.show', $a->tail_number) }}">{{ $a->tail_number }}</a></td><td>{{ $a->model->model_code }} / {{ $a->base_airport_code ?? 'Chưa xác định' }}</td><td>@if($a->configuration)v{{ $a->configuration->version }} · {{ $a->configuration->sellable_capacity }} ghế sử dụng@else<span class="text-warning-emphasis">Chưa xác nhận sơ đồ ghế</span>@endif</td><td>{{ $a->total_flight_hours }} / {{ $a->total_flight_cycles }}</td><td>{{ ['OPERATIONAL'=>'Khả dụng','MAINTENANCE'=>'Bảo dưỡng','GROUNDED'=>'Ngừng khai thác'][$a->operational_status] }} @if($a->needsMaintenance())<span class="badge bg-danger">Quá hạn</span>@endif</td></tr>
@empty<tr><td colspan="5">Không có máy bay trong phạm vi hoặc bộ lọc đã chọn.</td></tr>@endforelse
</tbody></table></div>{{ $aircrafts->links() }}
@if(app(\App\Shared\Security\AccessControl::class)->scopes(auth()->user(), 'aircraft.write')->isNotEmpty())
<section class="card my-3"><div class="card-body"><h2 class="h5">Thêm máy bay</h2><form method="post" action="{{ route('admin.aircrafts.store') }}">@csrf @include('fleet.fields')<button class="btn btn-primary mt-3">Lưu máy bay</button></form></div></section>
@endif
@can('aircraft.write')
<section class="card my-3"><div class="card-body"><h2 class="h5">Thêm dòng máy bay</h2><form method="post" action="{{ route('admin.aircraft_models.store') }}" class="row g-3">@csrf
@foreach(['model_code'=>'Mã dòng','manufacturer'=>'Hãng sản xuất','seat_capacity'=>'Sức chứa tối đa','turnaround_time_minutes'=>'Quay đầu tối thiểu (phút)'] as $field=>$label)<div class="col-md-3"><label for="new_{{ $field }}" class="form-label">{{ $label }}</label><input id="new_{{ $field }}" name="{{ $field }}" class="form-control" type="{{ in_array($field,['seat_capacity','turnaround_time_minutes']) ? 'number' : 'text' }}" required value="{{ old($field) }}"></div>@endforeach
<div><button class="btn btn-outline-primary">Lưu dòng máy bay</button></div></form></div></section>
@endcan
</div>
@endsection
