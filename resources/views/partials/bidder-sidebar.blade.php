{{--
    Older pages built on dashboard.css include this partial. It renders the
    shared portal sidebar (partials/portal/sidebar) with its own stylesheet,
    so every page of the portal has the same navigation.
--}}
@include('partials.portal.assets')
@include('partials.portal.sidebar')
@include('partials.notification-live')
