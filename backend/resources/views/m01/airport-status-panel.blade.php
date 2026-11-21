@php($canUpdateStatus = \App\Support\AirportStatusAuthority::canUpdate(auth()->user(), $airport->code))
<section class="airport-card airport-operation" id="airport-operation">
    <div class="airport-operation-overview">
        <h2>Trạng thái vận hành</h2>
        <div class="airport-operation-meta">
            @include('m01.status', ['status' => $airport->operational_status ?? null])
            @include('m01.airport-status-time')
        </div>
    </div>
    @if($canUpdateStatus && $statusReady)
    <details class="airport-operation-editor" @if(request()->boolean('edit_status') || $errors->hasAny(['operational_status','note','confirmed'])) open @endif>
        <summary class="btn btn-outline-primary">Đổi trạng thái</summary>
        <form method="post" action="/workspace/catalog/airports/{{ $airport->code }}/status" class="airport-form" data-airport-status-form>
            @csrf
            <div class="airport-form-grid">
                <label>Trạng thái đã xác nhận<select class="form-select" name="operational_status" required><option value="">Chọn trạng thái…</option>@foreach($aw::STATUSES as $key => $state)<option value="{{ $key }}" @selected(old('operational_status', $airport->operational_status) === $key)>{{ $state['label'] }}</option>@endforeach</select>@include('m01.infra-error',['field'=>'operational_status'])</label>
                <label>Lý do và căn cứ ghi nhận<textarea class="form-control" name="note" rows="2" required minlength="5" maxlength="500">{{ old('note') }}</textarea>@include('m01.infra-error',['field'=>'note'])</label>
            </div>
            <label class="airport-confirm"><input type="checkbox" name="confirmed" value="1" required> Tôi xác nhận trạng thái và lý do trên cho sân bay {{ $airport->code }}.</label>@include('m01.infra-error',['field'=>'confirmed'])
            <button class="btn btn-primary" type="submit">Xác nhận cập nhật trạng thái</button>
        </form>
    </details>
    @endif
</section>
