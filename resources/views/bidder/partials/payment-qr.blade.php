@once
<style>
    .bidder-submit-modal-overlay .sb .sb-paycard { background: #fff; border-color: var(--ui-primary-line, #b7d2c4); }
    .bidder-submit-modal-overlay .sb-payment-slip { display: grid; grid-template-columns: 168px minmax(0, 1fr); align-items: center; gap: 24px; padding: 16px; border: 1px solid var(--ui-primary-line, #b7d2c4); border-radius: 12px; background: var(--ui-primary-soft, #e8f1ec); }
    .bidder-submit-modal-overlay .sb-payment-code { margin: 0; text-align: center; }
    .bidder-submit-modal-overlay .sb-payment-code img { display: block; width: 168px; height: 168px; max-width: 100%; margin: auto; background: #fff; border-radius: 8px; }
    .bidder-submit-modal-overlay .sb-payment-code figcaption { margin-top: 8px; color: var(--ui-muted); font-size: 11px; letter-spacing: .08em; }
    .bidder-submit-modal-overlay .sb-payment-details { display: grid; gap: 10px; min-width: 0; margin: 0; }
    .bidder-submit-modal-overlay .sb-payment-details dt { color: var(--ui-muted); font-size: 11px; font-weight: 600; letter-spacing: .04em; }
    .bidder-submit-modal-overlay .sb-payment-details dd { margin: 4px 0 0; color: var(--ui-ink); font-size: 15px; overflow-wrap: anywhere; }
    .bidder-submit-modal-overlay .sb-payment-details .sb-payment-amount { color: var(--ui-primary); font-size: 27px; font-weight: 700; }
    .bidder-submit-modal-overlay .sb-payment-download { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; min-height: 46px; padding: 12px 16px; border: 0; border-radius: 8px; background: var(--ui-primary); color: #fff; font: 600 14px/1.3 var(--ui-font); cursor: pointer; }
    .bidder-submit-modal-overlay .sb-payment-download:disabled { opacity: .65; cursor: wait; }
    .bidder-submit-modal-overlay .sb-payment-help { margin: 0; text-align: center; color: var(--ui-muted); font-size: 12px; line-height: 1.6; }
    @media (max-width: 640px) {
        .bidder-submit-modal-overlay .sb-payment-slip { grid-template-columns: 1fr; gap: 20px; padding: 16px; }
        .bidder-submit-modal-overlay .sb-payment-details { grid-template-columns: 1fr 1fr; gap: 16px; }
        .bidder-submit-modal-overlay .sb-payment-details .sb-payment-amount { font-size: 23px; }
    }
</style>
@endonce
<div class="sb-payment-slip" data-payment-slip>
    <figure class="sb-payment-code">
        <img src="{{ route('bidder.payment-qr', $project) }}" width="200" height="200" loading="lazy" alt="Payment QR for {{ $project->reference_no ?: $project->title }}" data-payment-qr-image>
        <figcaption>YOUR PAYMENT QR</figcaption>
    </figure>
    <dl class="sb-payment-details">
        <div><dt>BIDDER</dt><dd>{{ auth()->user()->name }}</dd></div>
        <div><dt>COMPANY</dt><dd>{{ auth()->user()->company ?: auth()->user()->name }}</dd></div>
        <div><dt>PROJECT REFERENCE</dt><dd>{{ $project->reference_no ?: 'Not assigned' }}</dd></div>
        <div><dt>BIDDING DOCUMENTS FEE</dt><dd class="sb-payment-amount">{{ $feeLabel }}</dd></div>
    </dl>
</div>
<button type="button" class="sb-payment-download" data-payment-qr-download data-payment-details="{{ json_encode([
    'name' => auth()->user()->name, 'company' => auth()->user()->company ?: auth()->user()->name,
    'project' => $project->title, 'reference' => $project->reference_no ?: (string) $pid,
    'fee' => $feeLabel, 'venue' => $project->paymentVenueLabel(),
    'deadline' => $deadlineLocal?->format('M d, Y g:i A') ?: 'See the project notice',
], JSON_UNESCAPED_UNICODE) }}"><i class="fas fa-download" aria-hidden="true"></i> <span>Download Payment QR</span></button>
<p class="sb-payment-help" data-payment-qr-status role="status">Save to your phone or print. Show this QR at the BAC office.<br>Pay in person first, then submit here.</p>
