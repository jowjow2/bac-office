<?php

namespace App\Http\Controllers;

use App\Models\Award;
use App\Models\Bid;
use App\Models\Bidder;
use App\Support\QrSvg;
use Illuminate\Support\Facades\Log;

class PublicBidderController extends Controller
{
    /**
     * Public, read-only bidding-record page (no login required).
     * URL: /bidder/verify/{token}
     */
    public function verify(string $token)
    {
        $bidder = Bidder::with('user')->where('qr_token', $token)->first();
        abort_unless($bidder && $bidder->user, 404, 'Invalid or unavailable bidder record.');

        $user = $bidder->user;

        $approvedBids = Bid::with('project')
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->orderByDesc('created_at')
            ->get();

        $awards = Award::query()
            ->publiclyPosted()
            ->whereHas('bid', fn ($query) => $query->where('user_id', $user->id))
            ->with('project')
            ->orderByDesc('notice_of_award_date')
            ->orderByDesc('contract_date')
            ->get();

        return view('public.bidder-verify', [
            'bidder' => $bidder,
            'user' => $user,
            'approvedBids' => $approvedBids,
            'awards' => $awards,
        ]);
    }

    /**
     * Generate the QR code image (SVG) that encodes the verification URL above.
     * URL: /bidder/qr/{token}.svg
     */
    public function qrByToken(string $token)
    {
        $bidder = Bidder::where('qr_token', $token)->first();
        abort_unless($bidder, 404);

        try {
            $svg = QrSvg::render($bidder->verificationUrl(), 320);
        } catch (\Throwable $exception) {
            Log::error('Bidder QR code generation failed', [
                'bidder_id' => $bidder->getKey(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            abort(500, 'The bidder QR code could not be generated.');
        }

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
