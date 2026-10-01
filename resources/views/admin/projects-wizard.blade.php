{{--
    Create procurement project â€” five-step wizard (Project info, Requirements,
    Documents, Dates, Review). Posts to admin.projects.wizard.store with the
    same field names as before: status=draft saves a draft project; status=open
    asks the server to publish it in this BAC system (it stays a draft, with the
    reasons, when a publication check fails). Behaviour: resources/js/project-wizard.js.
--}}
@extends('layouts.portal')

@use('App\Support\ProcurementMode')

@php
    /** @var \App\Models\ProcurementRequest|null $procurementRequest */
    $pr = $procurementRequest ?? null;
    $err = fn (string $field) => $errors->first($field);
    $invalid = fn (string $field) => $errors->has($field) ? 'true' : 'false';
    $tz = config('bac-office.display_timezone');

    $categories = ['goods' => 'Goods', 'services' => 'Goods â€” general support services', 'infrastructure' => 'Infrastructure', 'consultancy' => 'Consulting services'];

    $sourceOfFundOptions = ['General Fund', 'Special Education Fund (SEF)', '20% Development Fund', 'Local Disaster Risk Reduction and Management Fund (LDRRMF)', 'Trust Fund', 'National Government Grant / Transfer', 'Loan / Grant', 'Other'];
    $sourceOfFund = old('source_of_fund');
    $sourceOfFundChoice = in_array($sourceOfFund, $sourceOfFundOptions, true) ? $sourceOfFund : (filled($sourceOfFund) ? 'Other' : '');
    $sourceOfFundOther = $sourceOfFundChoice === 'Other' && ! in_array($sourceOfFund, $sourceOfFundOptions, true) ? $sourceOfFund : '';

    $durationOptions = ['30 Calendar Days', '60 Calendar Days', '90 Calendar Days', '120 Calendar Days', '180 Calendar Days', '1 Year', 'Custom'];
    $duration = old('contract_duration');
    $durationChoice = in_array($duration, $durationOptions, true) ? $duration : (filled($duration) ? 'Custom' : '');
    $durationCustom = $durationChoice === 'Custom' && ! in_array($duration, $durationOptions, true) ? $duration : '';

    $budget = old('budget');
    $budgetDisplay = filled($budget) && is_numeric($budget) ? number_format((float) $budget, 2, '.', ',') : '';
    $fee = old('bidding_documents_fee');
    $feeDisplay = filled($fee) && is_numeric($fee) ? number_format((float) $fee, 2, '.', ',') : '';
    $feeMode = old('bidding_fee_mode', \App\Support\BiddingDocumentsFee::MODE_SCHEDULE);

    // Required documents: the standard checklist, grouped; anything else the BAC typed is an extra row.
    // No legal and eligibility group: the bidder's PhilGEPS Platinum certificate stands for its permits,
    // registration and tax clearance (RA 12009 IRR Sec. 52.1, 54.2), which are verified at registration
    // and post-qualification (Sec. 63.2); the per-bid forms are added from the mode and category.
    $requirementGroups = [
        'Technical' => ['Technical Proposal'],
        'Financial' => ['Financial Proposal'],
        'Other' => ['Other BAC Required Documents'],
    ];
    $standardRequirements = collect($requirementGroups)->flatten()->all();
    $selectedRequirements = (array) old('required_documents', []);
    $extraRequirements = array_values(array_filter($selectedRequirements, fn ($item) => filled($item) && ! in_array($item, $standardRequirements, true)));

    $documentTypes = [
        'invitation_to_bid' => 'Invitation to Bid',
        'bidding_documents' => 'Bidding Documents',
        'terms_of_reference' => 'Terms of Reference',
        'technical_specifications' => 'Technical Specifications',
        'bill_of_quantities' => 'Bill of Quantities',
        'project_plans' => 'Project Plans / Drawings',
        'supplemental_bulletin' => 'Supplemental Bid Bulletin',
        'other' => 'Other',
    ];

    $steps = [1 => 'Project info', 2 => 'Requirements', 3 => 'Documents', 4 => 'Dates', 5 => 'Review'];

    // Which step holds each field, to reopen the right step after a server error.
    $fieldSteps = [
        1 => ['title', 'description', 'category', 'location', 'procurement_mode', 'award_criterion', 'evaluation_procedure', 'negotiation_ground', 'legal_basis', 'end_user_unit', 'source_of_fund', 'contract_duration', 'budget', 'philgeps_reference_no', 'philgeps_url', 'procurement_request_id'],
        2 => ['required_documents', 'eligibility_requirements', 'technical_requirements', 'financial_requirements', 'qualification_notes', 'special_instructions', 'evaluation_criteria', 'quality_price_ratio', 'submission_mode', 'submission_venue', 'bidding_documents_fee', 'bidding_fee_mode', 'bidding_fee_reason', 'payment_venue', 'bid_security_required', 'bid_security_notes', 'electronic_submission_authority'],
        3 => ['project_documents', 'document_type'],
        4 => ['pre_procurement_conference_at', 'pre_procurement_reference', 'date_posted', 'pre_bid_conference_date', 'clarification_deadline', 'bid_submission_deadline', 'bid_opening_date', 'bid_opening_venue', 'evaluation_start_date', 'expected_award_date'],
        5 => ['confirm_correct', 'status', 'error'],
    ];
    $firstErrorStep = 1;
    if ($errors->any()) {
        foreach ($fieldSteps as $step => $fields) {
            foreach (array_keys($errors->getMessages()) as $key) {
                if (in_array(explode('.', $key)[0], $fields, true)) {
                    $firstErrorStep = $step;
                    break 2;
                }
            }
        }
    }

    $today = now($tz);
@endphp

@section('title', 'Create procurement project')
@section('subtitle', 'Prepare the project, then save it as a draft or publish it in this BAC system.')

@push('head')
    @vite('resources/css/project-wizard.css')
@endpush

