<header class="navbar">
    <div class="nav-left">
        <h2>{{ $staffNavbarTitle ?? 'Dashboard' }}</h2>
        <p>{{ $staffNavbarSubtitle ?? 'Overview & Analytics' }}</p>
    </div>

    <div class="nav-right">
        <span id="realtimeDate"></span>
        <a href="{{ route('staff.notifications') }}" class="notification-button" aria-label="Notifications">
            <i class="fas fa-bell"></i>
            @if(($staffNotificationCount ?? 0) > 0)
                <span class="notification-badge">{{ $staffNotificationCount }}</span>
            @endif
        </a>
    </div>
</header>

<script>
    (function () {
        function updateRealtimeDate() {
            const dateElement = document.getElementById('realtimeDate');
            if (!dateElement) return;

            const now = new Date();
            dateElement.textContent = now.toLocaleString('en-PH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
            });
        }

        updateRealtimeDate();
        setInterval(updateRealtimeDate, 1000);
    })();
</script>
