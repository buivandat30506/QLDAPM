@extends('layouts.app')
@section('title', 'Thêm hạ tầng · Airline Smart')
@section('content')
<div class="airport-page"><nav class="airport-breadcrumb" aria-label="Đường dẫn"><a href="{{ \App\Support\AirportWorkspace::url($context) }}">{{ $airport->code }} · Cơ sở hạ tầng</a><span>/</span><span>{{ $type['label'] }}</span></nav><header class="airport-hero"><div><span class="eyebrow">{{ $airport->code }} · {{ $airport->name }}</span><h1>Thêm {{ mb_strtolower($type['label']) }}</h1></div></header><section class="airport-card"><form class="airport-form" method="post" action="/workspace/catalog/infrastructure/items">@csrf @include('m01.infrastructure-fields')<button type="submit" class="btn btn-primary">Tạo hồ sơ hạ tầng</button></form></section></div>
@endsection
