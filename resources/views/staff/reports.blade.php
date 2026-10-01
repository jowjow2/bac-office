<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard staff-role-page dashboard-home admin-dashboard-page staff-dashboard staff-dashboard-page">
    @vite(['resources/css/dashboard.css'])

    @include('partials.staff-sidebar', ['activeStaffMenu' => 'reports'])

    <div class="main-area">
        <x-page-header title="Reports" subtitle="Reports on the projects assigned to you" />

        <main class="dashboard-content dashboard-home-content">
            <section class="dashboard-summary-grid">
                <article class="dashboard-summary-card">
                    <div class="summary-icon summary-icon-blue">
                        <i class="fas fa-chart-column"></i>
                    </div>
                    <div class="summary-copy">
                        <strong>P{{ number_format($totalBudgetAllocated, 2) }}</strong>
                        <h3>total Budget Allocated</h3>
                        <p>All projects</p>
                    </div>
                </article>

                <article class="dashboard-summary-card">
                    <div class="summary-icon summary-icon-green">
                        <i class="fas fa-award"></i>
                    </div>
                    <div class="summary-copy">
                        <strong>P{{ number_format($totalAwardedAmount, 2) }}</strong>
                        <h3>total Awarded</h3>
                        <p>Contracted amount</p>
                    </div>
                </article>

                <article class="dashboard-summary-card">
                    <div class="summary-icon summary-icon-gold">
                        <i class="fas fa-ribbon"></i>
                    </div>
                    <div class="summary-copy">
                        <strong>P{{ number_format($governmentSavings, 2) }}</strong>
                        <h3>Gov't Savings</h3>
                        <p>Budget vs. awarded</p>
                    </div>
                </article>

                <article class="dashboard-summary-card">
                    <div class="summary-icon summary-icon-orange">
                        <i class="fas fa-square-check"></i>
                    </div>
                    <div class="summary-copy">
                        <strong>{{ $bidParticipation }}</strong>
                        <h3>bid Participation</h3>
                        <p>total submissions</p>
                    </div>
                </article>
            </section>

            <section class="dashboard-main-grid">
                <section class="dashboard-panel dashboard-table-panel">
                    <div class="dashboard-panel-header">
                        <div>
                            <h2>Project summary Report</h2>
                            <p>Project status and Budget overview</p>
                        </div>
                        <a href="{{ route('staff.reports.print') }}" target="_blank" class="dashboard-panel-button">export pdf</a>
                    </div>

                    <div class="dashboard-table-wrap">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Budget</th>
                                    <th>Bids</th>
                                    <th>Awarded</th>
                                    <th>status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignedProjects as $project)
                                    <tr>
                                        <td><strong>{{ $project->title }}</strong></td>
                                        <td>P{{ number_format((float) $project->budget, 2) }}</td>
                                        <td>{{ $project->bids_count }}</td>
                                        <td>{{ $project->status === 'awarded' ? 'Yes' : 'no' }}</td>
                                        <td><span class="dashboard-badge dashboard-badge-{{ $project->status }}">{{ $project->status }}</span></td>
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
                            <h2>bidder performance</h2>
                            <p>Submission and approval summary</p>
                        </div>
                        <a href="{{ route('staff.reports.export.csv') }}" class="dashboard-panel-button">export Excel</a>
                    </div>

                    <div class="dashboard-table-wrap">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>bidder</th>
                                    <th>total Bids</th>
                                    <th>Approved</th>
                                    <th>Won</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($bidderPerformance as $bidder)
                                    <tr>
                                        <td><strong>{{ $bidder['bidder'] }}</strong></td>
                                        <td>{{ $bidder['total_bids'] }}</td>
                                        <td>{{ $bidder['approved'] }}</td>
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
                                        <td colspan="4" class="empty-cell">no bidder performance data available.</td>
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