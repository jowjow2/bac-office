<aside class="sidebar">
    <a href="{{ route('staff.dashboard') }}" class="sidebar-logo-link">
        <h2 class="sidebar-logo">
            <img src="{{ asset('Images/Logo2.png') }}" alt="BAC Office logo" class="sidebar-logo-image">
            <span>BAC-Office</span>
        </h2>
    </a>
    @include('partials.sidebar-profile')
    <ul class="sidebar-menu">
        <p class="menu-title">MAIN</p>
        <li><a href="{{ route('staff.dashboard') }}" class="{{ request()->routeIs('staff.dashboard') ? 'active' : '' }}"><span class="menu-icon-dashboard" aria-hidden="true"></span> Dashboard</a></li>
        <li><a href="{{ route('staff.assign-projects') }}" class="{{ request()->routeIs('staff.assign-projects') ? 'active' : '' }}"><i class="fas fa-folder-plus"></i> Assign Project</a></li>
        <li><a href="{{ route('staff.review-bids') }}" class="{{ request()->routeIs('staff.review-bids') ? 'active' : '' }}"><i class="fas fa-square-check"></i> Review Bids</a></li>

        <p class="menu-title">TOOLS</p>
        <li>
            <a href="{{ route('staff.messages') }}" class="{{ request()->routeIs('staff.messages*') ? 'active' : '' }}">
                <i class="fas fa-comments"></i> Messages
                @if(($staffUnreadMessagesCount ?? 0) > 0)
                    <span class="notification-badge">{{ $staffUnreadMessagesCount }}</span>
                @endif
            </a>
        </li>
        <li><a href="{{ route('staff.reports') }}" class="{{ request()->routeIs('staff.reports*') ? 'active' : '' }}"><i class="fas fa-chart-bar"></i> Reports</a></li>
        <li>
            <a href="{{ route('staff.notifications') }}" class="{{ request()->routeIs('staff.notifications*') ? 'active' : '' }}">
                <i class="fas fa-bell"></i> Notifications
                <span class="notification-badge" data-notification-badge @if(($staffNotificationCount ?? 0) <= 0) hidden style="display:none" @endif>{{ ($staffNotificationCount ?? 0) > 0 ? $staffNotificationCount : '' }}</span>
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
