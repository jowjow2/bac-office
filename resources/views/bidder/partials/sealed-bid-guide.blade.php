{{--
    A manual-submission project takes sealed paper bids at the BAC Secretariat only,
    so the bidder files nothing online: this modal says how to pay, what goes in
    each envelope, and where and when to hand them in. The BAC Secretariat records
    the receipt (BidWorkflow::receiveWalkInSealedBid). Same shell as submit-bid-modal.
--}}
@php
    $requirements = \App\Support\BidSubmissionRequirements::for($project);
    $tz = config('bac-office.display_timezone');
    $deadlineLocal = $deadline?->copy()->timezone($tz);
    $openingAt = $project->schedule?->bid_opening_date?->copy()->timezone($tz);
    $pid = $project->id;
    $abc = (float) $project->budget;
    $venue = $project->submission_venue ?: 'BAC Secretariat, Municipal Hall';
    $received = $myBid && ! $myBid->isDraft();
    $daysLeftLabel = null;
    if ($deadlineLocal && $deadlineLocal->isFuture()) {
        $days = (int) now($tz)->startOfDay()->diffInDays($deadlineLocal->copy()->startOfDay(), false);
        $daysLeftLabel = $days <= 0 ? 'closes today' : $days.' '.\Illuminate\Support\Str::plural('day', $days).' left';
    }
    $envelopes = [
        ['Technical envelope', 'fa-file-shield', 'Eligibility documents and technical forms. Opened at the bid opening.', $requirements->technical()],
        ['Financial envelope', 'fa-file-invoice-dollar', 'Your price and financial forms. Opened only if your technical envelope passes.', $requirements->financial()],
    ];
@endphp

