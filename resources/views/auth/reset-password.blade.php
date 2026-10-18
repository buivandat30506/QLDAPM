@extends('layouts.portal')
@section('title', 'Đặt lại mật khẩu')
@section('content')
<form method="post" action="{{ route('password.update') }}" class="card p-4">@csrf
<input type="hidden" name="token" value="{{ $token }}"><input type="hidden" name="email" value="{{ request('email') }}">
<label for="password">Mật khẩu mới (ít nhất 12 ký tự, có chữ và số)</label><input id="password" name="password" type="password" class="form-control mb-3" required autocomplete="new-password">
<label for="password_confirmation">Nhập lại mật khẩu</label><input id="password_confirmation" name="password_confirmation" type="password" class="form-control mb-3" required autocomplete="new-password">
<button class="btn btn-primary">Đặt lại mật khẩu</button></form>
@endsection
