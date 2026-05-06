<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard">
    @vite(['resources/css/dashboard.css'])

    <style>
        .fas,
        .far,
        .fab,
        .fa-solid,
        .fa-regular,
        .fa-brands {
            font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands" !important;
        }

        .fas,
        .far,
        .fa-solid,
        .fa-regular {
            font-weight: 900 !important;
        }
    </style>

    @include('partials.admin-sidebar')

    <div class="main-area">
        <header class="navbar">
            <div class="nav-left">
                <h2>Reports & Analytics</h2>
                <p>Export and view reports</p>
            </div>

            <div class="nav-right">
                <div class="report-toolbar">
                    <a href="{{ route('admin.notifications') }}" class="notification-button" aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                        @if(($unreadNotificationsCount ?? 0) > 0)
                            <span class="notification-badge">{{ $unreadNotificationsCount }}</span>
                        @endif
                    </a>
                </div>
            </div>
        </header>

        <main class="dashboard-content reports-page">


            <section class="report-kpi-grid">
                <article class="report-kpi-card">
                    <div class="report-kpi-icon report-kpi-blue">
                        <i class="fas fa-chart-column"></i>
                    </div>
                    <div class="report-kpi-content">
                        <h3>P{{ number_format($totalBudgetAllocated, 2) }}</h3>
                        <p>Total Budget Allocated</p>
                        <span>All projects</span>
                    </div>
                </article>

                <article class="report-kpi-card">
                    <div class="report-kpi-icon report-kpi-green">
                        <i class="fas fa-award"></i>
                    </div>
                    <div class="report-kpi-content">
                        <h3>P{{ number_format($totalAwardedAmount, 2) }}</h3>
                        <p>Total Awarded</p>
                        <span>Contracted amount</span>
                    </div>
                </article>

                <article class="report-kpi-card">
                    <div class="report-kpi-icon report-kpi-gold">
                        <i class="fas fa-medal"></i>
                    </div>
                    <div class="report-kpi-content">
                        <h3>P{{ number_format($governmentSavings, 2) }}</h3>
                        <p>Gov't Savings</p>
                        <span>Budget vs. awarded</span>
                    </div>
                </article>

                <article class="report-kpi-card report-kpi-slim">
                    <div class="report-kpi-icon report-kpi-sky">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="report-kpi-content">
                        <h3>{{ $bidParticipation }}</h3>
                        <p>Bid Participation</p>
                        <span>Total submissions</span>
                    </div>
                </article>
            </section>

            <section class="report-dual-grid">
                <article class="panel report-panel">
                    <div class="panel-header">
                        <div>
                            <h2>Project Summary Report</h2>
                        </div>
                        <a href="{{ route('admin.reports.print') }}" target="_blank" class="btn-secondary">Export PDF</a>
                    </div>

                    <div class="data-table-wrap">
                        <table class="data-table report-table">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Budget</th>
                                    <th>Bids</th>
                                    <th>Awarded</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($projectSummary as $project)
                                    <tr>
                                        <td>{{ $project->title }}</td>
                                        <td>P{{ number_format((float) $project->budget, 2) }}</td>
                                        <td>{{ $project->bids_count }}</td>
                                        <td>
                                            @if((float) $project->awarded_amount > 0)
                                                P{{ number_format((float) $project->awarded_amount, 2) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td><span class="status-pill status-{{ $project->status }}">{{ \Illuminate\Support\Str::headline($project->status) }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="empty-cell">No projects available yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="panel report-panel">
                    <div class="panel-header">
                        <div>
                            <h2>Bidder Performance</h2>
                        </div>
                        <a href="{{ route('admin.reports.export.csv') }}" class="btn-secondary">Export Excel</a>
                    </div>

                    <div class="data-table-wrap">
                        <table class="data-table report-table">
                            <thead>
                                <tr>
                                    <th>Bidder</th>
                                    <th>Total Bids</th>
                                    <th>Approved</th>
                                    <th>Won</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($bidderPerformance as $bidder)
                                    <tr>
                                        <td>{{ $bidder->bidder_name }}</td>
                                        <td>{{ $bidder->total_bids }}</td>
                                        <td>{{ $bidder->approved_bids }}</td>
                                        <td>
                                            @if((int) $bidder->won_bids > 0)
                                                <span class="report-badge">{{ $bidder->won_bids }} won</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="empty-cell">No bidder performance data yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>

            <section class="report-dual-grid">
                <article class="panel report-panel">
                    <div class="panel-header">
                        <div>
                            <h2>Operations Snapshot</h2>
                            <p>Live counts for projects, bids, awards, and user activity.</p>
                        </div>
                    </div>

                    <div class="mini-stats">
                        <div class="mini-stat">
                            <span class="mini-label">Projects</span>
                            <strong>{{ $totalProjects }}</strong>
                        </div>
                        <div class="mini-stat">
                            <span class="mini-label">Approved for Bidding</span>
                            <strong>{{ $projectStatusCounts['approved_for_bidding'] }}</strong>
                        </div>
                        <div class="mini-stat">
                            <span class="mini-label">Open Projects</span>
                            <strong>{{ $projectStatusCounts['open'] }}</strong>
                        </div>
                        <div class="mini-stat">
                            <span class="mini-label">Closed Projects</span>
                            <strong>{{ $projectStatusCounts['closed'] }}</strong>
                        </div>
                        <div class="mini-stat">
                            <span class="mini-label">Awarded Projects</span>
                            <strong>{{ $projectStatusCounts['awarded'] }}</strong>
                        </div>
                        <div class="mini-stat">
                            <span class="mini-label">Pending Bids</span>
                            <strong>{{ $bidStatusCounts['pending'] }}</strong>
                        </div>
                        <div class="mini-stat">
                            <span class="mini-label">Approved Bids</span>
                            <strong>{{ $bidStatusCounts['approved'] }}</strong>
                        </div>
                    </div>
                </article>

                <article class="panel report-panel">
                    <div class="panel-header">
                        <div>
                            <h2>Staff & Accounts</h2>
                            <p>Role distribution and workload in one panel.</p>
                        </div>
                    </div>

                    <div class="mini-stats">
                        <div class="mini-stat">
                            <span class="mini-label">Users</span>
                            <strong>{{ $totalUsers }}</strong>
                        </div>
                        <div class="mini-stat">
                            <span class="mini-label">Admins</span>
                            <strong>{{ $userRoleCounts['admin'] }}</strong>
                        </div>
                        <div class="mini-stat">
                            <span class="mini-label">Staff</span>
                            <strong>{{ $userRoleCounts['staff'] }}</strong>
                        </div>
                        <div class="mini-stat">
                            <span class="mini-label">Bidders</span>
                            <strong>{{ $userRoleCounts['bidder'] }}</strong>
                        </div>
                        <div class="mini-stat">
                            <span class="mini-label">Assignments</span>
                            <strong>{{ $totalAssignments }}</strong>
                        </div>
                        <div class="mini-stat">
                            <span class="mini-label">Staffed Projects</span>
                            <strong>{{ $projectsWithAssignments }}</strong>
                        </div>
                    </div>
                </article>
            </section>
        </main>
    </div>
</div>
