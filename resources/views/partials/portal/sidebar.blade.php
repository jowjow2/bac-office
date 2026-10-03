{{--
    Portal sidebar for every role. Items come from App\Support\PortalNavigation
    (view composer in AppServiceProvider); badges keep the data-* hooks the
    live notification feed updates.
--}}
@php
    $portalUser = auth()->user();
    $portalName = $portalUser?->role === 'bidder'
        ? ($portalUser?->company ?: $portalUser?->name)
        : $portalUser?->name;
    $portalInitials = collect(preg_split('/\s+/', trim((string) $portalName)))
        ->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $portalRoleLabel = match ($portalUser?->role) {
        'admin' => 'BAC · Administrator',
        'staff' => 'BAC Secretariat',
        'bidder' => 'Supplier / Bidder',
        'end_user' => $portalUser?->office ?: 'End-user office',
        default => 'User',
    };
    $portalHome = match ($portalUser?->role) {
        'staff' => route('staff.dashboard'),
        'bidder' => route('bidder.dashboard'),
        'end_user' => route('end-user.dashboard'),
        default => route('admin.dashboard'),
    };
@endphp

<aside class="portal-sidebar" id="portalSidebar" aria-label="Main navigation">
    <a href="{{ $portalHome }}" class="portal-brand">
        <span class="portal-brand__name">SJBAC Procurement</span>
        <span class="portal-brand__sub">Bids and Awards Committee · San Jose, Occ. Mindoro</span>
    </a>

    <button type="button" class="portal-sidebar__close" data-portal-nav-close aria-label="Close navigation">
        <i class="fas fa-xmark" aria-hidden="true"></i>
    </button>

    <nav class="portal-nav">
        @foreach($portalNavigation as $section)
            <div class="portal-nav__section">
                @if($section['title'])
                    <p class="portal-nav__title">{{ $section['title'] }}</p>
                @endif
                <ul class="portal-nav__list">
                    @foreach($section['items'] as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="portal-nav__link" @if($item['active']) aria-current="page" @endif>
                                <i class="fas {{ $item['icon'] }}" aria-hidden="true"></i>
                                <span class="portal-nav__label">{{ $item['label'] }}</span>
                                @if($item['badge_attr'])
                                    <span class="portal-nav__badge" {{ $item['badge_attr'] }} @if(($item['badge'] ?? 0) <= 0) hidden @endif>{{ ($item['badge'] ?? 0) > 0 ? $item['badge'] : '' }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach

        @if($portalUser?->role === 'bidder' && ! $portalUser->isApprovedBidder())
            <p class="portal-nav__locked">
                <i class="fas fa-lock" aria-hidden="true"></i>
                <span>Opportunities and bid submission open once the BAC approves your registration.</span>
            </p>
        @endif
    </nav>

    <div class="portal-user">
        <span class="portal-user__avatar" aria-hidden="true">{{ $portalInitials ?: 'U' }}</span>
        <span class="portal-user__meta">
            <span class="portal-user__name">{{ $portalName ?: 'Signed in' }}</span>
            <span class="portal-user__role">{{ $portalRoleLabel }}</span>
        </span>
        {{-- Opens the confirmation below (portal.js setupDialogs); the dialog holds the real sign-out form. --}}
        <button type="button" class="portal-user__logout" data-dialog-open="portalSignOutDialog" aria-haspopup="dialog" aria-label="Sign out" title="Sign out">
            <i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i>
        </button>
    </div>
</aside>
<div class="portal-backdrop" data-portal-nav-close></div>

<dialog class="portal-signout" id="portalSignOutDialog" aria-labelledby="portalSignOutTitle" aria-describedby="portalSignOutText">
    {{-- Signing out also clears bid files kept in this browser (bidder.partials.bid-file-memory). --}}
    <form method="POST" action="{{ route('logout') }}" class="portal-signout__form" onsubmit="try { window.indexedDB && indexedDB.deleteDatabase('bac-bid-files'); } catch (e) {}">
        @csrf
        <span class="portal-signout__icon" aria-hidden="true"><i class="fas fa-arrow-right-from-bracket"></i></span>
        <h2 id="portalSignOutTitle">Sign out of SJBAC?</h2>
        <p id="portalSignOutText">
            You are signed in as <strong>{{ $portalName ?: 'this account' }}</strong>{{ $portalRoleLabel ? ' · '.$portalRoleLabel : '' }}.
            Anything not yet saved on this page will be lost.
        </p>
        <div class="portal-signout__actions">
            <button type="button" class="portal-signout__button" data-dialog-close autofocus>Cancel</button>
            <button type="submit" class="portal-signout__button is-primary">Sign out</button>
        </div>
    </form>
</dialog>
