@extends('layouts.admin')
@section('page_title', 'Bảo dưỡng kỹ thuật')
@section('content')
<p>Kế hoạch và lệnh bảo dưỡng lưu trong cơ sở dữ liệu. Thời gian nhập và hiển thị ở UTC.</p>
<form method="GET" class="row g-2 mb-3" aria-label="Lọc kế hoạch">
<div class="col-md-5"><label for="q" class="form-label">Mã kế hoạch / tàu bay</label><input id="q" name="q" value="{{ request('q') }}" class="form-control"></div>
<div class="col-md-4"><label for="status" class="form-label">Trạng thái kế hoạch</label><select id="status" name="status" class="form-select"><option value="">Tất cả</option><option value="ACTIVE" @selected(request('status')==='ACTIVE')>Đang theo dõi</option><option value="RESOLVED" @selected(request('status')==='RESOLVED')>Đã giải quyết</option></select></div><div class="col-md-3 align-self-end"><button class="btn btn-outline-primary">Lọc</button></div>
</form>
<div class="table-responsive"><table class="table" aria-label="Kế hoạch bảo dưỡng"><thead><tr><th>Mã</th><th>Tàu bay</th><th>Hạng mục</th><th>Lịch UTC</th><th>Hạn UTC</th><th>Trạng thái</th></tr></thead><tbody>
@forelse($plans as $p)<tr><td><a href="{{ route('admin.maintenance.show', $p->id) }}">{{ $p->plan_code }}</a></td><td>{{ $p->aircraft_tail }}</td><td>{{ $p->task }}</td><td>{{ $p->starts_at_utc }} → {{ $p->ends_at_utc }}</td><td>{{ $p->due_at_utc }}</td><td>{{ $p->status==='ACTIVE' ? 'Đang theo dõi' : 'Đã giải quyết' }}</td></tr>@empty<tr><td colspan="6">Chưa có kế hoạch trong phạm vi tìm kiếm.</td></tr>@endforelse
</tbody></table></div>{{ $plans->links() }}
@if($aircrafts->contains(fn($a)=>Gate::allows('maintenance.write', $a->base_airport_code ? [['airport'=>$a->base_airport_code]] : [])))
<section class="card mb-4"><h2 class="card-header h5">Lập kế hoạch bảo dưỡng</h2><form method="POST" action="{{ route('admin.maintenance.store') }}" class="card-body" aria-label="Kế hoạch bảo dưỡng">@csrf @include('maintenance.plan-form')</form></section>
@endif
<h2 class="h5">Vật tư theo kho sân bay</h2>
@if($legacy->isNotEmpty())
<section class="card mb-3"><h2 class="card-header h5">Hồ sơ tương thích cần rà soát</h2><div class="card-body"><p>Các lệnh cũ chưa có kế hoạch, chữ ký và tài liệu riêng tư. Nội dung văn bản cũ được giữ trong lịch sử; chưa được dùng để giải phóng.</p>
@foreach($legacy as $o)<p>{{ $o->order_code }} — {{ $o->aircraft_tail }} — {{ $o->task }}. Cơ sở: {{ $o->base_airport_code ?: 'chưa có; bộ phận đội tàu cần bổ sung' }}.</p>
@if($o->base_airport_code && Gate::allows('maintenance.write', [['airport'=>$o->base_airport_code]]) && in_array($o->status,['PLANNED','WAITING_MATERIALS','IN_PROGRESS']))
<form method="POST" action="{{ route('admin.maintenance.adopt', $o->id) }}" class="row g-2 mb-3" aria-label="Chuyển hồ sơ {{ $o->order_code }}">@csrf
<div class="col-md-4"><label for="legacy_tech_{{ $o->id }}">Kỹ thuật viên phụ trách ban đầu</label><select id="legacy_tech_{{ $o->id }}" name="technician_id" class="form-select"><option value="{{ auth()->id() }}">{{ auth()->user()->username }}</option></select></div>
<div class="col-md-3"><label for="legacy_location_{{ $o->id }}">Vị trí đỗ tại {{ $o->base_airport_code }}</label><select id="legacy_location_{{ $o->id }}" name="location_id" class="form-select" required>@foreach($legacyLocations->where('airport_code',$o->base_airport_code) as $loc)<option value="{{ $loc->id }}">{{ $loc->resource_code }}</option>@endforeach</select></div>
<div class="col-md-5"><label for="legacy_reason_{{ $o->id }}">Căn cứ rà soát lại hạng mục / vật tư</label><input id="legacy_reason_{{ $o->id }}" name="reason" class="form-control" required></div><div><button class="btn btn-outline-primary">Chuyển sang hồ sơ đầy đủ</button></div></form>
@endif @endforeach {{ $legacy->links() }}</div></section>
@endif
<div class="table-responsive"><table class="table" aria-label="Tồn kho vật tư"><thead><tr><th>Sân bay</th><th>Mã</th><th>Tên</th><th>Tồn kho</th></tr></thead><tbody>@forelse($stock as $m)<tr><td>{{ $m->airport_code }}</td><td>{{ $m->code }}</td><td>{{ $m->name }}</td><td>{{ $m->quantity }} {{ $m->unit }}</td></tr>@empty<tr><td colspan="4">Chưa có vật tư.</td></tr>@endforelse</tbody></table></div>{{ $stock->links() }}
@if($aircrafts->contains(fn($a)=>Gate::allows('maintenance.write', $a->base_airport_code ? [['airport'=>$a->base_airport_code]] : [])))
<section class="card"><h2 class="card-header h5">Nhập vật tư</h2><form method="POST" action="{{ route('admin.maintenance.stock') }}" class="card-body row g-3" aria-label="Nhập vật tư">@csrf
<div class="col-md-4"><label for="stock_airport" class="form-label">Kho sân bay</label><select id="stock_airport" name="airport_code" class="form-select" required>@foreach($aircrafts->pluck('base_airport_code')->filter()->unique() as $base)@can('maintenance.write', [['airport'=>$base]])<option>{{ $base }}</option>@endcan @endforeach</select></div>
@foreach(['code'=>'Mã vật tư','name'=>'Tên vật tư','unit'=>'Đơn vị nguyên (cái, bộ…)','quantity'=>'Số lượng nhập','reference'=>'Số phiếu nhập duy nhất','reason'=>'Căn cứ nhập kho'] as $field=>$label)<div class="col-md-4"><label for="stock_{{ $field }}" class="form-label">{{ $label }}</label><input id="stock_{{ $field }}" name="{{ $field }}" class="form-control" required @if($field==='quantity') type="number" min="1" @else maxlength="{{ $field==='reason' ? 2000 : ($field==='name' ? 200 : ($field==='unit' ? 30 : ($field==='reference' ? 100 : 50))) }}" @endif></div>@endforeach
<div><button class="btn btn-primary">Ghi phiếu nhập</button></div></form></section>
@endif
@endsection
