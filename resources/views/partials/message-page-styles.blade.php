<style>
    .staff-messages-shell {
        display: grid;
        gap: 16px;
    }

    .staff-message-tabs {
        display: inline-flex;
        width: fit-content;
        gap: 6px;
        padding: 5px;
        border: 1px solid #dbe4f0;
        border-radius: 14px;
        background: #ffffff;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
    }

    .staff-message-tab {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 36px;
        padding: 0 14px;
        border-radius: 10px;
        color: #475569;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
    }

    .staff-message-tab.is-active {
        background: #fff7ed;
        color: #c2410c;
    }

    .staff-messages-panel {
        background: #ffffff;
        border: 1px solid #dbe4f0;
        border-radius: 18px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        overflow: hidden;
    }

    .staff-messages-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 20px;
        border-bottom: 1px solid #e2e8f0;
    }

    .staff-messages-panel-title h2 {
        margin: 0;
        color: #0f172a;
        font-size: 15px;
        font-weight: 800;
    }

    .staff-messages-panel-title p {
        margin: 4px 0 0;
        color: #94a3b8;
        font-size: 12px;
    }

    .staff-message-search {
        position: relative;
        width: min(320px, 100%);
    }

    .staff-message-search i {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 12px;
    }

    .staff-message-search input {
        width: 100%;
        height: 40px;
        padding: 0 14px 0 35px;
        border: 1px solid #dbe4f0;
        border-radius: 12px;
        color: #0f172a;
        background: #f8fafc;
        font-size: 13px;
        outline: none;
    }

    .staff-message-search input:focus {
        border-color: #f97316;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
    }

    .staff-message-list {
        display: grid;
    }

    .staff-message-contact {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        align-items: center;
        gap: 14px;
        padding: 16px 20px;
        border-bottom: 1px solid #eef2f7;
        color: inherit;
        text-decoration: none;
        background: #ffffff;
        transition: background 0.18s ease;
    }

    .staff-message-contact:hover,
    .staff-message-contact.is-active {
        background: #fff7ed;
    }

    .staff-message-contact:last-child {
        border-bottom: 0;
    }

    .staff-message-avatar-wrap {
        position: relative;
    }

    .staff-message-avatar {
        width: 48px;
        height: 48px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #e2e8f0;
        color: #334155;
        font-size: 15px;
        font-weight: 800;
    }

    .staff-message-status-dot {
        position: absolute;
        right: 1px;
        bottom: 1px;
        width: 12px;
        height: 12px;
        border-radius: 999px;
        border: 2px solid #ffffff;
        background: #94a3b8;
    }

    .staff-message-status-dot.is-online {
        background: #22c55e;
    }

    .staff-message-copy {
        min-width: 0;
    }

    .staff-message-contact-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 5px;
    }

    .staff-message-name {
        color: #0f172a;
        font-size: 14px;
        font-weight: 800;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .staff-message-time {
        flex: 0 0 auto;
        color: #94a3b8;
        font-size: 11px;
    }

    .staff-message-preview {
        margin: 0 0 8px;
        color: #64748b;
        font-size: 12px;
        line-height: 1.5;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .staff-message-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .staff-message-pill {
        display: inline-flex;
        align-items: center;
        min-height: 22px;
        padding: 0 9px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #475569;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .staff-message-pill.is-new {
        background: #ffedd5;
        color: #c2410c;
    }

    .staff-message-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 36px;
        padding: 0 14px;
        border-radius: 10px;
        border: 1px solid #fed7aa;
        background: #fff7ed;
        color: #c2410c;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .staff-message-empty {
        padding: 44px 20px;
        color: #94a3b8;
        font-size: 13px;
        line-height: 1.6;
        text-align: center;
    }

    .staff-message-empty i {
        display: block;
        margin-bottom: 12px;
        color: #cbd5e1;
        font-size: 34px;
    }

    @media (max-width: 760px) {
        .staff-messages-panel-header {
            flex-direction: column;
            align-items: stretch;
        }

        .staff-message-tabs,
        .staff-message-tab {
            width: 100%;
        }

        .staff-message-tab {
            justify-content: center;
        }

        .staff-message-contact {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .staff-message-action {
            grid-column: 1 / -1;
            width: 100%;
        }
    }

    @media (max-width: 520px) {
        .staff-message-contact {
            padding: 14px 16px;
            gap: 12px;
        }

        .staff-message-avatar {
            width: 42px;
            height: 42px;
        }
    }
</style>
