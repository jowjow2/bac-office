<?php

namespace App\Models;

use App\Support\Uploads;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class Bid extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'bid_amount',
        'proposal_file',
        'status',
        'notes',
    ];

    protected $casts = [
        'bid_amount' => 'decimal:2',
    ];

    public function getAmountAttribute(): mixed
    {
        return $this->bid_amount;
    }

    public function setAmountAttribute(string|int|float|null $value): void
    {
        $this->attributes['bid_amount'] = $value;
    }

    public function getProposalUrlAttribute(): ?string
    {
        return Uploads::url($this->proposal_file);
    }

    public function getProposalFilenameAttribute(): ?string
    {
        return Uploads::fileName($this->proposal_file);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function award(): HasOne
    {
        return $this->hasOne(Award::class);
    }
}
