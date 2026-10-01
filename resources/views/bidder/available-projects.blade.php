<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
@php
    // Reopen the bid modal after a failed submission, or when a notification links to a project.
    $reopenProjectId = old('project_id') ?: request()->integer('bid_project');
    $reopenBidModalId = $reopenProjectId ? 'bid-modal-' . $reopenProjectId : '';
@endphp
<div class="admin-dashboard dashboard-home admin-dashboard-page bidder-dashboard-page bidder-available-page" data-reopen-bid-modal="{{ $reopenBidModalId }}">
    @vite(['resources/css/dashboard.css'])
    @if(session('success'))
        <div id="bidSubmitSuccess" class="bidder-success-overlay" data-auto-hide="5000" role="status" aria-live="polite">
            <div class="bidder-success-alert">
                <span class="bidder-success-icon" aria-hidden="true">
                    <span class="bidder-success-loader"></span>
                    <svg class="bidder-success-check" viewBox="0 0 24 24">
                        <path d="M20 6L9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span class="bidder-success-text">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    <style>
        /* ---------------------------------------------------------------------
           Available Projects list. Palette is fixed to the existing system:
           #2B7FD4/#1E6FC4 primary, #1b2420 headings, #6B7280 muted, #9AA3AF
           disabled, #E5E9F0 borders, #EEF1F6 dividers, #faf8f3 surfaces,
           #EAF3FB/#1E6FC4 soft blue, #ECFDF3/#15803D success, #DC2626 danger,
           #F59E0B amber with #B45309 text. No gradients, one card depth.
           Numerals use tabular figures of the existing family rather than a new
           monospace face, since no new font may be introduced.
           --------------------------------------------------------------------- */
        .bidder-available-page {
            font-family: var(--ui-font);
            color: var(--ui-ink);
        }

        body .admin-dashboard.bidder-available-page .dashboard-content {
            background: var(--ui-page) !important;
        }

        .bidder-list-card {
            border: 1px solid var(--ui-line);
            border-radius: var(--ui-radius-lg);
            background: #FFFFFF;
            box-shadow: none;
            overflow: hidden;
        }

        .bidder-list-head {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 10px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--ui-line);
            background: #FFFFFF;
        }

        .bidder-list-head h2 {
            margin: 0 0 3px;
            color: var(--ui-ink);
            font-size: 16px;
            font-weight: 600;
            line-height: 1.3;
        }

        .bidder-list-summary {
            margin: 0;
            color: var(--ui-muted);
            font-size: 13px;
            line-height: 1.4;
        }

        .bidder-list-summary strong {
            color: var(--ui-ink);
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

        .bidder-list-summary .is-urgent {
            color: #B45309;
            font-weight: 600;
        }

        /* Filter bar stays reachable while the list scrolls. */
        .bidder-list-toolbar {
            position: sticky;
            top: var(--bac-dashboard-topbar-height, 70px);
            z-index: 6;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 20px;
            border-bottom: 1px solid var(--ui-line);
            background: #FFFFFF;
        }

        .bidder-tabs {
            display: inline-flex;
            gap: 2px;
            padding: 3px;
            border: 1px solid var(--ui-line);
            border-radius: var(--ui-radius-lg);
            background: var(--ui-surface-2);
        }

        .bidder-tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border: 1px solid transparent;
            border-radius: 8px;
            color: var(--ui-muted);
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            white-space: nowrap;
        }

        .bidder-tab:hover {
            color: var(--ui-ink);
        }

        .bidder-tab.is-active {
            border-color: var(--ui-line);
            background: #FFFFFF;
            color: var(--ui-ink);
            font-weight: 600;
        }

        .bidder-tab-count {
            color: var(--ui-subtle);
            font-size: 12px;
            font-variant-numeric: tabular-nums;
        }

        .bidder-tab.is-active .bidder-tab-count {
            color: var(--ui-info);
        }

        .bidder-toolbar-controls {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .bidder-search {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .bidder-search i {
            position: absolute;
            left: 11px;
            color: var(--ui-subtle);
            font-size: 13px;
            pointer-events: none;
        }

        body .admin-dashboard.bidder-available-page .bidder-search input {
            width: 268px;
            min-height: 36px;
            height: 36px;
            padding: 0 12px 0 32px;
            box-sizing: border-box;
            border: 1px solid var(--ui-line);
            border-radius: var(--ui-radius);
            background: #FFFFFF;
            color: var(--ui-ink);
            font-size: 13px;
        }

        body .admin-dashboard.bidder-available-page .bidder-search input::placeholder {
            color: var(--ui-subtle);
        }

        body .admin-dashboard.bidder-available-page .bidder-search input:focus {
            border-color: var(--ui-info);
            outline: none;
            box-shadow: 0 0 0 3px rgba(43, 127, 212, 0.12);
        }

        body .admin-dashboard.bidder-available-page .bidder-sort {
            min-height: 38px;
            height: 38px;
            padding: 0 12px;
            box-sizing: border-box;
            border: 1px solid var(--ui-line);
            border-radius: var(--ui-radius-lg);
            background: #FFFFFF;
            color: var(--ui-ink);
            font-size: 13px;
        }

        body .admin-dashboard.bidder-available-page .bidder-sort:focus {
            border-color: var(--ui-info);
            outline: none;
            box-shadow: 0 0 0 3px rgba(43, 127, 212, 0.12);
        }

        .bidder-thead,
        .bidder-row {
            display: grid;
            grid-template-columns: minmax(0, 2.6fr) minmax(112px, 0.85fr) minmax(128px, 0.95fr) 72px 138px;
            align-items: center;
            gap: 16px;
        }

        .bidder-thead {
            padding: 9px 20px;
            border-bottom: 1px solid var(--ui-line);
            background: var(--ui-surface-2);
        }

        .bidder-thead span {
            color: var(--ui-muted);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: normal;
            text-transform: none;
        }

        .bidder-th-right {
            text-align: right;
        }

        .bidder-row {
            position: relative;
            padding: 13px 20px;
            border-bottom: 1px solid #EEF1F6;
        }

        .bidder-row:last-of-type {
            border-bottom: 0;
        }

        .bidder-row:hover {
            background: var(--ui-surface-2);
        }

        /* Urgency reads from the stripe before any text is parsed. */
        .bidder-row::before {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: var(--ui-info);
            content: "";
        }

        .bidder-row.is-today::before { background: #DC2626; }
        .bidder-row.is-soon::before { background: #F59E0B; }
        .bidder-row.is-closed::before { background: var(--ui-line); }

        .bidder-cell-project {
            min-width: 0;
        }

        .bidder-row-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }

        .bidder-ref {
            color: var(--ui-muted);
            font-size: 11.5px;
            font-weight: 600;
            letter-spacing: 0.04em;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .bidder-pill {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border: 1px solid transparent;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        .bidder-pill.is-open { background: #ECFDF3; color: #15803D; }
        .bidder-pill.is-closed { border-color: var(--ui-line); background: var(--ui-surface-2); color: var(--ui-muted); }
        .bidder-pill.is-bid { background: var(--ui-info-soft); color: var(--ui-info); }

        .bidder-row-title {
            margin: 0 0 3px;
            overflow: hidden;
            color: var(--ui-ink);
            font-size: 14px;
            font-weight: 600;
            line-height: 1.35;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .bidder-row-sub {
            margin: 0;
            overflow: hidden;
            color: var(--ui-muted);
            font-size: 12.5px;
            line-height: 1.4;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .bidder-cell-budget {
            text-align: right;
        }

        .bidder-amount {
            color: var(--ui-ink);
            font-size: 14px;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            font-feature-settings: "tnum" 1;
            white-space: nowrap;
        }

        .bidder-cell-deadline {
            min-width: 0;
        }

        .bidder-date {
            display: block;
            color: var(--ui-ink);
            font-size: 13.5px;
            font-weight: 500;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .bidder-urgency {
            display: block;
            margin-top: 2px;
            font-size: 12px;
            font-weight: 600;
        }

        .bidder-urgency.is-today { color: #DC2626; }
        .bidder-urgency.is-soon { color: #B45309; }
        .bidder-urgency.is-open { color: var(--ui-info); }
        .bidder-urgency.is-closed { color: var(--ui-subtle); }

        .bidder-cell-bids {
            color: var(--ui-ink);
            font-size: 14px;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            text-align: right;
        }

        /* One button shape in one slot - only the label and fill change. */
        body .admin-dashboard.bidder-available-page .bidder-action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 36px;
            height: 36px;
            padding: 0 12px;
            box-sizing: border-box;
            border: 1px solid transparent;
            border-radius: 8px;
            background: none;
            font-size: 12.5px;
            font-weight: 600;
            line-height: 1;
            text-decoration: none;
            white-space: nowrap;
            cursor: pointer;
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }

        body .admin-dashboard.bidder-available-page .bidder-action-primary {
            border-color: var(--ui-info);
            background: var(--ui-info);
            color: #FFFFFF;
            -webkit-text-fill-color: #FFFFFF;
        }

        body .admin-dashboard.bidder-available-page .bidder-action-primary:hover {
            border-color: var(--ui-info);
            background: var(--ui-info);
        }

        body .admin-dashboard.bidder-available-page .bidder-action-outline {
            border-color: #BBD8F0;
            background: #FFFFFF;
            color: var(--ui-info);
            -webkit-text-fill-color: var(--ui-info);
        }

        body .admin-dashboard.bidder-available-page .bidder-action-outline:hover {
            background: var(--ui-info-soft);
        }

        body .admin-dashboard.bidder-available-page .bidder-action-muted {
            border-color: var(--ui-line);
            background: #FFFFFF;
            color: var(--ui-muted);
            -webkit-text-fill-color: var(--ui-muted);
        }

        body .admin-dashboard.bidder-available-page .bidder-action-muted:hover {
            background: var(--ui-surface-2);
            color: var(--ui-ink);
            -webkit-text-fill-color: var(--ui-ink);
        }

        .bidder-list-empty {
            padding: 46px 20px;
            text-align: center;
        }

        .bidder-list-empty-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            margin: 0 auto 12px;
            border: 1px solid var(--ui-line);
            border-radius: var(--ui-radius-lg);
            background: var(--ui-surface-2);
            color: var(--ui-subtle);
            font-size: 17px;
        }

        .bidder-list-empty h3 {
            margin: 0 0 4px;
            color: var(--ui-ink);
            font-size: 14px;
            font-weight: 600;
        }

        .bidder-list-empty p {
            margin: 0 0 14px;
            color: var(--ui-muted);
            font-size: 13px;
        }

        .bidder-list-empty a {
            display: inline-flex;
            align-items: center;
            height: 34px;
            padding: 0 14px;
            border: 1px solid #BBD8F0;
            border-radius: 8px;
            background: #FFFFFF;
            color: var(--ui-info);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .bidder-list-empty a:hover {
            background: var(--ui-info-soft);
        }

        .bidder-list-foot {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 11px 20px;
            border-top: 1px solid var(--ui-line);
            background: var(--ui-surface-2);
        }

        .bidder-list-count {
            color: var(--ui-muted);
            font-size: 12.5px;
            font-variant-numeric: tabular-nums;
        }

        .bidder-list-count strong {
            color: var(--ui-ink);
            font-weight: 600;
        }

        .bidder-pager {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .bidder-pager-info {
            color: var(--ui-muted);
            font-size: 12.5px;
            font-variant-numeric: tabular-nums;
        }

        .bidder-pager-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 32px;
            padding: 0 12px;
            border: 1px solid var(--ui-line);
            border-radius: 8px;
            background: #FFFFFF;
            color: var(--ui-ink);
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
        }

        .bidder-pager-btn:hover {
            border-color: #BBD8F0;
            background: var(--ui-info-soft);
            color: var(--ui-info);
        }

        .bidder-pager-btn.is-disabled {
            background: var(--ui-surface-2);
            color: var(--ui-subtle);
            pointer-events: none;
        }

        /* Column labels are hidden on desktop (the header row carries them) and only
           reappear per cell once the grid collapses. */
        @media (max-width: 1040px) {
            .bidder-thead {
                display: none;
            }

            .bidder-row {
                grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
                gap: 12px 16px;
                padding: 14px 16px 14px 18px;
            }

            .bidder-cell-project,
            .bidder-cell-action {
                grid-column: 1 / -1;
            }

            .bidder-cell-budget,
            .bidder-cell-bids {
                text-align: left;
            }

            .bidder-cell-budget::before,
            .bidder-cell-deadline::before,
            .bidder-cell-bids::before {
                display: block;
                margin-bottom: 2px;
                color: var(--ui-muted);
                font-size: 11px;
                font-weight: 600;
                letter-spacing: normal;
                text-transform: none;
                content: attr(data-label);
            }
        }

        @media (max-width: 720px) {
            .bidder-list-toolbar {
                position: static;
                align-items: stretch;
                flex-direction: column;
            }

            .bidder-tabs {
                overflow-x: auto;
            }

            .bidder-toolbar-controls {
                display: grid;
                grid-template-columns: minmax(0, 1fr);
                gap: 8px;
            }

            body .admin-dashboard.bidder-available-page .bidder-search input {
                width: 100%;
            }

            .bidder-list-foot {
                flex-direction: column;
                align-items: stretch;
            }

            .bidder-pager {
                justify-content: space-between;
            }
        }
        /* .bidder-submit-trigger is only a JS hook in the list - the row button takes its
           appearance from .bidder-action-* so every action slot shares one shape. */
        .bidder-submit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 0 18px;
            border-radius: var(--ui-radius);
            border: 1px solid var(--ui-info);
            background: var(--ui-info);
            color: #FFFFFF;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.18s ease, border-color 0.18s ease;
        }

        .bidder-submit-btn:hover {
            background: var(--ui-info);
            border-color: var(--ui-info);
        }

        .bidder-modal-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        .bidder-modal-overlay.show {
            display: flex;
        }

        .bidder-modal {
            box-sizing: border-box;
        }

        .bidder-modal-header,
        .bidder-modal-footer {
            display: flex;
        }

        .bidder-modal-heading {
            min-width: 0;
        }

        .bidder-modal-body {
            min-width: 0;
            min-height: 0;
        }

        .bidder-project-card-stats {
            display: grid;
        }

        .bidder-project-stat {
            min-width: 0;
        }

        .bidder-project-stat strong {
            display: block;
        }

        .bidder-requirements-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
        }

        .bidder-requirements-icon {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .bidder-required-docs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .bidder-required-doc {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .bidder-requirements-grid {
            display: grid;
        }

        .bidder-requirement-section {
            min-width: 0;
        }

        .bidder-requirement-section strong {
            display: block;
        }

        .bidder-bid-form {
            display: grid;
        }

        .bidder-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
            min-width: 0;
        }

        .bidder-money-input {
            position: relative;
            min-width: 0;
        }

        .bidder-money-prefix {
            pointer-events: none;
            user-select: none;
        }

        .bidder-input {
            width: 100%;
            padding: 13px 15px;
            box-sizing: border-box;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        /* The currency symbol lives in the field label, not as an absolutely positioned
           overlay: the global modal-input normalizers in dashboard.css carry ID-level
           specificity (via :not(#budget_display) inside :is()) and reset the left padding
           an overlay would depend on, so the symbol would sit on top of the value. */
        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-bid-amount-section .bidder-money-input > .bidder-input {
            width: 100% !important;
            min-width: 0 !important;
            box-sizing: border-box !important;
            line-height: 1.4 !important;
        }

        .bidder-upload-box {
            position: relative;
            display: grid;
            grid-template-columns: 28px 34px minmax(0, 1fr) auto;
            align-items: center;
            gap: 10px;
            min-width: 0;
            text-align: left;
            overflow: hidden;
            cursor: pointer;
            transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
        }
        .bidder-upload-box input[type="file"] {
            position: absolute;
            inset: 0;
            display: block !important;
            width: 100% !important;
            height: 100% !important;
            opacity: 0 !important;
            color: transparent !important;
            background: transparent !important;
            border: 0 !important;
            appearance: none !important;
            -webkit-appearance: none !important;
            font-size: 0;
            cursor: pointer;
            z-index: 1;
        }

        .bidder-upload-step {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            flex: 0 0 28px;
            font-size: 11px;
            font-weight: 700;
        }

        .bidder-upload-copy {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .bidder-upload-actions {
            min-width: 0;
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
        }
        .bidder-upload-title {
            font-size: 12px;
            font-weight: 600;
        }

        .bidder-upload-meta {
            font-size: 11px;
        }

        .bidder-upload-selected {
            min-width: 0;
            max-width: 220px;
            flex: 0 1 auto;
            padding: 7px 11px;
            border-radius: 999px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .bidder-textarea {
            resize: vertical;
            line-height: 1.5;
        }

        .bidder-modal-footer {
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
        }

        .bidder-cancel-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 20px;
            cursor: pointer;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay {
            padding: clamp(12px, 2vw, 24px) !important;
            background: rgba(27, 36, 32, 0.58) !important;
            backdrop-filter: blur(6px) !important;
            -webkit-backdrop-filter: blur(6px) !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-submit-modal {
            display: flex !important;
            flex-direction: column !important;
            width: min(980px, calc(100vw - 32px)) !important;
            max-height: min(88vh, calc(100dvh - 24px)) !important;
            overflow: hidden !important;
            border: 1px solid var(--ui-line) !important;
            border-radius: var(--ui-radius-lg) !important;
            background: #ffffff !important;
            color: var(--ui-ink) !important;
            font-family: var(--ui-font) !important;
            box-shadow: 0 24px 70px rgba(27, 36, 32, 0.28) !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-modal-header,
        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-modal-footer {
            flex: 0 0 auto !important;
            background: #ffffff !important;
            border-color: var(--ui-line) !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-modal-header {
            align-items: center !important;
            padding: 18px 22px !important;
            border-bottom: 1px solid var(--ui-line) !important;
        }

        .bidder-submit-modal-overlay .bidder-modal-heading {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .bidder-submit-modal-overlay .bidder-modal-title-icon {
            width: 40px;
            height: 40px;
            flex: 0 0 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--ui-radius-lg);
            background: var(--ui-primary-soft);
            color: var(--ui-info);
            font-size: 17px;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-modal-title {
            margin: 0 0 4px !important;
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
            font-size: 20px !important;
            line-height: 1.2 !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-modal-subtitle {
            margin: 0 !important;
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
            font-size: 13px !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-modal-close {
            width: 36px !important;
            height: 36px !important;
            border: 1px solid var(--ui-line) !important;
            border-radius: var(--ui-radius-lg) !important;
            background: #ffffff !important;
            color: var(--ui-ink-2) !important;
            -webkit-text-fill-color: var(--ui-ink-2) !important;
            box-shadow: none !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-modal-close:hover {
            border-color: var(--ui-primary-line) !important;
            background: var(--ui-info-soft) !important;
            color: var(--ui-info) !important;
            -webkit-text-fill-color: var(--ui-info) !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-modal-body {
            flex: 1 1 auto !important;
            min-height: 0 !important;
            padding: 16px 22px !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            overscroll-behavior: contain !important;
            background: #ffffff !important;
            color: var(--ui-ink) !important;
        }

        /* Two-column modal layout: read-only project reference on the left,
           the actual bid form on the right, so the form is never below the fold. */
        .bidder-submit-modal-overlay .bidder-bid-layout {
            display: grid;
            grid-template-columns: minmax(0, 320px) minmax(0, 1fr);
            gap: 18px;
            align-items: start;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-bid-reference {
            display: grid !important;
            gap: 14px !important;
            padding: 16px !important;
            border: 1px solid var(--ui-line) !important;
            border-radius: var(--ui-radius-lg) !important;
            background: var(--ui-surface-2) !important;
            color: var(--ui-ink) !important;
        }

        /* Sections inside the reference column stay flat - the column itself is the card. */
        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-bid-reference .bidder-project-files,
        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-bid-reference .bidder-requirements-card {
            margin: 0 !important;
            padding: 14px 0 0 !important;
            border: 0 !important;
            border-top: 1px solid var(--ui-line) !important;
            border-radius: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-form-block {
            margin: 0 0 16px !important;
            padding: 16px !important;
            border: 1px solid var(--ui-line) !important;
            border-radius: var(--ui-radius-lg) !important;
            background: #ffffff !important;
            color: var(--ui-ink) !important;
            box-shadow: none !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-form-block:last-child {
            margin-bottom: 0 !important;
        }

        .bidder-submit-modal-overlay .bidder-ref-eyebrow,
        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-project-files-title {
            display: block;
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: normal;
            text-transform: uppercase;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-project-stat span,
        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-field label,
        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-field .bsm-pin-title {
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            text-transform: none !important;
            letter-spacing: 0 !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-project-card-title {
            margin: 6px 0 0 !important;
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
            font-size: 17px !important;
            font-weight: 600 !important;
            line-height: 1.35 !important;
            overflow-wrap: anywhere !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-project-card-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 10px !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-project-stat {
            padding: 10px 12px !important;
            border: 1px solid var(--ui-line) !important;
            border-radius: var(--ui-radius-lg) !important;
            background: #ffffff !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-project-stat strong {
            margin-top: 4px !important;
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
            font-size: 15px !important;
            overflow-wrap: anywhere !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-project-files-heading {
            display: flex !important;
            align-items: baseline !important;
            justify-content: space-between !important;
            gap: 8px !important;
            margin-bottom: 8px !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-project-files-count {
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
            font-size: 11px !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-project-file-link {
            width: 100% !important;
            justify-content: flex-start !important;
            padding: 9px 11px !important;
            border: 1px solid var(--ui-line) !important;
            border-radius: var(--ui-radius-lg) !important;
            background: #ffffff !important;
            color: var(--ui-info) !important;
            -webkit-text-fill-color: var(--ui-info) !important;
            font-size: 12px !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-project-file-link i {
            color: var(--ui-info) !important;
            -webkit-text-fill-color: var(--ui-info) !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-requirements-heading {
            align-items: flex-start !important;
            margin-bottom: 10px !important;
        }

        /* Decorative clipboard glyph floated out of alignment with the heading - the
           section label already carries the meaning, so it is hidden here. */
        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-requirements-icon {
            display: none !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-requirements-heading h4 {
            margin: 0 0 3px !important;
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            letter-spacing: normal !important;
            text-transform: none !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-form-section-heading {
            display: flex !important;
            align-items: flex-start !important;
            justify-content: space-between !important;
            gap: 12px !important;
            margin-bottom: 12px !important;
            padding-bottom: 10px !important;
            border-bottom: 1px solid var(--ui-line) !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-form-section-heading h3 {
            margin: 0 !important;
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
            font-size: 15px !important;
            font-weight: 600 !important;
            line-height: 1.35 !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-form-section-heading p {
            margin: 3px 0 0 !important;
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
            font-size: 12px !important;
            line-height: 1.4 !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-document-count {
            flex: 0 0 auto;
            padding: 5px 9px !important;
            border: 1px solid var(--ui-line) !important;
            border-radius: 999px !important;
            background: var(--ui-surface-2) !important;
            color: var(--ui-ink-2) !important;
            -webkit-text-fill-color: var(--ui-ink-2) !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            white-space: nowrap;
        }
        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-requirements-heading p,
        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-field-hint {
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
            font-size: 12px !important;
            line-height: 1.5 !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-required-docs {
            display: grid !important;
            gap: 6px !important;
            margin-bottom: 0 !important;
        }

        /* Reminder list, not completion state - flat rows with a neutral marker so these
           never read as "already satisfied" the way a checkmark pill does. */
        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-required-doc {
            gap: 8px !important;
            padding: 0 !important;
            border: 0 !important;
            border-radius: 0 !important;
            background: transparent !important;
            color: var(--ui-ink-2) !important;
            -webkit-text-fill-color: var(--ui-ink-2) !important;
            font-size: 12px !important;
            line-height: 1.4 !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-required-doc::before {
            flex: 0 0 5px;
            width: 5px;
            height: 5px;
            border-radius: 999px;
            background: var(--ui-subtle);
            content: "";
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-upload-icon {
            background: var(--ui-info-soft) !important;
            color: var(--ui-info) !important;
            -webkit-text-fill-color: var(--ui-info) !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-requirements-grid {
            gap: 10px !important;
            margin-top: 12px !important;
            padding-top: 12px !important;
            border-top: 1px solid var(--ui-line) !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-requirement-section {
            padding: 0 !important;
            border: 0 !important;
            border-radius: 0 !important;
            background: transparent !important;
            color: var(--ui-ink) !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-requirement-section strong {
            margin-bottom: 2px !important;
            color: var(--ui-ink-2) !important;
            -webkit-text-fill-color: var(--ui-ink-2) !important;
            font-size: 12px !important;
            font-weight: 600 !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-requirement-section p {
            margin: 0 !important;
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
            font-size: 12px !important;
            line-height: 1.45 !important;
            overflow-wrap: anywhere !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-bid-form {
            gap: 0 !important;
            min-width: 0 !important;
            padding-bottom: 0 !important;
        }

        /* The bid amount is the primary input of this dialog, so it gets the accent
           treatment and a larger control than the rest of the form. */
        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-bid-amount-section {
            border-color: var(--ui-primary-line) !important;
            background: var(--ui-surface-2) !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-bid-amount-section .bidder-field {
            gap: 6px !important;
            margin-top: 0 !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-bid-amount-section .bidder-input {
            min-height: 36px !important;
            font-size: 20px !important;
            font-weight: 600 !important;
            letter-spacing: -0.01em !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-bid-amount-section .bidder-money-prefix {
            font-size: 18px !important;
            font-weight: 600 !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-required-mark {
            color: #dc2626 !important;
            -webkit-text-fill-color: #dc2626 !important;
            font-weight: 700 !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-inline-error {
            min-height: 16px !important;
            margin-top: 1px !important;
            color: #dc2626 !important;
            -webkit-text-fill-color: #dc2626 !important;
            font-size: 12px !important;
            line-height: 1.35 !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-bid-amount-section .bidder-input.is-invalid {
            border-color: #dc2626 !important;
            box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.1) !important;
        }
        .bidder-submit-modal-overlay .bidder-bid-amount-section .bidder-field,
        .bidder-submit-modal-overlay .bidder-proposal-documents-section .bidder-field {
            margin-top: 10px;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-input {
            min-height: 36px !important;
            border: 1px solid #BBD8F0 !important;
            border-radius: var(--ui-radius) !important;
            background: #ffffff !important;
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
            font-size: 13px !important;
            box-shadow: none !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-input::placeholder {
            color: var(--ui-subtle) !important;
            -webkit-text-fill-color: var(--ui-subtle) !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-input:focus {
            border-color: var(--ui-info) !important;
            box-shadow: 0 0 0 2px rgba(29, 79, 64, 0.1) !important;
            outline: none !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-money-prefix {
            position: absolute !important;
            left: 14px !important;
            top: 50% !important;
            width: 20px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            transform: translateY(-50%) !important;
            line-height: 1 !important;
            pointer-events: none !important;
            user-select: none !important;
            z-index: 2 !important;
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-upload-box {
            min-height: 64px !important;
            padding: 8px 10px !important;
            border: 1px dashed #BBD8F0 !important;
            border-radius: var(--ui-radius-lg) !important;
            background: var(--ui-surface-2) !important;
            color: var(--ui-ink) !important;
            display: grid !important;
            grid-template-columns: 26px 30px minmax(0, 1fr) auto !important;
            align-items: center !important;
            gap: 8px !important;
            box-shadow: none !important;
        }
        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-upload-box:hover,
        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-upload-box.has-file {
            border-color: var(--ui-info) !important;
            background: var(--ui-info-soft) !important;
            box-shadow: 0 0 0 4px rgba(29, 79, 64, 0.08) !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-upload-step {
            border: 1px solid var(--ui-line) !important;
            background: var(--ui-info-soft) !important;
            color: var(--ui-info) !important;
            -webkit-text-fill-color: var(--ui-info) !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-upload-icon {
            width: 28px !important;
            height: 28px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 8px !important;
            font-size: 13px !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-upload-copy {
            min-width: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 3px !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-upload-actions {
            min-width: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: flex-end !important;
            gap: 6px !important;
            position: relative !important;
            z-index: 2 !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-upload-title {
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            letter-spacing: 0 !important;
            line-height: 1.3 !important;
            text-transform: none !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-upload-meta {
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
            font-size: 11px !important;
            font-weight: 400 !important;
            line-height: 1.35 !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-upload-selected {
            max-width: 170px !important;
            min-height: 28px !important;
            padding: 4px 8px !important;
            border: 1px solid var(--ui-line) !important;
            border-radius: 8px !important;
            background: #ffffff !important;
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
            font-size: 11px !important;
            font-weight: 500 !important;
            line-height: 1.4 !important;
            text-align: center;
            align-self: center !important;
        }
        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-upload-trigger {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-height: 28px !important;
            padding: 0 10px !important;
            border: 1px solid #BBD8F0 !important;
            border-radius: 8px !important;
            background: #ffffff !important;
            color: var(--ui-ink-2) !important;
            -webkit-text-fill-color: var(--ui-ink-2) !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            line-height: 1 !important;
            white-space: nowrap;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-upload-box.has-file .bidder-upload-selected {
            border-color: var(--ui-primary-line) !important;
            background: var(--ui-info-soft) !important;
            color: var(--ui-info) !important;
            -webkit-text-fill-color: var(--ui-info) !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-upload-trigger:hover {
            border-color: var(--ui-info) !important;
            color: var(--ui-info) !important;
            -webkit-text-fill-color: var(--ui-info) !important;
        }
        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-upload-remove {
            position: relative;
            z-index: 2;
            min-height: 28px !important;
            padding: 0 4px !important;
            border: 0 !important;
            background: transparent !important;
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            cursor: pointer;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-upload-remove:hover {
            color: #dc2626 !important;
            -webkit-text-fill-color: #dc2626 !important;
        }
        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-textarea {
            min-height: 88px !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-modal-footer {
            justify-content: space-between !important;
            gap: 12px !important;
            padding: 12px 22px !important;
            border-top: 1px solid var(--ui-line) !important;
        }

        /* Explains why Submit is disabled instead of leaving the bidder guessing. */
        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-submit-status {
            min-width: 0 !important;
            flex: 1 1 auto !important;
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
            font-size: 12px !important;
            line-height: 1.4 !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-submit-status.is-ready {
            color: #15803d !important;
            -webkit-text-fill-color: #15803d !important;
            font-weight: 600 !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-modal-footer-actions {
            display: inline-flex !important;
            flex: 0 0 auto !important;
            align-items: center !important;
            gap: 10px !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-cancel-btn,
        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-submit-btn {
            min-height: 36px !important;
            min-width: 112px !important;
            border-radius: var(--ui-radius) !important;
            font-size: 12.5px !important;
            font-weight: 600 !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-cancel-btn {
            border: 1px solid #BBD8F0 !important;
            background: #ffffff !important;
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-submit-btn {
            border-color: var(--ui-info) !important;
            background: var(--ui-info) !important;
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
            box-shadow: none !important;
        }

        body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-submit-btn:disabled {
            opacity: 0.5 !important;
            cursor: not-allowed !important;
            box-shadow: none !important;
            transform: none !important;
        }
        @media (max-width: 900px) {
            .bidder-submit-modal-overlay .bidder-bid-layout {
                grid-template-columns: 1fr;
                gap: 14px;
            }
        }

        @media (max-width: 760px) {
            body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay {
                align-items: center !important;
                padding: 10px !important;
            }

            body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-submit-modal {
                width: calc(100vw - 20px) !important;
                max-height: calc(100dvh - 20px) !important;
                border-radius: var(--ui-radius-lg) !important;
            }

            body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-modal-header,
            body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-modal-body,
            body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-modal-footer {
                padding-left: 16px !important;
                padding-right: 16px !important;
            }

            body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-project-card-stats,
            body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-requirements-grid {
                grid-template-columns: 1fr !important;
            }

            /* dashboard.css turns .bidder-available-page .bidder-modal-header into a
               column at this breakpoint, which drops the close button onto its own row. */
            body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-modal-header {
                flex-direction: row !important;
                flex-wrap: nowrap !important;
                align-items: center !important;
                gap: 12px !important;
            }

            body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-modal-heading {
                min-width: 0 !important;
            }

            body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-modal-close {
                flex: 0 0 auto !important;
            }

            body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-modal-footer {
                flex-direction: column-reverse !important;
                align-items: stretch !important;
            }

            body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-modal-footer-actions {
                display: flex !important;
                flex-direction: column-reverse !important;
                width: 100% !important;
                gap: 8px !important;
            }

            body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-submit-status {
                text-align: center !important;
            }

            body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-submit-btn,
            body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-cancel-btn {
                width: 100% !important;
            }
            body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-upload-box {
                grid-template-columns: 26px 30px minmax(0, 1fr) !important;
                align-items: center !important;
            }

            body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-upload-actions {
                grid-column: 3 !important;
                justify-content: flex-start !important;
                flex-wrap: wrap !important;
                width: 100% !important;
            }

            body .admin-dashboard.bidder-available-page .bidder-submit-modal-overlay .bidder-upload-selected {
                max-width: min(100%, 170px) !important;
                justify-self: auto !important;
            }
        }


        .bidder-alert {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: var(--ui-radius-lg);
            font-size: 12px;
        }

        .bidder-alert-success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .bidder-alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

        /* Submit modal: notice summary, submission method, checklist tags. */
        .bidder-submit-modal .bsm-notice { display: grid; gap: 6px; margin: 12px 0; padding: 0; font-size: 12.5px; }
        .bidder-submit-modal .bsm-notice > div { display: flex; justify-content: space-between; gap: 12px; padding: 5px 0; border-bottom: 1px solid var(--ui-line-soft); }
        .bidder-submit-modal .bsm-notice dt { color: var(--ui-muted); }
        .bidder-submit-modal .bsm-notice dd { margin: 0; color: var(--ui-ink); text-align: right; font-weight: 600; overflow-wrap: anywhere; }
        .bidder-submit-modal .bsm-method { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 14px; padding: 12px 14px; border-radius: var(--ui-radius-lg); border-left: 4px solid; font-size: 13px; line-height: 1.5; }
        .bidder-submit-modal .bsm-method p { margin: 2px 0 0; }
        .bidder-submit-modal .bsm-method > i { margin-top: 3px; }
        .bidder-submit-modal .bsm-method.is-electronic { background: var(--ui-primary-soft); border-color: var(--ui-primary); color: var(--ui-primary-hover); }
        .bidder-submit-modal .bsm-pin-title { display: block; font-weight: 600; }
        .bidder-submit-modal .bsm-pin-row { display: flex; flex-wrap: wrap; gap: 12px 16px; margin-top: 4px; }
        .bidder-submit-modal .bsm-pin-field { display: grid; gap: 4px; font-size: 13px; font-weight: 600; }
        .bidder-submit-modal .bsm-pin-field .bsm-pin { width: 11rem; max-width: 100%; text-align: center; font-size: 20px; letter-spacing: .5em; padding-left: calc(12px + .5em); font-variant-numeric: tabular-nums; }
        .bidder-submit-modal .bsm-pin-field .bsm-pin::placeholder { letter-spacing: .3em; }
        .bidder-submit-modal .bsm-method.is-modify { background: #fffbeb; border-color: #d97706; color: #78350f; }
        .bidder-submit-modal .bsm-method.is-manual { background: #fffbeb; border-color: #d97706; color: #78350f; }
        .bidder-submit-modal .bsm-method.is-security { background: var(--ui-surface-2); border-color: var(--ui-ink-2); color: var(--ui-ink); }

        /* Bidding fee: list pill, step tracker, payment card, locked form. */
        .bidder-row-meta { flex-wrap: wrap; }
        .bidder-pill.bsm-fee-pill { gap: 5px; }
        .bidder-pill.bsm-fee-pill.is-due { border-color: #FDE68A; background: #FFFBEB; color: #B45309; }
        .bidder-pill.bsm-fee-pill.is-paid { background: #ECFDF3; color: #15803D; }
        .bidder-submit-modal .bsm-steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin: 0 0 14px; padding: 0; list-style: none; }
        .bidder-submit-modal .bsm-step { position: relative; display: flex; align-items: center; gap: 10px; min-width: 0; padding: 10px 12px; border: 1px solid var(--ui-line); border-radius: var(--ui-radius-lg); background: #fff; color: var(--ui-muted); }
        .bidder-submit-modal .bsm-step-dot { display: grid; place-items: center; flex: 0 0 26px; height: 26px; border-radius: 50%; background: var(--ui-line-soft); color: var(--ui-ink-2); font-size: 12px; font-weight: 700; }
        .bidder-submit-modal .bsm-step-text { display: grid; min-width: 0; line-height: 1.3; }
        .bidder-submit-modal .bsm-step-text strong { color: var(--ui-ink); font-size: 12.5px; font-weight: 600; }
        .bidder-submit-modal .bsm-step-text small { font-size: 11.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .bidder-submit-modal .bsm-step.is-current { border-color: var(--ui-primary-line); background: var(--ui-primary-soft); }
        .bidder-submit-modal .bsm-step.is-current .bsm-step-dot { background: var(--ui-primary); color: #fff; }
        .bidder-submit-modal .bsm-step.is-done .bsm-step-dot { background: #15803d; color: #fff; }
        .bidder-submit-modal .bsm-pay { margin-bottom: 14px; padding: 14px 16px; border: 1px solid; border-radius: var(--ui-radius-lg); font-size: 13px; line-height: 1.5; }
        .bidder-submit-modal .bsm-pay.is-due { border-color: #fcd34d; background: #fffbeb; color: #78350f; }
        .bidder-submit-modal .bsm-pay.is-paid { border-color: #a7f3d0; background: #ecfdf5; color: #065f46; }
        .bidder-submit-modal .bsm-pay-head { display: flex; gap: 12px; align-items: flex-start; }
        .bidder-submit-modal .bsm-pay-head strong { display: block; font-size: 14px; color: inherit; }
        .bidder-submit-modal .bsm-pay-head p { margin: 2px 0 0; }
        .bidder-submit-modal .bsm-pay-icon { display: grid; place-items: center; flex: 0 0 34px; height: 34px; border-radius: var(--ui-radius-lg); background: rgba(255, 255, 255, .7); font-size: 15px; }
        .bidder-submit-modal .bsm-pay-details { display: grid; gap: 0; margin: 12px 0 0; padding: 4px 12px; border-radius: var(--ui-radius-lg); background: #fff; border: 1px solid #fde68a; }
        .bidder-submit-modal .bsm-pay-details > div { display: grid; grid-template-columns: 110px minmax(0, 1fr); gap: 12px; padding: 8px 0; border-bottom: 1px solid #fef3c7; }
        .bidder-submit-modal .bsm-pay-details > div:last-child { border-bottom: 0; }
        .bidder-submit-modal .bsm-pay-details dt { color: #92400e; font-size: 12px; font-weight: 600; }
        .bidder-submit-modal .bsm-pay-details dd { margin: 0; color: var(--ui-ink); overflow-wrap: anywhere; }
        .bidder-submit-modal .bsm-pay-amount { font-size: 18px; font-weight: 700; font-variant-numeric: tabular-nums; }
        .bidder-submit-modal .bsm-pay-foot { display: flex; gap: 8px; align-items: baseline; margin: 10px 0 0; font-size: 12.5px; }
        .bidder-submit-modal .bsm-notice dd strong { white-space: nowrap; }
        .bidder-submit-modal .bidder-submit-btn .fa-lock { margin-right: 6px; }
        .bidder-submit-modal .bsm-fieldset { min-width: 0; margin: 0; padding: 0; border: 0; }
        .bidder-submit-modal .bsm-fieldset:disabled { opacity: .55; }
        .bidder-submit-modal .bsm-fieldset:disabled .bidder-upload-box { cursor: not-allowed; }
        @media (max-width: 640px) {
            .bidder-submit-modal .bsm-steps { grid-template-columns: minmax(0, 1fr); }
            .bidder-submit-modal .bsm-pay-details > div { grid-template-columns: minmax(0, 1fr); gap: 2px; }
        }
        .bidder-submit-modal .bsm-tag { display: inline-block; margin-left: 6px; padding: 1px 7px; border-radius: 999px; background: var(--ui-line-soft); color: var(--ui-ink-2); font-size: 10.5px; font-weight: 700; vertical-align: middle; }
        .bidder-submit-modal .bsm-tag.is-required { background: #fee2e2; color: #b91c1c; }
        .bidder-submit-modal .bsm-error-list { margin: 6px 0 0; padding-left: 18px; }
        .bidder-project-files-subtitle { display: block; margin: 8px 0 4px; font-size: 11.5px; font-weight: 700; color: var(--ui-ink-2); text-transform: none; letter-spacing: normal; }
        .bidder-project-files-none, .bidder-project-files-empty { font-size: 12.5px; color: var(--ui-muted); margin: 4px 0; }
        .bidder-project-files-warning { margin: 8px 0 0; padding: 8px 10px; border-radius: 8px; background: #fffbeb; color: #92400e; font-size: 12px; }
        .bidder-success-overlay {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(27, 36, 32, 0.28);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            z-index: 2300;
            opacity: 1;
            transition: opacity 0.35s ease;
        }

        .bidder-success-overlay.fade-out {
            opacity: 0;
        }

        .bidder-success-alert {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: min(320px, calc(100vw - 32px));
            text-align: center;
            color: #ffffff;
            opacity: 1;
            transform: translateY(0);
            transition: opacity 0.35s ease, transform 0.35s ease;
        }

        .bidder-success-alert.fade-out {
            opacity: 0;
            transform: translateY(-12px);
        }

        body .admin-dashboard.bidder-available-page .bidder-success-icon {
            width: 86px;
            height: 86px;
            flex: 0 0 86px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 999px;
            background: #ffffff;
            color: #16a34a;
            box-shadow: 0 10px 28px rgba(27, 36, 32, 0.14);
        }

        body .admin-dashboard.bidder-available-page .bidder-success-loader {
            position: absolute;
            inset: 7px;
            border-radius: 999px;
            border: 4px solid rgba(34, 197, 94, 0.14);
            border-top-color: #22c55e;
            border-right-color: #16a34a;
            animation: bidderSuccessSpin 0.7s linear 2, bidderSuccessLoaderOut 0.2s ease 1.25s forwards;
        }

        body .admin-dashboard.bidder-available-page .bidder-success-check {
            width: 32px;
            height: 32px;
            opacity: 0;
            transform: scale(0.7);
            filter: drop-shadow(0 6px 12px rgba(22, 163, 74, 0.22));
            animation: bidderSuccessCheckIn 0.38s cubic-bezier(0.2, 0.9, 0.2, 1) 1.3s forwards;
        }

        body .admin-dashboard.bidder-available-page .bidder-success-check path {
            stroke-dasharray: 24;
            stroke-dashoffset: 24;
            animation: bidderSuccessCheckDraw 0.32s ease 1.34s forwards;
        }

        body .admin-dashboard.bidder-available-page .bidder-success-text {
            max-width: 100%;
            color: #ffffff;
            text-align: center;
            text-shadow: 0 10px 24px rgba(27, 36, 32, 0.35);
            font-size: 15px;
            font-weight: 600;
            line-height: 1.45;
            opacity: 0;
            transform: translateY(10px);
            animation: bidderSuccessTextIn 0.35s ease 1.75s forwards;
        }

        @keyframes bidderSuccessSpin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes bidderSuccessLoaderOut {
            to {
                opacity: 0;
                transform: scale(0.88);
            }
        }

        @keyframes bidderSuccessCheckIn {
            0% {
                opacity: 0;
                transform: scale(0.7);
            }
            70% {
                opacity: 1;
                transform: scale(1.08);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes bidderSuccessCheckDraw {
            to {
                stroke-dashoffset: 0;
            }
        }

        @keyframes bidderSuccessTextIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @media (max-width: 900px) {
            .bidder-card-top {
                flex-direction: column;
                gap: 14px;
            }

            .bidder-available-badges {
                width: 100%;
                max-width: none;
                justify-content: flex-start;
            }

            .bidder-card-meta {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px 20px;
            }
        }

        @media (max-width: 640px) {
            .bidder-card-meta {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .bidder-available-badges .bidder-submit-trigger {
                flex: 1 1 auto;
            }
        }
        .sb-steps { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; padding:14px 24px; border-bottom:1px solid #e7e1d6; background:#fff; }
        .sb-step { display:flex; align-items:center; gap:10px; min-width:0; padding:10px 12px; border:1px solid #e7e1d6; border-radius:12px; background:#fff; color:#47534e; text-align:left; cursor:pointer; }
        .sb-step.is-current { border-color:#9bc9b7; background:#e9f2ed; color:#173f32; }
        .sb-step__number { display:grid; place-items:center; flex:0 0 34px; width:34px; height:34px; border:2px solid #d8d1c4; border-radius:50%; font-weight:700; }
        .sb-step.is-current .sb-step__number { border-color:#205846; background:#205846; color:#fff; }
        .sb-step strong,.sb-step small { display:block; }
        .sb-step strong { font-size:14px; }
        .sb-step small { margin-top:2px; color:#65736d; font-size:12px; }
        .sb-panel[hidden],.sb-bulk-upload[hidden],.sb-foot-actions [hidden] { display:none !important; }
        @media(max-width:700px) { .sb-steps { gap:6px; padding:10px 12px; } .sb-step { justify-content:center; padding:8px 5px; } .sb-step small { display:none; } .sb-step strong { font-size:12px; } .sb-step__number { flex-basis:28px; width:28px; height:28px; } }
    </style>

        @include('partials.bidder-sidebar')

    <div class="main-area">
        <x-page-header title="Available projects" subtitle="Browse and bid on open procurement projects" />

        <main class="dashboard-content dashboard-home-content">

            @if($errors->any())
                <div class="bidder-alert bidder-alert-error">{{ $errors->first() }}</div>
            @endif

            @php
                $tabs = [
                    'all' => 'All',
                    'closing' => 'Closing soon',
                    'mine' => 'Already bid',
                    'closed' => 'Closed',
                ];
                $tabLink = fn (string $key) => request()->fullUrlWithQuery(['tab' => $key, 'page' => null]);
            @endphp

            <section class="bidder-list-card">
                <header class="bidder-list-head">
                    <div>
                        <h2>Open procurement opportunities</h2>
                        <p class="bidder-list-summary">
                            <strong>{{ $listOpenCount }}</strong> open for bidding
                            &middot;
                            <span class="is-urgent"><strong>{{ $listClosingSoonCount }}</strong> closing within 4 days</span>
                        </p>
                    </div>
                </header>

                <form method="GET" action="{{ route('bidder.available-projects') }}" class="bidder-list-toolbar">
                    <input type="hidden" name="tab" value="{{ $listTab }}">

                    <div class="bidder-tabs" role="tablist" aria-label="Filter projects">
                        @foreach($tabs as $tabKey => $tabLabel)
                            <a href="{{ $tabLink($tabKey) }}"
                               class="bidder-tab {{ $listTab === $tabKey ? 'is-active' : '' }}"
                               role="tab"
                               aria-selected="{{ $listTab === $tabKey ? 'true' : 'false' }}">
                                {{ $tabLabel }}
                                @if($tabKey === 'closing' && $listClosingSoonCount > 0)
                                    <span class="bidder-tab-count">{{ $listClosingSoonCount }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>

                    <div class="bidder-toolbar-controls">
                        <label class="bidder-search">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <span class="sr-only">Search projects</span>
                            <input type="search" name="q" value="{{ $listSearch }}" placeholder="Search title, reference no., or office">
                        </label>

                        <label class="sr-only" for="projects-sort">Sort projects</label>
                        <select name="sort" id="projects-sort" class="bidder-sort" onchange="this.form.submit()">
                            <option value="closing" @selected($listSort === 'closing')>Closing soonest</option>
                            <option value="budget" @selected($listSort === 'budget')>Highest budget</option>
                            <option value="newest" @selected($listSort === 'newest')>Newest posted</option>
                        </select>
                    </div>
                </form>

                <div class="bidder-thead" aria-hidden="true">
                    <span>Project</span>
                    <span class="bidder-th-right">Budget</span>
                    <span>Deadline</span>
                    <span class="bidder-th-right">Bids</span>
                    <span></span>
                </div>

            @forelse($listProjects as $project)
                @php
                    $myBid = $myBids->firstWhere('project_id', $project->id);
                    $deadline = $project->bidSubmissionDeadline();
                    $isOpen = $project->isOpenForBidding();
                    $daysLeft = $deadline && $isOpen
                        ? (int) round(now()->startOfDay()->diffInDays($deadline->copy()->startOfDay(), false))
                        : null;
                    $tone = match (true) {
                        ! $isOpen => 'closed',
                        $daysLeft <= 0 => 'today',
                        $daysLeft <= 4 => 'soon',
                        default => 'open',
                    };
                    $urgencyText = match ($tone) {
                        'closed' => $deadline ? 'Closed ' . $deadline->format('M d') : 'Closed',
                        'today' => 'Due today, ' . $deadline->format('g:i A'),
                        default => $daysLeft . ' ' . \Illuminate\Support\Str::plural('day', $daysLeft) . ' left',
                    };
                    $fileCount = $project->officialDocuments()->count();
                @endphp
                @php
                    $isDraftBid = $myBid && $myBid->isDraft();
                    $electronic = $project->acceptsElectronicSubmission();
                    // Sec. 55.1: an official online bid may be modified until the deadline.
                    $isModifying = $isOpen && $electronic && $myBid && $myBid->isModifiableOnline();
                    $canPrepare = $isOpen && (! $myBid || $isDraftBid || $isModifying);
                    $requiresFee = $project->requiresBiddingFee();
                    $feePayment = $requiresFee ? $project->biddingFeePaymentFor(auth()->id()) : null;
                    $paymentLocked = $requiresFee && ! $feePayment;
                    $feeLabel = '₱' . number_format((float) $project->bidding_documents_fee, 2);
                @endphp
                <div class="bidder-row is-{{ $tone }}">
                    <div class="bidder-cell-project">
                        <div class="bidder-row-meta">
                            <span class="bidder-ref">{{ $project->reference_no ?: 'REF PENDING' }}</span>
                            @if($isDraftBid)
                                <span class="bidder-pill is-closed" title="A saved draft is not an official bid">Draft &middot; not submitted</span>
                            @elseif($myBid)
                                <span class="bidder-pill is-bid">Bid submitted</span>
                            @elseif($isOpen)
                                <span class="bidder-pill is-open">Open for bidding</span>
                            @else
                                <span class="bidder-pill is-closed">Closed</span>
                            @endif
                            @if($canPrepare && $requiresFee)
                                @if($feePayment)
                                    <span class="bidder-pill bsm-fee-pill is-paid" title="OR No. {{ $feePayment->or_number }}"><i class="fas fa-circle-check" aria-hidden="true"></i> Fee paid</span>
                                @else
                                    <span class="bidder-pill bsm-fee-pill is-due"><i class="fas fa-receipt" aria-hidden="true"></i> Pay {{ $feeLabel }} at BAC</span>
                                @endif
                            @endif
                        </div>
                        <h3 class="bidder-row-title" title="{{ $project->title }}">{{ $project->title }}</h3>
                        <p class="bidder-row-sub">{{ $project->end_user_unit ?: 'Bids and Awards Committee' }} &middot; {{ $fileCount }} official {{ \Illuminate\Support\Str::plural('file', $fileCount) }}</p>
                    </div>

                    <div class="bidder-cell-budget" data-label="Budget">
                        <span class="bidder-amount">&#8369;{{ number_format((float) $project->budget, 2) }}</span>
                    </div>

                    <div class="bidder-cell-deadline" data-label="Deadline">
                        <span class="bidder-date">{{ $deadline?->format('M d, Y') ?? 'Not set' }}</span>
                        <span class="bidder-urgency is-{{ $tone }}">{{ $urgencyText }}</span>
                    </div>

                    <div class="bidder-cell-bids" data-label="Bids">{{ $project->bids_count }}</div>

                    <div class="bidder-cell-action">
                        @if($isModifying)
                            <a href="{{ route('bidder.bidding-track', ['bid' => $myBid->id]) }}" class="bidder-action-btn bidder-action-outline">View your bid</a>
                            <button type="button" class="bidder-action-btn bidder-action-primary bidder-submit-trigger" data-target="bid-modal-{{ $project->id }}" title="Replace your price or documents before the deadline">Modify bid</button>
                        @elseif($canPrepare)
                            <button type="button" class="bidder-action-btn bidder-action-primary bidder-submit-trigger" data-target="bid-modal-{{ $project->id }}">{{ $paymentLocked ? 'How to pay' : ($isDraftBid ? 'Continue draft' : ($electronic ? 'Submit bid' : 'Prepare bid')) }}</button>
                        @elseif($myBid && $isOpen)
                            <a href="{{ route('bidder.bidding-track', ['bid' => $myBid->id]) }}" class="bidder-action-btn bidder-action-outline">View your bid</a>
                        @else
                            <a href="{{ route('bidder.my-bids') }}" class="bidder-action-btn bidder-action-muted">View result</a>
                        @endif
                    </div>
                </div>

                @if($canPrepare)
                    @include('bidder.partials.submit-bid-modal', compact('project', 'myBid', 'isDraftBid', 'isModifying', 'electronic', 'requiresFee', 'feePayment', 'paymentLocked', 'feeLabel', 'deadline'))
                @endif
            @empty
                <div class="bidder-list-empty">
                    <span class="bidder-list-empty-icon" aria-hidden="true"><i class="fas fa-folder-open"></i></span>
                    <h3>No projects match these filters</h3>
                    <p>
                        @if($listSearch !== '')
                            Nothing matched &ldquo;{{ $listSearch }}&rdquo; in this tab.
                        @else
                            There are no projects in this tab right now.
                        @endif
                    </p>
                    <a href="{{ route('bidder.available-projects') }}">Clear filters</a>
                </div>
            @endforelse

                <footer class="bidder-list-foot">
                    <span class="bidder-list-count">
                        Showing <strong>{{ $listProjects->count() }}</strong> of <strong>{{ $listProjects->total() }}</strong> {{ \Illuminate\Support\Str::plural('project', $listProjects->total()) }}
                    </span>

                    @if($listProjects->lastPage() > 1)
                        <span class="bidder-pager">
                            <a href="{{ $listProjects->previousPageUrl() ?: '#' }}"
                               class="bidder-pager-btn {{ $listProjects->onFirstPage() ? 'is-disabled' : '' }}"
                               @if($listProjects->onFirstPage()) aria-disabled="true" @endif>Previous</a>
                            <span class="bidder-pager-info">Page {{ $listProjects->currentPage() }} of {{ $listProjects->lastPage() }}</span>
                            <a href="{{ $listProjects->nextPageUrl() ?: '#' }}"
                               class="bidder-pager-btn {{ $listProjects->hasMorePages() ? '' : 'is-disabled' }}"
                               @unless($listProjects->hasMorePages()) aria-disabled="true" @endunless>Next</a>
                        </span>
                    @endif
                </footer>
            </section>
        </main>
    </div>
</div>

@include('bidder.partials.submit-bid-assets')
<script>
    (function () {
        const BID_SUCCESS_HIDE_DELAY = 5000;
        const BID_SUCCESS_FADE_DURATION = 350;
        const bidSubmitSuccess = document.getElementById('bidSubmitSuccess');

        if (bidSubmitSuccess) {
            const bidSubmitAlert = bidSubmitSuccess.querySelector('.bidder-success-alert');
            const delay = Number(bidSubmitSuccess.dataset.autoHide) || BID_SUCCESS_HIDE_DELAY;

            window.setTimeout(function () {
                if (bidSubmitAlert) {
                    bidSubmitAlert.classList.add('fade-out');
                }

                bidSubmitSuccess.classList.add('fade-out');

                window.setTimeout(function () {
                    bidSubmitSuccess.remove();
                }, BID_SUCCESS_FADE_DURATION);
            }, delay);
        }
        function resetBidderModalScroll(target) {
            const scrollTargets = [
                target,
                target.querySelector('.bidder-modal, [data-bid-dialog]'),
                target.querySelector('.bidder-modal-body, [data-scroll-body]')
            ];

            scrollTargets.forEach(function (element) {
                if (element) element.scrollTop = 0;
            });
        }

        function openBidderModal(target) {
            if (!target) return;

            target.hidden = false;
            target.classList.add('show');
            target.setAttribute('aria-hidden', 'false');
            document.body.classList.add('bac-modal-open');
            resetBidderModalScroll(target);

            requestAnimationFrame(function () {
                resetBidderModalScroll(target);
                const dialog = target.querySelector('.bidder-modal, [data-bid-dialog]');
                if (dialog) dialog.focus({ preventScroll: true });
            });
        }

        function closeBidderModal(target) {
            if (!target) return;

            target.classList.remove('show');
            target.setAttribute('aria-hidden', 'true');
            target.hidden = true;

            if (!document.querySelector('.bidder-modal-overlay.show')) {
                document.body.classList.remove('bac-modal-open');
            }
        }

        document.querySelectorAll('.bidder-submit-trigger').forEach(function (button) {
            button.addEventListener('click', function () {
                openBidderModal(document.getElementById(button.dataset.target));
            });
        });

        document.querySelectorAll('[data-close-modal]').forEach(function (button) {
            button.addEventListener('click', function () {
                closeBidderModal(document.getElementById(button.dataset.closeModal));
            });
        });

        document.querySelectorAll('.bidder-modal-overlay').forEach(function (overlay) {
            overlay.addEventListener('click', function (event) {
                if (event.target === overlay) {
                    closeBidderModal(overlay);
                }
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;

            const openModal = document.querySelector('.bidder-modal-overlay.show');
            if (openModal) closeBidderModal(openModal);
        });

        const pageShell = document.querySelector('.bidder-available-page[data-reopen-bid-modal]');
        if (pageShell && pageShell.dataset.reopenBidModal) {
            openBidderModal(document.getElementById(pageShell.dataset.reopenBidModal));
        }
        function getBidSubmitButton(form) {
            const modal = form ? form.closest('.bidder-submit-modal, [data-bid-dialog]') : null;
            return modal ? modal.querySelector('.bidder-submit-btn, [data-bid-submit]') : null;
        }

        function sanitizeBidAmount(value) {
            let clean = String(value || '').replace(/,/g, '').replace(/[^0-9.]/g, '');
            const dot = clean.indexOf('.');
            if (dot >= 0) {
                clean = clean.slice(0, dot + 1) + clean.slice(dot + 1).replace(/[.]/g, '');
            }
            const parts = clean.split('.');
            if (parts.length > 1) {
                clean = parts[0] + '.' + parts[1].slice(0, 2);
            }
            return clean;
        }

        function formatBidAmount(value, withDecimals) {
            const clean = sanitizeBidAmount(value);
            if (!/[0-9]/.test(clean)) return '';

            const parts = clean.split('.');
            let whole = (parts[0] || '0').replace(/^0+(?=[0-9])/, '') || '0';
            whole = whole.replace(/([0-9])(?=([0-9]{3})+(?![0-9]))/g, '$1,');

            if (parts.length === 1) {
                return withDecimals ? whole + '.00' : whole;
            }

            let decimals = parts[1].slice(0, 2);
            if (withDecimals) decimals = (decimals + '00').slice(0, 2);
            return whole + '.' + decimals;
        }

        function normalizeBidAmount(value) {
            const clean = sanitizeBidAmount(value);
            if (!clean || !/[0-9]/.test(clean)) return '';

            const amount = Number(clean);
            return Number.isFinite(amount) && amount >= 0 ? amount.toFixed(2) : '';
        }

        function setBidAmountError(input, message) {
            const field = input.closest('.bidder-field, [data-bid-field]');
            const error = field ? field.querySelector('[data-bid-amount-error]') : null;
            input.classList.toggle('is-invalid', Boolean(message));
            input.setAttribute('aria-invalid', message ? 'true' : 'false');
            if (error) error.textContent = message || '';
        }

        // Compares two-decimal amounts as integer centavos, so the check never
        // depends on floating-point rounding. The server repeats this check.
        function toCentavos(value) {
            const parts = String(value).split('.');
            return BigInt(parts[0] || '0') * 100n + BigInt(((parts[1] || '') + '00').slice(0, 2));
        }

        function validateBidAmount(input, showEmpty) {
            const raw = input.value.trim();
            const normalized = normalizeBidAmount(raw);
            const abc = input.form ? input.form.dataset.abc : '';
            let message = '';

            if (!normalized && (showEmpty || raw)) {
                message = raw ? 'Enter a valid numeric amount.' : 'Bid price is required.';
            } else if (normalized && Number(normalized) <= 0) {
                message = 'Bid price must be greater than zero.';
            } else if (normalized && abc && Number(abc) > 0 && toCentavos(normalized) > toCentavos(abc)) {
                message = 'Your bid price exceeds the Approved Budget for the Contract (₱' + formatBidAmount(abc, true) + '). Bids above the ABC are not accepted.';
            }

            setBidAmountError(input, message);
            return Boolean(normalized) && message === '';
        }

        function updateUploadState(input) {
            const uploadBox = input.closest('.bidder-upload-box, [data-upload-box]');
            if (!uploadBox) return;

            const selected = uploadBox.querySelector('[data-file-name]');
            const changeButton = uploadBox.querySelector('[data-upload-change]');
            const removeButton = uploadBox.querySelector('[data-upload-remove]');
            const file = input.files && input.files.length ? input.files[0] : null;

            if (selected) {
                selected.textContent = file ? file.name : 'Not selected';
                selected.title = file ? file.name : '';
            }
            if (changeButton) {
                if (!changeButton.dataset.label) changeButton.dataset.label = changeButton.textContent.trim();
                changeButton.textContent = file ? 'Change file' : changeButton.dataset.label;
            }
            if (removeButton) removeButton.hidden = !file;
            uploadBox.classList.toggle('has-file', Boolean(file));
        }

        function updateBidStatusHint(form, amountValid, missingDocuments) {
            const modal = form ? form.closest('.bidder-submit-modal, [data-bid-dialog]') : null;
            const status = modal ? modal.querySelector('[data-bid-status]') : null;
            if (!status) return;

            const pending = [];
            if (!amountValid) pending.push('a valid bid price');
            if (!pinState(form).valid) pending.push(pinState(form).message);
            if (missingDocuments > 0) {
                pending.push(missingDocuments + ' required ' + (missingDocuments === 1 ? 'document' : 'documents'));
            }

            if (form.dataset.paymentLocked === 'true') {
                status.classList.remove('is-ready');
                status.textContent = 'Pay the bidding documents fee at the BAC office first.';
                return;
            }

            const electronic = form.dataset.electronic === 'true';
            status.classList.toggle('is-ready', pending.length === 0);
            status.textContent = pending.length === 0
                ? (electronic ? 'Ready to submit officially.' : 'Ready to save as a draft record (not an official bid).')
                : 'Still needed: ' + pending.join(' and ') + '.';
        }

        // The financial PIN: 6 digits, typed twice. Forms without one (manual drafts) pass.
        function pinState(form) {
            const pins = form.querySelectorAll('[data-pin]');
            if (pins.length < 2) return { valid: true };
            const pin = pins[0].value;
            const confirm = pins[1].value;
            if (!/^[0-9]{6}$/.test(pin)) return { valid: false, message: 'a 6-digit PIN' };
            if (confirm !== pin) return { valid: false, message: confirm.length === 6 ? 'matching PINs' : 'the PIN confirmation' };
            return { valid: true };
        }

        document.querySelectorAll('[data-pin]').forEach(function (input) {
            input.addEventListener('input', function () {
                const digits = input.value.replace(/[^0-9]/g, '').slice(0, 6);
                if (digits !== input.value) input.value = digits;
                const form = input.form;
                const error = form ? form.querySelector('[data-pin-error]') : null;
                const pins = form ? form.querySelectorAll('[data-pin]') : [];
                if (error && pins.length === 2) {
                    error.textContent = pins[1].value.length === 6 && pins[0].value !== pins[1].value ? 'The two PINs do not match.' : '';
                }
                updateBidFormState(form);
            });
        });

        function updateBidFormState(form) {
            if (!form) return false;

            const amount = form.querySelector('[data-bid-amount]');
            // Only requirements the project marks as required block the button.
            const documents = Array.from(form.querySelectorAll('[data-upload-input][data-required-upload]'));
            const amountValid = amount ? validateBidAmount(amount, amount.dataset.touched === 'true') : false;
            const missingDocuments = documents.filter(function (input) {
                return !(input.files && input.files.length > 0);
            }).length;
            const documentsValid = missingDocuments === 0;
            // The server refuses the bid too until the BAC records the fee payment.
            const valid = form.dataset.paymentLocked !== 'true' && amountValid && documentsValid && pinState(form).valid;
            const submitButton = getBidSubmitButton(form);

            if (submitButton) submitButton.disabled = !valid;
            updateBidStatusHint(form, amountValid, missingDocuments);
            return valid;
        }
        // Keep one bid submission while guiding bidders through its three components.
        document.querySelectorAll('[data-bid-form]').forEach(function (form) {
            const nav = form.querySelector('.sb-steps');
            if (!nav) return;
            const panels = Array.from(form.querySelectorAll('[data-sb-panel]'));
            const tabs = Array.from(nav.querySelectorAll('[data-sb-go]'));
            const footer = form.closest('.bidder-submit-modal, [data-bid-dialog]');
            const back = footer ? footer.querySelector('[data-sb-prev]') : null;
            const next = footer ? footer.querySelector('[data-sb-next]') : null;
            const submit = footer ? footer.querySelector('[data-bid-submit]') : null;
            const uploaders = Array.from(form.querySelectorAll('[data-bulk-uploader]'));
            let activeStep = 1;

            const showStep = function (step, focus) {
                activeStep = Math.max(1, Math.min(3, Number(step) || 1));
                panels.forEach(function (panel) {
                    panel.hidden = Number(panel.dataset.sbPanel) !== activeStep;
                });
                uploaders.forEach(function (uploader) {
                    uploader.hidden = activeStep !== (uploader.dataset.bulkUploader === 'financial' ? 2 : 1);
                });
                tabs.forEach(function (tab) {
                    const active = Number(tab.dataset.sbGo) === activeStep;
                    tab.classList.toggle('is-current', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                    tab.tabIndex = active ? 0 : -1;
                    if (active && focus) tab.focus();
                });
                if (back) back.hidden = activeStep === 1;
                if (next) next.hidden = activeStep === 3;
                if (submit) submit.hidden = activeStep !== 3;
                const body = form.closest('.sb-body');
                if (body) body.scrollTop = 0;
            };

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () { showStep(tab.dataset.sbGo); });
            });
            if (back) back.addEventListener('click', function () { showStep(activeStep - 1); });
            if (next) next.addEventListener('click', function () { showStep(activeStep + 1); });
            form.addEventListener('bid:incomplete', function () {
                const requiredFiles = Array.from(form.querySelectorAll('[data-upload-input][data-required-upload]'));
                const firstMissing = requiredFiles.find(function (input) { return !(input.files && input.files.length); });
                showStep(firstMissing && firstMissing.closest('[data-sb-panel="1"]') ? 1 : 2);
            });
            showStep(1);
        });
        document.querySelectorAll('.bidder-bid-form, [data-bid-form]').forEach(function (form) {
            const amount = form.querySelector('[data-bid-amount]');
            if (!amount) return;

            amount.value = formatBidAmount(amount.value, true);

            amount.addEventListener('keydown', function (event) {
                const navigationKeys = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'Tab', 'Enter'];
                if (event.ctrlKey || event.metaKey || event.altKey || navigationKeys.indexOf(event.key) >= 0) return;
                if (/^[0-9]$/.test(event.key)) return;
                if (event.key === '.' && amount.value.indexOf('.') === -1) return;
                event.preventDefault();
            });

            amount.addEventListener('paste', function (event) {
                event.preventDefault();
                const pasted = event.clipboardData ? event.clipboardData.getData('text') : '';
                amount.value = formatBidAmount(pasted, false);
                amount.dataset.touched = 'true';
                amount.dispatchEvent(new Event('input', { bubbles: true }));
            });

            amount.addEventListener('input', function () {
                amount.dataset.touched = 'true';
                amount.value = formatBidAmount(amount.value, false);
                updateBidFormState(form);
            });

            amount.addEventListener('blur', function () {
                amount.dataset.touched = 'true';
                amount.value = formatBidAmount(amount.value, true);
                updateBidFormState(form);
            });

            form.addEventListener('submit', function (event) {
                amount.dataset.touched = 'true';

                if (!updateBidFormState(form)) {
                    event.preventDefault();
                    // The step wizard (submit-bid-modal) moves to the first incomplete step.
                    form.dispatchEvent(new CustomEvent('bid:incomplete'));
                    if (!normalizeBidAmount(amount.value) && amount.offsetParent) amount.focus();
                    return;
                }

                amount.value = normalizeBidAmount(amount.value);
                const submitButton = getBidSubmitButton(form);
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.setAttribute('aria-busy', 'true');
                }
            });

            updateBidFormState(form);
        });

        document.querySelectorAll('[data-upload-input]').forEach(function (input) {
            updateUploadState(input);
            input.addEventListener('change', function () {
                updateUploadState(input);
                updateBidFormState(input.form);
            });
        });

        document.querySelectorAll('[data-upload-change]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                const uploadBox = button.closest('.bidder-upload-box, [data-upload-box]');
                const input = uploadBox ? uploadBox.querySelector('[data-upload-input]') : null;
                if (input) input.click();
            });
        });

        document.querySelectorAll('[data-upload-remove]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                const uploadBox = button.closest('.bidder-upload-box, [data-upload-box]');
                const input = uploadBox ? uploadBox.querySelector('[data-upload-input]') : null;
                if (!input) return;

                input.value = '';
                updateUploadState(input);
                updateBidFormState(input.form);
            });
        });
    })();
</script>







