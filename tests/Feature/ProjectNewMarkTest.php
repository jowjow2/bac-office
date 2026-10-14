<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('marks a project NEW in the Projects list until the admin opens it', function () {
    testCase()->withoutVite();
    $admin = User::create(['name' => 'BAC Admin', 'email' => 'new-mark-admin@example.org', 'password' => 'x-not-used-1', 'role' => 'admin', 'status' => 'active']);
    $make = fn (string $title, array $extra = []) => Project::create(array_merge([
        'title' => $title, 'description' => 'x', 'category' => 'goods', 'procurement_mode' => 'public_bidding', 'legal_basis' => 'ra_12009',
        'budget' => 500000, 'status' => 'draft',
    ], $extra));

    $seen = $make('Already seen project', ['seen_at' => now()->subDay()]);
    Project::whereKey($seen->id)->update(['created_at' => now()->subHour()]);
    $fresh = $make('Just created project');
    expect($seen->isNew())->toBeFalse()->and($fresh->isNew())->toBeTrue();

    $html = testCase()->actingAs($admin)->get(route('admin.projects'))->assertOk()->getContent();
    // Only the new one carries the mark, and it is the first row of the list.
    preg_match_all('/class="projects-title-button"[^>]*>\s*(<span class="pj-new")?/', $html, $rows);
    expect(substr_count($html, 'class="pj-new"'))->toBe(1)
        ->and($rows[1][0])->not->toBe('')->and($rows[1][1])->toBe('');

    // Opening the project (the View button) clears the mark.
    testCase()->actingAs($admin)->get(route('admin.project.view', $fresh), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
    expect($fresh->fresh()->isNew())->toBeFalse();
    expect(substr_count(testCase()->actingAs($admin)->get(route('admin.projects'))->getContent(), 'class="pj-new"'))->toBe(0);
});
