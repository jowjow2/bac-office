<?php

namespace App\Support;

use App\Models\Award;
use App\Models\ContractImplementation;
use App\Models\ContractImplementationEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Infrastructure works progress, inspection recommendation, and contract close-out. */
class InfrastructureImplementationWorkflow
{
    public function eligible(Award $award): bool
    {
        $award->loadMissing(['project', 'bid']);
        return ! $award->isCancelled()
            && strtolower((string) $award->project?->category) === 'infrastructure'
            && $award->bid?->contract_signed_at !== null
            && $award->bid?->notice_to_proceed_at !== null;
    }

    public function ensure(Award $award): ContractImplementation
    {
        if (! $this->eligible($award)) {
            throw ValidationException::withMessages(['implementation' => 'Infrastructure tracking starts only after the winning contract is signed and its NTP is issued.']);
        }

        return ContractImplementation::firstOrCreate(['award_id' => $award->id], ['status' => ContractImplementation::INFRA_IN_PROGRESS]);
    }

    public function configure(Award $award, User $actor, array $data, UploadedFile $document): ContractImplementation
    {
        $record = $this->ensure($award);
        return DB::transaction(function () use ($award, $actor, $data, $document, $record) {
            $record = ContractImplementation::query()->lockForUpdate()->findOrFail($record->id);
            if ($record->isConfigured() || $record->events()->exists()) {
                throw ValidationException::withMessages(['implementation' => 'Contract terms can only be recorded once before progress reporting begins.']);
            }
            $ntp = $award->bid->notice_to_proceed_at->timezone('Asia/Manila')->toDateString();
            if ($data['delivery_deadline'] < $ntp) {
                throw ValidationException::withMessages(['delivery_deadline' => 'The contract completion deadline cannot be before the NTP date.']);
            }
            $path = $document->store('contract-implementation/private', 'local');
            $record->fill([
                'status' => ContractImplementation::INFRA_IN_PROGRESS,
                'delivery_deadline' => $data['delivery_deadline'],
                'delivery_location' => trim($data['delivery_location']),
                'signed_contract_reference' => trim($data['signed_contract_reference']),
                'signed_contract_file_path' => $path,
                'signed_contract_file_name' => $document->getClientOriginalName(),
                'contract_items' => array_values($data['contract_items']),
                'configured_by' => $actor->id,
                'configured_at' => app(ProcurementClock::class)->now(),
            ])->save();
            $this->event($record, $actor, 'infrastructure_terms_recorded', null, ContractImplementation::INFRA_IN_PROGRESS, $data['remarks'], ['deadline' => $data['delivery_deadline'], 'site' => $data['delivery_location'], 'items' => $record->contract_items], ['path' => $path, 'name' => $document->getClientOriginalName()]);
            $this->notifyWinner($award, 'Infrastructure contract tracking is ready', 'The BAC recorded your signed contract terms and NTP. Submit work progress updates from Awarded contracts.');
            return $record->fresh(['events.actor']);
        });
    }

    /** Statuses in which the admin may still correct the recorded terms (before formal acceptance). */
    public const CORRECTABLE_STATUSES = [
        ContractImplementation::INFRA_IN_PROGRESS,
        ContractImplementation::INFRA_FOR_INSPECTION,
        ContractImplementation::INFRA_FOR_CORRECTION,
    ];

    public function canCorrectTerms(ContractImplementation $record): bool
    {
        return $record->isConfigured() && in_array($record->status, self::CORRECTABLE_STATUSES, true);
    }

    /** Fix terms that were recorded wrongly; the status and history stay as they are. */
    public function correctTerms(Award $award, User $actor, array $data, ?UploadedFile $document): ContractImplementation
    {
        if ($actor->role !== 'admin') {
            throw ValidationException::withMessages(['implementation' => 'Only the BAC admin can correct recorded contract terms.']);
        }
        $record = $this->ensure($award);
        return DB::transaction(function () use ($award, $actor, $data, $document, $record) {
            $record = ContractImplementation::query()->lockForUpdate()->findOrFail($record->id);
            if (! $this->canCorrectTerms($record)) {
                throw ValidationException::withMessages(['implementation' => 'Contract terms can only be corrected before the work is formally accepted.']);
            }
            $ntp = $award->bid->notice_to_proceed_at->timezone('Asia/Manila')->toDateString();
            if ($data['delivery_deadline'] < $ntp) {
                throw ValidationException::withMessages(['delivery_deadline' => 'The contract completion deadline cannot be before the NTP date.']);
            }
            $terms = fn (ContractImplementation $r) => [
                'delivery_deadline' => $r->delivery_deadline?->toDateString(),
                'delivery_location' => $r->delivery_location,
                'signed_contract_reference' => $r->signed_contract_reference,
                'contract_items' => array_values($r->contract_items ?? []),
            ];
            $before = $terms($record);
            $record->fill([
                'delivery_deadline' => $data['delivery_deadline'],
                'delivery_location' => trim($data['delivery_location']),
                'signed_contract_reference' => trim($data['signed_contract_reference']),
                'contract_items' => array_values($data['contract_items']),
            ]);
            $after = $terms($record);
            if ($before === $after && ! $document) {
                throw ValidationException::withMessages(['implementation' => 'Nothing was changed in the contract terms.']);
            }
            $file = null;
            if ($document) {
                $file = ['path' => $document->store('contract-implementation/private', 'local'), 'name' => $document->getClientOriginalName()];
                $record->fill(['signed_contract_file_path' => $file['path'], 'signed_contract_file_name' => $file['name']]);
            }
            $record->save();
            $this->event($record, $actor, 'infrastructure_terms_corrected', $record->status, $record->status, $data['remarks'], ['before' => $before, 'after' => $after], $file);
            \App\Models\AuditLog::log('infrastructure_terms_corrected', $record, $before, $after, ['user_id' => $actor->id]);
            $this->notifyWinner($award, 'Contract terms corrected', 'The BAC corrected the recorded terms of your infrastructure contract. Review them in Awarded contracts.');
            return $record->fresh(['events.actor']);
        });
    }

