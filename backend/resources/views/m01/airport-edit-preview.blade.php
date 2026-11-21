@extends('layouts.app')
@section('title', 'Xem trước đề nghị · '.$airport->code.' · Airline Smart')
@section('content')
<div class="airport-page">
    <header class="airport-hero"><div><span class="eyebrow">{{ $airport->code }} · {{ $airport->name }}</span><h1>Xem trước đề nghị sửa hồ sơ</h1><p class="subtle">Thông tin hiện tại chưa thay đổi.</p></div></header>
    <section class="airport-card"><h2>Nội dung đề nghị</h2>
        @include('m01.airport-profile-diff',['differences'=>$differences,'beforeLabel'=>'Hiện tại','afterLabel'=>'Đề nghị'])
        <dl class="airport-info"><div class="airport-info-wide"><dt>Lý do sửa</dt><dd>{{ $data['reason'] }}</dd></div></dl>
        <form method="post" action="/workspace/catalog/airports/{{ $airport->code }}/changes" class="airport-form mt-4">@csrf
            <input type="hidden" name="preview_token" value="{{ $token }}">
            <div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit">Xác nhận gửi đề nghị</button><a class="btn btn-outline-secondary" href="/workspace/catalog/airports/{{ $airport->code }}?edit_proposal=1#airport-edit">Chỉnh sửa lại</a></div>
            <p class="subtle">Đề nghị cần người khác có quyền phê duyệt trước khi có hiệu lực.</p>
        </form>
    </section>
</div>
@endsection
