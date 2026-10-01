<?php

namespace App\Support;

use App\Models\Bid;
use App\Models\BidTracking;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\ProjectProceeding;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The whole procurement, from the end-user office's request to acceptance,
 * as stages with who is responsible and what happens next. Everything is
 * derived from recorded facts; nothing is advanced here.
 *
 * Stage states: done, current, upcoming, skipped (not applicable) and
 * stopped (after a failure of bidding).
 */
class ProcurementTimeline
{
    private ?Collection $bids = null;

    public function __construct(private readonly ?Project $project, private readonly ?ProcurementRequest $request) {}

    public static function forProject(Project $project): self
    {
        $project->loadMissing(['schedule', 'procurementRequest', 'proceedings', 'bids.user', 'awards.bid.user', 'documents']);

        return new self($project, $project->procurementRequest);
    }

    public static function forRequest(ProcurementRequest $request): self
    {
        $request->loadMissing('project');

        return $request->project
            ? self::forProject($request->project)
            : new self(null, $request);
    }

    private ?array $stageCache = null;

    /**
     * The stages of this procurement. Competitive bidding and the
     * alternative modes (SVP, Shopping, Negotiated Procurement, Direct
     * Contracting) follow different sequences; see ProcurementMode.
     *
     * @return list<array{key: string, label: string, office: string, state: string, date: ?CarbonInterface, note: ?string}>
     */
    public function stages(): array
    {
        if ($this->stageCache !== null) {
            return $this->stageCache;
        }

        $p = $this->project;
        $mode = $this->mode();

        // Before the BAC opens a project the mode is not chosen yet, so only
        // the request stages and the hand-over to the BAC are shown.
        $stages = $p === null
            ? array_merge($this->requestStages(), [
                $this->stage('preparation', 'BAC chooses the mode and prepares the procurement', 'BAC / BAC Secretariat', 'upcoming', null,
                    'Competitive bidding or an alternative mode (such as SVP), depending on the ABC and the nature of the procurement.'),
            ])
            : array_merge(
                $this->requestStages(),
                $mode->isCompetitive() ? $this->competitiveStages() : $this->alternativeStages($mode),
                $this->awardStages($mode),
            );

        // A later stage on record implies the earlier ones happened (older
        // projects were awarded before every milestone was tracked).
        $lastDone = collect($stages)->keys()->filter(fn (int $index) => $stages[$index]['state'] === 'done')->last();
        foreach ($stages as $index => $stage) {
            if ($lastDone !== null && $index < $lastDone && $stage['state'] === 'upcoming') {
                $stages[$index]['state'] = 'done';
            }
        }

        // After a failure of bidding / procurement, the remaining stages of this round do not happen.
        if ($p?->isFailedBidding()) {
            foreach ($stages as $index => $stage) {
                if (in_array($stage['state'], ['upcoming', 'current'], true)) {
                    $stages[$index]['state'] = 'stopped';
                }
            }
        }

        // The first stage not yet done is the current one.
        foreach ($stages as $index => $stage) {
            // External PhilGEPS recording is tracked separately and must not
            // prevent the local BAC publication/submission flow from being current.
            if ($stage['key'] === 'philgeps_posting') {
                continue;
            }
            if ($stage['state'] === 'upcoming') {
                $stages[$index]['state'] = 'current';
                break;
            }
            if (in_array($stage['state'], ['current', 'stopped'], true)) {
                break;
            }
        }

        return $this->stageCache = $stages;
    }

    public function mode(): ?ProcurementMode
    {
        return $this->project?->mode();
    }

