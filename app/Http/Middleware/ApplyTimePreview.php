<?php

namespace App\Http\Middleware;

use Carbon\Carbon as BaseCarbon;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Time preview for a BAC admin: the pages show the system as of a chosen date
 * and time (deadlines passed, bid opening due, ...), for this admin's session
 * only. Nothing is saved while it is on: saving actions are refused, and
 * anything a page would write while being viewed is rolled back. Other users
 * keep the real time.
 */
class ApplyTimePreview
{
    public const SESSION_KEY = 'time_preview_at';

    public const TIMEZONE = 'Asia/Manila';

    public function handle(Request $request, Closure $next): Response
    {
        $stored = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;
        $user = $stored ? $request->user() : null;
        if (! $stored || ! $user || $user->role !== 'admin') {
            return $next($request);
        }

        $previewAt = CarbonImmutable::parse($stored, self::TIMEZONE);

        // Saving is refused, except turning the preview off (and signing out).
        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
            && ! $request->routeIs('admin.time-preview.*', 'logout')) {
            $message = 'Time preview is on ('.$previewAt->format('M d, Y h:i A').'): nothing can be saved. Choose "Back to real time" to make changes.';

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $message, 'time_preview' => true], 423)
                : back()->with('error', $message);
        }

        Carbon::setTestNow($previewAt);
        BaseCarbon::setTestNow($previewAt);
        CarbonImmutable::setTestNow($previewAt);
        View::share('timePreviewAt', $previewAt);

        // Pages may record things while they are viewed (read marks, hand-offs, logs): undo all of it.
        $level = DB::transactionLevel();
        DB::beginTransaction();
        try {
            return $next($request);
        } finally {
            while (DB::transactionLevel() > $level) {
                DB::rollBack();
            }
            Carbon::setTestNow();
            BaseCarbon::setTestNow();
            CarbonImmutable::setTestNow();
            View::share('timePreviewAt', null);
        }
    }
}
