@extends('layouts.portal')

@use('App\Support\SystemNotification')

@php
    $user = auth()->user();
    $hour = now()->hour;
    [$greeting, $greetingIcon] = match (true) {
        $hour < 12 => ['Good morning', 'fa-sun'],
        $hour < 18 => ['Good afternoon', 'fa-cloud-sun'],
        default => ['Good evening', 'fa-moon'],
    };
    $peso = fn (float $amount) => '₱'.number_format($amount, $amount >= 1000000 ? 0 : 2);
    $kpis = [
        ['draft', 'Drafts', $counts['drafts'], 'Not yet submitted', 'fa-pen-to-square', 'neutral'],
        ['returned', 'Returned for correction', $counts['returned'], 'See the reviewer\'s remarks', 'fa-rotate-left', $counts['returned'] > 0 ? 'warning' : 'neutral'],
        ['submitted', 'In review / with the BAC', $counts['review'], 'PPMP/APP and funds check', 'fa-magnifying-glass-chart', 'info'],
        ['in_procurement', 'In procurement', $counts['procurement'], 'Bidding, RFQ, award or delivery', 'fa-gavel', 'success'],
    ];
@endphp

@section('title', 'Purchase requests overview')
@section('subtitle', $office.' · File purchase requests and follow each one through the PPMP/APP check, bidding or RFQ, award and acceptance.')

@section('actions')
    <a href="{{ route('end-user.requests.create') }}" class="ui-btn ui-btn--primary"><i class="fas fa-plus" aria-hidden="true"></i> New purchase request</a>
@endsection

