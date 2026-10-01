@extends('layouts.portal')

@use('App\Support\Format')

@php
    /** @var \App\Models\Project $project */
    /** @var \App\Support\ProcurementMode $mode */
    $deadline = $project->bidSubmissionDeadline();
    $open = $project->isOpenForBidding();
    $noun = $mode->submissionNoun();
    $submitted = $myBid && ! $myBid->isDraft();
    $feeRequired = $project->requiresBiddingFee();
    $feePaid = $feePayment !== null;
    $canSubmit = $open && ! $submitted && (! $feeRequired || $feePaid);
    $days = $deadline && $deadline->isFuture() ? (int) now()->startOfDay()->diffInDays($deadline->copy()->startOfDay()) : null;
    $official = $project->officialDocuments();
    $schedule = $project->schedule;
    $toneMap = ['active' => 'info', 'muted' => 'neutral', 'success' => 'success', 'warning' => 'warning', 'danger' => 'danger'];
    $dates = collect([
        ['Published in BAC system', $project->published_at ?? $schedule?->date_posted, false],
        ['Actual PhilGEPS posting', $project->philgeps_posted_at, false],
        ['Pre-bid conference', $schedule?->pre_bid_conference_date, true],
        ['Deadline for clarifications', $schedule?->clarification_deadline, true],
        [$mode->deadlineLabel(), $deadline, true],
        [$mode->openingLabel(), $project->bids_opened_at ?? $schedule?->bid_opening_date, true],
    ])->filter(fn ($row) => $row[1] !== null);
@endphp

@section('title', $project->title)
@section('crumbs')
    <a href="{{ route('bidder.available-projects') }}">Opportunities</a>
    <span aria-hidden="true">/</span>
    <span class="ui-mono">{{ $project->reference_no ?: 'Project #'.$project->id }}</span>
@endsection
@section('subtitle', $mode->label().' · '.(\App\Support\BidSubmissionRequirements::CATEGORY_LABELS[$project->category] ?? 'Category not set').' · '.($project->end_user_unit ?: config('bac-office.procuring_entity')))

@section('actions')
    @if($canSubmit)
        <a href="{{ route('bidder.available-projects', ['bid_project' => $project->id]) }}" class="ui-btn ui-btn--primary"><i class="fas fa-paper-plane" aria-hidden="true"></i> Prepare {{ $noun }}</a>
    @elseif($submitted)
        <a href="{{ route('bidder.bidding-track') }}" class="ui-btn ui-btn--primary"><i class="fas fa-route" aria-hidden="true"></i> Track my {{ $noun }}</a>
    @endif
@endsection

