@extends('layouts.admin')
@section('title', 'Dữ liệu chủ')
@section('page_title', 'Dữ liệu chủ')
@section('content')
<div class="card mb-4"><div class="card-body"><form method="GET" class="row g-2 align-items-end">
<div class="col-md-4"><label for="search" class="form-label">Tìm mã hoặc tên</label><input id="search" name="search" value="{{ request('search') }}" class="form-control"></div>
<div class="col-md-3"><label for="category" class="form-label">Nhóm dữ liệu</label><select id="category" name="category" class="form-select"><option value="">Tất cả</option>@foreach(\App\Services\Core\MasterDataQuality::CATEGORIES as $v=>$label)<option value="{{ $v }}" @selected(request('category')===$v)>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-3"><label for="status" class="form-label">Trạng thái</label><select id="status" name="status" class="form-select"><option value="">Tất cả</option>@foreach(['DRAFT','PENDING','APPROVED','PUBLISHED'] as $v)<option value="{{ $v }}" @selected(request('status')===$v)>@include('partials.ui-label', ['uiGroup'=>'master_status', 'uiCode'=>$v])</option>@endforeach</select></div>
<div class="col-md-2"><button class="btn btn-primary">Lọc dữ liệu</button></div></form>
@can('master.export')<a class="btn btn-outline-secondary mt-3" href="{{ route('admin.master.export', request()->query()) }}">Xuất CSV theo bộ lọc</a>@endcan
</div></div>
<div class="card mb-4"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nhóm / Mã</th><th>Tên</th><th>Trạng thái</th><th>Phiên bản công bố</th><th></th></tr></thead><tbody>
@forelse($records as $record)<tr><td>{{ \App\Services\Core\MasterDataQuality::CATEGORIES[$record->category] ?? $record->category }} / {{ $record->code }}</td><td>{{ json_decode($record->payload,true)['name'] ?? 'Thiếu tên' }}</td><td>@include('partials.ui-label', ['uiGroup'=>'master_status', 'uiCode'=>$record->status])</td><td>{{ $record->version }}</td><td><a href="{{ route('admin.master.show',$record->id) }}">Chi tiết</a></td></tr>@empty<tr><td colspan="5">Không có dữ liệu theo bộ lọc.</td></tr>@endforelse
</tbody></table></div><div class="card-body">{{ $records->links() }}</div></div>
@can('master.write')
<details class="card mb-4" @if($errors->any()) open @endif><summary class="card-header">Tạo dữ liệu chủ</summary><div class="card-body"><form method="POST" action="{{ route('admin.master.store') }}">@csrf
<label for="new-category" class="form-label">Nhóm dữ liệu mới</label><select id="new-category" name="category" class="form-select mb-2">@foreach(\App\Services\Core\MasterDataQuality::CATEGORIES as $v=>$label)<option value="{{ $v }}" @selected(old('category')===$v)>{{ $label }}</option>@endforeach</select>
<label for="code" class="form-label">Mã dữ liệu</label><input id="code" name="code" class="form-control mb-2" value="{{ old('code') }}" required maxlength="50">
<label for="payload_json" class="form-label">Thuộc tính dữ liệu (JSON)</label><textarea id="payload_json" name="payload_json" class="form-control font-monospace mb-2" rows="4" required>{{ old('payload_json','{"name":"Tên dữ liệu"}') }}</textarea>
@include('foundation.master-schema')<button class="btn btn-primary">Lưu bản nháp</button></form></div></details>
<div class="card mb-4"><div class="card-header">Nhập CSV có kiểm tra chất lượng</div><div class="card-body">
<p>Dùng tệp CSV theo mẫu, mã hóa UTF-8, tối đa 1 MiB và 1.000 bản ghi. Giữ các cột category, code, payload; nhập thuộc tính dạng JSON trong cột payload. Một dòng lỗi sẽ chặn cả lô. Dữ liệu nhập thành công được lưu nháp để phê duyệt.</p>
<a href="{{ route('admin.master.template') }}">Tải mẫu nhập CSV</a>
<form method="POST" enctype="multipart/form-data" action="{{ route('admin.master.preview') }}" class="mt-3 d-flex flex-wrap gap-2">@csrf<label for="file" class="visually-hidden">Tệp CSV</label><input id="file" name="file" type="file" accept=".csv" class="form-control" required><button class="btn btn-primary">Kiểm tra tệp</button></form>
</div></div>
@endcan
<div class="card"><div class="card-header">Lịch sử lô nhập</div><div class="card-body"><ul>@forelse($batches as $batch)<li><a href="{{ route('admin.master.batch',$batch->id) }}">{{ $batch->filename }}</a> · @include('partials.ui-label', ['uiGroup'=>'import_status', 'uiCode'=>$batch->status]) · {{ $batch->row_count }} bản ghi / {{ $batch->error_count }} lỗi · {{ $batch->created_at }} UTC</li>@empty<li>Chưa có lô nhập.</li>@endforelse</ul>{{ $batches->links() }}</div></div>
@endsection
