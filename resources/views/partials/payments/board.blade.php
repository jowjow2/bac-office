@php
    /**
     * Bidding documents fee payments board, shared by the admin and staff portals.
     *
     * @var string $routePrefix 'admin' | 'staff'
     */
    $tz = config('bac-office.display_timezone');
    $today = now()->timezone($tz)->toDateString();
    $peso = fn ($value) => '₱' . number_format((float) $value, 2);
    $recordFormActive = old('_form') === 'record';
    $editFormActive = old('_form') === 'edit';
    $postedDate = fn ($project) => ($project->schedule?->date_posted ?? $project->created_at)?->format('Y-m-d');
    $selectedProjectId = $recordFormActive ? (int) old('project_id') : 0;
    $selectedBidderId = $recordFormActive ? (int) old('user_id') : 0;
    $hasFilters = $search !== '' || $projectFilter;
    $pageQuery = request()->only(['q', 'project', 'page']);
    $reopenEdit = $editFormActive && (int) old('_payment_id') > 0
        ? [
            'action' => route($routePrefix . '.payments.update', array_merge(['payment' => (int) old('_payment_id')], $pageQuery)),
            'id' => (int) old('_payment_id'),
            'bidder' => (string) old('_bidder'),
            'project' => (string) old('_project'),
        ]
        : null;
@endphp

