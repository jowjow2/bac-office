<aside class="sidebar">
    <a href="{{ route('admin.dashboard') }}" class="sidebar-logo-link">
        <h2 class="sidebar-logo">
            <img src="{{ asset('Images/Logo2.png') }}" alt="BAC Office logo" class="sidebar-logo-image">
            <span>BAC-Office</span>
        </h2>
    </a>
    @include('partials.sidebar-profile')
    <ul class="sidebar-menu">
        <p class="menu-title">MAIN</p>
        <li><a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><span class="menu-icon-dashboard" aria-hidden="true"></span> Dashboard</a></li>
        <li><a href="{{ route('admin.projects') }}" class="{{ request()->routeIs('admin.projects*', 'admin.project.*') ? 'active' : '' }}"><i class="fas fa-folder-open"></i> Project/Biddings</a></li>
        <li><a href="{{ route('admin.bids') }}" class="{{ request()->routeIs('admin.bids', 'admin.bid.*') ? 'active' : '' }}"><span class="menu-icon-all-bids" aria-hidden="true"></span> All Bids</a></li>
        <li><a href="{{ route('admin.awards.index') }}" class="{{ request()->routeIs('admin.awards*', 'admin.award.*') ? 'active' : '' }}"><i class="fas fa-trophy"></i> Awards & Contracts</a></li>

        <p class="menu-title">MANAGEMENT</p>
        <li><a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users*') ? 'active' : '' }}"><i class="fas fa-users-cog"></i> Manage Users</a></li>
        <li>
            <a href="{{ route('admin.messages') }}" class="{{ request()->routeIs('admin.messages*') ? 'active' : '' }}">
                <i class="fas fa-comments"></i> Messages
                @if(($adminUnreadMessagesCount ?? 0) > 0)
                    <span class="notification-badge">{{ $adminUnreadMessagesCount }}</span>
                @endif
            </a>
        </li>
        <li><a href="{{ route('admin.assignments') }}" class="{{ request()->routeIs('admin.assignments*') ? 'active' : '' }}"><i class="fas fa-tasks"></i> Staff Assignments</a></li>
        <li><a href="{{ route('admin.reports') }}" class="{{ request()->routeIs('admin.reports*') ? 'active' : '' }}"><i class="fas fa-chart-bar"></i> Reports</a></li>

        <p class="menu-title">SYSTEM</p>
        <li>
            <a href="{{ route('admin.notifications') }}" class="{{ request()->routeIs('admin.notifications*') ? 'active' : '' }}">
                <i class="fas fa-bell"></i> Notifications
                <span class="notification-badge" data-notification-badge @if(($unreadNotificationsCount ?? 0) <= 0) hidden style="display:none" @endif>{{ ($unreadNotificationsCount ?? 0) > 0 ? $unreadNotificationsCount : '' }}</span>
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

@include('partials.notification-live')
