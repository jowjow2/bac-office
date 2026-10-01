<?php

namespace App\Support;

use App\Models\Award;
use App\Models\ContractImplementation;
use App\Models\ContractImplementationEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContractImplementationWorkflow
{
    public function eligible(Award $award): bool
    {
        $award->loadMissing(['project', 'bid']);
        return ! $award->isCancelled()
            && strtolower((string) $award->project?->category) === 'goods'
            && $award->bid?->notice_to_proceed_at !== null
            && $award->bid?->contract_signed_at !== null;
    }

    /**
     * Lazily creates tracking for historical Goods awards which already have
     * an NTP. Contract dates and quantities stay unset until copied from the
     * signed contract by an authorized LGU user.
     */
    public function ensure(Award $award): ContractImplementation
    {
        $award->loadMissing(['project', 'bid']);
        if (! $this->eligible($award)) {
            throw ValidationException::withMessages(['implementation' => 'Contract implementation is available only for a Goods award after contract signing and NTP issuance.']);
        }

        return ContractImplementation::firstOrCreate(
            ['award_id' => $award->id],
            ['status' => ContractImplementation::FOR_DELIVERY]
        );
    }

    public function configure(Award $award, User $actor, array $input, ?UploadedFile $document): ContractImplementation
    {
        $implementation = $this->ensure($award);

        return DB::transaction(function () use ($implementation, $award, $actor, $input, $document) {
            $record = ContractImplementation::query()->lockForUpdate()->findOrFail($implementation->id);
            if ($record->status !== ContractImplementation::FOR_DELIVERY
                || $record->events()->whereIn('action', ['supplier_delivery', 'actual_receipt', 'inspection'])->exists()) {
                throw ValidationException::withMessages(['implementation' => 'Signed contract terms can only be set before delivery starts.']);
            }

            $ntpDate = $award->bid->notice_to_proceed_at->timezone('Asia/Manila')->toDateString();
            $deadline = Carbon::parse($input['delivery_deadline'], 'Asia/Manila')->toDateString();
            if ($deadline < $ntpDate) {
                throw ValidationException::withMessages(['delivery_deadline' => 'The contract delivery deadline cannot be before the NTP date.']);
            }

            $warranty = $this->warrantyTerms($input);

            $file = $this->storeDocument($document, $record->signed_contract_file_path);
            $record->fill([
                'delivery_deadline' => $deadline,
                'delivery_location' => trim($input['delivery_location']),
                'contract_items' => array_values($input['contract_items']),
                'signed_contract_reference' => trim($input['signed_contract_reference']),
                'signed_contract_file_path' => $file['path'],
                'signed_contract_file_name' => $file['name'],
                'configured_by' => $actor->id,
                'configured_at' => now(),
            ] + $warranty)->save();

            $this->writeEvent($record, $actor, 'contract_terms_recorded', $record->status, $record->status, $input['remarks'] ?? 'Terms copied from the signed contract.', [
                'delivery_deadline' => $deadline,
                'delivery_location' => $record->delivery_location,
                'contract_items' => $record->contract_items,
                'signed_contract_reference' => $record->signed_contract_reference,
            ] + $warranty, $document ? $file : null);

            return $record->fresh(['award.bid', 'events.actor']);
        });
    }

    /**
     * An approved time extension of the delivery deadline (RA 12009 IRR Sec. 71.1.1(c), 71.1.3):
     * requested before the deadline, approved by the HoPE, and in total no longer than the
     * initial delivery period. The HoPE may grant it with or without liquidated damages.
     */
    public function extendDeadline(Award $award, User $actor, array $input, ?UploadedFile $document): ContractImplementation
    {
        $implementation = $this->ensure($award);

        return DB::transaction(function () use ($implementation, $award, $actor, $input, $document) {
            $record = ContractImplementation::query()->lockForUpdate()->findOrFail($implementation->id);
            $this->assertConfigured($record);
            if (! in_array($record->status, [ContractImplementation::FOR_DELIVERY, ContractImplementation::DELIVERED, ContractImplementation::FOR_INSPECTION, ContractImplementation::FOR_CORRECTION], true)) {
                throw ValidationException::withMessages(['status' => 'A time extension can only be recorded before the goods are accepted.']);
            }

            $current = $record->effectiveDeadline()->copy()->startOfDay();
            $original = $record->delivery_deadline->copy()->startOfDay();
            $ntp = $award->bid->notice_to_proceed_at->timezone('Asia/Manila')->startOfDay();
            $requested = Carbon::parse($input['requested_on'], 'Asia/Manila')->startOfDay();
            $approved = Carbon::parse($input['approved_on'], 'Asia/Manila')->startOfDay();
            $newDeadline = Carbon::parse($input['new_deadline'], 'Asia/Manila')->startOfDay();

            if ($requested->gt($current)) {
                throw ValidationException::withMessages(['requested_on' => 'An extension must be requested on or before the delivery deadline in force ('.$current->format('M d, Y').').']);
            }
            if ($approved->lt($requested) || $approved->isAfter(today('Asia/Manila'))) {
                throw ValidationException::withMessages(['approved_on' => 'The approval date must be on or after the request date and cannot be in the future.']);
            }
            if ($newDeadline->lte($current)) {
                throw ValidationException::withMessages(['new_deadline' => 'The new deadline must be later than '.$current->format('M d, Y').'.']);
            }
            $initialPeriod = (int) $ntp->diffInDays($original);
            if ((int) $original->diffInDays($newDeadline) > $initialPeriod) {
                throw ValidationException::withMessages(['new_deadline' => 'The total extension cannot be longer than the initial delivery period of '.$initialPeriod.' days (latest '.$original->copy()->addDays($initialPeriod)->format('M d, Y').').']);
            }

            $keepsDamages = (bool) ($input['keeps_liquidated_damages'] ?? false);
            $file = $this->storeDocument($document);
            $record->update([
                'revised_deadline' => $newDeadline->toDateString(),
                // Once an extension keeps damages running, they count from the original deadline.
                'extension_keeps_ld' => $record->extension_keeps_ld || $keepsDamages,
            ]);
            $this->writeEvent($record, $actor, 'deadline_extended', $record->status, $record->status, $input['remarks'] ?? null, [
                'previous_deadline' => $current->toDateString(),
                'new_deadline' => $newDeadline->toDateString(),
                'requested_on' => $requested->toDateString(),
                'approved_on' => $approved->toDateString(),
                'approval_reference' => trim($input['approval_reference']),
                'keeps_liquidated_damages' => $keepsDamages,
            ], $file);

            $this->notifySupplier($award, 'Delivery deadline extended', 'The HoPE approved a time extension. The delivery deadline is now '.$newDeadline->format('M d, Y').'.');
            return $record->fresh(['events.actor']);
        });
    }

    public function supplierDelivery(Award $award, User $supplier, array $input, UploadedFile $document, bool $correction = false): ContractImplementation
    {
        $implementation = $this->ensure($award);

        return DB::transaction(function () use ($implementation, $award, $supplier, $input, $document, $correction) {
            $record = ContractImplementation::query()->lockForUpdate()->findOrFail($implementation->id);
            $expected = $correction ? ContractImplementation::FOR_CORRECTION : ContractImplementation::FOR_DELIVERY;
            if ($record->status !== $expected) {
                throw ValidationException::withMessages(['status' => 'A delivery submission is not allowed at the current stage.']);
            }
            $this->assertConfigured($record);
            $this->assertDeliveryDate($award, $input['delivered_on']);

            $quantities = $this->quantities($record, $input['delivered_quantities'] ?? [], 'delivered_quantities', false);
            $file = $this->storeDocument($document);
            $this->transition($record, $supplier, 'supplier_delivery', ContractImplementation::DELIVERED, $input['remarks'] ?? null, [
                'delivered_on' => $input['delivered_on'],
                'delivery_reference' => trim($input['delivery_reference']),
                'delivered_quantities' => $quantities,
                'is_correction_delivery' => $correction,
            ], $file);

            $this->notifySupplier($award, 'Delivery submitted', 'Your delivery details and supporting proof were submitted and are awaiting LGU receipt and inspection.');
            return $record->fresh(['events.actor']);
        });
    }

    public function recordReceipt(Award $award, User $actor, array $input, ?UploadedFile $document): ContractImplementation
    {
        $implementation = $this->ensure($award);

        return DB::transaction(function () use ($implementation, $award, $actor, $input, $document) {
            $record = ContractImplementation::query()->lockForUpdate()->findOrFail($implementation->id);
            if ($record->status !== ContractImplementation::DELIVERED) {
                throw ValidationException::withMessages(['status' => 'Record actual receipt only after the supplier marks the delivery as delivered.']);
            }

            $actualDate = Carbon::parse($input['actual_received_on'], 'Asia/Manila')->startOfDay();
            $ntpDate = $award->bid->notice_to_proceed_at->timezone('Asia/Manila')->startOfDay();
            if ($actualDate->lt($ntpDate) || $actualDate->isAfter(today('Asia/Manila'))) {
                throw ValidationException::withMessages(['actual_received_on' => 'Actual receipt must be on or after the NTP date and cannot be in the future.']);
            }
            $delivered = $record->events()->where('action', 'supplier_delivery')->latest('id')->first();
            if ($delivered && $actualDate->lt(Carbon::parse($delivered->details['delivered_on'] ?? $delivered->occurred_at)->startOfDay())) {
                throw ValidationException::withMessages(['actual_received_on' => 'Actual receipt cannot be before the supplier delivery date.']);
            }

            $quantities = $this->quantities($record, $input['received_quantities'] ?? [], 'received_quantities', false);
            $file = $this->storeDocument($document);
            $this->transition($record, $actor, 'actual_receipt', ContractImplementation::FOR_INSPECTION, $input['remarks'] ?? null, [
                'actual_received_on' => $actualDate->toDateString(),
                'received_quantities' => $quantities,
            ], $file);
            $this->notifySupplier($award, 'Goods received for inspection', 'The LGU recorded receipt of your delivery. Inspection findings will be posted here.');
            return $record->fresh(['events.actor']);
        });
    }

    public function inspect(Award $award, User $actor, array $input, ?UploadedFile $document): ContractImplementation
    {
        $implementation = $this->ensure($award);

        return DB::transaction(function () use ($implementation, $award, $actor, $input, $document) {
            $record = ContractImplementation::query()->lockForUpdate()->findOrFail($implementation->id);
            if ($record->status !== ContractImplementation::FOR_INSPECTION) {
                throw ValidationException::withMessages(['status' => 'Inspection can only be recorded after actual receipt.']);
            }

            $accepted = $this->quantities($record, $input['accepted_quantities'] ?? [], 'accepted_quantities', false);
            $latestReceipt = $record->events()->where('action', 'actual_receipt')->latest('id')->first();
            if (! $latestReceipt) {
                throw ValidationException::withMessages(['accepted_quantities' => 'Record actual receipt quantities before inspection.']);
            }
            $priorInspection = $record->events()->where('action', 'inspection')->latest('id')->first();
            $priorAccepted = $priorInspection?->status_to === ContractImplementation::FOR_CORRECTION
                ? ($priorInspection->details['accepted_quantities'] ?? [])
                : [];
            $inspectedOn = Carbon::parse($input['inspected_on'], 'Asia/Manila')->startOfDay();
            $receivedOn = Carbon::parse($latestReceipt->details['actual_received_on'] ?? $latestReceipt->occurred_at)->startOfDay();
            if ($inspectedOn->lt($receivedOn) || $inspectedOn->isAfter(today('Asia/Manila'))) {
                throw ValidationException::withMessages(['inspected_on' => 'The inspection date must be on or after the actual receipt ('.$receivedOn->format('M d, Y').') and cannot be in the future.']);
            }
            $received = $latestReceipt->details['received_quantities'] ?? [];
            foreach ($accepted as $index => $row) {
                $previouslyAccepted = (float) ($priorAccepted[$index]['quantity'] ?? 0);
                $availableNow = $previouslyAccepted + (float) ($received[$index]['quantity'] ?? 0);
                if ((float) $row['quantity'] < $previouslyAccepted || (float) $row['quantity'] > $availableNow) {
                    throw ValidationException::withMessages([
                        'accepted_quantities.'.$index => 'Accepted quantity must include prior accepted items and cannot exceed the quantity actually received.',
                    ]);
                }
            }
            $fullAcceptance = collect($accepted)->every(fn ($row) => (float) $row['quantity'] === (float) $row['contract_quantity']);
            $outcome = $input['outcome'];
            if ($outcome === 'accepted' && ! $fullAcceptance) {
                throw ValidationException::withMessages(['accepted_quantities' => 'All contract quantities must be accepted to mark the delivery Accepted. Record the deficiencies and request correction for any shortfall.']);
            }
            if ($outcome === 'for_correction' && blank($input['correction_request'] ?? null)) {
                throw ValidationException::withMessages(['correction_request' => 'Describe the correction the supplier must make.']);
            }

            $next = $outcome === 'accepted' ? ContractImplementation::ACCEPTED : ContractImplementation::FOR_CORRECTION;
            if ($next === ContractImplementation::ACCEPTED) {
                // The warranty runs from acceptance (Sec. 90.1).
                $record->accepted_on = $inspectedOn->toDateString();
            }
            $file = $this->storeDocument($document);
            $this->transition($record, $actor, 'inspection', $next, $input['remarks'] ?? null, [
                'inspected_on' => $inspectedOn->toDateString(),
                'iar_number' => trim($input['iar_number']),
                'inspection_findings' => trim($input['inspection_findings']),
                'accepted_quantities' => $accepted,
                'deficiencies' => $input['deficiencies'] ?? null,
                'correction_request' => $input['correction_request'] ?? null,
            ], $file);
            $this->notifySupplier($award,
                $next === ContractImplementation::ACCEPTED ? 'Goods accepted' : 'Correction requested',
                $next === ContractImplementation::ACCEPTED
                    ? 'The LGU accepted the delivered goods. The contract is ready for payment processing.'
                    : 'The LGU inspection recorded deficiencies. Review the correction request and submit the corrected delivery details and proof.'
            );
            return $record->fresh(['events.actor']);
        });
    }

    public function advance(Award $award, User $actor, string $action, array $input, ?UploadedFile $document): ContractImplementation
    {
        $implementation = $this->ensure($award);

        return DB::transaction(function () use ($implementation, $award, $actor, $action, $input, $document) {
            $record = ContractImplementation::query()->lockForUpdate()->findOrFail($implementation->id);
            [$from, $to, $eventAction, $title, $message] = match ($action) {
                'payment_processing' => [ContractImplementation::ACCEPTED, ContractImplementation::PAYMENT_PROCESSING, 'payment_processing', 'Payment processing', 'The LGU started processing payment for the accepted goods. This status records procurement progress only.'],
                'paid' => [ContractImplementation::PAYMENT_PROCESSING, ContractImplementation::PAID, 'paid', 'Payment status updated', 'The LGU recorded the contract payment as Paid. This is a tracking status only.'],
                'complete' => [ContractImplementation::PAID, ContractImplementation::COMPLETED, 'completed', 'Contract completed', 'The LGU marked the Goods contract implementation Completed.'],
                default => throw ValidationException::withMessages(['action' => 'That contract implementation action is not allowed.']),
            };

            if ($record->status !== $from) {
                throw ValidationException::withMessages(['status' => 'That action is not allowed at the current contract implementation stage.']);
            }
            $this->assertConfigured($record);

            $contractPrice = (float) $award->contract_amount;
            $details = [];
            if ($action === 'payment_processing') {
                // What the payment must account for, as of acceptance.
                $damages = LiquidatedDamages::for($record->loadMissing('events'), $contractPrice);
                $details = [
                    'liquidated_damages' => $damages['total'],
                    'days_late' => $damages['max_days'],
                    'damages_priced' => $damages['priced'],
                    'warranty_security' => $record->warranty_security,
                    'warranty_security_amount' => $record->hasWarrantyTerms() ? $record->warrantySecurityAmount($contractPrice) : null,
                ];
            }
            if ($action === 'complete') {
                $details = $this->assertWarrantyReleasable($record, $input);
            }

            $file = $this->storeDocument($document);
            $this->transition($record, $actor, $eventAction, $to, $input['remarks'] ?? null, $details, $file);
            $this->notifySupplier($award, $title, $message);
            return $record->fresh(['events.actor']);
        });
    }

    /**
     * Warranty terms from the signed contract (Sec. 90.1): at least 3 months for expendable and
     * 1 year for non-expendable supplies after acceptance; security of 1% to 5%, 1% by default.
     *
     * @return array{supply_type: string, warranty_months: int, warranty_security: string, warranty_percent: float}
     */
    private function warrantyTerms(array $input): array
    {
        $type = $input['supply_type'] ?? null;
        if (! isset(ContractImplementation::SUPPLY_TYPES[$type])) {
            throw ValidationException::withMessages(['supply_type' => 'Choose whether the goods are expendable or non-expendable supplies.']);
        }
        $minimum = ContractImplementation::SUPPLY_TYPES[$type]['min_months'];
        $months = (int) ($input['warranty_months'] ?? $minimum);
        if ($months < $minimum) {
            throw ValidationException::withMessages(['warranty_months' => 'The warranty for '.strtolower(ContractImplementation::SUPPLY_TYPES[$type]['label']).' is at least '.$minimum.' months after acceptance.']);
        }
        if (! array_key_exists($input['warranty_security'] ?? null, ContractImplementation::WARRANTY_SECURITIES)) {
            throw ValidationException::withMessages(['warranty_security' => 'Choose retention money or a special bank guarantee.']);
        }
        $percent = filled($input['warranty_percent'] ?? null) ? (float) $input['warranty_percent'] : ContractImplementation::DEFAULT_WARRANTY_PERCENT;
        if ($percent < 1 || $percent > 5) {
            throw ValidationException::withMessages(['warranty_percent' => 'The warranty security is from 1% to 5% of the contract price.']);
        }

        return ['supply_type' => $type, 'warranty_months' => $months, 'warranty_security' => $input['warranty_security'], 'warranty_percent' => $percent];
    }

    /**
     * The warranty security is released, and the contract completed, after the warranty
     * period, or for expendable supplies after they are consumed, when no defects remain.
     */
    private function assertWarrantyReleasable(ContractImplementation $record, array $input): array
    {
        if (! $record->hasWarrantyTerms()) {
            // Contracts recorded before warranty terms were captured give them now.
            $record->fill($this->warrantyTerms($input));
        }
        if (! $record->accepted_on) {
            $acceptance = $record->events()->where('action', 'inspection')->where('status_to', ContractImplementation::ACCEPTED)->latest('id')->first();
            $record->accepted_on = Carbon::parse($acceptance?->details['inspected_on'] ?? $acceptance?->occurred_at ?? today('Asia/Manila'))->toDateString();
        }

        $endsOn = $record->warrantyEndsOn();
        $consumed = $record->supply_type === 'expendable' && (bool) ($input['consumed'] ?? false);
        if (! $consumed && $endsOn->isAfter(today('Asia/Manila'))) {
            throw ValidationException::withMessages(['warranty' => 'The warranty runs until '.$endsOn->format('M d, Y').'. Complete the contract after it ends'.($record->supply_type === 'expendable' ? ', or once the expendable supplies are consumed.' : '.')]);
        }
        if (! ($input['no_defects'] ?? false)) {
            throw ValidationException::withMessages(['no_defects' => 'Confirm that the supplies are free from defects and every contract condition was met before releasing the warranty security.']);
        }
        $released = Carbon::parse($input['warranty_released_on'], 'Asia/Manila')->startOfDay();
        if ($released->lt($record->accepted_on) || $released->isAfter(today('Asia/Manila'))) {
            throw ValidationException::withMessages(['warranty_released_on' => 'The release date must be after acceptance and cannot be in the future.']);
        }
        $record->warranty_released_on = $released->toDateString();

        return [
            'warranty_ended_on' => $endsOn->toDateString(),
            'released_after_consumption' => $consumed,
            'warranty_released_on' => $released->toDateString(),
        ];
    }

    private function assertConfigured(ContractImplementation $record): void
    {
        if (! $record->isConfigured()) {
            throw ValidationException::withMessages(['implementation' => 'Record the delivery terms from the signed contract before processing the delivery.']);
        }
    }

    private function assertDeliveryDate(Award $award, string $date): void
    {
        $submitted = Carbon::parse($date, 'Asia/Manila')->startOfDay();
        $ntp = $award->bid->notice_to_proceed_at->timezone('Asia/Manila')->startOfDay();
        if ($submitted->lt($ntp) || $submitted->isAfter(today('Asia/Manila'))) {
            throw ValidationException::withMessages(['delivered_on' => 'The delivery date must be on or after the NTP date and cannot be in the future.']);
        }
    }

    private function quantities(ContractImplementation $record, array $values, string $field, bool $allowOver): array
    {
        $items = $record->contract_items ?? [];
        if ($items === [] || count($values) !== count($items)) {
            throw ValidationException::withMessages([$field => 'Enter a quantity for every item in the signed contract.']);
        }
        $result = [];
        foreach (array_values($items) as $index => $item) {
            if (! array_key_exists($index, $values) || ! is_numeric($values[$index]) || (float) $values[$index] < 0) {
                throw ValidationException::withMessages([$field.'.'.$index => 'Enter a valid non-negative quantity for every contract item.']);
            }
            $quantity = (float) $values[$index];
            $contractQuantity = (float) $item['quantity'];
            if (! $allowOver && $quantity > $contractQuantity && $field === 'accepted_quantities') {
                throw ValidationException::withMessages([$field.'.'.$index => 'Accepted quantity cannot exceed the quantity in the signed contract.']);
            }
            $result[] = [
                'description' => $item['description'],
                'unit' => $item['unit'],
                'contract_quantity' => $contractQuantity,
                'quantity' => $quantity,
            ];
        }

        return $result;
    }

    private function transition(ContractImplementation $record, User $actor, string $action, string $to, ?string $remarks, array $details = [], ?array $file = null): void
    {
        $from = $record->status;
        $record->update(['status' => $to]);
        $this->writeEvent($record, $actor, $action, $from, $to, $remarks, $details, $file);
    }

    private function writeEvent(ContractImplementation $record, User $actor, string $action, ?string $from, ?string $to, ?string $remarks, array $details = [], ?array $file = null): ContractImplementationEvent
    {
        return $record->events()->create([
            'action' => $action,
            'status_from' => $from,
            'status_to' => $to,
            'actor_id' => $actor->id,
            'actor_role' => $actor->role,
            'occurred_at' => now(),
            'remarks' => filled($remarks) ? trim($remarks) : null,
            'details' => $details,
            'document_path' => $file['path'] ?? null,
            'document_name' => $file['name'] ?? null,
        ]);
    }

    private function storeDocument(?UploadedFile $document, ?string $preservePath = null): array
    {
        if (! $document) {
            return ['path' => $preservePath, 'name' => null];
        }

        return [
            'path' => $document->store('contract-implementation/private', 'local'),
            'name' => $document->getClientOriginalName(),
        ];
    }

    private function notifySupplier(Award $award, string $title, string $message): void
    {
        $url = route('bidder.awarded-contracts', ['award' => $award->id]);
        SystemNotification::createForUser((int) ($award->bid?->user_id ?? $award->bidder_id), $title, $message, 'system_alert', [
            'important' => true,
            'url' => $url,
            'award_id' => $award->id,
            'kind' => 'contract_implementation',
        ]);
    }
}
