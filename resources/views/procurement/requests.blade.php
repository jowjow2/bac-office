@extends('layouts.portal')

@section('title', 'Purchase requests')
@section('subtitle', 'Record the signed hard copy an office hands to the BAC. A recorded request is ready for procurement: prepare the project from it.')

@php
    $tz = config('bac-office.display_timezone');
    $tabs = [
        'review' => ['For PPMP/APP review', $counts['review']],
        'bac' => ['Ready for procurement', $counts['bac']],
        'returned' => ['Returned / not approved', $counts['returned']],
        'procurement' => ['In procurement', $counts['procurement']],
        'all' => ['All', null],
    ];
    // Recorded requests are ready at once; these two only hold older requests, so they show while they have any.
    foreach (['review', 'returned'] as $legacy) {
        if (($tabs[$legacy][1] ?? 0) === 0 && ($tab ?? null) !== $legacy) {
            unset($tabs[$legacy]);
        }
    }
    $reviewErrors = old('_review_id') ? (int) old('_review_id') : null;
@endphp

@section('actions')
    @if($routePrefix === 'admin')
        <a href="{{ route('admin.requests.create') }}" class="ui-btn ui-btn--primary"><i class="fas fa-plus" aria-hidden="true"></i> Record purchase request</a>
    @endif
@endsection

