@if(\App\Support\InfrastructureDisplay::parentTypes($type['code']) === [])
    <input type="hidden" name="parent_id" value="">
    <p class="subtle">Trực thuộc sân bay {{ $airport->code }}; loại này không cần công trình cha.</p>
@else
    <label>Thuộc khu vực / công trình (nếu đã xác nhận)
        <select class="form-select" name="parent_id"><option value="">Sân bay {{ $airport->code }}</option>
        @foreach($parentChoices as $choice)<option value="{{ $choice->id }}" @selected((string)old('parent_id', $record->parent_id ?? '') === (string)$choice->id)>{{ \App\Support\InfrastructureDisplay::name($choice) }}</option>@endforeach
        </select>
        <small class="subtle">Chỉ chọn vị trí chi tiết đã được xác nhận.</small>@include('m01.infra-error',['field'=>'parent_id'])
    </label>
    @if($parentChoices->isEmpty())<p class="subtle">Chưa có công trình phù hợp. Người quản trị dữ liệu có thể <a href="/workspace/catalog?flow=infrastructure&amp;airport={{ $airport->code }}">khai báo công trình hoặc khu vực</a> trước khi lập đề nghị.</p>@endif
@endif
