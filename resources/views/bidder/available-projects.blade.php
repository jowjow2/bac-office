<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home bidder-available-page">
    @vite(['resources/css/dashboard.css'])
    @if(session('success'))
        <div id="bidSubmitSuccess" class="bidder-success-overlay" data-auto-hide="5000">
            <div class="bidder-success-alert">
                <span class="bidder-success-icon" aria-hidden="true">
                    <span class="bidder-success-loader"></span>
                    <svg class="bidder-success-check" viewBox="0 0 24 24">
                        <path d="M20 6L9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
            </div>
        </div>
    @endif

    <style>
        .bidder-available-page {
            font-family: 'Inter', sans-serif;
        }

        .bidder-sidebar-badge {
            margin-left: auto;
        }

        .bidder-available-page .page-intro {
            margin-bottom: 22px;
        }

        .bidder-available-page .page-title {
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
        }

        .bidder-available-page .page-subtitle {
            margin: 0;
            font-size: 13px;
            color: #94a3b8;
        }

        .bidder-available-card {
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            overflow: hidden;
            margin-bottom: 18px;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }

        .bidder-available-card.is-scanned-target {
            border-color: #60a5fa;
            box-shadow: 0 18px 36px rgba(37, 99, 235, 0.18);
            transform: translateY(-2px);
        }

        .bidder-available-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 22px;
            border-bottom: 1px solid #e2e8f0;
        }

        .bidder-available-header h2 {
            margin: 0 0 6px;
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
        }

        .bidder-available-meta {
            margin: 0;
            font-size: 12px;
            color: #94a3b8;
        }

        .bidder-available-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
            align-items: center;
        }

        .bidder-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 28px;
            padding: 0 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        .bidder-badge-count {
            background: #fef3c7;
            color: #b45309;
        }

        .bidder-badge-approved {
            background: #dcfce7;
            color: #15803d;
        }

        .bidder-badge-pending {
            background: #fef3c7;
            color: #b45309;
        }

        .bidder-badge-rejected {
            background: #fee2e2;
            color: #b91c1c;
        }

        .bidder-badge-open {
            background: #e0f2fe;
            color: #0369a1;
        }

        .bidder-available-body {
            padding: 18px 22px 22px;
        }

        .bidder-available-desc {
            margin: 0 0 16px;
            font-size: 12px;
            line-height: 1.7;
            color: #64748b;
        }

        .bidder-project-scan {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 16px;
            margin-bottom: 16px;
            border: 1px solid #dbeafe;
            border-radius: 16px;
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
        }

        .bidder-project-scan-copy {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 0;
        }

        .bidder-project-scan-title {
            font-size: 12px;
            font-weight: 700;
            color: #1d4ed8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .bidder-project-scan-text {
            margin: 0;
            font-size: 12px;
            line-height: 1.6;
            color: #64748b;
        }

        .bidder-project-scan-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            width: fit-content;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
        }

        .bidder-project-scan-link:hover {
            color: #1e40af;
        }

        .bidder-project-qr {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 96px;
            height: 96px;
            padding: 8px;
            border-radius: 14px;
            background: #ffffff;
            border: 1px solid #bfdbfe;
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.08);
            flex-shrink: 0;
        }

        .bidder-project-qr img {
            display: block;
            width: 100%;
            height: auto;
        }

        .bidder-available-footer {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            font-size: 12px;
            color: #64748b;
        }

        .bidder-available-footer strong {
            color: #0f172a;
            font-weight: 600;
        }

        .bidder-submit-trigger,
        .bidder-submit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 18px;
            border-radius: 10px;
            border: 1px solid #1d4ed8;
            background: #1d4ed8;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        .bidder-submit-trigger:hover,
        .bidder-submit-btn:hover {
            background: #1e40af;
            border-color: #1e40af;
        }

        .bidder-modal-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(15, 23, 42, 0.45);
            z-index: 2000;
        }

        .bidder-modal-overlay.show {
            display: flex;
        }

        .bidder-modal {
            width: min(100%, 660px);
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.2);
            overflow: hidden;
        }

        .bidder-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 22px 24px 18px;
            border-bottom: 1px solid #e2e8f0;
        }

        .bidder-modal-title {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            line-height: 24px;
            color: #1f2937;
        }

        .bidder-modal-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border: 0;
            border-radius: 10px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 17px;
            cursor: pointer;
        }

        .bidder-modal-body {
            padding: 20px 24px;
        }

        .bidder-project-card {
            margin-bottom: 18px;
            padding: 16px;
            border-radius: 12px;
            background: #eef4ff;
        }

        .bidder-project-card-title {
            margin: 0 0 6px;
            font-size: 15px;
            font-weight: 600;
            color: #111827;
        }

        .bidder-project-card-meta {
            margin: 0;
            font-size: 13px;
            font-weight: 400;
            color: #6b7280;
        }

        .bidder-bid-form {
            display: grid;
            gap: 18px;
        }

        .bidder-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .bidder-field label {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #6b7280;
        }

        .bidder-input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #fff;
            color: #111827;
            font-size: 14px;
            font-weight: 400;
            padding: 12px 14px;
        }

        .bidder-input::placeholder {
            color: #9ca3af;
            font-size: 14px;
        }

        .bidder-upload-box {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 136px;
            padding: 18px;
            border: 2px dashed #cfd8e6;
            border-radius: 12px;
            background: #fff;
            text-align: center;
            overflow: hidden;
        }

        .bidder-upload-box input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .bidder-upload-icon {
            margin-bottom: 10px;
            font-size: 28px;
            color: #b8c1d1;
        }

        .bidder-upload-title {
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
        }

        .bidder-upload-meta {
            margin-top: 6px;
            font-size: 12px;
            color: #94a3b8;
        }

        .bidder-upload-selected {
            margin-top: 8px;
            font-size: 12px;
            color: #475569;
            word-break: break-all;
        }

        .bidder-textarea {
            min-height: 88px;
            resize: vertical;
            line-height: 1.5;
        }

        .bidder-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 0 24px 24px;
        }

        .bidder-cancel-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 18px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }

        .bidder-alert {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            font-size: 12px;
        }

        .bidder-alert-success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .bidder-alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .bidder-success-overlay {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(15, 23, 42, 0.18);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            z-index: 2300;
            opacity: 1;
            transition: opacity 0.35s ease;
        }

        .bidder-success-overlay.fade-out {
            opacity: 0;
        }

        .bidder-success-alert {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0;
            text-align: center;
            color: #ffffff;
            opacity: 1;
            transform: translateY(0);
            transition: opacity 0.35s ease, transform 0.35s ease;
        }

        .bidder-success-alert.fade-out {
            opacity: 0;
            transform: translateY(-12px);
        }

        .bidder-success-icon {
            width: 86px;
            height: 86px;
            flex: 0 0 86px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #ffffff;
            color: #16a34a;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.14);
        }

        .bidder-success-loader {
            position: absolute;
            inset: 7px;
            border-radius: 999px;
            border: 4px solid rgba(34, 197, 94, 0.14);
            border-top-color: #22c55e;
            border-right-color: #16a34a;
            animation: bidderSuccessSpin 0.7s linear 2, bidderSuccessLoaderOut 0.2s ease 1.25s forwards;
        }

        .bidder-success-check {
            width: 32px;
            height: 32px;
            opacity: 0;
            transform: scale(0.7);
            filter: drop-shadow(0 6px 12px rgba(22, 163, 74, 0.22));
            animation: bidderSuccessCheckIn 0.38s cubic-bezier(0.2, 0.9, 0.2, 1) 1.3s forwards;
        }

        .bidder-success-check path {
            stroke-dasharray: 24;
            stroke-dashoffset: 24;
            animation: bidderSuccessCheckDraw 0.32s ease 1.34s forwards;
        }

        @keyframes bidderSuccessSpin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes bidderSuccessLoaderOut {
            to {
                opacity: 0;
                transform: scale(0.88);
            }
        }

        @keyframes bidderSuccessCheckIn {
            0% {
                opacity: 0;
                transform: scale(0.7);
            }
            70% {
                opacity: 1;
                transform: scale(1.08);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes bidderSuccessCheckDraw {
            to {
                stroke-dashoffset: 0;
            }
        }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .bidder-empty {
            padding: 26px 20px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 18px;
        }

        @media (max-width: 900px) {
            .bidder-available-header {
                flex-direction: column;
            }

            .bidder-available-badges {
                justify-content: flex-start;
            }

            .bidder-project-scan {
                flex-direction: column;
                align-items: flex-start;
            }

            .bidder-modal {
                width: 100%;
            }
        }
    </style>

    <aside class="sidebar">
        <a href="{{ route('bidder.dashboard') }}" class="sidebar-logo-link"><h2 class="sidebar-logo">BAC-Office</h2></a>
        @include('partials.sidebar-profile')
        <ul class="sidebar-menu">
            <p class="menu-title">MAIN</p>
            <li><a href="{{ route('bidder.dashboard') }}"><span class="menu-icon-dashboard" aria-hidden="true"></span> Dashboard</a></li>
            <li><a href="{{ route('bidder.available-projects') }}" class="active"><i class="fas fa-folder-open"></i> Available Projects</a></li>
            <li><a href="{{ route('bidder.my-bids') }}"><i class="fas fa-file-signature"></i> My Bids</a></li>
            <li><a href="{{ route('bidder.awarded-contracts') }}"><i class="fas fa-award"></i> Awarded Contracts</a></li>

            <p class="menu-title">ACCOUNT</p>
            <li><a href="{{ route('bidder.company-profile') }}"><i class="fas fa-building"></i> Company Profile</a></li>
            <li><a href="{{ route('bidder.notifications') }}"><i class="fas fa-bell"></i> Notification @if(($bidderNotificationCount ?? 0) > 0)<span class="notification-badge bidder-sidebar-badge">{{ $bidderNotificationCount }}</span>@endif</a></li>

            <li>
                <form action="{{ route('logout') }}" method="POST" class="sidebar-form">
                    @csrf
                    <button type="submit" class="sidebar-logout"><i class="fas fa-sign-out-alt"></i> Logout</button>
                </form>
            </li>
        </ul>
    </aside>

    <div class="main-area">
        <header class="navbar">
            <div class="nav-left">
                <h2>Available Projects</h2>
                <p>Browse and bid on open procurement projects</p>
            </div>

            <div class="nav-right">
                <div class="nav-date-chip"><span id="realtimeDate">{{ now()->format('M d, Y h:i A') }}</span></div>
                <a href="{{ route('bidder.notifications') }}" class="notification-button" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    @if(($bidderNotificationCount ?? 0) > 0)
                        <span class="notification-badge">{{ $bidderNotificationCount }}</span>
                    @endif
                </a>
            </div>
        </header>

        <main class="dashboard-content dashboard-home-content">
            <section class="page-intro">
                <h1 class="page-title">Available Projects</h1>
                <p class="page-subtitle">Browse and bid on open procurement projects</p>
                <div class="bidder-page-actions">
                    <button type="button" class="bidder-scanner-trigger" data-scanner-open>
                        <i class="fas fa-camera" aria-hidden="true"></i>
                        Scan Project QR
                    </button>
                </div>
            </section>

            @php
                $scannedProject = isset($scanProjectId) ? $availableProjects->firstWhere('id', $scanProjectId) : null;
                $scannedBid = $scannedProject ? $myBids->firstWhere('project_id', $scannedProject->id) : null;
            @endphp

            @if($errors->any())
                <div class="bidder-alert bidder-alert-error">{{ $errors->first() }}</div>
            @endif

            @if($scanProjectId)
                <div class="bidder-alert {{ $scannedProject ? 'bidder-alert-success' : 'bidder-alert-error' }}">
                    @if($scannedProject && ! $scannedBid)
                        QR project found. Review the project below and complete your bid submission.
                    @elseif($scannedProject)
                        You already submitted a bid for {{ $scannedProject->title }}.
                    @else
                        The scanned project is not currently open for bidder submission.
                    @endif
                </div>
            @endif

            @forelse($availableProjects as $project)
                @php
                    $myBid = $myBids->firstWhere('project_id', $project->id);
                    $yourBidClass = match($myBid?->status) {
                        'approved' => 'bidder-badge-approved',
                        'rejected' => 'bidder-badge-rejected',
                        'pending' => 'bidder-badge-pending',
                        default => 'bidder-badge-open',
                    };
                    $yourBidLabel = $myBid ? 'Your bid: ' . strtolower($myBid->status) : null;
                @endphp

                <article
                    class="bidder-available-card"
                    id="project-{{ $project->id }}"
                    data-project-card
                    data-project-id="{{ $project->id }}"
                    data-project-modal-id="{{ $myBid ? '' : 'bid-modal-' . $project->id }}"
                >
                    <div class="bidder-available-header">
                        <div>
                            <h2>{{ $project->title }}</h2>
                            <p class="bidder-available-meta">Approved Budget: P{{ number_format((float) $project->budget, 2) }} &middot; {{ $project->deadline && $project->deadline->isPast() ? 'Deadline passed' : 'Open for bidding' }}</p>
                        </div>

                        <div class="bidder-available-badges">
                            <span class="bidder-badge bidder-badge-count">{{ $project->bids_count }} bids</span>
                            @if($myBid)
                                <span class="bidder-badge {{ $yourBidClass }}">{{ $yourBidLabel }}</span>
                            @else
                                <button type="button" class="bidder-submit-trigger" data-target="bid-modal-{{ $project->id }}">Submit Bid</button>
                            @endif
                        </div>
                    </div>

                    <div class="bidder-available-body">
                        <p class="bidder-available-desc">{{ $project->description ?: 'No project description available.' }}</p>

                        <div class="bidder-project-scan">
                            <div class="bidder-project-scan-copy">
                                <span class="bidder-project-scan-title">Project QR</span>
                                <p class="bidder-project-scan-text">Scan this code to open the public project page on another device.</p>
                                <a href="{{ $project->scan_url }}" class="bidder-project-scan-link">
                                    Open project directly
                                    <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                </a>
                            </div>

                            <a href="{{ $project->scan_url }}" class="bidder-project-qr" aria-label="Open the project flow for {{ $project->title }}">
                                <img src="{{ $project->qr_code_data_uri }}" alt="QR code for {{ $project->title }} public project page">
                            </a>
                        </div>

                        <div class="bidder-available-footer">
                            <span>Deadline: <strong>{{ $project->deadline?->format('Y-m-d') ?? 'N/A' }}</strong></span>
                            <span>Posted: <strong>{{ $project->created_at?->format('Y-m-d') ?? 'N/A' }}</strong></span>
                        </div>
                    </div>
                </article>

                @if(! $myBid)
                    <div class="bidder-modal-overlay" id="bid-modal-{{ $project->id }}">
                        <div class="bidder-modal">
                            <div class="bidder-modal-header">
                                <div>
                                    <h2 class="bidder-modal-title">Submit Bid Proposal</h2>
                                </div>
                                <button type="button" class="bidder-modal-close" data-close-modal="bid-modal-{{ $project->id }}">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>

                            <div class="bidder-modal-body">
                                <div class="bidder-project-card">
                                    <h3 class="bidder-project-card-title">{{ $project->title }}</h3>
                                    <p class="bidder-project-card-meta">Approved Budget: P{{ number_format((float) $project->budget, 2) }} &middot; Deadline: {{ $project->deadline?->format('Y-m-d') ?? 'N/A' }}</p>
                                </div>

                                <form method="POST" action="{{ route('bidder.bids.store', $project) }}" enctype="multipart/form-data" class="bidder-bid-form" id="bid-form-{{ $project->id }}">
                                    @csrf
                                    <div class="bidder-field">
                                        <label>Bid Amount (P)</label>
                                        <input type="number" step="0.01" min="0" name="bid_amount" class="bidder-input" value="{{ old('bid_amount') }}" placeholder="Enter your bid amount" required>
                                    </div>

                                    <div class="bidder-field">
                                        <label>Bid Proposal Document</label>
                                        <label class="bidder-upload-box">
                                            <input type="file" name="proposal_file" accept=".pdf,.doc,.docx" data-upload-input>
                                            <i class="fas fa-upload bidder-upload-icon"></i>
                                            <span class="bidder-upload-title">Click to upload proposal file</span>
                                            <span class="bidder-upload-meta">PDF, DOC, DOCX up to 20MB</span>
                                            <span class="bidder-upload-selected">No file selected</span>
                                        </label>
                                    </div>

                                    <div class="bidder-field">
                                        <label>Technical Proposal Summary</label>
                                        <textarea name="notes" class="bidder-input bidder-textarea" placeholder="Brief overview of your technical approach and qualifications...">{{ old('notes') }}</textarea>
                                    </div>
                                </form>
                            </div>

                            <div class="bidder-modal-footer">
                                <button type="button" class="bidder-cancel-btn" data-close-modal="bid-modal-{{ $project->id }}">Cancel</button>
                                <button type="submit" form="bid-form-{{ $project->id }}" class="bidder-submit-btn">Submit Bid</button>
                            </div>
                        </div>
                    </div>
                @endif
            @empty
                <div class="bidder-empty">No open procurement projects are available right now.</div>
            @endforelse
        </main>
    </div>
</div>

@include('bidder.partials.project-scanner')

<script>
    (function () {
        const BID_SUCCESS_HIDE_DELAY = 5000;
        const BID_SUCCESS_FADE_DURATION = 350;
        const bidSubmitSuccess = document.getElementById('bidSubmitSuccess');
        const scannedProjectId = @json($scanProjectId);
        const cleanAvailableProjectsUrl = @json(route('bidder.available-projects'));

        if (bidSubmitSuccess) {
            const bidSubmitAlert = bidSubmitSuccess.querySelector('.bidder-success-alert');
            const delay = Number(bidSubmitSuccess.dataset.autoHide) || BID_SUCCESS_HIDE_DELAY;

            window.setTimeout(function () {
                if (bidSubmitAlert) {
                    bidSubmitAlert.classList.add('fade-out');
                }

                bidSubmitSuccess.classList.add('fade-out');

                window.setTimeout(function () {
                    bidSubmitSuccess.remove();
                }, BID_SUCCESS_FADE_DURATION);
            }, delay);
        }
        const realtimeDate = document.getElementById('realtimeDate');
        if (realtimeDate) {
            function updateRealtimeDate() {
                realtimeDate.textContent = new Date().toLocaleString('en-PH', {
                    month: 'short',
                    day: '2-digit',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit'
                });
            }

            updateRealtimeDate();
            setInterval(updateRealtimeDate, 1000);
        }

        document.querySelectorAll('.bidder-submit-trigger').forEach(function (button) {
            button.addEventListener('click', function () {
                const target = document.getElementById(button.dataset.target);
                if (!target) return;
                target.classList.add('show');
            });
        });

        document.querySelectorAll('[data-close-modal]').forEach(function (button) {
            button.addEventListener('click', function () {
                const target = document.getElementById(button.dataset.closeModal);
                if (!target) return;
                target.classList.remove('show');
            });
        });

        document.querySelectorAll('.bidder-modal-overlay').forEach(function (overlay) {
            overlay.addEventListener('click', function (event) {
                if (event.target === overlay) {
                    overlay.classList.remove('show');
                }
            });
        });

        document.querySelectorAll('[data-upload-input]').forEach(function (input) {
            input.addEventListener('change', function () {
                const uploadBox = input.closest('.bidder-upload-box');
                const selected = uploadBox ? uploadBox.querySelector('.bidder-upload-selected') : null;

                if (!selected) return;

                selected.textContent = input.files && input.files.length
                    ? input.files[0].name
                    : 'No file selected';
            });
        });

        if (scannedProjectId) {
            const projectCard = document.querySelector('[data-project-card][data-project-id="' + scannedProjectId + '"]');

            if (projectCard) {
                projectCard.classList.add('is-scanned-target');
                projectCard.scrollIntoView({ behavior: 'smooth', block: 'center' });

                const modalId = projectCard.dataset.projectModalId;
                if (modalId) {
                    const targetModal = document.getElementById(modalId);
                    if (targetModal) {
                        targetModal.classList.add('show');
                    }
                }
            }

            if (window.history && typeof window.history.replaceState === 'function') {
                window.history.replaceState({}, document.title, cleanAvailableProjectsUrl);
            }
        }
    })();
</script>







