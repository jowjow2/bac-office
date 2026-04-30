<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard">
    @vite(['resources/css/dashboard.css'])

    <aside class="sidebar">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-logo-link"><h2 class="sidebar-logo">BAC-Office</h2></a>
        @include('partials.sidebar-profile')
        <ul class="sidebar-menu">
            <p class="menu-title">MAIN</p>
            <li><a href="{{ route('admin.dashboard') }}"><span class="menu-icon-dashboard" aria-hidden="true"></span> Dashboard</a></li>
            <li><a href="{{ route('admin.projects') }}"><i class="fas fa-folder-open"></i> Project/Biddings</a></li>
            <li><a href="{{ route('admin.bids') }}"><span class="menu-icon-all-bids" aria-hidden="true"></span> All Bids</a></li>
            <li><a href="{{ route('admin.awards') }}"><i class="fas fa-trophy"></i> Awards & Contracts</a></li>

            <p class="menu-title">MANAGEMENT</p>
            <li><a href="{{ route('admin.users') }}"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <li><a href="{{ route('admin.assignments') }}"><i class="fas fa-tasks"></i> Staff Assignments</a></li>
            <li><a href="{{ route('admin.reports') }}"><i class="fas fa-chart-bar"></i> Reports</a></li>

            <p class="menu-title">SYSTEM</p>
            <li>
                <a href="{{ route('admin.notifications') }}" class="active">
                    <i class="fas fa-bell"></i> Notifications
                    @if($unreadNotificationsCount > 0)
                        <span class="notification-badge">{{ $unreadNotificationsCount }}</span>
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

    <div class="main-area">
        <header class="navbar">
            <div class="nav-left">
                <h2>Notifications</h2>
                <p>System alerts and updates</p>
            </div>
        </header>

        <main class="dashboard-content">
            <section class="notifications-page-header">
                <h1 class="title">Notifications</h1>
                <p class="subtitle">System alerts and updates</p>
            </section>

            <section class="panel notifications-panel">
                <div class="panel-header">
                    <div>
                        <h2>All Notifications</h2>
                        @if($unreadNotificationsCount > 0)
                            <p>{{ $unreadNotificationsCount }} unread notification{{ $unreadNotificationsCount > 1 ? 's' : '' }}</p>
                        @endif
                    </div>

                    @if(count($notifications) > 0)
                        <form method="POST" action="{{ route('admin.notifications.read-all') }}">
                            @csrf
                            <button type="submit" class="notifications-link-btn">Mark all as read</button>
                        </form>
                    @endif
                </div>

                <div class="notifications-list">
                    @forelse($notifications as $notification)
                        <div class="notification-row {{ $notification['is_read'] ? 'is-read' : 'is-unread' }}">
                            <div class="notification-row-main">
                                <span class="notification-dot"></span>
                                <div class="notification-row-copy">
                                    <div class="notification-row-title">{{ $notification['message'] }}</div>
                                    <div class="notification-row-time">{{ $notification['time'] }}</div>
                                </div>
                            </div>

                            <div class="notification-row-action">
                                @if(! $notification['is_read'])
                                    <form method="POST" action="{{ route('admin.notifications.read', $notification['id']) }}">
                                        @csrf
                                        <button type="submit" class="notifications-link-btn">Mark read</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">No notifications available right now.</div>
                    @endforelse
                </div>
            </section>
        </main>
    </div>
</div>
