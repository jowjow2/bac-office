{{--
    The notification center, the same for every role. Rows are grouped by day
    and come from SystemNotification::payload; partials.notification-live
    redraws the list every few seconds with the same markup (renderPageRow).
--}}
@php
    $centerUser = auth()->user();
    $centerItems = \App\Support\SystemNotification::payloads(\App\Support\SystemNotification::forUser($centerUser?->id, 30), $centerUser);
    $centerUnread = $centerItems->where('is_read', false)->count();
    $centerTz = config('bac-office.display_timezone', 'Asia/Manila');
    $centerGroup = function (?string $iso) use ($centerTz) {
        if (! $iso) return 'Earlier';
        $day = \Illuminate\Support\Carbon::parse($iso)->timezone($centerTz)->startOfDay();
        $today = now($centerTz)->startOfDay();
        return match (true) {
            $day->gte($today) => 'Today',
            $day->eq($today->copy()->subDay()) => 'Yesterday',
            $day->gte($today->copy()->subDays(6)) => 'This week',
            default => 'Earlier',
        };
    };
    $centerGroups = $centerItems->groupBy(fn ($item) => $centerGroup($item['created_at'] ?? null));
@endphp

<style>
    .nc { max-width: 980px; margin: 0 auto 28px; overflow: hidden; border: 1px solid var(--ui-line, #e6e1d6); border-radius: 16px; background: #fff; color: var(--ui-ink, #1b2420); font-family: var(--ui-font, inherit); }
    .nc-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 22px; border-bottom: 1px solid var(--ui-line, #e6e1d6); }
    .nc-head h2 { margin: 0; font-size: 17px; font-weight: 700; color: var(--ui-ink, #1b2420); }
    .nc-head p { margin: 2px 0 0; color: var(--ui-muted, #66756e); font-size: 13px; }
    .nc-tools { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .nc-tabs { display: inline-flex; gap: 2px; padding: 3px; border-radius: 10px; background: var(--ui-surface-2, #f7f5ef); }
    .nc :is(.nc-tab, #nc-x#nc-x) { display: inline-flex !important; align-items: center !important; gap: 6px !important; min-height: 32px !important; padding: 0 12px !important; border: 0 !important; border-radius: 8px !important; background: transparent !important; color: var(--ui-muted, #66756e) !important; -webkit-text-fill-color: currentColor !important; font: inherit !important; font-size: 13px !important; font-weight: 600 !important; cursor: pointer; box-shadow: none !important; }
    .nc :is(.nc-tab[aria-pressed="true"], #nc-x#nc-x) { background: #fff !important; color: var(--ui-ink, #1b2420) !important; box-shadow: 0 1px 2px rgba(27, 36, 32, .08) !important; }
    .nc-tab__count { display: inline-grid; min-width: 18px; height: 18px; place-items: center; padding: 0 5px; border-radius: 999px; background: var(--ui-primary, #1d4f40); color: #fff; -webkit-text-fill-color: #fff; font-size: 11px; }
    .nc-tab__count[hidden] { display: none; }
    .nc :is(.nc-markall, #nc-x#nc-x) { display: inline-flex !important; align-items: center !important; gap: 7px !important; min-height: 36px !important; padding: 0 12px !important; border: 1px solid var(--ui-line, #e6e1d6) !important; border-radius: 9px !important; background: #fff !important; color: var(--ui-ink-2, #3c4641) !important; -webkit-text-fill-color: currentColor !important; font: inherit !important; font-size: 13px !important; font-weight: 600 !important; cursor: pointer; box-shadow: none !important; }
    .nc :is(.nc-markall:hover, #nc-x#nc-x) { border-color: var(--ui-line-strong, #d9d4c7) !important; color: var(--ui-ink, #1b2420) !important; }

    .nc-list { display: block; padding: 4px 0 8px; }
    .nc-group__label { margin: 0; padding: 14px 22px 6px; color: var(--ui-subtle, #78827c); font-size: 11.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .nc-item { position: relative; display: grid; grid-template-columns: 38px minmax(0, 1fr) auto; align-items: start; gap: 14px; padding: 12px 22px; color: inherit; text-decoration: none; transition: background-color .15s ease; }
    .nc-item + .nc-item { border-top: 1px solid var(--ui-line-soft, #efebe2); }
    .nc-item:hover, .nc-item:focus-visible { background: var(--ui-surface-2, #f7f5ef); outline: none; }
    .nc-item.notification-unread { background: #f6faf8; }
    .nc-item.notification-unread:hover { background: #eef6f1; }
    .nc-icon { display: grid; width: 38px; height: 38px; place-items: center; border-radius: 50%; background: var(--nc-soft, #f1efe8); color: var(--nc-accent, #66756e); -webkit-text-fill-color: var(--nc-accent, #66756e); font-size: 15px; }
    .nc-icon--info { --nc-accent: var(--ui-info, #1f4f86); --nc-soft: var(--ui-info-soft, #e6eef8); }
    .nc-icon--success { --nc-accent: var(--ui-success, #1d6a4d); --nc-soft: var(--ui-success-soft, #e2f2ea); }
    .nc-icon--warning { --nc-accent: var(--ui-warning, #875705); --nc-soft: var(--ui-warning-soft, #fcf1d8); }
    .nc-icon--danger { --nc-accent: var(--ui-danger, #b42318); --nc-soft: var(--ui-danger-soft, #fbe9e7); }
    .nc-body { display: grid; gap: 3px; min-width: 0; }
    .nc-top { display: flex; align-items: center; gap: 8px; min-width: 0; }
    .nc-title { overflow: hidden; color: var(--ui-ink, #1b2420); -webkit-text-fill-color: var(--ui-ink, #1b2420); font-size: 14px; font-weight: 500; text-overflow: ellipsis; white-space: nowrap; }
    .nc-item.notification-unread .nc-title { font-weight: 700; }
    .nc-cat { flex: 0 0 auto; padding: 1px 7px; border-radius: 999px; background: var(--ui-surface-2, #f7f5ef); color: var(--ui-muted, #66756e); -webkit-text-fill-color: var(--ui-muted, #66756e); font-size: 11px; font-weight: 600; }
    .nc-msg { display: -webkit-box; overflow: hidden; color: var(--ui-ink-2, #3c4641); -webkit-text-fill-color: var(--ui-ink-2, #3c4641); font-size: 13px; line-height: 1.5; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .nc-item.notification-read .nc-msg { color: var(--ui-muted, #66756e); -webkit-text-fill-color: var(--ui-muted, #66756e); }
    .nc-meta { display: flex; align-items: center; gap: 10px; padding-top: 2px; color: var(--ui-subtle, #78827c); -webkit-text-fill-color: var(--ui-subtle, #78827c); font-size: 12px; white-space: nowrap; }
    .nc-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--ui-primary, #1d4f40); }
    .nc-item.notification-read .nc-dot { visibility: hidden; }
    .nc-empty { display: grid; justify-items: center; gap: 6px; padding: 56px 20px; color: var(--ui-muted, #66756e); font-size: 13px; text-align: center; }
    .nc-empty i { display: grid; width: 48px; height: 48px; place-items: center; border-radius: 50%; background: var(--ui-primary-soft, #e8f1ec); color: var(--ui-primary, #1d4f40); -webkit-text-fill-color: var(--ui-primary, #1d4f40); font-size: 19px; }
    .nc-empty strong { color: var(--ui-ink, #1b2420); font-size: 14.5px; }
    /* Unread filter: read rows, and days left with nothing unread, step out. */
    .nc[data-filter="unread"] .nc-item.notification-read,
    .nc[data-filter="unread"] .nc-group:not(:has(.notification-unread)) { display: none; }
    .nc-none-unread { display: none; }
    .nc[data-filter="unread"] .nc-list:not(:has(.notification-unread)) + .nc-none-unread { display: grid; }
    @media (max-width: 640px) {
        .nc { border-radius: 12px; }
        .nc-head { flex-direction: column; align-items: stretch; padding: 16px; }
        .nc-tools { justify-content: space-between; }
        .nc-group__label { padding: 12px 16px 4px; }
        .nc-item { grid-template-columns: 34px minmax(0, 1fr); gap: 12px; padding: 12px 16px; }
        .nc-icon { width: 34px; height: 34px; font-size: 14px; }
        .nc-meta { grid-column: 2; padding-top: 0; }
        .nc-title { white-space: normal; }
    }
</style>

<section class="nc" data-nc data-filter="all" aria-labelledby="nc-title">
    <header class="nc-head">
        <div>
            <h2 id="nc-title">Updates</h2>
            <p data-notification-unread-label>{{ $centerUnread > 0 ? $centerUnread.' unread' : 'You are all caught up' }}</p>
        </div>
        <div class="nc-tools">
            <div class="nc-tabs" role="group" aria-label="Show">
                <button type="button" class="nc-tab" data-nc-filter="all" aria-pressed="true">All</button>
                <button type="button" class="nc-tab" data-nc-filter="unread" aria-pressed="false">Unread <span class="nc-tab__count" data-nc-unread-count @if($centerUnread === 0) hidden @endif>{{ $centerUnread }}</span></button>
            </div>
            <form method="POST" action="{{ route('notifications.read-all') }}" data-notifications-read-all>
                @csrf
                <button type="submit" class="nc-markall"><i class="fas fa-check-double" aria-hidden="true"></i> Mark all as read</button>
            </form>
        </div>
    </header>

    <div class="nc-list" data-notifications-list data-nc-list>
        @forelse($centerGroups as $label => $items)
            <div class="nc-group">
                <h3 class="nc-group__label">{{ $label }}</h3>
                @foreach($items as $item)
                    <a href="{{ route('notifications.open', ['notification' => $item['id']]) }}" class="nc-item {{ $item['is_read'] ? 'notification-read' : 'notification-unread' }}" data-notification-row data-notification-open data-notification-id="{{ $item['id'] }}">
                        <span class="nc-icon nc-icon--{{ $item['tone'] }}" aria-hidden="true"><i class="fas {{ $item['icon'] }}"></i></span>
                        <span class="nc-body">
                            <span class="nc-top"><span class="nc-title">{{ $item['title'] }}</span><span class="nc-cat">{{ $item['category'] }}</span></span>
                            <span class="nc-msg">{{ $item['message'] }}</span>
                        </span>
                        <span class="nc-meta"><time datetime="{{ $item['created_at'] }}">{{ $item['time'] }}</time><span class="nc-dot" aria-label="{{ $item['is_read'] ? '' : 'Unread' }}"></span></span>
                    </a>
                @endforeach
            </div>
        @empty
            <div class="nc-empty"><i class="fas fa-inbox" aria-hidden="true"></i><strong>No notifications yet</strong><span>Procurement updates will appear here.</span></div>
        @endforelse
    </div>
    <div class="nc-empty nc-none-unread"><i class="fas fa-check" aria-hidden="true"></i><strong>No unread notifications</strong><span>You are all caught up.</span></div>
</section>

<script>
    document.querySelectorAll('[data-nc]').forEach(function (center) {
        center.querySelectorAll('[data-nc-filter]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                center.dataset.filter = tab.dataset.ncFilter;
                center.querySelectorAll('[data-nc-filter]').forEach(function (other) {
                    other.setAttribute('aria-pressed', other === tab ? 'true' : 'false');
                });
            });
        });
    });
</script>
