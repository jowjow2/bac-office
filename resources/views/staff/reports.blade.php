<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard staff-role-page dashboard-home admin-dashboard-page staff-dashboard staff-dashboard-page">
    @vite(['resources/css/dashboard.css'])

    @include('partials.staff-sidebar', ['activeStaffMenu' => 'reports'])

    <style>
        .staff-report-status { display: inline-flex; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .staff-report-status.is-success { background: #e7f5ec; color: #166534; }
        .staff-report-status.is-warning { background: #fdf3e1; color: #92400e; }
        .staff-report-status.is-danger { background: #fdecec; color: #b91c1c; }
        .staff-report-status.is-info { background: #e8f0fb; color: #1e4f8f; }
        .staff-report-status.is-neutral { background: var(--ui-surface-2); color: var(--ui-muted); }
        .staff-dashboard-page .dashboard-table td { white-space: normal; }
        .staff-dashboard-page .dashboard-panel-button { display: inline-flex; align-items: center; gap: 8px; }
    </style>

    <div class="main-area">
        <x-page-header title="Reports" subtitle="Reports on the projects assigned to you" />

        <main class="dashboard-content dashboard-home-content">
            <section class="dashboard-summary-grid">
                <article class="dashboard-summary-card">
                    <div class="summary-icon summary-icon-blue">
                        <i class="fas fa-chart-column"></i>
                    </div>
                    <div class="summary-copy">
                        <strong>&#8369;{{ number_format($totalBudgetAllocated, 2) }}</strong>
                        <h3>Total ABC</h3>
                        <p>Projects assigned to you</p>
                    </div>
                </article>

                <article class="dashboard-summary-card">
                    <div class="summary-icon summary-icon-green">
                        <i class="fas fa-award"></i>
                    </div>
                    <div class="summary-copy">
                        <strong>&#8369;{{ number_format($totalAwardedAmount, 2) }}</strong>
                        <h3>Total awarded</h3>
                        <p>Contract amounts of awards in force</p>
                    </div>
                </article>

                <article class="dashboard-summary-card">
                    <div class="summary-icon summary-icon-gold">
                        <i class="fas fa-ribbon"></i>
                    </div>
                    <div class="summary-copy">
                        <strong>&#8369;{{ number_format($governmentSavings, 2) }}</strong>
                        <h3>Government savings</h3>
                        <p>ABC of awarded projects less award amounts</p>
                    </div>
                </article>

                <article class="dashboard-summary-card">
                    <div class="summary-icon summary-icon-orange">
                        <i class="fas fa-square-check"></i>
                    </div>
                    <div class="summary-copy">
                        <strong>{{ $bidParticipation }}</strong>
                        <h3>Bid participation</h3>
                        <p>Official submissions (drafts excluded)</p>
                    </div>
                </article>
            </section>

            <section class="dashboard-main-grid">
                <section class="dashboard-panel dashboard-table-panel">
                    <div class="dashboard-panel-header">
                        <div>
                            <h2>Project summary</h2>
                            <p>Status and ABC of each assigned project</p>
                        </div>
                        <a href="{{ route('staff.reports.print') }}" target="_blank" class="dashboard-panel-button"><i class="fas fa-file-pdf" aria-hidden="true"></i> Export PDF</a>
                    </div>

                    <div class="dashboard-table-wrap">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>ABC</th>
                                    <th>Bids</th>
                                    <th>Awarded</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignedProjects as $project)
                                    <tr>
                                        <td><strong>{{ $project->title }}</strong></td>
                                        <td style="white-space: nowrap">&#8369;{{ number_format((float) $project->budget, 2) }}</td>
                                        <td>{{ $project->bids_count }}</td>
                                        <td>{{ $project->status === 'awarded' ? 'Yes' : 'No' }}</td>
                                        @php $portalStatus = $project->portalStatus(); @endphp
                                        <td><span class="staff-report-status is-{{ $portalStatus['tone'] }}">{{ $portalStatus['label'] }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="empty-cell">No project data available for reporting.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="dashboard-panel dashboard-table-panel">
                    <div class="dashboard-panel-header">
                        <div>
                            <h2>Bidder performance</h2>
                            <p>Official bids, preliminary results and awards</p>
                        </div>
                        <a href="{{ route('staff.reports.export.csv') }}" class="dashboard-panel-button"><i class="fas fa-file-csv" aria-hidden="true"></i> Export CSV</a>
                    </div>

                    <div class="dashboard-table-wrap">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Bidder</th>
                                    <th>Bids</th>
                                    <th>Passed preliminary</th>
                                    <th>Won</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($bidderPerformance as $bidder)
                                    <tr>
                                        <td><strong>{{ $bidder['bidder'] }}</strong></td>
                                        <td>{{ $bidder['total_bids'] }}</td>
                                        <td>{{ $bidder['passed'] }}</td>
                                        <td>
                                            @if($bidder['won'] > 0)
                                                <span class="dashboard-badge dashboard-badge-approved">{{ $bidder['won'] }} Won</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="empty-cell">No official bids on your assigned projects yet.</td>
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