    /** End-user request and the PPMP/APP and funds review: the same for every mode. */
    private function requestStages(): array
    {
        $p = $this->project;
        $r = $this->request;

        return [
            $this->stage('request', 'Purchase request', 'End-user office',
                match (true) {
                    $r === null && $p !== null => 'skipped',
                    $r !== null && $r->submitted_at !== null => 'done',
                    default => 'current',
                },
                $r?->submitted_at,
                $r === null && $p !== null ? 'Prepared directly by the BAC.' : ($r ? $r->end_user_office.' · '.$r->reference_no : null)),

            $this->stage('review', 'PPMP/APP & funds check', 'Budget / Procurement Office',
                match (true) {
                    $r === null && $p !== null => 'skipped',
                    $r !== null && $r->forwarded_at !== null => 'done',
                    $r !== null && $r->status === ProcurementRequest::STATUS_REJECTED => 'stopped',
                    default => 'upcoming',
                },
                $r?->forwarded_at ?? $r?->reviewed_at,
                $r?->ppmp_reference ? 'PPMP '.$r->ppmp_reference.($r->app_reference ? ' · APP '.$r->app_reference : '') : null),
        ];
    }

    private function competitiveStages(): array
    {
        $p = $this->project;
        $mode = $this->mode();
        $bids = $this->officialBids();
        $recommended = $this->recommendedBid();
        $opened = $p?->bidsAreOpened() ?? false;
        $deadline = $p?->bidSubmissionDeadline();
        $published = $this->isPublished();
        $preBid = $p?->schedule?->pre_bid_conference_date;
        $preBidRecords = $p ? $p->proceedings->whereIn('type', [ProjectProceeding::TYPE_PRE_BID, ProjectProceeding::TYPE_CLARIFICATION, ProjectProceeding::TYPE_BID_BULLETIN]) : collect();
        $evaluationStarted = $bids->contains(fn (Bid $bid) => $bid->bac_evaluation_at !== null || $bid->evaluated_at !== null || $bid->post_qualification_at !== null);
        $prebidRequired = $mode?->requiresPrebid() ?? false;

        return [
            $this->stage('preparation', 'Bidding documents, ABC & schedule', 'BAC / BAC Secretariat',
                $published ? 'done' : 'upcoming',
                $p?->schedule?->date_posted,
                $p ? 'ABC ₱'.number_format((float) $p->budget, 2).' · '.$mode->legalBasisShort() : null),

            $this->stage('publication', 'Published in BAC system', 'BAC Secretariat',
                $published ? 'done' : 'upcoming',
                $p?->published_at ?? $p?->schedule?->date_posted,
                $published ? 'Visible to eligible bidders in Available Bids. This is separate from PhilGEPS.' : 'Complete the local publication checks before opening the opportunity.'),

            $this->stage('philgeps_posting', 'PhilGEPS posting (external)', 'BAC Secretariat',
                match (true) {
                    $p?->philgeps_posted_at !== null => 'done',
                    ! $mode->requiresPosting() => 'skipped',
                    default => 'upcoming',
                },
                $p?->philgeps_posted_at,
                $p?->philgeps_posted_at !== null
                    ? 'PhilGEPS '.$p->philgeps_reference_no.', posted '.$p->philgeps_posted_at->format('M d, Y')
                    : 'Not recorded. Post externally when required, then record the actual date and reference here.'),

            $this->stage('prebid', 'Pre-bid conference & bid bulletins', 'BAC',
                match (true) {
                    ! $prebidRequired && $preBid === null && $preBidRecords->isEmpty() => 'skipped',
                    $preBidRecords->isNotEmpty() || ($deadline !== null && $deadline->isPast()) => 'done',
                    default => 'upcoming',
                },
                $preBid,
                match (true) {
                    $preBidRecords->isNotEmpty() => $preBidRecords->count().' '.str('record')->plural($preBidRecords->count()).' on file',
                    ! $prebidRequired && $preBid === null => 'Not required for this ABC; held at the BAC\'s discretion.',
                    $prebidRequired && $preBid === null => 'No pre-bid conference on record. '.$mode->prebidRule(),
                    default => null,
                }),

            $this->stage('submission', 'Bid submission', 'Bidders',
                $opened || ($deadline !== null && $deadline->isPast()) ? 'done' : 'upcoming',
                $deadline,
                $p ? $p->submissionMethodLabel().' · '.$bids->count().' official '.str('bid')->plural($bids->count()) : null),

            $this->stage('opening', 'Bid opening & preliminary examination', 'BAC',
                $opened && ($evaluationStarted || $bids->every(fn (Bid $bid) => $bid->progress()->facts()['prelim_passed'] || $bid->progress()->facts()['disqualified'])) ? 'done' : 'upcoming',
                $p?->bids_opened_at ?? $p?->schedule?->bid_opening_date,
                null),

            $this->stage('evaluation', 'Evaluation & post-qualification', 'BAC / TWG',
                $recommended ? 'done' : 'upcoming',
                $recommended?->post_qualification_completed_at,
                $this->awardDueNote()),
        ];
    }

