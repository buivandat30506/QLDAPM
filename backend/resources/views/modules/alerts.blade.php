@extends('modules._shell')
@section('module-body')
<section class="card-surface mb-4">
    <h2>Cảnh báo cảm biến gần nhất</h2>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Thiết bị</th><th>Chỉ số</th><th>Thời điểm</th><th>Tình trạng</th></tr></thead><tbody>
        @forelse($alertEvents as $event)
            <tr><td>{{ $devices->firstWhere('id', $event->device_id)?->code ?? 'Thiết bị #'.$event->device_id }}</td><td>{{ $event->value }}</td><td>{{ \App\Support\Ui::date($event->event_time) }}</td><td><span class="badge-state">Vượt ngưỡng</span></td></tr>
        @empty<tr><td colspan="4" class="subtle">Chưa có cảnh báo cảm biến trong phạm vi được xem.</td></tr>@endforelse
    </tbody></table></div>
</section>
@if(\App\Modules\Shared\Access::allows(auth()->user(), 'analyst', 'operations', 'ops_manager'))
@if(session('result')) @include('partials.forecast-result', ['result' => session('result')]) @endif
<section class="card-surface mb-4">
    <h2>Đánh giá rủi ro chuyến bay</h2>
    <p class="subtle">Chọn chuyến để kiểm tra các yếu tố ảnh hưởng đến khai thác.</p>
    <form method="post" action="/workspace/actions/forecast">@csrf<label class="form-label" for="alert-flight">Chuyến bay</label><select class="form-select mb-3" id="alert-flight" name="flight_id" required><option value="">Chọn chuyến bay</option>@foreach($alertFlights as $flight)<option value="{{ $flight->id }}">{{ \App\Support\Ui::flight($flight) }}</option>@endforeach</select><button class="btn btn-primary" type="submit">Kiểm tra cảnh báo</button></form>
</section>
@endif
<section class="card-surface">
    <h2>Khuyến nghị gần nhất</h2>
    @forelse($recommendations as $recommendation)<p>{{ $recommendation->recommendation }} <small class="subtle">· {{ round($recommendation->confidence * 100) }}%</small></p>@empty<p class="subtle">Chưa có khuyến nghị.</p>@endforelse
</section>
@endsection
