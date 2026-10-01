@extends('layouts.portal')

@php
    /** @var \App\Models\ProcurementRequest $procurementRequest */
    $editing = $procurementRequest->exists;
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

@section('title', $editing ? 'Edit purchase request' : 'New purchase request')
@section('crumbs')
    <a href="{{ route('end-user.requests.index') }}">My purchase requests</a>
    <span aria-hidden="true">/</span>
    <span class="ui-mono">{{ $editing ? $procurementRequest->reference_no : 'New' }}</span>
@endsection
@section('subtitle', auth()->user()->office.' · After you submit, the Budget / Procurement Office checks the request against the PPMP/APP and available funds before it goes to the BAC.')

@section('content')
    @if($editing && $procurementRequest->status === 'returned' && $procurementRequest->review_remarks)
        <div class="ui-alert ui-alert--warning" role="alert">
            <i class="fas fa-rotate-left" aria-hidden="true"></i>
            <div><strong>Returned for correction.</strong> {{ $procurementRequest->review_remarks }}</div>
        </div>
    @endif

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

    <form method="POST" action="{{ $editing ? route('end-user.requests.update', $procurementRequest) : route('end-user.requests.store') }}" enctype="multipart/form-data" class="ui-card" data-stepped data-validate>
        @csrf
        @if($editing) @method('PUT') @endif

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
                <p class="ui-fieldset__desc">Fields marked <span class="ui-required" aria-hidden="true">*</span><span class="sr-only">with an asterisk</span> are required to submit. You can save a draft with only the title and finish it later.</p>
                <div class="ui-fields">
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
                    <button type="submit" name="action" value="draft" class="ui-btn ui-btn--ghost" formnovalidate>Save draft</button>
                    <button type="button" class="ui-btn ui-btn--primary" data-step-next>Next: quantity and cost <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
                </div>
            </fieldset>

            {{-- Step 2 --}}
            <fieldset class="ui-fieldset ui-step-panel" data-step>
                <legend class="ui-fieldset__legend">Quantity, cost and schedule</legend>
                <p class="ui-fieldset__desc">The estimated total cost helps the reviewer check the funds. The BAC sets the final Approved Budget for the Contract (ABC) and the mode of procurement.</p>
                <div class="ui-fields">
                    <div class="ui-field">
                        <label class="ui-label" for="quantity">Quantity <span class="ui-required" aria-hidden="true">*</span></label>
                        <input id="quantity" name="quantity" type="number" min="0.01" step="0.01" inputmode="decimal" class="ui-input" required value="{{ $value('quantity') !== null ? rtrim(rtrim(number_format((float) $value('quantity'), 2, '.', ''), '0'), '.') : '' }}" aria-invalid="{{ $invalid('quantity') }}" aria-describedby="quantity-error" data-review-label="Quantity">
                        <span class="ui-error" id="quantity-error" data-client @unless($errors->has('quantity')) hidden @endunless>{{ $errors->first('quantity') }}</span>
                    </div>
                    <div class="ui-field">
                        <label class="ui-label" for="unit">Unit <span class="ui-required" aria-hidden="true">*</span></label>
                        <input id="unit" name="unit" class="ui-input" required maxlength="40" value="{{ $value('unit') }}" list="unit-options" aria-invalid="{{ $invalid('unit') }}" aria-describedby="unit-error" placeholder="e.g. lot, pcs, units" data-review-label="Unit">
                        <datalist id="unit-options">
                            @foreach(['lot', 'pcs', 'units', 'sets', 'boxes', 'reams', 'months', 'job'] as $unit)
                                <option value="{{ $unit }}"></option>
                            @endforeach
                        </datalist>
                        <span class="ui-error" id="unit-error" data-client @unless($errors->has('unit')) hidden @endunless>{{ $errors->first('unit') }}</span>
                    </div>
                    <div class="ui-field">
                        <label class="ui-label" for="estimated_cost">Estimated total cost <span class="ui-required" aria-hidden="true">*</span></label>
                        <div class="ui-input-group">
                            <span class="ui-input-group__prefix" aria-hidden="true">₱</span>
                            <input id="estimated_cost" name="estimated_cost" type="text" inputmode="decimal" autocomplete="off" pattern="\s*[0-9][0-9,]*(\.[0-9]{1,2})?\s*" title="Enter an amount in pesos, e.g. 500,000.00" class="ui-input ui-num" required value="{{ filled($value('estimated_cost')) && is_numeric(str_replace(',', '', (string) $value('estimated_cost'))) ? number_format((float) str_replace(',', '', (string) $value('estimated_cost')), 2) : $value('estimated_cost') }}" aria-invalid="{{ $invalid('estimated_cost') }}" aria-describedby="cost-hint cost-summary estimated_cost-error" placeholder="0.00" data-money data-money-quantity="#quantity" data-money-unit="#unit" data-money-summary="#cost-summary" data-review-label="Estimated total cost" data-review-format="money">
                        </div>
                        <span class="ui-hint" id="cost-hint">The total for the whole quantity, not the price of one unit. Base it on market research or the latest canvass.</span>
                        <span class="ui-money-summary" id="cost-summary" aria-live="polite" hidden></span>
                        <span class="ui-error" id="estimated_cost-error" data-client @unless($errors->has('estimated_cost')) hidden @endunless>{{ $errors->first('estimated_cost') }}</span>
                    </div>
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
                        <button type="submit" name="action" value="draft" class="ui-btn ui-btn--ghost" formnovalidate>Save draft</button>
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
                        <button type="submit" name="action" value="draft" class="ui-btn ui-btn--ghost" formnovalidate>Save draft</button>
                        <button type="button" class="ui-btn ui-btn--primary" data-step-next>Review request <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
                    </span>
                </div>
            </fieldset>

            {{-- Step 4 --}}
            <section class="ui-step-panel" data-step data-step-review aria-labelledby="review-title">
                <h2 class="ui-fieldset__legend" id="review-title">Review and submit</h2>
                <p class="ui-fieldset__desc">Check the details below. Submitting sends the request to the Budget / Procurement Office for the PPMP/APP and funds check; you can no longer edit it unless it is returned.</p>
                <dl class="ui-review" data-review></dl>
                <div class="ui-actions ui-actions--between ui-mt">
                    <button type="button" class="ui-btn ui-btn--secondary" data-step-prev><i class="fas fa-arrow-left" aria-hidden="true"></i> Back</button>
                    <span class="ui-actions">
                        <a href="{{ $editing ? route('end-user.requests.show', $procurementRequest) : route('end-user.requests.index') }}" class="ui-btn ui-btn--ghost">Cancel</a>
                        <button type="submit" name="action" value="draft" class="ui-btn ui-btn--secondary" formnovalidate>Save as draft</button>
                        <button type="submit" name="action" value="submit" class="ui-btn ui-btn--primary"><i class="fas fa-paper-plane" aria-hidden="true"></i> Submit for review</button>
                    </span>
                </div>
            </section>
        </div>
    </form>

    @if($editing)
        @foreach($procurementRequest->documents as $document)
            <form id="remove-document-{{ $document->id }}" method="POST" action="{{ route('end-user.requests.documents.destroy', [$procurementRequest, $document]) }}" hidden onsubmit="return confirm('Remove this attachment?');">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    @endif
@endsection

@push('scripts')
<script>
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
