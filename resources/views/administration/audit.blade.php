@extends('layouts.portal')
@section('title', 'Nhật ký kiểm toán')
@section('content')
<form method="get" class="card p-3 mb-3"><div class="row g-2">@foreach(['entity_type' => 'Loại đối tượng', 'action' => 'Hành động', 'actor_id' => 'UUID người thao tác', 'correlation_id' => 'Mã truy vết', 'from' => 'Từ ngày UTC', 'to' => 'Đến ngày UTC'] as $field => $label)<div class="col-md-4"><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" value="{{ request($field) }}" class="form-control" type="{{ in_array($field, ['from','to']) ? 'date' : 'text' }}"></div>@endforeach</div><button class="btn btn-primary mt-3">Lọc nhật ký</button></form>
@can('audit.export')<a class="btn btn-outline-secondary mb-3" href="{{ route('administration.audit.export', request()->query()) }}">Xuất CSV đã lọc</a>@endcan
<div class="table-responsive"><table class="table bg-white"><thead><tr><th>UTC / người thao tác</th><th>Hành động / kết quả</th><th>Đối tượng</th><th>Truy vết / dữ liệu</th></tr></thead><tbody>
@forelse($entries as $entry)<tr><td>{{ $entry->created_at }}<br>{{ $entry->actor_id ?? 'system' }}</td><td>{{ $entry->action }}<br>{{ $entry->outcome }}</td><td>{{ $entry->entity_type }} / {{ $entry->entity_id }}</td><td>{{ $entry->correlation_id }}<details><summary>Before / After đã che dữ liệu</summary><pre>{{ json_encode(['before' => $entry->before_data, 'after' => $entry->after_data], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre></details></td></tr>@empty<tr><td colspan="4">Không có nhật ký phù hợp.</td></tr>@endforelse
</tbody></table></div>{{ $entries->links() }}
@endsection
