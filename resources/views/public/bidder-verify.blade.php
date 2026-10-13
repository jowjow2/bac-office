@extends('layouts.public')

@section('title', ($user->company ?: 'Registered Bidder') . ' - Bidding Record')
@section('body_class', 'public-page')

@section('content')
    @php
        $tz = config('bac-office.display_timezone', 'Asia/Manila');
        $totalValue = $awards->sum(fn ($award) => (float) $award->contract_amount);
    @endphp
    <main class="public-shell bidder-record">
        <section class="public-page-hero">
            <p class="public-page-kicker"><i class="fas fa-circle-check" aria-hidden="true"></i> Verified by SJBAC</p>
            <h1>{{ $user->company ?: 'Registered Bidder' }}</h1>
            <p>
                The procurement record of this bidder with the Bids and Awards Committee of San Jose, Occidental Mindoro:
                contracts it won and bids approved for award.
                @if($bidder->created_at) Registered since {{ $bidder->created_at->timezone($tz)->format('F Y') }}. @endif
            </p>
        </section>

        <section class="bidder-record-stats" aria-label="Summary">
            <div><span>Successful bids</span><strong>{{ $awards->count() }}</strong></div>
            <div><span>Total contract value</span><strong>&#8369;{{ number_format($totalValue, 2) }}</strong></div>
            <div><span>Approved for award</span><strong>{{ $approvedBids->count() }}</strong></div>
        </section>

        <section class="bidder-record-section">
            <h2>Successful bids</h2>
            @forelse($awards as $award)
                <article class="bidder-record-row">
                    <div>
                        <span class="public-status public-status-awarded">Awarded</span>
                        <h3>{{ $award->project->title ?? 'Untitled Project' }}</h3>
                        <p>
                            @if($award->project?->reference_no){{ $award->project->reference_no }} · @endif
                            Notice of Award {{ ($award->notice_of_award_date ?? $award->contract_date)?->format('M d, Y') ?? 'date not recorded' }}
                        </p>
                    </div>
                    <div class="bidder-record-amount">
                        <strong>&#8369;{{ number_format((float) $award->contract_amount, 2) }}</strong>
                        @if($award->hasCertificateFile() && $award->verificationUrl())
                            <a href="{{ $award->verificationUrl() }}">View award document</a>
                        @endif
                    </div>
                </article>
            @empty
                <p class="bidder-record-empty">No awarded contracts on record yet.</p>
            @endforelse
        </section>

        <section class="bidder-record-section">
            <h2>Approved for award</h2>
            @forelse($approvedBids as $bid)
                <article class="bidder-record-row">
                    <div>
                        <span class="public-status public-status-open">Approved</span>
                        <h3>{{ $bid->project->title ?? 'Untitled Project' }}</h3>
                        <p>
                            @if($bid->project?->reference_no){{ $bid->project->reference_no }} · @endif
                            Approved {{ ($bid->award_decision_at ?? $bid->updated_at)?->timezone($tz)->format('M d, Y') }} · Notice of Award pending
                        </p>
                    </div>
                    <div class="bidder-record-amount">
                        <small>Amount published once awarded</small>
                    </div>
                </article>
            @empty
                <p class="bidder-record-empty">No bids awaiting award.</p>
            @endforelse
        </section>
        @if($bacView)
            @php
                $bacBids = $bacView['bids'];
                $outcome = function ($bid) {
                    $award = $bid->award;
                    if ($award && $award->cancelled_at) return ['Award cancelled', 'danger'];
                    if ($award && in_array($award->contractImplementation?->status, [\App\Models\ContractImplementation::COMPLETED, \App\Models\ContractImplementation::INFRA_COMPLETED], true)) return ['Awarded · completed', 'success'];
                    if ($award) return ['Awarded', 'success'];
                    if ($bid->workflow_step === \App\Models\Bid::STEP_DISQUALIFIED) return ['Disqualified', 'danger'];
                    if ($bid->workflow_step === \App\Models\Bid::STEP_NOT_AWARDED) return ['Not awarded', 'muted'];
                    if ($bid->status === 'rejected') return ['Rejected', 'danger'];
                    if ($bid->status === 'approved') return ['Approved, awaiting award', 'info'];

                    return [$bid->workflow_step_label, 'info'];
                };
                $counts = ['awarded' => 0, 'lost' => 0, 'disqualified' => 0, 'active' => 0];
                foreach ($bacBids as $bid) {
                    [$label] = $outcome($bid);
                    if (str_starts_with($label, 'Awarded')) $counts['awarded']++;
                    elseif (in_array($label, ['Disqualified', 'Rejected', 'Award cancelled'], true)) $counts['disqualified']++;
                    elseif ($label === 'Not awarded') $counts['lost']++;
                    else $counts['active']++;
                }
            @endphp
            <section class="bidder-record-section bac-view" aria-label="BAC view">
                <div class="bac-view-head">
                    <h2><i class="fas fa-user-shield" aria-hidden="true"></i> BAC view</h2>
                    <p>Only you can see this because you are signed in as {{ $bacView['role'] === 'admin' ? 'BAC Admin' : 'BAC Secretariat' }}. Do not share this page.@if($bacView['limited']) The bids below are only those on the projects assigned to you.@endif</p>
                    @if($bacView['role'] === 'admin')
                        <a class="bac-view-link" href="{{ route('admin.users', ['review' => $user->id]) }}">Open the bidder account</a>
                    @endif
                </div>

                <div class="bidder-record-stats">
                    <div><span>{{ $bacView['limited'] ? 'Bids on your projects' : 'All bids submitted' }}</span><strong>{{ $bacBids->count() }}</strong></div>
                    <div><span>Awarded</span><strong>{{ $counts['awarded'] }}</strong></div>
                    <div><span>Not awarded</span><strong>{{ $counts['lost'] }}</strong></div>
                    <div><span>Disqualified or rejected</span><strong>{{ $counts['disqualified'] }}</strong></div>
                </div>

                <h3 class="bac-view-sub">Sanctions</h3>
                @forelse($bacView['sanctions'] as $sanction)
                    @php $isActive = $sanction->lifted_at === null && (! $sanction->end_date || $sanction->end_date->endOfDay()->isFuture()); @endphp
                    <article class="bidder-record-row">
                        <div>
                            <span class="public-status {{ $isActive ? 'bac-tone-danger' : 'bac-tone-muted' }}">{{ $sanction->lifted_at ? 'Lifted' : ($isActive ? 'Active' : 'Ended') }}</span>
                            <h3>{{ ucfirst(str_replace('_', ' ', (string) $sanction->type)) }}</h3>
                            <p>{{ $sanction->reason }}@if($sanction->reference_number) · Ref. {{ $sanction->reference_number }}@endif</p>
                        </div>
                        <div class="bidder-record-amount">
                            <small>From {{ $sanction->effective_date?->format('M d, Y') }}@if($sanction->end_date) to {{ $sanction->end_date->format('M d, Y') }}@endif</small>
                            @if($sanction->lifted_at)<small>Lifted {{ $sanction->lifted_at->timezone($tz)->format('M d, Y') }}</small>@endif
                        </div>
                    </article>
                @empty
                    <p class="bidder-record-empty">No sanctions on record.</p>
                @endforelse

                <h3 class="bac-view-sub">{{ $bacView['limited'] ? 'Bids on your assigned projects' : 'Every bid' }}</h3>
                @forelse($bacBids as $bid)
                    @php
                        [$outcomeLabel, $outcomeTone] = $outcome($bid);
                        $viewUrl = $bacView['role'] === 'admin'
                            ? route('admin.bid.view', $bid)
                            : route('staff.review-bids', ['project' => $bid->project_id, 'view_bid' => $bid->id]);
                    @endphp
                    <article class="bidder-record-row">
                        <div>
                            <span class="public-status bac-tone-{{ $outcomeTone }}">{{ $outcomeLabel }}</span>
                            <h3>{{ $bid->project->title ?? 'Deleted project' }}</h3>
                            <p>
                                @if($bid->project?->reference_no){{ $bid->project->reference_no }} · @endif
                                Submitted {{ $bid->submitted_at?->timezone($tz)->format('M d, Y') ?? $bid->created_at?->timezone($tz)->format('M d, Y') }}
                                @if($bid->workflow_step_label && $bid->workflow_step_label !== $outcomeLabel) · Step: {{ $bid->workflow_step_label }}@endif
                            </p>
                        </div>
                        <div class="bidder-record-amount">
                            @if($bid->isSealed())
                                <small>Amount sealed until opening</small>
                            @else
                                <strong>&#8369;{{ number_format((float) $bid->bid_amount, 2) }}</strong>
                            @endif
                            <a href="{{ $viewUrl }}">Open the bid</a>
                        </div>
                    </article>
                @empty
                    <p class="bidder-record-empty">{{ $bacView['limited'] ? 'This bidder has no bid on the projects assigned to you.' : 'This bidder has not submitted any bid yet.' }}</p>
                @endforelse
            </section>
        @endif
    </main>

    <style>
        .bac-view { margin-top: 36px; padding: 20px; border: 2px solid #1d4f40; border-radius: 16px; background: #f4f8f6; }
        .bac-view-head h2 { display: flex; align-items: center; gap: 8px; margin: 0 0 4px; }
        .bac-view-head p { margin: 0 0 6px; color: #6b736e; font-size: 13px; }
        .bac-view-link { color: #1d4f40; font-size: 13px; font-weight: 700; }
        .bac-view-sub { margin: 20px 0 10px; font-size: 15px; }
        .bac-tone-success { background: #e1f3e8; color: #1f7a4d; }
        .bac-tone-danger { background: #fdecea; color: #b42318; }
        .bac-tone-info { background: #e8f0fb; color: #1f4f86; }
        .bac-tone-muted { background: #eef1ef; color: #55615b; }
        @media print { .bac-view { display: none; } }
    </style>

    <style>
        .bidder-record .public-page-kicker i { color: #047857; }
        .bidder-record-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin: 0 0 24px; }
        .bidder-record-stats div { padding: 16px 18px; border: 1px solid #e5dfd2; border-radius: 12px; background: #fff; }
        .bidder-record-stats span { display: block; color: #6b736e; font-size: 13px; }
        .bidder-record-stats strong { display: block; margin-top: 4px; font-size: 22px; color: #1b2420; }
        .bidder-record-section { margin: 0 0 28px; }
        .bidder-record-section h2 { margin: 0 0 12px; font-size: 18px; }
        .bidder-record-row { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 14px 16px; margin-bottom: 10px; border: 1px solid #e5dfd2; border-radius: 12px; background: #fff; }
        .bidder-record-row h3 { margin: 6px 0 2px; font-size: 15px; }
        .bidder-record-row p { margin: 0; color: #6b736e; font-size: 13px; }
        .bidder-record-amount { display: grid; justify-items: end; gap: 4px; text-align: right; white-space: nowrap; }
        .bidder-record-amount strong { font-size: 16px; }
        .bidder-record-amount a { color: #1d4f40; font-size: 13px; font-weight: 600; }
        .bidder-record-amount small { color: #6b736e; }
        .bidder-record-empty { padding: 14px 16px; border: 1px dashed #d9d2c3; border-radius: 12px; color: #6b736e; }
        @media (max-width: 600px) {
            .bidder-record-row { flex-direction: column; align-items: flex-start; }
            .bidder-record-amount { justify-items: start; text-align: left; }
        }
    </style>
@endsection
