@php
    $projectDocuments = $project->uploadedDocuments();
    $sourceOfFundOptions = [
        'General Fund',
        'Special Education Fund (SEF)',
        '20% Development Fund',
        'Local Disaster Risk Reduction and Management Fund (LDRRMF)',
        'Trust Fund',
        'National Government Grant / Transfer',
        'Loan / Grant',
        'Other',
    ];
    $selectedSourceOfFund = old('source_of_fund', $project->source_of_fund);
    $selectedSourceOfFundChoice = in_array($selectedSourceOfFund, $sourceOfFundOptions, true)
        ? $selectedSourceOfFund
        : (filled($selectedSourceOfFund) ? 'Other' : '');
    $specifiedSourceOfFund = $selectedSourceOfFundChoice === 'Other' && ! in_array($selectedSourceOfFund, $sourceOfFundOptions, true)
        ? $selectedSourceOfFund
        : '';
    $contractDurationOptions = [
        '30 Calendar Days',
        '60 Calendar Days',
        '90 Calendar Days',
        '120 Calendar Days',
        '180 Calendar Days',
        '1 Year',
        'Custom',
    ];
    $selectedContractDuration = old('contract_duration', $project->contract_duration);
    $selectedContractDurationChoice = in_array($selectedContractDuration, $contractDurationOptions, true)
        ? $selectedContractDuration
        : (filled($selectedContractDuration) ? 'Custom' : '');
    $specifiedContractDuration = $selectedContractDurationChoice === 'Custom' && ! in_array($selectedContractDuration, $contractDurationOptions, true)
        ? $selectedContractDuration
        : '';

    $mode = $project->mode();
    $competitive = $mode->isCompetitive();
    $budget = old('budget', $project->budget);
    $criterion = old('award_criterion', $project->award_criterion);
    $canPublish = in_array($project->status, ['draft', 'approved_for_bidding'], true);
    $editDocumentTypes = [
        'invitation_to_bid' => 'Invitation to Bid',
        'bidding_documents' => 'Bidding Documents',
        'terms_of_reference' => 'Terms of Reference',
        'technical_specifications' => 'Technical Specifications',
        'bill_of_quantities' => 'Bill of Quantities',
        'project_plans' => 'Project Plans / Drawings',
        'supplemental_bulletin' => 'Supplemental Bid Bulletin',
        'other' => 'Other',
    ];
    $statusTone = match ($project->status) {
        'open' => 'success',
        'approved_for_bidding' => 'info',
        'closed' => 'warning',
        'awarded' => 'primary',
        default => 'neutral',
    };
    $when = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('Y-m-d\TH:i') : '';
@endphp

