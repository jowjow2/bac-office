{{--
    Notification bell of the page header, shared by every page and role.
    $portalUnreadNotifications and $portalNotifications come from the view
    composer in AppServiceProvider; partials/notification-live keeps the
    badge and list current, and resources/js/portal.js opens the panel.
--}}
@php
    $portalNotificationsUrl = match (auth()->user()?->role) {
        'staff' => route('staff.notifications'),
        'bidder' => route('bidder.notifications'),
        default => route('admin.notifications'),
    };
@endphp

<div class="portal-bell" data-portal-bell>
    <button type="button" class="portal-bell__button" data-portal-bell-toggle aria-expanded="false" aria-controls="portalBellPanel" aria-label="Notifications{{ $portalUnreadNotifications > 0 ? ', '.$portalUnreadNotifications.' unread' : '' }}">
        <i class="fas fa-bell" aria-hidden="true"></i>
        <span class="portal-bell__count" data-notification-badge @if($portalUnreadNotifications <= 0) hidden @endif>{{ $portalUnreadNotifications > 0 ? $portalUnreadNotifications : '' }}</span>
    </button>

    <div class="portal-bell__panel" id="portalBellPanel" hidden>
        <div class="portal-bell__head">
            <strong data-notification-unread-label>{{ $portalUnreadNotifications > 0 ? $portalUnreadNotifications.' unread' : 'All caught up' }}</strong>
            @if($portalUnreadNotifications > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}" data-notifications-read-all>
                    @csrf
                    <button type="submit" class="portal-bell__mark">Mark all read</button>
                </form>
            @endif
        </div>
        <div class="portal-bell__list" data-notification-dropdown-list>
            @forelse($portalNotifications as $notification)
                <a href="{{ route('notifications.open', ['notification' => $notification['id']]) }}" class="portal-bell__item {{ ($notification['is_read'] ?? false) ? '' : 'is-unread' }}" data-notification-row data-notification-open data-notification-id="{{ $notification['id'] }}">
                    {{ $notification['title'] ?: 'Notification' }}
                    <small>{{ $notification['message'] ?? '' }} · {{ $notification['time'] ?? '' }}</small>
                </a>
            @empty
                <div class="portal-bell__empty">No notifications yet.</div>
            @endforelse
        </div>
        <a href="{{ $portalNotificationsUrl }}" class="portal-bell__foot">All notifications</a>
    </div>
</div>
