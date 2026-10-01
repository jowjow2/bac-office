@extends('layouts.public')

@section('title', 'Award Verification | SJBAC')
@section('body_class', 'public-page award-verification-body')

@push('pre_app_styles')
    <style>
        .award-verification-shell {
            width: min(100%, 1060px);
            padding-top: clamp(24px, 5vw, 64px);
            padding-bottom: clamp(28px, 6vw, 72px);
        }

        .award-verification-card {
            overflow: hidden;
            border: 1px solid var(--ui-line);
            border-radius: 24px;
            background: #ffffff;
            box-shadow: 0 24px 60px rgba(27, 36, 32, 0.1);
        }

        .award-verification-header,
        .award-verification-heading,
        .verification-section,
        .verification-footer {
            padding-right: clamp(20px, 4vw, 42px);
            padding-left: clamp(20px, 4vw, 42px);
        }

        .award-verification-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            min-height: 84px;
            border-bottom: 1px solid var(--ui-line-soft);
            background: var(--ui-surface-2);
        }

        .verification-office-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .verification-office-brand img {
            width: 48px;
            height: 48px;
            flex: 0 0 48px;
            border: 1px solid var(--ui-line);
            border-radius: 14px;
            background: #ffffff;
            object-fit: contain;
        }

        .verification-office-brand span,
        .verification-office-brand strong,
        .verification-office-brand small {
            display: block;
        }

        .verification-office-brand span {
            margin-bottom: 3px;
            color: var(--ui-muted);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .verification-office-brand strong {
            color: var(--ui-ink);
            font-size: 17px;
            line-height: 1.15;
        }

        .verification-office-brand small {
            margin-top: 3px;
            color: var(--ui-subtle);
            font-size: 11px;
        }

        .verification-readonly {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            flex: 0 0 auto;
            min-height: 30px;
            padding: 0 11px;
            border: 1px solid var(--ui-line);
            border-radius: 999px;
            color: var(--ui-ink-2);
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .verification-readonly i {
            color: var(--ui-primary);
        }

        .award-verification-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            padding-top: clamp(26px, 5vw, 46px);
            padding-bottom: 24px;
        }

        .verification-kicker {
            margin: 0 0 8px;
            color: var(--ui-primary);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .award-verification-heading h1 {
            margin: 0;
            color: var(--ui-ink);
            font-size: clamp(25px, 4vw, 36px);
            line-height: 1.12;
            letter-spacing: -.035em;
        }

        .verification-lead {
            max-width: 620px;
            margin: 11px 0 0;
            color: var(--ui-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .verification-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            flex: 0 0 auto;
            min-width: 132px;
            padding: 12px 14px;
            border-radius: 14px;
        }

        .verification-status-pill i {
            font-size: 15px;
        }

        .verification-status-pill strong,
        .verification-status-pill small {
            display: block;
        }

        .verification-status-pill strong {
            font-size: 14px;
            line-height: 1.2;
        }

        .verification-status-pill small {
            margin-top: 3px;
            font-size: 10px;
            font-weight: 700;
            opacity: .82;
        }

        .verification-status-pill.is-valid,
        .verification-status-banner.is-valid {
            background: var(--ui-success-soft);
            color: var(--ui-success);
        }

        .verification-status-pill.is-revoked,
        .verification-status-banner.is-revoked {
            background: var(--ui-danger-soft);
            color: var(--ui-danger);
        }

        .verification-status-pill.is-expired,
        .verification-status-pill.is-unknown,
        .verification-status-banner.is-expired,
        .verification-status-banner.is-unknown {
            background: var(--ui-surface-2);
            color: var(--ui-ink-2);
        }

        .verification-status-banner {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            margin: 0 clamp(20px, 4vw, 42px) 26px;
            padding: 16px 18px;
            border-radius: 15px;
        }

        .verification-status-banner > i {
            margin-top: 2px;
            font-size: 18px;
        }

        .verification-status-banner strong,
        .verification-status-banner p {
            display: block;
        }

        .verification-status-banner strong {
            font-size: 14px;
        }

        .verification-status-banner p {
            margin: 4px 0 0;
            font-size: 12px;
            line-height: 1.45;
            opacity: .86;
        }

        .verification-status-note {
            margin: -10px clamp(20px, 4vw, 42px) 26px;
            padding: 13px 15px;
            border-left: 4px solid var(--ui-warning);
            border-radius: 10px;
            background: var(--ui-warning-soft);
            color: var(--ui-warning);
            font-size: 12px;
            line-height: 1.5;
        }

        .verification-status-note strong {
            display: block;
            margin-bottom: 3px;
            font-size: 11px;
            text-transform: uppercase;
        }

        .verification-section {
            padding-top: 25px;
            padding-bottom: 25px;
            border-top: 1px solid var(--ui-line-soft);
        }

        .verification-section-heading {
            margin-bottom: 15px;
        }

        .verification-section-heading span {
            display: block;
            margin-bottom: 5px;
            color: var(--ui-subtle);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .verification-section-heading h2 {
            margin: 0;
            color: var(--ui-ink);
            font-size: 18px;
            line-height: 1.25;
        }

        .verification-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 11px;
            margin: 0;
        }

        .verification-field {
            min-width: 0;
            padding: 14px;
            border: 1px solid var(--ui-line);
            border-radius: 13px;
            background: var(--ui-surface-2);
        }

        .verification-field.is-wide {
            grid-column: span 2;
        }

        .verification-field dt {
            margin-bottom: 7px;
            color: var(--ui-muted);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .verification-field dd {
            margin: 0;
            overflow: hidden;
            color: var(--ui-ink);
            font-size: 14px;
            font-weight: 700;
            line-height: 1.4;
            text-overflow: ellipsis;
        }

        .verification-field dd small {
            display: block;
            margin-top: 4px;
            color: var(--ui-subtle);
            font-size: 11px;
            font-weight: 500;
        }

        .verification-money {
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .verification-money.is-savings {
            color: var(--ui-success) !important;
        }

        .verification-money.is-over-budget {
            color: var(--ui-danger) !important;
        }

        .verification-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding-top: 20px;
            padding-bottom: 20px;
            border-top: 1px solid var(--ui-line-soft);
            background: var(--ui-surface-2);
        }

        .verification-timestamp {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .verification-timestamp > i {
            color: var(--ui-primary);
            font-size: 16px;
        }

        .verification-timestamp span,
        .verification-timestamp strong {
            display: block;
        }

        .verification-timestamp span {
            color: var(--ui-muted);
            font-size: 11px;
        }

        .verification-timestamp strong {
            margin-top: 3px;
            color: var(--ui-ink);
            font-size: 12px;
        }

        .verification-document-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 38px;
            padding: 0 13px;
            border: 1px solid var(--ui-line-strong);
            border-radius: 10px;
            background: #ffffff;
            color: var(--ui-primary-hover);
            font-size: 12px;
            font-weight: 750;
            text-decoration: none;
        }

        .verification-document-link:hover {
            border-color: var(--ui-primary-line);
            background: var(--ui-primary-soft);
        }

        .verification-disclaimer {
            margin: 0;
            padding: 16px clamp(20px, 4vw, 42px) 20px;
            color: var(--ui-subtle);
            font-size: 11px;
            line-height: 1.55;
            text-align: center;
        }

        @media (max-width: 720px) {
            .award-verification-header,
            .award-verification-heading,
            .verification-footer {
                align-items: stretch;
                flex-direction: column;
            }

            .verification-readonly,
            .verification-status-pill {
                align-self: flex-start;
            }

            .verification-grid {
                grid-template-columns: 1fr;
            }

            .verification-field.is-wide {
                grid-column: auto;
            }

            .verification-footer {
                gap: 14px;
            }

            .verification-document-link {
                align-self: flex-start;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $status = in_array($verificationStatus, ['valid', 'revoked', 'expired'], true)
            ? $verificationStatus
            : 'unknown';
        $statusMeta = match ($status) {
            'valid' => [
                'label' => 'Valid',
                'icon' => 'fa-circle-check',
                'title' => 'This award record is valid',
                'message' => 'The published award record is currently active and available for public verification.',
            ],
            'revoked' => [
                'label' => 'Revoked',
                'icon' => 'fa-circle-xmark',
                'title' => 'This award record was revoked',
                'message' => 'The certificate is no longer considered valid by SJBAC.',
            ],
            'expired' => [
                'label' => 'Expired',
                'icon' => 'fa-clock',
                'title' => 'This award record has expired',
                'message' => 'The certificate is no longer within its valid verification period.',
            ],
            default => [
                'label' => 'Unavailable',
                'icon' => 'fa-circle-question',
                'title' => 'Award status is unavailable',
                'message' => 'The published record could not be assigned a recognized status.',
            ],
        };
        $projectTitle = $award->project?->title ?? 'Untitled project';
        $winner = $award->bid?->user?->company ?: ($award->bid?->user?->name ?? 'N/A');
        $abc = (float) ($award->project?->budget ?? 0);
        $contractAmount = (float) ($award->contract_amount ?? 0);
        $savings = $abc - $contractAmount;
        $savingsClass = $savings >= 0 ? 'is-savings' : 'is-over-budget';
        $statusNote = in_array($status, ['revoked', 'expired'], true) && filled($award->notes)
            ? $award->notes
            : null;
    @endphp

    <main class="public-shell award-verification-shell">
        <article class="award-verification-card">
            <header class="award-verification-header">
                <div class="verification-office-brand">
                    <img src="{{ asset('Images/Logo2.png') }}" alt="SJBAC logo">
                    <div>
                        <span>Issuing office</span>
                        <strong>SJBAC</strong>
                        <small>Bids and Awards Committee</small>
                    </div>
                </div>
                <span class="verification-readonly">
                    <i class="fas fa-lock" aria-hidden="true"></i>
                    Public read-only record
                </span>
            </header>

            <section class="award-verification-heading">
                <div>
                    <p class="verification-kicker">QR verification</p>
                    <h1>Official award record</h1>
                    <p class="verification-lead">
                        This page retrieves the current award record directly from SJBAC for public verification.
                    </p>
                </div>
                <div class="verification-status-pill is-{{ $status }}" aria-label="Award status: {{ $statusMeta['label'] }}">
                    <i class="fas {{ $statusMeta['icon'] }}" aria-hidden="true"></i>
                    <div>
                        <strong>{{ $statusMeta['label'] }}</strong>
                        <small>Award status</small>
                    </div>
                </div>
            </section>

            <section class="verification-status-banner is-{{ $status }}" aria-label="{{ $statusMeta['title'] }}">
                <i class="fas {{ $statusMeta['icon'] }}" aria-hidden="true"></i>
                <div>
                    <strong>{{ $statusMeta['title'] }}</strong>
                    <p>{{ $statusMeta['message'] }}</p>
                </div>
            </section>

            @if($statusNote)
                <div class="verification-status-note">
                    <strong>Status note</strong>
                    <span>{{ $statusNote }}</span>
                </div>
            @endif

            <section class="verification-section" aria-labelledby="verification-record-title">
                <div class="verification-section-heading">
                    <span>Procurement record</span>
                    <h2 id="verification-record-title">Award details</h2>
                </div>
                <dl class="verification-grid">
                    <div class="verification-field is-wide">
                        <dt>Project title</dt>
                        <dd title="{{ $projectTitle }}">{{ $projectTitle }}</dd>
                    </div>
                    <div class="verification-field">
                        <dt>Project number</dt>
                        <dd>Project #{{ $award->project_id }}</dd>
                    </div>
                    <div class="verification-field">
                        <dt>Winning bidder</dt>
                        <dd title="{{ $winner }}">{{ $winner }}</dd>
                    </div>
                    <div class="verification-field">
                        <dt>Award date</dt>
                        <dd>{{ $award->contract_date?->format('F j, Y') ?? 'Not available' }}</dd>
                    </div>
                    <div class="verification-field">
                        <dt>BAC award reference</dt>
                        <dd>{{ $award->certificate_number ?: 'Not assigned' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="verification-section" aria-labelledby="verification-financial-title">
                <div class="verification-section-heading">
                    <span>Contract values</span>
                    <h2 id="verification-financial-title">Financial summary</h2>
                </div>
                <dl class="verification-grid">
                    <div class="verification-field">
                        <dt>ABC (approved budget)</dt>
                        <dd class="verification-money">&#8369;{{ number_format($abc, 2) }}</dd>
                    </div>
                    <div class="verification-field">
                        <dt>Contract amount</dt>
                        <dd class="verification-money">&#8369;{{ number_format($contractAmount, 2) }}</dd>
                    </div>
                    <div class="verification-field">
                        <dt>Savings</dt>
                        <dd class="verification-money {{ $savingsClass }}">&#8369;{{ number_format($savings, 2) }}</dd>
                    </div>
                </dl>
            </section>

            <footer class="verification-footer">
                <div class="verification-timestamp">
                    <i class="fas fa-clock" aria-hidden="true"></i>
                    <div>
                        <span>Verified as of</span>
                        <strong>{{ $verifiedAt->format('F j, Y, g:i A') }}</strong>
                    </div>
                </div>
                @if($status === 'valid' && $award->isCertificateViewable())
                    <a href="{{ $award->tokenCertificateUrl() }}" class="verification-document-link" target="_blank" rel="noopener">
                        <i class="fas fa-file-pdf" aria-hidden="true"></i>
                        View certificate PDF
                    </a>
                @endif
            </footer>

            <p class="verification-disclaimer">
                This is a public, read-only verification page. The record was retrieved live and no bidder contact or personal details are displayed.
            </p>
        </article>
    </main>
@endsection
