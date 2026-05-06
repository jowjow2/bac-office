<?php

use App\Models\Assignment;
use App\Models\Bid;
use App\Models\BidderDocument;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');
});

function createStaffReviewFixture(array $bidOverrides = []): array
{
    $staff = User::create([
        'name' => 'Staff Reviewer',
        'email' => 'staff-reviewer@example.com',
        'password' => Hash::make('password'),
        'role' => 'staff',
        'status' => 'active',
    ]);

    $bidder = User::create([
        'name' => 'Bidder User',
        'email' => 'review-bidder@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Review Builders Inc.',
        'registration_no' => 'REG-REVIEW-100',
    ]);

    $project = Project::create([
        'title' => 'Road Repair Project',
        'description' => 'Repair municipal access road.',
        'budget' => 900000,
        'deadline' => now()->addDays(14),
        'status' => 'open',
    ]);

    Assignment::create([
        'staff_id' => $staff->id,
        'project_id' => $project->id,
        'role_in_project' => 'Evaluator',
    ]);

    Storage::disk('public')->put('proposals/review-proposal.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
    Storage::disk('public')->put('eligibility-documents/review-eligibility.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

    $bid = Bid::create(array_merge([
        'user_id' => $bidder->id,
        'project_id' => $project->id,
        'bid_amount' => 850000,
        'proposal_file' => 'proposals/review-proposal.pdf',
        'eligibility_file' => 'eligibility-documents/review-eligibility.pdf',
        'status' => 'pending',
        'notes' => '',
    ], $bidOverrides));

    return compact('staff', 'bidder', 'project', 'bid');
}

function attachCompleteBidderDocuments(User $bidder): void
{
    foreach ([
        'Business Permit',
        'PhilGEPS Certificate',
        'Audited Financial Statement',
        'DTI/SEC Registration',
    ] as $type) {
        $filePath = 'bidder-documents/' . str($type)->slug() . '.pdf';

        Storage::disk('public')->put($filePath, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

        BidderDocument::create([
            'user_id' => $bidder->id,
            'document_type' => $type,
            'original_name' => str($type)->slug() . '.pdf',
            'file_path' => $filePath,
            'status' => 'uploaded',
            'uploaded_at' => now(),
        ]);
    }
}

it('shows proposal view and document eligibility controls on staff review bids', function () {
    ['staff' => $staff, 'bidder' => $bidder, 'bid' => $bid] = createStaffReviewFixture();
    attachCompleteBidderDocuments($bidder);

    $response = testCase()
        ->actingAs($staff)
        ->get(route('staff.review-bids'));

    $response->assertOk();
    $response->assertSee('View PDF');
    $response->assertSee(route('staff.bids.proposal.preview', $bid), false);
    $response->assertSee(route('staff.bids.eligibility.preview', $bid), false);
    $response->assertSee('Documents: Complete');
    $response->assertSee('Eligibility: Pending Review');
    $response->assertSee('Eligibility file: uploaded');
    $response->assertSee('Check Bid');
    $response->assertSee('Eligibility Document');
    $response->assertSee('Business Permit');
    $response->assertSee('PhilGEPS Registration');
    $response->assertSee('Tax Clearance');

    $businessPermit = BidderDocument::where('user_id', $bidder->id)
        ->where('document_type', 'Business Permit')
        ->firstOrFail();

    $response->assertSee(route('staff.bids.documents.pdf', ['bid' => $bid, 'document' => $businessPermit]), false);
});

it('streams a staff proposal preview inline instead of forcing download', function () {
    ['staff' => $staff, 'bid' => $bid] = createStaffReviewFixture();

    $response = testCase()
        ->actingAs($staff)
        ->get(route('staff.bids.proposal.preview', $bid));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    $response->assertHeader('Content-Disposition', 'inline; filename="review-proposal.pdf"');
});

it('streams a staff eligibility document preview inline', function () {
    ['staff' => $staff, 'bid' => $bid] = createStaffReviewFixture();

    $response = testCase()
        ->actingAs($staff)
        ->get(route('staff.bids.eligibility.preview', $bid));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    $response->assertHeader('Content-Disposition', 'inline; filename="review-eligibility.pdf"');
});

it('streams submitted bidder documents as inline pdfs for assigned staff', function () {
    ['staff' => $staff, 'bidder' => $bidder, 'bid' => $bid] = createStaffReviewFixture();
    attachCompleteBidderDocuments($bidder);

    $businessPermit = BidderDocument::where('user_id', $bidder->id)
        ->where('document_type', 'Business Permit')
        ->firstOrFail();

    $response = testCase()
        ->actingAs($staff)
        ->get(route('staff.bids.documents.pdf', ['bid' => $bid, 'document' => $businessPermit]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    $response->assertHeader('Content-Disposition', 'inline; filename="business-permit.pdf"');
});

it('prevents staff validation until documents are complete and eligibility is valid', function () {
    ['staff' => $staff, 'bid' => $bid] = createStaffReviewFixture();

    $response = testCase()
        ->actingAs($staff)
        ->patch(route('staff.bids.validate', $bid));

    $response->assertRedirect();
    $response->assertSessionHas('warning', 'Cannot validate bid. Please ensure documents are complete and eligibility is valid.');

    expect($bid->fresh()->status)->toBe('pending');
});

it('lets staff mark eligibility valid and then validate a complete bid', function () {
    ['staff' => $staff, 'bidder' => $bidder, 'bid' => $bid] = createStaffReviewFixture();
    attachCompleteBidderDocuments($bidder);

    testCase()
        ->actingAs($staff)
        ->patch(route('staff.bids.eligibility', $bid), [
            'eligibility_status' => 'valid',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Eligibility confirmed valid. Your bid has moved to Documents Validated.');

    expect($bid->fresh()->eligibility_status)->toBe('valid');

    testCase()
        ->actingAs($staff)
        ->patch(route('staff.bids.validate', $bid))
        ->assertRedirect()
        ->assertSessionHas('success', 'Bid documents marked as validated. Workflow advanced to Documents Validated.');

    expect($bid->fresh()->status)->toBe('approved');
});

it('lets staff save a BAC bid evaluation through the check bid modal endpoint', function () {
    ['staff' => $staff, 'bidder' => $bidder, 'bid' => $bid] = createStaffReviewFixture();
    attachCompleteBidderDocuments($bidder);

    $response = testCase()
        ->actingAs($staff)
        ->patchJson(route('staff.bids.evaluate', $bid), [
            'evaluation_status' => 'documents_validated',
            'remarks' => 'Documents checked and validated.',
            'action' => 'save',
        ]);

    $response->assertOk();
    $response->assertJsonPath('ok', true);
    $response->assertJsonPath('bid.workflow_step', Bid::STEP_DOCUMENTS_VALIDATED);

    $bid->refresh();
    expect($bid->workflow_step)->toBe(Bid::STEP_DOCUMENTS_VALIDATED)
        ->and($bid->documents_validated_at)->not->toBeNull()
        ->and($bid->notes)->toContain('Documents checked and validated.');
});

it('requires remarks before staff can reject from the check bid modal', function () {
    ['staff' => $staff, 'bid' => $bid] = createStaffReviewFixture();

    $response = testCase()
        ->actingAs($staff)
        ->patchJson(route('staff.bids.evaluate', $bid), [
            'evaluation_status' => 'disqualified',
            'remarks' => '',
            'action' => 'reject',
        ]);

    $response->assertStatus(422);
    $response->assertJsonPath('ok', false);

    expect($bid->fresh()->status)->toBe('pending');
});
