@extends('layouts.admin')

@section('title', 'Bảng Điều Khiển Tổng Quan')
@section('page_title', 'Bảng Điều Khiển Tổng Quan')

@section('content')
@php
    $networkMetrics = [
        ['key' => 'total_airports', 'label' => 'Sân bay', 'icon' => 'geo-alt', 'tone' => 'primary', 'route' => 'admin.airports', 'permission' => 'airport.read'],
        ['key' => 'total_routes', 'label' => 'Đường bay', 'icon' => 'signpost-split', 'tone' => 'info', 'route' => 'admin.routes', 'permission' => 'route.read'],
        ['key' => 'total_aircrafts', 'label' => 'Đội máy bay', 'icon' => 'airplane', 'tone' => 'success', 'route' => 'admin.aircrafts', 'permission' => 'aircraft.read'],
        ['key' => 'today_flights', 'label' => 'Chuyến bay hôm nay', 'icon' => 'calendar2-week', 'tone' => 'warning', 'route' => 'admin.flights', 'permission' => 'flight.read', 'scope_types' => ['global', 'airport', 'flight']],
        ['key' => 'total_crew', 'label' => 'Phi hành đoàn', 'icon' => 'people', 'tone' => 'purple', 'route' => 'admin.crew', 'permission' => 'crew.read'],
        ['key' => 'maintenance_need', 'label' => 'Cảnh báo kỹ thuật', 'icon' => 'tools', 'tone' => 'danger', 'route' => 'admin.maintenance', 'permission' => 'maintenance.read'],
    ];
    $dashboardAccess = app(\App\Shared\Security\AccessControl::class);
    $dashboardNavigation = [];
    // These airport relations are already eager-loaded by the dashboard controller.
    $dashboardAirports = $todayFlights
        ->flatMap(fn ($flight) => [$flight->route?->originAirport, $flight->route?->destAirport])
        ->filter()->unique('iata_code')->sortBy('iata_code')->values();
