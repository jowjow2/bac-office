<?php

use App\Support\NewBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('marks only what was posted after the cutoff and in the last few days', function () {
    config(['bac-office.new_badge_from' => '2026-10-20 08:00', 'bac-office.new_badge_days' => 3]);
    $zone = config('bac-office.display_timezone', 'Asia/Manila');

    Carbon::setTestNow(Carbon::parse('2026-10-21 09:00', $zone));
    expect(NewBadge::isNew(Carbon::parse('2026-10-20 10:00', $zone)))->toBeTrue()
        // already there before the mark existed
        ->and(NewBadge::isNew(Carbon::parse('2026-10-19 10:00', $zone)))->toBeFalse()
        ->and(NewBadge::isNew(null))->toBeFalse();

    // Three days later it is no longer new.
    Carbon::setTestNow(Carbon::parse('2026-10-24 12:00', $zone));
    expect(NewBadge::isNew(Carbon::parse('2026-10-20 10:00', $zone)))->toBeFalse();
    Carbon::setTestNow();
});

it('shows New on a freshly published project and award posting, not on older ones', function () {
    config(['bac-office.new_badge_from' => now()->subHour()->toDateTimeString()]);
    $fresh = \App\Models\Project::create(['reference_no' => 'SJ-BAC-2026-G-NEW1', 'title' => 'Brand new procurement', 'description' => 'x', 'budget' => 500000, 'status' => 'open', 'deadline' => now()->addDays(10), 'published_at' => now()]);
    \App\Models\Project::create(['reference_no' => 'SJ-BAC-2026-G-OLD1', 'title' => 'Older procurement', 'description' => 'x', 'budget' => 500000, 'status' => 'open', 'deadline' => now()->addDays(10), 'published_at' => now()->subDays(5)]);

    $html = testCase()->get(route('public.procurement'))->assertOk()->getContent();
    expect(substr_count($html, 'public-new-badge">New'))->toBe(1);
    testCase()->get(route('public.procurement'))->assertSeeInOrder(['public-new-badge', 'SJ-BAC-2026-G-NEW1'], false);
});
