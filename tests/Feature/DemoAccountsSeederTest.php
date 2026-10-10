<?php

use App\Models\Bidder;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds 200 bidders once, and removes them again', function () {
    testCase()->seed(DemoAccountsSeeder::class);
    testCase()->seed(DemoAccountsSeeder::class); // Running it again updates, never duplicates.

    $demo = User::query()->where('email', 'like', '%@'.DemoAccountsSeeder::DOMAIN);
    expect((clone $demo)->where('role', 'bidder')->count())->toBe(200)
        ->and((clone $demo)->where('role', 'end_user')->count())->toBe(0)
        ->and(Bidder::where('approval_status', 'approved')->count())->toBe(170)
        ->and(Bidder::where('approval_status', 'pending')->count())->toBe(20)
        ->and(Bidder::where('approval_status', 'rejected')->count())->toBe(10)
        ->and(Bidder::distinct()->count('company_name'))->toBe(200);

    // Approved bidders pass the approved-bidder gate.
    $approved = User::query()->where('email', 'bidder001@'.DemoAccountsSeeder::DOMAIN)->firstOrFail();
    expect($approved->status)->toBe('active')->and($approved->bidderProfile->approval_status)->toBe('approved');

    putenv('DEMO_ACCOUNTS=remove');
    try {
        testCase()->seed(DemoAccountsSeeder::class);
    } finally {
        putenv('DEMO_ACCOUNTS');
    }
    expect(User::query()->where('email', 'like', '%@'.DemoAccountsSeeder::DOMAIN)->count())->toBe(0)
        ->and(Bidder::count())->toBe(0);
});
