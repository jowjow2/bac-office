@php
    $projectDocuments = $project->uploadedDocuments();
    $budget = (float) $project->budget;
    // Amounts are sealed until the bid opening, so no variance warning before it.
    $unusualBidCount = $budget > 0 && $project->bidsAreOpened()
        ? $project->bids->filter(function ($bid) use ($budget) {
            return ((((float) $bid->bid_amount - $budget) / $budget) * 100) > 200;
        })->count()
        : 0;
    $assignedStaff = $project->assignments->first()?->staff;
@endphp

<div class="view-project-modal-shell">
    <div class="view-project-modal-header">
        <div>
            <p class="view-project-eyebrow">Project summary</p>
            <h2>View Project</h2>
        </div>
    </div>

    <div class="view-project-modal-body">
        @if($unusualBidCount > 0)
            <div class="view-project-anomaly-banner" role="alert">
                <span class="view-project-anomaly-icon" aria-hidden="true">
                    <i class="fas fa-triangle-exclamation"></i>
                </span>
                <div class="view-project-anomaly-copy">
                    <strong>
                        {{ $unusualBidCount }} bid{{ $unusualBidCount === 1 ? '' : 's' }} {{ $unusualBidCount === 1 ? 'has' : 'have' }} unusually high variance from budget.
                    </strong>
                    <span>Review before closing this project.</span>
                    <a href="{{ route('admin.bids', ['project' => $project->id]) }}">
                        Review project bids
                        <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
        @endif

        <x-project-summary-section title="Project Overview" class="view-project-overview">
            <x-project-field-row label="Project Title" class="view-project-field--primary view-project-overview-title">
                {{ $project->title }}
            </x-project-field-row>

            <x-project-field-row label="Status" class="view-project-overview-status">
                <span class="view-project-status-pill {{ $project->status }}">
                    {{ \Illuminate\Support\Str::headline($project->status) }}
                </span>
            </x-project-field-row>

            <x-project-field-row label="Budget (PHP)" class="view-project-overview-budget">
                P{{ number_format((float) $project->budget, 2) }}
            </x-project-field-row>

            <x-project-field-row label="Total Bids" class="view-project-overview-bids">
                <span class="view-project-bid-count">{{ $project->bids_count }}</span>
                @if($unusualBidCount > 0)
                    <a href="{{ route('admin.bids', ['project' => $project->id]) }}" class="view-project-inline-warning" title="Review unusual bid variance" aria-label="Review unusual bid variance">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    </a>
                @endif
            </x-project-field-row>
        </x-project-summary-section>

        <x-project-summary-section title="Project Details" class="view-project-details">
            <x-project-field-row label="Description" :block="true">
                {{ $project->description ?: 'N/A' }}
            </x-project-field-row>

            <div class="view-project-field view-project-field--block">
                <div class="view-project-field-label">Project Files</div>
                <div class="view-project-file-note">
                    Click any file below to open its PDF preview.
                </div>
                <div class="view-project-file-list">
                    @if($projectDocuments->isNotEmpty())
                        @foreach($projectDocuments as $documentIndex => $document)
                            <x-file-attachment-item :project="$project" :document="$document" :index="$documentIndex" />
                        @endforeach
                    @else
                        <div class="view-project-empty-files">No files uploaded</div>
                    @endif
                </div>
            </div>
        </x-project-summary-section>

        <x-project-summary-section title="Schedule & Staffing" class="view-project-schedule">
            <x-project-field-row label="Deadline">
                {{ $project->deadline ? $project->deadline->format('m/d/Y') : 'N/A' }}
            </x-project-field-row>

            <x-project-field-row label="Assign Staff">
                <div class="view-project-staff-summary">
                    <span class="view-project-staff-value" data-view-project-staff-value>
                        {{ $assignedStaff?->name ?? 'Unassigned' }}
                    </span>
                    @if(!$assignedStaff)
                        <button type="button"
                                class="view-project-assign-trigger"
                                data-view-project-assign-toggle
                                aria-expanded="false">
                            <i class="fas fa-user-plus" aria-hidden="true"></i>
                            Assign
                        </button>
                    @endif
                </div>
            </x-project-field-row>

            @if(!$assignedStaff)
                <div class="view-project-assign-panel" data-view-project-assign-panel hidden>
                    <form action="{{ route('admin.assignments.store') }}" method="POST" data-view-project-assign-form>
                        @csrf
                        <input type="hidden" name="project_id" value="{{ $project->id }}">
                        <label for="view-project-staff-picker-{{ $project->id }}">Choose staff member</label>
                        <div class="view-project-assign-controls">
                            <select id="view-project-staff-picker-{{ $project->id }}" name="staff_id" required>
                                <option value="">Select staff</option>
                                @foreach(($staffMembers ?? collect()) as $staff)
                                    <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="view-project-assign-save">Save</button>
                        </div>
                        <button type="button" class="view-project-assign-cancel" data-view-project-assign-cancel>
                            Cancel
                        </button>
                        <p class="view-project-assign-error" data-view-project-assign-error></p>
                    </form>
                </div>
            @endif
        </x-project-summary-section>

        @php
            $unverifiedDocuments = $project->unverifiedDocuments();
        @endphp
        @if($unverifiedDocuments->isNotEmpty())
            <div class="view-project-anomaly-banner" role="alert">
                <span class="view-project-anomaly-icon" aria-hidden="true"><i class="fas fa-triangle-exclamation"></i></span>
                <div class="view-project-anomaly-copy">
                    <strong>{{ $unverifiedDocuments->count() }} linked {{ \Illuminate\Support\Str::plural('file', $unverifiedDocuments->count()) }} could not be verified as this project's bidding documents.</strong>
                    <span>{{ $unverifiedDocuments->pluck('display_name')->implode(', ') }} &mdash; stored among bidder uploads or named for another project. Hidden from bidders and the public; remove or re-upload the correct document.</span>
                </div>
            </div>
        @endif

        @php
            $feePaymentsCount = $project->biddingFeePayments()->count();
            $submissionDeadline = $project->bidSubmissionDeadline();
            $feeLocked = $feePaymentsCount > 0 || ($submissionDeadline !== null && $submissionDeadline->isPast());
        @endphp
        <style>
            .vp-online { display: grid; gap: 14px; }
            .vp-online-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
            .vp-online-stat { padding: 10px 12px; border: 1px solid var(--ui-line); border-radius: var(--ui-radius-lg); background: var(--ui-surface-2); }
            .vp-online-stat span { display: block; color: var(--ui-muted); font-size: 11.5px; font-weight: 600; text-transform: none; letter-spacing: normal; }
            .vp-online-stat strong { display: block; margin-top: 3px; color: var(--ui-ink); font-size: 14px; font-variant-numeric: tabular-nums; }
            .vp-online-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 14px; }
            .vp-online-field { display: grid; gap: 5px; min-width: 0; }
            .vp-online-field.is-wide { grid-column: 1 / -1; }
            .vp-online-field :is(input:not([type="checkbox"]), select, textarea) { width: 100%; min-height: 40px; border: 1px solid var(--ui-line-strong); border-radius: 8px; padding: 8px 10px; font: inherit; background: #fff; }
            .vp-online-field textarea { min-height: 72px; resize: vertical; }
            .vp-online-field :is(input, select, textarea):focus { outline: none; border-color: var(--ui-primary); box-shadow: 0 0 0 3px rgba(29, 79, 64, .15); }
            .vp-online-field :is(input, select):disabled { background: var(--ui-line-soft); color: var(--ui-muted); }
            .vp-fee-calc { margin: 0; color: var(--ui-ink); font-size: 13px; font-weight: 600; line-height: 1.45; }
            .vp-online-check { display: flex; gap: 8px; align-items: center; font-size: 13px; color: var(--ui-ink-2); }
            .vp-online-field small { color: var(--ui-muted); font-size: 12px; line-height: 1.4; }
            .vp-online-actions { grid-column: 1 / -1; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
            @media (max-width: 640px) { .vp-online-summary, .vp-online-form { grid-template-columns: minmax(0, 1fr); } }
        </style>
        <x-project-summary-section title="Notice, Submission & Bidding Fee" class="view-project-submission">
            <div class="vp-online">
                <div class="vp-online-summary">
                    <div class="vp-online-stat">
                        <span>Submission</span>
                        <strong>{{ $project->acceptsElectronicSubmission() ? 'Online' : 'Manual (sealed)' }}</strong>
                    </div>
                    <div class="vp-online-stat">
                        <span>Bidding fee</span>
                        <strong>{{ $project->requiresBiddingFee() ? '₱' . number_format((float) $project->bidding_documents_fee, 2) : ($project->biddingFeeWaived() ? 'Waived' : 'Free') }}</strong>
                    </div>
                    <div class="vp-online-stat">
                        <span>Payments recorded</span>
                        <strong>
                            @if($project->requiresBiddingFee())
                                <a href="{{ route('admin.payments', ['project' => $project->id]) }}">{{ $feePaymentsCount }}</a>
                            @else
                                &mdash;
                            @endif
                        </strong>
                    </div>
                </div>

                <form action="{{ route('admin.project.submission-settings', $project) }}" method="POST" class="vp-online-form">
                    @csrf
                    <div class="vp-online-field">
                        <label class="view-project-field-label" for="submission-mode-{{ $project->id }}">Submission method in the notice</label>
                        <select id="submission-mode-{{ $project->id }}" name="submission_mode" @disabled($submissionDeadline !== null && $submissionDeadline->isPast())>
                            <option value="electronic" @selected($project->acceptsElectronicSubmission())>Online, through this system</option>
                            <option value="manual" @selected(! $project->acceptsElectronicSubmission())>Manual, sealed bids at the BAC Secretariat</option>
                        </select>
                        @if($submissionDeadline !== null && $submissionDeadline->isPast())
                            <input type="hidden" name="submission_mode" value="{{ $project->submission_mode ?: 'electronic' }}">
                            <small>Locked after the submission deadline.</small>
                        @endif
                    </div>
                    <div class="vp-online-field">
                        <label class="view-project-field-label" for="submission-venue-{{ $project->id }}">Where sealed bids are submitted (manual)</label>
                        <input id="submission-venue-{{ $project->id }}" name="submission_venue" value="{{ $project->submission_venue }}" maxlength="255"
                               placeholder="e.g. BAC Secretariat, 2F Municipal Hall">
                    </div>
                    <div class="vp-online-field">
                        <label class="view-project-field-label" for="legal-basis-{{ $project->id }}">Legal basis</label>
                        <select id="legal-basis-{{ $project->id }}" name="legal_basis">
                            @unless($project->legal_basis)<option value="">Not specified</option>@endunless
                            @foreach(\App\Models\Project::LEGAL_BASES as $key => $label)
                                <option value="{{ $key }}" @selected($project->legal_basis === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="vp-online-field">
                        <label class="view-project-field-label" for="philgeps-url-{{ $project->id }}">PhilGEPS notice link</label>
                        <input id="philgeps-url-{{ $project->id }}" type="url" name="philgeps_url" value="{{ $project->philgeps_url }}" maxlength="500" placeholder="https://notices.philgeps.gov.ph/...">
                    </div>
                    <div class="vp-online-field is-wide">
                        <input type="hidden" name="bid_security_required" value="0">
                        <label class="vp-online-check"><input type="checkbox" name="bid_security_required" value="1" @checked($project->bid_security_required)> Bid security is required (separate from the bidding documents fee)</label>
                        <textarea name="bid_security_notes" maxlength="2000" aria-label="Bid security requirement" placeholder="e.g. In any acceptable form and in the amount stated in ITB Clause 16 of the bidding documents">{{ $project->bid_security_notes }}</textarea>
                    </div>
                    @php $feeInfo = \App\Support\BiddingDocumentsFee::describe($project); @endphp
                    <div class="vp-online-field">
                        <span class="view-project-field-label">Bidding documents fee</span>
                        <p class="vp-fee-calc">{{ $feeInfo['text'] }}</p>
                        @if($feeInfo['reason'])<small>Reason: {{ $feeInfo['reason'] }}</small>@endif
                        @if($feeLocked)
                            <small>Locked: {{ $feePaymentsCount > 0 ? 'payments were already recorded.' : 'the submission deadline has passed.' }}</small>
                        @else
                            <small>Change it with Edit project: a lower fee or a waiver needs a reason{{ $project->isPublishedLocally() ? ', and a published fee changes only through a recorded amendment' : '' }}.</small>
                        @endif
                    </div>
                    <div class="vp-online-field">
                        <label class="view-project-field-label" for="payment-venue-{{ $project->id }}">Where to pay</label>
                        <input id="payment-venue-{{ $project->id }}" name="payment_venue" value="{{ $project->payment_venue }}" maxlength="255"
                               placeholder="e.g. BAC Secretariat, 2F Municipal Hall, San Jose, Occidental Mindoro">
                        <small>Shown to bidders with the fee.</small>
                    </div>
                    <div class="vp-online-field">
                        <label class="view-project-field-label" for="submission-authority-{{ $project->id }}">LGU authority reference (optional)</label>
                        <input id="submission-authority-{{ $project->id }}" name="electronic_submission_authority" value="{{ $project->electronic_submission_authority }}" maxlength="255"
                               placeholder="e.g. BAC Resolution No. 2026-014">
                    </div>
                    <div class="vp-online-field">
                        <label class="view-project-field-label" for="philgeps-ref-{{ $project->id }}">PhilGEPS reference number</label>
                        <input id="philgeps-ref-{{ $project->id }}" name="philgeps_reference_no" value="{{ $project->philgeps_reference_no }}" maxlength="100">
                    </div>
                    <div class="vp-online-actions">
                        @if($project->requiresBiddingFee())
                            <a href="{{ route('admin.payments', ['project' => $project->id]) }}" class="btn-secondary"><i class="fas fa-receipt" aria-hidden="true"></i> View payments</a>
                        @else
                            <span></span>
                        @endif
                        <button type="submit" class="btn-primary">Save settings</button>
                    </div>
                </form>
            </div>
        </x-project-summary-section>

        @if(! $project->acceptsElectronicSubmission() && in_array($project->status, ['open', 'closed'], true) && ! $project->isFailedBidding() && $project->archived_at === null)
            {{-- Sealed paper bids: received at the BAC Secretariat, recorded here (AdminController::receiveSealedBid). --}}
            @php $tz = config('bac-office.display_timezone', 'Asia/Manila'); @endphp
            <x-project-summary-section title="Sealed Bids Received" class="view-project-sealed">
                @if(($sealedBids ?? collect())->isEmpty())
                    <p style="margin:0; color:#6b736e;">No sealed bid recorded yet.</p>
                @else
                    <ul style="display:grid; gap:6px; margin:0; padding:0; list-style:none;">
                        @foreach($sealedBids as $sealed)
                            <li style="display:flex; justify-content:space-between; gap:10px; padding:8px 10px; border:1px solid #e5dfd2; border-radius:8px;">
                                <span><strong>{{ $sealed->user?->company ?: $sealed->user?->name }}</strong> · {{ $sealed->receipt_no }}</span>
                                <span style="color:#6b736e;">{{ $sealed->submitted_at?->timezone($tz)->format('M d, Y h:i A') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if($project->bidsAreOpened())
                    <p style="margin:10px 0 0; color:#6b736e;">The bids were opened; no more sealed bids can be recorded.</p>
                @elseif(($sealedBidders ?? collect())->isEmpty())
                    <p style="margin:10px 0 0; color:#6b736e;">Every approved bidder already has a sealed bid recorded, or none is approved yet.</p>
                @else
                    <form action="{{ route('admin.project.sealed-bids.store', $project) }}" method="POST" style="display:grid; gap:10px; margin-top:12px;">
                        @csrf
                        <label class="view-project-field-label" for="sealed-bidder-{{ $project->id }}">Record a sealed bid handed in</label>
                        <select id="sealed-bidder-{{ $project->id }}" name="bidder_id" required style="border:1px solid #d2cbbb; border-radius:8px; padding:8px 10px; font:inherit;">
                            <option value="">Choose the bidder</option>
                            @foreach($sealedBidders as $option)
                                <option value="{{ $option['id'] }}" @disabled(! $option['fee_paid'])>{{ $option['label'] }}{{ $option['fee_paid'] ? '' : ' (bidding fee not recorded)' }}</option>
                            @endforeach
                        </select>
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px;">
                            <input type="text" name="receipt_no" required maxlength="100" placeholder="Logbook / receipt no., e.g. LOG-2026-031"
                                   style="border:1px solid #d2cbbb; border-radius:8px; padding:8px 10px; font:inherit;">
                            <input type="datetime-local" name="received_at" required value="{{ now($tz)->format('Y-m-d\TH:i') }}"
                                   style="border:1px solid #d2cbbb; border-radius:8px; padding:8px 10px; font:inherit;">
                        </div>
                        <p style="margin:0; color:#6b736e; font-size:12.5px;">Only for a sealed envelope received on or before the deadline. The price stays sealed until the financial opening, where you enter the amount read out.</p>
                        <div><button type="submit" class="btn-primary">Record sealed bid</button></div>
                    </form>
                @endif
            </x-project-summary-section>
        @endif

        @if($project->isFailedBidding() || in_array($project->status, ['open', 'closed'], true))
            <x-project-summary-section title="Bidding Outcome" class="view-project-outcome">
                @if($project->isFailedBidding())
                    <x-project-field-row label="Failed Bidding">
                        Declared {{ $project->failed_bidding_at->timezone(config('bac-office.display_timezone'))->format('M d, Y h:i A') }}
                    </x-project-field-row>
                    <x-project-field-row label="Ground" :block="true">
                        {{ $project->failed_bidding_reason }}
                    </x-project-field-row>
                @endif

                <form action="{{ route('admin.project.failed-bidding', $project) }}" method="POST" class="view-project-failed-form" style="display:grid; gap:10px; margin-top:8px;">
                    @csrf
                    @unless($project->isFailedBidding())
                        <label for="failed-bidding-reason-{{ $project->id }}" class="view-project-field-label">Declare failure of bidding</label>
                        <textarea id="failed-bidding-reason-{{ $project->id }}" name="failed_bidding_reason" rows="3" required minlength="5"
                                  placeholder="Ground shown to all bidders, e.g. no bids received, all bids post-disqualified, or bids exceeded the ABC."
                                  style="width:100%; border:1px solid #d2cbbb; border-radius:8px; padding:8px 10px; font:inherit;"></textarea>
                    @else
                        <input type="hidden" name="failed_bidding_reason" value="{{ $project->failed_bidding_reason }}">
                    @endunless

                    <label for="rebid-project-{{ $project->id }}" class="view-project-field-label">New bidding round (optional)</label>
                    <select id="rebid-project-{{ $project->id }}" name="rebid_project_id" style="border:1px solid #d2cbbb; border-radius:8px; padding:8px 10px; font:inherit;">
                        <option value="">Not posted yet</option>
                        @foreach(($rebidCandidates ?? collect()) as $candidate)
                            <option value="{{ $candidate->id }}" @selected($project->rebid_project_id === $candidate->id)>
                                {{ $candidate->title }}{{ $candidate->reference_no ? ' (' . $candidate->reference_no . ')' : '' }}
                            </option>
                        @endforeach
                    </select>

                    <div>
                        <button type="submit" class="btn-secondary">
                            {{ $project->isFailedBidding() ? 'Update new bidding round link' : 'Record failed bidding' }}
                        </button>
                    </div>
                </form>
            </x-project-summary-section>
        @endif

    </div>

    <div class="view-project-actions">
        <button type="button" onclick="closeViewModal()" class="btn-secondary">Close</button>
        <a href="{{ route('admin.procurement.show', $project) }}" class="btn-secondary">Procurement record</a>
        <button type="button" onclick="closeViewModal(); loadEditModal({{ $project->id }})" class="btn-primary">Edit Project</button>
    </div>
</div>
