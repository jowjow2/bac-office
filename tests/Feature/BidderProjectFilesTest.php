<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createBidderProjectFilesUser(): User
{
    return User::create([
        'name' => 'Project Files Bidder',
        'email' => 'project-files-bidder@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Project Files Builders Inc.',
        'registration_no' => 'REG-FILES-100',
    ]);
}

function createBidderProjectWithFiles(array $overrides = []): Project
{
    $project = Project::create(array_merge([
        'title' => 'Bidder Project Files Package',
        'description' => 'Project package with files that bidders should be able to open.',
        'budget' => 1250000,
        'deadline' => now()->addWeek(),
        'status' => 'open',
    ], $overrides));

    Storage::disk('public')->put('project-documents/scope-of-work.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
    Storage::disk('public')->put('project-documents/technical-specs.docx', 'docx preview fallback content');

    $project->documents()->createMany([
        [
            'original_name' => 'scope-of-work.pdf',
            'file_path' => 'project-documents/scope-of-work.pdf',
        ],
        [
            'original_name' => 'technical-specs.docx',
            'file_path' => 'project-documents/technical-specs.docx',
        ],
    ]);

    return $project;
}

beforeEach(function () {
    testCase()->withoutVite();
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');
});

it('shows attached project files on the bidder available projects page', function () {
    $bidder = createBidderProjectFilesUser();
    $project = createBidderProjectWithFiles([
        'title' => 'Municipal Building Improvement',
    ]);

    $response = testCase()
        ->actingAs($bidder)
        ->get(route('bidder.available-projects'));

    $response->assertOk();
    $response->assertSee('Municipal Building Improvement');
    $response->assertSee('Project Files');
    $response->assertSee('scope-of-work.pdf');
    $response->assertSee('technical-specs.docx');
    $response->assertSee('target="_blank"', false);
    $response->assertSee(route('bidder.project.document.preview', ['project' => $project, 'document' => 0]), false);
    $response->assertSee(route('bidder.project.document.preview', ['project' => $project, 'document' => 1]), false);
    $response->assertDontSee('data:image/svg+xml;base64,', false);
});

it('shows attached project files on the bidder dashboard open projects table', function () {
    $bidder = createBidderProjectFilesUser();
    $project = createBidderProjectWithFiles([
        'title' => 'Health Center Repair Package',
    ]);

    $response = testCase()
        ->actingAs($bidder)
        ->get(route('bidder.dashboard'));

    $response->assertOk();
    $response->assertSee('Open Projects');
    $response->assertSee('Health Center Repair Package');
    $response->assertSee('Project Files');
    $response->assertSee('scope-of-work.pdf');
    $response->assertSee('technical-specs.docx');
    $response->assertSee('target="_blank"', false);
    $response->assertSee(route('bidder.project.document.preview', ['project' => $project, 'document' => 0]), false);
    $response->assertDontSee('data:image/svg+xml;base64,', false);
});

it('opens bidder project files in an inline preview instead of forcing a download', function () {
    $bidder = createBidderProjectFilesUser();
    $project = createBidderProjectWithFiles([
        'title' => 'Flood Control Package',
    ]);

    testCase()
        ->actingAs($bidder)
        ->get(route('bidder.project.document.preview', ['project' => $project, 'document' => 0]))
        ->assertRedirect(route('bidder.project.document.pdf', ['project' => $project, 'document' => 0]));

    $pdfResponse = testCase()
        ->actingAs($bidder)
        ->get(route('bidder.project.document.pdf', ['project' => $project, 'document' => 0]));

    $pdfResponse->assertOk();
    expect((string) $pdfResponse->headers->get('content-type'))->toContain('application/pdf');
    expect((string) $pdfResponse->headers->get('content-disposition'))->toContain('inline');

    $docxResponse = testCase()
        ->actingAs($bidder)
        ->get(route('bidder.project.document.pdf', ['project' => $project, 'document' => 1]));

    $docxResponse->assertOk();
    expect((string) $docxResponse->headers->get('content-type'))->toContain('application/pdf');
});