    /** SVP / Shopping (RFQ), Negotiated Procurement and Direct Contracting. */
    private function alternativeStages(ProcurementMode $mode): array
    {
        $p = $this->project;
        $bids = $this->officialBids();
        $deadline = $p?->bidSubmissionDeadline();
        $published = $this->isPublished();
        $opened = $p?->bidsAreOpened() ?? false;
        $recommended = $this->recommendedBid();
        $proceedings = $p?->proceedings ?? collect();
        $rfqSent = $proceedings->where('type', ProjectProceeding::TYPE_RFQ_ISSUED)->sortByDesc('occurred_at')->first();
        $evaluated = $proceedings->whereIn('type', [ProjectProceeding::TYPE_ABSTRACT, ProjectProceeding::TYPE_NEGOTIATION])->sortByDesc('occurred_at')->first();
        $deadlinePassed = $deadline !== null && $deadline->isPast();
        $noun = $mode->submissionNoun(true);

        $prepLabel = match (true) {
            $mode->isNegotiated() => 'Terms, ABC & invitation to negotiate',
            $mode->isDirectContracting() => 'RFQ or pro-forma invoice & ABC',
            default => 'RFQ, specifications & ABC',
        };

        $sentLabel = match (true) {
            $mode->isDirectContracting() => 'RFQ sent to the direct supplier',
            $mode->isNegotiated() => 'Invitations to negotiate sent',
            default => 'RFQs sent to at least 3 suppliers',
        };

        $evaluationLabel = match (true) {
            $mode->isNegotiated() => 'Negotiation & abstract of offers',
            $mode->isDirectContracting() => 'Simplified negotiation',
            default => 'Abstract of Quotations & evaluation',
        };

        return [
            $this->stage('preparation', $prepLabel, $mode->isNegotiated() ? 'BAC / TWG' : 'BAC Secretariat',
                $published ? 'done' : 'upcoming',
                $p?->schedule?->date_posted,
                collect([
                    $p ? 'ABC ₱'.number_format((float) $p->budget, 2) : null,
                    $mode->negotiationGroundLabel(),
                    $mode->legalBasisShort(),
                ])->filter()->implode(' · ')),

            $this->stage('publication', 'Published in BAC system', 'BAC Secretariat',
                $published ? 'done' : 'upcoming',
                $p?->published_at ?? $p?->schedule?->date_posted,
                $published ? 'Visible to eligible bidders in Available Bids. This is separate from PhilGEPS.' : 'Complete the local publication checks before opening the opportunity.'),

            $this->stage('philgeps_posting', 'PhilGEPS posting (external)', 'BAC Secretariat',
                match (true) {
                    $p?->philgeps_posted_at !== null => 'done',
                    ! $mode->requiresPosting() => 'skipped',
                    default => 'upcoming',
                },
                $p?->philgeps_posted_at,
                $p?->philgeps_posted_at !== null
                    ? 'PhilGEPS '.$p->philgeps_reference_no.', posted '.$p->philgeps_posted_at->format('M d, Y')
                    : 'Not recorded. Post externally when required, then record the actual date and reference here.'),

            $this->stage('rfq_sent', $sentLabel, 'BAC Secretariat',
                $rfqSent ? 'done' : 'upcoming',
                $rfqSent?->occurred_at,
                $rfqSent ? 'Sent to '.$rfqSent->recipients_count.' '.str('supplier')->plural((int) $rfqSent->recipients_count).($rfqSent->reference_no ? ' · '.$rfqSent->reference_no : '') : null),

            $this->stage('submission', ucfirst($noun).' received', 'Suppliers',
                match (true) {
                    ($opened || $deadlinePassed) && $bids->isNotEmpty() => 'done',
                    default => 'upcoming',
                },
                $deadline,
                match (true) {
                    $deadlinePassed && $bids->isEmpty() && $published => 'No '.$mode->submissionNoun().' received by the deadline. Extend the deadline until at least one arrives'.($mode->isRa12009() ? ' (RA 12009 IRR Sec. 34.3(d)).' : '.'),
                    $p !== null => $bids->count().' '.str($mode->submissionNoun())->plural($bids->count()).' received · '.$p->submissionMethodLabel(),
                    default => null,
                }),

            $this->stage('evaluation', $evaluationLabel, 'BAC / TWG',
                match (true) {
                    $evaluated !== null || $recommended !== null => 'done',
                    default => 'upcoming',
                },
                $evaluated?->occurred_at,
                $evaluated?->reference_no ? 'No. '.$evaluated->reference_no : ($mode->isDirectContracting() ? 'Optional: negotiate the terms and conditions of the contract.' : null)),
        ];
    }

