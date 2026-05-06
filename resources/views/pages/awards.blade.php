@extends('layouts.public')

@section('title', 'Awards & Contracts')
@section('body_class', 'public-page')

@section('content')
    <main class="public-shell">
        <section class="public-page-hero">
            <p class="public-page-kicker">Awards & Contracts</p>
            <h1>Recently awarded procurement projects</h1>
            <p>
                See awarded contracts, project titles, winning bidders, and contract values published through BAC-Office.
            </p>
        </section>

        <section class="public-results-bar">
            <p>
                @if($query !== '')
                    Results for "<strong>{{ $query }}</strong>"
                @else
                    Showing the latest awarded contracts
                @endif
            </p>
            <span>{{ $awards->count() }} award{{ $awards->count() === 1 ? '' : 's' }}</span>
        </section>

        <section class="public-card-grid">
                @forelse($awards as $award)
                    @php
                        $winner = $award->bid?->user?->company ?: ($award->bid?->user?->name ?? 'N/A');
                        $hasCertificate = $award->hasCertificateFile();
                        $qrUrl = $award->tokenQrUrl();
                    @endphp
                    <article class="public-card">
                        <div class="public-card-meta">
                            <span class="public-status public-status-awarded">{{ ucfirst($award->status) }}</span>
                            <span>{{ $award->contract_date?->format('M d, Y') ?? 'TBA' }}</span>
                        </div>

                        <h2>{{ $award->project?->title ?? 'Untitled Project' }}</h2>
                        <p>Winning bidder: {{ $winner }}</p>

                        <div class="public-award-verify">
                            @if($hasCertificate)
                                <div class="public-award-qr" aria-label="Scan QR code for official award certificate">
                                    <img src="{{ $qrUrl }}" alt="QR code for official award certificate">
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
                    No awarded contracts matched your search yet.
                </div>
            @endforelse
        </section>
    </main>
@endsection
