<?php

namespace App\Support;

use App\Events\BidWorkflowUpdated;
use App\Models\Award;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\BidTracking;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of bid milestones. Every action:
 *  - checks the actor's role (BAC actions vs. HOPE-level actions),
 *  - checks the bid is actually positioned for it (no skipping stages, no
 *    advancing every bidder),
 *  - updates the bid's milestone columns (read by BidProgress), and
 *  - appends one structured BidTracking event (read by BidHistory),
 * so the admin table, the review modal and the bidder track always agree.
 */
class BidWorkflow
{
    public const PASS_PRELIMINARY = 'pass_preliminary';

    public const FAIL_PRELIMINARY = 'fail_preliminary';

    public const START_EVALUATION = 'start_evaluation';

    public const EVALUATE = 'evaluate';

    public const FAIL_EVALUATION = 'fail_evaluation';

    public const START_POST_QUALIFICATION = 'start_post_qualification';

    public const PASS_POST_QUALIFICATION = 'pass_post_qualification';

    public const FAIL_POST_QUALIFICATION = 'fail_post_qualification';

    public const RECOMMEND = 'recommend';

    public const APPROVE_AWARD = 'approve_award';

    public const DISAPPROVE_AWARD = 'disapprove_award';

    public const NOTICE_OF_AWARD = 'notice_of_award';

    public const CONTRACT_SIGNED = 'contract_signed';

    public const NOTICE_TO_PROCEED = 'notice_to_proceed';

    public const DISQUALIFY = 'disqualify';

    /** BAC Secretariat logs receipt of a sealed bid on a manual-submission project. */
    public const RECORD_MANUAL_RECEIPT = 'record_manual_receipt';

    /** Users who may record a binding BAC/HoPE workflow decision. */
    private const DECISION_ROLES = ['admin'];

    /** Actions reserved for the Head of the Procuring Entity's office. */
    private const HOPE_ROLES = ['admin'];

    /**
     * label: shown to reviewers; stage/decision: stored on the event.
     */
    public const ACTION_DEFINITIONS = [
        self::PASS_PRELIMINARY => ['label' => 'Passed Preliminary Examination', 'stage' => BidProgress::STAGE_PRELIMINARY, 'decision' => 'passed', 'adverse' => false, 'roles' => self::DECISION_ROLES],
        self::FAIL_PRELIMINARY => ['label' => 'Failed Preliminary Examination', 'stage' => BidProgress::STAGE_PRELIMINARY, 'decision' => 'failed', 'adverse' => true, 'roles' => self::DECISION_ROLES],
        self::START_EVALUATION => ['label' => 'Start Detailed Evaluation', 'stage' => BidProgress::STAGE_EVALUATION, 'decision' => 'started', 'adverse' => false, 'roles' => self::DECISION_ROLES],
        self::EVALUATE => ['label' => 'Record Detailed Evaluation', 'stage' => BidProgress::STAGE_EVALUATION, 'decision' => 'passed', 'adverse' => false, 'roles' => self::DECISION_ROLES],
        self::FAIL_EVALUATION => ['label' => 'Fail Detailed Evaluation', 'stage' => BidProgress::STAGE_EVALUATION, 'decision' => 'failed', 'adverse' => true, 'roles' => self::DECISION_ROLES],
        self::START_POST_QUALIFICATION => ['label' => 'Start Post-Qualification', 'stage' => BidProgress::STAGE_POST_QUALIFICATION, 'decision' => 'started', 'adverse' => false, 'roles' => self::DECISION_ROLES],
        self::PASS_POST_QUALIFICATION => ['label' => 'Post-Qualified', 'stage' => BidProgress::STAGE_POST_QUALIFICATION, 'decision' => 'passed', 'adverse' => false, 'roles' => self::DECISION_ROLES],
        self::FAIL_POST_QUALIFICATION => ['label' => 'Post-Disqualified', 'stage' => BidProgress::STAGE_POST_QUALIFICATION, 'decision' => 'failed', 'adverse' => true, 'roles' => self::DECISION_ROLES],
        self::RECOMMEND => ['label' => 'Recommend for Award', 'stage' => BidProgress::STAGE_RECOMMENDATION, 'decision' => 'recommended', 'adverse' => false, 'roles' => self::DECISION_ROLES],
        self::APPROVE_AWARD => ['label' => 'HoPE Approved the Award', 'stage' => BidProgress::STAGE_AWARD_APPROVAL, 'decision' => 'approved', 'adverse' => false, 'roles' => self::HOPE_ROLES],
        self::DISAPPROVE_AWARD => ['label' => 'HoPE Disapproved the Recommendation', 'stage' => BidProgress::STAGE_AWARD_APPROVAL, 'decision' => 'disapproved', 'adverse' => true, 'roles' => self::HOPE_ROLES],
        self::NOTICE_OF_AWARD => ['label' => 'Issue Notice of Award', 'stage' => BidProgress::STAGE_NOTICE_OF_AWARD, 'decision' => 'issued', 'adverse' => false, 'roles' => self::HOPE_ROLES],
        self::CONTRACT_SIGNED => ['label' => 'Record Contract Signing', 'stage' => BidProgress::STAGE_CONTRACT_SIGNED, 'decision' => 'signed', 'adverse' => false, 'roles' => self::HOPE_ROLES],
        self::NOTICE_TO_PROCEED => ['label' => 'Issue Notice to Proceed', 'stage' => BidProgress::STAGE_NOTICE_TO_PROCEED, 'decision' => 'issued', 'adverse' => false, 'roles' => self::HOPE_ROLES],
        self::DISQUALIFY => ['label' => 'Disqualify Bid', 'stage' => null, 'decision' => 'failed', 'adverse' => true, 'roles' => self::DECISION_ROLES],
        self::RECORD_MANUAL_RECEIPT => ['label' => 'Record Receipt of Sealed Bid (BAC Secretariat)', 'stage' => 'submitted', 'decision' => 'submitted', 'adverse' => false, 'roles' => self::DECISION_ROLES],
    ];

    public static function isAdverse(string $action): bool
    {
        return (bool) (self::ACTION_DEFINITIONS[$action]['adverse'] ?? false);
    }

    public static function label(string $action): string
    {
        return self::ACTION_DEFINITIONS[$action]['label'] ?? $action;
    }

    /**
     * @return array<string, string> actions this actor may record on this bid now
     */
    public function availableActions(Bid $bid, ?User $actor = null): array
    {
        return collect(self::ACTION_DEFINITIONS)
            ->filter(fn (array $definition, string $action) => $this->guardError($bid, $action, $actor) === null)
            ->map(fn (array $definition) => $definition['label'])
            ->all();
    }