    public function progress(Award $award, User $supplier, array $data, UploadedFile $document, bool $correction = false): ContractImplementation
    {
        $record = $this->ensure($award);
        return DB::transaction(function () use ($award, $supplier, $data, $document, $correction, $record) {
            $record = ContractImplementation::query()->lockForUpdate()->findOrFail($record->id);
            $expected = $correction ? ContractImplementation::INFRA_FOR_CORRECTION : ContractImplementation::INFRA_IN_PROGRESS;
            if (! $record->isConfigured() || $record->status !== $expected) {
                throw ValidationException::withMessages(['status' => 'Progress or correction updates are not allowed at the current stage.']);
            }
            $details = ['progress_percent' => (int) $data['progress_percent'], 'milestone' => trim($data['milestone'])];
            $clockNow = app(ProcurementClock::class)->now();
            $recentDuplicate = $record->events()->whereIn('action', ['infrastructure_progress_submitted', 'infrastructure_correction_submitted'])->where('actor_id', $supplier->id)->where('remarks', trim($data['remarks']))->where('occurred_at', '>=', $clockNow->copy()->subMinutes(2))->latest('id')->first();
            if ($recentDuplicate && (int) ($recentDuplicate->details['progress_percent'] ?? -1) === (int) $details['progress_percent'] && ($recentDuplicate->details['milestone'] ?? null) === $details['milestone']) {
                throw ValidationException::withMessages(['submission' => 'This progress update was just submitted. Wait before sending another identical update.']);
            }
            $path = $document->store('contract-implementation/private', 'local');
            $to = ! empty($data['request_inspection']) ? ContractImplementation::INFRA_FOR_INSPECTION : $record->status;
            $this->transition($record, $supplier, $correction ? 'infrastructure_correction_submitted' : 'infrastructure_progress_submitted', $to, $data['remarks'], $details, ['path' => $path, 'name' => $document->getClientOriginalName()]);
            $this->notifyOffice($award, 'Infrastructure progress update received', 'The winning supplier submitted an infrastructure progress update.');
            return $record->fresh(['events.actor']);
        });
    }

    public function inspect(Award $award, User $actor, array $data, UploadedFile $document): ContractImplementation
    {
        $record = $this->ensure($award);
        return DB::transaction(function () use ($award, $actor, $data, $document, $record) {
            $record = ContractImplementation::query()->lockForUpdate()->findOrFail($record->id);
            if (! $record->isConfigured() || $record->status !== ContractImplementation::INFRA_FOR_INSPECTION) {
                throw ValidationException::withMessages(['status' => 'Site inspection can only be recorded after the supplier requests inspection.']);
            }
            $to = $data['outcome'] === 'correction' ? ContractImplementation::INFRA_FOR_CORRECTION : ContractImplementation::INFRA_RECOMMENDED;
            $path = $document->store('contract-implementation/private', 'local');
            $this->transition($record, $actor, 'infrastructure_site_inspection', $to, $data['remarks'], ['outcome' => $data['outcome'], 'findings' => trim($data['findings']), 'deficiencies' => $data['deficiencies'] ?? null], ['path' => $path, 'name' => $document->getClientOriginalName()]);
            $this->notifyWinner($award, $to === ContractImplementation::INFRA_FOR_CORRECTION ? 'Corrections requested' : 'Site inspection completed', $to === ContractImplementation::INFRA_FOR_CORRECTION ? 'The end-user office recorded deficiencies. Review the findings and submit corrected work for reinspection.' : 'The end-user office recommends your completed infrastructure work for formal LGU acceptance.');
            return $record->fresh(['events.actor']);
        });
    }

