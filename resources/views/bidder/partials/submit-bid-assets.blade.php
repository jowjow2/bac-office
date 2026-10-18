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
    .sb-notes { padding: 14px; border: 1px solid var(--sb-line); border-radius: 10px; background: var(--sb-soft); }
    /* Financial offer: one card, a labelled row for the price and one for the PIN. */
    .sb-offer { border: 1px solid var(--sb-line); border-radius: 12px; background: #fff; }
    .sb-offer-row { display: grid; grid-template-columns: minmax(0, 210px) minmax(0, 1fr); gap: 10px 28px; padding: 18px 20px; }
    .sb-offer-row + .sb-offer-row { border-top: 1px solid var(--sb-line); }
    .sb-offer-label .sb-hint { margin: 4px 0 0; }
    .sb-offer-control { display: grid; align-content: start; gap: 8px; min-width: 0; }
    .sb-money { position: relative; max-width: 460px; }
    .sb-money-prefix { position: absolute; top: 50%; left: 14px; transform: translateY(-50%); color: var(--ui-muted); font-size: 18px; font-weight: 600; }
    :is(.sb, #sb-x#sb-x) .sb-money-input { min-height: 48px !important; padding-left: 34px !important; font-size: 20px !important; font-weight: 700; font-variant-numeric: tabular-nums; }
    .sb-compare { margin: 0; color: var(--ui-muted); font-size: 12.5px; }
    .sb-money-cap { margin: 0; color: var(--ui-muted); font-size: 12.5px; }
    .sb-money-cap strong { color: var(--ui-ink-2); font-weight: 600; }
    .sb-compare:not(:empty) ~ .sb-money-cap { display: none; }
    .sb-compare:empty { display: none; }
    .sb b.is-under { color: var(--ui-success); }
    .sb b.is-over { color: var(--ui-danger); }
    .sb-error { display: block; color: var(--ui-danger); font-size: 12px; font-weight: 600; }
    .sb-error:empty { display: none; }
    .sb-pin-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 16px; max-width: 460px; }
    .sb-pin-field { display: grid; gap: 6px; min-width: 0; }
    .sb-pin-top { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; }
    .sb-pin-top > label { color: var(--ui-ink-2); font-size: 12.5px; font-weight: 600; }
    .sb-pin-control { position: relative; }
    :is(.sb, #sb-x#sb-x) .sb-pin-input { width: 100% !important; min-height: 48px !important; padding: 8px 44px 8px calc(12px + .45em) !important; font-family: var(--ui-mono), monospace; font-size: 20px !important; letter-spacing: .45em; text-align: center; }
    :is(.sb, #sb-x#sb-x) .sb-pin-input.is-match { border-color: var(--ui-success) !important; }
    :is(.sb, #sb-x#sb-x) .sb-pin-input.is-mismatch { border-color: var(--ui-danger) !important; background: var(--ui-danger-soft) !important; animation: sb-pin-shake .25s ease 1; }
    .sb .sb-pin-input::placeholder { letter-spacing: .3em; }
    .sb .sb-pin-eye { position: absolute; top: 50%; right: 6px; display: grid; place-items: center; width: 34px; height: 34px; border: 0; border-radius: 8px; background: transparent; color: var(--ui-muted); transform: translateY(-50%); cursor: pointer; }
    .sb .sb-pin-eye:hover, .sb .sb-pin-eye:focus-visible { background: var(--sb-soft); color: var(--ui-ink); }
    .sb-pin-count { color: var(--ui-subtle); font-size: 11.5px; font-variant-numeric: tabular-nums; }
    .sb-pin-count.is-complete { color: var(--ui-success); font-weight: 600; }
    .sb-pin-status { display: flex; align-items: center; gap: 8px; max-width: 460px; margin: 0; padding: 0; border: 1px solid transparent; border-radius: 9px; color: var(--ui-muted); font-size: 12.5px; font-weight: 500; }
    .sb-pin-status[data-state="match"], .sb-pin-status[data-state="mismatch"] { padding: 8px 12px; font-weight: 600; }
    .sb-pin-status[data-state="match"] { border-color: var(--ui-success-line); background: var(--ui-success-soft); color: var(--ui-success); }
    .sb-pin-status[data-state="mismatch"] { border-color: var(--ui-danger-line); border-left: 4px solid var(--ui-danger); background: var(--ui-danger-soft); color: var(--ui-danger); }
    .sb-pin-note { display: flex; align-items: baseline; gap: 8px; margin: 2px 0 0; color: var(--ui-muted); font-size: 12px; line-height: 1.5; }
    .sb-pin-note i { color: var(--ui-subtle); font-size: 11px; }
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
    /* Sealed-bid guide (sealed-bid-guide): manual projects file nothing online. */
    .sg { display: grid; gap: 14px; }
    .sg-status { display: flex; align-items: flex-start; gap: 10px; margin: 0; padding: 12px 14px; border: 1px solid var(--sb-line); border-radius: 10px; background: #fff; color: var(--ui-ink-2); font-size: 13px; line-height: 1.5; }
    .sg-status i { margin-top: 3px; color: var(--ui-subtle); }
    .sg-status.is-done { border-color: var(--ui-success-line); background: var(--ui-success-soft); color: var(--ui-success); }
    .sg-status.is-done i { color: var(--ui-success); }
    .sg-note { display: flex; gap: 8px; margin: 0; color: var(--ui-muted); font-size: 12.5px; }
    .sg-steps { display: grid; gap: 0; margin: 0; padding: 0; list-style: none; }
    .sg-step { position: relative; display: grid; grid-template-columns: 30px minmax(0, 1fr); gap: 14px; padding: 0 0 20px; }
    .sg-step:not(:last-child)::before { content: ""; position: absolute; top: 32px; bottom: 4px; left: 14px; width: 2px; background: var(--sb-line); }
    .sg-step__num { display: grid; width: 30px; height: 30px; place-items: center; border-radius: 50%; background: var(--ui-primary); color: #fff; font-size: 13px; font-weight: 700; }
    .sg-step.is-done .sg-step__num { background: var(--ui-success-soft); color: var(--ui-success); }
    .sg-step.is-done .sg-step__num::after { content: "\f00c"; font-family: "Font Awesome 6 Free"; font-weight: 900; font-size: 12px; }
    .sg-step h3 { margin: 4px 0 4px; color: var(--ui-ink); font-size: 14px; font-weight: 700; }
    .sg-step p { margin: 0; color: var(--ui-ink-2); font-size: 13px; line-height: 1.55; }
    .sg-step p + p { margin-top: 6px; }
    .sg-muted { color: var(--ui-muted) !important; font-size: 12.5px !important; }
    .sg-envelopes { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 12px; }
    .sg-envelope { padding: 12px 14px; border: 1px solid var(--sb-line); border-radius: 10px; background: #fff; }
    .sg-envelope h4 { display: flex; align-items: center; gap: 8px; margin: 0; color: var(--ui-ink); font-size: 13px; font-weight: 700; }
    .sg-envelope h4 i { color: var(--ui-primary); }
    .sg-envelope__hint { margin: 2px 0 8px !important; color: var(--ui-muted) !important; font-size: 12px !important; }
    .sg-envelope ul { display: grid; gap: 6px; margin: 0; padding: 0; list-style: none; }
    .sg-envelope li { display: grid; grid-template-columns: 14px minmax(0, 1fr); gap: 8px; color: var(--ui-ink); font-size: 12.5px; line-height: 1.4; }
    .sg-envelope li > i { margin-top: 2px; color: var(--ui-subtle); }
    .sg-envelope li em { color: var(--ui-muted); font-style: normal; font-size: 11.5px; }
    .sg-envelope li small { display: block; color: var(--ui-muted); font-size: 11.5px; }
    /* Keep project requirements legible inside the expandable notice. */
    body .admin-dashboard .bidder-submit-modal-overlay .sb .sb-context-files .bidder-requirements-card { display: grid !important; gap: 10px !important; margin: 0 !important; padding: 12px 14px !important; border: 1px solid var(--sb-line) !important; border-radius: 10px !important; background: #fff !important; box-shadow: none !important; color: var(--ui-ink) !important; }
    body .admin-dashboard .bidder-submit-modal-overlay .sb .sb-context-files .bidder-requirements-heading { display: block !important; margin: 0 !important; padding: 0 !important; border: 0 !important; background: transparent !important; }
    body .admin-dashboard .bidder-submit-modal-overlay .sb .sb-context-files .bidder-requirements-heading h4 { margin: 0 !important; color: var(--ui-ink-2) !important; font-size: 12px !important; font-weight: 700 !important; letter-spacing: .02em !important; text-transform: uppercase !important; }
    body .admin-dashboard .bidder-submit-modal-overlay .sb .sb-context-files .bidder-requirements-heading p { margin: 3px 0 0 !important; color: var(--ui-muted) !important; font-size: 12px !important; line-height: 1.45 !important; }
    body .admin-dashboard .bidder-submit-modal-overlay .sb .sb-context-files .bidder-requirements-icon { display: none !important; }
    body .admin-dashboard .bidder-submit-modal-overlay .sb .sb-context-files .bidder-requirements-grid { display: grid !important; grid-template-columns: minmax(0, 1fr) !important; gap: 10px !important; margin: 0 !important; padding: 10px 0 0 !important; border-top: 1px solid var(--sb-line) !important; }
    body .admin-dashboard .bidder-submit-modal-overlay .sb .sb-context-files .bidder-requirement-section { margin: 0 !important; padding: 0 !important; border: 0 !important; border-radius: 0 !important; background: transparent !important; box-shadow: none !important; }
    body .admin-dashboard .bidder-submit-modal-overlay .sb .sb-context-files .bidder-requirement-section strong { display: block !important; margin: 0 0 2px !important; color: var(--ui-ink) !important; font-size: 12px !important; font-weight: 700 !important; }
    body .admin-dashboard .bidder-submit-modal-overlay .sb .sb-context-files .bidder-requirement-section p { margin: 0 !important; color: var(--ui-ink-2) !important; font-size: 12.5px !important; line-height: 1.5 !important; white-space: pre-line !important; overflow-wrap: anywhere !important; }

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
        .sg-envelopes { grid-template-columns: 1fr; }
        .sb-offer-row { grid-template-columns: minmax(0, 1fr); gap: 10px; padding: 14px 16px; }
        .sb-money { max-width: none; }
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
        .sb-file-actions { grid-column: 1 / -1; }
        .sb-file-actions .sb-btn { flex: 1; }
        .sb-foot { flex-direction: column; align-items: stretch; padding: 10px 16px; }
        .sb-foot-actions { display: grid; grid-auto-columns: minmax(0, 1fr); grid-auto-flow: column; }
        .sb-foot-actions > [data-close-modal]:not(.sb-btn--primary) { display: none; }
        .sb .sb-btn { padding: 0 10px; }
        .sb-status { text-align: center; }
    }

    /* Submission workspace: keep the task in one wide column and tuck the
       full project notice behind a disclosure above the form. */
    .bidder-submit-modal-overlay .sb { width: min(1160px, calc(100vw - 32px)); max-height: min(92dvh, calc(100dvh - 20px)); border-radius: 18px; }
    .bidder-submit-modal-overlay .sb-head-main { flex: 1 1 auto; }
    .bidder-submit-modal-overlay .sb-head { padding: 22px 30px 18px; background: linear-gradient(120deg, #fff 68%, #f1f7f3); }
    .bidder-submit-modal-overlay .sb-title { font-size: clamp(22px, 2vw, 27px); letter-spacing: -.025em; }
    .bidder-submit-modal-overlay .sb-project { margin-top: 4px; font-size: 15px; }
    .sb-seal-note { display: flex; align-items: flex-start; gap: 7px; margin: 9px 0 0; color: var(--ui-muted); font-size: 12px; line-height: 1.45; }
    .sb-seal-note i { margin-top: 2px; color: var(--ui-primary); }
    .bidder-submit-modal-overlay .sb-body { display: block; overflow-y: auto; }
    .bidder-submit-modal-overlay .sb-main { padding: 0 30px 30px; }
    .bidder-submit-modal-overlay .sb-form { gap: 18px; }
    .bidder-submit-modal-overlay .sb-steps { top: 0; gap: 10px; margin: 0 -30px 22px; padding: 13px 30px; background: #f7f9f7; box-shadow: 0 4px 10px rgba(27, 36, 32, .03); }
    .bidder-submit-modal-overlay .sb-step { justify-content: flex-start; gap: 12px; min-height: 56px; padding: 9px 14px; border-color: var(--sb-line); border-radius: 11px; background: #fff; }
    .bidder-submit-modal-overlay .sb-step.is-current { border-color: var(--ui-primary); background: var(--ui-primary-soft); box-shadow: inset 0 0 0 1px var(--ui-primary); }
    .bidder-submit-modal-overlay .sb-step small { display: block; margin-top: 2px; color: var(--ui-muted); font-size: 11px; line-height: 1.2; }
    .bidder-submit-modal-overlay .sb-step__number { flex-basis: 28px; height: 28px; font-size: 12px; }
    .bidder-submit-modal-overlay .sb-panel { gap: 16px; }
    .bidder-submit-modal-overlay .sb-panel-head { align-items: center; padding-bottom: 10px; border-bottom: 1px solid var(--sb-line); }
    .bidder-submit-modal-overlay .sb-panel-title { font-size: 17px; }
    .bidder-submit-modal-overlay .sb-panel-hint { font-size: 12.5px; }
    .bidder-submit-modal-overlay .sb-files { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .bidder-submit-modal-overlay .sb-file { min-height: 104px; grid-template-columns: 36px minmax(0, 1fr); align-content: center; gap: 8px 11px; padding: 13px 15px; }
    .bidder-submit-modal-overlay .sb-file-icon { align-self: start; width: 36px; height: 36px; }
    .bidder-submit-modal-overlay .sb-file-actions { grid-column: 2; justify-content: flex-start; }
    .bidder-submit-modal-overlay .sb-file-title { font-size: 13px; line-height: 1.35; }
    .bidder-submit-modal-overlay .sb-context { margin: 0; border-bottom: 1px solid var(--sb-line); background: #fff; }
    .bidder-submit-modal-overlay .sb-context > summary { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 7px 18px; padding: 12px 30px; color: var(--ui-ink-2); cursor: pointer; list-style-position: inside; }
    .bidder-submit-modal-overlay .sb-context > summary:hover { background: var(--sb-soft); }
    .bidder-submit-modal-overlay .sb-context-title { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; }
    .bidder-submit-modal-overlay .sb-context-title i { color: var(--ui-primary); }
    .bidder-submit-modal-overlay .sb-context-times { color: var(--ui-muted); font-size: 12px; }
    .bidder-submit-modal-overlay .sb-context-times > span { margin: 0 6px; }
    .bidder-submit-modal-overlay .sb-context-content { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 16px; padding: 4px 30px 20px; }
    .bidder-submit-modal-overlay .sb-context-card, .bidder-submit-modal-overlay .sb-context-files { min-width: 0; padding: 16px; border: 1px solid var(--sb-line); border-radius: 12px; background: #fff; }
    .bidder-submit-modal-overlay .sb-context-card h3 { margin: 0 0 9px; color: var(--ui-ink); font-size: 14px; }
    .bidder-submit-modal-overlay .sb-context-files { display: grid; align-content: start; gap: 16px; }
    .bidder-submit-modal-overlay .sb-context .sb-notice { margin: 0; }
    .bidder-submit-modal-overlay .sb-context .sb-notice > div { gap: 16px; }
    .bidder-submit-modal-overlay .sb-context .sb-notice dd { max-width: 55%; }
    .bidder-submit-modal-overlay .sb-foot { padding: 13px 30px; box-shadow: 0 -8px 20px rgba(27, 36, 32, .04); }
    .bidder-submit-modal-overlay .sb-status { max-width: 58%; line-height: 1.4; }
    @media (max-width: 900px) {
        .bidder-submit-modal-overlay .sb-files { grid-template-columns: 1fr; }
        .bidder-submit-modal-overlay .sb-context-content { grid-template-columns: 1fr; }
    }
    @media (max-width: 640px) {
        .bidder-submit-modal-overlay .sb { width: 100%; max-height: calc(100dvh - 12px); border-radius: 13px; }
        .bidder-submit-modal-overlay .sb-head { padding: 16px; }
        .bidder-submit-modal-overlay .sb-main { padding: 0 16px 20px; }
        .bidder-submit-modal-overlay .sb-steps { gap: 5px; margin: 0 -16px 18px; padding: 9px 16px; }
        .bidder-submit-modal-overlay .sb-step { min-height: 42px; padding: 6px 7px; }
        .bidder-submit-modal-overlay .sb-step:not(.is-current) { flex: 0 0 auto; }
        .bidder-submit-modal-overlay .sb-step small { display: none; }
        .bidder-submit-modal-overlay .sb-step__number { flex-basis: 24px; height: 24px; }
        .bidder-submit-modal-overlay .sb-context > summary { padding: 12px 16px; }
        .bidder-submit-modal-overlay .sb-context-content { padding: 4px 16px 18px; }
        .bidder-submit-modal-overlay .sb-foot { padding: 11px 16px; }
        .bidder-submit-modal-overlay .sb-status { max-width: none; }
    }

    /* ---------- Professional pass: header facts, payment path, component cards ---------- */
    .bidder-submit-modal-overlay .sb { border-radius: 18px; box-shadow: 0 30px 80px rgba(15, 25, 21, .35); }
    .bidder-submit-modal-overlay .sb-head-main { flex: 1 1 auto; }
    .bidder-submit-modal-overlay .sb-head { padding: 22px 30px 18px; border-bottom: 0; background: linear-gradient(180deg, var(--ui-surface-2, #f7faf8), #fff); }
    .bidder-submit-modal-overlay .sb-eyebrow { gap: 8px; margin-bottom: 8px; }
    .bidder-submit-modal-overlay .sb-eyebrow span { padding: 3px 10px; border-radius: 999px; background: var(--ui-primary-soft, #e8f3ee); color: var(--ui-primary, #1f5c45); font-size: 11px; font-weight: 700; letter-spacing: .02em; }
    .bidder-submit-modal-overlay .sb-eyebrow code { padding: 3px 9px; border: 1px solid var(--sb-line); border-radius: 999px; background: #fff; font-size: 11px; }
    .bidder-submit-modal-overlay .sb-title { font-size: 24px; letter-spacing: -.015em; }
    .bidder-submit-modal-overlay .sb-project { margin-top: 4px; color: var(--ui-muted); font-size: 15px; font-weight: 500; line-height: 1.4; }
    .bidder-submit-modal-overlay .sb-seal-note { align-items: center; margin-top: 12px; padding: 9px 12px; border: 1px solid var(--ui-primary-line, #cfe3d9); border-radius: 10px; background: var(--ui-primary-soft, #e8f3ee); color: var(--ui-ink-2); }
    .bidder-submit-modal-overlay .sb-facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0; margin: 14px 0 0; border: 1px solid var(--sb-line); border-radius: 12px; background: #fff; overflow: hidden; }
    .bidder-submit-modal-overlay .sb-facts > div { padding: 11px 14px; border-right: 1px solid var(--sb-line); min-width: 0; }
    .bidder-submit-modal-overlay .sb-facts > div:last-child { border-right: 0; }
    .bidder-submit-modal-overlay .sb-facts dt { color: var(--ui-muted); font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .bidder-submit-modal-overlay .sb-facts dd { margin: 3px 0 0; color: var(--ui-ink); font-size: 14px; font-weight: 700; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
    .bidder-submit-modal-overlay .sb-facts dd small { display: block; margin-top: 1px; color: var(--ui-muted); font-size: 11.5px; font-weight: 600; }
    .bidder-submit-modal-overlay .sb-facts .is-warn dd small, .bidder-submit-modal-overlay .sb-facts .is-warn dd { color: var(--ui-warning); }
    .bidder-submit-modal-overlay .sb-facts .is-ok dd { color: var(--ui-success); }
    .bidder-submit-modal-overlay .sb-close { width: 38px; height: 38px; border-radius: 11px; }
    .bidder-submit-modal-overlay .sb-main { padding: 22px 30px 26px; }

    .bidder-submit-modal-overlay .sb-flow { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin: 0; padding: 0; list-style: none; counter-reset: none; }
    .bidder-submit-modal-overlay .sb-flow li { position: relative; display: grid; grid-template-columns: 30px minmax(0, 1fr); column-gap: 10px; padding: 12px 14px; border: 1px solid var(--sb-line); border-radius: 12px; background: #fff; }
    .bidder-submit-modal-overlay .sb-flow li > span { grid-row: 1 / span 2; display: grid; width: 30px; height: 30px; place-items: center; border-radius: 50%; background: var(--sb-soft); color: var(--ui-muted); font-size: 13px; font-weight: 700; }
    .bidder-submit-modal-overlay .sb-flow li strong { color: var(--ui-ink-2); font-size: 13px; }
    .bidder-submit-modal-overlay .sb-flow li small { color: var(--ui-muted); font-size: 11.5px; line-height: 1.4; }
    .bidder-submit-modal-overlay .sb-flow li.is-current { border-color: var(--ui-primary, #1f5c45); background: var(--ui-primary-soft, #e8f3ee); }
    .bidder-submit-modal-overlay .sb-flow li.is-current > span { background: var(--ui-primary, #1f5c45); color: #fff; }
    .bidder-submit-modal-overlay .sb-flow li.is-current strong { color: var(--ui-ink); }

    .bidder-submit-modal-overlay .sb-pay { grid-template-columns: 230px minmax(0, 1fr); gap: 0; padding: 0; overflow: hidden; border: 1px solid var(--ui-warning-line); border-radius: 14px; background: #fff; }
    .bidder-submit-modal-overlay .sb-pay-box { display: grid; align-content: center; gap: 4px; padding: 22px 20px; background: var(--ui-warning-soft); border-right: 1px solid var(--ui-warning-line); }
    .bidder-submit-modal-overlay .sb-pay-box span { color: var(--ui-warning); font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .bidder-submit-modal-overlay .sb-pay-box strong { color: var(--ui-ink); font-size: 30px; font-weight: 800; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
    .bidder-submit-modal-overlay .sb-pay-box small { color: var(--ui-muted); font-size: 11.5px; }
    .bidder-submit-modal-overlay .sb-pay-main { padding: 18px 22px; }
    .bidder-submit-modal-overlay .sb-pay-main h3 { font-size: 16px; }
    .bidder-submit-modal-overlay .sb-pay-details { margin: 12px 0 10px; }
    .bidder-submit-modal-overlay .sb-pay-details div { grid-template-columns: 100px minmax(0, 1fr); padding: 7px 0; border-top: 1px solid var(--ui-line-soft, #eef2ef); }
    .bidder-submit-modal-overlay .sb-pay-details div:first-child { border-top: 0; }

    .bidder-submit-modal-overlay .sb-comp-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .bidder-submit-modal-overlay .sb-comp { padding: 16px 18px; border: 1px solid var(--sb-line); border-radius: 14px; background: #fff; }
    .bidder-submit-modal-overlay .sb-comp .sb-group-title { display: flex; align-items: center; gap: 9px; margin: 0; font-size: 14px; }
    .bidder-submit-modal-overlay .sb-comp .sb-group-title > span { display: grid; flex: 0 0 24px; width: 24px; height: 24px; place-items: center; border-radius: 50%; background: var(--ui-primary-soft, #e8f3ee); color: var(--ui-primary, #1f5c45); font-size: 12px; }
    .bidder-submit-modal-overlay .sb-comp .sb-group-title small { margin-left: auto; color: var(--ui-muted); font-size: 11.5px; font-weight: 600; white-space: nowrap; }
    .bidder-submit-modal-overlay .sb-comp-hint { margin: 6px 0 10px; color: var(--ui-muted); font-size: 12px; line-height: 1.45; }
    .bidder-submit-modal-overlay .sb-comp .sb-checklist { gap: 0; }
    .bidder-submit-modal-overlay .sb-comp .sb-checklist li { display: flex; align-items: flex-start; gap: 8px; padding: 8px 0; border-top: 1px solid var(--ui-line-soft, #eef2ef); line-height: 1.4; }
    .bidder-submit-modal-overlay .sb-comp .sb-checklist li:first-child { border-top: 0; }
    .bidder-submit-modal-overlay .sb-comp .sb-checklist .sb-tag { margin-left: auto; flex: 0 0 auto; }

    .bidder-submit-modal-overlay .sb-foot { padding: 14px 30px; background: var(--ui-surface-2, #f7faf8); box-shadow: none; }
    .bidder-submit-modal-overlay .sb-btn { min-height: 42px; padding: 0 20px; border-radius: 11px; font-weight: 700; }
    .bidder-submit-modal-overlay .sb-btn--primary:disabled { border-color: var(--ui-line); background: var(--ui-line-soft, #eef2ef); color: var(--ui-subtle); cursor: not-allowed; }

    @media (max-width: 900px) {
        .bidder-submit-modal-overlay .sb-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .bidder-submit-modal-overlay .sb-facts > div:nth-child(2) { border-right: 0; }
        .bidder-submit-modal-overlay .sb-facts > div:nth-child(-n+2) { border-bottom: 1px solid var(--sb-line); }
        .bidder-submit-modal-overlay .sb-comp-grid, .bidder-submit-modal-overlay .sb-flow { grid-template-columns: 1fr; }
        .bidder-submit-modal-overlay .sb-pay { grid-template-columns: 1fr; }
        .bidder-submit-modal-overlay .sb-pay-box { border-right: 0; border-bottom: 1px solid var(--ui-warning-line); padding: 16px 20px; }
    }
    @media (max-width: 640px) {
        .bidder-submit-modal-overlay .sb-head { padding: 16px 16px 14px; }
        .bidder-submit-modal-overlay .sb-title { font-size: 20px; }
        .bidder-submit-modal-overlay .sb-main { padding: 16px 16px 20px; }
        .bidder-submit-modal-overlay .sb-pay-box strong { font-size: 26px; }
        .bidder-submit-modal-overlay .sb-pay-main { padding: 16px; }
    }

    /* ---------- Upload rows: numbered requirement cards ---------- */
    .bidder-submit-modal-overlay .sb-panel-head { align-items: center; padding-bottom: 12px; border-bottom: 1px solid var(--sb-line); }
    .bidder-submit-modal-overlay .sb-panel-title { font-size: 17px; letter-spacing: -.01em; }
    .bidder-submit-modal-overlay .sb-count { padding: 5px 12px; border: 1px solid var(--sb-line); background: #fff; font-size: 12px; }
    .bidder-submit-modal-overlay .sb-count.is-done { border-color: var(--ui-success-line); }
    .bidder-submit-modal-overlay .sb-files { counter-reset: sbfile; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .bidder-submit-modal-overlay .sb-file { counter-increment: sbfile; grid-template-columns: 40px minmax(0, 1fr); grid-template-areas: "icon copy" "icon actions"; align-items: start; gap: 4px 14px; padding: 16px; border-radius: 14px; box-shadow: 0 1px 2px rgba(27, 36, 32, .04); }
    .bidder-submit-modal-overlay .sb-file:hover { border-color: var(--ui-line-strong); }
    .bidder-submit-modal-overlay .sb-file-icon { grid-area: icon; position: relative; width: 40px; height: 40px; border-radius: 12px; }
    .bidder-submit-modal-overlay .sb-file-icon::after { content: counter(sbfile, decimal-leading-zero); position: absolute; right: -6px; top: -8px; padding: 0 5px; border-radius: 999px; background: var(--ui-ink-2); color: #fff; font-size: 9.5px; font-weight: 700; line-height: 15px; }
    .bidder-submit-modal-overlay .sb-file-copy { grid-area: copy; gap: 3px; }
    .bidder-submit-modal-overlay .sb-file-title { font-size: 13.5px; line-height: 1.35; }
    .bidder-submit-modal-overlay .sb-file-meta { font-size: 11.5px; }
    .bidder-submit-modal-overlay .sb-file-chosen { display: inline-flex; align-items: center; gap: 6px; width: fit-content; max-width: 100%; margin-top: 4px; padding: 3px 10px; border-radius: 999px; background: var(--sb-soft); color: var(--ui-muted); font-size: 11.5px; }
    .bidder-submit-modal-overlay .sb-file.has-file .sb-file-chosen { background: #fff; color: var(--ui-success); }
    .bidder-submit-modal-overlay .sb-file.has-file .sb-file-chosen::before { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; font-size: 10px; }
    .bidder-submit-modal-overlay .sb-file-actions { grid-area: actions; justify-content: flex-start; margin-top: 8px; }
    .bidder-submit-modal-overlay .sb-file-actions .sb-btn { min-height: 34px; padding: 0 14px; border-radius: 9px; font-size: 12.5px; }
    .bidder-submit-modal-overlay .sb-file-actions [data-upload-change]::before { content: '\f093'; margin-right: 7px; font-family: 'Font Awesome 6 Free'; font-weight: 900; font-size: 11px; }
    @media (max-width: 900px) { .bidder-submit-modal-overlay .sb-files { grid-template-columns: 1fr; } }

    /* ---------- Second pass: slim header, summary scrolls with the form, compact upload rows ---------- */
    .bidder-submit-modal-overlay .sb-head { padding: 20px 30px 16px; background: #fff; border-bottom: 1px solid var(--sb-line); }
    .bidder-submit-modal-overlay .sb-title { font-size: 22px; }
    .bidder-submit-modal-overlay .sb-project { margin-top: 2px; font-size: 14px; }
    .bidder-submit-modal-overlay .sb-summary { padding: 18px 30px 4px; background: #fff; }
    .bidder-submit-modal-overlay .sb-summary .sb-seal-note { margin: 0 0 12px; }
    .bidder-submit-modal-overlay .sb-summary .sb-facts { margin: 0; }
    .bidder-submit-modal-overlay .sb-context > summary { padding-top: 14px; }

    .bidder-submit-modal-overlay .sb-files { grid-template-columns: 1fr; gap: 8px; }
    .bidder-submit-modal-overlay .sb-file { grid-template-columns: 44px minmax(0, 1fr) auto; grid-template-areas: "icon copy actions"; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; box-shadow: none; }
    .bidder-submit-modal-overlay .sb-file-actions { margin: 0; justify-content: flex-end; flex-wrap: nowrap; }
    .bidder-submit-modal-overlay .sb-file-chosen { margin-top: 2px; }
    .bidder-submit-modal-overlay .sb-file-title { font-size: 13.5px; }
    @media (max-width: 640px) {
        .bidder-submit-modal-overlay .sb-summary { padding: 14px 16px 2px; }
        .bidder-submit-modal-overlay .sb-file { grid-template-columns: 40px minmax(0, 1fr); grid-template-areas: "icon copy" "actions actions"; }
        .bidder-submit-modal-overlay .sb-file-actions { justify-content: flex-start; }
    }

    /* ---------- Requirement list as one clean table ---------- */
    .bidder-submit-modal-overlay .sb-filetable { overflow: hidden; border: 1px solid var(--sb-line); border-radius: 14px; background: #fff; }
    .bidder-submit-modal-overlay .sb-files-head { display: grid; grid-template-columns: 52px minmax(0, 1.7fr) minmax(0, 1fr) 150px; gap: 14px; padding: 10px 18px; border-bottom: 1px solid var(--sb-line); background: var(--ui-surface-2, #f7faf8); color: var(--ui-muted); font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .bidder-submit-modal-overlay .sb-filetable .sb-files { display: block; gap: 0; margin: 0; padding: 0; counter-reset: sbfile; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file { display: grid; grid-template-columns: 52px minmax(0, 1.7fr) minmax(0, 1fr) 150px; grid-template-areas: "icon copy status actions"; align-items: center; gap: 14px; margin: 0; padding: 14px 18px; border: 0; border-top: 1px solid var(--ui-line-soft, #eef2ef); border-radius: 0; background: #fff; box-shadow: none; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file:first-child { border-top: 0; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file:hover { background: var(--ui-surface-2, #f7faf8); }
    .bidder-submit-modal-overlay .sb-filetable .sb-file.has-file { background: var(--ui-success-soft, #eaf6ef); }
    .bidder-submit-modal-overlay .sb-filetable .sb-file.is-invalid { background: var(--ui-danger-soft, #fdf0ee); }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-icon { grid-area: icon; width: 30px; height: 30px; border-radius: 50%; background: var(--sb-soft); color: var(--ui-ink-2); font-size: 12px; font-weight: 700; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-icon i { display: none; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-icon::after { position: static; padding: 0; background: transparent; color: inherit; font-size: 12px; line-height: 1; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file.has-file .sb-file-icon { background: var(--ui-success, #1f7a4d); color: #fff; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file.has-file .sb-file-icon::after { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; font-size: 11px; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-copy { grid-area: copy; gap: 3px; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-status { grid-area: status; display: grid; gap: 3px; min-width: 0; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-chosen { display: flex; align-items: center; gap: 7px; width: auto; margin: 0; padding: 0; border-radius: 0; background: transparent; color: var(--ui-muted); font-size: 12.5px; font-weight: 500; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-chosen::before { content: ''; flex: 0 0 8px; width: 8px; height: 8px; border-radius: 50%; background: var(--ui-line-strong, #cfd8d3); }
    .bidder-submit-modal-overlay .sb-filetable .sb-file.has-file .sb-file-chosen { background: transparent; color: var(--ui-success, #1f7a4d); font-weight: 700; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file.has-file .sb-file-chosen::before { content: ''; font-family: inherit; background: var(--ui-success, #1f7a4d); font-size: 0; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-actions { grid-area: actions; justify-content: flex-end; margin: 0; gap: 8px; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-actions .sb-btn { min-height: 34px; padding: 0 12px; }
    @media (max-width: 760px) {
        .bidder-submit-modal-overlay .sb-files-head { display: none; }
        .bidder-submit-modal-overlay .sb-filetable .sb-file { grid-template-columns: 34px minmax(0, 1fr); grid-template-areas: "icon copy" "icon status" "actions actions"; row-gap: 6px; }
        .bidder-submit-modal-overlay .sb-filetable .sb-file-actions { justify-content: flex-start; padding-left: 48px; }
    }

    /* ---------- Requirement rows: number, name with its tags, and one action ---------- */
    .bidder-submit-modal-overlay .sb-files-head { grid-template-columns: 42px minmax(0, 1fr) 130px; padding: 8px 16px; }
    .bidder-submit-modal-overlay .sb-files-head span:last-child { text-align: right; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file { grid-template-columns: 42px minmax(0, 1fr) auto; grid-template-areas: "icon copy actions"; align-items: start; gap: 4px 14px; padding: 11px 16px; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-icon { width: 28px; height: 28px; margin-top: 0; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-copy { display: grid; gap: 3px; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-title { display: block; font-size: 13px; font-weight: 600; line-height: 1.35; }
    .bidder-submit-modal-overlay .sb-file-sub { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; }
    .bidder-submit-modal-overlay .sb-file-sub .sb-file-meta { font-size: 11.5px; line-height: 1.35; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-status { display: grid; gap: 2px; margin-top: 0; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-chosen { font-size: 12px; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-actions { align-self: center; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-actions .sb-btn { min-width: 112px; min-height: 30px; justify-content: center; }
    @media (max-width: 760px) {
        .bidder-submit-modal-overlay .sb-filetable .sb-file { grid-template-columns: 34px minmax(0, 1fr); grid-template-areas: "icon copy" "actions actions"; padding: 14px 16px; }
        .bidder-submit-modal-overlay .sb-filetable .sb-file-actions { padding-left: 50px; align-self: start; }
        .bidder-submit-modal-overlay .sb-filetable .sb-file-actions .sb-btn { flex: 0 1 auto; }
    }

    /* ---------- Requirement rows in the plain list style of the registration form ---------- */
    .bidder-submit-modal-overlay .sb-files-head { display: none; }
    .bidder-submit-modal-overlay .sb-filetable { border-radius: 10px; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file { grid-template-columns: 38px minmax(0, 1fr) auto; align-items: center; gap: 12px; padding: 10px 14px; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-icon { width: 36px; height: 36px; border-radius: 8px; background: var(--sb-soft); color: var(--ui-ink-2); font-size: 14px; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-icon i { display: inline; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-icon::after, .bidder-submit-modal-overlay .sb-filetable .sb-file.has-file .sb-file-icon::after { content: none; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file.has-file .sb-file-icon { background: var(--ui-success, #1f7a4d); color: #fff; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-copy { gap: 1px; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-title { font-size: 13px; }
    .bidder-submit-modal-overlay .sb-star { color: var(--ui-danger, #b42318); font-weight: 700; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-meta { font-size: 11.5px; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file:not(.has-file) .sb-file-chosen { display: none; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-chosen::before { display: none; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file.has-file .sb-file-chosen { display: block; font-size: 12px; font-weight: 600; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-actions .sb-btn { min-width: 84px; min-height: 30px; padding: 0 12px; font-size: 12.5px; }
    .bidder-submit-modal-overlay .sb-filetable .sb-file-actions [data-upload-change]::before { content: none; margin: 0; }
    @media (max-width: 760px) {
        .bidder-submit-modal-overlay .sb-filetable .sb-file { grid-template-columns: 34px minmax(0, 1fr) auto; padding: 10px 12px; }
        .bidder-submit-modal-overlay .sb-filetable .sb-file-actions { padding-left: 0; grid-column: auto; }
    }

    /* ---------- Compact dialog chrome: smaller header, step tabs, panel head and footer ---------- */
    @media (min-width: 761px) {
        .bidder-submit-modal-overlay .sb { width: min(940px, calc(100vw - 32px)); border-radius: 14px; }
        .bidder-submit-modal-overlay .sb-head { padding: 14px 24px 12px; }
        .bidder-submit-modal-overlay .sb-title { font-size: 19px; line-height: 1.2; }
        .bidder-submit-modal-overlay .sb-project { margin-top: 2px; font-size: 13px; }
        .bidder-submit-modal-overlay .sb-main { padding: 0 24px 18px; }
        .bidder-submit-modal-overlay .sb-form { gap: 12px; }
        .bidder-submit-modal-overlay .sb-steps { gap: 8px; margin: 0 -24px 14px; padding: 8px 24px; }
        .bidder-submit-modal-overlay .sb-step { gap: 9px; min-height: 40px; padding: 5px 11px; border-radius: 9px; }
        .bidder-submit-modal-overlay .sb-step__number { flex-basis: 22px; width: 22px; height: 22px; font-size: 11px; }
        .bidder-submit-modal-overlay .sb-step strong { font-size: 12.5px; }
        .bidder-submit-modal-overlay .sb-step small { margin-top: 0; font-size: 10.5px; }
        .bidder-submit-modal-overlay .sb-panel { gap: 10px; }
        .bidder-submit-modal-overlay .sb-panel-head { padding-bottom: 8px; }
        .bidder-submit-modal-overlay .sb-panel-title { font-size: 15px; }
        .bidder-submit-modal-overlay .sb-panel-hint { font-size: 12px; line-height: 1.4; }
        .bidder-submit-modal-overlay .sb-count { padding: 3px 10px; font-size: 11.5px; }
        .bidder-submit-modal-overlay .sb-foot { padding: 10px 24px; }
        .bidder-submit-modal-overlay .sb-foot .sb-btn { min-height: 36px; font-size: 13px; }
    }

    /* ---------- One combined file: same list style as the other requirement rows ---------- */
    .bidder-submit-modal-overlay .sb-mode { display: inline-flex; gap: 2px; margin: 0 0 10px; padding: 3px; border: 1px solid var(--sb-line); border-radius: 10px; background: var(--sb-soft); }
    .bidder-submit-modal-overlay .sb-mode-opt { position: relative; display: inline-flex; align-items: center; gap: 7px; padding: 6px 14px; border-radius: 8px; color: var(--ui-ink-2); font-size: 12.5px; font-weight: 600; cursor: pointer; }
    .bidder-submit-modal-overlay .sb-mode-opt input { position: absolute; opacity: 0; pointer-events: none; }
    .bidder-submit-modal-overlay .sb-mode-opt small { margin-left: 4px; padding: 1px 7px; border-radius: 999px; background: var(--ui-success-soft, #eaf6ef); color: var(--ui-success, #1f7a4d); font-size: 10.5px; font-weight: 700; }
    .bidder-submit-modal-overlay .sb-mode-opt:has(input:checked) { background: #fff; color: var(--ui-ink); box-shadow: 0 1px 3px rgba(27, 36, 32, .14); }
    .bidder-submit-modal-overlay .sb-mode-opt:has(input:focus-visible) { outline: 2px solid var(--ui-primary, #1d4f40); outline-offset: 1px; }
    .bidder-submit-modal-overlay .sb-combined { display: grid; gap: 8px; }
    .bidder-submit-modal-overlay .sb-combined[hidden], .bidder-submit-modal-overlay [data-sb-separate][hidden] { display: none; }
    .bidder-submit-modal-overlay .sb-list-label { margin: 6px 0 0; color: var(--ui-muted); font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .bidder-submit-modal-overlay .sb-list-note { margin: 0; color: var(--ui-muted); font-size: 12px; }
    .bidder-submit-modal-overlay .sb-list-note i { margin-right: 4px; }
    .bidder-submit-modal-overlay .sb-files--ref .sb-file { grid-template-columns: 38px minmax(0, 1fr) auto; padding: 8px 14px; }
    .bidder-submit-modal-overlay .sb-files--ref .sb-file:hover { background: #fff; }
    .bidder-submit-modal-overlay .sb-ref-state { color: var(--ui-subtle, #8a948f); font-size: 12px; white-space: nowrap; }
    .bidder-submit-modal-overlay .sb-ref-done { display: none; color: var(--ui-success, #1f7a4d); font-weight: 600; }
    .bidder-submit-modal-overlay .sb-combined.is-filled .sb-ref-wait { display: none; }
    .bidder-submit-modal-overlay .sb-combined.is-filled .sb-ref-done { display: inline; }
    .bidder-submit-modal-overlay .sb-combined.is-filled .sb-files--ref .sb-file-icon { background: var(--ui-success, #1f7a4d); color: #fff; }
    @media (max-width: 760px) { .bidder-submit-modal-overlay .sb-mode { display: flex; } .bidder-submit-modal-overlay .sb-mode-opt { flex: 1; justify-content: center; } }

    /* ---------- Financial offer: ABC reference, live budget check, PIN boxes ---------- */
    .bidder-submit-modal-overlay .sb-abc { display: grid; gap: 2px; margin: 12px 0 0; padding: 10px 12px; border: 1px solid var(--sb-line); border-radius: 10px; background: var(--ui-surface-2, #f7faf8); }
    .bidder-submit-modal-overlay .sb-abc span { color: var(--ui-muted); font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .bidder-submit-modal-overlay .sb-abc strong { color: var(--ui-ink); font-size: 15px; font-variant-numeric: tabular-nums; }
    .bidder-submit-modal-overlay .sb-compare { display: flex; align-items: center; gap: 6px; font-size: 13px; }
    .bidder-submit-modal-overlay .sb-compare b { display: inline-flex; align-items: center; gap: 7px; padding: 5px 11px; border-radius: 999px; font-weight: 700; }
    .bidder-submit-modal-overlay .sb-compare b.is-under { background: var(--ui-success-soft, #eaf6ef); color: var(--ui-success, #1f7a4d); }
    .bidder-submit-modal-overlay .sb-compare b.is-over { background: var(--ui-danger-soft, #fdf0ee); color: var(--ui-danger, #b42318); }

    .bidder-submit-modal-overlay .sb-pin-grid { grid-template-columns: 1fr; max-width: 400px; gap: 14px; }
    .bidder-submit-modal-overlay .sb-pin-control { display: grid; grid-template-columns: minmax(0, 1fr) 36px; align-items: center; gap: 8px; }
    .bidder-submit-modal-overlay .sb-pin-cells { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 8px; cursor: text; }
    .bidder-submit-modal-overlay .sb-pin-cells > span { display: grid; place-items: center; height: 50px; border: 1px solid var(--ui-line-strong, #cfd8d3); border-radius: 10px; background: #fff; color: var(--ui-ink); font-size: 22px; font-weight: 700; line-height: 1; transition: border-color .12s ease, box-shadow .12s ease; }
    .bidder-submit-modal-overlay .sb-pin-cells > span.is-filled { border-color: var(--ui-ink-2); background: var(--ui-surface-2, #f7faf8); }
    .bidder-submit-modal-overlay .sb-pin-cells > span.is-current { border-color: var(--ui-primary, #1f5c45); box-shadow: 0 0 0 3px rgba(31, 92, 69, .18); }
    :is(.sb, #sb-x#sb-x) .sb-pin-input.sb-pin-overlay { position: absolute !important; inset: 0 44px 0 0 !important; width: auto !important; min-height: 0 !important; height: 100% !important; padding: 0 !important; border: 0 !important; background: transparent !important; color: transparent !important; -webkit-text-fill-color: transparent !important; caret-color: transparent !important; opacity: .01; cursor: text; }
    .bidder-submit-modal-overlay .sb-pin-control:has(.sb-pin-input.is-match) .sb-pin-cells > span { border-color: var(--ui-success, #1f7a4d); background: var(--ui-success-soft, #eaf6ef); }
    .bidder-submit-modal-overlay .sb-pin-control:has(.sb-pin-input.is-mismatch) .sb-pin-cells > span { border-color: var(--ui-danger, #b42318); background: var(--ui-danger-soft, #fdf0ee); }
    .bidder-submit-modal-overlay .sb-pin-eye { position: static !important; transform: none !important; width: 36px; height: 36px; border: 1px solid var(--sb-line); border-radius: 10px; background: #fff; }

    .bidder-submit-modal-overlay .sb-pin-warning { display: flex; gap: 12px; align-items: flex-start; max-width: 520px; margin-top: 4px; padding: 12px 14px; border: 1px solid var(--ui-warning-line); border-left: 4px solid var(--ui-warning); border-radius: 10px; background: var(--ui-warning-soft); }
    .bidder-submit-modal-overlay .sb-pin-warning > i { margin-top: 3px; color: var(--ui-warning); }
    .bidder-submit-modal-overlay .sb-pin-warning strong { color: var(--ui-ink); font-size: 13px; }
    .bidder-submit-modal-overlay .sb-pin-warning p { margin: 2px 0 0; color: var(--ui-ink-2); font-size: 12.5px; line-height: 1.5; }
    @media (max-width: 640px) { .bidder-submit-modal-overlay .sb-pin-cells > span { height: 44px; font-size: 19px; } }

    /* ---------- Review & submit ---------- */
    .bidder-submit-modal-overlay .sb-ready { display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; border: 1px solid var(--sb-line); border-radius: 12px; background: var(--sb-soft); }
    .bidder-submit-modal-overlay .sb-ready > i { margin-top: 2px; font-size: 18px; color: var(--ui-muted); }
    .bidder-submit-modal-overlay .sb-ready strong { color: var(--ui-ink); font-size: 14.5px; }
    .bidder-submit-modal-overlay .sb-ready p { margin: 2px 0 0; color: var(--ui-ink-2); font-size: 12.5px; line-height: 1.5; }
    .bidder-submit-modal-overlay .sb-ready[data-state="ready"] { border-color: var(--ui-success-line); background: var(--ui-success-soft); }
    .bidder-submit-modal-overlay .sb-ready[data-state="ready"] > i { color: var(--ui-success); }
    .bidder-submit-modal-overlay .sb-ready[data-state="needs"] { border-color: var(--ui-warning-line); background: var(--ui-warning-soft); }
    .bidder-submit-modal-overlay .sb-ready[data-state="needs"] > i { color: var(--ui-warning); }

    .bidder-submit-modal-overlay .sb-review-hero { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.5fr); gap: 12px; }
    .bidder-submit-modal-overlay .sb-review-offer { align-content: center; gap: 4px; padding: 18px 20px; border-radius: 14px; }
    .bidder-submit-modal-overlay .sb-review-offer span { font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .bidder-submit-modal-overlay .sb-review-offer strong { font-size: 30px; font-weight: 800; letter-spacing: -.02em; }
    .bidder-submit-modal-overlay .sb-review-offer small b { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 12px; }
    .bidder-submit-modal-overlay .sb-review-offer small b.is-under { background: #fff; color: var(--ui-success); }
    .bidder-submit-modal-overlay .sb-review-offer small b.is-over { background: #fff; color: var(--ui-danger); }
    .bidder-submit-modal-overlay .sb-review-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; }
    .bidder-submit-modal-overlay .sb-tile { display: grid; align-content: center; gap: 3px; padding: 14px 16px; border: 1px solid var(--sb-line); border-radius: 14px; background: #fff; }
    .bidder-submit-modal-overlay .sb-tile span { color: var(--ui-muted); font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .bidder-submit-modal-overlay .sb-tile strong { color: var(--ui-ink); font-size: 22px; font-variant-numeric: tabular-nums; }
    .bidder-submit-modal-overlay .sb-tile small { color: var(--ui-muted); font-size: 12px; }
    .bidder-submit-modal-overlay .sb-tile.is-ok { border-color: var(--ui-success-line); background: var(--ui-success-soft); }
    .bidder-submit-modal-overlay .sb-tile.is-ok strong { color: var(--ui-success); }
    .bidder-submit-modal-overlay .sb-tile.is-missing { border-color: var(--ui-danger-line); background: var(--ui-danger-soft, #fdf0ee); }
    .bidder-submit-modal-overlay .sb-tile.is-missing strong { color: var(--ui-danger); }

    .bidder-submit-modal-overlay .sb-review-block { padding: 0; overflow: hidden; border-radius: 14px; }
    .bidder-submit-modal-overlay .sb-review-head { margin: 0; padding: 12px 16px; border-bottom: 1px solid var(--sb-line); background: var(--ui-surface-2, #f7faf8); }
    .bidder-submit-modal-overlay .sb-review-head h4 { font-size: 12px; letter-spacing: .05em; text-transform: uppercase; color: var(--ui-ink-2); }
    .bidder-submit-modal-overlay .sb-review-list { gap: 0; }
    .bidder-submit-modal-overlay .sb-review-list li { padding: 10px 16px; border-top: 1px solid var(--ui-line-soft, #eef2ef); }
    .bidder-submit-modal-overlay .sb-review-list li:first-child { border-top: 0; }
    .bidder-submit-modal-overlay .sb-review-list li.is-missing { background: var(--ui-danger-soft, #fdf0ee); }
    .bidder-submit-modal-overlay .sb-review-pin { margin: 0; padding: 11px 16px; border-top: 1px solid var(--sb-line); }
    .bidder-submit-modal-overlay .sb-notes { padding: 16px; border-radius: 14px; background: #fff; }
    @media (max-width: 900px) { .bidder-submit-modal-overlay .sb-review-hero { grid-template-columns: 1fr; } }
    /* ---- Redesign: the combined technical upload and the financial offer ---- */
    /* One combined file: a single upload card. */
    .bidder-submit-modal-overlay .sb .sb-drop { position: relative; display: grid; grid-template-columns: 48px minmax(0, 1fr) auto; align-items: center; gap: 16px; padding: 18px 20px; border: 1.5px dashed var(--ui-line-strong, #cfd8d3); border-radius: 14px; background: var(--ui-surface-2, #f7faf8); transition: border-color .15s ease, background .15s ease; }
    .bidder-submit-modal-overlay .sb .sb-drop:hover { border-color: var(--ui-primary, #1f5c45); }
    .bidder-submit-modal-overlay .sb .sb-drop.has-file { border-style: solid; border-color: var(--ui-success-line, #b9dfc9); background: var(--ui-success-soft, #eaf6ef); }
    .bidder-submit-modal-overlay .sb .sb-drop-icon { display: grid; place-items: center; width: 48px; height: 48px; border-radius: 12px; background: #fff; color: var(--ui-primary, #1f5c45); font-size: 20px; box-shadow: 0 1px 2px rgba(27, 36, 32, .08); }
    .bidder-submit-modal-overlay .sb .sb-drop.has-file .sb-drop-icon { background: var(--ui-success, #1f7a4d); color: #fff; }
    .bidder-submit-modal-overlay .sb .sb-drop-copy { display: grid; gap: 3px; min-width: 0; }
    .bidder-submit-modal-overlay .sb .sb-drop-title { color: var(--ui-ink); font-size: 14.5px; font-weight: 700; }
    .bidder-submit-modal-overlay .sb .sb-drop-meta { color: var(--ui-muted); font-size: 12px; line-height: 1.45; }
    .bidder-submit-modal-overlay .sb .sb-drop .sb-file-chosen { display: none; overflow: hidden; color: var(--ui-success, #1f7a4d); font-size: 12.5px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
    .bidder-submit-modal-overlay .sb .sb-drop.has-file .sb-file-chosen { display: block; }
    .bidder-submit-modal-overlay .sb .sb-drop.has-file .sb-file-chosen::before { content: '\f00c'; margin-right: 6px; font-family: 'Font Awesome 6 Free'; font-weight: 900; font-size: 11px; }
    .bidder-submit-modal-overlay .sb .sb-drop .sb-file-actions { display: flex; grid-area: auto; grid-column: 3; grid-row: 1; justify-content: flex-end; gap: 8px; margin: 0; }
    .bidder-submit-modal-overlay .sb .sb-drop .sb-file-actions .sb-btn { min-height: 38px; padding: 0 16px; }
    .bidder-submit-modal-overlay .sb .sb-drop .sb-file-actions [data-upload-change]::before { content: '\f093'; margin-right: 8px; font-family: 'Font Awesome 6 Free'; font-weight: 900; font-size: 12px; }

    /* What the file contains: a compact, numbered two-column list. */
    .bidder-submit-modal-overlay .sb .sb-merge { display: grid; gap: 10px; margin-top: 6px; }
    /* Before payment: a compact step line, one payment card, and the file list folded away. */
    .bidder-submit-modal-overlay .sb-flow2 { display: flex; align-items: center; gap: 0; margin: 4px 0 0; padding: 0; list-style: none; font-size: 12.5px; color: var(--ui-muted); }
    .bidder-submit-modal-overlay .sb-flow2 li { display: flex; flex: 1 1 0; align-items: center; gap: 8px; min-width: 0; }
    .bidder-submit-modal-overlay .sb-flow2 li + li::before { content: ''; flex: 0 0 24px; height: 2px; margin-right: 8px; border-radius: 1px; background: var(--ui-line-strong, #d5ddd8); }
    .bidder-submit-modal-overlay .sb-flow2 li > span { display: grid; flex: 0 0 24px; height: 24px; place-items: center; border-radius: 50%; background: var(--ui-surface-2, #eef2ef); color: var(--ui-muted); font-size: 12px; font-weight: 700; }
    .bidder-submit-modal-overlay .sb-flow2 li.is-current { color: var(--ui-ink); font-weight: 700; }
    .bidder-submit-modal-overlay .sb-flow2 li.is-current > span { background: var(--ui-primary, #1f5c45); color: #fff; box-shadow: 0 0 0 4px var(--ui-primary-soft, #e3f0ea); }
    .bidder-submit-modal-overlay .sb-paycard { display: grid; gap: 14px; padding: 20px 22px; border: 1px solid var(--ui-warning-line, #f1d79c); border-radius: 16px; background: linear-gradient(180deg, var(--ui-warning-soft, #fdf6e5) 0, #fff 120px); }
    .bidder-submit-modal-overlay .sb-paycard-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 12px; }
    .bidder-submit-modal-overlay .sb-paycard-kicker { display: block; color: var(--ui-warning, #8a5a0b); font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .bidder-submit-modal-overlay .sb-paycard-amount { display: block; margin: 2px 0; color: var(--ui-ink); font-size: 32px; font-weight: 800; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
    .bidder-submit-modal-overlay .sb-paycard-head small { color: var(--ui-muted); font-size: 12px; }
    .bidder-submit-modal-overlay .sb-paycard-due { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; background: #fff; border: 1px solid var(--ui-warning-line, #f1d79c); color: var(--ui-warning, #8a5a0b); font-size: 13px; font-weight: 700; }
    .bidder-submit-modal-overlay .sb-paycard-due.is-urgent { border-color: var(--ui-danger, #b42318); color: var(--ui-danger, #b42318); }
    .bidder-submit-modal-overlay .sb-paycard-title { margin: 0; color: var(--ui-ink); font-size: 16px; }
    .bidder-submit-modal-overlay .sb-paycard-facts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin: 0; padding: 0; list-style: none; }
    .bidder-submit-modal-overlay .sb-paycard-facts li { display: grid; align-content: start; gap: 3px; padding: 12px 14px; border: 1px solid var(--ui-line, #e3e8e4); border-radius: 12px; background: #fff; }
    .bidder-submit-modal-overlay .sb-paycard-facts li > i { color: var(--ui-primary); font-size: 15px; }
    .bidder-submit-modal-overlay .sb-paycard-facts li > span { color: var(--ui-muted); font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .bidder-submit-modal-overlay .sb-paycard-facts li > strong { color: var(--ui-ink); font-size: 13.5px; line-height: 1.35; }
    .bidder-submit-modal-overlay .sb-paycard-facts li > small { color: var(--ui-muted); font-size: 12px; }
    .bidder-submit-modal-overlay .sb-copy { justify-self: start; display: inline-flex; align-items: center; gap: 6px; margin-top: 4px; padding: 3px 8px; border: 1px dashed var(--ui-primary-line, #9fc4b3); border-radius: 8px; background: var(--ui-primary-soft, #eef6f2); color: var(--ui-primary); font-size: 12px; cursor: pointer; }
    .bidder-submit-modal-overlay .sb-copy.is-copied { border-style: solid; }
    .bidder-submit-modal-overlay .sb-paycard-note { margin: 0; color: var(--ui-ink-2); font-size: 12.5px; line-height: 1.5; }
    .bidder-submit-modal-overlay .sb-paycard-note i { margin-right: 6px; color: var(--ui-primary); }
    .bidder-submit-modal-overlay details.sb-prepare { border: 1px solid var(--ui-line, #e3e8e4); border-radius: 12px; background: #fff; }
    .bidder-submit-modal-overlay details.sb-prepare > summary { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 16px; cursor: pointer; list-style: none; color: var(--ui-ink); font-size: 14px; font-weight: 600; }
    .bidder-submit-modal-overlay details.sb-prepare > summary::-webkit-details-marker { display: none; }
    .bidder-submit-modal-overlay details.sb-prepare > summary i { margin-right: 8px; color: var(--ui-primary); }
    .bidder-submit-modal-overlay details.sb-prepare > summary small { color: var(--ui-muted); font-size: 12px; font-weight: 500; }
    .bidder-submit-modal-overlay details.sb-prepare > summary small::after { content: '\f078'; margin-left: 8px; font-family: 'Font Awesome 6 Free'; font-weight: 900; font-size: 10px; }
    .bidder-submit-modal-overlay details.sb-prepare[open] > summary small::after { content: '\f077'; }
    .bidder-submit-modal-overlay details.sb-prepare > .sb-comp-grid { padding: 0 16px 16px; }
    @media (max-width: 720px) {
        .bidder-submit-modal-overlay .sb-paycard-facts { grid-template-columns: 1fr; }
        .bidder-submit-modal-overlay .sb-flow2 li:not(.is-current) { font-size: 0; flex: 0 0 auto; }
        .bidder-submit-modal-overlay .sb-flow2 li:not(.is-current) > span { font-size: 12px; }
        .bidder-submit-modal-overlay .sb-paycard-amount { font-size: 28px; }
    }
    /* The key facts on one line above the notice. */
    .bidder-submit-modal-overlay .sb-strip { display: flex; flex-wrap: wrap; gap: 8px; margin: 0; padding: 14px 30px 4px; list-style: none; }
    .bidder-submit-modal-overlay .sb-strip li { display: inline-flex; align-items: baseline; gap: 6px; padding: 6px 12px; border: 1px solid var(--sb-line, #e3e8e4); border-radius: 999px; background: #fff; font-size: 13px; }
    .bidder-submit-modal-overlay .sb-strip span { color: var(--ui-muted); font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .bidder-submit-modal-overlay .sb-strip strong { color: var(--ui-ink); font-weight: 700; }
    .bidder-submit-modal-overlay .sb-strip small { color: var(--ui-muted); font-size: 12px; }
    .bidder-submit-modal-overlay .sb-strip li.is-ok { border-color: var(--ui-success-line, #b7dcc8); background: var(--ui-success-soft, #ecf7f0); }
    .bidder-submit-modal-overlay .sb-strip li.is-ok strong { color: var(--ui-success, #1f7a4d); }
    .bidder-submit-modal-overlay .sb-strip li.is-warn { border-color: var(--ui-warning-line, #f1d79c); background: var(--ui-warning-soft, #fdf6e5); }
    .bidder-submit-modal-overlay .sb-panel > .sb-seal-note { margin: 0; padding: 10px 12px; border-radius: 10px; background: var(--ui-primary-soft, #eef6f2); color: var(--ui-ink-2); }
    /* The order list, folded under the upload box. */
    .bidder-submit-modal-overlay .sb details.sb-merge > summary { display: flex; justify-content: flex-start; align-items: center; cursor: pointer; list-style: none; color: var(--ui-primary); font-size: 13px; font-weight: 600; }
    .bidder-submit-modal-overlay .sb details.sb-merge > summary::-webkit-details-marker { display: none; }
    .bidder-submit-modal-overlay .sb details.sb-merge > summary::before { content: '\f078'; margin-right: 8px; font-family: 'Font Awesome 6 Free'; font-weight: 900; font-size: 11px; display: inline-block; transition: transform .15s ease; }
    .bidder-submit-modal-overlay .sb details.sb-merge[open] > summary::before { transform: rotate(180deg); }
    .bidder-submit-modal-overlay .sb-status { font-weight: 500; }
    .bidder-submit-modal-overlay .sb-status.is-ready { color: var(--ui-success, #1f7a4d); font-weight: 600; }
    .bidder-submit-modal-overlay .sb .sb-merge-title { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; margin: 0; color: var(--ui-ink); font-size: 13px; font-weight: 700; }
    .bidder-submit-modal-overlay .sb .sb-merge-title span { color: var(--ui-muted); font-size: 12px; font-weight: 600; }
    .bidder-submit-modal-overlay .sb .sb-merge-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin: 0; padding: 0; list-style: none; }
    .bidder-submit-modal-overlay .sb .sb-merge-list li { display: grid; grid-template-columns: 26px minmax(0, 1fr) 16px; align-items: center; gap: 10px; min-height: 48px; padding: 8px 12px; border: 1px solid var(--sb-line); border-radius: 10px; background: #fff; }
    .bidder-submit-modal-overlay .sb .sb-merge-num { display: grid; place-items: center; width: 26px; height: 26px; border-radius: 50%; background: var(--sb-soft); color: var(--ui-ink-2); font-size: 12px; font-weight: 700; }
    .bidder-submit-modal-overlay .sb .sb-merge-label { display: grid; gap: 1px; color: var(--ui-ink); font-size: 12.5px; font-weight: 600; line-height: 1.35; }
    .bidder-submit-modal-overlay .sb .sb-merge-label small { color: var(--ui-muted); font-size: 11px; font-weight: 500; }
    .bidder-submit-modal-overlay .sb .sb-merge-check { color: var(--ui-line-strong, #cfd8d3); font-size: 15px; }
    .bidder-submit-modal-overlay .sb .sb-combined.is-filled .sb-merge-list li { border-color: var(--ui-success-line, #b9dfc9); }
    .bidder-submit-modal-overlay .sb .sb-combined.is-filled .sb-merge-num { background: var(--ui-success-soft, #eaf6ef); color: var(--ui-success, #1f7a4d); }
    .bidder-submit-modal-overlay .sb .sb-combined.is-filled .sb-merge-check { color: var(--ui-success, #1f7a4d); }

    /* Upload rows (separate technical files and financial forms): one tidy line each. */
    .bidder-submit-modal-overlay .sb .sb-filetable { border-radius: 12px; }
    .bidder-submit-modal-overlay .sb .sb-filetable .sb-file { grid-template-columns: 36px minmax(0, 1fr) auto; grid-template-areas: "icon copy actions"; min-height: 0; align-items: center; gap: 4px 14px; padding: 12px 16px; }
    .bidder-submit-modal-overlay .sb .sb-filetable .sb-file-copy { display: grid; grid-template-columns: minmax(0, 1fr); gap: 2px; }
    .bidder-submit-modal-overlay .sb .sb-filetable .sb-file-status { grid-area: auto; }
    .bidder-submit-modal-overlay .sb .sb-filetable .sb-file-meta { color: var(--ui-muted); font-size: 11.5px; }

    /* The financial offer: two cards. */
    .bidder-submit-modal-overlay .sb .sb-offer2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; align-items: start; }
    .bidder-submit-modal-overlay .sb .sb-offer2.is-single { grid-template-columns: minmax(0, 560px); }
    .bidder-submit-modal-overlay .sb .sb-card { display: grid; align-content: start; gap: 10px; min-width: 0; padding: 18px; border: 1px solid var(--sb-line); border-radius: 14px; background: #fff; }
    .bidder-submit-modal-overlay .sb .sb-card-head { display: grid; grid-template-columns: 38px minmax(0, 1fr); align-items: start; gap: 12px; }
    .bidder-submit-modal-overlay .sb .sb-card-icon { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 10px; background: var(--ui-primary-soft, #e4f0ea); color: var(--ui-primary, #1f5c45); font-size: 17px; font-weight: 800; }
    .bidder-submit-modal-overlay .sb .sb-card-title { display: block; color: var(--ui-ink); font-size: 14.5px; font-weight: 700; }
    .bidder-submit-modal-overlay .sb .sb-card-hint { margin: 2px 0 0; color: var(--ui-muted); font-size: 12.5px; line-height: 1.45; }
    .bidder-submit-modal-overlay .sb .sb-card .sb-money { max-width: none; }
    .bidder-submit-modal-overlay .sb .sb-abc-line { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; margin: 2px 0 0; padding-top: 10px; border-top: 1px dashed var(--sb-line); }
    .bidder-submit-modal-overlay .sb .sb-abc-line span { color: var(--ui-muted); font-size: 11.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .bidder-submit-modal-overlay .sb .sb-abc-line strong { color: var(--ui-ink); font-size: 15px; font-variant-numeric: tabular-nums; }
    .bidder-submit-modal-overlay .sb .sb-card .sb-pin-grid { grid-template-columns: 1fr; max-width: none; gap: 10px; }
    .bidder-submit-modal-overlay .sb .sb-card .sb-pin-cells > span { height: 44px; }
    .bidder-submit-modal-overlay .sb .sb-card .sb-pin-status { max-width: none; }
    .bidder-submit-modal-overlay .sb .sb-card .sb-pin-warning { display: flex; align-items: flex-start; gap: 8px; max-width: none; margin: 0; padding: 9px 12px; border: 1px solid var(--ui-warning-line); border-radius: 10px; background: var(--ui-warning-soft); color: var(--ui-ink-2); font-size: 12px; line-height: 1.5; }
    .bidder-submit-modal-overlay .sb .sb-card .sb-pin-warning > i { margin-top: 3px; color: var(--ui-warning); }
    .bidder-submit-modal-overlay .sb .sb-card .sb-pin-warning strong { color: var(--ui-ink); }
    .bidder-submit-modal-overlay .sb [data-sb-panel="2"] .sb-group-title { margin: 18px 0 8px; font-size: 13.5px; }

    @media (max-width: 900px) {
        .bidder-submit-modal-overlay .sb .sb-offer2 { grid-template-columns: 1fr; }
        .bidder-submit-modal-overlay .sb .sb-merge-list { grid-template-columns: 1fr; }
    }
    @media (max-width: 640px) {
        .bidder-submit-modal-overlay .sb .sb-drop { grid-template-columns: 40px minmax(0, 1fr); padding: 14px; }
        .bidder-submit-modal-overlay .sb .sb-drop-icon { align-self: start; width: 40px; height: 40px; font-size: 17px; }
        .bidder-submit-modal-overlay .sb .sb-drop .sb-file-actions { grid-column: 1 / -1; grid-row: 2; justify-content: stretch; }
        .bidder-submit-modal-overlay .sb .sb-drop .sb-file-actions .sb-btn { flex: 1; }
        .bidder-submit-modal-overlay .sb .sb-filetable .sb-file { grid-template-columns: 32px minmax(0, 1fr); grid-template-areas: "icon copy" "actions actions"; }
        .bidder-submit-modal-overlay .sb .sb-filetable .sb-file-actions { padding-left: 0; }
    }
    /* Compact final review: one summary strip, with files available on demand. */
    .bidder-submit-modal-overlay .sb-ready[data-state="ready"] { align-items: center; gap: 8px; padding: 0; border: 0; border-radius: 0; background: transparent; }
    .bidder-submit-modal-overlay .sb-ready[data-state="ready"] > i { margin: 0; font-size: 14px; }
    .bidder-submit-modal-overlay .sb-ready[data-state="ready"] strong { font-size: 12.5px; font-weight: 600; color: var(--ui-success); }
    .bidder-submit-modal-overlay .sb-ready[data-state="ready"] p { display: none; }
    .bidder-submit-modal-overlay .sb-review-hero { grid-template-columns: minmax(200px, 1fr) minmax(0, 1.6fr); gap: 0; border: 1px solid var(--sb-line); border-radius: 10px; background: #fff; overflow: hidden; }
    .bidder-submit-modal-overlay .sb-review-offer { min-width: 0; gap: 3px; padding: 12px 16px; border: 0; border-right: 1px solid var(--sb-line); border-radius: 0; background: transparent; }
    .bidder-submit-modal-overlay .sb-review-offer span { font-size: 10px; }
    .bidder-submit-modal-overlay .sb-review-offer strong { font-size: 22px; font-weight: 700; overflow-wrap: anywhere; }
    .bidder-submit-modal-overlay .sb-review-offer small b { padding: 0; border-radius: 0; font-size: 11px; background: transparent; }
    .bidder-submit-modal-overlay .sb-review-tiles { grid-template-columns: repeat(auto-fit, minmax(85px, 1fr)); gap: 0; }
    .bidder-submit-modal-overlay .sb-tile { min-width: 0; padding: 12px 14px; border: 0; border-right: 1px solid var(--sb-line); border-radius: 0; }
    .bidder-submit-modal-overlay .sb-tile:last-child { border-right: 0; }
    .bidder-submit-modal-overlay .sb-tile span { font-size: 10px; letter-spacing: .02em; }
    .bidder-submit-modal-overlay .sb-tile strong { font-size: 16px; }
    .bidder-submit-modal-overlay .sb-tile small { font-size: 11px; }
    .bidder-submit-modal-overlay .sb-tile.is-ok { background: transparent; border-color: var(--sb-line); }
    .bidder-submit-modal-overlay .sb-tile.is-ok small { display: none; }
    .bidder-submit-modal-overlay .sb-review-documents { border: 1px solid var(--sb-line); border-radius: 10px; overflow: hidden; }
    .bidder-submit-modal-overlay .sb-review-documents > summary { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 13px 16px; color: var(--ui-ink); font-size: 13px; font-weight: 600; list-style: none; cursor: pointer; }
    .bidder-submit-modal-overlay .sb-review-documents > summary::-webkit-details-marker { display: none; }
    .bidder-submit-modal-overlay .sb-review-documents > summary i { font-size: 11px; color: var(--ui-muted); }
    .bidder-submit-modal-overlay .sb-review-documents[open] > summary { border-bottom: 1px solid var(--sb-line); }
    .bidder-submit-modal-overlay .sb-review-documents[open] > summary i { transform: rotate(180deg); }
    .bidder-submit-modal-overlay .sb-review-documents .sb-review-grid { gap: 0; }
    .bidder-submit-modal-overlay .sb-review-documents .sb-review-block { border: 0; border-radius: 0; }
    .bidder-submit-modal-overlay .sb-review-documents .sb-review-block + .sb-review-block { border-left: 1px solid var(--sb-line); }
    @media (max-width: 640px) {
        .bidder-submit-modal-overlay .sb-review-hero { grid-template-columns: minmax(0, 1fr); }
        .bidder-submit-modal-overlay .sb-review-offer { border-right: 0; border-bottom: 1px solid var(--sb-line); }
        .bidder-submit-modal-overlay .sb-tile { padding: 12px 10px; }
        .bidder-submit-modal-overlay .sb-review-documents .sb-review-grid { grid-template-columns: minmax(0, 1fr); }
        .bidder-submit-modal-overlay .sb-review-documents .sb-review-block + .sb-review-block { border-left: 0; border-top: 1px solid var(--sb-line); }
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
            dialog.querySelectorAll('[data-sb-copy]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const text = button.dataset.sbCopy;
                    const done = function () { button.classList.add('is-copied'); const code = button.querySelector('code'); if (code) { const before = code.textContent; code.textContent = 'Copied'; window.setTimeout(function () { code.textContent = before; button.classList.remove('is-copied'); }, 1500); } };
                    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(text).then(done).catch(done); else done();
                });
            });
            // A tab chosen directly also moves the status line to that step.
            dialog.querySelectorAll('.sb-step[data-sb-go]').forEach(function (tab) {
                tab.addEventListener('click', function () { step = Number(tab.dataset.sbGo) || 1; refresh(); });
            });
            // The PIN is typed into one real box (so autofill guards, the eye button and validation keep working);
            // six boxes show it digit by digit on top.
            dialog.querySelectorAll('.sb-pin-control').forEach(function (control) {
                const input = control.querySelector('[data-pin]');
                if (!input) return;
                const cells = document.createElement('span');
                cells.className = 'sb-pin-cells';
                cells.setAttribute('aria-hidden', 'true');
                for (let i = 0; i < 6; i += 1) cells.appendChild(document.createElement('span'));
                control.insertBefore(cells, input);
                input.classList.add('sb-pin-overlay');
                const paint = function () {
                    const value = input.value;
                    const visible = input.type === 'text';
                    Array.from(cells.children).forEach(function (cell, index) {
                        cell.textContent = index < value.length ? (visible ? value[index] : '\u2022') : '';
                        cell.classList.toggle('is-filled', index < value.length);
                        cell.classList.toggle('is-current', document.activeElement === input && index === Math.min(value.length, 5));
                    });
                };
                ['input', 'focus', 'blur', 'change'].forEach(function (name) { input.addEventListener(name, paint); });
                const eye = control.querySelector('[data-pin-toggle]');
                if (eye) eye.addEventListener('click', function () { window.setTimeout(paint, 0); });
                cells.addEventListener('click', function () { input.focus(); });
                paint();
            });
            function rows(component) {
                return Array.from(form.querySelectorAll('[data-sb-files="' + component + '"] [data-upload-box]'));
            }
            // "One combined file": the single file stands for every required technical item.
            function combinedInput() { return form.querySelector('[data-combined-input]'); }
            function combinedOn() {
                const choice = form.querySelector('input[name="technical_mode"]:checked');
                return Boolean(choice) && choice.value === 'combined' && Boolean(combinedInput());
            }
            function combinedName() {
                const input = combinedInput();
                return input && input.files && input.files.length ? input.files[0].name : '';
            }
            function setActive(input, active) {
                if (!input) return;
                if (active) {
                    input.disabled = false;
                    if (input.dataset.wasRequired === '1') { input.setAttribute('data-required-upload', ''); input.required = true; }
                } else {
                    if (input.hasAttribute('data-required-upload')) { input.dataset.wasRequired = '1'; }
                    input.removeAttribute('data-required-upload');
                    input.required = false;
                    input.disabled = true;
                }
            }
            function applyMode() {
                const combined = combinedOn();
                form.querySelectorAll('[data-sb-files="technical"] [data-upload-input]').forEach(function (input) { setActive(input, !combined); });
                setActive(combinedInput(), combined);
                const drop = form.querySelector('[data-sb-combined]');
                const separate = form.querySelector('[data-sb-separate]');
                if (drop) drop.hidden = !combined;
                if (separate) separate.hidden = combined;
                if (drop) drop.classList.toggle('is-filled', Boolean(combinedName()));
            }
            function rowState(row) {
                const input = row.querySelector('[data-upload-input]');
                if (combinedOn() && row.closest('[data-sb-files="technical"]')) {
                    const shared = combinedName();
                    return { label: row.dataset.label, file: shared, record: '', required: row.dataset.required === '1', missing: !shared && row.dataset.required === '1' };
                }
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
                if (value === null || !abc || value <= 0) return '';
                if (value > abc) return '<b class="is-over"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Exceeds the ABC by ' + peso(value - abc) + '</b>';
                return '<b class="is-under"><i class="fas fa-circle-check" aria-hidden="true"></i> Within budget &middot; ' + (value / abc * 100).toFixed(1) + '% of the ABC</b>';
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

                // Each step tab shows its progress, and a check once done.
                const progress = {};
                ['technical', 'financial'].forEach(function (component, index) {
                    const list = rows(component).map(rowState);
                    const required = list.filter(function (row) { return row.required; });
                    const ready = required.filter(function (row) { return row.file || row.record; }).length;
                    progress[index + 1] = { ready: ready, total: required.length };
                });
                dialog.querySelectorAll('.sb-step[data-sb-go]').forEach(function (tab) {
                    const n = Number(tab.dataset.sbGo);
                    const done = stepComplete(n);
                    tab.classList.toggle('is-done', done);
                    const small = tab.querySelector('small');
                    if (!small) return;
                    if (small.dataset.hint === undefined) small.dataset.hint = small.textContent;
                    const p = progress[n];
                    small.textContent = p && p.total
                        ? p.ready + ' of ' + p.total + ' required' + (done ? ' · done' : '')
                        : (done ? 'Done' : small.dataset.hint);
                });

                // The line beside Next: only what the current step still needs.
                const status = dialog.querySelector('[data-bid-status]');
                if (status && form.dataset.paymentLocked !== 'true') {
                    let text;
                    if (step === 1) {
                        const p = progress[1];
                        text = stepComplete(1)
                            ? 'Technical documents ready. Next: the financial offer.'
                            : 'Step 1 of 3: upload the technical documents' + (p && p.total ? ' (' + p.ready + ' of ' + p.total + ' ready).' : '.');
                    } else if (step === 2) {
                        const need = [];
                        if (!amountOk()) need.push(amountOf(amount) === null ? 'the bid price' : 'a bid price within the ABC');
                        if (electronic && !pinOk()) need.push('the 6-digit PIN');
                        const p = progress[2];
                        if (p && p.total > p.ready) need.push((p.total - p.ready) + ' financial ' + (p.total - p.ready === 1 ? 'file' : 'files'));
                        text = need.length ? 'Step 2 of 3: still needed: ' + need.join(', ') + '.' : 'Financial offer ready. Next: review and submit.';
                    } else {
                        text = stepComplete(3) ? (electronic ? 'Ready to submit officially.' : 'Ready to save as a draft record (not an official bid).') : 'Some parts are missing: see the checklist above.';
                    }
                    status.textContent = text;
                    status.classList.toggle('is-ready', stepComplete(3) && step === 3);
                    status.dataset.sbOwned = '1';
                }

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
                const fileCount = dialog.querySelector('[data-sb-review-file-count]');
                if (fileCount) {
                    const attached = ['technical', 'financial'].reduce(function (total, component) {
                        return total + rows(component).map(rowState).filter(function (row) { return row.file || row.record; }).length;
                    }, 0);
                    fileCount.textContent = 'View ' + attached + ' attached ' + (attached === 1 ? 'file' : 'files');
                }
                // Readiness banner and the three summary tiles on the review step.
                (function () {
                    const tile = function (name, value, note, state) {
                        const node = dialog.querySelector('[data-sb-tile="' + name + '"]');
                        if (!node) return;
                        node.querySelector('strong').textContent = value;
                        node.querySelector('small').textContent = note;
                        node.classList.toggle('is-ok', state === 'ok');
                        node.classList.toggle('is-missing', state === 'missing');
                    };
                    const gaps = [];
                    ['technical', 'financial'].forEach(function (component) {
                        const list = rows(component).map(rowState);
                        const required = list.filter(function (row) { return row.required; });
                        const ready = required.filter(function (row) { return row.file || row.record; }).length;
                        const attached = list.filter(function (row) { return row.file || row.record; }).length;
                        const missing = required.length - ready;
                        if (electronic && required.length) {
                            tile(component, ready + ' / ' + required.length, 'required files ready', missing === 0 ? 'ok' : 'missing');
                            if (missing > 0) gaps.push(missing + ' required ' + component + ' ' + (missing === 1 ? 'file' : 'files'));
                        } else {
                            tile(component, String(attached), attached === 1 ? 'file attached' : 'files attached', attached ? 'ok' : '');
                        }
                    });
                    if (!amountOk()) gaps.unshift(amountOf(amount) === null ? 'the bid price' : 'a bid price within the ABC');
                    if (electronic) {
                        tile('pin', pinOk() ? 'Set' : 'Not set', pinOk() ? 'Matches in both boxes' : 'Enter and confirm 6 digits', pinOk() ? 'ok' : 'missing');
                        if (!pinOk()) gaps.push('the 6-digit PIN');
                    }
                    const banner = dialog.querySelector('[data-sb-ready]');
                    if (banner) {
                        const ready = gaps.length === 0;
                        banner.dataset.state = ready ? 'ready' : 'needs';
                        banner.querySelector('i').className = 'fas ' + (ready ? 'fa-circle-check' : 'fa-circle-exclamation');
                        banner.querySelector('[data-sb-ready-title]').textContent = ready ? 'Ready to submit' : 'Not ready yet';
                        banner.querySelector('[data-sb-ready-text]').textContent = ready
                            ? 'Everything the BAC asks for is in place. Check the details below, then submit.'
                            : 'Still needed: ' + gaps.join(', ') + '.';
                    }
                })();
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
                const tab = dialog.querySelector('[data-sb-go="' + step + '"]');
                if (tab) tab.click();
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

            form.querySelectorAll('input[name="technical_mode"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    applyMode();
                    const input = combinedInput();
                    // Lets the page's own readiness checks run again for the active set.
                    if (input) input.dispatchEvent(new Event('change', { bubbles: true }));
                    refresh();
                });
            });
            applyMode();
            // The page's own readiness check ran before this mode was applied; let it run again.
            const priceBox = form.querySelector('[data-bid-amount]');
            if (priceBox) priceBox.dispatchEvent(new Event('blur'));
            form.addEventListener('input', function () { applyMode(); refresh(); });
            form.addEventListener('change', function () { applyMode(); refresh(); });
            dialog.querySelectorAll('[data-upload-remove]').forEach(function (button) {
                button.addEventListener('click', function () { setTimeout(function () { refresh(); }, 0); });
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
