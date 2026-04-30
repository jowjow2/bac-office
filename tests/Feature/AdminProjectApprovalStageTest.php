<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
});

it('allows admins to create a project with approved for bidding status', function () {
    $test = testCase();

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin-approval-stage@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $response = $test
        ->actingAs($admin)
        ->post(route('admin.projects.store'), [
            'title' => 'Community Hall Expansion',
            'description' => 'Expansion works for the municipal community hall.',
            'budget' => 1800000,
            'status' => 'approved_for_bidding',
            'deadline' => now()->addWeek()->toDateString(),
        ]);

    $response->assertRedirect(route('admin.projects'));
    $response->assertSessionHas('success', 'Project created successfully!');

    $test->assertDatabaseHas('projects', [
        'title' => 'Community Hall Expansion',
        'status' => 'approved_for_bidding',
    ]);

    $listingResponse = $test
        ->actingAs($admin)
        ->get(route('admin.projects'));

    $listingResponse->assertOk();
    $listingResponse->assertSee('Community Hall Expansion');
    $listingResponse->assertSee('Approved for Bidding');
});

it('keeps approved for bidding projects hidden from bidder available projects until they are opened', function () {
    $test = testCase();

    $bidder = User::create([
        'name' => 'Bidder User',
        'email' => 'bidder-approval-stage@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Approval Stage Company',
        'registration_no' => 'REG-3001',
    ]);

    Project::create([
        'title' => 'Drainage Improvement Project',
        'description' => 'Drainage improvement and desilting works.',
        'budget' => 950000,
        'deadline' => now()->addDays(8),
        'status' => 'approved_for_bidding',
    ]);

    Project::create([
        'title' => 'Road Concreting Project',
        'description' => 'Road concreting for barangay access route.',
        'budget' => 2100000,
        'deadline' => now()->addDays(12),
        'status' => 'open',
    ]);

    $response = $test
        ->actingAs($bidder)
        ->get(route('bidder.available-projects'));

    $response->assertOk();
    $response->assertSee('Road Concreting Project');
    $response->assertDontSee('Drainage Improvement Project');
});
