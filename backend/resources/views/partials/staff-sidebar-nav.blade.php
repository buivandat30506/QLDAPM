@php($sidebarGroups = \App\Support\StaffNavigation::groups(auth()->user()))
<nav class="sidebar-nav" aria-label="Chức năng nhân viên">
    <div class="sidebar-section">
        <div class="sidebar-section-label">Tổng quan</div>
        <a class="sidebar-link {{ request()->is('staff') ? 'active' : '' }}" href="/staff" @if(request()->is('staff')) aria-current="page" @endif>
            <span class="sidebar-icon">@include('partials.sidebar-icon', ['icon' => 'dashboard'])</span><span>Bảng điều khiển tổng quan</span>
        </a>
    </div>
    @foreach($sidebarGroups as $group)
        <div class="sidebar-section">
            <div class="sidebar-section-label">{{ $group['label'] }}</div>
            @foreach($group['items'] as $item)
                @if(isset($item['children']))
                    @php($groupActive = collect($item['children'])->contains(fn ($child) => \App\Support\StaffNavigation::isActive($child, request())))
                    <details class="sidebar-group" name="staff-navigation" data-sidebar-group="{{ $item['key'] }}" @if($groupActive) open @endif>
                        <summary class="sidebar-group-summary {{ $groupActive ? 'active' : '' }}">
                            <span class="sidebar-icon">@include('partials.sidebar-icon', ['icon' => $item['icon']])</span><span>{{ $item['label'] }}</span><svg class="sidebar-chevron" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m4 6 4 4 4-4" /></svg>
                        </summary>
                        <div class="sidebar-subnav">
                            @foreach($item['children'] as $child)
                                @php($childActive = \App\Support\StaffNavigation::isActive($child, request()))
                                <a class="sidebar-sublink {{ $childActive ? 'active' : '' }}" href="{{ $child['href'] }}" @if($childActive) aria-current="page" @endif>{{ $child['label'] }}</a>
                            @endforeach
                        </div>
                    </details>
                @else
                    @php($itemActive = \App\Support\StaffNavigation::isActive($item, request()))
                    <a class="sidebar-link {{ $itemActive ? 'active' : '' }}" href="{{ $item['href'] }}" @if($itemActive) aria-current="page" @endif><span class="sidebar-icon">@include('partials.sidebar-icon', ['icon' => $item['icon']])</span><span>{{ $item['label'] }}</span></a>
                @endif
            @endforeach
        </div>
    @endforeach
</nav>