@section('content')
<div class="ui-page">
    @if($errors->any() && ! $reviewErrors)
        <div class="ui-alert ui-alert--danger" role="alert">
            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="ui-card">
        <nav class="ui-tabs ui-tabs--inset" aria-label="Request queues">
            @foreach($tabs as $key => [$label, $count])
                <a class="ui-tab" href="{{ route($routePrefix.'.requests', array_filter(['tab' => $key, 'q' => $search])) }}" @if($tab === $key) aria-current="page" @endif>
                    {{ $label }}
                    @if($count !== null)<span class="ui-tab__count {{ in_array($key, ['review', 'returned'], true) && $count > 0 ? 'is-alert' : '' }}">{{ $count }}</span>@endif
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route($routePrefix.'.requests') }}" class="ui-toolbar" role="search">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label class="ui-search">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <span class="sr-only">Search requests</span>
                <input type="search" name="q" value="{{ $search }}" class="ui-input" placeholder="Search by title, PR number, or office">
            </label>
            <button type="submit" class="ui-btn ui-btn--secondary">Search</button>
        </form>

        @if($requests->isEmpty())
            @php
                // Queues where the search does find something.
                $elsewhere = $search === '' ? [] : array_filter($tabs, fn ($entry, $key) => $key !== $tab && $entry[1] > 0, ARRAY_FILTER_USE_BOTH);
            @endphp
            @if($search === '' && $tab === 'review' && ($counts['bac'] + $counts['procurement']) > 0)
                {{-- Nothing to review: say where the requests went, in one line. --}}
                <p class="pr-allclear"><i class="fas fa-circle-check" aria-hidden="true"></i>
                    <strong>Nothing waiting for your review.</strong>
                    <a class="ui-link" href="{{ route($routePrefix.'.requests', ['tab' => 'bac']) }}">{{ $counts['bac'] }} ready for procurement</a>
                    &middot;
                    <a class="ui-link" href="{{ route($routePrefix.'.requests', ['tab' => 'procurement']) }}">{{ $counts['procurement'] }} in procurement</a>
                </p>
            @else
            <div class="ui-empty">
                <i class="fas fa-inbox" aria-hidden="true"></i>
                <strong>{{ $search !== '' ? 'No requests in '.$tabs[$tab][0].' match "'.$search.'"' : ($tab === 'review' ? 'Nothing waiting for your review' : 'This queue is empty') }}</strong>
                <span>
                    @if($search === '' && $tab === 'review' && ($counts['bac'] + $counts['procurement']) > 0)
                        {{-- Say where the requests went, so an empty review queue does not look like missing data. --}}
                        All submitted requests have been checked:
                        <a class="ui-link" href="{{ route($routePrefix.'.requests', ['tab' => 'bac']) }}">{{ $counts['bac'] }} ready for procurement</a>
                        &middot;
                        <a class="ui-link" href="{{ route($routePrefix.'.requests', ['tab' => 'procurement']) }}">{{ $counts['procurement'] }} in procurement</a>.
                    @elseif($search !== '')
                        @if($elsewhere)
                            Found in
                            @foreach($elsewhere as $key => [$label, $count])
                                <a class="ui-link" href="{{ route($routePrefix.'.requests', ['tab' => $key, 'q' => $search]) }}">{{ $label }} ({{ $count }})</a>@if(! $loop->last), @endif
                            @endforeach
                            &middot;
                        @endif
                        <a class="ui-link" href="{{ route($routePrefix.'.requests', ['tab' => $tab]) }}">Clear search</a>
                    @elseif($tab === 'review')
                        Record the signed hard copies that end-user offices hand to the BAC, and they appear here ready to become projects.
                        @if($drafts > 0) {{ $drafts }} {{ \Illuminate\Support\Str::plural('draft', $drafts) }} {{ $drafts === 1 ? 'is' : 'are' }} still being prepared by the offices. @endif
                    @else
                        Try another queue.
                    @endif
                </span>
            </div>
            @endif
        @else
            <div class="ui-table-wrap">
                <table class="ui-table ui-table--stack">
                    <thead>
                        <tr>
                            <th scope="col">Request</th>
                            <th scope="col">End-user office</th>
                            <th scope="col" class="is-num">Estimated cost (₱)</th>
                            <th scope="col">Submitted</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="is-actions"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $item)
                            <tr>
                                <td data-label="Request">
                                    <span class="ui-cell-title">{{ $item->title }}</span>
                                    <span class="ui-cell-sub"><span class="ui-mono">{{ $item->reference_no }}</span> &middot; {{ ucfirst($item->category) }} &middot; {{ $item->quantityLabel() }}</span>
                                </td>
                                <td data-label="Office">{{ $item->end_user_office }}</td>
                                <td data-label="Estimated cost" class="is-num">{{ number_format((float) $item->estimated_cost, 2) }}</td>
                                <td data-label="Submitted" class="is-nowrap">{{ $item->submitted_at?->timezone($tz)->format('M d, Y') ?? '—' }}</td>
                                <td data-label="Status"><span class="ui-badge ui-badge--{{ $item->statusTone() }}">{{ $item->statusLabel() }}</span></td>
                                <td data-label="Actions" class="is-actions">
                                    <span class="ui-actions ui-actions--end">
                                        <button type="button" class="ui-btn {{ $item->awaitsReview() ? 'ui-btn--primary' : 'ui-btn--secondary' }} ui-btn--sm" data-dialog-open="request-{{ $item->id }}">
                                            {{ $item->awaitsReview() ? 'Review' : 'View' }}<span class="sr-only"> {{ $item->reference_no }}</span>
                                        </button>
                                        @if($item->awaitsBac() && $routePrefix === 'admin')
                                            <a class="ui-btn ui-btn--success ui-btn--sm" href="{{ route('admin.projects.create', ['request' => $item->id]) }}">Prepare procurement</a>
                                        @endif
                                        <a class="ui-btn ui-btn--ghost ui-btn--sm" href="{{ route($routePrefix.'.requests.print', $item) }}" target="_blank" rel="noopener" title="Print the PR form"><i class="fas fa-print" aria-hidden="true"></i><span class="sr-only">Print {{ $item->reference_no }}</span></a>
                                        @if($item->project)
                                            <a class="ui-btn ui-btn--ghost ui-btn--sm" href="{{ route($routePrefix.'.procurement.show', $item->project) }}">Open procurement</a>
                                        @endif
                                        @if($routePrefix === 'admin' && ! $item->project && $item->status !== \App\Models\ProcurementRequest::STATUS_IN_PROCUREMENT)
                                            <button type="button" class="ui-btn ui-btn--danger ui-btn--sm" data-dialog-open="delete-request-{{ $item->id }}" title="Delete {{ $item->reference_no }}"><i class="fas fa-trash-can" aria-hidden="true"></i><span class="sr-only">Delete {{ $item->reference_no }}</span></button>
                                        @endif
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <nav class="ui-pager" aria-label="Pages">
                <span>Showing {{ $requests->firstItem() }}–{{ $requests->lastItem() }} of {{ $requests->total() }}</span>
                <span class="ui-actions">
                    <a class="ui-btn ui-btn--secondary ui-btn--sm" href="{{ $requests->previousPageUrl() ?? '#' }}" @if($requests->onFirstPage()) aria-disabled="true" tabindex="-1" @endif>Previous</a>
                    <a class="ui-btn ui-btn--secondary ui-btn--sm" href="{{ $requests->nextPageUrl() ?? '#' }}" @unless($requests->hasMorePages()) aria-disabled="true" tabindex="-1" @endunless>Next</a>
                </span>
            </nav>
        @endif
    </section>
