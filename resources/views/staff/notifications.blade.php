<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home">
    @vite(['resources/css/dashboard.css'])
    @include('partials.staff-page-styles')

    @include('partials.staff-sidebar', ['activeStaffMenu' => 'notifications'])

    <div class="main-area">
        @include('partials.staff-topbar', [
            'staffNavbarTitle' => 'Notifications',
            'staffNavbarSubtitle' => 'Staff activity alerts',
        ])

        <main class="dashboard-content dashboard-home-content">
            <section class="staff-dashboard">
                <section class="staff-page-intro">
                    <h1 class="staff-page-title">Notifications</h1>
                    <p class="staff-page-subtitle">System alerts and updates</p>
                </section>


                <section class="staff-panel staff-notifications-panel">
                    <div class="staff-panel-header staff-notifications-header">
                        <h2>All Notifications</h2>
                        <form action="{{ route('staff.notifications.read-all') }}" method="POST">
                            @csrf
                            
                            <button type="submit" class="staff-notification-clear">Mark all as read</button>
                        </form>
                    </div>

                    <div class="staff-notification-list staff-notification-list-compact">
                        @forelse($staffNotifications as $notification)
                            <div class="staff-notification-item staff-notification-row">
                                <span class="staff-notification-dot"></span>
                                <div class="staff-notification-copy">
                                    <strong>{{ $notification['title'] }}</strong>
                                    <p>{{ $notification['time'] ?? 'Just now' }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="staff-empty-state">No notifications available right now.</div>
                        @endforelse
                    </div>
                </section>
            </section>
        </main>
    </div>
</div>