    /** From the BAC resolution to acceptance: the same records for every mode. */
    private function awardStages(?ProcurementMode $mode): array
    {
        $p = $this->project;
        $recommended = $this->recommendedBid();
        $winner = $this->contractedBid();
        $alternative = $mode?->isAlternative() ?? false;
        // Older awards were recorded on the award register only, without
        // the Notice of Award / contract milestones on the bid.
        $legacyAward = $winner === null ? $p?->awards->sortByDesc('id')->first() : null;
        $awardedTo = $winner
            ? ($winner->user?->company ?: $winner->user?->name)
            : ($legacyAward?->bid?->user?->company ?: $legacyAward?->bid?->user?->name);

        return [
            $this->stage('recommendation', 'BAC resolution recommending award', 'BAC',
                $recommended || $legacyAward ? 'done' : 'upcoming',
                $recommended?->bac_resolution_date ?? $recommended?->bac_recommended_at,
                $recommended?->bac_resolution_no ? 'Resolution No. '.$recommended->bac_resolution_no : null),

            $this->stage('award', 'HoPE approval & Notice of Award', 'Head of the Procuring Entity',
                $winner?->notice_of_award_at || $legacyAward?->notice_of_award_date ? 'done' : 'upcoming',
                $winner?->notice_of_award_at ?? $legacyAward?->notice_of_award_date,
                $awardedTo ?: null),

            $this->stage('contract', $alternative ? 'Contract or Purchase / Job Order signed' : 'Performance security & contract signing', 'HoPE / winning supplier',
                $winner?->contract_signed_at || $legacyAward?->contract_date ? 'done' : 'upcoming',
                $winner?->contract_signed_at ?? $legacyAward?->contract_date,
                $winner?->performance_security_at ? 'Performance security posted '.$winner->performance_security_at->format('M d, Y') : ($legacyAward ? 'From the award register.' : null)),

            $this->stage('ntp', 'Notice to Proceed', 'Head of the Procuring Entity',
                $winner?->notice_to_proceed_at ? 'done' : 'upcoming',
                $winner?->notice_to_proceed_at,
                null),

            $this->stage('acceptance', 'Delivery, inspection & acceptance', 'End-user office / Inspection committee',
                $p?->isCompleted() ? 'done' : 'upcoming',
                $p?->completed_at,
                $p?->isCompleted() ? 'Delivered, inspected and accepted.' : null),
        ];
    }

    private function isPublished(): bool
    {
        return $this->project !== null && $this->project->isPublishedLocally();
    }

    private function recommendedBid(): ?Bid
    {
        return $this->officialBids()->first(fn (Bid $bid) => $bid->bac_recommended_at !== null && $bid->award_decision !== Bid::AWARD_DECISION_DISAPPROVED);
    }

