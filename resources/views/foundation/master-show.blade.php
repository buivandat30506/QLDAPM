@extends('layouts.admin')
@section('title', $record->code.' · Dữ liệu chủ')
@section('page_title', $record->category.' / '.$record->code)
@section('content')
<a class="btn btn-outline-secondary mb-3" href="{{ route('admin.master.index') }}">Danh sách dữ liệu chủ</a>
<div class="card mb-4"><div class="card-body"><p>Trạng thái: <strong>{{ $record->status }}</strong> · Phiên bản đã công bố: {{ $record->version }} · Lần sửa: {{ $record->revision }}</p>
@if($qualityErrors)<div class="alert alert-warning">Kết quả kiểm tra chất lượng:<ul>@foreach($qualityErrors as $field=>$messages)@foreach($messages as $message)<li>{{ $field }}: {{ $message }}</li>@endforeach @endforeach</ul></div>@else<div class="alert alert-success">Dữ liệu đạt kiểm tra chất lượng hiện tại. Hệ thống kiểm tra lại khi gửi duyệt và công bố.</div>@endif
@if($record->status==='DRAFT' && \Illuminate\Support\Facades\Gate::allows('master.write'))
<form action="{{ route('admin.master.update',$record->id) }}" method="POST">@csrf @method('PUT')<input type="hidden" name="category" value="{{ $record->category }}"><input type="hidden" name="code" value="{{ $record->code }}"><input type="hidden" name="expected_revision" value="{{ $record->revision }}">
<label for="payload_json" class="form-label">Thuộc tính JSON</label><textarea id="payload_json" name="payload_json" class="form-control font-monospace mb-2" rows="7" required>{{ old('payload_json', json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) }}</textarea>@include('foundation.master-schema')<button class="btn btn-primary">Lưu thay đổi</button></form>
@else<pre class="bg-light p-3">{{ json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>@endif
<div class="d-flex flex-wrap gap-3 mt-3">
@foreach(['DRAFT'=>['submit'=>'Gửi phê duyệt'],'PENDING'=>['approve'=>'Phê duyệt','reject'=>'Trả về sửa'],'APPROVED'=>['publish'=>'Công bố','reject'=>'Trả về sửa'],'PUBLISHED'=>['revise'=>'Tạo bản sửa đổi']][$record->status] ?? [] as $action=>$label)
@can(in_array($action,['approve','publish','reject'])?'master.approve':'master.write')
<form method="POST" action="{{ route('admin.master.action',[$record->id,$action]) }}">@csrf<input type="hidden" name="expected_revision" value="{{ $record->revision }}">
@if(in_array($action,['revise','reject']))<label for="reason-{{ $action }}" class="form-label">Lý do {{ mb_strtolower($label) }}</label><input id="reason-{{ $action }}" name="reason" class="form-control mb-2" minlength="5" maxlength="500" required>@endif
<button class="btn btn-outline-primary">{{ $label }}</button></form>@endcan @endforeach
</div></div></div>
<div class="card mb-4"><div class="card-header">Các phiên bản công bố bất biến</div><div class="card-body">@forelse($versions as $version)<details class="mb-2"><summary>Phiên bản {{ $version->version }} · {{ $version->created_at }} UTC</summary><pre class="bg-light p-3 text-wrap">{{ json_encode(json_decode($version->snapshot,true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>@empty<p>Chưa có phiên bản công bố.</p>@endforelse</div></div>
<div class="card"><div class="card-header">Lịch sử thao tác (UTC)</div><div class="card-body"><ul>@foreach($history as $event)<li>{{ $event->created_at }} · {{ $event->action }} · {{ $event->actor_id ?? 'Tác vụ hệ thống' }}@if($event->reason) · {{ $event->reason }}@endif</li>@endforeach</ul>{{ $history->links() }}</div></div>
@endsection
