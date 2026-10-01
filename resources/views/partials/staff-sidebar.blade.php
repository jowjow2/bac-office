{{--
    Older pages built on dashboard.css include this partial. It renders the
    shared portal sidebar (partials/portal/sidebar) with its own stylesheet,
    so every page of the portal has the same navigation.
--}}
@once
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Inter:wght@400;500;600;700&display=swap">
    @vite(['resources/css/portal-shell.css', 'resources/js/portal.js'])
@endonce
@include('partials.portal.sidebar')
@include('partials.notification-live')