    /**
     * Competitive bidding must reach the award within 60 calendar days of
     * the bid opening (RA 12009 IRR Sec. 67.1; 3 months under RA 9184).
     */
    public function awardDueDate(): ?CarbonInterface
    {
        $p = $this->project;
        if ($p === null || $p->bids_opened_at === null || $p->isFailedBidding() || $this->contractedBid() !== null || $p->awards->isNotEmpty()) {
            return null;
        }

        return $p->mode()->awardDueDate($p->bids_opened_at);
    }

    public function isPastAwardPeriod(): bool
    {
        return $this->awardDueDate()?->isPast() ?? false;
    }

    private function awardDueNote(): ?string
    {
        $due = $this->awardDueDate();
        if ($due === null) {
            return null;
        }

        $label = $due->copy()->timezone(config('bac-office.display_timezone'))->format('M d, Y');

        return $due->isPast()
            ? 'Past the award period of '.$this->project->mode()->awardPeriodLabel().' — was due '.$label.'.'
            : 'Award due by '.$label.' ('.$this->project->mode()->awardPeriodLabel().').';
    }

    /**
     * @return array{key: string, label: string, office: string, state: string, date: ?CarbonInterface, note: ?string}|null
     */
    public function current(): ?array
    {
        return collect($this->stages())->firstWhere('state', 'current');
    }

    public function progressPercent(): int
    {
        $stages = collect($this->stages())->reject(fn (array $stage) => $stage['state'] === 'skipped');

        return $stages->isEmpty() ? 0 : (int) round(100 * $stages->where('state', 'done')->count() / $stages->count());
    }

    /**
     * What has to happen next and who does it.
     *
     * @return array{title: string, office: string, detail: string}|null
     */
    public function nextAction(): ?array
    {
        $p = $this->project;
        $r = $this->request;

        if ($p?->isFailedBidding()) {
            $alternative = $p->mode()->isAlternative();

            return [
                'title' => $alternative ? 'Failure of procurement declared' : 'Failure of bidding declared',
                'office' => 'BAC',
                'detail' => $p->rebidProject
                    ? 'A new round was posted: '.$p->rebidProject->title.'.'
                    : ($alternative
                        ? 'Record the review of the terms and ABC, then post a new round when ready.'
                        : 'The end-user office reviews the terms, specifications and cost estimate (RA 12009 IRR Sec. 64.2); the BAC then revises them and posts the rebid. After a second failure, Negotiated Procurement may be used.'),
            ];
        }

        if ($r?->status === ProcurementRequest::STATUS_REJECTED) {
            return ['title' => 'Request not approved', 'office' => 'End-user office', 'detail' => $r->review_remarks ?: 'See the review remarks.'];
        }

        if ($r?->status === ProcurementRequest::STATUS_RETURNED) {
            return ['title' => 'Correct and resubmit the request', 'office' => 'End-user office', 'detail' => $r->review_remarks ?: 'See the review remarks.'];
        }

        $current = $this->current();
        if ($current === null) {
            return $p?->isCompleted()
                ? ['title' => 'Procurement completed', 'office' => 'BAC Secretariat', 'detail' => 'Keep the records on file.']
                : null;
        }

        $mode = $this->mode();
        $alternative = $mode?->isAlternative() ?? false;
        $noun = $mode?->submissionNoun() ?? 'bid';
        $deadline = $p?->bidSubmissionDeadline();
        $deadlineText = $deadline?->copy()->timezone(config('bac-office.display_timezone'))->format('M d, Y h:i A');

        $detail = match ($current['key']) {
            'request' => 'Complete the request with the specifications or TOR, then submit it for review.',
            'review' => 'Check the request against the PPMP/APP and the available funds, then forward it to the BAC or return it.',
            'preparation' => match (true) {
                $p === null => 'Prepare the '.($alternative ? 'RFQ' : 'bidding documents').' and schedule from this request.',
                $alternative => 'Complete the '.$mode->noticeLabel().', technical specifications, ABC and '.lcfirst($mode->deadlineLabel()).', then publish once the checks pass.',
                default => 'Complete the bidding documents, ABC and schedule, then publish once the posting checks pass.',
            },
            'publication' => 'Publish the project in the BAC system once the local documents and schedule pass validation. Any PhilGEPS posting is recorded separately after the real external posting.',
            'philgeps_posting' => 'If required, post the '.($mode?->noticeLabel() ?? 'Invitation to Bid').' on PhilGEPS externally, then record the actual reference number and posting date here. '.($mode?->postingRule() ?? ''),
            'prebid' => 'Hold the pre-bid conference and record it, with any clarifications and bid bulletins.',
            'rfq_sent' => $mode?->isDirectContracting()
                ? 'Send the RFQ or pro-forma invoice with the terms and conditions of sale to the identified supplier, then record it.'
                : 'Send the '.($mode?->isNegotiated() ? 'invitation' : 'RFQ').' to at least three (3) suppliers of known qualifications, then record it. Suppliers who responded to the posting may also participate.',
            'submission' => match (true) {
                $deadline === null => 'Set the '.lcfirst($mode?->deadlineLabel() ?? 'submission deadline').'.',
                $deadline->isPast() && $this->officialBids()->isEmpty() => 'No '.$noun.' was received. Extend the '.lcfirst($mode->deadlineLabel()).' until at least one '.$noun.' arrives.',
                default => ucfirst(str($noun)->plural()).' are accepted until '.$deadlineText.'.',
            },
            'opening' => $p?->bidsAreOpened()
                ? 'Record the preliminary pass/fail examination of each opened bid.'
                : 'Open the bids at the scheduled bid opening, after the submission deadline.',
            'evaluation' => match (true) {
                ! $alternative => 'Evaluate the passing bids, then post-qualify the bidder with the lowest calculated bid.',
                $mode->isNegotiated() => 'Negotiate with the invited suppliers on equal terms, then record the negotiation and the abstract of offers.',
                $mode->isDirectContracting() => 'Optionally negotiate the terms with the supplier, then recommend the award.',
                default => 'Open the quotations, prepare the Abstract of Quotations and determine the lowest calculated and responsive quotation.',
            },
            'recommendation' => $alternative
                ? 'Recommend the award to the HoPE through a BAC resolution.'
                : 'Recommend the award through a BAC resolution for the post-qualified bidder.',
            'award' => 'The HoPE approves or disapproves the recommendation; then record the signed Notice of Award.',
            'contract' => $alternative
                ? 'Record the signed contract or Purchase / Job Order.'
                : 'Record the performance security and the contract signing.',
            'ntp' => 'Issue the Notice to Proceed.',
            'acceptance' => 'Record the inspection, then the acceptance of the delivery or work.',
            default => '',
        };

        return ['title' => $current['label'], 'office' => $current['office'], 'detail' => trim($detail)];
    }

