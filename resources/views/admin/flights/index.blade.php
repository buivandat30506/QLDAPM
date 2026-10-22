@extends('layouts.admin')

@section('title', 'Quản lý Chuyến bay & Lịch bay')
@section('page_title', 'Quản Lý Lịch Bay & Chuyến Bay Khai Thác (UC-05, UC-06, UC-07, UC-08)')

@section('content')
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-calendar2-week-fill me-2 text-warning"></i> Chuyến Bay Khai Thác Thực Tế</span>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#createScheduleModal">
                <i class="bi bi-calendar-plus me-1"></i> Tạo Lịch Bay Mẫu
            </button>
            <button type="button" class="btn btn-warning text-dark btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#createFlightModal">
                <i class="bi bi-plus-lg me-1"></i> Khởi Tạo Chuyến Bay
            </button>
        </div>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form method="GET" action="{{ route('admin.flights') }}" class="row g-2 mb-3">
            <div class="col-md-3">
                <input type="date" name="date" class="form-control form-control-sm" value="{{ request('date') }}">
            </div>
            <div class="col-md-3">
                <select name="flight_status" class="form-select form-select-sm">
                    <option value="">-- Tất cả trạng thái --</option>
                    <option value="SCHEDULED" {{ request('flight_status') === 'SCHEDULED' ? 'selected' : '' }}>Lên lịch (SCHEDULED)</option>
                    <option value="BOARDING" {{ request('flight_status') === 'BOARDING' ? 'selected' : '' }}>Đón khách (BOARDING)</option>
                    <option value="DEPARTED" {{ request('flight_status') === 'DEPARTED' ? 'selected' : '' }}>Đã cất cánh (DEPARTED)</option>
                    <option value="ARRIVED" {{ request('flight_status') === 'ARRIVED' ? 'selected' : '' }}>Đã hạ cánh (ARRIVED)</option>
                    <option value="DELAYED" {{ request('flight_status') === 'DELAYED' ? 'selected' : '' }}>Chậm chuyến (DELAYED)</option>
                    <option value="CANCELLED" {{ request('flight_status') === 'CANCELLED' ? 'selected' : '' }}>Hủy chuyến (CANCELLED)</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="aircraft_tail" class="form-select form-select-sm">
                    <option value="">-- Lọc theo máy bay --</option>
                    @foreach($aircrafts as $ac)
                        <option value="{{ $ac->tail_number }}" {{ request('aircraft_tail') === $ac->tail_number ? 'selected' : '' }}>{{ $ac->tail_number }}</option>
                    @endforeach
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
                        <th>Số hiệu & Ngày bay</th>
                        <th>Hành trình</th>
                        <th>Máy bay</th>
                        <th>Giờ cất cánh (UTC)</th>
                        <th>Giờ hạ cánh (UTC)</th>
                        <th>Cửa / Chậm</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Cập nhật</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($flights as $flight)
                        <tr>
                            <td>
                                <strong class="text-primary fs-6">{{ $flight->flight_number }}</strong>
                                <small class="text-muted d-block">{{ $flight->flight_date }}</small>
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ $flight->route->origin_airport_code }}</span>
                                <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                <span class="badge bg-secondary">{{ $flight->route->dest_airport_code }}</span>
                            </td>
                            <td>
                                <code>{{ $flight->aircraft_tail }}</code>
                                <small class="text-muted d-block">{{ $flight->aircraft->model->model_code ?? '' }}</small>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ \Carbon\Carbon::parse($flight->scheduled_dep_utc)->format('H:i d/m/Y') }}</div>
                                @if($flight->actual_dep_utc)
                                    <small class="text-success d-block">Thực tế: {{ \Carbon\Carbon::parse($flight->actual_dep_utc)->format('H:i') }}</small>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold">{{ \Carbon\Carbon::parse($flight->scheduled_arr_utc)->format('H:i d/m/Y') }}</div>
                                @if($flight->actual_arr_utc)
                                    <small class="text-success d-block">Thực tế: {{ \Carbon\Carbon::parse($flight->actual_arr_utc)->format('H:i') }}</small>
                                @endif
                            </td>
                            <td>
                                @if($flight->gate_number)
                                    <span class="badge bg-light text-dark border">Cửa {{ $flight->gate_number }}</span>
                                @else
                                    <span class="text-muted small">---</span>
                                @endif
                                @if($flight->delay_reason)
                                    <small class="text-danger d-block" title="{{ $flight->delay_reason }}">
                                        <i class="bi bi-exclamation-circle"></i> {{ Str::limit($flight->delay_reason, 20) }}
                                    </small>
                                @endif
                            </td>
                            <td>
                                @switch($flight->flight_status)
                                    @case('SCHEDULED')
                                        <span class="badge bg-info text-dark">LÊN LỊCH</span>
                                        @break
                                    @case('BOARDING')
                                        <span class="badge bg-primary">ĐÓN KHÁCH</span>
                                        @break
                                    @case('DEPARTED')
                                        <span class="badge bg-success">ĐÃ CẤT CÁNH</span>
                                        @break
                                    @case('ARRIVED')
                                        <span class="badge bg-dark">ĐÃ HẠ CÁNH</span>
                                        @break
                                    @case('DELAYED')
                                        <span class="badge bg-warning text-dark">CHẬM CHUYẾN</span>
                                        @break
                                    @case('CANCELLED')
                                        <span class="badge bg-danger">HỦY CHUYẾN</span>
                                        @break
                                @endswitch
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#statusModal{{ $flight->id }}">
                                    <i class="bi bi-sliders me-1"></i> Trạng thái
                                </button>
                            </td>
                        </tr>

                        <!-- Modal Cập nhật Trạng thái Chuyến Bay -->
                        <div class="modal fade" id="statusModal{{ $flight->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form action="{{ route('admin.flights.status.update', $flight->id) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header">
                                            <h5 class="modal-title">Cập nhật Chuyến Bay {{ $flight->flight_number }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Trạng thái vận hành</label>
                                                <select name="flight_status" class="form-select" required>
                                                    <option value="SCHEDULED" {{ $flight->flight_status === 'SCHEDULED' ? 'selected' : '' }}>Lên lịch (SCHEDULED)</option>
                                                    <option value="BOARDING" {{ $flight->flight_status === 'BOARDING' ? 'selected' : '' }}>Đón khách (BOARDING)</option>
                                                    <option value="DEPARTED" {{ $flight->flight_status === 'DEPARTED' ? 'selected' : '' }}>Đã cất cánh (DEPARTED)</option>
                                                    <option value="ARRIVED" {{ $flight->flight_status === 'ARRIVED' ? 'selected' : '' }}>Đã hạ cánh (ARRIVED)</option>
                                                    <option value="DELAYED" {{ $flight->flight_status === 'DELAYED' ? 'selected' : '' }}>Chậm chuyến (DELAYED)</option>
                                                    <option value="CANCELLED" {{ $flight->flight_status === 'CANCELLED' ? 'selected' : '' }}>Hủy chuyến (CANCELLED)</option>
                                                </select>
                                            </div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Thực tế cất cánh (UTC)</label>
                                                    <input type="datetime-local" name="actual_dep_utc" class="form-control" value="{{ $flight->actual_dep_utc ? \Carbon\Carbon::parse($flight->actual_dep_utc)->format('Y-m-d\TH:i') : '' }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Thực tế hạ cánh (UTC)</label>
                                                    <input type="datetime-local" name="actual_arr_utc" class="form-control" value="{{ $flight->actual_arr_utc ? \Carbon\Carbon::parse($flight->actual_arr_utc)->format('Y-m-d\TH:i') : '' }}">
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Cửa khởi hành (Gate)</label>
                                                <input type="text" name="gate_number" class="form-control" placeholder="VD: 12A, Gate 04" value="{{ $flight->gate_number }}">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Lý do chậm / Hủy chuyến (nếu có)</label>
                                                <textarea name="delay_reason" class="form-control" rows="2" placeholder="VD: Thời tiết xấu, bảo dưỡng kỹ thuật phát sinh...">{{ $flight->delay_reason }}</textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                            <button type="submit" class="btn btn-primary">Lưu trạng thái</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">Không có chuyến bay nào phù hợp điều kiện lọc.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Khởi Tạo Chuyến Bay (Kèm Kiểm Tra Xung Đột Máy Bay) -->
<div class="modal fade" id="createFlightModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('admin.flights.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Khởi Tạo Chuyến Bay Khai Thác (UC-07)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Lịch bay đã công bố</label>
                    <select name="schedule_id" class="form-select" required>
                        @foreach($schedules->where('status', 'PUBLISHED') as $schedule)
                            <option value="{{ $schedule->id }}">{{ $schedule->flight_number }} / {{ $schedule->aircraft_tail }} / {{ $schedule->effective_from }} - {{ $schedule->effective_to }}</option>
                        @endforeach
                    </select>
                    <label class="form-label mt-3">Ngày khai thác (UTC)</label>
                    <input type="date" name="flight_date" class="form-control" value="{{ now('UTC')->toDateString() }}" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold">Khởi tạo Chuyến bay</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Tạo Lịch Bay Mẫu (UC-05) -->
<div class="modal fade" id="createScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.schedules.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tạo Lịch Bay Mẫu Template (UC-05)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Số hiệu chuyến bay <span class="text-danger">*</span></label>
                        <input type="text" name="flight_number" class="form-control text-uppercase" placeholder="VD: VN123" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tuyến bay <span class="text-danger">*</span></label>
                            <select name="route_id" class="form-select" required>
                                <option value="">-- Chọn tuyến --</option>
                                @foreach($routes as $r)
                                    <option value="{{ $r->id }}">{{ $r->route_code ?? ($r->origin_airport_code . '-' . $r->dest_airport_code) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Dòng máy bay <span class="text-danger">*</span></label>
                            <select name="model_id" class="form-select" required>
                                <option value="">-- Chọn model --</option>
                                @foreach($models as $m)
                                    <option value="{{ $m->id }}">{{ $m->model_code }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Giờ cất cánh (UTC) <span class="text-danger">*</span></label>
                            <input type="time" name="departure_time_utc" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Giờ hạ cánh (UTC) <span class="text-danger">*</span></label>
                            <input type="time" name="arrival_time_utc" class="form-control" required>
                        </div>
                    </div>
                    <input type="hidden" name="status" value="DRAFT">
                    <label class="form-label">Máy bay</label><select name="aircraft_tail" class="form-select"><option value="">Chưa phân công</option>@foreach($aircrafts as $aircraft)<option value="{{ $aircraft->tail_number }}">{{ $aircraft->tail_number }}</option>@endforeach</select>
                    <label class="form-label">Cửa khởi hành</label><select name="resource_id" class="form-select"><option value="">Chưa phân công</option>@foreach(\App\Models\AirportResource::where('resource_type','GATE')->get() as $gate)<option value="{{ $gate->id }}">{{ $gate->airport_code }} / {{ $gate->resource_code }}</option>@endforeach</select>
                    <label class="form-label">Hiệu lực từ (UTC)</label><input type="date" name="effective_from" class="form-control" required>
                    <label class="form-label">Hiệu lực đến (UTC)</label><input type="date" name="effective_to" class="form-control" required>
                    <label class="form-label">Ngày ISO (1=thứ Hai, 7=Chủ nhật)</label><input name="days_of_week" class="form-control" value="1,2,3,4,5,6,7" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu Lịch bay</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