@section('content')
    @if($welcome)
        <section class="eu-welcome" role="status" aria-labelledby="eu-welcome-title" data-eu-welcome>
            <span class="eu-welcome__icon" aria-hidden="true"><i class="fas {{ $greetingIcon }}"></i></span>
            <div class="eu-welcome__text">
                <p class="eu-welcome__eyebrow">{{ $greeting }}</p>
                <h2 class="eu-welcome__title" id="eu-welcome-title">Welcome back, {{ $user->name }}!</h2>
                <p class="eu-welcome__meta">{{ collect([$user->position ?? null, $office])->filter()->implode(' · ') }}</p>
            </div>
            <p class="eu-welcome__summary">
                @if($counts['returned'] > 0)
                    <strong>{{ $counts['returned'] }} {{ \Illuminate\Support\Str::plural('request', $counts['returned']) }}</strong> returned for correction.
                @elseif($counts['drafts'] > 0)
                    <strong>{{ $counts['drafts'] }} {{ \Illuminate\Support\Str::plural('draft', $counts['drafts']) }}</strong> waiting to be submitted.
                @else
                    {{ $counts['review'] + $counts['procurement'] }} {{ \Illuminate\Support\Str::plural('request', $counts['review'] + $counts['procurement']) }} moving through review and procurement.
                @endif
            </p>
            <button type="button" class="eu-welcome__close" data-eu-welcome-close aria-label="Dismiss welcome message"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </section>
    @endif

    <section class="ui-kpis eu-kpis" aria-label="Request summary">
        @foreach($kpis as [$status, $label, $value, $foot, $icon, $tone])
            <a class="eu-kpi eu-kpi--{{ $tone }}" href="{{ route('end-user.requests.index', ['status' => $status]) }}">
                <span class="eu-kpi__icon" aria-hidden="true"><i class="fas {{ $icon }}"></i></span>
                <span class="eu-kpi__body">
                    <span class="eu-kpi__label">{{ $label }}</span>
                    <span class="eu-kpi__value">{{ $value }}</span>
                    <span class="eu-kpi__foot">{{ $foot }}</span>
                </span>
            </a>
        @endforeach
    </section>

    @if($needsAction->isEmpty())
        <p class="eu-allclear"><i class="fas fa-circle-check" aria-hidden="true"></i> <strong>Nothing needs your action.</strong> <span>Drafts and requests returned by the reviewer will show here.</span></p>
    @else
        <section class="ui-card" aria-labelledby="eu-action-title">
            <header class="ui-card__head">
                <div>
                    <h2 class="ui-card__title" id="eu-action-title">Needs your action · {{ $needsAction->count() }}</h2>
                    <p class="ui-card__desc">Drafts to complete and requests returned by the reviewer.</p>
                </div>
            </header>
            <ul class="ui-feed">
                @foreach($needsAction as $item)
                    <li class="ui-feed__item ui-feed__item--{{ $item->status === 'returned' ? 'danger' : 'info' }}">
                        <span class="ui-feed__dot" aria-hidden="true"></span>
                        <div class="ui-row">
                            <div class="ui-row__main">
                                <p class="ui-feed__title"><a class="ui-link" href="{{ route('end-user.requests.show', $item) }}">{{ $item->title }}</a></p>
                                <p class="ui-feed__detail"><span class="ui-mono">{{ $item->reference_no }}</span> · <span class="ui-badge ui-badge--{{ $item->statusTone() }}">{{ $item->statusLabel() }}</span></p>
                                @if($item->status === 'returned' && $item->review_remarks)
                                    <p class="ui-feed__detail">Reviewer: {{ $item->review_remarks }}</p>
                                @endif
                            </div>
                            <a class="ui-btn ui-btn--secondary ui-btn--sm" href="{{ route('end-user.requests.edit', $item) }}">{{ $item->status === 'returned' ? 'Correct' : 'Continue' }}<span class="sr-only"> {{ $item->reference_no }}</span></a>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="ui-grid ui-grid--sidebar">
        @include('partials.portal.register', ['routeName' => 'end-user.dashboard', 'title' => 'My requests and procurements', 'showOffice' => false, 'progress' => true])

        <div class="eu-side">
            <section class="ui-card" aria-labelledby="eu-budget-title">
                <header class="ui-card__head">
                    <div>
                        <h2 class="ui-card__title" id="eu-budget-title">Budget · {{ $budget['year'] }}</h2>
                        <p class="ui-card__desc">Your purchase requests this year.</p>
                    </div>
                </header>
                <dl class="eu-budget">
                    <div><dt>Requested</dt><dd>{{ $peso($budget['requested']) }}</dd></div>
                    <div><dt>In procurement</dt><dd>{{ $peso($budget['in_procurement']) }}</dd></div>
                    <div><dt>Awarded</dt><dd>{{ $peso($budget['awarded']) }}</dd></div>
                    <div class="eu-budget__savings"><dt>Savings vs ABC</dt><dd>{{ $peso($budget['savings']) }}</dd></div>
                </dl>
            </section>

            @include('partials.portal.upcoming', ['upcomingTitle' => 'Coming up for your requests'])

            <section class="ui-card" aria-labelledby="eu-activity-title">
                <header class="ui-card__head">
                    <div>
                        <h2 class="ui-card__title" id="eu-activity-title">Recent activity</h2>
                    </div>
                    <a href="{{ route('end-user.notifications') }}" class="ui-link eu-side__more">View all</a>
                </header>
                @if($activity->isEmpty())
                    <p class="eu-empty-line"><i class="fas fa-clock-rotate-left" aria-hidden="true"></i> Updates on your requests will show here.</p>
                @else
                    <ol class="eu-activity">
                        @foreach($activity as $event)
                            @php $look = SystemNotification::presentation((string) $event->title, (string) $event->message, (string) $event->type); @endphp
                            <li class="eu-activity__item eu-activity__item--{{ $look['tone'] ?? 'neutral' }}">
                                <span class="eu-activity__icon" aria-hidden="true"><i class="fas {{ $look['icon'] ?? 'fa-bell' }}"></i></span>
                                <span class="eu-activity__text">
                                    @php $eventUrl = data_get($event->data, 'url'); @endphp
                                    @if($eventUrl)
                                        <a href="{{ $eventUrl }}" class="eu-activity__title">{{ $event->title }}</a>
                                    @else
                                        <span class="eu-activity__title">{{ $event->title }}</span>
                                    @endif
                                    <span class="eu-activity__detail">{{ \Illuminate\Support\Str::limit((string) $event->message, 90) }}</span>
                                    <time class="eu-activity__time" datetime="{{ $event->created_at?->toIso8601String() }}">{{ $event->created_at?->diffForHumans() }}</time>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>
    </div>
