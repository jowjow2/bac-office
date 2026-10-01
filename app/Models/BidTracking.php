<?php

namespace App\Models;

use App\Models\Concerns\MasksFutureProcurementEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a bid's event history. Structured rows (stage + decision) are
 * written only by App\Support\BidWorkflow; rows without a stage are legacy
 * free-text entries and are never shown to bidders.
 */
class BidTracking extends Model
{
    use MasksFutureProcurementEvents;

    protected function futureProcurementEventFields(): array { return ['created_at']; }

    protected $table = 'bid_trackings';

    protected $fillable = [
        'bid_id',
        'bidder_id',
        'project_id',
        'status_title',
        'status_description',
        'status_type',
        'stage',
        'decision',
        'reason',
        'visible_to_bidder',
        'details',
        'created_by',
        'attachment_path',
        'attachment_name',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'visible_to_bidder' => 'boolean',
        'details' => 'array',
    ];

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }

    public function bidder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bidder_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
