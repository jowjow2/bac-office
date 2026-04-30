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
            <h2>Declare Award - {{ $project->title }}</h2>
        </div>
    </div>

    <form action="{{ route('admin.awards.store') }}" method="POST" class="declare-award-form">
        @csrf
        <input type="hidden" name="project_id" value="{{ $project->id }}">
        <input type="hidden" name="contract_amount" id="awardContractAmount" value="{{ $defaultAmount }}">
        <input type="hidden" name="contract_date" value="{{ $defaultDate }}">
        <input type="hidden" name="status" value="active">

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

            <p class="declare-award-helper">Select the winning bidder from approved bids.</p>

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
                                    <div class="declare-award-lowest">Lowest Bid</div>
                                @endif
                            </div>

                            <div class="declare-award-amount-wrap">
                                <div class="declare-award-amount">P{{ number_format((float) $bid->amount, 2) }}</div>
                                <div class="declare-award-variance {{ !is_null($variance) && $variance <= 0 ? 'is-good' : '' }}">{{ $varianceLabel }}</div>
                            </div>
                        </div>
                    </label>
                @empty
                    <div class="declare-award-empty">No eligible bids are available for this project yet.</div>
                @endforelse
            </div>
            <p class="declare-award-field-error" data-error-for="bid_id"></p>

            <div class="declare-award-field">
                <label>Award Notes</label>
                <textarea name="notes" rows="4" class="declare-award-textarea" placeholder="Lowest compliant bid with complete documentation.">{{ old('notes', 'Lowest compliant bid with complete documentation.') }}</textarea>
                <p class="declare-award-field-error" data-error-for="notes"></p>
            </div>
        </div>

        <div class="declare-award-actions">
            <button type="button" class="declare-award-secondary" onclick="closeDeclareWinnerModal()">Cancel</button>
            <button type="submit" class="declare-award-primary" {{ $bids->isEmpty() ? 'disabled' : '' }}>Declare Winner</button>
        </div>
    </form>
</div>

<style>
    .declare-award-modal-shell {
        background: #fff;
        border-radius: 16px;
        overflow: hidden;
        font-family: 'Inter', sans-serif;
        box-shadow: 0 18px 42px rgba(15, 23, 42, 0.12);
    }

    .declare-award-modal-header {
        min-height: 64px;
        display: flex;
        align-items: center;
        padding: 0 20px;
        border-bottom: 1px solid #edf2f7;
    }

    .declare-award-modal-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #111827;
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
        color: #64748b;
        font-size: 12px;
        font-weight: 500;
    }

    .declare-award-error {
        margin-bottom: 8px;
        padding: 10px 12px;
        border-radius: 10px;
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
        border: 1px solid #d1d5db;
        border-radius: 12px;
        padding: 14px;
        cursor: pointer;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        background: #fff;
    }

    .declare-award-option.is-selected {
        border-color: #1d4ed8;
        box-shadow: 0 0 0 4px rgba(29, 78, 216, 0.08);
        background: #f8fbff;
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
        color: #0f172a;
        line-height: 1.4;
        margin-bottom: 4px;
    }

    .declare-award-bidder-email {
        font-size: 12px;
        color: #94a3b8;
        line-height: 1.4;
        margin-bottom: 10px;
    }

    .declare-award-lowest {
        font-size: 11px;
        font-weight: 600;
        color: #166534;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .declare-award-amount-wrap {
        min-width: 160px;
        text-align: right;
    }

    .declare-award-amount {
        font-size: 18px;
        font-weight: 600;
        color: #0f172a;
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
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .declare-award-textarea {
        width: 100%;
        min-height: 84px;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        font-size: 13px;
        color: #111827;
        resize: vertical;
        box-sizing: border-box;
        outline: none;
        font-family: 'Inter', sans-serif;
        transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
    }

    .declare-award-textarea:focus {
        border-color: #93c5fd;
        box-shadow: 0 0 0 4px rgba(29, 78, 216, 0.12);
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
        border-radius: 14px;
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
        border-top: 1px solid #edf2f7;
        background: #fff;
        box-sizing: border-box;
    }

    .declare-award-primary,
    .declare-award-secondary {
        min-width: 132px;
        height: 38px;
        padding: 0 16px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .declare-award-primary {
        background: #1d4ed8;
        border: 1px solid #1d4ed8;
        color: #fff;
        box-shadow: 0 10px 24px rgba(29, 78, 216, 0.22);
    }

    .declare-award-primary:hover {
        background: #1e40af;
        border-color: #1e40af;
    }

    .declare-award-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .declare-award-secondary {
        background: #fff;
        color: #374151;
        border: 1px solid #d1d5db;
        font-weight: 500;
    }

    .declare-award-secondary:hover {
        background: #f8fafc;
    }

    .declare-award-empty {
        padding: 18px;
        border: 1px dashed #d1d5db;
        border-radius: 12px;
        text-align: center;
        color: #94a3b8;
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
