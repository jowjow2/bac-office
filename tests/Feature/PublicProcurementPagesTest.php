<?php

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPublicProjectPage(array $overrides = []): Project
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

it('shows visible procurement projects on the public listing', function () {
    $project = createPublicProjectPage([
        'title' => 'Bridge Repair Project',
    ]);

    createPublicProjectPage([
        'title' => 'Internal Draft Project',
        'status' => 'approved_for_bidding',
    ]);

    $response = testCase()->get(route('public.procurement'));

    $response->assertOk();
    $response->assertSee('Bridge Repair Project');
    $response->assertSee(route('public.procurement.show', $project), false);
    $response->assertDontSee('data:image/svg+xml;base64,', false);
    $response->assertDontSee('Internal Draft Project');
});

it('shows the public procurement detail page', function () {
    $project = createPublicProjectPage([
        'title' => 'Town Plaza Renovation',
        'status' => 'awarded',
    ]);

    $response = testCase()->get(route('public.procurement.show', $project));

    $response->assertOk();
    $response->assertSee('Town Plaza Renovation');
    $response->assertDontSee('data:image/svg+xml;base64,', false);
});

it('returns not found for non-public procurement detail pages and the removed scan endpoint', function () {
    $project = createPublicProjectPage([
        'title' => 'Pre-release Project',
        'status' => 'approved_for_bidding',
    ]);

    testCase()
        ->get(route('public.procurement.show', $project))
        ->assertNotFound();

    testCase()
        ->get('/procurement/projects/' . $project->id . '/scan')
        ->assertNotFound();
});