@section('content')
    <section class="ui-kpis" aria-label="Key facts">
        <div class="ui-kpi">
            <span class="ui-kpi__label">Approved Budget for the Contract</span>
            <span class="ui-kpi__value ui-kpi__value--money ui-mono">{{ Format::peso($project->budget) }}</span>
            <span class="ui-kpi__foot">{{ ucfirst($noun) }}s above the ABC are not accepted</span>
        </div>
        <div class="ui-kpi {{ $open && $days !== null && $days <= 2 ? 'ui-kpi--warning' : '' }}">
            <span class="ui-kpi__label">{{ $mode->deadlineLabel() }}</span>
            <span class="ui-kpi__value ui-kpi__value--text">{{ Format::date($deadline, true) }}</span>
            <span class="ui-kpi__foot">{{ ! $open ? 'Closed' : ($days === 0 ? 'Closes today' : 'Closes in '.$days.' '.\Illuminate\Support\Str::plural('day', $days)) }}</span>
        </div>
        <div class="ui-kpi">
            <span class="ui-kpi__label">How to submit</span>
            <span class="ui-kpi__value ui-kpi__value--text">{{ $project->acceptsElectronicSubmission() ? 'Online, through this portal' : 'Sealed, at the BAC office' }}</span>
            <span class="ui-kpi__foot">{{ $project->acceptsElectronicSubmission() ? 'Upload each required document' : ($project->submission_venue ?: 'BAC Secretariat, '.config('bac-office.procuring_entity')) }}</span>
        </div>
        <div class="ui-kpi">
            <span class="ui-kpi__label">Your submission</span>
            <span class="ui-kpi__value ui-kpi__value--text">
                @if($submitted)
                    {{ $progress['current']['label'] }}
                @elseif($myBid)
                    Draft saved — not submitted
                @else
                    Not submitted
                @endif
            </span>
            <span class="ui-kpi__foot">{{ $submitted ? 'Received '.Format::date($myBid->submitted_at ?? $myBid->created_at, true).($myBid->receipt_no ? ' · Receipt '.$myBid->receipt_no : '') : 'A saved draft is not a '.$noun }}</span>
        </div>
    </section>

    @if($submitted && ($progress['outcome'] ?? null))
        <section class="ui-callout ui-callout--{{ ($progress['outcome']['tone'] ?? '') === 'danger' ? 'danger' : 'success' }}">
            <span class="ui-callout__label">Result</span>
            <h2 class="ui-callout__title">{{ $progress['outcome']['title'] }}</h2>
            <p class="ui-callout__text">{{ $progress['outcome']['message'] }}</p>
        </section>
    @elseif(! $open && ! $submitted)
        <div class="ui-alert ui-alert--warning" role="status">
            <i class="fas fa-lock" aria-hidden="true"></i>
            <span>The {{ lcfirst($mode->deadlineLabel()) }} has passed. {{ ucfirst($noun) }}s are no longer accepted.</span>
        </div>
    @endif

    <div class="ui-grid ui-grid--sidebar">
        <div class="ui-stack">
            <section class="ui-card" aria-labelledby="steps-title">
                <header class="ui-card__head">
                    <div>
                        <h2 class="ui-card__title" id="steps-title">Before you submit</h2>
                        <p class="ui-card__desc">What this {{ strtolower($mode->noticeLabel()) }} requires, in order.</p>
                    </div>
                </header>
                <div class="ui-card__body">
                    <ol class="ui-timeline">
                        <li class="ui-timeline__item {{ $official->isNotEmpty() ? 'is-done' : 'is-upcoming' }}">
                            <span class="ui-timeline__marker" aria-hidden="true">1</span>
                            <div>
                                <p class="ui-timeline__title">Get the {{ $mode->isCompetitive() ? 'bidding documents' : 'RFQ and specifications' }}</p>
                                <p class="ui-timeline__meta">{{ $official->count() }} official {{ \Illuminate\Support\Str::plural('file', $official->count()) }} below{{ $bulletins->isNotEmpty() ? ', plus '.$bulletins->count().' bid '.\Illuminate\Support\Str::plural('bulletin', $bulletins->count()) : '' }}. Read every bulletin: it can change the requirements.</p>
                            </div>
                        </li>
                        <li class="ui-timeline__item {{ ! $feeRequired ? 'is-skipped' : ($feePaid ? 'is-done' : 'is-current') }}">
                            <span class="ui-timeline__marker" aria-hidden="true">2</span>
                            <div>
                                <p class="ui-timeline__title">Bidding documents fee {{ $feeRequired ? '— '.Format::peso($project->bidding_documents_fee) : ($project->biddingFeeWaived() ? '— waived by the BAC' : '— none for this notice') }}</p>
                                <p class="ui-timeline__meta">
                                    @if(! $feeRequired)
                                        The notice does not charge a fee for the documents.
                                    @elseif($feePaid)
                                        Paid. Official Receipt <span class="ui-mono">{{ $feePayment->or_number }}</span> recorded {{ Format::date($feePayment->paid_at) }}.
                                    @else
                                        Pay at {{ $project->paymentVenueLabel() }}. The BAC records your Official Receipt; submission opens after that. The fee is separate from the bid security.
                                    @endif
                                </p>
                            </div>
                        </li>
                        <li class="ui-timeline__item {{ $project->bid_security_required ? 'is-upcoming' : 'is-skipped' }}">
                            <span class="ui-timeline__marker" aria-hidden="true">3</span>
                            <div>
                                <p class="ui-timeline__title">Bid security {{ $project->bid_security_required ? '— required' : '— not required' }}</p>
                                <p class="ui-timeline__meta">{{ $project->bid_security_required ? ($project->bid_security_notes ?: 'Submit it with your '.$noun.' in the form stated in the notice (Bid Securing Declaration, cash, bank draft or guarantee, or surety bond).') : 'This notice does not ask for a bid security.' }}</p>
                            </div>
                        </li>
                        <li class="ui-timeline__item is-upcoming">
                            <span class="ui-timeline__marker" aria-hidden="true">4</span>
                            <div>
                                <p class="ui-timeline__title">Prepare the required documents</p>
                                <p class="ui-timeline__meta">{{ $requirements->technical()->count() }} eligibility and technical, {{ $requirements->financial()->count() }} financial — listed below.</p>
                            </div>
                        </li>
                        <li class="ui-timeline__item {{ $submitted ? 'is-done' : ($open ? 'is-upcoming' : 'is-stopped') }}">
                            <span class="ui-timeline__marker" aria-hidden="true">5</span>
                            <div>
                                <p class="ui-timeline__title">Submit before {{ Format::date($deadline, true) }}</p>
                                <p class="ui-timeline__meta">{{ $project->acceptsElectronicSubmission() ? 'Upload through this portal. Late submissions are not accepted.' : 'Bring your sealed '.$noun.' to '.($project->submission_venue ?: 'the BAC Secretariat').'. The BAC records the receipt.' }}</p>
                            </div>
                        </li>
                    </ol>
                    @if($open && ! $submitted)
                        <div class="ui-actions ui-mt">
                            @if($canSubmit)
                                <a href="{{ route('bidder.available-projects', ['bid_project' => $project->id]) }}" class="ui-btn ui-btn--primary">Prepare {{ $noun }}</a>
                            @else
                                <span class="ui-hint">Submission opens once the BAC records your bidding documents fee.</span>
                            @endif
                        </div>
                    @endif
                </div>
            </section>

            <section class="ui-card" aria-labelledby="reqs-title">
                <header class="ui-card__head">
                    <div>
                        <h2 class="ui-card__title" id="reqs-title">Required documents</h2>
                        <p class="ui-card__desc">{{ $mode->isCompetitive() ? 'Technical and eligibility component (first envelope) and financial component (second envelope).' : 'Documents to send with your quotation. The RFQ issued by the BAC governs if it lists different requirements.' }}</p>
                    </div>
                </header>
                <div class="ui-card__body">
                    @foreach(['Eligibility and technical' => $requirements->technical(), 'Financial' => $requirements->financial()] as $group => $items)
                        <p class="ui-files__group">{{ $group }}</p>
                        <ul class="ui-checklist">
                            @foreach($items as $item)
                                <li>
                                    <i class="far fa-square" aria-hidden="true"></i>
                                    <span>{{ $item['label'] }}@if($item['condition'])<small>{{ $item['condition'] }}</small>@endif</span>
                                    <span class="ui-pill ui-pill--{{ $item['required'] ? 'neutral' : 'info' }}">{{ $item['required'] ? 'Required' : 'If applicable' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="ui-stack">
            <section class="ui-card" aria-labelledby="dates-title">
                <header class="ui-card__head"><h2 class="ui-card__title" id="dates-title">Schedule</h2></header>
                <div class="ui-card__body">
                    <dl class="ui-dl">
                        @foreach($dates as [$label, $date, $time])
                            <div><dt>{{ $label }}</dt><dd class="ui-num">{{ Format::date($date, $time) }}</dd></div>
                        @endforeach
                    </dl>
                </div>
            </section>

            <section class="ui-card" aria-labelledby="docs-title">
                <header class="ui-card__head"><h2 class="ui-card__title" id="docs-title">Official documents</h2></header>
                <div class="ui-card__body">
                    @if($official->isEmpty())
                        <p class="ui-hint">No documents are posted online yet. Obtain them from the BAC Secretariat.</p>
                    @else
                        <ul class="ui-files">
                            @foreach($official as $index => $document)
                                <li>
                                    <i class="fas fa-file-lines" aria-hidden="true"></i>
                                    <a class="ui-link" href="{{ route('bidder.project.document.preview', ['project' => $project, 'document' => $index]) }}" target="_blank" rel="noopener">{{ $document->document_type ? $document->document_type_label.': ' : '' }}{{ $document->display_name }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if($bulletins->isNotEmpty())
                        <p class="ui-files__group ui-mt">Bid bulletins</p>
                        <ul class="ui-files">
                            @foreach($bulletins as $bulletin)
                                <li><i class="fas fa-bullhorn" aria-hidden="true"></i><span>{{ $bulletin->title }} @if($bulletin->reference_no)<span class="ui-mono">({{ $bulletin->reference_no }})</span>@endif · {{ Format::date($bulletin->occurred_at) }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            <section class="ui-card" aria-labelledby="notice-title">
                <header class="ui-card__head"><h2 class="ui-card__title" id="notice-title">Notice</h2></header>
                <div class="ui-card__body">
                    <dl class="ui-dl">
                        <div><dt>Procuring entity</dt><dd>{{ config('bac-office.procuring_entity') }}</dd></div>
                        <div><dt>Mode</dt><dd>{{ $mode->label() }}</dd></div>
                        <div><dt>Legal basis</dt><dd>{{ $mode->legalBasisShort() }}</dd></div>
                        <div><dt>BAC publication</dt><dd>{{ $project->published_at ? Format::date($project->published_at, true) : 'Published in this system' }} <span class="ui-optional">(local)</span></dd></div>
                        <div><dt>PhilGEPS reference</dt><dd>
                            <span class="ui-mono">{{ $project->philgeps_reference_no ?: 'Not recorded' }}</span>
                            @if($project->philgeps_url)
                                <br><a class="ui-link" href="{{ $project->philgeps_url }}" target="_blank" rel="noopener noreferrer">View on PhilGEPS</a>
                            @endif
                        </dd></div>
                        <div><dt>Delivery / duration</dt><dd>{{ $project->contract_duration ?: '—' }}</dd></div>
                        <div><dt>Source of funds</dt><dd>{{ $project->source_of_fund ?: '—' }}</dd></div>
                        <div><dt>Location</dt><dd>{{ $project->location ?: '—' }}</dd></div>
                        @include('partials.invitation.notice-rows', ['project' => $project])
                    </dl>
                    <p class="ui-hint ui-mt-sm">This opportunity is published in the BAC system. Any PhilGEPS reference shown above is an optional external record and is not generated by this portal. Questions? <a href="{{ route('bidder.messages') }}" class="ui-link">Message the BAC</a>.</p>
                </div>
            </section>

            <section class="ui-card" aria-labelledby="contact-title">
                <header class="ui-card__head"><h2 class="ui-card__title" id="contact-title">BAC contact</h2></header>
                <div class="ui-card__body">
                    @include('partials.invitation.contact')
                </div>
            </section>
        </div>
    </div>
@endsection
