{{--
    Public Bid Notice Abstract of a project, laid out like the PhilGEPS notice.
    Only what the Invitation to Bid itself publishes is shown: no bid counts, bidder
    names or amounts, internal references, opening rules or who recorded what.

    @param \App\Models\Project $project
    @param bool $compact  inside the listing's detail dialog
--}}
@php
    $compact = $compact ?? false;
    $tz = config('bac-office.display_timezone', 'Asia/Manila');
    $when = fn ($at, $format = 'M d, Y h:i A') => $at ? $at->copy()->timezone($tz)->format($format) : null;
    $mode = $project->mode();
    $schedule = $project->schedule;
    $deadline = $project->bidSubmissionDeadline();
    $documents = $project->publicDocuments();
    $statusLabel = ucwords(str_replace('_', ' ', $project->status));
    $categoryLabel = $project->category ? (string) \Illuminate\Support\Str::of($project->category)->replace('_', ' ')->title() : 'Uncategorized';
    $contact = \App\Support\ProcuringEntity::contact();
    $isOpen = $project->status === 'open';
    $participateUrl = route('login.page', ['qr_project' => $project->id]);
    $qrUrl = route('public.procurement.qr', ['project' => $project, 'status' => $project->status]);
    $titleId = ($compact ? 'bid-notice-dialog-' : 'bid-notice-').$project->id.'-title';

    $details = array_filter([
        'Reference No.' => $project->reference_no,
        'PhilGEPS Reference No.' => $project->philgeps_reference_no,
        'Procuring entity' => \App\Support\ProcuringEntity::name(),
        'Procurement mode' => $mode->label().' ('.$mode->legalBasisShort().')',
        'Classification' => $categoryLabel,
        'Area of delivery' => $project->location,
        'Delivery / contract period' => $project->contract_duration,
        'Source of fund' => $project->source_of_fund,
        'Award criterion' => $mode->isCompetitive() ? ($project->awardCriterionLabel() ?? 'As stated in the bidding documents') : null,
        // Sec. 50.2(e)-(g): criteria, weights and ratio for MEARB/MARB; procedure for consulting.
        'Criteria and weights' => $mode->isCompetitive() && $project->usesWeightedCriteria() && ! empty($project->evaluation_criteria)
            ? collect($project->evaluation_criteria)->map(fn ($c) => $c['name'].' '.rtrim(rtrim(number_format((float) $c['weight'], 2), '0'), '.').'%')->implode(', ')
                .($project->award_criterion === 'mearb' && $project->quality_price_ratio ? '; quality-price ratio '.$project->quality_price_ratio.'% technical / '.(100 - $project->quality_price_ratio).'% price' : '')
            : null,
        'Evaluation procedure' => $project->category === 'consultancy' && $project->evaluation_procedure
            ? (\App\Models\Project::EVALUATION_PROCEDURES[$project->evaluation_procedure] ?? $project->evaluation_procedure)
            : null,
        'Date published' => $when($project->published_at, 'M d, Y'),
        'Pre-bid conference' => $when($schedule?->pre_bid_conference_date),
        'Deadline for clarifications' => $when($schedule?->clarification_deadline),
        'Bid opening' => $mode->isCompetitive() && ($schedule?->bid_opening_date || $project->bid_opening_venue)
            ? trim(($when($schedule?->bid_opening_date) ?? '').($project->bid_opening_venue ? ' · '.$project->bid_opening_venue : ''), ' ·')
            : null,
        'Submission of bids' => match ($project->submission_mode) {
            \App\Models\Project::SUBMISSION_ELECTRONIC => 'Online, through the SJBAC bidder portal',
            \App\Models\Project::SUBMISSION_MANUAL => 'Sealed bids at '.($project->submission_venue ?: 'the BAC Secretariat'),
            default => null,
        },
        // The fee and how it was computed (GPPB Circular No. 02-2026, Sec. 5.2 for competitive bidding),
        // with any amendment issued after publication.
        'Bidding documents fee' => $project->requiresBiddingFee() || $project->biddingFeeWaived()
            ? (function () use ($project) {
                $fee = \App\Support\BiddingDocumentsFee::describe($project);
                $amendment = $project->biddingFeeAmendments()->first();

                return $fee['text']
                    .($fee['reason'] ? ' Reason: '.$fee['reason'].'.' : '')
                    .($project->requiresBiddingFee() ? ' Pay at '.$project->paymentVenueLabel().'; separate from the bid security.' : '')
                    .($amendment ? ' Amended by '.$amendment->reference.' on '.$amendment->created_at?->format('M d, Y').'.' : '');
            })()
            : null,
        'Bid security' => $project->bid_security_required ? ($project->bid_security_notes ?: 'Required, as stated in the bidding documents') : null,
    ], fn ($value) => filled($value));
