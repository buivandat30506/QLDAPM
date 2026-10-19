@php($labels=['DRAFT'=>'Nháp','PENDING'=>'Chờ duyệt','APPROVED'=>'Đã duyệt','PUBLISHED'=>'Đã công bố','SUPERSEDED'=>'Đã thay thế','CANCELLED'=>'Đã hủy'])
<span class="badge bg-{{ $status==='PUBLISHED'?'success':($status==='CANCELLED'?'secondary':'primary') }}" data-status="{{ $status }}">{{ $labels[$status]??$status }}</span>
