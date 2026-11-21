@extends('layouts.app')
@section('title', 'Nhập hạ tầng · Airline Smart')
@section('content')
<div class="airport-page">
    <nav class="airport-breadcrumb" aria-label="Đường dẫn"><a href="/workspace/catalog?flow=infrastructure&amp;airport={{ $airport->code }}">{{ $airport->code }} · Cơ sở hạ tầng</a><span>/</span><span>Nhập dữ liệu</span></nav>
    <header class="airport-hero"><div><span class="eyebrow">{{ $airport->code }} · {{ $airport->name }}</span><h1>Nhập hạ tầng từ CSV</h1></div><a class="btn btn-outline-primary" href="/workspace/catalog/infrastructure/import/template">Tải tệp mẫu</a></header>
    @if(isset($rows))
    <section class="airport-card"><h2>Xác nhận {{ count($rows) }} hạng mục</h2><p>Sân bay quản lý: <strong>{{ $airport->code }}</strong> · Nguồn cung cấp: {{ $source }}</p><p class="subtle">Chỉ nhập các hạng mục đã được kiểm tra. Hệ thống sẽ kiểm tra lại trước khi lưu toàn bộ tệp.</p>
        <div class="table-responsive"><table class="table airport-table"><thead><tr><th>Mã / tên</th><th>Loại</th><th>Vị trí</th><th>Trạng thái</th><th>Thông tin bổ sung</th></tr></thead><tbody>
        @foreach($rows as $row)
        <tr><td>{{ $row['code'] }}@if(!empty($row['name']))<small class="d-block subtle">{{ $row['name'] }}</small>@endif</td><td>{{ \App\Support\InfrastructureCatalog::label($row['type_code']) }}</td><td>{{ $row['parent_code'] ?? '' ?: 'Sân bay '.$airport->code }}</td><td>{{ \App\Support\InfrastructureCatalog::CONDITIONS[$row['technical_state'] ?? ''] ?? 'Chưa xác định' }}</td><td>
            @foreach(\App\Support\InfrastructureCatalog::ATTRIBUTE_OPTIONS as $field=>$definition)@if(!empty($row[$field]))<div>{{ $definition['label'] }}: {{ $definition['options'][$row[$field]] ?? $row[$field] }}</div>@endif @endforeach
            @foreach(['description'=>'Mô tả','serves_departures'=>'Phục vụ khởi hành','serves_arrivals'=>'Phục vụ chuyến đến','asset_reference'=>'Mã tài sản','length_m'=>'Chiều dài (m)','width_m'=>'Chiều rộng (m)','limitations'=>'Giới hạn','directions'=>'Hướng khai thác','compatibility_notes'=>'Thông tin phục vụ máy bay','gate_codes'=>'Cổng liên kết','stand_codes'=>'Vị trí đỗ liên kết'] as $field=>$label)@if(isset($row[$field]) && $row[$field] !== '')<div>{{ $label }}: {{ in_array($field,['serves_departures','serves_arrivals']) ? ($row[$field] === '1' ? 'Có' : 'Không') : $row[$field] }}</div>@endif @endforeach
        </td></tr>
        @endforeach</tbody></table></div>
        <form method="post" action="/workspace/catalog/infrastructure/import" class="airport-form">@csrf<input type="hidden" name="token" value="{{ $token }}"><button type="submit" class="btn btn-primary">Xác nhận nhập {{ count($rows) }} hạng mục</button><a href="/workspace/catalog/infrastructure/import?airport={{ $airport->code }}">Chọn tệp khác</a></form>
    </section>
    @else
    <section class="airport-card"><h2>Chọn tệp đã xác minh</h2><p class="subtle">Tệp CSV UTF-8, tối đa 200 hạng mục / 1 MB. Chỉ thêm mới; không ghi đè hồ sơ hiện có. Công trình và đối tượng liên kết cần được khai báo trước.</p>
        <form method="post" action="/workspace/catalog/infrastructure/import/preview" enctype="multipart/form-data" class="airport-form">@csrf<input type="hidden" name="airport_code" value="{{ $airport->code }}">
            <label>Tài liệu hoặc người cung cấp<input class="form-control" name="source_reference" required maxlength="500" value="{{ old('source_reference') }}">@include('m01.infra-error',['field'=>'source_reference'])</label>
            <label>Tệp CSV<input class="form-control" type="file" name="file" accept=".csv,text/csv" required>@include('m01.infra-error',['field'=>'file'])</label>
            <button class="btn btn-primary" type="submit">Kiểm tra và xem trước</button>
        </form>
    </section>
    <details class="airport-card airport-disclosure"><summary>Hướng dẫn điền tệp</summary><p>Dùng tiêu đề trong tệp mẫu. Bắt buộc mã hạng mục và loại; các thông tin khác chỉ điền khi đã được cung cấp. Các danh sách hướng, cổng hoặc vị trí đỗ phân cách bằng dấu |. Có/Không dùng 1/0.</p><div class="table-responsive"><table class="table airport-table"><thead><tr><th>Loại hạ tầng</th><th>Giá trị trong tệp</th></tr></thead><tbody>@foreach(\App\Support\InfrastructureCatalog::LABELS as $code=>$label)<tr><td>{{ $label }}</td><td>{{ $code }}</td></tr>@endforeach</tbody></table></div></details>
    @endif
</div>
@endsection
