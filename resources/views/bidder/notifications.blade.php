<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home bidder-notifications-page">
    @vite(['resources/css/dashboard.css'])

    @include('partials.bidder-sidebar')

    <div class="main-area">
        <header class="navbar">
            <div class="nav-left">
                <h2>Notifications</h2>
                <p>System alerts and updates</p>
            </div>

            <div class="nav-right">
                <div class="nav-date-chip"><span id="realtimeDate">{{ now()->format('M d, Y h:i A') }}</span></div>
                <a href="{{ route('bidder.notifications') }}" class="notification-button" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    @if(($bidderNotificationCount ?? 0) > 0)
                        <span class="notification-badge">{{ $bidderNotificationCount }}</span>
                    @endif
                </a>
            </div>
        </header>

        <main class="dashboard-content dashboard-home-content">


            <section class="panel notifications-panel">
                <div class="panel-header notifications-panel-header">
                    <div class="notifications-header-left">
                        <h2>All Notifications</h2>
                        <p class="notifications-header-subtitle" data-notification-unread-label>
                            {{ ($bidderNotificationCount ?? 0) > 0 ? $bidderNotificationCount . ' unread notification' . ($bidderNotificationCount > 1 ? 's' : '') : 'All important notifications are read' }}
                        </p>
                    </div>
                    @if(($bidderNotificationCount ?? 0) > 0)
                        <form method="POST" action="{{ route('notifications.read-all') }}" data-notifications-read-all>
                            @csrf
                            <button type="submit" class="notification-mark-all-read-btn">Mark all as read</button>
                        </form>
                    @endif
                </div>

                <div class="notification-list" data-notifications-list>
                    @forelse($bidderNotifications as $notification)
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

<script>
    (function () {
        const realtimeDate = document.getElementById('realtimeDate');
        if (!realtimeDate) return;

        function updateRealtimeDate() {
            realtimeDate.textContent = new Date().toLocaleString('en-PH', {
                month: 'short',
                day: '2-digit',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit'
            });
        }

        updateRealtimeDate();
        setInterval(updateRealtimeDate, 1000);
    })();
</script>
