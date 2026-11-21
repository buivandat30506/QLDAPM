@extends('m02.layout')
@section('heading','Tạo lịch chuyến bay')
@section('actions')<a href="/workspace/planning">← Danh sách chuyến bay</a>@endsection
@section('body')<form method="post" action="/workspace/planning/flights" class="card-surface m02-form">@csrf
<label>Số hiệu<input name="number" value="{{ old('number') }}" pattern="[A-Z]{2}[0-9]{2,5}" maxlength="12" placeholder="Ví dụ: AS123" required></label>
@include('m02._route-select')
@include('m02._flight-fields')
<div class="m02-full"><button class="btn btn-primary">Tạo lịch nháp</button><p class="text-muted">Lịch mới chưa được công bố. Giá bán được dùng khi mở bán sau công bố.</p></div></form>@endsection
