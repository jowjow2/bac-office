<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

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
    $response->assertSessionHas('success', 'Project created successfully.');

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

it('allows admins to upload a project file during project creation', function () {
    $test = testCase();

    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');

    $admin = User::create([
        'name' => 'Admin Uploader',
        'email' => 'admin-project-upload@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $response = $test
        ->actingAs($admin)
        ->post(route('admin.projects.store'), [
            'title' => 'Municipal Annex Renovation',
            'description' => 'Interior and exterior renovation works for the annex building.',
            'budget' => 2750000,
            'status' => 'approved_for_bidding',
            'deadline' => now()->addDays(9)->toDateString(),
            'document_files' => [
                UploadedFile::fake()->create('scope-of-work.pdf', 128, 'application/pdf'),
                UploadedFile::fake()->create('project-specs.docx', 96, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ],
        ]);

    $response->assertRedirect(route('admin.projects'));
    $response->assertSessionHas('success', 'Project created successfully.');

    $project = Project::where('title', 'Municipal Annex Renovation')->firstOrFail();

    expect($project->document_path)->toStartWith('project-documents/');
    expect($project->document_original_name)->toBe('scope-of-work.pdf');
    Storage::disk('public')->assertExists($project->document_path);
    expect($project->documents)->toHaveCount(2);
    expect($project->documents->pluck('original_name')->all())->toBe([
        'scope-of-work.pdf',
        'project-specs.docx',
    ]);
    Storage::disk('public')->assertExists($project->documents[1]->file_path);

    $viewResponse = $test
        ->actingAs($admin)
        ->get(route('admin.project.view', $project));

    $viewResponse->assertOk();
    $viewResponse->assertSee('Project Files');
    $viewResponse->assertSee('Click any file below to open its PDF preview.');
    $viewResponse->assertSee('scope-of-work.pdf');
    $viewResponse->assertSee('project-specs.docx');
    $viewResponse->assertSee(route('admin.project.document.pdf', ['project' => $project, 'document' => 0]), false);
    $viewResponse->assertSee(route('admin.project.document.pdf', ['project' => $project, 'document' => 1]), false);

    $listingResponse = $test
        ->actingAs($admin)
        ->get(route('admin.projects'));

    $listingResponse->assertOk();
    $listingResponse->assertSee('2 files attached');
    $listingResponse->assertDontSee('scope-of-work.pdf');
    $listingResponse->assertDontSee('project-specs.docx');

    $filesResponse = $test
        ->actingAs($admin)
        ->get(route('admin.project.files', $project));

    $filesResponse->assertOk();
    $filesResponse->assertSee('Project Files');
    $filesResponse->assertSee('2 files uploaded');
    $filesResponse->assertSee('scope-of-work.pdf');
    $filesResponse->assertSee('project-specs.docx');
    $filesResponse->assertDontSee('Total Bids');
    $filesResponse->assertDontSee('Deadline');

    $firstPreviewResponse = $test
        ->actingAs($admin)
        ->get(route('admin.project.document.pdf', ['project' => $project, 'document' => 0]));

    $firstPreviewResponse->assertOk();
    expect((string) $firstPreviewResponse->headers->get('content-type'))->toContain('application/pdf');

    $secondPreviewResponse = $test
        ->actingAs($admin)
        ->get(route('admin.project.document.pdf', ['project' => $project, 'document' => 1]));

    $secondPreviewResponse->assertOk();
    expect((string) $secondPreviewResponse->headers->get('content-type'))->toContain('application/pdf');
});

it('allows admins to append more project files when editing a project', function () {
    $test = testCase();

    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');

    $admin = User::create([
        'name' => 'Admin Editor',
        'email' => 'admin-project-editor@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $project = Project::create([
        'title' => 'Public Market Upgrade',
        'description' => 'Initial project package.',
        'budget' => 1500000,
        'deadline' => now()->addDays(7),
        'status' => 'approved_for_bidding',
    ]);

    $response = $test
        ->actingAs($admin)
        ->put(route('admin.project.update', $project), [
            'title' => 'Public Market Upgrade',
            'description' => 'Initial project package with added files.',
            'budget' => 1500000,
            'status' => 'approved_for_bidding',
            'deadline' => now()->addDays(10)->toDateString(),
            'document_files' => [
                UploadedFile::fake()->create('terms.pdf', 64, 'application/pdf'),
                UploadedFile::fake()->create('drawings.png', 120, 'image/png'),
            ],
        ]);

    $response->assertRedirect(route('admin.projects'));

    $project->refresh()->load('documents');

    expect($project->documents)->toHaveCount(2);
    expect($project->uploadedDocuments()->pluck('display_name')->all())->toBe([
        'terms.pdf',
        'drawings.png',
    ]);
});

it('allows admins to delete uploaded project files', function () {
    $test = testCase();

    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');

    $admin = User::create([
        'name' => 'Admin File Manager',
        'email' => 'admin-project-files@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $response = $test
        ->actingAs($admin)
        ->post(route('admin.projects.store'), [
            'title' => 'Bridge Repair Package',
            'description' => 'Uploaded files will be managed from the files modal.',
            'budget' => 1950000,
            'status' => 'approved_for_bidding',
            'deadline' => now()->addDays(6)->toDateString(),
            'document_files' => [
                UploadedFile::fake()->create('bridge-plan.pdf', 128, 'application/pdf'),
                UploadedFile::fake()->create('bridge-specs.docx', 96, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ],
        ]);

    $response->assertRedirect(route('admin.projects'));

    $project = Project::where('title', 'Bridge Repair Package')->firstOrFail();
    $project->load('documents');

    $firstStoredPath = $project->documents[0]->file_path;
    $secondStoredPath = $project->documents[1]->file_path;

    $firstDeleteResponse = $test
        ->actingAs($admin)
        ->deleteJson(route('admin.project.document.destroy', ['project' => $project, 'document' => 0]));

    $firstDeleteResponse->assertOk();
    $firstDeleteResponse->assertJson([
        'success' => true,
        'message' => 'Project file deleted successfully.',
        'deleted_name' => 'bridge-plan.pdf',
        'remaining_count' => 1,
    ]);

    $project->refresh()->load('documents');

    expect($project->documents)->toHaveCount(1);
    expect($project->document_path)->toBe($secondStoredPath);
    expect($project->document_original_name)->toBe('bridge-specs.docx');
    Storage::disk('public')->assertMissing($firstStoredPath);
    Storage::disk('public')->assertExists($secondStoredPath);

    $secondDeleteResponse = $test
        ->actingAs($admin)
        ->deleteJson(route('admin.project.document.destroy', ['project' => $project, 'document' => 0]));

    $secondDeleteResponse->assertOk();
    $secondDeleteResponse->assertJson([
        'success' => true,
        'message' => 'Project file deleted successfully.',
        'deleted_name' => 'bridge-specs.docx',
        'remaining_count' => 0,
    ]);

    $project->refresh()->load('documents');

    expect($project->documents)->toHaveCount(0);
    expect($project->document_path)->toBeNull();
    expect($project->document_original_name)->toBeNull();
    Storage::disk('public')->assertMissing($secondStoredPath);
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
