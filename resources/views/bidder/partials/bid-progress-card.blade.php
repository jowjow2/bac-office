@php
    /** @var \App\Models\Bid $bid */
    $progress ??= $bid->progress()->toArray();
    $history ??= \App\Support\BidHistory::for($bid)->forBidder();
    $signature = \App\Support\BidHistory::trackerSignature($progress, $history);
    $tz = config('bac-office.display_timezone');
    $project = $bid->project;
    $current = $progress['current'];
    $outcome = $progress['outcome'];
    $projectOutcome = $progress['project_outcome'];
    $isOpen = $open ?? ! $progress['is_closed'];
    $stages = collect($progress['stages']);
    $currentStage = $stages->firstWhere('state', 'current');
    $nextStage = $currentStage ? $stages->slice($stages->search($currentStage) + 1)->firstWhere('state', 'pending') : null;
    $lastRecorded = $stages->whereIn('state', ['done', 'failed'])->last();
    $deadline = $project?->bidSubmissionDeadline()?->copy()->timezone($tz);
    $opening = $project?->schedule?->bid_opening_date?->copy()->timezone($tz);
    $canModify = $bid->canBeModifiedNow();
    $budget = (float) ($project?->budget ?? 0);
    $variance = $budget > 0 ? (((float) $bid->bid_amount - $budget) / $budget) * 100 : null;
    $toneClass = fn (?string $tone) => match ($tone) {
        'success' => 'success',
        'danger' => 'danger',
        'warning' => 'warning',
        'muted', 'neutral' => 'neutral',
        default => 'info',
    };

    $stateIcon = [
        'done' => 'fa-check',
        'current' => 'fa-circle',
        'pending' => 'fa-circle',
        'failed' => 'fa-xmark',
        'unrecorded' => 'fa-minus',
    ];
    $stateText = [
        'done' => 'Completed',
        'current' => 'Current stage',
        'pending' => 'Pending',
        'failed' => 'Stopped here',
        'unrecorded' => 'No record',
    ];
@endphp

