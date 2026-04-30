<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home bidder-my-bids-page">
    @vite(['resources/css/dashboard.css'])

    <style>
        .bidder-my-bids-page {
            font-family: 'Inter', sans-serif;
        }

        .bidder-sidebar-badge {
            margin-left: auto;
        }

        .bidder-my-bids-page .page-intro {
            margin-bottom: 22px;
        }

        .bidder-my-bids-page .page-title {
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
        }

        .bidder-my-bids-page .page-subtitle {
            margin: 0;
            font-size: 13px;
            color: #94a3b8;
        }

        .bidder-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .bidder-stat-card {
            display: flex;
            align-items: center;
            gap: 16px;
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            padding: 20px 22px;
        }

        .bidder-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .bidder-stat-icon.blue { background: #dbeafe; color: #2563eb; }
        .bidder-stat-icon.gold { background: #fef3c7; color: #d97706; }
        .bidder-stat-icon.green { background: #dcfce7; color: #15803d; }
        .bidder-stat-icon.red { background: #fee2e2; color: #dc2626; }

        .bidder-stat-copy strong {
            display: block;
            font-size: 21px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
            line-height: 1;
        }

        .bidder-stat-copy h3 {
            margin: 0 0 4px;
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
        }

        .bidder-stat-copy p {
            margin: 0;
            font-size: 10px;
            color: #94a3b8;
        }

        .bidder-table-panel {
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            overflow: hidden;
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

        .bidder-project-title {
            display: block;
            margin-bottom: 4px;
            font-size: 13px;
            font-weight: 600;
            color: #0f172a;
        }

        .bidder-project-subtitle {
            font-size: 12px;
            color: #94a3b8;
        }

        .bidder-variance-positive {
            color: #dc2626;
        }

        .bidder-variance-negative {
            color: #047857;
        }

        .bidder-doc-link {
            color: #1d4ed8;
            text-decoration: underline;
            word-break: break-all;
        }

        .bidder-status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            text-transform: lowercase;
        }

        .bidder-status-pill.pending { background: #fef3c7; color: #b45309; }
        .bidder-status-pill.approved { background: #dcfce7; color: #15803d; }
        .bidder-status-pill.rejected { background: #fee2e2; color: #b91c1c; }

        .bidder-empty {
            padding: 26px 20px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }

        @media (max-width: 1200px) {
            .bidder-stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .bidder-stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <aside class="sidebar">
        <a href="{{ route('bidder.dashboard') }}" class="sidebar-logo-link"><h2 class="sidebar-logo">BAC-Office</h2></a>
        @include('partials.sidebar-profile')
        <ul class="sidebar-menu">
            <p class="menu-title">MAIN</p>
            <li><a href="{{ route('bidder.dashboard') }}"><span class="menu-icon-dashboard" aria-hidden="true"></span> Dashboard</a></li>
            <li><a href="{{ route('bidder.available-projects') }}"><i class="fas fa-folder-open"></i> Available Projects</a></li>
            <li><a href="{{ route('bidder.my-bids') }}" class="active"><i class="fas fa-file-signature"></i> My Bids</a></li>
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
                <h2>My Bids</h2>
                <p>Track all your submitted bid proposals</p>
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
                <h1 class="page-title">My Bids</h1>
                <p class="page-subtitle">Track all your submitted bid proposals</p>
            </section>

            <section class="bidder-stats-grid">
                <article class="bidder-stat-card">
                    <div class="bidder-stat-icon blue"><i class="fas fa-square-check"></i></div>
                    <div class="bidder-stat-copy">
                        <strong>{{ $myBids->count() }}</strong>
                        <h3>Total Bids</h3>
                        <p>Submitted</p>
                    </div>
                </article>

                <article class="bidder-stat-card">
                    <div class="bidder-stat-icon gold"><i class="fas fa-pen-to-square"></i></div>
                    <div class="bidder-stat-copy">
                        <strong>{{ $pendingBids }}</strong>
                        <h3>Pending</h3>
                        <p>Under review</p>
                    </div>
                </article>

                <article class="bidder-stat-card">
                    <div class="bidder-stat-icon green"><i class="fas fa-check-double"></i></div>
                    <div class="bidder-stat-copy">
                        <strong>{{ $approvedBids }}</strong>
                        <h3>Approved</h3>
                        <p>For evaluation</p>
                    </div>
                </article>

                <article class="bidder-stat-card">
                    <div class="bidder-stat-icon red"><i class="fas fa-xmark"></i></div>
                    <div class="bidder-stat-copy">
                        <strong>{{ $rejectedBids }}</strong>
                        <h3>Rejected</h3>
                        <p>Unsuccessful</p>
                    </div>
                </article>
            </section>

            <section class="bidder-table-panel">
                <div class="bidder-table-wrap">
                    <table class="bidder-table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Budget</th>
                                <th>My Bid</th>
                                <th>Variance</th>
                                <th>Submitted</th>
                                <th>Document</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($myBids as $bid)
                                @php
                                    $budget = (float) ($bid->project->budget ?? 0);
                                    $bidAmount = (float) $bid->bid_amount;
                                    $variance = $budget > 0 ? (($bidAmount - $budget) / $budget) * 100 : null;
                                    $statusText = $bid->project?->status === 'awarded' ? 'Awarded' : ($bid->project?->status === 'closed' ? 'Bidding closed' : 'Bidding open');
                                @endphp
                                <tr>
                                    <td>
                                        <span class="bidder-project-title">{{ $bid->project->title ?? 'N/A' }}</span>
                                        <span class="bidder-project-subtitle">{{ $statusText }}</span>
                                    </td>
                                    <td>P{{ number_format($budget, 2) }}</td>
                                    <td>P{{ number_format($bidAmount, 2) }}</td>
                                    <td>
                                        @if($variance !== null)
                                            <span class="{{ $variance > 0 ? 'bidder-variance-positive' : 'bidder-variance-negative' }}">
                                                {{ $variance > 0 ? '+' : '' }}{{ number_format($variance, 1) }}%
                                            </span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $bid->created_at?->format('Y-m-d') ?? 'N/A' }}</td>
                                    <td>
                                        @if($bid->proposal_file)
                                            <a href="{{ asset($bid->proposal_file) }}" target="_blank" class="bidder-doc-link">{{ basename($bid->proposal_file) }}</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td><span class="bidder-status-pill {{ $bid->status }}">{{ $bid->status }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="bidder-empty">No bids submitted yet.</td>
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



