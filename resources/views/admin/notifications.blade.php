<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard">
    @vite(['resources/css/dashboard.css'])

    @include('partials.admin-sidebar')

    <div class="main-area">
        <header class="navbar">
            <div class="nav-left">
                <h2>Notifications</h2>
                <p>System alerts and updates</p>
            </div>
        </header>

        <main class="dashboard-content">


            <section class="panel notifications-panel">
                <div class="panel-header notifications-panel-header">
                    <div class="notifications-header-left">
                        <h2>All Notifications</h2>
                        <p class="notifications-header-subtitle" data-notification-unread-label>
                            {{ $unreadNotificationsCount > 0 ? $unreadNotificationsCount . ' unread notification' . ($unreadNotificationsCount > 1 ? 's' : '') : 'All important notifications are read' }}
                        </p>
                    </div>

                    @if(count($notifications) > 0)
                        <form method="POST" action="{{ route('notifications.read-all') }}" data-notifications-read-all>
                            @csrf
                            <button type="submit" class="notification-mark-all-read-btn">Mark all as read</button>
                        </form>
                    @endif
                </div>

                <div class="notification-list" data-notifications-list>
                    @forelse($notifications as $notification)
                        <x-notification-item :notification="$notification" />
                    @empty
                        <div class="notifications-empty-state">
                            <div class="notifications-empty-icon">
                                <i class="fas fa-inbox"></i>
                            </div>
                            <p>No important notifications right now.</p>
                            <p class="notifications-empty-subtitle">You're all caught up!</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </main>
    </div>
</div>
