<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A change of a published project's bidding documents fee, with its issuance reference. */
class BiddingFeeAmendment extends Model
{
    protected $fillable = [
        'project_id',
        'previous_fee',
        'previous_mode',
        'new_fee',
        'new_mode',
        'reference',
        'reason',
        'amended_by',
    ];

    protected $casts = [
        'previous_fee' => 'decimal:2',
        'new_fee' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function amender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'amended_by');
    }
}