<div class="bidder-modal-overlay bidder-submit-modal-overlay" id="bid-modal-{{ $pid }}" hidden aria-hidden="true">
    <div class="sb" role="dialog" aria-modal="true" aria-labelledby="bid-modal-title-{{ $pid }}" tabindex="-1" data-sealed-guide>
        <header class="sb-head">
            <div class="sb-head-main">
                <p class="sb-eyebrow">
                    <span>{{ $requirements->modeLabel() }}</span>
                    <span>{{ $requirements->categoryLabel() }}</span>
                    @if($project->reference_no)<code>{{ $project->reference_no }}</code>@endif
                </p>
                <h2 class="sb-title" id="bid-modal-title-{{ $pid }}">How to submit your sealed bid</h2>
                <p class="sb-project">{{ $project->title }}</p>
                <div class="sb-chips">
                    <span class="sb-chip"><i class="fas fa-scale-balanced" aria-hidden="true"></i> ABC &#8369;{{ number_format($abc, 2) }}</span>
                    <span class="sb-chip {{ $daysLeftLabel && str_contains($daysLeftLabel, 'today') ? 'is-warn' : '' }}"><i class="fas fa-clock" aria-hidden="true"></i> Deadline {{ $deadlineLocal ? $deadlineLocal->format('M d, Y h:i A') : 'not set' }}{{ $daysLeftLabel ? ' · '.$daysLeftLabel : '' }}</span>
                </div>
            </div>
            <button type="button" class="sb-close" data-close-modal="bid-modal-{{ $pid }}" aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </header>

        <div class="sb-body" data-scroll-body>
            <div class="sb-main">
                <div class="sg">
                    <div class="sb-alert is-info" role="note">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <div>
                            <strong>This project takes sealed paper bids only</strong>
                            <p>Nothing is uploaded here. Your bid counts once the BAC Secretariat receives your sealed envelopes at {{ $venue }} and records them.</p>
                        </div>
                    </div>

                    {{-- Where the bid stands. --}}
                    @if($received)
                        <p class="sg-status is-done"><i class="fas fa-circle-check" aria-hidden="true"></i> <span><strong>Sealed bid received</strong>{{ $myBid->submitted_at ? ' on '.$myBid->submitted_at->copy()->timezone($tz)->format('M d, Y h:i A') : '' }}{{ $myBid->receipt_no ? ' · Logbook no. '.$myBid->receipt_no : '' }}</span></p>
                    @else
                        <p class="sg-status"><i class="fas fa-hourglass-half" aria-hidden="true"></i> <span><strong>Not yet received.</strong> This changes when the BAC Secretariat records your envelopes.</span></p>
                    @endif
                    @if($myBid && $myBid->isDraft())
                        <p class="sg-note"><i class="fas fa-circle-info" aria-hidden="true"></i> You saved a draft online earlier. It is not an official bid; only your sealed envelopes count.</p>
                    @endif

                    <ol class="sg-steps">
                        @if($requiresFee)
                            <li class="sg-step {{ $feePayment ? 'is-done' : '' }}">
                                <span class="sg-step__num" aria-hidden="true">{{ $feePayment ? '' : '1' }}</span>
                                <div>
                                    <h3>Pay the bidding documents fee</h3>
                                    @if($feePayment)
                                        <p>Paid · OR No. {{ $feePayment->or_number }} · &#8369;{{ number_format((float) $feePayment->amount, 2) }}</p>
                                    @else
                                        <p>Pay {{ $feeLabel }} at the {{ $project->paymentVenueLabel() }} and keep the Official Receipt. The BAC receives your sealed bid only after the payment is recorded.</p>
                                    @endif
                                </div>
                            </li>
                        @endif

                        <li class="sg-step">
                            <span class="sg-step__num" aria-hidden="true">{{ $requiresFee ? 2 : 1 }}</span>
                            <div>
                                <h3>Prepare two sealed envelopes</h3>
                                <p>Follow the bidding documents. Mark each envelope with the project reference{{ $project->reference_no ? ' ('.$project->reference_no.')' : '' }}, the project title and your company name.</p>
                                <div class="sg-envelopes">
                                    @foreach($envelopes as [$name, $icon, $hint, $items])
                                        <section class="sg-envelope">
                                            <h4><i class="fas {{ $icon }}" aria-hidden="true"></i> {{ $name }}</h4>
                                            <p class="sg-envelope__hint">{{ $hint }}</p>
                                            <ul>
                                                @foreach($items as $item)
                                                    <li>
                                                        <i class="far fa-square" aria-hidden="true"></i>
                                                        <span>{{ $item['label'] }}@if(! $item['required']) <em>if applicable</em>@endif @if($item['condition'])<small>{{ $item['condition'] }}</small>@endif</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </section>
                                    @endforeach
                                </div>
                            </div>
                        </li>

                        <li class="sg-step">
                            <span class="sg-step__num" aria-hidden="true">{{ $requiresFee ? 3 : 2 }}</span>
                            <div>
                                <h3>Hand them in on or before the deadline</h3>
                                <p>Bring both envelopes to <strong>{{ $venue }}</strong> by <strong>{{ $deadlineLocal ? $deadlineLocal->format('l, M d, Y h:i A') : 'the deadline' }}</strong>. Late bids are not accepted. Ask for the logbook number.</p>
                                @if($openingAt)
                                    <p class="sg-muted">Bid opening: {{ $openingAt->format('M d, Y h:i A') }}. You may attend.</p>
                                @endif
                            </div>
                        </li>
                    </ol>
                </div>
            </div>

            <aside class="sb-rail" aria-label="Project notice">
                <div class="sb-rail-block sb-rail-docs">
                    @include('bidder.partials.project-documents', ['project' => $project, 'compact' => true])
                    @include('bidder.partials.project-requirements', ['project' => $project, 'showDocumentChips' => false])
                </div>
            </aside>
        </div>

        <footer class="sb-foot">
            <span class="sb-status">{{ $received ? 'Your sealed bid is on record.' : 'Nothing to submit online for this project.' }}</span>
            <span class="sb-foot-actions">
                <button type="button" class="sb-btn sb-btn--primary" data-close-modal="bid-modal-{{ $pid }}">Got it</button>
            </span>
        </footer>
    </div>
</div>
