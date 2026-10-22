@extends('layouts.admin')
@section('title', 'Quản lý chuyến bay')
@section('page_title', 'Quản lý chuyến bay')
@section('content')
@php
    $moduleAccess = app(\App\Shared\Security\AccessControl::class);
    $moduleCanReadRoutes = $moduleAccess->scopes(auth()->user(), 'route.read')->contains(fn ($scope) => in_array($scope->scope_type, ['global', 'airport']));
    $moduleCanReadSchedules = $moduleAccess->scopes(auth()->user(), 'schedule.read')->contains(fn ($scope) => in_array($scope->scope_type, ['global', 'airport']));
    $creationCrewAllowed = in_array(old('schedule_id', $schedules->first()?->id), $crewAssignableScheduleIds ?? [], true);
@endphp
<div class="sitemap-module-page flight-module-page">
<h1 class="h3">Quản lý chuyến bay</h1>
@if($moduleCanReadRoutes)
<section class="card module-panel module-tone-info"><div class="card-body module-page-heading">
<h2 class="module-section-title mb-0"><i class="bi bi-signpost-split module-icon" aria-hidden="true"></i>Tuyến đường bay</h2><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.routes') }}">Xem tất cả</a>
</div></section>
@endif
<section class="module-section" aria-labelledby="flight-search-title">
<h2 id="flight-search-title" class="module-section-title">Tra cứu chuyến bay</h2>
<form method="get" class="row g-2 mb-3">
<div class="col-md-4"><label for="search" class="form-label">Số hiệu chuyến</label><input id="search" name="search" value="{{ request('search') }}" class="form-control"></div>
<div class="col-md-3"><label for="date" class="form-label">Ngày UTC</label><input id="date" name="date" type="date" value="{{ request('date') }}" class="form-control"></div>
<div class="col-md-3"><label for="filter_status" class="form-label">Trạng thái</label><select id="filter_status" name="flight_status" class="form-select"><option value="">Tất cả</option>@foreach(['SCHEDULED'=>'Theo lịch','BOARDING'=>'Đang lên máy bay','DELAYED'=>'Chậm','DEPARTED'=>'Đã khởi hành','ARRIVED'=>'Đã hạ cánh','CANCELLED'=>'Đã hủy'] as $s=>$label)<option value="{{ $s }}" @selected(request('flight_status')===$s)>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-2 align-self-end"><button class="btn btn-primary">Lọc chuyến</button></div></form>
</section>
<section class="module-section" aria-labelledby="flight-list-title">
<h2 id="flight-list-title" class="module-section-title">Danh sách chuyến bay</h2>
<div class="card module-table-wrap">
<div class="table-responsive"><table class="table table-striped"><thead><tr><th scope="col">Chuyến</th><th scope="col">Tuyến</th><th scope="col">Máy bay / cửa</th><th scope="col">Kế hoạch UTC</th><th scope="col">Dự kiến UTC</th><th scope="col">Trạng thái</th></tr></thead><tbody>
@forelse($flights as $f)
<tr>
<td><a href="{{ route('admin.flights.show',$f->id) }}">{{ $f->flight_number }}</a></td>
<td>{{ $f->route->origin_airport_code }} → {{ $f->route->dest_airport_code }}</td>
<td>{{ $f->aircraft_tail }} / {{ $f->gate_number ?: 'Thiếu cửa' }}</td>
<td>{{ $f->scheduled_dep_utc->format('d/m/Y H:i') }} → {{ $f->scheduled_arr_utc->format('H:i') }}</td>
<td>{{ $f->estimated_dep_utc?->format('d/m H:i') ?: 'Chưa nhận' }}</td>
<td>@include('operations.status',['status'=>$f->flight_status]) @if($f->closed_at_utc)<span class="badge bg-dark">Đã đóng</span>@endif</td>
</tr>
@empty<tr><td colspan="6">Không có chuyến trong phạm vi/bộ lọc.</td></tr>@endforelse
</tbody></table></div>

</div>{{ $flights->links() }}
</section>
@can('flight.write')<details id="flight-create-panel" class="card module-disclosure module-tone-info" @if($errors->any()) open @endif><summary><span><i class="bi bi-plus-lg module-icon" aria-hidden="true"></i>Khởi tạo chuyến bay</span></summary><div class="card-body"><h2 class="h5">Khởi tạo từ lịch đã công bố</h2><form method="post" action="{{ route('admin.flights.store') }}" data-flight-create-form>@csrf
<label for="schedule_id" class="form-label">Lịch công bố</label><select id="schedule_id" name="schedule_id" class="form-select" required>@foreach($schedules as $s)<option value="{{ $s->id }}" data-crew-allowed="{{ in_array($s->id, $crewAssignableScheduleIds ?? [], true) ? 'true' : 'false' }}" @selected(old('schedule_id') === $s->id)>{{ $s->flight_number }} — {{ $s->route->origin_airport_code }} → {{ $s->route->dest_airport_code }} — phiên bản {{ $s->version }}</option>@endforeach</select>
<label for="service_date" class="form-label mt-2">Ngày khai thác theo lịch nguồn</label><input id="service_date" type="date" name="service_date" value="{{ old('service_date',now('UTC')->toDateString()) }}" class="form-control" required>
@if($canAssignCrewOnCreate ?? false)
    @include('operations.create-crew-fields')
@endif
<button class="btn btn-primary mt-3">Khởi tạo chuyến</button>
@if($schedules->isEmpty())<p class="text-muted">Chưa có lịch trình đã công bố phù hợp.</p>@endif</form></div></details>@endcan

@if($moduleCanReadSchedules)
<a class="card module-panel module-tone-info flight-schedule-link" href="{{ route('admin.schedules') }}">
    <span class="module-section-title mb-0"><i class="bi bi-calendar3 module-icon" aria-hidden="true"></i>Lịch trình bay</span>
    <i class="bi bi-arrow-right" aria-hidden="true"></i>
</a>
@endif
</div>
@endsection
