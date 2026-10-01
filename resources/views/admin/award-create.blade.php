@php
    $defaultBidId = old('bid_id', $selectedBidId ?? $bids->first()?->id);
    $selectedBid = $bids->firstWhere('id', $defaultBidId) ?? $bids->first();
    $defaultAmount = old('contract_amount', $selectedBid?->amount);
    $defaultDate = old('contract_date', now()->toDateString());
    $lowestBidId = $bids->first()?->id;
@endphp

<div class="declare-award-modal-shell">
    <div class="declare-award-modal-header">
        <div>
            <h2>Notice of Award - {{ $project->title }}</h2>
        </div>
    </div>

     <form action="{{ route('admin.awards.declare', $project) }}" method="POST" class="declare-award-form" enctype="multipart/form-data">
         @csrf
         <input type="hidden" name="project_id" value="{{ $project->id }}">
         <input type="hidden" name="contract_amount" id="awardContractAmount" value="{{ $defaultAmount }}">
         <input type="hidden" name="contract_date" value="{{ $defaultDate }}">

        <div class="declare-award-body">
            <div id="awardFormAlert" class="declare-award-error" style="display: none;"></div>

            @if($errors->any())
                <div class="declare-award-error">
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <p class="declare-award-helper">Only a bid the BAC recommended and the Head of the Procuring Entity approved can receive the Notice of Award. Upload the signed Notice of Award (PDF): it becomes the QR-verifiable award document. The contract amount is the bid price as submitted; record contract signing and the Notice to Proceed afterwards on the bid.</p>

            <div class="declare-award-options">
                @forelse($bids as $bid)
                    @php
                        $variance = $project->budget > 0 ? ((($bid->amount ?? 0) - $project->budget) / $project->budget) * 100 : null;
                        $varianceLabel = is_null($variance) ? 'No budget basis' : number_format($variance, 1) . '% vs budget';
                        $isSelected = (string) $defaultBidId === (string) $bid->id;
                        $isLowest = (int) $lowestBidId === (int) $bid->id;
                    @endphp
                    <label class="declare-award-option {{ $isSelected ? 'is-selected' : '' }}" data-bid-option data-bid-amount="{{ $bid->amount }}" onclick="selectDeclareWinnerOption(this)">
                        <input
                            type="radio"
                            name="bid_id"
                            value="{{ $bid->id }}"
                            {{ $isSelected ? 'checked' : '' }}
                            hidden
                        >

                        <div class="declare-award-option-main">
                            <div class="declare-award-bidder">
                                <div class="declare-award-bidder-name">{{ $bid->user->company ?: ($bid->user->name ?? 'N/A') }}</div>
                                <div class="declare-award-bidder-email">{{ $bid->user->email ?? 'N/A' }}</div>
                                @if($isLowest)
                                    <div class="declare-award-lowest">BAC Recommended</div>
                                @endif
                            </div>

                            <div class="declare-award-amount-wrap">
                                <div class="declare-award-amount">P{{ number_format((float) $bid->amount, 2) }}</div>
                                <div class="declare-award-variance {{ !is_null($variance) && $variance <= 0 ? 'is-good' : '' }}">{{ $varianceLabel }}</div>
                            </div>
                        </div>
                    </label>
                @empty
                    <div class="declare-award-empty">No BAC-recommended bid is awaiting award approval for this project.</div>
                @endforelse
            </div>
            <p class="declare-award-field-error" data-error-for="bid_id"></p>

            <div class="declare-award-field">
                <label>Award Notes</label>
                <textarea name="notes" rows="4" class="declare-award-textarea" placeholder="Lowest compliant bid with complete documentation.">{{ old('notes', 'Lowest compliant bid with complete documentation.') }}</textarea>
                <p class="declare-award-field-error" data-error-for="notes"></p>
            </div>

            <div class="declare-award-field">
                <label for="certificateFile">Signed Notice of Award (PDF) <span style="color:red">*</span></label>
                <input type="file" name="certificate_file" id="certificateFile" class="declare-award-file" accept="application/pdf" required onchange="validateDeclareWinnerForm()">
                <p class="declare-award-field-hint" style="font-size:11px; color:#6b7280; margin-top:4px;">Upload the authentic Certificate of Winner (PDF only, max 5MB).</p>
                <p class="declare-award-field-error" data-error-for="certificate_file"></p>
                <p id="certificateFileName" style="font-size:11px; color:#059669; margin-top:4px;"></p>
            </div>
        </div>

        <div class="declare-award-actions">
            <button type="button" class="declare-award-secondary" onclick="closeDeclareWinnerModal()" style="background: #ffffff !important; border: 1px solid #d2cbbb !important; color: #1b2420 !important; -webkit-text-fill-color: #1b2420 !important; box-shadow: none !important; opacity: 1 !important; text-indent: 0 !important; font-size: 14px !important;">Cancel</button>
            <button type="submit" id="declareWinnerSubmitBtn" class="declare-award-primary" disabled>Issue Notice of Award</button>
        </div>
    </form>
