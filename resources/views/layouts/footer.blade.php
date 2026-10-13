@php
    $footerGovernment = [
        ['Office of the President', 'https://op-proper.gov.ph/'],
        ['Office of the Vice President', 'https://ovp.gov.ph/'],
        ['Senate of the Philippines', 'https://www.senate.gov.ph/'],
        ['House of the Representatives', 'https://www.congress.gov.ph/'],
        ['Supreme Court', 'https://sc.judiciary.gov.ph/'],
        ['Court of Appeals', 'https://ca.judiciary.gov.ph/'],
        ['Sandiganbayan', 'https://sb.judiciary.gov.ph/'],
        ['Government Procurement Policy Board', 'https://www.gppb.gov.ph/'],
        ['Commission on Audit', 'https://www.coa.gov.ph/'],
        ['Department of the Interior and Local Government', 'https://dilg.gov.ph/'],
    ];
@endphp
<footer class="site-footer" aria-label="Site footer">
    <div class="site-footer__inner">
        <section class="site-footer__col" aria-labelledby="footerContact">
            <h2 id="footerContact">Contact us</h2>
            <ul>
                <li><a href="{{ route('contact') }}">Contact the BAC</a></li>
                <li><a href="{{ url('/about') }}">About the BAC</a></li>
                <li><a href="{{ route('public.procurement') }}">Public procurement</a></li>
                <li><a href="{{ route('public.awards') }}">Awards &amp; contracts</a></li>
                <li><a href="{{ route('privacy') }}">Privacy notice</a></li>
            </ul>
            <p class="site-footer__time">
                <span aria-hidden="true">&#127477;&#127469;</span> | Philippine Standard Time:
                <time id="footerPhTime" data-footer-time>&nbsp;</time>
            </p>
        </section>

        <section class="site-footer__col" aria-labelledby="footerGov">
            <h2 id="footerGov">Government links</h2>
            <ul>
                @foreach($footerGovernment as [$label, $href])
                    <li><a href="{{ $href }}" target="_blank" rel="noopener noreferrer">{{ $label }}</a></li>
                @endforeach
            </ul>
        </section>
    </div>
    <div class="site-footer__bar">
        Bids and Awards Committee &middot; The Official Procurement Portal of the Municipality of San Jose, Occidental Mindoro
    </div>
</footer>

<style>
    .site-footer { margin-top: 48px; background: #1f5c45; color: #fff; font-family: inherit; }
    .site-footer__inner { display: grid; grid-template-columns: 1fr 1fr; gap: 32px 56px; max-width: 1000px; margin: 0 auto; padding: 44px 24px 36px; }
    .site-footer h2 { margin: 0 0 14px; color: #fff; font-size: 14px; font-weight: 700; letter-spacing: .02em; text-transform: uppercase; }
    .site-footer ul { display: grid; gap: 9px; margin: 0; padding: 0; list-style: none; }
    .site-footer li { position: relative; padding-left: 16px; color: rgba(255, 255, 255, .92); font-size: 14px; line-height: 1.4; }
    .site-footer li::before { content: ''; position: absolute; left: 2px; top: .55em; width: 5px; height: 5px; border-radius: 50%; background: rgba(255, 255, 255, .85); }
    .site-footer a { color: inherit; text-decoration: none; }
    .site-footer a:hover, .site-footer a:focus-visible { text-decoration: underline; }
    .site-footer__time { margin: 22px 0 0; color: rgba(255, 255, 255, .92); font-size: 13.5px; line-height: 1.6; }
    .site-footer__time time { display: block; font-weight: 700; font-variant-numeric: tabular-nums; }
    .site-footer__bar { padding: 14px 130px; background: #123d2d; color: rgba(255, 255, 255, .92); font-size: 13px; text-align: center; }
    @media (max-width: 900px) {
        .site-footer__inner { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 560px) {
        .site-footer__inner { grid-template-columns: 1fr; padding: 32px 16px 28px; }
        .site-footer__bar { padding: 14px 56px 14px 16px; }
    }
    @media print { .site-footer { display: none; } }
</style>
<script>
    (function () {
        var node = document.querySelector('[data-footer-time]');
        if (!node) return;
        var format = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Manila', weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit', second: '2-digit' });
        function tick() { node.textContent = format.format(new Date()); }
        tick();
        setInterval(tick, 1000);
    })();
</script>
