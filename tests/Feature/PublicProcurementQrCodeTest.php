<?php

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPublicProcurementProject(array $overrides = []): Project
{
    return Project::create(array_merge([
        'title' => 'Public Project',
        'description' => 'Public procurement details.',
        'budget' => 1500000,
        'deadline' => now()->addWeek(),
        'status' => 'open',
    ], $overrides));
}

beforeEach(function () {
    testCase()->withoutVite();
});

it('shows qr-enabled public links for visible procurement projects', function () {
    $project = createPublicProcurementProject([
        'title' => 'Bridge Repair Project',
    ]);

    createPublicProcurementProject([
        'title' => 'Internal Draft Project',
        'status' => 'approved_for_bidding',
    ]);

    $response = testCase()->get(route('public.procurement'));

    $response->assertOk();
    $response->assertSee('Bridge Repair Project');
    $response->assertSee(route('public.procurement.scan', $project), false);
    $response->assertSee(route('public.procurement.show', $project), false);
    $response->assertSee('data:image/svg+xml;base64,', false);
    $response->assertDontSee('Internal Draft Project');
});

it('shows the public procurement detail page with a qr code', function () {
    $project = createPublicProcurementProject([
        'title' => 'Town Plaza Renovation',
        'status' => 'awarded',
    ]);

    $response = testCase()->get(route('public.procurement.show', $project));

    $response->assertOk();
    $response->assertSee('Town Plaza Renovation');
    $response->assertSee('Scan to open this page');
    $response->assertSee(route('public.procurement.scan', $project), false);
    $response->assertSee('data:image/svg+xml;base64,', false);
});

it('redirects phone qr scans for guests to the public procurement detail page', function () {
    $project = createPublicProcurementProject([
        'title' => 'Riverbank Protection Project',
        'status' => 'open',
    ]);

    testCase()
        ->get(route('public.procurement.scan', $project))
        ->assertRedirect(route('public.procurement.show', $project));
});

it('returns not found for non-public procurement detail pages', function () {
    $project = createPublicProcurementProject([
        'title' => 'Pre-release Project',
        'status' => 'approved_for_bidding',
    ]);

    testCase()
        ->get(route('public.procurement.show', $project))
        ->assertNotFound();

    testCase()
        ->get(route('public.procurement.scan', $project))
        ->assertNotFound();
});