<details class="bt-card" data-bid-id="{{ $bid->id }}" data-signature="{{ $signature }}" data-tone="{{ $current['tone'] }}" @if($isOpen) open @endif>
    <summary class="bt-card-head">
        <div class="bt-card-project">
            <p class="bt-card-ref">
                @if($project?->reference_no)<code>{{ $project->reference_no }}</code>@endif
                @if($project?->end_user_unit)<span>{{ $project->end_user_unit }}</span>@endif
            </p>
            <h3 class="bt-card-title">{{ $project?->title ?? 'Unknown Project' }}</h3>
            <span class="ui-badge ui-badge--{{ $toneClass($current['tone']) }}">{{ $current['label'] }}</span>
        </div>
        <div class="bt-card-amount">
            <span>Your bid</span>
            <strong>&#8369;{{ number_format((float) $bid->bid_amount, 2) }}</strong>
            @if($variance !== null)
                <small><b class="{{ $variance > 0 ? 'is-over' : 'is-under' }}">{{ number_format(abs($variance), 1) }}% {{ $variance > 0 ? 'above' : 'below' }}</b> the ABC of &#8369;{{ number_format($budget, 2) }}</small>
            @endif
        </div>
        <i class="fas fa-chevron-down bt-card-toggle" aria-hidden="true"></i>
    </summary>

    <div class="bt-card-body">
        <div class="bt-main">
            @if($outcome)
                <section class="bt-now is-{{ $toneClass($outcome['tone']) }}" role="status">
                    <span class="bt-now-label">Result</span>
                    <p class="bt-now-title">
                        <i class="fas {{ match($outcome['key']) { 'awarded' => 'fa-trophy', 'disqualified' => 'fa-circle-xmark', default => 'fa-circle-info' } }}" aria-hidden="true"></i>
                        {{ $outcome['title'] }}
                        @if($outcome['at'])<span class="bt-now-time">{{ $outcome['at'] }}</span>@endif
                    </p>
                    <p class="bt-now-text">@if($outcome['key'] === 'disqualified')<strong>Reason:</strong> @endif{{ $outcome['message'] }}</p>
                </section>
            @elseif(! $projectOutcome)
                <section class="bt-now is-info" role="status">
                    <span class="bt-now-label">Where your bid is now</span>
                    <p class="bt-now-title">{{ $currentStage['label'] ?? $current['label'] }}</p>
                    @if($currentStage)<p class="bt-now-text">{{ $currentStage['description'] }}</p>@endif
                    <p class="bt-now-next">
                        @if($nextStage)<span><i class="fas fa-arrow-right" aria-hidden="true"></i> Next: {{ $nextStage['label'] }}</span>@endif
                        <span>No result yet. Stages change only when the BAC or LGU records an action.</span>
                    </p>
                </section>
            @endif

            @if($projectOutcome)
                <section class="bt-now is-{{ $toneClass($projectOutcome['tone']) }}" role="status">
                    <span class="bt-now-label">Project result</span>
                    <p class="bt-now-title">
                        <i class="fas fa-rotate" aria-hidden="true"></i> {{ $projectOutcome['title'] }}
                        @if($projectOutcome['at'])<span class="bt-now-time">{{ $projectOutcome['at'] }}</span>@endif
                    </p>
                    <p class="bt-now-text">{{ $projectOutcome['message'] }}</p>
                    @if($projectOutcome['reason'])
                        <p class="bt-now-text"><strong>Ground:</strong> {{ $projectOutcome['reason'] }}</p>
                    @endif
                    @if($projectOutcome['rebid'])
                        <a class="ui-link" href="{{ $projectOutcome['rebid']['url'] }}">View the new bidding round: {{ $projectOutcome['rebid']['title'] }} <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                    @else
                        <p class="bt-now-text bt-subtle">A new bidding round has not been posted yet.</p>
                    @endif
                </section>
            @endif

            <h4 class="bt-section-title">Evaluation stages</h4>
            <ol class="bt-timeline" aria-label="Bid progress for {{ $project?->title ?? 'project' }}">
                @php $previousDescription = null; @endphp
                @foreach($progress['stages'] as $stage)
                    @php
                        // Later award stages share one description; show it once.
                        $showDescription = $stage['state'] !== 'pending' || $stage['description'] !== $previousDescription;
                        $previousDescription = $stage['description'];
                    @endphp
                    <li class="bt-step is-{{ $stage['state'] }}" @if($stage['state'] === 'current') aria-current="step" @endif>
                        <span class="bt-step-marker" aria-hidden="true">
                            <i class="fas {{ $stateIcon[$stage['state']] ?? 'fa-circle' }}"></i>
                        </span>
                        <div class="bt-step-body">
                            <div class="bt-step-row">
                                <h5 class="bt-step-title">{{ $stage['label'] }}</h5>
                                <span class="bt-step-when">
                                    @if($stage['at'])
                                        @if($stage['state'] === 'current' && $stage['note'])
                                            {{ $stage['note'] }}
                                        @endif
                                        <time datetime="{{ $stage['at_iso'] }}">{{ $stage['at'] }}</time>
                                    @elseif($stage['state'] === 'done')
                                        Date not recorded
                                    @else
                                        {{ $stateText[$stage['state']] ?? '' }}
                                    @endif
                                </span>
                            </div>
                            @if($showDescription)
                                <p class="bt-step-desc">{{ $stage['description'] }}</p>
                            @endif
                            @if($stage['state'] === 'done' && $stage['note'])
                                <p class="bt-step-note">{{ $stage['note'] }}</p>
                            @endif
                            <span class="bt-sr-only">Status: {{ $stateText[$stage['state']] ?? $stage['state'] }}</span>
                        </div>
                    </li>
                @endforeach
            </ol>

            @if($progress['is_closed'])
                <p class="bt-footnote">Later stages are not shown because this bid is no longer in the running for this project.</p>
            @endif
        </div>

        <aside class="bt-side" aria-label="Bid details">
            @if($canModify)
                <div class="bt-side-action">
                    <a href="{{ route('bidder.available-projects', ['tab' => 'mine', 'bid_project' => $project->id]) }}" class="ui-btn ui-btn--primary ui-btn--block"><i class="fas fa-pen-to-square" aria-hidden="true"></i> Modify bid</a>
                    <p>You can change your price or files until {{ $deadline?->format('M d, h:i A') ?? 'the deadline' }}.</p>
                </div>
            @endif

            <dl class="bt-facts">
                <div>
                    <dt>Receipt No.</dt>
                    <dd>
                        @if($bid->receipt_no)
                            <span class="ui-mono">{{ $bid->receipt_no }}</span>
                            @if(str_contains($bid->receipt_no, '-M'))<small>Modified bid</small>@endif
                        @else
                            {{ $bid->isDraft() ? 'None: draft only' : 'Recorded by the BAC' }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt>Submitted</dt>
                    <dd>{{ ($bid->submitted_at ?? $bid->created_at)?->copy()->timezone($tz)->format('M d, Y · h:i A') ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Submission deadline</dt>
                    <dd>{{ $deadline?->format('M d, Y · h:i A') ?? 'Not set' }}</dd>
                </div>
                <div>
                    <dt>Bid opening</dt>
                    <dd>{{ $opening?->format('M d, Y · h:i A') ?? 'To be announced' }}</dd>
                </div>
                <div>
                    <dt>Documents</dt>
                    <dd>
                        @if($bid->documents->isNotEmpty())
                            {{ $bid->documents->count() }} {{ \Illuminate\Support\Str::plural('file', $bid->documents->count()) }} on record
                        @elseif($bid->proposal_url)
                            <a href="{{ $bid->proposal_url }}" target="_blank" rel="noopener" class="ui-link">View proposal</a>
                        @else
                            None uploaded online
                        @endif
                    </dd>
                </div>
                @if($lastRecorded && $lastRecorded['at'])
                    <div>
                        <dt>Last recorded</dt>
                        <dd>{{ $lastRecorded['at'] }}</dd>
                    </div>
                @endif
            </dl>

            <section class="bt-history" aria-labelledby="bt-history-{{ $bid->id }}">
                <h4 class="bt-section-title" id="bt-history-{{ $bid->id }}">Activity history <span>({{ count($history) }})</span></h4>
                @if(empty($history))
                    <p class="bt-subtle">Nothing recorded yet.</p>
                @else
                    <ol class="ui-feed bt-feed">
                        @foreach(array_reverse($history) as $entry)
                            <li class="ui-feed__item {{ $entry['adverse'] ? 'ui-feed__item--danger' : 'ui-feed__item--success' }}">
                                <span class="ui-feed__dot" aria-hidden="true"></span>
                                <div>
                                    <div class="ui-feed__title">{{ $entry['title'] }}</div>
                                    <div class="ui-feed__detail">{{ $entry['stage'] }}{{ $entry['decision'] ? ' · '.$entry['decision'] : '' }}</div>
                                    @if($entry['reason'])
                                        <div class="ui-feed__detail"><strong>Reason:</strong> {{ $entry['reason'] }}</div>
                                    @endif
                                    <time class="ui-feed__meta" datetime="{{ $entry['at_iso'] }}">{{ $entry['at'] }}</time>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </aside>
    </div>
</details>
