<?php

namespace App\Events;

use App\Models\Bid;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BidWorkflowUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Bid $bid;

    public function __construct(Bid $bid)
    {
        $this->bid = $bid->load(['project.awards', 'project.rebidProject', 'award']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('bidding-track.' . $this->bid->user_id),
        ];
    }

    public function broadcastWith(): array
    {
        // Sent to the bidder's own channel: same payload as the track page,
        // no internal notes.
        $progress = $this->bid->progress()->toArray();

        return [
            'id' => $this->bid->id,
            'project_title' => $this->bid->project?->title ?? 'Unknown Project',
            'current' => $progress['current'],
            'signature' => $progress['signature'],
            'updated_at' => $this->bid->updated_at?->timestamp,
        ];
    }
}
