@extends('layouts.admin')
@section('title', 'Kiểm tra lô nhập')
@section('page_title', 'Kiểm tra lô nhập dữ liệu chủ')
@section('content')
<a class="btn btn-outline-secondary mb-3" href="{{ route('admin.master.index') }}">Dữ liệu chủ</a>
<div class="card mb-4"><div class="card-body"><p><strong>{{ $batch->filename }}</strong> · @include('partials.ui-label', ['uiGroup'=>'import_status', 'uiCode'=>$batch->status]) · {{ $batch->row_count }} bản ghi / {{ $batch->error_count }} lỗi</p><p class="small text-break">Mã kiểm tra tính toàn vẹn (SHA-256): {{ $batch->sha256 }}</p>
@if($batch->status==='READY')@can('master.write')<form method="POST" action="{{ route('admin.master.commit',$batch->id) }}">@csrf<button class="btn btn-primary">Xác nhận nhập toàn bộ bản nháp</button></form>@endcan
@elseif($batch->status==='INVALID')<div class="alert alert-danger">Lô chưa đạt chất lượng. Không nhập bất kỳ dòng nào. Sửa tệp theo thông báo rồi kiểm tra lại.</div>
@else<div class="alert alert-success">Đã nhập toàn bộ bản nháp. Tiếp tục duyệt và công bố từng bản ghi.</div>@endif
</div></div>
<div class="card"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Bản ghi CSV</th><th>Định danh</th><th>Dữ liệu</th><th>Kết quả</th></tr></thead><tbody>
@foreach($rows as $row)@php($input=json_decode($row->input,true)) @php($rowErrors=json_decode($row->errors,true))
<tr><td>{{ $row->line }}</td><td>{{ $input['category'] ?? '' }} / {{ $input['code'] ?? '' }}</td><td><pre class="text-wrap small">{{ json_encode($input['payload'] ?? [],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre></td><td>@if($rowErrors)<ul>@foreach($rowErrors as $field=>$messages)@foreach($messages as $message)<li>{{ $field }}: {{ $message }}</li>@endforeach @endforeach</ul>@elseif($row->record_id)<a href="{{ route('admin.master.show',$row->record_id) }}">Mở bản nháp đã nhập</a>@else Đạt kiểm tra @endif</td></tr>
@endforeach
</tbody></table></div><div class="card-body">{{ $rows->links() }}</div></div>
@endsection
