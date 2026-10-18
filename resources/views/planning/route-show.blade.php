@extends('layouts.admin')
@section('title','Chi tiết đường bay')
@section('content')
<a href="{{ route('admin.routes') }}">← Danh sách đường bay</a><h5 class="mt-3">{{ $route->route_code }}</h5>
<p>{{ $route->originAirport->name }} → {{ $route->destAirport->name }}; {{ $route->distance_km }} km, {{ $route->standard_duration_minutes }} phút. {{ $route->restriction_notes }}</p>
@can('route.write',[['airport'=>$route->origin_airport_code]])
<form class="card p-3 mb-3" method="POST" action="{{ route('admin.routes.update',$route->id) }}">@csrf @method('PUT')<input type="hidden" name="expected_revision" value="{{ $route->revision }}">@include('planning.route-fields')<button class="btn btn-primary mt-3">Lưu đường bay</button></form>
<form method="POST" action="{{ route('admin.routes.destroy',$route->id) }}" onsubmit="return confirm('Xóa đường bay chưa có lịch hoặc chuyến bay?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Xóa đường bay</button></form>
@endcan
<h6 class="mt-4">Lịch sử đường bay</h6><div class="table-responsive"><table class="table"><thead><tr><th>UTC</th><th>Hành động</th><th>Người thực hiện (ID)</th></tr></thead><tbody>@forelse($history as $event)<tr><td>{{ $event->created_at }}</td><td>{{ $event->action }}</td><td>{{ $event->actor_id??'Dữ liệu khởi tạo / chưa xác định' }}</td></tr>@empty<tr><td colspan="3">Chưa có lịch sử.</td></tr>@endforelse</tbody></table></div>{{ $history->links() }}
@endsection