    /**
     * @return list<array{label: string, date: CarbonInterface, time: bool}>
     */
    public function keyDates(): array
    {
        $p = $this->project;
        $r = $this->request;
        $s = $p?->schedule;
        $mode = $this->mode();
        $winner = $this->contractedBid();
        $rfqSent = $p?->proceedings->where('type', ProjectProceeding::TYPE_RFQ_ISSUED)->sortByDesc('occurred_at')->first();

        return collect([
            ['Request submitted', $r?->submitted_at, true],
            ['Forwarded to BAC', $r?->forwarded_at, true],
            ['Local BAC publication', $p?->published_at ?? $s?->date_posted, false],
            ['Actual PhilGEPS posting', $p?->philgeps_posted_at, false],
            [$mode?->isDirectContracting() ? 'RFQ sent' : 'RFQs sent', $rfqSent?->occurred_at, false],
            ['Pre-bid conference', $s?->pre_bid_conference_date, true],
            ['Clarification deadline', $s?->clarification_deadline, true],
            [$mode?->deadlineLabel() ?? 'Submission deadline', $p?->bidSubmissionDeadline(), true],
            [$mode?->openingLabel() ?? 'Bid opening', $p?->bids_opened_at ?? $s?->bid_opening_date, true],
            ['Award due', $this->awardDueDate(), false],
            ['Notice of Award', $winner?->notice_of_award_at, false],
            ['Contract signed', $winner?->contract_signed_at, false],
            ['Notice to Proceed', $winner?->notice_to_proceed_at, false],
            ['Accepted', $p?->completed_at, false],
        ])
            ->filter(fn (array $row) => $row[1] !== null)
            ->map(fn (array $row) => ['label' => $row[0], 'date' => $row[1], 'time' => $row[2]])
            ->values()
            ->all();
    }

