<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
@php
    $tz = config('bac-office.display_timezone', 'Asia/Manila');
    $statusTotal = max(1, collect($procurementStatusDistribution)->sum('value'));
    $pipelineTotal = max(1, $pipeline['total']);
    // Donut: circumference of r = 48.
    $donutC = 2 * M_PI * 48;
    $donutOffset = 0;
    $activeFilters = array_filter([
        'date' => ($filters['date_from'] || $filters['date_to'])
            ? (($filters['date_from'] ? \Carbon\Carbon::parse($filters['date_from'])->format('M d, Y') : 'Any date').' – '.($filters['date_to'] ? \Carbon\Carbon::parse($filters['date_to'])->format('M d, Y') : 'today'))
            : null,
        'status' => $filters['status'] ? $filters['status_label'] : null,
        'procurement_type' => $filters['procurement_type'] ? $filters['procurement_type_label'] : null,
    ]);
    $withoutFilter = fn (string $key) => route('admin.reports', \Illuminate\Support\Arr::except($filterQuery, $key === 'date' ? ['date_from', 'date_to'] : [$key]));
    $eventIcons = ['pre_bid' => 'fa-people-group', 'deadline' => 'fa-hourglass-end', 'opening' => 'fa-envelope-open'];
@endphp
<div class="admin-dashboard admin-role-page">
    @vite(['resources/css/dashboard.css'])

    <style id="report-analytics-page-styles">
        .report-analytics-page { background: var(--ui-page) !important; color: var(--ui-ink) !important; }
        .report-analytics-page .ra-card {
            min-width: 0 !important;
            border: 1px solid var(--ui-line) !important;
            border-radius: var(--ui-radius-lg) !important;
            background: #ffffff !important;
            box-shadow: var(--ui-shadow) !important;
        }
        .report-analytics-page .ra-section { margin-bottom: 16px !important; }
        .report-analytics-page h3 { margin: 0 !important; color: var(--ui-ink) !important; font-size: 14px !important; font-weight: 700 !important; letter-spacing: normal !important; }
        .report-analytics-page .ra-sub { margin: 3px 0 0 !important; color: var(--ui-subtle) !important; font-size: 11.5px !important; }
        .report-analytics-page .ra-head { display: flex !important; align-items: flex-start !important; justify-content: space-between !important; gap: 12px !important; margin-bottom: 16px !important; }
        .report-analytics-page .ra-empty { color: var(--ui-subtle) !important; font-size: 12px !important; }
        .report-analytics-page a.ra-link { color: inherit !important; -webkit-text-fill-color: currentColor !important; text-decoration: none !important; }
        .report-analytics-page a.ra-link:hover strong { color: var(--ui-primary) !important; text-decoration: underline !important; }

        /* Export menu (page header) */
        .ra-export-wrap { position: relative; }
        .ra-export-wrap .ra-button { display: inline-flex; align-items: center; gap: 8px; height: 38px; padding: 0 14px; border: 1px solid var(--ui-line-strong); border-radius: var(--ui-radius); background: #fff; color: var(--ui-ink-2); font: 600 12.5px/1 var(--ui-font); cursor: pointer; }
        .ra-export-wrap .ra-button:hover { border-color: var(--ui-primary-line); background: var(--ui-primary-soft); color: var(--ui-primary); }
        .ra-export-menu { position: absolute; top: calc(100% + 6px); right: 0; z-index: 40; min-width: 180px; padding: 5px; border: 1px solid var(--ui-line-strong); border-radius: var(--ui-radius-lg); background: #fff; box-shadow: 0 16px 32px rgba(27, 36, 32, .15); }
        .ra-export-menu a { display: flex; align-items: center; gap: 9px; min-height: 34px; padding: 0 10px; border-radius: 7px; color: var(--ui-ink-2); font: 600 12.5px/1 var(--ui-font); text-decoration: none; }
        .ra-export-menu a:hover { background: var(--ui-primary-soft); color: var(--ui-primary); }

        /* Filters */
        .report-analytics-page .ra-filters { padding: 14px 16px !important; }
        .report-analytics-page .ra-presets { display: flex !important; flex-wrap: wrap !important; gap: 6px !important; margin-bottom: 12px !important; }
        .report-analytics-page .ra-preset { display: inline-flex !important; align-items: center !important; height: 30px !important; padding: 0 12px !important; border: 1px solid var(--ui-line-strong) !important; border-radius: 999px !important; background: #fff !important; color: var(--ui-ink-2) !important; -webkit-text-fill-color: var(--ui-ink-2) !important; font-size: 12px !important; font-weight: 600 !important; text-decoration: none !important; transition: background .15s ease, border-color .15s ease !important; }
        .report-analytics-page .ra-preset:hover { border-color: var(--ui-primary-line) !important; background: var(--ui-primary-soft) !important; }
        .report-analytics-page .ra-preset.is-active { border-color: var(--ui-primary) !important; background: var(--ui-primary) !important; color: #fff !important; -webkit-text-fill-color: #fff !important; }
        .report-analytics-page .ra-filter-row { display: flex !important; flex-wrap: wrap !important; align-items: flex-end !important; gap: 10px !important; }
        .report-analytics-page .ra-field { display: flex !important; flex: 1 1 150px !important; flex-direction: column !important; gap: 5px !important; min-width: 140px !important; }
        .report-analytics-page .ra-field span { color: var(--ui-muted) !important; font-size: 11.5px !important; font-weight: 600 !important; }
        .report-analytics-page .ra-field input,
        .report-analytics-page .ra-field select { width: 100% !important; height: 38px !important; box-sizing: border-box !important; margin: 0 !important; padding: 0 10px !important; border: 1px solid var(--ui-line-strong) !important; border-radius: var(--ui-radius) !important; background: #fff !important; color: var(--ui-ink) !important; font: 400 13px/1.2 var(--ui-font) !important; box-shadow: none !important; }
        .report-analytics-page .ra-field input:focus,
        .report-analytics-page .ra-field select:focus { border-color: var(--ui-primary) !important; box-shadow: var(--ui-focus) !important; outline: none !important; }
        .report-analytics-page .ra-filter-actions { display: flex !important; gap: 8px !important; }
        .report-analytics-page .ra-btn { display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 7px !important; height: 38px !important; padding: 0 16px !important; border: 1px solid var(--ui-line-strong) !important; border-radius: var(--ui-radius) !important; background: #fff !important; color: var(--ui-ink-2) !important; -webkit-text-fill-color: var(--ui-ink-2) !important; font: 600 12.5px/1 var(--ui-font) !important; text-decoration: none !important; box-shadow: none !important; cursor: pointer !important; }
        .report-analytics-page .ra-btn:hover { background: var(--ui-surface-2) !important; }
        .report-analytics-page :is(.ra-btn.is-primary, #ra-x#ra-x) { border-color: var(--ui-primary) !important; background: var(--ui-primary) !important; color: #fff !important; -webkit-text-fill-color: #fff !important; }
        .report-analytics-page :is(.ra-btn.is-primary, #ra-x#ra-x):hover { background: var(--ui-primary-hover) !important; }
        .report-analytics-page .ra-chips { display: flex !important; flex-wrap: wrap !important; align-items: center !important; gap: 6px !important; margin-top: 12px !important; color: var(--ui-muted) !important; font-size: 12px !important; }
        .report-analytics-page .ra-chip { display: inline-flex !important; align-items: center !important; gap: 7px !important; height: 26px !important; padding: 0 6px 0 10px !important; border-radius: 999px !important; background: var(--ui-primary-soft) !important; color: var(--ui-primary) !important; font-size: 12px !important; font-weight: 600 !important; }
        .report-analytics-page .ra-chip a { display: grid !important; width: 18px !important; height: 18px !important; place-items: center !important; border-radius: 50% !important; color: var(--ui-primary) !important; -webkit-text-fill-color: var(--ui-primary) !important; text-decoration: none !important; font-size: 10px !important; }
        .report-analytics-page .ra-chip a:hover { background: rgba(29, 79, 64, .15) !important; }

        /* KPI cards */
        .report-analytics-page .ra-kpis { display: grid !important; grid-template-columns: repeat(4, minmax(0, 1fr)) !important; gap: 12px !important; }
        .report-analytics-page .ra-kpi { position: relative !important; padding: 16px !important; overflow: hidden !important; }
        .report-analytics-page .ra-kpi-top { display: flex !important; align-items: center !important; gap: 10px !important; }
        .report-analytics-page .ra-kpi-icon { display: grid !important; flex: 0 0 34px !important; width: 34px !important; height: 34px !important; place-items: center !important; border-radius: var(--ui-radius-lg) !important; font-size: 14px !important; }
        .report-analytics-page .ra-kpi-label { color: var(--ui-muted) !important; font-size: 12px !important; font-weight: 600 !important; }
        .report-analytics-page .ra-kpi-value { display: block !important; margin-top: 12px !important; color: var(--ui-ink) !important; font-size: 26px !important; font-weight: 700 !important; line-height: 1.05 !important; font-variant-numeric: tabular-nums !important; letter-spacing: -.02em !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; }
        .report-analytics-page .ra-kpi-note { display: block !important; margin-top: 6px !important; color: var(--ui-subtle) !important; font-size: 11.5px !important; line-height: 1.4 !important; }
        .report-analytics-page .tone-blue .ra-kpi-icon { background: var(--ui-primary-soft) !important; color: var(--ui-primary) !important; }
        .report-analytics-page .tone-green .ra-kpi-icon { background: #ecfdf5 !important; color: #059669 !important; }
        .report-analytics-page .tone-violet .ra-kpi-icon { background: #f5f3ff !important; color: #7c3aed !important; }
        .report-analytics-page .tone-gold .ra-kpi-icon { background: #fffbeb !important; color: #d97706 !important; }
        .report-analytics-page .tone-sky .ra-kpi-icon { background: #f0f9ff !important; color: #0284c7 !important; }
        .report-analytics-page .tone-red .ra-kpi-icon { background: #fef2f2 !important; color: #dc2626 !important; }

        /* Pipeline funnel */
        .report-analytics-page .ra-pipeline { padding: 18px !important; }
        .report-analytics-page .ra-funnel { display: grid !important; gap: 8px !important; margin: 0 !important; padding: 0 !important; list-style: none !important; }
        .report-analytics-page .ra-funnel li { display: grid !important; grid-template-columns: 150px minmax(0, 1fr) 44px 92px !important; align-items: center !important; gap: 12px !important; }
        .report-analytics-page .ra-funnel-label { color: var(--ui-ink-2) !important; font-size: 12.5px !important; font-weight: 600 !important; }
        .report-analytics-page .ra-funnel-track { position: relative !important; height: 22px !important; border-radius: 6px !important; background: var(--ui-line-soft) !important; overflow: hidden !important; }
        .report-analytics-page .ra-funnel-fill { display: block !important; width: var(--bar-width) !important; height: 100% !important; border-radius: 6px !important; background: linear-gradient(90deg, #1d4f40, #2f7d63) !important; }
        .report-analytics-page .ra-funnel-count { color: var(--ui-ink) !important; font-size: 13px !important; font-weight: 700 !important; text-align: right !important; font-variant-numeric: tabular-nums !important; }
        .report-analytics-page .ra-funnel-here { justify-self: start !important; padding: 2px 8px !important; border-radius: 999px !important; background: var(--ui-surface-2) !important; color: var(--ui-muted) !important; font-size: 11px !important; font-weight: 600 !important; white-space: nowrap !important; }
        .report-analytics-page .ra-funnel-here.is-busy { background: #fffbeb !important; color: #b45309 !important; }
        .report-analytics-page .ra-funnel-foot { display: flex !important; flex-wrap: wrap !important; gap: 14px !important; margin-top: 14px !important; color: var(--ui-muted) !important; font-size: 12px !important; }

        /* Monitoring */
        .report-analytics-page .ra-monitor-grid { display: grid !important; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) !important; gap: 12px !important; }
        .report-analytics-page .ra-monitor { padding: 16px !important; }
        .report-analytics-page .ra-count { display: inline-grid !important; min-width: 28px !important; height: 28px !important; padding: 0 8px !important; place-items: center !important; border-radius: 999px !important; background: var(--ui-primary-soft) !important; color: var(--ui-primary) !important; font-size: 12.5px !important; font-weight: 700 !important; }
        .report-analytics-page .ra-count.is-danger { background: #fef2f2 !important; color: #b91c1c !important; }
        .report-analytics-page .ra-count.is-warning { background: #fffbeb !important; color: #b45309 !important; }
        .report-analytics-page .ra-list { display: grid !important; margin: 0 !important; padding: 0 !important; list-style: none !important; }
        .report-analytics-page .ra-list li { display: flex !important; align-items: center !important; justify-content: space-between !important; gap: 12px !important; min-width: 0 !important; padding: 9px 0 !important; border-top: 1px solid var(--ui-line-soft) !important; }
        .report-analytics-page .ra-list li:first-child { padding-top: 0 !important; border-top: 0 !important; }
        .report-analytics-page .ra-list strong { overflow: hidden !important; color: var(--ui-ink-2) !important; font-size: 12.5px !important; font-weight: 600 !important; text-overflow: ellipsis !important; white-space: nowrap !important; }
        .report-analytics-page .ra-list span { flex: 0 0 auto !important; color: var(--ui-subtle) !important; font-size: 11.5px !important; }
        .report-analytics-page .ra-reason { padding: 3px 9px !important; border-radius: 999px !important; font-size: 11.5px !important; font-weight: 600 !important; white-space: nowrap !important; }
        .report-analytics-page .ra-reason.is-danger { background: #fef2f2 !important; color: #b91c1c !important; }
        .report-analytics-page .ra-reason.is-warning { background: #fffbeb !important; color: #b45309 !important; }
        .report-analytics-page .ra-reason.is-info { background: #f0f9ff !important; color: #0369a1 !important; }

        /* Upcoming schedule */
        .report-analytics-page .ra-agenda { padding: 18px !important; }
        .report-analytics-page .ra-days { display: grid !important; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)) !important; gap: 12px !important; }
        .report-analytics-page .ra-day { padding: 12px !important; border: 1px solid var(--ui-line) !important; border-radius: var(--ui-radius-lg) !important; background: var(--ui-surface-2) !important; }
        .report-analytics-page .ra-day.is-today { border-color: var(--ui-primary-line) !important; background: var(--ui-primary-soft) !important; }
        .report-analytics-page .ra-day-head { display: flex !important; align-items: baseline !important; gap: 8px !important; margin-bottom: 8px !important; }
        .report-analytics-page .ra-day-head strong { color: var(--ui-ink) !important; font-size: 13px !important; }
        .report-analytics-page .ra-day-head span { color: var(--ui-muted) !important; font-size: 11.5px !important; }
        .report-analytics-page .ra-event { display: grid !important; grid-template-columns: 28px minmax(0, 1fr) !important; gap: 8px !important; align-items: start !important; padding: 7px 0 !important; border-top: 1px solid rgba(0, 0, 0, .05) !important; }
        .report-analytics-page .ra-event:first-of-type { border-top: 0 !important; }
        .report-analytics-page .ra-event-icon { display: grid !important; width: 28px !important; height: 28px !important; place-items: center !important; border-radius: 8px !important; background: #fff !important; font-size: 12px !important; }
        .report-analytics-page .ra-event.is-deadline .ra-event-icon { color: #b45309 !important; }
        .report-analytics-page .ra-event.is-opening .ra-event-icon { color: var(--ui-primary) !important; }
        .report-analytics-page .ra-event.is-pre_bid .ra-event-icon { color: #0369a1 !important; }
        .report-analytics-page .ra-event strong { display: block !important; overflow: hidden !important; color: var(--ui-ink-2) !important; font-size: 12.5px !important; font-weight: 600 !important; text-overflow: ellipsis !important; white-space: nowrap !important; }
        .report-analytics-page .ra-event small { display: block !important; color: var(--ui-muted) !important; font-size: 11.5px !important; }

        /* Charts */
        .report-analytics-page .ra-chart-grid { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 12px !important; }
        .report-analytics-page .ra-chart { position: relative !important; padding: 18px !important; }
        .report-analytics-page .ra-chart.is-wide { grid-column: 1 / -1 !important; }
        .report-analytics-page .ra-legend { display: flex !important; flex-wrap: wrap !important; justify-content: flex-end !important; gap: 6px 12px !important; }
        .report-analytics-page .ra-legend span { display: inline-flex !important; align-items: center !important; gap: 6px !important; color: var(--ui-muted) !important; font-size: 11.5px !important; }
        .report-analytics-page .ra-dot { display: inline-block !important; flex: 0 0 8px !important; width: 8px !important; height: 8px !important; border-radius: 50% !important; background: var(--dot) !important; }
        .report-analytics-page .ra-donut-wrap { display: grid !important; grid-template-columns: 170px minmax(0, 1fr) !important; gap: 20px !important; align-items: center !important; }
        .report-analytics-page .ra-donut { width: 170px !important; height: 170px !important; }
        .report-analytics-page .ra-donut circle { fill: none !important; stroke-width: 16 !important; }
        .report-analytics-page .ra-donut-total { fill: var(--ui-ink) !important; font: 700 22px var(--ui-font) !important; }
        .report-analytics-page .ra-donut-caption { fill: var(--ui-muted) !important; font: 600 8px var(--ui-font) !important; }
        .report-analytics-page .ra-rows { display: grid !important; gap: 9px !important; }
        .report-analytics-page .ra-row { display: grid !important; grid-template-columns: minmax(110px, 1.1fr) minmax(70px, 2fr) 36px !important; align-items: center !important; gap: 10px !important; }
        .report-analytics-page .ra-row-label { display: flex !important; align-items: center !important; gap: 7px !important; min-width: 0 !important; overflow: hidden !important; color: var(--ui-ink-2) !important; font-size: 12px !important; font-weight: 600 !important; white-space: nowrap !important; text-overflow: ellipsis !important; }
        .report-analytics-page .ra-row-label > span:last-child { overflow: hidden !important; text-overflow: ellipsis !important; }
        /* Stage names next to the donut are long: give them room and let them wrap. */
        .report-analytics-page .ra-donut-wrap .ra-row { grid-template-columns: minmax(150px, 1.7fr) minmax(50px, 1fr) 28px !important; }
        .report-analytics-page .ra-donut-wrap .ra-row-label { white-space: normal !important; line-height: 1.3 !important; }
        .report-analytics-page .ra-track { height: 8px !important; border-radius: 999px !important; background: var(--ui-line-soft) !important; overflow: hidden !important; }
        .report-analytics-page .ra-fill { display: block !important; width: var(--bar-width) !important; height: 100% !important; border-radius: inherit !important; background: var(--bar-color, var(--ui-primary)) !important; }
        .report-analytics-page .ra-row-value { color: var(--ui-ink) !important; font-size: 12px !important; font-weight: 700 !important; text-align: right !important; font-variant-numeric: tabular-nums !important; }
        .report-analytics-page .ra-months { display: grid !important; grid-template-columns: repeat(12, minmax(34px, 1fr)) !important; align-items: end !important; gap: 8px !important; height: 190px !important; padding-top: 6px !important; border-bottom: 1px solid var(--ui-line) !important; }
        .report-analytics-page .ra-month { display: flex !important; flex-direction: column !important; justify-content: flex-end !important; gap: 6px !important; height: 100% !important; min-width: 0 !important; border-radius: 6px !important; outline: none !important; cursor: default !important; }
        .report-analytics-page .ra-month:hover,
        .report-analytics-page .ra-month:focus-visible { background: var(--ui-surface-2) !important; }
        .report-analytics-page .ra-month-bars { display: flex !important; align-items: flex-end !important; justify-content: center !important; gap: 3px !important; height: 150px !important; }
        .report-analytics-page .ra-month-bar { width: 8px !important; height: var(--bar-height) !important; min-height: 2px !important; border-radius: 3px 3px 0 0 !important; background: var(--bar-color) !important; transform-origin: bottom !important; }
        .report-analytics-page .ra-month-label { overflow: hidden !important; color: var(--ui-subtle) !important; font-size: 10.5px !important; text-align: center !important; white-space: nowrap !important; text-overflow: ellipsis !important; }
        .report-analytics-page .ra-tooltip { position: absolute !important; z-index: 5 !important; padding: 8px 10px !important; border-radius: 8px !important; background: #1b2420 !important; color: #fff !important; font-size: 11.5px !important; line-height: 1.5 !important; white-space: nowrap !important; pointer-events: none !important; box-shadow: 0 10px 24px rgba(0, 0, 0, .18) !important; transform: translate(-50%, -100%) !important; }
        .report-analytics-page .ra-tooltip[hidden] { display: none !important; }
        .report-analytics-page .ra-compare { display: grid !important; grid-template-columns: 130px minmax(0, 1fr) 110px !important; align-items: center !important; gap: 10px !important; margin-bottom: 12px !important; }
        .report-analytics-page .ra-compare-bars { display: grid !important; gap: 4px !important; }
        .report-analytics-page .ra-compare-bars .ra-fill { height: 8px !important; border-radius: 999px !important; }
        .report-analytics-page .ra-compare-amount { color: var(--ui-ink-2) !important; font-size: 11.5px !important; font-weight: 600 !important; text-align: right !important; white-space: nowrap !important; }
        .report-analytics-page .ra-compare-amount small { display: block !important; color: #047857 !important; font-size: 10.5px !important; font-weight: 600 !important; }

        /* Animation: only when JS runs and motion is welcome (see the script below). */
        .report-analytics-page.ra-animate .ra-anim .ra-fill,
        .report-analytics-page.ra-animate .ra-anim .ra-funnel-fill { width: 0 !important; transition: width .9s cubic-bezier(.2, .8, .2, 1) calc(var(--i, 0) * 70ms) !important; }
        .report-analytics-page.ra-animate .ra-anim.is-visible .ra-fill,
        .report-analytics-page.ra-animate .ra-anim.is-visible .ra-funnel-fill { width: var(--bar-width) !important; }
        .report-analytics-page.ra-animate .ra-anim .ra-month-bar { transform: scaleY(0) !important; transition: transform .8s cubic-bezier(.2, .8, .2, 1) calc(var(--i, 0) * 45ms) !important; }
        .report-analytics-page.ra-animate .ra-anim.is-visible .ra-month-bar { transform: scaleY(1) !important; }
        .report-analytics-page.ra-animate .ra-anim .ra-donut-seg { stroke-dasharray: 0 999 !important; transition: stroke-dasharray 1s cubic-bezier(.2, .8, .2, 1) calc(var(--i, 0) * 90ms) !important; }
        .report-analytics-page.ra-animate .ra-anim.is-visible .ra-donut-seg { stroke-dasharray: var(--len) 999 !important; }
        .report-analytics-page.ra-animate .ra-rise { opacity: 0; transform: translateY(8px); transition: opacity .5s ease calc(var(--i, 0) * 60ms), transform .5s ease calc(var(--i, 0) * 60ms); }
        .report-analytics-page.ra-animate .ra-rise.is-visible { opacity: 1; transform: none; }

        @media (max-width: 1200px) {
            .report-analytics-page .ra-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
            .report-analytics-page .ra-monitor-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) !important; }
            .report-analytics-page .ra-monitor-grid > :first-child { grid-column: 1 / -1 !important; }
        }
        @media (max-width: 760px) {
            .report-analytics-page .ra-chart-grid,
            .report-analytics-page .ra-monitor-grid { grid-template-columns: minmax(0, 1fr) !important; }
            .report-analytics-page .ra-chart.is-wide { grid-column: auto !important; }
            .report-analytics-page .ra-months { overflow-x: auto !important; grid-template-columns: repeat(12, 40px) !important; }
            .report-analytics-page .ra-donut-wrap { grid-template-columns: minmax(0, 1fr) !important; justify-items: center !important; }
            .report-analytics-page .ra-donut-wrap .ra-rows { width: 100% !important; }
            .report-analytics-page .ra-funnel li { grid-template-columns: 110px minmax(0, 1fr) 32px !important; }
            .report-analytics-page .ra-funnel-here { display: none !important; }
            .report-analytics-page .ra-filter-actions { width: 100% !important; }
            .report-analytics-page .ra-filter-actions > * { flex: 1 1 0 !important; }
        }
        @media (max-width: 520px) {
            .report-analytics-page .ra-kpis { grid-template-columns: minmax(0, 1fr) !important; }
            .report-analytics-page .ra-field { flex-basis: 100% !important; }
            .report-analytics-page .ra-compare { grid-template-columns: 90px minmax(0, 1fr) 90px !important; }
            .report-analytics-page .ra-head { flex-direction: column !important; }
            .report-analytics-page .ra-legend { justify-content: flex-start !important; }
        }
    </style>

    @include('partials.admin-sidebar')

    <div class="main-area">
        <x-page-header title="Report analytics" subtitle="Procurement performance and what needs the BAC's attention, from live records">
            <x-slot:actions>
                <div class="ra-export-wrap">
@php $exportFormats = [['label' => 'PDF report', 'hint' => 'Opens a print-ready report', 'url' => route('admin.reports.print', $filterQuery), 'icon' => 'fa-file-pdf', 'open' => true], ['label' => 'Excel (CSV)', 'hint' => 'Opens in Excel or any spreadsheet', 'url' => route('admin.reports.export.csv', $filterQuery), 'icon' => 'fa-file-excel']]; @endphp
                    <a href="{{ route('admin.reports.export.csv', $filterQuery) }}" id="reportExportToggle" class="ra-button" style="text-decoration:none" data-export-dialog data-export-title="Export report" data-export-note="Uses the period and filters currently shown on this page." data-export-formats='@json($exportFormats)'>
                        <i class="fas fa-file-export" aria-hidden="true"></i> Export
                    </a>
                </div>
                @include('partials.export-dialog')
            </x-slot:actions>
        </x-page-header>

        <main class="dashboard-content reports-page report-analytics-page">
            <script>
                // Animate only with JS and when the viewer has not asked for reduced motion.
                if (!window.matchMedia || !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    document.currentScript.parentElement.classList.add('ra-animate');
                }
            </script>

            <form method="GET" action="{{ route('admin.reports') }}" class="ra-card ra-filters ra-section" aria-label="Report filters">
                <div class="ra-presets" aria-label="Quick date ranges">
                    @foreach($datePresets as $preset)
                        <a href="{{ $preset['url'] }}" class="ra-preset {{ $preset['active'] ? 'is-active' : '' }}" @if($preset['active']) aria-current="true" @endif>{{ $preset['label'] }}</a>
                    @endforeach
                </div>
                <div class="ra-filter-row">
                    <label class="ra-field">
                        <span>Date from</span>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] }}">
                    </label>
                    <label class="ra-field">
                        <span>Date to</span>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] }}">
                    </label>
                    <label class="ra-field">
                        <span>Status</span>
                        <select name="status">
                            <option value="">All statuses</option>
                            @foreach($statusOptions as $statusKey => $statusLabel)
                                <option value="{{ $statusKey }}" @selected($filters['status'] === $statusKey)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="ra-field">
                        <span>Procurement type</span>
                        <select name="procurement_type">
                            <option value="">All procurement types</option>
                            @foreach($procurementTypeOptions as $typeKey => $typeLabel)
                                <option value="{{ $typeKey }}" @selected($filters['procurement_type'] === $typeKey)>{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="ra-filter-actions">
                        <button type="submit" class="ra-btn is-primary"><i class="fas fa-filter" aria-hidden="true"></i> Apply</button>
                        <a href="{{ route('admin.reports') }}" class="ra-btn">Reset</a>
                    </div>
                </div>
                @if($activeFilters !== [])
                    <div class="ra-chips">
                        <span>Showing:</span>
                        @foreach($activeFilters as $key => $label)
                            <span class="ra-chip">{{ $label }} <a href="{{ $withoutFilter($key) }}" aria-label="Remove filter {{ $label }}"><i class="fas fa-xmark" aria-hidden="true"></i></a></span>
                        @endforeach
                    </div>
                @endif
            </form>

            <section class="ra-kpis ra-section" aria-label="Key figures">
                @foreach($summaryCards as $card)
                    <article class="ra-card ra-kpi tone-{{ $card['tone'] }} ra-rise" style="--i: {{ $loop->index }}">
                        <div class="ra-kpi-top">
                            <span class="ra-kpi-icon"><i class="fas {{ $card['icon'] }}" aria-hidden="true"></i></span>
                            <span class="ra-kpi-label">{{ $card['label'] }}</span>
                        </div>
                        <strong class="ra-kpi-value" @if($card['value'] !== null) data-count="{{ $card['value'] }}" data-format="{{ $card['format'] }}" @endif>{{ $card['display'] }}</strong>
                        <span class="ra-kpi-note">{{ $card['note'] }}</span>
                    </article>
                @endforeach
            </section>

            <section class="ra-card ra-pipeline ra-section ra-anim" aria-label="Procurement pipeline">
                <div class="ra-head">
                    <div>
                        <h3>Procurement pipeline</h3>
                        <p class="ra-sub">How far the projects have gone. The bar counts every project that reached the stage; the tag shows the ones stopped there now.</p>
                    </div>
                </div>
                <ol class="ra-funnel">
                    @foreach($pipeline['stages'] as $stage)
                        <li>
                            <span class="ra-funnel-label">{{ $stage['label'] }}</span>
                            <span class="ra-funnel-track"><span class="ra-funnel-fill" style="--bar-width: {{ $stage['reached'] / $pipelineTotal * 100 }}%; --i: {{ $loop->index }}"></span></span>
                            <span class="ra-funnel-count">{{ $stage['reached'] }}</span>
                            <span class="ra-funnel-here {{ $stage['here'] > 0 && $stage['key'] !== 'completed' ? 'is-busy' : '' }}">{{ $stage['here'] }} here now</span>
                        </li>
                    @endforeach
                </ol>
                <div class="ra-funnel-foot">
                    <span><i class="fas fa-folder-open" aria-hidden="true"></i> {{ $pipeline['total'] }} {{ \Illuminate\Support\Str::plural('project', $pipeline['total']) }} in range</span>
                    <span><i class="fas fa-circle-xmark" aria-hidden="true" style="color:#ef4444"></i> {{ $pipeline['failed'] }} failed {{ \Illuminate\Support\Str::plural('bidding', $pipeline['failed']) }}</span>
                </div>
            </section>

            <section class="ra-monitor-grid ra-section" aria-label="Needs attention">
                <article class="ra-card ra-monitor ra-rise">
                    <div class="ra-head">
                        <div><h3>Needs action</h3><p class="ra-sub">Waiting on the BAC, by the saved schedule and recorded decisions</p></div>
                        <span class="ra-count {{ $monitoring['needs_action']['count'] ? 'is-danger' : '' }}">{{ $monitoring['needs_action']['count'] }}</span>
                    </div>
                    <ul class="ra-list">
                        @forelse($monitoring['needs_action']['items'] as $row)
                            <li>
                                <a href="{{ route('admin.project.view', $row['project']) }}" class="ra-link" style="min-width:0"><strong>{{ $row['project']->title }}</strong></a>
                                <span class="ra-reason is-{{ $row['tone'] }}">{{ $row['reason'] }}</span>
                            </li>
                        @empty
                            <li class="ra-empty">Nothing is waiting on the BAC.</li>
                        @endforelse
                    </ul>
                </article>
                <article class="ra-card ra-monitor ra-rise" style="--i: 1">
                    <div class="ra-head">
                        <div><h3>Bidder validations</h3><p class="ra-sub">Registrations to review</p></div>
                        <span class="ra-count {{ $monitoring['pending_bidder_validations']['count'] ? 'is-warning' : '' }}">{{ $monitoring['pending_bidder_validations']['count'] }}</span>
                    </div>
                    <ul class="ra-list">
                        @forelse($monitoring['pending_bidder_validations']['items'] as $user)
                            <li><a href="{{ route('admin.users.review', $user) }}" class="ra-link" style="min-width:0"><strong>{{ $user->company ?: $user->name }}</strong></a><span>{{ $user->created_at?->timezone($tz)->format('M d') }}</span></li>
                        @empty
                            <li class="ra-empty">No pending registrations.</li>
                        @endforelse
                    </ul>
                </article>
                <article class="ra-card ra-monitor ra-rise" style="--i: 2">
                    <div class="ra-head">
                        <div><h3>Awaiting BAC evaluation</h3><p class="ra-sub">Bids for committee review</p></div>
                        <span class="ra-count">{{ $monitoring['awaiting_bac_evaluation']['count'] }}</span>
                    </div>
                    <ul class="ra-list">
                        @forelse($monitoring['awaiting_bac_evaluation']['items'] as $row)
                            <li><a href="{{ route('admin.project.view', $row['project']) }}" class="ra-link" style="min-width:0"><strong>{{ $row['project']->title }}</strong></a><span>{{ $row['bids'] }} {{ \Illuminate\Support\Str::plural('bid', $row['bids']) }}</span></li>
                        @empty
                            <li class="ra-empty">No bids awaiting evaluation.</li>
                        @endforelse
                    </ul>
                </article>
            </section>

            <section class="ra-card ra-agenda ra-section ra-rise" aria-label="Upcoming schedule">
                <div class="ra-head">
                    <div><h3>Next 14 days</h3><p class="ra-sub">Pre-bid conferences, submission deadlines and bid openings (Philippine time)</p></div>
                    <div class="ra-legend">
                        <span><i class="fas fa-people-group" style="color:#0369a1" aria-hidden="true"></i> Pre-bid</span>
                        <span><i class="fas fa-hourglass-end" style="color:#b45309" aria-hidden="true"></i> Deadline</span>
                        <span><i class="fas fa-envelope-open" style="color:var(--ui-primary)" aria-hidden="true"></i> Opening</span>
                    </div>
                </div>
                @if($upcomingSchedule === [])
                    <p class="ra-empty">Nothing scheduled in the next 14 days.</p>
                @else
                    <div class="ra-days">
                        @foreach($upcomingSchedule as $day)
                            <div class="ra-day {{ $day['date']->isToday() ? 'is-today' : '' }}">
                                <div class="ra-day-head">
                                    <strong>{{ $day['date']->isToday() ? 'Today' : ($day['date']->isTomorrow() ? 'Tomorrow' : $day['date']->format('D, M d')) }}</strong>
                                    <span>{{ $day['date']->isToday() || $day['date']->isTomorrow() ? $day['date']->format('D, M d') : 'in '.(int) round(now($tz)->startOfDay()->diffInDays($day['date'])).' days' }}</span>
                                </div>
                                @foreach($day['events'] as $event)
                                    <a href="{{ route('admin.project.view', $event['project']) }}" class="ra-event is-{{ $event['type'] }} ra-link">
                                        <span class="ra-event-icon"><i class="fas {{ $eventIcons[$event['type']] }}" aria-hidden="true"></i></span>
                                        <span style="min-width:0">
                                            <strong>{{ $event['project']->title }}</strong>
                                            <small>{{ $event['time']->format('h:i A') }} · {{ $event['label'] }}</small>
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="ra-chart-grid" aria-label="Charts">
                <article class="ra-card ra-chart ra-anim">
                    <div class="ra-head">
                        <div><h3>Where projects stand</h3><p class="ra-sub">Current stage by schedule and recorded decisions</p></div>
                    </div>
                    <div class="ra-donut-wrap">
                        <svg class="ra-donut" viewBox="0 0 120 120" role="img" aria-label="Projects by stage">
                            <circle cx="60" cy="60" r="48" stroke="var(--ui-line-soft)"></circle>
                            <g transform="rotate(-90 60 60)">
                                @foreach($procurementStatusDistribution as $row)
                                    @continue($row['value'] === 0)
                                    @php $len = $row['value'] / $statusTotal * $donutC; @endphp
                                    <circle class="ra-donut-seg" cx="60" cy="60" r="48" stroke="{{ $row['color'] }}"
                                        style="--len: {{ $len }}; --i: {{ $loop->index }}; stroke-dasharray: {{ $len }} 999; stroke-dashoffset: {{ -$donutOffset }};">
                                        <title>{{ $row['label'] }}: {{ $row['value'] }}</title>
                                    </circle>
                                    @php $donutOffset += $len; @endphp
                                @endforeach
                            </g>
                            <text x="60" y="60" text-anchor="middle" class="ra-donut-total">{{ collect($procurementStatusDistribution)->sum('value') }}</text>
                            <text x="60" y="74" text-anchor="middle" class="ra-donut-caption">PROJECTS</text>
                        </svg>
                        <div class="ra-rows">
                            @foreach($procurementStatusDistribution as $row)
                                @continue($row['value'] === 0 && ! in_array($row['key'], ['accepting', 'awaiting_opening', 'evaluation', 'awarded'], true))
                                <div class="ra-row">
                                    <span class="ra-row-label"><i class="ra-dot" style="--dot: {{ $row['color'] }}"></i><span>{{ $row['label'] }}</span></span>
                                    <span class="ra-track"><span class="ra-fill" style="--bar-width: {{ $row['value'] / $chartMaxima['status'] * 100 }}%; --bar-color: {{ $row['color'] }}; --i: {{ $loop->index }}"></span></span>
                                    <strong class="ra-row-value">{{ $row['value'] }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </article>

                <article class="ra-card ra-chart ra-anim">
                    <div class="ra-head">
                        <div><h3>Bids per project</h3><p class="ra-sub">Official bids received, most first</p></div>
                    </div>
                    <div class="ra-rows">
                        @forelse($bidsPerProject as $row)
                            <div class="ra-row" title="{{ $row['full_label'] }}">
                                <span class="ra-row-label"><span>{{ $row['label'] }}</span></span>
                                <span class="ra-track"><span class="ra-fill" style="--bar-width: {{ $row['value'] / $chartMaxima['bids_per_project'] * 100 }}%; --bar-color: #235e4c; --i: {{ $loop->index }}"></span></span>
                                <strong class="ra-row-value">{{ $row['value'] }}</strong>
                            </div>
                        @empty
                            <p class="ra-empty">No official bids for the selected projects.</p>
                        @endforelse
                    </div>
                </article>

                <article class="ra-card ra-chart is-wide ra-anim" data-month-chart>
                    <div class="ra-head">
                        <div><h3>Monthly activity</h3><p class="ra-sub">Projects created, official bids and awards per month. Point at a month for the numbers.</p></div>
                        <div class="ra-legend">
                            <span><i class="ra-dot" style="--dot:#235e4c"></i>Projects</span>
                            <span><i class="ra-dot" style="--dot:#10b981"></i>Bids</span>
                            <span><i class="ra-dot" style="--dot:#f59e0b"></i>Awards</span>
                        </div>
                    </div>
                    <div class="ra-months">
                        @foreach($monthlyActivity as $row)
                            <div class="ra-month" tabindex="0" data-tip="{{ $row['label'] }}|{{ $row['projects'] }}|{{ $row['bids'] }}|{{ $row['awards'] }}"
                                aria-label="{{ $row['label'] }}: {{ $row['projects'] }} projects, {{ $row['bids'] }} bids, {{ $row['awards'] }} awards">
                                <div class="ra-month-bars">
                                    <span class="ra-month-bar" style="--bar-height: {{ max(1.5, $row['projects'] / $chartMaxima['activity'] * 100) }}%; --bar-color:#235e4c; --i: {{ $loop->index }}"></span>
                                    <span class="ra-month-bar" style="--bar-height: {{ max(1.5, $row['bids'] / $chartMaxima['activity'] * 100) }}%; --bar-color:#10b981; --i: {{ $loop->index }}"></span>
                                    <span class="ra-month-bar" style="--bar-height: {{ max(1.5, $row['awards'] / $chartMaxima['activity'] * 100) }}%; --bar-color:#f59e0b; --i: {{ $loop->index }}"></span>
                                </div>
                                <span class="ra-month-label">{{ \Carbon\Carbon::parse($row['key'].'-01')->format('M y') }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="ra-tooltip" role="status" hidden></div>
                </article>

                <article class="ra-card ra-chart ra-anim">
                    <div class="ra-head">
                        <div><h3>ABC vs contract amount</h3><p class="ra-sub">Approved budget against the awarded amount</p></div>
                        <div class="ra-legend"><span><i class="ra-dot" style="--dot:#99a19c"></i>ABC</span><span><i class="ra-dot" style="--dot:#235e4c"></i>Awarded</span></div>
                    </div>
                    @forelse($abcVsWinning as $row)
                        <div class="ra-compare" title="{{ $row['full_label'] }}">
                            <span class="ra-row-label"><span>{{ $row['label'] }}</span></span>
                            <span class="ra-compare-bars">
                                <span class="ra-track"><span class="ra-fill" style="--bar-width: {{ $row['abc'] / $chartMaxima['abc'] * 100 }}%; --bar-color:#99a19c; --i: {{ $loop->index }}"></span></span>
                                <span class="ra-track"><span class="ra-fill" style="--bar-width: {{ $row['winning'] / $chartMaxima['abc'] * 100 }}%; --bar-color:#235e4c; --i: {{ $loop->index }}"></span></span>
                            </span>
                            <span class="ra-compare-amount">
                                ₱{{ number_format($row['winning'], 2) }}
                                @if($row['abc'] > 0 && $row['winning'] <= $row['abc'])<small>{{ number_format(($row['abc'] - $row['winning']) / $row['abc'] * 100, 1) }}% saved</small>@endif
                            </span>
                        </div>
                    @empty
                        <p class="ra-empty">No awards for the selected projects.</p>
                    @endforelse
                </article>

                <article class="ra-card ra-chart ra-anim">
                    <div class="ra-head">
                        <div><h3>Bid results</h3><p class="ra-sub">Official bids by outcome so far</p></div>
                    </div>
                    <div class="ra-rows">
                        @foreach($bidResultDistribution as $row)
                            <div class="ra-row">
                                <span class="ra-row-label"><i class="ra-dot" style="--dot: {{ $row['color'] }}"></i><span>{{ $row['label'] }}</span></span>
                                <span class="ra-track"><span class="ra-fill" style="--bar-width: {{ $row['value'] / $chartMaxima['bid_result'] * 100 }}%; --bar-color: {{ $row['color'] }}; --i: {{ $loop->index }}"></span></span>
                                <strong class="ra-row-value">{{ $row['value'] }}</strong>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="ra-card ra-chart is-wide ra-anim">
                    <div class="ra-head">
                        <div><h3>Bidder participation</h3><p class="ra-sub">Official bids by bidder · {{ number_format($bidderTotals['registered']) }} registered, {{ number_format($bidderTotals['blacklisted']) }} blacklisted</p></div>
                    </div>
                    <div class="ra-rows">
                        @forelse($bidderParticipation as $row)
                            <div class="ra-row" title="{{ $row['full_label'] }}">
                                <span class="ra-row-label"><span>{{ $row['label'] }}</span></span>
                                <span class="ra-track"><span class="ra-fill" style="--bar-width: {{ $row['value'] / $chartMaxima['bidder'] * 100 }}%; --bar-color: #7c3aed; --i: {{ $loop->index }}"></span></span>
                                <strong class="ra-row-value">{{ $row['value'] }}</strong>
                            </div>
                        @empty
                            <p class="ra-empty">No bidder participation for the selected projects.</p>
                        @endforelse
                    </div>
                </article>
            </section>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Export menu
        const toggle = document.getElementById('reportExportToggle');
        const menu = document.getElementById('reportExportMenu');
        if (toggle && menu) {
            toggle.addEventListener('click', function (event) {
                event.stopPropagation();
                const open = menu.hasAttribute('hidden');
                menu.toggleAttribute('hidden', !open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            document.addEventListener('click', function () {
                menu.setAttribute('hidden', '');
                toggle.setAttribute('aria-expanded', 'false');
            });
        }

        const page = document.querySelector('.report-analytics-page');
        const animate = page && page.classList.contains('ra-animate');

        // Count-up for the key figures.
        const format = function (value, kind) {
            if (kind === 'peso') return '₱' + value.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (kind === 'percent') return value.toLocaleString('en-PH', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';
            if (kind === 'decimal') return value.toLocaleString('en-PH', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
            return Math.round(value).toLocaleString('en-PH');
        };
        const countUp = function (element) {
            const target = parseFloat(element.dataset.count);
            if (!isFinite(target)) return;
            const start = performance.now();
            const duration = 900;
            const step = function (now) {
                const t = Math.min(1, (now - start) / duration);
                const eased = 1 - Math.pow(1 - t, 3);
                element.textContent = format(target * eased, element.dataset.format);
                if (t < 1) requestAnimationFrame(step); else element.textContent = format(target, element.dataset.format);
            };
            requestAnimationFrame(step);
        };

        // Reveal each block as it scrolls into view, once.
        const reveal = function (element) {
            element.classList.add('is-visible');
            if (animate) element.querySelectorAll('[data-count]').forEach(countUp);
        };
        const targets = document.querySelectorAll('.ra-anim, .ra-rise');
        if (!animate || !('IntersectionObserver' in window)) {
            targets.forEach(function (element) { element.classList.add('is-visible'); });
        } else {
            const observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    reveal(entry.target);
                    observer.unobserve(entry.target);
                });
            }, { threshold: 0.15 });
            targets.forEach(function (element) { observer.observe(element); });
        }

        // Monthly activity tooltip (pointer and keyboard).
        document.querySelectorAll('[data-month-chart]').forEach(function (chart) {
            const tip = chart.querySelector('.ra-tooltip');
            const show = function (month) {
                const parts = month.dataset.tip.split('|');
                tip.innerHTML = '';
                const title = document.createElement('strong');
                title.textContent = parts[0];
                tip.append(title, document.createElement('br'), parts[1] + ' projects · ' + parts[2] + ' bids · ' + parts[3] + ' awards');
                const box = chart.getBoundingClientRect();
                const rect = month.getBoundingClientRect();
                tip.style.left = (rect.left - box.left + rect.width / 2) + 'px';
                tip.style.top = (rect.top - box.top + 4) + 'px';
                tip.hidden = false;
            };
            chart.querySelectorAll('.ra-month').forEach(function (month) {
                month.addEventListener('mouseenter', function () { show(month); });
                month.addEventListener('focus', function () { show(month); });
                month.addEventListener('mouseleave', function () { tip.hidden = true; });
                month.addEventListener('blur', function () { tip.hidden = true; });
            });
        });
    });
</script>