    public function action(Award $award, User $actor, string $action, array $data, UploadedFile $document): ContractImplementation
    {
        $record = $this->ensure($award);
        $moves = [
            'accept' => [ContractImplementation::INFRA_RECOMMENDED, ContractImplementation::INFRA_ACCEPTED],
            'payment_processing' => [ContractImplementation::INFRA_ACCEPTED, ContractImplementation::INFRA_PAYMENT_PROCESSING],
            'paid' => [ContractImplementation::INFRA_PAYMENT_PROCESSING, ContractImplementation::INFRA_PAID],
            'complete' => [ContractImplementation::INFRA_PAID, ContractImplementation::INFRA_COMPLETED],
        ];
        if (! isset($moves[$action])) throw ValidationException::withMessages(['action' => 'Unknown infrastructure contract action.']);
        return DB::transaction(function () use ($award, $actor, $action, $data, $document, $record, $moves) {
            $record = ContractImplementation::query()->lockForUpdate()->findOrFail($record->id);
            [$from, $to] = $moves[$action];
            if ($record->status === ContractImplementation::INFRA_COMPLETED) {
                throw ValidationException::withMessages(['status' => 'This contract is already marked completed.']);
            }
            if (! $record->isConfigured() || $record->status !== $from) {
                throw ValidationException::withMessages(['status' => 'This contract action is not valid at the current stage.']);
            }
            $path = $document->store('contract-implementation/private', 'local');
            $this->transition($record, $actor, 'infrastructure_'.$action, $to, $data['remarks'], [], ['path' => $path, 'name' => $document->getClientOriginalName()]);
            if ($action === 'complete') {
                $this->markContractCompleted($award, $record, $actor, $from, $document->getClientOriginalName());
            }
            $labels = [
                'accept' => ['Infrastructure work accepted', 'LGU staff formally accepted the infrastructure work.'],
                'payment_processing' => ['Payment processing recorded', 'The LGU recorded that contract payment processing has started.'],
                'paid' => ['Payment status updated', 'The LGU recorded the contract as paid. No money is transferred through this system.'],
                'complete' => ['Infrastructure contract completed', 'LGU staff marked the infrastructure contract implementation completed.'],
            ];
            [$title, $message] = $labels[$action];
            $this->notifyWinner($award, $title, $message);
            return $record->fresh(['events.actor']);
        });
    }

    /**
     * The completed contract also completes the procurement, so the awards list, the
     * winner's contract view and the reports read "Completed" from the same record.
     */
    private function markContractCompleted(Award $award, ContractImplementation $record, User $actor, string $from, string $documentName): void
    {
        $at = app(ProcurementClock::class)->now();
        $project = \App\Models\Project::query()->lockForUpdate()->find($award->project_id);
        if ($project && $project->completed_at === null) {
            $project->update(['completed_at' => $at]);
        }
        if ($award->bid && $award->bid->project_completed_at === null) {
            $award->bid->update([
                'workflow_step' => \App\Models\Bid::STEP_PROJECT_COMPLETED,
                'project_completed_at' => $at,
                'project_completed_by' => $actor->id,
                'workflow_step_updated_at' => $at,
                'workflow_step_updated_by' => $actor->id,
            ]);
        }
        \App\Models\AuditLog::log('infrastructure_contract_completed', $record, ['status' => $from], [
            'status' => ContractImplementation::INFRA_COMPLETED,
            'completed_at' => $at->toDateTimeString(),
            'project_id' => $award->project_id,
            'award_id' => $award->id,
            'completion_record' => $documentName,
        ], ['user_id' => $actor->id]);
    }

    private function transition(ContractImplementation $record, User $actor, string $action, string $to, ?string $remarks, array $details, ?array $file): void
    {
        $from = $record->status;
        $record->update(['status' => $to]);
        $this->event($record, $actor, $action, $from, $to, $remarks, $details, $file);
    }

    private function event(ContractImplementation $record, User $actor, string $action, ?string $from, ?string $to, ?string $remarks, array $details, ?array $file): ContractImplementationEvent
    {
        $at = app(ProcurementClock::class)->now();
        $event = $record->events()->make(['action' => $action, 'status_from' => $from, 'status_to' => $to, 'actor_id' => $actor->id, 'actor_role' => $actor->role, 'occurred_at' => $at, 'remarks' => trim((string) $remarks), 'details' => $details, 'document_path' => $file['path'] ?? null, 'document_name' => $file['name'] ?? null]);
        $event->created_at = $at;
        $event->updated_at = $at;
        $event->save();
        return $event;
    }

    private function notifyWinner(Award $award, string $title, string $message): void
    {
        $this->notify((int) ($award->bid?->user_id ?? $award->bidder_id), $title, $message, route('bidder.awarded-contracts', ['award' => $award->id]), $award);
    }

    private function notifyOffice(Award $award, string $title, string $message): void
    {
        $ids = User::query()->where('role', 'end_user')->where('office', $award->project?->end_user_unit)->pluck('id');
        foreach ($ids as $id) $this->notify((int) $id, $title, $message, route('end-user.infrastructure.index'), $award);
    }

    private function notify(int $userId, string $title, string $message, string $url, Award $award): void
    {
        $at = app(ProcurementClock::class)->now();
        $notification = new \App\Models\UserNotification([
            'user_id' => $userId, 'title' => $title, 'message' => $message, 'type' => 'system_alert',
            'data' => ['important' => true, 'url' => $url, 'award_id' => $award->id, 'kind' => 'infrastructure_implementation'],
        ]);
        $notification->created_at = $at;
        $notification->updated_at = $at;
        $notification->save();
    }
}
