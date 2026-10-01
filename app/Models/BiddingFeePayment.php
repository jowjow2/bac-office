<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The bidding documents fee a bidder paid over the counter at the BAC,
 * recorded with its Official Receipt. One per bidder per project.
 */
class BiddingFeePayment extends Model
{
    /** Only a verified payment with an Official Receipt reference counts as paid. */
    public const STATUS_VERIFIED = 'verified';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'project_id',
        'user_id',
        'amount',
        'or_number',
        'status',
        'paid_at',
        'verified_at',
        'recorded_by',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
        'verified_at' => 'datetime',
    ];

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED && $this->verified_at !== null && filled($this->or_number);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function bidder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
