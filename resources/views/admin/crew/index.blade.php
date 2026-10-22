@extends('layouts.admin')

@section('title', 'Quản lý Phi hành đoàn')
@section('page_title', 'Quản Lý Hồ Sơ & Phân Công Tổ Bay (UC-15, UC-16, UC-17)')

@section('content')
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-people-fill me-2 text-secondary"></i> Danh Sách Phi Hành Đoàn (Crew Members)</span>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignCrewModal">
                <i class="bi bi-person-check-fill me-1"></i> Phân Công Chuyến Bay
            </button>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createCrewModal">
                <i class="bi bi-person-plus-fill me-1"></i> Thêm Nhân Sự Mới
            </button>
        </div>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form method="GET" action="{{ route('admin.crew') }}" class="row g-2 mb-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Tìm theo mã, tên, số bằng..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="rank" class="form-select form-select-sm">
                    <option value="">-- Cấp bậc (Rank) --</option>
                    <option value="CAPTAIN" {{ request('rank') === 'CAPTAIN' ? 'selected' : '' }}>Cơ trưởng (CAPTAIN)</option>
                    <option value="FIRST_OFFICER" {{ request('rank') === 'FIRST_OFFICER' ? 'selected' : '' }}>Cơ phó (FIRST_OFFICER)</option>
                    <option value="PURSER" {{ request('rank') === 'PURSER' ? 'selected' : '' }}>Tiếp viên trưởng (PURSER)</option>
                    <option value="FLIGHT_ATTENDANT" {{ request('rank') === 'FLIGHT_ATTENDANT' ? 'selected' : '' }}>Tiếp viên (FLIGHT_ATTENDANT)</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- Trạng thái khả dụng --</option>
                    <option value="AVAILABLE" {{ request('status') === 'AVAILABLE' ? 'selected' : '' }}>Sẵn sàng (AVAILABLE)</option>
                    <option value="ON_DUTY" {{ request('status') === 'ON_DUTY' ? 'selected' : '' }}>Đang làm nhiệm vụ (ON_DUTY)</option>
                    <option value="RESTING" {{ request('status') === 'RESTING' ? 'selected' : '' }}>Nghỉ ngơi (RESTING)</option>
                    <option value="LEAVE" {{ request('status') === 'LEAVE' ? 'selected' : '' }}>Nghỉ phép (LEAVE)</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-secondary w-100"><i class="bi bi-search me-1"></i> Lọc danh sách</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Mã nhân sự</th>
                        <th>Họ và tên</th>
                        <th>Cấp bậc (Rank)</th>
                        <th>Căn cứ (Base)</th>
                        <th>Số bằng lái (License)</th>
                        <th>Tích lũy giờ bay</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($crews as $crew)
                        <tr>
                            <td><strong class="text-primary">{{ $crew->crew_code }}</strong></td>
                            <td>
                                <div class="fw-bold text-dark">{{ $crew->full_name }}</div>
                                <small class="text-muted">{{ $crew->email }} | {{ $crew->phone }}</small>
                            </td>
                            <td>
                                @switch($crew->rank)
                                    @case('CAPTAIN')
                                        <span class="badge bg-danger"><i class="bi bi-award-fill me-1"></i> CƠ TRƯỞNG</span>
                                        @break
                                    @case('FIRST_OFFICER')
                                        <span class="badge bg-warning text-dark"><i class="bi bi-star-fill me-1"></i> CƠ PHÓ</span>
                                        @break
                                    @case('PURSER')
                                        <span class="badge bg-info text-dark">TIẾP VIÊN TRƯỞNG</span>
                                        @break
                                    @case('FLIGHT_ATTENDANT')
                                        <span class="badge bg-secondary">TIẾP VIÊN</span>
                                        @break
                                @endswitch
                            </td>
                            <td><span class="badge bg-light text-dark border">{{ $crew->base_airport_code }}</span></td>
                            <td><code>{{ $crew->license_number }}</code></td>
                            <td>{{ number_format($crew->total_flight_hours, 1) }} giờ</td>
                            <td>
                                @switch($crew->status)
                                    @case('AVAILABLE')
                                        <span class="badge bg-success">KHẢ DỤNG</span>
                                        @break
                                    @case('ON_DUTY')
                                        <span class="badge bg-primary">ĐANG BAY</span>
                                        @break
                                    @case('RESTING')
                                        <span class="badge bg-info text-dark">NGHỈ NGƠI</span>
                                        @break
                                    @case('LEAVE')
                                        <span class="badge bg-secondary">NGHỈ PHÉP</span>
                                        @break
                                @endswitch
                            </td>
                            <td class="text-end">
                                @include('admin.partials.edit-form', [
                                    'action' => route('admin.crew.update', $crew->id), 'record' => $crew,
                                    'fields' => [
                                        'full_name' => ['label' => 'Họ tên'], 'email' => ['label' => 'Email', 'type' => 'email'],
                                        'phone' => ['label' => 'Điện thoại'],
                                        'license_expires_on' => ['label' => 'Hạn giấy phép', 'type' => 'date'],
                                        'training_expires_on' => ['label' => 'Hạn đào tạo', 'type' => 'date'],
                                        'qualified_model_ids' => ['label' => 'Năng định', 'multiple' => true, 'options' => \App\Models\AircraftModel::pluck('model_code', 'id')],
                                        'available_from_utc' => ['label' => 'Khả dụng từ UTC', 'type' => 'datetime-local', 'required' => false],
                                        'available_until_utc' => ['label' => 'Khả dụng đến UTC', 'type' => 'datetime-local', 'required' => false],
                                        'rank' => ['label' => 'Cấp bậc', 'options' => ['CAPTAIN' => 'Cơ trưởng', 'FIRST_OFFICER' => 'Cơ phó', 'PURSER' => 'Tiếp viên trưởng', 'FLIGHT_ATTENDANT' => 'Tiếp viên']],
                                        'base_airport_code' => ['label' => 'Sân bay căn cứ', 'options' => $airports->pluck('iata_code', 'iata_code')],
                                        'status' => ['label' => 'Trạng thái', 'options' => ['AVAILABLE' => 'Sẵn sàng', 'ON_DUTY' => 'Đang trực', 'RESTING' => 'Nghỉ', 'LEAVE' => 'Nghỉ phép']],
                                    ],
                                ])
                                <form action="{{ route('admin.crew.destroy', $crew->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa hồ sơ nhân sự {{ $crew->crew_code }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">Chưa có hồ sơ tổ bay nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Thêm Nhân Sự Tổ Bay Mới (UC-15) -->
