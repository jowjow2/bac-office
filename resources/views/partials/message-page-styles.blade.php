<style>
    .messenger-shell,
    .messenger-shell * {
        box-sizing: border-box;
    }

    .messenger-shell {
        min-width: 0;
        display: grid;
        gap: 12px;
    }

    .messenger-alert {
        margin: 0;
        padding: 12px 14px;
        border-radius: var(--ui-radius-lg);
        font-size: 13px;
        line-height: 1.45;
    }

    .messenger-alert-success {
        background: #ecfdf5;
        border: 1px solid #bbf7d0;
        color: #166534;
    }

    .messenger-alert-warning {
        background: #fff7ed;
        border: 1px solid #fed7aa;
        color: #9a3412;
    }

    .messenger-grid {
        position: relative;
        min-width: 0;
        height: clamp(560px, calc(100vh - 176px), 820px);
        min-height: 0;
        display: grid;
        grid-template-columns: minmax(286px, 370px) minmax(0, 1fr);
        overflow: hidden;
        border: 1px solid var(--ui-line);
        border-radius: var(--ui-radius-lg);
        background: #ffffff;
        box-shadow: 0 18px 46px rgba(27, 36, 32, 0.09);
    }

    .messenger-conversations {
        min-width: 0;
        min-height: 0;
        display: grid;
        grid-template-rows: auto minmax(0, 1fr);
        background: #ffffff;
        border-right: 1px solid var(--ui-line);
    }

    .messenger-conversation-top {
        display: grid;
        gap: 12px;
        padding: 16px;
        border-bottom: 1px solid var(--ui-line-soft);
        background: linear-gradient(180deg, #ffffff 0%, var(--ui-surface-2) 100%);
    }

    .messenger-tabs {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: 1fr;
        gap: 6px;
        padding: 4px;
        border: 1px solid var(--ui-line);
        border-radius: var(--ui-radius-lg);
        background: #ffffff;
    }

    .messenger-tab {
        min-width: 0;
        min-height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 0 10px;
        border-radius: var(--ui-radius-lg);
        color: var(--ui-ink-2);
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        transition: background 0.18s ease, color 0.18s ease, transform 0.18s ease;
    }

    .messenger-tab:hover {
        background: var(--ui-line-soft);
        color: var(--ui-ink);
    }

    .messenger-tab.is-active {
        background: var(--ui-ink);
        color: #ffffff;
        box-shadow: inset 0 -2px 0 #235e4c;
    }

    .messenger-search {
        position: relative;
        min-width: 0;
    }

    .messenger-search i {
        position: absolute;
        left: 14px;
        top: 50%;
        width: 16px;
        height: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--ui-subtle);
        font-size: 16px;
        line-height: 1;
        pointer-events: none;
        transform: translateY(-50%);
    }

    .messenger-search input {
        width: 100%;
        min-width: 0;
        height: 36px;
        padding: 0 14px 0 42px;
        border: 1px solid var(--ui-line);
        border-radius: var(--ui-radius);
        outline: none;
        background: var(--ui-surface-2);
        color: var(--ui-ink);
        font-size: 13px;
        box-sizing: border-box;
        transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
    }

    .messenger-search input:focus {
        border-color: var(--ui-primary);
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(29, 79, 64, 0.12);
    }

    .messenger-conversation-list {
        min-height: 0;
        overflow-y: auto;
        padding: 8px;
        overscroll-behavior: contain;
    }

    .messenger-conversation-list::-webkit-scrollbar,
    .messenger-chat-body::-webkit-scrollbar {
        width: 8px;
    }

    .messenger-conversation-list::-webkit-scrollbar-thumb,
    .messenger-chat-body::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: var(--ui-line-strong);
    }

    .messenger-thread {
        width: 100%;
        min-width: 0;
        display: grid;
        grid-template-columns: 46px minmax(0, 1fr) auto;
        align-items: center;
        gap: 11px;
        padding: 10px;
        border-radius: var(--ui-radius-lg);
        color: inherit;
        text-decoration: none;
        background: transparent;
        transition: background 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
    }

    .messenger-thread:hover {
        background: var(--ui-surface-2);
        transform: translateY(-1px);
    }

    .messenger-thread.is-active {
        background: var(--ui-ink);
        color: #ffffff;
        box-shadow: 0 10px 22px rgba(27, 36, 32, 0.16);
    }

    .messenger-avatar-wrap {
        position: relative;
        width: 46px;
        height: 46px;
    }

    .messenger-avatar {
        width: 46px;
        height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border-radius: 999px;
        background: var(--ui-line);
        color: var(--ui-ink);
        font-size: 14px;
        font-weight: 700;
        border: 1px solid rgba(27, 36, 32, 0.08);
    }

    .messenger-thread.is-active .messenger-avatar {
        background: var(--ui-primary);
        color: #ffffff;
        border-color: rgba(255, 255, 255, 0.24);
    }

    .messenger-presence-dot {
        position: absolute;
        right: 0;
        bottom: 0;
        width: 13px;
        height: 13px;
        border-radius: 999px;
        border: 2px solid #ffffff;
        background: var(--ui-subtle);
    }

    .messenger-presence-dot.is-online {
        background: #22c55e;
    }

    .messenger-thread.is-active .messenger-presence-dot {
        border-color: var(--ui-ink);
    }

    .messenger-thread-copy {
        min-width: 0;
        display: grid;
        gap: 4px;
    }

    .messenger-thread-top,
    .messenger-thread-bottom {
        min-width: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .messenger-thread-name {
        min-width: 0;
        overflow: hidden;
        color: var(--ui-ink);
        font-size: 13px;
        font-weight: 700;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .messenger-thread.is-active .messenger-thread-name {
        color: #ffffff;
    }

    .messenger-thread-time {
        flex: 0 0 auto;
        color: var(--ui-subtle);
        font-size: 10px;
        font-weight: 700;
        white-space: nowrap;
    }

    .messenger-thread.is-active .messenger-thread-time {
        color: var(--ui-line-strong);
    }

    .messenger-thread-preview {
        min-width: 0;
        margin: 0;
        overflow: hidden;
        color: var(--ui-muted);
        font-size: 12px;
        line-height: 1.35;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .messenger-thread.is-active .messenger-thread-preview {
        color: var(--ui-line);
    }

    .messenger-role-pill {
        flex: 0 0 auto;
        min-height: 20px;
        display: inline-flex;
        align-items: center;
        padding: 0 8px;
        border-radius: 999px;
        background: var(--ui-line-soft);
        color: var(--ui-ink-2);
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .messenger-thread.is-active .messenger-role-pill {
        background: rgba(255, 255, 255, 0.12);
        color: #ffffff;
    }

    .messenger-unread-badge {
        min-width: 20px;
        height: 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 6px;
        border-radius: 999px;
        background: #ef4444;
        color: #ffffff;
        font-size: 11px;
        font-weight: 700;
        box-shadow: 0 6px 14px rgba(239, 68, 68, 0.28);
    }

    .messenger-empty-list,
    .messenger-empty-chat,
    .messenger-no-messages {
        min-height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 24px;
        color: var(--ui-muted);
        text-align: center;
    }

    .messenger-empty-chat[hidden],
    .messenger-chat-header[hidden],
    .messenger-chat-body[hidden],
    .messenger-composer[hidden] {
        display: none !important;
    }

    .messenger-empty-list i,
    .messenger-empty-chat i,
    .messenger-no-messages i {
        width: 40px;
        height: 40px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: var(--ui-primary-soft);
        color: var(--ui-primary);
        font-size: 16px;
    }

    .messenger-empty-chat strong,
    .messenger-no-messages strong {
        color: var(--ui-ink);
        font-size: 15px;
    }

    .messenger-empty-chat p,
    .messenger-empty-list p {
        margin: 0;
        max-width: 260px;
        color: var(--ui-muted);
        font-size: 12px;
        line-height: 1.45;
    }

    .messenger-chat {
        min-width: 0;
        min-height: 0;
        display: grid;
        grid-template-rows: auto minmax(0, 1fr) auto auto;
        background: var(--ui-surface-2);
    }

    .messenger-chat.is-empty {
        grid-template-rows: 1fr;
    }

    .messenger-chat-header {
        min-width: 0;
        min-height: 70px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 14px 18px;
        border-bottom: 1px solid var(--ui-line);
        background: #ffffff;
    }

    .messenger-chat-person {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 11px;
    }

    .messenger-chat-avatar {
        position: relative;
        width: 46px;
        height: 46px;
        flex: 0 0 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: var(--ui-ink);
        color: #ffffff;
        font-size: 14px;
        font-weight: 700;
    }

    .messenger-chat-title-wrap {
        min-width: 0;
        display: grid;
        gap: 3px;
    }

    .messenger-chat-title {
        margin: 0;
        overflow: hidden;
        color: var(--ui-ink);
        font-size: 15px;
        font-weight: 700;
        line-height: 1.25;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .messenger-chat-subtitle,
    .messenger-chat-status {
        display: flex;
        align-items: center;
        gap: 6px;
        color: var(--ui-muted);
        font-size: 11px;
        font-weight: 700;
        line-height: 1.35;
    }

    .messenger-chat-status .messenger-status-dot {
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: var(--ui-subtle);
    }

    .messenger-chat-status.is-online .messenger-status-dot {
        background: #22c55e;
    }

    .messenger-mobile-back {
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        display: none;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 999px;
        background: var(--ui-line-soft);
        color: var(--ui-ink);
        cursor: pointer;
    }

    .messenger-chat-body {
        min-height: 0;
        overflow-y: auto;
        padding: 18px;
        background:
            linear-gradient(180deg, rgba(255, 255, 255, 0.92), rgba(248, 250, 252, 0.92)),
            radial-gradient(circle at top right, rgba(29, 79, 64, 0.10), transparent 34%);
        overscroll-behavior: contain;
    }

    .messenger-message-stack {
        min-height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        gap: 12px;
    }

    .messenger-message {
        width: 100%;
        display: flex;
        flex-direction: column;
    }

    .messenger-message.is-outgoing {
        align-items: flex-end;
    }

    .messenger-message.is-incoming {
        align-items: flex-start;
    }

    .messenger-message-author {
        margin: 0 5px 4px;
        color: var(--ui-muted);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: normal;
        text-transform: none;
    }

    .messenger-message-bubble {
        max-width: min(70%, 620px);
        padding: 10px 13px;
        border-radius: 18px;
        border: 1px solid var(--ui-line);
        background: #ffffff;
        color: var(--ui-ink);
        box-shadow: 0 8px 18px rgba(27, 36, 32, 0.06);
    }

    .messenger-message.is-incoming .messenger-message-bubble {
        border-bottom-left-radius: 6px;
    }

    .messenger-message.is-outgoing .messenger-message-bubble {
        border-color: var(--ui-primary);
        border-bottom-right-radius: 6px;
        background: var(--ui-primary);
        color: #ffffff;
        box-shadow: 0 10px 22px rgba(29, 79, 64, 0.20);
    }

    .messenger-message-text {
        margin: 0;
        font-size: 14px;
        line-height: 1.55;
        overflow-wrap: anywhere;
        white-space: pre-wrap;
    }

    .messenger-message-meta,
    .messenger-message-state {
        margin: 4px 6px 0;
        color: var(--ui-muted);
        font-size: 11px;
        line-height: 1.35;
    }

    .messenger-message.is-outgoing .messenger-message-state {
        text-align: right;
    }

    .messenger-attachment {
        margin-top: 8px;
    }

    .messenger-attachment-image {
        display: block;
        width: min(280px, 100%);
        max-height: 250px;
        border-radius: var(--ui-radius-lg);
        object-fit: cover;
        border: 1px solid rgba(27, 36, 32, 0.12);
    }

    .messenger-file-link {
        min-width: min(300px, 100%);
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        border-radius: var(--ui-radius-lg);
        background: rgba(27, 36, 32, 0.08);
        color: inherit;
        text-decoration: none;
    }

    .messenger-file-icon,
    .messenger-attachment-icon {
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--ui-radius-lg);
        background: var(--ui-ink);
        color: #6fae94;
    }

    .messenger-file-info,
    .messenger-attachment-info {
        min-width: 0;
        display: grid;
        gap: 2px;
    }

    .messenger-file-name,
    .messenger-attachment-name {
        overflow: hidden;
        font-size: 12px;
        font-weight: 700;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .messenger-file-size,
    .messenger-attachment-size {
        color: var(--ui-muted);
        font-size: 11px;
    }

    .messenger-typing {
        min-height: 32px;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 0 18px 8px;
        background: var(--ui-surface-2);
        color: var(--ui-muted);
        font-size: 12px;
        font-weight: 700;
    }

    .messenger-typing[hidden] {
        display: none;
    }

    .messenger-typing-dots {
        display: inline-flex;
        gap: 3px;
    }

    .messenger-typing-dots span {
        width: 5px;
        height: 5px;
        border-radius: 999px;
        background: var(--ui-primary);
        animation: messengerTypingPulse 1s infinite ease-in-out;
    }

    .messenger-typing-dots span:nth-child(2) {
        animation-delay: 0.12s;
    }

    .messenger-typing-dots span:nth-child(3) {
        animation-delay: 0.24s;
    }

    .messenger-composer {
        padding: 12px 14px 14px;
        border-top: 1px solid var(--ui-line);
        background: #ffffff;
    }

    .messenger-attachment-preview {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
        padding: 9px 10px;
        border-radius: var(--ui-radius-lg);
        background: var(--ui-surface-2);
        border: 1px solid var(--ui-line);
    }

    .messenger-attachment-preview[hidden] {
        display: none;
    }

    .messenger-attachment-thumb {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        object-fit: cover;
        border: 1px solid var(--ui-line);
    }

    .messenger-remove-attachment {
        width: 30px;
        height: 30px;
        margin-left: auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 999px;
        background: #ffffff;
        color: var(--ui-muted);
        cursor: pointer;
    }

    .messenger-input-row {
        display: flex;
        align-items: flex-end;
        gap: 9px;
    }

    .messenger-attach-button,
    .messenger-send-button {
        width: 42px;
        height: 36px;
        flex: 0 0 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 999px;
        cursor: pointer;
    }

    .messenger-attach-button {
        background: var(--ui-line-soft);
        color: var(--ui-ink);
    }

    .messenger-attach-button:hover {
        background: var(--ui-ink);
        color: #6fae94;
    }

    .messenger-file-input {
        display: none;
    }

    .messenger-input {
        min-width: 0;
        min-height: 36px;
        max-height: 136px;
        flex: 1;
        resize: none;
        padding: 11px 14px;
        border: 1px solid var(--ui-line);
        border-radius: 20px;
        outline: none;
        background: var(--ui-surface-2);
        color: var(--ui-ink);
        font: inherit;
        font-size: 13px;
        line-height: 1.45;
    }

    .messenger-input:focus {
        border-color: var(--ui-primary);
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(29, 79, 64, 0.12);
    }

    .messenger-send-button {
        background: var(--ui-primary);
        color: #ffffff;
        box-shadow: none;
    }

    .messenger-send-button:disabled {
        background: var(--ui-line-strong);
        color: var(--ui-muted);
        cursor: not-allowed;
        box-shadow: none;
    }

    @keyframes messengerTypingPulse {
        0%, 80%, 100% { opacity: 0.35; transform: translateY(0); }
        40% { opacity: 1; transform: translateY(-2px); }
    }

    @media (max-width: 1100px) {
        .messenger-grid {
            grid-template-columns: minmax(264px, 330px) minmax(0, 1fr);
        }

        .messenger-message-bubble {
            max-width: 78%;
        }
    }

    @media (max-width: 820px) {
        .messenger-grid {
            height: calc(100dvh - 112px);
            min-height: 520px;
            display: block;
            border-radius: var(--ui-radius-lg);
        }

        .messenger-conversations {
            height: 100%;
            border-right: 0;
        }

        .messenger-chat {
            position: absolute;
            inset: 0;
            z-index: 3;
            transform: translateX(100%);
            transition: transform 0.24s ease;
        }

        .messenger-grid.is-chat-open .messenger-chat {
            transform: translateX(0);
        }

        .messenger-mobile-back {
            display: inline-flex;
        }

        .messenger-chat-header {
            min-height: 64px;
            padding: 12px;
        }

        .messenger-chat-body {
            padding: 14px 12px;
        }

        .messenger-message-bubble {
            max-width: 88%;
        }
    }

    @media (max-width: 560px) {
        .messenger-shell {
            gap: 10px;
        }

        .messenger-grid {
            height: calc(100dvh - 96px);
            border-left: 0;
            border-right: 0;
            border-radius: 0;
            /* Full-bleed: cancel exactly the gutter dashboard-content applies
               (--bac-dash-gutter), instead of a hardcoded 16px that silently
               overshoots whenever that padding changes. */
            margin-inline: calc(-1 * var(--bac-dash-gutter, 16px));
        }

        .messenger-conversation-top {
            padding: 12px;
        }

        .messenger-thread {
            grid-template-columns: 42px minmax(0, 1fr) auto;
            gap: 9px;
            padding: 9px;
        }

        .messenger-avatar-wrap,
        .messenger-avatar {
            width: 42px;
            height: 42px;
        }

        .messenger-message-bubble {
            max-width: 92%;
        }

        .messenger-input-row {
            gap: 7px;
        }

        .messenger-attach-button,
        .messenger-send-button {
            width: 38px;
            height: 36px;
            flex-basis: 38px;
        }
    }

    /* Cloud AI messenger polish */
    .admin-messages-page .dashboard-home-content,
    .staff-messages-page .dashboard-home-content,
    .bidder-messages-page .dashboard-home-content {
        background:
            linear-gradient(180deg, var(--ui-surface-2) 0%, var(--ui-line-soft) 100%) !important;
    }

    .messenger-shell {
        --msg-ink: var(--ui-ink);
        --msg-muted: var(--ui-muted);
        --msg-line: var(--ui-line);
        --msg-panel: rgba(255, 255, 255, 0.92);
        --msg-cloud: var(--ui-primary);
        --msg-cloud-dark: #0f3f91;
        --msg-soft: var(--ui-info-soft);
        gap: 14px;
    }

    .messenger-grid {
        height: clamp(620px, calc(100vh - 178px), 860px);
        grid-template-columns: minmax(312px, 438px) minmax(0, 1fr);
        border: 1px solid rgba(148, 163, 184, 0.34);
        border-radius: 8px;
        background:
            linear-gradient(90deg, rgba(255,255,255,0.96) 0%, rgba(255,255,255,0.92) 34%, rgba(244,249,255,0.94) 100%);
        box-shadow: 0 24px 60px rgba(27, 36, 32, 0.12);
    }

    .messenger-grid::before {
        content: "";
        position: absolute;
        inset: 0;
        pointer-events: none;
        background-image:
            linear-gradient(rgba(29, 79, 64, 0.055) 1px, transparent 1px),
            linear-gradient(90deg, rgba(29, 79, 64, 0.045) 1px, transparent 1px);
        background-size: 42px 42px;
        mask-image: linear-gradient(90deg, transparent 0%, #000 42%, #000 100%);
        opacity: 0.8;
    }

    .messenger-conversations,
    .messenger-chat {
        position: relative;
        z-index: 1;
    }

    .messenger-conversations {
        background: rgba(255, 255, 255, 0.9);
        border-right: 1px solid var(--msg-line);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
    }

    .messenger-conversation-top {
        gap: 14px;
        padding: 20px;
        background:
            linear-gradient(180deg, rgba(255,255,255,0.98), rgba(247,250,255,0.94));
        border-bottom: 1px solid var(--msg-line);
    }

    .messenger-tabs {
        gap: 8px;
        padding: 5px;
        border-color: rgba(29, 79, 64, 0.16);
        border-radius: 8px;
        background: var(--ui-info-soft);
    }

    .messenger-tab {
        min-height: 40px;
        border-radius: 6px;
        color: var(--ui-ink-2);
        font-size: 13px;
        letter-spacing: 0;
    }

    .messenger-tab:hover {
        background: rgba(255, 255, 255, 0.78);
        color: var(--msg-cloud-dark);
    }

    .messenger-tab.is-active {
        background: var(--ui-ink);
        color: #ffffff;
        box-shadow: inset 0 -3px 0 var(--msg-cloud), 0 10px 24px rgba(27, 36, 32, 0.14);
    }

    .messenger-conversation-list {
        padding: 10px;
    }

    .messenger-thread {
        grid-template-columns: 44px minmax(0, 1fr) auto;
        gap: 12px;
        padding: 12px;
        border: 1px solid transparent;
        border-radius: 8px;
    }

    .messenger-thread:hover {
        background: var(--ui-surface-2);
        border-color: rgba(29, 79, 64, 0.12);
        box-shadow: 0 8px 22px rgba(27, 36, 32, 0.06);
    }

    .messenger-thread.is-active {
        background: linear-gradient(135deg, var(--ui-ink) 0%, var(--ui-primary-hover) 100%);
        border-color: rgba(29, 79, 64, 0.32);
        box-shadow: 0 14px 30px rgba(27, 36, 32, 0.2);
    }

    .messenger-avatar-wrap,
    .messenger-avatar {
        width: 44px;
        height: 44px;
    }

    .messenger-avatar,
    .messenger-chat-avatar {
        background: linear-gradient(135deg, var(--ui-info-soft), var(--ui-primary-line));
        border: 1px solid rgba(29, 79, 64, 0.22);
        color: #0f3f91;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.75);
    }

    .messenger-thread.is-active .messenger-avatar,
    .messenger-chat-avatar {
        background: linear-gradient(135deg, var(--ui-primary), var(--ui-primary));
        color: #ffffff;
        border-color: rgba(255, 255, 255, 0.35);
    }

    .messenger-thread-name,
    .messenger-chat-title {
        color: var(--msg-ink);
        font-weight: 700;
    }

    .messenger-thread-preview,
    .messenger-thread-time,
    .messenger-chat-subtitle,
    .messenger-chat-status {
        color: var(--msg-muted);
    }

    .messenger-role-pill {
        border-radius: 6px;
        background: var(--ui-line-soft);
        color: var(--ui-ink-2);
        letter-spacing: 0;
    }

    .messenger-unread-badge {
        background: #dc2626;
        box-shadow: 0 8px 18px rgba(220, 38, 38, 0.26);
    }

    .messenger-chat {
        background: var(--ui-surface-2);
    }

    .messenger-chat-header {
        min-height: 78px;
        padding: 16px 22px;
        border-bottom: 1px solid var(--msg-line);
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }

    .messenger-chat-body {
        padding: 24px;
        background:
            linear-gradient(180deg, rgba(248, 251, 255, 0.96), rgba(241, 247, 253, 0.96)),
            linear-gradient(rgba(29, 79, 64, 0.055) 1px, transparent 1px),
            linear-gradient(90deg, rgba(29, 79, 64, 0.045) 1px, transparent 1px);
        background-size: auto, 40px 40px, 40px 40px;
    }

    .messenger-message-stack {
        gap: 14px;
    }

    .messenger-message-author {
        color: var(--ui-muted);
        letter-spacing: 0;
    }

    .messenger-message-bubble {
        max-width: min(68%, 640px);
        padding: 12px 14px;
        border-radius: 8px;
        border-color: rgba(148, 163, 184, 0.28);
        background: rgba(255, 255, 255, 0.96);
        color: var(--msg-ink);
        box-shadow: 0 10px 26px rgba(27, 36, 32, 0.07);
    }

    .messenger-message.is-incoming .messenger-message-bubble,
    .messenger-message.is-outgoing .messenger-message-bubble {
        border-bottom-left-radius: 8px;
        border-bottom-right-radius: 8px;
    }

    .messenger-message.is-outgoing .messenger-message-bubble {
        border-color: rgba(29, 79, 64, 0.36);
        background: var(--ui-primary);
        color: #ffffff;
        box-shadow: 0 14px 28px rgba(29, 79, 64, 0.22);
    }

    .messenger-message-meta,
    .messenger-message-state {
        color: var(--ui-muted);
    }

    .messenger-typing {
        background: var(--ui-surface-2);
        color: var(--ui-ink-2);
    }

    .messenger-typing-dots span {
        background: var(--msg-cloud);
    }

    .messenger-composer {
        padding: 14px 16px 16px;
        border-top: 1px solid var(--msg-line);
        background: rgba(255, 255, 255, 0.94);
    }

    .messenger-input-row {
        align-items: flex-end;
        gap: 10px;
    }

    .messenger-input {
        min-height: 36px;
        border-radius: 8px;
        border-color: rgba(148, 163, 184, 0.42);
        background: #ffffff;
        box-shadow: inset 0 1px 2px rgba(27, 36, 32, 0.04);
    }

    .messenger-input:focus {
        border-color: var(--msg-cloud);
        box-shadow: 0 0 0 4px rgba(29, 79, 64, 0.12);
    }

    .messenger-attach-button,
    .messenger-send-button,
    .messenger-mobile-back,
    .messenger-remove-attachment {
        border-radius: 8px;
    }

    .messenger-attach-button {
        background: var(--ui-line-soft);
        color: var(--ui-ink-2);
    }

    .messenger-attach-button:hover {
        background: var(--ui-primary-soft);
        color: var(--msg-cloud-dark);
    }

    .messenger-send-button {
        background: var(--ui-primary);
        color: #ffffff;
        box-shadow: none;
    }

    .messenger-send-button:hover:not(:disabled) {
        filter: brightness(0.96);
    }

    .messenger-empty-list,
    .messenger-empty-chat,
    .messenger-no-messages {
        color: var(--ui-muted);
    }

    .messenger-empty-chat strong,
    .messenger-no-messages strong,
    .messenger-empty-list strong {
        color: var(--msg-ink);
        font-size: 15px;
        font-weight: 700;
    }

    .messenger-attachment-preview,
    .messenger-file-link {
        border-radius: 8px;
        border: 1px solid rgba(148, 163, 184, 0.3);
        background: var(--ui-surface-2);
    }

    .messenger-file-icon,
    .messenger-attachment-icon {
        border-radius: 8px;
        background: var(--ui-info-soft);
        color: #0f3f91;
    }

    @media (max-width: 1100px) {
        .messenger-grid {
            grid-template-columns: minmax(286px, 370px) minmax(0, 1fr);
        }

        .messenger-message-bubble {
            max-width: 78%;
        }
    }

    @media (max-width: 820px) {
        .messenger-grid {
            min-height: 540px;
            border-radius: 8px;
        }

        /* The polish block above resets .messenger-chat to position: relative,
           which drops the chat below the conversation list on mobile. Keep it
           as the slide-over panel from the base mobile layout. */
        .messenger-chat {
            position: absolute;
            inset: 0;
            z-index: 3;
        }

        .messenger-chat-header,
        .messenger-chat-body,
        .messenger-composer {
            padding-inline: 14px;
        }
    }

    @media (max-width: 560px) {
        .messenger-grid {
            border-radius: 0;
        }
    }

    /* Bidder chat conversation polish */
    .bidder-messages-page .messenger-chat-body {
        padding: 24px 32px 36px;
    }

    .bidder-messages-page .messenger-message.is-outgoing .messenger-message-author,
    .bidder-messages-page .messenger-message.is-outgoing .messenger-message-meta,
    .bidder-messages-page .messenger-message.is-outgoing .messenger-message-state {
        align-self: flex-end;
        text-align: right;
    }

    .bidder-messages-page .messenger-message.is-outgoing .messenger-message-author {
        margin-right: 4px;
        margin-bottom: 4px;
        color: var(--ui-muted);
        font-size: 10px;
        letter-spacing: 0.08em;
    }

    .bidder-messages-page .messenger-message.is-outgoing .messenger-message-bubble {
        width: fit-content;
        max-width: 65%;
        padding: 10px 14px;
        border: 0;
        border-radius: var(--ui-radius);
        background: var(--ui-primary);
        color: #FFFFFF;
        box-shadow: 0 8px 18px rgba(29, 79, 64, 0.18);
    }

    .bidder-messages-page .messenger-message.is-outgoing .messenger-message-text {
        color: #FFFFFF !important;
        font-size: 14px;
        line-height: 1.4;
    }

    .bidder-messages-page .messenger-message.is-outgoing .messenger-message-meta,
    .bidder-messages-page .messenger-message.is-outgoing .messenger-message-state {
        margin-top: 3px;
        margin-right: 6px;
        color: var(--ui-muted);
        font-size: 10px;
        line-height: 1.2;
    }

    .bidder-messages-page .messenger-message.is-outgoing .messenger-message-state {
        margin-top: 1px;
    }

    .bidder-messages-page .messenger-message.is-incoming .messenger-message-bubble {
        max-width: 65%;
        color: var(--ui-ink);
        background: var(--ui-line-soft);
        border-color: var(--ui-line);
    }

    .bidder-messages-page .messenger-message.is-incoming .messenger-message-text {
        color: var(--ui-ink);
    }

    @media (max-width: 820px) {
        .bidder-messages-page .messenger-chat-body {
            padding-inline: 24px;
        }

        .bidder-messages-page .messenger-message.is-outgoing .messenger-message-bubble,
        .bidder-messages-page .messenger-message.is-incoming .messenger-message-bubble {
            max-width: 78%;
        }
    }

    @media (max-width: 560px) {
        .bidder-messages-page .messenger-chat-body {
            padding-inline: 18px;
            padding-bottom: 32px;
        }

        .bidder-messages-page .messenger-message.is-outgoing .messenger-message-bubble,
        .bidder-messages-page .messenger-message.is-incoming .messenger-message-bubble {
            max-width: 82%;
        }
    }
</style>