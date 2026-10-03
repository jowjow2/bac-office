<?php

namespace App\Support;

use App\Events\BidWorkflowUpdated;
use App\Models\{AuditLog, Bid, Project, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Explicit component openings; schedules and review milestones only permit an action. */
class BidOpening
{
    public const CRITERIA = ['lowest_calculated_bid', 'mearb', 'marb'];

    private function authorize(User $actor): void
    {
        abort_unless($actor->role === 'admin' && $actor->status === 'active', 403);
    }

    public function configure(Project $project, User $actor, array $input): void
    {
        $this->authorize($actor);
        $data = Validator::make($input, [
            'award_criterion' => ['required', Rule::in(self::CRITERIA)],
            'opening_documents_reference' => 'required|string|max:500',
            'minimum_technical_score' => 'required_if:award_criterion,mearb|nullable|numeric|min:0|max:999999',
        ])->validate();
        DB::transaction(function () use ($project, $actor, $data) {
            $locked = Project::lockForUpdate()->findOrFail($project->id);
            if (! $locked->requiresRecordedBidOpening() || ($locked->bidsAreOpened() && $locked->opening_documents_reference)) {
                throw ValidationException::withMessages(['opening' => 'Opening rules are locked after technical opening.']);
            }
            if ($locked->bidsAreOpened() && $locked->award_criterion && $locked->award_criterion !== $data['award_criterion']) {
                throw ValidationException::withMessages(['opening' => 'The recorded project award criterion cannot change after technical opening.']);
            }
            $before = $locked->only(['award_criterion', 'opening_documents_reference', 'minimum_technical_score']);
            $locked->update([
                'award_criterion' => $data['award_criterion'],
                'opening_documents_reference' => $data['opening_documents_reference'],
                'minimum_technical_score' => $data['award_criterion'] === 'mearb' ? $data['minimum_technical_score'] : null,
            ]);
            AuditLog::log('bid_opening_rules_recorded', $locked, $before, $locked->only(array_keys($before)) + ['recorded_by' => $actor->id]);
        });
    }

    public function recordScore(Bid $bid, User $actor, array $input): void
    {
        $this->authorize($actor);
        $data = Validator::make($input, [
            'technical_score' => 'required|numeric|min:0|max:999999',
            'technical_score_basis' => 'required|string|min:5|max:4000',
        ])->validate();
        DB::transaction(function () use ($bid, $actor, $data) {
            // All score/opening writers lock the project first, including MARB ranking.
            $project = Project::lockForUpdate()->findOrFail($bid->project_id);
            $locked = Bid::lockForUpdate()->findOrFail($bid->id)->setRelation('project', $project);
            if (! in_array($project->award_criterion, ['mearb', 'marb'], true) || ! $project->opening_documents_reference
                || $locked->isSealed() || ! $locked->documents_validated_at || $locked->progress()->facts()['disqualified']
                || $locked->technical_scored_at || $locked->financial_opened_at) {
                throw ValidationException::withMessages(['opening' => 'Record technical examination first. A technical score can be recorded once for an eligible MEARB/MARB bid.']);
            }
            $at = now('Asia/Manila');
            $locked->forceFill($data + ['technical_scored_at' => $at, 'technical_scored_by' => $actor->id])->save();
            AuditLog::log('bid_technical_score_recorded', $locked, [], $data + [
                'recorded_by' => $actor->id, 'recorded_at' => $at->toIso8601String(),
                'documents_reference' => $project->opening_documents_reference, 'timezone' => 'Asia/Manila',
            ]);
        });
    }

    /**
     * Open technical and eligibility files once the scheduled Manila time
     * arrives, exactly like a recorded opening: submissions close and every
     * bidder, the BAC and the assigned staff are told. Only a published,
     * active competitive project whose deadline has also passed is opened.
     */
    public function openScheduledTechnical(Project $project): bool
    {
        $opened = DB::transaction(function () use ($project) {
            $locked = Project::lockForUpdate()->with('schedule')->findOrFail($project->id);
            $now = now('Asia/Manila');
            $openingAt = $locked->schedule?->bid_opening_date;
            $deadline = $locked->bidSubmissionDeadline();
            $publishedAt = $locked->publicationTime();
            if (! $locked->requiresRecordedBidOpening() || $locked->bids_opened_at !== null
                || $locked->status !== 'open' || $locked->archived_at !== null || $locked->failed_bidding_at !== null
                || ! $openingAt || $openingAt->greaterThan($now)
                || ! $deadline || $deadline->greaterThan($now)
                || ($publishedAt && $publishedAt->greaterThan($now))) {
                return null;
            }
            app(BidWorkflow::class)->recordTechnicalOpening($locked, null, 'bid_technical_documents_auto_opened', [
                'opened_at' => $now->toIso8601String(),
                'scheduled_at' => $openingAt->timezone('Asia/Manila')->toIso8601String(),
                'method' => 'scheduled_automatic_opening',
            ]);

            return $locked;
        });
        if ($opened === null) {
            return false;
        }

        $message = 'Technical and eligibility components of '.$opened->title.' opened at the scheduled bid opening. Financial components stay sealed until opened by the BAC.';
        SystemNotification::createForRole('admin', 'Bids opened as scheduled', $message, 'project_status', ['project_id' => $opened->id]);
        SystemNotification::createForUsers($opened->assignments()->pluck('staff_id')->filter()->unique(), 'Bids opened as scheduled', $message, 'project_status', ['project_id' => $opened->id]);
        Bid::where('project_id', $opened->id)->get()->each(fn (Bid $bid) => event(new BidWorkflowUpdated($bid)));

        return true;
    }

    /**
     * Open every project whose bid-opening schedule has arrived (server
     * time). Runs from the scheduler, the cron endpoint and each request
     * (ApplyProcurementClock), so the opening never waits for a page visit
     * of the project itself; the query is cheap when nothing is due.
     */
    public function openDueTechnicalProjects(): int
    {
        $alternativeModes = collect(ProcurementMode::MODES)->reject(fn ($mode) => $mode['family'] === ProcurementMode::FAMILY_COMPETITIVE)->keys()->all();
        $now = now('Asia/Manila');
        $projects = Project::query()->with('schedule')
            ->where('status', 'open')->whereNull('bids_opened_at')->whereNull('archived_at')->whereNull('failed_bidding_at')
            ->where(fn ($mode) => $mode->whereNull('procurement_mode')->orWhereNotIn('procurement_mode', $alternativeModes))
            ->whereHas('schedule', fn ($query) => $query->where('bid_opening_date', '<=', $now))
            ->get();
        $opened = 0;
        foreach ($projects as $project) if ($this->openScheduledTechnical($project)) $opened++;
        return $opened;
    }
    public function financialBlocker(Bid $bid): ?string
    {
        $project = $bid->project;
        if (! $project?->requiresRecordedBidOpening()) return 'This action is for competitive bids.';
        if ($bid->financial_opened_at) return 'Financial opening is already recorded.';
        if ($bid->isSealed()) return 'Technical and eligibility documents are not yet available.';
        if ($project->archived_at || $project->failed_bidding_at || $bid->progress()->facts()['disqualified']
            || $bid->progress()->facts()['not_awarded']) return 'This bid is closed for opening.';
        if (! $bid->documents_validated_at || ! $bid->documents_validated_by) {
            return 'Approve the technical and eligibility review first.';
        }
        if (! in_array($project->award_criterion, self::CRITERIA, true)
            || ! $project->opening_documents_reference) {
            return 'Award criteria and the bidding-documents reference must be recorded first.';
        }
        return null;
    }
    public function openFinancial(Bid $bid, User $actor, ?string $password = null): void
    {
        $this->authorize($actor);
        if (! is_string($password) || strlen($password) < 6 || strlen($password) > 128) {
            throw ValidationException::withMessages(['opening_password' => 'Enter the financial password provided by the bidder.']);
        }
        $result = DB::transaction(function () use ($bid, $actor, $password) {
            $project = Project::lockForUpdate()->with('schedule')->findOrFail($bid->project_id);
            $this->openScheduledTechnical($project);
            $locked = Bid::lockForUpdate()->findOrFail($bid->id)->setRelation('project', $project->fresh(['schedule']));
            if ($error = $this->financialBlocker($locked)) return ['error' => $error];
            $at = now('Asia/Manila');
            if (! filled($locked->financial_opening_password_hash)) return ['error' => 'No bidder financial password is stored for this submission.'];
            if ($locked->financial_password_locked_until && $locked->financial_password_locked_until->greaterThan($at)) {
                return ['error' => 'Financial password attempts are temporarily locked.'];
            }
            if (! Hash::check($password, $locked->financial_opening_password_hash)) {
                $attempts = $locked->financial_password_locked_until && $locked->financial_password_locked_until->lessThanOrEqualTo($at)
                    ? 0 : (int) $locked->financial_password_attempts;
                $attempts++;
                $lockedUntil = $attempts >= 5 ? $at->copy()->addMinutes(15) : null;
                $locked->forceFill(['financial_password_attempts' => $attempts, 'financial_password_locked_until' => $lockedUntil])->save();
                AuditLog::log('bid_financial_password_failed', $locked, [], [
                    'bid_id' => $locked->id, 'project_id' => $project->id, 'attempt' => $attempts,
                    'locked_until' => $lockedUntil?->toIso8601String(), 'attempted_by' => $actor->id,
                ]);
                return ['error' => $lockedUntil
                    ? 'Too many incorrect passwords. Password entry is locked for 15 minutes.'
                    : 'The financial password is incorrect. '.(5 - $attempts).' attempts remain.'];
            }
            $details = [
                'component' => 'financial', 'opened_at' => $at->toIso8601String(),
                'opened_by' => $actor->id, 'timezone' => 'Asia/Manila',
                'award_criterion' => $project->award_criterion,
                'documents_reference' => $project->opening_documents_reference,
                'technical_score' => $locked->technical_score, 'method' => 'bidder_password',
            ];
            $locked->forceFill([
                'financial_password_attempts' => 0, 'financial_password_locked_until' => null,
                'financial_opening_method' => 'bidder_password', 'financial_opening_exception_reason' => null,
                'financial_opened_at' => $at, 'financial_opened_by' => $actor->id,
            ])->save();
            $locked->trackings()->create([
                'bidder_id' => $locked->user_id, 'project_id' => $project->id,
                'stage' => 'financial_opening', 'decision' => 'opened', 'created_by' => $actor->id,
                'status_title' => 'Financial Component Opened',
                'status_description' => 'BAC Admin verified the bidder-provided password and recorded the financial opening.',
                'status_type' => 'info', 'visible_to_bidder' => true, 'details' => $details,
            ]);
            AuditLog::log('bid_financial_opened', $locked, [], $details);
            return ['error' => null];
        });
        if ($result['error'] !== null) throw ValidationException::withMessages(['opening' => $result['error']]);
    }
}