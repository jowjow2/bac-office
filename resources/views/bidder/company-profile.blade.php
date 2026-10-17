@extends('layouts.portal')

@php
    $profile = $user->bidderProfile;
    $companyLabel = $user->company ?: ($user->name ?? 'Company');
    $companyInitial = strtoupper(substr($companyLabel, 0, 1));
    $uploadTypes = array_values(array_unique(array_merge(
        \App\Support\BidderRegistrationRequirements::documentTypes(),
        ['Audited Financial Statement', 'PCAB License']
    )));
    $isApproved = $accountStatusKey === 'approved';

    $statusMeta = [
        'suspended' => ['label' => 'Suspended', 'icon' => 'fa-ban', 'tone' => 'danger'],
        'rejected' => ['label' => 'Rejected', 'icon' => 'fa-circle-xmark', 'tone' => 'danger'],
        'needs_action' => ['label' => 'Needs Action', 'icon' => 'fa-triangle-exclamation', 'tone' => 'warning'],
        'resubmitted' => ['label' => 'Resubmitted', 'icon' => 'fa-paper-plane', 'tone' => 'info'],
        'under_review' => ['label' => 'Pending Review', 'icon' => 'fa-hourglass-half', 'tone' => 'info'],
        'approved' => ['label' => 'Approved', 'icon' => 'fa-circle-check', 'tone' => 'success'],
    ][$accountStatusKey] ?? ['label' => ucfirst($accountStatusKey), 'icon' => 'fa-circle-info', 'tone' => 'info'];

    $statusTitle = match ($accountStatusKey) {
        'needs_action' => 'Your account has limited access',
        'approved' => 'Approved: verified bidder',
        'under_review' => 'Your registration is under review',
        'resubmitted' => 'Resubmitted for review',
        default => $statusMeta['label'],
    };
    $statusDescription = match ($accountStatusKey) {
        'suspended' => $activeSanction?->reason ?: 'Your account has been suspended by the SJBAC.',
        'rejected' => $profile?->review_message ?: 'Your registration was not approved. Contact the SJBAC for details.',
        'needs_action' => $profile?->review_message ?: 'Correct the flagged documents below and submit them for review.',
        'resubmitted' => 'Your corrected documents were submitted and are waiting for the SJBAC review.',
        'under_review' => 'The SJBAC is reviewing your registration. This usually takes a few business days.',
        'approved' => 'You can browse and bid on open procurement projects.',
        default => null,
    };
    $requestedAt = $openCorrections->first()['requested_at'] ?? $profile?->review_requested_at;

    $progressStages = ['registered' => 'Registered', 'under_review' => 'Under Review', 'needs_action' => 'Needs Action', 'resubmitted' => 'Resubmitted', 'approved' => 'Approved'];
    $progressOrder = array_keys($progressStages);
    $currentStageIndex = array_search(in_array($accountStatusKey, $progressOrder, true) ? $accountStatusKey : 'under_review', $progressOrder, true);
    $showProgressTracker = ! in_array($accountStatusKey, ['rejected', 'suspended', 'approved'], true);
    $showActionRequired = $accountStatusKey === 'needs_action' && $openCorrections->isNotEmpty();
    $flaggedTypes = $openCorrections->pluck('document_type')->all();

    // Registration checklist rows, then anything else on file (e.g. PCAB License).
    $checklist = collect($registrationRequirementOptions)->map(fn (array $option) => $option + [
        'document' => $registrationDocuments->get($option['document_type']),
        'flagged' => in_array($option['document_type'], $flaggedTypes, true),
    ]);
    $otherDocuments = $bidderDocuments->reject(fn ($document) => $checklist->contains('document_type', $document->document_type));
    $onFile = $checklist->filter(fn ($row) => $row['document'])->count();
    $requiredTotal = $checklist->where('required', true)->count();
    $requiredOnFile = $checklist->where('required', true)->filter(fn ($row) => $row['document'])->count();
    $documentHistory = $bidderDocumentHistory->where('is_current', false)->sortByDesc('id');
    $uploadedOn = fn ($document) => optional($document->uploaded_at ?? $document->created_at)->format('M d, Y');
    $tracksValidity = \App\Models\BidderDocument::tracksValidity();
    // What the BAC made of each file: corrections asked, accepted with the approval, or a newer upload it has not seen.
    $bacStatus = function ($document, bool $flagged) use ($isApproved, $profile) {
        if ($flagged) return ['Correction requested', 'warning'];
        if (! $document) return null;
        $uploaded = $document->uploaded_at ?? $document->created_at;
        if ($isApproved && $profile?->approved_at && $uploaded && $uploaded->lessThanOrEqualTo($profile->approved_at)) {
            return ['Accepted by the BAC · '.$profile->approved_at->format('M d, Y'), 'success'];
        }
        return ['Not yet reviewed by the BAC', 'neutral'];
    };
    $missingLabels = $checklist->filter(fn ($row) => $row['required'] && ! $row['document'])->pluck('label');
    $expiredCount = $tracksValidity ? $bidderDocuments->filter(fn ($document) => ($document->validity()[0] ?? null) === 'expired')->count() : 0;
    $expiringCount = $tracksValidity ? $bidderDocuments->filter(fn ($document) => ($document->validity()[0] ?? null) === 'expiring')->count() : 0;
    $completePercent = $checklist->count() ? (int) round($onFile / $checklist->count() * 100) : 100;
    $adminContactId = \App\Models\User::where('role', 'admin')->value('id');
@endphp

@section('title', 'Company profile & documents')
@section('subtitle', 'Your registration status, company details and documents on file.')

@section('actions')
    @unless($isApproved)
        <button type="button" class="ui-btn ui-btn--secondary" id="openCompanyEditModal" data-dialog-open="companyEditModal"><i class="fas fa-pen" aria-hidden="true"></i> Edit details</button>
    @endunless
@endsection