</div>

@foreach($requests as $item)
    @php $showErrors = $reviewErrors === $item->id; @endphp
    <dialog class="ui-dialog" id="request-{{ $item->id }}" aria-labelledby="request-{{ $item->id }}-title" @if($showErrors) data-open-on-load @endif>
        <div class="ui-card__head">
            <div>
                <p class="ui-eyebrow">{{ $item->reference_no }} &middot; {{ $item->end_user_office }}</p>
                <h2 class="ui-card__title" id="request-{{ $item->id }}-title">{{ $item->title }}</h2>
            </div>
            <button type="button" class="ui-dialog__close" data-dialog-close aria-label="Close"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="ui-card__body ui-stack">
            <dl class="ui-dl">
                <div><dt>Category</dt><dd>{{ ucfirst($item->category) }}</dd></div>
                <div><dt>Quantity</dt><dd>{{ $item->quantityLabel() }}</dd></div>
                <div><dt>Estimated cost</dt><dd>&#8369;{{ number_format((float) $item->estimated_cost, 2) }}</dd></div>
                <div><dt>Source of funds</dt><dd>{{ $item->fund_source }}</dd></div>
                <div><dt>Delivery / duration</dt><dd>{{ $item->delivery_period }}</dd></div>
                <div><dt>Requested by</dt><dd>{{ $item->requester?->name ?? '—' }}</dd></div>
                @if($item->ppmp_reference)
                    <div><dt>PPMP / APP</dt><dd>{{ $item->ppmp_reference }} / {{ $item->app_reference }}</dd></div>
                @endif
                @if($item->budget_confirmed_at)
                    <div><dt>Budget confirmed</dt><dd>{{ $item->budgetConfirmer?->name ?? 'Former user' }} &middot; {{ $item->budget_confirmed_at->format('M j, Y g:i A') }}</dd></div>
                @endif
                @if($item->review_remarks)
                    <div><dt>Review remarks</dt><dd>{{ $item->review_remarks }}</dd></div>
                @endif
            </dl>
            @if($item->itemRows() !== [])
                <div>
                    <p class="ui-label">Items</p>
                    @include('procurement.partials.request-items', ['procurementRequest' => $item])
                </div>
            @endif
            <div>
                <p class="ui-label">Specifications / TOR</p>
                <p class="ui-prose-box">{{ $item->specifications }}</p>
            </div>
            @if($item->documents->isNotEmpty())
                <ul class="ui-files">
                    @foreach($item->documents as $document)
                        <li><i class="fas fa-file-lines" aria-hidden="true"></i><a class="ui-link" href="{{ route('procurement.files.request', $document) }}" target="_blank" rel="noopener">{{ $document->typeLabel() }}: {{ $document->original_name }}</a></li>
                    @endforeach
                </ul>
            @endif

            @if($item->awaitsReview())
                <form method="POST" action="{{ route($routePrefix.'.requests.review', $item) }}" class="ui-form ui-form--tight ui-form--divided">
                    @csrf
                    <input type="hidden" name="_review_id" value="{{ $item->id }}">
                    @if($showErrors && $errors->any())
                        <div class="ui-alert ui-alert--danger" role="alert">
                            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    @endif
                    <fieldset class="ui-fieldset">
                        <legend class="ui-label ui-legend">Decision <span class="ui-required" aria-hidden="true">*</span></legend>
                        <div class="ui-radio-cards">
                            <label class="ui-radio-card"><input type="radio" name="decision" value="forward" required @checked(! $showErrors || old('decision', 'forward') === 'forward')><strong>Forward to BAC</strong><span>Covered by PPMP/APP and budget</span></label>
                            <label class="ui-radio-card"><input type="radio" name="decision" value="return" @checked($showErrors && old('decision') === 'return')><strong>Return</strong><span>Needs correction by the office</span></label>
                            <label class="ui-radio-card"><input type="radio" name="decision" value="reject" @checked($showErrors && old('decision') === 'reject')><strong>Not approved</strong><span>Not in PPMP/APP or no budget</span></label>
                        </div>
                    </fieldset>
                    <div class="ui-fields">
                        <div class="ui-field">
                            <label class="ui-label" for="ppmp-{{ $item->id }}">PPMP reference</label>
                            <input id="ppmp-{{ $item->id }}" name="ppmp_reference" class="ui-input" maxlength="255" value="{{ $showErrors ? old('ppmp_reference') : $item->ppmp_reference }}" placeholder="e.g. PPMP-MEO-2026, item 12">
                        </div>
                        <div class="ui-field">
                            <label class="ui-label" for="app-{{ $item->id }}">APP reference</label>
                            <input id="app-{{ $item->id }}" name="app_reference" class="ui-input" maxlength="255" value="{{ $showErrors ? old('app_reference') : $item->app_reference }}" placeholder="e.g. APP 2026, line 45">
                        </div>
                        <div class="ui-field ui-field--wide">
                            <input type="hidden" name="budget_available" value="0">
                            <label class="ui-check"><input type="checkbox" name="budget_available" value="1" @checked($showErrors && old('budget_available'))> The budget for this request is available (certified by the Budget Office).</label>
                            <span class="ui-hint">Required to forward, together with the PPMP and APP references. Your name and the date and time are recorded when you confirm.</span>
                        </div>
                        <div class="ui-field ui-field--wide">
                            <label class="ui-label" for="remarks-{{ $item->id }}">Remarks</label>
                            <textarea id="remarks-{{ $item->id }}" name="review_remarks" class="ui-input" rows="3" maxlength="2000" placeholder="Required when returning or not approving; shown to the end-user office.">{{ $showErrors ? old('review_remarks') : '' }}</textarea>
                        </div>
                    </div>
                    <div class="ui-actions ui-actions--end">
                        <button type="button" class="ui-btn ui-btn--secondary" data-dialog-close>Cancel</button>
                        <button type="submit" class="ui-btn ui-btn--primary">Record decision</button>
                    </div>
                </form>
            @endif
        </div>
    </dialog>
    @if($routePrefix === 'admin' && ! $item->project && $item->status !== \App\Models\ProcurementRequest::STATUS_IN_PROCUREMENT)
        {{-- Delete a request filed by mistake or as a test (ProcurementRequestController::destroy). --}}
        @php $deleteErrors = (string) old('_delete_id') === (string) $item->id; @endphp
        <dialog class="ui-dialog pr-delete" id="delete-request-{{ $item->id }}" aria-labelledby="delete-request-{{ $item->id }}-title" @if($deleteErrors) data-open-on-load @endif>
            <div class="ui-card__head">
                <div>
                    <p class="ui-eyebrow">{{ $item->reference_no }} &middot; {{ $item->end_user_office }}</p>
                    <h2 class="ui-card__title" id="delete-request-{{ $item->id }}-title">Delete this purchase request?</h2>
                </div>
                <button type="button" class="ui-dialog__close" data-dialog-close aria-label="Close"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
            <form method="POST" action="{{ route('admin.requests.destroy', $item) }}" class="ui-card__body ui-stack">
                @csrf
                @method('DELETE')
                <input type="hidden" name="_delete_id" value="{{ $item->id }}">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <p class="pr-delete__title">{{ $item->title }}</p>
                <ul class="pr-delete__points">
                    <li>{{ $item->documents->isEmpty() ? 'The request is deleted.' : 'The request and its '.$item->documents->count().' '.\Illuminate\Support\Str::plural('attachment', $item->documents->count()).' are deleted.' }}</li>
                    <li>The {{ $item->end_user_office }} is notified with your reason.</li>
                    <li>The audit log keeps a copy of the request and the reason. This cannot be undone.</li>
                </ul>
                <p class="ui-hint">To send it back for correction instead, open <strong>{{ $item->awaitsReview() ? 'Review' : 'View' }}</strong> and return it to the office.</p>
                <div class="ui-field">
                    <label class="ui-label" for="delete-reason-{{ $item->id }}">Reason <span class="ui-required" aria-hidden="true">*</span></label>
                    <textarea id="delete-reason-{{ $item->id }}" name="reason" class="ui-input" rows="3" maxlength="500" required placeholder="e.g. Test entry; duplicate of PR-2026-0003">{{ $deleteErrors ? old('reason') : '' }}</textarea>
                    @if($deleteErrors && $errors->has('reason'))<span class="ui-error">{{ $errors->first('reason') }}</span>@endif
                </div>
                <div class="ui-actions ui-actions--end">
                    <button type="button" class="ui-btn ui-btn--secondary" data-dialog-close>Cancel</button>
                    <button type="submit" class="ui-btn pr-delete__go"><i class="fas fa-trash-can" aria-hidden="true"></i> Delete request</button>
                </div>
            </form>
        </dialog>
    @endif
