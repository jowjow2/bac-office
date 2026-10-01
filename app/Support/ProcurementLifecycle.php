<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\Project;
use App\Models\ProjectProceeding;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Project-level records of the procurement: the PhilGEPS posting done by the
 * BAC Secretariat, BAC proceedings (pre-bid conference, clarifications, bid
 * bulletins, resolutions, requests for reconsideration), and the inspection
 * and acceptance that close the contract. Bid-level decisions stay in
 * BidWorkflow.
 *
 * The system does not post to PhilGEPS: the Secretariat posts the Invitation
 * to Bid on PhilGEPS and records the reference number, link and date here.
 */
class ProcurementLifecycle
{
    public const DOCUMENT_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

    /**
     * @throws ValidationException
     */
    public function recordPublication(Project $project, User $actor, array $input): Project
    {
        if (in_array($project->status, ['draft', 'approved_for_bidding'], true)) {
            throw ValidationException::withMessages(['philgeps_posted_at' => 'Publish the project for bidding first, then record its PhilGEPS posting.']);
        }

        $postedAt = Carbon::parse($input['philgeps_posted_at'])->startOfDay();
        $deadline = $project->bidSubmissionDeadline();
        $mode = $project->mode();

        if ($postedAt->isAfter(today())) {
            throw ValidationException::withMessages(['philgeps_posted_at' => 'The PhilGEPS posting date cannot be in the future.']);
        }

        if ($deadline !== null && $postedAt->isAfter($deadline->copy()->startOfDay())) {
            throw ValidationException::withMessages(['philgeps_posted_at' => 'The PhilGEPS posting date must be before the '.lcfirst($mode->deadlineLabel()).'.']);
        }

        // 7 calendar days for an Invitation to Bid, 3 for an RFQ that must be posted.
        $days = $mode->minimumPostingDays();
        if ($deadline !== null && $days > 0 && $postedAt->copy()->addDays($days)->isAfter($deadline)) {
            throw ValidationException::withMessages(['philgeps_posted_at' => "The {$mode->noticeLabel()} must stay posted for at least {$days} calendar days before the ".lcfirst($mode->deadlineLabel()).'. '.$mode->postingRule()]);
        }

        $before = $project->only(['philgeps_reference_no', 'philgeps_url', 'philgeps_posted_at']);

        $project->update([
            'philgeps_reference_no' => trim((string) $input['philgeps_reference_no']),
            'philgeps_url' => filled($input['philgeps_url'] ?? null) ? trim($input['philgeps_url']) : null,
            'philgeps_posted_at' => $postedAt->toDateString(),
            'philgeps_posted_recorded_by' => $actor->id,
        ]);

        AuditLog::log('philgeps_posting_recorded', $project, $before, $project->only(array_keys($before)));

        return $project;
    }

