@extends('layouts.admin')
@section('title', $airport->iata_code.' · '.$airport->name)
@section('page_title', $airport->iata_code.' · '.$airport->name)
@section('content')
@php
    $resourcePageStatuses = $resources->getCollection()->countBy('status');
    $resourceOtherStatusCount = $resources->count() - collect(['AVAILABLE', 'MAINTENANCE', 'OCCUPIED'])
        ->sum(fn ($status) => $resourcePageStatuses->get($status, 0));
    $resourceFiltersActive = collect(['resource_search', 'resource_type', 'resource_status'])
        ->contains(fn ($key) => request($key) !== null && request($key) !== '');
    $infrastructureHasErrors = $errors->any();
@endphp
<div class="airport-detail-page">
<a href="{{ route('admin.airports') }}" class="btn btn-outline-secondary mb-3">Danh sách sân bay</a>
<section class="card airport-section airport-info mb-4" aria-labelledby="airport-info-title">
    <div class="card-header airport-section-header">
        <div class="airport-section-heading">
            <span class="airport-section-icon"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
            <h2 id="airport-info-title">Thông tin sân bay</h2>
        </div>
    </div>
    <div class="card-body">
@can('airport.write', [['airport'=>$airport->iata_code]])
<form action="{{ route('admin.airports.update', $airport->iata_code) }}" method="POST">@csrf @method('PUT') @include('foundation.airport-fields')<button class="btn btn-primary mt-3">Lưu sân bay</button></form>
@else
<dl class="row">@foreach(['icao_code'=>'ICAO','city'=>'Thành phố','country'=>'Quốc gia','timezone'=>'Múi giờ','operating_hours'=>'Giờ hoạt động','latitude'=>'Vĩ độ','longitude'=>'Kinh độ','status'=>'Trạng thái'] as $field=>$label)<dt class="col-sm-3">{{ str_replace('tàu bay', 'máy bay', $label) }}</dt><dd class="col-sm-9">{{ $airport->{$field} ?? 'Chưa nhập' }}</dd>@endforeach</dl>
@endcan
    </div>
</section>
<section class="card airport-section airport-infrastructure mb-4" data-infrastructure data-infrastructure-force-open="{{ $infrastructureHasErrors ? 'true' : 'false' }}" aria-labelledby="airport-infrastructure-title">
    <div class="card-header airport-section-header airport-infrastructure-header">
        <div class="airport-section-heading">
            <span class="airport-section-icon"><i class="bi bi-buildings" aria-hidden="true"></i></span>
            <h2 id="airport-infrastructure-title">Cơ sở hạ tầng</h2>
        </div>
        <div class="airport-infrastructure-status" role="group" aria-label="Trạng thái tài nguyên trong trang hiện tại{{ $resourceFiltersActive ? ' sau lọc' : '' }}">
            <dl class="airport-status-totals">
                <div class="airport-tone-success"><dt>Khả dụng</dt><dd>{{ $resourcePageStatuses->get('AVAILABLE', 0) }}</dd></div>
                <div class="airport-tone-warning"><dt>Bảo trì</dt><dd>{{ $resourcePageStatuses->get('MAINTENANCE', 0) }}</dd></div>
                <div class="airport-tone-info"><dt>Đang bận</dt><dd>{{ $resourcePageStatuses->get('OCCUPIED', 0) }}</dd></div>
                @if($resourceOtherStatusCount > 0)
                <div class="airport-status-other"><dt>Khác</dt><dd>{{ $resourceOtherStatusCount }}</dd></div>
                @endif
            </dl>
        </div>
        <button class="airport-infrastructure-toggle" type="button" data-infrastructure-toggle aria-expanded="true" aria-controls="airportInfrastructureContent" aria-label="Mở hoặc đóng phần cơ sở hạ tầng">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
    </div>
    <div id="airportInfrastructureContent" class="collapse show" data-infrastructure-content aria-hidden="false">
        <div class="card-body airport-infrastructure-body">
            <div class="airport-quick-grid">
                <article class="airport-quick-card airport-monitoring-module airport-tone-success" aria-labelledby="airport-monitoring-title">
                    <div class="airport-quick-heading">
                        <span class="airport-card-icon"><i class="bi bi-activity" aria-hidden="true"></i></span>
                        <h3 id="airport-monitoring-title">Theo dõi tình trạng cơ sở hạ tầng</h3>
                    </div>
                    <section class="airport-disclosure" aria-labelledby="airport-filters-title">
                        <h4 id="airport-filters-title">
                            <button class="airport-disclosure-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#airportResourceFilters" aria-expanded="{{ $resourceFiltersActive || $infrastructureHasErrors ? 'true' : 'false' }}" aria-controls="airportResourceFilters">
                                <span>Bộ lọc</span><i class="bi bi-chevron-down" aria-hidden="true"></i>
                            </button>
                        </h4>
                        <div id="airportResourceFilters" class="collapse {{ $resourceFiltersActive || $infrastructureHasErrors ? 'show' : '' }}">
                            <div class="airport-disclosure-body">
