<?php

namespace App\Support;

use App\Models\Bid;
use App\Models\BidTracking;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The event history of one bid, shared by the admin review modal and the
 * bidder track.
 *
 * Source of truth: structured BidTracking events written by BidWorkflow.
 * Bids decided before events were structured still have their milestone
 * timestamps; those are shown as entries "from the bid record" when no
 * matching event exists. Nothing is added without a recorded timestamp.
 *
 * Bidders see only structured events flagged visible_to_bidder plus record
 * entries, without the responsible user, internal notes or legacy free-text
 * rows (which may contain internal remarks).
 */
class BidHistory
{
    private const DECISIONS = [
        'submitted' => 'Submitted',
        'opened' => 'Opened',
        'started' => 'Started',
        'passed' => 'Passed',
        'failed' => 'Failed',
        'recommended' => 'Recommended',
        'approved' => 'Approved',
        'disapproved' => 'Disapproved',
        'issued' => 'Issued',
        'signed' => 'Signed',
        'not_awarded' => 'Not awarded',
        'declared' => 'Declared',
        'draft_saved' => 'Draft saved (not official)',
        'modified' => 'Modified (supersedes earlier receipt)',
    ];

    public function __construct(private readonly Bid $bid) {}

    public static function for(Bid $bid): self
    {
        return new self($bid);
    }