@endphp

<article class="bid-notice {{ $compact ? 'is-compact' : '' }}" aria-labelledby="{{ $titleId }}">
    <header class="bid-notice-head">
        <p class="bid-notice-eyebrow">
            <span>Bid Notice Abstract</span>
            <span class="public-status public-status-{{ $project->status }}">{{ $statusLabel }}</span>
        </p>
        <h2 id="{{ $titleId }}">{{ $project->title }}</h2>
        <p class="bid-notice-entity">{{ \App\Support\ProcuringEntity::name() }}@if($project->reference_no) &middot; <span class="bid-notice-mono">{{ $project->reference_no }}</span>@endif</p>
    </header>

    <dl class="bid-notice-keys">
        <div><dt>Approved budget for the contract</dt><dd class="is-amount">&#8369;{{ number_format((float) $project->budget, 2) }}</dd></div>
        <div><dt>Closing date / time</dt><dd>{{ $when($deadline) ?? 'To be announced' }}</dd></div>
        <div><dt>Procurement mode</dt><dd>{{ $mode->label() }}</dd></div>
        <div><dt>Category</dt><dd>{{ $categoryLabel }}</dd></div>
    </dl>

    @if($project->description)
        <section class="bid-notice-section">
            <h3>Description</h3>
            <p class="bid-notice-text">{{ $project->description }}</p>
        </section>
    @endif

    <section class="bid-notice-section">
        <h3>Notice details</h3>
        <dl class="bid-notice-details">
            @foreach($details as $label => $value)
                <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
        </dl>
    </section>

    <section class="bid-notice-section">
        <h3>Bidding documents</h3>
        @if($documents->isEmpty())
            <p class="bid-notice-empty">No bidding documents have been posted for this project yet.</p>
        @else
            <ul class="bid-notice-files">
                @foreach($documents as $index => $document)
                    <li>
                        <span class="bid-notice-file-icon" aria-hidden="true">PDF</span>
                        <span class="bid-notice-file-text">
                            <strong>{{ $document->document_type_label }}</strong>
                            <small>{{ $document->display_name ?: 'Bidding file' }}</small>
                        </span>
                        <a href="{{ route('public.procurement.document.preview', ['project' => $project, 'document' => $index]) }}" target="_blank" rel="noopener" class="btn-outline">View</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="bid-notice-section bid-notice-participate">
        <div class="bid-notice-qr">
            <a href="{{ $isOpen ? $participateUrl : route('public.procurement.show', $project) }}" aria-label="{{ $isOpen ? 'Participate' : 'View' }}: {{ $project->title }}">
                <img src="{{ $qrUrl }}" alt="QR code for {{ $project->title }}" loading="lazy">
            </a>
        </div>
        <div class="bid-notice-participate-copy">
            <h3>{{ $isOpen ? 'How to participate' : 'This notice is '.strtolower($statusLabel) }}</h3>
            @if($isOpen)
                <p>Scan the code or select <strong>Login to Participate</strong>. Sign in with an approved SJBAC bidder account to download the bidding documents and submit your bid before the closing date.</p>
            @else
                <p>Bids are no longer accepted for this project. Results are posted under Awards &amp; Contracts once awarded.</p>
            @endif
            {{-- BAC contact required in every Invitation to Bid (Sec. 50.2(m)). --}}
            <p class="bid-notice-contact">
                <strong>BAC contact:</strong>
                @if($contact['person']){{ $contact['person'] }}{{ $contact['person_position'] ? ', '.$contact['person_position'] : '' }} &middot; @endif
                {{ collect([$contact['office'], $contact['address']])->filter()->implode(', ') }}
                <br>
                @if($contact['email'])<a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a>@endif
                @if($contact['phone']) &middot; Tel. {{ $contact['phone'] }}@endif
                @if($contact['fax']) &middot; Fax {{ $contact['fax'] }}@endif
                @if($contact['website']) &middot; <a href="{{ $contact['website'] }}">{{ preg_replace('#^https?://#', '', $contact['website']) }}</a>@endif
            </p>
        </div>
    </section>
</article>
