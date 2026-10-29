@php
    $currentUser = auth()->user();
    if ($currentUser?->isAdministrator()) {
        $dashboardIdentity = ['label' => 'DILG / System Administrator', 'icon' => 'bi-shield-lock-fill', 'theme' => 'admin'];
    } elseif ($currentUser?->hasDepartment('BFP')) {
        $dashboardIdentity = ['label' => 'BFP Dashboard', 'icon' => 'bi-fire', 'theme' => 'bfp'];
    } elseif ($currentUser?->hasDepartment('PNP')) {
        $dashboardIdentity = ['label' => 'PNP Dashboard', 'icon' => 'bi-shield-fill-check', 'theme' => 'pnp'];
    } elseif ($currentUser?->hasDepartment('MDRRMO')) {
        $dashboardIdentity = ['label' => 'MDRRMO Dashboard', 'icon' => 'bi-life-preserver', 'theme' => 'mdrrmo'];
    } else {
        $dashboardIdentity = ['label' => 'Emergency Response Dashboard', 'icon' => 'bi-grid-1x2-fill', 'theme' => 'default'];
    }
@endphp
<header class="topnav">
    <div class="nav-left">
        <button class="menu-toggle" id="menuToggle"><i class="bi bi-list"></i></button>
        <div class="dashboard-identity dashboard-identity-{{ $dashboardIdentity['theme'] }}" aria-label="{{ $dashboardIdentity['label'] }}">
            <span class="dashboard-identity-icon" aria-hidden="true"><i class="bi {{ $dashboardIdentity['icon'] }}"></i></span>
            <span class="dashboard-identity-label">{{ $dashboardIdentity['label'] }}</span>
        </div>
    </div>

    <div class="nav-right">
        <div class="dropdown">
    <button class="icon-btn dropdown-toggle" data-bs-toggle="dropdown" style="list-style:none;">
        <i class="bi bi-bell-fill"></i>
        @if ($navUnreadCount > 0)
            <span class="badge-dot">{{ $navUnreadCount }}</span>
        @endif
    </button>
    <ul class="dropdown-menu dropdown-menu-end p-2" style="width:300px;">
        <li class="dropdown-header d-flex justify-content-between align-items-center">
            Notifications
            @if ($navUnreadCount > 0)
                <form method="POST" action="{{ route('notifications.markAllRead') }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-link btn-sm p-0" style="font-size:.72rem;">Mark all read</button>
                </form>
            @endif
        </li>
        @forelse ($navNotifications as $n)
            <li>
                <a class="dropdown-item small d-flex align-items-start gap-2 {{ $n->status === 'Unread' ? 'fw-semibold' : '' }}" href="{{ route('notifications.index') }}">
                    <span>{{ $n->status === 'Unread' ? '🔴' : '⚪' }}</span>
                    <span>{{ $n->title }}<br><span class="text-muted" style="font-weight:400;">{{ $n->message }}</span></span>
                </a>
            </li>
        @empty
            <li><span class="dropdown-item-text small text-muted">No notifications yet.</span></li>
        @endforelse
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item small text-center" href="{{ route('notifications.index') }}">View all</a></li>
    </ul>
</div>

        <button class="icon-btn"><i class="bi bi-envelope-fill"></i></button>

        <div class="dropdown">
            <div class="user-chip dropdown-toggle" data-bs-toggle="dropdown">
                <span class="avatar d-inline-block"></span>
                <div>
                    <div class="name">{{ auth()->user()->name ?? 'Jamel Requioma' }}</div>
                    <div class="role">{{ auth()->user()->role ?? 'BFP Personnel' }}</div>
                </div>
            </div>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Profile</a></li>
                <li><a class="dropdown-item" href="{{ route('settings.index') ?? '#' }}"><i class="bi bi-gear me-2"></i>Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="dropdown-item text-danger">
            <i class="bi bi-box-arrow-right me-2"></i>Log out
        </button>
    </form>
</li>
            </ul>
        </div>
    </div>
</header>