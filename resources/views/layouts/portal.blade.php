<!doctype html>
<html lang="vi">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('title', 'Smart Airline')</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="bg-light">
<nav class="navbar bg-dark navbar-dark px-4"><a class="navbar-brand" href="/account">Smart Airline</a><div class="d-flex flex-wrap gap-3 align-items-center">
@auth
@can('passenger.read', [['self' => auth()->id()]])<a class="text-white" href="{{ route('passenger.profiles') }}">Hồ sơ hành khách</a>@endcan
@if(app(\App\Shared\Security\AccessControl::class)->scopes(auth()->user(), 'assistance.read')->isNotEmpty())<a class="text-white" href="{{ route('passenger.assistance') }}">Yêu cầu hỗ trợ</a>@endif
@can('users.manage')<a class="text-white" href="{{ route('administration.users') }}">Tài khoản và quyền</a>@endcan
@can('audit.read')<a class="text-white" href="{{ route('administration.audit') }}">Nhật ký</a>@endcan
@can('operations.read')<a class="text-white" href="/admin/workflows">Điều hành</a>@endcan
<form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-sm btn-outline-light">Đăng xuất</button></form>
@endauth
</div></nav>
<main class="container py-4"><h1 class="h3 mb-4">@yield('title', 'Smart Airline')</h1>
@if(session('status'))<div role="status" class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div role="alert" class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')</main>
</body></html>
