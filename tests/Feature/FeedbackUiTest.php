<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('asks before deleting or archiving a project with the in-app dialog, not the browser prompt', function () {
    testCase()->withoutVite();
    $admin = User::create(['name' => 'BAC Admin', 'email' => 'fb-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    Project::create(['title' => 'Purchase of Office Supplies', 'description' => 'Supplies.', 'reference_no' => 'SJ-BAC-2026-G-001', 'category' => 'goods', 'procurement_mode' => 'small_value_procurement', 'budget' => 24450, 'status' => 'open', 'deadline' => now()->addDays(3)]);

    $page = testCase()->actingAs($admin)->get(route('admin.projects'))->assertOk();
    $page->assertSee('data-confirm-title="Delete this project permanently?"', false)
        ->assertSee('data-confirm-tone="danger"', false)
        ->assertSee('data-confirm="Purchase of Office Supplies · SJ-BAC-2026-G-001"', false)
        ->assertSee('data-confirm-title="Archive this project?"', false)
        ->assertSee('window.bacConfirm = function', false)
        ->assertSee('window.bacToast = function', false)
        ->assertDontSee('return confirm(', false);
});

it('starts an empty Projects page from the purchase requests waiting for a project', function () {
    testCase()->withoutVite();
    $admin = User::create(['name' => 'BAC Admin', 'email' => 'fb-admin2@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $office = User::create(['name' => 'Mayor Office', 'email' => 'fb-office@example.com', 'password' => Hash::make('password'), 'role' => 'end_user', 'status' => 'active', 'office' => 'Office of the Municipal Mayor']);
    $request = \App\Models\ProcurementRequest::create([
        'reference_no' => 'PR-2026-0101', 'end_user_office' => 'Office of the Municipal Mayor', 'requested_by' => $office->id,
        'title' => 'Office supplies for the Mayor', 'category' => 'goods', 'specifications' => 'Bond paper', 'quantity' => 10, 'unit' => 'reams',
        'estimated_cost' => 24450, 'fund_source' => 'General Fund', 'delivery_period' => '15 calendar days',
        'status' => \App\Models\ProcurementRequest::STATUS_FORWARDED, 'forwarded_at' => now(),
    ]);

    testCase()->actingAs($admin)->get(route('admin.projects'))->assertOk()
        ->assertSee('Start your first procurement')
        ->assertSee('Office supplies for the Mayor')
        ->assertSee(route('admin.projects.create', ['request' => $request->id]), false)
        ->assertDontSee('projects-summary-grid', false)
        ->assertDontSee('class="projects-filter-form"', false);

    // Once projects exist, the list is back with a reminder of the waiting request.
    Project::create(['title' => 'Road works', 'description' => 'Road.', 'reference_no' => 'SJ-BAC-2026-I-002', 'category' => 'infrastructure', 'procurement_mode' => 'public_bidding', 'budget' => 3000000, 'status' => 'draft']);
    testCase()->actingAs($admin)->get(route('admin.projects'))->assertOk()
        ->assertSee('projects-summary-grid', false)
        ->assertSee('forwarded to the BAC is waiting for a project')
        ->assertDontSee('Start your first procurement');
});
