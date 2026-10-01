{{--
    Warranty terms of the signed contract (RA 12009 IRR Sec. 90.1). Used inside partials.contract-implementation,
    in the contract terms form and, for contracts recorded before these terms, in the completion form.
--}}
@php $supplyType = $old('supply_type', $implementation?->supply_type); @endphp
<fieldset class="ci-field" style="border:0;padding:0;margin:0">
    <legend class="ci-label" style="margin-bottom:6px">Warranty <span class="ci-req" aria-hidden="true">*</span></legend>
    <span class="ci-hint" style="margin-bottom:6px">Runs from acceptance: at least 3 months for expendable and 1 year for non-expendable supplies. Secured by retention money or a special bank guarantee of 1% to 5% (1% when the contract is silent).</span>
    <div class="ci-outcomes">
        @foreach(\App\Models\ContractImplementation::SUPPLY_TYPES as $type => $meta)
            <label class="ci-outcome">
                <input type="radio" name="supply_type" value="{{ $type }}" data-ci-min-months="{{ $meta['min_months'] }}" @checked($supplyType === $type) required>
                <span><strong>{{ $meta['label'] }}</strong><span>Warranty of at least {{ $meta['min_months'] }} months</span></span>
            </label>
        @endforeach
    </div>
    @if($err('supply_type'))<span class="ci-error">{{ $err('supply_type') }}</span>@endif
    <div class="ci-grid" style="margin-top:12px">
        <div class="ci-field">
            <label class="ci-label" for="{{ $uid }}-warranty-months">Warranty period (months) <span class="ci-req" aria-hidden="true">*</span></label>
            <input class="ci-input" type="number" id="{{ $uid }}-warranty-months" name="warranty_months" value="{{ $old('warranty_months', $implementation?->warranty_months) }}" min="1" max="120" step="1" required placeholder="{{ $supplyType === 'expendable' ? 3 : 12 }}" data-ci-warranty-months @if($err('warranty_months')) aria-invalid="true" @endif>
            @if($err('warranty_months'))<span class="ci-error">{{ $err('warranty_months') }}</span>@endif
        </div>
        <div class="ci-field">
            <label class="ci-label" for="{{ $uid }}-warranty-security">Warranty security <span class="ci-req" aria-hidden="true">*</span></label>
            <select class="ci-input" id="{{ $uid }}-warranty-security" name="warranty_security" required>
                @foreach(\App\Models\ContractImplementation::WARRANTY_SECURITIES as $key => $label)
                    <option value="{{ $key }}" @selected($old('warranty_security', $implementation?->warranty_security ?? 'retention') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @if($err('warranty_security'))<span class="ci-error">{{ $err('warranty_security') }}</span>@endif
        </div>
        <div class="ci-field">
            <label class="ci-label" for="{{ $uid }}-warranty-percent">Security rate (%)</label>
            <input class="ci-input" type="number" id="{{ $uid }}-warranty-percent" name="warranty_percent" value="{{ $old('warranty_percent', $implementation?->warranty_percent ?? '1') }}" min="1" max="5" step="0.01" @if($err('warranty_percent')) aria-invalid="true" @endif>
            @if($err('warranty_percent'))<span class="ci-error">{{ $err('warranty_percent') }}</span>@endif
        </div>
    </div>
</fieldset>
