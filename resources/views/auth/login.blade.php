<!doctype html><html lang="vi"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Đăng nhập Smart Airline</title>@vite(['resources/css/app.css', 'resources/js/app.js'])<body class="login-page"><main>
<h1>Smart Airline</h1><p>Đăng nhập để thao tác theo vai trò.</p>
@foreach($errors->all() as $error)<p role="alert" style="color:#b91c1c">{{ $error }}</p>@endforeach
<form method="POST" action="{{ route('login.store') }}">@csrf
<p><label>Tên đăng nhập <input name="username" value="{{ old('username') }}" required autocomplete="username" aria-describedby="username-help"></label></p>
<p id="username-help">Dùng tên đăng nhập đã được cấp cho tài khoản.</p>
<p><label>Mật khẩu <input type="password" name="password" required autocomplete="current-password"></label></p>
<button type="submit">Đăng nhập</button></form><p><a href="{{ route('password.request') }}">Quên mật khẩu</a></p>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
</main></body></html>
