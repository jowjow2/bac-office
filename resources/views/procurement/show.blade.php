@extends('layouts.portal')

@use('App\Support\Format')
@use('App\Models\ProjectProceeding')

@php
    /** @var \App\Models\Project $project */
    /** @var \App\Support\ProcurementMode $mode */
    $tz = config('bac-office.display_timezone');
    $status = $project->portalStatus();
    $isAdmin = $routePrefix === 'admin';
    $published = $project->isPublishedLocally();
    $deadline = $project->bidSubmissionDeadline();
    $canCloseOut = $contractedBid?->notice_to_proceed_at !== null && ! $project->isCompleted();
    $hasInspection = $project->proceedings->contains('type', ProjectProceeding::TYPE_INSPECTION);
    $openDialog = old('_dialog');
    $noun = $mode->submissionNoun();
    $nounPlural = $mode->submissionNoun(true);
    $calloutTone = match (true) {
        $project->isFailedBidding() => 'danger',
        $project->isCompleted() => 'success',
        $timeline->isPastAwardPeriod() => 'warning',
        default => '',
    };
    $bidsUrl = $isAdmin ? route('admin.bids', ['project' => $project->id]) : route('staff.review-bids');
    $registerUrl = route($isAdmin ? 'admin.dashboard' : 'staff.dashboard');
    $formatDate = fn ($date, bool $time) => $date->copy()->timezone($tz)->format($time ? 'M d, Y · g:i A' : 'M d, Y');
    $localInput = fn ($date) => $date ? $date->copy()->timezone($tz)->format('Y-m-d\TH:i') : '';
    $nowLocal = now()->timezone($tz)->format('Y-m-d\TH:i');
    $reference = $project->reference_no ?: 'Reference pending';
    $needsPostingRecord = $published && $mode->requiresPosting() && ! $project->philgeps_posted_at;
    $awardDue = $timeline->awardDueDate();
@endphp

@section('title', $project->title)
@section('crumbs')
    <a href="{{ $registerUrl }}">Procurement register</a>
    <span aria-hidden="true">/</span>
    <span class="ui-mono">{{ $reference }}</span>
@endsection
@section('subtitle', $mode->label().' · '.$mode->legalBasisShort().' · '.($project->end_user_unit ?: 'End-user office not set'))

@section('actions')
    <a href="{{ $bidsUrl }}" class="ui-btn ui-btn--secondary"><i class="fas fa-envelope-open-text" aria-hidden="true"></i> Review {{ $nounPlural }}</a>
    @if($project->requiresBiddingFee())
        <a href="{{ route($routePrefix.'.payments', ['project' => $project->id]) }}" class="ui-btn ui-btn--secondary"><i class="fas fa-receipt" aria-hidden="true"></i> Fee payments</a>
    @endif
    @if($isAdmin)
        <a href="{{ route('admin.project.edit', $project) }}" class="ui-btn ui-btn--secondary"><i class="fas fa-pen" aria-hidden="true"></i> Edit</a>
    @endif
    @if($published)
        <a href="{{ route('public.procurement.show', $project) }}" class="ui-btn ui-btn--ghost" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i> Public notice</a>
    @endif
@endsection

