{{--
    Submit / modify bid modal as one complete form with one final submission. Technical and sealed financial components remain separate and are validated server-side.
--}}
@php
    $requirements = \App\Support\BidSubmissionRequirements::for($project);
    $tz = config('bac-office.display_timezone');
    $deadlineLocal = $deadline?->copy()->timezone($tz);
    $openingAt = $project->schedule?->bid_opening_date?->copy()->timezone($tz);
    $draftFiles = ($isDraftBid || $isModifying) ? $myBid->documents->keyBy('requirement_key') : collect();
    $prefillBid = $isDraftBid || $isModifying;
    $formId = 'bid-form-'.$project->id;
    $pid = $project->id;
    $hasErrors = $errors->any() && old('project_id') == $pid;
    $abc = (float) $project->budget;
    $components = [
        'technical' => ['step' => 1, 'title' => 'Technical Component (including Eligibility Documents)', 'hint' => 'Eligibility documents and technical forms. Opened at the bid opening.', 'items' => $requirements->technical()],
        'financial' => ['step' => 2, 'title' => 'Financial Component', 'hint' => 'Your bid price and financial forms. Opened only for bids that pass the preliminary examination.', 'items' => $requirements->financial()],
    ];
    $title = $isModifying ? 'Modify Your Bid' : ($electronic ? 'Submit Bid Proposal' : 'Prepare Bid (Draft Record)');
    $submitLabel = $isModifying ? 'Submit Modification' : ($electronic ? 'Submit Official Bid' : 'Save Draft Record');
    $daysLeftLabel = null;
    if ($deadlineLocal && $deadlineLocal->isFuture()) {
        $days = (int) now($tz)->startOfDay()->diffInDays($deadlineLocal->copy()->startOfDay(), false);
        $daysLeftLabel = $days <= 0 ? 'closes today' : $days.' '.\Illuminate\Support\Str::plural('day', $days).' left';
    }
@endphp

