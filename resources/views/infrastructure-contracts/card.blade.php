@php
    use App\Models\ContractImplementation as CI;

    $status = $record->status;
    $isWinner = $mode === 'bidder';
    $canInspect = in_array($mode, ['admin', 'staff'], true) && $status === CI::INFRA_FOR_INSPECTION;
    $canAct = in_array($mode, ['admin', 'staff'], true) && in_array($status, [CI::INFRA_RECOMMENDED, CI::INFRA_ACCEPTED, CI::INFRA_PAYMENT_PROCESSING, CI::INFRA_PAID], true);

    // Who acts next, for viewers who have no form at this stage.
    $canReport = $isWinner && in_array($status, [CI::INFRA_IN_PROGRESS, CI::INFRA_FOR_CORRECTION], true);
    $office = $award->project?->end_user_unit;
    $nextStep = null;
    if ($record->isConfigured() && ! $canReport && ! $canInspect && ! $canAct) {
        $nextStep = match ($status) {
            CI::INFRA_IN_PROGRESS => ['Waiting for the contractor', 'The contractor submits progress updates and requests a site inspection once the work is finished.'],
            CI::INFRA_FOR_CORRECTION => ['Waiting for the contractor', 'Corrections were requested after the site inspection. The contractor submits the corrected work for reinspection.'],
            CI::INFRA_FOR_INSPECTION => ['Waiting for the site inspection', 'The BAC admin or assigned staff records the site inspection'.($office ? ' for '.$office : '').': recommend acceptance, or ask for corrections.'],
            CI::INFRA_RECOMMENDED => ['Waiting for formal acceptance', 'The site inspection recommended the work for acceptance. The BAC admin or assigned staff formally accepts it.'],
            CI::INFRA_ACCEPTED => ['Waiting for payment processing', 'The BAC admin or assigned staff records when payment processing starts.'],
            CI::INFRA_PAYMENT_PROCESSING => ['Waiting for payment', 'The BAC admin or assigned staff records the paid status.'],
            CI::INFRA_PAID => ['Waiting for close-out', 'The BAC admin or assigned staff marks the contract implementation completed.'],
            CI::INFRA_COMPLETED => ['Contract completed.', 'All recorded implementation steps are complete.'],
            default => null,
        };
    }
    $tz = 'Asia/Manila';
    $supplier = $award->bid?->user?->company ?: $award->bid?->user?->name;
    $ntpOn = $award->ntp_issued_on ?? $award->bid?->notice_to_proceed_at?->timezone($tz);
    $configured = $record->isConfigured();

    // Starting values for the terms form, taken from the project and its purchase request.
    // They are only suggestions: the BAC checks them against the signed contract before saving.
    $project = $award->project;
    $request = $project->procurementRequest;
    $suggestedDeadline = null;
    if ($ntpOn && preg_match('/(\d+)\s*(calendar\s+)?(day|week|month|year)s?/i', (string) $project->contract_duration, $duration)) {
        $suggestedDeadline = match (strtolower($duration[3])) {
            'week' => $ntpOn->copy()->addWeeks((int) $duration[1]),
            'month' => $ntpOn->copy()->addMonthsNoOverflow((int) $duration[1]),
            'year' => $ntpOn->copy()->addYearsNoOverflow((int) $duration[1]),
            default => $ntpOn->copy()->addDays((int) $duration[1]),
        };
    }
    $suggestedItem = ['description' => $request?->title ?: $project->title, 'quantity' => '', 'unit' => ''];
    if ($request && (float) $request->quantity > 0 && filled($request->unit)) {
        $suggestedItem['quantity'] = rtrim(rtrim((string) $request->quantity, '0'), '.');
        $suggestedItem['unit'] = $request->unit;
    } elseif (preg_match('/^(.*?)\s*\(\s*([\d,]+(?:\.\d+)?)\s*([^\d()][^()]{0,20})\)\s*$/u', $suggestedItem['description'], $sized)) {
        // e.g. "Concreting of farm-to-market road (250 lm)"
        $suggestedItem = ['description' => $sized[1], 'quantity' => str_replace(',', '', $sized[2]), 'unit' => trim($sized[3])];
    }
    // The purchase request's own item list, when it has one.
    $requestItems = is_array($request?->items) ? $request->items : [];
    $suggestedItems = $requestItems !== []
        ? array_map(fn ($item) => ['description' => $item['description'] ?? '', 'quantity' => rtrim(rtrim(number_format((float) ($item['quantity'] ?? 0), 2, '.', ''), '0'), '.'), 'unit' => $item['unit'] ?? ''], $requestItems)
        : [$suggestedItem];
    // Old input only refills the form that was actually sent (record or correct).
    $termsOld = function (string $form, $default, ?string $field = null) {
        $mine = old('infra_form') === $form;
        if ($field !== null) {
            return $mine ? old($field, $default) : $default;
        }
        return $mine ? collect($default)->map(fn ($value, $key) => old($key, $value))->all() : $default;
    };
    $canCorrectTerms = $mode === 'admin' && app(\App\Support\InfrastructureImplementationWorkflow::class)->canCorrectTerms($record);

    // Where the contract stands (a correction sends the work back to inspection).
    $steps = [
        CI::INFRA_IN_PROGRESS => 'Work in progress',
        CI::INFRA_FOR_INSPECTION => 'Site inspection',
        CI::INFRA_RECOMMENDED => 'Recommended',
        CI::INFRA_ACCEPTED => 'Accepted',
        CI::INFRA_PAYMENT_PROCESSING => 'Payment',
        CI::INFRA_PAID => 'Paid',
        CI::INFRA_COMPLETED => 'Completed',
    ];
    $stepKeys = array_keys($steps);
    $currentIndex = $configured ? array_search($status === CI::INFRA_FOR_CORRECTION ? CI::INFRA_FOR_INSPECTION : $status, $stepKeys, true) : false;
    // A completed contract shows every step done, the last one included.
    $allDone = $configured && $status === CI::INFRA_COMPLETED;
    $tone = match (true) {
        ! $configured => 'neutral',
        $status === CI::INFRA_FOR_CORRECTION => 'danger',
        $status === CI::INFRA_COMPLETED => 'success',
        in_array($status, [CI::INFRA_FOR_INSPECTION, CI::INFRA_RECOMMENDED], true) => 'warning',
        default => 'info',
    };
    $latestProgress = $record->events->sortByDesc('occurred_at')->first(fn ($event) => isset($event->details['progress_percent']));
    $progress = $status === CI::INFRA_COMPLETED ? 100 : (int) ($latestProgress?->details['progress_percent'] ?? 0);
    $eventIcon = fn (string $action) => match (true) {
        str_contains($action, 'terms_corrected') => 'fa-pen-to-square',
        str_contains($action, 'terms') => 'fa-file-signature',
        str_contains($action, 'progress') => 'fa-person-digging',
        str_contains($action, 'inspect') => 'fa-clipboard-check',
        str_contains($action, 'payment') || str_contains($action, 'paid') => 'fa-money-check',
        str_contains($action, 'complete') || str_contains($action, 'accept') => 'fa-circle-check',
        default => 'fa-circle-dot',
    };
