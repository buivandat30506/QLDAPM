@extends('layouts.app')
@section('title', $title.' · Airline Smart')
@section('content')
<div class="page-heading"><h1>{{ $title }}</h1></div>
<section class="card-surface empty" aria-label="Trạng thái nội dung">
    <span aria-hidden="true">◇</span>
    <h2>Chưa có thông tin hiển thị</h2>
    <p class="subtle">Không có nội dung tại khu vực này trong phiên bản hiện tại.</p>
</section>
@endsection