<div class="pe" role="document" aria-labelledby="peTitle">
    <header class="pe-head">
        <p class="pe-eyebrow">Edit project</p>
        <h2 id="peTitle">{{ $project->title }}</h2>
        <p class="pe-meta">
            <span class="pe-pill is-{{ $statusTone }}">{{ \Illuminate\Support\Str::headline($project->status ?: 'draft') }}</span>
            @if($project->reference_no)<span class="pe-mono">{{ $project->reference_no }}</span>@endif
            <span>{{ $mode->label() }} &middot; {{ $mode->legalBasisShort() }}</span>
            <span>
                <i class="fas fa-bullhorn" aria-hidden="true"></i>
                @if($project->published_at)
                    Published {{ $project->published_at->timezone(config('app.timezone', 'Asia/Manila'))->format('M d, Y g:i A') }}
                @else
                    Not published yet
                @endif
            </span>
        </p>
    </header>

    <form action="{{ route('admin.project.update', $project->id) }}" method="POST" enctype="multipart/form-data" class="pe-form" novalidate>
        @csrf
        @method('PUT')
        <input type="hidden" name="status" value="{{ $project->status }}">

        <div class="pe-body">
            <div id="editFormAlert" class="pe-alert" role="alert" style="display: none;"></div>

            {{-- Project details --}}
            <section class="pe-section" aria-labelledby="pe-details-title">
                <div class="pe-section-head">
                    <h3 id="pe-details-title">Project details</h3>
                </div>
                <div class="pe-grid">
                    <div class="pe-field is-wide">
                        <label class="pe-label" for="pe_title">Project title <span class="pe-req" aria-hidden="true">*</span></label>
                        <input type="text" id="pe_title" name="title" value="{{ old('title', $project->title) }}" required maxlength="255" class="pe-input">
                        <p class="pe-error" data-error-for="title"></p>
                    </div>

                    <div class="pe-field">
                        <label class="pe-label" for="pe_budget">Approved Budget for the Contract (ABC) <span class="pe-req" aria-hidden="true">*</span></label>
                        <div class="pe-affix">
                            <span aria-hidden="true">&#8369;</span>
                            <input type="text" id="pe_budget" name="budget" value="{{ is_numeric($budget) ? number_format((float) $budget, 2) : $budget }}" required inputmode="decimal" autocomplete="off" placeholder="0.00" class="pe-input pe-num" data-money>
                        </div>
                        <p class="pe-error" data-error-for="budget"></p>
                    </div>

                    <div class="pe-field" data-source-of-fund-field>
                        <label class="pe-label" for="edit_source_of_fund_choice">Source of funds</label>
                        <input type="hidden" id="edit_source_of_fund" name="source_of_fund" value="{{ $selectedSourceOfFund }}" data-source-of-fund-value>
                        <select id="edit_source_of_fund_choice" class="pe-input" data-source-of-fund-select>
                            <option value="">Select source of funds</option>
                            @foreach($sourceOfFundOptions as $sourceOfFundOption)
                                <option value="{{ $sourceOfFundOption }}" @selected($selectedSourceOfFundChoice === $sourceOfFundOption)>{{ $sourceOfFundOption }}</option>
                            @endforeach
                        </select>
                        <div class="pe-sub" data-source-of-fund-other-wrap @unless($selectedSourceOfFundChoice === 'Other') hidden @endunless>
                            <label class="sr-only" for="edit_source_of_fund_other">Specify the source of funds</label>
                            <input type="text" id="edit_source_of_fund_other" value="{{ $specifiedSourceOfFund }}" placeholder="Specify the source of funds" class="pe-input" data-source-of-fund-other>
                        </div>
                        <p class="pe-error" data-error-for="source_of_fund"></p>
                    </div>

                    <div class="pe-field is-wide">
                        <label class="pe-label" for="pe_description">Description <span class="pe-req" aria-hidden="true">*</span></label>
                        <textarea id="pe_description" name="description" required rows="3" class="pe-input">{{ old('description', $project->description) }}</textarea>
                        <p class="pe-error" data-error-for="description"></p>
                    </div>

                    <div class="pe-field" data-contract-duration-field>
                        <label class="pe-label" for="edit_contract_duration_choice">Contract duration</label>
                        <input type="hidden" id="edit_contract_duration" name="contract_duration" value="{{ $selectedContractDuration }}" data-contract-duration-value>
                        <select id="edit_contract_duration_choice" class="pe-input" data-contract-duration-select>
                            <option value="">Select contract duration</option>
                            @foreach($contractDurationOptions as $contractDurationOption)
                                <option value="{{ $contractDurationOption }}" @selected($selectedContractDurationChoice === $contractDurationOption)>{{ $contractDurationOption }}</option>
                            @endforeach
                        </select>
                        <div class="pe-sub" data-contract-duration-custom-wrap @unless($selectedContractDurationChoice === 'Custom') hidden @endunless>
                            <label class="sr-only" for="edit_contract_duration_custom">Specify the contract duration</label>
                            <input type="text" id="edit_contract_duration_custom" value="{{ $specifiedContractDuration }}" placeholder="e.g. 45 calendar days" class="pe-input" data-contract-duration-custom>
                        </div>
                        <p class="pe-error" data-error-for="contract_duration"></p>
                    </div>

                    <div class="pe-field">
                        <label class="pe-label" for="pe_staff">Assigned staff</label>
                        <select id="pe_staff" name="staff_id" class="pe-input">
                            <option value="">Not assigned</option>
                            @foreach($staffMembers as $staff)
                                <option value="{{ $staff->id }}" @selected((string) old('staff_id', $currentAssignment?->staff_id) === (string) $staff->id)>{{ $staff->name }}</option>
                            @endforeach
                        </select>
                        <p class="pe-error" data-error-for="staff_id"></p>
                    </div>
                </div>
            </section>

            {{-- Schedule --}}
            <section class="pe-section" aria-labelledby="pe-schedule-title">
                <div class="pe-section-head">
                    <h3 id="pe-schedule-title">Schedule</h3>
                    <p>Philippine time. Any day and time the BAC sets; the posting and pre-bid periods still apply.</p>
                </div>
                <div class="pe-grid">
                    <div class="pe-field">
                        <label class="pe-label" for="pe_prebid">Pre-bid conference</label>
                        <input type="datetime-local" id="pe_prebid" name="pre_bid_conference_date" value="{{ old('pre_bid_conference_date', $when($project->schedule?->pre_bid_conference_date)) }}" class="pe-input">
                        <p class="pe-hint">{{ $mode->prebidRule() }}</p>
                        <p class="pe-error" data-error-for="pre_bid_conference_date"></p>
                    </div>

                    <div class="pe-field">
                        <label class="pe-label" for="pe_deadline">Bid submission deadline <span class="pe-req" aria-hidden="true">*</span></label>
                        <input type="datetime-local" id="pe_deadline" name="deadline" value="{{ old('deadline', $when($project->deadline)) }}" required class="pe-input">
                        <p class="pe-error" data-error-for="deadline"></p>
                        <p class="pe-error" data-error-for="bid_submission_deadline"></p>
                    </div>

                    <div class="pe-field">
                        <label class="pe-label" for="pe_opening">Bid opening</label>
                        <input type="datetime-local" id="pe_opening" name="bid_opening_date" value="{{ old('bid_opening_date', $when($project->schedule?->bid_opening_date)) }}" class="pe-input">
                        <p class="pe-hint">Same day, right after the deadline.</p>
                        <p class="pe-error" data-error-for="bid_opening_date"></p>
                    </div>

                    @if($competitive)
                        <div class="pe-field">
                            <label class="pe-label" for="edit_bid_opening_venue">Place of bid opening</label>
                            <input type="text" id="edit_bid_opening_venue" name="bid_opening_venue" maxlength="500" value="{{ old('bid_opening_venue', $project->bid_opening_venue) }}" class="pe-input" placeholder="{{ trim(config('bac-office.contact.office').', '.config('bac-office.contact.address'), ', ') }}">
                            <p class="pe-error" data-error-for="bid_opening_venue"></p>
                        </div>
                    @endif

                    @if($competitive && $project->acceptsElectronicSubmission())
                        <div class="pe-field is-wide">
                            <label class="pe-label" for="edit_electronic_submission_authority">IT certification for electronic bids</label>
                            <input type="text" id="edit_electronic_submission_authority" name="electronic_submission_authority" maxlength="255" value="{{ old('electronic_submission_authority', $project->electronic_submission_authority) }}" class="pe-input" placeholder="e.g. MIS Certification No. 2026-03, submitted to GPPB-TSO">
                            <p class="pe-hint">Required before posting when bids are submitted online (IRR Sec. 50.3.3).</p>
                            <p class="pe-error" data-error-for="electronic_submission_authority"></p>
                        </div>
                    @endif
                    <p class="pe-error" data-error-for="date_posted"></p>
                </div>
            </section>

            {{-- Bidding documents fee --}}
            @php
                $feeRules = \App\Support\BiddingDocumentsFee::class;
                $fee = old('bidding_documents_fee', $project->bidding_documents_fee);
                $feeLock = \App\Http\Controllers\AdminController::feeLockReason($project);
                $feeCompetitive = $feeRules::appliesTo($project);
                $feeInfo = $feeRules::describe($project);
                // Legacy fees (entered before the schedule): an amount equal to the maximum is the schedule.
                $feeMode = old('bidding_fee_mode', $project->bidding_fee_mode ?? (
                    $fee === null || ($feeInfo['maximum'] !== null && (float) $fee === (float) $feeInfo['maximum']) ? 'schedule' : ((float) $fee > 0 ? 'reduced' : 'waived')
                ));
                $feePublished = $project->isPublishedLocally();
                $feeAmendments = $project->biddingFeeAmendments()->with('amender')->get();
            @endphp
            <section class="pe-section" aria-labelledby="pe-fee-title" data-pe-fee-section data-pe-fee-competitive="{{ $feeCompetitive ? '1' : '0' }}" data-pe-fee-schedule='@json($feeRules::schedule())'>
                <div class="pe-section-head">
                    <h3 id="pe-fee-title">Bidding documents fee</h3>
                    <p>
                        @if($feeCompetitive)
                            Maximum rates from the ABC under {{ $feeRules::BASIS }}. The BAC may charge less or waive the fee with a recorded reason, never more.
                        @else
                            Bidders pay this before they can submit. Leave it blank when the notice charges no fee.
                        @endif
                    </p>
                </div>

                @if($feeLock)
                    <p class="pe-hint" id="pe_fee_lock"><i class="fas fa-lock" aria-hidden="true"></i> {{ $feeLock }} Current fee: {{ $feeInfo['text'] }}</p>
                @endif

                <div class="pe-grid">
                    @if($feeCompetitive)
                        <div class="pe-field is-wide">
                            <p class="pe-fee-calc" data-pe-fee-calc aria-live="polite">{{ $feeInfo['bracket'] ? 'ABC ₱'.number_format((float) $project->budget, 2).': '.preg_replace('/^ABC /', '', $feeInfo['bracket']).', so the maximum fee is ₱'.number_format((float) $feeInfo['maximum'], 2).'.' : 'Set the ABC to compute the maximum fee.' }}</p>
                            <div class="pe-fee-modes" role="radiogroup" aria-label="How the fee is charged">
                                <label class="pe-fee-mode"><input type="radio" name="bidding_fee_mode" value="schedule" data-pe-fee-mode @checked($feeMode === 'schedule') @disabled($feeLock)> Charge the maximum <strong data-pe-fee-max>{{ $feeInfo['maximum'] ? '(₱'.number_format((float) $feeInfo['maximum'], 2).')' : '' }}</strong></label>
                                <label class="pe-fee-mode"><input type="radio" name="bidding_fee_mode" value="reduced" data-pe-fee-mode @checked($feeMode === 'reduced') @disabled($feeLock)> Charge a lower fee</label>
                                <label class="pe-fee-mode"><input type="radio" name="bidding_fee_mode" value="waived" data-pe-fee-mode @checked($feeMode === 'waived') @disabled($feeLock)> Waive the fee</label>
                            </div>
                            <p class="pe-error" data-error-for="bidding_fee_mode"></p>
                        </div>
                    @endif

                    <div class="pe-field" data-pe-fee-amount>
                        <label class="pe-label" for="pe_fee">{{ $feeCompetitive ? 'Lower fee' : 'Fee' }}</label>
                        <div class="pe-affix">
                            <span aria-hidden="true">&#8369;</span>
                            <input type="text" id="pe_fee" name="bidding_documents_fee" value="{{ filled($fee) && is_numeric($fee) ? number_format((float) $fee, 2) : $fee }}" inputmode="decimal" autocomplete="off" placeholder="0.00" class="pe-input pe-num" data-money data-pe-fee @disabled($feeLock) @if($feeLock) aria-describedby="pe_fee_lock" @endif>
                        </div>
                        <p class="pe-error" data-error-for="bidding_documents_fee"></p>
                    </div>

                    <div class="pe-field" data-pe-fee-venue>
                        <label class="pe-label" for="pe_payment_venue">Where to pay</label>
                        <input type="text" id="pe_payment_venue" name="payment_venue" maxlength="255" value="{{ old('payment_venue', $project->payment_venue ?: 'BAC Secretariat, Municipal Hall, San Jose, Occidental Mindoro') }}" class="pe-input" @disabled($feeLock)>
                        <p class="pe-error" data-error-for="payment_venue"></p>
                    </div>

                    @if($feeCompetitive)
                        <div class="pe-field is-wide" data-pe-fee-reason>
                            <label class="pe-label" for="pe_fee_reason">Reason for the lower fee or waiver</label>
                            <textarea id="pe_fee_reason" name="bidding_fee_reason" rows="2" maxlength="2000" class="pe-input" placeholder="e.g. Per BAC Resolution No. 2026-020" @disabled($feeLock)>{{ old('bidding_fee_reason', $project->bidding_fee_reason) }}</textarea>
                            <p class="pe-error" data-error-for="bidding_fee_reason"></p>
                        </div>
                    @endif

                    @if($feePublished && ! $feeLock)
                        <div class="pe-field is-wide pe-fee-amend" data-pe-fee-amend>
                            <p class="pe-hint"><i class="fas fa-bullhorn" aria-hidden="true"></i> This project is published. Bidders were told {{ lcfirst(rtrim($feeInfo['text'], '.')) }}. Changing the fee, including through a new ABC, is an amendment: fill these in only if the fee changes.</p>
                            <label class="pe-label" for="pe_fee_amend_ref">Amendment reference</label>
                            <input type="text" id="pe_fee_amend_ref" name="bidding_fee_amendment_reference" maxlength="255" class="pe-input" placeholder="e.g. Supplemental Bid Bulletin No. 1" value="{{ old('bidding_fee_amendment_reference') }}">
                            <p class="pe-error" data-error-for="bidding_fee_amendment_reference"></p>
                            <label class="pe-label" for="pe_fee_amend_reason">Why the published fee changes</label>
                            <textarea id="pe_fee_amend_reason" name="bidding_fee_amendment_reason" rows="2" maxlength="2000" class="pe-input">{{ old('bidding_fee_amendment_reason') }}</textarea>
                            <p class="pe-error" data-error-for="bidding_fee_amendment_reason"></p>
                        </div>
                    @endif

                    @if($feeAmendments->isNotEmpty())
                        <div class="pe-field is-wide">
                            <span class="pe-label">Fee amendments</span>
                            <ul class="pe-fee-history">
                                @foreach($feeAmendments as $amendment)
                                    <li>{{ $amendment->created_at?->format('M d, Y') }} · {{ $amendment->reference }}: ₱{{ number_format((float) $amendment->previous_fee, 2) }} → ₱{{ number_format((float) $amendment->new_fee, 2) }} ({{ $amendment->reason }}) · {{ $amendment->amender?->name ?? 'BAC' }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Evaluation --}}
            <section class="pe-section" aria-labelledby="pe-evaluation-title">
                <div class="pe-section-head">
                    <h3 id="pe-evaluation-title">Evaluation and award</h3>
                    <p>Set these to match the approved bidding documents. They guide the evaluation and never pick a winner automatically.</p>
                </div>
                <div class="pe-grid">
                    <div class="pe-field">
                        <label class="pe-label" for="edit_award_criterion">Award criterion</label>
                        <select id="edit_award_criterion" name="award_criterion" class="pe-input" data-pe-criterion>
                            <option value="">As stated in the signed bidding documents</option>
                            @foreach(\App\Models\Project::AWARD_CRITERIA as $criterionKey => $criterionLabel)
                                <option value="{{ $criterionKey }}" @selected($criterion === $criterionKey)>{{ $criterionLabel }}</option>
                            @endforeach
                        </select>
                        <p class="pe-error" data-error-for="award_criterion"></p>
                    </div>

                    @if($competitive && $project->category === 'consultancy')
                        <div class="pe-field">
                            <label class="pe-label" for="edit_evaluation_procedure">Evaluation procedure</label>
                            <select id="edit_evaluation_procedure" name="evaluation_procedure" class="pe-input">
                                <option value="">Select QBE or QCBE</option>
                                @foreach(\App\Models\Project::EVALUATION_PROCEDURES as $procedureKey => $procedureLabel)
                                    <option value="{{ $procedureKey }}" @selected(old('evaluation_procedure', $project->evaluation_procedure) === $procedureKey)>{{ $procedureLabel }}</option>
                                @endforeach
                            </select>
                            <p class="pe-error" data-error-for="evaluation_procedure"></p>
                        </div>
                    @endif
                </div>

                @if($competitive)
                    @php
                        $editCriteria = collect(old('evaluation_criteria', $project->evaluation_criteria ?? []))->values()->pad(5, ['name' => '', 'weight' => ''])->take(8);
                    @endphp
                    <div class="pe-weighted" data-pe-weighted @unless(in_array($criterion, ['mearb', 'marb'], true)) hidden @endunless>
                        <div class="pe-field pe-ratio" data-pe-mearb @unless($criterion === 'mearb') hidden @endunless>
                            <label class="pe-label" for="edit_quality_price_ratio">Quality-price ratio</label>
                            <div class="pe-affix is-suffix">
                                <input type="number" id="edit_quality_price_ratio" name="quality_price_ratio" min="1" max="99" step="1" value="{{ old('quality_price_ratio', $project->quality_price_ratio) }}" class="pe-input pe-num" placeholder="70">
                                <span aria-hidden="true">% technical</span>
                            </div>
                            <p class="pe-error" data-error-for="quality_price_ratio"></p>
                        </div>

                        <table class="pe-criteria">
                            <caption>Criteria and weights</caption>
                            <thead>
                                <tr><th scope="col">Criterion</th><th scope="col" class="is-num">Weight</th></tr>
                            </thead>
                            <tbody>
                                @foreach($editCriteria as $index => $row)
                                    <tr>
                                        <td><input type="text" name="evaluation_criteria[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}" maxlength="255" class="pe-input" placeholder="e.g. Technical merit" aria-label="Criterion {{ $index + 1 }}"></td>
                                        <td>
                                            <div class="pe-affix is-suffix">
                                                <input type="number" name="evaluation_criteria[{{ $index }}][weight]" value="{{ $row['weight'] ?? '' }}" min="0" max="100" step="0.01" class="pe-input pe-num" placeholder="0" aria-label="Weight {{ $index + 1 }}" data-pe-weight>
                                                <span aria-hidden="true">%</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr data-pe-total><th scope="row">Total (must be 100%)</th><td class="is-num"><span data-pe-weight-total>0%</span></td></tr>
                            </tfoot>
                        </table>
                        <p class="pe-error" data-error-for="evaluation_criteria"></p>
                    </div>
                @endif
            </section>

            {{-- Files --}}
            <section class="pe-section" aria-labelledby="pe-files-title">
                <div class="pe-section-head">
                    <h3 id="pe-files-title">Project files</h3>
                    <p>New files are added to the ones below. PDF, Word or images, up to 20 MB each.</p>
                </div>
                @if($projectDocuments->isNotEmpty())
                    <ul class="pe-files">
                        @foreach($projectDocuments as $documentIndex => $document)
                            <li>
                                <i class="fas fa-file-lines" aria-hidden="true"></i>
                                <a href="{{ route('admin.project.document.pdf', ['project' => $project, 'document' => $documentIndex]) }}" target="_blank" rel="noopener">{{ $document->display_name }}</a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="pe-empty">No files uploaded yet.</p>
                @endif
                <p class="pe-error" data-error-for="project_documents"></p>

                <div class="pe-field">
                    <label class="pe-label" for="pe_document_type">Type for added files</label>
                    <select id="pe_document_type" name="document_type" class="pe-input">
                        @foreach($editDocumentTypes as $documentType => $documentTypeLabel)
                            <option value="{{ $documentType }}" @selected(old('document_type', 'other') === $documentType)>{{ $documentTypeLabel }}</option>
                        @endforeach
                    </select>
                    <p class="pe-hint">This type applies to every file selected below. Choose Invitation to Bid for the official ITB.</p>
                    <p class="pe-error" data-error-for="document_type"></p>
                </div>
                <label class="pe-upload">
                    <input type="file" name="document_files[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="sr-only" data-pe-files>
                    <i class="fas fa-arrow-up-from-bracket" aria-hidden="true"></i>
                    <span><strong>Add files</strong> <span data-pe-file-names>No files chosen</span></span>
                </label>
                <p class="pe-error" data-error-for="document_files"></p>
            </section>
        </div>

        <footer class="pe-foot">
            <p class="pe-foot-note">
                @if($canPublish)
                    Saving keeps it as {{ strtolower(\Illuminate\Support\Str::headline($project->status)) }}. Publish makes it visible to bidders; PhilGEPS posting is recorded separately.
                @else
                    Saving updates the project details; the status stays {{ strtolower(\Illuminate\Support\Str::headline($project->status)) }}.
                @endif
            </p>
            <div class="pe-actions">
                <button type="button" onclick="closeEditModal()" class="pe-btn">Cancel</button>
                @if($canPublish)
                    <button type="button" id="editPublishBtn" class="pe-btn is-outline"><i class="fas fa-bullhorn" aria-hidden="true"></i> Publish to BAC System</button>
                @endif
                <button type="submit" id="editSubmitBtn" class="pe-btn is-primary">Save changes</button>
            </div>
        </footer>
    </form>
