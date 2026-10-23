@extends('layouts.portal')
@section('title', 'Tài khoản của tôi')
@section('content')
<p>Xin chào {{ auth()->user()->full_name }}.</p>
<form method="post" action="{{ route('account.password') }}" class="card p-4 account-form">@csrf
<h2 class="h5">Đổi mật khẩu</h2>
<label for="current_password">Mật khẩu hiện tại</label><input id="current_password" name="current_password" type="password" class="form-control mb-3" required autocomplete="current-password">
<label for="password">Mật khẩu mới (ít nhất 12 ký tự, có chữ và số)</label><input id="password" name="password" type="password" class="form-control mb-3" required autocomplete="new-password">
<label for="password_confirmation">Nhập lại mật khẩu</label><input id="password_confirmation" name="password_confirmation" type="password" class="form-control mb-3" required autocomplete="new-password">
<button class="btn btn-primary">Đổi mật khẩu và đăng xuất</button></form>
@endsection
