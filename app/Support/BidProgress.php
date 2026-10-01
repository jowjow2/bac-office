<?php

namespace App\Support;

use App\Models\Award;
use App\Models\Bid;
use Carbon\CarbonInterface;

/**
 * Builds the bidder-facing progress view of one bid from recorded BAC / LGU
 * actions only. Nothing here advances a bid: a stage is "done" only when the
 * matching timestamp (or legacy status that proves it) exists.
 *
 * Stage states:
 *   done       - recorded event
 *   current    - the stage the bid is in right now
 *   pending    - later stage that has not happened (active bids only)
 *   failed     - the stage where the bid was disqualified / disapproved
 *   unrecorded - an earlier stage with no record, although a later one exists
 *                (legacy data entered before the milestone was tracked)
 */
class BidProgress
{
    public const STAGE_SUBMITTED = 'submitted';

    public const STAGE_PRELIMINARY = 'preliminary_examination';

    public const STAGE_EVALUATION = 'bid_evaluation';

    public const STAGE_POST_QUALIFICATION = 'post_qualification';

    public const STAGE_RECOMMENDATION = 'bac_recommendation';

    public const STAGE_AWARD_APPROVAL = 'award_approval';

    public const STAGE_NOTICE_OF_AWARD = 'notice_of_award';

    public const STAGE_CONTRACT_SIGNED = 'contract_signed';

    public const STAGE_NOTICE_TO_PROCEED = 'notice_to_proceed';

    public const STAGES = [
        self::STAGE_SUBMITTED => 'Bid Submitted',
        self::STAGE_PRELIMINARY => 'Bid Opening / Preliminary Examination',
        self::STAGE_EVALUATION => 'Bid Evaluation',
        self::STAGE_POST_QUALIFICATION => 'Post-Qualification',
        self::STAGE_RECOMMENDATION => 'BAC Recommendation',
        self::STAGE_AWARD_APPROVAL => 'Award Approval',
        self::STAGE_NOTICE_OF_AWARD => 'Notice of Award',
        self::STAGE_CONTRACT_SIGNED => 'Contract Signed',
        self::STAGE_NOTICE_TO_PROCEED => 'Notice to Proceed',
    ];

    public const OUTCOME_DISQUALIFIED = 'disqualified';

    public const OUTCOME_NOT_AWARDED = 'not_awarded';

    public const OUTCOME_AWARDED = 'awarded';

    public const OUTCOME_FAILED_BIDDING = 'failed_bidding';

    public const OUTCOME_NOT_SUBMITTED = 'not_submitted';

    /** Steps that prove the bid got past preliminary examination. */
    private const STEPS_PAST_PRELIMINARY = [
        Bid::STEP_DOCUMENTS_VALIDATED,
        Bid::STEP_FOR_BAC_EVALUATION,
        Bid::STEP_APPROVED,
        Bid::STEP_EVALUATED,
        Bid::STEP_POST_QUALIFICATION,
        Bid::STEP_POST_QUALIFIED,
        Bid::STEP_RECOMMENDED,
        Bid::STEP_AWARDED,
        Bid::STEP_NOTICE_OF_AWARD,
        Bid::STEP_CONTRACT_SIGNED,
        Bid::STEP_NOTICE_TO_PROCEED,
        Bid::STEP_PROJECT_COMPLETED,
    ];

    /** Legacy steps that were only ever set once an award was declared. */
    private const STEPS_AWARDED = [
        Bid::STEP_AWARDED,
        Bid::STEP_NOTICE_OF_AWARD,
        Bid::STEP_CONTRACT_SIGNED,
        Bid::STEP_NOTICE_TO_PROCEED,
        Bid::STEP_PROJECT_COMPLETED,
    ];

    private ?array $facts = null;

    public function __construct(private readonly Bid $bid) {}

    public static function for(Bid $bid): self
    {
        return new self($bid);
    }

