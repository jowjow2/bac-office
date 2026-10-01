<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Support\BidHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BiddingTrackController extends Controller
{
    /** How often the page re-checks for recorded changes (seconds). */
    public const REFRESH_SECONDS = 60;

    public function index(Request $request)
    {
        abort_unless(Auth::user()->role === 'bidder', 403);

        $query = $this->ownBids();

        // If specific bid ID provided, show only that bid (still scoped to the owner).
        if ($request->filled('bid')) {
            $query->whereKey((int) $request->query('bid'));
        }

        $bids = $query->get();

        $request->session()->put('bidding_track_last_viewed', now()->timestamp);

        return view('bidder.bidding-track', [
            'bids' => $bids,
            'activeBidsCount' => $bids->count(),
            'refreshSeconds' => self::REFRESH_SECONDS,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->role === 'bidder', 403);

        $bids = $this->ownBids()->get();
        $lastViewed = (int) $request->session()->get('bidding_track_last_viewed', 0);
        $includeHtml = $request->boolean('html');

        $payload = $bids->map(function (Bid $bid) use ($includeHtml) {
            $progress = $bid->progress()->toArray();
            $history = BidHistory::for($bid)->forBidder();

            $row = [
                'id' => $bid->id,
                'project_title' => $bid->project?->title ?? 'Unknown Project',
                'bid_amount' => $bid->bid_amount,
                'updated_at' => $bid->updated_at?->timestamp,
                'current' => $progress['current'],
                'outcome' => $progress['outcome'],
                'project_outcome' => $progress['project_outcome'],
                'stages' => $progress['stages'],
                'history' => $history,
                'signature' => BidHistory::trackerSignature($progress, $history),
            ];

            if ($includeHtml) {
                $row['html'] = view('bidder.partials.bid-progress-card', [
                    'bid' => $bid,
                    'progress' => $progress,
                    'history' => $history,
                ])->render();
            }

            return $row;
        })->values();

        return response()->json([
            'ok' => true,
            'bids' => $payload,
            'active_bids_count' => $bids->count(),
            'has_recent_updates' => $lastViewed > 0
                && $bids->contains(fn (Bid $bid) => ($bid->updated_at?->timestamp ?? 0) > $lastViewed),
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Bids owned by the signed-in bidder, with everything BidProgress reads.
     */
    private function ownBids(): Builder
    {
        return Bid::with(['project.awards', 'project.rebidProject', 'project.schedule', 'award', 'trackings', 'documents'])
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at');
    }
}