    /**
     * Decisions and proceedings, newest first. $forBac includes internal
     * reasons; the end-user view sees the public text only.
     *
     * @return list<array{at: CarbonInterface, title: string, detail: ?string, actor: ?string, tone: string, attachment: ?array}>
     */
    public function history(bool $forBac = true): array
    {
        $events = collect();
        $p = $this->project;
        $r = $this->request;

        if ($r?->submitted_at) {
            $events->push($this->event($r->submitted_at, 'Request submitted by '.$r->end_user_office, $r->reference_no, $r->requester?->name, 'info'));
        }
        if ($r?->reviewed_at && $r->status !== ProcurementRequest::STATUS_SUBMITTED) {
            $events->push($this->event($r->reviewed_at, match (true) {
                $r->forwarded_at !== null => 'PPMP/APP review completed; forwarded to the BAC',
                $r->status === ProcurementRequest::STATUS_REJECTED => 'Request not approved',
                default => 'Request returned for correction',
            }, $r->review_remarks, $r->reviewer?->name, $r->status === ProcurementRequest::STATUS_REJECTED ? 'danger' : 'info'));
        }

        if ($p) {
            if ($p->published_at) {
                $events->push($this->event($p->published_at, 'Published in the BAC system', 'Visible to eligible bidders; this does not record a PhilGEPS posting.', $p->publishedBy?->name, 'success'));
            }
            if ($p->philgeps_posted_at) {
                $events->push($this->event($p->philgeps_posted_at, $p->mode()->noticeLabel().' posted on PhilGEPS', 'Reference '.$p->philgeps_reference_no, null, 'info'));
            }
            if ($p->bids_opened_at) {
                $events->push($this->event($p->bids_opened_at, $p->mode()->isCompetitive() ? 'Bids opened' : ucfirst(str($p->mode()->submissionNoun())->plural()).' opened', null, null, 'info'));
            }
            if ($p->failed_bidding_at) {
                $events->push($this->event($p->failed_bidding_at, $p->mode()->isCompetitive() ? 'Failure of bidding declared' : 'Failure of procurement declared', $p->failed_bidding_reason, null, 'danger'));
            }

            foreach ($p->proceedings as $proceeding) {
                $detail = collect([
                    $proceeding->reference_no ? 'No. '.$proceeding->reference_no : null,
                    $proceeding->outcome ? 'Outcome: '.(ProjectProceeding::RECONSIDERATION_OUTCOMES[$proceeding->outcome] ?? $proceeding->outcome) : null,
                    $proceeding->summary,
                ])->filter()->implode(' · ');

                $events->push($this->event(
                    $proceeding->occurred_at,
                    $proceeding->typeLabel().($proceeding->title !== $proceeding->typeLabel() ? ': '.$proceeding->title : ''),
                    $detail ?: null,
                    $proceeding->recorder?->name,
                    'neutral',
                    $proceeding->file_path ? ['name' => $proceeding->original_name, 'route' => ['procurement.files.proceeding', $proceeding]] : null,
                ));
            }

            $trackings = BidTracking::with(['bid.user', 'creator'])
                ->where('project_id', $p->id)
                ->whereNotIn('decision', ['draft_saved', 'opened'])
                ->when(! $forBac, fn ($query) => $query->whereIn('decision', ['recommended', 'approved', 'disapproved', 'issued', 'signed']))
                ->get();

            foreach ($trackings as $tracking) {
                $bidder = $tracking->bid?->user?->company ?: $tracking->bid?->user?->name;
                $events->push($this->event(
                    $tracking->created_at,
                    $tracking->status_title.($bidder ? ' — '.$bidder : ''),
                    $forBac ? ($tracking->reason ?: $this->trackingDetail($tracking)) : $this->trackingDetail($tracking),
                    $forBac ? $tracking->creator?->name : null,
                    match ($tracking->decision) {
                        'failed', 'disapproved' => 'danger',
                        'recommended', 'approved', 'issued', 'signed' => 'success',
                        default => 'neutral',
                    },
                    $forBac && $tracking->attachment_path ? ['name' => $tracking->attachment_name, 'route' => ['procurement.files.decision', $tracking]] : null,
                ));
            }
        }

        return $events->sortByDesc(fn (array $event) => $event['at']->getTimestamp())->values()->all();
    }

