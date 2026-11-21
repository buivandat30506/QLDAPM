@extends('m02.layout')
@section('heading','Thêm đường bay')
@section('actions')<a href="/workspace/catalog?flow=routes">← Danh sách đường bay</a>@endsection
@section('body')<form method="post" action="/workspace/catalog/routes" class="card-surface m02-form">@csrf
@foreach(['origin'=>'Sân bay đi','destination'=>'Sân bay đến'] as $field=>$label)<label>{{ $label }}<select name="{{ $field }}" required><option value="">Chọn sân bay</option>@foreach($airports as $a)<option value="{{ $a->code }}" @selected(old($field)===$a->code)>{{ $a->code }} · {{ $a->name }}</option>@endforeach</select></label>@endforeach
<label>Thời gian bay dự kiến (phút)<input type="number" name="duration_minutes" min="15" max="1440" value="{{ old('duration_minutes') }}" required></label><label>Khoảng cách (km)<input type="number" name="distance_km" min="1" max="30000" value="{{ old('distance_km') }}" required></label><div class="m02-full"><button class="btn btn-primary">Tạo đường bay</button></div></form>@endsection
