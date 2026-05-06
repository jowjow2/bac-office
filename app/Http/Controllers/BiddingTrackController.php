<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BiddingTrackController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Auth::user()->role === 'bidder', 403);

        $query = Bid::with([
            'project',
            'award',
            'workflowStepUpdater',
            'documentsValidator',
            'bacEvaluator',
            'approvedByUser',
            'disqualifiedByUser',
            'awardedByUser',
            'noticeOfAwardByUser',
            'noticeToProceedByUser',
            'projectCompletedByUser',
            'trackings',
        ])->where('user_id', Auth::id());

        // If specific bid ID provided, show only that bid
        if ($request->has('bid')) {
            $bidId = (int) $request->query('bid');
            $query->where('id', $bidId);
        }

        $bids = $query->orderBy('created_at', 'desc')->get();

        $request->session()->put('bidding_track_last_viewed', now()->timestamp);

        return view('bidder.bidding-track', [
            'bids' => $bids,
            'activeBidsCount' => $bids->count(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->role === 'bidder', 403);

        $bids = Bid::with([
            'project',
            'award',
            'workflowStepUpdater',
            'documentsValidator',
            'bacEvaluator',
            'approvedByUser',
            'disqualifiedByUser',
            'awardedByUser',
            'noticeOfAwardByUser',
            'noticeToProceedByUser',
            'projectCompletedByUser',
            'trackings',
        ])->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        $lastViewed = (int) ($request->session()->get('bidding_track_last_viewed', 0));

        $payload = [];
        foreach ($bids as $bid) {
            $payload[] = [
                'id' => $bid->id,
                'project_title' => $bid->project?->title ?? 'Unknown Project',
                'bid_amount' => $bid->bid_amount,
                'workflow_step' => $bid->effective_workflow_step,
                'eligibility_status' => $bid->eligibility_status,
                'status' => $bid->status,
                'updated_at' => $bid->updated_at->timestamp,
                'timeline_steps' => $bid->workflow_timeline_steps,
                'trackings' => $bid->trackings->map(fn ($tracking) => [
                    'status_title' => $tracking->status_title,
                    'status_description' => $tracking->status_description,
                    'status_type' => $tracking->status_type,
                    'created_at' => $tracking->created_at?->toISOString(),
                ])->values(),
            ];
        }

        return response()->json([
            'ok' => true,
            'bids' => $payload,
            'active_bids_count' => count($bids),
        ]);
    }
}