</div>

<style id="project-edit-ui">
    /* The modal shell on the projects page, sized for this form. */
    html body #editProjectModal#editProjectModal > div {
        display: flex !important;
        flex-direction: column !important;
        width: min(880px, calc(100vw - 40px)) !important;
        max-width: calc(100vw - 40px) !important;
        max-height: calc(100dvh - 40px) !important;
        padding: 0 !important;
        overflow: clip !important;
        border: 1px solid var(--ui-line) !important;
        border-radius: var(--ui-radius-lg) !important;
        background: var(--ui-surface) !important;
        box-shadow: var(--ui-shadow-lg) !important;
    }
    html body #editProjectModal#editProjectModal > div > button[onclick="closeEditModal()"] {
        top: 14px !important;
        right: 14px !important;
        width: 32px !important;
        height: 32px !important;
        border: 1px solid var(--ui-line) !important;
        border-radius: var(--ui-radius) !important;
        background: var(--ui-surface) !important;
        color: var(--ui-muted) !important;
        font-size: 20px !important;
        box-shadow: none !important;
        z-index: 4 !important;
    }
    html body #editProjectModal#editProjectModal > div > button[onclick="closeEditModal()"]:hover,
    html body #editProjectModal#editProjectModal > div > button[onclick="closeEditModal()"]:focus-visible {
        border-color: var(--ui-line-strong) !important;
        background: var(--ui-page) !important;
        color: var(--ui-ink) !important;
        outline: none !important;
        box-shadow: var(--ui-focus) !important;
    }
    html body #editProjectModal#editProjectModal #editModalBody {
        display: flex !important;
        flex: 1 1 auto !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: clip !important;
    }

    /* The form. IDs in the selectors outrank the dashboard-wide field rules. */
    #editProjectModal #editModalBody .pe { display: flex; flex: 1 1 auto; flex-direction: column; width: 100%; min-width: 0; min-height: 0; overflow: clip; color: var(--ui-ink) !important; font-family: var(--ui-font); font-size: var(--ui-text); }
    #editProjectModal #editModalBody .pe :is(h2, h3, p, ul, table) { margin: 0; }

    #editProjectModal #editModalBody .pe-head { flex: 0 0 auto; padding: 18px 64px 16px 24px; border-bottom: 1px solid var(--ui-line); background: var(--ui-surface); }
    #editProjectModal #editModalBody .pe-eyebrow { color: var(--ui-primary) !important; font-size: var(--ui-text-xs); font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    #editProjectModal #editModalBody .pe-head h2 { display: -webkit-box; margin-top: 3px; overflow: hidden; color: var(--ui-ink) !important; font-size: 19px; font-weight: 700; line-height: 1.3; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow-wrap: anywhere; }
    #editProjectModal #editModalBody .pe-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 12px; margin-top: 8px; color: var(--ui-muted) !important; font-size: var(--ui-text-sm); }
    #editProjectModal #editModalBody .pe-meta i { margin-right: 4px; color: var(--ui-subtle) !important; }
    #editProjectModal #editModalBody .pe-mono { font-family: var(--ui-mono); font-size: .95em; }
    #editProjectModal #editModalBody .pe-pill { display: inline-flex; align-items: center; padding: 2px 9px; border-radius: 999px; background: var(--ui-line-soft); color: var(--ui-ink-2) !important; font-size: var(--ui-text-xs); font-weight: 700; }
    #editProjectModal #editModalBody .pe-pill.is-success { background: var(--ui-success-soft); color: var(--ui-success) !important; }
    #editProjectModal #editModalBody .pe-pill.is-info { background: var(--ui-info-soft); color: var(--ui-info) !important; }
    #editProjectModal #editModalBody .pe-pill.is-warning { background: var(--ui-warning-soft); color: var(--ui-warning) !important; }
    #editProjectModal #editModalBody .pe-pill.is-primary { background: var(--ui-primary-soft); color: var(--ui-primary) !important; }

    #editProjectModal #editModalBody .pe-form { display: flex; flex: 1 1 auto; flex-direction: column; min-height: 0; margin: 0; overflow: clip; }
    #editProjectModal #editModalBody .pe-body { display: grid; flex: 1 1 auto; gap: 14px; min-height: 0; padding: 16px 24px 20px; overflow-x: hidden; overflow-y: auto; background: var(--ui-page); scrollbar-gutter: stable; }

    #editProjectModal #editModalBody .pe-alert { padding: 10px 12px; border: 1px solid var(--ui-danger-line); border-radius: var(--ui-radius); background: var(--ui-danger-soft); color: var(--ui-danger) !important; font-size: var(--ui-text-sm); line-height: 1.5; }

    #editProjectModal #editModalBody .pe-section { min-width: 0; padding: 16px 18px 18px; border: 1px solid var(--ui-line); border-radius: var(--ui-radius-lg); background: var(--ui-surface); }
    #editProjectModal #editModalBody .pe-section-head { margin-bottom: 14px; }
    #editProjectModal #editModalBody .pe-section-head h3 { color: var(--ui-ink) !important; font-size: var(--ui-text-lg); font-weight: 700; line-height: 1.3; }
    #editProjectModal #editModalBody .pe-section-head p { margin-top: 3px; color: var(--ui-muted) !important; font-size: var(--ui-text-sm); line-height: 1.5; }

    #editProjectModal #editModalBody .pe-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 16px; }
    #editProjectModal #editModalBody .pe-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
    #editProjectModal #editModalBody .pe-field.is-wide { grid-column: 1 / -1; }
    #editProjectModal #editModalBody .pe-label { margin: 0; color: var(--ui-ink-2) !important; font-size: var(--ui-text-sm); font-weight: 600; line-height: 1.3; letter-spacing: normal; text-transform: none; }
    #editProjectModal #editModalBody .pe-req { color: var(--ui-danger) !important; }
    #editProjectModal #editModalBody .pe-hint { color: var(--ui-subtle) !important; font-size: var(--ui-text-xs); line-height: 1.45; }
    #editProjectModal #editModalBody .pe-error { color: var(--ui-danger) !important; font-size: var(--ui-text-xs); font-weight: 600; line-height: 1.4; }
    #editProjectModal #editModalBody .pe-error:empty { display: none; }
    #editProjectModal #editModalBody .pe-sub { margin-top: 2px; }
    #editProjectModal #editModalBody .pe-sub[hidden], #editProjectModal #editModalBody [hidden] { display: none !important; }

    #editProjectModal #editModalBody .pe-input {
        display: block !important;
        width: 100% !important;
        min-width: 0 !important;
        max-width: none !important;
        min-height: 38px !important;
        height: auto !important;
        margin: 0 !important;
        padding: 8px 11px !important;
        border: 1px solid var(--ui-line-strong) !important;
        border-radius: var(--ui-radius) !important;
        background: var(--ui-surface) !important;
        color: var(--ui-ink) !important;
        -webkit-text-fill-color: currentColor !important;
        font: 400 var(--ui-text)/1.4 var(--ui-font) !important;
        box-shadow: none !important;
        box-sizing: border-box !important;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    #editProjectModal #editModalBody textarea.pe-input { min-height: 84px !important; resize: vertical !important; line-height: 1.5 !important; }
    #editProjectModal #editModalBody select.pe-input { padding-right: 30px !important; text-overflow: ellipsis; }
    #editProjectModal #editModalBody .pe-input::placeholder { color: #9aa19c !important; -webkit-text-fill-color: #9aa19c !important; }
    #editProjectModal #editModalBody .pe-input:hover { border-color: #b9b19f !important; }
    #editProjectModal #editModalBody .pe-input:focus { border-color: var(--ui-focus-color) !important; outline: none !important; box-shadow: var(--ui-focus) !important; }
    #editProjectModal #editModalBody .pe-input:disabled { background: var(--ui-page) !important; color: var(--ui-muted) !important; cursor: not-allowed !important; }
    #editProjectModal #editModalBody .pe-input.input-error { border-color: #d9776c !important; box-shadow: 0 0 0 3px rgba(160, 50, 43, .12) !important; }
    #editProjectModal #editModalBody .pe-num { font-variant-numeric: tabular-nums !important; }

    #editProjectModal #editModalBody .pe-fee-calc { margin: 0; padding: 9px 12px; border: 1px solid var(--ui-line); border-radius: var(--ui-radius); background: var(--ui-page) !important; color: var(--ui-ink) !important; font-size: 13px; font-weight: 600; }
    #editProjectModal #editModalBody .pe-fee-modes { display: flex; flex-wrap: wrap; gap: 6px 18px; }
    #editProjectModal #editModalBody .pe-fee-mode { display: inline-flex; align-items: center; gap: 7px; color: var(--ui-ink) !important; font-size: 13px; cursor: pointer; }
    #editProjectModal #editModalBody .pe-fee-mode input { width: 16px; height: 16px; margin: 0; accent-color: var(--ui-primary); }
    #editProjectModal #editModalBody .pe-fee-amend { padding: 12px; border: 1px solid var(--ui-warning-line); border-radius: var(--ui-radius); background: var(--ui-warning-soft) !important; }
    #editProjectModal #editModalBody .pe-fee-history { display: grid; gap: 4px; margin: 0; padding-left: 18px; color: var(--ui-ink-2) !important; font-size: var(--ui-text-xs); }
    #editProjectModal #editModalBody [data-pe-fee-amount][hidden], #editProjectModal #editModalBody [data-pe-fee-reason][hidden], #editProjectModal #editModalBody [data-pe-fee-venue][hidden] { display: none !important; }
    #editProjectModal #editModalBody .pe-affix { position: relative; }
    #editProjectModal #editModalBody .pe-affix > span { position: absolute; top: 50%; left: 11px; color: var(--ui-subtle) !important; font-weight: 600; pointer-events: none; transform: translateY(-50%); }
    #editProjectModal #editModalBody .pe-affix > .pe-input { padding-left: 28px !important; }
    #editProjectModal #editModalBody .pe-affix.is-suffix > span { right: 11px; left: auto; font-weight: 500; font-size: var(--ui-text-sm); }
    #editProjectModal #editModalBody .pe-affix.is-suffix > .pe-input { padding-right: 34px !important; padding-left: 11px !important; }
    #editProjectModal #editModalBody .pe-ratio .pe-affix.is-suffix > .pe-input { padding-right: 84px !important; }

    #editProjectModal #editModalBody .pe-weighted { display: grid; gap: 12px; margin-top: 16px; padding-top: 16px; border-top: 1px dashed var(--ui-line-strong); }
    #editProjectModal #editModalBody .pe-ratio { max-width: 260px; }
    #editProjectModal #editModalBody .pe-criteria { width: 100%; border-collapse: separate; border-spacing: 0; }
    #editProjectModal #editModalBody .pe-criteria caption { padding-bottom: 6px; color: var(--ui-ink-2) !important; font-size: var(--ui-text-sm); font-weight: 600; text-align: left; }
    #editProjectModal #editModalBody .pe-criteria th { padding: 0 0 6px; color: var(--ui-subtle) !important; font-size: var(--ui-text-xs); font-weight: 700; letter-spacing: .04em; text-align: left; text-transform: uppercase; }
    #editProjectModal #editModalBody .pe-criteria td { padding: 0 0 8px; vertical-align: top; }
    #editProjectModal #editModalBody .pe-criteria td + td, #editProjectModal #editModalBody .pe-criteria th + th { width: 130px; padding-left: 10px; }
    #editProjectModal #editModalBody .pe-criteria .is-num { text-align: right; }
    #editProjectModal #editModalBody .pe-criteria tfoot th, #editProjectModal #editModalBody .pe-criteria tfoot td { padding: 8px 0 0; border-top: 1px solid var(--ui-line); color: var(--ui-ink-2) !important; font-size: var(--ui-text-sm); font-weight: 600; letter-spacing: normal; text-transform: none; }
    #editProjectModal #editModalBody .pe-criteria tfoot td { padding-right: 12px; font-variant-numeric: tabular-nums; }
    #editProjectModal #editModalBody .pe-criteria tr.is-off td { color: var(--ui-warning) !important; }

    #editProjectModal #editModalBody .pe-files { display: grid; gap: 6px; padding: 0; list-style: none; }
    #editProjectModal #editModalBody .pe-files li { display: flex; align-items: center; gap: 9px; min-width: 0; padding: 8px 11px; border: 1px solid var(--ui-line); border-radius: var(--ui-radius); background: var(--ui-page); }
    #editProjectModal #editModalBody .pe-files i { color: var(--ui-subtle) !important; }
    #editProjectModal #editModalBody .pe-files a { min-width: 0; overflow: hidden; color: var(--ui-primary) !important; font-size: var(--ui-text-sm); font-weight: 600; text-decoration: none; text-overflow: ellipsis; white-space: nowrap; }
    #editProjectModal #editModalBody .pe-files a:hover { text-decoration: underline; }
    #editProjectModal #editModalBody .pe-empty { padding: 10px 12px; border: 1px dashed var(--ui-line-strong); border-radius: var(--ui-radius); color: var(--ui-muted) !important; font-size: var(--ui-text-sm); }
    #editProjectModal #editModalBody .pe-upload { display: flex; align-items: center; gap: 10px; margin-top: 10px; padding: 11px 13px; border: 1px dashed var(--ui-primary-line); border-radius: var(--ui-radius); background: var(--ui-primary-soft); color: var(--ui-muted) !important; font-size: var(--ui-text-sm); cursor: pointer; }
    #editProjectModal #editModalBody .pe-upload i { color: var(--ui-primary) !important; }
    #editProjectModal #editModalBody .pe-upload strong { margin-right: 6px; color: var(--ui-primary) !important; }
    #editProjectModal #editModalBody .pe-upload:hover { border-color: var(--ui-primary); }
    #editProjectModal #editModalBody .pe-upload:focus-within { border-color: var(--ui-focus-color); box-shadow: var(--ui-focus); }

    #editProjectModal #editModalBody .pe-foot { display: flex; flex: 0 0 auto; align-items: center; justify-content: space-between; gap: 12px 18px; padding: 12px 24px; border-top: 1px solid var(--ui-line); background: var(--ui-surface); }
    #editProjectModal #editModalBody .pe-foot-note { max-width: 380px; color: var(--ui-subtle) !important; font-size: var(--ui-text-xs); line-height: 1.45; }
    #editProjectModal #editModalBody .pe-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; }
    #editProjectModal #editModalBody .pe-btn {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 7px !important;
        min-width: 0 !important;
        min-height: 36px !important;
        margin: 0 !important;
        padding: 0 14px !important;
        border: 1px solid var(--ui-line-strong) !important;
        border-radius: var(--ui-radius) !important;
        background: var(--ui-surface) !important;
        color: var(--ui-ink-2) !important;
        font: 600 var(--ui-text-sm)/1 var(--ui-font) !important;
        white-space: nowrap !important;
        box-shadow: none !important;
        cursor: pointer !important;
    }
    #editProjectModal #editModalBody .pe-btn:hover { border-color: var(--ui-subtle) !important; background: var(--ui-page) !important; }
    #editProjectModal #editModalBody .pe-btn:focus-visible { outline: none !important; box-shadow: var(--ui-focus) !important; }
    #editProjectModal #editModalBody .pe-btn:disabled { opacity: .7 !important; cursor: progress !important; }
    #editProjectModal #editModalBody .pe-btn.is-outline { border-color: var(--ui-primary-line) !important; color: var(--ui-primary) !important; }
    #editProjectModal #editModalBody .pe-btn.is-outline:hover { background: var(--ui-primary-soft) !important; }
    #editProjectModal #editModalBody .pe-btn.is-primary { border-color: var(--ui-primary) !important; background: var(--ui-primary) !important; color: #fff !important; }
    #editProjectModal #editModalBody .pe-btn.is-primary:hover { border-color: var(--ui-primary-hover) !important; background: var(--ui-primary-hover) !important; }

    @media (max-width: 700px) {
        html body #editProjectModal#editProjectModal { padding: 10px !important; }
        html body #editProjectModal#editProjectModal > div { width: calc(100vw - 20px) !important; max-width: calc(100vw - 20px) !important; max-height: calc(100dvh - 20px) !important; }
        #editProjectModal #editModalBody .pe-head { padding: 16px 56px 14px 16px; }
        #editProjectModal #editModalBody .pe-body { padding: 12px; }
        #editProjectModal #editModalBody .pe-section { padding: 14px; }
        #editProjectModal #editModalBody .pe-grid { grid-template-columns: 1fr; }
        #editProjectModal #editModalBody .pe-ratio { max-width: none; }
        #editProjectModal #editModalBody .pe-criteria td + td, #editProjectModal #editModalBody .pe-criteria th + th { width: 104px; }
        #editProjectModal #editModalBody .pe-foot { flex-direction: column; align-items: stretch; padding: 12px 16px; }
        #editProjectModal #editModalBody .pe-actions .pe-btn { flex: 1 1 auto !important; }
    }
</style>
