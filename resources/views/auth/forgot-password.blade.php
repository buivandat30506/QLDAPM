@extends('layouts.portal')
@section('title', 'Khôi phục mật khẩu')
@section('content')
<p>Chế độ mô phỏng: hướng dẫn được lưu trong hộp thư riêng tư trên máy chủ, không gửi email thật.</p>
<form method="post" action="{{ route('password.email') }}" class="card p-4 account-form">@csrf
<label for="email">Email tài khoản</label><input id="email" class="form-control mb-3" type="email" name="email" required autocomplete="email">
<button class="btn btn-primary">Yêu cầu khôi phục mật khẩu</button></form>
@endsection