@endsection

@push('head')
<style>
    /* Welcome card: first visit after signing in (ProcurementRequestController::dashboard). */
    .eu-welcome { position: relative; display: grid; grid-template-columns: auto minmax(0, 1fr) minmax(0, 320px); align-items: center; gap: 16px; padding: 18px 52px 18px 20px; border: 1px solid var(--ui-primary-line); border-radius: var(--ui-radius-lg); background: linear-gradient(120deg, var(--ui-primary-soft), var(--ui-surface) 70%); animation: eu-in .45s cubic-bezier(.2, .8, .2, 1) both; }
    .eu-welcome.is-leaving { animation: eu-out .25s ease-in forwards; }
    .eu-welcome__icon { display: grid; width: 44px; height: 44px; place-items: center; border-radius: 12px; background: var(--ui-primary); color: #f6d58b; font-size: 18px; }
    .eu-welcome__eyebrow { margin: 0; color: var(--ui-primary); font-size: 11.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .eu-welcome__title { margin: 1px 0 0; color: var(--ui-ink); font-size: 18px; font-weight: 700; letter-spacing: -.01em; overflow-wrap: anywhere; }
    .eu-welcome__meta { margin: 2px 0 0; color: var(--ui-muted); font-size: 13px; }
    .eu-welcome__summary { margin: 0; padding-left: 16px; border-left: 1px solid var(--ui-primary-line); color: var(--ui-ink-2); font-size: 13px; line-height: 1.5; }
    .eu-welcome__close { position: absolute; top: 10px; right: 10px; display: grid; width: 30px; height: 30px; place-items: center; border: 0; border-radius: 8px; background: transparent; color: var(--ui-muted); cursor: pointer; }
    .eu-welcome__close:hover { background: var(--ui-surface); color: var(--ui-ink); }

    /* Stat cards with an icon; each opens the matching requests. */
    .eu-kpi { display: flex; align-items: center; gap: 12px; min-width: 0; padding: 14px 16px; border: 1px solid var(--ui-line); border-radius: var(--ui-radius-lg); background: var(--ui-surface); color: inherit; text-decoration: none; transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease; }
    .eu-kpi:hover { border-color: var(--ui-line-strong); box-shadow: 0 6px 16px rgba(27, 36, 32, .06); transform: translateY(-1px); }
    .eu-kpi__icon { display: grid; flex: 0 0 40px; width: 40px; height: 40px; place-items: center; border-radius: 11px; font-size: 16px; }
    .eu-kpi--neutral .eu-kpi__icon { background: var(--ui-line-soft); color: var(--ui-ink-2); }
    .eu-kpi--info .eu-kpi__icon { background: var(--ui-info-soft); color: var(--ui-info); }
    .eu-kpi--success .eu-kpi__icon { background: var(--ui-success-soft); color: var(--ui-success); }
    .eu-kpi--warning { border-color: var(--ui-warning-line); background: var(--ui-warning-soft); }
    .eu-kpi--warning .eu-kpi__icon { background: var(--ui-warning); color: #fff; }
    .eu-kpi__body { display: grid; min-width: 0; }
    .eu-kpi__label { overflow: hidden; color: var(--ui-muted); font-size: 12px; font-weight: 500; text-overflow: ellipsis; white-space: nowrap; }
    .eu-kpi__value { color: var(--ui-ink); font-size: 24px; font-weight: 700; line-height: 1.2; font-variant-numeric: tabular-nums; }
    .eu-kpi__foot { overflow: hidden; color: var(--ui-subtle); font-size: 11.5px; text-overflow: ellipsis; white-space: nowrap; }

    /* "Needs your action" when nothing does: one line, not a card. */
    .eu-allclear { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; margin: 0; padding: 11px 14px; border: 1px solid var(--ui-success-line); border-radius: var(--ui-radius); background: var(--ui-success-soft); color: var(--ui-success); font-size: 13px; }
    .eu-allclear span { color: var(--ui-ink-2); }

    .eu-side { display: grid; align-content: start; gap: 18px; min-width: 0; }
    .eu-side__more { font-size: 12.5px; font-weight: 600; }

    .eu-budget { display: grid; gap: 0; margin: 0; padding: 4px 18px 12px; }
    .eu-budget div { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 9px 0; border-bottom: 1px solid var(--ui-line-soft); }
    .eu-budget div:last-child { border-bottom: 0; }
    .eu-budget dt { color: var(--ui-muted); font-size: 13px; }
    .eu-budget dd { margin: 0; color: var(--ui-ink); font-size: 14px; font-weight: 600; font-variant-numeric: tabular-nums; }
    .eu-budget__savings dd { color: var(--ui-success); }

    .eu-activity { display: grid; gap: 0; margin: 0; padding: 4px 18px 12px; list-style: none; }
    .eu-activity__item { display: flex; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--ui-line-soft); }
    .eu-activity__item:last-child { border-bottom: 0; }
    .eu-activity__icon { display: grid; flex: 0 0 28px; width: 28px; height: 28px; place-items: center; border-radius: 8px; background: var(--ui-line-soft); color: var(--ui-ink-2); font-size: 12px; }
    .eu-activity__item--success .eu-activity__icon { background: var(--ui-success-soft); color: var(--ui-success); }
    .eu-activity__item--warning .eu-activity__icon { background: var(--ui-warning-soft); color: var(--ui-warning); }
    .eu-activity__item--danger .eu-activity__icon { background: var(--ui-danger-soft); color: var(--ui-danger); }
    .eu-activity__item--info .eu-activity__icon { background: var(--ui-info-soft); color: var(--ui-info); }
    .eu-activity__text { display: grid; gap: 1px; min-width: 0; }
    .eu-activity__title { color: var(--ui-ink); font-size: 13px; font-weight: 600; text-decoration: none; }
    a.eu-activity__title:hover { color: var(--ui-primary); text-decoration: underline; }
    .eu-activity__detail { color: var(--ui-muted); font-size: 12.5px; line-height: 1.4; overflow-wrap: anywhere; }
    .eu-activity__time { color: var(--ui-subtle); font-size: 11.5px; }
    .eu-empty-line { display: flex; align-items: center; gap: 8px; margin: 0; padding: 8px 18px 16px; color: var(--ui-muted); font-size: 13px; }

    @keyframes eu-in { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: none; } }
    @keyframes eu-out { to { opacity: 0; transform: translateY(-6px); } }

    @media (max-width: 1100px) {
        .eu-welcome { grid-template-columns: auto minmax(0, 1fr); }
        .eu-welcome__summary { grid-column: 1 / -1; padding: 10px 0 0; border-top: 1px solid var(--ui-primary-line); border-left: 0; }
    }
    @media (max-width: 768px) {
        .eu-welcome { padding: 16px 44px 16px 16px; }
        .eu-kpi { flex-direction: column; align-items: flex-start; gap: 8px; padding: 12px; }
        .eu-kpi__icon { flex-basis: auto; width: 34px; height: 34px; font-size: 14px; }
        .eu-kpi__label { white-space: normal; }
        .eu-kpi__foot { display: none; }
    }
    @media (prefers-reduced-motion: reduce) {
        .eu-welcome, .eu-welcome.is-leaving { animation: none; }
        .eu-kpi { transition: none; }
    }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        const card = document.querySelector('[data-eu-welcome]');
        if (!card) return;
        card.querySelector('[data-eu-welcome-close]').addEventListener('click', function () {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { card.remove(); return; }
            card.classList.add('is-leaving');
            card.addEventListener('animationend', function () { card.remove(); }, { once: true });
        });
    })();
</script>
@endpush
