<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractImplementation extends Model
{
    public const FOR_DELIVERY = 'for_delivery';
    public const DELIVERED = 'delivered';
    public const FOR_INSPECTION = 'for_inspection';
    public const ACCEPTED = 'accepted';
    public const FOR_CORRECTION = 'for_correction';
    public const PAYMENT_PROCESSING = 'payment_processing';
    public const PAID = 'paid';
    public const COMPLETED = 'completed';
    public const INFRA_IN_PROGRESS = 'infra_in_progress';
    public const INFRA_FOR_INSPECTION = 'infra_for_inspection';
    public const INFRA_FOR_CORRECTION = 'infra_for_correction';
    public const INFRA_RECOMMENDED = 'infra_recommended_acceptance';
    public const INFRA_ACCEPTED = 'infra_accepted';
    public const INFRA_PAYMENT_PROCESSING = 'infra_payment_processing';
    public const INFRA_PAID = 'infra_paid';
    public const INFRA_COMPLETED = 'infra_completed';

    /** Supply types and the minimum warranty in months after acceptance (RA 12009 IRR Sec. 90.1). */
    public const SUPPLY_TYPES = [
        'expendable' => ['label' => 'Expendable supplies', 'min_months' => 3],
        'non_expendable' => ['label' => 'Non-expendable supplies', 'min_months' => 12],
    ];

    /** Forms of the warranty security (Sec. 90.1): 1% to 5%, 1% when the documents are silent. */
    public const WARRANTY_SECURITIES = [
        'retention' => 'Retention money from each payment',
        'bank_guarantee' => 'Special bank guarantee',
    ];

    public const DEFAULT_WARRANTY_PERCENT = 1.0;

    protected $fillable = [
        'award_id', 'status', 'delivery_deadline', 'revised_deadline', 'extension_keeps_ld', 'delivery_location', 'contract_items',
        'supply_type', 'warranty_months', 'warranty_security', 'warranty_percent', 'accepted_on', 'warranty_released_on',
        'signed_contract_reference', 'signed_contract_file_path', 'signed_contract_file_name',
        'configured_by', 'configured_at',
    ];

    protected $casts = [
        'delivery_deadline' => 'date',
        'revised_deadline' => 'date',
        'extension_keeps_ld' => 'boolean',
        'contract_items' => 'array',
        'warranty_months' => 'integer',
        'warranty_percent' => 'decimal:2',
        'accepted_on' => 'date',
        'warranty_released_on' => 'date',
        'configured_at' => 'datetime',
    ];

    /** The delivery deadline in force: the approved extension, else the signed contract's. */
    public function effectiveDeadline(): ?\Illuminate\Support\Carbon
    {
        return $this->revised_deadline ?? $this->delivery_deadline;
    }

    /**
     * The date liquidated damages count from: the original deadline when an
     * approved extension kept them running (Sec. 71.1.4), else the one in force.
     */
    public function damagesDeadline(): ?\Illuminate\Support\Carbon
    {
        return $this->extension_keeps_ld ? $this->delivery_deadline : $this->effectiveDeadline();
    }

    public function hasWarrantyTerms(): bool
    {
        return isset(self::SUPPLY_TYPES[$this->supply_type]) && $this->warranty_months > 0 && filled($this->warranty_security);
    }

    public function warrantyEndsOn(): ?\Illuminate\Support\Carbon
    {
        return $this->accepted_on && $this->warranty_months ? $this->accepted_on->copy()->addMonthsNoOverflow($this->warranty_months) : null;
    }

    public function supplyTypeLabel(): ?string
    {
        return self::SUPPLY_TYPES[$this->supply_type]['label'] ?? null;
    }

    /** The warranty security amount on the contract price. */
    public function warrantySecurityAmount(float $contractPrice): float
    {
        return round($contractPrice * (float) ($this->warranty_percent ?? self::DEFAULT_WARRANTY_PERCENT) / 100, 2);
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(Award::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ContractImplementationEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function getStatusAttribute($value): string
    {
        $clock = app(\App\Support\ProcurementClock::class);
        if (! $clock->demoModeEnabled()) return (string) ($value ?: self::FOR_DELIVERY);
        $at = $clock->now();
        $event = $this->events()->where('created_at', '<=', $at)->where('occurred_at', '<=', $at)->latest('id')->first();
        return (string) ($event?->status_to ?: self::FOR_DELIVERY);
    }

    public function isConfigured(): bool
    {
        $clock = app(\App\Support\ProcurementClock::class);
        if ($clock->demoModeEnabled() && $this->configured_at && $this->configured_at->isAfter($clock->now())) return false;
        return $this->delivery_deadline !== null && filled($this->delivery_location)
            && filled($this->signed_contract_reference) && is_array($this->contract_items)
            && count($this->contract_items) > 0;
    }
    public function label(): string
    {
        // Not "For Delivery" until the delivery terms of the signed contract are recorded.
        if ($this->status === self::FOR_DELIVERY && ! $this->isConfigured()) {
            return 'Awaiting contract terms';
        }

        return match ($this->status) {
            self::FOR_DELIVERY => 'For Delivery',
            self::DELIVERED => 'Delivered',
            self::FOR_INSPECTION => 'For Inspection',
            self::ACCEPTED => 'Accepted',
            self::FOR_CORRECTION => 'For Correction',
            self::PAYMENT_PROCESSING => 'Payment Processing',
            self::PAID => 'Paid',
            self::COMPLETED, self::INFRA_COMPLETED => 'Completed',
            self::INFRA_IN_PROGRESS => 'Work in Progress',
            self::INFRA_FOR_INSPECTION => 'For Site Inspection',
            self::INFRA_FOR_CORRECTION => 'For Correction',
            self::INFRA_RECOMMENDED => 'Recommended for Acceptance',
            self::INFRA_ACCEPTED => 'Accepted',
            self::INFRA_PAYMENT_PROCESSING => 'Payment Processing',
            self::INFRA_PAID => 'Paid (recorded)',
            default => 'For Delivery',
        };
    }
}

