@extends('layouts.admin')

@section('title', 'Bảng điều khiển Tổng quan')
@section('page_title', 'Bảng Điều Khiển Tổng Quan Khai Thác (Dashboard)')

@section('content')
<!-- Stats Cards Row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-2">
        <div class="card h-100 p-3 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Sân bay</div>
                    <div class="fs-4 fw-bold text-primary">{{ $stats['total_airports'] }}</div>
                </div>
                <i class="bi bi-geo-alt fs-2 text-primary opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card h-100 p-3 border-start border-4 border-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Đường bay</div>
                    <div class="fs-4 fw-bold text-info">{{ $stats['total_routes'] }}</div>
                </div>
                <i class="bi bi-signpost-split fs-2 text-info opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card h-100 p-3 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Đội tàu bay</div>
                    <div class="fs-4 fw-bold text-success">{{ $stats['total_aircrafts'] }}</div>
                </div>
                <i class="bi bi-airplane fs-2 text-success opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card h-100 p-3 border-start border-4 border-warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Chuyến bay hôm nay</div>
                    <div class="fs-4 fw-bold text-warning">{{ $stats['today_flights'] }}</div>
                </div>
                <i class="bi bi-calendar-event fs-2 text-warning opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card h-100 p-3 border-start border-4 border-secondary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Phi hành đoàn</div>
                    <div class="fs-4 fw-bold text-secondary">{{ $stats['total_crew'] }}</div>
                </div>
                <i class="bi bi-people fs-2 text-secondary opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card h-100 p-3 border-start border-4 border-danger">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Cảnh báo kỹ thuật</div>
                    <div class="fs-4 fw-bold text-danger">{{ $stats['maintenance_need'] }}</div>
                </div>
                <i class="bi bi-tools fs-2 text-danger opacity-50"></i>
            </div>
        </div>
    </div>
</div>

<!-- Main Dashboard Content Row -->
<div class="row g-4">
    <!-- Today's Flights Table -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fs-6"><i class="bi bi-airplane-engines me-2 text-primary"></i> Chuyến Bay Khai Thác Trong Ngày</span>
                <a href="{{ route('admin.flights') }}" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Chuyến bay</th>
                                <th>Hành trình</th>
                                <th>Tàu bay</th>
                                <th>Cất cánh (UTC)</th>
                                <th>Hạ cánh (UTC)</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($todayFlights as $flight)
                                <tr>
                                    <td>
                                        <span class="fw-bold text-primary">{{ $flight->flight_number }}</span>
                                        @if($flight->gate_number)
                                            <span class="badge bg-light text-dark border ms-1">Cửa {{ $flight->gate_number }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $flight->route->origin_airport_code }}</span>
                                        <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                        <span class="badge bg-secondary">{{ $flight->route->dest_airport_code }}</span>
                                    </td>
                                    <td><code>{{ $flight->aircraft_tail }}</code></td>
                                    <td>{{ \Carbon\Carbon::parse($flight->scheduled_dep_utc)->format('H:i d/m') }}</td>
                                    <td>{{ \Carbon\Carbon::parse($flight->scheduled_arr_utc)->format('H:i d/m') }}</td>
                                    <td>
                                        @switch($flight->flight_status)
                                            @case('SCHEDULED')
                                                <span class="badge bg-info text-dark badge-status">LÊN LỊCH</span>
                                                @break
                                            @case('BOARDING')
                                                <span class="badge bg-primary badge-status">ĐÓN KHÁCH</span>
                                                @break
                                            @case('DEPARTED')
                                                <span class="badge bg-success badge-status">ĐÃ CẤT CÁNH</span>
                                                @break
                                            @case('ARRIVED')
                                                <span class="badge bg-dark badge-status">ĐÃ HẠ CÁNH</span>
                                                @break
                                            @case('DELAYED')
                                                <span class="badge bg-warning text-dark badge-status">CHẬM CHUYẾN</span>
                                                @break
                                            @case('CANCELLED')
                                                <span class="badge bg-danger badge-status">HỦY CHUYẾN</span>
                                                @break
                                        @endswitch
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Chưa có chuyến bay nào được lên lịch cho ngày hôm nay.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Maintenance Alerts & Quick Info -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header text-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> Cảnh Báo Bảo Dưỡng Kỹ Thuật (UC-11)
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($alerts as $al)
                        <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <strong class="text-dark">{{ $al->tail_number }}</strong>
                                <small class="text-muted d-block">{{ $al->model->model_code }} ({{ $al->model->manufacturer }})</small>
                                <span class="badge bg-light text-danger border mt-1">
                                    {{ number_format($al->total_flight_hours, 1) }}h / {{ $al->total_flight_cycles }} chu kỳ
                                </span>
                            </div>
                            <span class="badge bg-danger">{{ $al->operational_status }}</span>
                        </li>
                    @empty
                        <li class="list-group-item py-4 text-center text-muted">
                            <i class="bi bi-shield-check text-success fs-3 d-block mb-1"></i>
                            Đội tàu bay đạt chuẩn an toàn kỹ thuật.
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle-fill me-2 text-primary"></i> Quy Tắc Nghiệp Vụ Module 1
            </div>
            <div class="card-body small">
                <ul class="mb-0 ps-3">
                    <li class="mb-1"><strong>BR-001:</strong> Mã IATA, số đăng ký tàu bay, mã nhân sự định danh duy nhất.</li>
                    <li class="mb-1"><strong>BR-005 / BR-006:</strong> Kiểm tra xung đột tàu bay & đảm bảo thời gian quay đầu (turnaround time) tối thiểu 45-60 phút.</li>
                    <li class="mb-1"><strong>UC-16:</strong> Phân công tổ bay yêu cầu tối thiểu 1 Cơ trưởng (Captain) và 1 Cơ phó (First Officer).</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
