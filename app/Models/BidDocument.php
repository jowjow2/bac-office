<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One uploaded file for one checklist requirement of a bid, kept in its
 * component (technical / financial) so the two envelopes stay separate.
 */
class BidDocument extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('known_as_of_demo_clock', function (Builder $query): void {
            $clock = app(\App\Support\ProcurementClock::class);
            if ($clock->demoModeEnabled()) $query->where('created_at', '<=', $clock->now());
        });
    }
    public const COMPONENT_TECHNICAL = 'technical';

    public const COMPONENT_FINANCIAL = 'financial';

    protected $fillable = [
        'bid_id',
        'requirement_key',
        'component',
        'label',
        'file_path',
        'original_name',
        'size',
        'sha256',
        'encrypted_at',
    ];

    protected $casts = ['encrypted_at' => 'datetime'];

    public function reviewEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BidDocumentReviewEvent::class, 'bid_document_id')->orderBy('id');
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }
}
