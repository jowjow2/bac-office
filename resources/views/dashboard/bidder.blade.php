<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home bidder-dashboard-page">
    @vite(['resources/css/dashboard.css'])

    <style>
        .bidder-dashboard-page {
            font-family: 'Inter', sans-serif;
        }

        .bidder-sidebar-badge {
            margin-left: auto;
        }

        .bidder-dashboard-page .dashboard-home-intro {
            margin-bottom: 22px;
        }

        .bidder-dashboard-page .dashboard-home-title {
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
        }

        .bidder-dashboard-page .dashboard-home-subtitle {
            margin: 0;
            font-size: 13px;
            color: #94a3b8;
        }

        .bidder-award-banner {
            display: flex;
            align-items: center;
            gap: 16px;
            background: linear-gradient(135deg, #d48a00 0%, #f5a300 100%);
            border-radius: 16px;
            padding: 18px 24px;
            color: #fff;
            margin-bottom: 22px;
            box-shadow: 0 12px 28px rgba(217, 119, 6, 0.22);
        }

        .bidder-award-banner-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.18);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 23px;
            flex-shrink: 0;
        }

        .bidder-award-banner h2 {
            margin: 0 0 6px;
            font-size: 15px;
            font-weight: 700;
            color: #fff;
        }

        .bidder-award-banner p {
            margin: 0;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.95);
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
        .bidder-stat-icon.award { background: #fef3c7; color: #b45309; }

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

        .bidder-dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 18px;
        }

        .bidder-table-panel {
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .bidder-table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .bidder-table-header h2 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }

        .bidder-header-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 86px;
            height: 30px;
            padding: 0 12px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
        }

        .bidder-header-action.primary {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #fff;
        }

        .bidder-table-wrap {
            overflow-x: auto;
        }

        .bidder-table {
            width: 100%;
            border-collapse: collapse;
        }

        .bidder-table thead th {
            padding: 14px 20px;
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
            padding: 16px 20px;
            border-bottom: 1px solid #eef2f7;
            font-size: 12px;
            color: #0f172a;
            vertical-align: middle;
        }

        .bidder-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .bidder-project-title {
            font-size: 13px;
            font-weight: 600;
            color: #0f172a;
        }

        .bidder-project-files {
            margin-top: 10px;
            padding: 10px 12px;
            border: 1px solid #dbe4f0;
            border-radius: 12px;
            background: #f8fbff;
        }

        .bidder-project-files-compact {
            margin-bottom: 0;
        }

        .bidder-project-files-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
        }

        .bidder-project-files-title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #334155;
        }

        .bidder-project-files-count {
            font-size: 10px;
            color: #64748b;
        }

        .bidder-project-files-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .bidder-project-file-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            max-width: 100%;
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid #dbeafe;
            background: #ffffff;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 600;
            text-decoration: none;
        }

        .bidder-project-file-link:hover {
            color: #1e40af;
            border-color: #93c5fd;
        }

        .bidder-project-file-link span {
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .bidder-project-file-link.is-disabled {
            color: #94a3b8;
            border-color: #e2e8f0;
            cursor: default;
        }

        .bidder-status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
        }

        .bidder-status-pill.pending { background: #fff7ed; color: #c2410c; }
        .bidder-status-pill.approved { background: #dcfce7; color: #15803d; }
        .bidder-status-pill.rejected { background: #fee2e2; color: #b91c1c; }
        .bidder-status-pill.submitted { background: #dcfce7; color: #047857; }
        .bidder-status-pill.open { background: #fff7ed; color: #c2410c; }

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

            .bidder-dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .bidder-stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .bidder-award-banner,
            .bidder-table-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .bidder-stat-card {
                padding: 16px;
                gap: 12px;
            }

            .bidder-stat-icon {
                width: 44px;
                height: 44px;
                border-radius: 12px;
                font-size: 18px;
            }

            .bidder-stat-copy strong {
                font-size: 24px;
                margin-bottom: 4px;
            }

            .bidder-stat-copy h3 {
                font-size: 13px;
                margin-bottom: 3px;
            }

            .bidder-stat-copy p {
                font-size: 11px;
                line-height: 1.4;
            }
        }

        @media (max-width: 560px) {
            .bidder-stats-grid {
                grid-template-columns: 1fr;
            }

            .bidder-header-action {
                width: 100%;
            }
        }
    </style>

        @include('partials.bidder-sidebar')

    <div class="main-area">
        @php
            $bidderNavbarUser = auth()->user();
            $bidderNavbarName = $bidderNavbarUser?->company ?: ($bidderNavbarUser?->name ?? 'Bidder');
            $bidderNavbarRole = ucfirst($bidderNavbarUser?->role ?? 'bidder');
            $bidderNavbarInitials = collect(preg_split('/\s+/', trim($bidderNavbarName)))
                ->filter()
                ->take(2)
                ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
                ->implode('');
        @endphp
        <header class="navbar">
            <div class="nav-left">
                <h2>Bidder Dashboard</h2>
                <p>Track your bids and available procurement opportunities.</p>
            </div>

            <div class="nav-right">
                <div class="nav-icons">
                    <div class="nav-date-chip">
                        <i class="far fa-clock" aria-hidden="true"></i>
                        <span id="realtimeDate">{{ now()->format('M d, Y h:i A') }}</span>
                    </div>
                    <a href="{{ route('bidder.notifications') }}" class="notification-button" aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                        @if(($bidderNotificationCount ?? 0) > 0)
                            <span class="notification-badge">{{ $bidderNotificationCount }}</span>
                        @endif
                    </a>
                    <div class="navbar-profile-chip bidder-navbar-profile">
                        <span class="navbar-user-avatar">{{ $bidderNavbarInitials }}</span>
                        <span class="navbar-profile-meta">
                            <span class="navbar-profile-name">{{ $bidderNavbarName }}</span>
                            <span class="navbar-profile-role">{{ $bidderNavbarRole }}</span>
                        </span>
                    </div>
                </div>
            </div>
        </header>

        <main class="dashboard-content dashboard-home-content">


            @if($awardedProjects->isNotEmpty())
                @php $latestAward = $awardedProjects->first(); @endphp
                <section class="bidder-award-banner">
                    <div class="bidder-award-banner-icon"><i class="fas fa-trophy"></i></div>
                    <div>
                        <h2>Congratulations! You've been awarded a contract.</h2>
                        <p>{{ $latestAward->project->title ?? 'Awarded project' }} - P{{ number_format((float) $latestAward->contract_amount, 2) }}</p>
                    </div>
                </section>
            @endif

            <section class="bidder-stats-grid">
                <article class="bidder-stat-card">
                    <div class="bidder-stat-icon blue"><i class="fas fa-chart-column"></i></div>
                    <div class="bidder-stat-copy">
                        <strong>{{ $availableProjects->count() }}</strong>
                        <h3>Available Projects</h3>
                        <p>Open for bidding</p>
                    </div>
                </article>

                <article class="bidder-stat-card">
                    <div class="bidder-stat-icon gold"><i class="fas fa-square-check"></i></div>
                    <div class="bidder-stat-copy">
                        <strong>{{ $myBids->count() }}</strong>
                        <h3>My Bids</h3>
                        <p>Total submitted</p>
                    </div>
                </article>

                <article class="bidder-stat-card">
                    <div class="bidder-stat-icon green"><i class="fas fa-ribbon"></i></div>
                    <div class="bidder-stat-copy">
                        <strong>{{ $approvedBids }}</strong>
                        <h3>Approved Bids</h3>
                        <p>Cleared for evaluation</p>
                    </div>
                </article>

                <article class="bidder-stat-card">
                    <div class="bidder-stat-icon award"><i class="fas fa-award"></i></div>
                    <div class="bidder-stat-copy">
                        <strong>{{ $awardedProjects->count() }}</strong>
                        <h3>Awards Won</h3>
                        <p>Contracts awarded</p>
                    </div>
                </article>
            </section>

            <section class="bidder-dashboard-grid">
                <section class="bidder-table-panel" id="my-recent-bids">
                    <div class="bidder-table-header">
                        <h2>My Recent Bids</h2>
                        <a href="{{ route('bidder.my-bids') }}" class="bidder-header-action">View All</a>
                    </div>

                    <div class="bidder-table-wrap">
                        <table class="bidder-table">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($myBids->take(5) as $bid)
                                    <tr>
                                        <td class="bidder-project-title">{{ $bid->project->title ?? 'N/A' }}</td>
                                        <td>P{{ number_format((float) $bid->bid_amount, 2) }}</td>
                                        <td>{{ $bid->created_at?->format('Y-m-d') ?? 'N/A' }}</td>
                                        <td><span class="bidder-status-pill {{ $bid->status }}">{{ $bid->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="bidder-empty">No bids submitted yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="bidder-table-panel" id="available-projects">
                    <div class="bidder-table-header">
                        <h2>Open Projects</h2>
                        <a href="{{ route('bidder.available-projects') }}" class="bidder-header-action primary">Browse All</a>
                    </div>

                    <div class="bidder-table-wrap">
                        <table class="bidder-table">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Budget</th>
                                    <th>Deadline</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                    @forelse($availableProjects->take(5) as $project)
                                        @php $hasBid = $myBids->contains('project_id', $project->id); @endphp
                                    <tr>
                                        <td>
                                            <div class="bidder-project-title">{{ $project->title }}</div>
                                            @include('bidder.partials.project-documents', ['project' => $project, 'compact' => true])
                                        </td>
                                        <td>P{{ number_format((float) $project->budget, 2) }}</td>
                                        <td>{{ $project->deadline?->format('Y-m-d') ?? 'N/A' }}</td>
                                        <td>
                                            @if($hasBid)
                                                <span class="bidder-status-pill submitted">Bid Submitted</span>
                                            @else
                                                <span class="bidder-status-pill open">Open</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="bidder-empty">No open projects available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>
        </main>
    </div>
</div>

<script>
    (function () {
        const realtimeDate = document.getElementById('realtimeDate');
        if (!realtimeDate) return;

        function updateRealtimeDate() {
            const compact = window.innerWidth <= 560;
            realtimeDate.textContent = new Date().toLocaleString('en-PH', compact ? {
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            } : {
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


