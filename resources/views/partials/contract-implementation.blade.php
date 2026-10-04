@use('App\Models\ContractImplementation', 'CI')
@php
    $implementation = $implementation ?? $award->contractImplementation;
    $viewerMode = $viewerMode ?? 'admin';
    // 'section': the whole contract screen; 'delivery-dialog': the supplier's focused delivery modal.
    $part = $part ?? 'section';
    $bid = $award->bid;
    $tz = config('bac-office.display_timezone');
    $ntpAt = $bid?->notice_to_proceed_at;
    $status = $implementation?->status ?? CI::FOR_DELIVERY;
    $items = array_values($implementation?->contract_items ?? []);
    $isLgu = in_array($viewerMode, ['admin', 'staff'], true);
    $configured = (bool) $implementation?->isConfigured();
    $configureRoute = $viewerMode === 'staff' ? 'staff.contract-implementation.configure' : 'admin.contract-implementation.configure';
    $actionRoute = $viewerMode === 'staff' ? 'staff.contract-implementation.action' : 'admin.contract-implementation.action';
    $uid = 'ci-'.$award->id;
    $accept = '.pdf,.jpg,.jpeg,.png,.doc,.docx';

    // Errors and old input belong to this award's form only.
    $mine = (string) old('ci_award') === (string) $award->id;
    $err = fn (string $field) => $mine ? $errors->first($field) : null;
    $old = fn (string $field, $default = null) => $mine ? old($field, $default) : $default;

    // Stages in order; a correction sends the contract back to delivery.
    $stages = [
        CI::FOR_DELIVERY => 'Delivery',
        CI::DELIVERED => 'Received',
        CI::FOR_INSPECTION => 'Inspection',
        CI::ACCEPTED => 'Accepted',
        CI::PAYMENT_PROCESSING => 'Payment processing',
        CI::PAID => 'Paid',
        'warranty' => 'Warranty',
        CI::COMPLETED => 'Completed',
    ];
    $stageKeys = array_keys($stages);
    // Once paid, the contract waits out the warranty before it is completed.
    $current = array_search(match ($status) { CI::FOR_CORRECTION => CI::FOR_DELIVERY, CI::PAID => 'warranty', default => $status }, $stageKeys, true);
    $current = $current === false ? 0 : $current;
    $statusTone = match ($status) {
        CI::FOR_CORRECTION => 'warning',
        CI::ACCEPTED, CI::PAID, CI::COMPLETED => 'success',
        default => 'info',
    };

    $deadline = $implementation?->effectiveDeadline();
    $daysLeft = $deadline ? (int) now($tz)->startOfDay()->diffInDays($deadline->copy()->startOfDay(), false) : null;
    $deliveryOpen = in_array($status, [CI::FOR_DELIVERY, CI::FOR_CORRECTION], true);
    $beforeAcceptance = in_array($status, [CI::FOR_DELIVERY, CI::DELIVERED, CI::FOR_INSPECTION, CI::FOR_CORRECTION], true);
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');
    $peso = fn ($value) => '₱'.number_format((float) $value, 2);
    $lastCorrection = $implementation?->events?->where('action', 'inspection')->last();

    // Liquidated damages (IRR Sec. 71.1.4) and the warranty (Sec. 90.1).
    $contractPrice = (float) $award->contract_amount;
    $damages = $configured ? \App\Support\LiquidatedDamages::for($implementation, $contractPrice) : null;
    $showDamages = $damages && ($damages['max_days'] > 0);
    $warrantyEnds = $implementation?->warrantyEndsOn();
    $securityAmount = $implementation?->hasWarrantyTerms() ? $implementation->warrantySecurityAmount($contractPrice) : null;
    $retention = $implementation?->warranty_security === 'retention' ? $securityAmount : 0;
    $payable = $damages ? max(0, $contractPrice - $damages['total'] - (float) $retention) : null;
