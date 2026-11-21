@extends('layouts.app')
@section('title', $title.' · Airline Smart')
@section('content')
<h1>{{ $title }}</h1>
<form method="get" class="card-surface mb-4"><label class="form-label" for="dependency-flight">ID chuyến bay</label><input class="form-control mb-3" id="dependency-flight" name="flight_id" type="number" min="1" value="{{ request('flight_id') }}"><button class="btn btn-outline-primary">Lọc chuyến bay</button></form>
@if($mode === 'crew')
<section class="card-surface"><h2>Phân công</h2>
@if($flights->isNotEmpty() && $crew->isNotEmpty())
<form method="post" action="/workspace/actions/assign_crew">@csrf
<label class="form-label" for="crew-flight">Chuyến bay</label><select class="form-select mb-3" id="crew-flight" name="flight_id">@foreach($flights as $f)<option value="{{ $f->id }}">#{{ $f->id }} · {{ $f->number }} · {{ $f->origin }} → {{ $f->destination }} · {{ \App\Support\Ui::date($f->departure) }}</option>@endforeach</select>
<label class="form-label" for="crew-member">Nhân sự</label><select class="form-select mb-3" id="crew-member" name="crew_member_id">@foreach($crew as $c)<option value="{{ $c->id }}">{{ $c->code }} · {{ $c->name }} · {{ $c->position }} · {{ $c->qualification }}</option>@endforeach</select><button class="btn btn-primary">Phân công tổ bay</button></form>
@else<p class="subtle">Chưa có chuyến hoặc nhân sự đủ điều kiện hiển thị.</p>@endif
<h2 class="mt-4">Phân công hiện tại</h2>@forelse($assignments as $a)<p>#{{ $a->flight_id }} · {{ $a->code }} · {{ $a->name }} · {{ $a->position }}</p>@empty<p class="subtle">Chưa có phân công cho danh sách chuyến.</p>@endforelse</section>
@else
@forelse($tasks as $t)<section class="card-surface mb-3"><h2>{{ $t->number }} · {{ $t->title }}</h2><p>{{ $t->origin }} → {{ $t->destination }} · {{ \App\Support\Ui::label($t->status) }}</p>
@if($t->status !== 'COMPLETED' && $t->assigned_to === auth()->id())<form method="post" action="/workspace/actions/ground_state">@csrf<input type="hidden" name="task_id" value="{{ $t->id }}"><input type="hidden" name="status" value="{{ $t->status === 'PENDING' ? 'IN_PROGRESS' : 'COMPLETED' }}"><label class="form-label" for="ground-evidence-{{ $t->id }}">Kết quả / bằng chứng</label><textarea class="form-control mb-3" name="evidence" id="ground-evidence-{{ $t->id }}" maxlength="4000" @if($t->status === 'IN_PROGRESS') required minlength="10" @endif></textarea><button class="btn btn-primary">{{ $t->status === 'PENDING' ? 'Bắt đầu công việc' : 'Hoàn tất công việc' }}</button></form>@endif</section>@empty<section class="card-surface empty">Chưa có nhiệm vụ phục vụ chuyến bay.</section>@endforelse
{{ $tasks->links('pagination::bootstrap-5') }}
@endif
@endsection
