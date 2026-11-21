@extends('layouts.app')
@section('title', 'Không tìm thấy dữ liệu sân bay · Airline Smart')
@section('content')
<div class="airport-card airport-empty" role="status"><h1>Không tìm thấy dữ liệu</h1><p>Sân bay hoặc tài nguyên không tồn tại trong phạm vi truy cập của bạn.</p><div class="airport-inline-links"><a class="btn btn-primary" href="/workspace/catalog?flow=airports">Danh sách sân bay</a><a class="btn btn-outline-primary" href="/workspace/catalog?flow=infrastructure">Chọn lại sân bay</a></div></div>
@endsection