@endphp
@once
<style id="contract-implementation-ui">
    /* Dashboard pages style every field and button with ID-level !important rules. :is() takes the
       specificity of its strongest argument, so the never-matching ID pair lifts these rules above them. */
    .ci { margin: 20px 0; padding: 0; border: 1px solid var(--ui-line, #e5e0d4); border-radius: var(--ui-radius-lg, 8px); background: var(--ui-surface, #fff); color: var(--ui-ink, #1b2420); font-family: var(--ui-font, Inter, system-ui, sans-serif); font-size: var(--ui-text, 13.5px); overflow: hidden; }
    .ci :is(h2, h3, h4, p, ol, ul, dl, dd, table) { margin: 0; }
    .ci :is(ol, ul) { padding: 0; list-style: none; }
    .ci [hidden] { display: none !important; }

    .ci-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 18px 20px 16px; border-bottom: 1px solid var(--ui-line, #e5e0d4); }
    .ci-eyebrow { color: var(--ui-primary, #1d4f40); font-size: var(--ui-text-xs, 11.5px); font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .ci-head h2 { margin-top: 3px; color: var(--ui-ink, #1b2420); font-size: 18px; font-weight: 700; line-height: 1.3; overflow-wrap: anywhere; }
    .ci-head-meta { display: flex; flex-wrap: wrap; gap: 4px 12px; margin-top: 5px; color: var(--ui-muted, #5b6761); font-size: var(--ui-text-sm, 12.5px); }
    .ci-mono { font-family: var(--ui-mono, ui-monospace, monospace); font-size: .95em; }
    .ci-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 11px; border-radius: 999px; font-size: var(--ui-text-sm, 12.5px); font-weight: 700; white-space: nowrap; }
    .ci-pill.is-info { background: var(--ui-info-soft, #e6eef8); color: var(--ui-info, #1f4f86); }
    .ci-pill.is-success { background: var(--ui-success-soft, #e2f2ea); color: var(--ui-success, #1d6a4d); }
    .ci-pill.is-warning { background: var(--ui-warning-soft, #fcf1d8); color: var(--ui-warning, #875705); }

    .ci-body { display: grid; gap: 16px; padding: 18px 20px 20px; background: var(--ui-page, #f3f1eb); }

    .ci-steps { display: grid; grid-template-columns: repeat(8, minmax(0, 1fr)); gap: 0; padding: 14px 16px; border: 1px solid var(--ui-line, #e5e0d4); border-radius: var(--ui-radius-lg, 8px); background: var(--ui-surface, #fff); counter-reset: ci-step; }
    .ci-steps li { position: relative; display: grid; justify-items: center; gap: 6px; min-width: 0; padding: 0 4px; color: var(--ui-subtle, #78827c); font-size: var(--ui-text-xs, 11.5px); font-weight: 600; text-align: center; }
    .ci-steps li::before { content: ''; position: absolute; top: 11px; left: calc(-50% + 14px); right: calc(50% + 14px); height: 2px; background: var(--ui-line, #e5e0d4); }
    .ci-steps li:first-child::before { display: none; }
    .ci-dot { position: relative; display: grid; width: 24px; height: 24px; place-items: center; border: 2px solid var(--ui-line-strong, #d2cbbb); border-radius: 50%; background: var(--ui-surface, #fff); color: var(--ui-subtle, #78827c); font-size: 11px; font-weight: 700; }
    .ci-steps li.is-done { color: var(--ui-ink-2, #33403a); }
    .ci-steps li.is-done::before, .ci-steps li.is-current::before { background: var(--ui-primary, #1d4f40); }
    .ci-steps li.is-done .ci-dot { border-color: var(--ui-primary, #1d4f40); background: var(--ui-primary, #1d4f40); color: #fff; }
    .ci-steps li.is-current { color: var(--ui-primary, #1d4f40); }
    .ci-steps li.is-current .ci-dot { border-color: var(--ui-primary, #1d4f40); color: var(--ui-primary, #1d4f40); box-shadow: 0 0 0 4px var(--ui-primary-soft, #e8f1ec); }
    .ci-steps li.is-warning, .ci-steps li.is-warning .ci-dot { border-color: var(--ui-warning, #875705); color: var(--ui-warning, #875705); }
    .ci-steps li.is-warning .ci-dot { box-shadow: 0 0 0 4px var(--ui-warning-soft, #fcf1d8); }

    .ci-facts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); overflow: hidden; border: 1px solid var(--ui-line, #e5e0d4); border-radius: var(--ui-radius-lg, 8px); background: var(--ui-line, #e5e0d4); gap: 1px; }
    .ci-facts > div { min-width: 0; padding: 11px 14px; background: var(--ui-surface, #fff); }
    .ci-facts dt { color: var(--ui-subtle, #78827c); font-size: var(--ui-text-xs, 11.5px); font-weight: 600; }
    .ci-facts dd { margin-top: 3px; color: var(--ui-ink, #1b2420); font-weight: 600; line-height: 1.35; overflow-wrap: anywhere; }
    .ci-facts dd.is-pending { color: var(--ui-muted, #5b6761); font-weight: 500; font-style: italic; }
    .ci-facts small { display: block; margin-top: 2px; font-size: var(--ui-text-xs, 11.5px); font-weight: 600; font-style: normal; }
    .ci-facts small.is-late { color: var(--ui-danger, #a0322b); }
    .ci-facts small.is-soon { color: var(--ui-warning, #875705); }
    .ci-facts small.is-ok { color: var(--ui-success, #1d6a4d); }

    .ci-card { min-width: 0; padding: 16px 18px 18px; border: 1px solid var(--ui-line, #e5e0d4); border-radius: var(--ui-radius-lg, 8px); background: var(--ui-surface, #fff); }
    .ci-card-head { margin-bottom: 14px; }
    .ci-card-head h3 { color: var(--ui-ink, #1b2420); font-size: var(--ui-text-lg, 15px); font-weight: 700; line-height: 1.3; }
    .ci-card-head p { margin-top: 3px; color: var(--ui-muted, #5b6761); font-size: var(--ui-text-sm, 12.5px); line-height: 1.5; }

    .ci-note { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border: 1px solid var(--ui-info-line, #bfd1ea); border-radius: var(--ui-radius-lg, 8px); background: var(--ui-info-soft, #e6eef8); color: var(--ui-info, #1f4f86); font-size: var(--ui-text-sm, 12.5px); line-height: 1.5; }
    .ci-note i { margin-top: 2px; }
    .ci-note strong { display: block; color: inherit; font-size: var(--ui-text, 13.5px); }
    .ci-note.is-warning { border-color: var(--ui-warning-line, #efd59a); background: var(--ui-warning-soft, #fcf1d8); color: var(--ui-warning, #875705); }
    .ci-note.is-success { border-color: var(--ui-success-line, #b4dac6); background: var(--ui-success-soft, #e2f2ea); color: var(--ui-success, #1d6a4d); }
    .ci-note.is-danger { border-color: var(--ui-danger-line, #efc5bf); background: var(--ui-danger-soft, #fbe9e6); color: var(--ui-danger, #a0322b); }

    .ci-form { display: grid; gap: 14px; }
    .ci-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px 16px; }
    .ci-grid.is-two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ci-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
    .ci-field.is-wide { grid-column: 1 / -1; }
    .ci :is(.ci-label, #ci-x#ci-x) { margin: 0 !important; color: var(--ui-ink-2, #33403a) !important; font-size: var(--ui-text-sm, 12.5px) !important; font-weight: 600 !important; line-height: 1.3 !important; letter-spacing: normal !important; text-transform: none !important; }
    .ci-req { color: var(--ui-danger, #a0322b); }
    .ci-hint { color: var(--ui-subtle, #78827c); font-size: var(--ui-text-xs, 11.5px); line-height: 1.45; }
    .ci-hint strong { color: var(--ui-ink-2, #3c4641); font-weight: 600; }
    .ci-prefill { display: flex; align-items: flex-start; gap: 8px; margin: 0 0 14px; padding: 9px 12px; border: 1px solid var(--ui-info-line, #c9d9ee); border-radius: var(--ui-radius, 6px); background: var(--ui-info-soft, #e6eef8); color: var(--ui-info, #1f4f86); font-size: var(--ui-text-sm, 12.5px); line-height: 1.45; }
    .ci-prefill i { margin-top: 2px; }
    .ci-error { color: var(--ui-danger, #a0322b); font-size: var(--ui-text-xs, 11.5px); font-weight: 600; }

    .ci :is(.ci-input, #ci-x#ci-x) { display: block !important; width: 100% !important; min-width: 0 !important; max-width: none !important; min-height: 38px !important; height: auto !important; margin: 0 !important; padding: 8px 11px !important; border: 1px solid var(--ui-line-strong, #d2cbbb) !important; border-radius: var(--ui-radius, 6px) !important; background: var(--ui-surface, #fff) !important; color: var(--ui-ink, #1b2420) !important; -webkit-text-fill-color: currentColor !important; font: 400 var(--ui-text, 13.5px)/1.4 var(--ui-font, Inter, system-ui, sans-serif) !important; box-shadow: none !important; box-sizing: border-box !important; }
    .ci :is(textarea.ci-input, #ci-x#ci-x) { min-height: 72px !important; resize: vertical !important; line-height: 1.5 !important; }
    .ci :is(.ci-input:focus, #ci-x#ci-x) { border-color: var(--ui-focus-color, #2f7a5f) !important; outline: none !important; box-shadow: var(--ui-focus, 0 0 0 3px rgba(47, 122, 95, .32)) !important; }
    .ci :is(.ci-input[aria-invalid="true"], #ci-x#ci-x) { border-color: #d9776c !important; }
    .ci :is(.ci-input[readonly], #ci-x#ci-x) { background: var(--ui-page, #f3f1eb) !important; color: var(--ui-muted, #5b6761) !important; }
    .ci :is(input.ci-file, #ci-x#ci-x) { padding: 7px 9px !important; background: var(--ui-page, #f3f1eb) !important; cursor: pointer !important; }

    .ci-table-wrap { overflow-x: auto; border: 1px solid var(--ui-line, #e5e0d4); border-radius: var(--ui-radius-lg, 8px); }
    .ci :is(.ci-table, #ci-x#ci-x) { width: 100% !important; margin: 0 !important; border: 0 !important; border-collapse: collapse !important; background: var(--ui-surface, #fff) !important; font-size: var(--ui-text-sm, 12.5px) !important; }
    .ci :is(.ci-table th, #ci-x#ci-x) { height: auto !important; padding: 8px 12px !important; border: 0 !important; border-bottom: 1px solid var(--ui-line, #e5e0d4) !important; background: var(--ui-page, #f3f1eb) !important; color: var(--ui-subtle, #78827c) !important; font-size: var(--ui-text-xs, 11.5px) !important; font-weight: 700 !important; letter-spacing: .04em !important; line-height: 1.3 !important; text-align: left !important; text-transform: uppercase !important; white-space: nowrap !important; word-break: normal !important; overflow-wrap: normal !important; }
    .ci :is(.ci-table td, #ci-x#ci-x) { height: auto !important; padding: 8px 12px !important; border: 0 !important; border-bottom: 1px solid var(--ui-line-soft, #efebe2) !important; background: transparent !important; color: var(--ui-ink, #1b2420) !important; font-size: var(--ui-text-sm, 12.5px) !important; line-height: 1.4 !important; vertical-align: middle !important; word-break: normal !important; overflow-wrap: break-word !important; }
    .ci :is(.ci-table tbody tr:last-child td, #ci-x#ci-x) { border-bottom: 0 !important; }
    .ci :is(.ci-table .is-num, #ci-x#ci-x) { text-align: right !important; font-variant-numeric: tabular-nums; }
    .ci-table .is-input { width: 170px; }
    .ci-table .is-remove { width: 44px; text-align: right; }
    .ci-table td .ci-input { min-height: 34px !important; }
    .ci-steps-caption { display: none; }

    .ci :is(.ci-btn, #ci-x#ci-x) { display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 7px !important; width: auto !important; min-width: 0 !important; min-height: 36px !important; margin: 0 !important; padding: 0 14px !important; border: 1px solid var(--ui-line-strong, #d2cbbb) !important; border-radius: var(--ui-radius, 6px) !important; background: var(--ui-surface, #fff) !important; color: var(--ui-ink-2, #33403a) !important; font: 600 var(--ui-text-sm, 12.5px)/1 var(--ui-font, Inter, system-ui, sans-serif) !important; text-transform: none !important; letter-spacing: normal !important; white-space: nowrap !important; box-shadow: none !important; cursor: pointer !important; }
    .ci :is(.ci-btn:hover, #ci-x#ci-x) { border-color: var(--ui-subtle, #78827c) !important; background: var(--ui-page, #f3f1eb) !important; }
    .ci :is(.ci-btn:focus-visible, #ci-x#ci-x) { outline: none !important; box-shadow: var(--ui-focus, 0 0 0 3px rgba(47, 122, 95, .32)) !important; }
    .ci :is(.ci-btn.is-primary, #ci-x#ci-x) { border-color: var(--ui-primary, #1d4f40) !important; background: var(--ui-primary, #1d4f40) !important; color: #fff !important; }
    .ci :is(.ci-btn.is-primary:hover, #ci-x#ci-x) { border-color: var(--ui-primary-hover, #153c30) !important; background: var(--ui-primary-hover, #153c30) !important; }
    .ci :is(.ci-btn.is-small, #ci-x#ci-x) { min-height: 30px !important; padding: 0 10px !important; font-size: var(--ui-text-xs, 11.5px) !important; }
    .ci :is(.ci-btn.is-icon, #ci-x#ci-x) { width: 30px !important; min-height: 30px !important; padding: 0 !important; color: var(--ui-muted, #5b6761) !important; }
    .ci-form-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 16px; padding-top: 14px; border-top: 1px solid var(--ui-line, #e5e0d4); }
    .ci-form-foot p { color: var(--ui-subtle, #78827c); font-size: var(--ui-text-xs, 11.5px); }

    /* The "Next step" card: what happens now and the one button for it. */
    .ci-next { display: flex; align-items: center; gap: 14px 16px; padding: 16px 18px; border: 1px solid var(--ui-line, #e5e0d4); border-radius: var(--ui-radius-lg, 8px); background: var(--ui-surface, #fff); }
    .ci-next-icon { display: grid; flex: 0 0 40px; width: 40px; height: 40px; place-items: center; border-radius: 50%; background: var(--ui-line-soft, #efebe2); color: var(--ui-muted, #5b6761); font-size: 17px; }
    .ci-next-text { flex: 1 1 auto; min-width: 0; }
    .ci-next-label { color: var(--ui-subtle, #78827c); font-size: var(--ui-text-xs, 11.5px); font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .ci-next h3 { margin-top: 2px; color: var(--ui-ink, #1b2420); font-size: var(--ui-text-lg, 15px); font-weight: 700; line-height: 1.35; }
    .ci-next-text p:not(.ci-next-label) { margin-top: 3px; color: var(--ui-muted, #5b6761); font-size: var(--ui-text-sm, 12.5px); line-height: 1.5; }
    .ci-next-actions { display: flex; flex: 0 0 auto; flex-wrap: wrap; justify-content: flex-end; gap: 8px; }
    .ci-next.is-action { border-color: var(--ui-primary-line, #b7d2c4); background: var(--ui-primary-soft, #e8f1ec); }
    .ci-next.is-action .ci-next-icon { background: var(--ui-primary, #1d4f40); color: #fff; }
    .ci-next.is-warning { border-color: var(--ui-warning-line, #efd59a); background: var(--ui-warning-soft, #fcf1d8); }
    .ci-next.is-warning .ci-next-icon { background: var(--ui-warning, #875705); color: #fff; }
    .ci-next.is-success { border-color: var(--ui-success-line, #b4dac6); background: var(--ui-success-soft, #e2f2ea); }
    .ci-next.is-success .ci-next-icon { background: var(--ui-success, #1d6a4d); color: #fff; }

    /* Focused modals (step dialogs, and the contract screen on the awards pages). */
    .ci-dialog { width: min(1040px, calc(100vw - 32px)); max-width: none; max-height: calc(100dvh - 32px); margin: auto; padding: 0; overflow: hidden; border: 0; border-radius: var(--ui-radius-lg, 8px); background: var(--ui-surface, #fff); color: var(--ui-ink, #1b2420); box-shadow: var(--ui-shadow-lg, 0 24px 60px rgba(27, 36, 32, .28)); }
    .ci-dialog.is-compact { width: min(820px, calc(100vw - 32px)); }
    .ci-dialog[open] { display: flex; flex-direction: column; }
    .ci-dialog::backdrop { background: rgba(17, 24, 39, .55); }
    .ci-dialog-scroll { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
    .ci-dialog .ci { margin: 0; border: 0; border-radius: 0; }
    .ci-dialog .ci-head { position: sticky; top: 0; z-index: 2; padding-right: 64px; background: var(--ui-surface, #fff); }
    .ci-dialog-close { position: absolute; top: 14px; right: 14px; z-index: 3; display: grid; width: 34px; height: 34px; place-items: center; border: 1px solid var(--ui-line, #e5e0d4); border-radius: var(--ui-radius, 6px); background: var(--ui-surface, #fff); color: var(--ui-muted, #5b6761); font-size: 16px; cursor: pointer; }
    .ci-dialog-close:hover { color: var(--ui-ink, #1b2420); background: var(--ui-page, #f3f1eb); }
    .ci-dialog-close:focus-visible { outline: none; box-shadow: var(--ui-focus, 0 0 0 3px rgba(47, 122, 95, .32)); }
    body:has(.ci-dialog[open]) { overflow: hidden; }

    .ci-cta { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px 20px; border-color: var(--ui-primary-line, #b7d2c4); background: var(--ui-primary-soft, #e8f1ec); }
    .ci-cta .ci-card-head { margin: 0; }
    .ci-facts.is-two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ci-howto { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .ci-howto li { display: flex; align-items: flex-start; gap: 9px; padding: 10px 12px; border: 1px solid var(--ui-line, #e5e0d4); border-radius: var(--ui-radius-lg, 8px); background: var(--ui-surface, #fff); color: var(--ui-ink-2, #33403a); font-size: var(--ui-text-sm, 12.5px); line-height: 1.45; }
    .ci-howto span { display: grid; flex: 0 0 22px; width: 22px; height: 22px; place-items: center; border-radius: 50%; background: var(--ui-primary, #1d4f40); color: #fff; font-size: 11px; font-weight: 700; }
    .ci-payable + .ci-form { margin-top: 16px; }
    .ci-payable { display: grid; margin: 0; border: 1px solid var(--ui-line, #e5e0d4); border-radius: var(--ui-radius-lg, 8px); overflow: hidden; }
    .ci-payable > div { display: flex; justify-content: space-between; gap: 16px; padding: 9px 14px; border-bottom: 1px solid var(--ui-line-soft, #efebe2); font-size: var(--ui-text-sm, 12.5px); }
    .ci-payable > div:last-child { border-bottom: 0; }
    .ci-payable dt { color: var(--ui-muted, #5b6761); }
    .ci-payable dd { color: var(--ui-ink, #1b2420); font-weight: 600; font-variant-numeric: tabular-nums; text-align: right; }
    .ci-payable .is-total { background: var(--ui-primary-soft, #e8f1ec); }
    .ci-payable .is-total dt, .ci-payable .is-total dd { color: var(--ui-primary, #1d4f40); font-weight: 700; }
    .ci-check { display: flex; align-items: flex-start; gap: 8px; color: var(--ui-ink-2, #33403a); font-size: var(--ui-text-sm, 12.5px); line-height: 1.4; cursor: pointer; }
    .ci-check input { margin-top: 2px; accent-color: var(--ui-primary, #1d4f40); }
    .ci-extension summary { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 4px 12px; color: var(--ui-primary, #1d4f40); font-weight: 700; cursor: pointer; list-style: none; }
    .ci-extension summary::-webkit-details-marker { display: none; }
    .ci-extension summary i { margin-right: 6px; }
    .ci-extension[open] summary { padding-bottom: 12px; border-bottom: 1px solid var(--ui-line, #e5e0d4); }
    .ci :is(.ci-table tfoot th, .ci-table tfoot td, #ci-x#ci-x) { border-top: 1px solid var(--ui-line, #e5e0d4) !important; border-bottom: 0 !important; background: var(--ui-page, #f3f1eb) !important; color: var(--ui-ink, #1b2420) !important; font-size: var(--ui-text-sm, 12.5px) !important; letter-spacing: normal !important; text-transform: none !important; white-space: normal !important; }
    .ci-card-head h3 .ci-pill { margin-left: 6px; padding: 2px 8px; font-size: var(--ui-text-xs, 11.5px); vertical-align: middle; }
    .ci-outcomes { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .ci-outcome { display: flex; align-items: flex-start; gap: 10px; padding: 11px 13px; border: 1px solid var(--ui-line-strong, #d2cbbb); border-radius: var(--ui-radius-lg, 8px); background: var(--ui-surface, #fff); cursor: pointer; }
    .ci-outcome input { margin-top: 3px; accent-color: var(--ui-primary, #1d4f40); }
    .ci-outcome strong { display: block; color: var(--ui-ink, #1b2420); font-size: var(--ui-text, 13.5px); }
    .ci-outcome span { color: var(--ui-muted, #5b6761); font-size: var(--ui-text-xs, 11.5px); }
    .ci-outcome:has(input:checked) { border-color: var(--ui-primary, #1d4f40); background: var(--ui-primary-soft, #e8f1ec); }

    .ci-history li { position: relative; display: grid; gap: 3px; padding: 0 0 16px 22px; }
    .ci-history li::before { content: ''; position: absolute; top: 5px; left: 0; width: 10px; height: 10px; border: 2px solid var(--ui-primary, #1d4f40); border-radius: 50%; background: var(--ui-surface, #fff); }
    .ci-history li::after { content: ''; position: absolute; top: 19px; bottom: 2px; left: 6px; width: 2px; background: var(--ui-line, #e5e0d4); }
    .ci-history li:last-child { padding-bottom: 0; }
    .ci-history li:last-child::after { display: none; }
    .ci-history-title { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 8px; color: var(--ui-ink, #1b2420); font-weight: 700; }
    .ci-history-title .ci-pill { padding: 2px 8px; font-size: var(--ui-text-xs, 11.5px); }
    .ci-history-meta { color: var(--ui-subtle, #78827c); font-size: var(--ui-text-xs, 11.5px); }
    .ci-history p { color: var(--ui-ink-2, #33403a); font-size: var(--ui-text-sm, 12.5px); line-height: 1.5; }
    .ci-history a { display: inline-flex; align-items: center; gap: 6px; width: fit-content; color: var(--ui-primary, #1d4f40); font-size: var(--ui-text-sm, 12.5px); font-weight: 600; text-decoration: none; }
    .ci-history a:hover { text-decoration: underline; }

    @media (max-width: 900px) {
        .ci-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .ci-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .ci-steps { grid-template-columns: repeat(4, minmax(0, 1fr)); row-gap: 14px; }
        .ci-steps li:nth-child(5)::before { display: none; }
    }
    @media (max-width: 600px) {
        .ci-head { flex-direction: column; padding: 16px; }
        .ci-body { padding: 12px; }
        .ci-card { padding: 14px; }
        .ci-facts, .ci-facts.is-two, .ci-grid, .ci-grid.is-two, .ci-outcomes, .ci-howto { grid-template-columns: 1fr; }
        .ci-next { flex-direction: column; align-items: stretch; }
        .ci-next-icon { display: none; }
        .ci-next-actions { justify-content: stretch; }
        .ci :is(.ci-next-actions .ci-btn, #ci-x#ci-x) { flex: 1 1 auto !important; }
        .ci-dialog, .ci-dialog.is-compact { width: 100vw; max-height: 100dvh; height: 100dvh; border-radius: 0; }
        .ci-dialog .ci-head { padding-right: 58px; }
        .ci-dialog-close { top: 12px; right: 12px; }
        /* Phones: one row of numbered dots, with the current stage named underneath. */
        .ci-steps { grid-template-columns: repeat(8, minmax(0, 1fr)); padding: 12px 10px 10px; }
        .ci-steps li::before, .ci-steps li:nth-child(5)::before { display: block; left: calc(-50% + 11px); right: calc(50% + 11px); top: 10px; }
        .ci-steps li:first-child::before { display: none; }
        .ci-steps li > span:last-child { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
        .ci-dot { width: 22px; height: 22px; font-size: 10px; }
        .ci-steps-caption { display: block; margin-top: -8px; color: var(--ui-muted, #5b6761); font-size: var(--ui-text-sm, 12.5px); text-align: center; }
        .ci-steps-caption strong { color: var(--ui-ink, #1b2420); }
        .ci-table .is-input { width: 104px; }
        .ci :is(.ci-table th, .ci-table td, #ci-x#ci-x) { padding: 8px !important; }
        .ci :is(.ci-table th, #ci-x#ci-x) { white-space: normal !important; }
        .ci-form-foot { flex-direction: column; align-items: stretch; }
        .ci :is(.ci-form-foot .ci-btn, #ci-x#ci-x) { width: 100% !important; }
    }
</style>
@endonce

@if($part === 'section')
<section class="ci" id="contract-implementation-{{ $award->id }}" aria-labelledby="{{ $uid }}-title">
    <header class="ci-head">
        <div>
            <p class="ci-eyebrow">Contract Implementation</p>
            <h2 id="{{ $uid }}-title">{{ $award->project?->title ?? 'Awarded contract' }}</h2>
            <p class="ci-head-meta">
                <span class="ci-mono">{{ $award->project?->reference_no ?: 'Award #'.$award->id }}</span>
                <span><i class="fas fa-building" aria-hidden="true"></i> {{ $bid?->user?->company ?: $bid?->user?->name ?: 'Awardee' }}</span>
            </p>
        </div>
        <span class="ci-pill is-{{ $statusTone }}">{{ $implementation?->label() ?? 'Awaiting contract terms' }}</span>
    </header>

    <div class="ci-body">
        <ol class="ci-steps" aria-label="Implementation stages">
            @foreach($stages as $key => $label)
                @php
                    $index = $loop->index;
                    $state = $status === CI::COMPLETED || $index < $current ? 'is-done' : ($index === $current ? 'is-current' : '');
                    if ($index === $current && $status === CI::FOR_CORRECTION) $state .= ' is-warning';
                @endphp
                <li class="{{ $state }}" @if($index === $current) aria-current="step" @endif>
                    <span class="ci-dot">@if(str_contains($state, 'is-done'))<i class="fas fa-check" aria-hidden="true"></i>@else{{ $index + 1 }}@endif</span>
                    <span>{{ $index === $current && $status === CI::FOR_CORRECTION ? 'Correction' : $label }}</span>
                </li>
            @endforeach
        </ol>
        <p class="ci-steps-caption" aria-hidden="true">Stage {{ $current + 1 }} of {{ count($stages) }}: <strong>{{ $status === CI::FOR_CORRECTION ? 'Correction' : $stages[$stageKeys[$current]] }}</strong></p>

        {{-- What happens now, who does it, and the one button for it. The forms open in focused modals below. --}}
        @php
            $step = fn (string $key) => $uid.'-step-'.$key;
            $lastDelivery = $implementation?->events?->where('action', 'supplier_delivery')->last();
            $dueText = $deadline
                ? ($daysLeft < 0 ? abs($daysLeft).' '.\Illuminate\Support\Str::plural('day', abs($daysLeft)).' overdue' : ($daysLeft === 0 ? 'due today' : $daysLeft.' '.\Illuminate\Support\Str::plural('day', $daysLeft).' left'))
                : null;
            $next = match (true) {
                $status === CI::COMPLETED => ['tone' => 'success', 'icon' => 'fa-circle-check', 'title' => 'Contract implementation completed', 'text' => 'Delivery, inspection, acceptance, payment and the warranty are recorded below.', 'who' => null, 'button' => null],
                ! $configured && $isLgu => ['tone' => 'action', 'icon' => 'fa-file-signature', 'title' => 'Record the contract terms', 'text' => 'Copy the delivery deadline, destination, items with unit prices, and the warranty from the signed contract. The supplier can deliver once these are recorded.', 'who' => 'BAC Secretariat or Supply Office', 'button' => ['terms', 'Record contract terms', 'fa-file-signature']],
                ! $configured => ['tone' => 'wait', 'icon' => 'fa-hourglass-half', 'title' => 'The LGU is recording the delivery terms from the signed contract.', 'text' => 'You can submit your delivery once the deadline, location and quantities are recorded here.', 'who' => null, 'button' => null],
                $viewerMode === 'bidder' && $deliveryOpen => ['tone' => $status === CI::FOR_CORRECTION ? 'warning' : 'action', 'icon' => 'fa-truck', 'title' => $status === CI::FOR_CORRECTION ? 'Submit the correction or remaining items' : 'Your delivery is next', 'text' => 'Deliver to '.$implementation->delivery_location.' by '.$deadline->format('M d, Y').' ('.$dueText.'). Then enter what you delivered and attach your delivery receipt.', 'who' => null, 'button' => ['delivery', $status === CI::FOR_CORRECTION ? 'Submit correction' : 'Submit delivery', 'fa-truck']],
                $isLgu && $deliveryOpen => ['tone' => $status === CI::FOR_CORRECTION ? 'warning' : 'wait', 'icon' => 'fa-truck', 'title' => $status === CI::FOR_CORRECTION ? 'Waiting for the correction or remaining items' : 'Waiting for the supplier to deliver', 'text' => 'Due at '.$implementation->delivery_location.' on '.$deadline->format('M d, Y').' ('.$dueText.'). The supplier submits the delivery from its account; you record the receipt next.', 'who' => 'Supplier', 'button' => null],
                $isLgu && $status === CI::DELIVERED => ['tone' => 'action', 'icon' => 'fa-box-open', 'title' => 'Record the actual receipt', 'text' => 'The supplier reports a delivery'.($lastDelivery ? ' on '.\Illuminate\Support\Carbon::parse($lastDelivery->details['delivered_on'] ?? $lastDelivery->occurred_at)->format('M d, Y').' ('.($lastDelivery->details['delivery_reference'] ?? 'no receipt no.').')' : '').'. Count what actually arrived.', 'who' => 'Supply Office', 'button' => ['receive', 'Record receipt', 'fa-box-open']],
                $isLgu && $status === CI::FOR_INSPECTION => ['tone' => 'action', 'icon' => 'fa-clipboard-check', 'title' => 'Inspect the goods', 'text' => 'Check the received goods against the contract and record the result with its Inspection and Acceptance Report.', 'who' => 'Inspection and Acceptance Committee', 'button' => ['inspect', 'Record inspection', 'fa-clipboard-check']],
                $isLgu && $status === CI::ACCEPTED => ['tone' => 'action', 'icon' => 'fa-file-invoice', 'title' => 'Start payment processing', 'text' => 'The goods were accepted'.($implementation->accepted_on ? ' on '.$implementation->accepted_on->format('M d, Y') : '').'.'.($damages['priced'] ? ' Amount payable after deductions: '.$peso($payable).'.' : ''), 'who' => 'Accounting / Budget Office', 'button' => ['payment', 'Start payment processing', 'fa-file-invoice']],
                $isLgu && $status === CI::PAYMENT_PROCESSING => ['tone' => 'action', 'icon' => 'fa-money-check', 'title' => 'Record the payment', 'text' => 'Record the Paid status once the payment is released.'.($damages['priced'] ? ' Amount payable: '.$peso($payable).'.' : ''), 'who' => 'Treasury / Accounting', 'button' => ['payment', 'Record paid', 'fa-money-check']],
                $isLgu && $status === CI::PAID && $warrantyEnds && $warrantyEnds->isFuture() => ['tone' => 'wait', 'icon' => 'fa-shield-halved', 'title' => 'Under warranty until '.$warrantyEnds->format('M d, Y'), 'text' => 'Release the warranty security and complete the contract after the warranty ends'.($implementation->supply_type === 'expendable' ? ', or earlier once the expendable supplies are consumed.' : '.'), 'who' => 'BAC Secretariat or Supply Office', 'button' => ['closeout', 'Close out', 'fa-flag-checkered']],
                $isLgu && $status === CI::PAID => ['tone' => 'action', 'icon' => 'fa-flag-checkered', 'title' => 'Release the warranty security and complete', 'text' => 'Confirm the supplies are free from defects, release the security, and complete the contract.', 'who' => 'BAC Secretariat or Supply Office', 'button' => ['closeout', 'Close out the contract', 'fa-flag-checkered']],
                default => ['tone' => 'wait', 'icon' => 'fa-hourglass-half', 'title' => match ($status) {
                    CI::DELIVERED => 'Your delivery was submitted. The LGU is recording the actual receipt.',
                    CI::FOR_INSPECTION => 'The LGU is inspecting the delivered goods.',
                    CI::ACCEPTED => 'Your delivery was accepted. The LGU will process the payment.',
                    CI::PAYMENT_PROCESSING => 'Your payment is being processed by the LGU.',
                    CI::PAID => $warrantyEnds && $warrantyEnds->isFuture() ? 'Payment is recorded. The goods are under warranty until '.$warrantyEnds->format('M d, Y').'.' : 'Payment is recorded. The LGU is releasing the warranty security and closing out the contract.',
                    default => 'No action is needed from you right now.',
                }, 'text' => $status === CI::PAID && $warrantyEnds && $warrantyEnds->isFuture() ? 'Report any defect so it can be corrected under the warranty.' : null, 'who' => null, 'button' => null],
            };
            $canExtend = $isLgu && $configured && $beforeAcceptance;
        @endphp
        <div class="ci-next is-{{ $next['tone'] }}" role="status">
            <span class="ci-next-icon" aria-hidden="true"><i class="fas {{ $next['icon'] }}"></i></span>
            <div class="ci-next-text">
                <p class="ci-next-label">{{ $status === CI::COMPLETED ? 'Done' : 'Next step' }}@if($next['who']) · {{ $next['who'] }}@endif</p>
                <h3>{{ $next['title'] }}</h3>
                @if($next['text'])<p>{{ $next['text'] }}</p>@endif
            </div>
            @if($next['button'] || $canExtend)
                <div class="ci-next-actions">
                    @if($next['button'])
                        @if($next['button'][0] === 'delivery')
                            <button type="button" class="ci-btn is-primary" data-ci-delivery="{{ $award->id }}"><i class="fas {{ $next['button'][2] }}" aria-hidden="true"></i> {{ $next['button'][1] }}</button>
                        @else
                            <button type="button" class="ci-btn is-primary" data-ci-step="{{ $step($next['button'][0]) }}"><i class="fas {{ $next['button'][2] }}" aria-hidden="true"></i> {{ $next['button'][1] }}</button>
                        @endif
                    @endif
                    @if($canExtend)
                        <button type="button" class="ci-btn" data-ci-step="{{ $step('extension') }}"><i class="fas fa-calendar-plus" aria-hidden="true"></i> Record time extension</button>
                    @endif
                </div>
            @endif
        </div>

        <dl class="ci-facts">
            <div><dt>Winning supplier</dt><dd>{{ $bid?->user?->company ?: $bid?->user?->name ?: 'Awardee' }}</dd></div>
            <div><dt>Contract signed</dt><dd>{{ $bid?->contract_signed_at?->timezone($tz)->format('M d, Y') ?? $award->contract_date?->format('M d, Y') ?? 'Not recorded' }}</dd></div>
            <div><dt>Notice to Proceed issued</dt><dd>{{ $ntpAt?->timezone($tz)->format('M d, Y g:i A') ?? 'Not recorded' }}</dd></div>
            <div>
                <dt>Contract delivery deadline</dt>
                @if($deadline)
                    <dd>
                        {{ $deadline->format('M d, Y') }}
                        @if($implementation->revised_deadline)<small class="is-soon">Extended from {{ $implementation->delivery_deadline->format('M d, Y') }}</small>@endif
                        @if($deliveryOpen)
                            @if($daysLeft < 0)<small class="is-late">{{ abs($daysLeft) }} {{ \Illuminate\Support\Str::plural('day', abs($daysLeft)) }} overdue</small>
                            @elseif($daysLeft === 0)<small class="is-soon">Due today</small>
                            @else<small class="{{ $daysLeft <= 7 ? 'is-soon' : 'is-ok' }}">{{ $daysLeft }} {{ \Illuminate\Support\Str::plural('day', $daysLeft) }} left</small>@endif
                        @endif
                    </dd>
                @else
                    <dd class="is-pending">Awaiting signed contract details</dd>
                @endif
            </div>
            <div><dt>Delivery location</dt>@if($implementation?->delivery_location)<dd>{{ $implementation->delivery_location }}</dd>@else<dd class="is-pending">Awaiting signed contract details</dd>@endif</div>
            <div><dt>Signed contract reference</dt>@if($implementation?->signed_contract_reference)<dd>{{ $implementation->signed_contract_reference }}</dd>@else<dd class="is-pending">Not recorded yet</dd>@endif</div>
            @if($implementation?->hasWarrantyTerms())
                <div>
                    <dt>Warranty</dt>
                    <dd>
                        {{ $implementation->warranty_months }} months after acceptance
                        <small class="is-ok">{{ $implementation->supplyTypeLabel() }}</small>
                    </dd>
                </div>
                <div>
                    <dt>Warranty security</dt>
                    <dd>
                        {{ rtrim(rtrim(number_format((float) $implementation->warranty_percent, 2), '0'), '.') }}% · {{ $peso($securityAmount) }}
                        <small class="is-ok">{{ CI::WARRANTY_SECURITIES[$implementation->warranty_security] }}</small>
                    </dd>
                </div>
                <div>
                    <dt>Warranty period</dt>
                    @if($warrantyEnds)
                        <dd>
                            {{ $implementation->accepted_on->format('M d, Y') }} – {{ $warrantyEnds->format('M d, Y') }}
                            @if($implementation->warranty_released_on)<small class="is-ok">Security released {{ $implementation->warranty_released_on->format('M d, Y') }}</small>
                            @elseif($warrantyEnds->isFuture())<small class="is-soon">Ends in {{ (int) today()->diffInDays($warrantyEnds) }} days</small>
                            @else<small class="is-ok">Ended: security can be released</small>@endif
                        </dd>
                    @else
                        <dd class="is-pending">Starts on acceptance</dd>
                    @endif
                </div>
            @endif
        </dl>

        @if($showDamages)
            {{-- Liquidated damages: IRR Sec. 71.1.4 --}}
            <div class="ci-card">
                <div class="ci-card-head">
                    <h3>Liquidated damages @if($damages['accruing'])<span class="ci-pill is-warning">Still accruing</span>@endif</h3>
                    <p>0.1% of the cost of the delayed goods for every day of delay after {{ $damages['deadline']->format('M d, Y') }}@if($implementation->extension_keeps_ld) (the original deadline: the approved extension kept damages running)@endif, until the goods are delivered and accepted. Deducted from the payment (RA 12009 IRR Sec. 71.1.4).</p>
                </div>
                <div class="ci-table-wrap">
                    <table class="ci-table">
                        <thead><tr><th scope="col">Item</th><th scope="col" class="is-num">Delayed qty</th><th scope="col" class="is-num">Days late</th><th scope="col" class="is-num">Amount</th></tr></thead>
                        <tbody>
                            @foreach($damages['items'] as $row)
                                <tr>
                                    <td>{{ $row['description'] }} <span class="ci-hint">({{ $row['unit'] }}@if($row['unit_price'] !== null)<span> · {{ $peso($row['unit_price']) }} each</span>@endif)</span></td>
                                    <td class="is-num">{{ $row['delayed_quantity'] > 0 ? $qty($row['delayed_quantity']) : '—' }}</td>
                                    <td class="is-num">{{ $row['days'] ?: '—' }}{{ $row['accruing'] ? '+' : '' }}</td>
                                    <td class="is-num">{{ $row['unit_price'] === null ? 'No unit price' : $peso($row['amount']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr><th scope="row" colspan="3">Total{{ $damages['accruing'] ? ' as of today' : '' }} · {{ number_format($damages['share'] * 100, 2) }}% of the contract price</th><td class="is-num"><strong>{{ $peso($damages['total']) }}</strong></td></tr></tfoot>
                    </table>
                </div>
                @if(! $damages['priced'])
                    <p class="ci-hint" style="margin-top:8px">Unit prices were not recorded with these contract terms, so the amount cannot be computed. Compute it from the signed contract.</p>
                @endif
                @if($damages['may_rescind'])
                    <div class="ci-note is-danger" style="margin-top:12px"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i><div><strong>Liquidated damages have reached 10% of the contract price.</strong> The LGU may rescind the contract and impose sanctions on top of the damages (Sec. 71.1.4).</div></div>
                @endif
            </div>
        @endif

        @if($configured)
            {{-- Contract items --}}
            <div class="ci-card">
                <div class="ci-card-head"><h3>Contract items and quantities</h3></div>
                <div class="ci-table-wrap">
                    <table class="ci-table">
                        <thead><tr><th scope="col">Item</th><th scope="col" class="is-num">Contract quantity</th><th scope="col">Unit</th><th scope="col" class="is-num">Unit price</th></tr></thead>
                        <tbody>@foreach($items as $item)<tr><td>{{ $item['description'] }}</td><td class="is-num">{{ $qty($item['quantity']) }}</td><td>{{ $item['unit'] }}</td><td class="is-num">{{ is_numeric($item['unit_price'] ?? null) ? $peso($item['unit_price']) : '—' }}</td></tr>@endforeach</tbody>
                    </table>
                </div>
            </div>

            @if($status === CI::FOR_CORRECTION && ($lastCorrection?->details['correction_request'] ?? null))
                <div class="ci-note is-warning" role="status">
                    <i class="fas fa-rotate-left" aria-hidden="true"></i>
                    <div>
                        <strong>Correction or remaining delivery requested</strong>
                        {{ $lastCorrection->details['correction_request'] }}
                        @if($lastCorrection->details['deficiencies'] ?? null)<br>Deficiencies: {{ $lastCorrection->details['deficiencies'] }}@endif
                    </div>
                </div>
            @endif
        @endif

        @if($implementation && $implementation->events->isNotEmpty())
            <div class="ci-card">
                <div class="ci-card-head"><h3>Activity history</h3></div>
                <ol class="ci-history">
                    @foreach($implementation->events->sortByDesc('occurred_at') as $event)
                        <li>
                            <span class="ci-history-title">
                                {{ \Illuminate\Support\Str::headline($event->action) }}
                                @if($event->status_to)<span class="ci-pill is-info">{{ \Illuminate\Support\Str::headline($event->status_to) }}</span>@endif
                            </span>
                            <span class="ci-history-meta">{{ $event->occurred_at?->timezone($tz)->format('M d, Y g:i A') }} &middot; {{ $event->actor?->name ?? 'User no longer available' }}</span>
                            @if($event->remarks)<p>{{ $event->remarks }}</p>@endif
                            @if($event->details['actual_received_on'] ?? null)<p>Actual receipt: {{ $event->details['actual_received_on'] }}</p>@endif
                            @if($event->details['iar_number'] ?? null)<p>{{ $event->details['iar_number'] }} · inspected {{ \Illuminate\Support\Carbon::parse($event->details['inspected_on'])->format('M d, Y') }}</p>@endif
                            @if($event->details['inspection_findings'] ?? null)<p>Inspection: {{ $event->details['inspection_findings'] }}</p>@endif
                            @if($event->details['new_deadline'] ?? null)<p>Deadline moved from {{ \Illuminate\Support\Carbon::parse($event->details['previous_deadline'])->format('M d, Y') }} to {{ \Illuminate\Support\Carbon::parse($event->details['new_deadline'])->format('M d, Y') }} · {{ $event->details['approval_reference'] }}{{ ($event->details['keeps_liquidated_damages'] ?? false) ? ' · liquidated damages still apply' : '' }}</p>@endif
                            @if(array_key_exists('liquidated_damages', $event->details ?? []))<p>Liquidated damages at payment: {{ ($event->details['damages_priced'] ?? true) ? $peso($event->details['liquidated_damages']) : 'not computed (no unit prices)' }}@if($event->details['warranty_security_amount'] ?? null) · warranty security {{ $peso($event->details['warranty_security_amount']) }}@endif</p>@endif
                            @if($event->details['warranty_released_on'] ?? null)<p>Warranty security released {{ \Illuminate\Support\Carbon::parse($event->details['warranty_released_on'])->format('M d, Y') }}{{ ($event->details['released_after_consumption'] ?? false) ? ' (supplies consumed)' : '' }}</p>@endif
                            @if($event->details['correction_request'] ?? null)<p>Correction: {{ $event->details['correction_request'] }}</p>@endif
                            @if($event->document_path)<a href="{{ route('contract-implementation.document', $event) }}" target="_blank" rel="noopener"><i class="fas fa-file-lines" aria-hidden="true"></i> {{ $event->document_name ?: 'Supporting document' }}</a>@endif
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    </div>

    {{-- The LGU's steps, each in its own focused modal. Opened from the "Next step" card. --}}
    @if($isLgu)
        @php
            $stepTitle = $award->project?->title ?? 'Awarded contract';
            $stepMeta = ($award->project?->reference_no ?: 'Award #'.$award->id).' · '.($bid?->user?->company ?: $bid?->user?->name ?: 'Awardee');
            $failed = fn (string $key) => $mine && old('ci_form') === $key;
        @endphp

        @if(! $configured)
            @php $suggest = \App\Support\ContractTermsSuggestion::for($award); @endphp
            <x-ci-step-dialog :id="$step('terms')" eyebrow="Record the contract terms" :title="$stepTitle" :meta="$stepMeta" :reopen="$failed('terms')"
                :steps="['Copy the delivery deadline, delivery location and contract reference.', 'List each item with its quantity, unit and unit price.', 'Set the warranty, attach the signed contract, and save.']">
                @include('partials.contract-implementation-step-error', ['key' => 'terms'])
                <div class="ci-card">
                    <form class="ci-form" method="POST" action="{{ route($configureRoute, $award) }}" enctype="multipart/form-data">
                        @csrf @method('PUT')
                        <input type="hidden" name="ci_award" value="{{ $award->id }}">
                        <input type="hidden" name="ci_form" value="terms">
                        <p class="ci-prefill"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> Filled in from the project and its purchase request. Check each value against the signed contract before saving.</p>
                        <div class="ci-grid">
                            <div class="ci-field">
                                <label class="ci-label" for="{{ $uid }}-deadline">Delivery deadline <span class="ci-req" aria-hidden="true">*</span></label>
                                <input class="ci-input" type="date" id="{{ $uid }}-deadline" name="delivery_deadline" value="{{ $old('delivery_deadline', $suggest['deadline']?->toDateString()) }}" required @if($err('delivery_deadline')) aria-invalid="true" @endif>
                                @if($suggest['deadline_hint'])<span class="ci-hint">{{ $suggest['deadline_hint'] }}</span>@endif
                                @if($err('delivery_deadline'))<span class="ci-error">{{ $err('delivery_deadline') }}</span>@endif
                            </div>
                            <div class="ci-field">
                                <label class="ci-label" for="{{ $uid }}-location">Delivery location <span class="ci-req" aria-hidden="true">*</span></label>
                                <input class="ci-input" id="{{ $uid }}-location" name="delivery_location" maxlength="255" value="{{ $old('delivery_location', $suggest['location']) }}" placeholder="e.g. Municipal Warehouse, San Jose" required @if($err('delivery_location')) aria-invalid="true" @endif>
                                @if($err('delivery_location'))<span class="ci-error">{{ $err('delivery_location') }}</span>@endif
                            </div>
                            <div class="ci-field">
                                <label class="ci-label" for="{{ $uid }}-reference">Signed contract reference <span class="ci-req" aria-hidden="true">*</span></label>
                                <input class="ci-input" id="{{ $uid }}-reference" name="signed_contract_reference" maxlength="255" value="{{ $old('signed_contract_reference', $suggest['reference']) }}" placeholder="Contract no., page or clause" required @if($err('signed_contract_reference')) aria-invalid="true" @endif>
                                @if($err('signed_contract_reference'))<span class="ci-error">{{ $err('signed_contract_reference') }}</span>@endif
                            </div>
                        </div>

                        @php $oldItems = array_values((array) $old('contract_items', $suggest['items'])); @endphp
                        <div class="ci-field">
                            <span class="ci-label" id="{{ $uid }}-items-label">Items, quantities and unit prices <span class="ci-req" aria-hidden="true">*</span></span>
                            <span class="ci-hint">Unit prices come from the signed contract; they are the basis of liquidated damages if the delivery is late.@if($suggest['prices_hint']) <strong>{{ $suggest['prices_hint'] }}</strong>@endif</span>
                            <div class="ci-table-wrap">
                                <table class="ci-table" aria-labelledby="{{ $uid }}-items-label">
                                    <thead><tr><th scope="col">Item</th><th scope="col" class="is-input">Quantity</th><th scope="col" class="is-input">Unit</th><th scope="col" class="is-input">Unit price (₱)</th><th scope="col" class="is-remove"><span class="sr-only">Remove</span></th></tr></thead>
                                    <tbody data-ci-items data-ci-next="{{ count($oldItems) }}">
                                        @foreach($oldItems as $i => $row)
                                            <tr>
                                                <td><input class="ci-input" name="contract_items[{{ $i }}][description]" value="{{ $row['description'] ?? '' }}" maxlength="255" required placeholder="e.g. Laptop computer, 16 GB RAM" aria-label="Item {{ $i + 1 }}"></td>
                                                <td class="is-input"><input class="ci-input" type="number" name="contract_items[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? '' }}" min="0.001" step="0.001" required placeholder="0" aria-label="Quantity {{ $i + 1 }}"></td>
                                                <td class="is-input"><input class="ci-input" name="contract_items[{{ $i }}][unit]" value="{{ $row['unit'] ?? '' }}" maxlength="50" required placeholder="units, pcs, sets" list="{{ $uid }}-units" aria-label="Unit {{ $i + 1 }}"></td>
                                                <td class="is-input"><input class="ci-input" type="number" name="contract_items[{{ $i }}][unit_price]" value="{{ $row['unit_price'] ?? '' }}" min="0.01" step="0.01" required placeholder="0.00" aria-label="Unit price {{ $i + 1 }}"></td>
                                                <td class="is-remove">@if($i > 0)<button type="button" class="ci-btn is-icon" data-ci-remove-item aria-label="Remove item {{ $i + 1 }}"><i class="fas fa-xmark" aria-hidden="true"></i></button>@endif</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <datalist id="{{ $uid }}-units">@foreach(['units', 'pcs', 'sets', 'lot', 'boxes', 'reams', 'liters', 'kg'] as $unit)<option value="{{ $unit }}"></option>@endforeach</datalist>
                            @if($err('contract_items'))<span class="ci-error">{{ $err('contract_items') }}</span>@endif
                            <div><button type="button" class="ci-btn is-small" data-ci-add-item><i class="fas fa-plus" aria-hidden="true"></i> Add item</button></div>
                        </div>

                        @include('partials.contract-implementation-warranty')

                        <div class="ci-grid is-two">
                            <div class="ci-field">
                                <label class="ci-label" for="{{ $uid }}-contract-file">Signed contract copy <span class="ci-req" aria-hidden="true">*</span></label>
                                <input class="ci-input ci-file" type="file" id="{{ $uid }}-contract-file" name="document" accept="{{ $accept }}" required>
                                <span class="ci-hint">Private: only the BAC and this supplier can open it.</span>
                                @if($err('document'))<span class="ci-error">{{ $err('document') }}</span>@endif
                            </div>
                            <div class="ci-field">
                                <label class="ci-label" for="{{ $uid }}-configure-remarks">Remarks <span class="ci-req" aria-hidden="true">*</span></label>
                                <textarea class="ci-input" id="{{ $uid }}-configure-remarks" name="remarks" rows="2" maxlength="2000" required placeholder="e.g. Terms copied from Section 5 of the signed contract.">{{ $old('remarks') }}</textarea>
                                @if($err('remarks'))<span class="ci-error">{{ $err('remarks') }}</span>@endif
                            </div>
                        </div>

                        <div class="ci-form-foot">
                            <p>After saving, the supplier can submit its delivery.</p>
                            <button class="ci-btn is-primary" type="submit"><i class="fas fa-file-signature" aria-hidden="true"></i> Save signed contract terms</button>
                        </div>
                    </form>
                </div>
            </x-ci-step-dialog>
        @endif

        @if($status === CI::DELIVERED)
            <x-ci-step-dialog :id="$step('receive')" eyebrow="Record the actual receipt" :title="$stepTitle" :meta="$stepMeta" :reopen="$failed('receive')"
                :steps="['Enter the date the goods actually arrived.', 'Count and enter the quantity received for each item.', 'Attach the receiving document and save. Inspection comes next.']">
                @include('partials.contract-implementation-step-error', ['key' => 'receive'])
                <div class="ci-card">
                    <form class="ci-form" method="POST" action="{{ route($actionRoute, $award) }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="ci_award" value="{{ $award->id }}">
                        <input type="hidden" name="ci_form" value="receive">
                        <input type="hidden" name="action" value="receive">
                        <div class="ci-grid">
                            <div class="ci-field">
                                <label class="ci-label" for="{{ $uid }}-received-on">Actual receipt date <span class="ci-req" aria-hidden="true">*</span></label>
                                <input class="ci-input" type="date" id="{{ $uid }}-received-on" name="actual_received_on" value="{{ $old('actual_received_on') }}" required @if($err('actual_received_on')) aria-invalid="true" @endif>
                                @if($err('actual_received_on'))<span class="ci-error">{{ $err('actual_received_on') }}</span>@endif
                            </div>
                        </div>
                        @include('partials.contract-implementation-quantities', ['field' => 'received_quantities', 'heading' => 'Received quantity'])
                        @include('partials.contract-implementation-record', ['fileLabel' => 'Receiving document', 'remarksLabel' => 'Receipt remarks'])
                        <div class="ci-form-foot">
                            <p>Moves the contract to inspection.</p>
                            <button class="ci-btn is-primary" type="submit"><i class="fas fa-box-open" aria-hidden="true"></i> Record receipt and send for inspection</button>
                        </div>
                    </form>
                </div>
            </x-ci-step-dialog>
        @endif

        @if($status === CI::FOR_INSPECTION)
            <x-ci-step-dialog :id="$step('inspect')" eyebrow="Record the inspection" :title="$stepTitle" :meta="$stepMeta" :reopen="$failed('inspect')"
                :steps="['Choose Accepted, or Not yet complete if items are short or defective.', 'Enter the inspection date, the IAR number, the findings and the accepted quantities.', 'Attach the inspection report and save. The supplier is notified.']">
                @include('partials.contract-implementation-step-error', ['key' => 'inspect'])
                <div class="ci-card">
                    <form class="ci-form" method="POST" action="{{ route($actionRoute, $award) }}" enctype="multipart/form-data" data-ci-inspection>
                        @csrf
                        <input type="hidden" name="ci_award" value="{{ $award->id }}">
                        <input type="hidden" name="ci_form" value="inspect">
                        <input type="hidden" name="action" value="inspect">
                        @php $outcome = $old('outcome', 'accepted'); @endphp
                        <fieldset class="ci-field" style="border:0;padding:0;margin:0">
                            <legend class="ci-label" style="margin-bottom:6px">Inspection result <span class="ci-req" aria-hidden="true">*</span></legend>
                            <div class="ci-outcomes">
                                <label class="ci-outcome"><input type="radio" name="outcome" value="accepted" @checked($outcome === 'accepted') required><span><strong>Accepted</strong><span>All items conform to the contract.</span></span></label>
                                <label class="ci-outcome"><input type="radio" name="outcome" value="for_correction" @checked($outcome === 'for_correction')><span><strong>Not yet complete</strong><span>Items are still to be delivered, or are damaged or non-conforming.</span></span></label>
                            </div>
                        </fieldset>
                        <div class="ci-grid is-two">
                            <div class="ci-field">
                                <label class="ci-label" for="{{ $uid }}-inspected-on">Inspection date <span class="ci-req" aria-hidden="true">*</span></label>
                                <input class="ci-input" type="date" id="{{ $uid }}-inspected-on" name="inspected_on" value="{{ $old('inspected_on') }}" max="{{ today()->toDateString() }}" required @if($err('inspected_on')) aria-invalid="true" @endif>
                                <span class="ci-hint">When the goods are accepted, the warranty starts on this date.</span>
                                @if($err('inspected_on'))<span class="ci-error">{{ $err('inspected_on') }}</span>@endif
                            </div>
                            <div class="ci-field">
                                <label class="ci-label" for="{{ $uid }}-iar">Inspection and Acceptance Report no. <span class="ci-req" aria-hidden="true">*</span></label>
                                <input class="ci-input" id="{{ $uid }}-iar" name="iar_number" maxlength="100" value="{{ $old('iar_number') }}" placeholder="e.g. IAR 2026-10-015" required @if($err('iar_number')) aria-invalid="true" @endif>
                                @if($err('iar_number'))<span class="ci-error">{{ $err('iar_number') }}</span>@endif
                            </div>
                        </div>
                        <div class="ci-field">
                            <label class="ci-label" for="{{ $uid }}-findings">Inspection findings <span class="ci-req" aria-hidden="true">*</span></label>
                            <textarea class="ci-input" id="{{ $uid }}-findings" name="inspection_findings" rows="3" maxlength="5000" required>{{ $old('inspection_findings') }}</textarea>
                            @if($err('inspection_findings'))<span class="ci-error">{{ $err('inspection_findings') }}</span>@endif
                        </div>
                        @include('partials.contract-implementation-quantities', ['field' => 'accepted_quantities', 'heading' => 'Accepted quantity'])
                        <div class="ci-grid is-two" data-ci-correction @if($outcome !== 'for_correction') hidden @endif>
                            <div class="ci-field">
                                <label class="ci-label" for="{{ $uid }}-deficiencies">Deficiencies</label>
                                <textarea class="ci-input" id="{{ $uid }}-deficiencies" name="deficiencies" rows="2" maxlength="5000">{{ $old('deficiencies') }}</textarea>
                            </div>
                            <div class="ci-field">
                                <label class="ci-label" for="{{ $uid }}-correction">What the supplier must deliver or correct <span class="ci-req" aria-hidden="true">*</span></label>
                                <textarea class="ci-input" id="{{ $uid }}-correction" name="correction_request" rows="2" maxlength="5000" @if($err('correction_request')) aria-invalid="true" @endif>{{ $old('correction_request') }}</textarea>
                                @if($err('correction_request'))<span class="ci-error">{{ $err('correction_request') }}</span>@endif
                            </div>
                        </div>
                        @include('partials.contract-implementation-record', ['fileLabel' => 'Inspection report / supporting proof', 'remarksLabel' => 'Remarks'])
                        <div class="ci-form-foot">
                            <p>The supplier is notified of the result.</p>
                            <button class="ci-btn is-primary" type="submit"><i class="fas fa-clipboard-check" aria-hidden="true"></i> Save inspection result</button>
                        </div>
                    </form>
                </div>
            </x-ci-step-dialog>
        @endif

        @if(in_array($status, [CI::ACCEPTED, CI::PAYMENT_PROCESSING], true))
                @php
                    [$action, $title, $text, $fileLabel, $button, $icon] = $status === CI::ACCEPTED
                        ? ['payment_processing', 'Goods accepted', 'Prepare the payment documents, then move the contract to payment processing.', 'Supporting record', 'Move to Payment Processing', 'fa-file-invoice']
                        : ['paid', 'Payment processing', 'Tracking only: this records the status and does not transfer or issue money.', 'Payment voucher / supporting record', 'Record Paid status', 'fa-money-check'];
                @endphp
            <x-ci-step-dialog :id="$step('payment')" :eyebrow="$status === CI::ACCEPTED ? 'Start payment processing' : 'Record the payment'" :title="$stepTitle" :meta="$stepMeta" :reopen="$failed('payment')"
                :steps="$status === CI::ACCEPTED
                    ? ['Check the amount payable below: liquidated damages and retention are already deducted.', 'Attach the supporting record, such as the disbursement voucher.', 'Save to move the contract to payment processing.']
                    : ['Check the amount payable below.', 'Attach the payment voucher or check.', 'Save to record the contract as Paid. No money moves through this system.']">
                @include('partials.contract-implementation-step-error', ['key' => 'payment'])
                <div class="ci-card">
                    <dl class="ci-payable">
                        <div><dt>Contract price</dt><dd>{{ $peso($contractPrice) }}</dd></div>
                        <div><dt>Less liquidated damages @if($damages['max_days'])({{ $damages['max_days'] }} {{ \Illuminate\Support\Str::plural('day', $damages['max_days']) }} late)@endif</dt><dd>{{ $damages['priced'] ? '− '.$peso($damages['total']) : 'Compute from the contract' }}</dd></div>
                        @if($implementation->warranty_security === 'retention')
                            <div><dt>Less warranty retention ({{ rtrim(rtrim(number_format((float) $implementation->warranty_percent, 2), '0'), '.') }}%, released after the warranty)</dt><dd>− {{ $peso($retention) }}</dd></div>
                        @elseif($implementation->warranty_security === 'bank_guarantee')
                            <div><dt>Warranty security</dt><dd>Special bank guarantee of {{ $peso($securityAmount) }} required before payment</dd></div>
                        @endif
                        <div class="is-total"><dt>Amount payable, before taxes</dt><dd>{{ $damages['priced'] ? $peso($payable) : '—' }}</dd></div>
                    </dl>
                    <form class="ci-form" method="POST" action="{{ route($actionRoute, $award) }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="ci_award" value="{{ $award->id }}">
                        <input type="hidden" name="ci_form" value="payment">
                        <input type="hidden" name="action" value="{{ $action }}">
                        @include('partials.contract-implementation-record', ['fileLabel' => $fileLabel, 'remarksLabel' => 'Remarks'])
                        <div class="ci-form-foot">
                            <p>Only authorized LGU users can record this step.</p>
                            <button class="ci-btn is-primary" type="submit"><i class="fas {{ $icon }}" aria-hidden="true"></i> {{ $button }}</button>
                        </div>
                    </form>
                </div>
            </x-ci-step-dialog>
        @endif

        @if($status === CI::PAID)
                @php
                    $canRelease = ! $warrantyEnds || ! $warrantyEnds->isFuture();
                    $expendable = ($implementation->supply_type ?? $old('supply_type')) === 'expendable';
                @endphp
            <x-ci-step-dialog :id="$step('closeout')" eyebrow="Warranty and close-out" :title="$stepTitle" :meta="$stepMeta" :reopen="$failed('closeout')"
                :steps="['Make sure the warranty period is over, or that the expendable supplies are consumed.', 'Enter when the warranty security was released and confirm there are no defects.', 'Attach the close-out document and complete the contract.']">
                @include('partials.contract-implementation-step-error', ['key' => 'closeout'])
                <div class="ci-card">
                    @if($warrantyEnds && $warrantyEnds->isFuture())
                        <div class="ci-note {{ $expendable ? '' : 'is-warning' }}" style="margin-bottom:14px">
                            <i class="fas fa-shield-halved" aria-hidden="true"></i>
                            <div><strong>Under warranty until {{ $warrantyEnds->format('M d, Y') }}.</strong> {{ $expendable ? 'You can complete it earlier only if the expendable supplies are already consumed.' : 'Report any defect to the supplier for correction while the warranty runs.' }}</div>
                        </div>
                    @endif
                    <form class="ci-form" method="POST" action="{{ route($actionRoute, $award) }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="ci_award" value="{{ $award->id }}">
                        <input type="hidden" name="ci_form" value="closeout">
                        <input type="hidden" name="action" value="complete">
                        @unless($implementation->hasWarrantyTerms())
                            <p class="ci-hint">These contract terms were recorded before warranty terms were captured; enter them from the signed contract.</p>
                            @include('partials.contract-implementation-warranty')
                        @endunless
                        @if($err('warranty'))<div class="ci-note is-danger"><i class="fas fa-circle-exclamation" aria-hidden="true"></i><div>{{ $err('warranty') }}</div></div>@endif
                        <div class="ci-grid is-two">
                            <div class="ci-field">
                                <label class="ci-label" for="{{ $uid }}-released-on">Warranty security released on <span class="ci-req" aria-hidden="true">*</span></label>
                                <input class="ci-input" type="date" id="{{ $uid }}-released-on" name="warranty_released_on" value="{{ $old('warranty_released_on') }}" max="{{ today()->toDateString() }}" required @if($err('warranty_released_on')) aria-invalid="true" @endif>
                                @if($err('warranty_released_on'))<span class="ci-error">{{ $err('warranty_released_on') }}</span>@endif
                            </div>
                            <div class="ci-field" style="justify-content:flex-end">
                                @if($expendable)
                                    <label class="ci-check"><input type="checkbox" name="consumed" value="1" @checked($old('consumed'))> The expendable supplies are already consumed</label>
                                @endif
                                <label class="ci-check"><input type="checkbox" name="no_defects" value="1" required @checked($old('no_defects'))> Free from defects, and every contract condition was met</label>
                                @if($err('no_defects'))<span class="ci-error">{{ $err('no_defects') }}</span>@endif
                            </div>
                        </div>
                        @include('partials.contract-implementation-record', ['fileLabel' => 'Close-out document', 'remarksLabel' => 'Remarks'])
                        <div class="ci-form-foot">
                            <p>{{ ! $implementation->hasWarrantyTerms() ? 'Checked against the warranty terms entered above.' : ($canRelease ? 'The warranty period has ended.' : 'Available from '.$warrantyEnds->format('M d, Y').($expendable ? ', or now if the supplies are consumed.' : '.')) }}</p>
                            <button class="ci-btn is-primary" type="submit" @if(! $canRelease && ! $expendable) disabled @endif><i class="fas fa-flag-checkered" aria-hidden="true"></i> Release security and mark Completed</button>
                        </div>
                    </form>
                </div>
            </x-ci-step-dialog>
        @endif

        @if($canExtend)
            @php
                $original = $implementation->delivery_deadline;
                $initialPeriod = (int) $ntpAt->copy()->timezone($tz)->startOfDay()->diffInDays($original);
                $latestAllowed = $original->copy()->addDays($initialPeriod);
            @endphp
            <x-ci-step-dialog :id="$step('extension')" eyebrow="Record an approved time extension" :title="$stepTitle" :meta="$stepMeta" :reopen="$failed('extension')"
                :steps="['Enter the date the supplier asked for more time; it must be on or before the deadline.', 'Enter the HoPE approval date, its reference and the new deadline.', 'Attach the approval and record it. The supplier is notified.']">
                @include('partials.contract-implementation-step-error', ['key' => 'extension'])
                <div class="ci-card">
                <form class="ci-form" method="POST" action="{{ route($actionRoute, $award) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="ci_award" value="{{ $award->id }}">
                        <input type="hidden" name="ci_form" value="extension">
                    <input type="hidden" name="action" value="extend">
                    <p class="ci-hint">The supplier must ask before the deadline in force ({{ $deadline->format('M d, Y') }}). In total the extension cannot be longer than the initial delivery period of {{ $initialPeriod }} days, so the latest possible deadline is {{ $latestAllowed->format('M d, Y') }} (RA 12009 IRR Sec. 71.1.1(c), 71.1.3).</p>
                    <div class="ci-grid">
                        <div class="ci-field">
                            <label class="ci-label" for="{{ $uid }}-requested-on">Supplier's request date <span class="ci-req" aria-hidden="true">*</span></label>
                            <input class="ci-input" type="date" id="{{ $uid }}-requested-on" name="requested_on" value="{{ $old('requested_on') }}" max="{{ $deadline->toDateString() }}" required @if($err('requested_on')) aria-invalid="true" @endif>
                            @if($err('requested_on'))<span class="ci-error">{{ $err('requested_on') }}</span>@endif
                        </div>
                        <div class="ci-field">
                            <label class="ci-label" for="{{ $uid }}-approved-on">HoPE approval date <span class="ci-req" aria-hidden="true">*</span></label>
                            <input class="ci-input" type="date" id="{{ $uid }}-approved-on" name="approved_on" value="{{ $old('approved_on') }}" max="{{ today()->toDateString() }}" required @if($err('approved_on')) aria-invalid="true" @endif>
                            @if($err('approved_on'))<span class="ci-error">{{ $err('approved_on') }}</span>@endif
                        </div>
                        <div class="ci-field">
                            <label class="ci-label" for="{{ $uid }}-new-deadline">New delivery deadline <span class="ci-req" aria-hidden="true">*</span></label>
                            <input class="ci-input" type="date" id="{{ $uid }}-new-deadline" name="new_deadline" value="{{ $old('new_deadline') }}" min="{{ $deadline->copy()->addDay()->toDateString() }}" max="{{ $latestAllowed->toDateString() }}" required @if($err('new_deadline')) aria-invalid="true" @endif>
                            @if($err('new_deadline'))<span class="ci-error">{{ $err('new_deadline') }}</span>@endif
                        </div>
                        <div class="ci-field is-wide">
                            <label class="ci-label" for="{{ $uid }}-approval-ref">HoPE approval reference <span class="ci-req" aria-hidden="true">*</span></label>
                            <input class="ci-input" id="{{ $uid }}-approval-ref" name="approval_reference" maxlength="255" value="{{ $old('approval_reference') }}" placeholder="e.g. Memorandum No. 2026-114, on the End-User's recommendation" required @if($err('approval_reference')) aria-invalid="true" @endif>
                            @if($err('approval_reference'))<span class="ci-error">{{ $err('approval_reference') }}</span>@endif
                        </div>
                    </div>
                    <label class="ci-check"><input type="checkbox" name="keeps_liquidated_damages" value="1" @checked($old('keeps_liquidated_damages'))> Liquidated damages still apply from the original deadline ({{ $original->format('M d, Y') }})</label>
                    @include('partials.contract-implementation-record', ['fileLabel' => 'Approved request / HoPE approval', 'remarksLabel' => 'Grounds for the extension'])
                    <div class="ci-form-foot">
                        <p>The supplier is notified of the new deadline.</p>
                        <button class="ci-btn is-primary" type="submit"><i class="fas fa-calendar-check" aria-hidden="true"></i> Record extension</button>
                    </div>
                </form>
                </div>
            </x-ci-step-dialog>
        @endif
    @endif
</section>
@elseif($part === 'delivery-dialog' && $viewerMode === 'bidder' && $configured && $deliveryOpen)
{{-- The supplier's delivery on its own: what, where and by when, and the form. --}}
<dialog class="ci-dialog is-compact" id="ci-delivery-{{ $award->id }}" aria-labelledby="{{ $uid }}-delivery-title" data-ci-delivery-dialog="{{ $award->id }}" @if($mine && old('ci_form') === 'delivery') data-ci-reopen-delivery @endif>
    <button type="button" class="ci-dialog-close" data-ci-close aria-label="Close the delivery form"><i class="fas fa-xmark" aria-hidden="true"></i></button>
    <div class="ci-dialog-scroll">
        <div class="ci">
            <header class="ci-head">
                <div>
                    <p class="ci-eyebrow">{{ $status === CI::FOR_CORRECTION ? 'Submit the correction or remaining items' : 'Submit delivery' }}</p>
                    <h2 id="{{ $uid }}-delivery-title">{{ $award->project?->title ?? 'Awarded contract' }}</h2>
                    <p class="ci-head-meta">
                        <span class="ci-mono">{{ $award->project?->reference_no ?: 'Award #'.$award->id }}</span>
                        <span>{{ $implementation->signed_contract_reference }}</span>
                    </p>
                </div>
            </header>
            <div class="ci-body">
                <dl class="ci-facts is-two">
                    <div>
                        <dt>Deliver by</dt>
                        <dd>
                            {{ $deadline->format('M d, Y') }}
                            @if($daysLeft < 0)<small class="is-late">{{ abs($daysLeft) }} {{ \Illuminate\Support\Str::plural('day', abs($daysLeft)) }} overdue; liquidated damages apply</small>
                            @elseif($daysLeft === 0)<small class="is-soon">Due today</small>
                            @else<small class="{{ $daysLeft <= 7 ? 'is-soon' : 'is-ok' }}">{{ $daysLeft }} {{ \Illuminate\Support\Str::plural('day', $daysLeft) }} left</small>@endif
                        </dd>
                    </div>
                    <div><dt>Deliver to</dt><dd>{{ $implementation->delivery_location }}</dd></div>
                </dl>

                @if($status === CI::FOR_CORRECTION && ($lastCorrection?->details['correction_request'] ?? null))
                    <div class="ci-note is-warning" role="status">
                        <i class="fas fa-rotate-left" aria-hidden="true"></i>
                        <div>
                            <strong>What the LGU asked for</strong>
                            {{ $lastCorrection->details['correction_request'] }}
                            @if($lastCorrection->details['deficiencies'] ?? null)<br>Deficiencies: {{ $lastCorrection->details['deficiencies'] }}@endif
                        </div>
                    </div>
                @endif

                @if($mine && old('ci_form') === 'delivery' && $errors->any())
                    <div class="ci-note is-danger" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i><div><strong>The delivery was not submitted.</strong> Correct the marked fields.@if($errors->has('status')) {{ $errors->first('status') }}@endif</div></div>
                @endif

                <ol class="ci-howto" aria-label="How to submit">
                    <li><span>1</span> Enter the delivery date and your delivery receipt number.</li>
                    <li><span>2</span> Enter how many of each item you delivered{{ $status === CI::FOR_CORRECTION ? ' this time' : '' }}.</li>
                    <li><span>3</span> Attach the delivery receipt and submit. The LGU then receives and inspects the goods.</li>
                </ol>

                <div class="ci-card">
                    <form class="ci-form" method="POST" action="{{ route('bidder.contract-implementation.delivery', $award) }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="ci_award" value="{{ $award->id }}">
                        <input type="hidden" name="ci_form" value="delivery">
                        <div class="ci-grid is-two">
                            <div class="ci-field">
                                <label class="ci-label" for="{{ $uid }}-delivered-on">Delivery date <span class="ci-req" aria-hidden="true">*</span></label>
                                <input class="ci-input" type="date" id="{{ $uid }}-delivered-on" name="delivered_on" value="{{ $old('delivered_on') }}" required @if($err('delivered_on')) aria-invalid="true" @endif>
                                @if($err('delivered_on'))<span class="ci-error">{{ $err('delivered_on') }}</span>@endif
                            </div>
                            <div class="ci-field">
                                <label class="ci-label" for="{{ $uid }}-delivery-ref">Delivery receipt / reference no. <span class="ci-req" aria-hidden="true">*</span></label>
                                <input class="ci-input" id="{{ $uid }}-delivery-ref" name="delivery_reference" maxlength="100" value="{{ $old('delivery_reference') }}" placeholder="e.g. DR-2026-001" required @if($err('delivery_reference')) aria-invalid="true" @endif>
                                @if($err('delivery_reference'))<span class="ci-error">{{ $err('delivery_reference') }}</span>@endif
                            </div>
                        </div>
                        @include('partials.contract-implementation-quantities', ['field' => 'delivered_quantities', 'heading' => 'Delivered quantity'])
                        <div class="ci-grid is-two">
                            <div class="ci-field">
                                <label class="ci-label" for="{{ $uid }}-delivery-file">Delivery receipt / supporting proof <span class="ci-req" aria-hidden="true">*</span></label>
                                <input class="ci-input ci-file" type="file" id="{{ $uid }}-delivery-file" name="document" accept="{{ $accept }}" required>
                                @if($err('document'))<span class="ci-error">{{ $err('document') }}</span>@endif
                            </div>
                            <div class="ci-field">
                                <label class="ci-label" for="{{ $uid }}-delivery-remarks">Remarks <span class="ci-req" aria-hidden="true">*</span></label>
                                <textarea class="ci-input" id="{{ $uid }}-delivery-remarks" name="remarks" rows="2" maxlength="2000" required>{{ $old('remarks') }}</textarea>
                                @if($err('remarks'))<span class="ci-error">{{ $err('remarks') }}</span>@endif
                            </div>
                        </div>
                        <div class="ci-form-foot">
                            <p>The BAC is notified when you submit.</p>
                            <button class="ci-btn is-primary" type="submit"><i class="fas fa-truck" aria-hidden="true"></i> Submit delivery for inspection</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</dialog>
@endif

@once
<script>
    (function () {
        // Contract items: add and remove rows. Row indexes only need to be unique; the server re-indexes them.
        document.addEventListener('click', function (event) {
            const add = event.target.closest('[data-ci-add-item]');
            if (add) {
                const rows = add.closest('form')?.querySelector('[data-ci-items]');
                if (!rows || rows.children.length >= 100) return;
                const index = Number(rows.dataset.ciNext || rows.children.length);
                rows.dataset.ciNext = index + 1;
                const list = rows.closest('form').querySelector('datalist')?.id || '';
                const row = document.createElement('tr');
                row.innerHTML = '<td><input class="ci-input" name="contract_items[' + index + '][description]" maxlength="255" required placeholder="Item" aria-label="Item"></td>'
                    + '<td class="is-input"><input class="ci-input" type="number" name="contract_items[' + index + '][quantity]" min="0.001" step="0.001" required placeholder="0" aria-label="Quantity"></td>'
                    + '<td class="is-input"><input class="ci-input" name="contract_items[' + index + '][unit]" maxlength="50" required placeholder="units, pcs, sets" list="' + list + '" aria-label="Unit"></td>'
                    + '<td class="is-input"><input class="ci-input" type="number" name="contract_items[' + index + '][unit_price]" min="0.01" step="0.01" required placeholder="0.00" aria-label="Unit price"></td>'
                    + '<td class="is-remove"><button type="button" class="ci-btn is-icon" data-ci-remove-item aria-label="Remove item"><i class="fas fa-xmark" aria-hidden="true"></i></button></td>';
                rows.appendChild(row);
                row.querySelector('input').focus();
                return;
            }

            const remove = event.target.closest('[data-ci-remove-item]');
            if (remove) {
                const row = remove.closest('tr');
                const focusTarget = row.previousElementSibling?.querySelector('input');
                row.remove();
                focusTarget?.focus();
            }
        });

        // Step modals: open from the "Next step" card, close with the X or a click outside.
        document.addEventListener('click', function (event) {
            const opener = event.target.closest('[data-ci-step]');
            if (opener) {
                const dialog = document.getElementById(opener.dataset.ciStep);
                if (dialog && !dialog.open) {
                    dialog.showModal();
                    dialog.querySelector('.ci-form :is(input:not([type=hidden]), select, textarea)')?.focus();
                }
                return;
            }
            if (event.target.closest('[data-ci-close]')) {
                event.target.closest('dialog')?.close();
                return;
            }
            if (event.target.matches('.ci-dialog')) {
                const box = event.target.getBoundingClientRect();
                const inside = event.clientX >= box.left && event.clientX <= box.right && event.clientY >= box.top && event.clientY <= box.bottom;
                if (!inside) event.target.close();
            }
        });

        // After a failed save, reopen that step's modal. Waits for the page, so on the awards
        // pages the contract screen reopens first and the step modal sits on top of it.
        document.addEventListener('DOMContentLoaded', function () {
            const failed = document.querySelector('[data-ci-reopen-step]');
            if (!failed) return;
            failed.showModal();
            failed.querySelector('.ci-note.is-danger, [aria-invalid="true"]')?.scrollIntoView({ block: 'nearest' });
        });

        // Warranty: the supply type sets the minimum period (3 or 12 months).
        document.addEventListener('change', function (event) {
            if (!event.target.matches('input[name="supply_type"][data-ci-min-months]')) return;
            const months = event.target.closest('form').querySelector('[data-ci-warranty-months]');
            const minimum = Number(event.target.dataset.ciMinMonths);
            months.min = minimum;
            months.placeholder = minimum;
            if (!months.value || Number(months.value) < minimum) months.value = minimum;
        });

        // Inspection: the correction fields apply only to "Not yet complete".
        document.addEventListener('change', function (event) {
            if (!event.target.matches('[data-ci-inspection] input[name="outcome"]')) return;
            const form = event.target.closest('form');
            const correction = form.querySelector('[data-ci-correction]');
            const needed = event.target.value === 'for_correction';
            correction.hidden = !needed;
            form.querySelector('[name="correction_request"]').required = needed;
        });
    })();
</script>
@endonce
