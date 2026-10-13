<?php

use App\Models\Bidder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('gives an approved bidder a bottom tab bar on every bidder page, and nobody else', function () {
    $admin = User::create(['name' => 'BAC Admin', 'email' => 'tabbar-admin@example.org', 'password' => 'x-not-used-1', 'role' => 'admin', 'status' => 'active']);
    $bidder = User::create(['name' => 'Tab Bidder', 'email' => 'tabbar-bidder@example.org', 'company' => 'Tab Trading', 'password' => 'x-not-used-1', 'role' => 'bidder', 'status' => 'active']);
    Bidder::create(['user_id' => $bidder->id, 'company_name' => 'Tab Trading', 'contact_person' => 'Tab Bidder', 'contact_number' => '09170000000', 'business_address' => 'San Jose', 'approval_status' => 'approved', 'approved_at' => now()->subDay(), 'approved_by' => $admin->id]);

    foreach (['bidder.dashboard', 'bidder.available-projects', 'bidder.my-bids', 'bidder.awarded-contracts', 'bidder.company-profile'] as $route) {
        testCase()->actingAs($bidder)->get(route($route))->assertOk()
            ->assertSee('portal-tabbar', false)->assertSee('My bids')->assertSee('Contracts')->assertSee('Menu');
    }

    // The current page is marked, and the admin's pages have no bar.
    testCase()->actingAs($bidder)->get(route('bidder.my-bids'))->assertSee('aria-current="page"', false);
    testCase()->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('portal-tabbar', false);
});