@push('head')
<style>
    .cp-page { display: grid; gap: 16px; }
    .cp-status { display: flex; align-items: flex-start; gap: 14px; padding: 16px 18px; border: 1px solid var(--ui-line); border-left: 4px solid var(--ui-info); border-radius: var(--ui-radius-lg); background: var(--ui-info-soft); }
    .cp-status.is-success { border-left-color: var(--ui-success); background: var(--ui-success-soft); }
    .cp-status.is-warning { border-left-color: var(--ui-warning); background: var(--ui-warning-soft); }
    .cp-status.is-danger { border-left-color: var(--ui-danger); background: var(--ui-danger-soft); }
    .cp-status-icon { display: grid; place-items: center; flex: 0 0 40px; height: 40px; border-radius: 11px; background: #fff; color: var(--ui-info); font-size: 17px; }
    .cp-status.is-success .cp-status-icon { color: var(--ui-success); }
    .cp-status.is-warning .cp-status-icon { color: var(--ui-warning); }
    .cp-status.is-danger .cp-status-icon { color: var(--ui-danger); }
    .cp-status-copy { display: grid; gap: 3px; min-width: 0; }
    .cp-status-label { color: var(--ui-muted); font-size: var(--ui-text-xs); font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .cp-status h2 { margin: 0; color: var(--ui-ink); font-size: 16px; font-weight: 700; }
    .cp-status p { margin: 0; color: var(--ui-ink-2); font-size: var(--ui-text-sm); line-height: 1.5; }
    .cp-status-meta { display: flex; flex-wrap: wrap; gap: 4px 14px; margin-top: 4px; color: var(--ui-ink-2); font-size: var(--ui-text-xs); font-weight: 600; }

    .cp-progress { margin: 0; padding: 14px 18px; overflow-x: auto; border: 1px solid var(--ui-line); border-radius: var(--ui-radius-lg); background: var(--ui-surface); }
    .cp-progress ol { display: flex; align-items: center; min-width: 520px; margin: 0; padding: 0; list-style: none; }
    .cp-progress li { display: flex; flex: 1 1 auto; align-items: center; gap: 8px; color: var(--ui-subtle); font-size: 12.5px; font-weight: 600; white-space: nowrap; }
    .cp-progress li:not(:last-child)::after { content: ''; flex: 1 1 auto; height: 2px; margin: 0 10px; background: var(--ui-line); }
    .cp-progress-dot { display: grid; place-items: center; flex: 0 0 22px; height: 22px; border: 2px solid var(--ui-line-strong); border-radius: 50%; background: #fff; font-size: 9px; }
    .cp-progress li.is-complete { color: var(--ui-success); }
    .cp-progress li.is-complete::after { background: var(--ui-success-line); }
    .cp-progress li.is-complete .cp-progress-dot { border-color: var(--ui-success); background: var(--ui-success); color: #fff; }
    .cp-progress li.is-current { color: var(--ui-primary); }
    .cp-progress li.is-current .cp-progress-dot { border-color: var(--ui-primary); color: var(--ui-primary); box-shadow: 0 0 0 4px var(--ui-primary-soft); }

    .cp-doc-file { display: block; overflow: hidden; color: var(--ui-subtle); font-size: 11.5px; text-overflow: ellipsis; white-space: nowrap; }
    .cp-doc-file i { margin-right: 4px; }
    .cp-tabs { display: flex; gap: 4px; overflow-x: auto; border-bottom: 1px solid var(--ui-line); }
    .cp-tab { display: inline-flex; align-items: center; gap: 8px; margin-bottom: -1px; padding: 11px 16px; border: 0; border-bottom: 2px solid transparent; background: transparent; color: var(--ui-muted); font: inherit; font-size: var(--ui-text-sm); font-weight: 600; white-space: nowrap; cursor: pointer; }
    .cp-tab:hover { color: var(--ui-ink); }
    .cp-tab.is-active { border-bottom-color: var(--ui-primary); color: var(--ui-primary); }
    .cp-tab:focus-visible { outline: 2px solid var(--ui-primary); outline-offset: -2px; border-radius: 6px; }
    .cp-tab-count { padding: 1px 8px; border-radius: 999px; background: var(--ui-success-soft); color: var(--ui-success); font-size: 11.5px; font-weight: 700; font-variant-numeric: tabular-nums; }
    .cp-tab-count.is-warn { background: var(--ui-warning-soft); color: var(--ui-warning); }
    /* Phone: the three sections share the width (labels may wrap), none hidden past the edge. */
    @media (max-width: 640px) {
        .cp-tabs { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr); gap: 0; overflow: visible; }
        .cp-tab { flex-direction: column; justify-content: center; gap: 4px; min-height: 52px; padding: 8px 4px; font-size: 13px; line-height: 1.2; text-align: center; white-space: normal; }
        .cp-tab > i { display: none; }
    }
    .cp-panel[hidden] { display: none; }
    .cp-panel--narrow { max-width: 760px; }
    .cp-record { display: grid; margin: 0; padding: 0; list-style: none; }
    .cp-record li { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 12px 18px; border-top: 1px solid var(--ui-line); }
    .cp-record li > div:first-child { display: grid; gap: 2px; min-width: 0; }
    .cp-record li strong { color: var(--ui-ink); font-size: 14px; }
    .cp-record li span { color: var(--ui-muted); font-size: 12.5px; }
    .cp-record-side { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; text-align: right; flex: 0 0 auto; }
    .cp-record-side strong { font-size: 14px; }
    @media (max-width: 640px) { .cp-record li { flex-direction: column; align-items: flex-start; } .cp-record-side { flex-direction: row; align-items: center; gap: 10px; text-align: left; } }
    .cp-grid { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 7fr); gap: 16px; align-items: start; }
    .cp-col { display: grid; gap: 16px; min-width: 0; }
    .cp-card-note { display: inline-flex; align-items: center; gap: 6px; color: var(--ui-muted); font-size: var(--ui-text-xs); font-weight: 600; }

    .cp-company { display: flex; align-items: center; gap: 14px; }
    .cp-avatar { display: grid; place-items: center; flex: 0 0 52px; height: 52px; border-radius: 14px; background: var(--ui-primary); color: #fff; font-size: 20px; font-weight: 700; }
    .cp-company h3 { margin: 0; color: var(--ui-ink); font-size: 16px; font-weight: 700; overflow-wrap: anywhere; }
    .cp-company p { margin: 2px 0 6px; color: var(--ui-muted); font-size: var(--ui-text-sm); overflow-wrap: anywhere; }
    .cp-facts { display: grid; gap: 0; margin: 16px 0 0; }
    .cp-facts > div { display: grid; grid-template-columns: 150px minmax(0, 1fr); gap: 10px; padding: 9px 0; border-top: 1px solid var(--ui-line-soft); }
    .cp-facts dt { color: var(--ui-muted); font-size: var(--ui-text-sm); }
    .cp-facts dd { margin: 0; color: var(--ui-ink); font-size: var(--ui-text-sm); font-weight: 600; overflow-wrap: anywhere; }
    .cp-facts dd.is-missing { color: var(--ui-danger); font-style: italic; }

    .cp-qr { display: grid; grid-template-columns: 120px minmax(0, 1fr); gap: 16px; align-items: center; }
    .cp-qr-image { display: block; padding: 8px; border: 1px solid var(--ui-line); border-radius: var(--ui-radius); background: #fff; }
    .cp-qr-image img { display: block; width: 100%; height: auto; }
    .cp-qr-copy { display: grid; gap: 8px; min-width: 0; }
    .cp-qr-link { display: flex; gap: 6px; }
    .cp-qr-link .ui-input { min-width: 0; font-family: var(--ui-mono); font-size: 12px; }
    .cp-qr-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .cp-qr-hint { margin: 0; color: var(--ui-muted); font-size: var(--ui-text-xs); line-height: 1.5; }

    .cp-docs { display: grid; margin: 0; padding: 0; list-style: none; }
    .cp-doc { display: grid; grid-template-columns: 38px minmax(0, 1fr) auto; align-items: center; gap: 14px; padding: 14px 18px; border-top: 1px solid var(--ui-line-soft); }
    .cp-doc:first-child { border-top: 0; }
    .cp-doc-icon { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 10px; background: var(--ui-success-soft); color: var(--ui-success); }
    .cp-doc.is-missing .cp-doc-icon { background: var(--ui-page); color: var(--ui-subtle); }
    .cp-doc.is-flagged .cp-doc-icon { background: var(--ui-warning-soft); color: var(--ui-warning); }
    .cp-doc-copy { display: grid; gap: 2px; min-width: 0; }
    .cp-doc-title { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; color: var(--ui-ink); font-size: 13px; font-weight: 600; }
    .cp-doc-meta { overflow: hidden; color: var(--ui-muted); font-size: var(--ui-text-xs); text-overflow: ellipsis; white-space: nowrap; }
    .cp-doc-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 6px; }
    .cp-meter { display: grid; gap: 8px; margin-bottom: 14px; padding: 14px 16px; border: 1px solid var(--ui-line); border-radius: var(--ui-radius-lg, 12px); background: var(--ui-surface, #fff); }
    .cp-meter-head { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; }
    .cp-meter-head strong { color: var(--ui-ink); font-size: 14px; }
    .cp-meter-head span { color: var(--ui-primary); font-weight: 700; }
    .cp-meter-note { margin: 0; color: var(--ui-muted); font-size: 12.5px; }
    .cp-meter-note.is-alert { color: var(--ui-danger, #b42318); font-weight: 600; }
    .cp-doc-flags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
    .cp-flag { padding: 1px 8px; border-radius: 999px; font-size: 11.5px; font-weight: 600; background: var(--ui-surface-2, #f4f7f5); color: var(--ui-muted); }
    .cp-flag.is-success { background: var(--ui-success-soft, #ecf7f0); color: var(--ui-success, #1f7a4d); }
    .cp-flag.is-warning { background: var(--ui-warning-soft, #fdf6e5); color: var(--ui-warning, #8a5a0b); }
    .cp-flag.is-danger { background: var(--ui-danger-soft, #fdecec); color: var(--ui-danger, #b42318); }
    .cp-validity { margin-top: 6px; }
    .cp-validity label { display: inline-flex; align-items: center; gap: 8px; color: var(--ui-muted); font-size: 12px; }
    .cp-validity input { width: auto; min-height: 32px; padding: 4px 8px; font-size: 12.5px; }
    .cp-drop { display: flex; flex: 1 1 260px; align-items: center; justify-content: center; gap: 10px; min-height: 52px; padding: 10px 14px; border: 1.5px dashed var(--ui-line-strong); border-radius: 10px; background: var(--ui-surface-2, #f7faf8); color: var(--ui-ink-2); font-size: 13px; cursor: pointer; transition: border-color .15s ease, background .15s ease; }
    .cp-drop:hover, .cp-drop.is-over { border-color: var(--ui-primary); background: var(--ui-primary-soft, #eef6f2); }
    .cp-drop i { color: var(--ui-primary); font-size: 18px; }
    .cp-upload-validity { flex: 0 1 190px; }
    .cp-locked { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-top: 12px; }
    .cp-locked .ui-hint { margin: 0; }
    .cp-panel--qr { margin-top: 14px; }
    .cp-panel--qr .cp-qr-image { width: 96px; }
    .cp-preview { width: min(980px, calc(100vw - 24px)); height: min(860px, calc(100dvh - 24px)); padding: 0; border: 0; border-radius: 16px; overflow: hidden; }
    .cp-preview[open] { display: grid; grid-template-rows: auto minmax(0, 1fr); }
    .cp-preview::backdrop { background: rgba(15, 25, 21, .55); }
    .cp-preview header { display: flex; justify-content: space-between; align-items: center; padding: 12px 18px; border-bottom: 1px solid var(--ui-line); }
    .cp-preview h2 { margin: 0; font-size: 16px; }
    .cp-preview-close { width: 32px; height: 32px; border: 1px solid var(--ui-line); border-radius: 9px; background: #fff; font-size: 18px; cursor: pointer; }
    .cp-preview iframe { width: 100%; height: 100%; border: 0; }
    .cp-inline-form { margin: 0; }
    .cp-file-input { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
    .cp-group-title { margin: 0; padding: 12px 18px 4px; color: var(--ui-muted); font-size: var(--ui-text-xs); font-weight: 700; text-transform: uppercase; letter-spacing: .04em; border-top: 1px solid var(--ui-line); }

    .cp-upload { display: grid; gap: 10px; padding: 16px 18px; border-top: 1px solid var(--ui-line); background: var(--ui-page); }
    .cp-upload-row { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 8px; align-items: end; }
    .cp-upload-name { color: var(--ui-muted); font-size: var(--ui-text-xs); overflow-wrap: anywhere; }
    .cp-history { padding: 12px 18px 16px; border-top: 1px solid var(--ui-line); }
    .cp-history summary { color: var(--ui-ink-2); font-size: var(--ui-text-sm); font-weight: 600; cursor: pointer; }
    .cp-history summary span { color: var(--ui-subtle); font-weight: 500; }
    .cp-history ul { display: grid; gap: 6px; margin: 10px 0 0; padding: 0; list-style: none; }
    .cp-history li { display: flex; justify-content: space-between; gap: 10px; padding: 8px 10px; border-radius: var(--ui-radius); background: var(--ui-page); color: var(--ui-muted); font-size: var(--ui-text-xs); }
    .cp-history li strong { color: var(--ui-ink-2); }

    .cp-corrections { display: grid; margin: 0; padding: 0; list-style: none; }
    .cp-correction { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 14px 18px; border-top: 1px solid var(--ui-line-soft); }
    .cp-correction:first-child { border-top: 0; }
    .cp-correction h3 { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; margin: 0; color: var(--ui-ink); font-size: 13.5px; font-weight: 700; }
    .cp-correction-reason { margin: 4px 0 0; padding: 7px 10px; border-radius: var(--ui-radius); background: var(--ui-warning-soft); color: var(--ui-ink-2); font-size: var(--ui-text-sm); line-height: 1.5; }
    .cp-correction.is-replaced .cp-correction-reason { background: var(--ui-page); }
    .cp-correction-file { margin: 6px 0 0; color: var(--ui-muted); font-size: var(--ui-text-xs); }
    .cp-correction-file.is-missing { color: var(--ui-danger); font-weight: 600; }
    .cp-submit-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 16px; padding: 14px 18px; border-top: 1px solid var(--ui-line); background: var(--ui-page); }
    .cp-submit-bar p { margin: 0; color: var(--ui-muted); font-size: var(--ui-text-sm); }
    .cp-submit-bar p.is-ready { color: var(--ui-success); font-weight: 600; }

    .cp-dialog .ui-card__body { display: grid; gap: 14px; }
    .cp-dialog-foot { display: flex; justify-content: flex-end; gap: 8px; padding: 14px 18px; border-top: 1px solid var(--ui-line); }

    @media (max-width: 1100px) {
        .cp-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 640px) {
        .cp-facts > div { grid-template-columns: 1fr; gap: 2px; }
        .cp-qr { grid-template-columns: 1fr; justify-items: start; }
        .cp-qr-image { width: 140px; }
        .cp-doc { grid-template-columns: 30px minmax(0, 1fr); padding: 12px 14px; }
        .cp-doc-actions { grid-column: 1 / -1; justify-content: flex-start; }
        .cp-correction { grid-template-columns: 1fr; padding: 14px; }
        .cp-upload-row { grid-template-columns: 1fr 1fr; }
        .cp-upload-row .ui-field { grid-column: 1 / -1; }
    }
</style>
@endpush

@section('content')
<div class="cp-page">
    @if($errors->any())
        <div class="ui-alert ui-alert--danger" role="alert">
            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    {{-- Account status: one source of truth from the controller. --}}
    <section class="cp-status is-{{ $statusMeta['tone'] }}" aria-label="Account status">
        <span class="cp-status-icon" aria-hidden="true"><i class="fas {{ $statusMeta['icon'] }}"></i></span>
        <div class="cp-status-copy">
            <span class="cp-status-label">Account status &middot; {{ $statusMeta['label'] }}</span>
            <h2>{{ $statusTitle }}</h2>
            @if($statusDescription)<p>{{ $statusDescription }}</p>@endif
            @if($accountStatusKey === 'needs_action')
                <div class="cp-status-meta">
                    @if($requestedAt)<span><i class="far fa-calendar" aria-hidden="true"></i> Requested {{ $requestedAt->format('M d, Y') }}</span>@endif
                    <span><i class="fas fa-file-circle-exclamation" aria-hidden="true"></i> {{ $openCorrections->count() }} {{ \Illuminate\Support\Str::plural('document', $openCorrections->count()) }} to correct</span>
                </div>
            @endif
            @unless($isApproved)
                <p class="cp-status-meta"><span><i class="fas fa-lock" aria-hidden="true"></i> Bidding unlocks once your registration is approved. Your profile, messages and notifications stay available.</span></p>
            @endunless
        </div>
    </section>

    @if($showProgressTracker)
        <nav class="cp-progress" aria-label="Registration progress">
            <ol>
                @foreach($progressStages as $stageKey => $stageLabel)
                    @php $stageIndex = array_search($stageKey, $progressOrder, true); @endphp
                    <li class="{{ $stageIndex < $currentStageIndex ? 'is-complete' : ($stageIndex === $currentStageIndex ? 'is-current' : '') }}" @if($stageIndex === $currentStageIndex) aria-current="step" @endif>
                        <span class="cp-progress-dot" aria-hidden="true"><i class="fas {{ $stageIndex < $currentStageIndex ? 'fa-check' : 'fa-circle' }}"></i></span>
                        {{ $stageLabel }}
                    </li>
                @endforeach
            </ol>
        </nav>
    @endif

    {{-- Documents the SJBAC asked the bidder to correct. --}}
    @if($showActionRequired)
        <section class="ui-card" aria-labelledby="cp-action-title">
            <div class="ui-card__head">
                <div>
                    <h2 class="ui-card__title" id="cp-action-title"><i class="fas fa-triangle-exclamation" aria-hidden="true" style="color: var(--ui-warning)"></i> Action Required</h2>
                    <p class="ui-card__desc">Replace each document below, then submit your corrections for review.</p>
                </div>
            </div>
            <ul class="cp-corrections">
                @foreach($openCorrections as $correction)
                    @php $doc = $correction['document']; @endphp
                    <li class="cp-correction {{ $correction['replaced'] ? 'is-replaced' : '' }}">
                        <div>
                            <h3>
                                {{ $correction['label'] }}
                                @if($correction['replaced'])
                                    <span class="ui-badge ui-badge--success">Replaced</span>
                                @else
                                    <span class="ui-badge ui-badge--warning">Needs correction</span>
                                @endif
                            </h3>
                            <p class="cp-correction-reason"><strong>SJBAC note:</strong> {{ $correction['reason'] }}</p>
                            @if($doc)
                                <p class="cp-correction-file">{{ $correction['replaced'] ? 'New file' : 'Current file' }}: <span title="{{ $doc->display_name }}">{{ \Illuminate\Support\Str::limit($doc->display_name, 48) }}</span> &middot; Uploaded {{ $uploadedOn($doc) }}</p>
                            @else
                                <p class="cp-correction-file is-missing">Not uploaded yet</p>
                            @endif
                        </div>
                        <div class="cp-doc-actions">
                            @if($doc)
                                <a href="{{ route('bidder.document.preview', $doc) }}" target="_blank" rel="noopener" class="ui-btn ui-btn--ghost ui-btn--sm"><i class="fas fa-eye" aria-hidden="true"></i> Preview</a>
                            @endif
                            <form action="{{ route('bidder.documents.store') }}" method="POST" enctype="multipart/form-data" class="cp-inline-form" data-auto-upload>
                                @csrf
                                <input type="hidden" name="document_type" value="{{ $correction['document_type'] }}">
                                <input type="file" name="document_file" class="cp-file-input" id="correction-upload-{{ $loop->index }}" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <label for="correction-upload-{{ $loop->index }}" class="ui-btn {{ $correction['replaced'] ? 'ui-btn--secondary' : 'ui-btn--primary' }} ui-btn--sm"><i class="fas fa-upload" aria-hidden="true"></i> {{ $correction['replaced'] ? 'Replace again' : 'Replace' }}</label>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="cp-submit-bar">
                <p class="{{ $allCorrectionsReplaced ? 'is-ready' : '' }}">
                    @if($allCorrectionsReplaced)
                        <i class="fas fa-circle-check" aria-hidden="true"></i> All flagged documents have been replaced. You're ready to submit.
                    @else
                        Replace every flagged document above to enable submission.
                    @endif
                </p>
                <button type="button" id="openSubmitCorrectionsModal" class="ui-btn ui-btn--primary" data-dialog-open="confirmResubmitModal" @disabled(! $allCorrectionsReplaced)>
                    <i class="fas fa-paper-plane" aria-hidden="true"></i> Submit Corrections for Review
                </button>
            </div>
        </section>
    @elseif($accountStatusKey === 'resubmitted')
        <div class="ui-alert" role="status">
            <i class="fas fa-clock" aria-hidden="true"></i>
            <span>Submitted for re-evaluation. The SJBAC will review your updated documents.</span>
        </div>
    @endif

    @php $hasQr = $isApproved && $profile?->tokenQrUrl(); @endphp
    <section class="cp-meter" aria-label="Profile completeness">
        <div class="cp-meter-head">
            <strong>Profile {{ $onFile }} of {{ $checklist->count() }} complete</strong>
            <span>{{ $completePercent }}%</span>
        </div>
        <div class="ui-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $completePercent }}"><span style="width: {{ $completePercent }}%"></span></div>
        @if($missingLabels->isNotEmpty())<p class="cp-meter-note">Missing: {{ $missingLabels->implode(', ') }}.</p>@endif
        @if($expiredCount || $expiringCount)
            <p class="cp-meter-note is-alert"><i class="fas fa-calendar-xmark" aria-hidden="true"></i>
                @if($expiredCount){{ $expiredCount }} {{ \Illuminate\Support\Str::plural('document', $expiredCount) }} expired. @endif
                @if($expiringCount){{ $expiringCount }} {{ \Illuminate\Support\Str::plural('document', $expiringCount) }} {{ $expiringCount === 1 ? 'expires' : 'expire' }} within 30 days. @endif
                Upload the renewed copies so your bids are not disqualified.
            </p>
        @endif
    </section>
    <div class="cp-tabs" role="tablist" aria-label="Profile sections">
        <button type="button" role="tab" class="cp-tab is-active" id="cp-tab-documents" aria-controls="cp-panel-documents" aria-selected="true" data-cp-tab="documents">
            <i class="fas fa-folder-open" aria-hidden="true"></i> Documents <span class="cp-tab-count {{ $requiredOnFile < $requiredTotal ? 'is-warn' : '' }}">{{ $onFile }}/{{ $checklist->count() }}</span>
        </button>
        <button type="button" role="tab" class="cp-tab" id="cp-tab-company" aria-controls="cp-panel-company" aria-selected="false" data-cp-tab="company" tabindex="-1">
            <i class="fas fa-building" aria-hidden="true"></i> Company details
        </button>
        <button type="button" role="tab" class="cp-tab" id="cp-tab-record" aria-controls="cp-panel-record" aria-selected="false" data-cp-tab="record" tabindex="-1">
            <i class="fas fa-trophy" aria-hidden="true"></i> Bidding record <span class="cp-tab-count">{{ $recordWon->count() + $recordApproved->count() + $recordOther->count() }}</span>
        </button>

    </div>

    <div class="cp-panel" id="cp-panel-documents" role="tabpanel" aria-labelledby="cp-tab-documents" data-cp-panel="documents">
        <section class="ui-card" aria-labelledby="cp-docs-title">
            <div class="ui-card__head">
                <div>
                    <h2 class="ui-card__title" id="cp-docs-title">Registration documents</h2>
                    <p class="ui-card__desc">{{ $onFile }} of {{ $checklist->count() }} on file{{ $missingRegistrationRequirements ? ' · '.count($missingRegistrationRequirements).' required still missing' : '' }}.</p>
                </div>
            </div>
            <ul class="cp-docs">
                @foreach($checklist as $row)
                    @php $document = $row['document']; @endphp
                    <li class="cp-doc {{ $row['flagged'] ? 'is-flagged' : (! $document ? 'is-missing' : '') }}">
                        <span class="cp-doc-icon" aria-hidden="true"><i class="fas {{ $row['flagged'] ? 'fa-file-circle-exclamation' : ($document ? 'fa-file-circle-check' : 'fa-file') }}"></i></span>
                        <div class="cp-doc-copy">
                            <span class="cp-doc-title">
                                {{ $row['label'] }}
                                @if($row['flagged'])
                                    <span class="ui-badge ui-badge--warning">Correction requested</span>
                                @elseif($document)
                                    <span class="ui-badge ui-badge--success">On file</span>
                                @elseif($row['required'])
                                    <span class="ui-badge ui-badge--danger">Missing</span>
                                @else
                                    <span class="ui-badge ui-badge--neutral">Optional</span>
                                @endif
                            </span>
                            <span class="cp-doc-meta" title="{{ $document?->display_name }}">
                                @if($document)
                                    Version {{ $document->version ?: 1 }} &middot; Uploaded {{ $uploadedOn($document) }}
                                @else
                                    Not uploaded yet
                                @endif
                            </span>
                            @if($document)<span class="cp-doc-file" title="{{ $document->display_name }}"><i class="far fa-file-lines" aria-hidden="true"></i> {{ $document->display_name }}</span>@endif
                            @php $status = $bacStatus($document, $row['flagged']); $validity = $document?->validity(); @endphp
                            @if($document)
                                <span class="cp-doc-flags">
                                    @if($status && ! $row['flagged'])<span class="cp-flag is-{{ $status[1] }}">{{ $status[0] }}</span>@endif
                                    @if($validity)
                                        <span class="cp-flag is-{{ $validity[0] === 'expired' ? 'danger' : ($validity[0] === 'expiring' ? 'warning' : 'success') }}">
                                            {{ $validity[0] === 'expired' ? 'Expired '.$document->valid_until->format('M d, Y').': upload the renewed copy' : 'Valid until '.$document->valid_until->format('M d, Y').($validity[0] === 'expiring' ? ' · '.$validity[1].' days left' : '') }}
                                        </span>
                                    @endif
                                </span>
                                @if($tracksValidity)
                                    <form action="{{ route('bidder.documents.validity', $document) }}" method="POST" class="cp-validity">
                                        @csrf
                                        @method('PATCH')
                                        <label>Valid until <input type="date" name="valid_until" value="{{ $document->valid_until?->format('Y-m-d') }}" class="ui-input" data-cp-validity></label>
                                    </form>
                                @endif
                            @endif
                        </div>
                        <div class="cp-doc-actions">
                            @if($document)
                                <a href="{{ route('bidder.document.preview', $document) }}" target="_blank" rel="noopener" data-cp-preview="{{ $document->document_type }}" class="ui-btn ui-btn--ghost ui-btn--sm" aria-label="Preview {{ $row['label'] }}"><i class="fas fa-eye" aria-hidden="true"></i> Preview</a>
                                <a href="{{ $document->file_url }}" download="{{ $document->display_name }}" class="ui-btn ui-btn--ghost ui-btn--sm" aria-label="Download {{ $row['label'] }}"><i class="fas fa-download" aria-hidden="true"></i></a>
                            @endif
                            @unless($row['flagged'])
                                <form action="{{ route('bidder.documents.store') }}" method="POST" enctype="multipart/form-data" class="cp-inline-form" data-auto-upload>
                                    @csrf
                                    <input type="hidden" name="document_type" value="{{ $row['document_type'] }}">
                                    <input type="file" name="document_file" class="cp-file-input" id="doc-upload-{{ $loop->index }}" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <label for="doc-upload-{{ $loop->index }}" class="ui-btn {{ $document ? 'ui-btn--secondary' : 'ui-btn--primary' }} ui-btn--sm"><i class="fas fa-upload" aria-hidden="true"></i> {{ $document ? 'Replace' : 'Upload' }}</label>
                                </form>
                            @endunless
                        </div>
                    </li>
                @endforeach
            </ul>

            @if($otherDocuments->isNotEmpty())
                <h3 class="cp-group-title">Other documents on file</h3>
                <ul class="cp-docs">
                    @foreach($otherDocuments as $document)
                        <li class="cp-doc">
                            <span class="cp-doc-icon" aria-hidden="true"><i class="fas fa-file-circle-check"></i></span>
                            <div class="cp-doc-copy">
                                <span class="cp-doc-title">{{ $document->document_type }}</span>
                                <span class="cp-doc-meta">Version {{ $document->version ?: 1 }} &middot; Uploaded {{ $uploadedOn($document) }}</span>
                                <span class="cp-doc-file" title="{{ $document->display_name }}"><i class="far fa-file-lines" aria-hidden="true"></i> {{ $document->display_name }}</span>
                                @php $status = $bacStatus($document, false); $validity = $document->validity(); @endphp
                                <span class="cp-doc-flags">
                                    @if($status)<span class="cp-flag is-{{ $status[1] }}">{{ $status[0] }}</span>@endif
                                    @if($validity)
                                        <span class="cp-flag is-{{ $validity[0] === 'expired' ? 'danger' : ($validity[0] === 'expiring' ? 'warning' : 'success') }}">
                                            {{ $validity[0] === 'expired' ? 'Expired '.$document->valid_until->format('M d, Y').': upload the renewed copy' : 'Valid until '.$document->valid_until->format('M d, Y').($validity[0] === 'expiring' ? ' · '.$validity[1].' days left' : '') }}
                                        </span>
                                    @endif
                                </span>
                                @if($tracksValidity)
                                    <form action="{{ route('bidder.documents.validity', $document) }}" method="POST" class="cp-validity">
                                        @csrf
                                        @method('PATCH')
                                        <label>Valid until <input type="date" name="valid_until" value="{{ $document->valid_until?->format('Y-m-d') }}" class="ui-input" data-cp-validity></label>
                                    </form>
                                @endif
                            </div>
                            <div class="cp-doc-actions">
                                <a href="{{ route('bidder.document.preview', $document) }}" target="_blank" rel="noopener" data-cp-preview="{{ $document->document_type }}" class="ui-btn ui-btn--ghost ui-btn--sm"><i class="fas fa-eye" aria-hidden="true"></i> Preview</a>
                                <a href="{{ $document->file_url }}" download="{{ $document->display_name }}" class="ui-btn ui-btn--ghost ui-btn--sm" aria-label="Download {{ $document->document_type }}"><i class="fas fa-download" aria-hidden="true"></i></a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form action="{{ route('bidder.documents.store') }}" method="POST" enctype="multipart/form-data" id="bidderDocumentUploadForm" class="cp-upload">
                @csrf
                <div class="cp-upload-row">
                    <div class="ui-field">
                        <label class="ui-label" for="bidderDocumentType">Upload another document</label>
                        <select name="document_type" id="bidderDocumentType" class="ui-input">
                            @foreach($uploadTypes as $type)
                                <option value="{{ $type }}" @selected(old('document_type', 'Audited Financial Statement') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="file" name="document_file" id="bidderDocumentUpload" class="cp-file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    <label for="bidderDocumentUpload" class="cp-drop" data-cp-drop><i class="fas fa-cloud-arrow-up" aria-hidden="true"></i> <span>Drop the file here or <u>choose a file</u></span></label>
                    @if($tracksValidity)
                        <div class="ui-field cp-upload-validity">
                            <label class="ui-label" for="bidderDocumentValidUntil">Valid until <small>(if written on it)</small></label>
                            <input type="date" name="valid_until" id="bidderDocumentValidUntil" class="ui-input">
                        </div>
                    @endif
                    <button type="submit" class="ui-btn ui-btn--primary" id="bidderDocumentSubmit" disabled><i class="fas fa-upload" aria-hidden="true"></i> Upload</button>
                </div>
                <span class="cp-upload-name" id="bidderDocumentName">PDF, DOC, DOCX, JPG or PNG, up to 20 MB. Uploading a type you already have saves it as a new version.</span>
            </form>

            @if($documentHistory->isNotEmpty())
                <details class="cp-history">
                    <summary>Earlier versions <span>({{ $documentHistory->count() }} archived)</span></summary>
                    <ul>
                        @foreach($documentHistory as $historyDocument)
                            <li><span><strong>{{ $historyDocument->document_type }}</strong> &middot; Version {{ $historyDocument->version ?: 1 }}</span><span>{{ $uploadedOn($historyDocument) }}</span></li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </section>
    </div>

    <div class="cp-panel cp-panel--narrow" id="cp-panel-company" role="tabpanel" aria-labelledby="cp-tab-company" data-cp-panel="company" hidden>
            <section class="ui-card" aria-labelledby="cp-company-title">
                <div class="ui-card__head">
                    <h2 class="ui-card__title" id="cp-company-title">Company Information</h2>
                    @if($isApproved)
                        <span class="cp-card-note" title="Verified company details are locked. Contact the SJBAC to request changes."><i class="fas fa-lock" aria-hidden="true"></i> Locked</span>
                    @else
                        <button type="button" class="ui-btn ui-btn--ghost ui-btn--sm" data-dialog-open="companyEditModal"><i class="fas fa-pen" aria-hidden="true"></i> Edit</button>
                    @endif
                </div>
                <div class="ui-card__body">
                    <div class="cp-company">
                        <span class="cp-avatar" aria-hidden="true">{{ $companyInitial }}</span>
                        <div>
                            <h3>{{ $companyLabel }}</h3>
                            <p>{{ $user->email }}</p>
                            <span class="ui-badge ui-badge--{{ $statusMeta['tone'] === 'success' ? 'success' : ($statusMeta['tone'] === 'info' ? 'info' : $statusMeta['tone']) }}">{{ $statusMeta['label'] }}</span>
                        </div>
                    </div>
                    <dl class="cp-facts">
                        <div><dt>Registration number</dt><dd>{{ $user->registration_no ?: 'Not provided' }}</dd></div>
                        <div><dt>Contact person</dt><dd>{{ $profile?->contact_person ?: $user->name }}</dd></div>
                        <div><dt>Contact number</dt><dd class="{{ $profile?->contact_number ? '' : 'is-missing' }}">{{ $profile?->contact_number ?: 'Not provided' }}</dd></div>
                        <div><dt>Business address</dt><dd>{{ $profile?->business_address ?: 'Not provided' }}</dd></div>
                    </dl>
                    @if($isApproved)
                        <div class="cp-locked">
                            <p class="ui-hint">Verified details are locked. Ask the SJBAC to change them.</p>
                            <a class="ui-btn ui-btn--secondary ui-btn--sm" href="{{ route('bidder.messages', array_filter(['user' => $adminContactId, 'draft' => 'Request to change our company details ('.$companyLabel.', registration no. '.($user->registration_no ?: 'n/a').'). Field to change: … New value: … Reason: …'])) }}"><i class="fas fa-pen-to-square" aria-hidden="true"></i> Request a change</a>
                        </div>
                    @endif
                </div>
            </section>
    </div>

    <div class="cp-panel" id="cp-panel-record" role="tabpanel" aria-labelledby="cp-tab-record" data-cp-panel="record" hidden>
        @php
            $recordTz = config('bac-office.display_timezone', 'Asia/Manila');
            $recordGroups = [
                ['Successful bids', 'Contracts you won.', $recordWon, 'success', 'Awarded'],
                ['Approved for award', 'Your bid passed and the Notice of Award is next.', $recordApproved, 'info', 'Approved'],
                ['Other submitted bids', 'Bids still being reviewed, and bids that were not awarded.', $recordOther, 'neutral', null],
            ];
        @endphp
        @foreach($recordGroups as [$groupTitle, $groupHint, $groupBids, $groupTone, $groupLabel])
            <section class="ui-card ui-mb" aria-label="{{ $groupTitle }}">
                <div class="ui-card__head">
                    <div>
                        <h2 class="ui-card__title">{{ $groupTitle }} <span class="cp-tab-count">{{ $groupBids->count() }}</span></h2>
                        <p class="ui-card__desc">{{ $groupHint }}</p>
                    </div>
                </div>
                @if($groupBids->isEmpty())
                    <div class="ui-card__body"><p class="ui-hint">Nothing here yet.</p></div>
                @else
                    <ul class="cp-record">
                        @foreach($groupBids as $recordBid)
                            <li>
                                <div>
                                    <strong>{{ $recordBid->project?->title ?? 'Untitled project' }}</strong>
                                    <span>{{ $recordBid->project?->reference_no }}@if($recordBid->project?->reference_no) · @endif Submitted {{ $recordBid->submitted_at?->timezone($recordTz)->format('M d, Y') }}</span>
                                </div>
                                <div class="cp-record-side">
                                    <span class="ui-badge ui-badge--{{ $groupTone }}">{{ $groupLabel ?? $recordBid->workflow_step_label }}</span>
                                    @if($groupLabel === 'Awarded' && $recordBid->award)<strong>₱{{ number_format((float) $recordBid->award->contract_amount, 2) }}</strong>@endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
        <p class="ui-hint">Every bid you submitted is also listed, with its status and documents, under <a href="{{ route('bidder.my-bids') }}">My bids</a>.</p>
    </div>

    @if($hasQr)
    <div class="cp-panel cp-panel--narrow cp-panel--qr" id="cp-panel-qr" aria-label="Public record QR" data-cp-panel="company" hidden>
                <section class="ui-card" aria-labelledby="cp-qr-title">
                    <div class="ui-card__head">
                        <div>
                            <h2 class="ui-card__title" id="cp-qr-title">Verified Bidding Record QR</h2>
                            <p class="ui-card__desc">Share your track record: anyone who scans it sees your approved bids and awarded contracts.</p>
                        </div>
                    </div>
                    <div class="ui-card__body cp-qr">
                        <a class="cp-qr-image" href="{{ $profile->verificationUrl() }}" target="_blank" rel="noopener" title="Open your public record">
                            <img src="{{ $profile->tokenQrUrl() }}" alt="QR code linking to your public bidding record">
                        </a>
                        <div class="cp-qr-copy">
                            <label class="ui-label" for="bidderQrLink">Public verification link</label>
                            <div class="cp-qr-link">
                                <input type="text" class="ui-input" id="bidderQrLink" value="{{ $profile->verificationUrl() }}" readonly>
                                <button type="button" class="ui-btn ui-btn--secondary" id="bidderQrCopyBtn"><i class="fas fa-copy" aria-hidden="true"></i> Copy</button>
                            </div>
                            <div class="cp-qr-actions">
                                <a href="{{ $profile->tokenQrUrl() }}" download="bidding-record-qr.svg" class="ui-btn ui-btn--secondary ui-btn--sm"><i class="fas fa-download" aria-hidden="true"></i> Download QR</a>
                                <a href="{{ $profile->verificationUrl() }}" target="_blank" rel="noopener" class="ui-btn ui-btn--ghost ui-btn--sm"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i> Open public page</a>
                            </div>
                            <p class="cp-qr-hint">Shows only your <strong>approved bids</strong> and <strong>awarded contracts</strong>, never your documents, pending bids or contact details.</p>
                        </div>
                    </div>
                </section>
    </div>
    @endif
</div>

<dialog class="cp-preview" id="cpPreviewDialog" aria-labelledby="cpPreviewTitle">
    <header><h2 id="cpPreviewTitle">Document</h2><button type="button" class="cp-preview-close" data-cp-preview-close aria-label="Close">&times;</button></header>
    <iframe title="Document preview" data-cp-preview-frame></iframe>
</dialog>

{{-- Confirmation before resubmitting corrections --}}
<dialog class="ui-dialog cp-dialog" id="confirmResubmitModal" aria-labelledby="confirmResubmitTitle" style="width: min(460px, calc(100vw - 32px))">
    <div class="ui-card__head">
        <h2 class="ui-card__title" id="confirmResubmitTitle">Submit corrections for review?</h2>
        <button type="button" class="ui-dialog__close" data-dialog-close aria-label="Close"><i class="fas fa-xmark" aria-hidden="true"></i></button>
    </div>
    <div class="ui-card__body">
        <p class="ui-muted" style="margin:0">Your replaced documents will be sent to the SJBAC for re-evaluation. You won't be able to submit again until they finish reviewing.</p>
    </div>
    <form action="{{ route('bidder.requirements.reevaluate') }}" method="POST" id="submitCorrectionsForm" class="cp-dialog-foot">
        @csrf
        <button type="button" class="ui-btn ui-btn--secondary" data-dialog-close>Cancel</button>
        <button type="submit" class="ui-btn ui-btn--primary" id="confirmSubmitCorrectionsBtn">Yes, submit for review</button>
    </form>
</dialog>

@unless($isApproved)
    {{-- Company details (editable until the account is approved) --}}
    <dialog class="ui-dialog cp-dialog" id="companyEditModal" aria-labelledby="companyEditTitle" @if($errors->hasAny(['company', 'registration_no'])) data-open-on-load @endif>
        <div class="ui-card__head">
            <h2 class="ui-card__title" id="companyEditTitle">Edit Company Profile</h2>
            <button type="button" class="ui-dialog__close" data-dialog-close aria-label="Close"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </div>
        <form method="POST" action="{{ route('bidder.profile.update') }}" id="companyProfileEditForm">
            @csrf
            @method('PATCH')
            <div class="ui-card__body">
                <div class="ui-field">
                    <label class="ui-label" for="cp-company">Company name <span class="ui-required" aria-hidden="true">*</span></label>
                    <input type="text" name="company" id="cp-company" class="ui-input" value="{{ old('company', $user->company) }}" required maxlength="255">
                    @error('company')<span class="ui-error">{{ $message }}</span>@enderror
                </div>
                <div class="ui-field">
                    <label class="ui-label" for="cp-registration">Registration number <span class="ui-required" aria-hidden="true">*</span></label>
                    <input type="text" name="registration_no" id="cp-registration" class="ui-input" value="{{ old('registration_no', $user->registration_no) }}" required maxlength="255">
                    @error('registration_no')<span class="ui-error">{{ $message }}</span>@enderror
                </div>
                <div class="ui-field">
                    <label class="ui-label" for="cp-email">Email address</label>
                    <input type="email" id="cp-email" class="ui-input" value="{{ $user->email }}" disabled>
                    <span class="ui-hint">Your sign-in email can't be changed here.</span>
                </div>
            </div>
            <div class="cp-dialog-foot">
                <button type="button" class="ui-btn ui-btn--secondary" data-dialog-close>Cancel</button>
                <button type="submit" class="ui-btn ui-btn--primary">Save changes</button>
            </div>
        </form>
    </dialog>
@endunless
@endsection

@push('scripts')
<script>
    (function () {
        var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-cp-tab]'));
        if (!tabs.length) return;
        function show(name, focus) {
            var found = tabs.some(function (tab) { return tab.dataset.cpTab === name; });
            if (!found) name = 'documents';
            tabs.forEach(function (tab) {
                var on = tab.dataset.cpTab === name;
                tab.classList.toggle('is-active', on);
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
                tab.tabIndex = on ? 0 : -1;
                if (on && focus) tab.focus();
            });
            document.querySelectorAll('[data-cp-panel]').forEach(function (panel) {
                panel.hidden = panel.dataset.cpPanel !== name;
            });
            try { history.replaceState(null, '', '#' + name); } catch (e) {}
        }
        tabs.forEach(function (tab, index) {
            tab.addEventListener('click', function () { show(tab.dataset.cpTab, false); });
            tab.addEventListener('keydown', function (event) {
                var next = event.key === 'ArrowRight' ? index + 1 : event.key === 'ArrowLeft' ? index - 1 : null;
                if (next === null) return;
                event.preventDefault();
                show(tabs[(next + tabs.length) % tabs.length].dataset.cpTab, true);
            });
        });
        show((location.hash || '').replace('#', '') || 'documents', false);
    })();
</script>
@endpush

@push('scripts')
<script>
    (function () {
        // Row uploads (Replace / Upload) send as soon as a file is picked.
        document.querySelectorAll('form[data-auto-upload] input[type=file]').forEach(function (input) {
            input.addEventListener('change', function () {
                if (!input.files.length) return;
                const label = input.form.querySelector('label[for="' + input.id + '"]');
                if (label) label.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Uploading…';
                input.form.submit();
            });
        });

        // Preview in a dialog over the page (the link still opens a tab without JavaScript).
        const previewDialog = document.getElementById('cpPreviewDialog');
        document.addEventListener('click', function (event) {
            const link = event.target.closest('[data-cp-preview]');
            if (link && previewDialog && typeof previewDialog.showModal === 'function') {
                event.preventDefault();
                previewDialog.querySelector('#cpPreviewTitle').textContent = link.dataset.cpPreview || 'Document';
                previewDialog.querySelector('[data-cp-preview-frame]').src = link.href;
                previewDialog.showModal();
            }
            if (event.target.closest('[data-cp-preview-close]') || event.target === previewDialog) {
                previewDialog.close();
                previewDialog.querySelector('[data-cp-preview-frame]').src = 'about:blank';
            }
        });

        // A validity date saves as soon as it is picked.
        document.querySelectorAll('[data-cp-validity]').forEach(function (input) {
            input.addEventListener('change', function () { input.form.submit(); });
        });

        // "Upload another document": drop a file or choose one; its name and size show before Upload.
        const drop = document.querySelector('[data-cp-drop]');
        const dropInput = document.getElementById('bidderDocumentUpload');
        if (drop && dropInput) {
            ['dragenter', 'dragover'].forEach(function (type) {
                drop.addEventListener(type, function (event) { event.preventDefault(); drop.classList.add('is-over'); });
            });
            ['dragleave', 'drop'].forEach(function (type) {
                drop.addEventListener(type, function () { drop.classList.remove('is-over'); });
            });
            drop.addEventListener('drop', function (event) {
                event.preventDefault();
                if (!event.dataTransfer || !event.dataTransfer.files.length) return;
                dropInput.files = event.dataTransfer.files;
                dropInput.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        // "Upload another document": choose, then confirm with Upload.        const upload = document.getElementById('bidderDocumentUpload');
        const uploadName = document.getElementById('bidderDocumentName');
        const uploadSubmit = document.getElementById('bidderDocumentSubmit');
        if (upload) {
            upload.addEventListener('change', function () {
                const file = upload.files.length ? upload.files[0] : null;
                if (uploadSubmit) uploadSubmit.disabled = !file;
                if (uploadName && file) uploadName.textContent = 'Selected: ' + file.name + ' (' + (file.size >= 1048576 ? (file.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(file.size / 1024)) + ' KB') + ')' + (file.size > 20 * 1048576 ? ': too large, the limit is 20 MB.' : '');
            });
        }

        const submitCorrectionsForm = document.getElementById('submitCorrectionsForm');
        const confirmSubmitCorrectionsBtn = document.getElementById('confirmSubmitCorrectionsBtn');
        if (submitCorrectionsForm && confirmSubmitCorrectionsBtn) {
            submitCorrectionsForm.addEventListener('submit', function () {
                confirmSubmitCorrectionsBtn.disabled = true;
                confirmSubmitCorrectionsBtn.textContent = 'Submitting…';
            });
        }

        const copyButton = document.getElementById('bidderQrCopyBtn');
        const link = document.getElementById('bidderQrLink');
        if (copyButton && link) {
            copyButton.addEventListener('click', function () {
                const done = function () {
                    copyButton.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i> Copied';
                    window.setTimeout(function () { copyButton.innerHTML = '<i class="fas fa-copy" aria-hidden="true"></i> Copy'; }, 1800);
                };
                link.select();
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(link.value).then(done).catch(function () { document.execCommand('copy'); done(); });
                } else {
                    document.execCommand('copy');
                    done();
                }
            });
        }
    })();
</script>
@endpush