<style>
    .fee-board { --fee-ink: var(--ui-ink); --fee-muted: var(--ui-muted); --fee-line: var(--ui-line); --fee-soft: var(--ui-surface-2); --fee-accent: var(--ui-primary); --fee-accent-soft: var(--ui-primary-soft); --fee-ok: #047857; --fee-ok-soft: #ecfdf5; --fee-warn: #b45309; --fee-warn-soft: #fffbeb; --fee-bad: #b91c1c; --fee-bad-soft: #fef2f2;
        display: grid; grid-template-columns: minmax(0, 1fr); gap: 20px; font-family: var(--ui-font); color: var(--fee-ink); font-size: 13.5px; }
    /* Long project names in selects must not widen the layout on small screens. */
    .fee-board > *, .fee-grid > *, .fee-card, .fee-form > * { min-width: 0; }
    .fee-board .fee-input { max-width: 100%; }
    .fee-board select.fee-input { text-overflow: ellipsis; }
    .fee-board .fas, .fee-board .far { font-family: "Font Awesome 6 Free" !important; font-weight: 700 !important; }
    .fee-board *, .fee-board *::before, .fee-board *::after { box-sizing: border-box; }

    .fee-alert { display: flex; gap: 10px; align-items: flex-start; padding: 12px 16px; border-radius: var(--ui-radius-lg); border: 1px solid; font-size: 13px; line-height: 1.5; }
    .fee-alert ul { margin: 0; padding-left: 18px; }
    .fee-alert i { margin-top: 3px; }
    .fee-alert-success { background: var(--fee-ok-soft); border-color: #a7f3d0; color: var(--fee-ok); }
    .fee-alert-error { background: var(--fee-bad-soft); border-color: #fecaca; color: var(--fee-bad); }
    .fee-alert-floating { position: fixed; top: 88px; right: 24px; z-index: 2400; width: min(380px, calc(100vw - 32px)); box-shadow: 0 16px 34px rgba(27, 36, 32, .14); transition: opacity .35s ease, transform .35s ease; }
    .fee-alert-floating.is-hiding { opacity: 0; transform: translateY(-10px); }

    /* ID selectors: the dashboard CSS forces dark heading colors and input padding with !important. */
    /* Doubled IDs outrank the admin form-control rule, whose :not(#budget_display) carries an ID. */
    #fee-amount#fee-amount, #fee-edit-amount#fee-edit-amount { padding-left: 30px !important; }
    #fee-search-input#fee-search-input { padding-left: 34px !important; }

    .fee-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
    .fee-stat { display: flex; gap: 14px; align-items: center; padding: 16px 18px; background: #fff; border: 1px solid var(--fee-line); border-radius: var(--ui-radius-lg); }
    .fee-stat-icon { display: grid; place-items: center; flex: 0 0 40px; height: 40px; border-radius: var(--ui-radius-lg); background: var(--fee-accent-soft); color: var(--fee-accent); font-size: 15px; }
    .fee-stat.is-ok .fee-stat-icon { background: var(--fee-ok-soft); color: var(--fee-ok); }
    .fee-stat.is-warn .fee-stat-icon { background: var(--fee-warn-soft); color: var(--fee-warn); }
    .fee-stat-label { display: block; color: var(--fee-muted); font-size: 12px; font-weight: 500; }
    .fee-stat-value { display: block; margin-top: 2px; font-size: 19px; font-weight: 700; font-variant-numeric: tabular-nums; }

    .fee-grid { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); gap: 20px; align-items: start; }
    .fee-card { background: #fff; border: 1px solid var(--fee-line); border-radius: var(--ui-radius-lg); box-shadow: var(--ui-shadow); }
    .fee-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 18px 20px 14px; border-bottom: 1px solid var(--fee-line); }
    .fee-card-head h2 { margin: 0; font-size: 15px; font-weight: 700; }
    .fee-card-head p { margin: 4px 0 0; color: var(--fee-muted); font-size: 12.5px; line-height: 1.5; }
    .fee-card-body { padding: 18px 20px 20px; }

    .fee-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 16px; }
    .fee-field { display: grid; gap: 6px; min-width: 0; }
    .fee-field.is-wide { grid-column: 1 / -1; }
    .fee-field label { font-size: 12.5px; font-weight: 600; color: var(--ui-ink-2); }
    .fee-field label .fee-req { color: var(--fee-bad); }
    .fee-input { width: 100%; min-height: 36px; padding: 9px 12px; border: 1px solid var(--ui-line-strong); border-radius: var(--ui-radius); background: #fff; color: var(--fee-ink); font: inherit; font-size: 13px; transition: border-color .15s ease, box-shadow .15s ease; }
    .fee-input:focus { outline: none; border-color: var(--fee-accent); box-shadow: 0 0 0 3px rgba(29, 79, 64, .15); }
    .fee-input.is-invalid { border-color: #f87171; }
    textarea.fee-input { min-height: 72px; resize: vertical; }
    .fee-money { position: relative; }
    .fee-money::before { content: '₱'; position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--fee-muted); font-weight: 600; pointer-events: none; }
    .fee-board .fee-money .fee-input { padding-left: 30px !important; font-variant-numeric: tabular-nums; }
    .fee-or { text-transform: none; letter-spacing: normal; }
    .fee-or::placeholder { text-transform: none; letter-spacing: normal; }
    .fee-hint { color: var(--fee-muted); font-size: 12px; line-height: 1.45; }
    .fee-error { color: var(--fee-bad); font-size: 12px; font-weight: 500; }
    .fee-project-note { display: none; gap: 12px; flex-wrap: wrap; padding: 10px 12px; border-radius: var(--ui-radius-lg); background: var(--fee-accent-soft); color: var(--ui-primary-hover); font-size: 12.5px; }
    .fee-project-note.is-visible { display: flex; }
    .fee-project-note strong { font-variant-numeric: tabular-nums; }
    .fee-form-actions { grid-column: 1 / -1; display: flex; justify-content: space-between; align-items: center; gap: 12px; padding-top: 4px; flex-wrap: wrap; }

    .fee-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 36px; padding: 9px 16px; border-radius: var(--ui-radius); border: 1px solid transparent; font: inherit; font-size: 12.5px; font-weight: 600; cursor: pointer; text-decoration: none; transition: background .15s ease, border-color .15s ease, color .15s ease; }
    .fee-btn:focus-visible { outline: 3px solid rgba(29, 79, 64, .35); outline-offset: 2px; }
    .fee-btn-primary { background: var(--ui-primary); color: #fff; }
    .fee-btn-primary:hover { background: var(--ui-primary-hover); }
    .fee-btn-primary:disabled { background: var(--ui-subtle); cursor: not-allowed; }
    .fee-btn-ghost { background: #fff; border-color: var(--ui-line-strong); color: var(--ui-ink-2); }
    .fee-btn-ghost:hover { border-color: var(--ui-subtle); background: var(--fee-soft); }
    .fee-btn-sm { min-height: 32px; padding: 6px 10px; font-size: 12px; border-radius: 8px; }
    .fee-board .fee-btn-ghost { background: #fff !important; border-color: var(--ui-line-strong) !important; color: var(--ui-ink-2) !important; -webkit-text-fill-color: var(--ui-ink-2) !important; box-shadow: none !important; }
    .fee-board .fee-btn-ghost:hover { background: var(--fee-soft) !important; border-color: var(--ui-subtle) !important; }
    /* The dashboard paints every submit button blue with a high-specificity rule; the
       never-matching ID inside :is() lifts this selector above it. */
    .fee-board :is(.fee-btn-danger, #fee-specificity#fee-specificity) { background: #fff !important; border-color: #fecaca !important; color: var(--fee-bad) !important; -webkit-text-fill-color: var(--fee-bad) !important; box-shadow: none !important; }
    .fee-board :is(.fee-btn-danger, #fee-specificity#fee-specificity):hover { background: var(--fee-bad-soft) !important; }
    .fee-board :is(.fee-btn-danger, #fee-specificity#fee-specificity):disabled { border-color: var(--fee-line) !important; color: var(--ui-subtle) !important; -webkit-text-fill-color: var(--ui-subtle) !important; background: #fff !important; cursor: not-allowed; }

    .fee-projects { display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; }
    .fee-project { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 4px 12px; padding: 12px 14px; border: 1px solid var(--fee-line); border-radius: var(--ui-radius-lg); background: var(--fee-soft); }
    .fee-project-ref { color: var(--fee-muted); font-size: 11.5px; font-weight: 600; letter-spacing: .02em; }
    .fee-project-title { margin: 0; font-size: 13px; font-weight: 600; line-height: 1.4; overflow-wrap: anywhere; }
    .fee-project-fee { grid-row: 1 / span 2; grid-column: 2; align-self: center; text-align: right; font-weight: 700; font-variant-numeric: tabular-nums; }
    .fee-project-fee small { display: block; color: var(--fee-muted); font-size: 11.5px; font-weight: 500; }
    .fee-project-meta { grid-column: 1 / -1; display: flex; gap: 14px; flex-wrap: wrap; color: var(--fee-muted); font-size: 12px; }
    .fee-empty { display: grid; justify-items: center; gap: 6px; padding: 28px 16px; text-align: center; color: var(--fee-muted); font-size: 13px; }
    .fee-empty i { font-size: 22px; color: var(--ui-subtle); }
    .fee-empty strong { color: var(--fee-ink); font-size: 14px; }

    .fee-toolbar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .fee-toolbar .fee-input { width: auto; min-width: 200px; flex: 1 1 200px; }
    .fee-search { position: relative; flex: 1 1 240px; }
    .fee-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--ui-subtle); font-size: 12px; }
    .fee-board .fee-search .fee-input { width: 100%; padding-left: 34px !important; }

    .fee-table-wrap { overflow-x: auto; }
    .fee-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .fee-table th { padding: 10px 14px; background: var(--fee-soft); color: var(--fee-muted); font-size: 11.5px; font-weight: 600; text-align: left; text-transform: none; letter-spacing: normal; border-bottom: 1px solid var(--fee-line); white-space: nowrap; }
    .fee-table td { padding: 12px 14px; border-bottom: 1px solid var(--ui-line-soft); vertical-align: middle; }
    .fee-table tbody tr:hover { background: var(--ui-surface-2); }
    .fee-table .is-num { text-align: right; font-variant-numeric: tabular-nums; }
    .fee-or-cell { font-weight: 700; letter-spacing: .02em; }
    .fee-board .fee-table :is(td, th).is-num,
    .fee-board .fee-table td.fee-or-cell,
    .fee-board .fee-table td.fee-actions-cell { white-space: nowrap !important; overflow-wrap: normal !important; }
    .fee-sub { display: block; color: var(--fee-muted); font-size: 12px; margin-top: 2px; }
    .fee-pill { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
    .fee-pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .fee-pill.is-ok { background: var(--fee-ok-soft); color: var(--fee-ok); }
    .fee-pill.is-warn { background: var(--fee-warn-soft); color: var(--fee-warn); }
    .fee-pill.is-muted { background: var(--ui-line-soft); color: var(--fee-muted); }
    .fee-row-actions { display: flex; gap: 6px; justify-content: flex-end; }
    .fee-row-actions form { margin: 0; }

    .fee-pager { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 14px 20px; border-top: 1px solid var(--fee-line); color: var(--fee-muted); font-size: 12.5px; flex-wrap: wrap; }
    .fee-pager-links { display: flex; gap: 8px; }
    .fee-btn[aria-disabled="true"] { pointer-events: none; opacity: .45; }

    .fee-dialog { position: fixed; inset: 50% auto auto 50%; transform: translate(-50%, -50%); width: min(520px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); overflow-y: auto; margin: 0; padding: 0; border: 0; border-radius: var(--ui-radius-lg); box-shadow: 0 24px 60px rgba(27, 36, 32, .3); color: var(--fee-ink); font-family: var(--ui-font); }
    .fee-dialog::backdrop { background: rgba(27, 36, 32, .45); backdrop-filter: blur(3px); }
    .fee-dialog .fee-card-head { align-items: center; }
    .fee-dialog-close { display: grid; place-items: center; width: 34px; height: 34px; border: 0; border-radius: 8px; background: transparent; color: var(--fee-muted); cursor: pointer; }
    .fee-dialog-close:hover { background: var(--fee-soft); color: var(--fee-ink); }
    .fee-dialog-context { display: grid; gap: 2px; padding: 10px 12px; margin-bottom: 14px; border-radius: var(--ui-radius-lg); background: var(--fee-soft); font-size: 12.5px; }

    /* Redesign: one summary strip, the record form in a dialog, open projects as a card row. */
    .fee-board .fee-stats { gap: 0; overflow: hidden; border: 1px solid var(--fee-line); border-radius: 14px; background: #fff; }
    .fee-board .fee-stat { padding: 14px 20px; border: 0; border-right: 1px solid var(--fee-line); border-radius: 0; background: transparent; }
    .fee-board .fee-stat:last-child { border-right: 0; }
    .fee-board .fee-stat-icon { flex-basis: 34px; height: 34px; border-radius: 9px; font-size: 13px; }
    .fee-board .fee-stat-value { font-size: 18px; }
    .fee-grid { grid-template-columns: minmax(0, 1fr); }
    .fee-board .fee-projects { grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
    .fee-record-dialog { width: min(580px, calc(100vw - 24px)); border-radius: 16px; }
    .fee-record-dialog[open] { animation: fee-rise .26s cubic-bezier(.2, .8, .2, 1) both; }
    .fee-record-dialog::backdrop { background: rgba(15, 25, 21, .55); backdrop-filter: blur(2px); }
    .fee-record-dialog .fee-card-head { align-items: flex-start; border-bottom: 0; padding-bottom: 6px; }
    .fee-record-dialog .fee-card-head h2 { font-size: 18px; letter-spacing: -.01em; }
    @keyframes fee-rise { from { opacity: 0; transform: translate(-50%, calc(-50% + 12px)); } to { opacity: 1; transform: translate(-50%, -50%); } }
    body .admin-dashboard .main-area button.pay-record { display: inline-flex !important; align-items: center !important; gap: 8px !important; height: 38px !important; padding: 0 16px !important; border: 0 !important; border-radius: 9px !important; background: var(--ui-primary) !important; color: #fff !important; -webkit-text-fill-color: #fff !important; font: 600 13.5px/1 var(--ui-font, inherit) !important; cursor: pointer !important; }
    body .admin-dashboard .main-area button.pay-record:hover { background: var(--ui-primary-hover) !important; }
    body .admin-dashboard .main-area a.hd-export { display: inline-flex !important; align-items: center !important; gap: 8px !important; height: 38px !important; padding: 0 16px !important; border: 1px solid var(--ui-line-strong, #d5ddd8) !important; border-radius: 9px !important; background: #fff !important; color: var(--ui-ink-2, #2d3a34) !important; -webkit-text-fill-color: var(--ui-ink-2, #2d3a34) !important; font: 600 13.5px/1 var(--ui-font, inherit) !important; text-decoration: none !important; }
    body .admin-dashboard .main-area a.hd-export:hover { border-color: var(--ui-primary) !important; background: var(--ui-primary-soft) !important; }
    body .admin-dashboard .main-area :is(button.pay-record, a.hd-export) i { color: inherit !important; -webkit-text-fill-color: currentColor !important; font-size: 12px !important; }

    @media (max-width: 1180px) {
        .fee-board .fee-stat { border-bottom: 1px solid var(--fee-line); }
        .fee-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .fee-grid { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 760px) {
        .fee-form { grid-template-columns: minmax(0, 1fr); }
        .fee-stats { grid-template-columns: minmax(0, 1fr); }
        .fee-table thead { display: none; }
        .fee-table, .fee-table tbody, .fee-table tr, .fee-table td { display: block; width: 100%; }
        .fee-table tr { padding: 12px 16px; border-bottom: 1px solid var(--fee-line); }
        .fee-board .fee-table td { display: grid !important; grid-template-columns: 92px minmax(0, 1fr); column-gap: 12px; padding: 6px 0; border: 0; text-align: right; }
        .fee-board .fee-table td > * { grid-column: 2; justify-self: end; }
        .fee-board .fee-table td::before { content: attr(data-label); grid-column: 1; grid-row: 1 / span 2; color: var(--fee-muted); font-size: 12px; font-weight: 600; text-align: left; white-space: nowrap; }
        .fee-table .is-num { text-align: right; }
        .fee-row-actions { justify-content: flex-end; }
        .fee-alert-floating { top: 80px; right: 16px; }
    }
</style>

<div class="fee-board">
    @if(session('success'))
        <div class="fee-alert fee-alert-success fee-alert-floating" role="status" data-fee-autohide>
            <i class="fas fa-circle-check" aria-hidden="true"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any() && ! $recordFormActive && ! $editFormActive)
        <div class="fee-alert fee-alert-error" role="alert">
            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="fee-stats" aria-label="Payment summary">
        <div class="fee-stat">
            <span class="fee-stat-icon" aria-hidden="true"><i class="fas fa-folder-open"></i></span>
            <div><span class="fee-stat-label">Open projects with a fee</span><span class="fee-stat-value">{{ number_format($stats['payable_projects']) }}</span></div>
        </div>
        <div class="fee-stat">
            <span class="fee-stat-icon" aria-hidden="true"><i class="fas fa-receipt"></i></span>
            <div><span class="fee-stat-label">Payments recorded</span><span class="fee-stat-value">{{ number_format($stats['payments']) }}</span></div>
        </div>
        <div class="fee-stat is-ok">
            <span class="fee-stat-icon" aria-hidden="true"><i class="fas fa-peso-sign"></i></span>
            <div><span class="fee-stat-label">Total collected</span><span class="fee-stat-value">{{ $peso($stats['collected']) }}</span></div>
        </div>
        <div class="fee-stat is-warn">
            <span class="fee-stat-icon" aria-hidden="true"><i class="fas fa-calendar-day"></i></span>
            <div><span class="fee-stat-label">Paid today</span><span class="fee-stat-value">{{ number_format($stats['today']) }}</span></div>
        </div>
    </section>

    <div class="fee-grid">
        <dialog class="fee-dialog fee-record-dialog" id="fee-record-dialog" aria-labelledby="fee-record-title">
            <div class="fee-card-head">
                <div>
                    <h2 id="fee-record-title">Record a payment</h2>
                    <p>Fill this in while the bidder is at the counter, using the details on the Official Receipt.</p>
                </div>
                <button type="button" class="fee-dialog-close" data-fee-record-close aria-label="Close"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="fee-card-body">
                @if($payableProjects->isEmpty())
                    <div class="fee-empty">
                        <i class="fas fa-circle-info" aria-hidden="true"></i>
                        <strong>No open project needs a payment</strong>
                        <span>A project appears here once it is open for bidding and has a bidding documents fee. Set the fee in the project's Online Submission settings.</span>
                    </div>
                @else
                    <form method="POST" action="{{ route($routePrefix . '.payments.store') }}" class="fee-form" data-fee-record-form novalidate>
                        @csrf
                        <input type="hidden" name="_form" value="record">

                        @if($recordFormActive && $errors->any())
                            <div class="fee-alert fee-alert-error is-wide" role="alert" style="grid-column: 1 / -1;">
                                <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                                <span>The payment was not recorded. Check the highlighted fields.</span>
                            </div>
                        @endif

                        <div class="fee-field is-wide">
                            <label for="fee-project">Project <span class="fee-req" aria-hidden="true">*</span></label>
                            <select id="fee-project" name="project_id" class="fee-input @if($recordFormActive && $errors->has('project_id')) is-invalid @endif" required data-fee-project>
                                <option value="">Select the project the bidder paid for</option>
                                @foreach($payableProjects as $project)
                                    <option value="{{ $project->id }}"
                                            data-fee="{{ number_format((float) $project->bidding_documents_fee, 2, '.', '') }}"
                                            data-fee-label="{{ $peso($project->bidding_documents_fee) }}"
                                            data-posted="{{ $postedDate($project) }}"
                                            data-deadline="{{ $project->bidSubmissionDeadline()?->copy()->timezone($tz)->format('M d, Y g:i A') }}"
                                            @selected($selectedProjectId === $project->id)>
                                        {{ $project->reference_no ?: 'REF PENDING' }} &middot; {{ \Illuminate\Support\Str::limit($project->title, 70) }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="fee-project-note" data-fee-project-note aria-live="polite">
                                <span>Fee: <strong data-fee-note-amount></strong></span>
                                <span>Submission closes: <strong data-fee-note-deadline></strong></span>
                            </div>
                            @if($recordFormActive) @error('project_id') <span class="fee-error">{{ $message }}</span> @enderror @endif
                        </div>

                        <div class="fee-field is-wide">
                            <label for="fee-bidder">Bidder <span class="fee-req" aria-hidden="true">*</span></label>
                            <select id="fee-bidder" name="user_id" class="fee-input @if($recordFormActive && $errors->has('user_id')) is-invalid @endif" required>
                                <option value="">Select the approved bidder who paid</option>
                                @foreach($bidders as $bidder)
                                    <option value="{{ $bidder->id }}" @selected($selectedBidderId === $bidder->id)>
                                        {{ $bidder->company ?: $bidder->name }}{{ $bidder->company ? ' — ' . $bidder->name : '' }} ({{ $bidder->email }})
                                    </option>
                                @endforeach
                            </select>
                            @if($bidders->isEmpty())
                                <span class="fee-hint">No approved bidders yet. Bidders must be approved before they can pay and bid.</span>
                            @endif
                            @if($recordFormActive) @error('user_id') <span class="fee-error">{{ $message }}</span> @enderror @endif
                        </div>

                        <div class="fee-field">
                            <label for="fee-amount">Amount paid <span class="fee-req" aria-hidden="true">*</span></label>
                            <div class="fee-money">
                                <input id="fee-amount" type="number" name="amount" min="0.01" step="0.01" inputmode="decimal" class="fee-input @if($recordFormActive && $errors->has('amount')) is-invalid @endif" value="{{ $recordFormActive ? old('amount') : '' }}" placeholder="0.00" required data-fee-amount>
                            </div>
                            @if($recordFormActive) @error('amount') <span class="fee-error">{{ $message }}</span> @enderror @endif
                        </div>

                        <div class="fee-field">
                            <label for="fee-or">Official Receipt No. <span class="fee-req" aria-hidden="true">*</span></label>
                            <input id="fee-or" type="text" name="or_number" maxlength="60" autocomplete="off" spellcheck="false" class="fee-input fee-or @if($recordFormActive && $errors->has('or_number')) is-invalid @endif" value="{{ $recordFormActive ? old('or_number') : '' }}" placeholder="e.g. 7654321" required>
                            @if($recordFormActive) @error('or_number') <span class="fee-error">{{ $message }}</span> @enderror @endif
                        </div>

                        <div class="fee-field">
                            <label for="fee-date">Date paid <span class="fee-req" aria-hidden="true">*</span></label>
                            <input id="fee-date" type="date" name="paid_at" max="{{ $today }}" class="fee-input @if($recordFormActive && $errors->has('paid_at')) is-invalid @endif" value="{{ $recordFormActive ? old('paid_at') : $today }}" required data-fee-date>
                            @if($recordFormActive) @error('paid_at') <span class="fee-error">{{ $message }}</span> @enderror @endif
                        </div>

                        <div class="fee-field">
                            <label for="fee-notes">Notes</label>
                            <textarea id="fee-notes" name="notes" maxlength="1000" class="fee-input" rows="2" placeholder="Optional, e.g. paid by authorized representative">{{ $recordFormActive ? old('notes') : '' }}</textarea>
                            @if($recordFormActive) @error('notes') <span class="fee-error">{{ $message }}</span> @enderror @endif
                        </div>

                        <div class="fee-form-actions">
                            <span class="fee-hint"><i class="fas fa-bell" aria-hidden="true"></i> The bidder is notified right away and can submit online.</span>
                            <button type="submit" class="fee-btn fee-btn-primary" @disabled($bidders->isEmpty())>
                                <i class="fas fa-check" aria-hidden="true"></i> Record payment
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </dialog>

        <section class="fee-card" aria-labelledby="fee-open-title">
            <div class="fee-card-head">
                <div>
                    <h2 id="fee-open-title">Open for payment</h2>
                    <p>Projects accepting bids that charge a bidding documents fee.</p>
                </div>
            </div>
            <div class="fee-card-body">
                @if($payableProjects->isEmpty())
                    <div class="fee-empty">
                        <i class="fas fa-folder-open" aria-hidden="true"></i>
                        <span>No projects right now.</span>
                    </div>
                @else
                    <ul class="fee-projects">
                        @foreach($payableProjects as $project)
                            <li class="fee-project">
                                <span class="fee-project-ref">{{ $project->reference_no ?: 'REF PENDING' }}</span>
                                <p class="fee-project-title">{{ $project->title }}</p>
                                <span class="fee-project-fee">{{ $peso($project->bidding_documents_fee) }}<small>per bidder</small></span>
                                <span class="fee-project-meta">
                                    <span><i class="far fa-clock" aria-hidden="true"></i> Closes {{ $project->bidSubmissionDeadline()?->copy()->timezone($tz)->format('M d, Y g:i A') ?? 'not set' }}</span>
                                    <span><i class="fas fa-user-check" aria-hidden="true"></i> {{ $project->bidding_fee_payments_count }} paid</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    </div>

    <section class="fee-card" aria-labelledby="fee-ledger-title">
        <div class="fee-card-head">
            <div>
                <h2 id="fee-ledger-title">Payment records</h2>
                <p>Every Official Receipt recorded. A payment already used for a submitted bid can be corrected but not removed.</p>
            </div>
            @unless($payments->isEmpty())
                <a href="{{ route($routePrefix . '.payments.export', array_filter(['q' => $search, 'project' => $projectFilter])) }}" class="fee-btn fee-btn-ghost" data-export-dialog data-export-title="Export payment records" data-export-noun="payment" data-export-count="{{ method_exists($payments, 'total') ? $payments->total() : $payments->count() }}" data-export-note="{{ $hasFilters ? 'Only the payments that match your current search or project filter.' : 'Every Official Receipt recorded.' }}">
                    <i class="fas fa-file-export" aria-hidden="true"></i> {{ $hasFilters ? 'Export filtered' : 'Export' }}
                </a>
                @include('partials.export-dialog')
            @endunless
        </div>
        <div class="fee-card-body" style="padding-bottom: 14px;">
            <form method="GET" action="{{ route($routePrefix . '.payments') }}" class="fee-toolbar" role="search">
                <div class="fee-search">
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="fee-search-input" name="q" value="{{ $search }}" class="fee-input" placeholder="Search OR no., bidder, or project" aria-label="Search payments">
                </div>
                <select name="project" class="fee-input" aria-label="Filter by project">
                    <option value="">All projects</option>
                    @foreach($feeProjects as $project)
                        <option value="{{ $project->id }}" @selected($projectFilter === $project->id)>{{ $project->reference_no ?: 'REF PENDING' }} &middot; {{ \Illuminate\Support\Str::limit($project->title, 50) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="fee-btn fee-btn-primary"><i class="fas fa-filter" aria-hidden="true"></i> Filter</button>
                @if($hasFilters)
                    <a href="{{ route($routePrefix . '.payments') }}" class="fee-btn fee-btn-ghost">Clear</a>
                @endif
            </form>
        </div>

        @if($payments->isEmpty())
            <div class="fee-empty" style="padding-bottom: 36px;">
                <i class="fas fa-receipt" aria-hidden="true"></i>
                <strong>{{ $hasFilters ? 'No payments match these filters' : 'No payments recorded yet' }}</strong>
                <span>{{ $hasFilters ? 'Try a different search or project.' : 'Payments you record will be listed here.' }}</span>
            </div>
        @else
            <div class="fee-table-wrap">
                <table class="fee-table">
                    <thead>
                        <tr>
                            <th scope="col">OR No.</th>
                            <th scope="col">Bidder</th>
                            <th scope="col">Project</th>
                            <th scope="col" class="is-num">Amount</th>
                            <th scope="col">Date paid</th>
                            <th scope="col">Bid status</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $payment)
                            @php
                                $bidderLabel = $payment->bidder?->company ?: ($payment->bidder?->name ?? 'Deleted bidder');
                                $bidSubmitted = $submittedKeys->has($payment->project_id . ':' . $payment->user_id);
                                $stillOpen = $payment->project?->isOpenForBidding() ?? false;
                            @endphp
                            <tr>
                                <td data-label="OR No." class="fee-or-cell">{{ $payment->or_number }}</td>
                                <td data-label="Bidder">
                                    <span>{{ $bidderLabel }}</span>
                                    @if($payment->bidder?->company)<span class="fee-sub">{{ $payment->bidder->name }}</span>@endif
                                </td>
                                <td data-label="Project">
                                    <span>{{ \Illuminate\Support\Str::limit($payment->project?->title ?? 'Deleted project', 48) }}</span>
                                    <span class="fee-sub">{{ $payment->project?->reference_no }}</span>
                                </td>
                                <td data-label="Amount" class="is-num">{{ $peso($payment->amount) }}</td>
                                <td data-label="Date paid">
                                    <span>{{ $payment->paid_at->format('M d, Y') }}</span>
                                    <span class="fee-sub">by {{ $payment->recorder?->name ?? 'unknown' }}</span>
                                    <span class="fee-sub">Verified {{ ($payment->verified_at ?? $payment->created_at)?->timezone($tz)->format('M d, Y h:i A') ?? 'legacy record' }}</span>
                                </td>
                                <td data-label="Bid status">
                                    @if($bidSubmitted)
                                        <span class="fee-pill is-ok">Bid submitted</span>
                                    @elseif($stillOpen)
                                        <span class="fee-pill is-warn">Awaiting bid</span>
                                    @else
                                        <span class="fee-pill is-muted">No bid submitted</span>
                                    @endif
                                </td>
                                <td data-label="Actions">
                                    <div class="fee-row-actions">
                                        <button type="button" class="fee-btn fee-btn-ghost fee-btn-sm" data-fee-edit
                                                data-action="{{ route($routePrefix . '.payments.update', array_merge(['payment' => $payment->id], $pageQuery)) }}"
                                                data-id="{{ $payment->id }}"
                                                data-or="{{ $payment->or_number }}"
                                                data-amount="{{ number_format((float) $payment->amount, 2, '.', '') }}"
                                                data-paid="{{ $payment->paid_at->format('Y-m-d') }}"
                                                data-notes="{{ $payment->notes }}"
                                                data-bidder="{{ $bidderLabel }}"
                                                data-project="{{ $payment->project?->title }}"
                                                data-posted="{{ $payment->project ? $postedDate($payment->project) : '' }}">
                                            <i class="fas fa-pen" aria-hidden="true"></i> Edit
                                        </button>
                                        <form method="POST" action="{{ route($routePrefix . '.payments.destroy', array_merge(['payment' => $payment->id], $pageQuery)) }}" data-confirm-title="Remove this payment?" data-confirm="OR No. {{ $payment->or_number }} for {{ $bidderLabel }}. The bidder will no longer be able to submit a bid for this project." data-confirm-button="Remove payment" data-confirm-tone="danger">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="fee-btn fee-btn-danger fee-btn-sm" @disabled($bidSubmitted) title="{{ $bidSubmitted ? 'Already used for a submitted bid' : 'Remove this payment' }}">
                                                <i class="fas fa-trash-can" aria-hidden="true"></i><span class="sr-only">Remove</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <nav class="fee-pager" aria-label="Payment pages">
                <span>Showing {{ $payments->firstItem() }}–{{ $payments->lastItem() }} of {{ $payments->total() }}</span>
                <span class="fee-pager-links">
                    <a class="fee-btn fee-btn-ghost fee-btn-sm" href="{{ $payments->previousPageUrl() ?? '#' }}" @if($payments->onFirstPage()) aria-disabled="true" tabindex="-1" @endif><i class="fas fa-chevron-left" aria-hidden="true"></i> Previous</a>
                    <a class="fee-btn fee-btn-ghost fee-btn-sm" href="{{ $payments->nextPageUrl() ?? '#' }}" @unless($payments->hasMorePages()) aria-disabled="true" tabindex="-1" @endunless>Next <i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                </span>
            </nav>
        @endif
    </section>

    <dialog class="fee-dialog" id="fee-edit-dialog" aria-labelledby="fee-edit-title">
        <form method="POST" action="#" data-fee-edit-form novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="edit">
            <input type="hidden" name="_payment_id" value="{{ $editFormActive ? old('_payment_id') : '' }}" data-fee-edit-id>
            <div class="fee-card-head">
                <div>
                    <h2 id="fee-edit-title">Correct payment record</h2>
                    <p>Changes are kept in the audit log.</p>
                </div>
                <button type="button" class="fee-dialog-close" data-fee-edit-close aria-label="Close"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="fee-card-body">
                <div class="fee-dialog-context">
                    <strong data-fee-edit-bidder>{{ $editFormActive ? old('_bidder') : '' }}</strong>
                    <span class="fee-hint" data-fee-edit-project>{{ $editFormActive ? old('_project') : '' }}</span>
                    <input type="hidden" name="_bidder" value="{{ $editFormActive ? old('_bidder') : '' }}" data-fee-edit-bidder-input>
                    <input type="hidden" name="_project" value="{{ $editFormActive ? old('_project') : '' }}" data-fee-edit-project-input>
                </div>

                @if($editFormActive && $errors->any())
                    <div class="fee-alert fee-alert-error" role="alert" style="margin-bottom: 14px;">
                        <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="fee-form">
                    <div class="fee-field">
                        <label for="fee-edit-amount">Amount paid <span class="fee-req" aria-hidden="true">*</span></label>
                        <div class="fee-money">
                            <input id="fee-edit-amount" type="number" name="amount" min="0.01" step="0.01" inputmode="decimal" class="fee-input" value="{{ $editFormActive ? old('amount') : '' }}" required>
                        </div>
                    </div>
                    <div class="fee-field">
                        <label for="fee-edit-or">Official Receipt No. <span class="fee-req" aria-hidden="true">*</span></label>
                        <input id="fee-edit-or" type="text" name="or_number" maxlength="60" autocomplete="off" spellcheck="false" class="fee-input fee-or" value="{{ $editFormActive ? old('or_number') : '' }}" required>
                    </div>
                    <div class="fee-field">
                        <label for="fee-edit-date">Date paid <span class="fee-req" aria-hidden="true">*</span></label>
                        <input id="fee-edit-date" type="date" name="paid_at" max="{{ $today }}" class="fee-input" value="{{ $editFormActive ? old('paid_at') : '' }}" required>
                    </div>
                    <div class="fee-field">
                        <label for="fee-edit-notes">Notes</label>
                        <textarea id="fee-edit-notes" name="notes" maxlength="1000" rows="2" class="fee-input">{{ $editFormActive ? old('notes') : '' }}</textarea>
                    </div>
                    <div class="fee-form-actions" style="justify-content: flex-end;">
                        <button type="button" class="fee-btn fee-btn-ghost" data-fee-edit-close>Cancel</button>
                        <button type="submit" class="fee-btn fee-btn-primary"><i class="fas fa-floppy-disk" aria-hidden="true"></i> Save changes</button>
                    </div>
                </div>
            </div>
        </form>
    </dialog>
</div>

<script>
    (function () {
        const alert = document.querySelector('[data-fee-autohide]');
        if (alert) {
            window.setTimeout(function () {
                alert.classList.add('is-hiding');
                window.setTimeout(function () { alert.remove(); }, 360);
            }, 4500);
        }

        // Record form: show the selected project's fee and prefill the amount.
        const recordForm = document.querySelector('[data-fee-record-form]');
        if (recordForm) {
            const project = recordForm.querySelector('[data-fee-project]');
            const amount = recordForm.querySelector('[data-fee-amount]');
            const date = recordForm.querySelector('[data-fee-date]');
            const note = recordForm.querySelector('[data-fee-project-note]');
            let lastFee = '';

            const syncProject = function (prefill) {
                const option = project.options[project.selectedIndex];
                const fee = option ? option.dataset.fee || '' : '';

                if (!fee) {
                    note.classList.remove('is-visible');
                    date.removeAttribute('min');
                    lastFee = '';
                    return;
                }

                note.querySelector('[data-fee-note-amount]').textContent = option.dataset.feeLabel;
                note.querySelector('[data-fee-note-deadline]').textContent = option.dataset.deadline || 'not set';
                note.classList.add('is-visible');
                if (option.dataset.posted) date.min = option.dataset.posted;
                if (prefill && (amount.value === '' || amount.value === lastFee)) amount.value = fee;
                lastFee = fee;
            };

            project.addEventListener('change', function () { syncProject(true); });
            syncProject(amount.value === '');

            recordForm.addEventListener('submit', function (event) {
                if (!recordForm.checkValidity()) {
                    event.preventDefault();
                    recordForm.reportValidity();
                    return;
                }
                const button = recordForm.querySelector('button[type="submit"]');
                if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); }
            });
        }

        // Record dialog: opened from the page header, reopened after a failed save.
        const recordDialog = document.getElementById('fee-record-dialog');
        if (recordDialog) {
            const openRecord = function () {
                if (typeof recordDialog.showModal === 'function') recordDialog.showModal(); else recordDialog.setAttribute('open', '');
            };
            document.querySelectorAll('[data-fee-open-record]').forEach(function (button) { button.addEventListener('click', openRecord); });
            recordDialog.querySelectorAll('[data-fee-record-close]').forEach(function (button) {
                button.addEventListener('click', function () { recordDialog.close ? recordDialog.close() : recordDialog.removeAttribute('open'); });
            });
            recordDialog.addEventListener('click', function (event) {
                if (event.target === recordDialog && recordDialog.close) recordDialog.close();
            });
            @if($recordFormActive)
                openRecord();
            @endif
        }

        // Edit dialog, filled from the row's data attributes.
        const dialog = document.getElementById('fee-edit-dialog');
        const editForm = dialog ? dialog.querySelector('[data-fee-edit-form]') : null;

        const openEdit = function (data) {
            if (!dialog || !editForm) return;
            editForm.action = data.action;
            editForm.querySelector('[data-fee-edit-id]').value = data.id;
            editForm.querySelector('[data-fee-edit-bidder]').textContent = data.bidder;
            editForm.querySelector('[data-fee-edit-bidder-input]').value = data.bidder;
            editForm.querySelector('[data-fee-edit-project]').textContent = data.project;
            editForm.querySelector('[data-fee-edit-project-input]').value = data.project;
            if (data.amount !== undefined) editForm.elements.amount.value = data.amount;
            if (data.or !== undefined) editForm.elements.or_number.value = data.or;
            if (data.paid !== undefined) editForm.elements.paid_at.value = data.paid;
            if (data.notes !== undefined) editForm.elements.notes.value = data.notes;
            if (data.posted) editForm.elements.paid_at.min = data.posted; else editForm.elements.paid_at.removeAttribute('min');
            if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
        };

        document.querySelectorAll('[data-fee-edit]').forEach(function (button) {
            button.addEventListener('click', function () {
                // Opening a row clears errors left from a previous edit attempt.
                dialog.querySelectorAll('.fee-alert-error').forEach(function (node) { node.remove(); });
                openEdit(button.dataset);
            });
        });

        dialog && dialog.querySelectorAll('[data-fee-edit-close]').forEach(function (button) {
            button.addEventListener('click', function () { dialog.close ? dialog.close() : dialog.removeAttribute('open'); });
        });

        dialog && dialog.addEventListener('click', function (event) {
            if (event.target === dialog && dialog.close) dialog.close();
        });

        if (editForm) {
            editForm.addEventListener('submit', function (event) {
                if (!editForm.checkValidity()) {
                    event.preventDefault();
                    editForm.reportValidity();
                }
            });
        }

        @if($reopenEdit !== null)
            // Reopen the dialog with the rejected values after a failed save.
            openEdit(@json($reopenEdit));
        @endif

    })();
</script>
