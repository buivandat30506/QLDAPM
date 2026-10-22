@extends('layouts.admin')
@section('title','Xem trước lô điều hành')
@section('page_title', 'Xử lý chuyến bay chậm hoặc hủy')
@section('content')
<div class="sitemap-module-page flight-module-page">
<h1>Xem trước lô điều hành</h1><p>Toàn bộ lô được kiểm tra lại khi áp dụng. Một chuyến vi phạm thì không cập nhật chuyến nào.</p>
@foreach($preview['items'] as $item)<section class="card p-3 mb-2"><h2 class="h5">{{ $item['flight_number'] }}</h2><p>{{ $preview['input']['action']==='CANCEL'?'Hủy chuyến':'Lùi giờ '.$preview['input']['delay_minutes'].' phút' }} · phiên bản {{ $item['impact']['flight_revision'] }}</p><p>Đơn/dịch vụ {{ count($item['impact']['orders']) }}, check-in {{ count($item['impact']['checkins']) }}, hành lý {{ count($item['impact']['baggage']) }}, tổ bay {{ count($item['impact']['crew']) }}. Chuyến sau: {{ collect($item['impact']['following'])->pluck('flight_number')->implode(', ') ?: 'Không có' }}.</p><p>{{ $item['impact']['finance'] }}</p></section>@endforeach
<p>Lý do quyết định: {{ $preview['input']['reason'] }}</p><form method="post" action="{{ route('admin.flights.bulk','commit') }}">@csrf
@foreach($preview['input']['flight_ids'] as $id)<input type="hidden" name="flight_ids[]" value="{{ $id }}">@endforeach
<input type="hidden" name="action" value="{{ $preview['input']['action'] }}"><input type="hidden" name="delay_minutes" value="{{ $preview['input']['delay_minutes'] ?? '' }}"><input type="hidden" name="reason" value="{{ $preview['input']['reason'] }}"><input type="hidden" name="preview_hash" value="{{ $preview['hash'] }}"><button class="btn btn-danger">Xác nhận áp dụng toàn bộ lô</button> <a href="{{ route('admin.flights') }}" class="btn btn-secondary">Quay lại</a></form>
</div>
@endsection
