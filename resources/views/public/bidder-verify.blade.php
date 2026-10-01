@extends('layouts.public')

@section('title', ($user->company ?: 'Registered Bidder') . ' - Bidding Record')
@section('body_class', 'public-page')

@section('content')
    <main class="public-shell">
        <section class="public-page-hero">
            <p class="public-page-kicker">Verified Bidding Record</p>
            <h1>{{ $user->company ?: 'Registered Bidder' }}</h1>
            <p>
                This page shows the publicly verifiable procurement record for this bidder, as recorded by the
                Bids and Awards Committee: approved bids and awarded contracts only.
            </p>
        </section>

        <section class="public-results-bar">
            <p>{{ $approvedBids->count() }} approved bid{{ $approvedBids->count() === 1 ? '' : 's' }}</p>
            <span>{{ $awards->count() }} awarded contract{{ $awards->count() === 1 ? '' : 's' }}</span>
        </section>

        <section class="public-card-grid">
            @forelse($approvedBids as $bid)
                <article class="public-card">
                    <div class="public-card-meta">
                        <span class="public-status public-status-open">Approved</span>
                        <span>{{ $bid->created_at?->format('M d, Y') ?? 'N/A' }}</span>
                    </div>

                    <h2>{{ $bid->project->title ?? 'Untitled Project' }}</h2>
                    <p>Bid amount is kept confidential unless the contract is awarded.</p>
                </article>
            @empty
                <div class="public-empty-state">
                    No approved bids on record yet.
                </div>
            @endforelse
        </section>

        <section class="public-results-bar">
            <p><strong>Awarded Contracts</strong></p>
        </section>

        <section class="public-card-grid">
            @forelse($awards as $award)
                @php
                    $hasCertificate = $award->hasCertificateFile();
                    $qrUrl = $award->tokenQrUrl();
                @endphp
                <article class="public-card">
                    <div class="public-card-meta">
                        <span class="public-status public-status-awarded">Awarded</span>
                        <span>{{ $award->contract_date?->format('M d, Y') ?? 'TBA' }}</span>
                    </div>

                    <h2>{{ $award->project->title ?? 'Untitled Project' }}</h2>

                    <div class="public-award-verify">
                        @if($hasCertificate)
                            <div class="public-award-qr" aria-label="Scan QR code for the official award certificate">
                                <img src="{{ $qrUrl }}" alt="QR code for the official award certificate">
                            </div>
                            <div class="public-award-verify-copy">
                                <span>Official Certificate QR</span>
                                <strong>{{ $award->certificate_number }}</strong>
                                <p>Scan QR to view the authentic certificate document.</p>
                            </div>
                        @else
                            <div class="public-award-verify-copy">
                                <span>Certificate Verification</span>
                                <strong>Pending certificate</strong>
                                <p>Certificate not yet uploaded.</p>
                            </div>
                        @endif
                    </div>

                    <div class="public-card-footer">
                        <strong>&#8369;{{ number_format((float) $award->contract_amount, 2) }}</strong>
                    </div>
                </article>
            @empty
                <div class="public-empty-state">
                    No awarded contracts on record yet.
                </div>
            @endforelse
        </section>
    </main>
@endsection