<div class="bidder-modal-overlay bidder-submit-modal-overlay" id="bid-modal-{{ $pid }}" hidden aria-hidden="true">
    <div class="sb" role="dialog" aria-modal="true" aria-labelledby="bid-modal-title-{{ $pid }}" tabindex="-1" data-bid-dialog @if($paymentLocked) data-sb-locked @endif>
        <header class="sb-head">
            <div class="sb-head-main">
                <p class="sb-eyebrow">
                    <span>{{ $requirements->modeLabel() }}</span>
                    <span>{{ $requirements->categoryLabel() }}</span>
                    @if($project->reference_no)<code>{{ $project->reference_no }}</code>@endif
                </p>
                <h2 class="sb-title" id="bid-modal-title-{{ $pid }}">{{ $title }}</h2>
                <p class="sb-project">{{ $project->title }}</p>
            </div>
            <button type="button" class="sb-close" data-close-modal="bid-modal-{{ $pid }}" aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </header>

        <div class="sb-body" data-scroll-body>
            {{-- One line with what the bidder must keep in mind; the project notice below holds the rest. --}}
            <ul class="sb-strip" aria-label="Key details">
                <li><span>ABC</span><strong>&#8369;{{ number_format($abc, 2) }}</strong></li>
                <li class="{{ $daysLeftLabel && str_contains($daysLeftLabel, 'today') ? 'is-warn' : '' }}"><span>Deadline</span><strong>{{ $deadlineLocal ? $deadlineLocal->format('M d, g:i A') : 'Not set' }}</strong>@if($daysLeftLabel)<small>{{ $daysLeftLabel }}</small>@endif</li>
                <li><span>Opening</span><strong>{{ $openingAt ? $openingAt->format('M d, g:i A') : 'To be announced' }}</strong></li>
                <li class="{{ $requiresFee ? ($feePayment ? 'is-ok' : 'is-warn') : '' }}"><span>Fee</span>
                    @if($requiresFee)
                        @if($feePayment)<strong><i class="fas fa-circle-check" aria-hidden="true"></i> Paid</strong><small>OR {{ $feePayment->or_number }}</small>@else<strong>{{ $feeLabel }}</strong><small>pay at the BAC office</small>@endif
                    @else
                        <strong>{{ $project->biddingFeeWaived() ? 'Waived' : 'None' }}</strong>
                    @endif
                </li>
            </ul>
            <details class="sb-context" data-sb-notice>
                <summary>
                    <span class="sb-context-title"><i class="fas fa-circle-info" aria-hidden="true"></i> Project notice &amp; bidding documents</span>
                </summary>
                <div class="sb-context-content">
                    <div class="sb-context-card">
                        <h3>Project notice</h3>
                        <dl class="sb-notice">
                            <div><dt>BAC publication</dt><dd>{{ $project->published_at ? $project->published_at->timezone($tz)->format('M d, Y h:i A') : 'Published in this system' }}</dd></div>
                            <div><dt>PhilGEPS Reference No.</dt><dd>{{ $project->philgeps_reference_no ?: 'Not recorded' }}</dd></div>
                            <div><dt>Solicitation No.</dt><dd>{{ $project->reference_no ?: 'Not provided' }}</dd></div>
                            <div><dt>Procurement Category</dt><dd>{{ $requirements->categoryLabel() }}</dd></div>
                            <div><dt>Mode of Procurement</dt><dd>{{ $requirements->modeLabel() }}</dd></div>
                            <div><dt>Approved Budget for the Contract</dt><dd><strong>&#8369;{{ number_format($abc, 2) }}</strong></dd></div>
                            <div><dt>Submission Deadline</dt><dd>{{ $deadlineLocal ? $deadlineLocal->format('M d, Y h:i A') : 'Not set' }}</dd></div>
                            <div><dt>Bid Opening</dt><dd>{{ $openingAt ? $openingAt->format('M d, Y h:i A') : 'To be announced' }}</dd></div>
                            <div><dt>Submission Method</dt><dd>{{ $electronic ? 'Online' : 'Manual (sealed bid)' }}</dd></div>
                            <div><dt>Bidding Documents Fee</dt><dd>{{ $requiresFee ? $feeLabel.', paid at the BAC' : ($project->biddingFeeWaived() ? 'Waived by the BAC' : 'Free') }}</dd></div>
                            <div><dt>Bid Security</dt><dd>{{ $project->bid_security_required ? 'Required, submitted with the bid' : 'Not required' }}</dd></div>
                        </dl>
                    </div>
                    <div class="sb-context-files">
                        @include('bidder.partials.project-documents', ['project' => $project, 'compact' => true])
                        @include('bidder.partials.project-requirements', ['project' => $project, 'showDocumentChips' => false])
                    </div>
                </div>
            </details>
            <div class="sb-main">
                <form method="POST" action="{{ route('bidder.bids.store', $project) }}" enctype="multipart/form-data" class="sb-form" id="{{ $formId }}" novalidate data-bid-form data-abc="{{ number_format($abc, 2, '.', '') }}" data-payment-locked="{{ $paymentLocked ? 'true' : 'false' }}" data-project-id="{{ $pid }}" data-owner="{{ auth()->id() }}" @if($electronic && \App\Support\VercelBlob::enabled()) data-direct-upload-url="{{ route('bidder.bids.upload-token', $project) }}" data-direct-upload-folder="bid-uploads/{{ auth()->id() }}/{{ $pid }}" @endif data-electronic="{{ $electronic ? 'true' : 'false' }}">
                    @csrf
                    <input type="hidden" name="project_id" value="{{ $pid }}">

                    @if($hasErrors)
                        <div class="sb-alert is-danger" role="alert" data-sb-errors>
                            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                            <div>
                                <strong>Your bid was not saved.</strong>
                                <ul>
                                    @foreach(collect($errors->all())->unique() as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    @if($paymentLocked)
                        @unless($electronic)
                            {{-- Said up front, even before payment: the sealed bid is the official one. --}}
                            <div class="sb-alert is-warning" role="note">
                                <i class="fas fa-envelope" aria-hidden="true"></i>
                                <div>
                                    <strong>Manual submission &mdash; this website upload is NOT an official bid</strong>
                                    <p>Submit your sealed bid envelopes to the BAC Secretariat{{ $project->submission_venue ? ', '.$project->submission_venue.',' : '' }} on or before {{ $deadlineLocal ? $deadlineLocal->format('M d, Y h:i A') : 'the deadline' }}. The BAC records receipt of a sealed bid only after your Official Receipt is recorded.</p>
                                </div>
                            </div>
                        @endunless
                        <ol class="sb-flow" aria-label="How to submit">
                            <li class="is-current"><span>1</span><strong>Pay at the BAC office</strong><small>Bring your company name and the reference no.</small></li>
                            <li><span>2</span><strong>The BAC records your receipt</strong><small>You are notified right away</small></li>
                            <li><span>3</span><strong>Submit your bid here</strong><small>Technical and financial files</small></li>
                        </ol>
                        <section class="sb-pay" aria-labelledby="bsm-pay-title-{{ $pid }}">
                            <div class="sb-pay-box">
                                <span>Amount to pay</span>
                                <strong>{{ $feeLabel }}</strong>
                                <small>Bidding documents fee, non-refundable</small>
                            </div>
                            <div class="sb-pay-main">
                                <h3 id="bsm-pay-title-{{ $pid }}">Pay the bidding documents fee first</h3>
                                <p>Pay in person at the BAC office. You can submit your bid here once the BAC records your Official Receipt.</p>
                                <dl class="sb-pay-details">
                                    <div><dt>Where to pay</dt><dd>{{ $project->paymentVenueLabel() }}</dd></div>
                                    <div><dt>Pay before</dt><dd>{{ $deadlineLocal ? $deadlineLocal->format('M d, Y h:i A') : 'the submission deadline' }}, during office hours (Mon&ndash;Fri, 8:00 AM&ndash;5:00 PM)</dd></div>
                                    <div><dt>Bring</dt><dd>Your company name and this project's reference no. ({{ $project->reference_no ?: 'shown above' }})</dd></div>
                                </dl>
                                <p class="sb-muted"><i class="fas fa-bell" aria-hidden="true"></i> You will get a notification once your payment is recorded. Leave enough time: late bids are refused.</p>
                            </div>
                        </section>

                        <section class="sb-panel">
                            <h3 class="sb-panel-title">What you will submit</h3>
                            <p class="sb-panel-hint">Prepare these files while you wait for the BAC to record your payment.</p>
                            <div class="sb-comp-grid">
                            @foreach($components as $component)
                                <div class="sb-comp">
                                <h4 class="sb-group-title"><span>{{ $component['step'] }}</span>{{ $component['title'] }}<small>{{ count($component['items']) }} {{ \Illuminate\Support\Str::plural('file', count($component['items'])) }}</small></h4>
                                <p class="sb-comp-hint">{{ $component['hint'] }}</p>
                                <ul class="sb-checklist">
                                    @foreach($component['items'] as $item)
                                        <li><i class="fas fa-file-lines" aria-hidden="true"></i> {{ $item['label'] }} <span class="sb-tag {{ $item['required'] ? 'is-required' : '' }}">{{ $item['required'] ? 'Required' : 'If applicable' }}</span></li>
                                    @endforeach
                                </ul>
                                </div>
                            @endforeach
                            </div>
                        </section>
                    @else
                        {{-- Step navigation: one final submission contains both sealed components. --}}
                        <nav class="sb-steps" role="tablist" aria-label="Bid submission steps">
                            <button type="button" class="sb-step is-current" role="tab" aria-selected="true" aria-controls="bsm-step-technical-{{ $pid }}" data-sb-go="1">
                                <span class="sb-step__number">1</span><span><strong>Technical documents</strong><small>Eligibility and technical forms</small></span>
                            </button>
                            <button type="button" class="sb-step" role="tab" aria-selected="false" aria-controls="bsm-step-financial-{{ $pid }}" data-sb-go="2" tabindex="-1">
                                <span class="sb-step__number">2</span><span><strong>Financial offer</strong><small>Bid price and sealed files</small></span>
                            </button>
                            <button type="button" class="sb-step" role="tab" aria-selected="false" aria-controls="bsm-step-review-{{ $pid }}" data-sb-go="3" tabindex="-1">
                                <span class="sb-step__number">3</span><span><strong>Review &amp; submit</strong><small>Check before final submission</small></span>
                            </button>
                        </nav>
                        {{-- Step 1: technical documents --}}
                        <section class="sb-panel" id="bsm-step-technical-{{ $pid }}" role="tabpanel" data-sb-panel="1" aria-labelledby="bsm-technical-{{ $pid }}">
                            @if($isModifying)
                                <div class="sb-alert is-warning" role="note">
                                    <i class="fas fa-pen-to-square" aria-hidden="true"></i>
                                    <div>
                                        <strong>Modifying Receipt No. {{ $myBid->receipt_no }} ({{ $myBid->submitted_at->timezone($tz)->format('M d, Y h:i A') }})</strong>
                                        <p>You can modify your bid until {{ $deadlineLocal ? $deadlineLocal->format('M d, Y h:i A') : 'the deadline' }}. Upload only the files you are replacing; the others stay as filed. Your modification gets a new receipt number, and its time becomes your official time of submission. Your earlier submission stays on the BAC record and cannot be withdrawn. Set the financial PIN again.</p>
                                    </div>
                                </div>
                            @elseif(! $electronic)
                                <div class="sb-alert is-warning" role="note">
                                    <i class="fas fa-envelope" aria-hidden="true"></i>
                                    <div>
                                        <strong>Manual submission &mdash; this website upload is NOT an official bid</strong>
                                        <p>Per the project notice, submit your sealed bid envelopes to the BAC Secretariat{{ $project->submission_venue ? ', '.$project->submission_venue.',' : '' }} on or before {{ $deadlineLocal ? $deadlineLocal->format('M d, Y h:i A') : 'the deadline' }}. Anything saved here is a draft / supplementary record only. Your bid counts as submitted only when the BAC Secretariat records receipt.</p>
                                    </div>
                                </div>
                            @endif

                            @if($requiresFee && ! $feePayment)
                                <div class="sb-alert is-warning" role="note">
                                    <i class="fas fa-receipt" aria-hidden="true"></i>
                                    <div>
                                        <strong>Pay the bidding documents fee of {{ $feeLabel }} at the {{ $project->paymentVenueLabel() }}</strong>
                                        <p>The BAC Secretariat records receipt of your sealed bid only after your Official Receipt is recorded.</p>
                                    </div>
                                </div>
                            @endif

                            <div class="sb-panel-head">
                                <div>
                                    <h3 class="sb-panel-title" id="bsm-technical-{{ $pid }}">{{ $components['technical']['title'] }}</h3>
                                    <p class="sb-panel-hint">{{ $components['technical']['hint'] }}{{ $isModifying ? ' Files already filed are kept unless you choose a replacement.' : '' }}</p>
                                </div>
                                <span class="sb-count" data-sb-count="1"></span>
                            </div>
                            @php $combinedOffered = $electronic && $components['technical']['items']->where('required', true)->count() > 1; @endphp
                            @if($combinedOffered)
                                @include('bidder.partials.submit-bid-combined', ['items' => $components['technical']['items']])
                            @endif
                            <div data-sb-separate @if($combinedOffered && ! ($isModifying && $draftFiles->isNotEmpty())) hidden @endif>
                                @include('bidder.partials.submit-bid-files', ['items' => $components['technical']['items'], 'component' => 'technical'])
                            </div>
                        </section>

                        {{-- Financial component stays separate and sealed, but is submitted with the technical component. --}}
                        <section class="sb-panel" id="bsm-step-financial-{{ $pid }}" role="tabpanel" data-sb-panel="2" aria-labelledby="bsm-financial-{{ $pid }}" hidden>
                            <div class="sb-panel-head">
                                <div>
                                    <h3 class="sb-panel-title" id="bsm-financial-{{ $pid }}">{{ $components['financial']['title'] }}</h3>
                                    <p class="sb-panel-hint">{{ $components['financial']['hint'] }}</p>
                                </div>
                                <span class="sb-count" data-sb-count="2"></span>
                            </div>
                            <p class="sb-seal-note"><i class="fas fa-shield-halved" aria-hidden="true"></i> Submit both components now. Technical opens at the scheduled bid opening; the financial offer stays sealed until the BAC approves the technical review.</p>

                            {{-- The financial offer: the bid price and the PIN that seals the financial component, side by side. --}}
                            <div class="sb-offer2 {{ $electronic ? '' : 'is-single' }}">
                                <section class="sb-card" data-bid-field aria-labelledby="bid-amount-label-{{ $pid }}">
                                    <header class="sb-card-head">
                                        <span class="sb-card-icon" aria-hidden="true">&#8369;</span>
                                        <div>
                                            <label class="sb-card-title" id="bid-amount-label-{{ $pid }}" for="bid-amount-{{ $pid }}">Bid price <span class="sb-req" aria-hidden="true">*</span></label>
                                            <p class="sb-card-hint">Exactly as written on your Financial Bid Form.</p>
                                        </div>
                                    </header>
                                    <div class="sb-money">
                                        <span class="sb-money-prefix" aria-hidden="true">&#8369;</span>
                                        <input type="text" inputmode="decimal" autocomplete="transaction-amount" data-lpignore="true" data-1p-ignore data-bwignore spellcheck="false" name="bid_amount" id="bid-amount-{{ $pid }}" class="sb-input sb-money-input" value="{{ old('project_id') == $pid ? old('bid_amount') : ($prefillBid ? $myBid->bid_amount : '') }}" placeholder="0.00" data-bid-amount required aria-describedby="bid-amount-error-{{ $pid }} bid-amount-compare-{{ $pid }} bid-amount-cap-{{ $pid }}">
                                    </div>
                                    <p class="sb-compare" id="bid-amount-compare-{{ $pid }}" data-bid-compare aria-live="polite"></p>
                                    <p class="sb-money-cap" id="bid-amount-cap-{{ $pid }}">Must not exceed the ABC.</p>
                                    <span class="sb-error" id="bid-amount-error-{{ $pid }}" data-bid-amount-error role="alert" aria-live="polite">@if(old('project_id') == $pid){{ $errors->first('bid_amount') }}@endif</span>
                                    <p class="sb-abc-line"><span>Approved budget (ABC)</span><strong>&#8369;{{ number_format($abc, 2) }}</strong></p>
                                </section>

                                @if($electronic)
                                    <section class="sb-card" data-pin-box aria-labelledby="pin-title-{{ $pid }}">
                                        <header class="sb-card-head">
                                            <span class="sb-card-icon" aria-hidden="true"><i class="fas fa-lock"></i></span>
                                            <div>
                                                <span class="sb-card-title" id="pin-title-{{ $pid }}">Financial PIN <span class="sb-req" aria-hidden="true">*</span></span>
                                                <p class="sb-card-hint">6 digits that seal your financial component{{ $isModifying ? ' (a new PIN, or the same as before)' : '' }}. You give it to the BAC at the financial opening.</p>
                                            </div>
                                        </header>
                                        <div class="sb-pin-grid">
                                            @foreach([['financial_password', 'financial-password-'.$pid, 'Create PIN'], ['financial_password_confirmation', 'financial-password-confirmation-'.$pid, 'Confirm PIN']] as [$pinName, $pinId, $pinLabel])
                                                <div class="sb-pin-field">
                                                    <span class="sb-pin-top">
                                                        <label for="{{ $pinId }}">{{ $pinLabel }}</label>
                                                        <span class="sb-pin-count" data-pin-count>0 / 6 digits</span>
                                                    </span>
                                                    <div class="sb-pin-control">
                                                        <input type="password" name="{{ $pinName }}" id="{{ $pinId }}" class="sb-input sb-pin-input" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" data-lpignore="true" data-1p-ignore data-bwignore placeholder="••••••" title="6 digits" data-pin required aria-describedby="pin-status-{{ $pid }}">
                                                        <button type="button" class="sb-pin-eye" data-pin-toggle aria-controls="{{ $pinId }}" aria-pressed="false" aria-label="Show {{ strtolower($pinLabel) }}"><i class="fas fa-eye" aria-hidden="true"></i></button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                        <p class="sb-pin-status" id="pin-status-{{ $pid }}" data-pin-status data-state="idle" role="status" aria-live="polite"><i class="fas fa-circle-info" aria-hidden="true"></i> <span>Type the same 6 digits in both boxes.</span></p>
                                        <span class="sb-error" data-pin-error role="alert">@if(old('project_id') == $pid){{ $errors->first('financial_password') }}@endif</span>
                                        <p class="sb-pin-warning" role="note"><i class="fas fa-key" aria-hidden="true"></i> <span><strong>The BAC cannot recover this PIN.</strong> Write it down somewhere safe. Do not put it in your bid documents or send it by email.</span></p>
                                    </section>
                                @endif
                            </div>

                            <h4 class="sb-group-title">Financial forms</h4>
                            @include('bidder.partials.submit-bid-files', ['items' => $components['financial']['items'], 'component' => 'financial'])
                        </section>

                        {{-- Final check appears inline; the bidder submits once from the footer. --}}
                        <section class="sb-panel" id="bsm-step-review-{{ $pid }}" role="tabpanel" data-sb-panel="3" aria-labelledby="bsm-review-{{ $pid }}" hidden>
                            <div class="sb-panel-head">
                                <div>
                                    <h3 class="sb-panel-title" id="bsm-review-{{ $pid }}">Final submission check</h3>
                                    <p class="sb-panel-hint">Look over everything once more. You submit once, from the button below.</p>
                                </div>
                            </div>

                            <div class="sb-ready" data-sb-ready data-state="pending" role="status" aria-live="polite">
                                <i class="fas fa-circle-info" aria-hidden="true"></i>
                                <div>
                                    <strong data-sb-ready-title>Checking your submission</strong>
                                    <p data-sb-ready-text></p>
                                </div>
                            </div>

                            <div class="sb-review-hero">
                                <div class="sb-review-offer">
                                    <span>Your bid price</span>
                                    <strong data-sb-review-price>&mdash;</strong>
                                    <small data-sb-review-compare></small>
                                </div>
                                <div class="sb-review-tiles">
                                    <div class="sb-tile" data-sb-tile="technical"><span>Technical files</span><strong>&ndash;</strong><small></small></div>
                                    <div class="sb-tile" data-sb-tile="financial"><span>Financial files</span><strong>&ndash;</strong><small></small></div>
                                    @if($electronic)
                                        <div class="sb-tile" data-sb-tile="pin"><span>Financial PIN</span><strong>&ndash;</strong><small></small></div>
                                    @endif
                                </div>
                            </div>
                            <div class="sb-review-grid">
                                <div class="sb-review-block">
                                    <div class="sb-review-head"><h4>Technical documents</h4></div>
                                    <ul class="sb-review-list" data-sb-review-files="technical"></ul>
                                </div>
                                <div class="sb-review-block">
                                    <div class="sb-review-head"><h4>Financial documents</h4></div>
                                    <ul class="sb-review-list" data-sb-review-files="financial"></ul>
                                    @if($electronic)
                                        <p class="sb-review-pin" data-sb-review-pin></p>
                                    @endif
                                </div>
                            </div>

                            <div class="sb-notes">
                                <label class="sb-label" for="bid-notes-{{ $pid }}">Notes for the BAC <span class="sb-optional">(optional)</span></label>
                                <textarea name="notes" id="bid-notes-{{ $pid }}" class="sb-input sb-textarea" rows="3" placeholder="Anything the BAC should know about this submission.">{{ old('project_id') == $pid ? old('notes') : ($prefillBid ? $myBid->notes : '') }}</textarea>
                            </div>

                            @if($electronic)
                                <div class="sb-alert is-info" role="note">
                                    <i class="fas fa-shield-halved" aria-hidden="true"></i>
                                    <div>
                                        <strong>Official online submission</strong>
                                        <p>Submitting records your bid with a receipt number and time{{ $project->electronic_submission_authority ? ' (authorized by '.$project->electronic_submission_authority.')' : '' }}. Your technical and financial components are stored separately and stay sealed until the bid opening. Submissions after the deadline are refused.</p>
                                    </div>
                                </div>
                            @endif
                            @if($project->bid_security_required)
                                <div class="sb-alert" role="note">
                                    <i class="fas fa-shield" aria-hidden="true"></i>
                                    <div>
                                        <strong>Bid security required &mdash; separate from the bidding documents fee</strong>
                                        <p>{{ $project->bid_security_notes ?: 'Submit the bid security with your bid, in the form and amount stated in the bidding documents.' }}</p>
                                    </div>
                                </div>
                            @endif
                        </section>
                    @endif
                </form>
            </div>

        </div>

        <footer class="sb-foot">
            <span class="sb-status" data-bid-status role="status" aria-live="polite"></span>
            <span class="sb-foot-actions">
                <button type="button" class="sb-btn sb-btn--ghost" data-close-modal="bid-modal-{{ $pid }}">Cancel</button>
                @if($paymentLocked)
                    <button type="button" class="sb-btn sb-btn--primary" disabled><i class="fas fa-lock" aria-hidden="true"></i> Payment required</button>
                @else
                    <button type="button" class="sb-btn sb-btn--ghost" data-sb-prev hidden>Back</button>
                    <button type="button" class="sb-btn sb-btn--primary" data-sb-next>Next <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
                    <button type="submit" form="{{ $formId }}" class="sb-btn sb-btn--primary" data-bid-submit disabled hidden>{{ $submitLabel }}</button>
                @endif
            </span>
        </footer>
    </div>
</div>
