{{--
    Switch portal pages in one frame, keeping the outgoing page visible until the next is ready.
    Wait for the whole portal document, including its styles and restored sidebar position,
    before taking the incoming snapshot. Otherwise a partial document can flash on navigation.
--}}
<link rel="expect" href="#portal-page-ready" blocking="render">
<style>
    @view-transition { navigation: auto; }
    .portal-sidebar { view-transition-name: portal-sidebar; }
    ::view-transition-group(*) { animation: none; }
    ::view-transition-old(*) { display: none; }
    ::view-transition-new(*) { animation: none; mix-blend-mode: normal; }
    @media (prefers-reduced-motion: reduce) { @view-transition { navigation: none; } }
</style>
