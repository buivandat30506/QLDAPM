@extends('layouts.admin')

@section('title', 'Quản lý Sân bay & Tài nguyên')
@section('page_title', 'Quản Lý Sân Bay & Tài Nguyên (UC-01, UC-02)')

@section('content')
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-geo-alt-fill me-2 text-primary"></i> Danh Sách Sân Bay</span>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createAirportModal">
            <i class="bi bi-plus-lg me-1"></i> Thêm Sân Bay Mới
        </button>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form method="GET" action="{{ route('admin.airports') }}" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Tìm kiếm theo mã IATA, tên, thành phố..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- Tất cả trạng thái --</option>
                    <option value="ACTIVE" {{ request('status') === 'ACTIVE' ? 'selected' : '' }}>Hoạt động (ACTIVE)</option>
                    <option value="INACTIVE" {{ request('status') === 'INACTIVE' ? 'selected' : '' }}>Tạm dừng (INACTIVE)</option>
                    <option value="CLOSED" {{ request('status') === 'CLOSED' ? 'selected' : '' }}>Đóng cửa (CLOSED)</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-secondary w-100"><i class="bi bi-search me-1"></i> Tìm kiếm</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>IATA / ICAO</th>
                        <th>Tên cảng hàng không</th>
                        <th>Thành phố / Quốc gia</th>
                        <th>Múi giờ</th>
                        <th>Giờ hoạt động</th>
                        <th>Tài nguyên</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($airports as $airport)
                        <tr>
                            <td>
                                <span class="badge bg-primary fs-6">{{ $airport->iata_code }}</span>
                                <small class="text-muted d-block">{{ $airport->icao_code ?? '---' }}</small>
                            </td>
                            <td><strong class="text-dark">{{ $airport->name }}</strong></td>
                            <td>{{ $airport->city }}, {{ $airport->country }}</td>
                            <td><code>{{ $airport->timezone }}</code></td>
                            <td>{{ $airport->operating_hours }}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#resourcesModal{{ $airport->iata_code }}">
                                    <i class="bi bi-diagram-3 me-1"></i> {{ $airport->resources_count ?? count($airport->resources) }} tài nguyên
                                </button>
                            </td>
                            <td>
                                @if($airport->status === 'ACTIVE')
                                    <span class="badge bg-success">HOẠT ĐỘNG</span>
                                @else
                                    <span class="badge bg-secondary">{{ $airport->status }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editModal{{ $airport->iata_code }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form action="{{ route('admin.airports.destroy', $airport->iata_code) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa sân bay {{ $airport->iata_code }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>

                        <!-- Modal Quản lý Tài nguyên (UC-02) -->
                        <div class="modal fade" id="resourcesModal{{ $airport->iata_code }}" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Tài nguyên Sân bay {{ $airport->iata_code }} - {{ $airport->name }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <!-- Form Thêm Tài nguyên -->
                                        <form action="{{ route('admin.airports.resources.store', $airport->iata_code) }}" method="POST" class="row g-2 mb-4 p-3 bg-light rounded border">
                                            @csrf
                                            <div class="col-md-4">
                                                <label class="form-label small fw-bold">Loại tài nguyên</label>
                                                <select name="resource_type" class="form-select form-select-sm" required>
                                                    <option value="TERMINAL">Nhà ga (TERMINAL)</option>
                                                    <option value="GATE">Cửa khởi hành (GATE)</option>
                                                    <option value="RUNWAY">Đường băng (RUNWAY)</option>
                                                    <option value="BAGGAGE_CAROUSEL">Băng chuyền hành lý (CAROUSEL)</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small fw-bold">Mã / Tên tài nguyên</label>
                                                <input type="text" name="resource_code" class="form-control form-control-sm" placeholder="VD: T1, Gate 05, Runway 25L" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small fw-bold">Trạng thái</label>
                                                <div class="input-group input-group-sm">
                                                    <select name="status" class="form-select">
                                                        <option value="AVAILABLE">Khả dụng (AVAILABLE)</option>
                                                        <option value="MAINTENANCE">Bảo trì (MAINTENANCE)</option>
                                                        <option value="OCCUPIED">Đang bận (OCCUPIED)</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Thêm</button>
                                                </div>
                                            </div>
                                        </form>

                                        <!-- Danh sách tài nguyên hiện có -->
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered align-middle">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Loại</th>
                                                        <th>Mã định danh</th>
                                                        <th>Trạng thái</th>
                                                        <th class="text-center" style="width: 80px;">Xóa</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($airport->resources as $res)
                                                        <tr>
                                                            <td><span class="badge bg-secondary">{{ $res->resource_type }}</span></td>
                                                            <td><strong>{{ $res->resource_code }}</strong></td>
                                                            <td>
                                                                @if($res->status === 'AVAILABLE')
                                                                    <span class="badge bg-success">KHẢ DỤNG</span>
                                                                @elseif($res->status === 'OCCUPIED')
                                                                    <span class="badge bg-warning text-dark">ĐANG BẬN</span>
                                                                @else
                                                                    <span class="badge bg-danger">BẢO TRÌ</span>
                                                                @endif
                                                            </td>
                                                            <td class="text-center">
                                                                <form action="{{ route('admin.airports.resources.destroy', $res->id) }}" method="POST" onsubmit="return confirm('Xóa tài nguyên này?')">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-2"><i class="bi bi-x"></i></button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="4" class="text-center text-muted py-3">Chưa có tài nguyên nào được cấu hình cho sân bay này.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Chỉnh sửa Sân bay -->
                        <div class="modal fade" id="editModal{{ $airport->iata_code }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form action="{{ route('admin.airports.update', $airport->iata_code) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header">
                                            <h5 class="modal-title">Cập nhật Sân bay {{ $airport->iata_code }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">Tên cảng hàng không</label>
                                                <input type="text" name="name" class="form-control" value="{{ $airport->name }}" required>
                                            </div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Thành phố</label>
                                                    <input type="text" name="city" class="form-control" value="{{ $airport->city }}" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Quốc gia</label>
                                                    <input type="text" name="country" class="form-control" value="{{ $airport->country }}">
                                                </div>
                                            </div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Mã ICAO</label>
                                                    <input type="text" name="icao_code" class="form-control" value="{{ $airport->icao_code }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Múi giờ</label>
                                                    <input type="text" name="timezone" class="form-control" value="{{ $airport->timezone }}">
                                                </div>
                                            </div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Giờ hoạt động</label>
                                                    <input type="text" name="operating_hours" class="form-control" value="{{ $airport->operating_hours }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Trạng thái</label>
                                                    <select name="status" class="form-select">
                                                        <option value="ACTIVE" {{ $airport->status === 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
                                                        <option value="INACTIVE" {{ $airport->status === 'INACTIVE' ? 'selected' : '' }}>INACTIVE</option>
                                                        <option value="CLOSED" {{ $airport->status === 'CLOSED' ? 'selected' : '' }}>CLOSED</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                            <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">Không tìm thấy sân bay nào phù hợp.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Thêm Sân Bay Mới -->
<div class="modal fade" id="createAirportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.airports.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Thêm Mới Sân Bay (UC-01)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Mã IATA (3 ký tự) <span class="text-danger">*</span></label>
                            <input type="text" name="iata_code" class="form-control text-uppercase" placeholder="VD: HAN" maxlength="3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mã ICAO (4 ký tự)</label>
                            <input type="text" name="icao_code" class="form-control text-uppercase" placeholder="VD: VVNB" maxlength="4">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tên sân bay <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="VD: Cảng hàng không quốc tế Nội Bài" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Thành phố <span class="text-danger">*</span></label>
                            <input type="text" name="city" class="form-control" placeholder="VD: Hà Nội" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Quốc gia</label>
                            <input type="text" name="country" class="form-control" value="Vietnam">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Múi giờ</label>
                            <input type="text" name="timezone" class="form-control" value="Asia/Ho_Chi_Minh">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Giờ hoạt động</label>
                            <input type="text" name="operating_hours" class="form-control" value="24/7">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="ACTIVE">Hoạt động (ACTIVE)</option>
                            <option value="INACTIVE">Tạm dừng (INACTIVE)</option>
                            <option value="CLOSED">Đóng cửa (CLOSED)</option>
                        </select>
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
@endsection
