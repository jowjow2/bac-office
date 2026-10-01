{{--
    Bidder review: the Suppliers & users modal (?modal=1) and the standalone page (staff).
    users.blade.php submits the forms marked data-async-review-action by AJAX and polls
    #recentLoginActivityRows; every other form posts normally. Styles: the .brv block below.
--}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@php
    $isReviewModal = request()->boolean('modal');
    $reviewRoutePrefix = auth()->user()?->role === 'staff' ? 'staff' : 'admin';

    $profile = $user->bidderProfile;
    $companyName = $profile?->company_name ?? $user->company ?? 'N/A';
    $contactPerson = $profile?->contact_person ?? $user->name ?? 'N/A';
    $registrationStatus = $profile?->approval_status ?? ($user->status === 'active' ? 'approved' : $user->status);
    $bidderSanctionsAvailable = \Illuminate\Support\Facades\Schema::hasTable('bidder_sanctions');
    $procurementStatus = $user->bidderProcurementStatus();
    $activeSanction = $bidderSanctionsAvailable ? $profile?->activeSanction : null;
    $sanctionHistory = $bidderSanctionsAvailable ? ($profile?->sanctions ?? collect()) : collect();
    $isAccountActive = $user->status === 'active';
    $isPendingReview = $user->status === 'pending';
    $canApproveBidder = $isPendingReview && ! $activeSanction;
    $registrationRequirementsComplete = $missingRegistrationRequirements === [];

    $accountStatus = match ($user->status) {
        'active' => ['Approved', 'is-success'],
        'rejected' => ['Rejected', 'is-danger'],
        default => [ucfirst((string) $user->status), 'is-neutral'],
    };
    $reviewStatus = $isPendingReview ? ($profile?->review_status ?: 'new') : null;
    $reviewStatusLabel = match ($reviewStatus) {
        'new' => ['New', 'is-info'],
        'under_review' => ['Under review', 'is-info'],
        'needs_action' => ['Needs action', 'is-warning'],
        'for_re_evaluation' => ['For re-evaluation', 'is-info'],
        default => null,
    };
    $sanctionPill = $activeSanction ? [$activeSanction->status_label, $activeSanction->type === 'blacklisted' ? 'is-danger' : 'is-warning'] : null;

    // One row per registration requirement, with its current file (if any) and open correction request.
    $requirementRows = collect($registrationRequirementOptions)->map(function (array $requirement) use ($registrationDocuments, $openRequirementRequests) {
        $document = $registrationDocuments->firstWhere('document_type', $requirement['document_type']);
        $request = $openRequirementRequests->firstWhere('document_type', $requirement['document_type']);

        return $requirement + [
            'file' => $document,
            'request' => $request,
            'state' => $request ? 'correction' : ($document ? 'uploaded' : (($requirement['required'] ?? false) ? 'missing' : 'optional')),
        ];
    });
    $matchedTypes = $requirementRows->pluck('document_type')->all();
    $otherRegistrationFiles = $registrationDocuments->reject(fn ($document) => in_array($document->document_type, $matchedTypes, true))->values();
    $requiredCount = $requirementRows->where('required', true)->count();
    $uploadedRequiredCount = $requirementRows->where('required', true)->filter(fn ($row) => $row['file'])->count();
    $previewUrl = fn ($document) => route($reviewRoutePrefix . '.user.document.preview', ['user' => $user, 'document' => $document]);
    $stamp = fn ($at) => $at?->format('M d, Y h:i A');
@endphp

@unless($isReviewModal)
    @include('partials.dashboard-viewport')
    @vite(['resources/css/dashboard.css'])
    <div class="admin-dashboard admin-role-page">
        @include(auth()->user()?->role === 'staff' ? 'partials.staff-sidebar' : 'partials.admin-sidebar')
        <div class="main-area">
            <x-page-header title="Bidder review" subtitle="Review registration documents, approval status and login activity.">
                <x-slot:actions>
                    <a href="{{ route($reviewRoutePrefix . '.messages', ['user' => $user->id]) }}" class="brv-btn is-primary"><i class="fas fa-comments" aria-hidden="true"></i> Message bidder</a>
                    <a href="{{ route($reviewRoutePrefix . '.dashboard') }}" class="brv-btn"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back</a>
                </x-slot:actions>
            </x-page-header>
            <main class="dashboard-content">
@endunless

<div class="brv {{ $isReviewModal ? 'is-modal' : '' }}">
    @foreach(['success' => 'is-success', 'warning' => 'is-warning'] as $flash => $tone)
        @if(session($flash))
            <div class="brv-alert {{ $tone }}" role="status">{{ session($flash) }}</div>
        @endif
    @endforeach
    @if($errors->any())
        <div class="brv-alert is-danger" role="alert">
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Identity and status --}}
    <header class="brv-head">
        <span class="brv-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($companyName, 0, 1)) }}</span>
        <div class="brv-head-text">
            <h2>{{ $companyName }}</h2>
            <p>
                <span><i class="fas fa-envelope" aria-hidden="true"></i> {{ $user->email }}</span>
                @if($user->username)<span>{{ '@' . $user->username }}</span>@endif
                <span>Registered {{ $user->created_at?->format('M d, Y') }}</span>
            </p>
        </div>
        <div class="brv-pills">
            <span class="brv-pill {{ $accountStatus[1] }}">Account {{ $accountStatus[0] }}</span>
            @if($reviewStatusLabel)<span class="brv-pill {{ $reviewStatusLabel[1] }}">{{ $reviewStatusLabel[0] }}</span>@endif
            @if($sanctionPill)<span class="brv-pill {{ $sanctionPill[1] }}"><i class="fas fa-ban" aria-hidden="true"></i> {{ $sanctionPill[0] }}</span>@endif
        </div>
    </header>

    <div class="brv-layout">
        <div class="brv-main">
            {{-- Profile --}}
            <section class="brv-card" aria-labelledby="brvProfileTitle">
                <h3 id="brvProfileTitle">Bidder profile</h3>
                <dl class="brv-facts">
                    <div><dt>Company name</dt><dd>{{ $companyName }}</dd></div>
                    <div><dt>Business registration no.</dt><dd class="brv-mono">{{ $user->registration_no ?: 'N/A' }}</dd></div>
                    <div><dt>Contact person</dt><dd>{{ $contactPerson }}</dd></div>
                    <div><dt>Contact number</dt><dd>{{ $profile?->contact_number ?? 'N/A' }}</dd></div>
                    <div class="is-wide"><dt>Business address</dt><dd>{{ $profile?->business_address ?? 'N/A' }}</dd></div>
                    <div><dt>Approved</dt><dd>{{ $stamp($profile?->approved_at) ?? 'Not yet approved' }}</dd></div>
                    <div><dt>Approved by</dt><dd>{{ $profile?->approver?->name ?? '—' }}</dd></div>
                    @if($registrationStatus === 'rejected')
                        <div class="is-wide is-danger"><dt>Rejection reason</dt><dd>{{ $profile?->rejection_reason ?: 'No rejection reason was provided.' }}</dd></div>
                    @endif
                </dl>
            </section>

            {{-- Registration documents, one row per requirement --}}
            <section class="brv-card" aria-labelledby="brvDocsTitle">
                <div class="brv-card-head">
                    <h3 id="brvDocsTitle">Registration documents</h3>
                    <span class="brv-pill {{ $registrationRequirementsComplete ? 'is-success' : 'is-warning' }}">{{ $registrationRequirementsComplete ? 'Complete' : 'Incomplete' }}</span>
                </div>
                <div class="brv-progress" aria-label="{{ $uploadedRequiredCount }} of {{ $requiredCount }} required documents uploaded">
                    <span style="width: {{ $requiredCount ? round($uploadedRequiredCount / $requiredCount * 100) : 100 }}%"></span>
                </div>
                <p class="brv-note">
                    {{ $uploadedRequiredCount }} of {{ $requiredCount }} required documents uploaded.
                    @unless($registrationRequirementsComplete) Missing: {{ implode(', ', $missingRegistrationRequirements) }}. @endunless
                    @if($isPendingReview) Tick <strong>Needs correction</strong> on any file to include it in a correction request. @endif
                </p>

                <ul class="brv-docs">
                    @foreach($requirementRows as $row)
                        <li class="brv-doc is-{{ $row['state'] }}">
                            <span class="brv-doc-icon" aria-hidden="true">
                                <i class="fas {{ ['uploaded' => 'fa-file-circle-check', 'correction' => 'fa-file-pen', 'missing' => 'fa-file-circle-xmark', 'optional' => 'fa-file'][$row['state']] }}"></i>
                            </span>
                            <span class="brv-doc-text">
                                <strong>{{ $row['label'] }}@unless($row['required'] ?? false) <em>(if requested)</em>@endunless</strong>
                                <small>{{ match ($row['state']) {
                                    'uploaded' => $row['file']->display_name . ' · uploaded ' . $stamp($row['file']->uploaded_at ?? $row['file']->created_at),
                                    'correction' => 'Correction requested' . ($row['file'] ? ' · current file: ' . $row['file']->display_name : ''),
                                    'missing' => 'Not uploaded',
                                    default => 'Not uploaded (optional)',
                                } }}</small>
                            </span>
                            <span class="brv-doc-actions">
                                @if($isPendingReview)
                                    <label class="brv-flag">
                                        <input type="checkbox" form="brvRequirementsForm" name="document_types[]" value="{{ $row['document_type'] }}" @checked($row['request'] || (! $row['file'] && ($row['required'] ?? false)))>
                                        <span>Needs correction</span>
                                    </label>
                                @endif
                                @if($row['file'])
                                    <a href="{{ $previewUrl($row['file']) }}" target="_blank" rel="noopener" class="brv-btn is-small">Preview</a>
                                @endif
                            </span>
                        </li>
                    @endforeach
                    @foreach($otherRegistrationFiles as $document)
                        <li class="brv-doc is-uploaded">
                            <span class="brv-doc-icon" aria-hidden="true"><i class="fas fa-file-lines"></i></span>
                            <span class="brv-doc-text">
                                <strong>{{ $document->document_type }}</strong>
                                <small>{{ $document->display_name }} &middot; uploaded {{ $stamp($document->uploaded_at ?? $document->created_at) }}</small>
                            </span>
                            <span class="brv-doc-actions"><a href="{{ $previewUrl($document) }}" target="_blank" rel="noopener" class="brv-btn is-small">Preview</a></span>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- Other documents from the bidder profile --}}
            <section class="brv-card" aria-labelledby="brvOtherDocsTitle">
                <h3 id="brvOtherDocsTitle">Additional documents</h3>
                @if($supportingDocuments->isEmpty())
                    <p class="brv-empty">No additional documents uploaded. Supporting profile documents appear here when the bidder adds them.</p>
                @else
                    <ul class="brv-docs">
                        @foreach($supportingDocuments as $document)
                            <li class="brv-doc is-uploaded">
                                <span class="brv-doc-icon" aria-hidden="true"><i class="fas fa-file-lines"></i></span>
                                <span class="brv-doc-text"><strong>{{ $document->document_type }}</strong><small>{{ $document->display_name }}</small></span>
                                <span class="brv-doc-actions"><a href="{{ $previewUrl($document) }}" target="_blank" rel="noopener" class="brv-btn is-small">Preview</a></span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Login activity (refreshed every 5 seconds while the modal is open) --}}
            <section class="brv-card" id="recentLoginActivity" data-login-activity-url="{{ route($reviewRoutePrefix . '.users.login-activity', $user) }}" aria-labelledby="brvLoginTitle">
                <h3 id="brvLoginTitle">Recent login activity</h3>
                <div class="brv-table-wrap">
                    <table class="brv-table">
                        <thead>
                            <tr><th>Date &amp; time</th><th>Method</th><th>Status</th><th>IP address</th><th>Device</th></tr>
                        </thead>
                        <tbody id="recentLoginActivityRows">
                            @include('admin.partials.bidder-login-activity-rows', ['loginLogs' => $user->loginLogs])
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="brv-side">
            {{-- Decision --}}
            <section class="brv-card" aria-labelledby="brvDecisionTitle">
                <h3 id="brvDecisionTitle">Registration decision</h3>

                @if($isAccountActive)
                    <div class="brv-alert is-success">
                        <i class="fas fa-circle-check" aria-hidden="true"></i>
                        <span><strong>Bidder approved</strong> {{ $stamp($profile?->approved_at) ?? '' }} by {{ $profile?->approver?->name ?? 'BAC Admin' }}.</span>
                    </div>
                @elseif($activeSanction)
                    <div class="brv-alert is-warning">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                        <span><strong>Approval unavailable.</strong> Lift the active procurement sanction before approving this bidder.</span>
                    </div>
                @endif

                @if($canApproveBidder)
                    <form action="{{ route($reviewRoutePrefix . '.users.approve', $user) }}" method="POST" data-async-review-action="approve" class="brv-step">
                        @csrf
                        @method('PATCH')
                        <p class="brv-note">Approving activates dashboard access and emails a confirmation to the bidder.</p>
                        <button type="submit" class="brv-btn is-primary is-block"><i class="fas fa-circle-check" aria-hidden="true"></i> Approve Bidder</button>
                    </form>
                @endif

                @if($isPendingReview)
                    <form id="brvRequirementsForm" action="{{ route($reviewRoutePrefix . '.users.requirements', $user) }}" method="POST" data-async-review-action="requirements" class="brv-step">
                        @csrf
                        <h4>Request corrections</h4>
                        <p class="brv-note">Includes every document ticked <strong>Needs correction</strong> in the list (missing required files are ticked for you). The bidder is emailed this message and the account is set to <em>Needs action</em>.</p>
                        <label class="brv-label" for="requirementsReason">Message to the bidder</label>
                        <textarea id="requirementsReason" name="reason" rows="3" required placeholder="Explain what the bidder must correct or submit.">@if($profile?->review_status === 'needs_action'){{ $profile->review_message }}@endif</textarea>
                        <button type="submit" class="brv-btn is-block"><i class="fas fa-clipboard-list" aria-hidden="true"></i> Send correction request</button>
                    </form>
                @endif

                @if($user->status !== 'rejected')
                    <form action="{{ route($reviewRoutePrefix . '.users.reject', $user) }}" method="POST" data-async-review-action="reject" class="brv-step">
                        @csrf
                        @method('PATCH')
                        <h4>{{ $isAccountActive ? 'Revoke approval' : 'Reject registration' }}</h4>
                        <label class="brv-label" for="rejectionReason">Reason (sent to the bidder)</label>
                        <textarea id="rejectionReason" name="rejection_reason" rows="3" required placeholder="State the ground for {{ $isAccountActive ? 'revoking' : 'rejecting' }} this registration."></textarea>
                        <button type="submit" class="brv-btn is-danger-outline is-block"><i class="fas fa-circle-xmark" aria-hidden="true"></i> {{ $isAccountActive ? 'Revoke Approval' : 'Reject Bidder' }}</button>
                    </form>
                @endif
            </section>

            {{-- Sanctions --}}
            <section class="brv-card" aria-labelledby="brvSanctionTitle">
                <h3 id="brvSanctionTitle">Procurement Sanctions</h3>

                @if(! $bidderSanctionsAvailable)
                    <p class="brv-empty">Sanctions are unavailable: the bidder sanctions table is not in this database.</p>
                @elseif($activeSanction)
                    <dl class="brv-facts is-compact">
                        <div><dt>Status</dt><dd><span class="brv-pill {{ $sanctionPill[1] }}">{{ $activeSanction->status_label }}</span></dd></div>
                        <div><dt>Order / reference no.</dt><dd class="brv-mono">{{ $activeSanction->reference_number }}</dd></div>
                        <div class="is-wide"><dt>Reason</dt><dd>{{ $activeSanction->reason }}</dd></div>
                        <div><dt>Effective</dt><dd>{{ $activeSanction->effective_date?->format('M d, Y') ?? 'N/A' }}</dd></div>
                        <div><dt>Ends</dt><dd>{{ $activeSanction->end_date?->format('M d, Y') ?? 'Until lifted' }}</dd></div>
                        <div><dt>Authorized by</dt><dd>{{ $activeSanction->authorized_by }}</dd></div>
                        <div><dt>Recorded</dt><dd>{{ $stamp($activeSanction->created_at) ?? 'N/A' }}</dd></div>
                    </dl>
                    <form action="{{ route($reviewRoutePrefix . '.users.sanction.lift', $user) }}" method="POST" class="brv-step">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="brv-btn is-block"><i class="fas fa-unlock" aria-hidden="true"></i> Lift procurement sanction</button>
                    </form>
                @else
                    <p class="brv-note">No active sanction. Record one only on an issued order after due process.</p>
                    <details class="brv-disclosure">
                        <summary>Record a suspension or blacklisting</summary>
                        <form action="{{ route($reviewRoutePrefix . '.users.sanction', $user) }}" method="POST" class="brv-step brv-grid">
                            @csrf
                            @method('PATCH')
                            <label class="brv-label">Status
                                <select name="type" required>
                                    <option value="suspended">Suspended</option>
                                    <option value="blacklisted">Blacklisted</option>
                                </select>
                            </label>
                            <label class="brv-label">Order / reference no.
                                <input type="text" name="reference_number" required>
                            </label>
                            <label class="brv-label is-wide">Reason
                                <textarea name="reason" rows="3" required placeholder="Reason for the suspension or blacklisting."></textarea>
                            </label>
                            <label class="brv-label">Effective date
                                <input type="date" name="effective_date" value="{{ now()->toDateString() }}" required>
                            </label>
                            <label class="brv-label">End date
                                <input type="date" name="end_date">
                            </label>
                            <label class="brv-label is-wide">Authorized / approved by
                                <input type="text" name="authorized_by" required>
                            </label>
                            <button type="submit" class="brv-btn is-danger is-block is-wide"><i class="fas fa-ban" aria-hidden="true"></i> Save procurement sanction</button>
                        </form>
                    </details>
                @endif

                @if($sanctionHistory->isNotEmpty())
                    <h4 class="brv-subhead">Sanction history</h4>
                    <ol class="brv-history">
                        @foreach($sanctionHistory as $sanction)
                            <li>
                                <div class="brv-history-head">
                                    <strong>{{ $sanction->status_label }} · <span class="brv-mono">{{ $sanction->reference_number }}</span></strong>
                                    <span class="brv-pill {{ $sanction->lifted_at ? 'is-success' : ($sanction->type === 'blacklisted' ? 'is-danger' : 'is-warning') }}">{{ $sanction->lifted_at ? 'Lifted' : $sanction->status_label }}</span>
                                </div>
                                <p>{{ $sanction->reason }}</p>
                                <small>
                                    {{ $sanction->effective_date?->format('M d, Y') ?? 'N/A' }} – {{ $sanction->end_date?->format('M d, Y') ?? 'until lifted' }}
                                    · by {{ $sanction->authorized_by }}
                                    @if($sanction->lifted_at) · lifted {{ $stamp($sanction->lifted_at) }} by {{ $sanction->lifter?->name ?? 'N/A' }}@endif
                                </small>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </aside>
    </div>
