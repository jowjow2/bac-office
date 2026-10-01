<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BidderMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user || $user->role !== 'bidder' || ! $user->canLoginAsBidder()) {
            abort(403, 'Unauthorized');
        }

        // Needs Action and For Re-evaluation bidders may use only the
        // SJBAC Portal correction flow. Approved bidders keep full access.
        if ($user->hasRestrictedBidderAccess() && ! $request->routeIs(
            'bidder.company-profile',
            'bidder.documents.store',
            'bidder.requirements.reevaluate'
        )) {
            return redirect()->route('bidder.company-profile');
        }

        // A Needs Action bidder may replace only a document explicitly
        // requested by SJBAC. The email never becomes an upload channel.
        if (
            $user->hasRestrictedBidderAccess()
            && $user->bidderReviewStatus() === 'needs_action'
            && $request->routeIs('bidder.documents.store')
        ) {
            $documentType = trim((string) $request->input('document_type'));
            $profile = $user->relationLoaded('bidderProfile')
                ? $user->bidderProfile
                : $user->bidderProfile()->first();

            $requested = $profile
                && $documentType !== ''
                && $profile->requirementRequests()
                    ->where('status', 'open')
                    ->where('document_type', $documentType)
                    ->exists();

            if (! $requested) {
                return redirect()
                    ->route('bidder.company-profile')
                    ->withErrors(['document_type' => 'Only documents requested by the SJBAC may be replaced.']);
            }
        }

        return $next($request);
    }
}
