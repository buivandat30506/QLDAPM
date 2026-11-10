@extends('layouts.app')
@section('title', 'Cơ sở hạ tầng sân bay · Airline Smart')
@section('content')
@if(isset($unified))
    @include('m01.unified-infrastructure')
@else
@php($aw = \App\Support\AirportWorkspace::class)
<div class="airport-page" data-airport-page>
    <header class="airport-hero"><div><span class="eyebrow">M01-F02 · Danh mục hạ tầng</span><h1>Cơ sở hạ tầng</h1><p class="subtle">{{ $airport ? $airport->name.' · '.$airport->code : 'Chọn sân bay cần xem cơ sở hạ tầng.' }}</p></div>@if($airport)<a class="btn btn-outline-primary" href="{{ $aw::url(['flow' => 'infrastructure']) }}">Đổi sân bay</a>@endif</header>
    @if(!$airport)
        <section class="airport-card airport-picker"><span class="airport-picker-symbol" aria-hidden="true">✈</span><h2>Chọn sân bay cần xem cơ sở hạ tầng</h2><p class="subtle">Chỉ hiển thị dữ liệu thuộc sân bay bạn chọn.</p>
            @if($choices->isEmpty())<div class="airport-empty"><strong>Chưa có sân bay trong phạm vi truy cập</strong></div>@else
            <form method="get" class="airport-form" data-airport-loading><input type="hidden" name="flow" value="infrastructure"><label for="airport-choice">Sân bay<select class="form-select" id="airport-choice" name="airport" required data-airport-choices><option value="">Chọn sân bay…</option>@foreach($choices as $choice)<option value="{{ $choice->code }}">{{ $choice->code }} · {{ $choice->name }}</option>@endforeach</select></label><button class="btn btn-primary" type="submit">Xem cơ sở hạ tầng</button><span data-airport-loading-status role="status" hidden>Đang tải cơ sở hạ tầng…</span></form>
            @endif
        </section>
    @else
        <div class="airport-context"><div><strong>{{ $airport->code }}</strong><span>{{ $airport->name }}</span></div>@include('m01.status', ['status' => $airport->operational_status ?? null])<a href="/workspace/catalog/airports/{{ $airport->code }}">Hồ sơ sân bay →</a></div>
        <section class="airport-card">
            <nav class="airport-tabs" aria-label="Nguồn dữ liệu hạ tầng"><a href="{{ $aw::url(['flow' => 'infrastructure', 'airport' => $airport->code, 'source' => 'resources']) }}" @if($source === 'resources') aria-current="page" @endif>Tài nguyên khai thác</a><a href="{{ $aw::url(['flow' => 'infrastructure', 'airport' => $airport->code, 'source' => 'areas']) }}" @if($source === 'areas') aria-current="page" @endif>Nhà ga & khu vực</a></nav>
            <form method="get" class="airport-filters" data-airport-loading><input type="hidden" name="flow" value="infrastructure"><input type="hidden" name="airport" value="{{ $airport->code }}"><input type="hidden" name="source" value="{{ $source }}"><label for="infrastructure-q">Tìm {{ $source === 'areas' ? 'khu vực' : 'tài nguyên' }}<input class="form-control" type="search" id="infrastructure-q" name="q" maxlength="100" value="{{ request('q') }}" placeholder="{{ $source === 'areas' ? 'Mã hoặc tên khu vực' : 'Mã tài nguyên' }}"></label><label for="infrastructure-kind">Loại<select class="form-select" id="infrastructure-kind" name="kind"><option value="">Tất cả loại hiện có</option>@foreach($types as $type)<option value="{{ $type }}" @selected(request('kind') === $type)>{{ $aw::TYPES[$type] ?? $type }}</option>@endforeach</select></label><button type="submit" class="btn btn-primary">Lọc</button><a href="{{ $aw::url(['flow' => 'infrastructure', 'airport' => $airport->code, 'source' => $source]) }}" class="btn btn-outline-secondary">Xóa lọc</a><span role="status" data-airport-loading-status hidden>Đang tải dữ liệu…</span></form>
            <div class="table-responsive"><table class="table airport-table align-middle"><caption class="visually-hidden">Cơ sở hạ tầng thuộc {{ $airport->code }}</caption><thead><tr><th>Mã</th>@if($source === 'areas')<th>Tên khu vực</th>@endif<th>Loại</th>@if($source === 'areas')<th>Thuộc khu vực</th>@endif<th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
                @forelse($items as $item)
                    @php($detail = '/workspace/catalog/infrastructure/'.$source.'/'.$item->id.'?'.http_build_query($context))
                    <tr><td><a class="airport-name" href="{{ $detail }}">{{ $item->code }}</a></td>@if($source === 'areas')<td>{{ $item->name }}</td>@endif<td>{{ $aw::TYPES[$item->type] ?? $item->type }}</td>@if($source === 'areas')<td>{{ $item->parent_id ? ($parents[$item->parent_id] ?? 'Không có thông tin khu vực cha') : '—' }}</td>@endif<td>@include('m01.infrastructure-state', ['effective'=>$item->active,'condition'=>null])</td><td><a class="airport-detail-link" href="{{ $detail }}" aria-label="Xem chi tiết {{ $item->code }}">Xem chi tiết →</a></td></tr>
                @empty<tr><td colspan="{{ $source === 'areas' ? 6 : 4 }}"><div class="airport-empty"><strong>{{ request('q') || request('kind') ? 'Không tìm thấy kết quả' : 'Chưa có dữ liệu' }}</strong><p>{{ request('q') || request('kind') ? 'Thử thay đổi bộ lọc trong sân bay đã chọn.' : 'Chưa có danh mục này tại '.$airport->code.'.' }}</p></div></td></tr>@endforelse
            </tbody></table></div><div class="airport-pagination"><span>{{ $items->total() }} bản ghi · {{ $airport->code }}</span>{{ $items->links('pagination::bootstrap-5') }}</div>
        </section>
        @if($source === 'resources')
        <details class="airport-card airport-disclosure" id="resource-create"><summary>Thêm tài nguyên tại {{ $airport->code }}</summary><form class="airport-form" method="post" action="/workspace/actions/resource">@csrf<input type="hidden" name="airport_code" value="{{ $airport->code }}"><div class="airport-form-grid"><label>Mã tài nguyên<input class="form-control" name="code" required maxlength="30" value="{{ old('code') }}"></label><label>Loại<select class="form-select" name="type" required>@foreach(['TERMINAL','GATE','STAND','COUNTER'] as $type)<option value="{{ $type }}">{{ $aw::TYPES[$type] }}</option>@endforeach</select></label></div><button class="btn btn-primary" type="submit">Thêm tại {{ $airport->code }}</button></form></details>
        @endif
    @endif
</div>
@endif
@endsection
