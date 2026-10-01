<?php

use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use App\Support\Uploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
});

it('lets an admin classify files added in project edit so the ITB publication check can pass', function () {
    $test = testCase();
    Storage::fake(Uploads::diskName());

    $admin = User::create([
        'name' => 'Project Editor',
        'email' => 'project-editor@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);
    $project = Project::create([
        'title' => 'Road Improvement Project',
        'description' => 'Concreting of a local road.',
        'budget' => 3450000,
        'deadline' => now()->addDays(14),
        'status' => 'draft',
    ]);

    $test->actingAs($admin)
        ->post(route('admin.project.update', $project), [
            '_method' => 'PUT',
            'title' => $project->title,
            'description' => $project->description,
            'budget' => '3,450,000.00',
            'status' => 'draft',
            'deadline' => now()->addDays(14)->format('Y-m-d H:i:s'),
            'document_type' => 'invitation_to_bid',
            'document_files' => [UploadedFile::fake()->create('invitation-to-bid.pdf', 20, 'application/pdf')],
        ], ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertJson(['success' => true]);

    $document = ProjectDocument::query()->where('project_id', $project->id)->firstOrFail();
    expect($document->document_type)->toBe('invitation_to_bid')
        ->and($document->original_name)->toBe('invitation-to-bid.pdf');
    Storage::disk(Uploads::diskName())->assertExists($document->file_path);
});
it('stores the selected document type when creating a project through the legacy form', function () {
    Storage::fake(Uploads::diskName());
    $admin = User::create([
        'name' => 'Project Creator',
        'email' => 'project-creator@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    testCase()->actingAs($admin)->post(route('admin.projects.store'), [
        'title' => 'New Goods Procurement',
        'description' => 'Supply of office equipment.',
        'budget' => 500000,
        'status' => 'draft',
        'deadline' => now()->addDays(14)->toDateString(),
        'document_type' => 'invitation_to_bid',
        'document_files' => [UploadedFile::fake()->create('itb.pdf', 10, 'application/pdf')],
    ])->assertSessionHasNoErrors();

    $document = ProjectDocument::query()->where('original_name', 'itb.pdf')->firstOrFail();
    expect($document->document_type)->toBe('invitation_to_bid');
    Storage::disk(Uploads::diskName())->assertExists($document->file_path);
});