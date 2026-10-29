<aside class="sidebar">
    <div class="brand">
        <div class="logo">🔥</div>
        <div class="title">Web GIS Emergency<br>Dispatch System</div>
    </div>

    <nav>
        <a href="{{ route('dashboard') ?? '#' }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-house-fill"></i><span>Dashboard</span>
        </a>
        <a href="{{ route('incidents.index') ?? '#' }}" class="nav-link {{ request()->routeIs('incidents.*') ? 'active' : '' }}">
            <i class="bi bi-truck-front-fill"></i><span>Incidents</span>
        </a>
        <a href="{{ route('gis.map') ?? '#' }}" class="nav-link {{ request()->routeIs('gis.*') ? 'active' : '' }}">
            <i class="bi bi-geo-alt-fill"></i><span>GIS Map</span>
        </a>
        @if(auth()->user()->role === 'Administrator' || auth()->user()->department === 'BFP')
            <a href="{{ route('firestations.index') ?? '#' }}" class="nav-link {{ request()->routeIs('firestations.*') ? 'active' : '' }}">
                <i class="bi bi-building-fill"></i><span>Fire Stations</span>
            </a>
        @endif
        @if(in_array(auth()->user()->role, ['Administrator', 'Head']))
            <a href="{{ route('users.index') ?? '#' }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <i class="bi bi-people-fill"></i><span>Users</span>
            </a>
        @endif
        <a href="{{ route('reports.index') ?? '#' }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
            <i class="bi bi-graph-up"></i><span>Reports</span>
        </a>
        <a href="{{ route('notifications.index') ?? '#' }}" class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
            <i class="bi bi-bell-fill"></i><span>Notifications</span>
        </a>
        @if(auth()->user()->role === 'Administrator')
            <a href="{{ route('settings.index') ?? '#' }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                <i class="bi bi-gear-fill"></i><span>Settings</span>
            </a>
        @endif
        <a href="{{ route('login') ?? '#' }}" class="nav-link logout-link">
            <i class="bi bi-box-arrow-right"></i><span>Logout</span>
        </a>
    </nav>
</aside>