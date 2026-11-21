@extends('layouts.app')
@section('title', $airport->code.' · Hồ sơ sân bay · Airline Smart')
@section('content')
@php
    $aw = \App\Support\AirportWorkspace::class;
    $ui = \App\Support\Ui::class;
    $canEdit = \App\Modules\Shared\Access::allows(auth()->user(), 'data_admin');
    $hours = isset($airport->operating_hours) ? json_decode($airport->operating_hours, true) : null;
@endphp
<div class="airport-page" data-airport-page>
    <a class="airport-back" href="{{ $aw::url($context) }}">← Quay lại danh sách sân bay</a>
    <header class="airport-hero"><div><span class="eyebrow">Hồ sơ sân bay · {{ $airport->code }}</span><h1>{{ $airport->name }}</h1><p class="subtle">{{ $airport->city }} · {{ $airport->timezone }}</p></div><div class="airport-hero-actions">@include('m01.status', ['status' => $airport->operational_status ?? null])@if($canEdit)<a class="btn btn-outline-primary" href="#airport-edit">Đề nghị sửa hồ sơ</a>@endif</div></header>
    <nav class="airport-tabs" aria-label="Thông tin sân bay"><a href="#airport-general">Thông tin chung</a>@if($canEdit)<a href="#airport-infrastructure">Cơ sở hạ tầng</a>@endif <a href="#airport-timezone">Múi giờ</a><a href="#airport-history">Lịch sử</a></nav>
    <div class="airport-detail-grid">
        <section class="airport-card" id="airport-general"><div class="airport-card-heading"><h2>Thông tin chung</h2></div><dl class="airport-info"><div><dt>Mã IATA</dt><dd>{{ $airport->code }}</dd></div><div><dt>Mã ICAO</dt><dd>{{ $airport->icao ?: 'Chưa có thông tin' }}</dd></div><div><dt>Tên sân bay</dt><dd>{{ $airport->name }}</dd></div><div><dt>Thành phố</dt><dd>{{ $airport->city }}</dd></div><div><dt>Mã quốc gia</dt><dd>{{ $airport->country_code ?: 'Chưa có thông tin' }}</dd></div><div><dt>Tọa độ</dt><dd>{{ isset($airport->latitude, $airport->longitude) ? $airport->latitude.', '.$airport->longitude : 'Chưa có thông tin' }}</dd></div></dl>
            @if($hours !== null)<details class="airport-inline-detail"><summary>Giờ hoạt động đã khai báo</summary>@include('m01.value-list', ['values' => $hours])</details>@endif
        </section>
        <section class="airport-card" id="airport-timezone"><h2>Múi giờ</h2><dl class="airport-info airport-info--single"><div><dt>Múi giờ IANA</dt><dd>{{ $airport->timezone }}</dd></div>@if($localTime)<div><dt>Giờ tại sân bay khi tải trang</dt><dd><time datetime="{{ $localTime->toIso8601String() }}">{{ $localTime->format('H:i:s · d/m/Y') }}</time> <small>UTC{{ $localTime->format('P') }}</small></dd></div><div><dt>Giờ UTC tương ứng</dt><dd>{{ $localTime->copy()->utc()->format('H:i:s · d/m/Y') }} UTC</dd></div>@else<div><dt>Quy đổi thời gian</dt><dd>Chưa quy đổi được múi giờ đã lưu.</dd></div>@endif</dl></section>
    </div>
    @if($canEdit)
    @if(app(\App\Modules\Operations\InfrastructureCompatibility::class)->ready())
        @include('m01.airport-infrastructure-summary')
    @else
    <section class="airport-card" id="airport-infrastructure"><div class="airport-card-heading"><div><h2>Cơ sở hạ tầng tại {{ $airport->code }}</h2><p class="subtle">Danh mục khai thác và cấu trúc khu vực được lưu riêng.</p></div><a class="btn btn-primary" href="{{ $aw::url(['flow' => 'infrastructure', 'airport' => $airport->code]) }}">Xem toàn bộ hạ tầng</a></div><div class="airport-detail-grid"><div><h3>Tài nguyên khai thác</h3>@forelse($resources as $group)<div class="airport-summary-row"><span>{{ $aw::TYPES[$group->type] ?? $group->type }}</span><a href="{{ $aw::url(['flow' => 'infrastructure', 'airport' => $airport->code, 'source' => 'resources', 'kind' => $group->type]) }}">{{ $group->total }} tài nguyên →</a></div>@empty<p class="subtle">Chưa có tài nguyên khai thác.</p>@endforelse</div><div><h3>Nhà ga & khu vực</h3>@forelse($areas as $group)<div class="airport-summary-row"><span>{{ $aw::TYPES[$group->type] ?? $group->type }}</span><a href="{{ $aw::url(['flow' => 'infrastructure', 'airport' => $airport->code, 'source' => 'areas', 'kind' => $group->type]) }}">{{ $group->total }} bản ghi →</a></div>@empty<p class="subtle">Chưa khai báo cấu trúc khu vực.</p>@endforelse</div></div></section>
    @endif
    @endif
    @include('m01.airport-status-panel')
    @if($canEdit) @include('m01.airport-edit') @endif
    @include('m01.airport-history')
</div>
@endsection
