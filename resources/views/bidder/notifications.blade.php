<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home admin-dashboard-page bidder-dashboard-page bidder-notifications-page">
    @vite(['resources/css/dashboard.css'])
    <style>
        body .bidder-notifications-page .notifications-panel{max-width:1180px;margin:0 auto 28px;background:#fff;border:1px solid #e7e1d6;border-radius:18px;overflow:hidden;box-shadow:0 8px 28px rgba(27,48,40,.06)}
        body .bidder-notifications-page .notifications-panel-header{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:24px 28px;background:#fff;border-bottom:1px solid #eee9df}
        body .bidder-notifications-page .notifications-header-left{display:flex;align-items:center;gap:15px;min-width:0}
        body .bidder-notifications-page .notifications-mark{display:grid;place-items:center;width:48px;height:48px;flex:none;border-radius:15px;background:#e8f2ed;color:#205b49;font-size:20px}
        body .bidder-notifications-page .notifications-header-left h2{margin:0 0 4px;font-size:20px;font-weight:700;color:#172b25}
        body .bidder-notifications-page .notifications-header-subtitle{margin:0;color:#66756e;font-size:13px}
        body .bidder-notifications-page .notifications-panel-tools{display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:flex-end}
        body .bidder-notifications-page .notification-filters{display:flex;gap:4px;padding:4px;border:1px solid #e7e1d6;border-radius:12px;background:#faf9f6}
        body .bidder-notifications-page .notification-filter{border:0;border-radius:9px;background:transparent;color:#607068;padding:8px 12px;font-family:inherit;font-size:12px;font-weight:600;cursor:pointer}
        body .bidder-notifications-page .notification-filter.is-active{background:#e8f2ed;color:#174f40}
        body .bidder-notifications-page .notification-mark-all-read-btn{border:0;border-radius:10px;padding:11px 15px;background:#205b49;color:#fff;font-weight:700;white-space:nowrap;cursor:pointer}
        body .bidder-notifications-page .notification-mark-all-read-btn:hover{background:#174838}
        body .bidder-notifications-page .notification-list{display:flex;flex-direction:column;gap:10px;padding:18px 22px 22px}
        body .bidder-notifications-page .notification-list .notification-item{display:grid;grid-template-columns:48px minmax(0,1fr) auto;align-items:center;gap:15px;min-height:82px;padding:15px 17px;border:1px solid #e9e5dc;border-left:3px solid transparent;border-radius:13px;background:#fff;box-shadow:none;transition:background .15s,border-color .15s,transform .15s}
        body .bidder-notifications-page .notification-list .notification-item:hover{transform:translateY(-1px);background:#fbfcfa;border-color:#cddbd3;border-left-color:#205b49;box-shadow:0 5px 15px rgba(27,48,40,.06)}
        body .bidder-notifications-page .notification-list .notification-item.notification-unread{background:#f5faf7;border-left-color:#205b49}
        body .bidder-notifications-page .notification-list .notification-item.notification-read{background:#fff}
        body .bidder-notifications-page .notification-list .notification-icon{width:46px;height:46px;margin:0;border-radius:13px;font-size:17px}
        body .bidder-notifications-page .notification-content{min-width:0}
        body .bidder-notifications-page .notification-title{display:flex;align-items:center;gap:9px}
        body .bidder-notifications-page .notification-title{margin:0;font-size:14px;line-height:1.35;font-weight:700;color:#1d3029!important}
        body .bidder-notifications-page .notification-message{font-size:13px;line-height:1.5;color:#68766f!important;-webkit-line-clamp:2}
        body .bidder-notifications-page .notification-state{display:inline-flex;padding:3px 7px;border-radius:999px;background:#dcece3;color:#205b49;font-size:10px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
        body .bidder-notifications-page .notification-read .notification-state{display:none}
        body .bidder-notifications-page .notification-time{gap:8px;margin:0;color:#76827b!important;font-size:12px}
        body .bidder-notifications-page .notification-time>span{white-space:nowrap}
        body .bidder-notifications-page .notification-action{width:30px;height:30px;border:1px solid #eee9df;border-radius:9px;background:#fff;color:#64736b}
        body .bidder-notifications-page .notifications-empty-state{margin:12px;padding:54px 20px;border:1px dashed #d9dfd9;border-radius:14px;background:#fbfcfa;text-align:center}
        body .bidder-notifications-page .notification-item.is-filtered{display:none!important}
        @media(max-width:760px){body .bidder-notifications-page .notifications-panel-header{align-items:flex-start;padding:19px 16px;flex-direction:column}body .bidder-notifications-page .notifications-panel-tools{width:100%;justify-content:space-between}body .bidder-notifications-page .notification-list{padding:12px}body .bidder-notifications-page .notification-list .notification-item{grid-template-columns:42px minmax(0,1fr);gap:11px;padding:13px}body .bidder-notifications-page .notification-list .notification-icon{width:40px;height:40px}body .bidder-notifications-page .notification-time{grid-column:2;justify-content:space-between;margin-top:4px}body .bidder-notifications-page .notification-filters{flex:1}body .bidder-notifications-page .notification-filter{padding:8px 9px}}
    </style>
    @include('partials.bidder-sidebar')
    <div class="main-area">
        <x-page-header title="Notifications" subtitle="System alerts and updates" />
        <main class="dashboard-content dashboard-home-content">
            <section class="panel notifications-panel" aria-label="Bidder notifications">
                <div class="panel-header notifications-panel-header">
                    <div class="notifications-header-left">
                        <span class="notifications-mark" aria-hidden="true"><i class="fas fa-bell"></i></span>
                        <div>
                            <h2>Notification center</h2>
                            <p class="notifications-header-subtitle" data-notification-unread-label>
                                {{ ($bidderNotificationCount ?? 0) > 0 ? $bidderNotificationCount . ' unread update' . ($bidderNotificationCount > 1 ? 's' : '') : 'You are all caught up' }}
                            </p>
                        </div>
                    </div>
                    <div class="notifications-panel-tools">
                        <div class="notification-filters" role="group" aria-label="Filter notifications">
                            <button type="button" class="notification-filter is-active" data-notification-filter="all" aria-pressed="true">All</button>
                            <button type="button" class="notification-filter" data-notification-filter="unread" aria-pressed="false">Unread</button>
                            <button type="button" class="notification-filter" data-notification-filter="read" aria-pressed="false">Read</button>
                        </div>
                        @if(($bidderNotificationCount ?? 0) > 0)
                            <form method="POST" action="{{ route('notifications.read-all') }}" data-notifications-read-all>
                                @csrf
                                <button type="submit" class="notification-mark-all-read-btn"><i class="fas fa-check-double" aria-hidden="true"></i> Mark all read</button>
                            </form>
                        @endif
                    </div>
                </div>
                <div class="notification-list" data-notifications-list>
                    @forelse($bidderNotifications as $notification)
                        <x-notification-item :notification="$notification" />
                    @empty
                        <div class="notifications-empty-state">
                            <div class="notifications-empty-icon"><i class="fas fa-inbox" aria-hidden="true"></i></div>
                            <p>No notifications yet</p>
                            <p class="notifications-empty-subtitle">Procurement updates will appear here.</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </main>
    </div>
</div>
<script>
    document.querySelectorAll('[data-notification-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            const filter = button.dataset.notificationFilter;
            document.querySelectorAll('[data-notification-filter]').forEach((tab) => {
                const active = tab === button;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            document.querySelectorAll('[data-notification-row]').forEach((row) => {
                const matches = filter === 'all' || row.classList.contains(`notification-${filter}`);
                row.classList.toggle('is-filtered', !matches);
            });
        });
    });
</script>
