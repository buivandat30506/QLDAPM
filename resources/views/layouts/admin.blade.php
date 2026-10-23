<!DOCTYPE html>
<html lang="vi" data-sidebar-state="closed">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Quản lý Hàng không') - Smart Airline Operations</title>
    <script>
        try {
            document.documentElement.dataset.sidebarState = localStorage.getItem('smartAirline.sidebar') === 'open' ? 'open' : 'closed';
        } catch {
            document.documentElement.dataset.sidebarState = 'closed';
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="admin-layout">
@php
    $sidebarAccess = app(\App\Shared\Security\AccessControl::class);
    $sidebarVisible = [];
    foreach (['maintenance.read', 'airport.read', 'route.read', 'schedule.read', 'aircraft.read'] as $permission) {
        $sidebarVisible[$permission] = $sidebarAccess->scopes(auth()->user(), $permission)
            ->contains(fn ($scope) => in_array($scope->scope_type, ['global', 'airport']));
    }
    $sidebarCrewScopes = $sidebarAccess->scopes(auth()->user(), 'crew.read');
    $sidebarVisible['flight.read'] = $sidebarAccess->scopes(auth()->user(), 'flight.read')
        ->contains(fn ($scope) => in_array($scope->scope_type, ['global', 'airport', 'flight']));
    $sidebarVisible['crew.read'] = $sidebarCrewScopes->contains(fn ($scope) => in_array($scope->scope_type, ['global', 'airport']));
    $sidebarVisible['crew.plans'] = $sidebarCrewScopes->contains(fn ($scope) => in_array($scope->scope_type, ['global', 'airport', 'flight']));
    $sidebarVisible['assistance.read'] = $sidebarAccess->scopes(auth()->user(), 'assistance.read')->isNotEmpty();
@endphp
        <aside class="sidebar" id="sidebarMenu" aria-label="Điều hướng quản trị" aria-hidden="true" inert>
            <div class="brand">
                <i class="bi bi-airplane-engines-fill text-warning fs-4"></i>
                <div>
                    <div>SMART AIRLINE</div>
                    <small class="brand-subtitle text-white-50">Quản lý hàng không</small>
                </div>
            </div>
            <nav class="py-3" aria-label="Các chức năng quản trị">
                <ul class="nav flex-column sidebar-menu">
                    @can('operations.read')
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                            <i class="bi bi-grid-1x2-fill" aria-hidden="true"></i><span>Trang chủ và bảng điều hành</span>
                        </a>
                    </li>
                    @endcan
                    @if($sidebarVisible['airport.read'])
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.airports*') ? 'active' : '' }}" href="{{ route('admin.airports') }}">
                            <i class="bi bi-geo-alt-fill" aria-hidden="true"></i><span>Quản lý sân bay và tài nguyên</span>
                        </a>
                    </li>
                    @endif
                    @if($sidebarVisible['flight.read'] || $sidebarVisible['route.read'] || $sidebarVisible['schedule.read'])
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.flights*', 'admin.events*', 'admin.routes*', 'admin.schedules*') ? 'active' : '' }}" href="{{ route($sidebarVisible['schedule.read'] ? 'admin.schedules' : ($sidebarVisible['flight.read'] ? 'admin.flights' : 'admin.routes')) }}">
                            <i class="bi bi-calendar-check" aria-hidden="true"></i><span>Quản lý chuyến bay</span>
                        </a>
                    </li>
                    @endif
                    @if(Gate::allows('operations.read') || Gate::allows('crew.confirm', [['self'=>auth()->id()]]))
                    <li class="nav-item sidebar-group">
                        <button class="nav-link sidebar-group-toggle {{ request()->routeIs('admin.workflows*') ? 'active' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarOperations" aria-controls="sidebarOperations" aria-expanded="{{ request()->routeIs('admin.workflows*') ? 'true' : 'false' }}">
                            <i class="bi bi-airplane-engines" aria-hidden="true"></i><span>Điều hành chuyến bay</span><i class="bi bi-chevron-down sidebar-chevron" aria-hidden="true"></i>
                        </button>
                        <ul class="nav flex-column sidebar-submenu collapse {{ request()->routeIs('admin.workflows*') ? 'show' : '' }}" id="sidebarOperations">
                            @if(Gate::allows('operations.read') || Gate::allows('crew.confirm', [['self'=>auth()->id()]]))
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.workflows*') ? 'active' : '' }}" href="{{ route('admin.workflows') }}"><i class="bi bi-diagram-3" aria-hidden="true"></i><span>Luồng nghiệp vụ</span></a></li>
                            @endif
                        </ul>
                    </li>
                    @endif
                    @if($sidebarVisible['aircraft.read'] || $sidebarVisible['maintenance.read'])
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.aircrafts*', 'admin.aircraft_models*', 'admin.maintenance*') ? 'active' : '' }}" href="{{ route($sidebarVisible['aircraft.read'] ? 'admin.aircrafts' : 'admin.maintenance') }}">
                            <i class="bi bi-airplane-fill" aria-hidden="true"></i><span>Quản lý máy bay</span>
                        </a>
                    </li>
                    @endif
                    @if($sidebarVisible['crew.read'] || $sidebarVisible['crew.plans'])
                    <li class="nav-item sidebar-group">
                        <button class="nav-link sidebar-group-toggle {{ request()->routeIs('admin.crew*') ? 'active' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCrew" aria-controls="sidebarCrew" aria-expanded="{{ request()->routeIs('admin.crew*') ? 'true' : 'false' }}">
                            <i class="bi bi-people-fill" aria-hidden="true"></i><span>Nhân sự phi hành đoàn</span><i class="bi bi-chevron-down sidebar-chevron" aria-hidden="true"></i>
                        </button>
                        <ul class="nav flex-column sidebar-submenu collapse {{ request()->routeIs('admin.crew*') ? 'show' : '' }}" id="sidebarCrew">
                            @if($sidebarVisible['crew.read'])
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.crew', 'admin.crew.show') ? 'active' : '' }}" href="{{ route('admin.crew') }}"><i class="bi bi-person-badge" aria-hidden="true"></i><span>Hồ sơ phi hành đoàn</span></a></li>
                            @endif
                            @if($sidebarVisible['crew.plans'])
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.crew.plans', 'admin.crew.plan') ? 'active' : '' }}" href="{{ route('admin.crew.plans') }}"><i class="bi bi-person-lines-fill" aria-hidden="true"></i><span>Phân công phi hành đoàn</span></a></li>
                            @endif
                        </ul>
                    </li>
                    @endif
                    @if(Gate::allows('passenger.read', [['self'=>auth()->id()]]) || $sidebarVisible['assistance.read'])
                    <li class="nav-item sidebar-group">
                        <button class="nav-link sidebar-group-toggle {{ request()->routeIs('passenger.*') ? 'active' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarPassengers" aria-controls="sidebarPassengers" aria-expanded="{{ request()->routeIs('passenger.*') ? 'true' : 'false' }}">
                            <i class="bi bi-person-vcard" aria-hidden="true"></i><span>Hành khách</span><i class="bi bi-chevron-down sidebar-chevron" aria-hidden="true"></i>
                        </button>
                        <ul class="nav flex-column sidebar-submenu collapse {{ request()->routeIs('passenger.*') ? 'show' : '' }}" id="sidebarPassengers">
                            @can('passenger.read', [['self'=>auth()->id()]])
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('passenger.profiles*') ? 'active' : '' }}" href="{{ route('passenger.profiles') }}"><i class="bi bi-person-vcard" aria-hidden="true"></i><span>Hồ sơ hành khách</span></a></li>
                            @endcan
                            @if($sidebarVisible['assistance.read'])
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('passenger.assistance*') ? 'active' : '' }}" href="{{ route('passenger.assistance') }}"><i class="bi bi-person-raised-hand" aria-hidden="true"></i><span>Yêu cầu hỗ trợ</span></a></li>
                            @endif
                        </ul>
                    </li>
                    @endif
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('account*') ? 'active' : '' }}" href="{{ route('account') }}"><i class="bi bi-person-circle" aria-hidden="true"></i><span>Quản lý tài khoản và hồ sơ cá nhân</span></a>
                    </li>
                    @if(Gate::allows('users.manage') || Gate::allows('audit.read'))
                    <li class="nav-item sidebar-group">
                        <button class="nav-link sidebar-group-toggle {{ request()->routeIs('administration.*') ? 'active' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarAdministration" aria-controls="sidebarAdministration" aria-expanded="{{ request()->routeIs('administration.*') ? 'true' : 'false' }}">
                            <i class="bi bi-shield-lock" aria-hidden="true"></i><span>Quản trị hệ thống</span><i class="bi bi-chevron-down sidebar-chevron" aria-hidden="true"></i>
                        </button>
                        <ul class="nav flex-column sidebar-submenu collapse {{ request()->routeIs('administration.*') ? 'show' : '' }}" id="sidebarAdministration">
                            @can('users.manage')
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('administration.users*') ? 'active' : '' }}" href="{{ route('administration.users') }}"><i class="bi bi-person-gear" aria-hidden="true"></i><span>Tài khoản và phân quyền</span></a></li>
                            @endcan
                            @can('audit.read')
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('administration.audit*') ? 'active' : '' }}" href="{{ route('administration.audit') }}"><i class="bi bi-journal-text" aria-hidden="true"></i><span>Nhật ký hệ thống</span></a></li>
                            @endcan
                        </ul>
                    </li>
                    @endif
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="admin-main">
            <!-- Top Navbar -->
            <nav class="top-navbar" aria-label="Thanh công cụ quản trị" data-admin-topbar>
                <div class="topbar-leading">
                    <button class="sidebar-toggle" type="button" aria-label="Mở hoặc đóng menu điều hướng" aria-controls="sidebarMenu" aria-expanded="false" data-sidebar-toggle>
                        <i class="bi bi-list" aria-hidden="true"></i>
                    </button>
                    <h5 class="mb-0 fw-bold">@yield('page_title', 'Hệ thống Quản lý Khai thác Bay')</h5>
                </div>
                <div class="topbar-actions">
                    <span class="badge bg-light text-dark border px-3 py-2">
                        <i class="bi bi-clock me-1 text-primary" title="Giờ phối hợp quốc tế (UTC)"></i> <span id="utcTime"></span> <abbr title="Giờ phối hợp quốc tế">UTC</abbr>
                    </span>
                    <div class="topbar-user-actions">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('account') }}"><i class="bi bi-person-circle me-1"></i>{{ auth()->user()?->full_name }}</a>
                        <form class="topbar-logout" method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Đăng xuất</button></form>
                    </div>
                </div>
            </nav>

            <!-- Alerts Container -->
            <main class="content-wrapper workspace-page" data-sidebar-dismiss>
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> {{ str_replace(['Tàu bay', 'tàu bay'], ['Máy bay', 'máy bay'], session('success')) }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng thông báo"></button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                        <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i> Đã xảy ra lỗi kiểm tra dữ liệu:</h6>
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $err)
                                <li>{{ str_replace(['Tàu bay', 'tàu bay'], ['Máy bay', 'máy bay'], $err) }}</li>
                            @endforeach
                        </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng thông báo"></button>
                    </div>
                @endif

                <!-- Body Content -->
                @yield('content')
            </main>
        </div>

<!-- Bootstrap 5.3 JS Bundle -->
<script>
    // Realtime UTC Clock
    function updateUtcTime() {
        const now = new Date();
        const str = now.toISOString().replace('T', ' ').substring(0, 19);
        const el = document.getElementById('utcTime');
        if (el) el.innerText = str;
    }
    setInterval(updateUtcTime, 1000);
    updateUtcTime();
</script>
@stack('scripts')
</body>
</html>
