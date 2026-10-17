@extends('layouts.portal')

@use('App\Support\Format')
@use('App\Models\ContractImplementation', 'CI')

@php
    $tz = config('bac-office.display_timezone');
    $ciWorkflow = app(\App\Support\ContractImplementationWorkflow::class);
    $awardsInForce = $awardedProjects->reject(fn ($award) => $award->isCancelled());
    $tracked = $awardedProjects->filter(fn ($award) => $ciWorkflow->eligible($award) && $award->contractImplementation);
    $inImplementation = $tracked->reject(fn ($award) => $award->contractImplementation->status === CI::COMPLETED);
    // Contracts waiting for this supplier: delivery terms recorded and a delivery or correction due.
    $needsDelivery = $tracked->filter(fn ($award) => $award->contractImplementation->isConfigured()
        && in_array($award->contractImplementation->status, [CI::FOR_DELIVERY, CI::FOR_CORRECTION], true));
    $awardedProjects = $awardedProjects->sortBy(fn ($award) => $award->isCancelled() ? 1 : 0)->values();
    $stateOf = fn ($award) => $award->isCancelled() ? 'cancelled'
        : (in_array($award->contractImplementation?->status, [CI::COMPLETED, CI::INFRA_COMPLETED], true) ? 'completed' : 'ongoing');
    $stateCounts = $awardedProjects->countBy($stateOf);
@endphp

@section('title', 'Awarded contracts')
@section('subtitle', 'Contracts you won, their award documents, and delivery through contract completion.')

@section('actions')
    <a href="{{ route('bidder.bidding-track') }}" class="ui-btn ui-btn--secondary"><i class="fas fa-route" aria-hidden="true"></i> Submission status</a>
@endsection

