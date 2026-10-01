<?php

namespace App\Http\Controllers;

use App\Support\ProcurementClock;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class DemoClockController extends Controller
{
    public function update(Request $request, ProcurementClock $clock)
    {
        abort_unless($clock->demoModeEnabled() && $request->user()?->role === 'admin', 404);
        $validated = $request->validate([
            'simulated_at' => ['required', 'date_format:Y-m-d\\TH:i'],
        ]);

        $clock->set(CarbonImmutable::createFromFormat('!Y-m-d\\TH:i', $validated['simulated_at'], $clock->timezone()), (int) $request->user()->id);

        return back()->with('success', 'Demo procurement time updated to '.$clock->now()->format('M d, Y h:i A').' (Asia/Manila).');
    }

    public function reset(Request $request, ProcurementClock $clock)
    {
        abort_unless($clock->demoModeEnabled() && $request->user()?->role === 'admin', 404);
        $clock->reset();

        return back()->with('success', 'Demo clock reset to verified server time.');
    }
}