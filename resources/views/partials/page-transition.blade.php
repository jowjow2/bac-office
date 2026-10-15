{{--
    Opts every portal page into the cross-page view transition (the sidebar stays put, the rest cross-fades).
    It must be in the page head so the browser sees it before the first paint; older pages load their styles
    later in the body, which would be too late.
--}}
<style>
    @view-transition { navigation: auto; }
    .portal-sidebar { view-transition-name: portal-sidebar; }
    ::view-transition-group(portal-sidebar) { animation: none; }
    ::view-transition-old(portal-sidebar), ::view-transition-new(portal-sidebar) { animation: none; mix-blend-mode: normal; }
    ::view-transition-old(root) { animation-duration: .12s; }
    ::view-transition-new(root) { animation-duration: .18s; }
    @media (prefers-reduced-motion: reduce) { @view-transition { navigation: none; } }
</style>
