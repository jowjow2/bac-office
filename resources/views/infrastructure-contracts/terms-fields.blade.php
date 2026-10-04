{{-- Deadline, site, reference and work items: shared by the record and correct forms. --}}
@php $items = array_values($items ?: [['description' => '', 'quantity' => '', 'unit' => '']]); @endphp
<input type="hidden" name="infra_form" value="{{ $form }}">
<div class="ui-fields infra-fields--3">
    <label class="ui-field">
        <span class="ui-label">Contract completion deadline <span class="ui-required">*</span></span>
        <input type="date" name="delivery_deadline" class="ui-input" value="{{ $values['delivery_deadline'] ?? '' }}" required>
        @if(! empty($deadlineHint))<span class="ui-hint">{{ $deadlineHint }}</span>@endif
    </label>
    <label class="ui-field">
        <span class="ui-label">Work site / delivery location <span class="ui-required">*</span></span>
        <input name="delivery_location" class="ui-input" maxlength="255" value="{{ $values['delivery_location'] ?? '' }}" required placeholder="e.g. Sitio Malaylay, Brgy. Bubog">
    </label>
    <label class="ui-field">
        <span class="ui-label">Signed contract reference <span class="ui-required">*</span></span>
        <input name="signed_contract_reference" class="ui-input" maxlength="255" value="{{ $values['signed_contract_reference'] ?? '' }}" required placeholder="e.g. Contract No. 2026-014">
    </label>
</div>
@if(! empty($note))<p class="ui-hint infra-prefill"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> {{ $note }}</p>@endif
<div class="infra-items" data-infra-items>
    <div class="infra-items__head">
        <span class="ui-label">Contract work items and quantities <span class="ui-required">*</span></span>
        <button type="button" class="ui-btn ui-btn--sm" data-infra-add-item><i class="fas fa-plus" aria-hidden="true"></i> Add work item</button>
    </div>
    <div class="infra-items__cols" aria-hidden="true"><span>Work item</span><span>Quantity</span><span>Unit</span><span></span></div>
    <div class="infra-items__rows" data-infra-rows>
        @foreach($items as $i => $row)
            <div class="infra-items__row" data-infra-row>
                <input name="contract_items[{{ $i }}][description]" class="ui-input" maxlength="255" value="{{ $row['description'] ?? '' }}" placeholder="e.g. PCCP 0.20 m thick" aria-label="Work item" required>
                <input name="contract_items[{{ $i }}][quantity]" class="ui-input" type="number" step="0.01" min="0.01" value="{{ $row['quantity'] ?? '' }}" placeholder="0.00" aria-label="Quantity" required>
                <input name="contract_items[{{ $i }}][unit]" class="ui-input" maxlength="50" value="{{ $row['unit'] ?? '' }}" placeholder="e.g. lm, sq.m." aria-label="Unit" required>
                <button type="button" class="infra-items__remove" data-infra-remove aria-label="Remove work item" @if(count($items) === 1) hidden @endif><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
        @endforeach
    </div>
</div>
