{{-- Styles and step logic for bidder.partials.submit-bid-modal (included once per page). --}}
<style>
    .bidder-submit-modal-overlay .sb { --sb-line: var(--ui-line, #e4e0d6); --sb-soft: var(--ui-page, #f6f4ee); display: flex; flex-direction: column; width: min(1040px, calc(100vw - 32px)); max-height: min(90vh, calc(100dvh - 24px)); overflow: hidden; border: 1px solid var(--sb-line); border-radius: 14px; background: #fff; color: var(--ui-ink); font-family: var(--ui-font); box-shadow: 0 24px 70px rgba(27, 36, 32, .28); outline: none; }
    .sb *, .sb *::before, .sb *::after { box-sizing: border-box; }
    .sb-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 18px 22px 14px; border-bottom: 1px solid var(--sb-line); }
    .sb-head-main { min-width: 0; }
    .sb-eyebrow { display: flex; flex-wrap: wrap; gap: 4px 10px; margin: 0 0 4px; color: var(--ui-subtle); font-size: 11.5px; font-weight: 600; }
    .sb-eyebrow code { color: var(--ui-muted); font-family: var(--ui-mono); }
    .sb-title { margin: 0; color: var(--ui-ink); font-size: 20px; font-weight: 700; line-height: 1.25; }
    .sb-project { margin: 2px 0 0; color: var(--ui-ink-2); font-size: 14px; font-weight: 500; overflow-wrap: anywhere; }
    .sb-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
    .sb-chip { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border: 1px solid var(--sb-line); border-radius: 999px; background: var(--sb-soft); color: var(--ui-ink-2); font-size: 12px; font-weight: 600; }
    .sb-chip i { color: var(--ui-muted); font-size: 11px; }
    .sb-chip.is-warn { border-color: var(--ui-warning-line); background: var(--ui-warning-soft); color: var(--ui-warning); }
    .sb-chip.is-ok { border-color: var(--ui-success-line); background: var(--ui-success-soft); color: var(--ui-success); }
    .sb-chip.is-ok i { color: inherit; }
    .sb-close { display: grid; place-items: center; flex: none; width: 36px; height: 36px; border: 1px solid var(--sb-line); border-radius: 10px; background: #fff; color: var(--ui-muted); cursor: pointer; }
    .sb-close:hover { color: var(--ui-ink); border-color: var(--ui-line-strong); }


    .sb-bulk-upload { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px 14px; align-items: center; padding: 14px 16px; border: 1px solid var(--sb-line); border-radius: 12px; background: #fff; }
    .sb-bulk-copy h3 { margin: 0; color: var(--ui-ink); font-size: 14px; font-weight: 700; }
    .sb-bulk-copy p, .sb-bulk-status, .sb-bulk-queue-hint { margin: 3px 0 0; color: var(--ui-muted); font-size: 12px; line-height: 1.45; }
    .sb-bulk-picker { position: relative; display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 40px; padding: 0 14px; overflow: hidden; border: 1px solid var(--ui-primary); border-radius: 9px; background: var(--ui-primary); color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap; }
    .sb-bulk-picker:hover { background: var(--ui-primary-hover); }
    /* Legacy dashboard modal rules give labels !important text colors. Keep the
       bulk picker label and icon readable on its filled primary background. */
    .bidder-submit-modal-overlay .sb .sb-bulk-picker,
    .bidder-submit-modal-overlay .sb .sb-bulk-picker :is(i, span) {
        color: #fff !important;
        -webkit-text-fill-color: #fff !important;
    }
    .bidder-submit-modal-overlay .sb .sb-bulk-picker:focus-within {
        outline: 3px solid rgba(32, 88, 70, .28);
        outline-offset: 2px;
    }
    .sb-bulk-picker input { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
    .sb-bulk-status { grid-column: 1 / -1; margin: 0; }
    .sb-bulk-queue { grid-column: 1 / -1; display: grid; gap: 8px; padding-top: 10px; border-top: 1px solid var(--sb-line); }
    .sb-bulk-queue[hidden] { display: none; }
    .sb-bulk-queue-hint { margin: 0; }
    .sb-bulk-assignments { display: grid; gap: 6px; }
    .sb-bulk-assignment { display: grid; grid-template-columns: minmax(0, 1fr) minmax(220px, .9fr); gap: 10px; align-items: center; padding: 8px 10px; border: 1px solid var(--sb-line); border-radius: 8px; background: var(--sb-soft); }
    .sb-bulk-assignment-name { min-width: 0; overflow-wrap: anywhere; color: var(--ui-ink-2); font-size: 12px; }
    .sb-bulk-assignment select { min-width: 0; min-height: 36px; padding: 0 9px; border: 1px solid var(--sb-line); border-radius: 7px; background: #fff; color: var(--ui-ink); font: inherit; font-size: 12px; }
    .sb-bulk-apply { justify-self: start; }
    .sb-bulk-clear { justify-self: start; }
    .sb-body { display: grid; grid-template-columns: minmax(0, 1fr) 300px; flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
    .sb-main { min-width: 0; padding: 18px 22px 22px; }
    /* Steps: a slim bar of three pills, pinned at the top while the form scrolls. */
    .sb-steps { position: sticky; top: -18px; z-index: 3; display: flex; gap: 4px; margin: -18px -22px 14px; padding: 8px 22px; border-bottom: 1px solid var(--sb-line); background: var(--sb-soft); }
    .sb-step { display: flex; flex: 1 1 0; align-items: center; justify-content: center; gap: 8px; min-width: 0; min-height: 36px; padding: 5px 10px; border: 1px solid transparent; border-radius: 9px; background: transparent; color: var(--ui-muted); font: inherit; cursor: pointer; }
    .sb-step:hover { background: #fff; }
    .sb-step:focus-visible { outline: 3px solid #9bc9b7; outline-offset: 2px; }
    .sb-step.is-current { border-color: var(--ui-primary-line); background: #fff; box-shadow: 0 1px 2px rgba(27, 36, 32, .06); }
    .sb-step__number { position: relative; display: grid; flex: 0 0 22px; height: 22px; place-items: center; border: 2px solid var(--ui-line-strong); border-radius: 50%; background: #fff; color: var(--ui-muted); font-size: 11px; font-weight: 700; }
    .sb-step.is-current .sb-step__number { border-color: var(--ui-primary); background: var(--ui-primary); color: #fff; }
    .sb-step.is-done:not(.is-current) .sb-step__number { border-color: var(--ui-primary); background: var(--ui-primary-soft); color: transparent; }
    .sb-step.is-done:not(.is-current) .sb-step__number::after { content: '\f00c'; position: absolute; color: var(--ui-primary); font-family: 'Font Awesome 6 Free'; font-size: 10px; font-weight: 900; }
    .sb-step > span:last-child { min-width: 0; text-align: left; }
    .sb-step strong { display: block; overflow: hidden; color: var(--ui-ink-2); font-size: 13px; font-weight: 600; line-height: 1.3; text-overflow: ellipsis; white-space: nowrap; }
    .sb-step.is-current strong { color: var(--ui-ink); }
    .sb-step small { display: none; }
    .sb-form { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; margin: 0; }
    .sb-panel { display: grid; gap: 12px; }
    .sb-panel[hidden] { display: none; }
    .sb-panel-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
    .sb-panel-title { margin: 0; color: var(--ui-ink); font-size: 15px; font-weight: 700; outline: none; }
    .sb-panel-hint { margin: 2px 0 0; color: var(--ui-muted); font-size: 12.5px; line-height: 1.5; }
    .sb-count { flex: none; padding: 3px 10px; border-radius: 999px; background: var(--sb-soft); color: var(--ui-ink-2); font-size: 12px; font-weight: 600; white-space: nowrap; }
    .sb-count.is-done { background: var(--ui-success-soft); color: var(--ui-success); }
    .sb-group-title { margin: 6px 0 0; color: var(--ui-ink); font-size: 13px; font-weight: 700; }

    .sb-alert { display: flex; gap: 10px; align-items: flex-start; padding: 11px 13px; border: 1px solid var(--sb-line); border-left: 4px solid var(--ui-ink-2); border-radius: 10px; background: var(--sb-soft); color: var(--ui-ink-2); font-size: 12.5px; line-height: 1.5; }
    .sb-alert > i { margin-top: 3px; }
    .sb-alert strong { color: var(--ui-ink); }
    .sb-alert p, .sb-alert ul { margin: 2px 0 0; }
    .sb-alert ul { padding-left: 18px; }
    .sb-alert.is-info { border-color: var(--ui-primary-line); border-left-color: var(--ui-primary); background: var(--ui-primary-soft); }
    .sb-alert.is-info > i { color: var(--ui-primary); }
    .sb-alert.is-warning { border-color: var(--ui-warning-line); border-left-color: var(--ui-warning); background: var(--ui-warning-soft); }
    .sb-alert.is-warning > i { color: var(--ui-warning); }
    .sb-alert.is-danger { border-color: var(--ui-danger-line); border-left-color: var(--ui-danger); background: var(--ui-danger-soft); }
    .sb-alert.is-danger > i { color: var(--ui-danger); }
    .sb-paid { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; margin: 0; color: var(--ui-muted); font-size: 12.5px; }
    .sb-paid i, .sb-paid strong { color: var(--ui-success); }

    .sb-files { display: grid; gap: 8px; margin: 0; padding: 0; list-style: none; }
    .sb-file { position: relative; display: grid; grid-template-columns: 34px minmax(0, 1fr) auto; align-items: center; gap: 12px; padding: 10px 12px; border: 1px solid var(--sb-line); border-radius: 10px; background: #fff; transition: border-color .15s ease, background .15s ease; }
    .sb-file.has-file { border-color: var(--ui-success-line); background: var(--ui-success-soft); }
    .sb-file.is-invalid { border-color: var(--ui-danger-line); }
    .sb-file-icon { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 9px; background: var(--sb-soft); color: var(--ui-muted); }
    .sb-file.has-file .sb-file-icon { background: #fff; color: var(--ui-success); }
    .sb-file-copy { display: grid; gap: 2px; min-width: 0; }
    .sb-file-title { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; color: var(--ui-ink); font-size: 13px; font-weight: 600; cursor: default; }
    .sb-file-meta { color: var(--ui-muted); font-size: 11.5px; }
    .sb-file-record { color: var(--ui-ink-2); font-size: 11.5px; overflow-wrap: anywhere; }
    .sb-file-chosen { overflow: hidden; color: var(--ui-subtle); font-size: 11.5px; text-overflow: ellipsis; white-space: nowrap; }
    .sb-file.has-record:not(.has-file) .sb-file-chosen { display: none; }
    .sb-file.has-file .sb-file-chosen { display: block; color: var(--ui-success); font-weight: 600; }
    .sb-file-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 6px; }
    .sb-file-input { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
    .sb-tag { display: inline-flex; padding: 1px 7px; border-radius: 999px; background: var(--sb-soft); color: var(--ui-muted); font-size: 10.5px; font-weight: 700; }
    .sb-tag.is-required { background: var(--ui-danger-soft); color: var(--ui-danger); }
    .sb-checklist { display: grid; gap: 6px; margin: 0; padding: 0; list-style: none; font-size: 12.5px; }
    .sb-checklist i { width: 16px; color: var(--ui-muted); }

    .sb-label { display: block; color: var(--ui-ink); font-size: 13px; font-weight: 600; }
    .sb-req { color: var(--ui-danger); }
    .sb-optional { color: var(--ui-subtle); font-weight: 500; }
    .sb-hint { margin: 2px 0 8px; color: var(--ui-muted); font-size: 12px; line-height: 1.5; }
    /* Legacy modal rules in dashboard.css force input styles with !important; the ID pair never matches and only raises specificity. */
    :is(.sb, #sb-x#sb-x) .sb-input { width: 100% !important; min-height: 40px !important; padding: 8px 12px !important; border: 1px solid var(--ui-line-strong) !important; border-radius: 9px !important; background: #fff !important; color: var(--ui-ink) !important; font: inherit; font-size: 14px !important; box-shadow: none !important; color-scheme: light !important; }
    :is(.sb, #sb-x#sb-x) .sb-input:focus { border-color: var(--ui-primary) !important; outline: 3px solid var(--ui-primary-soft) !important; outline-offset: 0 !important; }
    :is(.sb, #sb-x#sb-x) .sb-input.is-invalid { border-color: var(--ui-danger) !important; }
    .sb-price, .sb-pin, .sb-notes { padding: 14px; border: 1px solid var(--sb-line); border-radius: 10px; background: var(--sb-soft); }
    .sb-money { position: relative; max-width: 360px; }
    .sb-money-prefix { position: absolute; top: 50%; left: 14px; transform: translateY(-50%); color: var(--ui-muted); font-size: 18px; font-weight: 600; }
    :is(.sb, #sb-x#sb-x) .sb-money-input { min-height: 48px !important; padding-left: 34px !important; font-size: 20px !important; font-weight: 700; font-variant-numeric: tabular-nums; }
    .sb-compare { margin: 6px 0 0; color: var(--ui-muted); font-size: 12.5px; }
    .sb-compare:empty { display: none; }
    .sb b.is-under { color: var(--ui-success); }
    .sb b.is-over { color: var(--ui-danger); }
    .sb-error { display: block; color: var(--ui-danger); font-size: 12px; font-weight: 600; }
    .sb-error:empty { display: none; }
    .sb-pin { display: grid; gap: 12px; }
    .sb-pin-head { display: flex; align-items: flex-start; gap: 12px; }
    .sb-pin-icon { display: grid; place-items: center; flex: 0 0 36px; height: 36px; border-radius: 10px; background: #fff; color: var(--ui-primary); }
    .sb-pin-head .sb-hint { margin: 2px 0 0; }
    .sb-pin-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 220px)); gap: 12px 18px; }
    .sb-pin-field { display: grid; gap: 5px; min-width: 0; }
    .sb-pin-field > label { color: var(--ui-ink-2); font-size: 12.5px; font-weight: 600; }
    .sb-pin-control { position: relative; }
    :is(.sb, #sb-x#sb-x) .sb-pin-input { width: 100% !important; min-height: 48px !important; padding: 8px 44px 8px calc(12px + .45em) !important; font-family: var(--ui-mono), monospace; font-size: 20px !important; letter-spacing: .45em; text-align: center; }
    :is(.sb, #sb-x#sb-x) .sb-pin-input.is-match { border-color: var(--ui-success) !important; }
    :is(.sb, #sb-x#sb-x) .sb-pin-input.is-mismatch { border-color: var(--ui-danger) !important; background: var(--ui-danger-soft) !important; animation: sb-pin-shake .25s ease 1; }
    .sb .sb-pin-input::placeholder { letter-spacing: .3em; }
    .sb .sb-pin-eye { position: absolute; top: 50%; right: 6px; display: grid; place-items: center; width: 34px; height: 34px; border: 0; border-radius: 8px; background: transparent; color: var(--ui-muted); transform: translateY(-50%); cursor: pointer; }
    .sb .sb-pin-eye:hover, .sb .sb-pin-eye:focus-visible { background: var(--sb-soft); color: var(--ui-ink); }
    .sb-pin-count { color: var(--ui-subtle); font-size: 11.5px; font-variant-numeric: tabular-nums; }
    .sb-pin-count.is-complete { color: var(--ui-success); font-weight: 600; }
    .sb-pin-status { display: flex; align-items: center; gap: 8px; margin: 0; padding: 9px 12px; border: 1px solid var(--sb-line); border-radius: 9px; background: #fff; color: var(--ui-muted); font-size: 12.5px; font-weight: 600; }
    .sb-pin-status[data-state="match"] { border-color: var(--ui-success-line); background: var(--ui-success-soft); color: var(--ui-success); }
    .sb-pin-status[data-state="mismatch"] { border-color: var(--ui-danger-line); border-left: 4px solid var(--ui-danger); background: var(--ui-danger-soft); color: var(--ui-danger); }
    .sb-pin-tips { display: grid; gap: 4px; margin: 0; padding: 0; list-style: none; color: var(--ui-muted); font-size: 12px; }
    .sb-pin-tips i { width: 16px; color: var(--ui-subtle); }
    @keyframes sb-pin-shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-4px); } 75% { transform: translateX(4px); } }
    @media (prefers-reduced-motion: reduce) { :is(.sb, #sb-x#sb-x) .sb-pin-input.is-mismatch { animation: none; } }
    :is(.sb, #sb-x#sb-x) .sb-textarea { min-height: 80px !important; resize: vertical; }

    .sb-review-offer { display: grid; gap: 2px; padding: 14px 16px; border: 1px solid var(--ui-primary-line); border-radius: 10px; background: var(--ui-primary-soft); }
    .sb-review-offer span { color: var(--ui-muted); font-size: 12px; font-weight: 600; }
    .sb-review-offer strong { color: var(--ui-ink); font-size: 24px; font-variant-numeric: tabular-nums; }
    .sb-review-offer small { color: var(--ui-muted); font-size: 12.5px; }
    .sb-review-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .sb-review-block { padding: 12px 14px; border: 1px solid var(--sb-line); border-radius: 10px; }
    .sb-review-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
    .sb-review-head h4 { margin: 0; color: var(--ui-ink); font-size: 13px; font-weight: 700; }
    .sb-review-list { display: grid; gap: 7px; margin: 0; padding: 0; list-style: none; font-size: 12.5px; }
    .sb-review-list li { display: grid; grid-template-columns: 16px minmax(0, 1fr); gap: 8px; color: var(--ui-ink); }
    .sb-review-list li i { margin-top: 3px; font-size: 12px; }
    .sb-review-list .is-ok i { color: var(--ui-success); }
    .sb-review-list .is-missing { color: var(--ui-danger); }
    .sb-review-list .is-skip { color: var(--ui-subtle); }
    .sb-review-list small { display: block; color: var(--ui-muted); overflow-wrap: anywhere; }
    .sb-review-list .is-missing small { color: var(--ui-danger); }
    .sb-review-pin { margin: 10px 0 0; padding-top: 8px; border-top: 1px solid var(--sb-line); font-size: 12.5px; font-weight: 600; }
    .sb-review-pin.is-ok { color: var(--ui-success); }
    .sb-review-pin.is-missing { color: var(--ui-danger); }
    .sb-link { padding: 0; border: 0; background: none; color: var(--ui-primary); font: inherit; font-size: 12.5px; font-weight: 600; cursor: pointer; }
    .sb-link:hover { text-decoration: underline; }

    .sb-pay { display: grid; grid-template-columns: 40px minmax(0, 1fr); gap: 12px; padding: 16px; border: 1px solid var(--ui-warning-line); border-radius: 12px; background: var(--ui-warning-soft); }
    .sb-pay-icon { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 10px; background: #fff; color: var(--ui-warning); }
    .sb-pay h3 { margin: 0; color: var(--ui-ink); font-size: 15px; }
    .sb-pay p { margin: 4px 0 0; color: var(--ui-ink-2); font-size: 12.5px; }
    .sb-pay-details { display: grid; gap: 6px; margin: 12px 0 8px; font-size: 12.5px; }
    .sb-pay-details div { display: grid; grid-template-columns: 110px minmax(0, 1fr); gap: 8px; }
    .sb-pay-details dt { color: var(--ui-muted); }
    .sb-pay-details dd { margin: 0; color: var(--ui-ink); font-weight: 600; }
    .sb-pay-amount { font-size: 16px; }
    .sb-muted { color: var(--ui-muted); }

    .sb-rail { display: grid; align-content: start; gap: 14px; min-width: 0; padding: 18px 18px 22px; border-left: 1px solid var(--sb-line); background: var(--sb-soft); }
    .sb-rail-block > summary { color: var(--ui-ink); font-size: 13px; font-weight: 700; cursor: pointer; }
    .sb-notice { display: grid; margin: 8px 0 0; font-size: 12px; }
    .sb-notice > div { display: flex; justify-content: space-between; gap: 10px; padding: 6px 0; border-bottom: 1px solid var(--sb-line); }
    .sb-notice dt { color: var(--ui-muted); }
    .sb-notice dt { flex: 1 1 auto; min-width: 0; }
    .sb-notice dd { flex: 0 1 auto; margin: 0; color: var(--ui-ink); font-weight: 600; text-align: right; overflow-wrap: normal; word-break: normal; }
    .sb-rail-docs { display: grid; gap: 12px; }

    .sb-foot { display: flex; align-items: center; justify-content: space-between; gap: 10px 16px; padding: 12px 22px; border-top: 1px solid var(--sb-line); background: #fff; }
    .sb-status { color: var(--ui-muted); font-size: 12.5px; }
    .sb-status.is-ready { color: var(--ui-success); font-weight: 600; }
    .sb-foot-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; }
    .sb .sb-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 40px; padding: 0 16px; border: 1px solid transparent; border-radius: 9px; font: inherit; font-size: 13.5px; font-weight: 600; white-space: nowrap; cursor: pointer; }
    .sb .sb-btn[hidden] { display: none; }
    .sb .sb-btn--sm { min-height: 32px; padding: 0 12px; font-size: 12.5px; }
    .sb .sb-btn--primary { background: var(--ui-primary); color: #fff; }
    .sb .sb-btn--primary:hover { background: var(--ui-primary-hover); }
    .sb .sb-btn--primary:disabled { background: #9fb5ad; cursor: not-allowed; }
    .sb .sb-btn--secondary { border-color: var(--ui-line-strong); background: #fff; color: var(--ui-ink); }
    .sb .sb-btn--secondary:hover { border-color: var(--ui-ink-2); }
    .sb .sb-btn--ghost { background: transparent; color: var(--ui-muted); }
    .sb .sb-btn--ghost:hover { background: var(--sb-soft); color: var(--ui-ink); }
    .sb .sb-btn[aria-busy="true"] { opacity: .8; }

    @media (max-width: 900px) {
        .sb-body { grid-template-columns: minmax(0, 1fr); }
        .sb-rail { order: 2; border-top: 1px solid var(--sb-line); border-left: 0; }
        .sb-review-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 640px) {
        .sb-pin-grid { grid-template-columns: 1fr; }
        .bidder-submit-modal-overlay .sb { width: 100%; max-height: calc(100dvh - 16px); border-radius: 12px; }
        .sb-head { padding: 14px 16px 12px; }
        .sb-title { font-size: 18px; }
        .sb-steps { margin: -14px -16px 12px; padding: 6px 8px; top: -14px; }
        .sb-step { gap: 5px; padding: 5px 4px; }
        .sb-step:not(.is-current) { position: relative; flex: 0 0 auto; padding: 5px 8px; }
        .sb-step:not(.is-current) > span:last-child { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
        .sb-step strong { font-size: 12.5px; }
        .sb-main, .sb-rail { padding: 14px 16px 18px; }
        .sb-file { grid-template-columns: 30px minmax(0, 1fr); }
        .sb-bulk-upload { grid-template-columns: 1fr; }
        .sb-bulk-picker { justify-self: start; }
        .sb-bulk-assignment { grid-template-columns: 1fr; }
        .sb-file-actions { grid-column: 1 / -1; }
        .sb-file-actions .sb-btn { flex: 1; }
        .sb-foot { flex-direction: column; align-items: stretch; padding: 10px 16px; }
        .sb-foot-actions { display: grid; grid-auto-columns: minmax(0, 1fr); grid-auto-flow: column; }
        .sb-foot-actions > [data-close-modal] { display: none; }
        .sb .sb-btn { padding: 0 10px; }
        .sb-status { text-align: center; }
    }
</style>

<script>
    // One-page submit form. This updates the inline review and focuses the first incomplete section.
    document.addEventListener('DOMContentLoaded', function () {
        function peso(value) {
            return '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        function amountOf(input) {
            const clean = String(input ? input.value : '').replace(/[^0-9.]/g, '');
            return /[0-9]/.test(clean) && Number.isFinite(Number(clean)) ? Number(clean) : null;
        }
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        document.querySelectorAll('[data-bid-dialog]:not([data-sb-locked])').forEach(function (dialog) {
            const form = dialog.querySelector('[data-bid-form]');
            if (!form) return;
            const abc = Number(form.dataset.abc || 0);
            const electronic = form.dataset.electronic === 'true';
            const panels = Array.from(dialog.querySelectorAll('[data-sb-panel]'));
            const submit = dialog.querySelector('[data-bid-submit]');
            const body = dialog.querySelector('[data-scroll-body]');
            const amount = form.querySelector('[data-bid-amount]');
            const pins = form.querySelectorAll('[data-pin]');
            let step = 1;
            dialog.querySelectorAll('[data-bulk-uploader]').forEach(function (uploader) {
                const component = uploader.dataset.bulkUploader;
                const bulkPicker = uploader.querySelector('[data-bulk-file-picker]');
                const bulkQueue = uploader.querySelector('[data-bulk-queue]');
                const bulkAssignments = uploader.querySelector('[data-bulk-assignments]');
                const bulkApply = uploader.querySelector('[data-bulk-apply]');
                const bulkClear = uploader.querySelector('[data-bulk-clear]');
                const bulkStatus = uploader.querySelector('[data-bulk-status]');
                let bulkFiles = [];

                function bulkTargets() {
                    return Array.from(form.querySelectorAll('[data-upload-input]')).filter(function (input) {
                        const group = input.closest('[data-sb-files]');
                        return group && group.dataset.sbFiles === component;
                    });
                }
                function updateBulkApply() {
                    if (!bulkAssignments || !bulkApply) return;
                    const selects = Array.from(bulkAssignments.querySelectorAll('select'));
                    const values = selects.map(function (select) { return select.value; });
                    const unique = new Set(values.filter(Boolean));
                    bulkApply.disabled = !selects.length || selects.length !== bulkFiles.length || values.some(function (value) { return !value; }) || unique.size !== values.length;
                }
                function refreshBulkOptions() {
                    if (!bulkAssignments) return;
                    const selects = Array.from(bulkAssignments.querySelectorAll('select'));
                    selects.forEach(function (select) {
                        const current = select.value;
                        const usedElsewhere = new Set(selects.filter(function (other) { return other !== select; }).map(function (other) { return other.value; }).filter(Boolean));
                        const placeholder = document.createElement('option');
                        placeholder.value = '';
                        placeholder.textContent = 'Assign this file to a requirement';
                        select.replaceChildren(placeholder);
                        bulkTargets().forEach(function (input) {
                            if (input.files && input.files.length > 0) return;
                            if (usedElsewhere.has(input.id)) return;
                            const box = input.closest('[data-upload-box]');
                            if (!box) return;
                            const option = document.createElement('option');
                            option.value = input.id;
                            option.textContent = box.dataset.label;
                            select.appendChild(option);
                        });
                        select.value = current && Array.from(select.options).some(function (option) { return option.value === current; }) ? current : '';
                    });
                    updateBulkApply();
                }
                function renderBulkQueue(files) {
                    if (!bulkAssignments || !bulkQueue) return;
                    bulkFiles = Array.from(files || []);
                    bulkAssignments.replaceChildren();
                    bulkFiles.forEach(function (file) {
                        const row = document.createElement('div');
                        row.className = 'sb-bulk-assignment';
                        const name = document.createElement('span');
                        name.className = 'sb-bulk-assignment-name';
                        name.textContent = file.name;
                        const select = document.createElement('select');
                        select.setAttribute('aria-label', (component === 'financial' ? 'Financial requirement for ' : 'Technical requirement for ') + file.name);
                        select.addEventListener('change', refreshBulkOptions);
                        row.appendChild(name);
                        row.appendChild(select);
                        bulkAssignments.appendChild(row);
                    });
                    bulkQueue.hidden = bulkFiles.length === 0;
                    refreshBulkOptions();
                }
                if (bulkPicker) bulkPicker.addEventListener('change', function () { renderBulkQueue(bulkPicker.files); });
                if (bulkClear) bulkClear.addEventListener('click', function () {
                    if (bulkPicker) bulkPicker.value = '';
                    renderBulkQueue([]);
                    if (bulkStatus) bulkStatus.textContent = 'Selection cleared.';
                });
                if (bulkApply) bulkApply.addEventListener('click', function () {
                    const selects = bulkAssignments ? Array.from(bulkAssignments.querySelectorAll('select')) : [];
                    const targets = selects.map(function (select) { return select.value ? form.querySelector('#' + CSS.escape(select.value)) : null; });
                    if (bulkApply.disabled || targets.some(function (target) { return !target; })) return;
                    if (typeof DataTransfer === 'undefined') {
                        if (bulkStatus) bulkStatus.textContent = 'Your browser cannot assign the selected files. Please use a current version of Chrome or Edge.';
                        return;
                    }
                    bulkFiles.forEach(function (file, index) {
                        const transfer = new DataTransfer();
                        transfer.items.add(file);
                        targets[index].files = transfer.files;
                        targets[index].dispatchEvent(new Event('change', { bubbles: true }));
                    });
                    const count = bulkFiles.length;
                    if (bulkPicker) bulkPicker.value = '';
                    renderBulkQueue([]);
                    if (bulkStatus) bulkStatus.textContent = count + ' ' + component + ' file' + (count === 1 ? '' : 's') + ' assigned. Assigned requirements are removed from the list. Remove an attached file first if you need to replace it.';
                });
                uploader.addEventListener('bulk:refresh', refreshBulkOptions);
            });
            function rows(component) {
                return Array.from(form.querySelectorAll('[data-sb-files="' + component + '"] [data-upload-box]'));
            }
            function rowState(row) {
                const input = row.querySelector('[data-upload-input]');
                const file = input && input.files && input.files.length ? input.files[0].name : '';
                return {
                    label: row.dataset.label,
                    file: file,
                    record: row.dataset.onRecord || '',
                    required: row.dataset.required === '1',
                    missing: !file && Boolean(input) && input.hasAttribute('data-required-upload'),
                };
            }
            function pinOk() {
                return pins.length < 2 || (/^[0-9]{6}$/.test(pins[0].value) && pins[0].value === pins[1].value);
            }
            function amountOk() {
                const value = amountOf(amount);
                return value !== null && value > 0 && (!abc || value <= abc);
            }
            function compareText(value) {
                if (value === null || !abc) return '';
                const diff = (value - abc) / abc * 100;
                if (Math.abs(diff) < 0.05) return 'Equal to the ABC of ' + peso(abc);
                return '<b class="' + (diff > 0 ? 'is-over' : 'is-under') + '">' + Math.abs(diff).toFixed(1) + '% ' + (diff > 0 ? 'above' : 'below') + '</b> the ABC of ' + peso(abc);
            }
            function stepComplete(n) {
                if (n === 1) return !rows('technical').some(function (row) { return rowState(row).missing; });
                if (n === 2) return amountOk() && pinOk() && !rows('financial').some(function (row) { return rowState(row).missing; });
                return stepComplete(1) && stepComplete(2);
            }

            function refresh() {
                ['technical', 'financial'].forEach(function (component, index) {
                    const list = rows(component).map(rowState);
                    const required = list.filter(function (row) { return row.required; });
                    const ready = required.filter(function (row) { return row.file || row.record; }).length;
                    const attached = list.filter(function (row) { return row.file; }).length;
                    const text = electronic && required.length
                        ? ready + ' of ' + required.length + ' required ready'
                        : attached + ' ' + (attached === 1 ? 'file' : 'files') + ' attached';
                    const count = dialog.querySelector('[data-sb-count="' + (index + 1) + '"]');
                    if (count) {
                        count.textContent = text;
                        count.classList.toggle('is-done', stepComplete(index + 1));
                    }
                });

                const compare = dialog.querySelector('[data-bid-compare]');
                if (compare) compare.innerHTML = compareText(amountOf(amount));

                const price = dialog.querySelector('[data-sb-review-price]');
                if (price) price.textContent = amountOf(amount) !== null ? peso(amountOf(amount)) : 'Not entered yet';
                const reviewCompare = dialog.querySelector('[data-sb-review-compare]');
                if (reviewCompare) {
                    reviewCompare.innerHTML = amountOk()
                        ? compareText(amountOf(amount))
                        : (amountOf(amount) !== null ? '<b class="is-over">Check the price: it must be above zero and within the ABC.</b>' : '');
                }
                ['technical', 'financial'].forEach(function (component) {
                    const list = dialog.querySelector('[data-sb-review-files="' + component + '"]');
                    if (!list) return;
                    list.innerHTML = rows(component).map(rowState).map(function (row) {
                        let state = 'is-skip';
                        let icon = 'fa-minus';
                        let detail = 'Not provided';
                        if (row.file) { state = 'is-ok'; icon = 'fa-circle-check'; detail = 'New file: ' + row.file; }
                        else if (row.record) { state = 'is-ok'; icon = 'fa-circle-check'; detail = 'On record: ' + row.record; }
                        else if (row.missing) { state = 'is-missing'; icon = 'fa-circle-exclamation'; detail = 'Missing (required)'; }
                        return '<li class="' + state + '"><i class="fas ' + icon + '" aria-hidden="true"></i><span>' + escapeHtml(row.label) + '<small>' + escapeHtml(detail) + '</small></span></li>';
                    }).join('');
                });
                const pinLine = dialog.querySelector('[data-sb-review-pin]');
                if (pinLine) {
                    pinLine.className = 'sb-review-pin ' + (pinOk() ? 'is-ok' : 'is-missing');
                    pinLine.innerHTML = pinOk()
                        ? '<i class="fas fa-lock" aria-hidden="true"></i> Financial PIN set'
                        : '<i class="fas fa-circle-exclamation" aria-hidden="true"></i> Set and confirm your 6-digit financial PIN';
                }
            }

            function show(n, focus) {
                step = Math.max(1, Math.min(3, n));
                panels.forEach(function (panel) { panel.hidden = false; });
                if (submit) submit.hidden = false;
                refresh();
                if (focus) {
                    const title = dialog.querySelector('[data-sb-panel="' + step + '"] .sb-panel-title');
                    if (title) {
                        title.setAttribute('tabindex', '-1');
                        title.scrollIntoView({ block: 'start', behavior: 'smooth' });
                        title.focus({ preventScroll: true });
                    }
                }
            }

            form.addEventListener('input', refresh);
            form.addEventListener('change', refresh);
            dialog.querySelectorAll('[data-upload-remove]').forEach(function (button) {
                button.addEventListener('click', function () { setTimeout(function () { refresh(); dialog.querySelectorAll('[data-bulk-uploader]').forEach(function (uploader) { uploader.dispatchEvent(new Event('bulk:refresh')); }); }, 0); });
            });
            form.addEventListener('bid:incomplete', function () {
                show(!stepComplete(1) ? 1 : (!stepComplete(2) ? 2 : 3), true);
            });

            // After a refused submission, focus the section that contains the first error.
            const firstError = form.querySelector('[data-sb-panel] .sb-error:not(:empty), [data-sb-panel] .is-invalid');
            const errorPanel = firstError ? firstError.closest('[data-sb-panel]') : null;
            show(errorPanel ? Number(errorPanel.dataset.sbPanel) : 1, false);
        });
    });
</script>
