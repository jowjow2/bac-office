<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\BiddingFeeQr;
use App\Support\QrSvg;
use Illuminate\Http\Request;

class BiddingFeeQrController extends Controller
{
    public function __invoke(Request $request, Project $project)
    {
        $bidder = $request->user();
        abort_unless($bidder?->role === 'bidder' && $bidder->isApprovedBidder(), 403);
        abort_unless($project->isOpenForBidding() && $project->requiresBiddingFee(), 404);
        abort_if($project->hasPaidBiddingFee($bidder), 409, 'The bidding documents fee is already paid.');

        return response(QrSvg::render(BiddingFeeQr::encode($bidder, $project), 512), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
