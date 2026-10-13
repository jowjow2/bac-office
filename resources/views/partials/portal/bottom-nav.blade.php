{{--
    Bottom tab bar for bidders on a phone (hidden on wider screens, see ui.css). The four places a bidder uses most,
    and Menu, which opens the full navigation drawer. Items come from the same PortalNavigation list as the sidebar,
    so a bidder who is not yet approved sees only what the sidebar offers.
--}}
@php
    $tabbarItems = collect($portalNavigation ?? [])->flatMap(fn ($section) => $section['items']);
    $tabbarWanted = [
        route('bidder.dashboard') => ['Home', 'fa-house'],
        route('bidder.available-projects') => ['Projects', 'fa-bullhorn'],
        route('bidder.my-bids') => ['My bids', 'fa-envelope-circle-check'],
        route('bidder.awarded-contracts') => ['Contracts', 'fa-award'],
    ];
    $tabbar = $tabbarItems->filter(fn ($item) => isset($tabbarWanted[$item['url']]))->values();
@endphp
@if(auth()->user()?->role === 'bidder' && $tabbar->isNotEmpty())
    <nav class="portal-tabbar" aria-label="Quick navigation">
        @foreach($tabbar as $item)
            <a href="{{ $item['url'] }}" class="portal-tabbar__item" @if($item['active']) aria-current="page" @endif>
                <i class="fas {{ $tabbarWanted[$item['url']][1] }}" aria-hidden="true"></i>
                <span>{{ $tabbarWanted[$item['url']][0] }}</span>
            </a>
        @endforeach
        <button type="button" class="portal-tabbar__item" data-portal-nav-toggle aria-controls="portalSidebar" aria-label="Open the full menu">
            <i class="fas fa-bars" aria-hidden="true"></i>
            <span>Menu</span>
        </button>
    </nav>
@endif