    /**
     * Raw recorded facts, used by both the tracker and BidWorkflow guards.
     */
    public function facts(): array
    {
        if ($this->facts !== null) {
            return $this->facts;
        }

        $bid = $this->bid;
        $step = (string) ($bid->workflow_step ?: Bid::STEP_SUBMITTED);
        $rawWorkflowUpdatedAt = $bid->getRawOriginal('workflow_step_updated_at');
        $futureWorkflowUpdate = $rawWorkflowUpdatedAt !== null
            && \Carbon\CarbonImmutable::parse($rawWorkflowUpdatedAt, config('app.timezone', 'Asia/Manila'))
                ->greaterThan(app(ProcurementClock::class)->now());
        if ($futureWorkflowUpdate) $step = $this->workflowStepAsOf($bid);

        $prelimPassed = $bid->documents_validated_at !== null
            || (! $futureWorkflowUpdate && $bid->eligibility_status === Bid::ELIGIBILITY_VALID)
            || in_array($step, self::STEPS_PAST_PRELIMINARY, true)
            || (! $futureWorkflowUpdate && in_array($bid->status, ['approved', 'evaluated', 'awarded'], true));
        $award = $bid->award;
        $hasValidAward = $award !== null && $award->created_at !== null && in_array($award->status, [Award::STATUS_VALID, 'active'], true);

        // Award approval: an explicit HOPE decision, or (legacy) a declared award.
        $awardDecision = $futureWorkflowUpdate && $bid->award_decision_at === null ? null : $bid->award_decision;
        $awardDecisionAt = $bid->award_decision_at;
        if ($awardDecision === null && ($hasValidAward || in_array($step, self::STEPS_AWARDED, true) || (! $futureWorkflowUpdate && $bid->status === 'awarded'))) {
            $awardDecision = Bid::AWARD_DECISION_APPROVED;
            $awardDecisionAt = $bid->awarded_at ?? $award?->created_at;
        }
        $awardApproved = $awardDecision === Bid::AWARD_DECISION_APPROVED;

        $postQualFailed = $bid->post_qualification_result === Bid::POST_QUALIFICATION_FAILED
            && (! $futureWorkflowUpdate || $bid->post_qualification_completed_at !== null);

        $disqualified = ! $awardApproved && (
            $step === Bid::STEP_DISQUALIFIED
            || $bid->disqualified_at !== null
            || $postQualFailed
            || $bid->eligibility_status === Bid::ELIGIBILITY_INVALID
            // Legacy admin "reject" only flipped status.
            || ($bid->status === 'rejected' && in_array($step, [Bid::STEP_SUBMITTED, Bid::STEP_PENDING_VALIDATION], true))
        );

        $disqualifiedStage = null;
        if ($disqualified) {
            $disqualifiedStage = $bid->disqualified_stage ?: match (true) {
                $postQualFailed || $bid->post_qualification_at !== null => self::STAGE_POST_QUALIFICATION,
                ! $prelimPassed => self::STAGE_PRELIMINARY,
                default => self::STAGE_EVALUATION,
            };
        }

        $project = $bid->project;
        $competitiveOpening = $project?->requiresRecordedBidOpening() ?? true;
        $awardedToOther = false;
        if (! $awardApproved && $project !== null) {
            $awardedToOther = $project->awards
                ->contains(fn (Award $projectAward) => $projectAward->bid_id !== $bid->id
                    && in_array($projectAward->status, [Award::STATUS_VALID, 'active'], true));
        }

        $notAwarded = ! $disqualified && ! $awardApproved && (
            $step === Bid::STEP_NOT_AWARDED
            || $awardDecision === Bid::AWARD_DECISION_DISAPPROVED
            || $awardedToOther
        );

        $projectFailed = $project?->failed_bidding_at !== null && ! $awardApproved;

        // A draft saved before submission became online-only is not a bid until
        // the bidder submits it online.
        $draft = $bid->isDraft();
        $deadline = $project?->bidSubmissionDeadline();
        $notSubmitted = $draft && (($deadline !== null && $deadline->isPast()) || ($project?->bidsAreOpened() ?? false));

        return $this->facts = [
            'step' => $step,
            'draft' => $draft,
            'not_submitted' => $notSubmitted,
            'submitted_at' => $draft ? null : ($bid->submitted_at ?? $bid->created_at),
            'receipt_no' => $bid->receipt_no,
            'submission_channel' => $bid->submission_channel,
            // Legacy projects without a recorded opening but with a decided bid
            // were opened (see the 2026_09_26 migration backfill). RFQ and
            // alternative-mode review starts after the configured deadline and
            // does not require a competitive bid-opening event.
            'bids_opened' => $competitiveOpening
                ? ! $bid->isSealed()
                : ($project?->submissionDeadlinePassed() ?? false),
            'prelim_passed' => $prelimPassed,
            'prelim_passed_at' => $bid->documents_validated_at
                ?? ($bid->eligibility_status === Bid::ELIGIBILITY_VALID ? $bid->eligibility_reviewed_at : null),
            'evaluation_started_at' => $bid->bac_evaluation_at,
            'evaluated' => $bid->evaluated_at !== null,
            'evaluated_at' => $bid->evaluated_at,
            'post_qualification_started' => $bid->post_qualification_at !== null,
            'post_qualification_at' => $bid->post_qualification_at,
            'post_qualification_result' => $bid->post_qualification_result,
            'post_qualification_completed_at' => $bid->post_qualification_completed_at,
            'recommended' => $bid->bac_recommended_at !== null,
            'recommended_at' => $bid->bac_recommended_at,
            'award_decision' => $awardDecision,
            'award_decision_at' => $awardDecisionAt,
            'award_approved' => $awardApproved,
            'notice_of_award_at' => $bid->notice_of_award_at,
            'performance_security_at' => $bid->performance_security_at,
            'contract_signed_at' => $bid->contract_signed_at,
            'notice_to_proceed_at' => $bid->notice_to_proceed_at,
            'disqualified' => $disqualified,
            'disqualified_stage' => $disqualifiedStage,
            'disqualified_at' => $bid->disqualified_at ?? ($bid->eligibility_status === Bid::ELIGIBILITY_INVALID ? $bid->eligibility_reviewed_at : null),
            'not_awarded' => $notAwarded,
            'project_failed' => $projectFailed,
            'awarded' => $awardApproved && $bid->notice_of_award_at !== null,
        ];
    }

