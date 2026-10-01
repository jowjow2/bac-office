<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard admin-role-page">
    @vite(['resources/css/dashboard.css'])

    <style id="report-analytics-page-styles">
        .report-analytics-page { background: var(--ui-page) !important; color: var(--ui-ink) !important; }
        .report-analytics-page .ra-intro {
            display: flex !important;
            align-items: flex-start !important;
            justify-content: space-between !important;
            gap: 20px !important;
            margin: 0 0 18px !important;
        }
        .report-analytics-page .ra-intro h1 {
            margin: 0 0 5px !important;
            color: var(--ui-ink) !important;
            font-size: 25px !important;
            font-weight: 700 !important;
            letter-spacing: -.025em !important;
        }
        .report-analytics-page .ra-intro p { margin: 0 !important; color: var(--ui-muted) !important; font-size: 13px !important; }
        .report-analytics-page .ra-filter-card,
        .report-analytics-page .ra-monitor-card,
        .report-analytics-page .ra-chart-card,
        .report-analytics-page .ra-summary-card {
            border: 1px solid var(--ui-line) !important;
            border-radius: var(--ui-radius-lg) !important;
            background: #ffffff !important;
            box-shadow: var(--ui-shadow) !important;
        }
        .report-analytics-page .ra-filter-card {
            display: flex !important;
            align-items: flex-end !important;
            gap: 12px !important;
            flex-wrap: wrap !important;
            margin-bottom: 18px !important;
            padding: 14px !important;
        }
        .report-analytics-page .ra-filter-field {
            display: flex !important;
            flex: 1 1 155px !important;
            flex-direction: column !important;
            gap: 6px !important;
            min-width: 145px !important;
        }
        .report-analytics-page .ra-filter-field span {
            color: var(--ui-muted) !important;
            font-size: 10px !important;
            font-weight: 700 !important;
            letter-spacing: normal !important;
            text-transform: none !important;
        }
        .report-analytics-page .ra-filter-field input,
        .report-analytics-page .ra-filter-field select {
            width: 100% !important;
            height: 36px !important;
            box-sizing: border-box !important;
            border: 1px solid var(--ui-line-strong) !important;
            border-radius: var(--ui-radius) !important;
            background: #ffffff !important;
            color: var(--ui-ink) !important;
            font: 500 13px/1.2 Inter, sans-serif !important;
        }
        .report-analytics-page .ra-filter-field input:focus,
        .report-analytics-page .ra-filter-field select:focus {
            border-color: var(--ui-primary) !important;
            box-shadow: 0 0 0 3px rgba(29, 79, 64, .12) !important;
            outline: none !important;
        }
        .report-analytics-page .ra-filter-actions { display: flex !important; align-items: center !important; gap: 8px !important; }
        .report-analytics-page .ra-primary-button,
        .report-analytics-page .ra-secondary-button {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-height: 36px !important;
            padding: 0 14px !important;
            border-radius: var(--ui-radius) !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            text-decoration: none !important;
            white-space: nowrap !important;
            cursor: pointer !important;
        }
        .report-analytics-page .ra-primary-button { border: 1px solid var(--ui-primary) !important; background: var(--ui-primary) !important; color: #ffffff !important; }
        .report-analytics-page .ra-primary-button:hover { border-color: var(--ui-primary) !important; background: var(--ui-primary-hover) !important; }
        .report-analytics-page .ra-secondary-button { border: 1px solid var(--ui-line-strong) !important; background: #ffffff !important; color: var(--ui-ink-2) !important; }
        .report-analytics-page .ra-secondary-button:hover { border-color: var(--ui-primary-line) !important; background: var(--ui-primary-soft) !important; color: var(--ui-primary) !important; }
        .report-analytics-page .ra-export-wrap { position: relative !important; }
        .report-analytics-page .ra-export-menu {
            position: absolute !important;
            top: calc(100% + 7px) !important;
            right: 0 !important;
            z-index: 30 !important;
            min-width: 178px !important;
            padding: 5px !important;
            border: 1px solid var(--ui-line-strong) !important;
            border-radius: var(--ui-radius-lg) !important;
            background: #ffffff !important;
            box-shadow: 0 16px 32px rgba(27, 36, 32, .15) !important;
        }
        .report-analytics-page .ra-export-menu a {
            display: flex !important;
            align-items: center !important;
            gap: 9px !important;
            min-height: 34px !important;
            padding: 0 10px !important;
            border-radius: 7px !important;
            color: var(--ui-ink-2) !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            text-decoration: none !important;
        }
        .report-analytics-page .ra-export-menu a:hover { background: var(--ui-primary-soft) !important; color: var(--ui-primary) !important; }
        .report-analytics-page .ra-summary-grid {
            display: grid !important;
            grid-template-columns: repeat(6, minmax(0, 1fr)) !important;
            gap: 12px !important;
            margin-bottom: 18px !important;
        }
        .report-analytics-page .ra-summary-card { min-width: 0 !important; padding: 15px !important; }
        .report-analytics-page .ra-summary-top { display: flex !important; align-items: center !important; justify-content: space-between !important; gap: 8px !important; }
        .report-analytics-page .ra-summary-icon {
            display: grid !important;
            width: 34px !important;
            height: 34px !important;
            place-items: center !important;
            border-radius: var(--ui-radius-lg) !important;
            font-size: 14px !important;
        }
        .report-analytics-page .ra-summary-icon.blue { background: var(--ui-primary-soft) !important; color: var(--ui-primary) !important; }
        .report-analytics-page .ra-summary-icon.green { background: #ecfdf5 !important; color: #059669 !important; }
        .report-analytics-page .ra-summary-icon.violet { background: #f5f3ff !important; color: #7c3aed !important; }
        .report-analytics-page .ra-summary-icon.gold { background: #fffbeb !important; color: #d97706 !important; }
        .report-analytics-page .ra-summary-icon.sky { background: #f0f9ff !important; color: var(--ui-info) !important; }
        .report-analytics-page .ra-summary-icon.red { background: #fef2f2 !important; color: #dc2626 !important; }
        .report-analytics-page .ra-summary-label { display: block !important; margin-top: 12px !important; color: var(--ui-muted) !important; font-size: 11px !important; font-weight: 600 !important; }
        .report-analytics-page .ra-summary-value { display: block !important; margin-top: 3px !important; color: var(--ui-ink) !important; font-size: 25px !important; font-weight: 700 !important; line-height: 1 !important; }
        .report-analytics-page .ra-summary-note { display: block !important; margin-top: 7px !important; overflow: hidden !important; color: var(--ui-subtle) !important; font-size: 10px !important; text-overflow: ellipsis !important; white-space: nowrap !important; }
        .report-analytics-page .ra-monitor-grid { display: grid !important; grid-template-columns: repeat(4, minmax(0, 1fr)) !important; gap: 12px !important; margin-bottom: 18px !important; }
        .report-analytics-page .ra-monitor-card { min-width: 0 !important; padding: 15px !important; }
        .report-analytics-page .ra-monitor-head { display: flex !important; align-items: flex-start !important; justify-content: space-between !important; gap: 10px !important; margin-bottom: 13px !important; }
        .report-analytics-page .ra-monitor-head h3 { margin: 0 !important; color: var(--ui-ink) !important; font-size: 13px !important; font-weight: 700 !important; }
        .report-analytics-page .ra-monitor-head p { margin: 4px 0 0 !important; color: var(--ui-subtle) !important; font-size: 10px !important; }
        .report-analytics-page .ra-monitor-count { display: inline-flex !important; min-width: 27px !important; height: 27px !important; align-items: center !important; justify-content: center !important; border-radius: 999px !important; background: var(--ui-primary-soft) !important; color: var(--ui-primary) !important; font-size: 12px !important; font-weight: 700 !important; }
        .report-analytics-page .ra-monitor-list { display: grid !important; gap: 8px !important; margin: 0 !important; padding: 0 !important; list-style: none !important; }
        .report-analytics-page .ra-monitor-item { display: flex !important; align-items: center !important; justify-content: space-between !important; gap: 10px !important; min-width: 0 !important; padding-top: 8px !important; border-top: 1px solid var(--ui-line-soft) !important; }
        .report-analytics-page .ra-monitor-item:first-child { padding-top: 0 !important; border-top: 0 !important; }
        .report-analytics-page .ra-monitor-item strong,
        .report-analytics-page .ra-monitor-item span { overflow: hidden !important; text-overflow: ellipsis !important; white-space: nowrap !important; }
        .report-analytics-page .ra-monitor-item strong { color: var(--ui-ink-2) !important; font-size: 11px !important; font-weight: 600 !important; }
        .report-analytics-page .ra-monitor-item span { color: var(--ui-subtle) !important; font-size: 10px !important; }
        .report-analytics-page .ra-empty { color: var(--ui-subtle) !important; font-size: 11px !important; }
        .report-analytics-page .ra-chart-grid { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 14px !important; margin-bottom: 18px !important; }
        .report-analytics-page .ra-chart-card { min-width: 0 !important; padding: 18px !important; overflow: hidden !important; }
        .report-analytics-page .ra-chart-card.ra-chart-wide { grid-column: 1 / -1 !important; }
        .report-analytics-page .ra-chart-head { display: flex !important; align-items: flex-start !important; justify-content: space-between !important; gap: 10px !important; margin-bottom: 16px !important; }
        .report-analytics-page .ra-chart-head h3 { margin: 0 !important; color: var(--ui-ink) !important; font-size: 14px !important; font-weight: 700 !important; }
        .report-analytics-page .ra-chart-head p { margin: 4px 0 0 !important; color: var(--ui-subtle) !important; font-size: 11px !important; }
        .report-analytics-page .ra-chart-legend { display: flex !important; flex-wrap: wrap !important; justify-content: flex-end !important; gap: 8px 12px !important; }
        .report-analytics-page .ra-legend-item { display: inline-flex !important; align-items: center !important; gap: 5px !important; color: var(--ui-muted) !important; font-size: 10px !important; }
        .report-analytics-page .ra-legend-dot { width: 7px !important; height: 7px !important; border-radius: 50% !important; }
        .report-analytics-page .ra-segmented-bar { display: flex !important; width: 100% !important; height: 22px !important; overflow: hidden !important; border-radius: 7px !important; background: var(--ui-line-soft) !important; }
        .report-analytics-page .ra-segment { min-width: 2px !important; height: 100% !important; }
        .report-analytics-page .ra-stat-list { display: grid !important; gap: 10px !important; margin-top: 16px !important; }
        .report-analytics-page .ra-stat-row { display: grid !important; grid-template-columns: minmax(100px, 1fr) minmax(80px, 2fr) 42px !important; align-items: center !important; gap: 10px !important; }
        .report-analytics-page .ra-stat-label { overflow: hidden !important; color: var(--ui-ink-2) !important; font-size: 11px !important; font-weight: 600 !important; text-overflow: ellipsis !important; white-space: nowrap !important; }
        .report-analytics-page .ra-track { width: 100% !important; height: 8px !important; overflow: hidden !important; border-radius: 999px !important; background: var(--ui-line-soft) !important; }
        .report-analytics-page .ra-fill { display: block !important; width: var(--bar-width) !important; height: 100% !important; border-radius: inherit !important; background: var(--bar-color, var(--ui-primary)) !important; }
        .report-analytics-page .ra-stat-value { color: var(--ui-ink) !important; font-size: 11px !important; font-weight: 700 !important; text-align: right !important; }
        .report-analytics-page .ra-month-chart { display: grid !important; grid-template-columns: repeat(12, minmax(30px, 1fr)) !important; align-items: end !important; gap: 8px !important; min-height: 160px !important; padding: 8px 0 0 !important; border-bottom: 1px solid var(--ui-line) !important; }
        .report-analytics-page .ra-month-column { display: flex !important; flex-direction: column !important; justify-content: flex-end !important; gap: 5px !important; min-width: 0 !important; height: 145px !important; }
        .report-analytics-page .ra-month-bars { display: flex !important; align-items: flex-end !important; justify-content: center !important; gap: 2px !important; height: 116px !important; }
        .report-analytics-page .ra-month-bar { width: 7px !important; min-height: 2px !important; height: var(--bar-height) !important; border-radius: 3px 3px 0 0 !important; background: var(--bar-color) !important; }
        .report-analytics-page .ra-month-label { overflow: hidden !important; color: var(--ui-subtle) !important; font-size: 9px !important; text-align: center !important; text-overflow: ellipsis !important; white-space: nowrap !important; }
        .report-analytics-page .ra-chart-note { margin-top: 10px !important; color: var(--ui-subtle) !important; font-size: 10px !important; }
        .report-analytics-page .ra-compare-row { display: grid !important; grid-template-columns: 125px minmax(0, 1fr) 94px !important; align-items: center !important; gap: 10px !important; margin-bottom: 12px !important; }
        .report-analytics-page .ra-compare-label { overflow: hidden !important; color: var(--ui-ink-2) !important; font-size: 11px !important; font-weight: 600 !important; text-overflow: ellipsis !important; white-space: nowrap !important; }
        .report-analytics-page .ra-compare-bars { display: grid !important; gap: 4px !important; }
        .report-analytics-page .ra-compare-bar { display: block !important; width: var(--bar-width) !important; height: 7px !important; border-radius: 999px !important; background: var(--bar-color) !important; }
        .report-analytics-page .ra-compare-amount { color: var(--ui-muted) !important; font-size: 10px !important; text-align: right !important; white-space: nowrap !important; }
        .report-analytics-page .ra-compare-key { display: flex !important; gap: 12px !important; margin-top: 12px !important; }
        .report-analytics-page .ra-compare-key span { display: inline-flex !important; align-items: center !important; gap: 5px !important; color: var(--ui-muted) !important; font-size: 10px !important; }
        .report-analytics-page .ra-compare-key i { width: 8px !important; height: 8px !important; border-radius: 2px !important; }
        .report-analytics-page .ra-monitor-card.ra-danger .ra-monitor-count { background: #fef2f2 !important; color: #b91c1c !important; }
        .report-analytics-page .ra-monitor-card.ra-warning .ra-monitor-count { background: #fffbeb !important; color: #b45309 !important; }
        @media (max-width: 1250px) {
            .report-analytics-page .ra-summary-grid { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
            .report-analytics-page .ra-monitor-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
        }
        @media (max-width: 760px) {
            .report-analytics-page .ra-intro { flex-direction: column !important; }
            .report-analytics-page .ra-filter-card { align-items: stretch !important; }
            .report-analytics-page .ra-filter-field { flex-basis: calc(50% - 8px) !important; }
            .report-analytics-page .ra-filter-actions { width: 100% !important; }
            .report-analytics-page .ra-filter-actions > * { flex: 1 1 0 !important; }
            .report-analytics-page .ra-chart-grid { grid-template-columns: 1fr !important; }
            .report-analytics-page .ra-chart-card.ra-chart-wide { grid-column: auto !important; }
            .report-analytics-page .ra-month-chart { overflow-x: auto !important; grid-template-columns: repeat(12, 42px) !important; }
        }
        @media (max-width: 520px) {
            .report-analytics-page .ra-summary-grid,
            .report-analytics-page .ra-monitor-grid { grid-template-columns: 1fr !important; }
            .report-analytics-page .ra-filter-field { flex-basis: 100% !important; }
            .report-analytics-page .ra-filter-actions { flex-wrap: wrap !important; }
            .report-analytics-page .ra-filter-actions > * { flex-basis: calc(50% - 4px) !important; }
            .report-analytics-page .ra-stat-row { grid-template-columns: 1fr 1.3fr 32px !important; }
            .report-analytics-page .ra-compare-row { grid-template-columns: 90px minmax(0, 1fr) 78px !important; }
        }
    </style>

    @include('partials.admin-sidebar')

    <div class="main-area">
        <x-page-header title="Report analytics" subtitle="BAC procurement performance and monitoring dashboard" />

        <main class="dashboard-content reports-page report-analytics-page">
            <section class="ra-intro">
                <div>
                    <h1>Report Analytics</h1>
                    <p>Monitor procurement performance, bid outcomes, and bidder activity from live BAC records.</p>
                </div>
                <div class="ra-export-wrap">
                    <button type="button" id="reportExportToggle" class="ra-secondary-button" aria-expanded="false" aria-controls="reportExportMenu">
                        <i class="fas fa-download" aria-hidden="true"></i>
                        <span>Export Analytics</span>
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div id="reportExportMenu" class="ra-export-menu" hidden>
                        <a href="{{ route('admin.reports.print', $filterQuery) }}"><i class="fas fa-file-pdf" aria-hidden="true"></i>Export PDF</a>
                        <a href="{{ route('admin.reports.export.csv', $filterQuery) }}"><i class="fas fa-file-excel" aria-hidden="true"></i>Export Excel</a>
                    </div>
                </div>
            </section>

            <form method="GET" action="{{ route('admin.reports') }}" class="ra-filter-card" aria-label="Report filters">
                <label class="ra-filter-field">
                    <span>Date from</span>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}">
                </label>
                <label class="ra-filter-field">
                    <span>Date to</span>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}">
                </label>
                <label class="ra-filter-field">
                    <span>Status</span>
                    <select name="status">
                        <option value="">All statuses</option>
                        @foreach($statusOptions as $statusKey => $statusLabel)
                            <option value="{{ $statusKey }}" @selected($filters['status'] === $statusKey)>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ra-filter-field">
                    <span>Procurement type</span>
                    <select name="procurement_type">
                        <option value="">All procurement types</option>
                        @foreach($procurementTypeOptions as $typeKey => $typeLabel)
                            <option value="{{ $typeKey }}" @selected($filters['procurement_type'] === $typeKey)>{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="ra-filter-actions">
                    <button type="submit" class="ra-primary-button"><i class="fas fa-filter" aria-hidden="true"></i>&nbsp;Apply</button>
                    <a href="{{ route('admin.reports') }}" class="ra-secondary-button">Reset</a>
                </div>
            </form>

            <section class="ra-summary-grid" aria-label="Analytics summary">
                @foreach($summaryCards as $card)
                    <article class="ra-summary-card">
                        <div class="ra-summary-top">
                            <span class="ra-summary-icon {{ $card['tone'] }}"><i class="fas {{ $card['icon'] }}" aria-hidden="true"></i></span>
                        </div>
                        <span class="ra-summary-label">{{ $card['label'] }}</span>
                        <strong class="ra-summary-value">{{ number_format((int) $card['value']) }}</strong>
                        <span class="ra-summary-note">{{ $card['note'] }}</span>
                    </article>
                @endforeach
            </section>

            <section class="ra-monitor-grid" aria-label="Procurement monitoring">
                <article class="ra-monitor-card">
                    <div class="ra-monitor-head">
                        <div><h3>Upcoming Deadlines</h3><p>Next 30 days</p></div>
                        <span class="ra-monitor-count">{{ $monitoring['upcoming_deadlines']['count'] }}</span>
                    </div>
                    <ul class="ra-monitor-list">
                        @forelse($monitoring['upcoming_deadlines']['items'] as $row)
                            <li class="ra-monitor-item"><strong>{{ $row['project']->title }}</strong><span>{{ $row['deadline']->format('M d, Y') }}</span></li>
                        @empty
                            <li class="ra-empty">No upcoming deadlines in the selected data.</li>
                        @endforelse
                    </ul>
                </article>
                <article class="ra-monitor-card ra-danger">
                    <div class="ra-monitor-head">
                        <div><h3>Overdue Projects</h3><p>Open or pending procurement</p></div>
                        <span class="ra-monitor-count">{{ $monitoring['overdue_projects']['count'] }}</span>
                    </div>
                    <ul class="ra-monitor-list">
                        @forelse($monitoring['overdue_projects']['items'] as $row)
                            <li class="ra-monitor-item"><strong>{{ $row['project']->title }}</strong><span>{{ $row['deadline']->format('M d, Y') }}</span></li>
                        @empty
                            <li class="ra-empty">No overdue projects in the selected data.</li>
                        @endforelse
                    </ul>
                </article>
                <article class="ra-monitor-card ra-warning">
                    <div class="ra-monitor-head">
                        <div><h3>Pending Bidder Validations</h3><p>Registration review queue</p></div>
                        <span class="ra-monitor-count">{{ $monitoring['pending_bidder_validations']['count'] }}</span>
                    </div>
                    <ul class="ra-monitor-list">
                        @forelse($monitoring['pending_bidder_validations']['items'] as $user)
                            <li class="ra-monitor-item"><strong>{{ $user->company ?: $user->name }}</strong><span>{{ $user->created_at?->format('M d, Y') }}</span></li>
                        @empty
                            <li class="ra-empty">No pending bidder validations.</li>
                        @endforelse
                    </ul>
                </article>
                <article class="ra-monitor-card">
                    <div class="ra-monitor-head">
                        <div><h3>Awaiting BAC Evaluation</h3><p>Bids for committee review</p></div>
                        <span class="ra-monitor-count">{{ $monitoring['awaiting_bac_evaluation']['count'] }}</span>
                    </div>
                    <ul class="ra-monitor-list">
                        @forelse($monitoring['awaiting_bac_evaluation']['items'] as $row)
                            <li class="ra-monitor-item"><strong>{{ $row['project']->title }}</strong><span>{{ $row['bids'] }} {{ $row['bids'] === 1 ? 'bid' : 'bids' }}</span></li>
                        @empty
                            <li class="ra-empty">No projects are awaiting evaluation.</li>
                        @endforelse
                    </ul>
                </article>
            </section>

            @php
                $statusTotal = max(1, collect($procurementStatusDistribution)->sum('value'));
            @endphp
            <section class="ra-chart-grid" aria-label="Procurement analytics charts">
                <article class="ra-chart-card">
                    <div class="ra-chart-head">
                        <div><h3>Procurement Status Distribution</h3><p>Projects by current procurement status</p></div>
                    </div>
                    <div class="ra-segmented-bar" role="img" aria-label="Procurement status distribution">
                        @foreach($procurementStatusDistribution as $row)
                            <span class="ra-segment" style="width: {{ ($row['value'] / $statusTotal) * 100 }}%; background: {{ $row['color'] }};" title="{{ $row['label'] }}: {{ $row['value'] }}"></span>
                        @endforeach
                    </div>
                    <div class="ra-stat-list">
                        @foreach($procurementStatusDistribution as $row)
                            <div class="ra-stat-row">
                                <span class="ra-stat-label">{{ $row['label'] }}</span>
                                <span class="ra-track"><span class="ra-fill" style="--bar-width: {{ ($row['value'] / $chartMaxima['status']) * 100 }}%; --bar-color: {{ $row['color'] }};"></span></span>
                                <strong class="ra-stat-value">{{ $row['value'] }}</strong>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="ra-chart-card">
                    <div class="ra-chart-head">
                        <div><h3>Bids per Project</h3><p>Highest participation in the selected range</p></div>
                    </div>
                    <div class="ra-stat-list">
                        @forelse($bidsPerProject as $row)
                            <div class="ra-stat-row" title="{{ $row['full_label'] }}">
                                <span class="ra-stat-label">{{ $row['label'] }}</span>
                                <span class="ra-track"><span class="ra-fill" style="--bar-width: {{ ($row['value'] / $chartMaxima['bids_per_project']) * 100 }}%; --bar-color: #235e4c;"></span></span>
                                <strong class="ra-stat-value">{{ $row['value'] }}</strong>
                            </div>
                        @empty
                            <div class="ra-empty">No bid records for the selected projects.</div>
                        @endforelse
                    </div>
                </article>

                <article class="ra-chart-card ra-chart-wide">
                    <div class="ra-chart-head">
                        <div><h3>Monthly Procurement Activity</h3><p>Projects, bids, and awards created in each month</p></div>
                        <div class="ra-chart-legend">
                            <span class="ra-legend-item"><i class="ra-legend-dot" style="background:#235e4c"></i>Projects</span>
                            <span class="ra-legend-item"><i class="ra-legend-dot" style="background:#10b981"></i>Bids</span>
                            <span class="ra-legend-item"><i class="ra-legend-dot" style="background:#f59e0b"></i>Awards</span>
                        </div>
                    </div>
                    <div class="ra-month-chart">
                        @foreach($monthlyActivity as $row)
                            <div class="ra-month-column" title="{{ $row['label'] }}: {{ $row['projects'] }} projects, {{ $row['bids'] }} bids, {{ $row['awards'] }} awards">
                                <div class="ra-month-bars">
                                    <span class="ra-month-bar" style="--bar-height: {{ max(2, ($row['projects'] / $chartMaxima['activity']) * 100) }}%; --bar-color:#235e4c"></span>
                                    <span class="ra-month-bar" style="--bar-height: {{ max(2, ($row['bids'] / $chartMaxima['activity']) * 100) }}%; --bar-color:#10b981"></span>
                                    <span class="ra-month-bar" style="--bar-height: {{ max(2, ($row['awards'] / $chartMaxima['activity']) * 100) }}%; --bar-color:#f59e0b"></span>
                                </div>
                                <span class="ra-month-label">{{ $row['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="ra-chart-card">
                    <div class="ra-chart-head">
                        <div><h3>ABC vs Winning Bid Amount</h3><p>Approved budget compared with the recorded winning award</p></div>
                    </div>
                    @forelse($abcVsWinning as $row)
                        <div class="ra-compare-row" title="{{ $row['full_label'] }}">
                            <span class="ra-compare-label">{{ $row['label'] }}</span>
                            <span class="ra-compare-bars">
                                <i class="ra-compare-bar" style="--bar-width: {{ ($row['abc'] / $chartMaxima['abc']) * 100 }}%; --bar-color:#99a19c"></i>
                                <i class="ra-compare-bar" style="--bar-width: {{ ($row['winning'] / $chartMaxima['abc']) * 100 }}%; --bar-color:#235e4c"></i>
                            </span>
                            <span class="ra-compare-amount">₱{{ number_format($row['winning'], 2) }}</span>
                        </div>
                    @empty
                        <div class="ra-empty">No awarded bid amounts for the selected projects.</div>
                    @endforelse
                    <div class="ra-compare-key"><span><i style="background:#99a19c"></i>ABC</span><span><i style="background:#235e4c"></i>Winning bid</span></div>
                </article>

                <article class="ra-chart-card">
                    <div class="ra-chart-head">
                        <div><h3>Bid Result Distribution</h3><p>Current result and workflow outcomes</p></div>
                    </div>
                    <div class="ra-stat-list">
                        @foreach($bidResultDistribution as $row)
                            <div class="ra-stat-row">
                                <span class="ra-stat-label">{{ $row['label'] }}</span>
                                <span class="ra-track"><span class="ra-fill" style="--bar-width: {{ ($row['value'] / $chartMaxima['bid_result']) * 100 }}%; --bar-color: {{ $row['color'] }};"></span></span>
                                <strong class="ra-stat-value">{{ $row['value'] }}</strong>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="ra-chart-card">
                    <div class="ra-chart-head">
                        <div><h3>Bidder Participation</h3><p>Bid submissions by registered bidder</p></div>
                    </div>
                    <div class="ra-stat-list">
                        @forelse($bidderParticipation as $row)
                            <div class="ra-stat-row" title="{{ $row['full_label'] }}">
                                <span class="ra-stat-label">{{ $row['label'] }}</span>
                                <span class="ra-track"><span class="ra-fill" style="--bar-width: {{ ($row['value'] / $chartMaxima['bidder']) * 100 }}%; --bar-color: #7c3aed;"></span></span>
                                <strong class="ra-stat-value">{{ $row['value'] }}</strong>
                            </div>
                        @empty
                            <div class="ra-empty">No bidder participation for the selected projects.</div>
                        @endforelse
                    </div>
                </article>
            </section>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggle = document.getElementById('reportExportToggle');
        const menu = document.getElementById('reportExportMenu');
        if (!toggle || !menu) return;

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            const isOpen = menu.hasAttribute('hidden') === false;
            if (isOpen) {
                menu.setAttribute('hidden', 'hidden');
                toggle.setAttribute('aria-expanded', 'false');
            } else {
                menu.removeAttribute('hidden');
                toggle.setAttribute('aria-expanded', 'true');
            }
        });
        document.addEventListener('click', function () {
            menu.setAttribute('hidden', 'hidden');
            toggle.setAttribute('aria-expanded', 'false');
        });
    });
</script>
