@php
    use App\Models\Award;

    $modalAbc = (float) ($award->project?->budget ?? 0);
    $modalContractAmount = (float) ($award->contract_amount ?? 0);
    $modalSavings = $modalAbc - $modalContractAmount;
    $modalSavingsClass = $modalSavings >= 0 ? 'is-savings' : 'is-over-budget';
@endphp
<div class="view-bid-modal-shell">
    <div class="view-bid-modal-header">
        <div>
            <h2>View Award</h2>
        </div>
    </div>

    <div class="view-bid-modal-body">
        <div class="view-bid-grid view-bid-grid-two">
            <div class="view-bid-field">
                <label>Project</label>
                <div class="view-bid-value">{{ $award->project->title ?? 'N/A' }}</div>
            </div>

            <div class="view-bid-field">
                <label>Awardee</label>
                <div class="view-bid-value">{{ $award->bid->user->company ?: ($award->bid->user->name ?? 'N/A') }}</div>
            </div>
        </div>

        <div class="view-bid-grid view-bid-grid-three">
            <div class="view-bid-field">
                <label>ABC (Approved Budget)</label>
                <div class="view-bid-value award-modal-money">&#8369;{{ number_format($modalAbc, 2) }}</div>
            </div>

            <div class="view-bid-field">
                <label>Contract Amount</label>
                <div class="view-bid-value award-modal-money">&#8369;{{ number_format($modalContractAmount, 2) }}</div>
            </div>

            <div class="view-bid-field">
                <label>Savings</label>
                <div class="view-bid-value award-modal-money {{ $modalSavingsClass }}">&#8369;{{ number_format($modalSavings, 2) }}</div>
            </div>
        </div>

        <div class="view-bid-grid view-bid-grid-three">
            <div class="view-bid-field">
                <label>Contract Date</label>
                <div class="view-bid-value">{{ $award->contract_date?->format('m/d/Y') ?? 'N/A' }}</div>
            </div>

            <div class="view-bid-field">
                <label>Status</label>
                <div class="view-bid-value">{{ ucfirst($award->status) }}</div>
            </div>

            <div class="view-bid-field">
                <label>Winning Bid</label>
                <div class="view-bid-value award-modal-money">&#8369;{{ number_format((float) ($award->bid->amount ?? 0), 2) }}</div>
            </div>
        </div>

        @if($award->hasCertificateFile())
            <div class="view-bid-field">
                <label>Official Certificate</label>
                <div class="award-modal-certificate">
                    <div class="award-modal-qr" aria-label="Scan QR code for official award certificate">
                        <img src="{{ $award->tokenQrUrl() }}" alt="QR code for official award certificate">
                    </div>
                    <div>
                        <strong>{{ $award->certificate_number }}</strong>
                        <p>Scan this QR code to view the authentic award certificate.</p>
                    </div>
                </div>
            </div>
        @endif

        <div class="view-bid-field">
            <label>Certificate Administration</label>
            <div class="award-cert-admin" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <span class="status-badge status-{{ $award->status }}" style="
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 11px;
                    font-weight: 600;
                    @if($award->status === Award::STATUS_VALID)
                        background: #d1fae5; color: #065f46;
                    @elseif($award->status === Award::STATUS_REVOKED)
                        background: #fee2e2; color: #991b1b;
                    @else
                        background: #fef3c7; color: #92400e;
                    @endif
                ">{{ ucfirst($award->status) }}</span>
                <div class="award-cert-actions" style="display:flex; gap:8px; flex-wrap:wrap;">
                    @if($award->status === Award::STATUS_VALID)
                        <button type="button" class="award-cert-btn" onclick="confirmRevokeCertificate({{ $award->id }})" style="padding:6px 12px; border-radius:6px; border:1px solid #d2cbbb !important; background:#ffffff !important; color:#1b2420 !important; -webkit-text-fill-color:#1b2420 !important; cursor:pointer; font-size:12px; font-weight:700; box-shadow:none !important;">Revoke Certificate</button>
                    @endif
                    <button type="button" class="award-cert-btn" onclick="triggerReplaceCertificate({{ $award->id }})" style="padding:6px 12px; border-radius:6px; border:1px solid #050505 !important; background:#050505 !important; color:#ffffff !important; -webkit-text-fill-color:#ffffff !important; cursor:pointer; font-size:12px; font-weight:700; box-shadow:none !important;">Replace Certificate</button>
                    <button type="button" class="award-cert-btn" onclick="regenerateToken({{ $award->id }})" style="padding:6px 12px; border-radius:6px; border:1px solid #d2cbbb !important; background:#ffffff !important; color:#1b2420 !important; -webkit-text-fill-color:#1b2420 !important; cursor:pointer; font-size:12px; font-weight:700; box-shadow:none !important;">Regenerate QR Token</button>
                </div>
            </div>
            <p id="replaceCertificateMsg-{{ $award->id }}" style="font-size:11px; color:#059669; margin-top:4px; display:none;"></p>
        </div>

        <div class="view-bid-field">
            <label>Notes</label>
            <div class="view-bid-value view-bid-textarea">{{ $award->notes ?: 'No notes provided.' }}</div>
        </div>

        <div class="view-bid-actions">
            <button type="button" onclick="closeAwardViewModal()" class="btn-secondary">Close</button>
        </div>

        <!-- Hidden file input for certificate replacement -->
        <input type="file" class="replace-certificate-input" accept=".pdf" style="display:none;">
    </div>
