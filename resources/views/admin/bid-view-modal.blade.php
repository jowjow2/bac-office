@php
    /** @var \App\Models\Bid $bid */
    $project = $bid->project;
    $budget = (float) ($project?->budget ?? 0);
    $amount = (float) $bid->amount;
    $bidderName = $bid->user?->company ?: ($bid->user?->name ?? 'N/A');
    $sealed = $bid->isSealed();
    $financialSealed = $bid->isFinancialSealed();
    $proposalPreviewUrl = ! $financialSealed && $bid->proposal_url ? route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'proposal']) : null;
    $certificatePreviewUrl = ! $sealed && $bid->user?->philgepsCertificate?->file_url
        ? route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'certificate'])
        : null;
    $tz = config('bac-office.display_timezone');
    $mode = $project?->mode();
    $competitive = (bool) $mode?->isCompetitive();
    $reviewLabel = $competitive ? 'Review Bid' : 'Review '.ucfirst($mode?->submissionNoun() ?? 'submission');
    $openingRequired = $project?->requiresRecordedBidOpening() ?? true;
    $openingAt = $openingRequired ? $project?->schedule?->bid_opening_date : null;
    $deadline = $project?->bidSubmissionDeadline();
    $openingActor = $project?->bidsOpenedByUser;
    $documentStatus = $bid->submissionDocumentStatus();
    $status ??= $bid->progress()->adminStatus();
    $actions ??= [];
    $modalActionLabels = [
        'evaluate' => 'Record Evaluation Result',
        'fail_evaluation' => 'Mark as Failed',
    ];
    $checklist ??= $bid->reviewChecklist();
    $evaluationCriteria ??= app(\App\Support\BidWorkflow::class)->evaluationCriteria($bid);
    $history ??= \App\Support\BidHistory::for($bid)->forAdmin();
    $ranking ??= null;
    $missingRequirements = collect($checklist)->where('submitted', false)->filter(fn ($item) => $item['required'] ?? true);
    $prelimEvent = collect($bid->trackings ?? [])->filter(fn ($event) => $event->stage === \App\Support\BidProgress::STAGE_PRELIMINARY && in_array($event->decision, ['passed', 'failed'], true))->sortByDesc('created_at')->first();
    $prelimResults = collect($prelimEvent?->details['requirements'] ?? [])->keyBy('key');
    $componentFiles = $bid->documents->groupBy('component');
    $channelLabel = match ($bid->submission_channel) {
        \App\Models\Bid::CHANNEL_ELECTRONIC => 'Online',
        \App\Models\Bid::CHANNEL_MANUAL => 'Manual, sealed bid at the BAC Secretariat',
        default => 'Website (before submission channels)',
    };
    $feePayment = $project?->biddingFeePaymentFor($bid->user_id);

    // Component openings are recorded events by the BAC Admin, never a schedule.
    $technicalOpened = (bool) $project?->bidsAreOpened();
    $criterionLabels = ['lowest_calculated_bid' => 'LCRB', 'mearb' => 'MEARB', 'marb' => 'MARB'];
    $criterion = in_array($project?->award_criterion, \App\Support\BidOpening::CRITERIA, true) ? $project->award_criterion : null;
    $rulesRecorded = $criterion && filled($project?->opening_documents_reference);
    $rulesLocked = $technicalOpened && filled($project?->opening_documents_reference);
    $scored = in_array($criterion, ['mearb', 'marb'], true);
    $canScore = $scored && $rulesRecorded && $technicalOpened && ! $bid->isDraft() && $bid->documents_validated_at
        && ! $bid->technical_scored_at && ! $bid->financial_opened_at;
    $financialOpened = $bid->financial_opened_at && $bid->financial_opened_by;
    $financialBlocker = $openingRequired && $project && ! $bid->isDraft() ? app(\App\Support\BidOpening::class)->financialBlocker($bid) : null;
    $technicalBlocker = $openingBlocker ?? $project?->bidOpeningBlocker();
    $manila = fn ($at, $format = 'M d, Y h:i A') => $at?->timezone('Asia/Manila')->format($format);


    // Select the next stage before checking guards so a blocked stage is never skipped.
    $workflow = app(\App\Support\BidWorkflow::class);
    $progress = $bid->progress();
    $facts = $progress->facts();
    $nextAction = match (true) {
        $bid->isDraft() => $bid->submission_channel === \App\Models\Bid::CHANNEL_MANUAL ? 'record_manual_receipt' : 'submit-bid',
        $progress->isClosed(), $facts['notice_to_proceed_at'] !== null => null,
        // Resolve the earliest unmet review gate before trusting any later award fields.
        $openingRequired && ! $technicalOpened => 'technical-opening',
        ! $facts['prelim_passed'] => 'pass_preliminary',
        $openingRequired && ! $financialOpened => 'financial-opening',
        $facts['evaluation_started_at'] === null && ! $facts['evaluated'] => 'start_evaluation',
        ! $facts['evaluated'] => 'evaluate',
        $facts['post_qualification_result'] === null && $facts['post_qualification_started'] => 'pass_post_qualification',
        $facts['post_qualification_result'] === null => 'start_post_qualification',
        $facts['post_qualification_result'] === \App\Models\Bid::POST_QUALIFICATION_PASSED && ! $facts['recommended'] => 'recommend',
        $facts['award_approved'] && $facts['notice_of_award_at'] === null => 'notice_of_award',
        $facts['recommended'] && ! $facts['award_approved'] => 'approve_award',
        $facts['notice_of_award_at'] !== null && $facts['contract_signed_at'] === null => 'contract_signed',
        $facts['contract_signed_at'] !== null && $facts['notice_to_proceed_at'] === null => 'notice_to_proceed',
        default => null,
    };
    $selectedAction = array_key_exists((string) $nextAction, $actions) ? $nextAction : null;
    $nextLabel = match ($nextAction) {
        null => 'None',
        'submit-bid' => 'Bidder submission',
        'technical-opening' => 'Wait for scheduled opening',
        'financial-opening' => 'Verify Financial Password',
        default => $modalActionLabels[$nextAction] ?? $workflow::label($nextAction),
    };
    $proceedBlocker = match ($nextAction) {
        null => $progress->isClosed() ? 'This bid is closed. No further decision can be recorded.' : 'All bid workflow stages are complete.',
        'submit-bid' => 'This is an unsubmitted draft. The bidder must submit it before the deadline.',
        'technical-opening' => $project ? $technicalBlocker : 'The bid has no project to open.',
        'financial-opening' => $financialBlocker,
        default => $workflow->guardError($bid, $nextAction, auth()->user()),
    };
    if ($nextAction === 'pass_preliminary' && ! $proceedBlocker && $missingRequirements->isNotEmpty()) {
        $proceedBlocker = 'Missing required documents: '.$missingRequirements->pluck('label')->implode(', ').'. Record a failed preliminary examination instead.';
    }
    if (auth()->user()?->role !== 'admin' || auth()->user()?->status !== 'active') {
        $proceedBlocker = 'Only an active BAC Admin is authorized to proceed.';
    }

    // Files the reviewer may read inline; sealed components never get a URL.
    $previews = [];
    if ($proposalPreviewUrl) $previews[] = ['url' => $proposalPreviewUrl, 'title' => 'Proposal file', 'name' => $bid->proposal_filename ?: 'Proposal file'];
    foreach (['technical' => $sealed, 'financial' => $financialSealed] as $componentKey => $componentSealed) {
        if ($componentSealed) continue;
        foreach ($componentFiles[$componentKey] ?? [] as $file) {
            $previews[] = ['url' => route('admin.bid.component-file', ['bid' => $bid, 'bidDocument' => $file]), 'title' => $file->label, 'name' => $file->original_name];
        }
    }
    if ($certificatePreviewUrl) $previews[] = ['url' => $certificatePreviewUrl, 'title' => 'Certificate Proof', 'name' => $bid->user?->philgepsCertificate?->display_name ?: 'Certificate proof'];
    $firstPreview = $previews[0] ?? null;
    $documentCount = $bid->documents->count() + ($bid->proposal_file ? 1 : 0) + ($bid->user?->philgepsCertificate ? 1 : 0);
