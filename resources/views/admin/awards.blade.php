<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard admin-role-page admin-awards-page">
    @vite(['resources/css/dashboard.css'])

    <style>
        body .admin-dashboard.admin-role-page.admin-awards-page .admin-awards-table-card {
            padding: 16px !important;
            overflow-x: auto;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .admin-awards-table th,
        body .admin-dashboard.admin-role-page.admin-awards-page .admin-awards-table td {
            padding: 10px 10px !important;
            vertical-align: middle !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .admin-awards-table thead tr,
        body .admin-dashboard.admin-role-page.admin-awards-page .admin-awards-table thead th {
            background: var(--ui-surface-2) !important;
            color: var(--ui-muted) !important;
            border-color: var(--ui-line) !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .admin-awards-table th:nth-child(5),
        body .admin-dashboard.admin-role-page.admin-awards-page .admin-awards-table td:nth-child(5) {
            width: 92px;
            min-width: 92px;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .admin-awards-table th:nth-child(7),
        body .admin-dashboard.admin-role-page.admin-awards-page .admin-awards-table td:nth-child(7) {
            width: 118px;
            min-width: 118px;
            text-align: center !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .admin-awards-status-cell,
        body .admin-dashboard.admin-role-page.admin-awards-page .admin-awards-actions-cell {
            white-space: nowrap;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .admin-awards-status-badge {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            min-width: 54px;
            min-height: 24px;
            line-height: 1 !important;
            white-space: nowrap !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .admin-award-certificate-cell {
            min-width: 220px;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .admin-award-certificate {
            display: flex;
            align-items: center !important;
            gap: 9px !important;
            min-height: 108px;
            padding: 6px !important;
            box-sizing: border-box;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .admin-award-certificate-qr {
            display: inline-flex;
            width: 96px !important;
            height: 96px !important;
            min-width: 96px !important;
            min-height: 96px !important;
            max-width: 96px !important;
            max-height: 96px !important;
            padding: 6px !important;
            border: 1px solid var(--ui-line);
            border-radius: var(--ui-radius-lg);
            background: #fff;
            flex: 0 0 96px !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .admin-award-certificate-qr img {
            width: 100% !important;
            height: 100% !important;
            object-fit: contain !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .admin-award-certificate-meta {
            display: grid !important;
            align-content: center !important;
            gap: 3px !important;
            min-width: 0;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .admin-award-certificate-meta strong {
            display: block;
            margin-bottom: 0;
            color: var(--ui-ink);
            font-size: 11px;
            white-space: nowrap;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .admin-award-certificate-meta span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--ui-muted);
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .admin-award-no-certificate {
            display: inline-flex;
            align-items: center;
            min-height: 108px;
            white-space: nowrap;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .admin-awards-action-button {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: 104px;
            height: 36px;
            padding: 0 12px !important;
            box-sizing: border-box;
            line-height: 1 !important;
            white-space: nowrap !important;
        }
    </style>

    <style id="awards-registry-styles">
        body .admin-dashboard.admin-role-page.admin-awards-page .admin-awards-content {
            color: var(--ui-ink) !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-page-intro {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
            margin: 0 0 24px;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-eyebrow {
            display: block;
            margin-bottom: 8px;
            color: var(--ui-primary-hover) !important;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: normal;
            text-transform: none;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-page-intro h1 {
            margin: 0;
            color: var(--ui-ink) !important;
            font-size: clamp(24px, 2.3vw, 32px);
            font-weight: 700;
            letter-spacing: -.03em;
            line-height: 1.12;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-page-intro p {
            max-width: 640px;
            margin: 10px 0 0;
            color: var(--ui-muted) !important;
            font-size: 14px;
            line-height: 1.55;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
            min-height: 40px;
            padding: 0 14px;
            border: 1px solid var(--ui-line-strong);
            border-radius: var(--ui-radius-lg);
            background: var(--ui-surface-2);
            color: var(--ui-ink-2) !important;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: background .18s ease, border-color .18s ease, transform .18s ease;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-back-link:hover {
            border-color: #c4b5a5;
            background: #ffffff;
            transform: translateY(-1px);
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-card {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            min-width: 0;
            padding: 18px;
            border: 1px solid var(--ui-line);
            border-radius: var(--ui-radius-lg);
            background: var(--ui-surface-2);
            box-shadow: var(--ui-shadow);
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            border-radius: var(--ui-radius-lg);
            background: #fef3c7;
            color: #a16207;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon.is-green {
            background: #ecfdf5;
            color: #047857;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon.is-blue {
            background: var(--ui-primary-soft);
            color: var(--ui-primary);
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon.is-dark {
            background: var(--ui-line-soft);
            color: var(--ui-ink-2);
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-label,
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-note {
            display: block;
            color: var(--ui-muted) !important;
            font-size: 12px;
            line-height: 1.35;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-value {
            display: block;
            margin: 5px 0 3px;
            color: var(--ui-ink) !important;
            font-size: 23px;
            font-weight: 700;
            letter-spacing: -.03em;
            line-height: 1;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-toolbar-card,
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-table-card,
        body .admin-dashboard.admin-role-page.admin-awards-page .ready-awards-card {
            border: 1px solid var(--ui-line);
            border-radius: var(--ui-radius-lg);
            background: var(--ui-surface-2);
            box-shadow: var(--ui-shadow);
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-toolbar-card {
            padding: 20px;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-toolbar-top,
        body .admin-dashboard.admin-role-page.admin-awards-page .ready-awards-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-toolbar-title,
        body .admin-dashboard.admin-role-page.admin-awards-page .ready-awards-title {
            margin: 0;
            color: var(--ui-ink) !important;
            font-size: 17px;
            font-weight: 700;
            letter-spacing: -.015em;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-toolbar-description,
        body .admin-dashboard.admin-role-page.admin-awards-page .ready-awards-description {
            margin: 5px 0 0;
            color: var(--ui-muted) !important;
            font-size: 13px;
            line-height: 1.45;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-record-count,
        body .admin-dashboard.admin-role-page.admin-awards-page .ready-awards-count {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 10px;
            border: 1px solid var(--ui-line);
            border-radius: 999px;
            background: #f7f5f2;
            color: var(--ui-muted) !important;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-toolbar-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 18px;
        }

        /* Filter row: search and status sit inline as two separate bordered fields, no outer wrapper box. */
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-filter-group {
            display: flex;
            align-items: center;
            flex: 1 1 auto;
            min-width: 0;
            gap: 10px;
            border: 0;
            background: transparent;
            box-shadow: none;
            padding: 0;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-filter-divider {
            display: none;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-search-field {
            position: relative;
            display: flex !important;
            align-items: center;
            flex: 1 1 240px;
            min-width: 200px;
            width: auto !important;
            height: 40px !important;
            min-height: 40px !important;
            box-sizing: border-box !important;
            border: 1px solid var(--ui-line-strong) !important;
            border-radius: var(--ui-radius-lg) !important;
            background: #ffffff !important;
            overflow: hidden;
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-search-field:focus-within {
            border-color: var(--ui-primary-hover) !important;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, .13) !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-status-field {
            display: flex;
            align-items: center;
            flex: 0 0 auto;
            min-width: 150px;
            height: 40px;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-search-field .admin-search-icon {
            position: absolute !important;
            top: 50% !important;
            left: 13px !important;
            z-index: 1 !important;
            width: 14px !important;
            height: 14px !important;
            margin: 0 !important;
            color: var(--ui-subtle) !important;
            pointer-events: none !important;
            transform: translateY(-50%) !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-search-field input {
            width: 100% !important;
            height: 36px !important;
            min-height: 36px !important;
            box-sizing: border-box !important;
            border: 0 !important;
            border-radius: var(--ui-radius) !important;
            background: transparent !important;
            color: var(--ui-ink) !important;
            font-size: 13px !important;
            outline: none !important;
            padding: 0 13px 0 36px !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-status-select {
            width: 100% !important;
            height: 40px !important;
            min-height: 40px !important;
            box-sizing: border-box !important;
            border: 1px solid var(--ui-line-strong) !important;
            border-radius: 10px !important;
            background: #ffffff !important;
            color: var(--ui-ink) !important;
            font-size: 13px !important;
            outline: none !important;
            min-width: 150px;
            padding: 0 34px 0 12px !important;
            transition: border-color .18s ease, box-shadow .18s ease !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-status-select:focus {
            border-color: var(--ui-primary-hover) !important;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, .13) !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-clear-button {
            height: 36px;
            min-height: 36px;
            padding: 0 13px;
            border: 0;
            border-radius: var(--ui-radius);
            background: transparent;
            color: var(--ui-muted);
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-clear-button:hover {
            background: var(--ui-page);
            color: var(--ui-ink);
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-filter-tabs {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 17px;
            overflow-x: auto;
            scrollbar-width: none;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-filter-tabs::-webkit-scrollbar {
            display: none;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-filter-tab {
            min-height: 32px;
            padding: 0 12px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: var(--ui-muted);
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-filter-tab:hover {
            background: var(--ui-page);
            color: #44403c;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-filter-tab.is-active {
            background: var(--ui-primary-soft);
            color: var(--ui-primary-hover);
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-table-card {
            margin-top: 16px;
            overflow: hidden;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-table-wrap {
            width: 100%;
            overflow-x: hidden;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            table-layout: fixed !important;
            border-collapse: collapse;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table th {
            padding: 11px 8px;
            border-bottom: 1px solid var(--ui-line);
            background: #faf9f7;
            color: var(--ui-muted) !important;
            font-size: 9px;
            font-weight: 600;
            letter-spacing: normal;
            line-height: 1.25;
            text-align: left;
            text-transform: none;
            white-space: normal;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table td {
            padding: 12px 8px;
            border-bottom: 1px solid var(--ui-line-soft);
            color: var(--ui-ink-2) !important;
            font-size: 12px;
            vertical-align: middle;
            box-sizing: border-box !important;
            min-width: 0 !important;
            overflow-wrap: normal !important;
            word-break: normal !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table th:nth-child(7),
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table td:nth-child(7) {
            white-space: nowrap !important;
        }

        /* Fixed layout: the short columns get what their content needs; project, bidder
           and QR (no width) share the rest, so nothing spills into a neighbour. */
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table th:nth-child(3) { width: 132px; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table th:nth-child(4) { width: 128px; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table th:nth-child(5) { width: 214px; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table th:nth-child(7) { width: 136px; }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr {
            transition: background .16s ease;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr:hover {
            background: #fffaf6;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr:last-child td {
            border-bottom: 0;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-project-cell,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-party-cell,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-certificate-chip {
            display: flex;
            align-items: center;
            gap: 7px;
            min-width: 0;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-project-icon,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-certificate-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            flex: 0 0 28px;
            border-radius: 8px;
            background: #fff1eb;
            color: #c2410c;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-project-cell > div:last-child,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-party-cell > div,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-certificate-chip > div {
            min-width: 0;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-project-cell strong,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-party-cell strong,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-certificate-chip strong {
            display: block;
            overflow: hidden;
            color: var(--ui-ink) !important;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.35;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Names get two lines before they are cut, so narrower columns stay readable. */
        body .admin-dashboard.admin-role-page.admin-awards-page .award-project-cell strong,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-party-cell strong {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-project-cell span,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-party-cell span,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-certificate-chip small {
            display: block;
            margin-top: 3px;
            overflow: hidden;
            color: var(--ui-subtle) !important;
            font-size: 10px;
            line-height: 1.3;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-amount {
            display: block;
            color: var(--ui-ink) !important;
            font-size: 12px;
            font-variant-numeric: tabular-nums;
            font-weight: 600;
            white-space: nowrap !important;
            overflow-wrap: normal !important;
            word-break: normal !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-amount.is-savings {
            color: #047857 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-amount.is-over-budget {
            color: #b91c1c !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-amount-note {
            display: block;
            margin-top: 3px;
            color: var(--ui-subtle) !important;
            font-size: 10px;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-date {
            color: var(--ui-ink-2) !important;
            font-size: 11px;
            white-space: nowrap !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            max-width: 100%;
            min-height: 26px;
            padding: 0 7px;
            border-radius: 999px;
            box-sizing: border-box;
            font-size: 10px;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-status::before {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            content: '';
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-status.is-valid {
            background: #ecfdf5;
            color: #047857 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-status.is-revoked {
            background: #fef2f2;
            color: #b91c1c !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-status.is-expired,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-status.is-pending {
            background: #fffbeb;
            color: #a16207 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-certificate-chip {
            gap: 6px;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-certificate-icon {
            width: 27px;
            height: 27px;
            flex-basis: 27px;
            background: var(--ui-primary-soft);
            color: var(--ui-primary);
            border: 1px solid var(--ui-primary-soft);
            text-decoration: none;
            overflow: hidden;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-certificate-icon img {
            width: 21px;
            height: 21px;
            object-fit: contain;
            border-radius: 3px;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-certificate-muted {
            color: var(--ui-subtle) !important;
            font-size: 10px;
            white-space: nowrap;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action,
        body .admin-dashboard.admin-role-page.admin-awards-page .ready-award-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            min-height: 32px;
            padding: 0 7px;
            border-radius: var(--ui-radius-lg);
            cursor: pointer;
            font-size: 10px;
            font-weight: 500;
            white-space: nowrap;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action {
            width: 100%;
            min-width: 0;
            white-space: nowrap !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action {
            border: 1px solid var(--ui-line-strong);
            background: #ffffff;
            color: var(--ui-ink-2);
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action:hover {
            border-color: #c4b5a5;
            background: #fffaf6;
            color: #c2410c;
        }

        /* Keep the project and bidder icon tiles centered after the cell text rules. */
        body .admin-dashboard.admin-role-page.admin-awards-page .award-project-cell > .award-project-icon,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-party-cell > .award-project-icon {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 28px !important;
            height: 28px !important;
            min-width: 28px !important;
            flex: 0 0 28px !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
            color: #c2410c !important;
            font-size: 12px !important;
            line-height: 1 !important;
            text-overflow: clip !important;
            white-space: normal !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-project-cell > .award-project-icon > i,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-party-cell > .award-project-icon > i {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 1em !important;
            height: 1em !important;
            margin: 0 !important;
            padding: 0 !important;
            position: static !important;
            line-height: 1 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table td:nth-child(7) {
            padding: 10px 12px !important;
            text-align: center !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action {
            width: 100% !important;
            min-width: 0 !important;
            max-width: 140px;
            min-height: 34px !important;
            box-sizing: border-box !important;
            padding: 0 10px !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action > i {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            margin: 0 !important;
            line-height: 1 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action > span {
            display: inline-block !important;
            margin: 0 !important;
            overflow: visible !important;
            text-overflow: clip !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-empty {
            padding: 52px 20px !important;
            color: var(--ui-subtle) !important;
            text-align: center;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-empty i {
            display: block;
            margin-bottom: 12px;
            color: var(--ui-line-strong);
            font-size: 32px;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-empty strong {
            display: block;
            margin-bottom: 5px;
            color: var(--ui-ink-2) !important;
            font-size: 14px;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-empty span {
            font-size: 12px;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .ready-awards-card {
            margin-top: 20px;
            overflow: hidden;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .ready-awards-card + .awards-toolbar-card {
            margin-top: 20px;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .ready-awards-header {
            padding: 20px;
            border-bottom: 1px solid #eee9e3;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .ready-awards-list {
            display: grid;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .ready-award-row {
            display: grid;
            grid-template-columns: minmax(220px, 1.5fr) minmax(150px, 1fr) minmax(130px, .8fr) minmax(170px, 1fr) auto;
            align-items: center;
            gap: 16px;
            padding: 17px 20px;
            border-bottom: 1px solid var(--ui-line-soft);
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .ready-award-row:last-child {
            border-bottom: 0;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .ready-award-project strong,
        body .admin-dashboard.admin-role-page.admin-awards-page .ready-award-metric strong {
            display: block;
            color: var(--ui-ink) !important;
            font-size: 13px;
            font-weight: 700;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .ready-award-project span,
        body .admin-dashboard.admin-role-page.admin-awards-page .ready-award-metric span {
            display: block;
            margin-top: 4px;
            color: var(--ui-subtle) !important;
            font-size: 11px;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .ready-award-metric strong {
            font-variant-numeric: tabular-nums;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .ready-award-metric.is-highlight strong {
            color: var(--ui-primary-hover) !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .ready-award-button {
            border: 0;
            background: var(--ui-primary);
            color: #ffffff;
            box-shadow: none;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .ready-award-button:hover {
            background: var(--ui-primary-hover);
            transform: translateY(-1px);
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .ready-awards-empty {
            padding: 26px 20px;
            color: var(--ui-subtle) !important;
            font-size: 13px;
            text-align: center;
        }

        @media (max-width: 1180px) {

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .ready-award-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            body .admin-dashboard.admin-role-page.admin-awards-page .award-certificate-muted {
                white-space: normal;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-page-intro,
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-toolbar-controls,
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-toolbar-top,
            body .admin-dashboard.admin-role-page.admin-awards-page .ready-awards-header {
                align-items: stretch;
                flex-direction: column;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-back-link {
                align-self: flex-start;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-summary-grid {
                grid-template-columns: 1fr;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-filter-group {
                flex-direction: column;
                align-items: stretch;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-filter-divider {
                display: none;
            }

            /* In the stacked column the 240px flex-basis would become the field's height. */
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-search-field {
                flex: 0 0 auto;
                min-width: 0;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-record-count {
                align-self: flex-start;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-status-field,
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-status-select {
                min-width: 0;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .ready-award-row {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .ready-award-button {
                justify-self: start;
            }
        }

        /* Keep the Awards registry measured like the existing Admin tables. */
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table :is(th, td) {
            box-sizing: border-box !important;
            vertical-align: middle !important;
            line-height: 1.35 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table thead th {
            height: 42px !important;
            min-height: 42px !important;
            padding: 0 12px !important;
            background: var(--ui-surface-2) !important;
            color: var(--ui-muted) !important;
            font-size: 10px !important;
            font-weight: 600 !important;
            letter-spacing: normal !important;
            line-height: 1.2 !important;
            text-align: left !important;
            text-transform: none !important;
            white-space: nowrap !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody td {
            padding: 10px 12px !important;
            font-size: 12px !important;
            line-height: 1.35 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table th:nth-child(3),
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table td:nth-child(3) {
            text-align: right !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table th:nth-child(7),
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table td:nth-child(7) {
            text-align: right !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-project-cell,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-party-cell,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-certificate-chip {
            gap: 8px !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-project-cell strong {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.3 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-party-cell strong {
            font-size: 14px !important;
            font-weight: 500 !important;
            line-height: 1.3 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-project-cell span,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-party-cell span {
            font-size: 12px !important;
            line-height: 1.3 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-amount {
            font-size: 13px !important;
            font-weight: 600 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-amount-note,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-certificate-muted {
            font-size: 11px !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-date {
            font-size: 12px !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-status {
            min-height: 28px !important;
            padding: 0 9px !important;
            gap: 6px !important;
            font-size: 11px !important;
            font-weight: 600 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-certificate-chip strong {
            font-size: 12px !important;
            font-weight: 500 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-certificate-chip small {
            font-size: 11px !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action {
            width: auto !important;
            max-width: 100% !important;
            min-width: 0 !important;
            min-height: 34px !important;
            padding: 0 9px !important;
            gap: 6px !important;
            font-size: 12px !important;
            font-weight: 500 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action > i {
            width: 13px !important;
            font-size: 12px !important;
        }

        /* Below 1320px the seven columns no longer fit: each award becomes a labelled card. */
        @media (max-width: 1320px) {
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table thead {
                display: none !important;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table,
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody {
                display: block !important;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row]:not([hidden]) {
                display: grid !important;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 14px 18px;
                padding: 16px 18px;
                border-bottom: 1px solid var(--ui-line-soft);
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row]:last-of-type {
                border-bottom: 0;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td {
                display: block !important;
                width: auto !important;
                min-width: 0 !important;
                padding: 0 !important;
                border: 0 !important;
                text-align: left !important;
                white-space: normal !important;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td[data-label]::before {
                display: block;
                margin-bottom: 5px;
                color: var(--ui-subtle);
                content: attr(data-label);
                font-size: 11px;
                font-weight: 600;
                line-height: 1.2;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td:nth-child(1),
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td:nth-child(2) {
                grid-column: span 2;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td.award-action-cell {
                grid-column: 1 / -1;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td.award-action-cell .award-row-action {
                width: 100% !important;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr:not([data-award-row]):not([hidden]) {
                display: block !important;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr:not([data-award-row]):not([hidden]) > td {
                display: block !important;
            }
        }

        @media (max-width: 640px) {
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                padding: 14px;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td:nth-child(1),
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td:nth-child(2) {
                grid-column: 1 / -1;
            }
        }

        /* Match the Projects/Biddings summary-card and primary view-action treatment. */
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-summary-grid {
            gap: 10px !important;
            padding: 18px !important;
            border: 1px solid var(--ui-line) !important;
            border-radius: 8px !important;
            background: #ffffff !important;
            box-shadow: var(--ui-shadow) !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-card {
            min-height: 92px !important;
            align-items: center !important;
            gap: 14px !important;
            padding: 14px !important;
            border: 1px solid var(--ui-line-strong) !important;
            border-radius: 8px !important;
            background: #ffffff !important;
            box-shadow: none !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon {
            width: 50px !important;
            height: 50px !important;
            flex: 0 0 50px !important;
            border-radius: var(--ui-radius-lg) !important;
            font-size: 21px !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon i {
            font-size: 20px !important;
            line-height: 1 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon.is-dark,
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon.is-blue {
            background: var(--ui-primary-soft) !important;
            color: var(--ui-primary) !important;
            -webkit-text-fill-color: var(--ui-primary) !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon.is-green {
            background: #dcfce7 !important;
            color: #15803d !important;
            -webkit-text-fill-color: #15803d !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-card > div {
            display: flex !important;
            min-width: 0 !important;
            flex-direction: column !important;
            align-items: flex-start !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-label {
            order: 2 !important;
            margin-top: 3px !important;
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
            font-size: 10px !important;
            font-weight: 700 !important;
            line-height: 1.15 !important;
            letter-spacing: 0 !important;
            text-transform: none !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-value {
            order: 1 !important;
            margin: 0 !important;
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
            font-size: 20px !important;
            font-weight: 700 !important;
            line-height: 1 !important;
            letter-spacing: 0 !important;
            white-space: nowrap !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-note {
            order: 3 !important;
            margin-top: 4px !important;
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
            font-size: 10px !important;
            line-height: 1.2 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action {
            min-height: 34px !important;
            width: auto !important;
            max-width: 100% !important;
            padding: 7px 13px !important;
            border: 1px solid var(--ui-primary-hover) !important;
            border-radius: var(--ui-radius) !important;
            background: var(--ui-primary) !important;
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            box-shadow: none !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action:hover,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action:focus-visible {
            border-color: var(--ui-primary-hover) !important;
            background: var(--ui-primary-hover) !important;
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
        }

        @media (max-width: 1180px) {
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 640px) {
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-summary-grid {
                grid-template-columns: 1fr !important;
                padding: 12px !important;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-card {
                min-height: 86px !important;
                padding: 12px !important;
                gap: 11px !important;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon {
                width: 44px !important;
                height: 44px !important;
                flex-basis: 44px !important;
                border-radius: var(--ui-radius-lg) !important;
                font-size: 18px !important;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon i {
                font-size: 17px !important;
            }
        }

        /* Awards & Contracts remains the source styling for the shared KPI treatment. */
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-summary-grid {
            gap: 14px !important;
            padding: 0 !important;
            border: 0 !important;
            border-radius: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-card {
            min-height: 0 !important;
            align-items: flex-start !important;
            gap: 13px !important;
            padding: 18px !important;
            border: 1px solid var(--ui-line) !important;
            border-radius: var(--ui-radius-lg) !important;
            background: var(--ui-surface-2) !important;
            box-shadow: var(--ui-shadow) !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon {
            width: 36px !important;
            height: 36px !important;
            flex: 0 0 36px !important;
            border-radius: var(--ui-radius-lg) !important;
            font-size: 16px !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon i {
            font-size: 15px !important;
            line-height: 1 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon.is-dark {
            background: var(--ui-line-soft) !important;
            color: var(--ui-ink-2) !important;
            -webkit-text-fill-color: var(--ui-ink-2) !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon.is-green {
            background: #ecfdf5 !important;
            color: #047857 !important;
            -webkit-text-fill-color: #047857 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon.is-blue {
            background: var(--ui-primary-soft) !important;
            color: var(--ui-primary) !important;
            -webkit-text-fill-color: var(--ui-primary) !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-card > div {
            display: block !important;
            min-width: 0 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-label,
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-note {
            order: initial !important;
            margin-top: 0 !important;
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
            font-size: 12px !important;
            font-weight: 400 !important;
            line-height: 1.35 !important;
            letter-spacing: 0 !important;
            text-transform: none !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-value {
            order: initial !important;
            margin: 5px 0 3px !important;
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
            font-size: 23px !important;
            font-weight: 700 !important;
            letter-spacing: -.03em !important;
            line-height: 1 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action {
            width: auto !important;
            max-width: 100% !important;
            min-height: 34px !important;
            padding: 0 9px !important;
            border: 1px solid var(--ui-line-strong) !important;
            border-radius: var(--ui-radius-lg) !important;
            background: #ffffff !important;
            color: var(--ui-ink-2) !important;
            -webkit-text-fill-color: var(--ui-ink-2) !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            box-shadow: none !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action:hover,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action:focus-visible {
            border-color: #c4b5a5 !important;
            background: #fffaf6 !important;
            color: #c2410c !important;
            -webkit-text-fill-color: #c2410c !important;
        }

        @media (max-width: 1180px) {
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 640px) {
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-summary-grid {
                grid-template-columns: 1fr !important;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon {
                width: 36px !important;
                height: 36px !important;
                flex-basis: 36px !important;
                border-radius: var(--ui-radius-lg) !important;
                font-size: 16px !important;
            }

            body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon i {
                font-size: 15px !important;
            }
        }

        /* Keep the Awards search icon clear of the input text. */
        body .admin-dashboard.admin-role-page.admin-awards-page label.awards-search-field {
            position: relative !important;
            display: flex !important;
            align-items: center !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page label.awards-search-field > i {
            position: absolute !important;
            top: 50% !important;
            left: 16px !important;
            z-index: 2 !important;
            width: 16px !important;
            margin: 0 !important;
            color: var(--ui-subtle) !important;
            -webkit-text-fill-color: var(--ui-subtle) !important;
            font-size: 13px !important;
            line-height: 1 !important;
            pointer-events: none !important;
            transform: translateY(-50%) !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page label.awards-search-field > input {
            width: 100% !important;
            box-sizing: border-box !important;
            padding: 10px 13px 10px 48px !important;
            text-indent: 0 !important;
        }

        body .admin-dashboard.admin-role-page.admin-awards-page .award-scheduled { display: inline-flex; align-items: center; gap: 5px; width: fit-content; margin-top: 4px; padding: 2px 8px; border-radius: 999px; background: var(--ui-warning-soft); color: var(--ui-warning); font-size: 11.5px; font-weight: 700; }

        /* Row actions added by the award hand-off: Issue NOA and Cancel award. */
        body .admin-dashboard.admin-role-page.admin-awards-page td.award-action-cell { display: flex; flex-direction: column; align-items: stretch; gap: 6px; }
        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action.is-primary { border-color: var(--ui-primary) !important; background: var(--ui-primary) !important; color: #fff !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action.is-danger { border-color: var(--ui-danger-line) !important; background: #fff !important; color: var(--ui-danger) !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action.is-primary :is(span, i) { color: #fff !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action.is-danger :is(span, i) { color: var(--ui-danger) !important; }

        /* Cancel award dialog. Doubled IDs outrank the dashboard's ID-level field rules. */
        .award-cancel { width: min(560px, calc(100vw - 32px)); padding: 0; border: 0; border-radius: var(--ui-radius-lg); background: var(--ui-surface); color: var(--ui-ink); font-family: var(--ui-font); box-shadow: var(--ui-shadow-lg); }
        .award-cancel::backdrop { background: rgba(17, 24, 39, .55); }
        .award-cancel header { padding: 18px 20px 14px; border-bottom: 1px solid var(--ui-line); }
        .award-cancel h2 { margin: 3px 0 4px; font-size: 17px; font-weight: 700; line-height: 1.3; }
        .award-cancel header p { margin: 0; color: var(--ui-muted); font-size: var(--ui-text-sm); }
        .award-cancel .award-cancel-eyebrow { color: var(--ui-danger); font-size: var(--ui-text-xs); font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .award-cancel-body { display: grid; gap: 6px; padding: 16px 20px; }
        .award-cancel-note { margin: 0 0 8px; padding: 10px 12px; border-radius: var(--ui-radius); background: var(--ui-warning-soft); color: var(--ui-warning); font-size: var(--ui-text-sm); line-height: 1.5; }
        .award-cancel-error { margin-bottom: 6px; padding: 10px 12px; border: 1px solid var(--ui-danger-line); border-radius: var(--ui-radius); background: var(--ui-danger-soft); color: var(--ui-danger); font-size: var(--ui-text-sm); }
        .award-cancel-body label { margin-top: 6px; color: var(--ui-ink-2); font-size: var(--ui-text-sm); font-weight: 600; }
        .award-cancel-body label span { color: var(--ui-danger); }
        .award-cancel :is(input:not([type=hidden]), textarea, #award-x#award-x) { width: 100% !important; min-height: 38px !important; padding: 8px 11px !important; border: 1px solid var(--ui-line-strong) !important; border-radius: var(--ui-radius) !important; background: #fff !important; color: var(--ui-ink) !important; font: 400 var(--ui-text)/1.4 var(--ui-font) !important; box-sizing: border-box !important; box-shadow: none !important; }
        .award-cancel footer { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 20px; border-top: 1px solid var(--ui-line); }
        .award-cancel :is(.award-cancel-btn, #award-x#award-x) { display: inline-flex !important; align-items: center !important; gap: 7px !important; min-height: 36px !important; padding: 0 14px !important; border: 1px solid var(--ui-line-strong) !important; border-radius: var(--ui-radius) !important; background: #fff !important; color: var(--ui-ink-2) !important; font: 600 var(--ui-text-sm)/1 var(--ui-font) !important; cursor: pointer !important; }
        .award-cancel :is(.award-cancel-btn.is-danger, #award-x#award-x) { border-color: var(--ui-danger) !important; background: var(--ui-danger) !important; color: #fff !important; }
        /* Notice to Proceed record (receipt and PhilGEPS), entered by hand after issuance. */
        .award-ntp-record { margin-top: 8px; font-size: 12px; }
        .award-ntp-record summary { display: inline-flex; align-items: center; gap: 6px; color: var(--ui-primary); font-weight: 600; cursor: pointer; list-style: none; }
        .award-ntp-record summary::-webkit-details-marker { display: none; }
        .award-ntp-record summary i { font-size: 10px; }
        .award-ntp-record form { display: grid; gap: 8px; margin-top: 8px; padding: 10px; border: 1px solid var(--ui-line); border-radius: var(--ui-radius); background: #fff; }
        .award-ntp-record label { display: grid; gap: 4px; color: var(--ui-ink-2); font-size: 11.5px; font-weight: 600; }
        .award-ntp-record :is(input, #award-x#award-x) { width: 100% !important; min-height: 34px !important; padding: 6px 9px !important; border: 1px solid var(--ui-line-strong) !important; border-radius: var(--ui-radius) !important; background: #fff !important; color: var(--ui-ink) !important; font: inherit !important; font-size: 12.5px !important; }
        .award-ntp-record :is(.award-ntp-save, #award-x#award-x) { justify-self: start; display: inline-flex !important; align-items: center !important; min-height: 32px !important; padding: 0 14px !important; border: 1px solid var(--ui-primary) !important; border-radius: var(--ui-radius) !important; background: var(--ui-primary) !important; color: #fff !important; -webkit-text-fill-color: #fff !important; font: inherit !important; font-size: 12.5px !important; font-weight: 600 !important; cursor: pointer; }
        .award-ntp-record .award-amount-note a, .award-amount-note a { color: var(--ui-primary); font-weight: 600; }
        /* Contract stage cell: status, NTP date, receipt / PhilGEPS checklist, then implementation. */
        .award-stage-meta { display: block; margin-top: 6px; color: var(--ui-ink-2); font-size: 12px; }
        .award-stage-meta a { color: var(--ui-primary); font-weight: 600; text-decoration: none; }
        .award-stage-meta a:hover { text-decoration: underline; }
        .award-stage-meta a i { font-size: 9px; }
        .award-stage-checks { display: grid; gap: 5px; margin: 8px 0 0; padding: 0; list-style: none; }
        .award-stage-checks li { display: grid; grid-template-columns: 14px minmax(0, 1fr); gap: 7px; align-items: start; font-size: 12px; line-height: 1.35; }
        .award-stage-checks li i { margin-top: 2px; font-size: 12px; }
        :is(.award-stage-checks li.is-done i, #award-x#award-x) { color: var(--ui-success) !important; -webkit-text-fill-color: var(--ui-success) !important; }
        :is(.award-stage-checks li.is-todo i, #award-x#award-x) { color: #c9cfcb !important; -webkit-text-fill-color: #c9cfcb !important; font-size: 11px; }
        .award-stage-checks li span { color: var(--ui-ink); font-weight: 600; }
        .award-stage-checks li small { display: block; color: var(--ui-subtle); font-size: 11px; font-weight: 500; }
        .award-stage-checks li.is-todo small { color: var(--ui-warning) !important; }
        .awards-contracts-table td[data-label="Contract stage"] :is(.ci-open, #award-x#award-x) { display: flex !important; width: 100% !important; margin-top: 10px !important; }
        .awards-contracts-table td[data-label="Contract stage"] :is(.ci-open-status, #award-x#award-x) { white-space: normal !important; }

        /* Registry cards keep long NTP details readable without squeezing seven columns. */
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-summary-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            padding: 0 !important;
            gap: 12px !important;
        }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-card {
            min-height: 122px !important;
            padding: 18px 20px !important;
            background: #fff !important;
            border-top: 3px solid var(--ui-primary) !important;
        }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-card:nth-child(2) { border-top-color: #2c8b6b !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-card:nth-child(3) { border-top-color: #c99228 !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-card:nth-child(4) { border-top-color: #527ba9 !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-toolbar-card {
            margin-top: 22px !important;
            padding: 20px 24px 16px !important;
            background: #fff !important;
        }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-toolbar-controls { margin-top: 16px !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-filter-group { flex: 1 1 420px !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-search-field { max-width: 660px !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-filter-tabs {
            margin-top: 14px !important;
            gap: 6px !important;
        }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-table-card {
            margin-top: 14px !important;
            padding: 0 !important;
            overflow: visible !important;
            border: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
        }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-table-wrap { overflow: visible !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table,
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody {
            display: block !important;
            width: 100% !important;
        }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table thead { display: none !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row]:not([hidden]) {
            display: grid !important;
            grid-template-columns: minmax(0, 1.6fr) minmax(0, 1.2fr) minmax(0, .95fr) minmax(0, .85fr) !important;
            grid-template-areas: "project bidder amount actions" "date stage stage documents" !important;
            gap: 0 !important;
            margin-bottom: 14px !important;
            padding: 0 !important;
            overflow: hidden !important;
            border: 1px solid var(--ui-line) !important;
            border-radius: 14px !important;
            background: #fff !important;
            box-shadow: 0 5px 18px rgba(18, 52, 43, .045) !important;
        }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row]:hover {
            border-color: #bfd5ca !important;
            background: #fff !important;
        }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td {
            display: block !important;
            width: auto !important;
            min-width: 0 !important;
            padding: 18px 20px !important;
            border: 0 !important;
            text-align: left !important;
            white-space: normal !important;
            vertical-align: top !important;
        }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td[data-label]::before {
            display: block !important;
            margin-bottom: 9px !important;
            color: var(--ui-muted) !important;
            content: attr(data-label) !important;
            font-size: 10px !important;
            font-weight: 700 !important;
            letter-spacing: .055em !important;
            line-height: 1.2 !important;
            text-transform: uppercase !important;
        }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td:nth-child(-n+3),
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td:nth-child(7) {
            border-bottom: 1px solid var(--ui-line-soft) !important;
        }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td:nth-child(1) { grid-area: project !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td:nth-child(2) { grid-area: bidder !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td:nth-child(3) { grid-area: amount !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td:nth-child(4) { grid-area: date !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td:nth-child(5) {
            grid-area: stage !important;
            background: #f8fbf9 !important;
            border-left: 1px solid var(--ui-line-soft) !important;
            border-right: 1px solid var(--ui-line-soft) !important;
        }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td:nth-child(6) { grid-area: documents !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td:nth-child(7) {
            grid-area: actions !important;
            align-self: stretch !important;
            justify-content: flex-start !important;
            align-items: stretch !important;
        }
        body .admin-dashboard.admin-role-page.admin-awards-page .award-project-cell,
        body .admin-dashboard.admin-role-page.admin-awards-page .award-party-cell { align-items: flex-start !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .award-project-cell strong {
            display: block !important;
            overflow: visible !important;
            font-size: 15px !important;
            font-weight: 700 !important;
            line-height: 1.4 !important;
        }
        body .admin-dashboard.admin-role-page.admin-awards-page .award-party-cell strong { font-weight: 650 !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .award-amount {
            font-size: 19px !important;
            font-weight: 750 !important;
            letter-spacing: -.025em !important;
        }
        body .admin-dashboard.admin-role-page.admin-awards-page .award-stage-checks {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 8px 16px !important;
            max-width: 550px !important;
            margin: 12px 0 !important;
        }
        body .admin-dashboard.admin-role-page.admin-awards-page .award-ntp-record { margin-top: 12px !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .award-ntp-record summary {
            width: fit-content !important;
            padding: 7px 10px !important;
            border: 1px solid var(--ui-primary-line) !important;
            border-radius: 7px !important;
            background: #fff !important;
        }
        body .admin-dashboard.admin-role-page.admin-awards-page .award-ntp-record form {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            max-width: 580px !important;
            padding: 14px !important;
        }
        body .admin-dashboard.admin-role-page.admin-awards-page .award-ntp-record form label:nth-of-type(3) { grid-column: 1 / -1 !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .award-row-action { width: 100% !important; min-height: 38px !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr:not([data-award-row]):not([hidden]) { display: block !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr:not([data-award-row]):not([hidden]) > td { display: block !important; }
        @media (max-width: 1180px) {
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row]:not([hidden]) {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                grid-template-areas: "project actions" "bidder amount" "date documents" "stage stage" !important;
            }
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td:nth-child(5) {
                border-top: 1px solid var(--ui-line-soft) !important;
                border-left: 0 !important;
                border-right: 0 !important;
            }
        }
        @media (max-width: 640px) {
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row]:not([hidden]) {
                grid-template-columns: 1fr !important;
                grid-template-areas: "project" "bidder" "amount" "date" "stage" "documents" "actions" !important;
            }
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-contracts-table tbody tr[data-award-row] > td {
                padding: 15px 17px !important;
                border-bottom: 1px solid var(--ui-line-soft) !important;
            }
            body .admin-dashboard.admin-role-page.admin-awards-page .award-stage-checks { grid-template-columns: 1fr !important; }
            body .admin-dashboard.admin-role-page.admin-awards-page .award-ntp-record form { grid-template-columns: 1fr !important; }
            body .admin-dashboard.admin-role-page.admin-awards-page .award-ntp-record form label:nth-of-type(3) { grid-column: auto !important; }
        }
        @media (max-width: 430px) {
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-summary-grid { grid-template-columns: 1fr !important; }
        }
        .infra-tracking-modal {
            position: fixed !important;
            inset: auto !important;
            top: 50% !important;
            left: 50% !important;
            width: min(1380px, calc(100vw - 32px)) !important;
            height: min(920px, calc(100dvh - 32px)) !important;
            max-width: calc(100vw - 32px) !important;
            max-height: calc(100dvh - 32px) !important;
            margin: 0 !important;
            padding: 0;
            overflow: hidden;
            border: 1px solid #d8e3dc;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 26px 70px rgba(15, 38, 31, .28);
            transform: translate(-50%, -50%) !important;
        }
        .infra-tracking-modal::backdrop { background: rgba(9, 28, 22, .62); backdrop-filter: blur(3px); }
        .infra-tracking-modal__shell { display: grid; grid-template-rows: auto minmax(0, 1fr); height: 100%; }
        .infra-tracking-modal__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 20px;
            border-bottom: 1px solid #e0e8e3;
            background: #fff;
        }
        .infra-tracking-modal__head span { display: block; color: #5f766b; font-size: 11px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; }
        .infra-tracking-modal__head h2 { margin: 3px 0 0; color: #17372b; font-size: 18px; line-height: 1.3; }
        .infra-tracking-modal__close {
            display: grid;
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            place-items: center;
            border: 1px solid #d8e3dc;
            border-radius: 9px;
            background: #fff;
            color: #263a31;
            cursor: pointer;
        }
        .infra-tracking-modal__close:hover { background: #eef5f0; }
        .infra-tracking-modal iframe { display: block; width: 100%; height: 100%; border: 0; background: #fff; }
        @media (max-width: 640px) {
            .infra-tracking-modal { width: 100vw !important; height: 100dvh !important; max-width: 100vw !important; max-height: 100dvh !important; border: 0; border-radius: 0; }
        }
    </style>

    <style>
        /* Redesign: one summary strip, one compact registry header. */
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-summary-grid { display: grid !important; grid-template-columns: repeat(4, minmax(0, 1fr)) !important; gap: 0 !important; margin: 0 0 16px !important; padding: 0 !important; overflow: hidden !important; border: 1px solid var(--ui-line) !important; border-radius: 14px !important; background: #fff !important; box-shadow: none !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-card { display: flex !important; align-items: center !important; gap: 12px !important; min-height: 0 !important; padding: 14px 20px !important; border: 0 !important; border-right: 1px solid var(--ui-line) !important; border-radius: 0 !important; background: transparent !important; box-shadow: none !important; transform: none !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-card::before, body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-card::after { display: none !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-card:last-child { border-right: 0 !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon { flex: 0 0 34px !important; width: 34px !important; height: 34px !important; border-radius: 9px !important; font-size: 13px !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-value { font-size: 18px !important; line-height: 1.2 !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-note { font-size: 11.5px !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-toolbar-description { display: none !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-toolbar-card { display: flex !important; flex-wrap: wrap !important; align-items: center !important; gap: 12px 16px !important; padding: 14px 20px !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-toolbar-top { flex: 0 0 auto !important; width: auto !important; margin: 0 !important; padding: 0 !important; background: transparent !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-toolbar-controls { flex: 1 1 260px !important; display: flex !important; align-items: center !important; gap: 10px !important; margin: 0 !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-filter-group { flex: 1 1 auto !important; }
        body .admin-dashboard.admin-role-page.admin-awards-page .awards-filter-tabs { flex: 0 0 auto !important; margin: 0 !important; }
        @media (max-width: 560px) {
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-card { gap: 10px !important; padding: 12px 14px !important; }
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-icon { display: none !important; }
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-value { font-size: 16px !important; overflow-wrap: anywhere !important; }
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-toolbar-controls { flex: 1 1 100% !important; }
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-filter-group, body .admin-dashboard.admin-role-page.admin-awards-page .awards-search-field { width: 100% !important; max-width: none !important; }
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-filter-tabs { flex: 1 1 100% !important; overflow-x: auto !important; scrollbar-width: none !important; }
        }
        @media (max-width: 900px) {
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-card:nth-child(2) { border-right: 0 !important; }
            body .admin-dashboard.admin-role-page.admin-awards-page .awards-stat-card:nth-child(-n+2) { border-bottom: 1px solid var(--ui-line) !important; }
        }
    </style>

    @include('partials.admin-sidebar')

    <div class="main-area">
        <x-page-header title="Awards & contracts" subtitle="View all awarded projects and contracts">
            <x-slot:actions>
                <a href="{{ route('admin.awards.report') }}" class="xd-hbtn" data-report-dialog="awardsReport"><i class="fas fa-chart-column" aria-hidden="true"></i> Report</a>
                <a href="{{ route('admin.awards.export') }}" class="xd-hbtn" data-export-dialog data-export-title="Export awards" data-export-noun="award" data-export-count="{{ count($awards ?? []) }}" data-export-note="Every award with its contract amount, stage and certificate."><i class="fas fa-file-export" aria-hidden="true"></i> Export</a>
            </x-slot:actions>
        </x-page-header>
        @include('partials.export-dialog')
        @include('partials.report-dialog', ['id' => 'awardsReport', 'title' => 'Awards and contracts report', 'url' => route('admin.awards.report', ['embed' => 1])])

        <main class="dashboard-content admin-awards-content">
            @if(session('success'))
            <div id="successAlert" style="position: fixed; top: 90px; right: 25px; background: #dcfce7; color: #166534; padding: 16px 20px; border-radius: 8px; font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; display: flex; align-items: center; gap: 10px; min-width: 280px;">
                <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                <span>{{ session('success') }}</span>
                <button onclick="closeSuccessAlert()" style="margin-left: auto; background: none; border: none; color: #166534; cursor: pointer; font-size: 16px;">&times;</button>
            </div>
            @endif

            @if(session('error'))
            <div id="errorAlert" style="position: fixed; top: 90px; right: 25px; background: #fee2e2; color: #991b1b; padding: 16px 20px; border-radius: 8px; font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; display: flex; align-items: center; gap: 10px; min-width: 280px;">
                <i class="fas fa-exclamation-circle" style="font-size: 18px;"></i>
                <span>{{ session('error') }}</span>
                <button onclick="closeErrorAlert()" style="margin-left: auto; background: none; border: none; color: #991b1b; cursor: pointer; font-size: 16px;">&times;</button>
            </div>
            @endif

            @if($errors->any())
            <div id="validationAlert" style="margin: 16px 0; background: #fef2f2; color: #991b1b; padding: 14px 16px; border-radius: 8px; font-size: 13px; border: 1px solid #fecaca;">
                <strong style="display: block; margin-bottom: 6px;">Please fix the following:</strong>
                <ul style="margin: 0; padding-left: 18px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            @php
                $awardCollection = collect($awards ?? []);
                $readyProjectCollection = collect($readyProjects ?? []);
                $validAwardCount = $awardCollection->filter(function ($award) {
                    return ($award->certificate_status ?: $award->status) === \App\Models\Award::STATUS_VALID;
                })->count();
                $awardsInForce = $awardCollection->reject(fn ($award) => $award->isCancelled());
                $contractValue = $awardsInForce->sum(function ($award) {
                    return (float) $award->contract_amount;
                });
                $awaitingNoticeCount = $awardsInForce->filter(fn ($award) => $award->awaitsNoticeOfAward())->count();
                $issuedNtpCount = $awardsInForce->filter(fn ($award) => $award->hasPublishedNoticeToProceed())->count();
            @endphp

            <section class="awards-summary-grid" aria-label="Awards summary">
                <article class="awards-stat-card">
                    <span class="awards-stat-icon is-dark" aria-hidden="true"><i class="fas fa-award"></i></span>
                    <div>
                        <span class="awards-stat-label">Total Awards</span>
                        <strong class="awards-stat-value">{{ $awardsInForce->count() }}</strong>
                        <span class="awards-stat-note">{{ $awaitingNoticeCount ? $awaitingNoticeCount.' awaiting Notice of Award' : 'Recorded contracts' }}</span>
                    </div>
                </article>
                <article class="awards-stat-card">
                    <span class="awards-stat-icon is-green" aria-hidden="true"><i class="fas fa-shield-halved"></i></span>
                    <div>
                        <span class="awards-stat-label">Valid Certificates</span>
                        <strong class="awards-stat-value">{{ $validAwardCount }}</strong>
                        <span class="awards-stat-note">Ready to verify</span>
                    </div>
                </article>
                <article class="awards-stat-card">
                    <span class="awards-stat-icon is-green" aria-hidden="true"><i class="fas fa-file-circle-check"></i></span>
                    <div>
                        <span class="awards-stat-label">Notices to Proceed</span>
                        <strong class="awards-stat-value">{{ $issuedNtpCount }}</strong>
                        <span class="awards-stat-note">Issued and published</span>
                    </div>
                </article>
                <article class="awards-stat-card">
                    <span class="awards-stat-icon is-blue" aria-hidden="true"><i class="fas fa-coins"></i></span>
                    <div>
                        <span class="awards-stat-label">Total Contract Value</span>
                        <strong class="awards-stat-value">&#8369;{{ number_format($contractValue, 2) }}</strong>
                        <span class="awards-stat-note">Across all awards</span>
                    </div>
                </article>
            </section>

            {{-- Shown only when a HoPE-approved bid is waiting for its Notice of Award. --}}
            @if($readyProjectCollection->isNotEmpty())
            <section class="ready-awards-card" aria-labelledby="ready-awards-title">
                <div class="ready-awards-header">
                    <div>
                        <span class="awards-eyebrow">Next action</span>
                        <h2 id="ready-awards-title" class="ready-awards-title">Awaiting Notice of Award</h2>
                        <p class="ready-awards-description">Bids the BAC recommended and the Head of the Procuring Entity approved. Issue the signed Notice of Award to create the award record, then record contract signing and the Notice to Proceed on the bid.</p>
                    </div>
                    <span class="ready-awards-count">{{ $readyProjectCollection->count() }} {{ $readyProjectCollection->count() === 1 ? 'project' : 'projects' }}</span>
                </div>
                <div class="ready-awards-list">
                    @foreach($readyProjectCollection as $project)
                        @php
                            // bids are pre-filtered to the BAC-recommended bid awaiting decision
                            $recommendedBid = $project->bids->first();
                            $evaluatedCount = (int) ($project->evaluated_bids_count ?? 0);
                            $readyAbc = (float) ($project->budget ?? 0);
                        @endphp
                        <div class="ready-award-row">
                            <div class="ready-award-project">
                                <strong title="{{ $project->title }}">{{ $project->title }}</strong>
                                <span>Project #{{ $project->id }} &middot; Procurement award pending</span>
                            </div>
                            <div class="ready-award-metric">
                                <strong>&#8369;{{ number_format($readyAbc, 2) }}</strong>
                                <span>ABC</span>
                            </div>
                            <div class="ready-award-metric">
                                <strong>{{ $evaluatedCount }}</strong>
                                <span>Evaluated bids</span>
                            </div>
                            <div class="ready-award-metric is-highlight">
                                <strong>
                                    @if($recommendedBid)
                                        &#8369;{{ number_format((float) $recommendedBid->bid_amount, 2) }}
                                    @else
                                        &mdash;
                                    @endif
                                </strong>
                                <span>Recommended bid{{ $recommendedBid ? ' · ' . ($recommendedBid->user?->company ?: $recommendedBid->user?->name) : '' }}</span>
                            </div>
                            <div>
                                @if($recommendedBid)
                                    <button type="button" class="ready-award-button" onclick="loadDeclareWinnerModal({{ $project->id }}, {{ $recommendedBid->id }})">
                                        <i class="fas fa-award" aria-hidden="true"></i>
                                        <span>Issue Notice of Award</span>
                                    </button>
                                @else
                                    <span class="ready-awards-empty">No BAC recommendation</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
            @endif

            <section class="awards-toolbar-card" aria-labelledby="awards-registry-title">
                <div class="awards-toolbar-top">
                    <div>
                        <h2 id="awards-registry-title" class="awards-toolbar-title">Awarded contracts</h2>
                        <p class="awards-toolbar-description">Track the award, contract, and Notice to Proceed for each project.</p>
                    </div>
                    <span id="awardsRecordCount" class="awards-record-count">{{ $awardCollection->count() }} records</span>
                </div>
                <div class="awards-toolbar-controls">
                    <div class="awards-filter-group">
                        <label class="awards-search-field admin-search-field">
                            <svg class="admin-search-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                            <span class="sr-only">Search awards</span>
                            <input id="awardSearch" type="search" class="awards-search-input" placeholder="Search project, bidder, or certificate..." autocomplete="off">
                        </label>
                    </div>
                    <button type="button" id="clearAwardFilters" class="awards-clear-button">Clear</button>
                </div>
                <div class="awards-filter-tabs" role="tablist" aria-label="Award status filters">
                    <button type="button" class="awards-filter-tab is-active" data-award-filter="all" role="tab" aria-selected="true">All awards</button>
                    <button type="button" class="awards-filter-tab" data-award-filter="valid" role="tab" aria-selected="false">Valid</button>
                    <button type="button" class="awards-filter-tab" data-award-filter="revoked" role="tab" aria-selected="false">Revoked</button>
                    <button type="button" class="awards-filter-tab" data-award-filter="expired" role="tab" aria-selected="false">Expired</button>
                </div>
            </section>

            <section class="awards-table-card" aria-label="Awarded contracts registry">
                <div class="awards-table-wrap">
                <table class="awards-contracts-table">
                    <caption class="sr-only">Awards and contracts registry</caption>
                    <thead>
                        <tr>
                            <th scope="col">Project</th>
                            <th scope="col">Winning bidder</th>
                            <th scope="col">Contract amount</th>
                            <th scope="col">Award (NOA) date</th>
                            <th scope="col">Contract stage</th>
                            <th scope="col">QR / award documents</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($awardCollection as $award)
                            @php
                                $projectTitle = $award->project?->title ?? 'Untitled project';
                                $bidderName = $award->bidder?->company ?: ($award->bidder?->name ?? $award->bid?->user?->name ?? 'N/A');
                                $awardStatus = strtolower((string) ($award->certificate_status ?: $award->status ?: 'pending'));
                                $contractAmount = (float) ($award->contract_amount ?? 0);
                                $showCertificate = $award->hasCertificateFile() && filled($award->qr_token);
                                $statusClass = in_array($awardStatus, ['valid', 'revoked', 'expired'], true) ? $awardStatus : 'pending';
                                // Contract stage from the bid's recorded milestones (same source as the bidder track).
                                $contractStage = $award->bid ? $award->bid->progress()->adminStatus() : null;
                                [$contractStageLabel, $contractStageNext] = $award->isCancelled()
                                    ? ['Award cancelled', $award->cancellation_reference]
                                    : ($award->bid?->project_completed_at
                                    ? ['Completed', 'Completed '.$award->bid->project_completed_at->timezone(config('bac-office.display_timezone'))->format('M d, Y')]
                                    : match ($contractStage['key'] ?? null) {
                                        'notice_to_proceed' => ['NTP issued', null],
                                        'contract_signed' => ['Contract signed', 'Notice to Proceed pending'],
                                        'notice_of_award' => ['NOA issued', 'Contract signing pending'],
                                        'award_approval' => ['Award approved', 'Notice of Award pending'],
                                        default => [$contractStage['label'] ?? 'Awarded', null],
                                    });
                                $awaitingNotice = $award->awaitsNoticeOfAward();
                                // Cancellable by the HoPE until the contract is signed.
                                $cancellable = ! $award->isCancelled() && $award->contract_date === null && $award->bid?->contract_signed_at === null;
                                $criterion = $award->project?->award_criterion ? (\App\Models\Project::AWARD_CRITERIA[$award->project->award_criterion] ?? null) : null;
                            @endphp
                            <tr data-award-row data-status="{{ $awardStatus }}" data-search="{{ strtolower($projectTitle . ' ' . $bidderName . ' ' . ($award->certificate_number ?? '')) }}">
                                <td data-label="Project">
                                    <div class="award-project-cell">
                                        <span class="award-project-icon" aria-hidden="true"><i class="fas fa-folder-open"></i></span>
                                        <div>
                                            <strong title="{{ $projectTitle }}">{{ $projectTitle }}</strong>
                                            <span>{{ $award->project?->reference_no ?: 'Project #'.$award->project_id }}</span>
                                            @if($criterion)<span title="Award criterion">{{ $criterion }}</span>@endif
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Winning bidder">
                                    <div class="award-party-cell">
                                        <span class="award-project-icon" aria-hidden="true"><i class="fas fa-building"></i></span>
                                        <div>
                                            <strong title="{{ $bidderName }}">{{ $bidderName }}</strong>
                                            <span>Winning bidder</span>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Contract amount">
                                    <span class="award-amount">&#8369;{{ number_format($contractAmount, 2) }}</span>
                                    <span class="award-amount-note">Approved bid amount</span>
                                </td>
                                <td data-label="Award (NOA) date">
                                    @if($awaitingNotice)
                                        <span class="award-date">NOA not issued</span>
                                    @else
                                        <span class="award-date">{{ $award->awardDate()?->format('M d, Y') ?? '—' }}</span>
                                        <span class="award-amount-note">Contract: {{ $award->notice_of_award_date && $award->contract_date ? $award->contract_date->format('M d, Y') : ($award->notice_of_award_date ? 'not yet signed' : ($award->contract_date?->format('M d, Y') ?? '—')) }}</span>
                                    @endif
                                    @if($award->award_approved_at)
                                        <span class="award-amount-note">Approved {{ $award->award_approved_at->timezone(config('bac-office.display_timezone'))->format('M d, Y') }}@if($award->approver) by {{ $award->approver->name }}@endif</span>
                                    @endif
                                    @if($award->isScheduledForPublication())
                                        <span class="award-scheduled" title="Hidden from the public until then (server time, Asia/Manila)"><i class="fas fa-clock" aria-hidden="true"></i> Scheduled: public on {{ $award->publicationTime()->format('M d, Y g:i A') }}</span>
                                    @endif
                                </td>
                                <td data-label="Contract stage">
                                    <span class="award-status is-{{ $awardStatus === 'valid' ? 'valid' : $statusClass }}">{{ $contractStageLabel }}</span>
                                    @if($contractStageNext && $award->bid_id && ! $award->isCancelled() && auth()->user()?->role === 'admin'
                                        && in_array($contractStage['key'] ?? null, ['contract_signed', 'notice_of_award', 'award_approval'], true))
                                        {{-- The pending step is recorded in the bid's review modal. --}}
                                        <span class="award-amount-note"><a href="{{ route('admin.bid.view', $award->bid_id) }}">{{ $contractStageNext }} <i class="fas fa-arrow-right" aria-hidden="true"></i></a></span>
                                    @elseif($contractStageNext)
                                        <span class="award-amount-note">{{ $contractStageNext }}</span>
                                    @endif
                                    @if($awardStatus !== 'valid' && ! $awaitingNotice && ! $award->isCancelled())
                                        <span class="award-amount-note">Document {{ $awardStatus }}</span>
                                    @endif
                                    @if($award->hasPublishedNoticeToProceed())
                                        {{-- Issued NTP: its PDF, and the receipt / PhilGEPS details entered by hand (BidWorkflow::updateNoticeToProceedRecord). --}}
                                        @php $ntpTz = config('bac-office.display_timezone', 'Asia/Manila'); @endphp
                                        <span class="award-stage-meta">
                                            {{ $award->ntp_issued_on->format('M d, Y') }} ·
                                            <a href="{{ $award->noticeToProceedUrl() }}" target="_blank" rel="noopener">View NTP <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                                        </span>
                                        <ul class="award-stage-checks">
                                            @foreach([['Received by bidder', $award->ntp_received_on], ['Posted on PhilGEPS', $award->ntp_philgeps_posted_on]] as [$checkLabel, $checkDate])
                                                <li class="{{ $checkDate ? 'is-done' : 'is-todo' }}">
                                                    <i class="fas {{ $checkDate ? 'fa-circle-check' : 'fa-circle' }}" aria-hidden="true"></i>
                                                    <span>{{ $checkLabel }}<small>{{ $checkDate ? $checkDate->format('M d, Y') : 'Not recorded' }}</small></span>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <details class="award-ntp-record">
                                            <summary><i class="fas fa-pen" aria-hidden="true"></i> {{ $award->ntp_received_on && $award->ntp_philgeps_posted_on ? 'Edit' : 'Record' }} receipt &amp; PhilGEPS</summary>
                                            <form action="{{ route('admin.awards.ntp.update', $award) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <label>Received by the bidder on
                                                    <input type="date" name="ntp_received_on" value="{{ $award->ntp_received_on?->toDateString() }}" min="{{ $award->ntp_issued_on->toDateString() }}" max="{{ now($ntpTz)->toDateString() }}">
                                                </label>
                                                <label>Posted on PhilGEPS on
                                                    <input type="date" name="ntp_philgeps_posted_on" value="{{ $award->ntp_philgeps_posted_on?->toDateString() }}" min="{{ $award->ntp_issued_on->toDateString() }}" max="{{ now($ntpTz)->toDateString() }}">
                                                </label>
                                                <label>PhilGEPS reference or link
                                                    <input type="text" name="ntp_philgeps_reference" maxlength="500" value="{{ $award->ntp_philgeps_reference }}">
                                                </label>
                                                <button type="submit" class="award-ntp-save">Save</button>
                                            </form>
                                        </details>
                                    @endif
                                    @include('partials.contract-implementation-button', ['award' => $award])
                                </td>
                                <td data-label="QR / award documents">
                                    @if($showCertificate)
                                            <div class="award-certificate-chip">
                                                <a class="award-certificate-icon" href="{{ $award->tokenQrUrl() }}" target="_blank" rel="noopener" aria-label="Open award certificate QR">
                                                    <img src="{{ $award->tokenQrUrl() }}" alt="Award certificate QR code">
                                                </a>
                                            <div>
                                                <strong>QR &amp; certificate</strong>
                                                <small>{{ $award->certificate_number ?? 'QR verified' }}</small>
                                            </div>
                                        </div>
                                    @else
                                        <span class="award-certificate-muted"><i class="fas fa-file-circle-xmark" aria-hidden="true"></i> No QR/document</span>
                                    @endif
                                </td>
                                <td class="award-action-cell">
                                    <button type="button" class="award-row-action" onclick="loadAwardViewModal({{ $award->id }})">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                        <span>View details</span>
                                    </button>
                                    @if($awaitingNotice && $award->bid)
                                        <button type="button" class="award-row-action is-primary" onclick="loadDeclareWinnerModal({{ $award->project_id }}, {{ $award->bid_id }})">
                                            <i class="fas fa-file-signature" aria-hidden="true"></i>
                                            <span>Issue NOA</span>
                                        </button>
                                    @endif
                                    @if($cancellable)
                                        <button type="button" class="award-row-action is-danger" data-cancel-award="{{ $award->id }}">
                                            <i class="fas fa-ban" aria-hidden="true"></i>
                                            <span>Cancel award</span>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="awards-empty">
                                    <i class="fas fa-trophy" aria-hidden="true"></i>
                                    <strong>No awards yet</strong>
                                    <span>Awards appear here once a Notice of Award is issued to a HoPE-approved bid.</span>
                                </td>
                            </tr>
                        @endforelse
                        <tr id="awardsFilteredEmpty" hidden>
                            <td colspan="7" class="awards-empty">
                                <i class="fas fa-filter" aria-hidden="true"></i>
                                <strong>No matching awards</strong>
                                <span>Try another search term or status filter.</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </section>

        {{-- Cancelling an award: HoPE only, before contract signing, with its authority on record. --}}
        @php $cancelErrorFor = (string) old('cancel_award_id'); @endphp
        @foreach($awardCollection->filter(fn ($award) => ! $award->isCancelled() && $award->contract_date === null && $award->bid?->contract_signed_at === null) as $award)
            <dialog class="award-cancel" id="award-cancel-{{ $award->id }}" aria-labelledby="award-cancel-{{ $award->id }}-title" @if($cancelErrorFor === (string) $award->id) data-reopen @endif>
                <form method="POST" action="{{ route('admin.awards.cancel', $award) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="cancel_award_id" value="{{ $award->id }}">
                    <header>
                        <p class="award-cancel-eyebrow">Cancel award</p>
                        <h2 id="award-cancel-{{ $award->id }}-title">{{ $award->project?->title }}</h2>
                        <p>Awarded to <strong>{{ $award->bidder?->company ?: ($award->bid?->user?->company ?: $award->bid?->user?->name) }}</strong> for &#8369;{{ number_format((float) $award->contract_amount, 2) }}.</p>
                    </header>
                    <div class="award-cancel-body">
                        <p class="award-cancel-note">Use this when the award cannot proceed before contract signing, for example when the winning bidder does not post the performance security or sign the contract. The award is kept on record as cancelled, the winning bidder is told the reason, and bids closed as "Not Awarded" by this award reopen for the BAC's next decision.</p>
                        @if($cancelErrorFor === (string) $award->id && $errors->any())
                            <div class="award-cancel-error" role="alert">{{ $errors->first('cancel') ?: $errors->first() }}</div>
                        @endif
                        <label for="award-cancel-{{ $award->id }}-ref">HoPE memorandum or BAC resolution <span aria-hidden="true">*</span></label>
                        <input id="award-cancel-{{ $award->id }}-ref" name="cancellation_reference" maxlength="255" required value="{{ $cancelErrorFor === (string) $award->id ? old('cancellation_reference') : '' }}" placeholder="e.g. Memorandum No. 2026-120">
                        <label for="award-cancel-{{ $award->id }}-reason">Reason, shown to the winning bidder <span aria-hidden="true">*</span></label>
                        <textarea id="award-cancel-{{ $award->id }}-reason" name="cancellation_reason" rows="3" maxlength="2000" required>{{ $cancelErrorFor === (string) $award->id ? old('cancellation_reason') : '' }}</textarea>
                        <label for="award-cancel-{{ $award->id }}-doc">Supporting document (optional)</label>
                        <input id="award-cancel-{{ $award->id }}-doc" type="file" name="supporting_document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    </div>
                    <footer>
                        <button type="button" class="award-cancel-btn" data-close-cancel>Keep the award</button>
                        <button type="submit" class="award-cancel-btn is-danger"><i class="fas fa-ban" aria-hidden="true"></i> Cancel award</button>
                    </footer>
                </form>
            </dialog>
        @endforeach

        @include('partials.contract-implementation-dialogs', ['awards' => $awards, 'viewerMode' => 'admin'])

        </main>
    </div>
</div>

<dialog id="infrastructureTrackingModal" class="infra-tracking-modal" aria-labelledby="infrastructureTrackingTitle">
    <div class="infra-tracking-modal__shell">
        <header class="infra-tracking-modal__head">
            <div><span>Awards &amp; contracts</span><h2 id="infrastructureTrackingTitle">Infrastructure tracking</h2></div>
            <button type="button" class="infra-tracking-modal__close" data-infra-modal-close aria-label="Close infrastructure tracking"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </header>
        <iframe title="Infrastructure contract tracking" data-infra-modal-frame></iframe>
    </div>
</dialog>

<div id="awardViewModal" style="display: none; position: fixed; inset: 0; padding: 20px; background: rgba(27, 36, 32, 0.45); z-index: 10000; justify-content: center; align-items: center; box-sizing: border-box;">
    <div style="background: white; border-radius: 14px; width: min(720px, 100%); overflow: hidden; position: relative; box-shadow: 0 20px 44px rgba(27, 36, 32, 0.16); box-sizing: border-box;">
        <button onclick="closeAwardViewModal()" style="position: absolute; top: 16px; right: 16px; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; background: #f1eee6; border: none; border-radius: 9px; font-size: 18px; line-height: 1; cursor: pointer; color: #7c8ba1; z-index: 3;">&times;</button>
        <div id="awardViewModalBody"></div>
    </div>
</div>

<div id="declareWinnerModal" style="display: none; position: fixed; inset: 0; padding: 20px; background: rgba(27, 36, 32, 0.45); z-index: 10001; justify-content: center; align-items: center; box-sizing: border-box;">
    <div style="background: white; border-radius: 18px; width: min(690px, 100%); max-height: calc(100vh - 20px); overflow-y: auto; overflow-x: hidden; position: relative; box-shadow: 0 24px 48px rgba(27, 36, 32, 0.16); box-sizing: border-box;">
        <button onclick="closeDeclareWinnerModal()" style="position: absolute; top: 16px; right: 16px; width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; background: #f1eee6; border: none; border-radius: 10px; font-size: 20px; line-height: 1; cursor: pointer; color: #7c8ba1; z-index: 2;">&times;</button>
        <div id="declareWinnerModalBody"></div>
    </div>
</div>

<script>
    function loadAwardViewModal(id) {
        document.getElementById('awardViewModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
        document.getElementById('awardViewModalBody').innerHTML = '<div style="padding: 28px; color: #6b736e; font-size: 14px;">Loading award details...</div>';

        fetch(`/admin/awards/${id}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.text())
            .then(html => {
                document.getElementById('awardViewModalBody').innerHTML = html;
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('awardViewModalBody').innerHTML = '<div style="padding: 28px; color: #b91c1c; font-size: 14px;">Error loading award details.</div>';
            });
    }

    function closeAwardViewModal() {
        document.getElementById('awardViewModal').style.display = 'none';
        document.body.style.overflow = '';
    }

    // Cancel award dialogs: open from the row, reopen after a refused cancellation.
    document.addEventListener('click', function (event) {
        const opener = event.target.closest('[data-cancel-award]');
        if (opener) {
            document.getElementById('award-cancel-' + opener.dataset.cancelAward)?.showModal();
            return;
        }
        if (event.target.closest('[data-close-cancel]')) {
            event.target.closest('dialog')?.close();
        }
    });
    document.querySelector('.award-cancel[data-reopen]')?.showModal();

    function loadDeclareWinnerModal(projectId, bidId) {
        document.getElementById('declareWinnerModal').style.display = 'flex';
        document.getElementById('declareWinnerModalBody').innerHTML = '<div style="padding: 30px; color: #6b736e; font-size: 14px;">Loading award form...</div>';

        fetch(`/admin/projects/${projectId}/award?bid=${bidId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.text())
            .then(html => {
                document.getElementById('declareWinnerModalBody').innerHTML = html;
                // Re-initialize form validation after modal content loads
                if (typeof validateDeclareWinnerForm === 'function') {
                    validateDeclareWinnerForm();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('declareWinnerModalBody').innerHTML = '<div style="padding: 30px; color: #b91c1c; font-size: 14px;">Error loading award form.</div>';
            });
    }

    function closeDeclareWinnerModal() {
        document.getElementById('declareWinnerModal').style.display = 'none';
    }

    function selectDeclareWinnerOption(option) {
        document.querySelectorAll('#declareWinnerModal [data-bid-option]').forEach(function(item) {
            item.classList.remove('is-selected');
            const radio = item.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = false;
            }
        });

        option.classList.add('is-selected');

        const radio = option.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
        }

        const amountField = document.getElementById('awardContractAmount');
        if (amountField) {
            amountField.value = option.dataset.bidAmount || '';
        }

        // Update form validation state after bid selection
        if (typeof validateDeclareWinnerForm === 'function') {
            validateDeclareWinnerForm();
        }
    }

    function closeSuccessAlert() {
        const alert = document.getElementById('successAlert');
        if (alert) alert.style.display = 'none';
    }

    function closeErrorAlert() {
        const alert = document.getElementById('errorAlert');
        if (alert) alert.style.display = 'none';
    }

    function validateDeclareWinnerForm() {
        const modalBody = document.getElementById('declareWinnerModalBody');
        if (!modalBody) return;

        const fileInput = modalBody.querySelector('#certificateFile');
        const submitBtn = modalBody.querySelector('#declareWinnerSubmitBtn');
        const fileName = modalBody.querySelector('#certificateFileName');
        const fileError = modalBody.querySelector('[data-error-for="certificate_file"]');
        const formAlert = modalBody.querySelector('#awardFormAlert');

        if (!fileInput || !submitBtn) return;

        const file = fileInput.files[0] || null;
        const bidSelected = modalBody.querySelector('input[name="bid_id"]:checked') !== null;
        let fileIsValid = false;
        let error = '';

        if (fileError) fileError.textContent = '';
        if (fileName) fileName.textContent = '';
        if (formAlert) {
            formAlert.style.display = 'none';
            formAlert.textContent = '';
        }

        if (file) {
            const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
            if (!isPdf) {
                error = 'Only PDF files are allowed.';
            } else if (file.size > 5 * 1024 * 1024) {
                error = 'File size must not exceed 5MB.';
            } else {
                fileIsValid = true;
                if (fileName) {
                    fileName.textContent = 'Selected: ' + file.name;
                }
            }
        }

        if (error && fileError) {
            fileError.textContent = error;
        }

        submitBtn.disabled = !(fileIsValid && bidSelected);
    }

    function sendCertificateAction(awardId, actionType, successMsg, errorMsg) {
        let url;
        switch(actionType) {
            case 'revoke':
                url = '/admin/awards/' + awardId + '/revoke';
                break;
            case 'regenerate':
                url = '/admin/awards/' + awardId + '/regenerate-token';
                break;
            default:
                return;
        }

        fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(successMsg);
                location.reload();
            } else {
                alert('Error: ' + (data.message || errorMsg));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred.');
        });
    }

    async function confirmRevokeCertificate(awardId) {
        if (!await window.bacConfirm({ title: 'Revoke this certificate?', message: 'The award certificate and its QR code stop verifying. The action is logged.', points: ['This cannot be undone.'], confirmLabel: 'Revoke certificate', tone: 'danger' })) {
            return;
        }
        sendCertificateAction(awardId, 'revoke', 'Certificate revoked successfully.', 'Failed to revoke certificate.');
    }

    async function regenerateToken(awardId) {
        if (!await window.bacConfirm({ title: 'Regenerate the QR code?', message: 'A new QR code is issued for this award. The action is logged.', points: ['The old QR code stops working, including printed copies.'], confirmLabel: 'Regenerate QR', tone: 'danger' })) {
            return;
        }
        sendCertificateAction(awardId, 'regenerate', 'QR token regenerated successfully.', 'Failed to regenerate QR token.');
    }

    function triggerReplaceCertificate(awardId) {
        const modalBody = document.getElementById('awardViewModalBody');
        const fileInput = modalBody.querySelector('.replace-certificate-input');
        if (fileInput) {
            fileInput.onchange = async function() {
                if (this.files.length === 0) return;
                const file = this.files[0];
                // Validate PDF
                if (file.type !== 'application/pdf') {
                    window.bacToast('Only PDF files are allowed.', 'error');
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    window.bacToast('The file is larger than 5 MB.', 'error');
                    return;
                }
                if (!await window.bacConfirm({ title: 'Replace the certificate?', message: file.name + ' replaces the current certificate PDF. The action is logged.', confirmLabel: 'Replace certificate' })) {
                    this.value = ''; // reset
                    return;
                }
                submitCertificateReplacement(awardId, file);
            };
            fileInput.click();
        }
    }

    function submitCertificateReplacement(awardId, file) {
        const formData = new FormData();
        formData.append('certificate_file', file);
        formData.append('_token', '{{ csrf_token() }}');

        fetch('/admin/awards/' + awardId + '/certificate/replace', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Certificate replaced successfully.');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to replace certificate.'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while replacing the certificate.');
        });
    }

    document.getElementById('awardViewModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAwardViewModal();
        }
    });

    document.getElementById('declareWinnerModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeclareWinnerModal();
        }
    });

    document.getElementById('declareWinnerModalBody').addEventListener('submit', function(e) {
        const form = e.target.closest('.declare-award-form');
        if (!form) return;

        validateDeclareWinnerForm();

        const submitBtn = form.querySelector('#declareWinnerSubmitBtn');
        const formAlert = form.querySelector('#awardFormAlert');
        if (submitBtn && submitBtn.disabled) {
            e.preventDefault();
            if (formAlert) {
                formAlert.textContent = 'Please select an eligible winning bidder and upload a valid PDF certificate up to 5MB.';
                formAlert.style.display = 'block';
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const successAlert = document.getElementById('successAlert');
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.transition = 'opacity 0.5s';
                successAlert.style.opacity = '0';
                setTimeout(() => successAlert.style.display = 'none', 500);
            }, 5000);
        }
    });
</script>

<script>
    (() => {
        const rows = Array.from(document.querySelectorAll('[data-award-row]'));
        const searchInput = document.getElementById('awardSearch');
        const statusFilter = document.getElementById('awardStatusFilter');
        const countLabel = document.getElementById('awardsRecordCount');
        const clearButton = document.getElementById('clearAwardFilters');
        const filterTabs = Array.from(document.querySelectorAll('[data-award-filter]'));
        const filteredEmpty = document.getElementById('awardsFilteredEmpty');
        let activeFilter = 'all';

        const applyFilters = () => {
            const query = (searchInput?.value || '').trim().toLowerCase();
            let visibleCount = 0;

            rows.forEach((row) => {
                const matchesStatus = activeFilter === 'all' || row.dataset.status === activeFilter;
                const matchesSearch = !query || (row.dataset.search || '').includes(query);
                const isVisible = matchesStatus && matchesSearch;

                row.hidden = !isVisible;
                if (isVisible) visibleCount += 1;
            });

            if (filteredEmpty) {
                filteredEmpty.hidden = rows.length === 0 || visibleCount > 0;
            }

            if (countLabel) {
                countLabel.textContent = `${visibleCount} ${visibleCount === 1 ? 'record' : 'records'}`;
            }
        };

        const setFilter = (filter) => {
            activeFilter = filter || 'all';

            if (statusFilter) {
                statusFilter.value = activeFilter;
            }

            filterTabs.forEach((tab) => {
                const isActive = tab.dataset.awardFilter === activeFilter;
                tab.classList.toggle('is-active', isActive);
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            applyFilters();
        };

        searchInput?.addEventListener('input', applyFilters);
        statusFilter?.addEventListener('change', (event) => setFilter(event.target.value));
        filterTabs.forEach((tab) => {
            tab.addEventListener('click', () => setFilter(tab.dataset.awardFilter));
        });
        clearButton?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            setFilter('all');
        });

        applyFilters();
    })();
</script>

<script>
    (() => {
        const dialog = document.getElementById('infrastructureTrackingModal');
        const frame = dialog?.querySelector('[data-infra-modal-frame]');
        if (!dialog || !frame) return;
        let opener = null;
        let updated = false;

        document.addEventListener('click', event => {
            const link = event.target.closest('a[data-infra-modal]');
            if (!link) return;
            if (typeof dialog.showModal !== 'function') return;
            event.preventDefault();
            opener = link;
            updated = false;
            const url = new URL(link.href, window.location.href);
            url.searchParams.set('embed', '1');
            frame.src = url.href;
            dialog.showModal();
            dialog.querySelector('[data-infra-modal-close]').focus();
        });

        dialog.querySelector('[data-infra-modal-close]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
        window.addEventListener('message', event => {
            if (event.origin === window.location.origin && event.source === frame.contentWindow
                && event.data?.type === 'sjbac:infrastructure-updated') updated = true;
        });
        dialog.addEventListener('close', () => {
            frame.src = 'about:blank';
            if (updated) {
                window.location.reload();
            } else if (opener?.isConnected) {
                opener.focus();
            }
        });
    })();
</script>
