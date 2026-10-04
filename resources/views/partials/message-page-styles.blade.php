{{--
    Messages (admin, staff, bidder): conversation list + chat, one shared look.
    Markup: partials/messages/*. The live-chat script in messenger.blade.php
    relies on the data-* hooks and on .messenger-grid.is-chat-open (phones).
--}}
<style>
    .messenger-shell, .messenger-shell * { box-sizing: border-box; }
    .messenger-shell {
        --m-ink: var(--ui-ink, #1b2420);
        --m-ink-2: var(--ui-ink-2, #3a4641);
        --m-muted: var(--ui-muted, #5b6761);
        --m-subtle: var(--ui-subtle, #7a8680);
        --m-line: var(--ui-line, #e4e0d6);
        --m-soft: var(--ui-page, #f6f4ee);
        --m-primary: var(--ui-primary, #1d4f40);
        --m-primary-soft: var(--ui-primary-soft, #e9f2ed);
        --m-online: #22a06b;
        display: grid;
        gap: 12px;
        min-width: 0;
        color: var(--m-ink);
        font-family: var(--ui-font, inherit);
    }

    .messenger-alert { margin: 0; padding: 11px 14px; border-radius: 10px; font-size: 13px; line-height: 1.45; }
    .messenger-alert-success { border: 1px solid #bbf7d0; background: #ecfdf5; color: #166534; }
    .messenger-alert-warning { border: 1px solid #fed7aa; background: #fff7ed; color: #9a3412; }

    .messenger-grid {
        position: relative;
        display: grid;
        grid-template-columns: minmax(280px, 360px) minmax(0, 1fr);
        height: clamp(540px, calc(100vh - 170px), 860px);
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--m-line);
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 10px 30px rgba(27, 36, 32, .06);
    }

    /* ---------- Conversation list ---------- */
    .messenger-conversations { display: flex; flex-direction: column; min-width: 0; min-height: 0; border-right: 1px solid var(--m-line); background: #fff; }
    .messenger-conversation-top { display: grid; gap: 10px; padding: 16px 16px 12px; border-bottom: 1px solid var(--m-line); }
    .messenger-tabs { display: grid; grid-auto-columns: minmax(0, 1fr); grid-auto-flow: column; gap: 4px; padding: 4px; border-radius: 10px; background: var(--m-soft); }
    .messenger-tab { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 34px; padding: 0 12px; border-radius: 8px; color: var(--m-muted); font-size: 13px; font-weight: 600; text-decoration: none; transition: background .15s ease, color .15s ease; }
    .messenger-tab:hover { color: var(--m-ink); }
    .messenger-tab.is-active { background: #fff; color: var(--m-primary); box-shadow: 0 1px 3px rgba(27, 36, 32, .12); }
    .messenger-search { position: relative; display: block; }
    .messenger-search i { position: absolute; top: 50%; left: 12px; color: var(--m-subtle); font-size: 13px; transform: translateY(-50%); pointer-events: none; }
    .messenger-search input { width: 100%; height: 38px; padding: 0 12px 0 34px; border: 1px solid var(--m-line); border-radius: 10px; outline: none; background: var(--m-soft); color: var(--m-ink); font: inherit; font-size: 13px; }
    .messenger-search input:focus { border-color: var(--m-primary); background: #fff; box-shadow: 0 0 0 3px var(--m-primary-soft); }

    .messenger-conversation-list { flex: 1 1 auto; min-height: 0; padding: 6px; overflow-y: auto; }
    .messenger-thread { position: relative; display: grid; grid-template-columns: 44px minmax(0, 1fr) auto; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 12px; color: inherit; text-decoration: none; transition: background .15s ease; }
    .messenger-thread + .messenger-thread { margin-top: 2px; }
    .messenger-thread:hover { background: var(--m-soft); }
    .messenger-thread.is-active { background: var(--m-primary-soft); }
    .messenger-thread.is-active::before { content: ''; position: absolute; top: 12px; bottom: 12px; left: 0; width: 3px; border-radius: 3px; background: var(--m-primary); }
    .messenger-avatar-wrap { position: relative; width: 44px; height: 44px; }
    .messenger-avatar, .messenger-chat-avatar { display: grid; place-items: center; width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #dfeee7, #c9e1d6); color: var(--m-primary); font-size: 14px; font-weight: 700; letter-spacing: .02em; }
    .messenger-thread.is-active .messenger-avatar { background: var(--m-primary); color: #fff; }
    .messenger-presence-dot { position: absolute; right: 0; bottom: 1px; width: 12px; height: 12px; border: 2px solid #fff; border-radius: 50%; background: #b9c0bc; }
    .messenger-presence-dot.is-online { background: var(--m-online); }
    .messenger-thread-copy { display: grid; gap: 3px; min-width: 0; }
    .messenger-thread-top, .messenger-thread-bottom { display: flex; align-items: center; justify-content: space-between; gap: 8px; min-width: 0; }
    .messenger-thread-name { overflow: hidden; color: var(--m-ink); font-size: 14px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
    .messenger-thread-time { flex: none; color: var(--m-subtle); font-size: 11.5px; }
    .messenger-thread-preview { overflow: hidden; color: var(--m-muted); font-size: 12.5px; text-overflow: ellipsis; white-space: nowrap; }
    .messenger-role-pill { flex: none; padding: 2px 8px; border-radius: 999px; background: var(--m-soft); color: var(--m-muted); font-size: 10.5px; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; }
    .messenger-unread-badge { display: inline-grid; place-items: center; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; background: var(--m-primary); color: #fff; font-size: 11px; font-weight: 700; }
    .messenger-thread:has(.messenger-unread-badge:not([hidden])) .messenger-thread-name,
    .messenger-thread:has(.messenger-unread-badge:not([hidden])) .messenger-thread-preview { color: var(--m-ink); font-weight: 700; }
    .messenger-empty-list { display: grid; justify-items: center; gap: 8px; padding: 40px 16px; color: var(--m-muted); font-size: 13px; text-align: center; }
    .messenger-empty-list i { color: var(--m-subtle); font-size: 26px; }

    /* ---------- Chat ---------- */
    .messenger-chat { position: relative; display: flex; flex-direction: column; min-width: 0; min-height: 0; background: var(--m-soft); }
    .messenger-empty-chat { display: grid; place-content: center; justify-items: center; gap: 8px; height: 100%; padding: 24px; color: var(--m-muted); font-size: 13px; text-align: center; }
    .messenger-empty-chat[hidden] { display: none; }
    .messenger-empty-chat i { display: grid; place-items: center; width: 64px; height: 64px; margin-bottom: 6px; border-radius: 50%; background: #fff; color: var(--m-primary); font-size: 24px; box-shadow: 0 6px 18px rgba(27, 36, 32, .08); }
    .messenger-empty-chat strong { color: var(--m-ink); font-size: 16px; }
    .messenger-empty-chat p { max-width: 300px; margin: 0; line-height: 1.5; }

    .messenger-chat-header { display: flex; align-items: center; min-height: 68px; padding: 12px 18px; border-bottom: 1px solid var(--m-line); background: #fff; }
    .messenger-chat-header[hidden] { display: none; }
    .messenger-chat-person { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .messenger-mobile-back { display: none; place-items: center; width: 34px; height: 34px; border: 0; border-radius: 8px; background: transparent; color: var(--m-ink); cursor: pointer; }
    .messenger-mobile-back:hover { background: var(--m-soft); }
    .messenger-chat-avatar { position: relative; flex: none; }
    .messenger-chat-title-wrap { display: grid; min-width: 0; line-height: 1.3; }
    .messenger-chat-title { overflow: hidden; margin: 0; color: var(--m-ink); font-size: 15px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
    .messenger-chat-subtitle { overflow: hidden; color: var(--m-muted); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
    .messenger-chat-status { display: inline-flex; align-items: center; gap: 6px; color: var(--m-subtle); font-size: 11.5px; font-weight: 600; }
    .messenger-status-dot { width: 7px; height: 7px; border-radius: 50%; background: #b9c0bc; }
    .messenger-chat-status.is-online { color: var(--m-online); }
    .messenger-chat-status.is-online .messenger-status-dot { background: var(--m-online); box-shadow: 0 0 0 3px rgba(34, 160, 107, .15); }

    .messenger-chat-body { flex: 1 1 auto; min-height: 0; padding: 20px 22px; overflow-y: auto; scroll-behavior: smooth; }
    .messenger-chat-body[hidden] { display: none; }
    .messenger-chat-body::-webkit-scrollbar, .messenger-conversation-list::-webkit-scrollbar { width: 8px; }
    .messenger-chat-body::-webkit-scrollbar-thumb, .messenger-conversation-list::-webkit-scrollbar-thumb { border-radius: 8px; background: #d6d0c3; }
    .messenger-message-stack { display: flex; flex-direction: column; gap: 10px; min-height: 100%; justify-content: flex-end; }
    .messenger-no-messages { display: grid; justify-items: center; gap: 8px; margin: auto; color: var(--m-muted); font-size: 13px; }
    .messenger-no-messages i { color: var(--m-primary); font-size: 22px; }

    .messenger-message { display: flex; flex-direction: column; align-items: flex-start; max-width: 100%; }
    .messenger-message.is-outgoing { align-items: flex-end; }
    .messenger-message-author { margin: 0 6px 3px; color: var(--m-subtle); font-size: 11px; font-weight: 600; }
    .messenger-message.is-outgoing .messenger-message-author { display: none; }
    .messenger-message-bubble { max-width: min(560px, 72%); padding: 9px 13px; border: 1px solid var(--m-line); border-radius: 16px 16px 16px 4px; background: #fff; color: var(--m-ink); box-shadow: 0 1px 2px rgba(27, 36, 32, .05); overflow-wrap: anywhere; }
    .messenger-message.is-outgoing .messenger-message-bubble { border-color: var(--m-primary); border-radius: 16px 16px 4px 16px; background: var(--m-primary); color: #fff; }
    .messenger-message-text { margin: 0; font-size: 14px; line-height: 1.5; white-space: pre-wrap; }
    .messenger-message-meta, .messenger-message-state { margin: 3px 6px 0; color: var(--m-subtle); font-size: 10.5px; }
    .messenger-message-state { margin-top: 0; font-weight: 600; }
    .messenger-message-state[hidden] { display: none; }

    .messenger-attachment { margin-top: 6px; }
    .messenger-message-text + .messenger-attachment { margin-top: 8px; }
    .messenger-attachment-image { display: block; max-width: 260px; max-height: 220px; border-radius: 10px; object-fit: cover; }
    .messenger-file-link { display: flex; align-items: center; gap: 10px; min-width: 220px; padding: 8px 10px; border-radius: 10px; background: var(--m-soft); color: var(--m-ink); text-decoration: none; }
    .messenger-message.is-outgoing .messenger-file-link { background: rgba(255, 255, 255, .14); color: #fff; }
    .messenger-file-icon { display: grid; place-items: center; flex: 0 0 34px; height: 34px; border-radius: 8px; background: #fff; color: var(--m-primary); }
    .messenger-file-info { display: grid; min-width: 0; }
    .messenger-file-name { overflow: hidden; font-size: 13px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
    .messenger-file-size { opacity: .75; font-size: 11px; }

    .messenger-typing { display: flex; align-items: center; gap: 8px; padding: 0 22px 8px; color: var(--m-muted); font-size: 12px; }
    .messenger-typing[hidden] { display: none; }
    .messenger-typing-dots { display: inline-flex; gap: 3px; padding: 7px 10px; border: 1px solid var(--m-line); border-radius: 12px; background: #fff; }
    .messenger-typing-dots span { width: 6px; height: 6px; border-radius: 50%; background: var(--m-subtle); animation: messenger-typing 1s infinite ease-in-out; }
    .messenger-typing-dots span:nth-child(2) { animation-delay: .15s; }
    .messenger-typing-dots span:nth-child(3) { animation-delay: .3s; }
    @keyframes messenger-typing { 0%, 60%, 100% { opacity: .35; transform: translateY(0); } 30% { opacity: 1; transform: translateY(-3px); } }

    /* ---------- Composer ---------- */
    .messenger-composer { padding: 12px 16px 14px; border-top: 1px solid var(--m-line); background: #fff; }
    .messenger-composer[hidden] { display: none; }
    .messenger-attachment-preview { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; padding: 8px 10px; border: 1px solid var(--m-line); border-radius: 10px; background: var(--m-soft); }
    .messenger-attachment-preview[hidden] { display: none; }
    .messenger-attachment-thumb { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; }
    .messenger-attachment-icon { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 8px; background: #fff; color: var(--m-primary); }
    .messenger-attachment-info { display: grid; flex: 1 1 auto; min-width: 0; }
    .messenger-attachment-name { overflow: hidden; font-size: 13px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
    .messenger-attachment-size { color: var(--m-muted); font-size: 11px; }
    .messenger-remove-attachment { display: grid; place-items: center; width: 30px; height: 30px; border: 0; border-radius: 8px; background: transparent; color: var(--m-muted); cursor: pointer; }
    .messenger-remove-attachment:hover { background: #fee2e2; color: #b91c1c; }

    .messenger-input-row { display: flex; align-items: flex-end; gap: 8px; padding: 6px; border: 1px solid var(--m-line); border-radius: 14px; background: var(--m-soft); transition: border-color .15s ease, box-shadow .15s ease; }
    .messenger-input-row:focus-within { border-color: var(--m-primary); background: #fff; box-shadow: 0 0 0 3px var(--m-primary-soft); }
    .messenger-attach-button, .messenger-send-button { display: grid; place-items: center; flex: 0 0 38px; width: 38px; height: 38px; border: 0; border-radius: 10px; cursor: pointer; }
    .messenger-attach-button { position: relative; background: transparent; color: var(--m-muted); }
    .messenger-attach-button:hover { background: #fff; color: var(--m-primary); }
    .messenger-file-input { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
    .messenger-input { flex: 1 1 auto; min-height: 38px; max-height: 140px; padding: 9px 4px; border: 0; outline: none; background: transparent; color: var(--m-ink); font: inherit; font-size: 14px; line-height: 1.45; resize: none; }
    .messenger-send-button { background: var(--m-primary); color: #fff; box-shadow: 0 4px 10px rgba(29, 79, 64, .25); transition: transform .15s ease, opacity .15s ease; }
    .messenger-send-button:hover { transform: translateY(-1px); }
    .messenger-send-button:disabled { opacity: .55; cursor: not-allowed; transform: none; }

    /* The dashboard's general text and form styles are more specific; the ID pair never matches and only raises specificity. */
    :is(.messenger-shell, #m-x#m-x) .messenger-message-text { color: inherit !important; -webkit-text-fill-color: currentColor !important; }
    :is(.messenger-shell, #m-x#m-x) .messenger-message.is-outgoing .messenger-message-bubble,
    :is(.messenger-shell, #m-x#m-x) .messenger-message.is-outgoing .messenger-message-text { color: #fff !important; -webkit-text-fill-color: #fff !important; }
    :is(.messenger-shell, #m-x#m-x) .messenger-input { min-height: 38px !important; padding: 9px 4px !important; border: 0 !important; border-radius: 0 !important; background: transparent !important; box-shadow: none !important; color: var(--m-ink) !important; }
    :is(.messenger-shell, #m-x#m-x) .messenger-unread-badge { background: var(--m-primary) !important; color: #fff !important; }
    :is(.messenger-shell, #m-x#m-x) .messenger-role-pill { background: var(--m-soft) !important; color: var(--m-muted) !important; }

    .messenger-shell :focus-visible { outline: 2px solid var(--m-primary); outline-offset: 2px; }
    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }

    /* ---------- Smaller screens ---------- */
    @media (max-width: 1100px) {
        .messenger-grid { grid-template-columns: minmax(260px, 320px) minmax(0, 1fr); }
        .messenger-message-bubble { max-width: 82%; }
    }
    @media (max-width: 820px) {
        /* One panel at a time: the list, or the chat sliding over it. */
        .messenger-grid { display: block; height: calc(100dvh - 120px); min-height: 500px; }
        .messenger-conversations { height: 100%; border-right: 0; }
        .messenger-chat { position: absolute; inset: 0; z-index: 3; transform: translateX(100%); transition: transform .24s ease; }
        .messenger-grid.is-chat-open .messenger-chat { transform: translateX(0); }
        .messenger-mobile-back { display: inline-grid; }
        .messenger-chat-header { padding: 10px 12px; }
        .messenger-chat-body { padding: 14px 12px; }
        .messenger-message-bubble { max-width: 88%; }
        .messenger-composer { padding: 10px 10px calc(10px + env(safe-area-inset-bottom)); }

        /* An open chat fills the visible screen, above the on-screen keyboard
           (the script sets --m-vv-top/--m-vv-height from the visual viewport). */
        .messenger-grid.is-chat-open { position: fixed; top: var(--m-vv-top, 0px); right: 0; left: 0; z-index: 2000; height: var(--m-vv-height, 100dvh); min-height: 0; border: 0; border-radius: 0; box-shadow: none; }
        .messenger-grid.is-chat-open .messenger-chat-header { min-height: 60px; padding-top: calc(8px + env(safe-area-inset-top)); padding-bottom: 8px; }
        html.messenger-chat-fullscreen, html.messenger-chat-fullscreen body { overflow: hidden !important; overscroll-behavior: none; }
        .messenger-chat-body { overscroll-behavior: contain; }
        /* 16px keeps phones from zooming in when the box is tapped. */
        :is(.messenger-shell, #m-x#m-x) .messenger-input { font-size: 16px !important; }
    }
    @media (prefers-reduced-motion: reduce) {
        .messenger-chat, .messenger-typing-dots span, .messenger-send-button { transition: none; animation: none; }
    }
</style>
