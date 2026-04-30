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

        .bidder-empty {
            padding: 26px 20px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>

    <aside class="sidebar">
        <a href="{{ route('bidder.dashboard') }}" class="sidebar-logo-link"><h2 class="sidebar-logo">BAC-Office</h2></a>
        @include('partials.sidebar-profile')
        <ul class="sidebar-menu">
            <p class="menu-title">MAIN</p>
            <li><a href="{{ route('bidder.dashboard') }}"><span class="menu-icon-dashboard" aria-hidden="true"></span> Dashboard</a></li>
            <li><a href="{{ route('bidder.available-projects') }}"><i class="fas fa-folder-open"></i> Available Projects</a></li>
            <li><a href="{{ route('bidder.my-bids') }}"><i class="fas fa-file-signature"></i> My Bids</a></li>
            <li><a href="{{ route('bidder.awarded-contracts') }}" class="active"><i class="fas fa-award"></i> Awarded Contracts</a></li>

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
            <section class="page-intro">
                <h1 class="page-title">Award Contracts</h1>
                <p class="page-subtitle">View awarded contracts and projects awaiting final results</p>
            </section>

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
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($awardedProjects as $award)
                                <tr>
                                    <td>{{ $award->project->title ?? 'N/A' }}</td>
                                    <td>{{ $award->bid->user->company ?? $award->bid->user->name ?? 'N/A' }}</td>
                                    <td class="bidder-awarded-amount">P{{ number_format((float) $award->contract_amount, 2) }}</td>
                                    <td>{{ $award->contract_date?->format('Y-m-d') ?? 'N/A' }}</td>
                                    <td>{{ $award->notes ?: 'No notes provided.' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="bidder-empty">No awarded contracts yet.</td>
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


