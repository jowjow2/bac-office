<?php

use App\Models\Award;
use App\Models\Bid;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
});

it('shows live procurement highlights on the homepage', function () {
    $bidder = User::create([
        'name' => 'ACME Builders',
        'email' => 'acme@example.com',
        'password' => 'password123',
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'ACME Builders',
        'registration_no' => 'REG-9001',
    ]);

    $openProject = Project::create([
        'title' => 'School Building Repair',
        'description' => 'Roofing and classroom rehabilitation for the public elementary school.',
        'budget' => 1250000,
        'deadline' => now()->addDays(12),
        'status' => 'open',
    ]);

    $awardedProject = Project::create([
        'title' => 'Water System Upgrade',
        'description' => 'Upgrade of the municipal water distribution and pumping facilities.',
        'budget' => 2400000,
        'deadline' => now()->subDays(4),
        'status' => 'awarded',
    ]);

    Project::create([
        'title' => 'Internal Draft Project',
        'description' => 'Not yet visible to the public.',
        'budget' => 900000,
        'deadline' => now()->addMonth(),
        'status' => 'approved_for_bidding',
    ]);

    $approvedBid = Bid::create([
        'user_id' => $bidder->id,
        'project_id' => $awardedProject->id,
        'bid_amount' => 2300000,
        'status' => 'approved',
    ]);
    
    Award::create([
        'project_id' => $awardedProject->id,
        'bid_id' => $approvedBid->id,
        'contract_amount' => 2300000,
        'contract_date' => now()->subDay(),
        'status' => 'active',
    ]);

    $response = testCase()->get(route('home'));

    $response->assertOk();
    $response->assertViewIs('pages.home');
    $response->assertViewHas('publicProjectsCount', 2);
    $response->assertViewHas('openProjectsCount', 1);
    $response->assertViewHas('awardedContractsCount', 1);
    $response->assertViewHas('latestProjects', fn ($projects) => $projects->count() === 2
        && $projects->contains('title', 'School Building Repair')
        && $projects->contains('title', 'Water System Upgrade')
        && ! $projects->contains('title', 'Internal Draft Project'));
    $response->assertViewHas('latestAwards', fn ($awards) => $awards->count() === 1
        && $awards->first()->project?->title === 'Water System Upgrade');
    $response->assertSee('Bids and Awards Committee Portal');
    $response->assertSee('Ensuring transparency, accountability, and efficiency');
    $response->assertSee('School Building Repair');
    $response->assertSee('Water System Upgrade');
    $response->assertSee(route('public.procurement.show', $openProject), false);
    $response->assertDontSee('Internal Draft Project');
});
