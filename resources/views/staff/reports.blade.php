<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home">
    @vite(['resources/css/dashboard.css'])
    @include('partials.staff-page-styles')

    <style>
        .staff-reports-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .staff-reports-kpi-card {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px 22px;
            background: #ffffff;
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }

        .staff-reports-kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .staff-reports-kpi-icon.blue {
            background: #dbeafe;
            color: #2563eb;
        }

        .staff-reports-kpi-icon.green {
            background: #dcfce7;
            color: #15803d;
        }

        .staff-reports-kpi-icon.gold {
            background: #fef3c7;
            color: #d97706;
        }

        .staff-reports-kpi-icon.sky {
            background: #e0f2fe;
            color: #0369a1;
        }

        .staff-reports-kpi-copy strong {
            display: block;
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .staff-reports-kpi-copy h3 {
            margin: 0 0 4px;
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
        }

        .staff-reports-kpi-copy p {
            margin: 0;
            font-size: 10px;
            color: #94a3b8;
        }

        .staff-reports-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 18px;
        }

        .staff-report-export {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 96px;
            height: 30px;
            padding: 0 12px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #1e3a8a;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
        }

        .staff-report-won {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 44px;
            min-height: 32px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #fef3c7;
            color: #b45309;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.2;
            text-align: center;
        }

        @media (max-width: 1200px) {
            .staff-reports-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .staff-reports-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .staff-reports-kpi-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    @include('partials.staff-sidebar', ['activeStaffMenu' => 'reports'])

    <div class="main-area">
        @include('partials.staff-topbar', [
            'staffNavbarTitle' => 'Reports',
            'staffNavbarSubtitle' => 'Limited staff reports',
        ])

        <main class="dashboard-content dashboard-home-content">
            <section class="staff-dashboard">
                <section class="staff-page-intro">
                    <h1 class="staff-page-title">Reports &amp; Analytics</h1>
                    <p class="staff-page-subtitle">Generate and export procurement reports</p>
                </section>

                <section class="staff-reports-kpi-grid">
                    <article class="staff-reports-kpi-card">
                        <div class="staff-reports-kpi-icon blue"><i class="fas fa-chart-column"></i></div>
                        <div class="staff-reports-kpi-copy">
                            <strong>P{{ number_format($totalBudgetAllocated, 2) }}</strong>
                            <h3>Total Budget Allocated</h3>
                            <p>All projects</p>
                        </div>
                    </article>

                    <article class="staff-reports-kpi-card">
                        <div class="staff-reports-kpi-icon green"><i class="fas fa-award"></i></div>
                        <div class="staff-reports-kpi-copy">
                            <strong>P{{ number_format($totalAwardedAmount, 2) }}</strong>
                            <h3>Total Awarded</h3>
                            <p>Contracted amount</p>
                        </div>
                    </article>

                    <article class="staff-reports-kpi-card">
                        <div class="staff-reports-kpi-icon gold"><i class="fas fa-ribbon"></i></div>
                        <div class="staff-reports-kpi-copy">
                            <strong>P{{ number_format($governmentSavings, 2) }}</strong>
                            <h3>Gov't Savings</h3>
                            <p>Budget vs. awarded</p>
                        </div>
                    </article>

                    <article class="staff-reports-kpi-card">
                        <div class="staff-reports-kpi-icon sky"><i class="fas fa-square-check"></i></div>
                        <div class="staff-reports-kpi-copy">
                            <strong>{{ $bidParticipation }}</strong>
                            <h3>Bid Participation</h3>
                            <p>Total submissions</p>
                        </div>
                    </article>
                </section>

                <section class="staff-reports-grid">
                    <section class="staff-table-panel">
                        <div class="staff-table-header">
                            <h2>Project Summary Report</h2>
                            <a href="{{ route('staff.reports.print') }}" target="_blank" class="staff-report-export">Export PDF</a>
                        </div>

                        <div class="staff-table-wrap">
                            <table class="staff-table">
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
                                    @forelse($assignedProjects as $project)
                                        <tr>
                                            <td class="staff-project-title">{{ $project->title }}</td>
                                            <td>P{{ number_format((float) $project->budget, 2) }}</td>
                                            <td>{{ $project->bids_count }}</td>
                                            <td>{{ $project->status === 'awarded' ? 'Yes' : '—' }}</td>
                                            <td><span class="staff-status-pill {{ $project->status }}">{{ $project->status }}</span></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="staff-empty-cell">No project data available for reporting.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="staff-table-panel">
                        <div class="staff-table-header">
                            <h2>Bidder Performance</h2>
                            <a href="{{ route('staff.reports.export.csv') }}" class="staff-report-export">Export Excel</a>
                        </div>

                        <div class="staff-table-wrap">
                            <table class="staff-table">
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
                                            <td class="staff-project-title">{{ $bidder['bidder'] }}</td>
                                            <td>{{ $bidder['total_bids'] }}</td>
                                            <td>{{ $bidder['approved'] }}</td>
                                            <td>
                                                @if($bidder['won'] > 0)
                                                    <span class="staff-report-won">{{ $bidder['won'] }} won</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="staff-empty-cell">No bidder performance data available.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                </section>
            </section>
        </main>
    </div>
</div>

