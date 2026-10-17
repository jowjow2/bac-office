{{--
    Switch portal pages in one frame, keeping the outgoing page visible until the next is ready.
    It must be in the page head so the browser sees it before the first paint; older pages load their styles
    later in the body, which would be too late.
--}}
<style>
    @view-transition { navigation: auto; }
    .portal-sidebar { view-transition-name: portal-sidebar; }
    ::view-transition-group(*) { animation: none; }
    ::view-transition-old(*) { display: none; }
    ::view-transition-new(*) { animation: none; mix-blend-mode: normal; }
    @media (prefers-reduced-motion: reduce) { @view-transition { navigation: none; } }
</style>
