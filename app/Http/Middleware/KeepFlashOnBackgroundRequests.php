<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A "saved" message is flashed for the next request. The pages poll in the background (notifications,
 * live updates), and a poll that lands between a redirect and the page it leads to would use up
 * that message first. Background GET requests therefore carry the flashed data over.
 */
class KeepFlashOnBackgroundRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ($request->ajax() || $request->expectsJson()) && $request->hasSession()) {
            $request->session()->reflash();
        }

        return $next($request);
    }
}