@endphp

<section class="ui-card infra-card">
    <header class="infra-head">
        <div class="infra-head__main">
            <p class="infra-kicker"><span class="ui-ref">{{ $award->project->reference_no }}</span> <span class="ui-pill ui-pill--neutral">Infrastructure</span></p>
            <h2 class="infra-title">{{ $award->project->title }}</h2>
            <dl class="infra-meta">
                <div><dt>Contractor</dt><dd>{{ $supplier ?: '—' }}</dd></div>
                <div><dt>Notice to Proceed</dt><dd>{{ $ntpOn?->format('M d, Y') ?? '—' }}</dd></div>
                @if($configured)
                    <div><dt>Completion deadline</dt><dd>{{ $record->effectiveDeadline()?->format('M d, Y') ?? '—' }}</dd></div>
                @endif
            </dl>
        </div>
        <span class="ui-badge ui-badge--{{ $tone }} infra-status">{{ $configured ? $record->label() : 'Awaiting signed contract details' }}</span>
    </header>

    @if($configured)
        <div class="infra-progress" aria-label="Contract progress">
            <div class="infra-progress__bar" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">
                <span style="width: {{ $progress }}%"></span>
            </div>
            <span class="infra-progress__value">{{ $progress }}% reported</span>
        </div>
        <ol class="infra-steps">
            @foreach($steps as $key => $label)
                @php
                    $index = $loop->index;
                    $isDone = $allDone || ($currentIndex !== false && $index < $currentIndex);
                    $isCurrent = ! $allDone && $index === $currentIndex;
                    $isFlagged = $isCurrent && $status === CI::INFRA_FOR_CORRECTION;
                @endphp
                <li class="{{ $isDone ? 'is-done' : '' }} {{ $isCurrent ? 'is-current' : '' }} {{ $isFlagged ? 'is-flagged' : '' }}" @if($isCurrent || ($allDone && $loop->last)) aria-current="step" @endif>
                    <span class="infra-steps__dot" aria-hidden="true">@if($isDone)<i class="fas fa-check"></i>@else{{ $index + 1 }}@endif</span>
                    <span class="infra-steps__label">{{ $isFlagged ? 'For correction' : $label }}</span>
                </li>
            @endforeach
        </ol>
    @endif

    @if(! $configured && in_array($mode, ['admin', 'staff'], true))
        <form method="POST" enctype="multipart/form-data" action="{{ route($mode === 'staff' ? 'staff.infrastructure.configure' : 'admin.infrastructure.configure', $award) }}" class="infra-panel">
            @csrf
            @method('PUT')
            <div class="infra-panel__head">
                <h3>Record terms from signed contract</h3>
                <p class="ui-hint">Enter the actual completion deadline and work site from the signed contract. The NTP date is shown separately and is not the deadline.</p>
            </div>

            @include('infrastructure-contracts.terms-fields', [
                'form' => 'record',
                'values' => $termsOld('record', [
                    'delivery_deadline' => $suggestedDeadline?->toDateString(),
                    'delivery_location' => $project->location,
                    'signed_contract_reference' => $project->reference_no,
                ]),
                'items' => $termsOld('record', $suggestedItems, 'contract_items'),
                'deadlineHint' => $suggestedDeadline ? 'NTP date + '.$project->contract_duration.'.' : null,
                'note' => 'Filled in from the project. Check each value against the signed contract before saving.',
            ])

            <div class="ui-fields">
                <label class="ui-field">
                    <span class="ui-label">Remarks <span class="ui-required">*</span></span>
                    <textarea name="remarks" class="ui-input" rows="3" maxlength="2000" required placeholder="e.g. Terms copied from the signed contract.">{{ $termsOld('record', null, 'remarks') }}</textarea>
                </label>
                <label class="ui-field">
                    <span class="ui-label">Signed contract / supporting proof <span class="ui-required">*</span></span>
                    <input type="file" name="document" class="ui-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                    <span class="ui-hint">PDF, image or Word file.</span>
                </label>
            </div>

            <div class="infra-panel__foot">
                <button class="ui-btn ui-btn--primary"><i class="fas fa-floppy-disk" aria-hidden="true"></i> Save infrastructure terms</button>
            </div>
        </form>
    @elseif($configured)
        <div class="infra-terms">
            <dl class="infra-meta infra-meta--boxed">
                <div><dt>Completion deadline</dt><dd>{{ $record->effectiveDeadline()?->format('M d, Y') }}</dd></div>
                <div><dt>Work site</dt><dd>{{ $record->delivery_location }}</dd></div>
                <div><dt>Contract reference</dt><dd>{{ $record->signed_contract_reference }}</dd></div>
            </dl>
            <table class="ui-table">
                <thead><tr><th>Work item</th><th class="is-num">Contract quantity</th><th>Unit</th></tr></thead>
                <tbody>
                    @foreach($record->contract_items as $item)
                        <tr><td>{{ $item['description'] }}</td><td class="is-num">{{ $item['quantity'] }}</td><td>{{ $item['unit'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($canCorrectTerms)
            <details class="infra-panel infra-correct" @if(old('infra_form') === 'correct') open @endif>
                <summary><i class="fas fa-pen-to-square" aria-hidden="true"></i> Correct contract terms</summary>
                <form method="POST" enctype="multipart/form-data" action="{{ route('admin.infrastructure.terms.correct', $award) }}" class="infra-correct__form">
                    @csrf
                    @method('PUT')
                    <p class="ui-hint">Use this only when the terms were recorded wrongly. The status and history stay as they are; the old and new values and your reason are logged, and the contractor is notified.</p>
                    @include('infrastructure-contracts.terms-fields', [
                        'form' => 'correct',
                        'values' => $termsOld('correct', [
                            'delivery_deadline' => $record->delivery_deadline?->toDateString(),
                            'delivery_location' => $record->delivery_location,
                            'signed_contract_reference' => $record->signed_contract_reference,
                        ]),
                        'items' => $termsOld('correct', $record->contract_items, 'contract_items'),
                    ])
                    <div class="ui-fields">
                        <label class="ui-field">
                            <span class="ui-label">Reason for the correction <span class="ui-required">*</span></span>
                            <textarea name="remarks" class="ui-input" rows="3" maxlength="2000" required placeholder="e.g. Quantity was entered as 1 lot; the signed contract states 250 lm.">{{ $termsOld('correct', null, 'remarks') }}</textarea>
                        </label>
                        <label class="ui-field">
                            <span class="ui-label">Replace signed contract copy <span class="ui-optional">(optional)</span></span>
                            <input type="file" name="document" class="ui-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <span class="ui-hint">Leave empty to keep the file already on record.</span>
                        </label>
                    </div>
                    <div class="infra-panel__foot">
                        <button class="ui-btn ui-btn--primary"><i class="fas fa-floppy-disk" aria-hidden="true"></i> Save corrected terms</button>
                    </div>
                </form>
            </details>
        @endif

        @if($nextStep)
            <div class="ui-callout infra-next {{ $allDone ? 'infra-next--done' : '' }}" role="status">
                <p class="ui-callout__title">@if($allDone)<i class="fas fa-circle-check" aria-hidden="true"></i> {{ $nextStep[0] }}@else<i class="fas fa-hourglass-half" aria-hidden="true"></i> Next step: {{ $nextStep[0] }}@endif</p>
                <p class="ui-callout__text">{{ $nextStep[1] }}</p>
            </div>
        @endif

        @if($canReport)
            <form method="POST" enctype="multipart/form-data" action="{{ route('bidder.infrastructure.progress', $award) }}" class="infra-panel">
                @csrf
                <div class="infra-panel__head"><h3>{{ $status === CI::INFRA_FOR_CORRECTION ? 'Submit corrected work' : 'Submit progress update' }}</h3></div>
                <div class="ui-fields">
                    <label class="ui-field"><span class="ui-label">Progress (%) <span class="ui-required">*</span></span><input name="progress_percent" class="ui-input" type="number" min="0" max="100" required placeholder="e.g. 45"><span class="ui-hint">Overall physical accomplishment of the whole contract.</span></label>
                    <label class="ui-field"><span class="ui-label">Milestone / work completed <span class="ui-required">*</span></span><input name="milestone" class="ui-input" maxlength="255" required placeholder="e.g. Base course laid, Sta. 0+000 to 0+120"><span class="ui-hint">What part of the work was finished in this update.</span></label>
                    <label class="ui-field"><span class="ui-label">Remarks <span class="ui-required">*</span></span><textarea name="remarks" class="ui-input" rows="3" maxlength="2000" required placeholder="e.g. Pouring done this week; 2 rain days. Curing until Oct 20."></textarea><span class="ui-hint">A short written note on this update: work done, delays or issues. This is not a signature.</span></label>
                    <label class="ui-field"><span class="ui-label">Photo, report or progress proof <span class="ui-required">*</span></span><input type="file" name="document" class="ui-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required></label>
                </div>
                <label class="infra-check"><input type="checkbox" name="request_inspection" value="1"> Work is ready; request site inspection</label>
                <div class="infra-panel__foot"><button class="ui-btn ui-btn--primary">Submit update</button></div>
            </form>
        @endif

        @if($canInspect)
            <form method="POST" enctype="multipart/form-data" action="{{ route($mode === 'staff' ? 'staff.infrastructure.inspect' : 'admin.infrastructure.inspect', $award) }}" class="infra-panel">
                @csrf
                <div class="infra-panel__head"><h3>Record site inspection</h3></div>
                <div class="ui-fields">
                    <label class="ui-field"><span class="ui-label">Result <span class="ui-required">*</span></span>
                        <select name="outcome" class="ui-input" required><option value="recommend_acceptance">Recommend formal acceptance</option><option value="correction">Request correction / reinspection</option></select>
                    </label>
                    <label class="ui-field"><span class="ui-label">Inspection report / supporting document <span class="ui-required">*</span></span><input type="file" name="document" class="ui-input" required></label>
                    <label class="ui-field"><span class="ui-label">Findings <span class="ui-required">*</span></span><textarea name="findings" class="ui-input" rows="3" required></textarea></label>
                    <label class="ui-field"><span class="ui-label">Deficiencies <span class="ui-optional">(if any)</span></span><textarea name="deficiencies" class="ui-input" rows="3"></textarea></label>
                    <label class="ui-field ui-field--wide"><span class="ui-label">Remarks <span class="ui-required">*</span></span><textarea name="remarks" class="ui-input" rows="2" required></textarea></label>
                </div>
                <div class="infra-panel__foot"><button class="ui-btn ui-btn--primary">Record inspection</button></div>
            </form>
        @endif

        @if($canAct)
            <form method="POST" enctype="multipart/form-data" action="{{ route($mode === 'staff' ? 'staff.infrastructure.action' : 'admin.infrastructure.action', $award) }}" class="infra-panel">
                @csrf
                <div class="infra-panel__head">
                    <h3>Authorized LGU contract action</h3>
                    <p class="ui-hint">Status tracking only; this system does not transfer funds.</p>
                </div>
                <div class="ui-fields">
                    <label class="ui-field"><span class="ui-label">Next status <span class="ui-required">*</span></span>
                        <select name="action" class="ui-input" required>
                            @if($status === CI::INFRA_RECOMMENDED)<option value="accept">Formally accept completed work</option>
                            @elseif($status === CI::INFRA_ACCEPTED)<option value="payment_processing">Record payment processing</option>
                            @elseif($status === CI::INFRA_PAYMENT_PROCESSING)<option value="paid">Record paid status</option>
                            @elseif($status === CI::INFRA_PAID)<option value="complete">Mark contract implementation completed</option>
                            @endif
                        </select>
                    </label>
                    <label class="ui-field"><span class="ui-label">Acceptance / payment / completion record <span class="ui-required">*</span></span><input type="file" name="document" class="ui-input" required></label>
                    <label class="ui-field ui-field--wide"><span class="ui-label">Remarks <span class="ui-required">*</span></span><textarea name="remarks" class="ui-input" rows="2" required></textarea></label>
                </div>
                <div class="infra-panel__foot"><button class="ui-btn ui-btn--primary">Record action</button></div>
            </form>
        @endif
    @elseif($mode === 'bidder')
        <p class="ui-callout ui-callout__text infra-wait">The BAC is recording the infrastructure terms from the signed contract.</p>
    @endif

    <div class="infra-history">
        <h3>Activity history</h3>
        @if($record->events->isEmpty())
            <p class="ui-hint">No progress activity has been recorded yet.</p>
        @else
            <ol class="infra-timeline">
                @foreach($record->events->sortByDesc('occurred_at') as $event)
                    <li>
                        <span class="infra-timeline__icon" aria-hidden="true"><i class="fas {{ $eventIcon($event->action) }}"></i></span>
                        <div>
                            <strong>{{ str($event->action)->replace('_', ' ')->ucfirst() }}</strong>
                            <small>{{ $event->occurred_at?->timezone($tz)->format('M d, Y g:i A') }} · {{ $event->actor?->name ?? 'LGU user' }}@isset($event->details['progress_percent']) · {{ $event->details['progress_percent'] }}%@endisset</small>
                            @if($event->remarks)<p>{{ $event->remarks }}</p>@endif
                            @if($event->action === 'infrastructure_terms_corrected')
                                @php
                                    $itemsText = fn ($items) => collect($items ?? [])->map(fn ($item) => ($item['description'] ?? '').' — '.rtrim(rtrim(number_format((float) ($item['quantity'] ?? 0), 2, '.', ','), '0'), '.').' '.($item['unit'] ?? ''))->implode('; ');
                                    $before = $event->details['before'] ?? [];
                                    $after = $event->details['after'] ?? [];
                                    $termChanges = collect(['delivery_deadline' => 'Deadline', 'delivery_location' => 'Work site', 'signed_contract_reference' => 'Contract reference', 'contract_items' => 'Work items'])
                                        ->map(fn ($label, $key) => [$label, $key === 'contract_items' ? $itemsText($before[$key] ?? []) : ($before[$key] ?? ''), $key === 'contract_items' ? $itemsText($after[$key] ?? []) : ($after[$key] ?? '')])
                                        ->filter(fn ($change) => $change[1] !== $change[2]);
                                @endphp
                                @if($termChanges->isNotEmpty())
                                    <ul class="infra-changes">
                                        @foreach($termChanges as [$label, $old, $new])
                                            <li><span>{{ $label }}</span> <del>{{ $old ?: '—' }}</del> <i class="fas fa-arrow-right" aria-hidden="true"></i> <ins>{{ $new ?: '—' }}</ins></li>
                                        @endforeach
                                    </ul>
                                @endif
                            @endif
                            @if($event->document_path)<a href="{{ route('infrastructure.document', $event) }}"><i class="fas fa-paperclip" aria-hidden="true"></i> View supporting document</a>@endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</section>

@once
    <style>
        .infra-card { padding: 22px 24px; margin-bottom: 20px; }
        .infra-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 14px; }
        .infra-head__main { min-width: 0; }
        .infra-kicker { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 0; color: var(--ui-muted); font-size: 12px; }
        .infra-title { margin: 8px 0 12px; color: var(--ui-ink); font-size: 20px; line-height: 1.3; }
        .infra-status { flex: 0 0 auto; padding: 5px 12px; font-size: 12.5px; }
        .infra-meta { display: flex; flex-wrap: wrap; gap: 10px 28px; margin: 0; }
        .infra-meta div { min-width: 0; }
        .infra-meta dt { color: var(--ui-subtle); font-size: 11.5px; font-weight: 600; }
        .infra-meta dd { margin: 2px 0 0; color: var(--ui-ink); font-size: 14px; font-weight: 600; }
        .infra-meta--boxed { padding: 14px 16px; margin-bottom: 14px; border: 1px solid var(--ui-line); border-radius: var(--ui-radius-lg); background: var(--ui-surface-2); }
        .infra-progress { display: flex; align-items: center; gap: 12px; margin: 20px 0 14px; }
        .infra-progress__bar { flex: 1; height: 8px; overflow: hidden; border-radius: 999px; background: var(--ui-line-soft); }
        .infra-progress__bar span { display: block; height: 100%; border-radius: inherit; background: var(--ui-primary); transition: width .6s ease; }
        .infra-progress__value { color: var(--ui-muted); font-size: 12.5px; font-weight: 600; white-space: nowrap; }
        .infra-steps { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px; margin: 0 0 20px; padding: 0; list-style: none; }
        .infra-steps li { position: relative; display: grid; justify-items: center; gap: 6px; text-align: center; }
        .infra-steps li:not(:last-child)::after { content: ''; position: absolute; top: 13px; left: calc(50% + 16px); right: calc(-50% + 16px); height: 2px; background: var(--ui-line); }
        .infra-steps li.is-done:not(:last-child)::after { background: var(--ui-primary); }
        .infra-steps__dot { display: grid; width: 28px; height: 28px; place-items: center; border: 2px solid var(--ui-line-strong); border-radius: 50%; background: var(--ui-surface); color: var(--ui-subtle); font-size: 11.5px; font-weight: 700; }
        .infra-steps li.is-done .infra-steps__dot { border-color: var(--ui-primary); background: var(--ui-primary); color: #fff; }
        .infra-steps li.is-current .infra-steps__dot { border-color: var(--ui-primary); color: var(--ui-primary); box-shadow: 0 0 0 4px var(--ui-primary-soft); }
        .infra-steps li.is-flagged .infra-steps__dot { border-color: var(--ui-danger); color: var(--ui-danger); box-shadow: 0 0 0 4px var(--ui-danger-soft); }
        .infra-steps__label { color: var(--ui-muted); font-size: 11.5px; line-height: 1.3; }
        .infra-steps li:is(.is-done, .is-current) .infra-steps__label { color: var(--ui-ink); font-weight: 600; }
        .infra-panel { display: grid; gap: 16px; margin-top: 18px; padding: 18px 20px; border: 1px solid var(--ui-line); border-radius: var(--ui-radius-lg); background: var(--ui-surface-2); }
        .infra-panel__head h3 { margin: 0; color: var(--ui-ink); font-size: 15.5px; }
        .infra-panel__head .ui-hint { margin: 4px 0 0; }
        .infra-panel__foot { display: flex; justify-content: flex-end; }
        .infra-fields--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .infra-panel .ui-input { background: var(--ui-surface); }
        .infra-panel textarea.ui-input { min-height: 72px; resize: vertical; }
        .infra-items { display: grid; gap: 8px; }
        .infra-items__head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .infra-items__cols, .infra-items__row { display: grid; grid-template-columns: minmax(0, 2.2fr) minmax(0, 1fr) minmax(0, 1fr) 34px; gap: 8px; align-items: center; }
        .infra-items__cols span { color: var(--ui-subtle); font-size: 11.5px; font-weight: 600; }
        .infra-items__rows { display: grid; gap: 8px; }
        .infra-items__remove { display: grid; width: 34px; height: 34px; place-items: center; border: 1px solid var(--ui-line-strong); border-radius: var(--ui-radius); background: var(--ui-surface); color: var(--ui-muted); cursor: pointer; }
        .infra-items__remove:hover { border-color: var(--ui-danger); color: var(--ui-danger); }
        .infra-items__remove[hidden] { visibility: hidden; display: grid; }
        .infra-prefill { display: flex; align-items: center; gap: 6px; margin: -6px 0 0; }
        .infra-prefill i { color: var(--ui-primary); }
        .infra-correct { gap: 0; padding: 0; }
        .infra-correct summary { display: flex; align-items: center; gap: 8px; padding: 14px 20px; color: var(--ui-primary); font-size: 13.5px; font-weight: 600; cursor: pointer; list-style: none; }
        .infra-correct summary::-webkit-details-marker { display: none; }
        .infra-correct[open] summary { border-bottom: 1px solid var(--ui-line); }
        .infra-correct__form { display: grid; gap: 16px; padding: 16px 20px 18px; }
        .infra-correct__form > .ui-hint { margin: 0; }
        .infra-next { margin-top: 18px; }
        .infra-next .ui-callout__title { display: flex; align-items: center; gap: 8px; }
        .infra-next__warn { margin-top: 8px; color: var(--ui-danger); }
        .infra-next--done { border-color: var(--ui-success-line, #b7dcc6); border-left-color: var(--ui-success, #1f7a4d); background: var(--ui-success-soft, #eaf6ef); }
        .infra-next--done .ui-callout__title i { color: var(--ui-success, #1f7a4d); }
        .infra-check { display: flex; align-items: center; gap: 8px; color: var(--ui-ink-2); font-size: 13px; }
        .infra-wait { margin-top: 18px; }
        .infra-history { margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--ui-line); }
        .infra-history h3 { margin: 0 0 12px; font-size: 15.5px; }
        .infra-timeline { display: grid; gap: 14px; margin: 0; padding: 0; list-style: none; }
        .infra-timeline > li { display: grid; grid-template-columns: 32px minmax(0, 1fr); gap: 12px; }
        .infra-timeline__icon { display: grid; width: 32px; height: 32px; place-items: center; border-radius: 50%; background: var(--ui-primary-soft); color: var(--ui-primary); font-size: 13px; }
        .infra-timeline strong { display: block; color: var(--ui-ink); font-size: 13.5px; }
        .infra-timeline small { display: block; margin-top: 2px; color: var(--ui-subtle); font-size: 12px; }
        .infra-timeline p { margin: 6px 0 0; color: var(--ui-ink-2); font-size: 13px; }
        .infra-changes li { display: block; }
        .infra-changes { display: grid; gap: 4px; margin: 8px 0 0; padding: 8px 12px; border-radius: var(--ui-radius); background: var(--ui-surface-2); list-style: none; font-size: 12.5px; }
        .infra-changes span { color: var(--ui-subtle); font-weight: 600; }
        .infra-changes del { color: var(--ui-danger); }
        .infra-changes ins { color: var(--ui-ink); font-weight: 600; text-decoration: none; }
        .infra-changes i { color: var(--ui-subtle); font-size: 10px; }
        .infra-timeline a { display: inline-flex; gap: 6px; margin-top: 6px; color: var(--ui-primary); font-size: 12.5px; font-weight: 600; text-decoration: none; }
        @media (max-width: 900px) {
            .infra-fields--3 { grid-template-columns: minmax(0, 1fr); }
            .infra-steps { grid-template-columns: repeat(4, minmax(0, 1fr)); row-gap: 14px; }
            .infra-steps li::after { display: none; }
        }
        @media (max-width: 640px) {
            .infra-card { padding: 16px; }
            .infra-panel .ui-fields { grid-template-columns: minmax(0, 1fr); }
            .infra-items__cols { display: none; }
            .infra-items__row { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 34px; }
            .infra-items__row > :first-child { grid-column: 1 / -1; }
        }
    </style>
    <script>
        // A tracking form is sent once: a second click while it saves does nothing.
        document.addEventListener('submit', function (event) {
            const form = event.target.closest('.infra-card form');
            if (!form) return;
            if (form.dataset.sending) {
                event.preventDefault();
                return;
            }
            form.dataset.sending = '1';
            form.querySelectorAll('button:not([type="button"])').forEach(function (button) {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
            });
        });
        // Work items: add rows (contract_items[n][...]) and remove all but the last one.
        document.addEventListener('click', function (event) {
            const add = event.target.closest('[data-infra-add-item]');
            const remove = event.target.closest('[data-infra-remove]');
            const box = (add || remove)?.closest('[data-infra-items]');
            if (!box) return;
            const rows = box.querySelector('[data-infra-rows]');
            if (add) {
                const index = Array.from(rows.querySelectorAll('[data-infra-row] input')).reduce(function (max, input) {
                    const match = input.name.match(/contract_items\[(\d+)\]/);
                    return match ? Math.max(max, Number(match[1]) + 1) : max;
                }, 0);
                const row = rows.querySelector('[data-infra-row]').cloneNode(true);
                row.querySelectorAll('input').forEach(function (input) {
                    input.value = '';
                    input.name = input.name.replace(/contract_items\[\d+\]/, 'contract_items[' + index + ']');
                });
                rows.appendChild(row);
                row.querySelector('input').focus();
            } else {
                remove.closest('[data-infra-row]').remove();
            }
            const all = rows.querySelectorAll('[data-infra-row]');
            all.forEach(function (row) { row.querySelector('[data-infra-remove]').hidden = all.length === 1; });
        });
    </script>
@endonce
