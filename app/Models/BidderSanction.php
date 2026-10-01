<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BidderSanction extends Model
{
    public const TYPE_SUSPENDED = 'suspended';
    public const TYPE_BLACKLISTED = 'blacklisted';

    protected $fillable = [
        'bidder_id',
        'type',
        'previous_approval_status',
        'reason',
        'reference_number',
        'effective_date',
        'end_date',
        'authorized_by',
        'created_by',
        'lifted_at',
        'lifted_by',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'end_date' => 'date',
        'lifted_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function bidder(): BelongsTo
    {
        return $this->belongsTo(Bidder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lifter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('lifted_at')
            ->whereDate('effective_date', '<=', now()->toDateString())
            ->where(function (Builder $query): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', now()->toDateString());
            });
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_SUSPENDED => 'Suspended',
            self::TYPE_BLACKLISTED => 'Blacklisted',
            default => ucfirst((string) $this->type),
        };
    }
}