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
    $listingResponse->assertDontSee('files attached');
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
            // The edit form shows the ABC with thousands separators.
            'budget' => '1,500,000.00',
            'status' => 'approved_for_bidding',
            'deadline' => now()->addDays(10)->toDateString(),
            'document_files' => [
                UploadedFile::fake()->create('terms.pdf', 64, 'application/pdf'),
                UploadedFile::fake()->create('drawings.png', 120, 'image/png'),
            ],
        ]);

    $response->assertRedirect(route('admin.projects'));

    $project->refresh()->load('documents');

    expect($project->documents)->toHaveCount(2)
        ->and($project->budget)->toBe('1500000.00');
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

it('saves project wizard drafts without publish-only fields', function () {
    $test = testCase();

    $admin = User::create([
        'name' => 'Admin Draft Creator',
        'email' => 'admin-wizard-draft@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $response = $test
        ->actingAs($admin)
        ->post(route('admin.projects.wizard.store'), [
            'title' => 'Wizard Draft Project',
            'status' => 'draft',
        ]);

    $response->assertRedirect(route('admin.projects') . '?status=draft');
    $response->assertSessionHas('success', 'Project created successfully.');

    $project = Project::where('title', 'Wizard Draft Project')->firstOrFail();

    expect($project->status)->toBe('draft');
    expect((float) $project->budget)->toBe(0.0);
    expect($project->deadline)->toBeNull();
    expect($project->requirement)->not->toBeNull();
    expect($project->schedule)->not->toBeNull();

    $listingResponse = $test
        ->actingAs($admin)
        ->get(route('admin.projects', ['status' => 'draft']));

    $listingResponse->assertOk();
    $listingResponse->assertSee('Wizard Draft Project');
    expect($listingResponse->viewData('projectTotals')['draft'])->toBe(1);
});

it('publishes project wizard submissions as open projects with related records', function () {
    $test = testCase();

    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');

    $admin = User::create([
        'name' => 'Admin Wizard Publisher',
        'email' => 'admin-wizard-publish@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    // RA 9184: 7-day posting, pre-bid conference 12+ days before the deadline
    // (ABC >= ₱1M), bid opening immediately after the deadline the same day.
    $submissionDeadline = workdayAt(20, 9, 0)->format('Y-m-d\TH:i');
    $openingDate = workdayAt(20, 9, 30)->format('Y-m-d\TH:i');

    $response = $test
        ->actingAs($admin)
        ->post(route('admin.projects.wizard.store'), [
            'title' => 'Wizard Published Project',
            'description' => 'A complete project created from the wizard publish path.',
            'category' => 'goods',
            'location' => 'City Hall',
            'procurement_mode' => 'public_bidding',
            'source_of_fund' => 'General Fund',
            'contract_duration' => '45 calendar days',
            'budget' => 3250000,
            'status' => 'open',
            'philgeps_reference_no' => '11223344',
            'bidding_documents_fee' => '5000',
            'payment_venue' => 'BAC Secretariat, Municipal Hall',
            'award_criterion' => 'lowest_calculated_bid',
            'bid_opening_venue' => 'BAC Conference Room, Municipal Hall',
            'electronic_submission_authority' => 'MIS Certification No. 2026-03',
            'date_posted' => now()->toDateString(),
            'pre_bid_conference_date' => workdayAt(6, 10, 0)->format('Y-m-d\TH:i'),
            'bid_submission_deadline' => $submissionDeadline,
            'bid_opening_date' => $openingDate,
            'required_documents' => ['Business Permit', 'Technical Proposal'],
            'eligibility_requirements' => 'Valid registration documents.',
            'technical_requirements' => 'Compliant technical offer.',
            'financial_requirements' => 'Signed financial proposal.',
            'document_type' => ['invitation_to_bid', 'technical_specifications'],
            'project_documents' => [
                UploadedFile::fake()->create('invitation.pdf', 96, 'application/pdf'),
                UploadedFile::fake()->create('technical-specs.pdf', 96, 'application/pdf'),
            ],
            'confirm_correct' => 'on',
        ]);

    $response->assertRedirect(route('admin.projects'));
    $response->assertSessionHas('success', 'Project created successfully.');

    $project = Project::where('title', 'Wizard Published Project')->firstOrFail();
    $project->load(['requirement', 'schedule', 'documents']);

    expect($project->status)->toBe('open');
    expect($project->reference_no)->toBe('SJ-BAC-' . now()->format('Y') . '-G-001');
    expect($project->submission_mode)->toBe('electronic')
        ->and($project->bidding_documents_fee)->toBe('5000.00')
        ->and($project->payment_venue)->toBe('BAC Secretariat, Municipal Hall')
        // Electronic bids need the IT certification before posting (RA 12009 IRR Sec. 50.3.3).
        ->and($project->electronic_submission_authority)->toBe('MIS Certification No. 2026-03')
        ->and($project->award_criterion)->toBe('lowest_calculated_bid')
        ->and($project->bid_opening_venue)->toBe('BAC Conference Room, Municipal Hall');
    expect((float) $project->budget)->toBe(3250000.0);
    expect($project->deadline?->format('Y-m-d H:i'))->toBe(str_replace('T', ' ', $submissionDeadline));
    expect($project->requirement->required_documents)->toBe(['Business Permit', 'Technical Proposal']);
    expect($project->schedule->bid_submission_deadline?->format('Y-m-d H:i'))->toBe(str_replace('T', ' ', $submissionDeadline));
    expect($project->schedule->bid_opening_date?->format('Y-m-d H:i'))->toBe(str_replace('T', ' ', $openingDate));
    expect($project->documents)->toHaveCount(2);
    expect($project->documents->pluck('document_type')->all())->toBe([
        'invitation_to_bid',
        'technical_specifications',
    ]);
    expect($project->documents->pluck('original_name')->all())->toBe([
        'invitation.pdf',
        'technical-specs.pdf',
    ]);
    Storage::disk('public')->assertExists($project->documents[0]->file_path);
    Storage::disk('public')->assertExists($project->documents[1]->file_path);

    $listingResponse = $test
        ->actingAs($admin)
        ->get(route('admin.projects'));

    $listingResponse->assertOk();
    $listingResponse->assertSee('Wizard Published Project');
    expect($listingResponse->viewData('projectTotals')['all'])->toBe(1);
    expect($listingResponse->viewData('projectTotals')['open'])->toBe(1);
});

it('shows procurement QR login context with the project category', function () {
    $test = testCase();

    $project = Project::create([
        'title' => 'QR Category Project',
        'description' => 'Project opened for QR scan testing.',
        'category' => 'infrastructure',
        'budget' => 1230000,
        'deadline' => now()->addDays(8),
        'status' => 'open',
    ]);

    $procurementResponse = $test->get(route('public.procurement'));

    $procurementResponse->assertOk();
    $procurementResponse->assertSee(route('public.procurement.qr', $project), false);
    $procurementResponse->assertSee(route('login.page', ['qr_project' => $project->id]), false);
    $procurementResponse->assertSee('Infrastructure');

    $qrResponse = $test->get(route('public.procurement.qr', $project));

    $qrResponse->assertOk();
    $qrResponse->assertHeader('Content-Type', 'image/svg+xml');

    $loginResponse = $test->get(route('login.page', ['qr_project' => $project->id]));

    $loginResponse->assertRedirect(route('home'));
    $loginResponse->assertSessionHas('scanned_project_title', 'QR Category Project');
    $loginResponse->assertSessionHas('scanned_project_category', 'Infrastructure');
});
it('publishes draft projects from the admin projects page', function () {
    $test = testCase();

    $admin = User::create([
        'name' => 'Admin Publisher',
        'email' => 'admin-project-publisher@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $project = Project::create([
        'title' => 'Draft Road Repair',
        'description' => 'Draft project that should become open for bidding.',
        'category' => 'infrastructure',
        'procurement_mode' => 'public_bidding',
        'award_criterion' => 'lowest_calculated_bid',
        'bid_opening_venue' => 'BAC Conference Room, Municipal Hall',
        'electronic_submission_authority' => 'MIS Certification No. 2026-03',
        'philgeps_reference_no' => '55667788',
        'budget' => 850000,
        'deadline' => workdayAt(10, 9, 0),
        'status' => 'draft',
    ]);
    \App\Models\ProjectSchedule::create([
        'project_id' => $project->id,
        'date_posted' => now()->toDateString(),
        'bid_submission_deadline' => workdayAt(10, 9, 0),
        'bid_opening_date' => workdayAt(10, 9, 30),
    ]);
    \App\Models\ProjectDocument::create([
        'project_id' => $project->id,
        'original_name' => 'itb.pdf',
        'file_path' => 'project-documents/project_doc_' . $project->id . '_itb.pdf',
        'document_type' => 'invitation_to_bid',
    ]);

    $listingResponse = $test
        ->actingAs($admin)
        ->get(route('admin.projects'));

    $listingResponse->assertOk();
    $listingResponse->assertSee('onclick="publishDraft(' . $project->id . ', this)"', false);

    $response = $test
        ->actingAs($admin)
        ->postJson(route('admin.project.publish', $project));

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Project published successfully! It is now ready for bidding.',
        ]);

    expect($project->fresh()->status)->toBe('open');
});
