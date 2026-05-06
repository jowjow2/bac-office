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
        $this->bid = $bid->load(['project', 'award']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('bidding-track.' . $this->bid->user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->bid->id,
            'project_title' => $this->bid->project?->title ?? 'Unknown Project',
            'bid_amount' => $this->bid->bid_amount,
            'workflow_step' => $this->bid->effective_workflow_step,
            'eligibility_status' => $this->bid->eligibility_status,
            'status' => $this->bid->status,
            'updated_at' => $this->bid->updated_at?->timestamp,
            'timeline_steps' => $this->bid->workflow_timeline_steps,
        ];
    }
}
