@extends('layouts.portal')

@use('App\Support\Format')
@use('App\Support\BidProgress')

@php
    $tz = config('bac-office.display_timezone');
    $myBids->loadMissing('project.schedule');
    $stageCount = count(BidProgress::STAGES);

    // One row per bid: where it stands, what the bidder can still do, and which filter it belongs to.
    $rows = $myBids->map(function ($bid) use ($tz, $stageCount) {
        $project = $bid->project;
        $progress = $bid->progress()->toArray();
        $current = $progress['current'];
        $outcome = $progress['outcome'] ?? $progress['project_outcome'];
        $isOpen = $project?->isOpenForBidding() ?? false;
        $deadline = $project?->bidSubmissionDeadline()?->copy()->timezone($tz);
        $isDraft = $bid->isDraft();

        $group = match (true) {
            ($outcome['key'] ?? null) === BidProgress::OUTCOME_AWARDED || $current['tone'] === 'success' => 'won',
            $outcome !== null => 'ended',
            default => 'active',
        };

        $stages = collect($progress['stages']);
        $done = $stages->whereIn('state', ['done', 'unrecorded'])->count();
        $budget = (float) ($project?->budget ?? 0);
        $amount = (float) $bid->bid_amount;

        return [
            'bid' => $bid,
            'project' => $project,
            'group' => $group,
            'current' => $current,
            'outcome' => $outcome,
            'tone' => match ($current['tone']) {
                'success' => 'success',
                'danger' => 'danger',
                'warning' => 'warning',
                'muted' => 'neutral',
                default => 'info',
            },
            'step' => min($stageCount, $done + ($stages->contains('state', 'current') ? 1 : 0)),
            'currentStage' => $stages->firstWhere('state', 'current')['label'] ?? null,
            'isOpen' => $isOpen,
            'deadline' => $deadline,
            'daysLeft' => $isOpen && $deadline ? (int) now($tz)->startOfDay()->diffInDays($deadline->copy()->startOfDay(), false) : null,
            'isDraft' => $isDraft,
            'canModify' => $bid->canBeModifiedNow(),
            'canContinueDraft' => $isOpen && $isDraft,
            'amount' => $amount,
            'budget' => $budget,
            'variance' => $budget > 0 ? (($amount - $budget) / $budget) * 100 : null,
            'fileCount' => $bid->documents->count(),
        ];
    });

    $counts = $rows->countBy('group');
    $modifiable = $rows->where('canModify', true);
    $won = $rows->where('group', 'won');
    $filters = [
        'all' => ['All bids', $rows->count()],
        'active' => ['In progress', $counts['active'] ?? 0],
        'won' => ['Won', $counts['won'] ?? 0],
        'ended' => ['Ended', $counts['ended'] ?? 0],
    ];
@endphp

@section('title', 'My submission bids')
@section('subtitle', 'Every bid you submitted, where it stands with the BAC, and what you can still change.')

@section('actions')
    <a href="{{ route('bidder.available-projects') }}" class="ui-btn ui-btn--secondary"><i class="fas fa-bullhorn" aria-hidden="true"></i> View available bids</a>
@endsection

