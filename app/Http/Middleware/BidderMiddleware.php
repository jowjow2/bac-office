<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BidderMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || auth()->user()->role !== 'bidder') {
            abort(403);
        }

        return $next($request);
    }
}
