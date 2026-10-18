<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Quản lý Hàng không') - Smart Airline Operations</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --primary-navy: #0B2545;
            --accent-blue: #134074;
            --sky-blue: #0077b6;
            --gold-accent: #f59e0b;
            --bg-light: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -2px rgba(0,0,0,0.05);
        }
        body {
            background-color: var(--bg-light);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: #334155;
        }
        .sidebar {
            background: linear-gradient(180deg, var(--primary-navy) 0%, var(--accent-blue) 100%);
            min-height: 100vh;
            color: #fff;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar .brand {
            padding: 1.5rem 1.25rem;
            font-weight: 700;
            font-size: 1.15rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            font-weight: 500;
            padding: 0.75rem 1.25rem;
            margin: 0.25rem 0.75rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.2s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,0.15);
        }
        .sidebar .nav-link.active {
            background-color: var(--sky-blue);
            font-weight: 600;
            box-shadow: 0 4px 8px rgba(0,119,182,0.3);
        }
        .top-navbar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.75rem 1.5rem;
            box-shadow: var(--card-shadow);
        }
        .content-wrapper {
            padding: 1.75rem;
        }
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            background: #fff;
        }
        .card-header {
            background: transparent;
            border-bottom: 1px solid #f1f5f9;
            font-weight: 600;
            padding: 1rem 1.25rem;
        }
        .badge-status {
            font-weight: 600;
            padding: 0.4em 0.8em;
            border-radius: 6px;
        }
        .table th {
            font-size: 0.825rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            font-weight: 600;
            border-top: none;
        }
    </style>
    @stack('styles')
</head>
<body>
<div class="p-2 bg-white border-bottom d-flex justify-content-between flex-wrap gap-2">
    @if(Gate::allows('operations.read') || Gate::allows('crew.confirm', [['self'=>auth()->id()]]))<a href="{{ route('admin.workflows') }}">Luồng nghiệp vụ</a>@else<a href="{{ route('account') }}">Tài khoản của tôi</a>@endif
    <form method="POST" action="{{ route('logout') }}">@csrf <span>{{ auth()->user()?->full_name }}</span> <button class="btn btn-sm btn-outline-secondary">Đăng xuất</button></form>
</div>
<div class="container-fluid p-0">
    <div class="row g-0">
        <!-- Sidebar Navigation -->
        <div class="col-md-3 col-lg-2 sidebar d-md-block collapse" id="sidebarMenu">
            <div class="brand">
                <i class="bi bi-airplane-engines-fill text-warning fs-4"></i>
                <div>
                    <div>SMART AIRLINE</div>
                    <small class="text-white-50" style="font-size: 0.7rem; font-weight: 400;">Quản lý hàng không</small>
                </div>
            </div>
            <div class="py-3">
                <ul class="nav flex-column">
                    @if(app(\App\Shared\Security\AccessControl::class)->scopes(auth()->user(), 'maintenance.read')->contains(fn($s)=>in_array($s->scope_type,['global','airport'])))
                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.maintenance') }}">Bảo dưỡng kỹ thuật</a></li>
                    @endif
                    @can('operations.read')
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                            <i class="bi bi-grid-1x2-fill"></i> Tổng quan
                        </a>
                    </li>
                    @endcan
                    @if(app(\App\Shared\Security\AccessControl::class)->scopes(auth()->user(), 'airport.read')->contains(fn($s)=>in_array($s->scope_type,['global','airport'])))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.airports*') ? 'active' : '' }}" href="{{ route('admin.airports') }}">
                            <i class="bi bi-geo-alt-fill"></i> 1. Sân bay & Tài nguyên
                        </a>
                    </li>
                    @endif
                    @can('master.read')<li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.master.*') ? 'active' : '' }}" href="{{ route('admin.master.index') }}"><i class="bi bi-database"></i> Dữ liệu chủ</a></li>@endcan
                    @if(app(\App\Shared\Security\AccessControl::class)->scopes(auth()->user(), 'route.read')->contains(fn($s)=>in_array($s->scope_type,['global','airport'])))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.routes*') ? 'active' : '' }}" href="{{ route('admin.routes') }}">
                            <i class="bi bi-signpost-split-fill"></i> 2. Đường bay & Tuyến
                        </a>
                    </li>
                    @endif
                    @if(app(\App\Shared\Security\AccessControl::class)->scopes(auth()->user(), 'schedule.read')->contains(fn($s)=>in_array($s->scope_type,['global','airport'])))
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.schedules*') ? 'active' : '' }}" href="{{ route('admin.schedules') }}" aria-label="Lập và công bố lịch"><i class="bi bi-calendar-check" aria-hidden="true"></i> Lập và công bố lịch</a></li>
                    @endif
                    @if(app(\App\Shared\Security\AccessControl::class)->scopes(auth()->user(), 'aircraft.read')->contains(fn($s)=>in_array($s->scope_type,['global','airport'])))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.aircrafts*') ? 'active' : '' }}" href="{{ route('admin.aircrafts') }}" aria-label="Đội tàu bay">
                            <i class="bi bi-airplane-fill" aria-hidden="true"></i> 3. Đội tàu bay
                        </a>
                    </li>
                    @endif
                    @can('flight.read')
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.flights*') ? 'active' : '' }}" href="{{ route('admin.flights') }}">
                            <i class="bi bi-calendar2-week-fill"></i> 4. Lịch & Chuyến bay
                        </a>
                    </li>
                    @endcan
                    @if(app(\App\Shared\Security\AccessControl::class)->scopes(auth()->user(), 'crew.read')->contains(fn($s)=>in_array($s->scope_type,['global','airport'])))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.crew*') ? 'active' : '' }}" href="{{ route('admin.crew') }}">
                            <i class="bi bi-people-fill"></i> 5. Phi hành đoàn
                        </a>
                    </li>
                    @endif
                    @if(app(\App\Shared\Security\AccessControl::class)->scopes(auth()->user(), 'crew.read')->contains(fn($s)=>in_array($s->scope_type,['global','airport','flight'])))
                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.crew.plans') }}">Phương án tổ bay</a></li>
                    @endif
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <!-- Top Navbar -->
            <nav class="top-navbar d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <button class="btn btn-outline-secondary d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
                        <i class="bi bi-list"></i>
                    </button>
                    <h5 class="mb-0 fw-bold">@yield('page_title', 'Hệ thống Quản lý Khai thác Bay')</h5>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-light text-dark border px-3 py-2">
                        <i class="bi bi-clock me-1 text-primary"></i> <span id="utcTime"></span> UTC
                    </span>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('account') }}"><i class="bi bi-person-circle me-1"></i>{{ auth()->user()?->full_name }}</a>
                </div>
            </nav>

            <!-- Alerts Container -->
            <main class="content-wrapper">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                        <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i> Đã xảy ra lỗi kiểm tra dữ liệu:</h6>
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <!-- Body Content -->
                @yield('content')
            </main>
        </div>
    </div>
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
