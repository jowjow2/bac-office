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
    </main>

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