@push('head')
<style>
    .mb-list { display: grid; gap: 12px; padding: 14px; }
    .mb { display: grid; grid-template-columns: minmax(0, 1fr) 210px; overflow: hidden; border: 1px solid var(--ui-line); border-radius: var(--ui-radius-lg); background: var(--ui-surface); }
    .mb[hidden] { display: none; }
    .mb-main { display: grid; gap: 14px; min-width: 0; padding: 16px 18px; }
    .mb-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
    .mb-ref { display: flex; flex-wrap: wrap; gap: 4px 10px; margin: 0 0 4px; color: var(--ui-subtle); font-size: var(--ui-text-xs); font-weight: 600; }
    .mb-ref code { color: var(--ui-muted); font-family: var(--ui-mono); }
    .mb-title { margin: 0; color: var(--ui-ink); font-size: var(--ui-text-lg); font-weight: 700; line-height: 1.35; overflow-wrap: anywhere; }
    .mb-title a { color: inherit; text-decoration: none; }
    .mb-title a:hover { color: var(--ui-primary); }
    .mb-status { margin-top: 8px; }
    .mb-amount { flex: none; text-align: right; }
    .mb-amount span { display: block; color: var(--ui-subtle); font-size: var(--ui-text-xs); font-weight: 600; }
    .mb-amount strong { display: block; color: var(--ui-ink); font-size: 19px; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .mb-amount small { display: block; margin-top: 2px; color: var(--ui-muted); font-size: var(--ui-text-xs); }
    .mb-amount small.is-under b { color: var(--ui-success); }
    .mb-amount small.is-over b { color: var(--ui-danger); }

    .mb-progress { display: grid; gap: 6px; }
    .mb-progress-text { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 4px 12px; color: var(--ui-muted); font-size: var(--ui-text-sm); }
    .mb-progress-text strong { color: var(--ui-ink); }
    .mb-outcome { margin: 0; padding: 10px 12px; border-radius: var(--ui-radius); background: var(--ui-page); color: var(--ui-ink-2); font-size: var(--ui-text-sm); line-height: 1.5; }
    .mb-outcome strong { display: block; color: var(--ui-ink); }
    .mb-outcome.is-success { background: var(--ui-success-soft); }
    .mb-outcome.is-danger { background: var(--ui-danger-soft); }
    .mb-outcome.is-warning { background: var(--ui-warning-soft); }

    .mb-facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px 16px; margin: 0; padding-top: 12px; border-top: 1px solid var(--ui-line-soft); }
    .mb-facts div { min-width: 0; }
    .mb-facts dt { color: var(--ui-subtle); font-size: var(--ui-text-xs); font-weight: 600; }
    .mb-facts dd { margin: 2px 0 0; color: var(--ui-ink); font-size: var(--ui-text-sm); overflow-wrap: anywhere; }
    .mb-facts dd small { display: block; color: var(--ui-muted); font-size: var(--ui-text-xs); }
    .mb-facts dd small.is-soon { color: var(--ui-warning); font-weight: 600; }
    .mb-facts .ui-mono { font-size: 12px; }
    .mb-doc-review { display: grid; gap: 10px; margin-top: 14px; padding: 14px; border: 1px solid var(--ui-line); border-radius: var(--ui-radius-lg); background: var(--ui-surface-muted); }
    .mb-doc-review h4 { margin: 0; color: var(--ui-ink); font-size: var(--ui-text); }
    .mb-doc-review__locked { margin: 0; color: var(--ui-muted); font-size: var(--ui-text-sm); }
    .mb-doc-review__item { display: grid; gap: 6px; padding: 10px; border: 1px solid var(--ui-line-soft); border-radius: var(--ui-radius-md); background: var(--ui-surface); }
    .mb-doc-review__head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
    .mb-doc-review__head strong { color: var(--ui-ink); font-size: var(--ui-text-sm); }
    .mb-doc-review__item small { color: var(--ui-muted); font-size: var(--ui-text-xs); }
    .mb-doc-review__badge { flex: 0 0 auto; padding: 3px 8px; border-radius: 999px; background: var(--ui-warning-soft); color: var(--ui-warning-ink); font-size: var(--ui-text-xs); font-weight: 700; }
    .mb-doc-review__badge.is-accepted { background: var(--ui-success-soft); color: var(--ui-success-ink); }
    .mb-doc-review__badge.is-needs_revision { background: var(--ui-danger-soft); color: var(--ui-danger-ink); }
    .mb-doc-review__reason { margin: 0; color: var(--ui-danger-ink); font-size: var(--ui-text-sm); }
    .mb-doc-review__history summary { cursor: pointer; color: var(--ui-primary); font-size: var(--ui-text-sm); font-weight: 600; }
    .mb-doc-review__history ol { display: grid; gap: 6px; margin: 8px 0 0; padding-left: 20px; color: var(--ui-muted); font-size: var(--ui-text-xs); }
    .mb-doc-review__history li span { display: block; }
    .mb-doc-review__history li p { margin: 3px 0; color: var(--ui-ink); }
    .mb-doc-review__replace { display: flex; align-items: end; flex-wrap: wrap; gap: 8px; }
    .mb-doc-review__replace label { display: grid; gap: 4px; color: var(--ui-muted); font-size: var(--ui-text-xs); font-weight: 600; }
    .mb-doc-review__replace input { max-width: 100%; }

    .mb-side { display: grid; align-content: center; gap: 8px; padding: 16px; border-left: 1px solid var(--ui-line); background: var(--ui-page); }
    .mb-side .ui-btn { justify-content: center; }
    .mb-side-note { margin: 0; color: var(--ui-muted); font-size: var(--ui-text-xs); line-height: 1.45; text-align: center; }

    .mb-toolbar { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 10px 16px; padding: 0 14px; border-bottom: 1px solid var(--ui-line); }
    .mb-toolbar .ui-tabs { border-bottom: 0; }
    .mb-toolbar .ui-tab { border: 0; border-bottom: 2px solid transparent; background: none; font-family: inherit; cursor: pointer; }
    .mb-toolbar .ui-search { flex: 0 1 280px; margin: 8px 0; }

    @media (max-width: 900px) {
        .mb { grid-template-columns: 1fr; }
        .mb-side { grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); border-top: 1px solid var(--ui-line); border-left: 0; }
        .mb-side-note { grid-column: 1 / -1; }
        .mb-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 600px) {
        .mb-list { padding: 10px; }
        .mb-head { flex-direction: column; gap: 10px; }
        .mb-amount { text-align: left; }
        .mb-toolbar .ui-search { flex-basis: 100%; }
    }
