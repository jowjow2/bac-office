@extends('layouts.portal')

@php
    $onlyBid = request()->filled('bid');
    $refreshLabel = intdiv($refreshSeconds, 60) === 1 ? 'minute' : intdiv($refreshSeconds, 60).' minutes';
@endphp

@section('title', 'Submission status')
@section('subtitle', 'Follow each bid through the BAC process. A stage changes only when the BAC or LGU records an action.')

@section('actions')
    <a href="{{ route('bidder.my-bids') }}" class="ui-btn ui-btn--secondary"><i class="fas fa-list" aria-hidden="true"></i> My submission bids</a>
@endsection

@push('head')
<style>
    .bt-mini { display: flex; align-items: center; gap: 8px; margin-top: 6px; min-width: 0; }
    .bt-mini-bar { flex: 0 0 120px; height: 6px; border-radius: 999px; background: var(--ui-line, #e3e8e4); overflow: hidden; }
    .bt-mini-bar > span { display: block; height: 100%; border-radius: inherit; background: var(--ui-primary, #1f5c45); }
    .bt-mini-text { overflow: hidden; color: var(--ui-muted); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
    .bt-expected { color: var(--ui-primary); }
    .bt-filterbar { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 10px 16px; margin-bottom: 10px; border-bottom: 1px solid var(--ui-line); }
    .bt-filterbar .ui-tabs { border-bottom: 0; }
    .bt-filterbar .ui-tab { border: 0; border-bottom: 2px solid transparent; background: none; font-family: inherit; cursor: pointer; }
    .bt-filterbar .ui-search { flex: 0 1 300px; margin: 8px 0; }
    .bt-legend-help { position: relative; }
    .bt-legend-help > summary { display: inline-flex; align-items: center; gap: 6px; color: var(--ui-muted); font-size: 12.5px; cursor: pointer; list-style: none; }
    .bt-legend-help > summary::-webkit-details-marker { display: none; }
    .bt-legend-help[open] > .bt-legend { position: absolute; z-index: 5; top: calc(100% + 6px); left: 0; padding: 10px 12px; border: 1px solid var(--ui-line); border-radius: 10px; background: #fff; box-shadow: 0 10px 24px rgba(15, 25, 21, .1); }
    .bt-to-list { font-size: 12.5px; }
    .bt-ack { margin-bottom: 12px; }
    .bt-card.is-changed { animation: bt-flash 1.2s ease 3; }
    @keyframes bt-flash { 50% { box-shadow: 0 0 0 4px var(--ui-primary-soft, #dceee5); border-color: var(--ui-primary, #1f5c45); } }
    .bt-toast { position: fixed; z-index: 80; right: 18px; bottom: 18px; max-width: min(460px, calc(100vw - 36px)); padding: 12px 16px; border-radius: 12px; background: #1b2420; color: #fff; font-size: 13.5px; line-height: 1.45; box-shadow: 0 12px 30px rgba(0, 0, 0, .25); }
    @media (max-width: 768px) { .bt-toast { bottom: calc(80px + env(safe-area-inset-bottom, 0px)); } .bt-mini-bar { flex-basis: 80px; } }
    @media (prefers-reduced-motion: reduce) { .bt-card.is-changed { animation: none; outline: 2px solid var(--ui-primary); } }
    .bt-page { display: grid; gap: 14px; }
    .bt-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 16px; }
    .bt-legend { display: flex; flex-wrap: wrap; gap: 4px 14px; margin: 0; padding: 0; list-style: none; color: var(--ui-muted); font-size: var(--ui-text-sm); }
    .bt-legend li { display: inline-flex; align-items: center; gap: 6px; }
    .bt-legend .bt-step-marker { width: 16px; height: 16px; border-width: 2px; font-size: 7px; }
    .bt-refresh-wrap { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; color: var(--ui-muted); font-size: var(--ui-text-sm); }
    .bt-refresh[aria-busy="true"] i { animation: bt-spin .8s linear infinite; }
    @keyframes bt-spin { to { transform: rotate(360deg); } }
    .bt-filtered { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 12px; color: var(--ui-muted); font-size: var(--ui-text-sm); }

    .bt-list { display: grid; gap: 14px; }
    .bt-card { overflow: hidden; border: 1px solid var(--ui-line); border-radius: var(--ui-radius-lg); background: var(--ui-surface); }
    .bt-card-head { position: relative; display: flex; align-items: flex-start; justify-content: space-between; gap: 12px 20px; padding: 16px 52px 16px 18px; cursor: pointer; list-style: none; }
    .bt-card-head::-webkit-details-marker { display: none; }
    .bt-card-head:hover { background: var(--ui-page); }
    .bt-card-head:focus-visible { outline: 3px solid var(--ui-primary-line); outline-offset: -3px; }
    .bt-card[open] .bt-card-head { border-bottom: 1px solid var(--ui-line); }
    .bt-card-project { display: grid; justify-items: start; gap: 6px; min-width: 0; }
    .bt-card-ref { display: flex; flex-wrap: wrap; gap: 2px 10px; margin: 0; color: var(--ui-subtle); font-size: var(--ui-text-xs); font-weight: 600; }
    .bt-card-ref code { color: var(--ui-muted); font-family: var(--ui-mono); }
    .bt-card-title { margin: 0; color: var(--ui-ink); font-size: var(--ui-text-lg); font-weight: 700; line-height: 1.35; overflow-wrap: anywhere; }
    .bt-card-amount { flex: none; text-align: right; }
    .bt-card-amount span { display: block; color: var(--ui-subtle); font-size: var(--ui-text-xs); font-weight: 600; }
    .bt-card-amount strong { display: block; color: var(--ui-ink); font-size: 19px; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .bt-card-amount small { display: block; margin-top: 2px; color: var(--ui-muted); font-size: var(--ui-text-xs); }
    .bt-card-amount .is-under { color: var(--ui-success); }
    .bt-card-amount .is-over { color: var(--ui-danger); }
    .bt-card-toggle { position: absolute; top: 20px; right: 18px; color: var(--ui-muted); transition: transform .2s ease; }
    .bt-card[open] .bt-card-toggle { transform: rotate(180deg); }

    .bt-card-body { display: grid; grid-template-columns: minmax(0, 1fr) 300px; }
    .bt-main { display: grid; align-content: start; gap: 14px; min-width: 0; padding: 18px; }
    .bt-side { display: grid; align-content: start; gap: 16px; padding: 18px; border-left: 1px solid var(--ui-line); background: var(--ui-page); }
    .bt-section-title { margin: 0; color: var(--ui-ink); font-size: var(--ui-text-sm); font-weight: 700; }
    .bt-section-title span { color: var(--ui-subtle); font-weight: 500; }
    .bt-subtle { margin: 0; color: var(--ui-muted); font-size: var(--ui-text-sm); }

    .bt-now { display: grid; gap: 4px; padding: 12px 14px; border: 1px solid var(--ui-line); border-left: 4px solid var(--ui-info); border-radius: var(--ui-radius); background: var(--ui-info-soft); }
    .bt-now.is-success { border-left-color: var(--ui-success); background: var(--ui-success-soft); }
    .bt-now.is-danger { border-left-color: var(--ui-danger); background: var(--ui-danger-soft); }
    .bt-now.is-warning { border-left-color: var(--ui-warning); background: var(--ui-warning-soft); }
    .bt-now.is-neutral { border-left-color: var(--ui-line-strong); background: var(--ui-page); }
    .bt-now-label { color: var(--ui-muted); font-size: var(--ui-text-xs); font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .bt-now-title { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 8px; margin: 0; color: var(--ui-ink); font-size: 15px; font-weight: 700; }
    .bt-now-time { color: var(--ui-muted); font-size: var(--ui-text-sm); font-weight: 500; }
    .bt-now-text { margin: 0; color: var(--ui-ink-2); font-size: var(--ui-text-sm); line-height: 1.5; }
    .bt-now-next { display: flex; flex-wrap: wrap; gap: 4px 16px; margin: 4px 0 0; color: var(--ui-muted); font-size: var(--ui-text-xs); }
    .bt-now-next span:first-child { color: var(--ui-ink-2); font-weight: 600; }

    .bt-timeline { margin: 0; padding: 0; list-style: none; }
    .bt-step { position: relative; display: grid; grid-template-columns: 24px minmax(0, 1fr); gap: 12px; padding: 0 0 14px; }
    .bt-step:last-child { padding-bottom: 0; }
    .bt-step:not(:last-child)::before { content: ''; position: absolute; top: 26px; bottom: 2px; left: 11px; width: 2px; background: var(--ui-line); }
    .bt-step.is-done:not(:last-child)::before { background: var(--ui-success-line); }
    .bt-step-marker { display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box; width: 24px; height: 24px; border: 2px solid var(--ui-line-strong); border-radius: 50%; background: var(--ui-surface); color: var(--ui-line-strong); font-size: 10px; }
    .is-pending .bt-step-marker i { font-size: 6px; }
    .is-done .bt-step-marker { border-color: var(--ui-success); background: var(--ui-success); color: #fff; }
    .is-current .bt-step-marker { border-color: var(--ui-primary); color: var(--ui-primary); box-shadow: 0 0 0 4px var(--ui-primary-soft); }
    .is-current .bt-step-marker i { font-size: 8px; }
    .is-failed .bt-step-marker { border-color: var(--ui-danger); background: var(--ui-danger); color: #fff; }
    .is-unrecorded .bt-step-marker { border-style: dashed; color: var(--ui-subtle); }
    .bt-step-body { min-width: 0; padding-top: 2px; }
    .bt-step-row { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 2px 12px; }
    .bt-step-title { margin: 0; color: var(--ui-ink); font-size: 13.5px; font-weight: 600; }
    .bt-step-when { color: var(--ui-muted); font-size: var(--ui-text-xs); white-space: nowrap; }
    .bt-step-desc { margin: 2px 0 0; color: var(--ui-muted); font-size: var(--ui-text-sm); line-height: 1.5; }
    .bt-step-note { margin: 3px 0 0; color: var(--ui-ink-2); font-size: var(--ui-text-sm); }
    .is-current .bt-step-title, .is-current .bt-step-when { color: var(--ui-primary); font-weight: 700; }
    .is-failed .bt-step-title { color: var(--ui-danger); }
    .is-pending .bt-step-title, .is-unrecorded .bt-step-title { color: var(--ui-muted); font-weight: 500; }
    .bt-footnote { margin: 0; color: var(--ui-muted); font-size: var(--ui-text-sm); }

    .bt-side-action { display: grid; gap: 6px; }
    .bt-side-action p { margin: 0; color: var(--ui-muted); font-size: var(--ui-text-xs); text-align: center; }
    .bt-facts { display: grid; gap: 10px; margin: 0; }
    .bt-facts dt { color: var(--ui-subtle); font-size: var(--ui-text-xs); font-weight: 600; }
    .bt-facts dd { margin: 1px 0 0; color: var(--ui-ink); font-size: var(--ui-text-sm); overflow-wrap: anywhere; }
    .bt-facts dd small { display: block; color: var(--ui-muted); font-size: var(--ui-text-xs); }
    .bt-facts .ui-mono { font-size: 12px; }
    .bt-history { display: grid; gap: 8px; padding-top: 14px; border-top: 1px solid var(--ui-line); }
    .bt-feed { overflow: hidden; border: 1px solid var(--ui-line); border-radius: var(--ui-radius); background: var(--ui-surface); }
    .bt-feed .ui-feed__item { padding: 10px 12px; }

    .bt-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }

    @media (max-width: 960px) {
        .bt-card-body { grid-template-columns: 1fr; }
        .bt-side { border-top: 1px solid var(--ui-line); border-left: 0; }
        .bt-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 600px) {
        .bt-card-head { flex-direction: column; padding: 14px 44px 14px 14px; }
        .bt-card-amount { text-align: left; }
        .bt-card-toggle { right: 14px; }
        .bt-main, .bt-side { padding: 14px; }
        .bt-step-when { white-space: normal; }
    }
    @media (prefers-reduced-motion: reduce) {
        .bt-card-toggle, .bt-refresh[aria-busy="true"] i { transition: none; animation: none; }
    }
</style>
@endpush

@section('content')
    @if($bids->isEmpty())
        <div class="ui-card">
            <div class="ui-empty">
                <i class="fas fa-folder-open" aria-hidden="true"></i>
                <strong>You haven't submitted any bids yet.</strong>
                <span>Browse the open opportunities to start bidding and follow their progress here.</span>
                <a href="{{ route('bidder.available-projects') }}" class="ui-btn ui-btn--primary ui-btn--sm ui-mt-sm">View available bids</a>
            </div>
        </div>
    @else
        <div class="bt-page">
            @if($onlyBid)
                <p class="bt-filtered">
                    <span>Showing one bid.</span>
                    <a href="{{ route('bidder.bidding-track') }}" class="ui-link">Show all your bids</a>
                </p>
            @endif

            @unless($onlyBid)
                <div class="bt-filterbar">
                    <nav class="ui-tabs" aria-label="Filter bids">
                        <button type="button" class="ui-tab" data-bt-filter="all" aria-current="page">All <span class="ui-tab__count">{{ $bids->count() }}</span></button>
                        <button type="button" class="ui-tab" data-bt-filter="active">In progress <span class="ui-tab__count">{{ $bids->filter(fn ($b) => ! $b->progress()->toArray()['is_closed'])->count() }}</span></button>
                        <button type="button" class="ui-tab" data-bt-filter="ended">Ended <span class="ui-tab__count">{{ $bids->filter(fn ($b) => $b->progress()->toArray()['is_closed'])->count() }}</span></button>
                    </nav>
                    <label class="ui-search">
                        <span class="sr-only">Search bids</span>
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <input type="search" class="ui-input" id="btSearch" placeholder="Search project, reference or receipt no." autocomplete="off">
                    </label>
                </div>
            @endunless

            <div class="bt-toolbar">
                <details class="bt-legend-help">
                    <summary aria-label="What the marks mean"><i class="fas fa-circle-question" aria-hidden="true"></i> Legend</summary>
                <ul class="bt-legend" aria-label="Legend">
                    <li class="is-done"><span class="bt-step-marker" aria-hidden="true"><i class="fas fa-check"></i></span> Completed</li>
                    <li class="is-current"><span class="bt-step-marker" aria-hidden="true"><i class="fas fa-circle"></i></span> Current</li>
                    <li class="is-pending"><span class="bt-step-marker" aria-hidden="true"><i class="fas fa-circle"></i></span> Pending</li>
                    <li class="is-failed"><span class="bt-step-marker" aria-hidden="true"><i class="fas fa-xmark"></i></span> Stopped</li>
                </ul>
                </details>
                <a href="{{ route('bidder.my-bids') }}" class="ui-link bt-to-list">The list of your bids is under My bids</a>
                <div class="bt-refresh-wrap">
                    <span id="bt-checked" aria-live="polite">Checks for updates every {{ $refreshLabel }}.</span>
                    <button type="button" class="ui-btn ui-btn--secondary ui-btn--sm bt-refresh" id="bt-refresh">
                        <i class="fas fa-rotate-right" aria-hidden="true"></i> Refresh
                    </button>
                </div>
            </div>

            <div class="bt-toast" id="btToast" role="status" aria-live="polite" hidden></div>
            <div id="bidding-track-container" class="bt-list">
                @foreach($bids as $bid)
                    @include('bidder.partials.bid-progress-card', ['bid' => $bid])
                @endforeach
            </div>
        </div>
    @endif
@endsection

@if($bids->isNotEmpty())
@push('scripts')
<script>
    (function () {
        const container = document.getElementById('bidding-track-container');
        const checkedLabel = document.getElementById('bt-checked');
        const refreshButton = document.getElementById('bt-refresh');
        if (!container) return;

        const dataUrl = @json(route('bidder.bidding-track.data', ['html' => 1]));
        const onlyBidId = @json($onlyBid ? (int) request()->query('bid') : null);
        let inFlight = false;

        async function refresh() {
            if (inFlight) return;
            inFlight = true;
            refreshButton?.setAttribute('aria-busy', 'true');

            try {
                const response = await fetch(dataUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!response.ok) throw new Error('Request failed');
                const data = await response.json();
                if (!data.ok) throw new Error('Request failed');

                let changed = 0;
                const notes = [];
                data.bids.forEach(function (bid) {
                    if (onlyBidId !== null && bid.id !== onlyBidId) return;
                    const card = container.querySelector('.bt-card[data-bid-id="' + bid.id + '"]');
                    if (!card || card.dataset.signature === bid.signature) return;

                    const wasOpen = card.open;
                    const template = document.createElement('template');
                    template.innerHTML = bid.html.trim();
                    const fresh = template.content.firstElementChild;
                    if (!fresh) return;
                    fresh.open = wasOpen || fresh.open;
                    card.replaceWith(fresh);
                    fresh.classList.add('is-changed');
                    window.setTimeout(function () { fresh.classList.remove('is-changed'); }, 4000);
                    const title = (fresh.querySelector('.bt-card-title') || {}).textContent || 'A bid';
                    const status = (fresh.querySelector('.bt-card-head .ui-badge') || {}).textContent || 'a new status';
                    notes.push(title.trim() + ' is now: ' + status.trim());
                    changed++;
                });

                const toast = document.getElementById('btToast');
                if (toast && notes.length) {
                    toast.textContent = notes.join(' · ');
                    toast.hidden = false;
                    window.clearTimeout(toast.timer);
                    toast.timer = window.setTimeout(function () { toast.hidden = true; }, 8000);
                }
                applyFilter();
                const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                checkedLabel.textContent = (changed ? changed + ' bid' + (changed > 1 ? 's' : '') + ' updated. ' : '') + 'Last checked ' + time + '.';
            } catch (error) {
                checkedLabel.textContent = 'Could not check for updates. Try Refresh.';
            } finally {
                inFlight = false;
                refreshButton?.removeAttribute('aria-busy');
            }
        }

        let btFilter = 'all';
        const btSearch = document.getElementById('btSearch');
        function applyFilter() {
            const term = (btSearch ? btSearch.value : '').trim().toLowerCase();
            container.querySelectorAll('.bt-card').forEach(function (card) {
                const inFilter = btFilter === 'all' || card.dataset.state === btFilter;
                card.hidden = !(inFilter && (!term || (card.dataset.search || '').includes(term)));
            });
        }
        document.querySelectorAll('[data-bt-filter]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                btFilter = tab.dataset.btFilter;
                document.querySelectorAll('[data-bt-filter]').forEach(function (other) {
                    if (other === tab) other.setAttribute('aria-current', 'page'); else other.removeAttribute('aria-current');
                });
                applyFilter();
            });
        });
        btSearch?.addEventListener('input', applyFilter);

        refreshButton?.addEventListener('click', refresh);
        setInterval(function () {
            if (!document.hidden) refresh();
        }, {{ $refreshSeconds * 1000 }});
    })();
</script>
@endpush
@endif
