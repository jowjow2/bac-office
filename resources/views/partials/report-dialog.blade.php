{{--
    A report shown in a dialog over the page. The report is an ordinary page that understands ?embed=1.

      <a href="{{ route('…report') }}" data-report-dialog="awardsReport">Report</a>
      @include('partials.report-dialog', ['id' => 'awardsReport', 'title' => 'Awards report', 'url' => route('…report', ['embed' => 1])])

    The link still works as a normal link without JavaScript; the frame loads on first open.
--}}
@once
<style>
    .rd-dialog { width: min(1040px, calc(100vw - 24px)); height: min(880px, calc(100dvh - 24px)); max-width: none; max-height: none; margin: auto; padding: 0; overflow: hidden; border: 0; border-radius: 16px; background: #fff; box-shadow: 0 24px 70px rgba(15, 25, 21, .35); }
    .rd-dialog[open] { display: grid; grid-template-rows: auto minmax(0, 1fr); animation: rd-rise .26s cubic-bezier(.2, .8, .2, 1) both; }
    .rd-dialog::backdrop { background: rgba(15, 25, 21, .55); backdrop-filter: blur(2px); }
    .rd-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; border-bottom: 1px solid #dfe6e1; }
    .rd-head h2 { margin: 0; color: #1b2420; font: 700 17px/1.2 var(--ui-font, system-ui, sans-serif); letter-spacing: -.01em; }
    .rd-close { display: grid; width: 32px; height: 32px; place-items: center; border: 1px solid #dfe6e1; border-radius: 9px; background: #fff; color: #6b7a72; font-size: 18px; line-height: 1; cursor: pointer; }
    .rd-close:hover { background: #f4f7f5; color: #1b2420; }
    .rd-frame { display: block; width: 100%; height: 100%; border: 0; background: #fff; }
    @keyframes rd-rise { from { opacity: 0; transform: translateY(12px) scale(.985); } to { opacity: 1; transform: none; } }
    @media (max-width: 760px) { .rd-dialog { width: 100vw; height: 100dvh; border-radius: 0; } }
    @media (prefers-reduced-motion: reduce) { .rd-dialog[open] { animation: none; } }
</style>
<script>
    document.addEventListener('click', function (event) {
        const link = event.target.closest('[data-report-dialog]');
        if (!link) return;
        const dialog = document.getElementById(link.dataset.reportDialog);
        if (!dialog) return;
        event.preventDefault();
        const frame = dialog.querySelector('.rd-frame');
        if (!frame.getAttribute('src')) frame.setAttribute('src', frame.dataset.src);
        if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
    });
</script>
@endonce

<dialog class="rd-dialog" id="{{ $id }}" aria-labelledby="{{ $id }}Title">
    <div class="rd-head">
        <h2 id="{{ $id }}Title">{{ $title }}</h2>
        <button type="button" class="rd-close" aria-label="Close" onclick="this.closest('dialog').close()">&times;</button>
    </div>
    <iframe class="rd-frame" title="{{ $title }}" data-src="{{ $url }}"></iframe>
</dialog>
<script>
    (function () {
        const dialog = document.getElementById(@json($id));
        dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close(); });
    })();
</script>
