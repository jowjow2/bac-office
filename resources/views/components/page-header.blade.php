{{--
    Page header shared by every page and role: menu button (small screens),
    title, subtitle, optional breadcrumbs and actions, and the notification
    bell. Styles live in resources/css/portal-shell.css.

    <x-page-header title="Bid management" subtitle="Review submitted bids">
        <x-slot:actions>…buttons…</x-slot:actions>
    </x-page-header>
--}}
@props(['title', 'subtitle' => null, 'crumbs' => null, 'actions' => null])

<header {{ $attributes->class('portal-header') }}>
    <button type="button" class="portal-menu-btn" data-portal-nav-toggle aria-controls="portalSidebar" aria-expanded="false" aria-label="Open navigation">
        <i class="fas fa-bars" aria-hidden="true"></i>
    </button>

    <div class="portal-header__titles">
        @if(filled((string) $crumbs))
            <nav class="portal-header__crumbs" aria-label="Breadcrumb">{{ $crumbs }}</nav>
        @endif
        <h1 class="portal-header__title">{{ $title }}</h1>
        @if(filled((string) $subtitle))
            <p class="portal-header__subtitle">{{ $subtitle }}</p>
        @endif
    </div>

    @if(filled(trim((string) $actions)))
        <div class="portal-header__actions">{{ $actions }}</div>
    @endif

    @if(in_array(auth()->user()?->role, ['admin', 'staff'], true))
        {{-- Search the whole portal (portal.js); Ctrl+K opens it too. --}}
        <button type="button" class="portal-search-btn" data-portal-search="{{ route('portal.search') }}" aria-label="Search the portal (Ctrl+K)" title="Search the portal (Ctrl+K)">
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i><span>Search</span><kbd>Ctrl K</kbd>
        </button>
    @endif
    <x-portal-bell />
</header>
