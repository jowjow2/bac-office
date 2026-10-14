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

/**
 * The rows of an Excel (.xlsx) download as lists of cell values (an empty cell is ''), header row first.
 */
function xlsxRows($response): array
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $response->getContent());
    $zip = new ZipArchive;
    expect($zip->open($path, ZipArchive::CHECKCONS))->toBeTrue();
    $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
    $zip->close();
    @unlink($path);

    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $cell) {
            $letters = preg_replace('/\d+/', '', (string) $cell['r']);
            $index = 0;
            foreach (str_split($letters) as $letter) {
                $index = $index * 26 + ord($letter) - 64;
            }
            $cells[$index - 1] = isset($cell->is) ? (string) $cell->is->t : (string) $cell->v;
        }
        $line = [];
        for ($i = 0; $i <= (empty($cells) ? -1 : max(array_keys($cells))); $i++) {
            $line[] = $cells[$i] ?? '';
        }
        $rows[] = $line;
    }

    return $rows;
}

/** An Excel (.xlsx) download as plain text: one line per row, cells joined by commas. */
function xlsxText($response): string
{
    return implode("\n", array_map(fn ($row) => implode(',', $row), xlsxRows($response)));
}

/** A bidder account whose email address was already confirmed (new registrations must confirm it before approval). */
function verifiedBidder(array $attributes): \App\Models\User
{
    $user = \App\Models\User::create($attributes);
    $user->forceFill(['email_verified_at' => now()])->save();

    return $user;
}