<form method="GET" class="row g-2 airport-resource-form"><div class="col-md-3"><label class="form-label" for="resource_search">Tìm mã tài nguyên</label><input id="resource_search" name="resource_search" class="form-control" value="{{ request('resource_search') }}"></div>
<div class="col-md-3"><label class="form-label" for="filter-resource-type">Lọc loại tài nguyên</label><select id="filter-resource-type" name="resource_type" class="form-select"><option value="">Tất cả</option>@foreach(\App\Models\AirportResource::TYPES as $v=>$label)<option value="{{ $v }}" @selected(request('resource_type')===$v)>{{ str_replace('tàu bay', 'máy bay', $label) }}</option>@endforeach</select></div>
<div class="col-md-4"><label class="form-label" for="resource_status">Lọc trạng thái tài nguyên</label><select id="resource_status" name="resource_status" class="form-select"><option value="">Tất cả</option>@foreach(\App\Models\AirportResource::STATUSES as $v=>$label)<option value="{{ $v }}" @selected(request('resource_status')===$v)>{{ str_replace('tàu bay', 'máy bay', $label) }}</option>@endforeach</select></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary">Lọc tài nguyên</button></div></form>
                            </div>
                        </div>
                    </section>
@can('airport.write', [['airport'=>$airport->iata_code]])
                    <section class="airport-disclosure" aria-labelledby="airport-create-title">
                        <h4 id="airport-create-title">
                            <button class="airport-disclosure-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#airportResourceCreate" aria-expanded="{{ $infrastructureHasErrors ? 'true' : 'false' }}" aria-controls="airportResourceCreate">
                                <span><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Thêm cơ sở hạ tầng</span><i class="bi bi-chevron-down" aria-hidden="true"></i>
                            </button>
                        </h4>
                        <div id="airportResourceCreate" class="collapse {{ $infrastructureHasErrors ? 'show' : '' }}">
                            <div class="airport-disclosure-body">
