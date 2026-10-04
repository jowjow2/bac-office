<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    /** Plain-language names for the actions people look for most; others read from their key. */
    private const ACTION_LABELS = [
        'bids_opened' => 'Bid opening recorded by the BAC',
        'bid_technical_documents_auto_opened' => 'Bids opened at the scheduled time',
        'bid_financial_opened' => 'Financial bid opened',
        'bid_financial_password_failed' => 'Wrong financial bid PIN entered',
        'project_schedule_changed' => 'Schedule changed',
        'philgeps_posting_recorded' => 'PhilGEPS posting recorded',
        'proceeding_recorded' => 'Proceeding recorded',
        'failed_bidding_declared' => 'Failure of bidding declared',
        'notice_of_award_issued' => 'Notice of Award issued',
        'procurement_completed' => 'Procurement completed',
        'bidding_fee_payment_recorded' => 'Bidding fee payment recorded',
        'bidding_fee_payment_updated' => 'Bidding fee payment edited',
        'bidding_fee_payment_removed' => 'Bidding fee payment removed',
        'bidding_fee_amended' => 'Bidding documents fee amended',
        'bidder_approved' => 'Bidder approved',
        'bidder_rejected' => 'Bidder rejected',
        'infrastructure_terms_corrected' => 'Infrastructure contract terms corrected',
    ];

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? Str::ucfirst(str_replace('_', ' ', $this->action));
    }

    public static function recordTypeLabel(string $type): string
    {
        return Str::headline(class_basename($type));
    }

    /** "Project · SJ-BAC-2026-G-001 asdasd"; the record may since have been deleted. */
    public function recordLabel(): string
    {
        $type = self::recordTypeLabel((string) $this->auditable_type);
        $record = $this->auditable;
        $name = match (true) {
            $record instanceof Project => trim(($record->reference_no ? $record->reference_no.' ' : '').$record->title),
            $record instanceof Bid => trim(($record->receipt_no ?: '#'.$record->id).' · '.($record->user?->company ?: $record->user?->name)),
            $record instanceof User => $record->company ?: $record->name,
            $record instanceof Model => '#'.$record->getKey(),
            default => '#'.$this->auditable_id.' (deleted)',
        };

        return $type.' · '.$name;
    }

    /** Where the BAC can look at the record, when there is a page for it. */
    public function recordUrl(): ?string
    {
        return match (true) {
            $this->auditable instanceof Project => route('admin.project.view', $this->auditable),
            $this->auditable instanceof Bid => route('admin.bids', ['view_bid' => $this->auditable->id]),
            $this->auditable instanceof User => route('admin.users.review', $this->auditable),
            default => null,
        };
    }

    /** No user: done by the system itself (scheduled openings, automatic records). */
    public function actorLabel(): string
    {
        if ($this->user_id === null) {
            return 'System';
        }

        return $this->user?->name ?? 'Deleted user #'.$this->user_id;
    }

    /**
     * What changed, field by field (only fields whose value differs).
     *
     * @return list<array{field: string, old: ?string, new: ?string}>
     */
    public function changes(): array
    {
        $old = is_array($this->old_values) ? $this->old_values : [];
        $new = is_array($this->new_values) ? $this->new_values : [];

        return collect(array_unique(array_merge(array_keys($old), array_keys($new))))
            ->map(fn ($field) => [
                'field' => Str::ucfirst(str_replace('_', ' ', (string) $field)),
                'old' => self::formatValue($old[$field] ?? null),
                'new' => self::formatValue($new[$field] ?? null),
            ])
            ->reject(fn ($change) => $change['old'] === $change['new'])
            ->values()
            ->all();
    }

    private static function formatValue(mixed $value): ?string
    {
        return match (true) {
            $value === null || $value === '' => null,
            is_bool($value) => $value ? 'Yes' : 'No',
            is_array($value) => Str::limit(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 200),
            // Timestamps read in Philippine time.
            is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $value) === 1
                => Carbon::parse($value)->timezone(config('bac-office.display_timezone', 'Asia/Manila'))->format('M d, Y h:i A'),
            default => Str::limit((string) $value, 200),
        };
    }

    /**
     * Log an action
     */
    public static function log(string $action, $auditable, array $oldValues = null, array $newValues = null, array $context = []): self
    {
        $log = new self();
        $log->action = $action;
        $log->auditable_type = (new \ReflectionClass($auditable))->getName();
        $log->auditable_id = $auditable instanceof Model ? $auditable->getKey() : $auditable;
        $log->old_values = $oldValues;
        $log->new_values = $newValues;
        $log->ip_address = $context['ip_address'] ?? request()->ip();
        $log->user_agent = $context['user_agent'] ?? request()->userAgent();
        // An explicit null user_id records a system action (e.g. a scheduled opening during someone's request).
        $log->user_id = array_key_exists('user_id', $context) ? $context['user_id'] : auth()->id();
        $log->save();

        return $log;
    }
}
