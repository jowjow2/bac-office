@extends('layouts.portal')

@php
    /** @var \App\Models\ProcurementRequest $procurementRequest */
    $tz = config('bac-office.display_timezone');
    $project = $procurementRequest->project;
    $next = $timeline->nextAction();
    $missing = $procurementRequest->isEditable() ? $procurementRequest->missingForSubmission() : [];
    $isDraft = $procurementRequest->status === \App\Models\ProcurementRequest::STATUS_DRAFT;
    $categories = ['goods' => 'Goods', 'services' => 'General support services', 'infrastructure' => 'Infrastructure', 'consultancy' => 'Consulting services'];
    $calloutTone = match (true) {
        in_array($procurementRequest->status, ['returned', 'rejected'], true) || ($project?->isFailedBidding() ?? false) => 'danger',
        $project?->isCompleted() ?? false => 'success',
        default => '',
    };
    $orDash = fn ($value) => filled($value) ? $value : '—';
@endphp

@section('title', $procurementRequest->title)
@section('crumbs')
    <a href="{{ route('end-user.requests.index') }}">My purchase requests</a>
    <span aria-hidden="true">/</span>
    <span class="ui-mono">{{ $procurementRequest->reference_no }}</span>
@endsection
@section('subtitle', $procurementRequest->end_user_office.($project ? ' · '.$project->mode()->label() : ' · Mode of procurement set by the BAC'))

@section('actions')
    @if($procurementRequest->isEditable())
        <a href="{{ route('end-user.requests.edit', $procurementRequest) }}" class="ui-btn ui-btn--secondary"><i class="fas fa-pen" aria-hidden="true"></i> {{ $isDraft ? 'Continue editing' : 'Correct request' }}</a>
        @if($missing === [])
            <form method="POST" action="{{ route('end-user.requests.submit', $procurementRequest) }}">
                @csrf
                <button type="submit" class="ui-btn ui-btn--primary"><i class="fas fa-paper-plane" aria-hidden="true"></i> Submit for PPMP/APP review</button>
            </form>
        @endif
    @endif
@endsection