@endphp

<div class="br" data-bid-review-modal data-bid-id="{{ $bid->id }}" data-technical-open="{{ $technicalOpened ? '1' : '0' }}">
    <header class="br-head">
        <div class="br-head__main">
            <p class="br-eyebrow">
                <span>{{ $reviewLabel }}</span>
                @if($project?->reference_no)<span aria-hidden="true">&middot;</span><span class="br-mono">{{ $project->reference_no }}</span>@endif
            </p>
            <h2 class="br-title">{{ $bidderName }}</h2>
            <p class="br-subtitle">{{ $project?->title ?? 'Project not found' }}</p>
            <ul class="br-tags" aria-label="Procurement details">
                <li><i class="fas fa-gavel" aria-hidden="true"></i> {{ $mode?->label() ?? 'Mode not specified' }} &middot; {{ $mode?->legalBasisShort() ?? 'Regime not recorded' }}</li>
                @if($criterion)
                    <li><i class="fas fa-scale-balanced" aria-hidden="true"></i> {{ $criterionLabels[$criterion] }}</li>
                @endif
                <li><i class="fas {{ $bid->submission_channel === \App\Models\Bid::CHANNEL_MANUAL ? 'fa-envelope' : 'fa-globe' }}" aria-hidden="true"></i> {{ $bid->submission_channel === \App\Models\Bid::CHANNEL_MANUAL ? 'Manual submission' : 'Online submission' }}</li>
                @if(($ranking['status'] ?? null) && $ranking['status'] !== \App\Support\BidRanking::SEALED)
                    <li class="br-tag-rank"><x-bid-rank :ranking="$ranking" /></li>
                @endif
            </ul>
        </div>
        <div class="br-head__status">
            <x-bid-status-badge :bid="$bid" />
        </div>
    </header>

    <div class="br-body">
        <div class="br-main">
            @if($sealed)
                <div class="br-banner is-sealed" role="status">
                    <i class="fas fa-lock" aria-hidden="true"></i>
                    <div>
                        <strong>Submission sealed</strong>
                        <span>Technical and eligibility files open automatically at the scheduled bid-opening time. Financial Bid and the bid amount remain sealed until the technical review is approved and the password is verified.</span>
                        @if($openingAt)
                            <small>Scheduled {{ $openingAt->timezone($tz)->format('M d, Y h:i A') }} (Asia/Manila). Technical and eligibility files open automatically at this time.</small>
                        @endif
                    </div>
                </div>
            @elseif(! $openingRequired)
                <div class="br-banner is-info" role="status">
                    <i class="fas fa-list-check" aria-hidden="true"></i>
                    <div>
                        <span>{{ $mode?->label() ?? 'Alternative procurement mode' }} uses quotation/offer review after the {{ strtolower($mode?->deadlineLabel() ?? 'deadline') }}; no separate competitive bid-opening event is required.</span>
                    </div>
                </div>
            @endif

            <dl class="br-kpis">
                <div class="br-kpi">
                    <dt>Bid amount</dt>
                    <dd class="br-kpi__value {{ $financialSealed ? 'is-sealed' : '' }}">
                        @if($financialSealed)
                            <i class="fas fa-lock" aria-hidden="true"></i> Sealed
                        @else
                            &#8369;{{ number_format($amount, 2) }}
                        @endif
                    </dd>
                </div>
                <div class="br-kpi">
                    <dt>Approved budget (ABC)</dt>
                    <dd class="br-kpi__value">&#8369;{{ number_format($budget, 2) }}</dd>
                </div>
                <div class="br-kpi">
                    <dt>Variance vs ABC</dt>
                    <dd class="br-kpi__value">@if($financialSealed)<span class="br-muted">&mdash;</span>@else<x-bid-variance :bid="$bid" />@endif</dd>
                </div>
                <div class="br-kpi">
                    <dt>Submission</dt>
                    <dd><span class="br-pill is-{{ $documentStatus['key'] }}">{{ $documentStatus['label'] }}</span></dd>
                </div>
            </dl>

            <div class="br-tabs" role="tablist" aria-label="Bid review sections">
                <button type="button" role="tab" id="br-tab-overview" aria-controls="br-panel-overview" aria-selected="true" class="br-tab is-active" data-br-tab="overview">Overview</button>
                <button type="button" role="tab" id="br-tab-documents" aria-controls="br-panel-documents" aria-selected="false" class="br-tab" data-br-tab="documents" tabindex="-1">Documents <span class="br-count">{{ $documentCount }}</span></button>
                <button type="button" role="tab" id="br-tab-checklist" aria-controls="br-panel-checklist" aria-selected="false" class="br-tab" data-br-tab="checklist" tabindex="-1">Checklist <span class="br-count">{{ count($checklist) }}</span></button>
                <button type="button" role="tab" id="br-tab-history" aria-controls="br-panel-history" aria-selected="false" class="br-tab" data-br-tab="history" tabindex="-1"><span class="br-hide-sm">Activity</span>History <span class="br-count">{{ count($history) }}</span></button>
            </div>

            {{-- Overview --}}
            <section class="br-panel" role="tabpanel" id="br-panel-overview" aria-labelledby="br-tab-overview" data-br-panel="overview">
                <dl class="br-facts">
                    <div><dt>Bidder / Company</dt><dd>{{ $bidderName }}</dd></div>
                    <div><dt>Email</dt><dd class="br-break">{{ $bid->user?->email ?? 'N/A' }}</dd></div>
                    <div class="is-wide"><dt>Project</dt><dd>{{ $project?->reference_no ? $project->reference_no.' - ' : '' }}{{ $project?->title ?? 'N/A' }}</dd></div>
                    <div><dt>Procurement mode</dt><dd>{{ $mode?->label() ?? 'Not specified' }} &middot; {{ $mode?->legalBasisShort() ?? 'Regime not recorded' }}</dd></div>
                    <div><dt>Award criterion</dt><dd>{{ \App\Models\Project::AWARD_CRITERIA[$project?->award_criterion] ?? 'Not separately configured - use the signed bidding documents' }}</dd></div>
                    <div><dt>Channel</dt><dd>{{ $channelLabel }}</dd></div>
                    <div>
                        <dt>Officially submitted</dt>
                        <dd>
                            @if($bid->isDraft())
                                Not submitted &mdash; draft only
                            @else
                                {{ ($bid->submitted_at ?? $bid->created_at)?->timezone($tz)->format('M d, Y h:i:s A') }}
                            @endif
                        </dd>
                    </div>
                    <div><dt>Receipt / reference No.</dt><dd class="br-mono">{{ $bid->receipt_no ?: 'Not recorded' }}</dd></div>
                    <div>
                        <dt>Bidding fee</dt>
                        <dd>
                            @if($feePayment)
                                Paid &#8369;{{ number_format((float) $feePayment->amount, 2) }} &middot; OR {{ $feePayment->or_number }} ({{ $feePayment->paid_at->format('M d, Y') }})
                            @elseif($project?->requiresBiddingFee())
                                No payment recorded
                            @else
                                No fee for this project
                            @endif
                        </dd>
                    </div>
                    <div class="is-wide">
                        <dt>Bid opening</dt>
                        <dd>
                            @if($technicalOpened)
                                Opened automatically {{ $manila($project->bids_opened_at, 'M d, Y h:i:s A') }} (Asia/Manila)
                            @elseif($openingAt)
                                Scheduled {{ $openingAt->timezone($tz)->format('M d, Y h:i A') }}
                            @elseif($openingRequired)
                                Not yet opened
                            @else
                                Not required for this mode
                            @endif
                        </dd>
                    </div>
                </dl>

                @if($facts['recommended'] && $facts['post_qualification_result'] === \App\Models\Bid::POST_QUALIFICATION_PASSED)
                    <section class="br-note br-award-documents-panel" aria-labelledby="br-award-documents-title">
                        <p class="br-note__label" id="br-award-documents-title"><i class="fas fa-file-circle-check" aria-hidden="true"></i> BAC recommendation documents</p>
                        <p>Download or print these records to present the recommendation to the HoPE. They do not record the HoPE's decision.</p>
                        <div class="br-award-documents">
                            <a class="br-btn" href="{{ route('admin.bid.award-recommendation.document', ['bid' => $bid, 'document' => 'resolution']) }}" target="_blank" rel="noopener">BAC Resolution (PDF)</a>
                            <a class="br-btn" href="{{ route('admin.bid.award-recommendation.document', ['bid' => $bid, 'document' => 'post-qualification-report']) }}" target="_blank" rel="noopener">Post-Qualification Report (PDF)</a>
                        </div>
                    </section>
                @endif

                @unless($sealed)
                    <a href="{{ route('admin.bid.edit', $bid) }}" onclick="event.preventDefault(); loadBidEditModal({{ $bid->id }});" class="br-btn">
                        <i class="fas fa-pen-to-square" aria-hidden="true"></i> Internal Notes
                    </a>
                @endunless

                @if(filled($bid->notes))
                    <div class="br-note">
                        <p class="br-note__label"><i class="fas fa-note-sticky" aria-hidden="true"></i> Internal notes (not shown to bidder)</p>
                        <p>{{ $bid->notes }}</p>
                    </div>
                @endif
            </section>

            {{-- Documents --}}
            <section class="br-panel" role="tabpanel" id="br-panel-documents" aria-labelledby="br-tab-documents" data-br-panel="documents" hidden>
                @if($componentFiles->isNotEmpty())
                    <p class="br-hint">Technical & Eligibility files open at the scheduled time. Financial Bid stays sealed until technical approval and password verification.{{ $bid->isDraft() ? ' These are draft copies, not an official submission.' : '' }}</p>
                    @foreach(['technical' => 'Technical Component (including Eligibility Documents)', 'financial' => 'Financial Component'] as $componentKey => $componentLabel)
                        @php
                            $componentSealed = $componentKey === 'financial' ? $financialSealed : $sealed;
                            $filesInComponent = $componentFiles[$componentKey] ?? collect();
                        @endphp
                        <div class="br-group">
                            <div class="br-group__head">
                                <h3>{{ $componentLabel }}</h3>
                                <span class="br-state {{ $componentSealed ? 'is-sealed' : 'is-open' }}">
                                    <i class="fas {{ $componentSealed ? 'fa-lock' : 'fa-lock-open' }}" aria-hidden="true"></i>
                                    {{ $componentSealed ? 'Sealed' : 'Open' }} &middot; {{ $filesInComponent->count() }} {{ \Illuminate\Support\Str::plural('file', $filesInComponent->count()) }}
                                </span>
                            </div>
                            <ul class="br-files">
                                @forelse($filesInComponent as $file)
                                    @php $fileUrl = ($componentSealed || blank($file->file_path)) ? null : route('admin.bid.component-file', ['bid' => $bid, 'bidDocument' => $file]); @endphp
                                    <li class="br-file {{ $fileUrl && $firstPreview && $firstPreview['url'] === $fileUrl ? 'is-active' : '' }}">
                                        <span class="br-file__icon {{ $componentSealed ? 'is-sealed' : '' }}"><i class="fas {{ $componentSealed ? 'fa-lock' : 'fa-file-lines' }}" aria-hidden="true"></i></span>
                                        <span class="br-file__text">
                                            <strong>{{ $file->label }}</strong>
                                            @if($componentSealed)
                                                <small>Received &middot; sealed</small>
                                            @else
                                                <small>
                                                @if(filled($file->file_path))
                                                    {{ $file->original_name }} &middot; SHA-256 <span class="br-mono">{{ substr((string) $file->sha256, 0, 12) }}</span>
                                                @else
                                                    Missing upload requested by the BAC
                                                @endif
                                            </small>
                                            @endif
                                        </span>
                                        @if($componentKey === 'technical' && ! $componentSealed && ! $bid->isDraft())
                                            @php
                                                $reviewEvents = $file->reviewEvents;
                                                $currentReview = $reviewEvents->last();
                                                $reviewStatus = $currentReview?->status ?? \App\Models\BidDocumentReviewEvent::STATUS_PENDING;
                                                $reviewStatusLabel = match ($reviewStatus) {
                                                    \App\Models\BidDocumentReviewEvent::STATUS_ACCEPTED => 'Accepted',
                                                    \App\Models\BidDocumentReviewEvent::STATUS_NEEDS_REVISION => 'Needs Revision',
                                                    default => 'Awaiting Review',
                                                };
                                            @endphp
                                            <div class="br-doc-review">
                                                <span class="br-doc-review__status is-{{ $reviewStatus }}">{{ $reviewStatusLabel }} · Version {{ $currentReview?->version ?? 1 }}</span>
                                                @if($reviewStatus === \App\Models\BidDocumentReviewEvent::STATUS_NEEDS_REVISION && filled($currentReview?->comment))
                                                    <p class="br-doc-review__comment"><strong>Revision reason:</strong> {{ $currentReview->comment }}</p>
                                                @endif
                                                @if($reviewEvents->isNotEmpty())
                                                    <details class="br-doc-review__history">
                                                        <summary>Revision history ({{ $reviewEvents->count() }})</summary>
                                                        <ol>
                                                            @foreach($reviewEvents as $reviewEvent)
                                                                <li>
                                                                    <strong>Version {{ $reviewEvent->version }} · {{ match($reviewEvent->status) { 'accepted' => 'Accepted', 'needs_revision' => 'Needs Revision', default => 'Submitted for review' } }}</strong>
                                                                    <span>{{ $reviewEvent->actor?->name ?? 'System' }} · {{ $reviewEvent->created_at?->timezone($tz)->format('M d, Y h:i A') }}</span>
                                                                    @if($reviewEvent->comment)<p>{{ $reviewEvent->comment }}</p>@endif
                                                                </li>
                                                            @endforeach
                                                        </ol>
                                                    </details>
                                                @endif
                                            </div>
                                        @endif
                                        @if($fileUrl)
                                            <span class="br-file__actions">
                                                <button type="button" class="br-btn br-btn--sm" data-br-preview="{{ $fileUrl }}" data-br-preview-title="{{ $file->label }}" data-br-preview-name="{{ $file->original_name }}">Preview</button>
                                                <a href="{{ $fileUrl }}" target="_blank" rel="noopener" class="br-btn br-btn--sm br-btn--ghost" aria-label="Open {{ $file->label }} in a new tab"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                                            </span>
                                        @endif
                                    </li>
                                @empty
                                    <li class="br-file is-empty"><span class="br-muted">No files in this component.</span></li>
                                @endforelse
                            </ul>
                        </div>
                        @if($componentKey === 'technical' && ! $componentSealed && ! $bid->isDraft() && $bid->submission_channel !== \App\Models\Bid::CHANNEL_MANUAL)
                            @php
                                $reviewChecklist = collect($bid->documentChecklist())->where('component', \App\Models\BidDocument::COMPONENT_TECHNICAL);
                                $reviewOptions = $reviewChecklist->filter(function ($item) use ($filesInComponent) {
                                    $document = $filesInComponent->firstWhere('requirement_key', $item['key']);
                                    $latest = $document?->reviewEvents?->last();
                                    return $latest?->status !== \App\Models\BidDocumentReviewEvent::STATUS_ACCEPTED
                                        && $latest?->status !== \App\Models\BidDocumentReviewEvent::STATUS_NEEDS_REVISION;
                                });
                                $hasRevisionRequest = $filesInComponent->contains(fn ($document) => $document->reviewEvents->last()?->status === \App\Models\BidDocumentReviewEvent::STATUS_NEEDS_REVISION);
                                $allAccepted = $filesInComponent->isNotEmpty() && $filesInComponent->every(fn ($document) => $document->reviewEvents->last()?->status === \App\Models\BidDocumentReviewEvent::STATUS_ACCEPTED);
                                $submissionReviewRoute = auth()->user()?->role === 'admin' ? 'admin.bid.documents.review-submission' : 'staff.bid.documents.review-submission';
                            @endphp
                            <section class="br-submission-review">
                                <div class="br-submission-review__heading">
                                    <div>
                                        <strong>Submission review</strong>
                                        <p>{{ $hasRevisionRequest ? 'For Revision — waiting for the bidder to replace the requested document.' : ($allAccepted ? 'Approved' : 'Review the technical and eligibility documents together.') }}</p>
                                    </div>
                                    <span class="br-doc-review__status {{ $hasRevisionRequest ? 'is-needs_revision' : ($allAccepted ? 'is-accepted' : 'is-pending') }}">{{ $hasRevisionRequest ? 'For Revision' : ($allAccepted ? 'Approved' : 'Awaiting review') }}</span>
                                </div>
                                @if($errors->has('submission') || $errors->has('requirement_key') || $errors->has('comment'))
                                    <p class="br-submission-review__error">{{ $errors->first('submission') ?: ($errors->first('requirement_key') ?: $errors->first('comment')) }}</p>
                                @endif
                                <div class="br-submission-review__actions">
                                    <form method="POST" action="{{ route($submissionReviewRoute, ['bid' => $bid]) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="br-btn br-btn--accept" {{ $allAccepted ? 'disabled' : '' }}>Approve Submission</button>
                                    </form>
                                    @if($reviewOptions->isNotEmpty() && ! $hasRevisionRequest)
                                        <details class="br-submission-review__request">
                                            <summary class="br-btn br-btn--danger">Request Revision</summary>
                                            <form method="POST" action="{{ route($submissionReviewRoute, ['bid' => $bid]) }}">
                                                @csrf
                                                <input type="hidden" name="action" value="request_revision">
                                                <label>Document to revise
                                                    <select name="requirement_key" required>
                                                        <option value="">Select one document</option>
                                                        @foreach($reviewOptions as $item)
                                                            <option value="{{ $item['key'] }}">{{ $item['label'] }}{{ $item['submitted'] ? '' : ' (Missing)' }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                                <label>Message for bidder
                                                    <textarea name="comment" required minlength="5" maxlength="500" rows="2" placeholder="e.g. Missing signature or unreadable scan"></textarea>
                                                </label>
                                                <button type="submit" class="br-btn br-btn--sm br-btn--danger">Send revision request</button>
                                            </form>
                                        </details>
                                    @endif
                                </div>
                            </section>
                    @endif
                    @endforeach
                @endif

                <div class="br-group">
                    <div class="br-group__head"><h3>Submission files</h3></div>
                    <ul class="br-files">
                        <li class="br-file {{ $proposalPreviewUrl && $firstPreview && $firstPreview['url'] === $proposalPreviewUrl ? 'is-active' : '' }}" data-review-target="proposal" tabindex="-1">
                            <span class="br-file__icon {{ $financialSealed ? 'is-sealed' : '' }}"><i class="fas {{ $financialSealed ? 'fa-lock' : 'fa-file-lines' }}" aria-hidden="true"></i></span>
                            <span class="br-file__text">
                                <strong>Proposal File</strong>
                                <small>
                                    @if($financialSealed && $bid->proposal_file)
                                        Received &middot; sealed until bid opening
                                    @elseif($financialSealed && $bid->submission_channel === \App\Models\Bid::CHANNEL_MANUAL)
                                        Sealed envelope &mdash; verify at opening
                                    @else
                                        {{ $bid->proposal_filename ?: 'No proposal uploaded' }}
                                    @endif
                                </small>
                            </span>
                            <span class="br-file__actions">
                                @if($proposalPreviewUrl)
                                    <button type="button" class="br-btn br-btn--sm" data-br-preview="{{ $proposalPreviewUrl }}" data-br-preview-title="Proposal file" data-br-preview-name="{{ $bid->proposal_filename }}">Preview</button>
                                    <a href="{{ $proposalPreviewUrl }}" target="_blank" rel="noopener" class="br-btn br-btn--sm br-btn--ghost" aria-label="Open the proposal file in a new tab"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                                @else
                                    <span class="br-muted">{{ $financialSealed ? 'Sealed' : 'Unavailable' }}</span>
                                @endif
                            </span>
                        </li>
                        <li class="br-file">
                            <span class="br-file__icon {{ $sealed ? 'is-sealed' : '' }}"><i class="fas {{ $sealed ? 'fa-lock' : 'fa-certificate' }}" aria-hidden="true"></i></span>
                            <span class="br-file__text">
                                <strong>Certificate Proof</strong>
                                <small>
                                    @if($sealed && $bid->user?->philgepsCertificate)
                                        Received &middot; sealed until bid opening
                                    @elseif($sealed)
                                        Sealed envelope &mdash; verify at opening
                                    @else
                                        {{ $bid->user?->philgepsCertificate?->display_name ?: 'No certificate proof uploaded' }}
                                    @endif
                                </small>
                            </span>
                            <span class="br-file__actions">
                                @if($certificatePreviewUrl)
                                    <button type="button" class="br-btn br-btn--sm" data-br-preview="{{ $certificatePreviewUrl }}" data-br-preview-title="Certificate Proof" data-br-preview-name="{{ $bid->user?->philgepsCertificate?->display_name }}">Preview</button>
                                    <a href="{{ $certificatePreviewUrl }}" target="_blank" rel="noopener" class="br-btn br-btn--sm br-btn--ghost" aria-label="Open the certificate proof in a new tab"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                                @else
                                    <span class="br-muted">{{ $sealed ? 'Sealed' : 'Unavailable' }}</span>
                                @endif
                            </span>
                        </li>
                    </ul>
                </div>

                <div class="br-viewer" data-br-viewer>
                    @if($firstPreview)
                        <div class="br-viewer__bar">
                            <span class="br-viewer__title"><i class="fas fa-eye" aria-hidden="true"></i> <strong data-br-viewer-title>{{ $firstPreview['title'] }}</strong> <small data-br-viewer-name>{{ $firstPreview['name'] }}</small></span>
                            <a href="{{ $firstPreview['url'] }}" target="_blank" rel="noopener" class="br-btn br-btn--sm br-btn--ghost" data-br-viewer-open>Open in new tab</a>
                        </div>
                        <iframe class="br-viewer__frame bid-proposal-preview" src="{{ $firstPreview['url'] }}" title="{{ $firstPreview['title'] }} for {{ $bidderName }}" loading="lazy" data-br-viewer-frame></iframe>
                    @elseif($sealed)
                        <div class="br-empty">
                            <i class="fas fa-lock" aria-hidden="true"></i>
                            <p>Sealed until the bid opening is recorded{{ $deadline ? ' (submission deadline ' . $deadline->timezone($tz)->format('M d, Y h:i A') . ')' : '' }}.</p>
                        </div>
                    @else
                        <div class="br-empty">
                            <i class="fas fa-file-circle-xmark" aria-hidden="true"></i>
                            <p>{{ $financialSealed ? 'Financial files stay sealed until the financial opening is recorded.' : 'No proposal file was uploaded for this bid.' }}</p>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Checklist --}}
            <section class="br-panel" role="tabpanel" id="br-panel-checklist" aria-labelledby="br-tab-checklist" data-br-panel="checklist" hidden>
                <div class="br-panel__head">
                    <div>
                        <h3>{{ $competitive ? 'Preliminary examination checklist' : ucfirst($mode?->submissionNoun() ?? 'submission').' review checklist' }}</h3>
                        <p class="br-hint">Required items come from this project's configured bidding documents. Presence is not a pass; the BAC must record a result for every applicable item.</p>
                    </div>
                    <span class="br-pill is-{{ $documentStatus['key'] }}">{{ $documentStatus['label'] }}</span>
                </div>
                <ul class="br-checklist">
                    @foreach($checklist as $item)
                        @php
                            $itemResult = $prelimResults->get($item['key']);
                            $itemState = $item['submitted'] === null ? 'is-sealed' : ($item['submitted'] ? 'is-present' : 'is-missing');
                            $itemText = $item['submitted'] === null
                                ? 'Sealed envelope - verify at opening'
                                : (($item['sealed'] ?? $sealed) && $item['submitted']
                                    ? 'Received - sealed until opening'
                                    : ($item['submitted'] ? ($item['file_name'] ?: $item['document_type']) : 'Not submitted'));
                        @endphp
                        <li class="{{ $itemState }}">
                            <i class="fas {{ $item['submitted'] === null ? 'fa-lock' : ($item['submitted'] ? 'fa-circle-check' : 'fa-circle-xmark') }}" aria-hidden="true"></i>
                            <span class="br-checklist__text">
                                <strong>{{ $item['label'] }}{{ ($item['required'] ?? true) ? '' : ' (if applicable)' }}</strong>
                                <small>{{ $itemText }}</small>
                            </span>
                            @if($itemResult)
                                <span class="br-result is-{{ $itemResult['result'] }}">{{ ucfirst(str_replace('_', ' ', $itemResult['result'])) }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if($prelimEvent)
                    <div class="br-record">
                        <strong>Recorded {{ $prelimEvent->decision === 'passed' ? 'pass' : 'fail' }}</strong>
                        <span>{{ $prelimEvent->created_at?->timezone($tz)->format('M d, Y h:i:s A') }} - {{ $prelimEvent->creator?->name ?? 'Reviewer not recorded' }}</span>
                        @if($prelimEvent->reason)<span>Reason: {{ $prelimEvent->reason }}</span>@endif
                    </div>
                @else
                    <p class="br-hint br-hint--end">No preliminary examination result has been recorded yet.</p>
                @endif
            </section>

            {{-- Activity history --}}
            <section class="br-panel" role="tabpanel" id="br-panel-history" aria-labelledby="br-tab-history" data-br-panel="history" data-review-target="history" tabindex="-1" hidden>
                <h3 class="br-visually-hidden">Activity History</h3>
                @if(empty($history))
                    <div class="br-empty is-compact">
                        <i class="fas fa-clock-rotate-left" aria-hidden="true"></i>
                        <p>No activity recorded yet.</p>
                    </div>
                @else
                    <ol class="br-timeline">
                        @foreach(array_reverse($history) as $entry)
                            <li class="{{ $entry['adverse'] ? 'is-adverse' : '' }}">
                                <div class="br-timeline__head">
                                    <strong>{{ $entry['stage'] }}{{ $entry['decision'] ? ' - ' . $entry['decision'] : '' }}</strong>
                                    <time datetime="{{ $entry['at_iso'] }}">{{ $entry['at'] }}</time>
                                </div>
                                <p class="br-timeline__body">{{ $entry['title'] }}</p>
                                @if($entry['reason'])
                                    <p class="br-timeline__body"><span>Reason:</span> {{ $entry['reason'] }}</p>
                                @endif
                                @if(is_array($entry['details'] ?? null) && !empty($entry['details']['result']))
                                    <p class="br-timeline__body"><span>Recorded result:</span> {{ ucfirst(str_replace('_', ' ', (string) $entry['details']['result'])) }}</p>
                                @endif
                                @if(is_array($entry['details'] ?? null) && !empty($entry['details']['findings']))
                                    <p class="br-timeline__body"><span>Documented findings:</span> {{ $entry['details']['findings'] }}</p>
                                @endif
                                @if($entry['source'] === 'legacy_note' && $entry['description'])
                                    <p class="br-timeline__body">{{ $entry['description'] }}</p>
                                @endif
                                <p class="br-timeline__meta">
                                    By {{ $entry['actor'] }}
                                    @if($entry['source'] === 'record') &middot; from bid record @elseif($entry['source'] === 'legacy_note') &middot; legacy note (internal) @endif
                                </p>
                            </li>
                        @endforeach

                    </ol>
                @endif
            </section>
        </div>

        <aside class="br-rail" aria-label="Opening and decisions">
            @if($openingRequired && $project)
                <section class="br-card" data-review-target="opening" tabindex="-1" aria-labelledby="br-opening-title">
                    <div class="br-card__head">
                        <h3 id="br-opening-title">Bid Progress</h3>
                        <span class="br-mono br-muted">Asia/Manila</span>
                    </div>
                    <p class="br-hint">Technical and eligibility files open automatically at the scheduled time. Financial Bid stays sealed until technical approval and password verification.</p>

                    <ol class="br-steps">
                        <li data-br-action="technical-opening" tabindex="-1" class="br-step {{ $technicalOpened ? ($bid->documents_validated_at ? 'is-done' : 'is-current') : 'is-current' }}">
                            <span class="br-step__dot" aria-hidden="true"><i class="fas {{ $technicalOpened ? 'fa-check' : 'fa-lock' }}"></i></span>
                            <div class="br-step__body">
                                <strong>Technical &amp; Eligibility</strong>
                                <small>
                                    @if($technicalOpened)
                                        Opened automatically {{ $manila($project->bids_opened_at, 'M d, Y h:i:s A') }}
                                    @else
                                        Sealed
                                    @endif
                                </small>
                                @unless($technicalOpened)
                                    @if($technicalBlocker)
                                        <p class="br-blocked"><i class="fas fa-clock" aria-hidden="true"></i> {{ $technicalBlocker }}</p>
                                    @else
                                    @endif
                                @endunless
                            </div>
                        </li>

                        <li data-br-action="financial-opening" tabindex="-1" class="br-step {{ $financialOpened ? 'is-done' : ($technicalOpened && ! $financialBlocker ? 'is-current' : '') }}">
                            <span class="br-step__dot" aria-hidden="true"><i class="fas {{ $financialOpened ? 'fa-check' : 'fa-lock' }}"></i></span>
                            <div class="br-step__body">
                                <strong>Financial Bid</strong>
                                <small>
                                    @if($financialOpened)
                                        Opened {{ $manila($bid->financial_opened_at, 'M d, Y h:i:s A') }} by {{ $bid->financialOpenedByUser?->name ?? 'BAC Admin' }}
                                    @else
                                        Sealed
                                    @endif
                                </small>
                                @if(! $bid->isDraft() && ! $bid->financial_opened_at)
                                    @if($financialBlocker)
                                        <p class="br-blocked"><i class="fas fa-lock" aria-hidden="true"></i> {{ $financialBlocker }}</p>
                                        @if($technicalOpened && ! $rulesRecorded && ! $bid->isDraft() && ! $bid->financial_opened_at && auth()->user()?->role === 'admin')
                                            <form action="{{ route('admin.project.bid-opening-rules', $project) }}" method="POST" class="br-form br-opening-rules" aria-label="Record financial opening rules">
                                                @csrf
                                                <strong>Record opening rules</strong>
                                                <p class="br-hint">Use the award criterion and reference from the signed bidding documents.</p>
                                                <label class="br-field">Award criterion
                                                    @if($technicalOpened && $criterion)<input type="hidden" name="award_criterion" value="{{ $criterion }}">@endif
                                                    <select name="award_criterion" required onchange="const field = this.form.querySelector('[data-br-min-score]'); const required = this.value === 'mearb'; field.hidden = !required; field.querySelector('input').required = required;" @disabled($technicalOpened && $criterion)>
                                                        <option value="">Select the criterion</option>
                                                        @foreach($criterionLabels as $key => $label)
                                                            <option value="{{ $key }}" @selected(old('award_criterion', $criterion) === $key)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                                <label class="br-field">Bidding documents reference
                                                    <input type="text" name="opening_documents_reference" value="{{ old('opening_documents_reference', $project->opening_documents_reference) }}" maxlength="500" placeholder="ITB section or bidding document reference" required>
                                                </label>
                                                <label class="br-field" data-br-min-score @if(old('award_criterion', $criterion) !== 'mearb') hidden @endif>Minimum technical score (MEARB)
                                                    <input type="number" name="minimum_technical_score" min="0" max="999999" step="0.0001" value="{{ old('minimum_technical_score', $project->minimum_technical_score) }}" @if(old('award_criterion', $criterion) === 'mearb') required @endif>
                                                </label>
                                                @if($errors->has('opening'))<p class="br-blocked" role="alert">{{ $errors->first('opening') }}</p>@endif
                                                <button type="submit" class="br-btn br-btn--primary">Save opening rules</button>
                                            </form>
                                        @endif
                                    @else
                                        @if($errors->has('opening'))
                                            <p class="br-blocked" role="alert">{{ $errors->first('opening') }}</p>
                                        @endif
                                        @if($bid->financial_opening_password_hash)
                                            <form action="{{ route('admin.bid.open-financial', $bid) }}" method="POST" class="br-form">
                                                @csrf
                                                <input type="hidden" name="opening_method" value="password">
                                                <label class="br-field">Financial Password
                                                    <span class="br-password-control"><input type="password" name="opening_password" minlength="6" maxlength="128" autocomplete="new-password" required><button type="button" class="br-btn" data-br-password-toggle aria-label="Show financial password">Show</button></span>
                                                </label>
                                                @if($errors->has('opening_password'))<p class="br-blocked" role="alert">{{ $errors->first('opening_password') }}</p>@endif
                                                <p class="br-hint">Verified on the server. The PIN is never saved, displayed after entry, or included in notifications and audit records.</p>
                                            </form>
                                        @else
                                            <p class="br-blocked">No bidder financial password is on record for this submission.</p>
                                        @endif
                                    @endif
                                @endif
                            </div>
                        </li>
                        <li class="br-step {{ $facts['evaluated'] ? 'is-done' : ($financialOpened ? 'is-current' : '') }}"><span class="br-step__dot" aria-hidden="true"><i class="fas fa-clipboard-check"></i></span><div class="br-step__body"><strong>Review &amp; Decision</strong><small>{{ $facts['evaluated'] ? 'In review' : ($financialOpened ? 'Current stage' : 'Follows financial bid') }}</small></div></li>
                    </ol>
                </section>
            @endif

            <section class="br-card" data-review-target="decision" tabindex="-1" aria-labelledby="br-decision-title">
                <div class="br-card__head">
                    <h3 id="br-decision-title">Evaluation Actions</h3>
                </div>

                @if($sealed && ! array_key_exists(\App\Support\BidWorkflow::RECORD_MANUAL_RECEIPT, $actions))
                    <p class="br-hint">Proposals and bid amounts stay sealed until the bid opening is recorded. It can be recorded only after the submission deadline{{ $openingAt ? ' and the scheduled opening' : '' }}.</p>
                    @if($openingBlocker ?? null)
                        <p class="br-blocked"><i class="fas fa-clock" aria-hidden="true"></i> {{ $openingBlocker }}</p>
                    @endif
                @elseif(empty($actions))
                    <div class="br-empty is-compact">
                        <i class="fas fa-circle-check" aria-hidden="true"></i>
                        <p>No further decision can be recorded for this bid at its current stage.</p>
                    </div>
                @else
                    <p class="br-hint">Select an action to record the bid evaluation result.</p>
                    @if($errors->any())
                        <div class="br-blocked" role="alert">
                            <strong>Review the following before continuing:</strong>
                            <ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                        </div>
                    @endif
                    <div class="br-decisions">
                        @foreach($actions as $action => $label)
                            @php
                                $adverse = \App\Support\BidWorkflow::isAdverse($action);
                                $hasActionRemarks = $adverse || in_array($action, [
                                    \App\Support\BidWorkflow::EVALUATE,
                                    \App\Support\BidWorkflow::FAIL_EVALUATION,
                                    \App\Support\BidWorkflow::PASS_POST_QUALIFICATION,
                                    \App\Support\BidWorkflow::FAIL_POST_QUALIFICATION,
                                ], true);
                            @endphp
                            <details data-br-action="{{ $action }}" data-br-next-label="{{ $modalActionLabels[$action] ?? $label }}" class="br-decision {{ $adverse ? 'is-adverse' : '' }}" @if($action === $selectedAction) open @endif>
                                <summary>
                                    <i class="fas {{ $adverse ? 'fa-circle-xmark' : 'fa-circle-check' }}" aria-hidden="true"></i>
                                    <span>{{ $modalActionLabels[$action] ?? $label }}</span>
                                    <i class="fas fa-chevron-down br-decision__chevron" aria-hidden="true"></i>
                                </summary>
                                <form action="{{ route('admin.bid.decision', ['bid' => $bid], false) }}" method="POST" class="br-form" enctype="multipart/form-data">
                                    @csrf
                                    <input type="hidden" name="action" value="{{ $action }}">

                                    @if($action === \App\Support\BidWorkflow::PASS_PRELIMINARY)
                                        @if($missingRequirements->isNotEmpty())
                                            <p class="br-blocked">Cannot pass: {{ $missingRequirements->pluck('label')->implode(', ') }} not submitted.</p>
                                        @endif
                                        <fieldset class="br-fieldset">
                                            <legend>Confirm each requirement was checked and complies (pass/fail)</legend>
                                            @foreach($checklist as $item)
                                                <label class="br-check">
                                                    <input type="checkbox" name="verified_requirements[]" value="{{ $item['key'] }}" @if($item['required'] ?? true) required @endif @disabled($item['submitted'] === false)>
                                                    <span>{{ $item['label'] }}{{ ($item['required'] ?? true) ? '' : ' (if applicable)' }}{{ $item['submitted'] === null ? ' - check the sealed envelope' : '' }}</span>
                                                </label>
                                            @endforeach
                                        </fieldset>
                                    @endif

                                    @if($action === \App\Support\BidWorkflow::FAIL_PRELIMINARY)
                                        <fieldset class="br-fieldset">
                                            <legend>Requirements that failed (optional)</legend>
                                            @foreach($checklist as $item)
                                                <label class="br-check">
                                                    <input type="checkbox" name="failed_requirements[]" value="{{ $item['key'] }}" @checked(! $item['submitted'])>
                                                    <span>{{ $item['label'] }}{{ $item['submitted'] ? '' : ' (not submitted)' }}</span>
                                                </label>
                                            @endforeach
                                        </fieldset>
                                    @endif

                                    @if($action === \App\Support\BidWorkflow::START_EVALUATION || $action === \App\Support\BidWorkflow::EVALUATE || $action === \App\Support\BidWorkflow::FAIL_EVALUATION)
                                        <fieldset class="br-fieldset br-criteria">
                                            <legend>Applicable evaluation criteria from this project's bidding documents</legend>
                                            @forelse($evaluationCriteria as $evaluationCriterion)
                                                <div class="br-criteria__row"><strong>{{ $evaluationCriterion['label'] }}</strong><span>{{ $evaluationCriterion['configured_text'] }}</span></div>
                                            @empty
                                                <p class="br-hint">No detailed criteria were configured separately. Record the basis used from the signed bidding documents.</p>
                                            @endforelse
                                        </fieldset>
                                    @endif

                                    @if($action === \App\Support\BidWorkflow::EVALUATE)
                                        <label class="br-field">Detailed evaluation result
                                            <select name="evaluation_result" required>
                                                <option value="">Select result</option>
                                                <option value="responsive">Responsive / meets the applicable criteria</option>
                                                <option value="nonresponsive">Nonresponsive</option>
                                            </select>
                                        </label>
                                        <label class="br-field">Remarks
                                            <textarea name="evaluation_findings" rows="4" required minlength="5" placeholder="Record the criterion-by-criterion basis and the applicable award criterion."></textarea>
                                        </label>
                                    @elseif($action === \App\Support\BidWorkflow::FAIL_EVALUATION)
                                        <input type="hidden" name="evaluation_result" value="nonresponsive">
                                        <label class="br-field">Remarks
                                            <textarea name="evaluation_findings" rows="4" required minlength="5" placeholder="Document the criterion or finding that made the bid nonresponsive."></textarea>
                                        </label>
                                    @endif

                                    @if($action === \App\Support\BidWorkflow::START_POST_QUALIFICATION)
                                        <p class="br-hint"><strong>Post-qualification basis:</strong> {{ $project?->requirement?->qualification_notes ?: 'Use the configured post-qualification requirements.' }}</p>
                                    @endif

                                    @if($action === \App\Support\BidWorkflow::PASS_POST_QUALIFICATION || $action === \App\Support\BidWorkflow::FAIL_POST_QUALIFICATION)
                                        <label class="br-field">Remarks
                                            <textarea name="post_qualification_findings" rows="4" required minlength="5" placeholder="Record the documents verified, findings, and basis for this post-qualification result."></textarea>
                                        </label>
                                    @endif

                                    @if($action === \App\Support\BidWorkflow::RECORD_MANUAL_RECEIPT)
                                        <label class="br-field">Receipt / logbook number
                                            <input type="text" name="receipt_no" required maxlength="60">
                                        </label>
                                        <label class="br-field">Received on (date and time)
                                            <input type="datetime-local" name="received_at" required max="{{ now()->timezone($tz)->format('Y-m-d') }}T{{ now()->timezone($tz)->format('H:i') }}">
                                        </label>
                                    @endif

                                    @if($action === \App\Support\BidWorkflow::RECOMMEND)
                                        <label class="br-field">BAC Resolution No.
                                            <input type="text" name="bac_resolution_no" required maxlength="100" placeholder="e.g. BAC Resolution No. 2026-031">
                                        </label>
                                        <label class="br-field">Resolution date
                                            <input type="date" name="bac_resolution_date" required max="{{ now()->timezone($tz)->toDateString() }}">
                                        </label>
                                        <p class="br-hint">The recommendation goes to the HoPE, who approves or disapproves it before the Notice of Award.</p>
                                    @endif

                                    @if($action === \App\Support\BidWorkflow::NOTICE_OF_AWARD)
                                        <label class="br-field">Signed Notice of Award (PDF)
                                            <input type="file" name="notice_file" accept="application/pdf,.pdf" required>
                                        </label>
                                        <p class="br-hint">Creates the award record (contract amount = the bid price as submitted) and marks the project Awarded. Post the NOA on PhilGEPS.</p>
                                    @endif

                                    @if($action === \App\Support\BidWorkflow::CONTRACT_SIGNED)
                                        <label class="br-field">Contract signed on
                                            <input type="date" name="contract_date" max="{{ now()->timezone($tz)->toDateString() }}" required>
                                        </label>
                                        <label class="br-field">Performance security posted on
                                            <input type="date" name="performance_security_at" max="{{ now()->toDateString() }}" required>
                                        </label>
                                    @endif

                                    @unless($hasActionRemarks)
                                        <label class="br-field">Remarks <span class="br-optional">(optional)</span>
                                            <textarea name="notes" rows="3" maxlength="2000" placeholder="Add a note to this action, if needed."></textarea>
                                        </label>
                                    @endunless

                                    @if($adverse)
                                        <label class="br-field">Remarks to share with the bidder
                                            <textarea name="reason" rows="3" required minlength="5" placeholder="State the ground. Do not include internal deliberations."></textarea>
                                        </label>
                                    @endif

                                    @unless(in_array($action, [\App\Support\BidWorkflow::NOTICE_OF_AWARD, \App\Support\BidWorkflow::RECORD_MANUAL_RECEIPT], true))
                                        <label class="br-field">{{ $action === \App\Support\BidWorkflow::RECOMMEND ? 'Signed BAC resolution' : 'Supporting document' }} <span class="br-optional">(optional: report, minutes, resolution)</span>
                                            <input type="file" name="supporting_document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                        </label>
                                    @endunless

                               </form>
                            </details>
                        @endforeach
                    </div>
                @endif
            </section>
        </aside>
    </div>

    <footer class="br-foot">
        <button type="button" onclick="closeBidViewModal()" class="br-btn">Close</button>
        <div class="br-foot__actions">
            @if($bid->user_id)
                <a href="{{ route('admin.messages', ['tab' => 'bidders', 'user' => $bid->user_id]) }}" class="br-btn">
                    <i class="fas fa-message" aria-hidden="true"></i> Message Bidder
                </a>
            @endif
            <div class="br-foot__next">
                <span id="br-next-action" data-br-next-step>Next step: {{ $nextLabel }}</span>
                @if($proceedBlocker)<small id="br-proceed-reason" role="status">{{ $proceedBlocker }}</small>@endif
            </div>
            <button type="button" class="br-btn br-btn--primary" data-br-proceed="{{ $selectedAction ?? $nextAction }}" aria-describedby="br-next-action{{ $proceedBlocker ? ' br-proceed-reason' : '' }}" @disabled($proceedBlocker)>Continue <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
        </div>
    </footer>
</div>
