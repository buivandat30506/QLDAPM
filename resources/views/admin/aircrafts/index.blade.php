@extends('layouts.admin')

@section('title', 'Quản lý Đội tàu bay')
@section('page_title', 'Quản Lý Đội Tàu Bay & Dòng Tàu Bay (UC-10, UC-11)')

@section('content')
<div class="row g-4 mb-4">
    <!-- Danh sách Dòng máy bay (Models) -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-box me-2 text-primary"></i> Dòng Máy Bay (Models)</span>
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModelModal">
                    <i class="bi bi-plus-lg"></i> Thêm Model
                </button>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($models as $model)
                        <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="mb-0 fw-bold">{{ $model->model_code }}</h6>
                                <small class="text-muted">{{ $model->manufacturer }} | {{ $model->seat_capacity }} ghế</small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-light text-dark border d-block mb-1">Quay đầu: {{ $model->turnaround_time_minutes }}p</span>
                                <small class="text-primary fw-semibold">{{ $model->aircrafts_count ?? count($model->aircrafts) }} tàu bay</small>
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-center py-3 text-muted">Chưa có dòng máy bay nào.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <!-- Danh sách Đội tàu bay (Fleet) -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-airplane-fill me-2 text-success"></i> Đội Tàu Bay Khai Thác</span>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#createAircraftModal">
                    <i class="bi bi-plus-lg me-1"></i> Tiếp Nhận Tàu Bay Mới
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Số đăng ký</th>
                                <th>Dòng máy bay</th>
                                <th>Năm SX</th>
                                <th>Tích lũy giờ bay / Chu kỳ</th>
                                <th>Trạng thái</th>
                                <th class="text-end">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($aircrafts as $aircraft)
                                <tr>
                                    <td>
                                        <code class="fs-6 fw-bold text-dark">{{ $aircraft->tail_number }}</code>
                                        @if($aircraft->needsMaintenance())
                                            <span class="badge bg-danger ms-1" title="Vượt ngưỡng bảo dưỡng an toàn"><i class="bi bi-tools"></i> Bảo dưỡng</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $aircraft->model->model_code ?? '---' }}</strong>
                                        <small class="text-muted d-block">{{ $aircraft->model->manufacturer ?? '' }}</small>
                                    </td>
                                    <td>{{ $aircraft->manufacture_year }}</td>
                                    <td>
                                        <div><i class="bi bi-clock-history me-1 text-muted"></i>{{ number_format($aircraft->total_flight_hours, 1) }} giờ</div>
                                        <small class="text-muted">{{ $aircraft->total_flight_cycles }} chu kỳ (cycles)</small>
                                    </td>
                                    <td>
                                        @switch($aircraft->operational_status)
                                            @case('OPERATIONAL')
                                                <span class="badge bg-success">KHẢ DỤNG</span>
                                                @break
                                            @case('MAINTENANCE')
                                                <span class="badge bg-warning text-dark">BẢO DƯỠNG</span>
                                                @break
                                            @case('GROUNDED')
                                                <span class="badge bg-danger">CẤM BAY</span>
                                                @break
                                        @endswitch
                                    </td>
                                    <td class="text-end">
                                        @include('admin.partials.edit-form', [
                                            'action' => route('admin.aircrafts.update', $aircraft->tail_number), 'record' => $aircraft,
                                            'fields' => [
                                                'model_id' => ['label' => 'Dòng tàu bay', 'options' => $models->pluck('model_code', 'id')],
                                                'manufacture_year' => ['label' => 'Năm sản xuất', 'type' => 'number', 'min' => 1970, 'max' => date('Y') + 1],
                                                'total_flight_hours' => ['label' => 'Giờ bay (từ chuyến hoàn tất)', 'type' => 'number', 'min' => 0, 'step' => '0.01', 'readonly' => true],
                                                'total_flight_cycles' => ['label' => 'Chu kỳ bay (từ chuyến hoàn tất)', 'type' => 'number', 'min' => 0, 'readonly' => true],
                                                'operational_status' => ['label' => 'Trạng thái', 'options' => ['OPERATIONAL' => 'Khai thác', 'MAINTENANCE' => 'Bảo dưỡng', 'GROUNDED' => 'Cấm bay']],
                                            ],
                                        ])
                                        <form action="{{ route('admin.aircrafts.destroy', $aircraft->tail_number) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa tàu bay {{ $aircraft->tail_number }} khỏi hệ thống?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Chưa có tàu bay nào trong đội bay.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Thêm Dòng Máy Bay -->
<div class="modal fade" id="createModelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.aircraft_models.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Thêm Mới Dòng Máy Bay (Aircraft Model)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mã dòng máy bay <span class="text-danger">*</span></label>
                        <input type="text" name="model_code" class="form-control text-uppercase" placeholder="VD: A321-200, B787-9" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nhà sản xuất <span class="text-danger">*</span></label>
                        <input type="text" name="manufacturer" class="form-control" placeholder="VD: Airbus, Boeing" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Sức chứa số ghế <span class="text-danger">*</span></label>
                            <input type="number" name="seat_capacity" class="form-control" placeholder="VD: 220" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Thời gian quay đầu (phút) <span class="text-danger">*</span></label>
                            <input type="number" name="turnaround_time_minutes" class="form-control" placeholder="VD: 45" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Xác nhận Thêm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Thêm Tàu Bay Mới Vào Đội Bay -->
<div class="modal fade" id="createAircraftModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.aircrafts.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tiếp Nhận Tàu Bay Mới (UC-10)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Số đăng ký (Tail Number) <span class="text-danger">*</span></label>
                        <input type="text" name="tail_number" class="form-control text-uppercase" placeholder="VD: VN-A601" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Dòng máy bay <span class="text-danger">*</span></label>
                        <select name="model_id" class="form-select" required>
                            <option value="">-- Chọn dòng máy bay --</option>
                            @foreach($models as $m)
                                <option value="{{ $m->id }}">{{ $m->model_code }} ({{ $m->manufacturer }} - {{ $m->seat_capacity }} ghế)</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Năm sản xuất <span class="text-danger">*</span></label>
                            <input type="number" name="manufacture_year" class="form-control" value="{{ date('Y') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tổng giờ bay ban đầu</label>
                            <input type="number" step="0.01" name="total_flight_hours" class="form-control" value="0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tổng chu kỳ (cycles)</label>
                            <input type="number" name="total_flight_cycles" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Trạng thái kỹ thuật</label>
                        <select name="operational_status" class="form-select">
                            <option value="OPERATIONAL">Khả dụng khai thác (OPERATIONAL)</option>
                            <option value="MAINTENANCE">Đang bảo dưỡng kỹ thuật (MAINTENANCE)</option>
                            <option value="GROUNDED">Cấm bay tạm thời (GROUNDED)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success">Thêm vào Đội bay</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