@section('content')
<div class="pw-backdrop">
    <div class="pw" role="dialog" aria-modal="true" aria-labelledby="pw-title" data-pw data-first-step="{{ $firstErrorStep }}">
        <header class="pw__header">
            <div class="pw__heading">
                <div>
                    <h2 class="pw__title" id="pw-title">Create procurement project</h2>
                    <p class="pw__subtitle">Fields marked <span class="ui-required" aria-hidden="true">*</span><span class="sr-only">with an asterisk</span> are needed to publish. A draft can be saved at any step.</p>
                </div>
                <a href="{{ route('admin.projects') }}" class="pw__close" data-pw-exit aria-label="Close and return to projects"><i class="fas fa-xmark" aria-hidden="true"></i></a>
            </div>

            <ol class="pw-steps" aria-label="Steps">
                @foreach($steps as $number => $label)
                    <li class="pw-steps__item" data-pw-marker="{{ $number }}">
                        <button type="button" class="pw-steps__button" data-pw-go="{{ $number }}">
                            <span class="pw-steps__number" aria-hidden="true">{{ $number }}</span>
                            <span class="pw-steps__label">{{ $label }}</span>
                            <span class="sr-only" data-pw-marker-state></span>
                        </button>
                    </li>
                @endforeach
            </ol>
        </header>

        <form id="projectWizardForm" class="pw__form" action="{{ route('admin.projects.wizard.store') }}" method="POST" enctype="multipart/form-data" novalidate>
            @csrf
            <input type="hidden" name="status" id="projectStatus" value="draft">
            @if(old('procurement_request_id') || $pr)
                <input type="hidden" name="procurement_request_id" value="{{ old('procurement_request_id', $pr?->id) }}">
            @endif

            <div class="pw__body" data-pw-body tabindex="-1">
                @if($errors->any())
                    <div class="ui-alert ui-alert--danger pw-summary" role="alert" data-pw-server-errors>
                        <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                        <div>
                            <strong>The project was not saved.</strong> Correct the fields below, then try again. Files you had selected are not kept after a failed save; attach them again in Documents.
                            <ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                        </div>
                    </div>
                @endif

                {{-- ============================== Step 1 ============================== --}}
                <section class="pw-step" data-pw-step="1" aria-labelledby="pw-step1-title">
                    <h3 class="pw-step__title" id="pw-step1-title">Project info</h3>

                    @if($pr)
                        <div class="pw-pr" aria-label="Linked purchase request">
                            <div class="pw-pr__main">
                                <span class="pw-pr__eyebrow">Linked purchase request</span>
                                <strong class="pw-pr__ref ui-mono">{{ $pr->reference_no }}</strong>
                                <span class="pw-pr__office">{{ $pr->end_user_office }}</span>
                            </div>
                            <dl class="pw-pr__facts">
                                <div><dt>Status</dt><dd><span class="ui-badge ui-badge--{{ $pr->statusTone() }}">{{ $pr->statusLabel() }}</span></dd></div>
                                <div><dt>Estimated cost</dt><dd class="ui-num">â‚±{{ number_format((float) $pr->estimated_cost, 2) }}</dd></div>
                                <div><dt>PPMP / APP</dt><dd>{{ $pr->ppmp_reference ?: 'â€”' }} / {{ $pr->app_reference ?: 'â€”' }}</dd></div>
                            </dl>
                            <p class="pw-pr__note">Preparing bidding from {{ $pr->reference_no }}. Fields marked <span class="pw-chip">From PR</span> were copied from the request; changing them here does not change the request. <a class="ui-link" href="{{ route('admin.requests', ['tab' => 'bac', 'q' => $pr->reference_no]) }}">Open the request</a></p>
                        </div>
                    @endif

                    <fieldset class="pw-section">
                        <legend class="pw-section__title">Project</legend>
                        <div class="pw-grid">
                            <div class="ui-field ui-field--wide">
                                <label class="ui-label" for="title">Project title <span class="ui-required" aria-hidden="true">*</span> @if($pr)<span class="pw-chip">From PR</span>@endif</label>
                                <input type="text" id="title" name="title" class="ui-input" value="{{ old('title') }}" maxlength="255" required data-review-label="Title" aria-invalid="{{ $invalid('title') }}" aria-describedby="title-error">
                                <span class="ui-error" id="title-error" data-pw-error @unless($err('title')) hidden @endunless>{{ $err('title') }}</span>
                            </div>
                            <div class="ui-field ui-field--wide">
                                <label class="ui-label" for="description">Description <span class="ui-required" aria-hidden="true">*</span> @if($pr)<span class="pw-chip">From PR</span>@endif</label>
                                <textarea id="description" name="description" class="ui-input" rows="4" required aria-invalid="{{ $invalid('description') }}" aria-describedby="description-hint description-error">{{ old('description') }}</textarea>
                                <span class="ui-hint" id="description-hint">Scope, quantities and key specifications. <span data-pw-count="description">0</span> characters.</span>
                                <span class="ui-error" id="description-error" data-pw-error @unless($err('description')) hidden @endunless>{{ $err('description') }}</span>
                            </div>
                            <div class="ui-field">
                                <label class="ui-label" for="category">Category <span class="ui-required" aria-hidden="true">*</span> @if($pr)<span class="pw-chip">From PR</span>@endif</label>
                                <select id="category" name="category" class="ui-input" required aria-invalid="{{ $invalid('category') }}" aria-describedby="category-error">
                                    <option value="">Select a category</option>
                                    @foreach($categories as $value => $label)
                                        <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <span class="ui-error" id="category-error" data-pw-error @unless($err('category')) hidden @endunless>{{ $err('category') }}</span>
                            </div>
                            <div class="ui-field">
                                <label class="ui-label" for="location">Location <span class="ui-required" aria-hidden="true">*</span></label>
                                <input type="text" id="location" name="location" class="ui-input" value="{{ old('location') }}" maxlength="255" placeholder="e.g. Brgy. Bubog, San Jose" required aria-invalid="{{ $invalid('location') }}" aria-describedby="location-error">
                                <span class="ui-error" id="location-error" data-pw-error @unless($err('location')) hidden @endunless>{{ $err('location') }}</span>
                            </div>
                            <div class="ui-field ui-field--wide">
                                <label class="ui-label" for="end_user_unit">End-user office @if($pr)<span class="pw-chip">From PR</span>@endif</label>
                                <input type="text" id="end_user_unit" name="end_user_unit" class="ui-input" value="{{ old('end_user_unit') }}" maxlength="255" placeholder="e.g. Municipal Engineering Office" aria-invalid="{{ $invalid('end_user_unit') }}" aria-describedby="end_user_unit-error">
                                <span class="ui-error" id="end_user_unit-error" data-pw-error @unless($err('end_user_unit')) hidden @endunless>{{ $err('end_user_unit') }}</span>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="pw-section">
                        <legend class="pw-section__title">Procurement method</legend>
                        <div class="pw-grid">
                            <div class="ui-field">
                                <label class="ui-label" for="legal_basis">Legal basis</label>
                                <select id="legal_basis" name="legal_basis" class="ui-input" aria-describedby="legal_basis-hint legal_basis-error">
                                    @foreach(\App\Models\Project::LEGAL_BASES as $key => $label)
                                        <option value="{{ $key }}" @selected(old('legal_basis', 'ra_12009') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <span class="ui-hint" id="legal_basis-hint">Keep RA 9184 only for procurement started under it.</span>
                                <span class="ui-error" id="legal_basis-error" data-pw-error @unless($err('legal_basis')) hidden @endunless>{{ $err('legal_basis') }}</span>
                            </div>
                            <div class="ui-field">
                                <label class="ui-label" for="procurement_mode">Mode of procurement <span class="ui-required" aria-hidden="true">*</span></label>
                                <select id="procurement_mode" name="procurement_mode" class="ui-input" required aria-invalid="{{ $invalid('procurement_mode') }}" aria-describedby="procurement_mode-error">
                                    <option value="">Select a mode</option>
                                    @foreach(ProcurementMode::MODES as $modeKey => $mode)
                                        @continue($mode['legacy'] ?? false)
                                        <option value="{{ $modeKey }}" data-family="{{ $mode['family'] }}" data-bases="{{ implode(' ', $mode['bases']) }}" @selected(old('procurement_mode') === $modeKey)>{{ $mode['label'] }}</option>
                                    @endforeach
                                </select>
                                <span class="ui-error" id="procurement_mode-error" data-pw-error @unless($err('procurement_mode')) hidden @endunless>{{ $err('procurement_mode') }}</span>
                            </div>
                            <div class="ui-field ui-field--wide">
                                <div class="pw-grid">
                                    <div class="ui-field" data-pw-award>
                                        <label class="ui-label" for="award_criterion">Award criterion <span class="ui-required" aria-hidden="true">*</span></label>
                                        <select id="award_criterion" name="award_criterion" class="ui-input" data-pw-criterion data-old="{{ old('award_criterion') }}" aria-describedby="award_criterion-hint award_criterion-error">
                                            <option value="">Select the criterion in the bidding documents</option>
                                            @foreach(\App\Models\Project::AWARD_CRITERIA as $criterionKey => $criterionLabel)
                                                <option value="{{ $criterionKey }}" @selected(old('award_criterion') === $criterionKey)>{{ $criterionLabel }}</option>
                                            @endforeach
                                        </select>
                                        <span class="ui-hint" id="award_criterion-hint">Stated in the Invitation to Bid (IRR Sec. 50.2(d)). It never selects a winner automatically.</span>
                                        <span class="ui-error" id="award_criterion-error" data-pw-error @unless($err('award_criterion')) hidden @endunless>{{ $err('award_criterion') }}</span>
                                    </div>
                                    <div class="ui-field" data-pw-consulting hidden>
                                        <label class="ui-label" for="evaluation_procedure">Evaluation procedure <span class="ui-required" aria-hidden="true">*</span></label>
                                        <select id="evaluation_procedure" name="evaluation_procedure" class="ui-input" aria-describedby="evaluation_procedure-hint evaluation_procedure-error">
                                            <option value="">Select QBE or QCBE</option>
                                            @foreach(\App\Models\Project::EVALUATION_PROCEDURES as $procedureKey => $procedureLabel)
                                                <option value="{{ $procedureKey }}" @selected(old('evaluation_procedure') === $procedureKey)>{{ $procedureLabel }}</option>
                                            @endforeach
                                        </select>
                                        <span class="ui-hint" id="evaluation_procedure-hint">Consulting services (IRR Sec. 50.2(g)).</span>
                                        <span class="ui-error" id="evaluation_procedure-error" data-pw-error @unless($err('evaluation_procedure')) hidden @endunless>{{ $err('evaluation_procedure') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="ui-field ui-field--wide" data-pw-negotiation hidden>
                                <label class="ui-label" for="negotiation_ground">Ground for Negotiated Procurement <span class="ui-required" aria-hidden="true">*</span></label>
                                <select id="negotiation_ground" name="negotiation_ground" class="ui-input" aria-describedby="negotiation_ground-hint negotiation_ground-error">
                                    <option value="">Select the ground</option>
                                    @foreach(ProcurementMode::NEGOTIATION_GROUNDS as $groundKey => $groundLabel)
                                        <option value="{{ $groundKey }}" @selected(old('negotiation_ground') === $groundKey)>{{ $groundLabel }}</option>
                                    @endforeach
                                </select>
                                <span class="ui-hint" id="negotiation_ground-hint">After two failed biddings the invitation is posted for 3 calendar days; emergency cases are not posted.</span>
                                <span class="ui-error" id="negotiation_ground-error" data-pw-error @unless($err('negotiation_ground')) hidden @endunless>{{ $err('negotiation_ground') }}</span>
                            </div>
                            <p class="pw-note ui-field--wide" data-pw-mode-rule aria-live="polite">Competitive bidding and the alternative modes follow different steps and dates.</p>
                        </div>
                    </fieldset>

                    <fieldset class="pw-section">
                        <legend class="pw-section__title">Budget and funding</legend>
                        <div class="pw-grid">
                            <div class="ui-field">
                                <label class="ui-label" for="budget_display">Approved Budget for the Contract (ABC) <span class="ui-required" aria-hidden="true">*</span> @if($pr)<span class="pw-chip">From PR</span>@endif</label>
                                <div class="ui-input-group">
                                    <span class="ui-input-group__prefix" aria-hidden="true">â‚±</span>
                                    <input type="text" id="budget_display" class="ui-input ui-num" value="{{ $budgetDisplay }}" inputmode="decimal" autocomplete="off" placeholder="0.00" required data-pw-money="budget" aria-invalid="{{ $invalid('budget') }}" aria-describedby="budget-hint budget-error">
                                </div>
                                <input type="hidden" id="budget" name="budget" value="{{ $budget }}">
                                <span class="ui-hint" id="budget-hint">@if($pr)The request estimated â‚±{{ number_format((float) $pr->estimated_cost, 2) }}; set the final ABC.@else The ceiling of the contract price.@endif</span>
                                <span class="ui-error" id="budget-error" data-pw-error @unless($err('budget')) hidden @endunless>{{ $err('budget') }}</span>
                            </div>
                            <div class="ui-field" data-pw-choice="source_of_fund" data-pw-choice-other="Other">
                                <label class="ui-label" for="source_of_fund_choice">Source of funds <span class="ui-required" aria-hidden="true">*</span> @if($pr)<span class="pw-chip">From PR</span>@endif</label>
                                <input type="hidden" id="source_of_fund" name="source_of_fund" value="{{ $sourceOfFund }}" data-pw-choice-value>
                                <select id="source_of_fund_choice" class="ui-input" required data-pw-choice-select aria-invalid="{{ $invalid('source_of_fund') }}" aria-describedby="source_of_fund-error">
                                    <option value="">Select the source of funds</option>
                                    @foreach($sourceOfFundOptions as $option)
                                        <option value="{{ $option }}" @selected($sourceOfFundChoice === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                <div class="pw-subfield" data-pw-choice-extra @unless($sourceOfFundChoice === 'Other') hidden @endunless>
                                    <label class="ui-label" for="source_of_fund_other">Specify the source of funds <span class="ui-required" aria-hidden="true">*</span></label>
                                    <input type="text" id="source_of_fund_other" class="ui-input" value="{{ $sourceOfFundOther }}" maxlength="255" data-pw-choice-input aria-describedby="source_of_fund-error">
                                </div>
                                <span class="ui-error" id="source_of_fund-error" data-pw-error @unless($err('source_of_fund')) hidden @endunless>{{ $err('source_of_fund') }}</span>
                            </div>
                            <div class="ui-field" data-pw-choice="contract_duration" data-pw-choice-other="Custom">
                                <label class="ui-label" for="contract_duration_choice">Contract duration / delivery period <span class="ui-required" aria-hidden="true">*</span> @if($pr)<span class="pw-chip">From PR</span>@endif</label>
                                <input type="hidden" id="contract_duration" name="contract_duration" value="{{ $duration }}" data-pw-choice-value>
                                <select id="contract_duration_choice" class="ui-input" required data-pw-choice-select aria-invalid="{{ $invalid('contract_duration') }}" aria-describedby="contract_duration-error">
                                    <option value="">Select the duration</option>
                                    @foreach($durationOptions as $option)
                                        <option value="{{ $option }}" @selected($durationChoice === $option)>{{ $option === 'Custom' ? 'Other (specify)' : $option }}</option>
                                    @endforeach
                                </select>
                                <div class="pw-subfield" data-pw-choice-extra @unless($durationChoice === 'Custom') hidden @endunless>
                                    <label class="ui-label" for="contract_duration_custom">Specify the duration <span class="ui-required" aria-hidden="true">*</span></label>
                                    <input type="text" id="contract_duration_custom" class="ui-input" value="{{ $durationCustom }}" maxlength="255" placeholder="e.g. 45 calendar days from receipt of the Notice to Proceed" data-pw-choice-input aria-describedby="contract_duration-error">
                                </div>
                                <span class="ui-error" id="contract_duration-error" data-pw-error @unless($err('contract_duration')) hidden @endunless>{{ $err('contract_duration') }}</span>
                            </div>
                        </div>
                    </fieldset>

                    <details class="pw-section pw-details" @if(old('philgeps_reference_no') || old('philgeps_url') || $err('philgeps_url')) open @endif>
                        <summary class="pw-section__title">External PhilGEPS record <span class="ui-optional">(optional)</span></summary>
                        <p class="ui-hint">Only if the notice was already posted on PhilGEPS. This system does not post to PhilGEPS, and these fields do not affect publication here.</p>
                        <div class="pw-grid">
                            <div class="ui-field">
                                <label class="ui-label" for="philgeps_reference_no">PhilGEPS reference no.</label>
                                <input type="text" id="philgeps_reference_no" name="philgeps_reference_no" class="ui-input ui-mono" value="{{ old('philgeps_reference_no') }}" maxlength="100" aria-describedby="philgeps_reference_no-error">
                                <span class="ui-error" id="philgeps_reference_no-error" data-pw-error @unless($err('philgeps_reference_no')) hidden @endunless>{{ $err('philgeps_reference_no') }}</span>
                            </div>
                            <div class="ui-field">
                                <label class="ui-label" for="philgeps_url">PhilGEPS notice link</label>
                                <input type="url" id="philgeps_url" name="philgeps_url" class="ui-input" value="{{ old('philgeps_url') }}" maxlength="500" placeholder="https://notices.philgeps.gov.ph/â€¦" aria-invalid="{{ $invalid('philgeps_url') }}" aria-describedby="philgeps_url-error">
                                <span class="ui-error" id="philgeps_url-error" data-pw-error @unless($err('philgeps_url')) hidden @endunless>{{ $err('philgeps_url') }}</span>
                            </div>
                        </div>
                    </details>
                </section>

                {{-- ============================== Step 2 ============================== --}}
                <section class="pw-step" data-pw-step="2" aria-labelledby="pw-step2-title" hidden>
                    <h3 class="pw-step__title" id="pw-step2-title">Requirements</h3>

                    <fieldset class="pw-section">
                        <legend class="pw-section__title">Documents bidders must submit</legend>
                        <p class="ui-hint">The mode and category already add the standard forms, including the PhilGEPS Platinum certificate and the Omnibus Sworn Statement. Permits, DTI/SEC registration and tax clearance are checked at the bidder's registration and at post-qualification, so list only what this project needs on top of those.</p>
                        <div class="pw-checkgroups">
                            @foreach($requirementGroups as $group => $items)
                                <div class="pw-checkgroup">
                                    <p class="pw-checkgroup__title">{{ $group }}</p>
                                    @foreach($items as $item)
                                        <label class="pw-check">
                                            <input type="checkbox" name="required_documents[]" value="{{ $item }}" @checked(in_array($item, $selectedRequirements, true))>
                                            <span>{{ $item === 'Other BAC Required Documents' ? 'Other documents required by the BAC' : $item }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>

                        <div class="pw-extra" data-pw-extra-list>
                            <p class="pw-checkgroup__title">Additional required documents</p>
                            <ul class="pw-extra__list" data-pw-extra-items>
                                @foreach($extraRequirements as $index => $extra)
                                    <li class="pw-extra__item" data-pw-extra-item>
                                        <label class="sr-only" for="extra-requirement-{{ $index }}">Additional required document</label>
                                        <input type="text" id="extra-requirement-{{ $index }}" name="required_documents[]" class="ui-input" value="{{ $extra }}" maxlength="255">
                                        <button type="button" class="ui-btn ui-btn--ghost ui-btn--sm" data-pw-extra-remove><i class="fas fa-trash-can" aria-hidden="true"></i> Remove</button>
                                    </li>
                                @endforeach
                            </ul>
                            <template data-pw-extra-template>
                                <li class="pw-extra__item" data-pw-extra-item>
                                    <label class="sr-only">Additional required document</label>
                                    <input type="text" name="required_documents[]" class="ui-input" maxlength="255" placeholder="e.g. Certificate of site inspection">
                                    <button type="button" class="ui-btn ui-btn--ghost ui-btn--sm" data-pw-extra-remove><i class="fas fa-trash-can" aria-hidden="true"></i> Remove</button>
                                </li>
                            </template>
                            <button type="button" class="ui-btn ui-btn--secondary ui-btn--sm" data-pw-extra-add><i class="fas fa-plus" aria-hidden="true"></i> Add a required document</button>
                        </div>
                        <span class="ui-error" id="required_documents-error" data-pw-error @unless($err('required_documents')) hidden @endunless>{{ $err('required_documents') }}</span>
                    </fieldset>

                    <fieldset class="pw-section">
                        <legend class="pw-section__title">Requirement details <span class="ui-optional">(optional)</span></legend>
                        <p class="ui-hint">Notes shown with the requirements, for example minimum experience or license classifications.</p>
                        <div class="pw-grid">
                            @foreach([
                                'eligibility_requirements' => ['Eligibility requirements', 'e.g. PCAB license category C or higher'],
                                'technical_requirements' => ['Technical requirements', 'e.g. Delivery within 30 calendar days; 1-year warranty'],
                                'financial_requirements' => ['Financial requirements', 'e.g. NFCC equal to the ABC or a committed line of credit'],
                                'qualification_notes' => ['Qualification notes', 'Anything bidders should know about post-qualification'],
                                'special_instructions' => ['Special instructions', 'e.g. Submit two copies of the technical proposal'],
                            ] as $field => [$label, $placeholder])
                                <div class="ui-field">
                                    <label class="ui-label" for="{{ $field }}">{{ $label }}</label>
                                    <textarea id="{{ $field }}" name="{{ $field }}" class="ui-input" rows="3" placeholder="{{ $placeholder }}" aria-describedby="{{ $field }}-error">{{ old($field) }}</textarea>
                                    <span class="ui-error" id="{{ $field }}-error" data-pw-error @unless($err($field)) hidden @endunless>{{ $err($field) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>

                    @php
                        $criteriaRows = collect((array) old('evaluation_criteria', []))->filter(fn ($row) => is_array($row))->values();
                        if ($criteriaRows->isEmpty()) {
                            $criteriaRows = collect([['name' => '', 'weight' => '']]);
                        }
                    @endphp
                    <fieldset class="pw-section" data-pw-weighted hidden>
                        <legend class="pw-section__title">Evaluation criteria <span data-pw-weighted-label>(MEARB / MARB)</span></legend>
                        <p class="ui-hint">The criteria and weights stated in the Invitation to Bid (IRR Sec. 50.2(e), (f)). Weights must total 100%.</p>
                        <ul class="pw-extra__list" data-pw-criteria-items>
                            @foreach($criteriaRows as $index => $row)
                                <li class="pw-criteria__item" data-pw-criteria-item>
                                    <input type="text" name="evaluation_criteria[{{ $index }}][name]" class="ui-input" value="{{ $row['name'] ?? '' }}" maxlength="255" placeholder="e.g. Technical capability" aria-label="Criterion">
                                    <div class="ui-input-group">
                                        <input type="number" name="evaluation_criteria[{{ $index }}][weight]" class="ui-input ui-num" value="{{ $row['weight'] ?? '' }}" min="0" max="100" step="0.01" placeholder="0" aria-label="Weight in percent" data-pw-criteria-weight>
                                        <span class="ui-input-group__prefix" aria-hidden="true">%</span>
                                    </div>
                                    <button type="button" class="ui-btn ui-btn--ghost ui-btn--sm" data-pw-criteria-remove><i class="fas fa-trash-can" aria-hidden="true"></i> Remove</button>
                                </li>
                            @endforeach
                        </ul>
                        <div class="pw-criteria__foot">
                            <button type="button" class="ui-btn ui-btn--secondary ui-btn--sm" data-pw-criteria-add><i class="fas fa-plus" aria-hidden="true"></i> Add a criterion</button>
                            <span class="ui-hint" data-pw-criteria-total aria-live="polite">Total: 0%</span>
                        </div>
                        <span class="ui-error" id="evaluation_criteria-error" data-pw-error @unless($err('evaluation_criteria')) hidden @endunless>{{ $err('evaluation_criteria') }}</span>
                        <div class="ui-field" data-pw-qpr hidden>
                            <label class="ui-label" for="quality_price_ratio">Quality-price ratio: technical weight <span class="ui-required" aria-hidden="true">*</span></label>
                            <div class="ui-input-group pw-qpr">
                                <input type="number" id="quality_price_ratio" name="quality_price_ratio" class="ui-input ui-num" value="{{ old('quality_price_ratio') }}" min="1" max="99" step="1" placeholder="70" aria-describedby="quality_price_ratio-hint quality_price_ratio-error">
                                <span class="ui-input-group__prefix" aria-hidden="true">%</span>
                            </div>
                            <span class="ui-hint" id="quality_price_ratio-hint" data-pw-qpr-hint>MEARB only. The price weight is the rest.</span>
                            <span class="ui-error" id="quality_price_ratio-error" data-pw-error @unless($err('quality_price_ratio')) hidden @endunless>{{ $err('quality_price_ratio') }}</span>
                        </div>
                    </fieldset>

                    <fieldset class="pw-section">
                        <legend class="pw-section__title">Submission, fee and bid security</legend>
                        <div class="pw-grid">
                            <div class="ui-field">
                                <label class="ui-label" for="submission_mode">How bidders submit</label>
                                <select id="submission_mode" name="submission_mode" class="ui-input" data-pw-submission-mode aria-describedby="submission_mode-error">
                                    <option value="electronic" @selected(old('submission_mode', 'electronic') === 'electronic')>Online, through this system</option>
                                    <option value="manual" @selected(old('submission_mode') === 'manual')>Manual, sealed at the BAC Secretariat</option>
                                </select>
                                <span class="ui-error" id="submission_mode-error" data-pw-error @unless($err('submission_mode')) hidden @endunless>{{ $err('submission_mode') }}</span>
                            </div>
                            <div class="ui-field" data-pw-show-when="manual">
                                <label class="ui-label" for="submission_venue">Where sealed bids are received</label>
                                <input type="text" id="submission_venue" name="submission_venue" class="ui-input" value="{{ old('submission_venue', 'BAC Secretariat, Municipal Hall, San Jose, Occidental Mindoro') }}" maxlength="255">
                            </div>
                            <div class="ui-field" data-pw-show-when="electronic">
                                <label class="ui-label" for="electronic_submission_authority">IT certification for electronic bids <span class="ui-required" aria-hidden="true">*</span></label>
                                <input type="text" id="electronic_submission_authority" name="electronic_submission_authority" class="ui-input" value="{{ old('electronic_submission_authority') }}" maxlength="255" placeholder="e.g. MIS Certification No. 2026-03, submitted to GPPB-TSO" aria-describedby="electronic_submission_authority-hint electronic_submission_authority-error">
                                <span class="ui-hint" id="electronic_submission_authority-hint">Certification of the official managing the LGU IT system, submitted to the GPPB-TSO before posting (IRR Sec. 50.3.3). Without it, accept manual sealed bids.</span>
                                <span class="ui-error" id="electronic_submission_authority-error" data-pw-error @unless($err('electronic_submission_authority')) hidden @endunless>{{ $err('electronic_submission_authority') }}</span>
                            </div>
                            <div class="ui-field ui-field--wide pw-fee" data-pw-fee-block data-pw-fee-schedule='@json(\App\Support\BiddingDocumentsFee::schedule())'>
                                <span class="ui-label" id="bidding_fee-label">Bidding documents fee</span>
                                {{-- Competitive bidding: the ABC schedule's maximum (GPPB Circular No. 02-2026, Sec. 5.2), lower or waived with a reason. --}}
                                <div class="pw-fee-calc" data-pw-fee-competitive>
                                    <p class="pw-fee-calc__line" data-pw-fee-calc aria-live="polite">Enter the ABC in step 1 to compute the maximum fee.</p>
                                    <p class="ui-hint">Maximum rates under {{ \App\Support\BiddingDocumentsFee::BASIS }}. The BAC may charge less or waive the fee, with a recorded reason, but never more.</p>
                                    <div class="pw-fee-modes" role="radiogroup" aria-labelledby="bidding_fee-label">
                                        <label class="ui-check"><input type="radio" name="bidding_fee_mode" value="schedule" data-pw-fee-mode @checked($feeMode === 'schedule')> Charge the maximum <strong data-pw-fee-max></strong></label>
                                        <label class="ui-check"><input type="radio" name="bidding_fee_mode" value="reduced" data-pw-fee-mode @checked($feeMode === 'reduced')> Charge a lower fee</label>
                                        <label class="ui-check"><input type="radio" name="bidding_fee_mode" value="waived" data-pw-fee-mode @checked($feeMode === 'waived')> Waive the fee</label>
                                    </div>
                                    <span class="ui-error" id="bidding_fee_mode-error" data-pw-error @unless($err('bidding_fee_mode')) hidden @endunless>{{ $err('bidding_fee_mode') }}</span>
                                </div>
                                <div class="pw-subfield" data-pw-fee-amount>
                                    <label class="ui-label" for="bidding_documents_fee_display" data-pw-fee-amount-label>Fee amount</label>
                                    <div class="ui-input-group">
                                        <span class="ui-input-group__prefix" aria-hidden="true">â‚±</span>
                                        <input type="text" id="bidding_documents_fee_display" class="ui-input ui-num" value="{{ $feeDisplay }}" inputmode="decimal" autocomplete="off" placeholder="0.00" data-pw-money="bidding_documents_fee" aria-invalid="{{ $invalid('bidding_documents_fee') }}" aria-describedby="bidding_documents_fee-hint bidding_documents_fee-error">
                                    </div>
                                    <input type="hidden" id="bidding_documents_fee" name="bidding_documents_fee" value="{{ $fee }}" data-pw-fee>
                                    <span class="ui-hint" id="bidding_documents_fee-hint" data-pw-fee-amount-hint>Leave blank when the notice charges no fee. Bidders pay at the BAC office before submitting.</span>
                                    <span class="ui-error" id="bidding_documents_fee-error" data-pw-error @unless($err('bidding_documents_fee')) hidden @endunless>{{ $err('bidding_documents_fee') }}</span>
                                </div>
                                <div class="pw-subfield" data-pw-fee-reason>
                                    <label class="ui-label" for="bidding_fee_reason">Reason for the lower fee or waiver <span class="ui-required" aria-hidden="true">*</span></label>
                                    <textarea id="bidding_fee_reason" name="bidding_fee_reason" class="ui-input" rows="2" maxlength="2000" placeholder="e.g. Per BAC Resolution No. 2026-020" aria-describedby="bidding_fee_reason-error">{{ old('bidding_fee_reason') }}</textarea>
                                    <span class="ui-error" id="bidding_fee_reason-error" data-pw-error @unless($err('bidding_fee_reason')) hidden @endunless>{{ $err('bidding_fee_reason') }}</span>
                                </div>
                            </div>
                            <div class="ui-field" data-pw-fee-venue>
                                <label class="ui-label" for="payment_venue">Where to pay the fee</label>
                                <input type="text" id="payment_venue" name="payment_venue" class="ui-input" value="{{ old('payment_venue', 'BAC Secretariat, Municipal Hall, San Jose, Occidental Mindoro') }}" maxlength="255" aria-describedby="payment_venue-error">
                                <span class="ui-error" id="payment_venue-error" data-pw-error @unless($err('payment_venue')) hidden @endunless>{{ $err('payment_venue') }}</span>
                            </div>
                            <div class="ui-field ui-field--wide">
                                <input type="hidden" name="bid_security_required" value="0">
                                <label class="ui-check"><input type="checkbox" name="bid_security_required" value="1" data-pw-security @checked(old('bid_security_required', '1') === '1')> Bid security is required</label>
                                <div class="pw-subfield" data-pw-security-notes>
                                    <label class="ui-label" for="bid_security_notes">Bid security details <span class="ui-optional">(optional)</span></label>
                                    <textarea id="bid_security_notes" name="bid_security_notes" class="ui-input" rows="2" maxlength="2000" placeholder="e.g. In any acceptable form and in the amount stated in ITB Clause 16" aria-describedby="bid_security_notes-error">{{ old('bid_security_notes') }}</textarea>
                                    <span class="ui-error" id="bid_security_notes-error" data-pw-error @unless($err('bid_security_notes')) hidden @endunless>{{ $err('bid_security_notes') }}</span>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </section>

                {{-- ============================== Step 3 ============================== --}}
                <section class="pw-step" data-pw-step="3" aria-labelledby="pw-step3-title" hidden>
                    <h3 class="pw-step__title" id="pw-step3-title">Documents</h3>
                    <p class="ui-hint">PDF, Word, Excel, JPG or PNG, up to 20 MB each. Selected files are uploaded when you save the draft or publish â€” until then they stay on this computer.</p>

                    <div class="pw-note" data-pw-doc-rule aria-live="polite"></div>

                    <ul class="pw-docs" data-pw-docs>
                        <li class="pw-doc" data-pw-doc>
                            <div class="ui-field">
                                <label class="ui-label" for="document-type-0">Document type</label>
                                <select id="document-type-0" name="document_type[]" class="ui-input" data-pw-doc-type>
                                    <option value="">Select the type</option>
                                    @foreach($documentTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="pw-doc__file">
                                <input type="file" id="document-file-0" name="project_documents[]" class="pw-doc__input" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" data-pw-doc-file>
                                <label class="ui-btn ui-btn--secondary ui-btn--sm" for="document-file-0" data-pw-doc-choose><i class="fas fa-paperclip" aria-hidden="true"></i> <span data-pw-doc-choose-label>Choose file</span></label>
                                <span class="pw-doc__name" data-pw-doc-name>No file selected</span>
                                <span class="ui-pill ui-pill--neutral" data-pw-doc-state hidden></span>
                            </div>
                            <button type="button" class="ui-btn ui-btn--ghost ui-btn--sm pw-doc__remove" data-pw-doc-remove><i class="fas fa-trash-can" aria-hidden="true"></i> Remove</button>
                            <span class="ui-error pw-doc__error" data-pw-doc-error hidden></span>
                        </li>
                    </ul>
                    <button type="button" class="ui-btn ui-btn--secondary ui-btn--sm" data-pw-doc-add><i class="fas fa-plus" aria-hidden="true"></i> Add another document</button>
                    <span class="ui-error" id="project_documents-error" data-pw-error @unless($errors->has('project_documents') || $errors->has('project_documents.*')) hidden @endunless>{{ $err('project_documents') ?: collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'project_documents.'))->flatten()->first() }}</span>
                </section>

                {{-- ============================== Step 4 ============================== --}}
                <section class="pw-step" data-pw-step="4" aria-labelledby="pw-step4-title" hidden>
                    <h3 class="pw-step__title" id="pw-step4-title">Dates</h3>
                    <p class="ui-hint">In the order they happen. All dates and times are Philippine Standard Time. Meetings, deadlines and openings fall on working days between 8:00 AM and 5:00 PM.</p>
                    <button type="button" class="ui-btn ui-btn--secondary ui-btn--sm" data-pw-suggest-dates><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> Suggest dates</button>
                    <p class="ui-hint" data-pw-schedule-suggestion role="status" aria-live="polite" hidden></p>

                    <ol class="pw-timeline">
                        <li class="pw-timeline__item" data-pw-preproc hidden>
                            <div class="ui-field">
                                <label class="ui-label" for="pre_procurement_conference_at">Pre-procurement conference held <span class="ui-required" aria-hidden="true" data-pw-preproc-required hidden>*</span> <span class="ui-optional" data-pw-preproc-optional>(if held)</span></label>
                                <input type="datetime-local" id="pre_procurement_conference_at" name="pre_procurement_conference_at" class="ui-input" value="{{ old('pre_procurement_conference_at') }}" max="{{ $today->format('Y-m-d\TH:i') }}" data-pw-date aria-describedby="pre_procurement_conference_at-hint pre_procurement_conference_at-error">
                                <label class="ui-label pw-subfield" for="pre_procurement_reference">Minutes / reference <span class="ui-optional">(optional)</span></label>
                                <input type="text" id="pre_procurement_reference" name="pre_procurement_reference" class="ui-input" value="{{ old('pre_procurement_reference') }}" maxlength="100" placeholder="e.g. Minutes of the Pre-Procurement Conference, 2026-015">
                                <span class="ui-hint" id="pre_procurement_conference_at-hint" data-pw-preproc-rule>Held before the Invitation to Bid is published (IRR Sec. 49.1). Recorded with the project.</span>
                                <span class="pw-when" data-pw-when="pre_procurement_conference_at"></span>
                                <span class="ui-error" id="pre_procurement_conference_at-error" data-pw-error @unless($err('pre_procurement_conference_at')) hidden @endunless>{{ $err('pre_procurement_conference_at') }}</span>
                            </div>
                        </li>
                        <li class="pw-timeline__item">
                            <div class="ui-field">
                                <label class="ui-label" for="date_posted">Publication in this BAC system</label>
                                <input type="date" id="date_posted" name="date_posted" class="ui-input" value="{{ old('date_posted') }}" data-pw-date aria-describedby="date_posted-hint date_posted-error">
                                <span class="ui-hint" id="date_posted-hint">Publishing from this wizard records today, {{ $today->format('D, M j, Y') }}, as the publication date. For a draft you may note a planned date.</span>
                                <span class="pw-when" data-pw-when="date_posted"></span>
                                <span class="ui-error" id="date_posted-error" data-pw-error @unless($err('date_posted')) hidden @endunless>{{ $err('date_posted') }}</span>
                            </div>
                        </li>
                        <li class="pw-timeline__item" data-pw-prebid>
                            <div class="ui-field">
                                <label class="ui-label" for="pre_bid_conference_date">Pre-bid conference <span class="ui-required" aria-hidden="true" data-pw-prebid-required hidden>*</span> <span class="ui-optional" data-pw-prebid-optional>(if held)</span></label>
                                <input type="datetime-local" id="pre_bid_conference_date" name="pre_bid_conference_date" class="ui-input" value="{{ old('pre_bid_conference_date') }}" data-pw-date aria-invalid="{{ $invalid('pre_bid_conference_date') }}" aria-describedby="pre_bid_conference_date-hint pre_bid_conference_date-error">
                                <span class="ui-hint" id="pre_bid_conference_date-hint" data-pw-prebid-rule></span>
                                <span class="pw-when" data-pw-when="pre_bid_conference_date"></span>
                                <span class="ui-error" id="pre_bid_conference_date-error" data-pw-error @unless($err('pre_bid_conference_date')) hidden @endunless>{{ $err('pre_bid_conference_date') }}</span>
                            </div>
                        </li>
                        <li class="pw-timeline__item">
                            <div class="ui-field">
                                <label class="ui-label" for="clarification_deadline">Deadline for written clarifications <span class="ui-optional">(optional)</span></label>
                                <input type="datetime-local" id="clarification_deadline" name="clarification_deadline" class="ui-input" value="{{ old('clarification_deadline') }}" data-pw-date aria-invalid="{{ $invalid('clarification_deadline') }}" aria-describedby="clarification_deadline-error">
                                <span class="pw-when" data-pw-when="clarification_deadline"></span>
                                <span class="ui-error" id="clarification_deadline-error" data-pw-error @unless($err('clarification_deadline')) hidden @endunless>{{ $err('clarification_deadline') }}</span>
                            </div>
                        </li>
                        <li class="pw-timeline__item">
                            <div class="ui-field">
                                <label class="ui-label" for="bid_submission_deadline"><span data-pw-deadline-label>Bid submission deadline</span> <span class="ui-required" aria-hidden="true">*</span></label>
                                <input type="datetime-local" id="bid_submission_deadline" name="bid_submission_deadline" class="ui-input" value="{{ old('bid_submission_deadline') }}" required data-pw-date aria-invalid="{{ $invalid('bid_submission_deadline') }}" aria-describedby="bid_submission_deadline-hint bid_submission_deadline-error">
                                <span class="ui-hint" id="bid_submission_deadline-hint" data-pw-deadline-rule></span>
                                <span class="pw-when" data-pw-when="bid_submission_deadline"></span>
                                <span class="ui-error" id="bid_submission_deadline-error" data-pw-error @unless($err('bid_submission_deadline')) hidden @endunless>{{ $err('bid_submission_deadline') }}</span>
                            </div>
                        </li>
                        <li class="pw-timeline__item">
                            <div class="ui-field">
                                <label class="ui-label" for="bid_opening_date"><span data-pw-opening-label>Bid opening</span> <span class="ui-required" aria-hidden="true" data-pw-opening-required>*</span></label>
                                <input type="datetime-local" id="bid_opening_date" name="bid_opening_date" class="ui-input" value="{{ old('bid_opening_date') }}" data-pw-date aria-invalid="{{ $invalid('bid_opening_date') }}" aria-describedby="bid_opening_date-hint bid_opening_date-error">
                                <span class="ui-hint" id="bid_opening_date-hint" data-pw-opening-rule></span>
                                <span class="pw-when" data-pw-when="bid_opening_date"></span>
                                <span class="ui-error" id="bid_opening_date-error" data-pw-error @unless($err('bid_opening_date')) hidden @endunless>{{ $err('bid_opening_date') }}</span>
                                <div class="pw-subfield" data-pw-opening-venue>
                                    <label class="ui-label" for="bid_opening_venue">Place of bid opening <span class="ui-required" aria-hidden="true">*</span></label>
                                    <input type="text" id="bid_opening_venue" name="bid_opening_venue" class="ui-input" value="{{ old('bid_opening_venue', trim(config('bac-office.contact.office').', '.config('bac-office.contact.address'), ', ')) }}" maxlength="500" aria-describedby="bid_opening_venue-hint bid_opening_venue-error">
                                    <span class="ui-hint" id="bid_opening_venue-hint">Stated in the Invitation to Bid (IRR Sec. 50.2(h)).</span>
                                    <span class="ui-error" id="bid_opening_venue-error" data-pw-error @unless($err('bid_opening_venue')) hidden @endunless>{{ $err('bid_opening_venue') }}</span>
                                </div>
                            </div>
                        </li>
                        <li class="pw-timeline__item">
                            <div class="ui-field">
                                <label class="ui-label" for="evaluation_start_date">Evaluation starts <span class="ui-optional">(planned)</span></label>
                                <input type="date" id="evaluation_start_date" name="evaluation_start_date" class="ui-input" value="{{ old('evaluation_start_date') }}" data-pw-date aria-invalid="{{ $invalid('evaluation_start_date') }}" aria-describedby="evaluation_start_date-error">
                                <span class="pw-when" data-pw-when="evaluation_start_date"></span>
                                <span class="ui-error" id="evaluation_start_date-error" data-pw-error @unless($err('evaluation_start_date')) hidden @endunless>{{ $err('evaluation_start_date') }}</span>
                            </div>
                        </li>
                        <li class="pw-timeline__item">
                            <div class="ui-field">
                                <label class="ui-label" for="expected_award_date">Expected award <span class="ui-optional">(planned)</span></label>
                                <input type="date" id="expected_award_date" name="expected_award_date" class="ui-input" value="{{ old('expected_award_date') }}" data-pw-date aria-invalid="{{ $invalid('expected_award_date') }}" aria-describedby="expected_award_date-error">
                                <span class="pw-when" data-pw-when="expected_award_date"></span>
                                <span class="ui-error" id="expected_award_date-error" data-pw-error @unless($err('expected_award_date')) hidden @endunless>{{ $err('expected_award_date') }}</span>
                            </div>
                        </li>
                    </ol>
                </section>

                {{-- ============================== Step 5 ============================== --}}
                <section class="pw-step" data-pw-step="5" aria-labelledby="pw-step5-title" hidden>
                    <h3 class="pw-step__title" id="pw-step5-title">Review</h3>
                    <p class="ui-hint">Check each part. <strong>Save as draft</strong> keeps the project private to the BAC. <strong>Publish in the BAC system</strong> makes it visible to bidders here; it does not post to PhilGEPS.</p>

                    <div class="pw-review" data-pw-review>
                        @foreach([1 => 'Project info', 2 => 'Requirements', 3 => 'Documents', 4 => 'Dates'] as $number => $label)
                            <section class="pw-review__card" aria-labelledby="pw-review-{{ $number }}">
                                <header class="pw-review__head">
                                    <h4 id="pw-review-{{ $number }}">{{ $label }}</h4>
                                    <button type="button" class="ui-btn ui-btn--ghost ui-btn--sm" data-pw-go="{{ $number }}"><i class="fas fa-pen" aria-hidden="true"></i> Edit<span class="sr-only"> {{ strtolower($label) }}</span></button>
                                </header>
                                <dl class="pw-review__list" data-pw-review-list="{{ $number }}"></dl>
                            </section>
                        @endforeach
                    </div>

                    <section class="pw-readiness" aria-labelledby="pw-readiness-title">
                        <h4 id="pw-readiness-title">Before publishing</h4>
                        <ul class="pw-readiness__list" data-pw-readiness></ul>
                    </section>

                    <div class="ui-field">
                        <label class="ui-check">
                            <input type="checkbox" name="confirm_correct" value="1" data-pw-confirm aria-describedby="confirm_correct-error" @checked(old('confirm_correct'))>
                            <span>I confirm the project details, requirements, documents and schedule are accurate and ready for publication in the BAC system.</span>
                        </label>
                        <span class="ui-error" id="confirm_correct-error" data-pw-error @unless($err('confirm_correct')) hidden @endunless>{{ $err('confirm_correct') }}</span>
                    </div>
                </section>
            </div>

            <footer class="pw__footer">
                <a href="{{ route('admin.projects') }}" class="ui-btn ui-btn--ghost pw__cancel" data-pw-exit>Cancel</a>
                <span class="pw__position" aria-live="polite" data-pw-position>Step 1 of 5 Â· Project info</span>
                <div class="pw__actions">
                    <button type="button" class="ui-btn ui-btn--secondary" data-pw-draft><i class="fas fa-floppy-disk" aria-hidden="true"></i> <span data-pw-draft-label>Save as draft</span></button>
                    <button type="button" class="ui-btn ui-btn--secondary" data-pw-back hidden><i class="fas fa-arrow-left" aria-hidden="true"></i> Back</button>
                    <button type="button" class="ui-btn ui-btn--primary" data-pw-next>Next <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
                    <button type="button" class="ui-btn ui-btn--primary" data-pw-publish hidden><i class="fas fa-bullhorn" aria-hidden="true"></i> <span data-pw-publish-label>Publish in the BAC system</span></button>
                </div>
            </footer>
        </form>
    </div>
</div>

<dialog class="ui-dialog pw-dialog" id="pwPublishDialog" aria-labelledby="pwPublishTitle">
    <div class="ui-card__head">
        <div>
            <h2 class="ui-card__title" id="pwPublishTitle">Publish this project in the BAC system?</h2>
            <p class="ui-card__desc">It becomes visible to bidders in this system. It is not posted to PhilGEPS.</p>
        </div>
    </div>
    <div class="ui-card__body">
        <dl class="ui-dl" data-pw-publish-summary></dl>
        <p class="ui-hint ui-mt-sm">If a publication check fails, the project is kept as a draft and the reasons are shown.</p>
    </div>
    <div class="ui-card__foot">
        <button type="button" class="ui-btn ui-btn--secondary" data-pw-publish-cancel>Keep editing</button>
        <button type="button" class="ui-btn ui-btn--primary" data-pw-publish-confirm><i class="fas fa-bullhorn" aria-hidden="true"></i> Publish now</button>
    </div>
</dialog>

<dialog class="ui-dialog pw-dialog" id="pwLeaveDialog" aria-labelledby="pwLeaveTitle">
    <div class="ui-card__head">
        <div>
            <h2 class="ui-card__title" id="pwLeaveTitle">Leave without saving?</h2>
            <p class="ui-card__desc">The details you entered in this wizard will be lost. Save a draft to keep them.</p>
        </div>
    </div>
    <div class="ui-card__foot">
        <button type="button" class="ui-btn ui-btn--secondary" data-pw-leave-stay>Stay</button>
        <button type="button" class="ui-btn ui-btn--secondary" data-pw-leave-draft><i class="fas fa-floppy-disk" aria-hidden="true"></i> Save draft</button>
        <button type="button" class="ui-btn ui-btn--danger" data-pw-leave-go>Leave</button>
    </div>
</dialog>
@endsection

@push('scripts')
    <script type="application/json" id="pwRules">@json(\App\Support\ProcurementMode::clientRules() + ['today' => $today->format('Y-m-d'), 'maxUploadKb' => 20480])</script>
    @vite('resources/js/project-wizard.js')
@endpush