@endphp
<div class="dashboard-page">
    <section aria-labelledby="network-overview-title">
        <div class="dashboard-section-heading">
            <div>
                <h2 id="network-overview-title">Tình trạng hoạt động toàn mạng lưới</h2>
            </div>
        </div>
        <div class="dashboard-stats">
            @foreach($networkMetrics as $metric)
            @php
                $allowedScopeTypes = $metric['scope_types'] ?? ['global', 'airport'];
                $canNavigate = $dashboardAccess->scopes(auth()->user(), $metric['permission'])
                    ->contains(fn ($scope) => in_array($scope->scope_type, $allowedScopeTypes, true));
                $dashboardNavigation[$metric['route']] = $canNavigate;
            @endphp
            @if($canNavigate)
            <a href="{{ route($metric['route']) }}" class="card dashboard-stat-card dashboard-stat-link dashboard-tone-{{ $metric['tone'] }}">
            @else
            <article class="card dashboard-stat-card dashboard-tone-{{ $metric['tone'] }}">
            @endif
                <span class="dashboard-stat-label">{{ $metric['label'] }}</span>
                <strong class="dashboard-stat-value">{{ number_format($stats[$metric['key']]) }}</strong>
                <span class="dashboard-stat-icon"><i class="bi bi-{{ $metric['icon'] }}" aria-hidden="true"></i></span>
            @if($canNavigate)
            </a>
            @else
            </article>
            @endif
            @endforeach
        </div>
    </section>

    <section aria-labelledby="airport-overview-title">
        <div class="dashboard-section-heading">
            <div>
                <h2 id="airport-overview-title">Tổng quan theo từng sân bay</h2>
            </div>
        </div>
        <div class="dashboard-airports">
            @forelse($dashboardAirports as $airport)
            @php
                $displayedAirportFlights = $todayFlights->filter(fn ($flight) => in_array(
                    $airport->iata_code,
                    [$flight->route?->origin_airport_code, $flight->route?->dest_airport_code],
                    true
                ))->count();
                $canViewAirport = Gate::allows('airport.read', [['airport' => strtoupper($airport->iata_code)]]);
            @endphp
            @if($canViewAirport)
            <a href="{{ route('admin.airports.show', ['iataCode' => $airport->iata_code]) }}" class="card dashboard-airport-card dashboard-content-link">
            @else
            <article class="card dashboard-airport-card">
            @endif
                <div class="dashboard-airport-heading">
                    <span class="dashboard-airport-code">{{ $airport->iata_code }}</span>
                    <span class="dashboard-status {{ match ($airport->status) { 'ACTIVE' => 'is-positive', 'INACTIVE' => 'is-attention', 'CLOSED' => 'is-danger', default => 'is-neutral' } }}">{{ ['ACTIVE' => 'Hoạt động', 'INACTIVE' => 'Tạm ngừng', 'CLOSED' => 'Đóng cửa'][$airport->status] ?? $airport->status }}</span>
                </div>
                <h3>{{ $airport->name }}</h3>
                <p class="dashboard-airport-location"><i class="bi bi-geo-alt" aria-hidden="true"></i>{{ $airport->city }}@if($airport->country), {{ $airport->country }}@endif</p>
                <dl class="dashboard-airport-metrics mb-0">
                    <div><dt>Chuyến trong bảng</dt><dd>{{ $displayedAirportFlights }}</dd></div>
                    <div><dt>Tài nguyên</dt><dd class="dashboard-unavailable">Chưa có dữ liệu</dd></div>
                </dl>
            @if($canViewAirport)
            </a>
            @else
            </article>
            @endif
            @empty
            @if($dashboardNavigation['admin.airports'])
            <a href="{{ route('admin.airports') }}" class="card dashboard-empty dashboard-airport-empty dashboard-content-link">
            @else
            <div class="card dashboard-empty dashboard-airport-empty">
            @endif
                <span class="dashboard-empty-icon"><i class="bi bi-pin-map" aria-hidden="true"></i></span>
                <p>Chưa có dữ liệu tổng quan theo sân bay</p>
            @if($dashboardNavigation['admin.airports'])
            </a>
            @else
            </div>
            @endif
            @endforelse
        </div>
    </section>

    <div class="dashboard-panels">
        <section class="card dashboard-panel dashboard-notices dashboard-tone-purple" aria-labelledby="internal-notices-title">
            <div class="card-header dashboard-panel-header">
                <i class="bi bi-bell" aria-hidden="true"></i><h2 id="internal-notices-title">Thông báo nội bộ</h2>
            </div>
            <div class="dashboard-empty dashboard-panel-empty">
                <span class="dashboard-empty-icon"><i class="bi bi-inbox" aria-hidden="true"></i></span>
                <p>Chưa có thông báo nội bộ</p>
            </div>
        </section>

        <section class="card dashboard-panel dashboard-alerts dashboard-tone-danger" aria-labelledby="featured-alerts-title">
            <div class="card-header dashboard-panel-header">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i><h2 id="featured-alerts-title">Cảnh báo nổi bật</h2>
            </div>
            <ul class="list-unstyled dashboard-alert-list mb-0">
                @forelse($alerts as $al)
                @php
                    $canViewAircraft = !empty($al->tail_number) && Gate::allows('aircraft.read',
                        $al->base_airport_code ? [['airport' => $al->base_airport_code]] : []);
                @endphp
                <li class="dashboard-alert-item">
                    @if($canViewAircraft)
                    <a href="{{ route('admin.aircrafts.show', ['tailNumber' => $al->tail_number]) }}" class="dashboard-content-link dashboard-alert-link">
                    @endif
                    <div class="dashboard-alert-heading">
                        <strong><i class="bi bi-airplane" aria-hidden="true"></i>{{ $al->tail_number }}</strong>
                        <span class="dashboard-status {{ match ($al->operational_status) { 'OPERATIONAL' => 'is-positive', 'MAINTENANCE' => 'is-attention', 'GROUNDED' => 'is-danger', default => 'is-neutral' } }}">{{ ['OPERATIONAL' => 'Khai thác', 'MAINTENANCE' => 'Bảo dưỡng', 'GROUNDED' => 'Ngừng khai thác'][$al->operational_status] ?? $al->operational_status }}</span>
                    </div>
                    <p class="dashboard-alert-model">{{ $al->model?->model_code ?? 'Chưa có dữ liệu' }}@if($al->model?->manufacturer) · {{ $al->model->manufacturer }}@endif</p>
                    <div class="dashboard-alert-usage">
                        <span><i class="bi bi-clock" aria-hidden="true"></i>{{ $al->total_flight_hours !== null ? number_format($al->total_flight_hours, 1).' giờ' : 'Chưa có dữ liệu' }}</span>
                        <span><i class="bi bi-arrow-repeat" aria-hidden="true"></i>{{ $al->total_flight_cycles !== null ? number_format($al->total_flight_cycles).' chu kỳ' : 'Chưa có dữ liệu' }}</span>
                    </div>
                    @if($canViewAircraft)
                    </a>
                    @endif
                </li>
                @empty
                <li class="dashboard-empty dashboard-panel-empty">
                    <span class="dashboard-empty-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
                    <p>Chưa có cảnh báo nổi bật</p>
                </li>
                @endforelse
            </ul>
        </section>

        <section class="card dashboard-panel dashboard-tasks dashboard-tone-warning" aria-labelledby="pending-tasks-title">
            @php
                $canViewWorkflows = Gate::allows('operations.read')
                    || Gate::allows('crew.confirm', [['self' => auth()->id()]]);
            @endphp
            @if($canViewWorkflows)
            <a href="{{ route('admin.workflows') }}" class="dashboard-content-link dashboard-panel-link" aria-labelledby="pending-tasks-title">
            @endif
            <div class="card-header dashboard-panel-header">
                <i class="bi bi-list-check" aria-hidden="true"></i><h2 id="pending-tasks-title">Công việc cần xử lý</h2>
            </div>
            <div class="dashboard-empty dashboard-panel-empty">
                <span class="dashboard-empty-icon"><i class="bi bi-clipboard-check" aria-hidden="true"></i></span>
                <p>Chưa có công việc cần xử lý</p>
            </div>
            @if($canViewWorkflows)
            </a>
            @endif
        </section>
    </div>

    <section class="card dashboard-operations" aria-labelledby="operations-board-title">
        <div class="card-header dashboard-section-heading">
            <div>
                <h2 id="operations-board-title">Bảng điều hành vận hành theo thời gian thực</h2>
            </div>
            @if($dashboardNavigation['admin.flights'])
            <a href="{{ route('admin.flights') }}" class="btn btn-sm btn-outline-primary dashboard-view-all">Xem tất cả<i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 dashboard-operations-table">
                <caption class="visually-hidden">Danh sách tối đa 10 chuyến bay trong ngày UTC theo giờ khởi hành.</caption>
                <thead>
                    <tr>
                        <th scope="col">Chuyến bay</th>
                        <th scope="col">Hành trình</th>
                        <th scope="col">Máy bay</th>
                        <th scope="col">Cất cánh (UTC)</th>
                        <th scope="col">Hạ cánh (UTC)</th>
                        <th scope="col">Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($todayFlights as $flight)
                    @php
                        $canViewFlight = !empty($flight->id) && $flight->route && Gate::allows('flight.read', [[
                            'airport' => $flight->route->origin_airport_code,
                            'flight' => $flight->id,
                        ]]);
                    @endphp
                    <tr class="{{ $canViewFlight ? 'dashboard-flight-row-link' : '' }}">
                        <td>
                            @if($canViewFlight)
                            <a href="{{ route('admin.flights.show', ['id' => $flight->id]) }}" class="dashboard-content-link dashboard-flight-link stretched-link">
                            @endif
                            <strong class="dashboard-flight-number">{{ $flight->flight_number }}</strong>
                            @if($canViewFlight)
                            </a>
                            @endif
                            @if($flight->gate_number)
                            <small class="dashboard-flight-gate">Cửa {{ $flight->gate_number }}</small>
                            @endif
                        </td>
                        <td>
                            <div class="dashboard-flight-route">
                                <span>{{ $flight->route?->origin_airport_code ?? '—' }}</span>
                                <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                <span>{{ $flight->route?->dest_airport_code ?? '—' }}</span>
                            </div>
                        </td>
                        <td><code>{{ $flight->aircraft_tail }}</code></td>
                        <td>{{ $flight->scheduled_dep_utc ? \Carbon\Carbon::parse($flight->scheduled_dep_utc)->format('H:i d/m') : 'Chưa có dữ liệu' }}</td>
                        <td>{{ $flight->scheduled_arr_utc ? \Carbon\Carbon::parse($flight->scheduled_arr_utc)->format('H:i d/m') : 'Chưa có dữ liệu' }}</td>
                        <td>
                            @switch($flight->flight_status)
                                @case('SCHEDULED')
                                    <span class="dashboard-status is-info">Lên lịch</span>
                                    @break
                                @case('BOARDING')
                                    <span class="dashboard-status is-boarding">Đón khách</span>
                                    @break
                                @case('DEPARTED')
                                    <span class="dashboard-status is-departed">Đã cất cánh</span>
                                    @break
                                @case('ARRIVED')
                                    <span class="dashboard-status is-positive">Đã hạ cánh</span>
                                    @break
                                @case('DELAYED')
                                    <span class="dashboard-status is-attention">Chậm chuyến</span>
                                    @break
                                @case('CANCELLED')
                                    <span class="dashboard-status is-danger">Hủy chuyến</span>
                                    @break
                                @default
                                    <span class="dashboard-status is-neutral">{{ $flight->flight_status }}</span>
                            @endswitch
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6">
                            <div class="dashboard-empty dashboard-flight-empty">
                                <span class="dashboard-empty-icon"><i class="bi bi-airplane-engines" aria-hidden="true"></i></span>
                                <p>Chưa có chuyến bay nào được lên lịch cho ngày hôm nay</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="dashboard-operations-footer">
            <span>Đang hiển thị {{ $todayFlights->count() }} / {{ number_format($stats['today_flights']) }} chuyến trong ngày UTC</span>
        </div>
    </section>
</div>
@endsection
