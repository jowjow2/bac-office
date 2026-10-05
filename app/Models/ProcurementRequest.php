<?php

namespace App\Models;

use App\Support\EndUserAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A request from an end-user office to procure something. It is reviewed
 * against the PPMP/APP and budget by authorized staff, then forwarded to the
 * BAC, which prepares the bidding project from it.
 *
 * draft -> submitted -> forwarded -> in_procurement
 *               \-> returned (back to the office for correction) -> submitted
 *               \-> rejected
 */
class ProcurementRequest extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FORWARDED = 'forwarded';

    public const STATUS_IN_PROCUREMENT = 'in_procurement';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SUBMITTED => 'For PPMP/APP review',
        self::STATUS_RETURNED => 'Returned for correction',
        self::STATUS_REJECTED => 'Not approved',
        self::STATUS_FORWARDED => 'Forwarded to BAC',
        self::STATUS_IN_PROCUREMENT => 'In procurement',
    ];

    public const DOCUMENT_TYPES = [
        'tor' => 'Terms of Reference',
        'specifications' => 'Technical specifications',
        'ppmp' => 'PPMP extract',
        'other' => 'Other attachment',
    ];

    protected $fillable = [
        'reference_no',
        'end_user_office',
        'requested_by',
        'title',
        'category',
        'specifications',
        'quantity',
        'unit',
        'items',
        'estimated_cost',
        'fund_source',
        'delivery_period',
        'justification',
        'status',
        'submitted_at',
        'ppmp_reference',
        'app_reference',
        'budget_available',
        'budget_confirmed_by',
        'budget_confirmed_at',
        'review_remarks',
        'reviewed_by',
        'reviewed_at',
        'forwarded_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'items' => 'array',
        'estimated_cost' => 'decimal:2',
        'budget_available' => 'boolean',
        'budget_confirmed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'forwarded_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function budgetConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'budget_confirmed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProcurementRequestDocument::class);
    }

    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }

    /** Requests an end-user account may open: its own (App\Support\EndUserAccess). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return EndUserAccess::scopeRequests($query, $user);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** "1,200 pcs", "2.5 lots": whole quantities without decimals. */
    /**
     * Details a request must have before it is submitted for the PPMP/APP
     * and funds review. A draft may be saved without them.
     */
    public const REQUIRED_FOR_SUBMISSION = [
        'title' => 'Title',
        'category' => 'Procurement category',
        'specifications' => 'Technical specifications / TOR',
        'quantity' => 'Quantity',
        'unit' => 'Unit',
        'estimated_cost' => 'Estimated total cost',
        'fund_source' => 'Source of funds',
        'delivery_period' => 'Delivery period or contract duration',
    ];

    /** @return array<string, string> field => label of the details still missing */
    public function missingForSubmission(): array
    {
        return collect(self::REQUIRED_FOR_SUBMISSION)
            ->filter(fn (string $label, string $field) => blank($this->{$field}) || (in_array($field, ['quantity', 'estimated_cost'], true) && (float) $this->{$field} <= 0))
            ->all();
    }

    /** Whether the database has the items column yet (its migration may still be pending). */
    public static function storesItems(): bool
    {
        static $stores = null;

        return $stores ??= \Illuminate\Support\Facades\Schema::hasColumn('procurement_requests', 'items');
    }

    /**
     * The requested items with their totals. A request saved before items were
     * listed shows its single quantity and estimated cost as one row.
     *
     * @return list<array{description: string, quantity: float, unit: string, unit_cost: float, total: float}>
     */
    public function itemRows(): array
    {
        $items = is_array($this->items) ? $this->items : [];
        if ($items !== []) {
            return array_values(array_map(fn (array $item) => [
                'description' => (string) ($item['description'] ?? ''),
                'quantity' => (float) ($item['quantity'] ?? 0),
                'unit' => (string) ($item['unit'] ?? ''),
                'unit_cost' => (float) ($item['unit_cost'] ?? 0),
                'total' => round((float) ($item['quantity'] ?? 0) * (float) ($item['unit_cost'] ?? 0), 2),
            ], $items));
        }

        if ($this->quantity === null || (float) $this->quantity <= 0) {
            return [];
        }

        $total = (float) $this->estimated_cost;

        return [[
            'description' => (string) $this->title,
            'quantity' => (float) $this->quantity,
            'unit' => (string) $this->unit,
            'unit_cost' => round($total / (float) $this->quantity, 2),
            'total' => $total,
        ]];
    }

    public function quantityLabel(): string
    {
        $count = is_array($this->items) ? count($this->items) : 0;
        if ($count > 1) {
            return $count.' items';
        }

        if ($this->quantity === null) {
            return '—';
        }

        $quantity = (float) $this->quantity;
        $formatted = number_format($quantity, fmod($quantity, 1.0) === 0.0 ? 0 : 2);

        return trim($formatted.' '.$this->unit);
    }

    /** Badge tone of the status in the design system (ui-badge--*). */
    public function statusTone(): string
    {
        return match ($this->status) {
            self::STATUS_RETURNED => 'warning',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_SUBMITTED, self::STATUS_FORWARDED => 'info',
            self::STATUS_IN_PROCUREMENT => 'success',
            default => 'neutral',
        };
    }

    /** The end-user office can still change the request. */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED], true);
    }

    public function awaitsReview(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function awaitsBac(): bool
    {
        return $this->status === self::STATUS_FORWARDED;
    }

    public static function nextReferenceNo(): string
    {
        $prefix = 'PR-'.now()->format('Y').'-';
        // Seeded/imported references (such as PR-2026-T001) are not sequence numbers.
        // Compare numeric suffixes so 10000 also sorts after 9999.
        $highest = 0;
        foreach (static::where('reference_no', 'like', $prefix.'%')->pluck('reference_no') as $reference) {
            $suffix = substr($reference, strlen($prefix));
            if ($suffix !== '' && ctype_digit($suffix)) {
                $highest = max($highest, (int) $suffix);
            }
        }
        $next = $highest + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
