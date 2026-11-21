@extends('layouts.app')
@push('styles')<link rel="stylesheet" href="/m02.css">@endpush
@push('scripts')<script src="/m02.js" defer></script>@endpush
@section('content')
<div class="m02">
    <div class="m02-heading"><div><p class="eyebrow">Quản lý chuyến bay</p><h1>@yield('heading')</h1></div><div class="m02-actions">@yield('actions')</div></div>
    @yield('body')
</div>
@endsection
