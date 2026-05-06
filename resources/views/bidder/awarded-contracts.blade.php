<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home bidder-awards-page">
    @vite(['resources/css/dashboard.css'])

    <style>
        .bidder-awards-page {
            font-family: 'Inter', sans-serif;
        }

        .bidder-sidebar-badge {
            margin-left: auto;
        }

        .bidder-awards-page .page-intro {
            margin-bottom: 22px;
        }

        .bidder-awards-page .page-title {
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
        }

        .bidder-awards-page .page-subtitle {
            margin: 0;
            font-size: 13px;
            color: #94a3b8;
        }

        .bidder-panel {
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            overflow: hidden;
            margin-bottom: 18px;
        }

        .bidder-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .bidder-panel-header h2 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }

        .bidder-panel-subtext {
            font-size: 12px;
            color: #94a3b8;
        }

        .bidder-table-wrap {
            overflow-x: auto;
        }

        .bidder-table {
            width: 100%;
            border-collapse: collapse;
        }

        .bidder-table thead th {
            padding: 14px 18px;
            background: #f8fafc;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }

        .bidder-table tbody td {
            padding: 16px 18px;
            border-bottom: 1px solid #eef2f7;
            font-size: 12px;
            color: #0f172a;
            vertical-align: middle;
        }

        .bidder-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .bidder-awarded-amount {
            color: #047857;
            font-weight: 600;
        }

        .bidder-approved-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            background: #fef3c7;
            color: #b45309;
        }

        .bidder-await-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 32px;
            padding: 0 14px;
            border-radius: 10px;
            border: 1px solid #d97706;
            background: #d97706;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
        }

        .bidder-certificate-cell {
            min-width: 190px;
        }

        .bidder-certificate-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bidder-certificate-qr {
            display: inline-flex;
            width: 112px;
            height: 112px;
            padding: 8px;
            border: 1px solid #dbe4f0;
            border-radius: 12px;
            background: #fff;
            flex: 0 0 auto;
        }

        .bidder-certificate-qr img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .bidder-certificate-meta {
            min-width: 0;
        }

        .bidder-certificate-meta strong {
            display: block;
            margin-bottom: 4px;
            color: #0f172a;
            font-size: 11px;
            white-space: nowrap;
        }

        .bidder-certificate-meta span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .bidder-empty {
            padding: 26px 20px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>

        @include('partials.bidder-sidebar')

    <div class="main-area">
        <header class="navbar">
            <div class="nav-left">
                <h2>Award Contracts</h2>
                <p>View awarded contracts and projects awaiting final results</p>
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


            <section class="bidder-panel">
                <div class="bidder-panel-header">
                    <h2>Awarded Contracts</h2>
                </div>
                <div class="bidder-table-wrap">
                    <table class="bidder-table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Awardee</th>
                                <th>Awarded Amount</th>
                                <th>Date</th>
                                <th>Certificate QR</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($awardedProjects as $award)
                                @php
                                    $hasCertificate = $award->hasCertificateFile();
                                    $qrUrl = $award->tokenQrUrl();
                                @endphp
                                <tr>
                                    <td>{{ $award->project->title ?? 'N/A' }}</td>
                                    <td>{{ $award->bid->user->company ?? $award->bid->user->name ?? 'N/A' }}</td>
                                    <td class="bidder-awarded-amount">&#8369;{{ number_format((float) $award->contract_amount, 2) }}</td>
                                    <td>{{ $award->contract_date?->format('Y-m-d') ?? 'N/A' }}</td>
                                    <td class="bidder-certificate-cell">
                                        @if($hasCertificate)
                                            <div class="bidder-certificate-wrap">
                                                <div class="bidder-certificate-qr" aria-label="Scan QR code for official award certificate">
                                                    <img src="{{ $qrUrl }}" alt="QR code for official award certificate">
                                                </div>
                                                <div class="bidder-certificate-meta">
                                                    <strong>{{ $award->certificate_number }}</strong>
                                                    <span>Scan QR to view certificate</span>
                                                </div>
                                            </div>
                                        @else
                                            <span class="bidder-panel-subtext">Pending certificate</span>
                                        @endif
                                    </td>
                                    <td>{{ $award->notes ?: 'No notes provided.' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="bidder-empty">No awarded contracts yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="bidder-panel">
                <div class="bidder-panel-header">
                    <h2>Projects Awaiting Award</h2>
                    <span class="bidder-panel-subtext">Closed projects with submitted bids</span>
                </div>
                <div class="bidder-table-wrap">
                    <table class="bidder-table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Budget</th>
                                <th>Total Bids</th>
                                <th>Lowest Bid</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($awaitingAwardBids as $bid)
                                @php
                                    $lowestBid = $bid->project && $bid->project->bids()->exists()
                                        ? (float) $bid->project->bids()->min('bid_amount')
                                        : null;
                                @endphp
                                <tr>
                                    <td>{{ $bid->project->title ?? 'N/A' }}</td>
                                    <td>P{{ number_format((float) ($bid->project->budget ?? 0), 2) }}</td>
                                    <td><span class="bidder-approved-pill">{{ $bid->project->bids()->count() }} approved</span></td>
                                    <td>{{ $lowestBid !== null ? 'P' . number_format($lowestBid, 2) : '-' }}</td>
                                    <td><span class="bidder-await-btn">Awaiting Result</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="bidder-empty">No closed projects are currently awaiting award results.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
    (function () {
        const realtimeDate = document.getElementById('realtimeDate');
        if (!realtimeDate) return;

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
    })();
</script>