</div>

<style>
    .view-bid-modal-shell {
        display: flex;
        flex-direction: column;
        min-height: 0;
        background: #fff;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        font-family: var(--ui-font);
    }

    .view-bid-modal-header {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 56px;
        border-bottom: 1px solid var(--ui-line-soft);
        background: #ffffff;
    }

    .view-bid-modal-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        line-height: 1.2;
        color: var(--ui-ink);
    }

    .view-bid-modal-body {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        display: grid;
        gap: 10px;
        box-sizing: border-box;
    }

    .view-bid-grid {
        display: grid;
        gap: 12px;
    }

    .view-bid-grid-two {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .view-bid-grid-three {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .view-bid-field label {
        display: block;
        margin-bottom: 6px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: normal;
        text-transform: none;
        color: var(--ui-muted);
    }

    .view-bid-value {
        width: 100%;
        min-height: 40px;
        display: flex;
        align-items: center;
        padding: 10px 12px;
        border: 1px solid var(--ui-line-strong);
        border-radius: var(--ui-radius-lg);
        background: #fff;
        color: var(--ui-ink);
        font-size: 13px;
        line-height: 1.5;
        box-sizing: border-box;
    }

    .view-bid-value.award-modal-money {
        font-variant-numeric: tabular-nums;
        font-weight: 700;
        white-space: nowrap;
    }

    .view-bid-value.award-modal-money.is-savings {
        color: #047857;
    }

    .view-bid-value.award-modal-money.is-over-budget {
        color: #b91c1c;
    }

    .view-bid-textarea {
        min-height: 84px;
        align-items: flex-start;
        white-space: pre-wrap;
    }

    .award-modal-certificate {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px;
        border: 1px solid var(--ui-line);
        border-radius: var(--ui-radius-lg);
        background: var(--ui-surface-2);
    }

    .award-modal-qr {
        display: inline-flex;
        width: 160px;
        height: 160px;
        padding: 10px;
        border-radius: var(--ui-radius-lg);
        background: #fff;
        border: 1px solid var(--ui-line);
        flex: 0 0 auto;
    }

    .award-modal-qr img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .award-modal-certificate strong {
        display: block;
        color: var(--ui-ink);
        font-size: 14px;
        margin-bottom: 4px;
    }

    .award-modal-certificate p {
        margin: 0 0 6px;
        color: var(--ui-muted);
        font-size: 12px;
        line-height: 1.5;
    }

    .award-modal-certificate a {
        color: var(--ui-primary);
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
    }

    .view-bid-actions {
        flex: 0 0 auto;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        align-items: center;
        padding: 12px 16px;
        border-top: 1px solid var(--ui-line-soft);
        background: #fff;
        box-shadow: 0 -8px 16px -12px rgba(27, 36, 32, 0.18);
        box-sizing: border-box;
    }

    .view-bid-actions .btn-primary,
    .view-bid-actions .btn-secondary {
        min-width: 132px;
        height: 36px;
        padding: 0 16px;
        border-radius: var(--ui-radius);
        font-size: 12px;
        font-family: var(--ui-font);
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    @media (max-width: 700px) {
        .view-bid-grid-two,
        .view-bid-grid-three {
            grid-template-columns: 1fr;
        }

        .award-modal-certificate {
            flex-direction: column;
            align-items: flex-start;
        }

        .view-bid-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .view-bid-actions .btn-primary,
        .view-bid-actions .btn-secondary {
            width: 100%;
        }
    }

</style>