@section('content')
    @if($errors->any() && ! $openDialog)
        <div class="ui-alert ui-alert--danger" role="alert">
            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="ui-kpis" aria-label="Summary">
        <div class="ui-kpi">
            <span class="ui-kpi__label">Current stage</span>
            <span class="ui-kpi__value ui-kpi__value--text">{{ $project->isCompleted() ? 'Completed' : ($project->isFailedBidding() ? ($mode->isCompetitive() ? 'Failed bidding' : 'Failed procurement') : ($current['label'] ?? $status['label'])) }}</span>
            <span class="ui-kpi__foot">{{ $timeline->progressPercent() }}% of applicable stages done</span>
        </div>
        <div class="ui-kpi">
            <span class="ui-kpi__label">Responsible office</span>
            <span class="ui-kpi__value ui-kpi__value--text">{{ $nextAction['office'] ?? '—' }}</span>
            <span class="ui-kpi__foot">For the next required action</span>
        </div>
        <div class="ui-kpi">
            <span class="ui-kpi__label">Approved Budget for the Contract</span>
            <span class="ui-kpi__value ui-kpi__value--money ui-mono">{{ Format::peso($project->budget) }}</span>
            <span class="ui-kpi__foot">{{ ucfirst((string) $project->category) ?: 'Category not set' }}</span>
        </div>
        <div class="ui-kpi {{ $timeline->isPastAwardPeriod() ? 'ui-kpi--danger' : '' }}">
            <span class="ui-kpi__label">{{ $awardDue ? 'Award due (IRR period)' : $mode->deadlineLabel() }}</span>
            <span class="ui-kpi__value ui-kpi__value--text">{{ $awardDue ? Format::date($awardDue) : ($deadline ? Format::date($deadline, true) : 'Not set') }}</span>
            <span class="ui-kpi__foot">{{ $awardDue ? $mode->awardPeriodLabel() : $project->submissionMethodLabel() }}</span>
        </div>
    </section>

    <div class="ui-actions" aria-label="Classification">
        <span class="ui-mode ui-mode--{{ $mode->family() }}">{{ $mode->label() }}</span>
        @if($mode->negotiationGroundLabel())
            <span class="ui-badge ui-badge--neutral">{{ $mode->negotiationGroundLabel() }}</span>
        @endif
        <span class="ui-badge ui-badge--{{ $status['tone'] }}">{{ $status['label'] }}</span>
        <span class="ui-badge ui-badge--neutral">{{ $mode->legalBasisLabel() }}</span>
        <span class="ui-badge ui-badge--neutral">{{ $project->acceptsElectronicSubmission() ? 'Online submission' : 'Manual (sealed) submission' }}</span>
    </div>

    @if($nextAction)
        <section class="ui-callout {{ $calloutTone ? 'ui-callout--'.$calloutTone : '' }}" aria-labelledby="next-title">
            <span class="ui-callout__label">{{ $calloutTone === 'success' ? 'Status' : 'Next required action' }} · {{ $nextAction['office'] }}</span>
            <h2 class="ui-callout__title" id="next-title">{{ $nextAction['title'] }}</h2>
            <p class="ui-callout__text">{{ $nextAction['detail'] }}</p>
            @if($timeline->isPastAwardPeriod())
                <p class="ui-callout__text"><strong>The award period of {{ $mode->awardPeriodLabel() }} from the bid opening has passed.</strong> Record the award decision, or document the reason for the delay.</p>
            @endif
        </section>
    @endif

    @if($published)
        <div class="ui-alert ui-alert--info" role="note">
            <i class="fas fa-circle-info" aria-hidden="true"></i>
            <div><strong>Published in the BAC system.</strong> This local publication makes the project visible to eligible bidders and starts the local submission schedule. It does not complete or certify any separate official PhilGEPS posting obligation.</div>
        </div>
    @endif

    @if($needsPostingRecord)
        <div class="ui-alert ui-alert--warning" role="note">
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
            <div>
                <strong>PhilGEPS posting not recorded.</strong>
                This system does not post to PhilGEPS. Post the {{ $mode->noticeLabel() }} on PhilGEPS, then record its reference number, link and posting date here. {{ $mode->postingRule() }}
                <div class="ui-mt-sm"><button type="button" class="ui-btn ui-btn--secondary ui-btn--sm" data-dialog-open="dialog-publication">Record PhilGEPS posting</button></div>
            </div>
        </div>
    @endif

    <div class="ui-grid ui-grid--sidebar">
        <div class="ui-stack">
            <section class="ui-card" aria-labelledby="timeline-title">
                <header class="ui-card__head">
                    <div>
                        <h2 class="ui-card__title" id="timeline-title">Progress timeline · {{ $mode->label() }}</h2>
                        <p class="ui-card__desc">Stages of this mode under {{ $mode->legalBasisShort() }}. Dashed stages do not apply to this procurement.</p>
                    </div>
                    <span class="ui-card__aside ui-num">{{ $timeline->progressPercent() }}%</span>
                </header>
                <div class="ui-card__body">
                    <div class="ui-progress ui-progress--spaced" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $timeline->progressPercent() }}" aria-label="Progress"><span style="width: {{ $timeline->progressPercent() }}%"></span></div>
                    @include('partials.ui.timeline', ['stages' => $stages])
                </div>
            </section>

            <section class="ui-card" aria-labelledby="bids-title">
                <header class="ui-card__head">
                    <div>
                        <h2 class="ui-card__title" id="bids-title">{{ ucfirst($nounPlural) }} received · {{ $bids->count() }}</h2>
                        <p class="ui-card__desc">Official submissions only. Amounts stay sealed until the {{ strtolower($mode->openingLabel()) }} and preliminary examination. The award is never decided by amount alone: it follows evaluation, post-qualification, a BAC resolution and HoPE approval.</p>
                    </div>
                    <a href="{{ $bidsUrl }}" class="ui-link">Open review</a>
                </header>
                @if($bids->isEmpty())
                    <div class="ui-empty">
                        <i class="fas fa-inbox" aria-hidden="true"></i>
                        <span>{{ $published ? 'No official '.$nounPlural.' yet.' : ucfirst($nounPlural).' are received once the project is posted.' }}</span>
                    </div>
                @else
                    <div class="ui-table-wrap">
                        <table class="ui-table ui-table--stack">
                            <thead>
                                <tr>
                                    <th scope="col">{{ $mode->isCompetitive() ? 'Bidder' : 'Supplier' }}</th>
                                    <th scope="col">Receipt no.</th>
                                    <th scope="col" class="is-num">{{ ucfirst($noun) }} price</th>
                                    <th scope="col">Stage</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($bids as $bid)
                                    @php $bidStatus = $bid->progress()->adminStatus(); @endphp
                                    <tr>
                                        <td data-label="{{ $mode->isCompetitive() ? 'Bidder' : 'Supplier' }}">
                                            <span class="ui-cell-title">{{ $bid->user?->company ?: ($bid->user?->name ?? 'Supplier') }}</span>
                                            @if($bid->bac_resolution_no)<span class="ui-cell-sub">BAC Resolution No. {{ $bid->bac_resolution_no }}</span>@endif
                                        </td>
                                        <td data-label="Receipt no." class="is-nowrap ui-mono">{{ $bid->receipt_no ?: '—' }}</td>
                                        <td data-label="Price" class="is-num">
                                            @if($bid->isFinancialSealed())
                                                <span class="ui-badge ui-badge--neutral"><i class="fas fa-lock" aria-hidden="true"></i> Sealed</span>
                                            @else
                                                {{ number_format((float) $bid->bid_amount, 2) }}
                                            @endif
                                        </td>
                                        <td data-label="Stage"><span class="ui-pill ui-pill--{{ \App\Support\BidProgress::tone($bidStatus['key']) }}">{{ $bidStatus['label'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="ui-card" aria-labelledby="history-title">
                <header class="ui-card__head">
                    <div>
                        <h2 class="ui-card__title" id="history-title">Decisions and proceedings</h2>
                        <p class="ui-card__desc">Every recorded decision and proceeding, newest first, with its reason and supporting document.</p>
                    </div>
                </header>
                @include('partials.ui.history', ['history' => $history])
            </section>

            <section class="ui-card" aria-labelledby="audit-title">
                <header class="ui-card__head">
                    <div>
                        <h2 class="ui-card__title" id="audit-title">Audit history</h2>
                        <p class="ui-card__desc">System changes to this project and its {{ $nounPlural }}: who, what and when (latest 25).</p>
                    </div>
                </header>
                @if($auditTrail->isEmpty())
                    <div class="ui-empty"><span>No audit entries yet.</span></div>
                @else
                    <ul class="ui-feed">
                        @foreach($auditTrail as $entry)
                            <li class="ui-feed__item">
                                <span class="ui-feed__dot" aria-hidden="true"></span>
                                <div>
                                    <p class="ui-feed__title">{{ \Illuminate\Support\Str::of($entry->action)->replace('_', ' ')->ucfirst() }}{{ $entry->auditable_type === \App\Models\Bid::class ? ' · '.$noun.' #'.$entry->auditable_id : '' }}</p>
                                    <p class="ui-feed__meta">{{ $entry->user?->name ?? 'System' }} · <time datetime="{{ $entry->created_at?->toIso8601String() }}">{{ Format::date($entry->created_at, true) }}</time></p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <div class="ui-stack">
            <section class="ui-card" aria-labelledby="record-title">
                <header class="ui-card__head">
                    <div>
                        <h2 class="ui-card__title" id="record-title">Record</h2>
                        <p class="ui-card__desc">Project-level records. Decisions on each {{ $noun }} are recorded in review.</p>
                    </div>
                </header>
                <div class="ui-card__body ui-stack ui-stack--tight">
                    <button type="button" class="ui-btn ui-btn--secondary ui-btn--block" data-dialog-open="dialog-publication" @disabled(! $published)><i class="fas fa-bullhorn" aria-hidden="true"></i> PhilGEPS posting</button>
                    <button type="button" class="ui-btn ui-btn--secondary ui-btn--block" data-dialog-open="dialog-proceeding" @disabled(empty($proceedingTypes))><i class="fas fa-file-pen" aria-hidden="true"></i> {{ ! $published && $mode->isCompetitive() ? 'Pre-procurement conference' : ($mode->isCompetitive() ? 'Pre-bid, posting, observers, resolution…' : 'RFQs sent, abstract, resolution…') }}</button>
                    <button type="button" class="ui-btn ui-btn--secondary ui-btn--block" data-dialog-open="dialog-inspection" @disabled(! $canCloseOut)><i class="fas fa-clipboard-check" aria-hidden="true"></i> Inspection</button>
                    <button type="button" class="ui-btn ui-btn--secondary ui-btn--block" data-dialog-open="dialog-acceptance" @disabled(! $canCloseOut || ! $hasInspection)><i class="fas fa-flag-checkered" aria-hidden="true"></i> Acceptance</button>
                    @unless($published)
                        <p class="ui-hint">{{ $mode->isCompetitive() ? 'Before publishing, only the pre-procurement conference is recorded; the rest opens once the project is published.' : 'Available once the project is published in the BAC system.' }}</p>
                    @endunless
                    @if($isAdmin && $published && ! $project->isFailedBidding() && ! $project->isCompleted())
                        <p class="ui-hint">A failure of {{ $mode->isCompetitive() ? 'bidding' : 'procurement' }} is declared from the <a href="{{ route('admin.projects') }}" class="ui-link">project list</a> after the opening, with the reason and review.</p>
                    @endif
                </div>
            </section>

            @if(! empty($compliance))
                <section class="ui-card" aria-labelledby="compliance-title">
                    <header class="ui-card__head">
                        <div>
                            <h2 class="ui-card__title" id="compliance-title">Posting and transparency</h2>
                            <p class="ui-card__desc">What the IRR requires for this {{ Format::peso($project->budget) }} bidding, and what is recorded.</p>
                        </div>
                    </header>
                    <ul class="ui-card__body ui-stack ui-stack--tight compliance-list">
                        @foreach($compliance as $item)
                            <li>
                                <span class="ui-pill ui-pill--{{ ['done' => 'success', 'pending' => 'warning', 'optional' => 'neutral'][$item['status']] }}">{{ ['done' => 'Recorded', 'pending' => 'To do', 'optional' => 'Optional'][$item['status']] }}</span>
                                <strong>{{ $item['label'] }}</strong> <span class="ui-optional">{{ $item['basis'] }}</span>
                                <p class="ui-hint">{{ $item['detail'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="ui-card" aria-labelledby="dates-title">
                <header class="ui-card__head"><h2 class="ui-card__title" id="dates-title">Key dates</h2></header>
                <div class="ui-card__body">
                    @if(empty($keyDates))
                        <p class="ui-hint">No dates set yet.</p>
                    @else
                        <dl class="ui-dl">
                            @foreach($keyDates as $row)
                                <div><dt>{{ $row['label'] }}</dt><dd class="ui-num">{{ $formatDate($row['date'], $row['time']) }}</dd></div>
                            @endforeach
                        </dl>
                    @endif
                </div>
            </section>

            <section class="ui-card" aria-labelledby="notice-title">
                <header class="ui-card__head"><h2 class="ui-card__title" id="notice-title">Notice details</h2></header>
                <div class="ui-card__body">
                    <dl class="ui-dl">
                        <div><dt>Mode</dt><dd>{{ $mode->label() }}</dd></div>
                        @if($mode->negotiationGroundLabel())
                            <div><dt>Ground</dt><dd>{{ $mode->negotiationGroundLabel() }}</dd></div>
                        @endif
                        <div><dt>Legal basis</dt><dd>{{ $mode->legalBasisLabel() }}</dd></div>
                        <div><dt>Posting</dt><dd>{{ $mode->postingRule() }}</dd></div>
                        @if($mode->isCompetitive())
                            <div><dt>Pre-bid conference</dt><dd>{{ $mode->prebidRule() }}</dd></div>
                        @endif
                        <div><dt>End-user office</dt><dd>{{ $project->end_user_unit ?: ($project->procurementRequest?->end_user_office ?: '—') }}</dd></div>
                        @if($project->procurementRequest)
                            <div><dt>Purchase request</dt><dd class="ui-mono">{{ $project->procurementRequest->reference_no }}</dd></div>
                        @endif
                        <div><dt>BAC publication</dt><dd>{{ $project->published_at ? $formatDate($project->published_at, true) : 'Not recorded' }} <span class="ui-optional">(local system)</span></dd></div>
                        <div><dt>PhilGEPS reference</dt><dd>
                            <span class="ui-mono">{{ $project->philgeps_reference_no ?: 'Not recorded' }}</span>
                            @if($project->philgeps_url)
                                <br><a class="ui-link" href="{{ $project->philgeps_url }}" target="_blank" rel="noopener noreferrer">View on PhilGEPS</a>
                            @endif
                        </dd></div>
                        <div><dt>Submission</dt><dd>{{ $project->acceptsElectronicSubmission() ? 'Online, through this system' : 'Manual, sealed'.($project->submission_venue ? ' — '.$project->submission_venue : ' at the BAC Secretariat') }}</dd></div>
                        <div><dt>Bidding documents fee</dt><dd>{{ \App\Support\BiddingDocumentsFee::describe($project)['text'] }}{{ $project->requiresBiddingFee() ? ' — '.$project->paymentVenueLabel() : '' }}</dd></div>
                        <div><dt>Bid security</dt><dd>{{ $project->bid_security_required ? ($project->bid_security_notes ?: 'Required, as stated in the notice') : 'Not required' }}</dd></div>
                    </dl>
                </div>
            </section>

            <section class="ui-card" aria-labelledby="docs-title">
                <header class="ui-card__head"><h2 class="ui-card__title" id="docs-title">Documents</h2></header>
                <div class="ui-card__body">
                    @include('partials.ui.documents', ['documents' => $documents])
                </div>
            </section>
        </div>
    </div>

    {{-- ---------- Dialogs ---------- --}}
    @php
        $dialogErrors = fn (string $name) => $openDialog === $name && $errors->any() ? $errors->all() : [];
    @endphp

    <dialog class="ui-dialog" id="dialog-publication" aria-labelledby="dialog-publication-title" @if($openDialog === 'publication') data-open-on-load @endif>
        <form method="POST" action="{{ route($routePrefix.'.procurement.publication', $project) }}" data-validate>
            @csrf
            <input type="hidden" name="_dialog" value="publication">
            <div class="ui-card__head">
                <div>
                    <h2 class="ui-card__title" id="dialog-publication-title">Record PhilGEPS posting</h2>
                    <p class="ui-card__desc">Post the {{ $mode->noticeLabel() }} on PhilGEPS yourself, then record it here. This system does not post to PhilGEPS.</p>
                </div>
                <button type="button" class="ui-dialog__close" data-dialog-close aria-label="Close"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="ui-card__body ui-form ui-form--tight">
                @include('procurement.partials.dialog-errors', ['messages' => $dialogErrors('publication')])
                <p class="ui-hint">{{ $mode->postingRule() }} The BAC system does not perform this external posting; record the actual PhilGEPS details only after it occurs.</p>
                <div class="ui-fields">
                    <div class="ui-field">
                        <label class="ui-label" for="pub-ref">PhilGEPS reference no. <span class="ui-required" aria-hidden="true">*</span></label>
                        <input id="pub-ref" name="philgeps_reference_no" class="ui-input ui-mono" required maxlength="100" value="{{ old('philgeps_reference_no', $project->philgeps_reference_no) }}" aria-describedby="pub-ref-error">
                        <span class="ui-error" id="pub-ref-error" data-client hidden></span>
                    </div>
                    <div class="ui-field">
                        <label class="ui-label" for="pub-date">Date posted on PhilGEPS <span class="ui-required" aria-hidden="true">*</span></label>
                        <input id="pub-date" type="date" name="philgeps_posted_at" class="ui-input" required max="{{ now()->timezone($tz)->toDateString() }}" value="{{ old('philgeps_posted_at', $project->philgeps_posted_at?->toDateString()) }}" aria-describedby="pub-date-error">
                        <span class="ui-error" id="pub-date-error" data-client hidden></span>
                    </div>
                    <div class="ui-field ui-field--wide">
                        <label class="ui-label" for="pub-url">Link to the PhilGEPS notice <span class="ui-optional">(optional)</span></label>
                        <input id="pub-url" type="url" name="philgeps_url" class="ui-input" maxlength="500" value="{{ old('philgeps_url', $project->philgeps_url) }}" placeholder="https://notices.philgeps.gov.ph/…" aria-describedby="pub-url-error">
                        <span class="ui-error" id="pub-url-error" data-client hidden></span>
                    </div>
                </div>
            </div>
            <div class="ui-card__foot">
                <button type="button" class="ui-btn ui-btn--secondary" data-dialog-close>Cancel</button>
                <button type="submit" class="ui-btn ui-btn--primary">Save posting</button>
            </div>
        </form>
    </dialog>

    <dialog class="ui-dialog" id="dialog-proceeding" aria-labelledby="dialog-proceeding-title" @if($openDialog === 'proceeding') data-open-on-load @endif>
        <form method="POST" action="{{ route($routePrefix.'.procurement.proceedings', $project) }}" enctype="multipart/form-data" data-proceeding-form data-validate>
            @csrf
            <input type="hidden" name="_dialog" value="proceeding">
            <div class="ui-card__head">
                <div>
                    <h2 class="ui-card__title" id="dialog-proceeding-title">Record a proceeding</h2>
                    <p class="ui-card__desc">Only the records that apply to {{ $mode->label() }} are listed. Record them after they take place.</p>
                </div>
                <button type="button" class="ui-dialog__close" data-dialog-close aria-label="Close"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="ui-card__body ui-form ui-form--tight">
                @include('procurement.partials.dialog-errors', ['messages' => $dialogErrors('proceeding')])
                <div class="ui-fields">
                    <div class="ui-field">
                        <label class="ui-label" for="proc-type">Type <span class="ui-required" aria-hidden="true">*</span></label>
                        <select id="proc-type" name="type" class="ui-input" required data-proceeding-type>
                            @foreach($proceedingTypes as $type => $label)
                                <option value="{{ $type }}" @selected(old('type') === $type)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ui-field">
                        <label class="ui-label" for="proc-date">Date and time <span class="ui-required" aria-hidden="true">*</span></label>
                        <input id="proc-date" type="datetime-local" name="occurred_at" class="ui-input" required max="{{ $nowLocal }}" value="{{ old('occurred_at', $localInput($project->schedule?->pre_bid_conference_date?->isPast() ? $project->schedule->pre_bid_conference_date : null)) }}">
                    </div>
                    <div class="ui-field" data-recipients-field hidden>
                        <label class="ui-label" for="proc-recipients">Suppliers the RFQ was sent to <span class="ui-required" aria-hidden="true">*</span></label>
                        <input id="proc-recipients" type="number" min="1" max="500" name="recipients_count" class="ui-input" value="{{ old('recipients_count') }}" aria-describedby="proc-recipients-hint">
                        <span class="ui-hint" id="proc-recipients-hint">{{ $mode->isDirectContracting() ? 'The identified direct supplier.' : 'At least three (3) suppliers of known qualifications.' }}</span>
                    </div>
                    <div class="ui-field">
                        <label class="ui-label" for="proc-ref">Reference no. <span class="ui-hint" data-ref-hint>(document number)</span></label>
                        <input id="proc-ref" name="reference_no" class="ui-input ui-mono" maxlength="100" value="{{ old('reference_no') }}">
                    </div>
                    <div class="ui-field">
                        <label class="ui-label" for="proc-title">Title <span class="ui-optional">(optional)</span></label>
                        <input id="proc-title" name="title" class="ui-input" maxlength="255" value="{{ old('title') }}">
                    </div>
                    <div class="ui-field ui-field--wide" data-outcome-field hidden>
                        <label class="ui-label" for="proc-outcome">Outcome</label>
                        <select id="proc-outcome" name="outcome" class="ui-input">
                            @foreach(ProjectProceeding::RECONSIDERATION_OUTCOMES as $key => $label)
                                <option value="{{ $key }}" @selected(old('outcome') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ui-field ui-field--wide">
                        <label class="ui-label" for="proc-summary">Summary, minutes or findings</label>
                        <textarea id="proc-summary" name="summary" class="ui-input" rows="4" maxlength="5000">{{ old('summary') }}</textarea>
                    </div>
                    <div class="ui-field ui-field--wide">
                        <label class="ui-label" for="proc-doc">Supporting document <span class="ui-optional">(PDF, image or Word, up to 20 MB)</span></label>
                        <input id="proc-doc" type="file" name="document" class="ui-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    </div>
                </div>
            </div>
            <div class="ui-card__foot">
                <button type="button" class="ui-btn ui-btn--secondary" data-dialog-close>Cancel</button>
                <button type="submit" class="ui-btn ui-btn--primary">Save record</button>
            </div>
        </form>
    </dialog>

    @foreach(['inspection' => ['Record inspection', 'Inspection report no.', 'Date inspected', 'The end-user office or inspection committee inspected the delivery or work.'], 'acceptance' => ['Record acceptance', 'IAR / acceptance no.', 'Date accepted', 'Acceptance of the delivery or work completes the procurement.']] as $kind => [$heading, $refLabel, $dateLabel, $desc])
        <dialog class="ui-dialog" id="dialog-{{ $kind }}" aria-labelledby="dialog-{{ $kind }}-title" @if($openDialog === $kind) data-open-on-load @endif>
            <form method="POST" action="{{ route($routePrefix.'.procurement.'.$kind, $project) }}" enctype="multipart/form-data" data-validate>
                @csrf
                <input type="hidden" name="_dialog" value="{{ $kind }}">
                <div class="ui-card__head">
                    <div>
                        <h2 class="ui-card__title" id="dialog-{{ $kind }}-title">{{ $heading }}</h2>
                        <p class="ui-card__desc">{{ $desc }}</p>
                    </div>
                    <button type="button" class="ui-dialog__close" data-dialog-close aria-label="Close"><i class="fas fa-xmark" aria-hidden="true"></i></button>
                </div>
                <div class="ui-card__body ui-form ui-form--tight">
                    @include('procurement.partials.dialog-errors', ['messages' => $dialogErrors($kind)])
                    <div class="ui-fields">
                        <div class="ui-field">
                            <label class="ui-label" for="{{ $kind }}-ref">{{ $refLabel }} <span class="ui-required" aria-hidden="true">*</span></label>
                            <input id="{{ $kind }}-ref" name="reference_no" class="ui-input ui-mono" required maxlength="100" value="{{ $openDialog === $kind ? old('reference_no') : '' }}">
                        </div>
                        <div class="ui-field">
                            <label class="ui-label" for="{{ $kind }}-date">{{ $dateLabel }} <span class="ui-required" aria-hidden="true">*</span></label>
                            <input id="{{ $kind }}-date" type="datetime-local" name="occurred_at" class="ui-input" required max="{{ $nowLocal }}" value="{{ $openDialog === $kind ? old('occurred_at') : '' }}">
                        </div>
                        <div class="ui-field ui-field--wide">
                            <label class="ui-label" for="{{ $kind }}-summary">Remarks</label>
                            <textarea id="{{ $kind }}-summary" name="summary" class="ui-input" rows="3" maxlength="5000">{{ $openDialog === $kind ? old('summary') : '' }}</textarea>
                        </div>
                        <div class="ui-field ui-field--wide">
                            <label class="ui-label" for="{{ $kind }}-doc">Report / certificate <span class="ui-optional">(optional)</span></label>
                            <input id="{{ $kind }}-doc" type="file" name="document" class="ui-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        </div>
                    </div>
                </div>
                <div class="ui-card__foot">
                    <button type="button" class="ui-btn ui-btn--secondary" data-dialog-close>Cancel</button>
                    <button type="submit" class="ui-btn ui-btn--primary">Save</button>
                </div>
            </form>
        </dialog>
    @endforeach
    @if(isset($contractAward) && $contractAward)
        @include('partials.contract-implementation', ['award' => $contractAward, 'implementation' => $contractAward->contractImplementation, 'viewerMode' => $routePrefix === 'staff' ? 'staff' : 'admin'])
    @endif

@endsection

@push('scripts')
<script>
    (function () {
        // Fields that depend on the proceeding type.
        const form = document.querySelector('[data-proceeding-form]');
        if (!form) return;
        const type = form.querySelector('[data-proceeding-type]');
        const outcome = form.querySelector('[data-outcome-field]');
        const recipients = form.querySelector('[data-recipients-field]');
        const recipientsInput = recipients.querySelector('input');
        const reference = form.querySelector('#proc-ref');
        const recipientsLabel = recipients.querySelector('label');
        const recipientsHint = recipients.querySelector('.ui-hint');
        const rfqLabel = recipientsLabel.innerHTML;
        const rfqHint = recipientsHint.textContent;
        const sync = function () {
            const counted = ['rfq_issued', 'observers_invited'].includes(type.value);
            const observers = type.value === 'observers_invited';
            outcome.hidden = type.value !== 'request_for_reconsideration';
            recipients.hidden = !counted;
            recipientsInput.required = counted;
            recipientsInput.disabled = !counted;
            recipientsInput.min = observers ? 3 : 1;
            recipientsLabel.innerHTML = observers ? 'Number invited (COA and observers) <span class="ui-required" aria-hidden="true">*</span>' : rfqLabel;
            recipientsHint.textContent = observers ? 'The COA representative and at least two (2) observers (IRR Sec. 43.1).' : rfqHint;
            reference.required = ['bid_bulletin', 'bac_resolution', 'abstract_of_quotations', 'video_recording'].includes(type.value);
        };
        type.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush
