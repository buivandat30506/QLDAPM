@extends('layouts.app')
@section('title', 'Chưa tải được dữ liệu sân bay · Airline Smart')
@section('content')
<div class="airport-card airport-empty" role="alert"><h1>Chưa tải được dữ liệu sân bay</h1><p>Dữ liệu tạm thời chưa truy cập được. Hãy thử tải lại sau ít phút.</p><div class="airport-inline-links"><a class="btn btn-primary" href="{{ request()->getRequestUri() }}">Thử tải lại</a><a class="btn btn-outline-primary" href="/staff">Về bàn làm việc</a></div></div>
@endsection