</div>

@unless($isReviewModal)
            </main>
        </div>
    </div>
@endunless

<style id="bidder-review-ui">
    .brv { display: grid; gap: 16px; min-width: 0; color: var(--ui-ink); font-family: var(--ui-font); font-size: var(--ui-text); }
    .brv :is(h2, h3, h4, p, dl, dd, ul, ol) { margin: 0; }
    .brv :is(ul, ol) { padding: 0; list-style: none; }
    .brv-mono { font-family: var(--ui-mono); font-size: .95em; }

    .brv-alert { display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border: 1px solid; border-radius: var(--ui-radius); font-size: var(--ui-text-sm); line-height: 1.5; }
    .brv-alert i { margin-top: 2px; }
    .brv-alert ul { padding-left: 16px; list-style: disc; }
    .brv-alert.is-success { border-color: var(--ui-success-line); background: var(--ui-success-soft); color: var(--ui-success); }
    .brv-alert.is-warning { border-color: var(--ui-warning-line); background: var(--ui-warning-soft); color: var(--ui-warning); }
    .brv-alert.is-danger { border-color: var(--ui-danger-line); background: var(--ui-danger-soft); color: var(--ui-danger); }

    .brv-head { display: flex; align-items: center; gap: 14px; padding-bottom: 16px; border-bottom: 1px solid var(--ui-line); }
    .brv-avatar { display: grid; flex: 0 0 44px; width: 44px; height: 44px; place-items: center; border-radius: var(--ui-radius-lg); background: var(--ui-primary); color: #fff; font-size: 18px; font-weight: 700; }
    .brv-head-text { flex: 1 1 auto; min-width: 0; }
    .brv-head h2 { color: var(--ui-ink); font-size: 20px; font-weight: 700; line-height: 1.25; overflow-wrap: anywhere; }
    .brv-head p { display: flex; flex-wrap: wrap; gap: 2px 14px; margin-top: 4px; color: var(--ui-muted); font-size: var(--ui-text-sm); }
    .brv-head p i { margin-right: 4px; color: var(--ui-subtle); }
    .brv-pills { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 6px; }

    .brv-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; background: var(--ui-line-soft); color: var(--ui-ink-2); font-size: var(--ui-text-xs); font-weight: 700; white-space: nowrap; }
    .brv-pill.is-success { background: var(--ui-success-soft); color: var(--ui-success); }
    .brv-pill.is-warning { background: var(--ui-warning-soft); color: var(--ui-warning); }
    .brv-pill.is-danger { background: var(--ui-danger-soft); color: var(--ui-danger); }
    .brv-pill.is-info { background: var(--ui-info-soft); color: var(--ui-info); }

    .brv-layout { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 16px; align-items: start; }
    .brv-main, .brv-side { display: grid; gap: 16px; min-width: 0; }
    .brv-side { position: sticky; top: 0; }

    .brv-card { min-width: 0; padding: 16px 18px; border: 1px solid var(--ui-line); border-radius: var(--ui-radius-lg); background: var(--ui-surface); }
    .brv-card > h3, .brv-card-head h3 { color: var(--ui-ink); font-size: var(--ui-text-lg); font-weight: 700; }
    .brv-card > h3 { margin-bottom: 12px; }
    .brv-card-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
    .brv-card h4 { color: var(--ui-ink); font-size: var(--ui-text); font-weight: 700; }
    .brv-subhead { margin-top: 16px !important; margin-bottom: 8px !important; }
    .brv-note { color: var(--ui-muted); font-size: var(--ui-text-sm); line-height: 1.5; }
    .brv-empty { padding: 12px; border: 1px dashed var(--ui-line-strong); border-radius: var(--ui-radius); color: var(--ui-muted); font-size: var(--ui-text-sm); }

    .brv-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); border: 1px solid var(--ui-line); border-radius: var(--ui-radius); overflow: hidden; }
    .brv-facts > div { min-width: 0; padding: 9px 12px; border-bottom: 1px solid var(--ui-line-soft); }
    .brv-facts > div:nth-child(odd):not(.is-wide) { border-right: 1px solid var(--ui-line-soft); }
    .brv-facts > div.is-wide { grid-column: 1 / -1; border-right: 0; }
    .brv-facts > div:last-child, .brv-facts > div:nth-last-child(2):nth-child(odd):not(.is-wide) { border-bottom: 0; }
    .brv-facts > div.is-danger { background: var(--ui-danger-soft); }
    .brv-facts dt { color: var(--ui-subtle); font-size: var(--ui-text-xs); font-weight: 600; }
    .brv-facts dd { margin-top: 2px !important; color: var(--ui-ink); font-size: var(--ui-text); font-weight: 500; overflow-wrap: anywhere; }
    .brv-facts.is-compact { margin-bottom: 12px; }

    .brv-progress { height: 6px; margin-bottom: 8px; border-radius: 999px; background: var(--ui-line-soft); overflow: hidden; }
    .brv-progress span { display: block; height: 100%; border-radius: inherit; background: var(--ui-success); }
    .brv-docs { margin-top: 12px !important; border: 1px solid var(--ui-line); border-radius: var(--ui-radius); overflow: hidden; }
    .brv-note + .brv-docs { margin-top: 12px !important; }
    .brv-card > .brv-docs:first-of-type { margin-top: 0 !important; }
    .brv-doc { display: flex; align-items: center; gap: 12px; padding: 10px 12px; }
    .brv-doc + .brv-doc { border-top: 1px solid var(--ui-line-soft); }
    .brv-doc-icon { display: grid; flex: 0 0 32px; width: 32px; height: 32px; place-items: center; border-radius: var(--ui-radius); background: var(--ui-surface-2); color: var(--ui-subtle); }
    .brv-doc.is-uploaded .brv-doc-icon { background: var(--ui-success-soft); color: var(--ui-success); }
    .brv-doc.is-missing { background: var(--ui-danger-soft); }
    .brv-doc.is-missing .brv-doc-icon { background: var(--ui-surface); color: var(--ui-danger); }
    .brv-doc.is-correction { background: var(--ui-warning-soft); }
    .brv-doc.is-correction .brv-doc-icon { background: var(--ui-surface); color: var(--ui-warning); }
    .brv-doc-text { display: grid; flex: 1 1 auto; gap: 1px; min-width: 0; }
    .brv-doc-text strong { color: var(--ui-ink); font-size: var(--ui-text); font-weight: 600; overflow-wrap: anywhere; }
    .brv-doc-text em { color: var(--ui-subtle); font-size: var(--ui-text-xs); font-style: normal; font-weight: 500; }
    .brv-doc-text small { color: var(--ui-muted); font-size: var(--ui-text-sm); overflow-wrap: anywhere; }
    .brv-doc.is-missing .brv-doc-text small { color: var(--ui-danger); }
    .brv-doc.is-correction .brv-doc-text small { color: var(--ui-warning); }
    .brv-doc-actions { display: flex; flex: 0 0 auto; align-items: center; gap: 10px; }
    .brv-flag { display: inline-flex; align-items: center; gap: 6px; color: var(--ui-ink-2); font-size: var(--ui-text-xs); font-weight: 600; cursor: pointer; white-space: nowrap; }
    .brv-flag input { width: 15px; height: 15px; margin: 0; accent-color: var(--ui-warning); }

    .brv-table-wrap { border: 1px solid var(--ui-line); border-radius: var(--ui-radius); overflow-x: auto; }
    .brv-table { width: 100%; border-collapse: collapse; font-size: var(--ui-text-sm); }
    .brv-table :is(th, td) { padding: 9px 12px; border-bottom: 1px solid var(--ui-line-soft); text-align: left; vertical-align: top; overflow-wrap: anywhere; }
    .brv-table th { background: var(--ui-surface-2); color: var(--ui-muted); font-size: var(--ui-text-xs); font-weight: 600; white-space: nowrap; }
    .brv-table tr:last-child td { border-bottom: 0; }

    .brv-step { display: grid; gap: 8px; margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--ui-line-soft); }
    .brv-card > h3 + .brv-step, .brv-card > .brv-alert + .brv-step, .brv-card > h3 + .brv-alert { margin-top: 0; padding-top: 0; border-top: 0; }
    .brv-card > .brv-alert { margin-bottom: 4px; }
    .brv-label { display: grid; gap: 5px; color: var(--ui-ink-2); font-size: var(--ui-text-sm); font-weight: 600; }
    .brv :is(textarea, select, input[type="text"], input[type="date"]) { width: 100%; min-height: 36px; padding: 7px 10px; border: 1px solid var(--ui-line-strong); border-radius: var(--ui-radius); background: var(--ui-surface); color: var(--ui-ink); font: inherit; font-size: var(--ui-text); font-weight: 400; box-sizing: border-box; }
    .brv textarea { min-height: 72px; resize: vertical; line-height: 1.5; }
    .brv :is(textarea, select, input):focus { border-color: var(--ui-focus-color); outline: none; box-shadow: var(--ui-focus); }
    .brv-grid { grid-template-columns: 1fr 1fr; }
    .brv-grid .is-wide { grid-column: 1 / -1; }
    .brv-disclosure { margin-top: 10px; }
    .brv-disclosure > summary { color: var(--ui-primary); font-size: var(--ui-text-sm); font-weight: 600; cursor: pointer; }
    .brv-disclosure > .brv-step { margin-top: 10px; padding-top: 10px; }

    .brv-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 36px; padding: 0 14px; border: 1px solid var(--ui-line-strong); border-radius: var(--ui-radius); background: var(--ui-surface); color: var(--ui-ink-2); font: inherit; font-size: var(--ui-text-sm); font-weight: 600; line-height: 1; text-decoration: none; white-space: nowrap; cursor: pointer; }
    .brv-btn:hover { border-color: var(--ui-primary-line); background: var(--ui-primary-soft); color: var(--ui-primary); }
    .brv-btn:focus-visible { outline: none; box-shadow: var(--ui-focus); }
    .brv-btn:disabled { opacity: .7; cursor: progress; }
    .brv-btn.is-small { min-height: 30px; padding: 0 10px; font-size: var(--ui-text-xs); }
    .brv-btn.is-block { width: 100%; }
    .brv-btn.is-primary { border-color: var(--ui-primary); background: var(--ui-primary); color: #fff; }
    .brv-btn.is-primary:hover { border-color: var(--ui-primary-hover); background: var(--ui-primary-hover); color: #fff; }
    .brv-btn.is-danger { border-color: var(--ui-danger); background: var(--ui-danger); color: #fff; }
    .brv-btn.is-danger:hover { background: #85281f; color: #fff; }
    .brv-btn.is-danger-outline { border-color: var(--ui-danger-line); color: var(--ui-danger); }
    .brv-btn.is-danger-outline:hover { background: var(--ui-danger-soft); color: var(--ui-danger); }

    .brv-history { display: grid; gap: 8px; }
    .brv-history li { padding: 10px 12px; border: 1px solid var(--ui-line); border-radius: var(--ui-radius); background: var(--ui-surface-2); }
    .brv-history-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
    .brv-history strong { font-size: var(--ui-text-sm); }
    .brv-history p { margin-top: 4px !important; color: var(--ui-ink-2); font-size: var(--ui-text-sm); }
    .brv-history small { display: block; margin-top: 4px; color: var(--ui-muted); font-size: var(--ui-text-xs); }

    @media (max-width: 960px) {
        .brv-layout { grid-template-columns: 1fr; }
        .brv-side { position: static; }
    }
    @media (max-width: 600px) {
        .brv-head { flex-wrap: wrap; }
        .brv-pills { justify-content: flex-start; width: 100%; }
        .brv-facts { grid-template-columns: 1fr; }
        .brv-facts > div { border-right: 0 !important; border-bottom: 1px solid var(--ui-line-soft) !important; }
        .brv-facts > div:last-child { border-bottom: 0 !important; }
        .brv-doc { flex-wrap: wrap; }
        .brv-doc-actions { width: 100%; justify-content: space-between; padding-left: 44px; }
        .brv-grid { grid-template-columns: 1fr; }
    }
</style>
