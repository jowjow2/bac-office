<?php

use App\Models\Award;
use App\Models\Bid;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    $this->admin = User::create(['name' => 'BAC Admin', 'email' => 'cons-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->bidder = User::create(['name' => 'Divine Company', 'email' => 'cons-bidder@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Divine Company']);
});

it('treats a Consulting award as tracked up to its Notice to Proceed', function () {
    $project = Project::create([
        'title' => 'Consulting Services for the Detailed Engineering Design', 'description' => 'Design.', 'reference_no' => 'SJ-BAC-2026-C-001',
        'category' => 'consultancy', 'procurement_mode' => 'public_bidding', 'legal_basis' => 'ra_12009', 'budget' => 1200000, 'status' => 'awarded',
        'deadline' => now()->subDays(5), 'contract_duration' => '150 calendar days from receipt of the Notice to Proceed',
    ]);
    $bid = Bid::create([
        'project_id' => $project->id, 'user_id' => $this->bidder->id, 'bid_amount' => 1100000, 'status' => 'awarded',
        'workflow_step' => Bid::STEP_NOTICE_TO_PROCEED, 'submission_channel' => Bid::CHANNEL_ELECTRONIC, 'submitted_at' => now()->subDays(6),
        'notice_of_award_at' => now()->subDays(3), 'contract_signed_at' => now()->subDays(2), 'notice_to_proceed_at' => now()->subDay(),
    ]);
    Award::create([
        'project_id' => $project->id, 'bid_id' => $bid->id, 'bidder_id' => $this->bidder->id, 'contract_amount' => 1100000,
        'contract_date' => now()->subDays(2)->toDateString(), 'notice_of_award_date' => now()->subDays(3)->toDateString(), 'status' => 'active',
        'ntp_file_path' => 'notices/ntp.pdf', 'ntp_issued_on' => now()->subDay()->toDateString(), 'ntp_published_at' => now()->subDay(),
    ]);

    testCase()->actingAs($this->admin)->get(route('admin.awards.index'))->assertOk()
        ->assertSee('data-stage="completed"', false)
        ->assertSee('Tracked up to the Notice to Proceed for this category.')
        ->assertSee('150 calendar days')
        ->assertDontSee('No further tracking for this category yet.')
        ->assertDontSee('award-step-label">Completed', false);
});

it('marks the parts of the awards page that the live refresh swaps in place', function () {
    $admin = \App\Models\User::create(['name' => 'Live Admin', 'email' => 'awards-live-admin@example.com', 'password' => \Illuminate\Support\Facades\Hash::make('password'), 'role' => 'admin', 'status' => 'active']);

    $html = testCase()->actingAs($admin)->get(route('admin.awards.index'))->assertOk()->getContent();
    foreach (['summary', 'ready', 'rows', 'cancel-dialogs', 'contract-dialogs'] as $region) {
        expect(substr_count($html, 'data-live-region="'.$region.'"'))->toBe(1, "awards region {$region}");
    }
    foreach (['awaiting', 'implementing', 'completed', 'cancelled'] as $stage) {
        expect(substr_count($html, 'data-live-text="count-'.$stage.'"'))->toBe(1, "awards count {$stage}");
    }
});
