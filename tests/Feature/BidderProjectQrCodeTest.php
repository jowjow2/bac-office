<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function createBidderQrUser(): User
{
    return User::create([
        'name' => 'QR Bidder',
        'email' => 'qr-bidder@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'QR Builders Inc.',
        'registration_no' => 'REG-QR-100',
    ]);
}

function createBidderQrProject(array $overrides = []): Project
{
    return Project::create(array_merge([
        'title' => 'Bidder QR Project',
        'description' => 'Project visible to bidders with a QR code.',
        'budget' => 850000,
        'deadline' => now()->addWeek(),
        'status' => 'open',
    ], $overrides));
}

beforeEach(function () {
    testCase()->withoutVite();
});

it('shows a scannable project qr code on the bidder available projects page', function () {
    $bidder = createBidderQrUser();
    $project = createBidderQrProject([
        'title' => 'Water System Upgrade',
    ]);

    $response = testCase()
        ->actingAs($bidder)
        ->get(route('bidder.available-projects'));

    $response->assertOk();
    $response->assertSee('Water System Upgrade');
    $response->assertSee('Scan Project QR');
    $response->assertSee('Project QR');
    $response->assertSee('Use Website Camera');
    $response->assertSee('Open Phone Camera');
    $response->assertSee('Upload QR Image');
    $response->assertSee('capture="environment"', false);
    $response->assertSee('data-scanner-quick-input', false);
    $response->assertSee('data-scanner-preview', false);
    $response->assertSee(route('public.procurement.scan', $project), false);
    $response->assertSee(route('public.procurement.show', $project), false);
    $response->assertSee('data:image/svg+xml;base64,', false);
});

it('shows project qr codes on the bidder dashboard open projects table', function () {
    $bidder = createBidderQrUser();
    $project = createBidderQrProject([
        'title' => 'Health Center Extension',
    ]);

    $response = testCase()
        ->actingAs($bidder)
        ->get(route('bidder.dashboard'));

    $response->assertOk();
    $response->assertSee('Open Projects');
    $response->assertSee('Health Center Extension');
    $response->assertSee('Scan Project QR');
    $response->assertSee('Scan QR');
    $response->assertSee('Use Website Camera');
    $response->assertSee('Open Phone Camera');
    $response->assertSee('Upload QR Image');
    $response->assertSee('data-scanner-quick-input', false);
    $response->assertSee('data-scanner-preview', false);
    $response->assertSee(route('public.procurement.scan', $project), false);
    $response->assertSee(route('public.procurement.show', $project), false);
    $response->assertSee('data:image/svg+xml;base64,', false);
});

it('targets the scanned open project on the bidder available projects page', function () {
    $bidder = createBidderQrUser();
    $project = createBidderQrProject([
        'title' => 'Market Rehabilitation',
    ]);

    $response = testCase()
        ->actingAs($bidder)
        ->get(route('bidder.available-projects', ['scan_project' => $project->id]));

    $response->assertOk();
    $response->assertSee('QR project found. Review the project below and complete your bid submission.');
    $response->assertSee('data-project-id="' . $project->id . '"', false);
    $response->assertSee('bid-modal-' . $project->id, false);
});

it('redirects bidder phone qr scans for open projects to the bidder action page', function () {
    $bidder = createBidderQrUser();
    $project = createBidderQrProject([
        'title' => 'Municipal Hall Retrofit',
    ]);

    testCase()
        ->actingAs($bidder)
        ->get(route('public.procurement.scan', $project))
        ->assertRedirect(route('bidder.available-projects', ['scan_project' => $project->id]));
});
