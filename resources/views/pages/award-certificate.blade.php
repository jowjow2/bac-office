@extends('layouts.public')

@section('title', 'Official Award Certificate')
@section('body_class', 'public-page')

@section('content')
    <main class="public-shell certificate-shell">
        <section class="certificate-card">
            <div class="certificate-document-border">
                <div class="certificate-letterhead">
                    <img src="{{ asset('Images/Logo2.png') }}" alt="BAC-Office logo">
                    <div>
                        <span>Republic Procurement Record</span>
                        <strong>BAC-Office</strong>
                        <p>Bids and Awards Committee Official Verification</p>
                    </div>
                </div>

                <div class="certificate-topline">
                    <div>
                        <p class="public-page-kicker">Verified Award Record</p>
                        <h1>Certificate of Award</h1>
                        <p class="certificate-subtitle">
                            This certifies that the procurement award below is an authentic BAC-Office published record.
                        </p>
                    </div>

                    @if($qrUrl)
                        <div class="certificate-qr">
                            <img src="{{ $qrUrl }}" alt="QR code for this official award certificate">
                            <span>Scan to verify</span>
                        </div>
                    @endif
                </div>

                <div class="certificate-stamp">
                    <span>Authentic Official Document</span>
                    <strong>{{ $award->certificate_number }}</strong>
                </div>

                <div class="certificate-statement">
                    <p>
                        This document confirms that <strong>{{ $winner }}</strong> has been declared the winning bidder for
                        <strong>{{ $award->project?->title ?? 'Untitled Project' }}</strong>, subject to the published award record
                        maintained by BAC-Office.
                    </p>
                </div>

                <div class="certificate-grid">
                    <div class="certificate-field certificate-field-wide">
                        <span>Project Title</span>
                        <strong>{{ $award->project?->title ?? 'Untitled Project' }}</strong>
                    </div>

                    <div class="certificate-field">
                        <span>Awarded To</span>
                        <strong>{{ $winner }}</strong>
                        <small>{{ $award->bid?->user?->email }}</small>
                    </div>

                    <div class="certificate-field">
                        <span>Contract Amount</span>
                        <strong>&#8369;{{ number_format((float) $award->contract_amount, 2) }}</strong>
                    </div>

                    <div class="certificate-field">
                        <span>Award Date</span>
                        <strong>{{ $award->contract_date?->format('F d, Y') ?? 'TBA' }}</strong>
                    </div>

                    <div class="certificate-field">
                        <span>Record Status</span>
                        <strong>{{ ucfirst($award->status) }}</strong>
                    </div>

                    <div class="certificate-field certificate-field-wide">
                        <span>Remarks</span>
                        <p>{{ $award->notes ?: 'No additional notes were published for this award.' }}</p>
                    </div>
                </div>

                <div class="certificate-signature-row">
                    <div class="certificate-signature">
                        <span>BAC-Office Verification System</span>
                        <strong>Official Digital Record</strong>
                    </div>
                    <div class="certificate-issued">
                        <span>Verified on</span>
                        <strong>{{ now()->format('F d, Y h:i A') }}</strong>
                    </div>
                </div>

                <div class="certificate-verification">
                    <div>
                        <span>Verification URL</span>
                        <a href="{{ $verificationUrl }}">{{ $verificationUrl }}</a>
                    </div>
                    <p>Scanning the QR code must open this exact verification URL.</p>
                </div>

                <div class="certificate-actions">
                    <a href="{{ route('public.awards') }}" class="btn btn-outline">Back to Awards</a>
                    <button type="button" class="btn" onclick="window.print()">Print Certificate</button>
                </div>
            </div>
        </section>
    </main>
@endsection
