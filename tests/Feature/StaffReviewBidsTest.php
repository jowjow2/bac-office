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
        // Bids already opened, so documents can be examined and previewed.
        'deadline' => now()->subDay(),
        'status' => 'closed',
        'bids_opened_at' => now()->subHour(),
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

/** Keys of the default preliminary checklist (project has no requirements list). */
function allRequirementKeys(): array
{
    return collect(Bid::REQUIRED_DOCUMENT_CHECKS)->pluck('key')->all();
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

it('keeps the staff proposal preview sealed until financial opening', function () {
    ['staff' => $staff, 'bid' => $bid] = createStaffReviewFixture();

    $response = testCase()
        ->actingAs($staff)
        ->get(route('staff.bids.proposal.preview', $bid));

    $response->assertForbidden();
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

it("prevents BAC Staff from recording a preliminary decision", function () {
    ["staff" => $staff, "bid" => $bid] = createStaffReviewFixture();

    testCase()->actingAs($staff)
        ->patch(route("staff.bids.validate", $bid), ["verified_requirements" => allRequirementKeys()])
        ->assertForbidden();

    expect($bid->fresh()->status)->toBe("pending")
        ->and($bid->fresh()->documents_validated_at)->toBeNull();
});

it("keeps BAC Staff view/prepare-only even for a complete bid", function () {
    ["staff" => $staff, "bidder" => $bidder, "bid" => $bid] = createStaffReviewFixture();
    attachCompleteBidderDocuments($bidder);

    testCase()->actingAs($staff)
        ->patch(route("staff.bids.validate", $bid), ["verified_requirements" => allRequirementKeys()])
        ->assertForbidden();

    expect($bid->fresh()->documents_validated_at)->toBeNull();
});

it("requires BAC Admin for a complete preliminary decision", function () {
    ["staff" => $staff, "bidder" => $bidder, "bid" => $bid] = createStaffReviewFixture();
    attachCompleteBidderDocuments($bidder);

    testCase()->actingAs($staff)
        ->patch(route("staff.bids.validate", $bid), ["verified_requirements" => allRequirementKeys()])
        ->assertForbidden();

    expect($bid->fresh()->workflow_step)->toBe(Bid::STEP_SUBMITTED);
});

it("does not let BAC Staff save a detailed evaluation", function () {
    ["staff" => $staff, "bidder" => $bidder, "bid" => $bid] = createStaffReviewFixture();
    attachCompleteBidderDocuments($bidder);

    testCase()->actingAs($staff)->patchJson(route("staff.bids.evaluate", $bid), [
        "evaluation_status" => "documents_validated",
        "remarks" => "Documents checked and validated.",
        "action" => "save",
        "verified_requirements" => allRequirementKeys(),
    ])->assertForbidden();

    expect($bid->fresh()->workflow_step)->toBe(Bid::STEP_SUBMITTED);
});

it("does not let BAC Staff save an adverse decision", function () {
    ["staff" => $staff, "bid" => $bid] = createStaffReviewFixture();

    testCase()->actingAs($staff)->patchJson(route("staff.bids.evaluate", $bid), [
        "evaluation_status" => "disqualified",
        "remarks" => "Required documents are not compliant.",
        "action" => "reject",
    ])->assertForbidden();

    expect($bid->fresh()->status)->toBe("pending");
});

