@php
    use App\Models\Award;

    $project = $award->project;
    $awardee = $award->bid?->user;
    $abc = (float) ($project?->budget ?? 0);
    $contractAmount = (float) ($award->contract_amount ?? 0);
    $savings = $abc - $contractAmount;
    $savingsPercent = $abc > 0 ? ($savings / $abc) * 100 : 0;

    // One status for the header: cancelled and revoked awards say so, an approved award without its Notice of Award waits for it.
    [$statusLabel, $statusTone] = match (true) {
        $award->isCancelled() => ['Cancelled', 'danger'],
        $award->status === Award::STATUS_REVOKED => ['Revoked', 'danger'],
        $award->awaitsNoticeOfAward() => ['Awaiting Notice of Award', 'warning'],
        $award->status === Award::STATUS_VALID => ['Valid', 'success'],
        default => [ucfirst((string) $award->status), 'warning'],
    };
    $dates = [
        ['Notice of Award', $award->notice_of_award_date],
        ['Contract date', $award->contract_date],
        ['Award approved', $award->award_approved_at],
    ];
@endphp
<div class="av">
    <header class="av-head">
        <span class="av-eyebrow">Award</span>
        <h2>{{ $project->title ?? 'Untitled project' }}</h2>
        <div class="av-chips">
            @if($project?->reference_no)<span class="av-chip av-chip--mono">{{ $project->reference_no }}</span>@endif
            @if($project?->category)<span class="av-chip">{{ ucfirst($project->category) }}</span>@endif
            <span class="av-chip av-chip--{{ $statusTone }}">{{ $statusLabel }}</span>
        </div>
    </header>

    <div class="av-body">
        <section class="av-awardee" aria-label="Awardee">
            <span class="av-awardee-icon"><i class="fas fa-building" aria-hidden="true"></i></span>
            <div>
                <small>Awardee</small>
                <strong>{{ $awardee?->company ?: ($awardee?->name ?? 'N/A') }}</strong>
                @if($award->bid?->receipt_no)<span>Bid receipt {{ $award->bid->receipt_no }}</span>@endif
            </div>
        </section>

        <section class="av-figures" aria-label="Contract figures">
            <div class="av-figure">
                <small>ABC (Approved Budget)</small>
                <strong>&#8369;{{ number_format($abc, 2) }}</strong>
            </div>
            <div class="av-figure av-figure--main">
                <small>Contract amount</small>
                <strong>&#8369;{{ number_format($contractAmount, 2) }}</strong>
                <span>Winning bid &#8369;{{ number_format((float) ($award->bid->amount ?? 0), 2) }}</span>
            </div>
            <div class="av-figure {{ $savings >= 0 ? 'is-savings' : 'is-over' }}">
                <small>{{ $savings >= 0 ? 'Savings' : 'Over budget' }}</small>
                <strong>&#8369;{{ number_format(abs($savings), 2) }}</strong>
                <span>{{ number_format(abs($savingsPercent), 2) }}% of the ABC</span>
            </div>
        </section>

        <section class="av-card" aria-label="Key dates">
            <h3>Key dates</h3>
            <dl class="av-dates">
                @foreach($dates as [$label, $date])
                    <div>
                        <dt>{{ $label }}</dt>
                        <dd>{{ $date ? $date->format('M d, Y') : 'Not yet' }}</dd>
                    </div>
                @endforeach
                <div>
                    <dt>Certificate no.</dt>
                    <dd class="av-mono">{{ $award->certificate_number ?: 'Not yet' }}</dd>
                </div>
            </dl>
        </section>

        @if($award->hasCertificateFile())
            <section class="av-card av-cert" aria-label="Official certificate">
                <div class="av-qr" aria-label="Scan QR code for official award certificate">
                    <img src="{{ $award->tokenQrUrl() }}" alt="QR code for official award certificate">
                </div>
                <div>
                    <h3>Official certificate</h3>
                    <strong class="av-mono">{{ $award->certificate_number }}</strong>
                    <p>Scan this QR code to view the authentic award certificate.</p>
                </div>
            </section>
        @endif

        <section class="av-card" aria-label="Certificate administration">
            <h3>Certificate administration</h3>
            <div class="av-actions">
                @if($award->status === Award::STATUS_VALID)
                    <button type="button" class="av-btn av-btn--danger" onclick="confirmRevokeCertificate({{ $award->id }})"><i class="fas fa-ban" aria-hidden="true"></i> Revoke certificate</button>
                @endif
                <button type="button" class="av-btn av-btn--primary" onclick="triggerReplaceCertificate({{ $award->id }})"><i class="fas fa-file-arrow-up" aria-hidden="true"></i> Replace certificate</button>
                <button type="button" class="av-btn" onclick="regenerateToken({{ $award->id }})"><i class="fas fa-rotate" aria-hidden="true"></i> Regenerate QR token</button>
            </div>
            <p id="replaceCertificateMsg-{{ $award->id }}" class="av-msg" style="display:none;"></p>
        </section>

        <section class="av-card" aria-label="Notes">
            <h3>Notes</h3>
            <p class="av-notes {{ $award->notes ? '' : 'is-empty' }}">{{ $award->notes ?: 'No notes provided.' }}</p>
        </section>

        <!-- Hidden file input for certificate replacement -->
        <input type="file" class="replace-certificate-input" accept=".pdf" style="display:none;">
    </div>

    <footer class="av-foot">
        <button type="button" onclick="closeAwardViewModal()" class="av-btn">Close</button>
    </footer>
</div>