@push('head')
<style>
    .awc-list { display: grid; gap: 14px; }
    .awc { display: grid; grid-template-columns: minmax(0, 1fr); overflow: hidden; border: 1px solid var(--ui-line); border-radius: var(--ui-radius-lg); background: var(--ui-surface); }
    .awc.is-highlight { border-color: var(--ui-primary-line); box-shadow: 0 0 0 3px var(--ui-primary-soft); }
    .awc-main { display: grid; gap: 14px; min-width: 0; padding: 18px 20px; }
    .awc.is-cancelled { opacity: .7; }
    .awc-toolbar { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 10px 16px; margin-bottom: 12px; border-bottom: 1px solid var(--ui-line); }
    .awc-toolbar .ui-tabs { border-bottom: 0; }
    .awc-toolbar .ui-tab { border: 0; border-bottom: 2px solid transparent; background: none; font-family: inherit; cursor: pointer; }
    .awc-toolbar .ui-search { flex: 0 1 280px; margin: 8px 0; }
    .awc-kpi-filter { border: 1px solid var(--ui-line); font: inherit; text-align: left; cursor: pointer; }
    .awc-kpi-filter:disabled { cursor: default; }
    .awc-next { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 0; padding: 9px 12px; border-radius: 10px; background: var(--ui-surface-2, #f4f7f5); color: var(--ui-ink-2); font-size: 13px; }
    .awc-next.is-you { background: var(--ui-warning-soft, #fdf6e5); color: var(--ui-ink); font-weight: 600; }
    .awc-next.is-you > i { color: var(--ui-warning, #b7791f); }
    .awc-next.is-done { background: var(--ui-success-soft, #ecf7f0); color: var(--ui-success, #1f7a4d); }
    .awc-next > span { flex: 1 1 240px; }
    .awc-due { padding: 2px 9px; border-radius: 999px; background: #fff; color: var(--ui-ink-2); font-size: 12px; }
    .awc-due.is-soon { background: var(--ui-danger-soft, #fdecec); color: var(--ui-danger, #b42318); }
    .awc-docs { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; font-size: 13px; }
    .awc-docs-label { color: var(--ui-muted); font-size: 11.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .awc-doc { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border: 1px solid var(--ui-line); border-radius: 999px; background: #fff; }
    .awc-doc i { color: var(--ui-primary); }
    .awc-doc a { color: var(--ui-primary); font-weight: 600; }
    .awc-certno { display: block; margin-top: 2px; color: var(--ui-muted); font-size: 12px; }
    .awc-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
    .awc-title { margin: 0; color: var(--ui-ink); font-size: var(--ui-text-lg); font-weight: 700; line-height: 1.35; overflow-wrap: anywhere; }
    .awc-sub { display: flex; flex-wrap: wrap; gap: 4px 12px; margin: 4px 0 0; color: var(--ui-muted); font-size: var(--ui-text-sm); }
    .awc-amount { text-align: right; white-space: nowrap; }
    .awc-amount span { display: block; color: var(--ui-subtle); font-size: var(--ui-text-xs); font-weight: 600; }
    .awc-amount strong { color: var(--ui-success); font-size: 19px; font-variant-numeric: tabular-nums; }

    .awc-steps { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0; margin: 0; padding: 0; list-style: none; }
    .awc-steps li { position: relative; display: grid; gap: 2px; padding: 24px 12px 0 0; }
    .awc-steps li::before { content: ''; position: absolute; top: 0; left: 0; width: 16px; height: 16px; border: 2px solid var(--ui-line-strong); border-radius: 50%; background: var(--ui-surface); }
    .awc-steps li::after { content: ''; position: absolute; top: 7px; left: 22px; right: 6px; height: 2px; background: var(--ui-line); }
    .awc-steps li:last-child::after { display: none; }
    .awc-steps li.is-done::before { border-color: var(--ui-success); background: var(--ui-success); box-shadow: inset 0 0 0 3px var(--ui-surface); }
    .awc-steps li.is-done::after { background: var(--ui-success-line); }
    .awc-steps strong { color: var(--ui-ink); font-size: var(--ui-text-sm); }
    .awc-steps span { color: var(--ui-muted); font-size: var(--ui-text-xs); }
    .awc-steps li:not(.is-done) strong { color: var(--ui-muted); }

    .awc-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 16px; padding-top: 14px; border-top: 1px solid var(--ui-line-soft); }
    .awc-foot .ci-open { margin-top: 0; }
    .awc-notes { margin: 0; color: var(--ui-muted); font-size: var(--ui-text-sm); line-height: 1.5; }
    .awc-wait { display: inline-flex; align-items: center; gap: 7px; color: var(--ui-subtle); font-size: var(--ui-text-sm); }

    .awc-cert { display: grid; align-content: center; justify-items: center; gap: 8px; padding: 18px 16px; border-left: 1px solid var(--ui-line); background: var(--ui-page); text-align: center; }
    .awc-cert a.awc-qr { display: block; padding: 8px; border: 1px solid var(--ui-line); border-radius: var(--ui-radius); background: #fff; }
    .awc-cert img { display: block; width: 112px; height: 112px; }
    .awc-cert strong { color: var(--ui-ink); font-family: var(--ui-mono); font-size: var(--ui-text-xs); overflow-wrap: anywhere; }
    .awc-cert .ui-link { font-size: var(--ui-text-xs); font-weight: 600; }
    .awc-cert-pending { display: grid; justify-items: center; gap: 6px; color: var(--ui-subtle); font-size: var(--ui-text-sm); }
    .awc-cert-pending i { font-size: 26px; color: var(--ui-line-strong); }

    .bidder-infra-modal {
        position: fixed !important;
        inset: auto !important;
        top: 50% !important;
        left: 50% !important;
        width: min(1380px, calc(100vw - 32px)) !important;
        height: min(920px, calc(100dvh - 32px)) !important;
        max-width: calc(100vw - 32px) !important;
        max-height: calc(100dvh - 32px) !important;
        margin: 0 !important;
        padding: 0;
        overflow: hidden;
        border: 1px solid #d8e3dc;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 26px 70px rgba(15, 38, 31, .28);
        transform: translate(-50%, -50%) !important;
    }
    .bidder-infra-modal::backdrop { background: rgba(9, 28, 22, .62); backdrop-filter: blur(3px); }
    .bidder-infra-modal__shell { display: grid; grid-template-rows: auto minmax(0, 1fr); height: 100%; }
    .bidder-infra-modal__head { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 20px; border-bottom: 1px solid #e0e8e3; background: #fff; }
    .bidder-infra-modal__head span { display: block; color: #5f766b; font-size: 11px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; }
    .bidder-infra-modal__head h2 { margin: 3px 0 0; color: #17372b; font-size: 18px; line-height: 1.3; }
    .bidder-infra-modal__close { display: grid; width: 38px; height: 38px; flex: 0 0 38px; place-items: center; border: 1px solid #d8e3dc; border-radius: 9px; background: #fff; color: #263a31; cursor: pointer; }
    .bidder-infra-modal__close:hover { background: #eef5f0; }
    .bidder-infra-modal iframe { display: block; width: 100%; height: 100%; border: 0; background: #fff; }

    @media (max-width: 760px) {
        .awc { grid-template-columns: 1fr; }
        .awc-cert { grid-template-columns: auto 1fr; justify-items: start; text-align: left; border-top: 1px solid var(--ui-line); border-left: 0; }
        .awc-cert a.awc-qr { grid-row: span 3; }
        .awc-cert img { width: 84px; height: 84px; }
        .awc-head { flex-direction: column; }
        .awc-amount { text-align: left; }
        .awc-steps { grid-template-columns: 1fr; gap: 10px; }
        .awc-steps li { padding: 0 0 0 26px; }
        .awc-steps li::before { top: 2px; }
        .awc-steps li::after { top: 22px; bottom: -8px; left: 7px; right: auto; width: 2px; height: auto; }
    }
    @media (max-width: 640px) {
        .bidder-infra-modal { width: 100vw !important; height: 100dvh !important; max-width: 100vw !important; max-height: 100dvh !important; border: 0; border-radius: 0; }
    }
</style>
@endpush

@section('content')
    <section class="ui-kpis" aria-label="Summary">
        <div class="ui-kpi">
            <span class="ui-kpi__label">Contracts won</span>
            <span class="ui-kpi__value">{{ $awardsInForce->count() }}</span>
            <span class="ui-kpi__foot">{{ Format::peso($awardsInForce->sum(fn ($award) => (float) $award->contract_amount)) }} total contract amount</span>
        </div>
        <button type="button" class="ui-kpi awc-kpi-filter" data-awc-filter="implementation" @disabled($inImplementation->isEmpty())>
            <span class="ui-kpi__label">In implementation</span>
            <span class="ui-kpi__value">{{ $inImplementation->count() }}</span>
            <span class="ui-kpi__foot">{{ $inImplementation->isNotEmpty() ? 'Show them' : 'Delivery, inspection or payment in progress' }}</span>
        </button>
        <button type="button" class="ui-kpi awc-kpi-filter {{ $needsDelivery->isNotEmpty() ? 'ui-kpi--warning' : '' }}" data-awc-filter="delivery" @disabled($needsDelivery->isEmpty())>
            <span class="ui-kpi__label">Needs your delivery</span>
            <span class="ui-kpi__value">{{ $needsDelivery->count() }}</span>
            <span class="ui-kpi__foot">{{ $needsDelivery->isNotEmpty() ? 'Show them' : 'Deliveries or corrections to submit' }}</span>
        </button>
        <a href="{{ route('bidder.bidding-track') }}" class="ui-kpi">
            <span class="ui-kpi__label">Awaiting award</span>
            <span class="ui-kpi__value">{{ $awaitingAwardBids->count() }}</span>
            <span class="ui-kpi__foot">Closed projects the BAC is still deciding</span>
        </a>
    </section>

    @if($needsDelivery->isNotEmpty())
        <section class="ui-callout ui-callout--warning" aria-labelledby="needs-delivery-title">
            <span class="ui-callout__label">Action needed</span>
            <h2 class="ui-callout__title" id="needs-delivery-title">{{ $needsDelivery->count() === 1 ? 'A contract is waiting for your delivery' : $needsDelivery->count().' contracts are waiting for your delivery' }}</h2>
            <p class="ui-callout__text">Submit the delivery details and your delivery receipt so the LGU can receive and inspect the goods.</p>
            <div class="ui-actions">
                @foreach($needsDelivery as $award)
                    <button type="button" class="ui-btn ui-btn--primary ui-btn--sm" data-ci-delivery="{{ $award->id }}" aria-haspopup="dialog">
                        <i class="fas fa-truck" aria-hidden="true"></i>
                        {{ $award->contractImplementation->status === CI::FOR_CORRECTION ? 'Submit correction' : 'Submit delivery' }}: {{ \Illuminate\Support\Str::limit($award->project?->title ?? 'Contract', 40) }}
                    </button>
                @endforeach
            </div>
        </section>
    @endif

    <section aria-labelledby="contracts-title">
        <div class="ui-section-head" style="display:flex;align-items:baseline;gap:10px;margin:4px 0 12px">
            <h2 class="ui-card__title" id="contracts-title">Awarded contracts</h2>
            <span class="ui-cell-sub">{{ $awardedProjects->count() }} {{ \Illuminate\Support\Str::plural('contract', $awardedProjects->count()) }}</span>
        </div>

        @if($awardedProjects->isEmpty())
            <div class="ui-card">
                <div class="ui-empty">
                    <i class="fas fa-award" aria-hidden="true"></i>
                    <strong>No awarded contracts yet</strong>
                    <span>Contracts appear here once the BAC issues you a Notice of Award.</span>
                    <a href="{{ route('bidder.available-projects') }}" class="ui-btn ui-btn--secondary ui-btn--sm">Browse opportunities</a>
                </div>
            </div>
        @else
            <div class="awc-toolbar">
                <nav class="ui-tabs" aria-label="Filter contracts">
                    <button type="button" class="ui-tab" data-awc-filter="all" aria-current="page">All <span class="ui-tab__count">{{ $awardedProjects->count() }}</span></button>
                    <button type="button" class="ui-tab" data-awc-filter="ongoing">Ongoing <span class="ui-tab__count">{{ $stateCounts['ongoing'] ?? 0 }}</span></button>
                    <button type="button" class="ui-tab" data-awc-filter="completed">Completed <span class="ui-tab__count">{{ $stateCounts['completed'] ?? 0 }}</span></button>
                    <button type="button" class="ui-tab" data-awc-filter="cancelled">Cancelled <span class="ui-tab__count">{{ $stateCounts['cancelled'] ?? 0 }}</span></button>
                </nav>
                <label class="ui-search">
                    <span class="sr-only">Search contracts</span>
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" class="ui-input" id="awcSearch" placeholder="Search project or reference no." autocomplete="off">
                </label>
            </div>
            <div class="awc-list">
                @foreach($awardedProjects as $award)
                    @php
                        $project = $award->project;
                        $bid = $award->bid;
                        $approvedAt = $award->award_approved_at ?? $bid?->award_decision_at;
                        $noaAt = $award->awaitsNoticeOfAward() ? null : $award->awardDate();
                        $signedAt = $bid?->contract_signed_at ?? $award->contract_date;
                        $ntpAt = $bid?->notice_to_proceed_at;
                        $hasCertificate = $award->hasCertificateFile() && filled($award->qr_token) && ! $award->isCancelled();
                        $isTracked = $ciWorkflow->eligible($award) && $award->contractImplementation;
                        $criterion = $project?->award_criterion ? (\App\Models\Project::AWARD_CRITERIA[$project->award_criterion] ?? null) : null;
                        $ci = $award->contractImplementation;
                        $state = $stateOf($award);
                        $waitsForDelivery = $needsDelivery->contains('id', $award->id);
                        // Who holds the next step, in one sentence.
                        $next = match (true) {
                            $award->isCancelled() => null,
                            $state === 'completed' => ['done', 'Contract completed. Thank you.'],
                            ! $noaAt => ['bac', 'Waiting for the BAC: the Notice of Award is being prepared.'],
                            ! $signedAt => ['you', 'Your turn: sign the contract at the BAC office and bring the performance security.'],
                            ! $ntpAt => ['bac', 'Waiting for the BAC: the Notice to Proceed is being prepared.'],
                            $waitsForDelivery => ['you', $ci?->status === CI::FOR_CORRECTION ? 'Your turn: send the correction the LGU asked for.' : 'Your turn: deliver and submit the delivery details and receipt.'],
                            $ci !== null => ['bac', 'The LGU is receiving, inspecting or paying for the delivery.'],
                            default => ['bac', 'Waiting for the BAC to record the contract terms.'],
                        };
                        // The delivery date in force, while it still matters.
                        $dueOn = $ci && ! $ci->accepted_on && ! in_array($ci->status, [CI::COMPLETED, CI::INFRA_COMPLETED], true) ? $ci->effectiveDeadline() : null;
                        $dueDays = $dueOn ? (int) now($tz)->startOfDay()->diffInDays($dueOn->copy()->startOfDay(), false) : null;
                        // Every document of the award in one row.
                        $documents = collect();
                        if ($hasCertificate) $documents->push(['Notice of Award certificate', $award->tokenCertificateUrl(), null]);
                        if ($ci?->signed_contract_file_path) $documents->push(['Signed contract', \App\Support\Uploads::url($ci->signed_contract_file_path), null]);
                        if ($award->hasPublishedNoticeToProceed()) $documents->push(['Notice to Proceed', $award->noticeToProceedUrl(), $award->noticeToProceedUrl(download: true)]);
                    @endphp
                    <article class="awc is-{{ $state }} {{ (string) request('award') === (string) $award->id ? 'is-highlight' : '' }}" data-awc-row data-state="{{ $state }}" data-impl="{{ $inImplementation->contains('id', $award->id) ? '1' : '0' }}" data-delivery="{{ $waitsForDelivery ? '1' : '0' }}" data-search="{{ \Illuminate\Support\Str::lower(($project?->title ?? '').' '.($project?->reference_no ?? '').' '.($award->certificate_number ?? '')) }}" id="award-{{ $award->id }}" aria-labelledby="award-{{ $award->id }}-title">
                        <div class="awc-main">
                            <div class="awc-head">
                                <div>
                                    <h3 class="awc-title" id="award-{{ $award->id }}-title">{{ $project?->title ?? 'Awarded contract' }}</h3>
                                    <p class="awc-sub">
                                        @if($project?->reference_no)<span class="ui-ref">{{ $project->reference_no }}</span>@endif
                                        @if($project)<span>{{ $project->mode()->label() }}</span>@endif
                                        <span>{{ $bid?->user?->company ?: $bid?->user?->name ?: 'Awardee' }}</span>
                                        @if($criterion)<span>{{ $criterion }}</span>@endif
                                    </p>
                                </div>
                                <div class="awc-amount">
                                    <span>Approved bid amount</span>
                                    <strong>{{ Format::peso($award->contract_amount) }}</strong>
                                    @if($hasCertificate)<small class="awc-certno">Certificate {{ $award->certificate_number }}</small>@elseif(! $award->isCancelled())<small class="awc-certno">Certificate pending</small>@endif
                                </div>
                            </div>

                            @if($next)
                                <p class="awc-next is-{{ $next[0] }}">
                                    <i class="fas {{ $next[0] === 'you' ? 'fa-hand-point-right' : ($next[0] === 'done' ? 'fa-circle-check' : 'fa-hourglass-half') }}" aria-hidden="true"></i>
                                    <span>{{ $next[1] }}</span>
                                    @if($dueOn)
                                        <strong class="awc-due {{ $dueDays !== null && $dueDays <= 3 ? 'is-soon' : '' }}">Delivery due {{ $dueOn->format('M d, Y') }} · {{ $dueDays < 0 ? abs($dueDays).' '.\Illuminate\Support\Str::plural('day', abs($dueDays)).' overdue' : ($dueDays === 0 ? 'today' : 'in '.$dueDays.' '.\Illuminate\Support\Str::plural('day', $dueDays)) }}</strong>
                                    @endif
                                </p>
                            @endif

                            @if($award->isCancelled())
                                <div class="ui-callout ui-callout--warning" role="status">
                                    <span class="ui-callout__label">Award cancelled {{ $award->cancelled_at->timezone($tz)->format('M d, Y') }}</span>
                                    <p class="ui-callout__text">{{ $award->cancellation_reason }} ({{ $award->cancellation_reference }})</p>
                                </div>
                            @else
                                <ol class="awc-steps" aria-label="Award milestones">
                                    <li class="{{ $approvedAt ? 'is-done' : '' }}">
                                        <strong>Award approved</strong>
                                        <span>{{ $approvedAt?->timezone($tz)->format('M d, Y') ?? 'Recorded' }}</span>
                                    </li>
                                    <li class="{{ $noaAt ? 'is-done' : '' }}">
                                        <strong>{{ $noaAt ? 'NOA issued' : 'Notice of Award' }}</strong>
                                        <span>{{ $noaAt?->format('M d, Y') ?? 'Being prepared by the BAC' }}</span>
                                    </li>
                                    <li class="{{ $signedAt ? 'is-done' : '' }}">
                                        <strong>{{ $signedAt ? 'Contract signed' : 'Contract signing' }}</strong>
                                        <span>{{ $signedAt ? $signedAt->timezone($tz)->format('M d, Y') : ($noaAt ? 'Contract signing pending (performance security required)' : 'After the Notice of Award') }}</span>
                                    </li>
                                    <li class="{{ $ntpAt ? 'is-done' : '' }}">
                                        <strong>{{ $ntpAt ? 'NTP issued' : 'Notice to Proceed' }}</strong>
                                        <span>{{ ($award->ntp_issued_on ?? $ntpAt?->timezone($tz))?->format('M d, Y') ?? ($signedAt ? 'Pending' : 'After contract signing') }}</span>
                                        @if($award->hasPublishedNoticeToProceed())
                                            <span>
                                                <a href="{{ $award->noticeToProceedUrl() }}" target="_blank" rel="noopener">View</a> ·
                                                <a href="{{ $award->noticeToProceedUrl(download: true) }}">Download</a>
                                                @if($award->ntp_received_on) · Received {{ $award->ntp_received_on->format('M d, Y') }}@endif
                                            </span>
                                        @endif
                                    </li>
                                </ol>
                            @endif

                            @if($documents->isNotEmpty())
                                <div class="awc-docs" aria-label="Award documents">
                                    <span class="awc-docs-label">Documents</span>
                                    @foreach($documents as [$label, $view, $download])
                                        <span class="awc-doc"><i class="fas fa-file-lines" aria-hidden="true"></i> {{ $label }}
                                            <a href="{{ $view }}" target="_blank" rel="noopener">View</a>@if($download) · <a href="{{ $download }}">Download</a>@endif
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="awc-foot">
                                @if($award->isCancelled())
                                    <span class="awc-wait"><i class="fas fa-ban" aria-hidden="true"></i> This award no longer proceeds to a contract</span>
                                @elseif(strtolower((string) $project?->category) === 'infrastructure' && $signedAt && $ntpAt)
                                    @include('partials.contract-implementation-button', ['award' => $award])
                                @elseif($isTracked)
                                    @include('partials.contract-implementation-button', ['award' => $award])
                                @else
                                    <span class="awc-wait"><i class="fas fa-truck" aria-hidden="true"></i> Delivery opens after the Notice to Proceed</span>
                                @endif
                                @if($award->notes)
                                    <p class="awc-notes"><strong>BAC notes:</strong> {{ $award->notes }}</p>
                                @endif
                            </div>
                        </div>

                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="ui-card" aria-labelledby="awaiting-title">
        <header class="ui-card__head">
            <div>
                <h2 class="ui-card__title" id="awaiting-title">Awaiting award result</h2>
                <p class="ui-card__desc">Closed projects you bid on while the BAC evaluates and decides the award.</p>
            </div>
        </header>
        @if($awaitingAwardBids->isEmpty())
            <div class="ui-empty">
                <i class="fas fa-hourglass-half" aria-hidden="true"></i>
                <strong>No projects awaiting award results</strong>
                <span>Closed projects you bid on will appear here while BAC finalizes the award.</span>
            </div>
        @else
            <div class="ui-table-wrap">
                <table class="ui-table ui-table--stack">
                    <thead>
                        <tr>
                            <th scope="col">Project</th>
                            <th scope="col" class="is-num">ABC (₱)</th>
                            <th scope="col" class="is-num">Bids received</th>
                            <th scope="col" class="is-num">Lowest opened bid (₱)</th>
                            <th scope="col" class="is-num">Your bid</th>
                            <th scope="col">Your status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($awaitingAwardBids as $bid)
                            @php
                                // Only official bids, and only prices already opened (bids that passed
                                // preliminary examination, read at the public bid opening).
                                $projectBids = $bid->project ? $bid->project->bids()->with(['project.awards', 'award'])->get()->filter->isOfficiallySubmitted() : collect();
                                $openedPrices = $projectBids->reject->isFinancialSealed()->map(fn ($projectBid) => (float) $projectBid->bid_amount);
                                $lowestBid = $openedPrices->isNotEmpty() ? $openedPrices->min() : null;
                                $myStage = $bid->progress()->toArray()['current']['label'];
                            @endphp
                            <tr>
                                <td data-label="Project">
                                    <span class="ui-cell-title">{{ $bid->project->title ?? 'N/A' }}</span>
                                    @if($bid->project?->reference_no)<span class="ui-cell-sub">{{ $bid->project->reference_no }}</span>@endif
                                </td>
                                <td data-label="ABC (₱)" class="is-num">{{ number_format((float) ($bid->project->budget ?? 0), 2) }}</td>
                                <td data-label="Bids received" class="is-num">{{ $projectBids->count() }}</td>
                                <td data-label="Lowest opened bid (₱)" class="is-num">{{ $lowestBid !== null ? number_format($lowestBid, 2) : '—' }}</td>
                                <td data-label="Your bid" class="is-num">
                                    @if($bid->isFinancialSealed())
                                        <span class="ui-muted">Sealed</span>
                                    @else
                                        {{ number_format((float) $bid->bid_amount, 2) }}
                                        @php $myPlace = $openedPrices->filter(fn ($price) => $price < (float) $bid->bid_amount)->count() + 1; @endphp
                                        <span class="ui-cell-sub">#{{ $myPlace }} of {{ $openedPrices->count() }} {{ $myPlace === 1 ? '(lowest)' : '' }}</span>
                                    @endif
                                </td>
                                <td data-label="Your status"><span class="ui-pill ui-pill--info"><i class="fas fa-hourglass-half" aria-hidden="true"></i> {{ $myStage }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @include('partials.contract-implementation-dialogs', ['awards' => $awardedProjects, 'viewerMode' => 'bidder'])

    <dialog id="infrastructureTrackingModal" class="bidder-infra-modal" aria-labelledby="infrastructureTrackingTitle">
        <div class="bidder-infra-modal__shell">
            <header class="bidder-infra-modal__head">
                <div><span>Awarded contracts</span><h2 id="infrastructureTrackingTitle">Infrastructure tracking</h2></div>
                <button type="button" class="bidder-infra-modal__close" data-infra-modal-close aria-label="Close infrastructure tracking"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </header>
            <iframe title="Infrastructure contract tracking" data-infra-modal-frame></iframe>
        </div>
    </dialog>
@endsection

@push('scripts')
<script>
    (() => {
        const rows = Array.from(document.querySelectorAll('[data-awc-row]'));
        const search = document.getElementById('awcSearch');
        let filter = 'all';
        const apply = () => {
            const term = (search ? search.value : '').trim().toLowerCase();
            rows.forEach((row) => {
                const inFilter = filter === 'all' || row.dataset.state === filter
                    || (filter === 'implementation' && row.dataset.impl === '1')
                    || (filter === 'delivery' && row.dataset.delivery === '1');
                row.hidden = !(inFilter && (!term || row.dataset.search.includes(term)));
            });
        };
        document.querySelectorAll('[data-awc-filter]').forEach((button) => button.addEventListener('click', () => {
            filter = button.dataset.awcFilter;
            document.querySelectorAll('.awc-toolbar [data-awc-filter]').forEach((tab) => {
                if (tab.dataset.awcFilter === filter) tab.setAttribute('aria-current', 'page'); else tab.removeAttribute('aria-current');
            });
            apply();
            if (!button.closest('.awc-toolbar')) document.querySelector('.awc-toolbar')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }));
        search?.addEventListener('input', apply);
    })();
</script>
<script>
    (() => {
        const dialog = document.getElementById('infrastructureTrackingModal');
        const frame = dialog?.querySelector('[data-infra-modal-frame]');
        if (!dialog || !frame) return;
        let opener = null;
        let updated = false;

        document.addEventListener('click', event => {
            const link = event.target.closest('a[data-infra-modal]');
            if (!link || typeof dialog.showModal !== 'function') return;
            event.preventDefault();
            opener = link;
            updated = false;
            const url = new URL(link.href, window.location.href);
            url.searchParams.set('embed', '1');
            frame.src = url.href;
            dialog.showModal();
            dialog.querySelector('[data-infra-modal-close]').focus();
        });

        dialog.querySelector('[data-infra-modal-close]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
        window.addEventListener('message', event => {
            if (event.origin === window.location.origin && event.source === frame.contentWindow
                && event.data?.type === 'sjbac:infrastructure-updated') updated = true;
        });
        dialog.addEventListener('close', () => {
            frame.src = 'about:blank';
            if (updated) window.location.reload();
            else if (opener?.isConnected) opener.focus();
        });
    })();
</script>
@endpush
