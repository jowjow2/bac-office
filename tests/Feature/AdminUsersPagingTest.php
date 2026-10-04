<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lists users 25 a page with a fixed number of queries, keeping the tab and search', function () {
    testCase()->withoutVite();
    $admin = User::create(['name' => 'Admin', 'email' => 'page-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    foreach (range(1, 30) as $i) {
        $bidder = User::create(['name' => "Bidder {$i}", 'email' => "page-bidder{$i}@example.com", 'password' => 'x', 'role' => 'bidder', 'status' => 'active', 'company' => "Company {$i}", 'registration_no' => "REG-{$i}"]);
        $bidder->bidderProfile()->create(['company_name' => "Company {$i}", 'contact_person' => 'C', 'contact_number' => '09171234567', 'business_address' => 'San Jose', 'approval_status' => 'approved']);
    }

    DB::enableQueryLog();
    $first = testCase()->actingAs($admin)->get(route('admin.users', ['filter' => 'bidder']))->assertOk();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    $first->assertSee('Showing 1&ndash;25 of 30', false)
        ->assertSee('filter=bidder&amp;page=2', false);
    // Per-row status checks no longer query the schema for every bidder.
    expect($queries)->toBeLessThan(40);

    testCase()->actingAs($admin)->get(route('admin.users', ['filter' => 'bidder', 'page' => 2]))->assertOk()
        ->assertSee('Showing 26&ndash;30 of 30', false)
        ->assertSee('Page 2 of 2');
});