    public function guardError(Bid $bid, string $action, ?User $actor = null): ?string
    {
        $definition = self::ACTION_DEFINITIONS[$action] ?? null;

        if ($definition === null) {
            return 'Unknown workflow action.';
        }

        if ($actor !== null && ! in_array($actor->role, $definition['roles'], true)) {
            return 'Your role is not authorized to record this decision.';
        }

        $progress = BidProgress::for($bid);
        $f = $progress->facts();

        if ($action === self::RECORD_MANUAL_RECEIPT) {
            return match (true) {
                $bid->submission_channel !== Bid::CHANNEL_MANUAL || ! $f['draft'] => 'Only a manual-submission draft that has not been received can be recorded as received.',
                $bid->project !== null && $bid->project->acceptsElectronicSubmission() => 'This project takes bids online; sealed bids are not received for it.',
                (bool) $bid->project?->bidsAreOpened() => 'Bids for this project were already opened; late bids cannot be received.',
                $bid->project !== null && ! $bid->project->hasPaidBiddingFee($bid->user_id) => 'The bidding documents fee of this bidder is not recorded yet. Record the payment first.',
                default => null,
            };
        }

        if ($f['draft']) {
            return $bid->submission_channel === Bid::CHANNEL_MANUAL
                ? 'This is a draft record, not an official bid. The BAC Secretariat must first record receipt of the sealed bid.'
                : 'This is an unsubmitted draft, not an official bid. The bidder must submit it before the deadline.';
        }

        if ($progress->isClosed()) {
            return 'This bid is closed (disqualified, not awarded, or failed bidding). No further decisions can be recorded.';
        }

        if (! $f['bids_opened']) {
            return $bid->project?->mode()->isCompetitive()
                ? 'Bids for this project have not been opened yet.'
                : 'The quotation/offer deadline has not passed yet.';
        }

        if ($action === self::START_POST_QUALIFICATION
            && ($postQualificationBlocker = $this->postQualificationOrderBlocker($bid)) !== null) {
            return $postQualificationBlocker;
        }
        return match ($action) {
            self::PASS_PRELIMINARY, self::FAIL_PRELIMINARY => $f['prelim_passed'] ? 'Preliminary examination is already recorded for this bid.' : null,
            self::START_EVALUATION => match (true) {
                ! $f['prelim_passed'] => 'The bid must pass preliminary examination first.',
                $f['evaluated'] || $f['evaluation_started_at'] !== null => 'Evaluation is already started for this bid.',
                default => null,
            },
            self::EVALUATE => match (true) {
                ! $f['prelim_passed'] => 'The bid must pass preliminary examination before evaluation.',
                $f['evaluation_started_at'] === null => 'Start detailed evaluation before recording its result.',
                $f['evaluated'] => 'Evaluation is already recorded for this bid.',
                $bid->isFinancialSealed() => 'Record the authorized financial opening before completing bid evaluation.',
                default => null,
            },
            self::FAIL_EVALUATION => match (true) {
                ! $f['prelim_passed'] => 'Use "Failed Preliminary Examination" for a bid that has not passed preliminary examination.',
                $f['evaluation_started_at'] === null => 'Start detailed evaluation before recording its result.',
                $f['post_qualification_started'] => 'Use "Failed Post-Qualification" for a bid under post-qualification.',
                $f['recommended'] => 'This bid is already recommended for award.',
                default => null,
            },
            self::START_POST_QUALIFICATION => match (true) {
                ! $f['evaluated'] => 'Record the bid evaluation before post-qualification.',
                $f['post_qualification_started'] => 'Post-qualification is already recorded for this bid.',
                $this->projectHasOtherBid($bid, fn (Bid $other) => $other->post_qualification_at !== null && $other->post_qualification_result === null) => 'Another bidder is currently under post-qualification for this project.',
                $this->projectHasActiveRecommendation($bid) => 'Another bidder has already been recommended for award.',
                default => null,
            },
            self::PASS_POST_QUALIFICATION, self::FAIL_POST_QUALIFICATION => (! $f['post_qualification_started'] || $f['post_qualification_result'] !== null)
                ? 'Post-qualification must be in progress for this bid.'
                : null,
            self::RECOMMEND => match (true) {
                $this->awardStageBlocker($bid, $f) !== null => $this->awardStageBlocker($bid, $f),
                $f['recommended'] => 'This bid is already recommended for award.',
                $this->projectHasActiveRecommendation($bid) => 'Another bidder has already been recommended for award.',
                default => null,
            },
            self::APPROVE_AWARD => match (true) {
                $this->awardStageBlocker($bid, $f) !== null => $this->awardStageBlocker($bid, $f),
                ! $f['recommended'] || $f['award_decision'] !== null => 'The HoPE can only act on a pending BAC recommendation.',
                $this->projectHasActiveAwardForOtherBid($bid) => 'This project already has an award in force for another bidder. Cancel that award in Awards & Contracts before approving a different bid.',
                default => null,
            },
            self::DISAPPROVE_AWARD => match (true) {
                $this->awardStageBlocker($bid, $f) !== null => $this->awardStageBlocker($bid, $f),
                ! $f['recommended'] || $f['award_decision'] !== null => 'The HoPE can only act on a pending BAC recommendation.',
                default => null,
            },
            self::NOTICE_OF_AWARD => match (true) {
                $this->awardStageBlocker($bid, $f) !== null => $this->awardStageBlocker($bid, $f),
                ! $f['award_approved'] => 'The award must be approved by the Head of the Procuring Entity first.',
                $f['notice_of_award_at'] !== null => 'The Notice of Award is already recorded.',
                // The HoPE approval already handed off this bid's award record; another bid's is a conflict.
                $this->projectHasActiveAwardForOtherBid($bid) => 'This project already has an award record.',
                default => null,
            },
            self::CONTRACT_SIGNED => match (true) {
                $this->awardStageBlocker($bid, $f) !== null => $this->awardStageBlocker($bid, $f),
                $f['notice_of_award_at'] === null => 'Issue the Notice of Award before recording the contract signing.',
                $f['contract_signed_at'] !== null => 'The contract signing is already recorded.',
                default => null,
            },
            self::NOTICE_TO_PROCEED => match (true) {
                $this->awardStageBlocker($bid, $f) !== null => $this->awardStageBlocker($bid, $f),
                $f['contract_signed_at'] === null => 'Record the contract signing before issuing the Notice to Proceed.',
                $f['notice_to_proceed_at'] !== null => 'The Notice to Proceed is already recorded.',
                default => null,
            },
            self::DISQUALIFY => $f['award_approved'] ? 'An approved award cannot be changed to disqualified here.' : null,
        };
    }

    /**
     * Every award decision depends on a completed technical and financial review.
     * Keep stale downstream fields from skipping an incomplete earlier stage.
     */
    private function awardStageBlocker(Bid $bid, array $facts): ?string
    {
        return match (true) {
            ! $facts['prelim_passed'] => 'Pass preliminary examination before continuing to award decisions.',
            $bid->isFinancialSealed() => 'Open and review the financial bid before continuing to award decisions.',
            ! $facts['evaluated'] => 'Complete the detailed evaluation before continuing to award decisions.',
            $facts['post_qualification_result'] !== Bid::POST_QUALIFICATION_PASSED => 'Only a post-qualified bidder can continue to award decisions.',
            default => null,
        };
    }

