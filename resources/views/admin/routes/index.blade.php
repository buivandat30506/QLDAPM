@extends('layouts.admin')

@section('title', 'Quản lý Đường bay & Tuyến bay')
@section('page_title', 'Quản Lý Đường Bay & Tuyến Bay (UC-04)')

@section('content')
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-signpost-split-fill me-2 text-info"></i> Danh Sách Đường Bay Khai Thác</span>
        <button type="button" class="btn btn-info text-white btn-sm" data-bs-toggle="modal" data-bs-target="#createRouteModal">
            <i class="bi bi-plus-lg me-1"></i> Thiết Lập Tuyến Bay Mới
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Mã tuyến</th>
                        <th>Sân bay xuất phát</th>
                        <th>Sân bay điểm đến</th>
                        <th>Khoảng cách</th>
                        <th>Thời gian bay tiêu chuẩn</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($routes as $route)
                        <tr>
                            <td><strong class="text-primary">{{ $route->route_code ?? ($route->origin_airport_code . '-' . $route->dest_airport_code) }}</strong></td>
                            <td>
                                <span class="badge bg-secondary fs-6">{{ $route->origin_airport_code }}</span>
                                <span class="ms-1">{{ $route->originAirport->name ?? '' }} ({{ $route->originAirport->city ?? '' }})</span>
                            </td>
                            <td>
                                <span class="badge bg-secondary fs-6">{{ $route->dest_airport_code }}</span>
                                <span class="ms-1">{{ $route->destAirport->name ?? '' }} ({{ $route->destAirport->city ?? '' }})</span>
                            </td>
                            <td>{{ number_format($route->distance_km, 1) }} km</td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-stopwatch me-1"></i> {{ $route->standard_duration_minutes }} phút ({{ floor($route->standard_duration_minutes / 60) }}h {{ $route->standard_duration_minutes % 60 }}p)
                                </span>
                            </td>
                            <td>
                                @if($route->status === 'ACTIVE')
                                    <span class="badge bg-success">KHAI THÁC</span>
                                @else
                                    <span class="badge bg-warning text-dark">TẠM DỪNG</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @include('admin.partials.edit-form', [
                                    'action' => route('admin.routes.update', $route->id), 'record' => $route,
                                    'fields' => [
                                        'route_code' => ['label' => 'Mã đường bay'],
                                        'origin_airport_code' => ['label' => 'Sân bay đi', 'options' => $airports->pluck('iata_code', 'iata_code')],
                                        'dest_airport_code' => ['label' => 'Sân bay đến', 'options' => $airports->pluck('iata_code', 'iata_code')],
                                        'distance_km' => ['label' => 'Khoảng cách (km)', 'type' => 'number', 'min' => 1, 'step' => '0.01'],
                                        'standard_duration_minutes' => ['label' => 'Thời gian bay (phút)', 'type' => 'number', 'min' => 1],
                                        'status' => ['label' => 'Trạng thái', 'options' => ['ACTIVE' => 'Khai thác', 'SUSPENDED' => 'Tạm dừng']],
                                    ],
                                ])
                                <form action="{{ route('admin.routes.destroy', $route->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa đường bay này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Chưa có đường bay nào được thiết lập.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Thêm Tuyến Bay Mới -->
<div class="modal fade" id="createRouteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.routes.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Thiết Lập Tuyến Bay Mới (UC-04)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Mã tuyến bay (Tùy chọn)</label>
                        <input type="text" name="route_code" class="form-control text-uppercase" placeholder="VD: HAN-SGN (để trống sẽ tự động sinh)">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Sân bay xuất phát <span class="text-danger">*</span></label>
                            <select name="origin_airport_code" class="form-select" required>
                                <option value="">-- Chọn sân bay đi --</option>
                                @foreach($airports as $ap)
                                    <option value="{{ $ap->iata_code }}">{{ $ap->iata_code }} - {{ $ap->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Sân bay điểm đến <span class="text-danger">*</span></label>
                            <select name="dest_airport_code" class="form-select" required>
                                <option value="">-- Chọn sân bay đến --</option>
                                @foreach($airports as $ap)
                                    <option value="{{ $ap->iata_code }}">{{ $ap->iata_code }} - {{ $ap->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Khoảng cách (km) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="distance_km" class="form-control" placeholder="VD: 1160" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Thời gian bay chuẩn (phút) <span class="text-danger">*</span></label>
                            <input type="number" name="standard_duration_minutes" class="form-control" placeholder="VD: 130" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="ACTIVE">Khai thác (ACTIVE)</option>
                            <option value="SUSPENDED">Tạm ngưng (SUSPENDED)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-info text-white">Xác nhận Thêm</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
