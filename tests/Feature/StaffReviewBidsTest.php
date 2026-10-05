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

it('shows staff the bid register for their assigned projects, with the review modal but no decisions', function () {
    ['staff' => $staff, 'bid' => $bid] = createStaffReviewFixture();
    $otherProject = Project::create(['title' => 'Unassigned Library Project', 'description' => 'x', 'budget' => 500000, 'deadline' => now()->subDay(), 'status' => 'closed']);
    $otherBid = Bid::create(['user_id' => $bid->user_id, 'project_id' => $otherProject->id, 'bid_amount' => 400000, 'status' => 'pending', 'notes' => '']);

    testCase()->actingAs($staff)->get(route('staff.review-bids'))->assertOk()
        ->assertSee('Review bids &amp; quotations', false)
        ->assertSee('Road Repair Project')
        ->assertDontSee('Unassigned Library Project')
        ->assertSee(route('staff.bid.view', ['bid' => '__BID__']), false)
        ->assertDontSee(route('admin.bid.edit', ['bid' => '__BID__']), false);

    $modal = testCase()->actingAs($staff)->get(route('staff.bid.view', $bid), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
    $modal->assertSee('The BAC Admin records the opening and every evaluation decision.')
        // Every link in the modal goes through the staff routes.
        ->assertDontSee('/admin/bids/', false)
        ->assertDontSee(route('admin.bid.decision', ['bid' => $bid], false), false)
        ->assertDontSee('data-br-proceed', false);

    // Bids of projects not assigned to this staff member stay closed.
    testCase()->actingAs($staff)->get(route('staff.bid.view', $otherBid), ['X-Requested-With' => 'XMLHttpRequest'])->assertForbidden();
    testCase()->actingAs($staff)->get(route('staff.bid.document.pdf', ['bid' => $otherBid, 'document' => 'proposal']))->assertForbidden();
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


it('shows staff their assigned projects with tasks and review links, and honest report figures', function () {
    ['staff' => $staff, 'bid' => $bid, 'project' => $project] = createStaffReviewFixture(['submitted_at' => now()->subDays(2), 'receipt_no' => 'R-001']);
    $project->update(['status' => 'awarded']);
    \App\Models\Award::create(['project_id' => $project->id, 'bid_id' => $bid->id, 'bidder_id' => $bid->user_id, 'contract_amount' => 850000, 'status' => 'active']);
    $unawarded = Project::create(['title' => 'Open Clinic Supplies', 'description' => 'x', 'budget' => 2000000, 'deadline' => now()->addDays(5), 'status' => 'open']);
    Assignment::create(['staff_id' => $staff->id, 'project_id' => $unawarded->id]);

    testCase()->actingAs($staff)->get(route('staff.assign-projects'))->assertOk()
        ->assertSee('My assigned projects')
        ->assertSee('Your tasks')
        ->assertSee('Record the PhilGEPS posting of the ITB / RFQ')
        ->assertSee(e(route('staff.review-bids', ['project' => $project->id, 'view_bid' => $bid->id])), false)
        ->assertDontSee(route('staff.bids.validate', $bid), false)
        ->assertDontSee('>Validate<', false);

    $report = testCase()->actingAs($staff)->get(route('staff.reports'))->assertOk()
        ->assertSee('Government savings')->assertSee('Passed preliminary')
        ->assertSee('Where your projects stand')->assertSee('5.6% below the ABC of awarded projects')->assertSee('50,000.00');
    // Savings count only the awarded project's ABC (900,000 - 850,000), not the open project's.
    expect($report->viewData('governmentSavings'))->toBe(50000.0)
        ->and($report->viewData('totalAwardedAmount'))->toBe(850000.0);
});

it('explains an empty staff report instead of showing zeros', function () {
    testCase()->withoutVite();
    $staff = \App\Models\User::create(['name' => 'New Staff', 'email' => 'new-staff-report@example.com', 'password' => \Illuminate\Support\Facades\Hash::make('password'), 'role' => 'staff', 'status' => 'active', 'office' => 'BAC Secretariat']);

    testCase()->actingAs($staff)->get(route('staff.reports'))->assertOk()
        ->assertSee('No projects are assigned to you yet')
        ->assertDontSee('Download PDF')
        ->assertDontSee('Total ABC');
});
