<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home">
    @vite(['resources/css/dashboard.css'])
    @include('partials.staff-page-styles')

    @include('partials.staff-sidebar')

    <div class="main-area">
        @include('partials.staff-topbar', [
            'staffNavbarTitle' => 'Notifications',
            'staffNavbarSubtitle' => 'Staff activity alerts',
        ])

        <main class="dashboard-content dashboard-home-content">
            <section class="staff-dashboard">


                <section class="staff-panel staff-notifications-panel">
                    <div class="staff-panel-header staff-notifications-header">
                        <div class="notifications-header-left">
                            <h2>All Notifications</h2>
                            <p class="notifications-header-subtitle" data-notification-unread-label>
                                {{ ($staffNotificationCount ?? 0) > 0 ? $staffNotificationCount . ' unread notification' . ($staffNotificationCount > 1 ? 's' : '') : 'All important notifications are read' }}
                            </p>
                        </div>
                        <form action="{{ route('notifications.read-all') }}" method="POST" data-notifications-read-all>
                            @csrf
                            <button type="submit" class="staff-notification-clear-btn">Mark all as read</button>
                        </form>
                    </div>

                    <div class="notification-list" data-notifications-list>
                        @forelse($staffNotifications as $notification)
                            <x-notification-item :notification="$notification" />
                        @empty
                            <div class="staff-empty-state notifications-empty-state">
                                <div class="notifications-empty-icon">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <p>No important notifications right now.</p>
                                <p class="notifications-empty-subtitle">You're all caught up!</p>
                            </div>
                        @endforelse
                    </div>
                </section>
            </section>
        </main>
    </div>
</div>