    /**
     * @param  array{reason?: ?string, performance_security_at?: ?string, verified_requirements?: array, failed_requirements?: array}  $input
     *
     * @throws ValidationException
     */
    public function apply(Bid $bid, string $action, User $actor, array $input = []): Bid
    {
        // Stage/role check first so a skipped step is reported as such; it is
        // repeated under lock inside the transaction.
        if ($error = $this->guardError($bid->loadMissing(['project.awards', 'award']), $action, $actor)) {
            throw ValidationException::withMessages(['milestone' => $error]);
        }

        $reason = trim((string) ($input['reason'] ?? ''));

        if (self::isAdverse($action) && $reason === '') {
            throw ValidationException::withMessages(['reason' => 'Enter the reason that will be shown to the bidder.']);
        }

        if ($action === self::RECORD_MANUAL_RECEIPT) {
            $this->validateManualReceipt($bid, $input);
        }

        if ($action === self::RECOMMEND) {
            $this->validateResolution($bid, $input);
        }

        $this->assertSupportingDocument($input['supporting_document'] ?? null);

        if ($action === self::CONTRACT_SIGNED && blank($input['performance_security_at'] ?? null)) {
            throw ValidationException::withMessages(['performance_security_at' => 'Enter the date the performance security was posted.']);
        }

        if ($action === self::CONTRACT_SIGNED) {
            if (blank($input['contract_date'] ?? null)) {
                throw ValidationException::withMessages(['contract_date' => 'Enter the date the contract was signed.']);
            }

            $signed = \Illuminate\Support\Carbon::parse($input['contract_date'])->startOfDay();
            if ($signed->isAfter(today()) || ($bid->notice_of_award_at && $signed->lt($bid->notice_of_award_at->copy()->startOfDay()))) {
                throw ValidationException::withMessages(['contract_date' => 'The contract signing date must be on or after the Notice of Award and not in the future.']);
            }

            $securityPosted = \Illuminate\Support\Carbon::parse($input['performance_security_at'])->startOfDay();
            if ($bid->notice_of_award_at && $securityPosted->lt($bid->notice_of_award_at->copy()->startOfDay())) {
                throw ValidationException::withMessages(['performance_security_at' => 'The performance security date must be on or after the Notice of Award.']);
            }
        }

        if ($action === self::NOTICE_OF_AWARD) {
            $this->assertSignedPdf($input['notice_file'] ?? null);
        }

        $this->storedFiles = [];

        $affected = collect();

        try {
            $bid = $this->runTransaction($bid, $action, $actor, $input, $reason, $affected);
        } catch (\Throwable $exception) {
            foreach ($this->storedFiles as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        $affected->each(fn (Bid $changed) => event(new BidWorkflowUpdated($changed->fresh())));

        return $bid->fresh();
    }

    /** Files written during the current apply(), removed if it fails. */
    private array $storedFiles = [];

    private function runTransaction(Bid $bid, string $action, User $actor, array $input, string $reason, Collection &$affected): Bid
    {
        return DB::transaction(function () use ($bid, $action, $actor, $input, $reason, &$affected) {
            /** @var Bid $locked */
            $locked = Bid::with(['project.awards', 'project.requirement', 'award', 'user.bidderDocuments'])
                ->lockForUpdate()
                ->findOrFail($bid->id);

            if ($error = $this->guardError($locked, $action, $actor)) {
                throw ValidationException::withMessages(['milestone' => $error]);
            }

            $details = match ($action) {
                self::PASS_PRELIMINARY, self::FAIL_PRELIMINARY => $this->preliminaryDetails($locked, $action, $input),
                self::START_EVALUATION => ['criteria' => $this->evaluationCriteria($locked)],
                self::EVALUATE, self::FAIL_EVALUATION => $this->evaluationDetails($locked, $action, $input, $reason),
                self::START_POST_QUALIFICATION => ['basis' => trim((string) ($locked->project?->requirement?->qualification_notes ?? ''))],
                self::PASS_POST_QUALIFICATION, self::FAIL_POST_QUALIFICATION => $this->postQualificationDetails($locked, $action, $input, $reason),
                self::RECORD_MANUAL_RECEIPT => ['channel' => Bid::CHANNEL_MANUAL, 'receipt_no' => trim((string) $input['receipt_no']), 'received_at' => $input['received_at']],
                self::RECOMMEND => ['bac_resolution_no' => trim((string) $input['bac_resolution_no']), 'bac_resolution_date' => $input['bac_resolution_date']],
                default => null,
            };

            if (filled($input['notes'] ?? null)) {
                $details = array_merge($details ?? [], ['remarks' => trim((string) $input['notes'])]);
            }
            $changes = $this->changesFor($locked, $action, $actor->id, $input, $reason);
            $locked->update($changes + [
                'workflow_step_updated_at' => now(),
                'workflow_step_updated_by' => $actor->id,
            ]);

            $definition = self::ACTION_DEFINITIONS[$action];
            [$title, $description] = $this->publicText($action, $reason);

            $event = $this->record(
                $locked,
                $definition['stage'] ?? $changes['disqualified_stage'] ?? null,
                $definition['decision'],
                $actor->id,
                $definition['adverse'] ? $reason : null,
                $title,
                $description,
                $details
            );

            $this->attachSupportingDocument($event, $locked, $input['supporting_document'] ?? null);

            $affected->push($locked);

            if ($action === self::NOTICE_OF_AWARD) {
                $this->createAwardRecord($locked, $actor, $input['notice_file'], $input['notes'] ?? null);
            }

            if ($action === self::CONTRACT_SIGNED) {
                Award::where('bid_id', $locked->id)->update(['contract_date' => $input['contract_date']]);
            }

            if ($action === self::APPROVE_AWARD) {
                // Close the other bids first (with their notices), then hand the
                // approved award to Awards & Contracts in the same transaction.
                $affected = $affected->merge($this->markOthersNotAwarded($locked, $actor->id));
                $this->handOffApprovedAward($locked, $actor);
            }

            return $locked;
        });
    }

    /**
     * @throws ValidationException
     */
    private function assertSignedPdf(mixed $file): void
    {
        if (! $file instanceof \Illuminate\Http\UploadedFile || ! $file->isValid()) {
            throw ValidationException::withMessages(['notice_file' => 'Attach the signed Notice of Award (PDF).']);
        }

        $handle = @fopen($file->getRealPath(), 'rb');
        $signature = $handle ? fread($handle, 4) : '';
        if ($handle) {
            fclose($handle);
        }

        if (strtolower((string) $file->getClientOriginalExtension()) !== 'pdf' || $signature !== '%PDF') {
            throw ValidationException::withMessages(['notice_file' => 'The Notice of Award must be a valid PDF document.']);
        }
    }

    /**
     * Hands a HoPE-approved bid to Awards & Contracts: exactly one award record
     * for the project, linked to the approved bid and its supplier, with the
     * approved bid price and who approved it and when. Idempotent: a retry or
     * a repeated call updates the same record. It never replaces an award in
     * force for another bid; that needs an audited cancellation first.
     *
     * Also used to hand off bids the HoPE approved before this existed.
     *
     * @throws ValidationException
     */
    public function handOffApprovedAward(Bid $bid, ?User $actor = null): Award
    {
        return DB::transaction(function () use ($bid, $actor) {
            // Serialize hand-offs per project so two approvals cannot both create a record.
            Project::whereKey($bid->project_id)->lockForUpdate()->first();
            $bid = Bid::lockForUpdate()->findOrFail($bid->id);

            if ($bid->award_decision !== Bid::AWARD_DECISION_APPROVED || $bid->bac_recommended_at === null) {
                throw ValidationException::withMessages(['milestone' => 'Only a bid the BAC recommended and the HoPE approved can be handed off as an award.']);
            }
            if ($this->projectHasActiveAwardForOtherBid($bid)) {
                throw ValidationException::withMessages(['milestone' => 'This project already has an award in force for another bidder. Cancel that award in Awards & Contracts first.']);
            }

            $award = Award::where('bid_id', $bid->id)->whereNull('cancelled_at')->lockForUpdate()->first();
            $approvedBy = $bid->award_decision_by ?? $actor?->id;

            if ($award === null) {
                $award = Award::create([
                    'project_id' => $bid->project_id,
                    'bid_id' => $bid->id,
                    'bidder_id' => $bid->user_id,
                    'contract_amount' => $bid->getRawOriginal('bid_amount'),
                    'contract_date' => null,
                    'notice_of_award_date' => null,
                    'status' => Award::STATUS_VALID,
                    'certificate_status' => Award::CERTIFICATE_PENDING_NOTICE,
                    'award_approved_at' => $bid->award_decision_at ?? now(),
                    'award_approved_by' => $approvedBy,
                ]);

                AuditLog::log('award_approved_handoff', $award, [], [
                    'project_id' => $award->project_id,
                    'bid_id' => $award->bid_id,
                    'bidder_id' => $award->bidder_id,
                    'contract_amount' => $award->contract_amount,
                    'approved_by' => $approvedBy,
                ]);

                return $award;
            }

            // Already handed off (or created at an earlier Notice of Award): fill in only what is missing.
            $missing = array_filter([
                'award_approved_at' => $award->award_approved_at ? null : ($bid->award_decision_at ?? now()),
                'award_approved_by' => $award->award_approved_by ? null : $approvedBy,
                'bidder_id' => $award->bidder_id ? null : $bid->user_id,
            ], fn ($value) => $value !== null);
            if ($missing !== []) {
                $award->update($missing);
            }

            return $award;
        });
    }

    /**
     * Cancels an award before the contract is signed (e.g. the winning bidder
     * did not post the performance security), by the HoPE, with its reason and
     * authority kept on the record and in the audit log. The winning bid is
     * closed; bids closed as "Not Awarded" by this award reopen so the BAC can
     * take its next decision. A signed contract is never cancelled here.
     *
     * @throws ValidationException
     */
    public function cancelAward(Award $award, User $actor, string $reason, string $reference, ?\Illuminate\Http\UploadedFile $document = null): Award
    {
        if (! in_array($actor->role, self::HOPE_ROLES, true)) {
            throw ValidationException::withMessages(['cancel' => 'Only the Head of the Procuring Entity can cancel an award.']);
        }
        if (trim($reason) === '' || trim($reference) === '') {
            throw ValidationException::withMessages(['cancellation_reason' => 'Enter the reason and the authority (HoPE memo or BAC resolution) for the cancellation.']);
        }
        $this->assertSupportingDocument($document);

        $affected = collect();
        $award = DB::transaction(function () use ($award, $actor, $reason, $reference, $document, &$affected) {
            Project::whereKey($award->project_id)->lockForUpdate()->first();
            $award = Award::lockForUpdate()->findOrFail($award->id);
            $bid = Bid::with(['project.awards', 'award'])->lockForUpdate()->find($award->bid_id);

            if ($award->isCancelled()) {
                throw ValidationException::withMessages(['cancel' => 'This award is already cancelled.']);
            }
            if ($award->contract_date !== null || $bid?->contract_signed_at !== null) {
                throw ValidationException::withMessages(['cancel' => 'The contract is already signed. A signed contract is terminated under its own terms, not cancelled here.']);
            }

            $before = $award->only(['status', 'certificate_status', 'cancelled_at']);
            $award->update([
                'status' => Award::STATUS_REVOKED,
                'certificate_status' => Award::STATUS_REVOKED,
                'certificate_revoked_at' => now(),
                'certificate_revoked_by' => $actor->id,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reference' => trim($reference),
                'cancellation_reason' => trim($reason),
            ]);

            if ($bid !== null) {
                $bid->update([
                    'award_decision' => Bid::AWARD_DECISION_CANCELLED,
                    'workflow_step' => Bid::STEP_NOT_AWARDED,
                    'workflow_step_updated_at' => now(),
                    'workflow_step_updated_by' => $actor->id,
                ]);
                $event = $this->record($bid, BidProgress::OUTCOME_NOT_AWARDED, 'award_cancelled', $actor->id, trim($reason),
                    'Award Cancelled', 'The Head of the Procuring Entity cancelled the award ('.trim($reference).').',
                    ['reference' => trim($reference), 'award_id' => $award->id]);
                $this->attachSupportingDocument($event, $bid, $document);
                $affected->push($bid);
                $affected = $affected->merge($this->reopenBidsClosedBy($bid, $actor->id));
            }

            // The project goes back to the BAC for its next decision.
            Project::whereKey($award->project_id)->where('status', 'awarded')->update(['status' => 'closed']);

            AuditLog::log('award_cancelled', $award, $before, [
                'status' => $award->status,
                'cancelled_by' => $actor->id,
                'cancellation_reference' => $award->cancellation_reference,
                'cancellation_reason' => $award->cancellation_reason,
                'reopened_bids' => $affected->pluck('id')->reject(fn ($id) => $id === $bid?->id)->values()->all(),
            ]);

            return $award;
        });

        $affected->each(fn (Bid $changed) => event(new BidWorkflowUpdated($changed->fresh())));

        return $award->fresh();
    }

    /**
     * Bids this award closed as "Not Awarded" go back to where they stood.
     * Only bids whose earlier step was recorded at that time are reopened.
     */
    private function reopenBidsClosedBy(Bid $winner, int $actorId): Collection
    {
        return BidTracking::where('decision', 'not_awarded')
            ->whereHas('bid', fn ($query) => $query->where('project_id', $winner->project_id)->whereKeyNot($winner->id)->where('workflow_step', Bid::STEP_NOT_AWARDED))
            ->get()
            ->filter(fn (BidTracking $event) => (int) ($event->details['winner_bid_id'] ?? 0) === $winner->id && filled($event->details['previous_workflow_step'] ?? null))
            ->map(function (BidTracking $event) use ($actorId) {
                $other = Bid::lockForUpdate()->find($event->bid_id);
                $other->update([
                    'workflow_step' => $event->details['previous_workflow_step'],
                    'workflow_step_updated_at' => now(),
                    'workflow_step_updated_by' => $actorId,
                ]);
                $this->record($other, BidProgress::STAGE_RECOMMENDATION, 'reopened', $actorId, null,
                    'Bid Reopened', 'The award to another bidder was cancelled. Your bid is back under BAC consideration.');

                return $other;
            })
            ->values();
    }

    private function projectHasActiveAwardForOtherBid(Bid $bid): bool
    {
        return Award::where('project_id', $bid->project_id)
            ->where('bid_id', '!=', $bid->id)
            ->active()
            ->exists();
    }

    /**
     * The Notice of Award completes the award record the HoPE approval handed
     * off (or creates it for bids approved before the hand-off existed):
     * contract amount = the bid price as submitted, award date = today, contract
     * date set later at signing. The signed NOA is kept as the award document
     * (QR-verifiable), and the project becomes Awarded.
     */
    private function createAwardRecord(Bid $bid, User $actor, \Illuminate\Http\UploadedFile $file, ?string $notes): Award
    {
        $path = $file->storeAs('certificates/'.$bid->project_id, \Illuminate\Support\Str::random(40).'.pdf', 'local');
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw new \RuntimeException('The Notice of Award PDF could not be stored.');
        }
        $this->storedFiles[] = $path;

        $award = $this->handOffApprovedAward($bid, $actor);
        $award->update([
            'contract_amount' => $bid->getRawOriginal('bid_amount'),
            'notice_of_award_date' => now()->toDateString(),
            'status' => Award::STATUS_VALID,
            'notes' => $notes ?? $award->notes,
            'certificate_file_path' => $path,
            'certificate_status' => Award::STATUS_VALID,
            'certificate_uploaded_at' => now(),
        ]);

        $award = app(\App\Services\AwardCertificateService::class)->ensureForValidAward($award);

        Project::whereKey($bid->project_id)->update(['status' => 'awarded']);

        \App\Models\AuditLog::log('notice_of_award_issued', $award, [], [
            'project_id' => $bid->project_id,
            'bid_id' => $bid->id,
            'bidder_id' => $bid->user_id,
            'contract_amount' => $award->contract_amount,
            'file_path' => $path,
            'recorded_by' => $actor->id,
        ]);

        return $award;
    }

    /**
     * Record the authorized bid opening for a project. Until then proposals
     * and bid amounts are sealed and no examination can be recorded.
     *
     * @throws ValidationException
     */
    public function openBids(Project $project, User $actor): Project
    {
        if (! in_array($actor->role, self::DECISION_ROLES, true) || $actor->status !== 'active') {
            throw ValidationException::withMessages(['bids_opened_at' => 'Your role is not authorized to open bids.']);
        }

        $project = DB::transaction(function () use ($project, $actor) {
            $locked = Project::with('schedule')->lockForUpdate()->findOrFail($project->id);

            if (! $locked->requiresRecordedBidOpening()) {
                throw ValidationException::withMessages(['bids_opened_at' => 'This procurement mode uses quotation/offer review after its deadline; it does not require a competitive bid-opening event.']);
            }

            if ($error = $locked->bidOpeningBlocker()) {
                throw ValidationException::withMessages(['bids_opened_at' => $error]);
            }

            // Already opened at its scheduled time: the BAC records that it conducted the opening.
            if ($locked->bidsAreOpened()) {
                $locked->update(['bids_opened_by' => $actor->id]);
                AuditLog::log('bids_opened', $locked, [], [
                    'bids_opened_at' => $locked->bids_opened_at->timezone('Asia/Manila')->toIso8601String(),
                    'bids_opened_by' => $actor->id,
                    'recorded_at' => now('Asia/Manila')->toIso8601String(),
                    'timezone' => 'Asia/Manila',
                    'component' => 'technical',
                    'method' => 'scheduled_opening_recorded_by_bac',
                ]);

                return $locked;
            }

            $this->recordTechnicalOpening($locked, $actor->id, 'bids_opened', [
                'bids_opened_by' => $actor->id,
                'procurement_mode' => $locked->procurement_mode,
                'legal_basis' => $locked->mode()->legalBasisShort(),
            ]);

            return $locked;
        });

        Bid::where('project_id', $project->id)->get()->each(fn (Bid $bid) => event(new BidWorkflowUpdated($bid)));

        return $project;
    }

    /**
     * The technical opening itself, recorded by the BAC ($actorId) or at the
     * scheduled time (no actor, BidOpening::openScheduledTechnical): it ends
     * the submission period and every official bid's history shows it. Run
     * inside a transaction that holds the project lock.
     */
    public function recordTechnicalOpening(Project $locked, ?int $actorId, string $auditAction, array $auditDetails = []): void
    {
        // The opening timestamp is generated by the application server in
        // Philippine Standard Time; the browser/laptop clock is irrelevant.
        $openedAt = now('Asia/Manila');

        // Bid opening ends the submission period: bidding is closed.
        $locked->update([
            'bids_opened_at' => $openedAt,
            'bids_opened_by' => $actorId,
            'status' => $locked->status === 'open' ? 'closed' : $locked->status,
        ]);

        AuditLog::log($auditAction, $locked, [], $auditDetails + [
            'bids_opened_at' => $openedAt->toIso8601String(),
            'timezone' => 'Asia/Manila',
            'component' => 'technical',
        ], ['user_id' => $actorId]);

        $description = $actorId !== null
            ? 'The BAC recorded technical opening. Financial components remain sealed until separately opened.'
            : 'Technical components were opened at the scheduled bid opening time. Financial components remain sealed until separately opened.';

        // Drafts are not bids; only official submissions are opened.
        Bid::where('project_id', $locked->id)->get()->reject(fn (Bid $bid) => $bid->isDraft())->each(fn (Bid $bid) => $this->record(
            $bid,
            BidProgress::STAGE_PRELIMINARY,
            'opened',
            $actorId,
            null,
            'Technical Components Opened',
            $description
        ));
    }

    /**
     * @throws ValidationException
     */
    public function declareFailedBidding(Project $project, int $actorId, string $reason, ?int $rebidProjectId = null): Project
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['failed_bidding_reason' => 'Enter the ground for declaring a failure of bidding.']);
        }

        if ($rebidProjectId !== null && $rebidProjectId === $project->id) {
            throw ValidationException::withMessages(['rebid_project_id' => 'The new bidding round must be a different project.']);
        }

        $project = DB::transaction(function () use ($project, $actorId, $reason, $rebidProjectId) {
            $locked = Project::lockForUpdate()->findOrFail($project->id);

            $hasValidAward = Award::where('project_id', $locked->id)
                ->whereIn('status', [Award::STATUS_VALID, 'active'])
                ->exists()
                || Bid::where('project_id', $locked->id)->where('award_decision', Bid::AWARD_DECISION_APPROVED)->exists();

            if ($hasValidAward || $locked->status === 'awarded') {
                throw ValidationException::withMessages(['failed_bidding_reason' => 'An awarded project cannot be declared a failed bidding.']);
            }

            if ($locked->failed_bidding_at !== null) {
                // Already declared: only the link to the new round can change.
                $locked->update(['rebid_project_id' => $rebidProjectId]);

                return $locked;
            }

            if ($locked->archived_at !== null) {
                throw ValidationException::withMessages(['failed_bidding_reason' => 'An archived project cannot be declared a failed bidding.']);
            }

            // Competitive bidding fails after its recorded opening; SVP, negotiated and direct
            // procurement have no opening event and fail after their deadline (checked below).
            if ($locked->requiresRecordedBidOpening() && ($locked->status !== 'closed' || ! $locked->bidsAreOpened())) {
                throw ValidationException::withMessages(['failed_bidding_reason' => 'Open the bids and complete the bidding stage before declaring a failed bidding.']);
            }
            if (! in_array($locked->status, ['open', 'closed'], true)) {
                throw ValidationException::withMessages(['failed_bidding_reason' => 'Only a posted procurement can be declared a failure.']);
            }

            $deadline = $locked->bidSubmissionDeadline();
            if ($deadline === null || $deadline->isAfter(now())) {
                throw ValidationException::withMessages(['failed_bidding_reason' => 'Failure of bidding can only be recorded after the submission deadline.']);
            }

            $locked->update([
                'failed_bidding_at' => now(),
                'failed_bidding_by' => $actorId,
                'failed_bidding_reason' => $reason,
                'rebid_project_id' => $rebidProjectId,
                'status' => in_array($locked->status, ['open', 'approved_for_bidding'], true) ? 'closed' : $locked->status,
            ]);

            $bids = Bid::where('project_id', $locked->id)->get();

            foreach ($bids as $bid) {
                $this->record(
                    $bid,
                    BidProgress::OUTCOME_FAILED_BIDDING,
                    'declared',
                    $actorId,
                    $reason,
                    'Failed Bidding Declared',
                    'The BAC declared a failure of bidding for this project.',
                    notify: false
                );
            }

            SystemNotification::createForUsers(
                $bids->pluck('user_id'),
                'Failed bidding declared',
                'The BAC declared a failure of bidding for '.$locked->title.'.',
                'bid_progress',
                ['project_id' => $locked->id, 'url' => route('bidder.bidding-track')]
            );

            return $locked;
        });

        Bid::where('project_id', $project->id)->get()
            ->each(fn (Bid $bid) => event(new BidWorkflowUpdated($bid)));

        return $project;
    }

    /**
     * A manual bid counts only if it was received on or before the submission
     * deadline and carries the Secretariat's receipt (logbook) number.
     *
     * @throws ValidationException
     */
    private function validateManualReceipt(Bid $bid, array $input): void
    {
        $receiptNo = trim((string) ($input['receipt_no'] ?? ''));
        if ($receiptNo === '') {
            throw ValidationException::withMessages(['receipt_no' => 'Enter the receipt / logbook number issued by the BAC Secretariat.']);
        }

        if (Bid::where('receipt_no', $receiptNo)->whereKeyNot($bid->id)->exists()) {
            throw ValidationException::withMessages(['receipt_no' => 'This receipt number is already used by another bid.']);
        }

        if (blank($input['received_at'] ?? null)) {
            throw ValidationException::withMessages(['received_at' => 'Enter the date and time the sealed bid was received.']);
        }

        $receivedAt = \Illuminate\Support\Carbon::parse($input['received_at']);
        $deadline = $bid->project?->bidSubmissionDeadline();

        if ($receivedAt->isFuture()) {
            throw ValidationException::withMessages(['received_at' => 'The receipt time cannot be in the future.']);
        }

        if ($deadline !== null && $receivedAt->greaterThan($deadline)) {
            throw ValidationException::withMessages(['received_at' => 'This bid was received after the submission deadline and cannot be recorded as submitted.']);
        }
    }

    /**
     * The BAC recommends the award through a resolution; its number and date
     * are recorded with the recommendation.
     *
     * @throws ValidationException
     */
    private function validateResolution(Bid $bid, array $input): void
    {
        if (blank($input['bac_resolution_no'] ?? null)) {
            throw ValidationException::withMessages(['bac_resolution_no' => 'Enter the number of the BAC resolution recommending the award.']);
        }

        if (blank($input['bac_resolution_date'] ?? null)) {
            throw ValidationException::withMessages(['bac_resolution_date' => 'Enter the date of the BAC resolution.']);
        }

        $date = \Illuminate\Support\Carbon::parse($input['bac_resolution_date'])->startOfDay();
        $opened = $bid->project?->bids_opened_at?->copy()->startOfDay();

        if ($date->isAfter(today()) || ($opened !== null && $date->lt($opened))) {
            throw ValidationException::withMessages(['bac_resolution_date' => 'The resolution date must be on or after the bid opening and not in the future.']);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertSupportingDocument(mixed $file): void
    {
        if ($file === null) {
            return;
        }

        if (! $file instanceof \Illuminate\Http\UploadedFile || ! $file->isValid()
            || ! in_array(strtolower((string) $file->getClientOriginalExtension()), ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'], true)) {
            throw ValidationException::withMessages(['supporting_document' => 'Attach the supporting document as a PDF, image, or Word file.']);
        }
    }

    /** Keeps the supporting document (report, minutes, resolution) with the recorded decision. */
    private function attachSupportingDocument(BidTracking $event, Bid $bid, mixed $file): void
    {
        if (! $file instanceof \Illuminate\Http\UploadedFile) {
            return;
        }

        $path = $file->storeAs(
            'decision-documents/'.$bid->project_id.'/'.$bid->id,
            \Illuminate\Support\Str::random(32).'.'.strtolower($file->getClientOriginalExtension()),
            'local'
        );

        if (! $path) {
            throw new \RuntimeException('The supporting document could not be stored.');
        }

        $this->storedFiles[] = $path;
        $event->update(['attachment_path' => $path, 'attachment_name' => $file->getClientOriginalName()]);
    }

    /**
     * The "Bid Submitted" event for an official electronic submission. Later
     * stages are recorded only through apply() by the BAC / LGU.
     */
    public function recordElectronicSubmission(Bid $bid, array $files): BidTracking
    {
        return $this->record(
            $bid,
            'submitted',
            'submitted',
            $bid->user_id,
            null,
            'Bid Submitted',
            'Your bid was received electronically. Receipt No. '.$bid->receipt_no.'.',
            ['channel' => Bid::CHANNEL_ELECTRONIC, 'receipt_no' => $bid->receipt_no, 'files' => $files]
        );
    }

    /**
     * Append one structured event to the bid's history.
     */
    public function record(
        Bid $bid,
        ?string $stage,
        string $decision,
        ?int $actorId,
        ?string $reason,
        string $title,
        string $description,
        ?array $details = null,
        bool $visibleToBidder = true,
        bool $notify = true,
    ): BidTracking {
        $event = BidTracking::create([
            'bid_id' => $bid->id,
            'bidder_id' => $bid->user_id,
            'project_id' => $bid->project_id,
            'status_title' => $title,
            'status_description' => $description,
            'status_type' => $decision,
            'stage' => $stage,
            'decision' => $decision,
            'reason' => $reason,
            'visible_to_bidder' => $visibleToBidder,
            'details' => $details,
            'created_by' => $actorId,
        ]);

        if ($visibleToBidder && $notify) {
            SystemNotification::createForUser(
                $bid->user_id,
                $title,
                $description.($reason ? ' Reason: '.$reason : ''),
                'bid_progress',
                [
                    'project_id' => $bid->project_id,
                    'bid_id' => $bid->id,
                    'url' => route('bidder.bidding-track', ['bid' => $bid->id]),
                ]
            );
        }

        return $event;
    }

    /**
     * Disqualification fields. The stage is explicit for FAIL_* actions and
     * inferred from what was recorded for a generic disqualification.
     */
    public function disqualificationAttributes(Bid $bid, int $actorId, ?string $reason, ?string $stage = null): array
    {
        $f = BidProgress::for($bid)->facts();

        $stage ??= match (true) {
            $f['post_qualification_started'] => BidProgress::STAGE_POST_QUALIFICATION,
            $f['prelim_passed'] => BidProgress::STAGE_EVALUATION,
            default => BidProgress::STAGE_PRELIMINARY,
        };

        return array_filter([
            'workflow_step' => Bid::STEP_DISQUALIFIED,
            'status' => 'rejected',
            'eligibility_status' => $stage === BidProgress::STAGE_PRELIMINARY ? Bid::ELIGIBILITY_INVALID : null,
            'eligibility_reviewed_at' => $stage === BidProgress::STAGE_PRELIMINARY ? now() : null,
            'eligibility_reviewed_by' => $stage === BidProgress::STAGE_PRELIMINARY ? $actorId : null,
            'disqualified_at' => now(),
            'disqualified_by' => $actorId,
            'disqualified_stage' => $stage,
            'rejection_reason' => filled($reason) ? trim($reason) : null,
            'post_qualification_result' => $stage === BidProgress::STAGE_POST_QUALIFICATION ? Bid::POST_QUALIFICATION_FAILED : null,
            'post_qualification_completed_at' => $stage === BidProgress::STAGE_POST_QUALIFICATION ? now() : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * Pass requires every project requirement to be present AND individually
     * verified by the reviewer: an uploaded file alone is not a pass.
     */
    private function preliminaryDetails(Bid $bid, string $action, array $input): array
    {
        $checklist = collect($bid->documentChecklist())->filter(fn (array $item) => ! $bid->isFinancialSealed() || ($item['component'] ?? 'technical') !== 'financial');
        $verified = collect($input['verified_requirements'] ?? [])->map(fn ($key) => (string) $key)->all();
        $failed = collect($input['failed_requirements'] ?? [])->map(fn ($key) => (string) $key)->all();

        if ($action === self::PASS_PRELIMINARY) {
            $required = $checklist->filter(fn (array $item) => $item['required'] ?? true);
            // Strictly false: a sealed paper bid marks its documents null (checked by hand
            // against the envelope), which a loose where('submitted', false) took as missing.
            $missing = $required->filter(fn (array $item) => $item['submitted'] === false)->pluck('label');
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'verified_requirements' => 'Missing required documents: '.$missing->implode(', ').'. Record a failed preliminary examination instead.',
                ]);
            }

            $unverified = $required->reject(fn (array $item) => in_array($item['key'], $verified, true))->pluck('label');
            if ($unverified->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'verified_requirements' => 'Verify each requirement against the bidding documents before passing: '.$unverified->implode(', ').'.',
                ]);
            }
        }

        return [
            'requirements' => $checklist->map(fn (array $item) => [
                'key' => $item['key'],
                'label' => $item['label'],
                'submitted' => $item['submitted'],
                'file_name' => ($item['component'] ?? 'technical') === 'financial' && $bid->isFinancialSealed() ? null : $item['file_name'],
                'result' => match (true) {
                    in_array($item['key'], $failed, true) => 'failed',
                    in_array($item['key'], $verified, true) => 'passed',
                    default => $action === self::PASS_PRELIMINARY ? 'passed' : 'not_checked',
                },
            ])->values()->all(),
        ];
    }

    /** Criteria and documentary basis configured for this project; no global checklist is assumed. */
    public function evaluationCriteria(Bid $bid): array
    {
        $requirement = $bid->project?->requirement;
        if ($requirement === null) {
            return [];
        }

        $criteria = [];
        foreach (['eligibility_requirements' => 'Eligibility and documentary requirements', 'technical_requirements' => 'Technical compliance', 'financial_requirements' => 'Financial/evaluation basis'] as $key => $label) {
            $text = trim((string) ($requirement->{$key} ?? ''));
            if ($text !== '') {
                $criteria[] = ['key' => $key, 'label' => $label, 'configured_text' => $text];
            }
        }

        foreach (collect($requirement->required_documents ?? [])->filter()->values() as $index => $document) {
            $criteria[] = ['key' => 'required_document_' . $index, 'label' => (string) $document, 'configured_text' => 'Required by the project bidding documents'];
        }

        return $criteria;
    }

    private function evaluationDetails(Bid $bid, string $action, array $input, string $reason): array
    {
        $result = trim((string) ($input['evaluation_result'] ?? ($action === self::FAIL_EVALUATION ? 'nonresponsive' : 'responsive')));
        if (! in_array($result, ['responsive', 'nonresponsive'], true)) {
            throw ValidationException::withMessages(['evaluation_result' => 'Select the detailed evaluation result before recording it.']);
        }

        $findings = trim((string) ($input['evaluation_findings'] ?? $reason));
        if ($findings === '') {
            throw ValidationException::withMessages(['evaluation_findings' => 'Document the detailed evaluation findings before recording the result.']);
        }

        $criterionResults = collect($input['criterion_results'] ?? [])->map(function ($value, $key) {
            return ['key' => (string) $key, 'result' => trim((string) $value)];
        })->filter(fn (array $item) => $item['result'] !== '')->values()->all();

        return ['result' => $result, 'findings' => $findings, 'criteria' => $this->evaluationCriteria($bid), 'criterion_results' => $criterionResults];
    }

    private function postQualificationDetails(Bid $bid, string $action, array $input, string $reason): array
    {
        $findings = trim((string) ($input['post_qualification_findings'] ?? $reason));
        if ($findings === '') {
            throw ValidationException::withMessages(['post_qualification_findings' => 'Document the post-qualification findings before recording the result.']);
        }

        return ['result' => $action === self::PASS_POST_QUALIFICATION ? 'post_qualified' : 'post_disqualified', 'findings' => $findings, 'qualification_basis' => trim((string) ($bid->project?->requirement?->qualification_notes ?? ''))];
    }

    private function changesFor(Bid $bid, string $action, int $actorId, array $input, string $reason): array
    {
        $now = now();


        return match ($action) {
            self::PASS_PRELIMINARY => [
                'workflow_step' => Bid::STEP_DOCUMENTS_VALIDATED,
                'eligibility_status' => Bid::ELIGIBILITY_VALID,
                'eligibility_reviewed_at' => $now,
                'eligibility_reviewed_by' => $actorId,
                'documents_validated_at' => $now,
                'documents_validated_by' => $actorId,
                // Internal "passed examination" marker kept for older reports.
                'status' => $bid->status === 'pending' ? 'approved' : $bid->status,
            ],
            self::FAIL_PRELIMINARY => $this->disqualificationAttributes($bid, $actorId, $reason, BidProgress::STAGE_PRELIMINARY),
            self::START_EVALUATION => [
                'workflow_step' => Bid::STEP_FOR_BAC_EVALUATION,
                'bac_evaluation_at' => $now,
                'bac_evaluation_by' => $actorId,
            ],
            self::EVALUATE => [
                'workflow_step' => Bid::STEP_EVALUATED,
                'bac_evaluation_at' => $bid->bac_evaluation_at ?? $now,
                'bac_evaluation_by' => $bid->bac_evaluation_by ?? $actorId,
                'evaluated_at' => $now,
                'evaluated_by' => $actorId,
            ],
            self::FAIL_EVALUATION => $this->disqualificationAttributes($bid, $actorId, $reason, BidProgress::STAGE_EVALUATION),
            self::START_POST_QUALIFICATION => [
                'workflow_step' => Bid::STEP_POST_QUALIFICATION,
                'post_qualification_at' => $now,
                'post_qualification_by' => $actorId,
            ],
            self::PASS_POST_QUALIFICATION => [
                'workflow_step' => Bid::STEP_POST_QUALIFIED,
                'post_qualification_result' => Bid::POST_QUALIFICATION_PASSED,
                'post_qualification_completed_at' => $now,
            ],
            self::FAIL_POST_QUALIFICATION => $this->disqualificationAttributes($bid, $actorId, $reason, BidProgress::STAGE_POST_QUALIFICATION),
            self::RECOMMEND => [
                'workflow_step' => Bid::STEP_RECOMMENDED,
                'bac_recommended_at' => $now,
                'bac_recommended_by' => $actorId,
                'bac_resolution_no' => trim((string) $input['bac_resolution_no']),
                'bac_resolution_date' => $input['bac_resolution_date'],
            ],
            self::APPROVE_AWARD => [
                'workflow_step' => Bid::STEP_AWARDED,
                'status' => 'awarded',
                'award_decision' => Bid::AWARD_DECISION_APPROVED,
                'award_decision_at' => $now,
                'award_decision_by' => $actorId,
                'awarded_at' => $now,
                'awarded_by' => $actorId,
            ],
            self::DISAPPROVE_AWARD => [
                'workflow_step' => Bid::STEP_NOT_AWARDED,
                'award_decision' => Bid::AWARD_DECISION_DISAPPROVED,
                'award_decision_at' => $now,
                'award_decision_by' => $actorId,
                'rejection_reason' => $reason,
            ],
            self::NOTICE_OF_AWARD => [
                'workflow_step' => Bid::STEP_NOTICE_OF_AWARD,
                'notice_of_award_at' => $now,
                'notice_of_award_by' => $actorId,
            ],
            self::CONTRACT_SIGNED => [
                'workflow_step' => Bid::STEP_CONTRACT_SIGNED,
                'performance_security_at' => $input['performance_security_at'],
                'contract_signed_at' => $now,
                'contract_signed_by' => $actorId,
            ],
            self::NOTICE_TO_PROCEED => [
                'workflow_step' => Bid::STEP_NOTICE_TO_PROCEED,
                'notice_to_proceed_at' => $now,
                'notice_to_proceed_by' => $actorId,
            ],
            self::DISQUALIFY => $this->disqualificationAttributes($bid, $actorId, $reason),
            self::RECORD_MANUAL_RECEIPT => [
                'submitted_at' => \Illuminate\Support\Carbon::parse($input['received_at']),
                'receipt_no' => trim((string) $input['receipt_no']),
                'submission_received_by' => $actorId,
            ],
        };
    }

    /**
     * Once the HoPE approves the award, every other bid still in the running
     * is closed as Not Awarded (never as disqualified).
     */
    private function markOthersNotAwarded(Bid $winner, int $actorId): Collection
    {
        return Bid::with(['project.awards', 'award'])
            ->where('project_id', $winner->project_id)
            ->whereKeyNot($winner->id)
            ->lockForUpdate()
            ->get()
            ->reject(fn (Bid $other) => BidProgress::for($other)->isClosed())
            ->each(function (Bid $other) use ($actorId, $winner) {
                // Kept so a later award cancellation can put the bid back where it stood.
                $previousStep = $other->workflow_step;
                $other->update([
                    'workflow_step' => Bid::STEP_NOT_AWARDED,
                    'workflow_step_updated_at' => now(),
                    'workflow_step_updated_by' => $actorId,
                ]);

                $this->record(
                    $other,
                    BidProgress::OUTCOME_NOT_AWARDED,
                    'not_awarded',
                    $actorId,
                    null,
                    'Not Awarded',
                    'The contract was awarded to another bidder. Your bid was not disqualified.',
                    ['winner_bid_id' => $winner->id, 'previous_workflow_step' => $previousStep]
                );
            })
            ->values();
    }

    /**
     * @return array{0: string, 1: string} bidder-facing title and description
     */
    private function publicText(string $action, string $reason): array
    {

        return match ($action) {
            self::PASS_PRELIMINARY => ['Passed Preliminary Examination', 'Your bid passed the pass/fail check of required documents and is now under evaluation.'],
            self::FAIL_PRELIMINARY => ['Failed Preliminary Examination', 'Your bid did not pass the pass/fail check of required documents.'],
            self::START_EVALUATION => ['Bid Evaluation Started', 'The BAC started evaluating your bid.'],
            self::EVALUATE => ['Bid Evaluation Completed', 'The BAC completed the evaluation of your bid. Results follow after ranking and post-qualification.'],
            self::FAIL_EVALUATION => ['Disqualified at Bid Evaluation', 'Your bid was disqualified during evaluation.'],
            self::START_POST_QUALIFICATION => ['Post-Qualification Started', 'The BAC is verifying your documents and qualifications.'],
            self::PASS_POST_QUALIFICATION => ['Passed Post-Qualification', 'You passed post-qualification.'],
            self::FAIL_POST_QUALIFICATION => ['Post-Disqualified', 'You did not pass post-qualification.'],
            self::RECOMMEND => ['Recommended for Award', 'The BAC recommended your bid for award. Awaiting approval of the Head of the Procuring Entity.'],
            self::APPROVE_AWARD => ['Award Approved', 'The Head of the Procuring Entity approved the award. The Notice of Award will follow.'],
            self::DISAPPROVE_AWARD => ['Recommendation Not Approved', 'The Head of the Procuring Entity did not approve the recommendation for award.'],
            self::NOTICE_OF_AWARD => ['Notice of Award Issued', 'The Notice of Award was issued to you.'],
            self::CONTRACT_SIGNED => ['Contract Signed', 'The contract was signed after the performance security was posted.'],
            self::NOTICE_TO_PROCEED => ['Notice to Proceed Issued', 'The Notice to Proceed was issued.'],
            self::DISQUALIFY => ['Bid Disqualified', 'Your bid was disqualified by the BAC.'],
            self::RECORD_MANUAL_RECEIPT => ['Bid Submitted', 'The BAC Secretariat recorded receipt of your sealed bid.'],
        };
    }

    /**
     * Competitive ranking is sequential: only the highest-ranked eligible bid
     * may begin post-qualification. Move to a lower-ranked bid only after every
     * higher-ranked candidate has a recorded post-disqualification.
     */
    private function postQualificationOrderBlocker(Bid $bid): ?string
    {
        $project = $bid->project;
        if (! $project) {
            return 'The project ranking is unavailable. Reload the bid and try again.';
        }

        $projectBids = Bid::with('project')->where('project_id', $bid->project_id)->get();
        $rankings = app(\App\Support\BidRanking::class)->forProject($project, $projectBids);
        $current = $rankings[$bid->id] ?? null;

        if (($current['status'] ?? null) !== \App\Support\BidRanking::RANKED || ! isset($current['rank'])) {
            return 'This bid is not currently eligible for post-qualification in the project ranking.';
        }

        foreach ($projectBids as $other) {
            $otherRank = $rankings[$other->id] ?? null;
            if (($otherRank['status'] ?? null) !== \App\Support\BidRanking::RANKED
                || ($otherRank['rank'] ?? PHP_INT_MAX) >= $current['rank']) {
                continue;
            }

            // A higher-ranked bidder is out of the way once post-disqualified, or once its
            // award was disapproved by the HoPE or cancelled (e.g. it refused to sign).
            if ($other->post_qualification_result !== Bid::POST_QUALIFICATION_FAILED
                && ! in_array($other->award_decision, [Bid::AWARD_DECISION_DISAPPROVED, Bid::AWARD_DECISION_CANCELLED], true)) {
                return 'Post-qualify the highest-ranked eligible bidder first. A lower-ranked bidder may proceed only after the higher-ranked bidder is formally post-disqualified.';
            }
        }

        return null;
    }
    private function projectHasOtherBid(Bid $bid, callable $predicate): bool
    {
        return Bid::where('project_id', $bid->project_id)
            ->whereKeyNot($bid->id)
            ->get()
            ->contains($predicate);
    }

    private function projectHasActiveRecommendation(Bid $bid): bool
    {
        return $this->projectHasOtherBid(
            $bid,
            fn (Bid $other) => $other->bac_recommended_at !== null
                && ! in_array($other->award_decision, [Bid::AWARD_DECISION_DISAPPROVED, Bid::AWARD_DECISION_CANCELLED], true)
                && $other->disqualified_at === null
        );
    }
}
