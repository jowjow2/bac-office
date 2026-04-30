<aside class="sidebar">
    <a href="{{ route('staff.dashboard') }}" class="sidebar-logo-link"><h2 class="sidebar-logo">BAC-Office</h2></a>
    @include('partials.sidebar-profile')
    <ul class="sidebar-menu">
        <p class="menu-title">MAIN</p>
        <li><a href="{{ route('staff.dashboard') }}" class="{{ ($activeStaffMenu ?? '') === 'dashboard' ? 'active' : '' }}"><span class="menu-icon-dashboard" aria-hidden="true"></span> Dashboard</a></li>
        <li><a href="{{ route('staff.assign-projects') }}" class="{{ ($activeStaffMenu ?? '') === 'assign-projects' ? 'active' : '' }}"><i class="fas fa-folder-plus"></i> Assign Project</a></li>
        <li><a href="{{ route('staff.review-bids') }}" class="{{ ($activeStaffMenu ?? '') === 'review-bids' ? 'active' : '' }}"><i class="fas fa-square-check"></i> Review Bids</a></li>

        <p class="menu-title">TOOLS</p>
        <li><a href="{{ route('staff.reports') }}" class="{{ ($activeStaffMenu ?? '') === 'reports' ? 'active' : '' }}"><i class="fas fa-chart-bar"></i> Reports</a></li>
        <li>
            <a href="{{ route('staff.notifications') }}" class="{{ ($activeStaffMenu ?? '') === 'notifications' ? 'active' : '' }}">
                <i class="fas fa-bell"></i> Notifications
                @if(($staffNotificationCount ?? 0) > 0)
                    <span class="notification-badge">{{ $staffNotificationCount }}</span>
                @endif
            </a>
        </li>
        <li>
            <form action="{{ route('logout') }}" method="POST" class="sidebar-form">
                @csrf
                <button type="submit" class="sidebar-logout"><i class="fas fa-sign-out-alt"></i> Logout</button>
            </form>
        </li>
    </ul>
</aside>
