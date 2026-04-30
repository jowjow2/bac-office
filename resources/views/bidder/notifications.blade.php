<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home bidder-notifications-page">
    @vite(['resources/css/dashboard.css'])

    <style>
        .bidder-notifications-page {
            font-family: 'Inter', sans-serif;
        }

        .bidder-sidebar-badge {
            margin-left: auto;
        }

        .bidder-notifications-page .page-intro {
            margin-bottom: 22px;
        }

        .bidder-notifications-page .page-title {
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
        }

        .bidder-notifications-page .page-subtitle {
            margin: 0;
            font-size: 13px;
            color: #94a3b8;
        }

        .bidder-notifications-panel {
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .bidder-notifications-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 22px;
            border-bottom: 1px solid #e2e8f0;
        }

        .bidder-notifications-header h2 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }

        .bidder-mark-read-btn {
            border: 0;
            background: transparent;
            color: #64748b;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
        }

        .bidder-notification-list {
            padding: 0 22px;
        }

        .bidder-notification-item {
            display: flex;
            gap: 12px;
            padding: 18px 0;
            border-bottom: 1px solid #eef2f7;
        }

        .bidder-notification-item:last-child {
            border-bottom: 0;
        }

        .bidder-notification-dot {
            width: 10px;
            height: 10px;
            margin-top: 8px;
            border-radius: 999px;
            background: #cbd5e1;
            flex-shrink: 0;
        }

        .bidder-notification-title {
            margin: 0 0 6px;
            font-size: 13px;
            font-weight: 500;
            color: #0f172a;
        }

        .bidder-notification-time {
            margin: 0;
            font-size: 12px;
            color: #94a3b8;
        }

        .bidder-empty {
            padding: 26px 20px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }

        .bidder-alert {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            font-size: 12px;
        }

        .bidder-alert-success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    </style>

    <aside class="sidebar">
        <a href="{{ route('bidder.dashboard') }}" class="sidebar-logo-link"><h2 class="sidebar-logo">BAC-Office</h2></a>
        @include('partials.sidebar-profile')
        <ul class="sidebar-menu">
            <p class="menu-title">MAIN</p>
            <li><a href="{{ route('bidder.dashboard') }}"><span class="menu-icon-dashboard" aria-hidden="true"></span> Dashboard</a></li>
            <li><a href="{{ route('bidder.available-projects') }}"><i class="fas fa-folder-open"></i> Available Projects</a></li>
            <li><a href="{{ route('bidder.my-bids') }}"><i class="fas fa-file-signature"></i> My Bids</a></li>
            <li><a href="{{ route('bidder.awarded-contracts') }}"><i class="fas fa-award"></i> Awarded Contracts</a></li>

            <p class="menu-title">ACCOUNT</p>
            <li><a href="{{ route('bidder.company-profile') }}"><i class="fas fa-building"></i> Company Profile</a></li>
            <li><a href="{{ route('bidder.notifications') }}" class="active"><i class="fas fa-bell"></i> Notification @if(($bidderNotificationCount ?? 0) > 0)<span class="notification-badge bidder-sidebar-badge">{{ $bidderNotificationCount }}</span>@endif</a></li>

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
            <section class="page-intro">
                <h1 class="page-title">Notifications</h1>
                <p class="page-subtitle">System alerts and updates</p>
            </section>


            <section class="bidder-notifications-panel">
                <div class="bidder-notifications-header">
                    <h2>All Notifications</h2>
                    @if(($bidderNotificationCount ?? 0) > 0)
                        <form method="POST" action="{{ route('bidder.notifications.read-all') }}">
                            @csrf
                            
                            <button type="submit" class="bidder-mark-read-btn">Mark all as read</button>
                        </form>
                    @endif
                </div>

                <div class="bidder-notification-list">
                    @forelse($bidderNotifications as $notification)
                        <div class="bidder-notification-item">
                            <span class="bidder-notification-dot"></span>
                            <div>
                                <p class="bidder-notification-title">{{ $notification['title'] }}</p>
                                <p class="bidder-notification-time">{{ $notification['time'] }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="bidder-empty">No new notifications.</div>
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