<div class="card mb-4">
    <div class="card-header">Phân công tổ bay hiện tại</div>
    <div class="card-body table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Chuyến bay</th><th>Nhân sự</th><th>Vị trí</th><th>Ca trực (UTC)</th><th>Hành động</th></tr></thead>
            <tbody>
                @forelse($assignments as $assignment)
                    <tr>
                        <td>{{ $assignment->flight->flight_number }} / {{ $assignment->flight->flight_date->format('Y-m-d') }}</td>
                        <td>{{ $assignment->crewMember->full_name }}</td>
                        <td>{{ $assignment->assigned_position }}</td>
                        <td>{{ $assignment->duty_start_utc->format('Y-m-d H:i') }} — {{ $assignment->duty_end_utc->format('Y-m-d H:i') }}</td>
                        <td>
                            <form action="{{ route('admin.crew.assignments.destroy', $assignment->id) }}" method="POST" onsubmit="return confirm('Hủy phân công này?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">Hủy phân công</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Chưa có phân công.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="createCrewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.crew.store') }}" method="POST">
                @csrf
                <div class="p-3 bg-light">
                    <label class="form-label">Hạn giấy phép<input type="date" name="license_expires_on" class="form-control" required></label>
                    <label class="form-label">Hạn đào tạo<input type="date" name="training_expires_on" class="form-control" required></label>
                    <label class="form-label d-block">Năng định (chọn các dòng máy bay)</label>
                    @foreach(\App\Models\AircraftModel::all() as $model)
                        <label class="me-3"><input type="checkbox" name="qualified_model_ids[]" value="{{ $model->id }}"> {{ $model->model_code }}</label>
                    @endforeach
                </div>
                <div class="modal-header">
                    <h5 class="modal-title">Thêm Mới Hồ Sơ Tổ Bay (UC-15)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Mã nhân sự <span class="text-danger">*</span></label>
                            <input type="text" name="crew_code" class="form-control text-uppercase" placeholder="VD: CRW-001" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Họ và tên <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" placeholder="VD: Nguyễn Văn Tuấn" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="VD: pilot@smart-airline.vn" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Điện thoại <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" placeholder="VD: 0912345678" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Cấp bậc (Rank) <span class="text-danger">*</span></label>
                            <select name="rank" class="form-select" required>
                                <option value="CAPTAIN">Cơ trưởng (CAPTAIN)</option>
                                <option value="FIRST_OFFICER">Cơ phó (FIRST_OFFICER)</option>
                                <option value="PURSER">Tiếp viên trưởng (PURSER)</option>
                                <option value="FLIGHT_ATTENDANT">Tiếp viên hàng không (FLIGHT_ATTENDANT)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Sân bay căn cứ <span class="text-danger">*</span></label>
                            <select name="base_airport_code" class="form-select" required>
                                @foreach($airports as $ap)
                                    <option value="{{ $ap->iata_code }}">{{ $ap->iata_code }} - {{ $ap->city }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Số bằng lái / Chứng chỉ <span class="text-danger">*</span></label>
                            <input type="text" name="license_number" class="form-control text-uppercase" placeholder="VD: ATPL-VN-8821" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tích lũy giờ bay</label>
                            <input type="number" step="0.1" name="total_flight_hours" class="form-control" value="0.0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Trạng thái ban đầu</label>
                        <select name="status" class="form-select">
                            <option value="AVAILABLE">Khả dụng (AVAILABLE)</option>
                            <option value="ON_DUTY">Đang làm nhiệm vụ (ON_DUTY)</option>
                            <option value="RESTING">Nghỉ ngơi (RESTING)</option>
                            <option value="LEAVE">Nghỉ phép (LEAVE)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu Hồ Sơ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Phân Công Tổ Bay Vào Chuyến Bay (UC-16, UC-17) -->
<div class="modal fade" id="assignCrewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST" id="assignCrewForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Phân Công Tổ Bay Cho Chuyến Bay (UC-16)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="bi bi-info-circle me-1"></i> Mỗi chuyến bay cần tối thiểu 1 Cơ trưởng (Captain) và 1 Cơ phó (First Officer) trước khi cất cánh.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Chọn Chuyến bay cần phân công <span class="text-danger">*</span></label>
                        <select name="flight_id_select" id="flightSelect" class="form-select" required onchange="updateAssignAction(this.value)">
                            <option value="">-- Chọn chuyến bay --</option>
                            @foreach($activeFlights as $f)
                                <option value="{{ $f->id }}">{{ $f->flight_number }} ({{ $f->route->origin_airport_code }}-{{ $f->route->dest_airport_code }}) lúc {{ \Carbon\Carbon::parse($f->scheduled_dep_utc)->format('H:i d/m') }} UTC</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Chọn Nhân sự tổ bay (Khả dụng) <span class="text-danger">*</span></label>
                        <select name="crew_id" class="form-select" required>
                            <option value="">-- Chọn nhân sự --</option>
                            @foreach($crews->where('status', 'AVAILABLE') as $cr)
                                <option value="{{ $cr->id }}">{{ $cr->crew_code }} - {{ $cr->full_name }} ({{ $cr->rank }} - Base {{ $cr->base_airport_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Vị trí phân công trong ca bay <span class="text-danger">*</span></label>
                        <select name="assigned_position" class="form-select" required>
                            <option value="CAPTAIN">Cơ trưởng (CAPTAIN)</option>
                            <option value="FIRST_OFFICER">Cơ phó (FIRST_OFFICER)</option>
                            <option value="PURSER">Tiếp viên trưởng (PURSER)</option>
                            <option value="FLIGHT_ATTENDANT">Tiếp viên (FLIGHT_ATTENDANT)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ghi chú phân công</label>
                        <input type="text" name="notes" class="form-control" placeholder="VD: Ca bay thường kỳ">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Xác nhận Phân công</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function updateAssignAction(flightId) {
        const form = document.getElementById('assignCrewForm');
        if (flightId) {
            form.action = `/admin/flights/${flightId}/crew/assign`;
        }
    }
</script>
@endpush
