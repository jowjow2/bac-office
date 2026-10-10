{{-- The Record purchase request form, shown as a dialog over the queue (ProcurementRequestController::adminCreate). --}}
@php
    /** @var \App\Models\ProcurementRequest $procurementRequest */
    $editing = false;
    // The admin records a request an end-user office handed in as a hard copy (ProcurementRequestController::adminCreate).
    $adminMode = true;
    $categories = [
        'goods' => ['Goods', 'Supplies, equipment, materials'],
        'services' => ['General support services', 'Janitorial, security, repairs'],
        'infrastructure' => ['Infrastructure', 'Civil works, buildings, roads'],
        'consultancy' => ['Consulting services', 'Studies, design, supervision'],
    ];
    $value = fn (string $field) => old($field, $procurementRequest->{$field});
    $invalid = fn (string $field) => $errors->has($field) ? 'true' : 'false';
    $steps = ['What to procure', 'Quantity, cost & schedule', 'Planning documents', 'Review & submit'];
@endphp

    @if($errors->any())
        <div class="ui-alert ui-alert--danger" role="alert" id="form-errors" tabindex="-1">
            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
            <div>
                <strong>Please correct {{ $errors->count() === 1 ? 'this field' : 'these fields' }}:</strong>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.requests.store') }}" enctype="multipart/form-data" class="ui-card" data-stepped data-validate>
        @csrf

        <div class="ui-card__head">
            <ol class="ui-steps" aria-label="Steps">
                @foreach($steps as $index => $label)
                    <li class="ui-steps__item" data-step-marker>
                        <button type="button" class="ui-steps__button" data-step-go="{{ $index }}">{{ $label }}</button>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="ui-card__body ui-form">
            {{-- Step 1 --}}
            <fieldset class="ui-fieldset ui-step-panel" data-step>
                <legend class="ui-fieldset__legend">What to procure</legend>
                <p class="ui-fieldset__desc">Fields marked <span class="ui-required" aria-hidden="true">*</span><span class="sr-only">with an asterisk</span> are required to submit.</p>
                <div class="ui-fields">
                    <div class="ui-field ui-field--wide">
                        <label class="ui-label" for="end_user_office">End-user office <span class="ui-required" aria-hidden="true">*</span></label>
                        <select id="end_user_office" name="end_user_office" class="ui-input" required aria-invalid="{{ $invalid('end_user_office') }}">
                            <option value="">Select the office that filed the request</option>
                            @foreach($offices as $office)
                                <option value="{{ $office }}" @selected(old('end_user_office') === $office)>{{ $office }}</option>
                            @endforeach
                        </select>
                        <span class="ui-hint">The office named on the signed hard copy.</span>
                        <span class="ui-error" id="end_user_office-error" data-client @unless($errors->has('end_user_office')) hidden @endunless>{{ $errors->first('end_user_office') }}</span>
                    </div>

                    <div class="ui-field ui-field--wide">
                        <label class="ui-label" for="title">Title <span class="ui-required" aria-hidden="true">*</span></label>
                        <input id="title" name="title" class="ui-input" maxlength="255" required value="{{ $value('title') }}" aria-invalid="{{ $invalid('title') }}" aria-describedby="title-hint title-error" placeholder="e.g. Supply and delivery of office equipment" data-review-label="Title">
                        <span class="ui-hint" id="title-hint">A short name the BAC will also use for the procurement.</span>
                        <span class="ui-error" id="title-error" data-client @unless($errors->has('title')) hidden @endunless>{{ $errors->first('title') }}</span>
                    </div>

                    <fieldset class="ui-field ui-field--wide ui-fieldset">
                        <legend class="ui-label">Procurement category <span class="ui-required" aria-hidden="true">*</span></legend>
                        <div class="ui-radio-cards ui-radio-cards--4">
                            @foreach($categories as $key => [$label, $hint])
                                <label class="ui-radio-card">
                                    <input type="radio" name="category" value="{{ $key }}" @checked($value('category') === $key) required data-review-label="Category" data-review-value="{{ $label }}">
                                    <strong>{{ $label }}</strong>
                                    <span>{{ $hint }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('category') <span class="ui-error">{{ $message }}</span> @enderror
                    </fieldset>

                    <div class="ui-field ui-field--wide">
                        <label class="ui-label" for="specifications">Technical specifications / Terms of Reference / scope of work <span class="ui-required" aria-hidden="true">*</span></label>
                        <textarea id="specifications" name="specifications" class="ui-input" rows="7" required maxlength="20000" aria-invalid="{{ $invalid('specifications') }}" aria-describedby="spec-hint specifications-error" data-review-label="Specifications / TOR">{{ $value('specifications') }}</textarea>
                        <span class="ui-hint" id="spec-hint">List each item with its specifications, or summarize the scope of work. Attach the full TOR in step 3 if you have one.</span>
                        <span class="ui-error" id="specifications-error" data-client @unless($errors->has('specifications')) hidden @endunless>{{ $errors->first('specifications') }}</span>
                    </div>

                    <div class="ui-field ui-field--wide">
                        <label class="ui-label" for="justification">Purpose <span class="ui-optional">(optional)</span></label>
                        <textarea id="justification" name="justification" class="ui-input" rows="3" maxlength="5000" data-review-label="Purpose">{{ $value('justification') }}</textarea>
                        @error('justification') <span class="ui-error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="ui-actions ui-actions--end ui-mt">
                    <button type="button" class="ui-btn ui-btn--primary" data-step-next>Next: quantity and cost <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
                </div>
            </fieldset>

            {{-- Step 2 --}}
            <fieldset class="ui-fieldset ui-step-panel" data-step>
                <legend class="ui-fieldset__legend">Quantity, cost and schedule</legend>
                <p class="ui-fieldset__desc">List each item to procure with its quantity and estimated unit cost. The estimated total cost helps the reviewer check the funds; the BAC sets the final Approved Budget for the Contract (ABC) and the mode of procurement.</p>
                @php
                    // Rows to show: what was just sent back, the saved items, or one empty row.
                    $formItems = old('items');
                    if (! is_array($formItems) || $formItems === []) {
                        $formItems = array_map(fn ($row) => [
                            'description' => $row['description'],
                            'quantity' => rtrim(rtrim(number_format($row['quantity'], 2, '.', ''), '0'), '.'),
                            'unit' => $row['unit'],
                            'unit_cost' => number_format($row['unit_cost'], 2),
                        ], $procurementRequest->itemRows());
                    }
                    $formItems = array_values($formItems ?: [['description' => '', 'quantity' => '', 'unit' => '', 'unit_cost' => '']]);
                    $itemsError = $errors->first('items') ?: collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'items.'))->flatten()->first();
                @endphp
                <div class="pr-items" data-items aria-describedby="items-error" data-review-label="Items" data-review-required>
                    <div class="pr-items__head" aria-hidden="true">
                        <span>Item description / specifications</span>
                        <span>Quantity</span>
                        <span>Unit</span>
                        <span>Est. unit cost (₱)</span>
                        <span class="is-num">Item total</span>
                        <span></span>
                    </div>
                    <div class="pr-items__rows" data-item-rows>
                        @foreach($formItems as $i => $row)
                            <div class="pr-items__row" data-item-row>
                                <label class="pr-items__cell pr-items__cell--desc">
                                    <span class="pr-items__label">Item description / specifications</span>
                                    <input name="items[{{ $i }}][description]" class="ui-input" maxlength="500" required value="{{ $row['description'] ?? '' }}" placeholder="e.g. Bond paper, A4, 80 gsm" data-item-field="description">
                                </label>
                                <label class="pr-items__cell">
                                    <span class="pr-items__label">Quantity</span>
                                    <input name="items[{{ $i }}][quantity]" type="number" min="0.01" step="0.01" inputmode="decimal" class="ui-input ui-num" required value="{{ $row['quantity'] ?? '' }}" placeholder="0" data-item-field="quantity">
                                </label>
                                <label class="pr-items__cell">
                                    <span class="pr-items__label">Unit</span>
                                    <input name="items[{{ $i }}][unit]" class="ui-input" maxlength="40" required list="unit-options" value="{{ $row['unit'] ?? '' }}" placeholder="piece" data-item-field="unit">
                                </label>
                                <label class="pr-items__cell">
                                    <span class="pr-items__label">Est. unit cost (₱)</span>
                                    <input name="items[{{ $i }}][unit_cost]" type="text" inputmode="decimal" autocomplete="off" pattern="\s*[0-9][0-9,]*(\.[0-9]{1,2})?\s*" title="Enter an amount in pesos, e.g. 1,250.00" class="ui-input ui-num" required value="{{ $row['unit_cost'] ?? '' }}" placeholder="0.00" data-item-field="unit_cost">
                                </label>
                                <div class="pr-items__cell pr-items__total">
                                    <span class="pr-items__label">Item total</span>
                                    <output class="ui-num" data-item-total>₱0.00</output>
                                </div>
                                <button type="button" class="pr-items__remove" data-item-remove aria-label="Remove this item"><i class="fas fa-trash-can" aria-hidden="true"></i></button>
                            </div>
                        @endforeach
                    </div>
                    <datalist id="unit-options">
                        @foreach(['piece', 'pcs', 'box', 'ream', 'set', 'lot', 'unit', 'pack', 'bottle', 'gallon', 'kg', 'meter', 'roll', 'month', 'job'] as $unit)
                            <option value="{{ $unit }}"></option>
                        @endforeach
                    </datalist>
                    <div class="pr-items__foot">
                        <button type="button" class="ui-btn ui-btn--secondary ui-btn--sm" data-item-add><i class="fas fa-plus" aria-hidden="true"></i> Add item</button>
                        <div class="pr-items__grand">
                            <span>Estimated total cost</span>
                            <strong class="ui-num" data-items-total>₱0.00</strong>
                        </div>
                    </div>
                    <span class="ui-hint">The total is the sum of quantity × estimated unit cost of every item. Base the unit costs on market research or the latest canvass.</span>
                    <span class="ui-error" id="items-error" role="alert" @unless($itemsError) hidden @endunless>{{ $itemsError }}</span>
                </div>
                <div class="ui-fields ui-mt">
                    <div class="ui-field">
                        <label class="ui-label" for="fund_source">Source of funds <span class="ui-required" aria-hidden="true">*</span></label>
                        <input id="fund_source" name="fund_source" class="ui-input" required maxlength="255" value="{{ $value('fund_source') }}" list="fund-options" aria-invalid="{{ $invalid('fund_source') }}" aria-describedby="fund_source-error" placeholder="e.g. General Fund" data-review-label="Source of funds">
                        <datalist id="fund-options">
                            @foreach(['General Fund', '20% Development Fund', 'Special Education Fund', 'Trust Fund', 'MDRRM Fund'] as $fund)
                                <option value="{{ $fund }}"></option>
                            @endforeach
                        </datalist>
                        <span class="ui-error" id="fund_source-error" data-client @unless($errors->has('fund_source')) hidden @endunless>{{ $errors->first('fund_source') }}</span>
                    </div>
                    <div class="ui-field ui-field--wide">
                        <label class="ui-label" for="delivery_period">Proposed delivery period or contract duration <span class="ui-required" aria-hidden="true">*</span></label>
                        <input id="delivery_period" name="delivery_period" class="ui-input" required maxlength="255" value="{{ $value('delivery_period') }}" aria-invalid="{{ $invalid('delivery_period') }}" aria-describedby="delivery_period-error" placeholder="e.g. 30 calendar days from receipt of the Notice to Proceed" data-review-label="Delivery / duration">
                        <span class="ui-error" id="delivery_period-error" data-client @unless($errors->has('delivery_period')) hidden @endunless>{{ $errors->first('delivery_period') }}</span>
                    </div>
                </div>
                <div class="ui-actions ui-actions--between ui-mt">
                    <button type="button" class="ui-btn ui-btn--secondary" data-step-prev><i class="fas fa-arrow-left" aria-hidden="true"></i> Back</button>
                    <span class="ui-actions">
                        <button type="button" class="ui-btn ui-btn--primary" data-step-next>Next: planning documents <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
                    </span>
                </div>
            </fieldset>

            {{-- Step 3 --}}
            <fieldset class="ui-fieldset ui-step-panel" data-step>
                <legend class="ui-fieldset__legend">Planning documents <span class="ui-optional">(optional)</span></legend>
                <p class="ui-fieldset__desc">Terms of reference, specifications, or the PPMP extract that covers this request. PDF, Word, Excel or images, up to 20 MB each.</p>

                @if($editing && $procurementRequest->documents->isNotEmpty())
                    <ul class="ui-files ui-mb">
                        @foreach($procurementRequest->documents as $document)
                            <li>
                                <i class="fas fa-file-lines" aria-hidden="true"></i>
                                <a class="ui-link" href="{{ route('procurement.files.request', $document) }}" target="_blank" rel="noopener">{{ $document->typeLabel() }}: {{ $document->original_name }}</a>
                                <button type="submit" form="remove-document-{{ $document->id }}" class="ui-btn ui-btn--danger ui-btn--sm ui-push-end" formnovalidate>Remove<span class="sr-only"> {{ $document->original_name }}</span></button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="ui-stack ui-stack--tight" data-attachment-rows>
                    <div class="ui-fields" data-attachment-row>
                        <div class="ui-field">
                            <label class="ui-label" for="document-type-0">Document type</label>
                            <select id="document-type-0" name="document_types[]" class="ui-input">
                                @foreach(\App\Models\ProcurementRequest::DOCUMENT_TYPES as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ui-field">
                            <label class="ui-label" for="document-file-0">File</label>
                            <input id="document-file-0" type="file" name="documents[]" class="ui-input" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" data-review-label="Attachment">
                        </div>
                    </div>
                </div>
                <button type="button" class="ui-btn ui-btn--ghost ui-btn--sm ui-mt-sm" data-add-attachment><i class="fas fa-plus" aria-hidden="true"></i> Add another file</button>
                @error('documents') <span class="ui-error">{{ $message }}</span> @enderror
                @error('documents.*') <span class="ui-error">{{ $message }}</span> @enderror

                <div class="ui-actions ui-actions--between ui-mt">
                    <button type="button" class="ui-btn ui-btn--secondary" data-step-prev><i class="fas fa-arrow-left" aria-hidden="true"></i> Back</button>
                    <span class="ui-actions">
                        <button type="button" class="ui-btn ui-btn--primary" data-step-next>Review request <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
                    </span>
                </div>
            </fieldset>

            {{-- Step 4 --}}
            <section class="ui-step-panel" data-step data-step-review aria-labelledby="review-title">
                <h2 class="ui-fieldset__legend" id="review-title">Review and submit</h2>
                <p class="ui-fieldset__desc">Check the details below. Recording sends the request to the Budget / Procurement Office for the PPMP/APP and funds check.</p>
                <dl class="ui-review" data-review></dl>
                <div class="ui-actions ui-actions--between ui-mt">
                    <button type="button" class="ui-btn ui-btn--secondary" data-step-prev><i class="fas fa-arrow-left" aria-hidden="true"></i> Back</button>
                    <span class="ui-actions">
                        <a href="{{ route('admin.requests') }}" class="ui-btn ui-btn--ghost">Cancel</a>
                        <button type="submit" name="action" value="submit" class="ui-btn ui-btn--primary"><i class="fas fa-paper-plane" aria-hidden="true"></i> Record request</button>
                    </span>
                </div>
            </section>
        </div>
    </form>

@push('head')
<style>
    .pr-items { display: grid; gap: 10px; min-width: 0; }
    .pr-items__head,
    .pr-items__row { display: grid; grid-template-columns: minmax(0, 3fr) minmax(76px, 0.8fr) minmax(90px, 0.9fr) minmax(120px, 1.2fr) minmax(110px, 1.1fr) 40px; gap: 8px; align-items: center; }
    .pr-items__head { padding: 0 2px; color: var(--ui-muted); font-size: 12px; font-weight: 600; }
    .pr-items__head .is-num, .pr-items__total { text-align: right; }
    .pr-items__rows { display: grid; gap: 8px; }
    .pr-items__cell { display: grid; gap: 4px; min-width: 0; margin: 0; }
    .pr-items__label { display: none; color: var(--ui-muted); font-size: 12px; font-weight: 600; }
    .pr-items__total output { color: var(--ui-ink); font-weight: 600; white-space: nowrap; }
    .pr-items__remove { display: grid; width: 40px; height: 40px; place-items: center; border: 1px solid var(--ui-line-strong); border-radius: var(--ui-radius); background: var(--ui-surface); color: var(--ui-muted); cursor: pointer; }
    .pr-items__remove:hover { border-color: var(--ui-danger); color: var(--ui-danger); }
    .pr-items__foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding-top: 10px; border-top: 1px solid var(--ui-line); }
    .pr-items__grand { display: flex; align-items: baseline; gap: 12px; }
    .pr-items__grand span { color: var(--ui-muted); font-size: 13px; font-weight: 600; }
    .pr-items__grand strong { color: var(--ui-ink); font-size: 20px; }

    /* Phones: each item becomes a small card. */
    @media (max-width: 760px) {
        .pr-items__head { display: none; }
        .pr-items__row { position: relative; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; padding: 12px; border: 1px solid var(--ui-line); border-radius: var(--ui-radius-lg); background: var(--ui-surface-2); }
        .pr-items__label { display: block; }
        .pr-items__cell--desc { grid-column: 1 / -1; }
        .pr-items__total { text-align: left; }
        /* Remove sits in the card corner, beside the description label. */
        .pr-items__remove { position: absolute; top: 6px; right: 6px; width: 36px; height: 36px; }
        .pr-items__cell--desc .pr-items__label { display: flex; align-items: center; min-height: 32px; padding-right: 40px; }
        .pr-items__grand { width: 100%; justify-content: space-between; }
    }
