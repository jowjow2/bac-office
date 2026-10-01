<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BidderRequirementRequest extends Model
{
    protected $fillable = [
        'bidder_id', 'document_type', 'document_id', 'reason', 'status',
        'requested_by', 'requested_at', 'resolved_at', 'resolved_by',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function bidder(): BelongsTo
    {
        return $this->belongsTo(Bidder::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(BidderDocument::class, 'document_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
