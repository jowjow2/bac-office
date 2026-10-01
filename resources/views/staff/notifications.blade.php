<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard staff-role-page dashboard-home admin-dashboard-page staff-dashboard staff-dashboard-page">
    @vite(['resources/css/dashboard.css'])

    @include('partials.staff-sidebar')

    <div class="main-area">
        <x-page-header title="Notifications" subtitle="Staff activity alerts" />

        <main class="dashboard-content dashboard-home-content">
            <section class="panel notifications-panel staff-notifications-panel">
                <div class="panel-header notifications-panel-header">
                    <div class="notifications-header-left">
                        <h2>All Notifications</h2>
                        <p class="notifications-header-subtitle" data-notification-unread-label>
                            {{ ($staffNotificationCount ?? 0) > 0 ? $staffNotificationCount . ' unread notification' . ($staffNotificationCount > 1 ? 's' : '') : 'All important notifications are read' }}
                        </p>
                    </div>
                    @if(($staffNotificationCount ?? 0) > 0)
                        <form action="{{ route('notifications.read-all') }}" method="POST" data-notifications-read-all>
                            @csrf
                            <button type="submit" class="notification-mark-all-read-btn">Mark all as read</button>
                        </form>
                    @endif
                </div>

                <div class="notification-list" data-notifications-list>
                    @forelse($staffNotifications as $notification)
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