<style>
    .av { display: flex; flex-direction: column; min-height: 0; max-height: calc(100vh - 40px); background: #fff; font-family: var(--ui-font); }
    .av-head { flex: 0 0 auto; padding: 22px 64px 16px 24px; border-bottom: 1px solid var(--ui-line-soft); background: var(--ui-surface-2, #f7faf8); }
    .av-eyebrow { color: var(--ui-primary, #1f5c45); font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .av-head h2 { margin: 4px 0 10px; color: var(--ui-ink); font-size: 19px; font-weight: 700; line-height: 1.3; }
    .av-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .av-chip { display: inline-flex; align-items: center; min-height: 24px; padding: 0 10px; border: 1px solid var(--ui-line); border-radius: 999px; background: #fff; color: var(--ui-ink-2); font-size: 12px; font-weight: 600; }
    .av-chip--mono, .av-mono { font-family: var(--ui-mono, ui-monospace, monospace); }
    .av-chip--success { border-color: var(--ui-success-line, #b9dfc9); background: var(--ui-success-soft, #eaf6ef); color: var(--ui-success, #1f7a4d); }
    .av-chip--warning { border-color: var(--ui-warning-line, #ecd9a6); background: var(--ui-warning-soft, #fbf3dc); color: var(--ui-warning, #8a5a00); }
    .av-chip--danger { border-color: #f3c4c4; background: #fdeeee; color: #a42a2a; }
    .av-body { display: grid; gap: 14px; padding: 18px 24px 20px; overflow-y: auto; }
    .av-awardee { display: flex; align-items: center; gap: 14px; padding: 14px 16px; border: 1px solid var(--ui-line); border-radius: 12px; }
    .av-awardee-icon { display: grid; place-items: center; width: 42px; height: 42px; border-radius: 11px; background: var(--ui-primary-soft, #e4f0ea); color: var(--ui-primary, #1f5c45); font-size: 17px; flex: 0 0 auto; }
    .av-awardee small, .av-figure small { display: block; color: var(--ui-muted); font-size: 11.5px; font-weight: 600; }
    .av-awardee strong { display: block; color: var(--ui-ink); font-size: 15px; }
    .av-awardee div > span { color: var(--ui-muted); font-size: 12px; }
    .av-figures { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .av-figure { display: grid; align-content: start; gap: 3px; padding: 14px 16px; border: 1px solid var(--ui-line); border-radius: 12px; background: #fff; }
    .av-figure strong { color: var(--ui-ink); font-size: 18px; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .av-figure span { color: var(--ui-muted); font-size: 12px; }
    .av-figure--main { border-color: var(--ui-primary, #1f5c45); background: var(--ui-primary-soft, #e4f0ea); }
    .av-figure--main strong { color: var(--ui-primary, #1f5c45); font-size: 20px; }
    .av-figure.is-savings strong { color: #047857; }
    .av-figure.is-over strong { color: #b91c1c; }
    .av-card { padding: 14px 16px; border: 1px solid var(--ui-line); border-radius: 12px; }
    .av-card h3 { margin: 0 0 10px; color: var(--ui-ink); font-size: 13px; font-weight: 700; }
    .av-dates { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin: 0; }
    .av-dates dt { color: var(--ui-muted); font-size: 11.5px; font-weight: 600; }
    .av-dates dd { margin: 3px 0 0; color: var(--ui-ink); font-size: 13px; font-weight: 600; overflow-wrap: anywhere; }
    .av-cert { display: flex; align-items: center; gap: 16px; background: var(--ui-surface-2, #f7faf8); }
    .av-cert h3 { margin-bottom: 4px; }
    .av-cert p { margin: 4px 0 0; color: var(--ui-muted); font-size: 12px; line-height: 1.5; }
    .av-qr { display: inline-flex; width: 128px; height: 128px; padding: 8px; border: 1px solid var(--ui-line); border-radius: 12px; background: #fff; flex: 0 0 auto; }
    .av-qr img { width: 100%; height: 100%; object-fit: contain; }
    .av-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .av-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 38px; padding: 0 14px; border: 1px solid var(--ui-line-strong); border-radius: 10px; background: #fff; color: var(--ui-ink); font: inherit; font-size: 13px; font-weight: 700; cursor: pointer; }
    .av-btn:hover { background: var(--ui-surface-2, #f7faf8); }
    .av-btn--primary { border-color: var(--ui-primary, #1f5c45); background: var(--ui-primary, #1f5c45); color: #fff; }
    .av-btn--primary:hover { background: var(--ui-primary-hover, #174a37); }
    .av-btn--danger { border-color: #f3c4c4; color: #a42a2a; }
    .av-btn--danger:hover { background: #fdeeee; }
    .av-msg { margin: 8px 0 0; color: #059669; font-size: 12px; }
    .av-notes { margin: 0; color: var(--ui-ink); font-size: 13px; line-height: 1.55; white-space: pre-wrap; }
    .av-notes.is-empty { color: var(--ui-muted); }
    .av-foot { flex: 0 0 auto; display: flex; justify-content: flex-end; padding: 12px 24px; border-top: 1px solid var(--ui-line-soft); background: #fff; }
    @media (max-width: 700px) {
        .av-head { padding: 18px 56px 14px 18px; }
        .av-body { padding: 14px 16px 16px; }
        .av-figures { grid-template-columns: 1fr; }
        .av-dates { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .av-cert { flex-direction: column; align-items: flex-start; }
        .av-actions .av-btn { flex: 1 1 100%; }
        .av-foot { padding: 12px 16px; }
        .av-foot .av-btn { width: 100%; }
    }
</style>
