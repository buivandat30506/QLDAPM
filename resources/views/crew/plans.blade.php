@extends('layouts.admin')
@section('title','Phân công phi hành đoàn')
@section('page_title', 'Phân công phi hành đoàn')
@section('content')
<div class="sitemap-module-page crew-module-page">
<h1 class="h3 module-heading"><i class="bi bi-people module-icon" aria-hidden="true"></i>Phân công phi hành đoàn</h1><form method="get" class="d-flex gap-2 mb-3"><label for="flight_search" class="visually-hidden">Tìm chuyến</label><input id="flight_search" name="search" class="form-control" placeholder="Số hiệu chuyến bay" value="{{ request('search') }}"><button class="btn btn-primary">Tìm chuyến</button></form>
<div class="table-responsive"><table class="table"><thead><tr><th>Chuyến</th><th>Tuyến</th><th>Khởi hành UTC</th><th>Trạng thái / phân công</th></tr></thead><tbody>@forelse($flights as $f)<tr><td><a href="{{ route('admin.crew.plan',$f->id) }}">{{ $f->flight_number }}</a></td><td>{{ $f->route->route_code }}</td><td>{{ $f->scheduled_dep_utc }}</td><td>{{ config('resource-labels.flight_status.'.$f->flight_status,$f->flight_status) }} / {{ $f->crewAssignments->where('status','!=','REPLACED')->count() }}</td></tr>@empty<tr><td colspan="4">Không có chuyến trong bộ lọc/phạm vi.</td></tr>@endforelse</tbody></table></div>{{ $flights->links() }}
</div>
@endsection