    /**
     * Change detector for the bidder track refresh (progress + history).
     */
    public static function trackerSignature(array $progress, array $history): string
    {
        return md5($progress['signature'].json_encode($history));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forAdmin(): array
    {
        return $this->build(forBidder: false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forBidder(): array
    {
        return $this->build(forBidder: true);
    }

    private function build(bool $forBidder): array
    {
        $events = $this->bid->relationLoaded('trackings')
            ? $this->bid->trackings
            : $this->bid->trackings()->get();
        $events = $events->filter(fn (BidTracking $event) => $event->created_at !== null);
        $events->loadMissing('creator');

        $structured = $events->filter(fn (BidTracking $event) => $event->stage !== null);
        $covered = $structured->map(fn (BidTracking $event) => $event->stage.'|'.$event->decision)->unique()->all();

        $entries = $structured
            ->filter(fn (BidTracking $event) => ! $forBidder || $event->visible_to_bidder)
            ->map(fn (BidTracking $event) => [
                'at' => $event->created_at,
                'stage' => $event->stage,
                'decision' => $event->decision,
                'title' => $event->status_title,
                'description' => $event->status_description,
                'reason' => $event->reason,
                'actor' => $event->creator,
                'source' => 'event',
                'details' => $event->details,
            ]);

        $entries = $entries->concat(
            $this->recordEntries()->reject(fn (array $entry) => in_array($entry['stage'].'|'.$entry['decision'], $covered, true))
        );

        if (! $forBidder) {
            // Legacy free-text rows: internal only.
            $entries = $entries->concat($events
                ->filter(fn (BidTracking $event) => $event->stage === null)
                ->map(fn (BidTracking $event) => [
                    'at' => $event->created_at,
                    'stage' => null,
                    'decision' => null,
                    'title' => $event->status_title ?: 'Note',
                    'description' => $event->status_description,
                    'reason' => null,
                    'actor' => $event->creator,
                    'source' => 'legacy_note',
                    'details' => null,
                ]));
        }

        return $entries
            ->filter(fn (array $entry) => $entry['at'] !== null)
            ->sortBy(fn (array $entry) => $entry['at']->getTimestamp())
            ->values()
            ->map(fn (array $entry) => $this->present($entry, $forBidder))
            ->all();
    }

    /**
     * Milestones evidenced by the bid's own timestamp columns.
     */
    private function recordEntries(): Collection
    {
        $bid = $this->bid;
        $project = $bid->project;
        $rows = collect();

        $add = function (?CarbonInterface $at, string $stage, string $decision, string $title, ?int $actorId = null, ?string $reason = null) use ($rows) {
            if ($at !== null) {
                $rows->push(compact('at', 'stage', 'decision', 'title', 'reason') + ['actor_id' => $actorId]);
            }
        };

        // Only an official submission is a "Bid Submitted" entry; a draft upload is not.
        if ($bid->isOfficiallySubmitted()) {
            $add($bid->submitted_at ?? $bid->created_at, 'submitted', 'submitted', 'Bid Submitted', $bid->submission_received_by ?? $bid->user_id);
        }
        $add($project?->bids_opened_at, BidProgress::STAGE_PRELIMINARY, 'opened', 'Bids Opened', $project?->bids_opened_by);
        $add($bid->documents_validated_at, BidProgress::STAGE_PRELIMINARY, 'passed', 'Passed Preliminary Examination', $bid->documents_validated_by);
        $add($bid->bac_evaluation_at, BidProgress::STAGE_EVALUATION, 'started', 'Bid Evaluation Started', $bid->bac_evaluation_by);
        $add($bid->evaluated_at, BidProgress::STAGE_EVALUATION, 'passed', 'Bid Evaluation Completed', $bid->evaluated_by);
        $add($bid->post_qualification_at, BidProgress::STAGE_POST_QUALIFICATION, 'started', 'Post-Qualification Started', $bid->post_qualification_by);

        if ($bid->post_qualification_result === 'passed') {
            $add($bid->post_qualification_completed_at, BidProgress::STAGE_POST_QUALIFICATION, 'passed', 'Passed Post-Qualification');
        }

        if ($bid->disqualified_at !== null) {
            $stage = BidProgress::for($bid)->facts()['disqualified_stage'] ?? BidProgress::STAGE_EVALUATION;
            $add($bid->disqualified_at, $stage, 'failed', 'Disqualified at '.(BidProgress::STAGES[$stage] ?? 'Evaluation'), $bid->disqualified_by, $bid->rejection_reason);
        }

        $add($bid->bac_recommended_at, BidProgress::STAGE_RECOMMENDATION, 'recommended', 'Recommended for Award', $bid->bac_recommended_by);

        if ($bid->award_decision !== null) {
            $add($bid->award_decision_at, BidProgress::STAGE_AWARD_APPROVAL, $bid->award_decision, $bid->award_decision === 'approved' ? 'Award Approved' : 'Recommendation Not Approved', $bid->award_decision_by);
        } elseif ($bid->awarded_at !== null) {
            $add($bid->awarded_at, BidProgress::STAGE_AWARD_APPROVAL, 'approved', 'Award Approved', $bid->awarded_by);
        }

        $add($bid->notice_of_award_at, BidProgress::STAGE_NOTICE_OF_AWARD, 'issued', 'Notice of Award Issued', $bid->notice_of_award_by);
        $add($bid->contract_signed_at, BidProgress::STAGE_CONTRACT_SIGNED, 'signed', 'Contract Signed', $bid->contract_signed_by);
        $add($bid->notice_to_proceed_at, BidProgress::STAGE_NOTICE_TO_PROCEED, 'issued', 'Notice to Proceed Issued', $bid->notice_to_proceed_by);

        if ($bid->workflow_step === Bid::STEP_NOT_AWARDED && $bid->award_decision === null) {
            $add($bid->workflow_step_updated_at, BidProgress::OUTCOME_NOT_AWARDED, 'not_awarded', 'Not Awarded', $bid->workflow_step_updated_by);
        }

        $add($project?->failed_bidding_at, BidProgress::OUTCOME_FAILED_BIDDING, 'declared', 'Failed Bidding Declared', $project?->failed_bidding_by, $project?->failed_bidding_reason);

        $actors = User::whereIn('id', $rows->pluck('actor_id')->filter()->unique())->get()->keyBy('id');

        return $rows->map(fn (array $row) => [
            'at' => $row['at'],
            'stage' => $row['stage'],
            'decision' => $row['decision'],
            'title' => $row['title'],
            'description' => null,
            'reason' => $row['reason'],
            'actor' => $row['actor_id'] ? $actors->get($row['actor_id']) : null,
            'source' => 'record',
            'details' => null,
        ]);
    }

    private function present(array $entry, bool $forBidder): array
    {
        $stageLabel = match ($entry['stage']) {
            null => 'Note',
            'submitted' => 'Submission',
            default => BidProgress::STAGES[$entry['stage']] ?? BidProgress::ADMIN_STAGES[$entry['stage']] ?? $entry['stage'],
        };

        /** @var User|null $actor */
        $actor = $entry['actor'];

        return [
            'at' => $entry['at']->copy()->timezone(config('bac-office.display_timezone'))->format('M d, Y · h:i A'),
            'at_iso' => $entry['at']->toIso8601String(),
            'stage' => $stageLabel,
            'decision' => self::DECISIONS[$entry['decision']] ?? null,
            'title' => $entry['title'],
            'description' => $forBidder ? null : $entry['description'],
            'reason' => $entry['reason'],
            'adverse' => in_array($entry['decision'], ['failed', 'disapproved', 'not_awarded', 'declared'], true),
            'actor' => $forBidder ? null : ($actor ? $actor->name.' ('.ucfirst((string) $actor->role).')' : 'Not recorded'),
            'source' => $entry['source'],
            'details' => $forBidder ? null : $entry['details'],
        ];
    }
}
