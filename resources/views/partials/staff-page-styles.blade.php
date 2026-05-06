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

    .staff-quick-actions {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 26px;
    }

    .staff-quick-action-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        min-height: 118px;
        padding: 20px 22px;
        background: #fff;
        border: 1px solid #dbe4f0;
        border-radius: 18px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    }

    .staff-quick-action-main {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
    }

    .staff-quick-action-icon {
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #fff7ed;
        color: #ea580c;
        font-size: 19px;
    }

    .staff-quick-action-copy h3 {
        margin: 0 0 5px;
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
    }

    .staff-quick-action-copy p {
        margin: 0;
        font-size: 12px;
        line-height: 1.5;
        color: #64748b;
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

    /* Old notification styles removed - using new notification-card-redesigned styles */

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

    .staff-dashboard-page .staff-stat-card {
        padding: 20px 22px;
        gap: 14px;
    }

    .staff-dashboard-page .staff-table-panel {
        margin-bottom: 0;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
    }

    .staff-dashboard-page .staff-table-header {
        padding: 18px 20px;
    }

    @media (max-width: 1024px) {
        .staff-dashboard-page .staff-stats-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .staff-quick-actions {
            grid-template-columns: 1fr;
        }

        .staff-report-grid {
            grid-template-columns: 1fr;
        }

        .staff-table-header,
        .staff-panel-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    @media (max-width: 768px) {
        .staff-dashboard-page .staff-stat-card {
            padding: 16px;
            gap: 12px;
        }

        .staff-dashboard-page .staff-stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            font-size: 18px;
        }

        .staff-dashboard-page .staff-stat-copy strong {
            font-size: 24px;
            margin-bottom: 4px;
        }

        .staff-dashboard-page .staff-stat-copy h3 {
            font-size: 13px;
            margin-bottom: 3px;
        }

        .staff-dashboard-page .staff-stat-copy p {
            font-size: 11px;
            line-height: 1.4;
        }

        .staff-dashboard-page .staff-table-header {
            flex-direction: row;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 16px;
        }
    }

    @media (max-width: 560px) {
        .staff-dashboard-page .staff-stats-grid {
            grid-template-columns: 1fr;
        }

        .staff-dashboard-page .staff-table-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .staff-dashboard-page .staff-view-all,
        .staff-dashboard-page .staff-action-button {
            width: 100%;
        }

        .staff-quick-action-card {
            flex-direction: column;
            align-items: stretch;
        }
    }

    /* ===== REDESIGNED NOTIFICATIONS UI ===== */

    .notifications-header-left h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
    }

    .notifications-header-subtitle {
        margin: 6px 0 0;
        font-size: 12px;
        color: #94a3b8;
    }

    .staff-notifications-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 22px;
        border-bottom: 1px solid #e2e8f0;
    }

    .staff-notification-clear-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 36px;
        padding: 0 16px;
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .staff-notification-clear-btn:hover {
        background: #eff6ff;
        border-color: #bfdbfe;
        color: #1e40af;
    }

    .notifications-list-redesigned {
        display: flex;
        flex-direction: column;
        gap: 0;
        padding: 0;
    }

    .notification-card-redesigned {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 22px;
        border-bottom: 1px solid #eef2f7;
        text-decoration: none !important;
        color: inherit !important;
        transition: all 0.2s ease;
        background: #ffffff;
        position: relative;
    }

    .notification-card-redesigned,
    .notification-card-redesigned:link,
    .notification-card-redesigned:visited,
    .notification-card-redesigned:hover,
    .notification-card-redesigned:active {
        text-decoration: none !important;
        color: inherit !important;
    }

    .notification-card-redesigned:last-child {
        border-bottom: none;
    }

    .notification-card-redesigned:hover {
        background: #f8fafc;
    }

    .notification-card-redesigned.notification-unread {
        background: #faf8f5;
        border-left: 4px solid #ea580c;
    }

    .notification-card-redesigned.notification-unread:hover {
        background: #fef3ed;
    }

    .notification-card-icon-wrapper {
        position: relative;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .notification-card-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .notification-icon-default {
        background: #e0e7ff;
        color: #4f46e5;
    }

    .notification-icon-success {
        background: #dcfce7;
        color: #16a34a;
    }

    .notification-icon-danger {
        background: #fee2e2;
        color: #dc2626;
    }

    .notification-icon-warning {
        background: #fef3c7;
        color: #d97706;
    }

    .notification-icon-info {
        background: #e0f2fe;
        color: #0284c7;
    }

    .notification-icon-primary {
        background: #fef3c7;
        color: #d97706;
    }

    .notification-unread-indicator {
        position: absolute;
        top: -4px;
        right: -4px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #ea580c;
        border: 2px solid #ffffff;
        box-shadow: 0 2px 4px rgba(234, 88, 12, 0.3);
    }

    .notification-card-content {
        flex: 1;
        min-width: 0;
    }

    .notification-card-content a,
    .notification-card-content a:link,
    .notification-card-content a:visited,
    .notification-card-content a:hover,
    .notification-card-content a:active {
        color: inherit !important;
        text-decoration: none !important;
    }

    .notification-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 4px;
    }

    .notification-card-title {
        margin: 0;
        font-size: 13px;
        font-weight: 600;
        color: #0f172a !important;
        line-height: 1.4;
    }

    .notification-card-redesigned.notification-unread .notification-card-title {
        font-weight: 700;
        color: #0f172a !important;
    }

    .notification-card-time {
        flex-shrink: 0;
        font-size: 11px;
        color: #cbd5e1;
        white-space: nowrap;
    }

    .notification-card-message {
        margin: 0;
        font-size: 12px;
        color: #64748b !important;
        line-height: 1.45;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .notification-card-redesigned.notification-unread .notification-card-message {
        color: #475569 !important;
    }

    .notification-card-redesigned.notification-read .notification-card-title,
    .notification-card-redesigned.notification-read .notification-card-message {
        opacity: 0.7;
    }

    .notification-card-action {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .notification-action-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #f1f5f9;
        color: #64748b;
        font-size: 13px;
        transition: all 0.2s ease;
    }

    .notification-card-redesigned:hover .notification-action-button {
        background: #e2e8f0;
        color: #334155;
    }

    .notification-card-redesigned.notification-unread:hover .notification-action-button {
        background: #fed7aa;
        color: #92400e;
    }

    .notifications-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        text-align: center;
        background: #ffffff;
    }

    .notifications-empty-icon {
        font-size: 48px;
        color: #cbd5e1;
        margin-bottom: 16px;
    }

    .notifications-empty-state p {
        margin: 0;
        font-size: 14px;
        color: #0f172a;
        font-weight: 500;
    }

    .notifications-empty-state p:first-of-type {
        margin-bottom: 4px;
    }

    .notifications-empty-subtitle {
        font-size: 12px;
        color: #94a3b8;
        font-weight: 400;
    }

    /* Responsive Design for Notifications */

    @media (max-width: 768px) {
        .staff-notifications-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
            padding: 16px 16px;
        }

        .notifications-header-left h2 {
            font-size: 16px;
        }

        .staff-notification-clear-btn {
            width: 100%;
            height: 40px;
        }

        .notification-card-redesigned {
            gap: 10px;
            padding: 12px 16px;
        }

        .notification-card-icon {
            width: 36px;
            height: 36px;
            font-size: 16px;
        }

        .notification-card-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
        }

        .notification-card-time {
            font-size: 10px;
            color: #cbd5e1;
        }

        .notification-card-title {
            font-size: 12px;
        }

        .notification-card-message {
            font-size: 11px;
        }

        .notification-card-action {
            min-width: auto;
        }

        .notification-action-button {
            width: 28px;
            height: 28px;
            font-size: 12px;
        }
    }

    @media (max-width: 480px) {
        .notification-card-redesigned {
            gap: 8px;
            padding: 10px 12px;
        }

        .notification-card-icon {
            width: 32px;
            height: 32px;
            font-size: 14px;
        }

        .notification-card-title {
            font-size: 12px;
        }

        .notification-card-message {
            font-size: 11px;
            -webkit-line-clamp: 1;
        }

        .notification-card-header {
            gap: 0;
        }

        .notification-card-time {
            display: none;
        }

        .notification-action-button {
            width: 26px;
            height: 26px;
            font-size: 11px;
        }
    }
</style>
