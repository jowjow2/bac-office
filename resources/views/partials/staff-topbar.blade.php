@php
    $staffNavbarUser = auth()->user();
    $staffNavbarName = $staffNavbarUser?->name ?? 'Staff Member';
    $staffNavbarRole = ucfirst($staffNavbarUser?->role ?? 'staff');
    $staffNavbarInitials = collect(preg_split('/\s+/', trim($staffNavbarName)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->implode('');
@endphp

<header class="navbar">
    <div class="nav-left">
        <h2>{{ $staffNavbarTitle ?? 'Dashboard' }}</h2>
        <p>{{ $staffNavbarSubtitle ?? 'Overview & Analytics' }}</p>
    </div>

    <div class="nav-right">
        <div class="nav-icons">
            <a href="{{ route('staff.notifications') }}" class="notification-button" aria-label="Notifications">
                <i class="fas fa-bell"></i>
                <span class="notification-badge" data-notification-badge @if(($staffNotificationCount ?? 0) <= 0) hidden style="display:none" @endif>{{ ($staffNotificationCount ?? 0) > 0 ? $staffNotificationCount : '' }}</span>
            </a>
            <span class="nav-date-chip">
                <i class="far fa-clock" aria-hidden="true"></i>
                <span id="realtimeDate"></span>
            </span>
            <div class="navbar-profile-chip staff-navbar-profile">
                <span class="navbar-user-avatar">{{ $staffNavbarInitials }}</span>
                <span class="navbar-profile-meta">
                    <span class="navbar-profile-name">{{ $staffNavbarName }}</span>
                    <span class="navbar-profile-role">{{ $staffNavbarRole }}</span>
                </span>
            </div>
        </div>
    </div>
</header>

<script>
    (function () {
        function updateRealtimeDate() {
            const dateElement = document.getElementById('realtimeDate');
            if (!dateElement) return;

            const now = new Date();
            const compact = window.innerWidth <= 560;
            dateElement.textContent = now.toLocaleString('en-PH', compact ? {
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
            } : {
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
