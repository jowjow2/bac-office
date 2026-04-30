<style>
    .staff-dashboard {
        font-family: 'Inter', sans-serif;
    }

    .staff-dashboard .dashboard-home-intro,
    .staff-page-intro {
        margin-bottom: 24px;
    }

    .staff-dashboard .dashboard-home-title,
    .staff-page-title {
        margin: 0 0 8px;
        font-size: 24px;
        font-weight: 700;
        color: #0f172a;
    }

    .staff-dashboard .dashboard-home-subtitle,
    .staff-page-subtitle {
        margin: 0;
        font-size: 13px;
        color: #94a3b8;
    }

    .staff-stats-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 26px;
    }

    .staff-stat-card {
        display: flex;
        align-items: center;
        gap: 16px;
        background: #fff;
        border: 1px solid #dbe4f0;
        border-radius: 18px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        padding: 22px 24px;
    }

    .staff-stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .staff-stat-icon.blue {
        background: #dbeafe;
        color: #2563eb;
    }

    .staff-stat-icon.gold {
        background: #fef3c7;
        color: #d97706;
    }

    .staff-stat-icon.green {
        background: #dcfce7;
        color: #15803d;
    }

    .staff-stat-copy strong {
        display: block;
        font-size: 21px;
        line-height: 1;
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 6px;
    }

    .staff-stat-copy h3 {
        margin: 0 0 4px;
        font-size: 12px;
        font-weight: 500;
        color: #64748b;
    }

    .staff-stat-copy p {
        margin: 0;
        font-size: 10px;
        color: #94a3b8;
    }

    .staff-table-panel,
    .staff-panel {
        background: #fff;
        border: 1px solid #dbe4f0;
        border-radius: 18px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        overflow: hidden;
        margin-bottom: 22px;
    }

    .staff-table-header,
    .staff-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
    }

    .staff-table-header h2,
    .staff-panel-header h2 {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
    }

    .staff-panel-header p {
        margin: 4px 0 0;
        font-size: 12px;
        color: #94a3b8;
    }

    .staff-view-all,
    .staff-action-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 90px;
        height: 34px;
        padding: 0 14px;
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #1e3a8a;
        font-size: 12px;
        font-weight: 500;
        text-decoration: none;
    }

    .staff-table-wrap {
        overflow-x: auto;
    }

    .staff-table {
        width: 100%;
        border-collapse: collapse;
    }

    .staff-table thead th {
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

    .staff-table tbody td {
        padding: 16px 20px;
        border-bottom: 1px solid #eef2f7;
        font-size: 12px;
        color: #0f172a;
        vertical-align: middle;
    }

    .staff-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .staff-project-title {
        font-weight: 600;
        color: #0f172a;
    }

    .staff-status-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 500;
        text-transform: lowercase;
    }

    .staff-status-pill.open {
        background: #dcfce7;
        color: #15803d;
    }

    .staff-status-pill.closed {
        background: #e2e8f0;
        color: #475569;
    }

    .staff-status-pill.awarded,
    .staff-status-pill.approved {
        background: #fef3c7;
        color: #b45309;
    }

    .staff-status-pill.pending {
        background: #fff7ed;
        color: #c2410c;
    }

    .staff-status-pill.rejected {
        background: #fee2e2;
        color: #b91c1c;
    }

    .staff-empty-cell,
    .staff-empty-state {
        text-align: center;
        color: #94a3b8;
        font-size: 12px;
        padding: 28px 20px;
    }

    .staff-inline-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .staff-inline-actions form {
        margin: 0;
    }

    .staff-document-cell {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .staff-note-input,
    .staff-select {
        width: 100%;
        min-height: 34px;
        padding: 8px 10px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 12px;
        color: #0f172a;
        background: #fff;
    }

    .staff-note-input {
        min-width: 150px;
    }

    .staff-button-primary,
    .staff-button-secondary,
    .staff-button-success,
    .staff-button-danger,
    .staff-document-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 0 14px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
    }

    .staff-button-primary {
        background: #1d4ed8;
        border: 1px solid #1d4ed8;
        color: #fff;
    }

    .staff-button-secondary {
        background: #fff;
        border: 1px solid #cbd5e1;
        color: #334155;
    }

    .staff-button-success {
        background: #16a34a;
        border: 1px solid #16a34a;
        color: #fff;
    }

    .staff-button-danger {
        background: #dc2626;
        border: 1px solid #dc2626;
        color: #fff;
    }

    .staff-document-link {
        min-height: 30px;
        padding: 0 12px;
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        color: #1d4ed8;
    }

    .staff-panel-body {
        padding: 18px 20px 20px;
    }

    .staff-report-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }

    .staff-report-card {
        padding: 16px;
        border-radius: 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .staff-report-card span {
        display: block;
        font-size: 11px;
        color: #94a3b8;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .staff-report-card strong {
        display: block;
        font-size: 18px;
        color: #0f172a;
    }

    .staff-notification-list {
        display: flex;
        flex-direction: column;
    }

    .staff-notification-item {
        padding: 16px 20px;
        border-bottom: 1px solid #eef2f7;
    }

    .staff-notification-item:last-child {
        border-bottom: 0;
    }

    .staff-notification-item strong {
        display: block;
        font-size: 13px;
        color: #0f172a;
        margin-bottom: 4px;
    }

    .staff-notification-item p {
        margin: 0;
        font-size: 12px;
        color: #64748b;
    }

    .staff-notifications-panel {
        border-radius: 18px;
    }

    .staff-notifications-header {
        padding: 18px 22px;
    }

    .staff-notification-clear {
        border: 0;
        background: transparent;
        color: #64748b;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        padding: 0;
    }

    .staff-notification-list-compact {
        padding: 0 22px;
    }

    .staff-notification-row {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px 0;
    }

    .staff-notification-dot {
        width: 9px;
        height: 9px;
        border-radius: 999px;
        background: #cbd5e1;
        flex-shrink: 0;
        margin-top: 6px;
    }

    .staff-notification-copy {
        min-width: 0;
    }

    .staff-notification-copy strong {
        margin-bottom: 6px;
        font-size: 13px;
        font-weight: 500;
        line-height: 1.45;
    }

    .staff-notification-copy p {
        font-size: 12px;
        color: #94a3b8;
    }

    .assignment-alert {
        margin-bottom: 18px;
        padding: 14px 16px;
        border-radius: 14px;
        font-size: 12px;
    }

    .assignment-alert-success {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }

    .assignment-alert-error {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }

    .assignment-alert-list {
        margin: 0;
        padding-left: 18px;
    }

    @media (max-width: 1024px) {
        .staff-stats-grid,
        .staff-report-grid {
            grid-template-columns: 1fr;
        }

        .staff-table-header,
        .staff-panel-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>
