@extends('layouts.admin')
@section('title', $airport->iata_code.' · '.$airport->name)
@section('page_title', $airport->iata_code.' · '.$airport->name)
@section('content')
<a href="{{ route('admin.airports') }}" class="btn btn-outline-secondary mb-3">Danh sách sân bay</a>
<div class="card mb-4"><div class="card-header">Thông tin sân bay</div><div class="card-body">
@can('airport.write', [['airport'=>$airport->iata_code]])
<form action="{{ route('admin.airports.update', $airport->iata_code) }}" method="POST">@csrf @method('PUT') @include('foundation.airport-fields')<button class="btn btn-primary mt-3">Lưu sân bay</button></form>
@else
<dl class="row">@foreach(['icao_code'=>'ICAO','city'=>'Thành phố','country'=>'Quốc gia','timezone'=>'Múi giờ','operating_hours'=>'Giờ hoạt động','latitude'=>'Vĩ độ','longitude'=>'Kinh độ','status'=>'Trạng thái'] as $field=>$label)<dt class="col-sm-3">{{ $label }}</dt><dd class="col-sm-9">{{ $airport->{$field} ?? 'Chưa nhập' }}</dd>@endforeach</dl>
@endcan
</div></div>
<div class="card mb-4"><div class="card-header">Tài nguyên sân bay</div><div class="card-body">
<form method="GET" class="row g-2 mb-4"><div class="col-md-3"><label class="form-label" for="resource_search">Tìm mã tài nguyên</label><input id="resource_search" name="resource_search" class="form-control" value="{{ request('resource_search') }}"></div>
<div class="col-md-3"><label class="form-label" for="filter-resource-type">Lọc loại tài nguyên</label><select id="filter-resource-type" name="resource_type" class="form-select"><option value="">Tất cả</option>@foreach(\App\Models\AirportResource::TYPES as $v=>$label)<option value="{{ $v }}" @selected(request('resource_type')===$v)>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-4"><label class="form-label" for="resource_status">Lọc trạng thái tài nguyên</label><select id="resource_status" name="resource_status" class="form-select"><option value="">Tất cả</option>@foreach(\App\Models\AirportResource::STATUSES as $v=>$label)<option value="{{ $v }}" @selected(request('resource_status')===$v)>{{ $label }}</option>@endforeach</select></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary">Lọc tài nguyên</button></div></form>
@can('airport.write', [['airport'=>$airport->iata_code]])
<form action="{{ route('admin.airports.resources.store', $airport->iata_code) }}" method="POST" class="row g-2 mb-4">@csrf
<div class="col-md-4"><label class="form-label" for="resource_type">Loại tài nguyên</label><select id="resource_type" name="resource_type" class="form-select">@foreach(\App\Models\AirportResource::TYPES as $v=>$label)<option value="{{ $v }}">{{ $label }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label" for="resource_code">Mã tài nguyên</label><input id="resource_code" name="resource_code" class="form-control" maxlength="50" required></div>
<div class="col-md-3"><label class="form-label" for="resource-status">Trạng thái tài nguyên</label><select id="resource-status" name="status" class="form-select">@foreach(\App\Models\AirportResource::STATUSES as $v=>$label)<option value="{{ $v }}">{{ $label }}</option>@endforeach</select></div>
<div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary">Thêm tài nguyên</button></div></form>
@endcan
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Loại</th><th>Mã</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
@forelse($resources as $resource)
<tr data-resource="{{ $resource->id }}"><td>{{ \App\Models\AirportResource::TYPES[$resource->resource_type] ?? $resource->resource_type }}</td><td>{{ $resource->resource_code }}</td><td>{{ \App\Models\AirportResource::STATUSES[$resource->status] ?? $resource->status }}</td><td>
@can('airport.write', [['airport'=>$airport->iata_code]])
<details><summary>Sửa tài nguyên</summary><form action="{{ route('admin.airports.resources.update', $resource->id) }}" method="POST" class="d-flex flex-wrap gap-2 mt-2">@csrf @method('PUT')
<input aria-label="Mã tài nguyên {{ $resource->id }}" name="resource_code" value="{{ $resource->resource_code }}" class="form-control" maxlength="50" required>
<select aria-label="Trạng thái tài nguyên {{ $resource->id }}" name="status" class="form-select">@foreach(\App\Models\AirportResource::STATUSES as $v=>$label)<option value="{{ $v }}" @selected($resource->status===$v)>{{ $label }}</option>@endforeach</select><button class="btn btn-sm btn-primary">Lưu tài nguyên</button></form>
<form class="mt-2" action="{{ route('admin.airports.resources.destroy', $resource->id) }}" method="POST" onsubmit="return confirm('Xóa tài nguyên chưa được sử dụng?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Xóa tài nguyên</button></form></details>
@endcan
</td></tr>
@empty<tr><td colspan="4">Chưa có tài nguyên.</td></tr>@endforelse
</tbody></table></div>{{ $resources->links() }}</div></div>
<div class="card mb-4"><div class="card-header">Lịch sử thay đổi sân bay và tài nguyên (UTC)</div><div class="card-body"><ul>@forelse($history as $event)<li>{{ $event->created_at }} · {{ $event->entity_type==='airports'?'Sân bay':'Tài nguyên' }} {{ $event->entity_id }} · {{ $event->action }} · {{ $event->actor_id ?? 'Tác vụ hệ thống' }}</li>@empty<li>Chưa có lịch sử.</li>@endforelse</ul>{{ $history->links() }}</div></div>
@can('airport.write', [['airport'=>$airport->iata_code]])
<form action="{{ route('admin.airports.destroy', $airport->iata_code) }}" method="POST" onsubmit="return confirm('Xóa sân bay chưa sử dụng?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Xóa sân bay chưa sử dụng</button></form>
@endcan
@endsection