@section('content')
<div class="ui-page">
    @if($errors->any())
        <div class="ui-alert ui-alert--danger" role="alert">
            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="ui-actions" aria-label="Status">
        <span class="ui-badge ui-badge--{{ $procurementRequest->statusTone() }}">{{ $procurementRequest->statusLabel() }}</span>
        @if($project)
            <span class="ui-mode ui-mode--{{ $project->mode()->family() }}">{{ $project->mode()->label() }}</span>
            <span class="ui-badge ui-badge--{{ $project->portalStatus()['tone'] }}">Project: {{ $project->portalStatus()['label'] }}</span>
        @endif
    </div>

    @if($missing !== [])
        <div class="ui-alert ui-alert--warning" role="status">
            <i class="fas fa-list-check" aria-hidden="true"></i>
            <div>
                <strong>This draft is not complete yet.</strong> Add the following before submitting it for the PPMP/APP and funds review:
                <ul>@foreach($missing as $label)<li>{{ $label }}</li>@endforeach</ul>
                <div class="ui-mt-sm"><a href="{{ route('end-user.requests.edit', $procurementRequest) }}" class="ui-btn ui-btn--secondary ui-btn--sm">Complete the draft</a></div>
            </div>
        </div>
    @elseif($next)
        <section class="ui-callout {{ $calloutTone ? 'ui-callout--'.$calloutTone : '' }}" aria-labelledby="next-title">
            <span class="ui-callout__label">{{ $calloutTone === 'success' ? 'Status' : 'Next step' }} &middot; {{ $next['office'] }}</span>
            <h2 class="ui-callout__title" id="next-title">{{ $next['title'] }}</h2>
            <p class="ui-callout__text">{{ $next['detail'] }}</p>
        </section>
    @endif

    <div class="ui-grid ui-grid--sidebar">
        <div class="ui-stack">
            <section class="ui-card" aria-labelledby="progress-title">
                <div class="ui-card__head">
                    <div>
                        <h2 class="ui-card__title" id="progress-title">Procurement progress</h2>
                        <p class="ui-card__desc">{{ $timeline->progressPercent() }}% of the applicable stages completed.</p>
                    </div>
                </div>
                <div class="ui-card__body">
                    <div class="ui-progress ui-progress--spaced" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $timeline->progressPercent() }}" aria-label="Progress"><span style="width: {{ $timeline->progressPercent() }}%"></span></div>
                    @include('partials.ui.timeline', ['stages' => $timeline->stages()])
                </div>
            </section>

            <section class="ui-card" aria-labelledby="history-title">
                <div class="ui-card__head">
                    <h2 class="ui-card__title" id="history-title">History</h2>
                </div>
                @include('partials.ui.history', ['history' => $timeline->history(false)])
            </section>
        </div>

        <div class="ui-stack">
            <section class="ui-card" aria-labelledby="details-title">
                <div class="ui-card__head">
                    <h2 class="ui-card__title" id="details-title">Request details</h2>
                </div>
                <div class="ui-card__body">
                    <dl class="ui-dl">
                        <div><dt>Reference no.</dt><dd class="ui-mono">{{ $procurementRequest->reference_no }}</dd></div>
                        <div><dt>End-user office</dt><dd>{{ $procurementRequest->end_user_office }}</dd></div>
                        <div><dt>Category</dt><dd>{{ $categories[$procurementRequest->category] ?? '—' }}</dd></div>
                        <div><dt>Quantity</dt><dd>{{ $procurementRequest->quantityLabel() }}</dd></div>
                        <div><dt>Estimated total cost</dt><dd class="ui-num">{{ $procurementRequest->estimated_cost !== null ? '₱'.number_format((float) $procurementRequest->estimated_cost, 2) : '—' }}</dd></div>
                        <div><dt>Source of funds</dt><dd>{{ $orDash($procurementRequest->fund_source) }}</dd></div>
                        <div><dt>Delivery / duration</dt><dd>{{ $orDash($procurementRequest->delivery_period) }}</dd></div>
                        <div><dt>Requested by</dt><dd>{{ $procurementRequest->requester?->name ?? '—' }}</dd></div>
                        @if($procurementRequest->submitted_at)
                            <div><dt>Submitted</dt><dd>{{ $procurementRequest->submitted_at->timezone($tz)->format('M d, Y h:i A') }}</dd></div>
                        @endif
                        @if($procurementRequest->ppmp_reference)
                            <div><dt>PPMP / APP</dt><dd>{{ $procurementRequest->ppmp_reference }} / {{ $procurementRequest->app_reference }}</dd></div>
                        @endif
                        @if($procurementRequest->review_remarks)
                            <div><dt>Reviewer remarks</dt><dd>{{ $procurementRequest->review_remarks }}</dd></div>
                        @endif
                        @if($project)
                            <div><dt>BAC project</dt><dd class="ui-mono">{{ $project->reference_no }}</dd></div>
                            <div><dt>ABC</dt><dd class="ui-num">₱{{ number_format((float) $project->budget, 2) }}</dd></div>
                        @endif
                    </dl>
                </div>
            </section>

            <section class="ui-card" aria-labelledby="spec-title">
                <div class="ui-card__head">
                    <h2 class="ui-card__title" id="spec-title">Specifications / TOR</h2>
                </div>
                <div class="ui-card__body">
                    <p class="ui-prose">{{ $orDash($procurementRequest->specifications) }}</p>
                    @if($procurementRequest->justification)
                        <p class="ui-hint ui-mt-sm"><strong>Purpose:</strong> {{ $procurementRequest->justification }}</p>
                    @endif
                </div>
            </section>

            <section class="ui-card" aria-labelledby="docs-title">
                <div class="ui-card__head">
                    <h2 class="ui-card__title" id="docs-title">Documents</h2>
                </div>
                <div class="ui-card__body">
                    @include('partials.ui.documents', ['documents' => $timeline->documents(false)])
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
