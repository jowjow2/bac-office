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