</style>
@endpush

@push('scripts')
<script>
    // Items: add/remove rows, item totals and the estimated total cost.
    (function () {
        const box = document.querySelector('[data-items]');
        if (!box) return;
        const rows = box.querySelector('[data-item-rows]');
        const error = document.getElementById('items-error');
        const peso = (amount) => '₱' + amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const number = (value) => {
            const parsed = Number(String(value).replace(/[,\s₱]/g, ''));
            return String(value).trim() !== '' && Number.isFinite(parsed) ? parsed : 0;
        };

        const recalc = () => {
            let grand = 0;
            const lines = [];
            rows.querySelectorAll('[data-item-row]').forEach((row) => {
                const field = (name) => row.querySelector('[data-item-field="' + name + '"]');
                const quantity = number(field('quantity').value);
                const cost = number(field('unit_cost').value);
                const total = Math.round(quantity * cost * 100) / 100;
                grand += total;
                row.querySelector('[data-item-total]').textContent = peso(total);
                const description = field('description').value.trim();
                if (description || quantity) {
                    lines.push((description || 'Item') + ' — ' + quantity.toLocaleString('en-PH') + ' ' + (field('unit').value.trim() || 'unit')
                        + ' × ' + peso(cost) + ' = ' + peso(total));
                }
            });
            box.querySelector('[data-items-total]').textContent = peso(grand);
            // Read by the Review step.
            box.dataset.reviewSummary = lines.length ? lines.join('\n') + '\nEstimated total cost: ' + peso(grand) : '';
        };

        const renumber = () => {
            rows.querySelectorAll('[data-item-row]').forEach((row, index) => {
                row.querySelectorAll('[data-item-field]').forEach((input) => {
                    input.name = 'items[' + index + '][' + input.dataset.itemField + ']';
                });
                row.querySelector('[data-item-remove]').setAttribute('aria-label', 'Remove item ' + (index + 1));
            });
        };

        // The unit follows what is being bought. The BAC can overwrite it: once someone types in the
        // Unit box it is left alone, until they clear it again.
        const unitRules = [
            [/\b(rental|subscription|janitorial|security (guard )?services?|internet|monthly)\b/, 'month'],
            [/\b(services?|repair|rehabilitation|construction|renovation|maintenance|installation|consult\w*|training|catering|printing|works|project)\b/, 'lot'],
            [/\b(bond paper|copy paper|xerox|photocopy paper|a4|short paper|long paper|ream)\b/, 'ream'],
            [/\b(cement)\b/, 'bag'],
            [/\b(rice)\b/, 'sack'],
            [/\b(gasoline|diesel|kerosene|lubricant|engine oil|liquid)\b/, 'liter'],
            [/\b(paint|thinner|varnish|primer|epoxy)\b/, 'gallon'],
            [/\b(alcohol|sanitizer|disinfectant|ink|vitamins?|syrup|shampoo|bleach|dishwashing)\b/, 'bottle'],
            [/\b(safety shoes|boots|shoes|slippers)\b/, 'pair'],
            [/\b(sand|gravel|aggregate|base course|filling material)\b/, 'cu.m'],
            [/\b(plywood|gi sheet|roofing sheet|marine board|acrylic sheet)\b/, 'sheet'],
            [/\b(lumber|coco lumber)\b/, 'bd.ft'],
            [/\b(fabric|cloth|curtain|canvas)\b/, 'meter'],
            [/\b(pvc pipe|gi pipe|hdpe pipe|pipe|conduit)\b/, 'length'],
            [/\b(sugar|coffee|salt|flour|nails|common nails|tie wire)\b/, 'kg'],
            [/\b(tape|carpet|thermal paper|electrical wire|cable|film)\b/, 'roll'],
            [/\b(ballpen|ball pen|pens?|folders?|envelopes?|staple wire|staples|paper clips?|markers?|gloves?|face masks?|masks?|syringes?|tablets? \d+\s?mg|capsules?|medicines?|tissue|diapers?)\b/, 'box'],
            [/\b(batter(y|ies)|napkins?|packs?)\b/, 'pack'],
            [/\b(first aid kit|kit|ppe|set of|set)\b/, 'set'],
            [/\b(monitor|oximeter|thermometer|laptops?|computers?|desktops?|printers?|scanner|aircon|air conditioner|television|tv|camera|cellphone|smartphone|projector|generator|vehicle|motorcycle|ambulance|truck|equipment|machine|apparatus|ups|router|server|nebulizer|stethoscope|wheelchair)\b/, 'unit'],
        ];
        const guessUnit = (description) => {
            const text = description.toLowerCase();
            for (const [pattern, unit] of unitRules) {
                if (pattern.test(text)) return unit;
            }
            return text.replace(/[^a-z]/g, '').length >= 4 ? 'piece' : '';
        };
        const unitTimers = new WeakMap();
        const suggestUnit = (row) => {
            const unit = row.querySelector('[data-item-field="unit"]');
            const description = row.querySelector('[data-item-field="description"]');
            if (!unit || !description || (unit.value !== '' && unit.dataset.auto !== '1')) return;
            const guess = guessUnit(description.value.trim());
            if (guess === '') { if (unit.dataset.auto === '1') { unit.value = ''; delete unit.dataset.auto; } return; }
            unit.value = guess;
            unit.dataset.auto = '1';
            unit.removeAttribute('aria-invalid');
            recalc();
        };

        box.addEventListener('input', (event) => {
            if (event.target.matches('[data-item-field]')) {
                recalc();
                if (error && !error.hidden) error.hidden = true;
            }
            const row = event.target.closest('[data-item-row]');
            if (event.target.matches('[data-item-field="unit"]')) {
                // Typing here takes the unit over; clearing it hands it back.
                if (event.isTrusted) delete event.target.dataset.auto;
            } else if (row && event.target.matches('[data-item-field="description"]')) {
                window.clearTimeout(unitTimers.get(row));
                unitTimers.set(row, window.setTimeout(() => suggestUnit(row), 450));
            }
        });
        box.addEventListener('change', (event) => {
            // Picking a unit from the list also counts as the person's own choice.
            if (event.target.matches('[data-item-field="unit"]') && event.isTrusted) delete event.target.dataset.auto;
        });
        box.addEventListener('focusout', (event) => {
            const input = event.target;
            if (input.matches('[data-item-field="unit_cost"]') && input.value.trim() !== '') {
                const value = number(input.value);
                if (value || input.value.trim() === '0') input.value = value.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        });
        box.addEventListener('click', (event) => {
            if (event.target.closest('[data-item-add]')) {
                if (rows.children.length >= 100) return;
                const row = rows.firstElementChild.cloneNode(true);
                row.querySelectorAll('[data-item-field]').forEach((input) => {
                    input.value = '';
                    delete input.dataset.auto;
                    input.removeAttribute('aria-invalid');
                });
                rows.appendChild(row);
                renumber();
                recalc();
                row.querySelector('[data-item-field="description"]').focus();
                return;
            }
            const remove = event.target.closest('[data-item-remove]');
            if (remove) {
                const row = remove.closest('[data-item-row]');
                if (rows.children.length > 1) {
                    row.remove();
                } else {
                    row.querySelectorAll('[data-item-field]').forEach((input) => { input.value = ''; });
                }
                renumber();
                recalc();
            }
        });

        renumber();
        recalc();
    })();

    (function () {
        const errors = document.getElementById('form-errors');
        if (errors) errors.focus();

        const rows = document.querySelector('[data-attachment-rows]');
        const add = document.querySelector('[data-add-attachment]');
        if (!rows || !add) return;

        let index = 1;
        add.addEventListener('click', function () {
            if (rows.children.length >= 10) return;
            const row = rows.firstElementChild.cloneNode(true);
            row.querySelectorAll('select, input').forEach(function (field) {
                const base = field.id.replace(/-\d+$/, '');
                const label = row.querySelector('label[for="' + field.id + '"]');
                field.id = base + '-' + index;
                if (field.type === 'file') field.value = '';
                if (label) label.htmlFor = field.id;
            });
            rows.appendChild(row);
            row.querySelector('select').focus();
            index++;
        });
    })();
</script>
@endpush


@push('head')
<style>
    .eu-modal { position: fixed; inset: 0; z-index: 80; display: grid; place-items: center; padding: 20px; background: rgba(15, 25, 21, .55); backdrop-filter: blur(2px); animation: eu-modal-fade .2s ease both; }
    .eu-modal__card { display: grid; grid-template-rows: auto minmax(0, 1fr); width: min(960px, 100%); max-height: min(920px, calc(100dvh - 40px)); overflow: hidden; border-radius: 16px; background: var(--ui-surface); box-shadow: 0 24px 70px rgba(0, 0, 0, .32); animation: eu-modal-rise .28s cubic-bezier(.2, .8, .2, 1) both; }
    .eu-modal__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 18px 22px 14px; border-bottom: 1px solid var(--ui-line); }
    .eu-modal__head h2 { margin: 0; color: var(--ui-ink); font-size: 18px; font-weight: 700; letter-spacing: -.01em; }
    .eu-modal__head p { margin: 3px 0 0; color: var(--ui-muted); font-size: 13px; line-height: 1.45; }
    .eu-modal__close { display: grid; flex: 0 0 34px; width: 34px; height: 34px; place-items: center; border: 1px solid var(--ui-line); border-radius: 9px; color: var(--ui-muted); text-decoration: none; }
    .eu-modal__close:hover { background: var(--ui-surface-2); color: var(--ui-ink); }
    .eu-modal__body { min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
    .eu-modal__body > .ui-alert { margin: 14px 22px 0; }
    .eu-modal__body > form.ui-card { border: 0; border-radius: 0; box-shadow: none; }
    body.eu-modal-open { overflow: hidden; }
    @keyframes eu-modal-fade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes eu-modal-rise { from { opacity: 0; transform: translateY(14px) scale(.985); } to { opacity: 1; transform: none; } }
    @media (max-width: 760px) {
        .eu-modal { padding: 0; align-items: end; }
        .eu-modal__card { width: 100%; max-height: 94dvh; border-radius: 16px 16px 0 0; }
        .eu-modal__head { padding: 14px 16px 12px; }
    }
    @media (prefers-reduced-motion: reduce) { .eu-modal, .eu-modal__card { animation: none; } }
</style>
@endpush

@push('scripts')
<script>
    // Esc, the X and the backdrop go back to the queue; a form with typed-in details asks first.
    (function () {
        const modal = document.querySelector('[data-eu-modal]');
        if (!modal) return;
        const form = modal.querySelector('form');
        const closeUrl = modal.dataset.closeUrl;
        document.body.classList.add('eu-modal-open');

        const dirty = () => Array.from(form.querySelectorAll('input:not([type=hidden]):not([type=file]):not([type=radio]), textarea, select'))
            .some((field) => field.value !== field.defaultValue && field.value !== '' && !(field.tagName === 'SELECT' && field.selectedIndex === 0));
        const leave = async () => {
            if (dirty() && window.bacConfirm) {
                const yes = await window.bacConfirm({ title: 'Discard this request?', message: 'What you typed has not been recorded.', confirmLabel: 'Discard', tone: 'danger' });
                if (!yes) return;
            }
            window.location.href = closeUrl;
        };

        modal.addEventListener('click', (event) => {
            if (event.target === modal || event.target.closest('[data-eu-modal-close]')) { event.preventDefault(); leave(); }
        });
        // Esc acts on key-up, and only when the key went down with no confirmation open.
        let escArmed = false;
        const confirmOpen = () => Boolean(document.querySelector('.bac-confirm'));
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') escArmed = !confirmOpen(); });
        document.addEventListener('keyup', (event) => { if (event.key === 'Escape' && escArmed) { escArmed = false; leave(); } });
        const first = form.querySelector('select[name="end_user_office"]');
        if (first && window.matchMedia('(hover: hover) and (pointer: fine)').matches) first.focus({ preventScroll: true });
    })();
</script>
@endpush