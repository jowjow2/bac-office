<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ApplyTimePreview;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Turns an admin's time preview on and off (see ApplyTimePreview). Only the session changes. */
class TimePreviewController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'preview_at' => ['required', 'date_format:Y-m-d\\TH:i'],
        ]);

        $at = CarbonImmutable::createFromFormat('!Y-m-d\\TH:i', $validated['preview_at'], ApplyTimePreview::TIMEZONE);
        $request->session()->put(ApplyTimePreview::SESSION_KEY, $at->format('Y-m-d H:i:s'));

        return back()->with('success', 'Time preview: pages now show '.$at->format('M d, Y h:i A').'. Nothing is saved until you go back to real time.');
    }

    public function destroy(Request $request)
    {
        $request->session()->forget(ApplyTimePreview::SESSION_KEY);

        return back()->with('success', 'Back to real time. Changes are saved again.');
    }
}
