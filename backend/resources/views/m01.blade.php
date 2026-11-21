@php
    $airportTable = $tables[0] ?? ['title' => 'Sân bay', 'columns' => [], 'rows' => collect()];
    $routeTable = $tables[1] ?? ['title' => 'Đường bay', 'columns' => [], 'rows' => collect()];
    $resourceTable = $tables[2] ?? ['title' => 'Tài nguyên sân bay', 'columns' => [], 'rows' => collect()];
    $changeTable = $tables[3] ?? ['title' => 'Phiên bản dữ liệu', 'columns' => [], 'rows' => collect()];
    $formGroups = collect($forms)->groupBy('action');
    $fieldId = static fn (string $prefix, array $field): string => $prefix.'-'.str_replace(['[', ']'], '-', $field['name']);
@endphp

<div class="m01-page">
    <header class="m01-hero">
        <div>
            <span class="eyebrow">M01 · Dữ liệu nền</span>
            <h1>Quản lý sân bay</h1>
            <p class="subtle">Hồ sơ sân bay, cấu trúc khu vực, tài nguyên và phiên bản dữ liệu trong phạm vi quản trị.</p>
        </div>
        <div class="m01-hero-actions">
            <span class="m01-scope">{{ auth()->user()->airport_code ?: 'Toàn hệ thống' }}</span>
            <a class="btn btn-primary" href="#m01-admin-tools">Công cụ quản trị</a>
        </div>
    </header>

    <nav class="m01-tabs" aria-label="Khu vực quản lý sân bay">
        <a href="#m01-airports">Sân bay</a>
        <a href="#m01-infrastructure">Cơ sở hạ tầng</a>
        <a href="#m01-admin-tools">Quản trị dữ liệu</a>
    </nav>

    <section id="m01-airports" class="card-surface m01-section">
        <div class="m01-section-heading">
            <div>
                <span class="eyebrow">M01-F01</span>
                <h2>Danh sách sân bay</h2>
                <p class="subtle">Tra cứu mã IATA, tên, thành phố và múi giờ. Chọn một dòng để xem thông tin hiện có.</p>
            </div>
            @if($formGroups->has('airport'))
                <a class="btn btn-outline-primary" href="#m01-airport-form">Thêm sân bay</a>
            @endif
        </div>

        <form method="get" action="/workspace/catalog" class="m01-filter-bar" role="search">
            <label class="m01-search-field" for="m01-q"><span>Tìm sân bay</span><input class="form-control" id="m01-q" name="q" value="{{ request('q') }}" placeholder="Mã, tên hoặc thành phố"></label>
            <button class="btn btn-outline-primary" type="submit">Tìm kiếm</button>
            @if(request('q'))<a class="btn btn-outline-secondary" href="/workspace/catalog">Xóa lọc</a>@endif
        </form>

        <div class="table-responsive m01-table-wrap">
            <table class="table align-middle m01-table">
                <caption class="visually-hidden">Danh sách sân bay</caption>
                <thead><tr>@foreach($airportTable['columns'] as $key => $label)<th scope="col">{{ $label }}</th>@endforeach<th scope="col">Thao tác</th></tr></thead>
                <tbody>
                    @forelse($airportTable['rows'] as $row)
                        <tr>
                            @foreach($airportTable['columns'] as $key => $label)<td class="text-break-all">{{ $presentation->cell($key, $row->$key ?? null) }}</td>@endforeach
                            <td><a class="btn btn-sm btn-outline-primary" href="/workspace/catalog?q={{ urlencode($row->code) }}">Xem dữ liệu liên quan</a></td>
                        </tr>
                        <tr class="m01-inline-detail"><td colspan="{{ count($airportTable['columns']) + 1 }}"><details><summary>Chi tiết {{ $row->code }}</summary><dl class="m01-detail-grid"><div><dt>Mã IATA</dt><dd>{{ $row->code }}</dd></div><div><dt>Tên</dt><dd>{{ $row->name }}</dd></div><div><dt>Thành phố</dt><dd>{{ $row->city }}</dd></div><div><dt>Múi giờ</dt><dd>{{ $row->timezone }}</dd></div></dl></details></td></tr>
                    @empty
                        <tr><td colspan="{{ count($airportTable['columns']) + 1 }}" class="m01-empty">Không có sân bay phù hợp.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $airportTable['rows']->links('pagination::bootstrap-5') }}
    </section>

    <section id="m01-infrastructure" class="m01-section-grid">
        <section class="card-surface m01-section">
            <div class="m01-section-heading"><div><span class="eyebrow">M01-F02</span><h2>Tài nguyên sân bay</h2><p class="subtle">Nhà ga, cửa, vị trí đỗ và quầy thuộc danh mục hiện có.</p></div></div>
            <div class="table-responsive m01-table-wrap"><table class="table align-middle m01-table"><caption class="visually-hidden">Tài nguyên sân bay</caption><thead><tr>@foreach($resourceTable['columns'] as $key => $label)<th scope="col">{{ $label }}</th>@endforeach</tr></thead><tbody>@forelse($resourceTable['rows'] as $row)<tr>@foreach($resourceTable['columns'] as $key => $label)<td class="text-break-all">{{ $presentation->cell($key, $row->$key ?? null) }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($resourceTable['columns']) }}" class="m01-empty">Không có tài nguyên phù hợp.</td></tr>@endforelse</tbody></table></div>{{ $resourceTable['rows']->links('pagination::bootstrap-5') }}
        </section>

        <section class="card-surface m01-section">
            <div class="m01-section-heading"><div><span class="eyebrow">Cấu trúc khu vực</span><h2>Nhà ga và khu vực</h2><p class="subtle">Backend đã có cấu trúc khu vực phân cấp; giao diện hiện tại chưa có endpoint đọc riêng để dựng cây chi tiết.</p></div></div>
            <div class="m01-limitation" role="note">Có thể tạo khu vực trong công cụ quản trị bên dưới. Việc xem cây Terminal → Zone → Gate/Stand/Counter sẽ được bổ sung khi Backend cung cấp dữ liệu đọc tương ứng.</div>
        </section>
    </section>

    <section id="m01-admin-tools" class="m01-section">
        <details class="card-surface m01-admin-panel" open>
            <summary><span><span class="eyebrow">M01-F03–F05</span><strong>Quản trị dữ liệu</strong><small>Kiểm tra, nhập/xuất, đề nghị sửa và phê duyệt phiên bản</small></span><span class="m01-summary-action">Mở khu vực</span></summary>
            <div class="m01-admin-content">
                <div class="m01-admin-grid">
                    <section id="m01-airport-form" class="m01-tool-card">
                        <h3>Thêm sân bay</h3><p class="subtle">Mã IATA, tên, thành phố và múi giờ là bắt buộc.</p>
                        @if($formGroups->has('airport')) @include('m01-form', ['form' => $formGroups->get('airport')->first(), 'formKey' => 'm01-airport']) @else <p class="m01-limitation">Role hiện tại không có quyền tạo sân bay.</p>@endif
                    </section>
                    <section class="m01-tool-card">
                        <h3>Nhập và xuất danh mục</h3><p class="subtle">CSV chỉ nhập sân bay, tối đa 200 dòng. Dữ liệu nhập được đưa vào hàng chờ duyệt.</p>
                        <form method="post" action="/workspace/master/import" enctype="multipart/form-data" class="m01-import-form">@csrf<label class="form-label" for="m01-master-file">Tệp CSV UTF-8</label><input class="form-control" type="file" id="m01-master-file" name="file" accept=".csv,.txt" required><button class="btn btn-primary" type="submit">Đưa vào hàng chờ</button></form>
                        <div class="m01-export-links"><a href="/airports-import-template.csv" download>Mẫu CSV sân bay</a><a href="/workspace/master/export/airports">Xuất sân bay</a><a href="/workspace/master/export/airport_resources">Xuất tài nguyên</a></div>
                    </section>
                </div>

                @if($formGroups->has('resource') || $formGroups->has('airport_area'))
                    <div class="m01-admin-grid">
                        @foreach($formGroups->get('resource', collect()) as $form)<section class="m01-tool-card"><h3>{{ $form['title'] }}</h3>@include('m01-form', ['form' => $form, 'formKey' => 'm01-resource-'.$loop->index])</section>@endforeach
                        @foreach($formGroups->get('airport_area', collect()) as $form)<section class="m01-tool-card"><h3>Khai báo khu vực sân bay</h3>@include('m01-form', ['form' => $form, 'formKey' => 'm01-area-'.$loop->index])</section>@endforeach
                    </div>
                @endif

                <section class="m01-version-section"><div class="m01-section-heading"><div><span class="eyebrow">Phiên bản và audit</span><h3>Đề nghị đang chờ xử lý</h3></div></div><div class="table-responsive m01-table-wrap"><table class="table align-middle m01-table"><caption class="visually-hidden">Phiên bản dữ liệu sân bay</caption><thead><tr>@foreach($changeTable['columns'] as $key => $label)<th scope="col">{{ $label }}</th>@endforeach</tr></thead><tbody>@forelse($changeTable['rows'] as $row)<tr>@foreach($changeTable['columns'] as $key => $label)<td class="text-break-all">{{ $presentation->cell($key, $row->$key ?? null) }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($changeTable['columns']) }}" class="m01-empty">Chưa có đề nghị thay đổi.</td></tr>@endforelse</tbody></table></div>{{ $changeTable['rows']->links('pagination::bootstrap-5') }}</section>

                <div class="m01-admin-grid">
                    @foreach($formGroups->get('master_propose', collect()) as $form)<section class="m01-tool-card"><h3>{{ $form['title'] }}</h3>@include('m01-form', ['form' => $form, 'formKey' => 'm01-propose-'.$loop->index])</section>@endforeach
                    @foreach($formGroups->get('master_approve', collect()) as $form)<section class="m01-tool-card"><h3>{{ $form['title'] }}</h3><p class="subtle">Chỉ chọn đề nghị do người khác tạo.</p>@include('m01-form', ['form' => $form, 'formKey' => 'm01-approve-'.$loop->index])</section>@endforeach
                </div>
            </div>
        </details>
    </section>

    <section id="m02-routes" class="m01-section">
        <details class="card-surface m01-secondary-panel">
            <summary><span><span class="eyebrow">Ngoài phạm vi M01</span><strong>M02 · Đường bay</strong><small>Giữ nguyên nghiệp vụ hiện có để không làm mất chức năng route.</small></span><span class="m01-summary-action">Mở danh mục M02</span></summary>
            <div class="m01-admin-content"><div class="table-responsive m01-table-wrap"><table class="table align-middle m01-table"><caption class="visually-hidden">Đường bay</caption><thead><tr>@foreach($routeTable['columns'] as $key => $label)<th scope="col">{{ $label }}</th>@endforeach</tr></thead><tbody>@forelse($routeTable['rows'] as $row)<tr>@foreach($routeTable['columns'] as $key => $label)<td class="text-break-all">{{ $presentation->cell($key, $row->$key ?? null) }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($routeTable['columns']) }}" class="m01-empty">Không có đường bay phù hợp.</td></tr>@endforelse</tbody></table></div>{{ $routeTable['rows']->links('pagination::bootstrap-5') }}@foreach($formGroups->get('route', collect()) as $form)<div class="m01-tool-card m01-route-form"><h3>{{ $form['title'] }}</h3>@include('m01-form', ['form' => $form, 'formKey' => 'm01-route-'.$loop->index])</div>@endforeach</div>
        </details>
    </section>
</div>
