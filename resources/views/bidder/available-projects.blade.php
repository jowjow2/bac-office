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

        .bidder-project-files {
            margin-bottom: 16px;
            padding: 14px 16px;
            border: 1px solid #dbe4f0;
            border-radius: 14px;
            background: #f8fbff;
        }

        .bidder-project-files-compact {
            margin-top: 14px;
            margin-bottom: 0;
            padding: 12px 14px;
            border-radius: 12px;
            background: #ffffff;
        }

        .bidder-project-files-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }

        .bidder-project-files-title {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .bidder-project-files-count {
            font-size: 11px;
            color: #64748b;
        }

        .bidder-project-files-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .bidder-project-file-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            max-width: 100%;
            padding: 9px 12px;
            border-radius: 999px;
            border: 1px solid #dbeafe;
            background: #ffffff;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
        }

        .bidder-project-file-link:hover {
            border-color: #93c5fd;
            color: #1e40af;
        }

        .bidder-project-file-link span {
            max-width: 240px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .bidder-project-file-link.is-disabled {
            color: #94a3b8;
            border-color: #e2e8f0;
            cursor: default;
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
            padding: 28px;
            background: rgba(15, 23, 42, 0.58);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 2000;
        }

        .bidder-modal-overlay.show {
            display: flex;
        }

        .bidder-modal {
            width: min(100%, 720px);
            max-height: calc(100vh - 56px);
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 24px;
            box-shadow: 0 28px 70px rgba(15, 23, 42, 0.3);
            overflow: auto;
        }

        .bidder-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 24px 30px 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .bidder-modal-title {
            margin: 0 0 6px;
            font-size: 24px;
            font-weight: 800;
            line-height: 1.2;
            color: #0f172a;
            letter-spacing: -0.01em;
        }

        .bidder-modal-subtitle {
            margin: 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        .bidder-modal-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border: 0;
            border-radius: 14px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 17px;
            cursor: pointer;
        }

        .bidder-modal-close:hover {
            background: #e2e8f0;
            color: #334155;
        }

        .bidder-modal-body {
            padding: 24px 30px 22px;
        }

        .bidder-project-card {
            margin-bottom: 22px;
            padding: 18px;
            border: 1px solid #dbeafe;
            border-radius: 18px;
            background: linear-gradient(135deg, #eff6ff 0%, #f8fbff 100%);
        }

        .bidder-project-card-title {
            margin: 0 0 12px;
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.3;
        }

        .bidder-project-card-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .bidder-project-stat {
            padding: 10px 12px;
            border: 1px solid #dbeafe;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.78);
        }

        .bidder-project-stat span {
            display: block;
            margin-bottom: 3px;
            font-size: 10px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .bidder-project-stat strong {
            display: block;
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
        }

        .bidder-project-card-note {
            margin: 0;
            margin-top: 12px;
            font-size: 12px;
            line-height: 1.55;
            color: #6b7280;
        }

        .bidder-requirements-card {
            margin-top: 14px;
            padding: 14px;
            border: 1px solid #fed7aa;
            border-radius: 14px;
            background: #fff7ed;
        }

        .bidder-requirements-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }

        .bidder-requirements-heading h4 {
            margin: 0 0 3px;
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
        }

        .bidder-requirements-heading p {
            margin: 0;
            font-size: 11px;
            color: #9a3412;
            line-height: 1.5;
        }

        .bidder-requirements-icon {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #ffedd5;
            color: #ea580c;
        }

        .bidder-required-docs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
        }

        .bidder-required-doc {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 10px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid #fed7aa;
            color: #9a3412;
            font-size: 11px;
            font-weight: 700;
        }

        .bidder-requirements-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .bidder-requirement-section {
            padding: 11px 12px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.86);
            border: 1px solid #fed7aa;
        }

        .bidder-requirement-section strong {
            display: block;
            margin-bottom: 5px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #c2410c;
        }

        .bidder-requirement-section p {
            margin: 0;
            font-size: 12px;
            line-height: 1.55;
            color: #475569;
            white-space: pre-line;
        }

        .bidder-bid-form {
            display: grid;
            gap: 20px;
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

        .bidder-field-hint {
            margin-top: -2px;
            font-size: 11px;
            color: #94a3b8;
        }

        .bidder-money-input {
            position: relative;
        }

        .bidder-money-prefix {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 14px;
            font-weight: 800;
            pointer-events: none;
        }

        .bidder-input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            background: #fff;
            color: #111827;
            font-size: 14px;
            font-weight: 400;
            padding: 13px 15px;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .bidder-money-input .bidder-input {
            padding-left: 34px;
        }

        .bidder-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
            outline: none;
        }

        .bidder-input::placeholder {
            color: #9ca3af;
            font-size: 14px;
        }

        .bidder-upload-box {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 58px;
            padding: 11px 13px;
            border: 1px dashed #bfdbfe;
            border-radius: 14px;
            background: #f8fbff;
            text-align: left;
            overflow: hidden;
            cursor: pointer;
            transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
        }

        .bidder-upload-box input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .bidder-upload-box:hover,
        .bidder-upload-box.has-file {
            border-color: #2563eb;
            background: #eff6ff;
            box-shadow: 0 12px 28px rgba(37, 99, 235, 0.08);
        }

        .bidder-upload-icon {
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #ffffff;
            color: #2563eb;
            font-size: 16px;
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.1);
        }

        .bidder-upload-copy {
            min-width: 0;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .bidder-upload-title {
            font-size: 12px;
            font-weight: 800;
            color: #334155;
        }

        .bidder-upload-meta {
            font-size: 11px;
            color: #94a3b8;
        }

        .bidder-upload-selected {
            max-width: 220px;
            flex: 0 1 auto;
            padding: 7px 11px;
            border-radius: 999px;
            background: #ffffff;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .bidder-upload-box.has-file .bidder-upload-selected {
            color: #1d4ed8;
        }

        .bidder-textarea {
            min-height: 104px;
            resize: vertical;
            line-height: 1.5;
        }

        .bidder-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 0 30px 28px;
        }

        .bidder-cancel-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 20px;
            border-radius: 14px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }

        .bidder-modal-footer .bidder-submit-btn,
        .bidder-modal-footer .bidder-cancel-btn {
            min-width: 120px;
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

            .bidder-modal {
                width: 100%;
                max-height: calc(100vh - 32px);
            }
        }

        @media (max-width: 640px) {
            .bidder-modal-overlay {
                padding: 14px;
                align-items: flex-end;
            }

            .bidder-modal {
                border-radius: 22px 22px 0 0;
            }

            .bidder-modal-header,
            .bidder-modal-body,
            .bidder-modal-footer {
                padding-left: 18px;
                padding-right: 18px;
            }

            .bidder-project-card-stats {
                grid-template-columns: 1fr;
            }

            .bidder-requirements-grid {
                grid-template-columns: 1fr;
            }

            .bidder-modal-footer {
                flex-direction: column-reverse;
            }

            .bidder-modal-footer .bidder-submit-btn,
            .bidder-modal-footer .bidder-cancel-btn {
                width: 100%;
            }
        }
    </style>

        @include('partials.bidder-sidebar')

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

            @if($errors->any())
                <div class="bidder-alert bidder-alert-error">{{ $errors->first() }}</div>
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

                <article class="bidder-available-card">
                    <div class="bidder-available-header">
                        <div>
                            <h2>{{ $project->title }}</h2>
                            <p class="bidder-available-meta">Approved Budget: ₱{{ number_format((float) $project->budget, 2) }} &middot; {{ $project->deadline && $project->deadline->isPast() ? 'Deadline passed' : 'Open for bidding' }}</p>
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

                        @include('bidder.partials.project-documents', ['project' => $project])

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
                                    <p class="bidder-modal-subtitle">Review the project details, enter your offer, and attach your proposal document.</p>
                                </div>
                                <button type="button" class="bidder-modal-close" data-close-modal="bid-modal-{{ $project->id }}">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>

                            <div class="bidder-modal-body">
                                <div class="bidder-project-card">
                                    <h3 class="bidder-project-card-title">{{ $project->title }}</h3>
                                    <div class="bidder-project-card-stats">
                                        <div class="bidder-project-stat">
                                            <span>Approved Budget</span>
                                            <strong>₱{{ number_format((float) $project->budget, 2) }}</strong>
                                        </div>
                                        <div class="bidder-project-stat">
                                            <span>Deadline</span>
                                            <strong>{{ $project->deadline?->format('Y-m-d') ?? 'N/A' }}</strong>
                                        </div>
                                    </div>

                                    @include('bidder.partials.project-documents', ['project' => $project, 'compact' => true])
                                    @include('bidder.partials.project-requirements', ['project' => $project])
                                </div>

                                <form method="POST" action="{{ route('bidder.bids.store', $project) }}" enctype="multipart/form-data" class="bidder-bid-form" id="bid-form-{{ $project->id }}">
                                    @csrf
                                    <div class="bidder-field">
                                        <label>Bid Amount (₱)</label>
                                        <div class="bidder-money-input">
                                            <span class="bidder-money-prefix">₱</span>
                                            <input type="number" step="0.01" min="0" name="bid_amount" class="bidder-input" value="{{ old('bid_amount') }}" placeholder="Enter your bid amount" required>
                                        </div>
                                    </div>

                                    <div class="bidder-field">
                                        <label>Document of Eligibility</label>
                                        <span class="bidder-field-hint">Upload your eligibility document for this bid.</span>
                                        <label class="bidder-upload-box">
                                            <input type="file" name="eligibility_file" accept=".pdf,.doc,.docx" data-upload-input required>
                                            <i class="fas fa-upload bidder-upload-icon"></i>
                                            <span class="bidder-upload-copy">
                                                <span class="bidder-upload-title">Choose eligibility file</span>
                                                <span class="bidder-upload-meta">PDF, DOC, DOCX up to 20MB</span>
                                            </span>
                                            <span class="bidder-upload-selected">No file selected</span>
                                        </label>
                                    </div>

                                    <div class="bidder-field">
                                        <label>Bid Proposal Document</label>
                                        <span class="bidder-field-hint">Upload the signed proposal file for this project.</span>
                                        <label class="bidder-upload-box">
                                            <input type="file" name="proposal_file" accept=".pdf,.doc,.docx" data-upload-input required>
                                            <i class="fas fa-file-arrow-up bidder-upload-icon"></i>
                                            <span class="bidder-upload-copy">
                                                <span class="bidder-upload-title">Choose proposal file</span>
                                                <span class="bidder-upload-meta">PDF, DOC, DOCX up to 20MB</span>
                                            </span>
                                            <span class="bidder-upload-selected">No file selected</span>
                                        </label>
                                    </div>

                                    <div class="bidder-field">
                                        <label>Technical Proposal Summary</label>
                                        <span class="bidder-field-hint">Optional summary for evaluators to review alongside your file.</span>
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

<script>
    (function () {
        const BID_SUCCESS_HIDE_DELAY = 5000;
        const BID_SUCCESS_FADE_DURATION = 350;
        const bidSubmitSuccess = document.getElementById('bidSubmitSuccess');

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

                if (uploadBox) {
                    uploadBox.classList.toggle('has-file', Boolean(input.files && input.files.length));
                }
            });
        });
    })();
</script>







