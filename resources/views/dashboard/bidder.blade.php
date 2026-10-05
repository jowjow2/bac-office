@extends('layouts.portal')

@use('App\Support\Format')

@php
    $bidderName = $user->company ?: $user->name;
    $isApproved = $user->isApprovedBidder();
    $officialBids = $myBids->reject(fn ($bid) => $bid->isDraft());
    $toneMap = ['active' => 'info', 'muted' => 'neutral', 'success' => 'success', 'warning' => 'warning', 'danger' => 'danger'];
    $reviewLabel = match ($reviewStatus ?? 'new') {
        'under_review' => 'Under review',
        'needs_action' => 'Needs action',
        'for_re_evaluation' => 'For re-evaluation',
        default => 'Submitted — waiting for review',
    };
@endphp

@section('title', $isApproved ? 'Bidding overview' : 'Registration status')
@section('subtitle', $bidderName.' · Supplier portal of the '.config('bac-office.procuring_entity').' BAC')

@section('actions')
    @if($isApproved)
        <a href="{{ route('bidder.my-bids') }}" class="ui-btn ui-btn--secondary">My bids &amp; quotations</a>
        <a href="{{ route('bidder.available-projects') }}" class="ui-btn ui-btn--primary"><i class="fas fa-bullhorn" aria-hidden="true"></i> Browse opportunities</a>
    @else
        <a href="{{ route('bidder.company-profile') }}" class="ui-btn ui-btn--primary"><i class="fas fa-folder-open" aria-hidden="true"></i> Registration documents</a>
    @endif
@endsection

