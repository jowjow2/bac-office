<?php

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Pest\Support\HigherOrderTapProxy;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function testCase(): TestCase
{
    $test = test();

    if ($test instanceof HigherOrderTapProxy && $test->target instanceof TestCase) {
        return $test->target;
    }

    throw new RuntimeException('The current Pest test is not bound to Tests\\TestCase.');
}

/**
 * A moment on a working day (Mon-Fri), at least $daysAhead days from now, at the
 * given local time. Schedules must fall within LGU office hours.
 */
function workdayAt(int $daysAhead, int $hour, int $minute = 0): Carbon
{
    // Count from a weekday: run on a weekend, pushing each date off the weekend
    // separately would shrink the gaps between them (e.g. +7 and +20 on a
    // Saturday became 11 days apart, under the 12-day pre-bid rule).
    $base = now();
    if ($base->isWeekend()) {
        $base = $base->copy()->next(CarbonInterface::MONDAY)->setTimeFrom(now());
    }
    $day = $base->copy()->addDays($daysAhead);
    while ($day->isWeekend()) {
        $day->addDay();
    }

    return $day->setTime($hour, $minute);
}
