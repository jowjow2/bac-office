<?php

namespace App\Http\Middleware;

use App\Support\BidOpening;
use App\Support\ProcurementClock;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ApplyProcurementClock
{
    public function __construct(private readonly ProcurementClock $clock) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->clock->applyToRequest();

        // Scheduled bid openings that came due since the last check happen
        // before the page is built, so every role sees the same state.
        try {
            app(BidOpening::class)->openDueTechnicalProjects();
        } catch (\Throwable $exception) {
            report($exception);
        }

        if ($this->clock->demoModeEnabled()) {
            View::share('procurementDemoMode', true);
            View::share('procurementDemoNow', $this->clock->now());
            View::share('procurementDemoTimezone', $this->clock->timezone());
        }

        return $next($request);
    }
}