@endforeach
<style>
    .pr-delete { width: min(520px, calc(100vw - 32px)); }
    /* The row actions stay on one line on wide screens. */
    @media (min-width: 1100px) { td.is-actions .ui-actions { flex-wrap: nowrap; } }
    .pr-delete__title { margin: 0; color: var(--ui-ink); font-size: 15px; font-weight: 600; }
    .pr-delete__points { display: grid; gap: 6px; margin: 0; padding: 10px 12px 10px 28px; border-radius: 10px; background: var(--ui-danger-soft); color: var(--ui-ink-2); font-size: 13px; line-height: 1.5; }
    .pr-delete__go { border-color: var(--ui-danger) !important; background: var(--ui-danger) !important; color: #fff !important; }
    .pr-delete__go:hover { filter: brightness(.95); }
</style>
    @if($recordForm ?? false)
        <div class="eu-modal" role="dialog" aria-modal="true" aria-labelledby="eu-modal-title" data-eu-modal data-close-url="{{ route('admin.requests') }}">
            <div class="eu-modal__card">
                <header class="eu-modal__head">
                    <div>
                        <h2 id="eu-modal-title">Record purchase request</h2>
                        <p>Record the signed hard copy an end-user office handed to the BAC. It is ready for procurement as soon as it is recorded.</p>
                    </div>
                    <a href="{{ route('admin.requests') }}" class="eu-modal__close" data-eu-modal-close aria-label="Close"><i class="fas fa-xmark" aria-hidden="true"></i></a>
                </header>
                <div class="eu-modal__body">
                    @include('procurement.request-form')
                </div>
            </div>
        </div>
    @endif
@endsection

@push('head')
<style>
    .ui-tab__count.is-alert { background: var(--ui-warning-soft); color: var(--ui-warning); }
    .pr-allclear { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; margin: 0; padding: 18px 20px; color: var(--ui-muted); font-size: 13.5px; }
    .pr-allclear > i { color: var(--ui-success); }
    .pr-allclear strong { color: var(--ui-ink); }
</style>
@endpush
