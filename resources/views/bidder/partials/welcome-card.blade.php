{{--
    Bidder dashboard: welcome card on the first visit after signing in
    (AuthController::queueBidderWelcome → BidderController::index).
    Uses the dashboard's $user, $bidderName, $isApproved, $opportunities,
    $awaitingResults and $upcoming.
--}}
@php
    $hour = now()->hour;
    [$greeting, $greetingIcon] = match (true) {
        $hour < 12 => ['Good morning', 'fa-sun'],
        $hour < 18 => ['Good afternoon', 'fa-cloud-sun'],
        default => ['Good evening', 'fa-moon'],
    };
    $nextDeadline = ($upcoming ?? collect())->first();
    $openCount = ($opportunities ?? collect())->count();
@endphp

<section class="bw" role="status" aria-labelledby="bw-title" data-bidder-welcome>
    <button type="button" class="bw__close" data-bidder-welcome-close aria-label="Dismiss welcome message">
        <i class="fas fa-xmark" aria-hidden="true"></i>
    </button>

    <div class="bw__head">
        <span class="bw__icon" aria-hidden="true"><i class="fas {{ $greetingIcon }}"></i></span>
        <div>
            <p class="bw__eyebrow">{{ $greeting }}</p>
            <h2 class="bw__title" id="bw-title">Welcome back, {{ $bidderName }}!</h2>
            <p class="bw__text">
                @if($isApproved)
                    Here's where things stand today.
                @else
                    Your registration is with the BAC. Its status is below.
                @endif
            </p>
        </div>
    </div>

    @if($isApproved)
        <ul class="bw__stats">
            <li class="bw__stat">
                <strong>{{ $openCount }}</strong>
                <span>{{ \Illuminate\Support\Str::plural('opportunity', $openCount) }} open for bidding</span>
            </li>
            <li class="bw__stat">
                <strong>{{ $awaitingResults }}</strong>
                <span>{{ \Illuminate\Support\Str::plural('submission', $awaitingResults) }} awaiting results</span>
            </li>
            <li class="bw__stat bw__stat--wide">
                @if($nextDeadline)
                    <a href="{{ $nextDeadline['url'] }}">
                        <small>Next: {{ $nextDeadline['title'] }}</small>
                        <span class="bw__deadline">{{ $nextDeadline['at']->format($nextDeadline['time'] ? 'M j, g:i A' : 'M j') }} · {{ $nextDeadline['at']->diffForHumans() }}</span>
                        <span class="bw__ref">{{ $nextDeadline['reference'] }}</span>
                    </a>
                @else
                    <small>Next deadline</small>
                    <span class="bw__deadline">Nothing in the next 3 weeks</span>
                @endif
            </li>
        </ul>
    @endif
</section>

@push('head')
<style>
    .bw {
        position: relative;
        display: grid;
        gap: 18px;
        padding: 22px 24px;
        overflow: hidden;
        border-radius: var(--ui-radius-lg);
        background: radial-gradient(circle at 92% -20%, rgba(255, 255, 255, .14), transparent 45%), var(--ui-sidebar);
        color: #fff;
        animation: bw-in .45s cubic-bezier(.2, .8, .2, 1) both;
    }
    .bw::after {
        content: "";
        position: absolute;
        right: -60px;
        bottom: -90px;
        width: 240px;
        height: 240px;
        border: 1px solid rgba(255, 255, 255, .08);
        border-radius: 50%;
        box-shadow: 0 0 0 40px rgba(255, 255, 255, .025);
        pointer-events: none;
    }
    .bw.is-leaving { animation: bw-out .25s ease-in forwards; }
    .bw__close {
        position: absolute;
        top: 12px;
        right: 12px;
        z-index: 1;
        display: grid;
        width: 32px;
        height: 32px;
        place-items: center;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: rgba(255, 255, 255, .7);
        cursor: pointer;
    }
    .bw__close:hover, .bw__close:focus-visible { background: rgba(255, 255, 255, .12); color: #fff; outline: none; }
    .bw__head { display: flex; align-items: center; gap: 14px; padding-right: 36px; }
    .bw__icon {
        display: grid;
        flex: 0 0 auto;
        width: 46px;
        height: 46px;
        place-items: center;
        border-radius: 12px;
        background: rgba(255, 255, 255, .12);
        color: #f6d58b;
        font-size: 20px;
        animation: bw-icon .6s ease-out .2s both;
    }
    .bw__eyebrow { margin: 0; color: #b9d6c8; font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }
    .bw__title { margin: 2px 0 0; color: #fff; font-size: 20px; font-weight: 700; letter-spacing: -.015em; line-height: 1.25; overflow-wrap: anywhere; }
    .bw__text { margin: 3px 0 0; color: rgba(255, 255, 255, .78); font-size: 13.5px; }
    .bw__stats { position: relative; z-index: 1; display: grid; grid-template-columns: 1fr 1fr 2fr; gap: 10px; margin: 0; padding: 0; list-style: none; }
    .bw__stat {
        display: grid;
        align-content: center;
        gap: 2px;
        min-width: 0;
        padding: 12px 14px;
        border: 1px solid rgba(255, 255, 255, .12);
        border-radius: 10px;
        background: rgba(255, 255, 255, .07);
        animation: bw-up .4s ease-out both;
    }
    .bw__stat:nth-child(1) { animation-delay: .2s; }
    .bw__stat:nth-child(2) { animation-delay: .3s; }
    .bw__stat:nth-child(3) { animation-delay: .4s; }
    .bw__stat strong { color: #fff; font-size: 22px; font-weight: 700; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .bw__stat span, .bw__stat small { color: rgba(255, 255, 255, .78); font-size: 12.5px; }
    .bw__stat a { display: grid; gap: 2px; min-width: 0; color: inherit; text-decoration: none; }
    .bw__stat a:hover .bw__deadline { text-decoration: underline; }
    .bw__stat small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .bw__stat .bw__deadline { color: #fff; font-size: 15px; font-weight: 600; }
    .bw__stat .bw__ref { color: rgba(255, 255, 255, .6); font-family: var(--ui-mono); font-size: 11.5px; }
    @keyframes bw-in { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: none; } }
    @keyframes bw-out { to { opacity: 0; transform: translateY(-8px); } }
    @keyframes bw-up { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
    @keyframes bw-icon { from { opacity: 0; transform: rotate(-25deg) scale(.7); } to { opacity: 1; transform: none; } }
    @media (max-width: 760px) {
        .bw { padding: 18px; }
        .bw__title { font-size: 18px; }
        .bw__stats { grid-template-columns: 1fr 1fr; }
        .bw__stat--wide { grid-column: 1 / -1; }
    }
    @media (prefers-reduced-motion: reduce) {
        .bw, .bw.is-leaving, .bw__icon, .bw__stat { animation: none; }
    }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        const card = document.querySelector('[data-bidder-welcome]');
        if (!card) return;
        card.querySelector('[data-bidder-welcome-close]').addEventListener('click', function () {
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reduce) { card.remove(); return; }
            card.classList.add('is-leaving');
            card.addEventListener('animationend', function () { card.remove(); }, { once: true });
        });
    })();
</script>
@endpush