</style>
@endpush

@section('content')
    <section class="ui-kpis" aria-label="Summary">
        <div class="ui-kpi">
            <span class="ui-kpi__label">Bids</span>
            <span class="ui-kpi__value">{{ $rows->count() }}</span>
            <span class="ui-kpi__foot">{{ $rows->where('isDraft', true)->count() ? $rows->where('isDraft', true)->count().' saved as draft, not submitted' : 'All submitted to the BAC' }}</span>
        </div>
        <div class="ui-kpi">
            <span class="ui-kpi__label">In progress</span>
            <span class="ui-kpi__value">{{ $counts['active'] ?? 0 }}</span>
            <span class="ui-kpi__foot">Awaiting opening or under evaluation</span>
        </div>
        <div class="ui-kpi {{ $modifiable->isNotEmpty() ? 'ui-kpi--warning' : '' }}">
            <span class="ui-kpi__label">You can still modify</span>
            <span class="ui-kpi__value">{{ $modifiable->count() }}</span>
            <span class="ui-kpi__foot">Online bids before their deadline</span>
        </div>
        <a href="{{ route('bidder.awarded-contracts') }}" class="ui-kpi">
            <span class="ui-kpi__label">Won</span>
            <span class="ui-kpi__value">{{ $won->count() }}</span>
            <span class="ui-kpi__foot">{{ $won->isNotEmpty() ? Format::peso($won->sum('amount')).' in awarded bids' : 'Awarded contracts appear here' }}</span>
        </a>
    </section>

    <section class="ui-card" aria-labelledby="bids-title">
        <div class="ui-card__head">
            <div>
                <h2 class="ui-card__title" id="bids-title">Your bids</h2>
                <p class="ui-card__desc" data-bids-count-label>{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('bid', $rows->count()) }}</p>
            </div>
        </div>

        @if($rows->isEmpty())
            <div class="ui-empty">
                <i class="fas fa-file-circle-question" aria-hidden="true"></i>
                <strong>No bids yet</strong>
                <span>Browse the open opportunities to submit your first bid.</span>
                <a href="{{ route('bidder.available-projects') }}" class="ui-btn ui-btn--primary ui-btn--sm ui-mt-sm">View available bids</a>
            </div>
        @else
            <div class="mb-toolbar">
                <nav class="ui-tabs" aria-label="Filter bids">
                    @foreach($filters as $key => [$label, $count])
                        <button type="button" class="ui-tab" data-bid-filter="{{ $key }}" @if($key === 'all') aria-current="page" @endif>
                            {{ $label }} <span class="ui-tab__count">{{ $count }}</span>
                        </button>
                    @endforeach
                </nav>
                <label class="ui-search">
                    <span class="sr-only">Search bids</span>
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" class="ui-input" id="bidderBidsSearch" placeholder="Search project or reference no." autocomplete="off">
                </label>
            </div>

            <div class="mb-list">
                @foreach($rows as $row)
                    @php
                        $bid = $row['bid'];
                        $project = $row['project'];
                        $trackUrl = route('bidder.bidding-track', ['bid' => $bid->id]);
                        $modifyUrl = route('bidder.available-projects', ['tab' => 'mine', 'bid_project' => $project?->id]);
                    @endphp
                    <article class="mb" data-bid-row data-group="{{ $row['group'] }}" data-search="{{ \Illuminate\Support\Str::lower(($project?->title ?? '').' '.($project?->reference_no ?? '').' '.($bid->receipt_no ?? '')) }}">
                        <div class="mb-main">
                            <div class="mb-head">
                                <div>
                                    <p class="mb-ref">
                                        @if($project?->reference_no)<code>{{ $project->reference_no }}</code>@endif
                                        @if($project?->end_user_unit)<span>{{ $project->end_user_unit }}</span>@endif
                                    </p>
                                    <h3 class="mb-title"><a href="{{ $trackUrl }}">{{ $project?->title ?? 'Project removed' }}</a></h3>
                                    <div class="mb-status">
                                        <span class="ui-badge ui-badge--{{ $row['tone'] }}">{{ $row['current']['label'] }}</span>
                                    </div>
                                </div>
                                <div class="mb-amount">
                                    <span>Your bid</span>
                                    <strong>{{ Format::peso($row['amount']) }}</strong>
                                    @if($row['variance'] !== null)
                                        <small class="{{ $row['variance'] > 0 ? 'is-over' : 'is-under' }}">
                                            <b>{{ number_format(abs($row['variance']), 1) }}% {{ $row['variance'] > 0 ? 'above' : 'below' }}</b> the ABC of {{ Format::peso($row['budget']) }}
                                        </small>
                                    @endif
                                </div>
                            </div>

                            @if($row['outcome'])
                                <p class="mb-outcome is-{{ $row['outcome']['tone'] === 'muted' ? 'neutral' : $row['outcome']['tone'] }}">
                                    <strong>{{ $row['outcome']['title'] }}{{ $row['outcome']['at'] ? ' · '.$row['outcome']['at'] : '' }}</strong>
                                    {{ $row['outcome']['message'] }}
                                </p>
                            @elseif(! $row['isDraft'])
                                <div class="mb-progress">
                                    <div class="mb-progress-text">
                                        <span>Step <strong>{{ $row['step'] }} of {{ $stageCount }}</strong>{{ $row['currentStage'] ? ': '.$row['currentStage'] : '' }}</span>
                                        <a href="{{ $trackUrl }}" class="ui-link">See full timeline</a>
                                    </div>
                                    <div class="ui-progress" role="progressbar" aria-label="Evaluation progress" aria-valuemin="0" aria-valuemax="{{ $stageCount }}" aria-valuenow="{{ $row['step'] }}">
                                        <span style="width: {{ round($row['step'] / $stageCount * 100) }}%"></span>
                                    </div>
                                </div>
                            @endif

                            <dl class="mb-facts">
                                <div>
                                    <dt>Receipt No.</dt>
                                    <dd>
                                        @if($bid->receipt_no)
                                            <span class="ui-mono">{{ $bid->receipt_no }}</span>
                                            @if(str_contains($bid->receipt_no, '-M'))<small>Modified bid</small>@endif
                                        @else
                                            {{ $row['isDraft'] ? 'None: draft only' : 'Recorded by the BAC' }}
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt>Submitted</dt>
                                    <dd>
                                        @if($bid->submitted_at)
                                            {{ $bid->submitted_at->copy()->timezone($tz)->format('M d, Y') }}
                                            <small>{{ $bid->submitted_at->copy()->timezone($tz)->format('h:i A') }}</small>
                                        @elseif($row['isDraft'])
                                            Not yet
                                        @else
                                            {{ $bid->created_at?->copy()->timezone($tz)->format('M d, Y') ?? '—' }}
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt>{{ $row['isOpen'] ? 'Deadline' : 'Deadline passed' }}</dt>
                                    <dd>
                                        {{ $row['deadline']?->format('M d, Y') ?? 'Not set' }}
                                        @if($row['deadline'])
                                            <small class="{{ $row['isOpen'] && $row['daysLeft'] !== null && $row['daysLeft'] <= 2 ? 'is-soon' : '' }}">
                                                {{ $row['deadline']->format('h:i A') }}@if($row['isOpen'] && $row['daysLeft'] !== null) · {{ $row['daysLeft'] <= 0 ? 'closes today' : $row['daysLeft'].' '.\Illuminate\Support\Str::plural('day', $row['daysLeft']).' left' }}@endif
                                            </small>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt>Documents</dt>
                                    <dd>
                                        @if($row['fileCount'] > 0)
                                            {{ $row['fileCount'] }} {{ \Illuminate\Support\Str::plural('file', $row['fileCount']) }} on record
                                            @if($row['isOpen'])<small>Sealed until the bid opening</small>@endif
                                        @elseif($bid->proposal_url)
                                            <a href="{{ $bid->proposal_url }}" target="_blank" rel="noopener" class="ui-link">View proposal</a>
                                        @else
                                            <span class="ui-muted">None uploaded online</span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                            @php
                                $technicalDocuments = $bid->documents->where('component', \App\Models\BidDocument::COMPONENT_TECHNICAL);
                                $submissionForRevision = $technicalDocuments->contains(fn ($document) => $document->reviewEvents->last()?->status === \App\Models\BidDocumentReviewEvent::STATUS_NEEDS_REVISION);
                                $submissionApproved = $technicalDocuments->isNotEmpty() && $technicalDocuments->every(fn ($document) => $document->reviewEvents->last()?->status === \App\Models\BidDocumentReviewEvent::STATUS_ACCEPTED);
                                $submissionReviewLabel = $submissionForRevision ? 'For Revision' : ($submissionApproved ? 'Approved' : 'Under Review');
                                $submissionReviewClass = $submissionForRevision ? 'is-needs_revision' : ($submissionApproved ? 'is-accepted' : 'is-pending');
                            @endphp
                            @if($technicalDocuments->isNotEmpty())
                                <section class="mb-doc-review" aria-label="Bid document review status">
                                    <h4>Technical document review <span class="mb-doc-review__badge {{ $submissionReviewClass }}">{{ $submissionReviewLabel }}</span></h4>
                                    @if($bid->isSealed())
                                        <p class="mb-doc-review__locked">Your technical and eligibility documents remain sealed until the authorized bid opening. Review results will appear here after they are opened.</p>
                                    @else
                                        @foreach($technicalDocuments as $document)
                                            @php
                                                $reviewEvents = $document->reviewEvents;
                                                $currentReview = $reviewEvents->last();
                                                $reviewStatus = $currentReview?->status ?? \App\Models\BidDocumentReviewEvent::STATUS_PENDING;
                                                $reviewLabel = match($reviewStatus) { 'accepted' => 'Accepted', 'needs_revision' => 'For Revision', default => 'Awaiting Review' };
                                            @endphp
                                            <article class="mb-doc-review__item">
                                                <div class="mb-doc-review__head">
                                                    <strong>{{ $document->label }}</strong>
                                                    <span class="mb-doc-review__badge is-{{ $reviewStatus }}">{{ $reviewLabel }}</span>
                                                </div>
                                                <small>Version {{ $currentReview?->version ?? 1 }} · Uploaded {{ ($currentReview?->uploaded_at ?? $document->updated_at)?->timezone($tz)->format('M d, Y h:i A') ?? 'date unavailable' }}</small>
                                                @if($reviewStatus === 'needs_revision' && $currentReview?->comment)
                                                    <p class="mb-doc-review__reason"><strong>Reviewer comment:</strong> {{ $currentReview->comment }}</p>
                                                @endif
                                                @if($reviewEvents->isNotEmpty())
                                                    <details class="mb-doc-review__history">
                                                        <summary>Revision history ({{ $reviewEvents->count() }})</summary>
                                                        <ol>
                                                            @foreach($reviewEvents as $reviewEvent)
                                                                <li>
                                                                    <strong>Version {{ $reviewEvent->version }} · {{ match($reviewEvent->status) { 'accepted' => 'Accepted', 'needs_revision' => 'For Revision', default => 'Submitted for review' } }}</strong>
                                                                    <span>{{ $reviewEvent->actor?->name ?? 'System' }} · {{ $reviewEvent->created_at?->timezone($tz)->format('M d, Y h:i A') }}</span>
                                                                    @if($reviewEvent->comment)<p>{{ $reviewEvent->comment }}</p>@endif
                                                                </li>
                                                            @endforeach
                                                        </ol>
                                                    </details>
                                                @endif
                                                @if($reviewStatus === 'needs_revision')
                                                    @if($row['isOpen'] && $bid->isModifiableOnline())
                                                        <form method="POST" action="{{ route('bidder.bid-document.replace', ['bid' => $bid, 'bidDocument' => $document]) }}" enctype="multipart/form-data" class="mb-doc-review__replace">
                                                            @csrf
                                                            <label>{{ blank($document->file_path) ? "Upload requested document" : "Upload replacement" }}
                                                                <input type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx" required>
                                                            </label>
                                                            <button type="submit" class="ui-btn ui-btn--primary ui-btn--sm">Resubmit document</button>
                                                        </form>
                                                    @else
                                                        <p class="mb-doc-review__locked">Replacements are closed because the bid submission deadline has passed.</p>
                                                    @endif
                                                @endif
                                            </article>
                                        @endforeach
                                    @endif
                                </section>
                            @endif
                        </div>

                        <div class="mb-side">
                            <a href="{{ $trackUrl }}" class="ui-btn {{ $row['canModify'] || $row['canContinueDraft'] ? 'ui-btn--secondary' : 'ui-btn--primary' }}"><i class="fas fa-route" aria-hidden="true"></i> Submission status</a>
                            @if($row['canModify'])
                                <a href="{{ $modifyUrl }}" class="ui-btn ui-btn--primary"><i class="fas fa-pen-to-square" aria-hidden="true"></i> Modify bid</a>
                                <p class="mb-side-note">You can change your price or files until {{ $row['deadline']?->format('M d, h:i A') ?? 'the deadline' }}.</p>
                            @elseif($row['canContinueDraft'])
                                <a href="{{ $modifyUrl }}" class="ui-btn ui-btn--primary"><i class="fas fa-paper-plane" aria-hidden="true"></i> Finish and submit</a>
                                <p class="mb-side-note">A draft is not a bid. Submit before {{ $row['deadline']?->format('M d, h:i A') ?? 'the deadline' }}.</p>
                            @elseif($row['group'] === 'won')
                                <a href="{{ route('bidder.awarded-contracts') }}" class="ui-btn ui-btn--secondary"><i class="fas fa-award" aria-hidden="true"></i> Awarded contract</a>
                            @elseif($row['isOpen'])
                                <p class="mb-side-note">The BAC has started on this bid, so it can no longer be modified.</p>
                            @endif
                        </div>
                    </article>
                @endforeach

                <div class="ui-empty" data-no-match hidden>
                    <i class="fas fa-filter-circle-xmark" aria-hidden="true"></i>
                    <strong>No bids match</strong>
                    <span>Try another filter or clear the search box.</span>
                </div>
            </div>
        @endif
    </section>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tabs = document.querySelectorAll('[data-bid-filter]');
        const rows = document.querySelectorAll('[data-bid-row]');
        const noMatch = document.querySelector('[data-no-match]');
        const search = document.getElementById('bidderBidsSearch');
        const countLabel = document.querySelector('[data-bids-count-label]');
        let filter = 'all';

        function apply() {
            const term = (search ? search.value : '').trim().toLowerCase();
            let shown = 0;
            rows.forEach(function (row) {
                const visible = (filter === 'all' || row.dataset.group === filter) && (!term || row.dataset.search.includes(term));
                row.hidden = !visible;
                if (visible) shown += 1;
            });
            if (noMatch) noMatch.hidden = shown !== 0;
            if (countLabel) countLabel.textContent = shown + (shown === 1 ? ' bid' : ' bids');
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                filter = tab.dataset.bidFilter;
                tabs.forEach(function (other) {
                    if (other === tab) other.setAttribute('aria-current', 'page');
                    else other.removeAttribute('aria-current');
                });
                apply();
            });
        });
        if (search) search.addEventListener('input', apply);
    });
</script>
@endpush
