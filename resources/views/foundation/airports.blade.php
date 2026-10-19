@extends('layouts.admin')
@section('title', 'Sân bay và tài nguyên')
@section('page_title', 'Sân bay và tài nguyên')
@section('content')
<div class="card mb-4"><div class="card-body">
    <form class="row g-2" method="GET">
        <div class="col-md-4"><label class="form-label" for="search">Tìm mã, tên, thành phố</label><input id="search" name="search" class="form-control" value="{{ request('search') }}"></div>
        <div class="col-md-3"><label class="form-label" for="status">Trạng thái</label><select id="status" name="status" class="form-select"><option value="">Tất cả</option>@foreach(['ACTIVE'=>'Hoạt động','INACTIVE'=>'Tạm ngừng','CLOSED'=>'Đóng cửa'] as $v=>$label)<option value="{{ $v }}" @selected(request('status')===$v)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label" for="sort">Sắp xếp</label><select id="sort" name="sort" class="form-select">@foreach(['iata_code'=>'Mã IATA','name'=>'Tên sân bay','city'=>'Thành phố','status'=>'Trạng thái'] as $v=>$label)<option value="{{ $v }}" @selected(request('sort')===$v)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Tìm kiếm</button></div>
    </form>
</div></div>
<div class="card mb-4"><div class="card-header">Danh sách sân bay · {{ $airports->total() }} kết quả</div><div class="table-responsive">
<table class="table align-middle mb-0"><thead><tr><th>IATA / ICAO</th><th>Tên sân bay</th><th>Địa điểm</th><th>Múi giờ</th><th>Trạng thái</th><th>Tài nguyên</th><th></th></tr></thead><tbody>
@forelse($airports as $airport)
<tr><td><strong>{{ $airport->iata_code }}</strong><br><small>{{ $airport->icao_code ?? 'Chưa có ICAO' }}</small></td><td>{{ $airport->name }}</td><td>{{ $airport->city }}, {{ $airport->country }}</td><td>{{ $airport->timezone }}</td><td>{{ ['ACTIVE'=>'Hoạt động','INACTIVE'=>'Tạm ngừng','CLOSED'=>'Đóng cửa'][$airport->status] ?? $airport->status }}</td><td>{{ $airport->resources_count }}</td><td><a href="{{ route('admin.airports.show', $airport->iata_code) }}" class="btn btn-sm btn-outline-primary">Chi tiết</a></td></tr>
@empty<tr><td colspan="7" class="text-center p-4">Không có sân bay trong phạm vi hoặc bộ lọc này.</td></tr>@endforelse
</tbody></table></div><div class="card-body">{{ $airports->links() }}</div></div>
@can('airport.write')
<details class="card"><summary class="card-header">Thêm sân bay</summary><div class="card-body"><form action="{{ route('admin.airports.store') }}" method="POST">@csrf
@include('foundation.airport-fields', ['airport'=>null])
<button class="btn btn-primary mt-3">Tạo sân bay</button></form></div></details>
@endcan
@endsection