</div>

<style>
    .declare-award-modal-shell {
        background: #fff;
        border-radius: var(--ui-radius-lg);
        overflow: hidden;
        font-family: var(--ui-font);
        box-shadow: 0 18px 42px rgba(27, 36, 32, 0.12);
    }

    .declare-award-modal-header {
        min-height: 64px;
        display: flex;
        align-items: center;
        padding: 0 20px;
        border-bottom: 1px solid var(--ui-line-soft);
    }

    .declare-award-modal-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: var(--ui-ink);
        line-height: 1.2;
    }

    .declare-award-form {
        margin: 0;
    }

    .declare-award-body {
        padding: 16px 16px 0;
        display: grid;
        gap: 8px;
    }

    .declare-award-helper {
        margin: 0 0 6px;
        color: var(--ui-muted);
        font-size: 12px;
        font-weight: 500;
    }

    .declare-award-error {
        margin-bottom: 8px;
        padding: 10px 12px;
        border-radius: var(--ui-radius-lg);
        border: 1px solid #fecaca;
        background: #fef2f2;
        color: #b91c1c;
        font-size: 12px;
    }

    .declare-award-error ul {
        margin: 0;
        padding-left: 18px;
    }

    .declare-award-options {
        display: grid;
        gap: 12px;
        margin-bottom: 2px;
    }

    .declare-award-option {
        display: block;
        border: 1px solid var(--ui-line-strong);
        border-radius: var(--ui-radius-lg);
        padding: 14px;
        cursor: pointer;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        background: #fff;
    }

    .declare-award-option.is-selected {
        border-color: var(--ui-primary);
        box-shadow: 0 0 0 4px rgba(29, 79, 64, 0.08);
        background: var(--ui-surface-2);
    }

    .declare-award-option-main {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 18px;
    }

    .declare-award-bidder-name {
        font-size: 14px;
        font-weight: 600;
        color: var(--ui-ink);
        line-height: 1.4;
        margin-bottom: 4px;
    }

    .declare-award-bidder-email {
        font-size: 12px;
        color: var(--ui-subtle);
        line-height: 1.4;
        margin-bottom: 10px;
    }

    .declare-award-lowest {
        font-size: 11px;
        font-weight: 600;
        color: #166534;
        text-transform: none;
        letter-spacing: normal;
    }

    .declare-award-amount-wrap {
        min-width: 160px;
        text-align: right;
    }

    .declare-award-amount {
        font-size: 18px;
        font-weight: 600;
        color: var(--ui-ink);
        line-height: 1.3;
        margin-bottom: 4px;
    }

    .declare-award-variance {
        font-size: 12px;
        color: #15803d;
        line-height: 1.4;
    }

    .declare-award-field {
        margin-bottom: 6px;
    }

    .declare-award-field label {
        display: block;
        margin-bottom: 6px;
        font-size: 11px;
        font-weight: 600;
        color: var(--ui-muted);
        text-transform: none;
        letter-spacing: normal;
    }

    .declare-award-textarea {
        width: 100%;
        min-height: 84px;
        padding: 10px 12px;
        border: 1px solid var(--ui-line-strong);
        border-radius: var(--ui-radius-lg);
        font-size: 13px;
        color: var(--ui-ink);
        resize: vertical;
        box-sizing: border-box;
        outline: none;
        font-family: var(--ui-font);
        transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
    }

    .declare-award-textarea:focus {
        border-color: var(--ui-primary-line);
        box-shadow: 0 0 0 4px rgba(29, 79, 64, 0.12);
        background: #ffffff;
    }

    .declare-award-textarea.input-error,
    .declare-award-options.input-error {
        border-color: #f87171;
        box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.12);
    }

    .declare-award-options.input-error {
        padding: 6px;
        border: 1px solid #fca5a5;
        border-radius: var(--ui-radius-lg);
    }

    .declare-award-field-error {
        margin: 2px 0 0;
        color: #dc2626;
        font-size: 11px;
        line-height: 1.4;
    }

    .declare-award-field-error:empty {
        display: none;
    }

    .declare-award-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        align-items: center;
        margin: 2px -16px 0;
        padding: 12px 16px 14px;
        border-top: 1px solid var(--ui-line-soft);
        background: #fff;
        box-sizing: border-box;
    }

    .declare-award-primary,
    .declare-award-secondary {
        min-width: 132px;
        height: 38px;
        padding: 0 16px;
        border-radius: var(--ui-radius-lg);
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        font-family: var(--ui-font);
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .declare-award-primary {
        background: var(--ui-primary);
        border: 1px solid var(--ui-primary);
        color: #fff;
        box-shadow: 0 10px 24px rgba(29, 79, 64, 0.22);
    }

    .declare-award-primary:hover {
        background: var(--ui-primary-hover);
        border-color: var(--ui-primary-hover);
    }

    .declare-award-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .declare-award-secondary {
        background: #fff;
        color: var(--ui-ink-2);
        border: 1px solid var(--ui-line-strong);
        font-weight: 500;
    }

    .declare-award-secondary:hover {
        background: var(--ui-surface-2);
    }

    .declare-award-empty {
        padding: 18px;
        border: 1px dashed var(--ui-line-strong);
        border-radius: var(--ui-radius-lg);
        text-align: center;
        color: var(--ui-subtle);
        font-size: 14px;
    }

    @media (max-width: 720px) {
        .declare-award-modal-header,
        .declare-award-body,
        .declare-award-actions {
            padding-left: 16px;
            padding-right: 16px;
        }

        .declare-award-option-main {
            flex-direction: column;
        }

        .declare-award-amount-wrap {
            min-width: 0;
            text-align: left;
        }

        .declare-award-actions {
            flex-direction: column;
        }

        .declare-award-primary,
        .declare-award-secondary {
            width: 100%;
        }
    }
</style>

<script>
    const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    function validateCertificateFile() {
        const fileInput = document.getElementById('certificateFile');
        const fileNameSpan = document.getElementById('certificateFileName');
        const errorSpan = document.querySelector('[data-error-for="certificate_file"]');
        const submitBtn = document.getElementById('declareWinnerSubmitBtn');

        // Clear previous error
        if (errorSpan) {
            errorSpan.textContent = '';
        }
        if (fileNameSpan) {
            fileNameSpan.textContent = '';
        }

        const file = fileInput.files?.[0];

        if (!file) {
            submitBtn.disabled = true;
            return;
        }

        let error = '';

        // Check file type
        if (file.type !== 'application/pdf') {
            error = 'Only PDF files are allowed.';
        }

        // Check file size (5MB max)
        if (!error && file.size > MAX_FILE_SIZE) {
            error = `File size exceeds 5MB. Your file is ${(file.size / 1024 / 1024).toFixed(2)}MB.`;
        }

        if (error) {
            if (errorSpan) {
                errorSpan.textContent = error;
            }
            submitBtn.disabled = true;
            fileInput.classList.add('input-error');
        } else {
            if (fileNameSpan) {
                fileNameSpan.textContent = '✓ ' + file.name + ' (' + (file.size / 1024).toFixed(2) + ' KB)';
            }
            submitBtn.disabled = false;
            fileInput.classList.remove('input-error');
        }
    }

    // Validate bidder selection and certificate on form submit
    document.querySelector('.declare-award-form')?.addEventListener('submit', function(e) {
        const bidId = document.querySelector('input[name="bid_id"]:checked');
        const certificateFile = document.getElementById('certificateFile');

        let isValid = true;
        const errorContainer = document.getElementById('awardFormAlert');

        // Clear previous errors
        errorContainer.style.display = 'none';
        errorContainer.textContent = '';

        // Validate bid selection
        if (!bidId) {
            e.preventDefault();
            errorContainer.textContent = 'Please select a winning bidder.';
            errorContainer.style.display = 'block';
            isValid = false;
        }

        // Validate certificate
        if (!certificateFile.files?.[0]) {
            e.preventDefault();
            errorContainer.textContent = 'Please upload a certificate PDF.';
            errorContainer.style.display = 'block';
            isValid = false;
        }

        if (!isValid) {
            e.preventDefault();
        }
    });

    // Initialize button state on load
    document.addEventListener('DOMContentLoaded', validateCertificateFile);
</script>

