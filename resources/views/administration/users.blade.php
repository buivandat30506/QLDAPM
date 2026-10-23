@extends('layouts.portal')
@section('title', 'Tài khoản và phân quyền')
@section('content')
<form method="get" class="d-flex gap-2 mb-3"><label class="visually-hidden" for="search">Tên đăng nhập</label><input class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="Tìm tên đăng nhập"><button class="btn btn-primary">Tìm tài khoản</button></form>
<div class="table-responsive"><table class="table table-striped bg-white"><thead><tr><th>Tên đăng nhập</th><th>Họ tên</th><th>Trạng thái</th><th>Vai trò</th><th>Thao tác</th></tr></thead><tbody>
@forelse($users as $user)<tr><td>{{ $user->username }}</td><td>{{ $user->full_name }}</td><td>@include('partials.ui-label', ['uiGroup'=>'user_status', 'uiCode'=>$user->status])</td><td>{{ $user->roles->pluck('role_name')->join(', ') }}</td><td><a href="{{ route('administration.users.edit', $user) }}">Sửa quyền</a></td></tr>@empty<tr><td colspan="5">Không có tài khoản phù hợp.</td></tr>@endforelse
</tbody></table></div>{{ $users->links() }}
<h2 class="h5 mt-4">Tạo tài khoản</h2>
<form method="post" action="{{ route('administration.users.store') }}" class="card p-4">@csrf
@foreach(['username' => 'Tên đăng nhập', 'full_name' => 'Họ tên', 'email' => 'Email'] as $field => $label)<label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" class="form-control mb-2" value="{{ old($field) }}" required>@endforeach
<label for="new_password">Mật khẩu ban đầu (ít nhất 12 ký tự)</label><input type="password" id="new_password" name="password" class="form-control mb-2" required minlength="12" autocomplete="new-password">
<input type="hidden" name="status" value="ACTIVE"><p>Tài khoản mới chưa có quyền. Gán vai trò và phạm vi tại màn hình tiếp theo.</p><button class="btn btn-primary">Tạo tài khoản</button></form>
@endsection
