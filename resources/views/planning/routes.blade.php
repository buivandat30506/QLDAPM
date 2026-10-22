@extends('layouts.admin')
@section('title','Tuyến đường bay')
@section('page_title', 'Tuyến đường bay')
@section('content')
@php
    $routePageAccess = app(\App\Shared\Security\AccessControl::class);
    $routePageCanReadFlights = $routePageAccess->scopes(auth()->user(), 'flight.read')
        ->contains(fn ($scope) => in_array($scope->scope_type, ['global', 'airport', 'flight']));
    $routePageCanReadSchedules = $routePageAccess->scopes(auth()->user(), 'schedule.read')
        ->contains(fn ($scope) => in_array($scope->scope_type, ['global', 'airport']));
@endphp
<div class="sitemap-module-page flight-module-page">
<div class="module-page-heading">
    <h1 class="h3">Tuyến đường bay</h1>
    @if($routePageCanReadFlights || $routePageCanReadSchedules)
        <a class="btn btn-outline-primary" href="{{ route($routePageCanReadFlights ? 'admin.flights' : 'admin.schedules') }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Quay lại Quản lý chuyến bay</a>
    @endif
</div>
<form class="row g-2 mb-3" method="GET">
    <div class="col-md-4"><label class="form-label" for="search">Tìm mã đường bay</label><input class="form-control" id="search" name="search" value="{{ request('search') }}"></div>
    <div class="col-md-3"><label class="form-label" for="status">Trạng thái</label><select class="form-select" name="status" id="status"><option value="">Tất cả</option><option value="ACTIVE" @selected(request('status')==='ACTIVE')>Hoạt động</option><option value="SUSPENDED" @selected(request('status')==='SUSPENDED')>Tạm ngừng</option></select></div>
    <div class="col-md-3"><label class="form-label" for="sort">Sắp xếp</label><select class="form-select" id="sort" name="sort">@foreach(['route_code'=>'Mã','distance_km'=>'Khoảng cách','updated_at'=>'Cập nhật'] as $key=>$label)<option value="{{ $key }}" @selected(request('sort','route_code')===$key)>{{ $label }}</option>@endforeach</select></div>
    <div class="col-md-2 align-self-end"><button class="btn btn-primary">Lọc đường bay</button></div>
</form>
<div class="card mb-3"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Mã</th><th>Hai sân bay</th><th>Khoảng cách / thời gian</th><th>Mùa khai thác</th><th>Trạng thái</th><th></th></tr></thead><tbody>
@forelse($routes as $route)<tr><td>{{ $route->route_code }}</td><td>{{ $route->origin_airport_code }} → {{ $route->dest_airport_code }}</td><td>{{ $route->distance_km }} km / {{ $route->standard_duration_minutes }} phút</td><td>{{ $route->operating_from??'Không giới hạn' }} {{ $route->operating_to?'→ '.$route->operating_to:'' }}</td><td>{{ $route->status==='ACTIVE'?'Hoạt động':'Tạm ngừng' }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.routes.show',$route->id) }}">Chi tiết</a></td></tr>@empty<tr><td colspan="6">Không có đường bay phù hợp.</td></tr>@endforelse
</tbody></table></div></div>{{ $routes->links() }}
@if(app(\App\Shared\Security\AccessControl::class)->scopes(auth()->user(),'route.write')->isNotEmpty())
<details class="card p-3"><summary>Thêm đường bay</summary><form method="POST" action="{{ route('admin.routes.store') }}" class="mt-3">@csrf @include('planning.route-fields',['route'=>null])<button class="btn btn-success mt-3">Tạo đường bay</button></form></details>
@endif
</div>
@endsection
