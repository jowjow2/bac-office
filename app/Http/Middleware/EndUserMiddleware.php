<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * An active end-user office account with its office recorded: it sees and
 * files procurement requests for that office only.
 */
class EndUserMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && $user->role === 'end_user' && $user->status === 'active' && filled($user->office)) {
            return $next($request);
        }

        abort(403, 'Unauthorized');
    }
}