    /**
     * @return list<array{group: string, name: string, route: ?array, url: ?string}>
     */
    public function documents(bool $forBac = true): array
    {
        $docs = collect();

        foreach ($this->request?->documents ?? [] as $document) {
            $docs->push(['group' => 'Request', 'name' => $document->typeLabel().': '.$document->original_name, 'route' => ['procurement.files.request', $document], 'url' => null]);
        }

        if ($this->project) {
            $official = $this->project->officialDocuments()->pluck('id')->all();
            // The admin PDF route addresses a file by its position among the
            // uploaded documents; the public one (used for staff, who cannot
            // open admin routes) by its position among the official ones.
            $officialIndex = 0;
            foreach ($this->project->uploadedDocuments()->values() as $index => $document) {
                if (! in_array($document->id, $official, true)) {
                    continue;
                }

                $docs->push([
                    'group' => $this->mode()?->isAlternative() ? 'RFQ documents' : 'Bidding documents',
                    'name' => $document->display_name ?? $document->original_name,
                    'route' => null,
                    'url' => match (true) {
                        ! $forBac => null,
                        auth()->user()?->role === 'admin' => route('admin.project.document.pdf', [$this->project, $index]),
                        in_array($this->project->status, Project::PUBLIC_STATUSES, true) => route('public.procurement.document.pdf', [$this->project, $officialIndex]),
                        default => null,
                    },
                ]);
                $officialIndex++;
            }

            foreach ($this->project->proceedings->whereNotNull('file_path') as $proceeding) {
                $docs->push(['group' => 'Proceedings', 'name' => $proceeding->typeLabel().': '.$proceeding->original_name, 'route' => ['procurement.files.proceeding', $proceeding], 'url' => null]);
            }

            if ($forBac) {
                BidTracking::where('project_id', $this->project->id)->whereNotNull('attachment_path')->get()
                    ->each(fn (BidTracking $tracking) => $docs->push(['group' => 'Decisions', 'name' => $tracking->status_title.': '.$tracking->attachment_name, 'route' => ['procurement.files.decision', $tracking], 'url' => null]));
            }
        }

        return $docs->values()->all();
    }

    public function contractedBid(): ?Bid
    {
        return $this->officialBids()
            ->filter(fn (Bid $bid) => $bid->notice_of_award_at !== null)
            ->sortByDesc('notice_of_award_at')
            ->first();
    }

    public function officialBids(): Collection
    {
        return $this->bids ??= ($this->project?->bids ?? collect())->reject(fn (Bid $bid) => $bid->isDraft())->values();
    }

    private function trackingDetail(BidTracking $tracking): ?string
    {
        $details = $tracking->details ?? [];

        return match (true) {
            filled($details['bac_resolution_no'] ?? null) => 'BAC Resolution No. '.$details['bac_resolution_no'],
            filled($details['receipt_no'] ?? null) => 'Receipt No. '.$details['receipt_no'],
            default => null,
        };
    }

    private function stage(string $key, string $label, string $office, string $state, mixed $date, ?string $note): array
    {
        return ['key' => $key, 'label' => $label, 'office' => $office, 'state' => $state, 'date' => $date instanceof CarbonInterface ? $date : null, 'note' => $note];
    }

    private function event(CarbonInterface $at, string $title, ?string $detail, ?string $actor, string $tone, ?array $attachment = null): array
    {
        return compact('at', 'title', 'detail', 'actor', 'tone', 'attachment');
    }
}
