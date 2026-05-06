<?php

namespace App\Http\Middleware;

use App\Support\UserPresence;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TouchUserPresence
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            UserPresence::touch((int) $request->user()->id);
        }

        return $next($request);
    }
}