<form action="{{ route('admin.airports.resources.store', $airport->iata_code) }}" method="POST" class="row g-2 airport-resource-form">@csrf
<div class="col-md-4"><label class="form-label" for="resource_type">Loại tài nguyên</label><select id="resource_type" name="resource_type" class="form-select">@foreach(\App\Models\AirportResource::TYPES as $v=>$label)<option value="{{ $v }}" @selected(old('resource_type') === $v)>{{ str_replace('tàu bay', 'máy bay', $label) }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label" for="resource_code">Mã tài nguyên</label><input id="resource_code" name="resource_code" value="{{ old('resource_code') }}" class="form-control" maxlength="50" required></div>
<div class="col-md-3"><label class="form-label" for="resource-status">Trạng thái tài nguyên</label><select id="resource-status" name="status" class="form-select">@foreach(\App\Models\AirportResource::STATUSES as $v=>$label)<option value="{{ $v }}" @selected(old('status', 'AVAILABLE') === $v)>{{ str_replace('tàu bay', 'máy bay', $label) }}</option>@endforeach</select></div>
<div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary">Thêm tài nguyên</button></div></form>
                            </div>
                        </div>
                    </section>
@endcan
                    <section class="airport-resource-list" aria-labelledby="airport-resource-list-title">
                        <h4 id="airport-resource-list-title">Danh sách cơ sở hạ tầng</h4>
<div class="table-responsive airport-resource-table-wrap"><table class="table align-middle airport-resource-table mb-0"><thead><tr><th>Loại</th><th>Mã</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
@forelse($resources as $resource)
<tr data-resource="{{ $resource->id }}"><td>{{ str_replace('tàu bay', 'máy bay', \App\Models\AirportResource::TYPES[$resource->resource_type] ?? $resource->resource_type) }}</td><td>{{ $resource->resource_code }}</td><td><span class="dashboard-status {{ match ($resource->status) { 'AVAILABLE' => 'is-positive', 'MAINTENANCE' => 'is-attention', 'OCCUPIED' => 'is-boarding', default => 'is-neutral' } }}">{{ \App\Models\AirportResource::STATUSES[$resource->status] ?? $resource->status }}</span></td><td>
@can('airport.write', [['airport'=>$airport->iata_code]])
<details><summary>Sửa tài nguyên</summary><form action="{{ route('admin.airports.resources.update', $resource->id) }}" method="POST" class="d-flex flex-wrap gap-2 mt-2">@csrf @method('PUT')
<input aria-label="Mã tài nguyên {{ $resource->id }}" name="resource_code" value="{{ $resource->resource_code }}" class="form-control" maxlength="50" required>
<select aria-label="Trạng thái tài nguyên {{ $resource->id }}" name="status" class="form-select">@foreach(\App\Models\AirportResource::STATUSES as $v=>$label)<option value="{{ $v }}" @selected($resource->status===$v)>{{ str_replace('tàu bay', 'máy bay', $label) }}</option>@endforeach</select><button class="btn btn-sm btn-primary">Lưu tài nguyên</button></form>
<form class="mt-2" action="{{ route('admin.airports.resources.destroy', $resource->id) }}" method="POST" onsubmit="return confirm('Xóa tài nguyên chưa được sử dụng?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Xóa tài nguyên</button></form></details>
@endcan
</td></tr>
@empty<tr><td colspan="4">Chưa có tài nguyên.</td></tr>@endforelse
</tbody></table></div>
                        <div class="airport-resource-pagination">{{ $resources->links() }}</div>
                    </section>
                </article>
                <article class="airport-quick-card airport-tone-warning">
                    <div class="airport-quick-heading">
                        <span class="airport-card-icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
                        <h3>Lịch bảo trì cơ sở hạ tầng</h3>
                    </div>
                    <p class="airport-quick-empty">Chưa có dữ liệu</p>
                </article>
                <article class="airport-quick-card airport-tone-danger">
                    <div class="airport-quick-heading">
                        <span class="airport-card-icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
                        <h3>Ghi nhận sự cố cơ sở hạ tầng</h3>
                    </div>
                    <p class="airport-quick-empty">Chưa có dữ liệu</p>
                </article>
            </div>
        </div>
    </div>
</section>
@can('airport.read', [['airport'=>$airport->iata_code]])
<section class="airport-ground-operations mb-4" aria-labelledby="airport-ground-title">
    <div class="airport-section-heading airport-ground-section-heading">
        <span class="airport-section-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></span>
        <h2 id="airport-ground-title">Quản lý nhân viên và phương tiện mặt đất</h2>
    </div>
    <div class="airport-ground-grid">
        <article class="card airport-ground-card airport-tone-purple">
            <div class="airport-ground-heading">
                <span class="airport-ground-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
                <h3>Nhân viên</h3>
            </div>
            <ul class="airport-ground-functions">
                <li>Danh sách nhân viên mặt đất</li>
                <li>Thông tin nhân viên mặt đất</li>
            </ul>
            <p class="airport-ground-empty">Chưa có dữ liệu</p>
        </article>
        <article class="card airport-ground-card airport-tone-info">
            <div class="airport-ground-heading">
                <span class="airport-ground-icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></span>
                <h3>Phân công</h3>
            </div>
            <ul class="airport-ground-functions">
                <li>Phân công nhiệm vụ</li>
                <li>Phân công khu vực làm việc</li>
            </ul>
            <p class="airport-ground-empty">Chưa có dữ liệu</p>
        </article>
        <article class="card airport-ground-card airport-tone-success">
            <div class="airport-ground-heading">
                <span class="airport-ground-icon"><i class="bi bi-calendar2-week" aria-hidden="true"></i></span>
                <h3>Lịch và trạng thái</h3>
            </div>
            <ul class="airport-ground-functions">
                <li>Lịch làm việc</li>
                <li>Theo dõi ca trực</li>
                <li>Theo dõi trạng thái công việc</li>
            </ul>
            <p class="airport-ground-empty">Chưa có dữ liệu</p>
        </article>
        <article class="card airport-ground-card airport-tone-warning">
            <div class="airport-ground-heading">
                <span class="airport-ground-icon"><i class="bi bi-truck" aria-hidden="true"></i></span>
                <h3>Phương tiện</h3>
            </div>
            <ul class="airport-ground-functions">
                <li>Quản lý phương tiện mặt đất</li>
            </ul>
            <p class="airport-ground-empty">Chưa có dữ liệu</p>
        </article>
    </div>
</section>
@endcan
<section class="card airport-section airport-history mb-4" aria-labelledby="airport-history-title">
    <div class="card-header airport-section-header">
        <div class="airport-section-heading">
            <span class="airport-section-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
            <h2 id="airport-history-title">Lịch sử thay đổi sân bay và tài nguyên (UTC)</h2>
        </div>
    </div>
    <div class="card-body"><ul>@forelse($history as $event)<li>{{ $event->created_at }} · {{ $event->entity_type==='airports'?'Sân bay':'Tài nguyên' }} {{ $event->entity_id }} · {{ $event->action }} · {{ $event->actor_id ?? 'Tác vụ hệ thống' }}</li>@empty<li>Chưa có lịch sử.</li>@endforelse</ul>{{ $history->links() }}</div></section>
@can('airport.write', [['airport'=>$airport->iata_code]])
<form action="{{ route('admin.airports.destroy', $airport->iata_code) }}" method="POST" onsubmit="return confirm('Xóa sân bay chưa sử dụng?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Xóa sân bay chưa sử dụng</button></form>
@endcan
</div>
@endsection