@section('content')
    @if($welcome ?? false)
        @include('bidder.partials.welcome-card')
    @endif

    @if(! $isApproved)
        <section class="ui-callout {{ ($reviewStatus ?? null) === 'needs_action' ? 'ui-callout--warning' : '' }}" aria-labelledby="registration-title">
            <span class="ui-callout__label">Registration · {{ $reviewLabel }}</span>
            <h2 class="ui-callout__title" id="registration-title">The BAC is reviewing your registration</h2>
            <p class="ui-callout__text">Bidding opportunities and submissions open as soon as the BAC approves your eligibility documents. You can update your documents and message the BAC while you wait.</p>
            @if(($reviewStatus ?? null) === 'needs_action' && $user->bidderProfile?->review_message)
                <p class="ui-callout__text"><strong>Requested by the BAC:</strong> {{ $user->bidderProfile->review_message }}</p>
            @endif
        </section>

        <div class="ui-grid ui-grid--2">
            <section class="ui-card" aria-labelledby="req-title">
                <header class="ui-card__head">
                    <div>
                        <h2 class="ui-card__title" id="req-title">Registration requirements · {{ count($registrationDocuments ?? []) }} of {{ count($registrationRequirementOptions ?? []) }} uploaded</h2>
                        <p class="ui-card__desc">Required documents the BAC checks before approving you.</p>
                    </div>
                </header>
                <div class="ui-card__body">
                    <ul class="ui-checklist">
                        @foreach($registrationRequirementOptions ?? [] as $option)
                            @php $has = ($registrationDocuments ?? collect())->has($option['document_type']); @endphp
                            <li class="{{ $has ? 'is-met' : '' }}">
                                <i class="fas {{ $has ? 'fa-circle-check' : 'fa-circle' }}" aria-hidden="true"></i>
                                <span>{{ $option['label'] }} @if(! ($option['required'] ?? false))<span class="ui-optional">(optional)</span>@endif</span>
                                <span class="ui-pill ui-pill--{{ $has ? 'success' : (($option['required'] ?? false) ? 'warning' : 'neutral') }}">{{ $has ? 'Uploaded' : 'Missing' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="ui-card__foot">
                    <a href="{{ route('bidder.company-profile') }}" class="ui-btn ui-btn--primary">Update documents</a>
                </div>
            </section>

            <section class="ui-card" aria-labelledby="how-title">
                <header class="ui-card__head">
                    <h2 class="ui-card__title" id="how-title">How bidding works here</h2>
                </header>
                <div class="ui-card__body">
                    <ol class="ui-timeline">
                        @foreach([
                            ['Register and get approved', 'Upload your PhilGEPS registration and eligibility documents.'],
                            ['Find an opportunity', 'Read the Invitation to Bid or Request for Quotation and download the documents.'],
                            ['Pay the bidding documents fee, if the notice requires one', 'Pay at the BAC Secretariat; the Official Receipt is recorded before you submit.'],
                            ['Prepare the requirements', 'Eligibility and technical documents, bid security when required, and your price.'],
                            ['Submit before the deadline', 'Online through this portal or sealed at the BAC office, as the notice states.'],
                            ['Follow the evaluation', 'Opening, evaluation, post-qualification, BAC resolution and HoPE approval.'],
                        ] as $index => [$step, $hint])
                            <li class="ui-timeline__item {{ $index === 0 ? 'is-current' : 'is-upcoming' }}">
                                <span class="ui-timeline__marker" aria-hidden="true">{{ $index + 1 }}</span>
                                <div>
                                    <p class="ui-timeline__title">{{ $step }}</p>
                                    <p class="ui-timeline__meta">{{ $hint }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>
        </div>
    @else
        @if($awardedProjects->isNotEmpty())
            @php $latestAward = $awardedProjects->first(); @endphp
            <section class="ui-callout ui-callout--success" id="bidderAwardBanner" data-award-id="{{ $latestAward->id }}" hidden aria-live="polite">
                <span class="ui-callout__label">Contract awarded</span>
                <div class="ui-row">
                    <div class="ui-row__main">
                        <h2 class="ui-callout__title">{{ $latestAward->project->title ?? 'Awarded project' }}</h2>
                        <p class="ui-callout__text">Contract amount {{ Format::peso($latestAward->contract_amount) }}. See the Notice of Award and next steps under Awarded contracts.</p>
                    </div>
                    <span class="ui-actions">
                        <a href="{{ route('bidder.awarded-contracts') }}" class="ui-btn ui-btn--success ui-btn--sm">View contract</a>
                        <button type="button" class="ui-btn ui-btn--ghost ui-btn--sm" id="bidderAwardBannerClose">Dismiss</button>
                    </span>
                </div>
            </section>
        @endif

        <section class="ui-kpis" aria-label="Summary">
            <a href="{{ route('bidder.available-projects') }}" class="ui-kpi">
                <span class="ui-kpi__label">Open opportunities</span>
                <span class="ui-kpi__value">{{ $opportunities->count() }}</span>
                <span class="ui-kpi__foot">Invitations to Bid and RFQs accepting submissions</span>
            </a>
            <a href="{{ route('bidder.my-bids') }}" class="ui-kpi">
                <span class="ui-kpi__label">My submissions</span>
                <span class="ui-kpi__value">{{ $officialBids->count() }}</span>
                <span class="ui-kpi__foot">Official bids and quotations received by the BAC</span>
            </a>
            <a href="{{ route('bidder.bidding-track') }}" class="ui-kpi">
                <span class="ui-kpi__label">Awaiting results</span>
                <span class="ui-kpi__value">{{ $awaitingResults }}</span>
                <span class="ui-kpi__foot">In opening, evaluation or award</span>
            </a>
            <a href="{{ route('bidder.awarded-contracts') }}" class="ui-kpi">
                <span class="ui-kpi__label">Contracts won</span>
                <span class="ui-kpi__value">{{ $awardedProjects->count() }}</span>
                <span class="ui-kpi__foot">{{ Format::pesoShort($awardedProjects->sum('contract_amount')) }} total contract amount</span>
            </a>
        </section>

        <div class="ui-grid ui-grid--sidebar">
            <section class="ui-card" aria-labelledby="open-title">
                <header class="ui-card__head">
                    <div>
                        <h2 class="ui-card__title" id="open-title">Open opportunities · {{ $opportunities->count() }}</h2>
                        <p class="ui-card__desc">Closest deadline first. Open one to see its requirements, fee, bid security and how to submit.</p>
                    </div>
                    <a href="{{ route('bidder.available-projects') }}" class="ui-link">All opportunities</a>
                </header>
                @if($opportunities->isEmpty())
                    <div class="ui-empty">
                        <i class="fas fa-bullhorn" aria-hidden="true"></i>
                        <strong>No open opportunities right now</strong>
                        <span>New Invitations to Bid and RFQs appear here once posted.</span>
                    </div>
                @else
                    <div class="ui-table-wrap">
                        <table class="ui-table ui-table--stack">
                            <thead>
                                <tr>
                                    <th scope="col">Reference</th>
                                    <th scope="col">Opportunity</th>
                                    <th scope="col">Mode</th>
                                    <th scope="col" class="is-num">ABC (₱)</th>
                                    <th scope="col">Deadline</th>
                                    <th scope="col">Your status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($opportunities->take(10) as $project)
                                    @php
                                        $mode = $project->mode();
                                        $deadline = $project->bidSubmissionDeadline();
                                        $bid = $myBids->firstWhere('project_id', $project->id);
                                        $days = $deadline ? (int) now()->startOfDay()->diffInDays($deadline->copy()->startOfDay()) : null;
                                    @endphp
                                    <tr>
                                        <td data-label="Reference"><a href="{{ route('bidder.opportunities.show', $project) }}" class="ui-ref">{{ $project->reference_no ?: 'Project #'.$project->id }}</a></td>
                                        <td data-label="Opportunity">
                                            <span class="ui-cell-title">{{ $project->title }}</span>
                                            <span class="ui-cell-sub">{{ $project->end_user_unit ?: 'End-user office not stated' }} · {{ $project->requiresBiddingFee() ? 'Documents fee '.Format::peso($project->bidding_documents_fee) : 'No documents fee' }}</span>
                                        </td>
                                        <td data-label="Mode"><span class="ui-mode ui-mode--{{ $mode->family() }}">{{ $mode->shortLabel() }}</span></td>
                                        <td data-label="ABC (₱)" class="is-num">{{ number_format((float) $project->budget, 2) }}</td>
                                        <td data-label="Deadline" class="is-nowrap">
                                            {{ Format::date($deadline, true) }}
                                            @if($days !== null)<span class="ui-cell-sub">{{ $days === 0 ? 'Today' : 'In '.$days.' '.\Illuminate\Support\Str::plural('day', $days) }}</span>@endif
                                        </td>
                                        <td data-label="Your status">
                                            @if($bid && ! $bid->isDraft())
                                                <span class="ui-pill ui-pill--success">Submitted</span>
                                            @elseif($bid)
                                                <span class="ui-pill ui-pill--warning">Draft saved — not submitted</span>
                                            @else
                                                <span class="ui-pill ui-pill--neutral">Not submitted</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            @include('partials.portal.upcoming', ['upcomingTitle' => 'Your deadlines · next 21 days'])
        </div>

        <section class="ui-card" aria-labelledby="mine-title">
            <header class="ui-card__head">
                <div>
                    <h2 class="ui-card__title" id="mine-title">My bids and quotations</h2>
                    <p class="ui-card__desc">The status the BAC has recorded for each submission.</p>
                </div>
                <a href="{{ route('bidder.bidding-track') }}" class="ui-link">Track evaluation</a>
            </header>
            @if($myBids->isEmpty())
                <div class="ui-empty">
                    <i class="fas fa-envelope-circle-check" aria-hidden="true"></i>
                    <span>You have not submitted a bid or quotation yet.</span>
                </div>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table ui-table--stack">
                        <thead>
                            <tr>
                                <th scope="col">Reference</th>
                                <th scope="col">Project</th>
                                <th scope="col">Mode</th>
                                <th scope="col">Submitted</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($myBids->take(8) as $bid)
                                @php $current = $bid->progress()->toArray()['current']; @endphp
                                <tr>
                                    <td data-label="Reference">
                                        @if($bid->project)
                                            <a href="{{ route('bidder.opportunities.show', $bid->project) }}" class="ui-ref">{{ $bid->project->reference_no ?: 'Project #'.$bid->project_id }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td data-label="Project"><span class="ui-cell-title">{{ $bid->project->title ?? 'Project removed' }}</span></td>
                                    <td data-label="Mode">@if($bid->project)<span class="ui-mode ui-mode--{{ $bid->project->mode()->family() }}">{{ $bid->project->mode()->shortLabel() }}</span>@endif</td>
                                    <td data-label="Submitted" class="is-nowrap">{{ $bid->isDraft() ? 'Not submitted' : Format::date($bid->submitted_at ?? $bid->created_at, true) }}</td>
                                    <td data-label="Status"><span class="ui-pill ui-pill--{{ $toneMap[$current['tone']] ?? 'neutral' }}">{{ $current['label'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
@endsection

@push('scripts')
<script>
    (function () {
        var banner = document.getElementById('bidderAwardBanner');
        if (!banner) return;
        var key = 'bac_award_banner_dismissed_' + banner.dataset.awardId;
        try { if (localStorage.getItem(key) === '1') return; } catch (e) {}
        banner.hidden = false;
        document.getElementById('bidderAwardBannerClose')?.addEventListener('click', function () {
            banner.hidden = true;
            try { localStorage.setItem(key, '1'); } catch (e) {}
        });
    })();
</script>
@endpush