    /**
     * Pre-bid conference, clarification, bid bulletin, BAC resolution or a
     * request for reconsideration, with its supporting document.
     *
     * @throws ValidationException
     */
    public function recordProceeding(Project $project, User $actor, array $input, ?UploadedFile $file): ProjectProceeding
    {
        $type = $input['type'];
        $occurredAt = Carbon::parse($input['occurred_at']);
        $deadline = $project->bidSubmissionDeadline();
        $mode = $project->mode();
        $deadlineLabel = lcfirst($mode->deadlineLabel());

        if (! array_key_exists($type, ProjectProceeding::typesFor($mode))) {
            throw ValidationException::withMessages(['type' => ProjectProceeding::labelFor($type, $mode).' is not part of '.$mode->label().'.']);
        }

        $unpublished = in_array($project->status, ['draft', 'approved_for_bidding'], true);
        if ($unpublished && ! in_array($type, ProjectProceeding::PRE_PUBLICATION_TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Proceedings are recorded once the project is posted.']);
        }
        // The pre-procurement conference comes before the Invitation to Bid (RA 12009 IRR Sec. 49.1).
        if ($type === ProjectProceeding::TYPE_PRE_PROCUREMENT && ! $unpublished) {
            throw ValidationException::withMessages(['type' => 'The pre-procurement conference is held before the Invitation to Bid is published.']);
        }

        // Collect every problem so the form shows them together (first message per field).
        $errors = [];
        $fail = function (string $field, string $message) use (&$errors): void {
            $errors[$field] ??= $message;
        };

        if ($occurredAt->isFuture()) {
            $fail('occurred_at', 'Record a proceeding only after it has taken place; the date cannot be in the future.');
        }

        $beforeDeadline = in_array($type, [ProjectProceeding::TYPE_PRE_BID, ProjectProceeding::TYPE_CLARIFICATION, ProjectProceeding::TYPE_BID_BULLETIN, ProjectProceeding::TYPE_RFQ_ISSUED], true);
        if ($beforeDeadline && $deadline !== null && $occurredAt->greaterThanOrEqualTo($deadline)) {
            $fail('occurred_at', ProjectProceeding::labelFor($type, $mode)." must be before the {$deadlineLabel}.");
        }

        if ($mode->isCompetitive() && $deadline !== null) {
            // RA 12009 IRR Sec. 51.2 / RA 9184 IRR Sec. 22.2: at least 12 calendar
            // days before the deadline; under RA 12009 also not earlier than 7
            // calendar days after the PhilGEPS posting.
            if ($type === ProjectProceeding::TYPE_PRE_BID) {
                if ($occurredAt->copy()->addDays(12)->isAfter($deadline)) {
                    $fail('occurred_at', 'The pre-bid conference must be held at least 12 calendar days before the bid submission deadline.');
                }
                if ($mode->isRa12009() && $project->philgeps_posted_at && $occurredAt->copy()->startOfDay()->lessThan($project->philgeps_posted_at->copy()->addDays(7))) {
                    $fail('occurred_at', 'Under RA 12009, the pre-bid conference cannot be earlier than 7 calendar days after the PhilGEPS posting (IRR Sec. 51.2).');
                }
            }

            // Bid bulletins: at least 7 calendar days before the deadline (RA 12009 IRR Sec. 51.5; RA 9184 IRR Sec. 22.5).
            if ($type === ProjectProceeding::TYPE_BID_BULLETIN && $occurredAt->copy()->addDays(7)->isAfter($deadline)) {
                $fail('occurred_at', 'A bid bulletin must be issued at least 7 calendar days before the bid submission deadline. Extend the deadline first if the bulletin changes the bidding documents.');
            }
        }

        if ($type === ProjectProceeding::TYPE_RFQ_ISSUED) {
            // SVP and Negotiated Procurement invite at least three suppliers
            // (RA 12009 IRR Sec. 34.3(c) and 35.1.1(b)); Direct Contracting
            // goes to the one identified supplier (Sec. 31.3).
            $minimum = $mode->isDirectContracting() ? 1 : 3;
            if ((int) ($input['recipients_count'] ?? 0) < $minimum) {
                $fail('recipients_count', $mode->isDirectContracting()
                    ? 'Enter the number of suppliers the RFQ was sent to.'
                    : 'Send the RFQ to at least three (3) suppliers of known qualifications, then record it.');
            }
        }

        if ($type === ProjectProceeding::TYPE_ABSTRACT) {
            if ($deadline === null || $occurredAt->lessThan($deadline)) {
                $fail('occurred_at', "The Abstract of Quotations is prepared after the {$deadlineLabel}.");
            }

            if ($project->bids()->get()->reject(fn ($bid) => $bid->isDraft())->isEmpty()) {
                $fail('type', 'No '.$mode->submissionNoun().' has been received. Extend the '.$deadlineLabel.' until at least one arrives'.($mode->isRa12009() ? ' (RA 12009 IRR Sec. 34.3(d)).' : '.'));
            }
        }

        // Posting at a conspicuous place runs for 7 calendar days from publication (Sec. 50.3.1(a)).
        if ($type === ProjectProceeding::TYPE_POSTING_CERTIFICATE && $project->published_at
            && $occurredAt->copy()->startOfDay()->lessThan($project->published_at->copy()->startOfDay()->addDays(7))) {
            $fail('occurred_at', 'The certificate of posting is issued after the 7 calendar days of posting at a conspicuous place (IRR Sec. 50.3.1(a)).');
        }

        // The COA representative and at least two observers (Sec. 43.1).
        if ($type === ProjectProceeding::TYPE_OBSERVERS && (int) ($input['recipients_count'] ?? 0) < 3) {
            $fail('recipients_count', 'Invite the COA representative and at least two (2) observers: enter 3 or more.');
        }

        if ($type === ProjectProceeding::TYPE_VIDEO_RECORDING && blank($input['reference_no'] ?? null) && blank($input['summary'] ?? null)) {
            $fail('reference_no', 'Enter the livestream link or the recording reference.');
        }

        if ($type === ProjectProceeding::TYPE_RECONSIDERATION && ! $project->bidsAreOpened()) {
            $fail('type', 'A request for reconsideration can be recorded only after the '.strtolower($mode->openingLabel()).'.');
        }

        if (in_array($type, [ProjectProceeding::TYPE_BID_BULLETIN, ProjectProceeding::TYPE_BAC_RESOLUTION, ProjectProceeding::TYPE_ABSTRACT], true) && blank($input['reference_no'] ?? null)) {
            $fail('reference_no', 'Enter the document number of the '.strtolower(ProjectProceeding::labelFor($type, $mode)).'.');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $outcome = $type === ProjectProceeding::TYPE_RECONSIDERATION
            ? ($input['outcome'] ?? 'pending')
            : null;

        return $this->storeProceeding($project, $actor, $type, $input['title'] ?? null, $occurredAt, $input, $outcome, $file);
    }

    /**
     * The end-user office / inspection committee inspects the delivery or
     * work after the Notice to Proceed.
     *
     * @throws ValidationException
     */
    public function recordInspection(Project $project, User $actor, array $input, ?UploadedFile $file): ProjectProceeding
    {
        $bid = $this->contractedBid($project);
        $date = Carbon::parse($input['occurred_at']);

        if ($date->isFuture() || $date->lt($bid->notice_to_proceed_at->copy()->startOfDay())) {
            throw ValidationException::withMessages(['occurred_at' => 'The inspection date must be on or after the Notice to Proceed and not in the future.']);
        }

        if (blank($input['reference_no'] ?? null)) {
            throw ValidationException::withMessages(['reference_no' => 'Enter the inspection report number.']);
        }

        return $this->storeProceeding($project, $actor, ProjectProceeding::TYPE_INSPECTION, 'Inspection', $date, $input, null, $file);
    }

    /**
     * Acceptance of the delivery or work completes the contract.
     *
     * @throws ValidationException
     */
    public function recordAcceptance(Project $project, User $actor, array $input, ?UploadedFile $file): ProjectProceeding
    {
        $bid = $this->contractedBid($project);
        $inspection = $project->proceedings()->where('type', ProjectProceeding::TYPE_INSPECTION)->latest('occurred_at')->first();

        if ($inspection === null) {
            throw ValidationException::withMessages(['occurred_at' => 'Record the inspection before the acceptance.']);
        }

        if ($project->isCompleted()) {
            throw ValidationException::withMessages(['occurred_at' => 'The acceptance is already recorded for this project.']);
        }

        $date = Carbon::parse($input['occurred_at']);
        if ($date->isFuture() || $date->lt($inspection->occurred_at->copy()->startOfDay())) {
            throw ValidationException::withMessages(['occurred_at' => 'The acceptance date must be on or after the inspection and not in the future.']);
        }

        if (blank($input['reference_no'] ?? null)) {
            throw ValidationException::withMessages(['reference_no' => 'Enter the Inspection and Acceptance Report (IAR) or certificate number.']);
        }

        return DB::transaction(function () use ($project, $actor, $input, $file, $bid, $date) {
            $proceeding = $this->storeProceeding($project, $actor, ProjectProceeding::TYPE_ACCEPTANCE, 'Acceptance', $date, $input, null, $file);

            $project->update(['completed_at' => $date]);
            $bid->update([
                'workflow_step' => Bid::STEP_PROJECT_COMPLETED,
                'project_completed_at' => $date,
                'project_completed_by' => $actor->id,
                'workflow_step_updated_at' => now(),
                'workflow_step_updated_by' => $actor->id,
            ]);

            AuditLog::log('procurement_completed', $project, null, ['completed_at' => $date->toDateTimeString(), 'bid_id' => $bid->id]);

            return $proceeding;
        });
    }

    /**
     * @throws ValidationException
     */
    private function contractedBid(Project $project): Bid
    {
        $bid = $project->bids()->whereNotNull('notice_to_proceed_at')->latest('notice_to_proceed_at')->first();

        if ($bid === null) {
            throw ValidationException::withMessages(['occurred_at' => 'Inspection and acceptance come after the Notice to Proceed is issued.']);
        }

        return $bid;
    }

    /**
     * @throws ValidationException
     */
    private function storeProceeding(Project $project, User $actor, string $type, ?string $title, Carbon $occurredAt, array $input, ?string $outcome, ?UploadedFile $file): ProjectProceeding
    {
        if ($file !== null && (! $file->isValid() || ! in_array(strtolower((string) $file->getClientOriginalExtension()), self::DOCUMENT_EXTENSIONS, true))) {
            throw ValidationException::withMessages(['document' => 'Attach the document as a PDF, image, or Word file.']);
        }

        $path = $file?->storeAs('proceedings/'.$project->id, Str::random(32).'.'.strtolower($file->getClientOriginalExtension()), 'local');

        try {
            $proceeding = ProjectProceeding::create([
                'project_id' => $project->id,
                'type' => $type,
                'title' => filled($title) ? trim($title) : ProjectProceeding::labelFor($type, $project->mode()),
                'occurred_at' => $occurredAt,
                'reference_no' => filled($input['reference_no'] ?? null) ? trim($input['reference_no']) : null,
                'recipients_count' => filled($input['recipients_count'] ?? null) ? (int) $input['recipients_count'] : null,
                'summary' => filled($input['summary'] ?? null) ? trim($input['summary']) : null,
                'outcome' => $outcome,
                'file_path' => $path ?: null,
                'original_name' => $file?->getClientOriginalName(),
                'recorded_by' => $actor->id,
            ]);
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        AuditLog::log('proceeding_recorded', $project, null, [
            'proceeding_id' => $proceeding->id,
            'type' => $type,
            'reference_no' => $proceeding->reference_no,
            'occurred_at' => $occurredAt->toDateTimeString(),
        ]);

        return $proceeding;
    }
}
