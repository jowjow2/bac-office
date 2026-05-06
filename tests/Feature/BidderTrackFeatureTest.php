<?php

use App\Models\Bid;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
});

it('shows approved admin bids as green validated progress in bidder track data', function () {
    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'track-admin@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $bidder = User::create([
        'name' => 'Track Bidder',
        'email' => 'track-bidder@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Track Company',
        'registration_no' => 'TRACK-001',
    ]);

    $project = Project::create([
        'title' => 'Track Project',
        'description' => 'Project for live track test.',
        'budget' => 200000,
        'deadline' => now()->addDays(5),
        'status' => 'open',
    ]);

    $bid = Bid::create([
        'project_id' => $project->id,
        'user_id' => $bidder->id,
        'bid_amount' => 190000,
        'status' => 'pending',
        'workflow_step' => Bid::STEP_SUBMITTED,
    ]);

    testCase()
        ->actingAs($admin)
        ->patch(route('admin.bid.approve', $bid))
        ->assertRedirect();

    $bid->refresh();
    expect($bid->status)->toBe('approved')
        ->and($bid->workflow_step)->toBe(Bid::STEP_APPROVED)
        ->and($bid->documents_validated_at)->not->toBeNull()
        ->and($bid->approved_at)->not->toBeNull();

    $response = testCase()
        ->actingAs($bidder)
        ->getJson(route('bidder.bidding-track.data'));

    $response->assertOk();
    $response->assertJsonPath('bids.0.workflow_step', Bid::STEP_APPROVED);
    $response->assertJsonPath('bids.0.timeline_steps.pending_validation.completed', true);
    $response->assertJsonPath('bids.0.timeline_steps.pending_validation.verified', true);
    $response->assertJsonPath('bids.0.timeline_steps.documents_validated.completed', true);
    $response->assertJsonPath('bids.0.timeline_steps.documents_validated.verified', true);
    $response->assertJsonPath('bids.0.timeline_steps.approved.current', true);
});
