@extends('layouts.portal')
@section('title', 'Khôi phục mật khẩu')
@section('content')
<p>Kênh local-simulation lưu hướng dẫn trong hộp thư riêng tư trên máy chủ. Không gửi email thật.</p>
<form method="post" action="{{ route('password.email') }}" class="card p-4">@csrf
<label for="email">Email tài khoản</label><input id="email" class="form-control mb-3" type="email" name="email" required autocomplete="email">
<button class="btn btn-primary">Yêu cầu hướng dẫn</button></form>
@endsection
