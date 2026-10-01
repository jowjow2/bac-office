<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BidDocumentReviewEvent extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_NEEDS_REVISION = 'needs_revision';

    protected $fillable = [
        'bid_id',
        'bid_document_id',
        'requirement_key',
        'version',
        'status',
        'comment',
        'file_path',
        'original_name',
        'sha256',
        'actor_id',
        'uploaded_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(BidDocument::class, 'bid_document_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}