    public function isClosed(): bool
    {
        $facts = $this->facts();

        return $facts['not_submitted'] || $facts['disqualified'] || $facts['not_awarded'] || $facts['project_failed'];
    }

    /**
     * Full tracker payload for the bidder view and JSON endpoint.
     */
    public function toArray(): array
    {
        $facts = $this->facts();
        $stages = $this->stages();
        $current = $this->currentStatus($stages);

        $payload = [
            'bid_id' => $this->bid->id,
            'current' => $current,
            'stages' => $stages,
            'outcome' => $this->outcome(),
            'project_outcome' => $this->projectOutcome(),
            'is_closed' => $this->isClosed(),
            'awarded' => $facts['awarded'],
        ];

        $payload['signature'] = md5(json_encode($payload));

        return $payload;
    }

    public function stages(): array
    {
        $facts = $this->facts();
        $rows = [];

        foreach ($this->stageRecords() as $key => $record) {
            $rows[] = ['key' => $key, 'label' => self::STAGES[$key]] + $record;
        }

        // Index of the last stage that has an actual record.
        $lastRecorded = -1;
        foreach ($rows as $index => $row) {
            if (in_array($row['recorded'], ['done', 'failed', 'in_progress'], true)) {
                $lastRecorded = $index;
            }
        }

        $closed = $this->isClosed();
        $currentAssigned = false;
        $result = [];

        foreach ($rows as $index => $row) {
            $state = match ($row['recorded']) {
                'done' => 'done',
                'failed' => 'failed',
                'in_progress' => 'current',
                default => null,
            };

            if ($state === 'current') {
                $currentAssigned = true;
            }

            if ($state === null) {
                if ($index < $lastRecorded) {
                    $state = 'unrecorded';
                } elseif ($closed) {
                    // The bid left the process; later stages never applied to it.
                    continue;
                } elseif (! $currentAssigned && $this->canBeCurrent($row['key'])) {
                    $state = 'current';
                    $currentAssigned = true;
                } else {
                    $state = 'pending';
                }
            }

            $result[] = [
                'key' => $row['key'],
                'label' => $row['label'],
                'state' => $state,
                'description' => $this->describe($row['key'], $state),
                'at' => $row['at'] ? $this->formatTime($row['at']) : null,
                'at_iso' => $row['at']?->toIso8601String(),
                'note' => $row['note'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Recorded status of each applicable stage, before layout decisions.
     */
    private function stageRecords(): array
    {
        $f = $this->facts();
        $dqStage = $f['disqualified_stage'];
        $bid = $this->bid;

        $records = [
            self::STAGE_SUBMITTED => match (true) {
                $f['not_submitted'] => ['recorded' => 'failed', 'at' => null],
                $f['draft'] => ['recorded' => 'in_progress', 'at' => $bid->created_at, 'note' => 'Draft saved'],
                default => [
                    'recorded' => 'done',
                    'at' => $f['submitted_at'],
                    'note' => $f['receipt_no'] ? 'Receipt No. '.$f['receipt_no'] : null,
                ],
            },
        ];

        $records[self::STAGE_PRELIMINARY] = match (true) {
            $dqStage === self::STAGE_PRELIMINARY => ['recorded' => 'failed', 'at' => $f['disqualified_at']],
            $f['prelim_passed'] => ['recorded' => 'done', 'at' => $f['prelim_passed_at']],
            default => ['recorded' => null, 'at' => null],
        };

        $records[self::STAGE_EVALUATION] = match (true) {
            $dqStage === self::STAGE_EVALUATION => ['recorded' => 'failed', 'at' => $f['disqualified_at']],
            $f['evaluated'] => ['recorded' => 'done', 'at' => $f['evaluated_at']],
            default => ['recorded' => null, 'at' => null],
        };

        // Post-qualification only exists for the bidder(s) the BAC actually
        // post-qualified; it is never shown as an upcoming step for others.
        if ($f['post_qualification_started'] || $dqStage === self::STAGE_POST_QUALIFICATION) {
            $records[self::STAGE_POST_QUALIFICATION] = match (true) {
                $dqStage === self::STAGE_POST_QUALIFICATION => [
                    'recorded' => 'failed',
                    'at' => $f['post_qualification_completed_at'] ?? $f['disqualified_at'],
                ],
                $f['post_qualification_result'] === Bid::POST_QUALIFICATION_PASSED => [
                    'recorded' => 'done',
                    'at' => $f['post_qualification_completed_at'],
                ],
                default => [
                    'recorded' => 'in_progress',
                    'at' => $f['post_qualification_at'],
                    'note' => 'Started',
                ],
            };
        }

        $records[self::STAGE_RECOMMENDATION] = $f['recommended']
            ? ['recorded' => 'done', 'at' => $f['recommended_at']]
            : ['recorded' => null, 'at' => null];

        $records[self::STAGE_AWARD_APPROVAL] = match ($f['award_decision']) {
            Bid::AWARD_DECISION_APPROVED => ['recorded' => 'done', 'at' => $f['award_decision_at']],
            Bid::AWARD_DECISION_DISAPPROVED => ['recorded' => 'failed', 'at' => $f['award_decision_at']],
            default => ['recorded' => null, 'at' => null],
        };

        $records[self::STAGE_NOTICE_OF_AWARD] = $f['notice_of_award_at']
            ? ['recorded' => 'done', 'at' => $f['notice_of_award_at']]
            : ['recorded' => null, 'at' => null];

        $records[self::STAGE_CONTRACT_SIGNED] = $f['contract_signed_at']
            ? [
                'recorded' => 'done',
                'at' => $f['contract_signed_at'],
                'note' => $f['performance_security_at']
                    ? 'Performance security posted '.$f['performance_security_at']->format('M d, Y')
                    : null,
            ]
            : ['recorded' => null, 'at' => null];

        $records[self::STAGE_NOTICE_TO_PROCEED] = $f['notice_to_proceed_at']
            ? ['recorded' => 'done', 'at' => $f['notice_to_proceed_at']]
            : ['recorded' => null, 'at' => null];

        return $records;
    }

    /**
     * A stage becomes "current" only when this bidder is actually positioned
     * for it. Recommendation onward is bidder-specific: a bid that was merely
     * evaluated is not "awaiting recommendation".
     */
    private function canBeCurrent(string $stage): bool
    {
        $f = $this->facts();

        return match ($stage) {
            self::STAGE_PRELIMINARY => true,
            self::STAGE_EVALUATION => $f['prelim_passed'],
            self::STAGE_RECOMMENDATION => $f['post_qualification_result'] === Bid::POST_QUALIFICATION_PASSED,
            self::STAGE_AWARD_APPROVAL => $f['recommended'],
            self::STAGE_NOTICE_OF_AWARD => $f['award_approved'],
            self::STAGE_CONTRACT_SIGNED => $f['notice_of_award_at'] !== null,
            self::STAGE_NOTICE_TO_PROCEED => $f['contract_signed_at'] !== null,
            default => false,
        };
    }

    private function describe(string $stage, string $state): string
    {
        if ($state === 'unrecorded') {
            return 'No record of this step was entered in the system.';
        }

        if ($state === 'failed') {
            return match ($stage) {
                self::STAGE_PRELIMINARY => 'The bid did not pass the pass/fail check of required documents.',
                self::STAGE_EVALUATION => 'The bid was disqualified during evaluation.',
                self::STAGE_POST_QUALIFICATION => 'The bidder did not pass post-qualification.',
                self::STAGE_AWARD_APPROVAL => 'The Head of the Procuring Entity did not approve the recommendation.',
                self::STAGE_SUBMITTED => 'No official bid was submitted online by the submission deadline. The saved draft was not a bid.',
                default => 'This step was not completed.',
            };
        }

        if ($state === 'done') {
            return match ($stage) {
                self::STAGE_SUBMITTED => $this->facts()['submission_channel'] === Bid::CHANNEL_MANUAL
                    ? 'The BAC Secretariat recorded receipt of your sealed bid.'
                    : 'Your bid was officially submitted.',
                self::STAGE_PRELIMINARY => 'The BAC opened the bid and the required documents passed the pass/fail check.',
                self::STAGE_EVALUATION => 'The BAC evaluated the bid according to the project\'s award criteria.',
                self::STAGE_POST_QUALIFICATION => 'The BAC verified the bidder\'s documents and qualifications.',
                self::STAGE_RECOMMENDATION => 'The BAC recommended this bid for award.',
                self::STAGE_AWARD_APPROVAL => 'The Head of the Procuring Entity approved the award.',
                self::STAGE_NOTICE_OF_AWARD => 'The Notice of Award was issued to you.',
                self::STAGE_CONTRACT_SIGNED => 'The contract was signed after the required documents and performance security were completed.',
                self::STAGE_NOTICE_TO_PROCEED => 'The Notice to Proceed was issued.',
                default => '',
            };
        }

        if ($state === 'current') {
            return match ($stage) {
                self::STAGE_SUBMITTED => 'Draft only - this is not an official bid yet. Open the project and submit your bid online before the deadline.',
                self::STAGE_PRELIMINARY => $this->facts()['bids_opened']
                    ? 'The BAC opened the bids and is checking your documents against the project requirements.'
                    : 'Your bid stays sealed until the scheduled bid opening.',
                self::STAGE_EVALUATION => 'The BAC is evaluating qualified bids.',
                self::STAGE_POST_QUALIFICATION => 'The BAC is verifying your documents and qualifications.',
                self::STAGE_RECOMMENDATION => 'Waiting for the BAC resolution recommending award.',
                self::STAGE_AWARD_APPROVAL => 'Waiting for the Head of the Procuring Entity to act on the recommendation.',
                self::STAGE_NOTICE_OF_AWARD => 'Award approved. Waiting for the Notice of Award to be issued.',
                self::STAGE_CONTRACT_SIGNED => 'Submit the performance security and required documents for contract signing.',
                self::STAGE_NOTICE_TO_PROCEED => 'Waiting for the Notice to Proceed to be issued.',
                default => '',
            };
        }

        return match ($stage) {
            self::STAGE_PRELIMINARY => 'Bid opening and pass/fail check of required documents.',
            self::STAGE_EVALUATION => 'Evaluation of qualified bids against the award criteria.',
            self::STAGE_RECOMMENDATION, self::STAGE_AWARD_APPROVAL, self::STAGE_NOTICE_OF_AWARD,
            self::STAGE_CONTRACT_SIGNED, self::STAGE_NOTICE_TO_PROCEED => 'Applies only to the bidder recommended for award.',
            default => '',
        };
    }

    /**
     * The one status list used by the admin table, its filter, exports and
     * badges. Each value is the furthest stage the bid actually reached.
     */
    public const ADMIN_STAGES = [
        'draft' => 'Draft (Not Official)',
        'submitted' => 'Submitted',
        self::STAGE_PRELIMINARY => 'Preliminary Examination',
        self::STAGE_EVALUATION => 'Bid Evaluation',
        self::STAGE_POST_QUALIFICATION => 'Post-Qualification',
        self::STAGE_RECOMMENDATION => 'BAC Recommendation',
        self::STAGE_AWARD_APPROVAL => 'Award Approval',
        self::STAGE_NOTICE_OF_AWARD => 'Notice of Award Issued',
        self::STAGE_CONTRACT_SIGNED => 'Contract Signed',
        self::STAGE_NOTICE_TO_PROCEED => 'Notice to Proceed Issued',
        self::OUTCOME_DISQUALIFIED => 'Disqualified',
        self::OUTCOME_NOT_AWARDED => 'Not Awarded',
        self::OUTCOME_FAILED_BIDDING => 'Failed Bidding',
        self::OUTCOME_NOT_SUBMITTED => 'Not Submitted',
    ];

    /** Old ?status= filter values, mapped onto the stages they used to cover. */
    public const LEGACY_FILTER_ALIASES = [
        'pending' => ['submitted', self::STAGE_PRELIMINARY],
        'approved' => [self::STAGE_EVALUATION, self::STAGE_POST_QUALIFICATION, self::STAGE_RECOMMENDATION],
        'rejected' => [self::OUTCOME_DISQUALIFIED],
        'awarded' => [self::STAGE_AWARD_APPROVAL, self::STAGE_NOTICE_OF_AWARD, self::STAGE_CONTRACT_SIGNED, self::STAGE_NOTICE_TO_PROCEED],
    ];

    /**
     * Admin-facing stage key for this bid (one of ADMIN_STAGES).
     */
    public function adminStage(): string
    {
        $f = $this->facts();

        return match (true) {
            $f['not_submitted'] => self::OUTCOME_NOT_SUBMITTED,
            $f['draft'] => 'draft',
            $f['disqualified'] => self::OUTCOME_DISQUALIFIED,
            $f['not_awarded'] => self::OUTCOME_NOT_AWARDED,
            $f['project_failed'] => self::OUTCOME_FAILED_BIDDING,
            // Earlier review gates take priority over stale downstream award fields.
            ! $f['bids_opened'] => 'submitted',
            ! $f['prelim_passed'] => self::STAGE_PRELIMINARY,
            $this->bid->project?->mode()->isCompetitive() && $this->bid->isFinancialSealed() => self::STAGE_EVALUATION,
            ! $f['evaluated'] => self::STAGE_EVALUATION,
            $f['post_qualification_started'] && $f['post_qualification_result'] !== Bid::POST_QUALIFICATION_PASSED => self::STAGE_POST_QUALIFICATION,
            $f['post_qualification_result'] === Bid::POST_QUALIFICATION_PASSED && ! $f['recommended'] => self::STAGE_POST_QUALIFICATION,
            $f['recommended'] && ! $f['award_approved'] => self::STAGE_RECOMMENDATION,
            $f['notice_to_proceed_at'] !== null => self::STAGE_NOTICE_TO_PROCEED,
            $f['contract_signed_at'] !== null => self::STAGE_CONTRACT_SIGNED,
            $f['awarded'] => self::STAGE_NOTICE_OF_AWARD,
            $f['award_approved'] => self::STAGE_AWARD_APPROVAL,
            $f['recommended'] => self::STAGE_RECOMMENDATION,
            $f['post_qualification_started'] => self::STAGE_POST_QUALIFICATION,
            $f['prelim_passed'] => self::STAGE_EVALUATION,
            $f['bids_opened'] => self::STAGE_PRELIMINARY,
            default => 'submitted',
        };
    }

    /**
     * Status for admin tables, filters, exports and badges.
     *
     * @return array{key: string, label: string, class: string}
     */
    private function workflowStepAsOf(Bid $bid): string
    {
        return match (true) {
            $bid->project_completed_at !== null => Bid::STEP_PROJECT_COMPLETED,
            $bid->notice_to_proceed_at !== null => Bid::STEP_NOTICE_TO_PROCEED,
            $bid->contract_signed_at !== null => Bid::STEP_CONTRACT_SIGNED,
            $bid->notice_of_award_at !== null => Bid::STEP_NOTICE_OF_AWARD,
            $bid->award_decision_at !== null && $bid->award_decision === Bid::AWARD_DECISION_APPROVED => Bid::STEP_AWARDED,
            $bid->award_decision_at !== null && $bid->award_decision === Bid::AWARD_DECISION_DISAPPROVED => Bid::STEP_NOT_AWARDED,
            $bid->bac_recommended_at !== null => Bid::STEP_RECOMMENDED,
            $bid->post_qualification_completed_at !== null => Bid::STEP_POST_QUALIFIED,
            $bid->post_qualification_at !== null => Bid::STEP_POST_QUALIFICATION,
            $bid->evaluated_at !== null => Bid::STEP_EVALUATED,
            $bid->bac_evaluation_at !== null => Bid::STEP_FOR_BAC_EVALUATION,
            $bid->documents_validated_at !== null => Bid::STEP_DOCUMENTS_VALIDATED,
            default => Bid::STEP_SUBMITTED,
        };
    }
    public function adminStatus(): array
    {
        $stage = $this->adminStage();
        $label = self::ADMIN_STAGES[$stage];

        if ($stage === self::STAGE_PRELIMINARY && $this->bid->project !== null && ! $this->bid->project->mode()->isCompetitive()) {
            $label = ucfirst($this->bid->project->mode()->submissionNoun(true)).' Review';
        }

        return ['key' => $stage, 'label' => $label, 'class' => self::pillClass($stage)];
    }

    /**
     * Maps a stage onto the existing .admin-bids-status-pill.is-* styles.
     */
    public static function pillClass(string $stage): string
    {
        return match ($stage) {
            self::OUTCOME_DISQUALIFIED => 'disqualified',
            self::OUTCOME_NOT_AWARDED, self::OUTCOME_FAILED_BIDDING, self::OUTCOME_NOT_SUBMITTED => 'default',
            self::STAGE_AWARD_APPROVAL, self::STAGE_NOTICE_OF_AWARD, self::STAGE_CONTRACT_SIGNED, self::STAGE_NOTICE_TO_PROCEED => 'awarded',
            self::STAGE_EVALUATION, self::STAGE_POST_QUALIFICATION, self::STAGE_RECOMMENDATION => 'validated',
            default => 'pending',
        };
    }

    /** Badge tone in the design system (ui-badge--*) for an admin stage key. */
    public static function tone(string $stage): string
    {
        return match (self::pillClass($stage)) {
            'disqualified' => 'danger',
            'default' => 'neutral',
            'awarded' => 'success',
            'validated' => 'info',
            default => 'warning',
        };
    }

    /**
     * @return array<int, string>|null stage keys for a ?status= filter value, null for "all"
     */
    public static function stagesForFilter(string $value): ?array
    {
        return match (true) {
            $value === '' => null,
            array_key_exists($value, self::ADMIN_STAGES) => [$value],
            array_key_exists($value, self::LEGACY_FILTER_ALIASES) => self::LEGACY_FILTER_ALIASES[$value],
            default => null,
        };
    }

    private function currentStatus(array $stages): array
    {
        $f = $this->facts();
        $stage = $this->adminStage();

        $tone = match ($stage) {
            self::OUTCOME_DISQUALIFIED => 'danger',
            self::OUTCOME_NOT_AWARDED, self::OUTCOME_NOT_SUBMITTED => 'muted',
            self::OUTCOME_FAILED_BIDDING, 'draft' => 'warning',
            self::STAGE_NOTICE_OF_AWARD, self::STAGE_CONTRACT_SIGNED, self::STAGE_NOTICE_TO_PROCEED => 'success',
            default => 'active',
        };

        $current = collect($stages)->firstWhere('state', 'current');

        // Bidder-facing wording; adminStage() is the shared classification.
        $label = match ($stage) {
            'draft' => 'Draft - Not Yet Submitted to the BAC',
            self::OUTCOME_DISQUALIFIED, self::OUTCOME_NOT_AWARDED, self::OUTCOME_FAILED_BIDDING, self::OUTCOME_NOT_SUBMITTED,
            self::STAGE_NOTICE_OF_AWARD, self::STAGE_CONTRACT_SIGNED, self::STAGE_NOTICE_TO_PROCEED => self::ADMIN_STAGES[$stage],
            default => match ($current['key'] ?? null) {
                self::STAGE_PRELIMINARY => $f['bids_opened'] ? 'Under Preliminary Examination' : 'Submitted - Awaiting Bid Opening',
                self::STAGE_EVALUATION => 'Under Evaluation',
                self::STAGE_POST_QUALIFICATION => 'Under Post-Qualification',
                self::STAGE_RECOMMENDATION => 'Post-Qualified',
                self::STAGE_AWARD_APPROVAL => 'Recommended for Award',
                self::STAGE_NOTICE_OF_AWARD => 'Award Approved',
                default => $f['evaluated'] ? 'Evaluated - Awaiting BAC Results' : 'In Progress',
            },
        };

        return [
            'key' => $current['key'] ?? $stage,
            'label' => $label,
            'tone' => $tone,
            'stage' => $stage,
            'stage_label' => self::ADMIN_STAGES[$stage],
        ];
    }

    public function outcome(): ?array
    {
        $f = $this->facts();
        $bid = $this->bid;

        if ($f['not_submitted']) {
            return [
                'key' => self::OUTCOME_NOT_SUBMITTED,
                'tone' => 'muted',
                'title' => 'No Official Bid Received',
                'message' => 'Your bid was not submitted online by the submission deadline. The saved draft is not a bid.',
                'at' => null,
            ];
        }

        if ($f['disqualified']) {
            $stageLabel = self::STAGES[$f['disqualified_stage']] ?? 'Evaluation';

            return [
                'key' => self::OUTCOME_DISQUALIFIED,
                'tone' => 'danger',
                'title' => 'Disqualified at '.$stageLabel,
                'message' => $this->publicDisqualificationReason($f['disqualified_stage']),
                'at' => $f['disqualified_at'] ? $this->formatTime($f['disqualified_at']) : null,
            ];
        }

        if ($f['not_awarded']) {
            $disapproved = $f['award_decision'] === Bid::AWARD_DECISION_DISAPPROVED;

            return [
                'key' => self::OUTCOME_NOT_AWARDED,
                'tone' => 'muted',
                'title' => 'Not Awarded',
                'message' => $disapproved
                    ? 'The Head of the Procuring Entity did not approve the BAC recommendation for your bid. Your bid was not disqualified.'
                    : 'The contract was awarded to another bidder. Your bid was not disqualified.',
                'at' => $disapproved && $f['award_decision_at']
                    ? $this->formatTime($f['award_decision_at'])
                    : ($f['step'] === Bid::STEP_NOT_AWARDED && $bid->workflow_step_updated_at ? $this->formatTime($bid->workflow_step_updated_at) : null),
            ];
        }

        if ($f['awarded']) {
            return [
                'key' => self::OUTCOME_AWARDED,
                'tone' => 'success',
                'title' => 'Awarded',
                'message' => 'The award was approved and the Notice of Award was issued to you.',
                'at' => $this->formatTime($f['notice_of_award_at']),
            ];
        }

        return null;
    }

    public function projectOutcome(): ?array
    {
        $project = $this->bid->project;

        if (! $this->facts()['project_failed'] || $project === null) {
            return null;
        }

        $rebid = $project->rebidProject;

        return [
            'key' => self::OUTCOME_FAILED_BIDDING,
            'tone' => 'warning',
            'title' => 'Failed Bidding',
            'message' => 'The BAC declared a failure of bidding for this project. No contract will be awarded from this round.',
            'reason' => $project->failed_bidding_reason,
            'at' => $this->formatTime($project->failed_bidding_at),
            'rebid' => $rebid ? [
                'title' => $rebid->title,
                'url' => route('public.procurement.show', $rebid),
            ] : null,
        ];
    }

    /**
     * Only the bidder-facing rejection_reason is disclosed; internal BAC notes
     * (bids.notes) are never shown to bidders.
     */
    private function publicDisqualificationReason(?string $stage): string
    {
        $reason = trim((string) $this->bid->rejection_reason);

        if ($reason !== '') {
            return $reason;
        }

        return match ($stage) {
            self::STAGE_PRELIMINARY => 'The BAC found that your bid did not comply with the required documents during preliminary examination. Please contact the BAC Secretariat for the written notice.',
            self::STAGE_POST_QUALIFICATION => 'The BAC found that your bid did not pass post-qualification. Please contact the BAC Secretariat for the written notice.',
            default => 'The BAC disqualified your bid during evaluation. Please contact the BAC Secretariat for the written notice.',
        };
    }

    private function formatTime(CarbonInterface $time): string
    {
        return $time->copy()
            ->timezone(config('bac-office.display_timezone', config('app.timezone')))
            ->format('M d, Y · h:i A');
    }
}
