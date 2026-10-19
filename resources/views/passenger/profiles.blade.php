@extends('layouts.portal')
@section('title', 'Hồ sơ hành khách')
@section('content')
<form method="get" class="d-flex gap-2 mb-3"><label class="visually-hidden" for="profile_search">Tìm theo tên</label><input id="profile_search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Tìm theo tên"><button class="btn btn-primary">Tìm</button></form>
<div class="table-responsive"><table class="table bg-white"><thead><tr><th>Họ tên</th><th>Giấy tờ đã che</th><th>Chi tiết</th></tr></thead><tbody>
@forelse($profiles as $item)<tr><td>{{ $item->full_name }}</td><td>****{{ substr($item->id_card_passport, -4) }}</td><td><a href="{{ route('passenger.profiles.show', $item->id) }}">Xem hồ sơ</a></td></tr>@empty<tr><td colspan="3">Chưa có hồ sơ hành khách phù hợp.</td></tr>@endforelse
</tbody></table></div>{{ $profiles->links() }}
@can('passenger.write', [['self' => auth()->id()]])
<h2 class="h5 mt-4">Thêm hồ sơ</h2><form method="post" action="{{ route('passenger.profiles.store') }}" class="card p-4">@csrf
<div class="row g-3">@include('passenger.profile-fields', ['profile' => null])</div>
<label class="mt-3"><input type="checkbox" name="is_self" value="1" checked> Đây là hồ sơ cá nhân của tôi (bỏ chọn khi thêm người đi cùng).</label>
<label class="my-3"><input type="checkbox" name="consent" value="1" required> Tôi đồng ý lưu và xử lý các thông tin này để cung cấp dịch vụ, theo lựa chọn consent phiên bản local-policy-v1.</label>
<button class="btn btn-primary">Xác nhận tạo hồ sơ</button></form>
@endcan
@endsection
