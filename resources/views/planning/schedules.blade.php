@extends('layouts.admin')
@section('title','Lịch trình bay')
@section('page_title','Quản lý chuyến bay')
@section('content')
<div class="flight-planning-page sitemap-module-page">
<section class="planning-schedule-section" aria-labelledby="planning-schedules-title">
<div class="module-page-heading">
<h2 id="planning-schedules-title" class="mb-0">Lịch trình bay</h2>
@if(app(\App\Shared\Security\AccessControl::class)->scopes(auth()->user(), 'route.read')->contains(fn ($scope) => in_array($scope->scope_type, ['global', 'airport'])))
<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.routes') }}">Tuyến đường bay</a>
@endif
</div>
<p>Nháp → kiểm tra xung đột → gửi duyệt → phê duyệt → công bố. Bản công bố được giữ riêng khi mở bản sửa đổi.</p>
<form class="row g-2 mb-3 planning-schedule-filter" method="GET"><div class="col-md-4"><label for="search" class="form-label">Tìm số hiệu</label><input id="search" class="form-control" name="search" value="{{ request('search') }}"></div><div class="col-md-3"><label for="status" class="form-label">Trạng thái</label><select class="form-select" name="status" id="status"><option value="">Tất cả</option>@foreach(['DRAFT','PENDING','APPROVED','PUBLISHED','SUPERSEDED','CANCELLED'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ['DRAFT'=>'Nháp','PENDING'=>'Chờ duyệt','APPROVED'=>'Đã duyệt','PUBLISHED'=>'Đã công bố','SUPERSEDED'=>'Đã thay thế','CANCELLED'=>'Đã hủy'][$status] }}</option>@endforeach</select></div><div class="col-md-3"><label for="season_filter" class="form-label">Mùa lịch</label><input class="form-control" id="season_filter" name="season_code" value="{{ request('season_code') }}"></div><div class="col-md-2 align-self-end"><button class="btn btn-primary">Lọc lịch bay</button></div></form>
<div class="card mb-3 table-responsive planning-schedule-table"><table class="table mb-0 align-middle"><thead><tr><th>Số hiệu</th><th>Đường bay / mùa</th><th>Hiệu lực</th><th>Trạng thái</th><th>Phiên bản</th><th></th></tr></thead><tbody>@forelse($schedules as $schedule)<tr><td>{{ $schedule->flight_number }}</td><td>{{ $schedule->route->route_code }} / {{ $schedule->season_code??'—' }}</td><td>{{ $schedule->effective_from??'Chưa có' }} → {{ $schedule->effective_to??'Chưa có' }}</td><td>@include('planning.status',['status'=>$schedule->status])</td><td>{{ $schedule->version }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.schedules.show',$schedule->id) }}">Chi tiết lịch trình</a></td></tr>@empty<tr><td colspan="6">Không có lịch bay phù hợp.</td></tr>@endforelse</tbody></table></div>{{ $schedules->links() }}
@if(app(\App\Shared\Security\AccessControl::class)->scopes(auth()->user(),'schedule.write')->isNotEmpty())<details class="card p-3"><summary>Thêm lịch trình</summary><form class="mt-3" method="POST" action="{{ route('admin.schedules.store') }}">@csrf @include('planning.schedule-fields',['schedule'=>null])<button class="btn btn-success mt-3">Lưu lịch nháp</button></form></details>@endif
</section>
</div>
@endsection
