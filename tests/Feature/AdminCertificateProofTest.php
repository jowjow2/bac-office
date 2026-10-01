<?php

use App\Models\Bid;
use App\Models\BidderDocument;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
});

it('shows certificate proof links on the admin dashboard', function () {
    $test = testCase();

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $bidder = User::create([
        'name' => 'Bidder User',
        'email' => 'bidder@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Bidder Company',
        'registration_no' => 'REG-1001',
    ]);

    $pendingBidder = User::create([
        'name' => 'Pending Bidder',
        'email' => 'pending@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'pending',
        'company' => 'Pending Company',
        'registration_no' => 'REG-1002',
    ]);

    $project = Project::create([
        'title' => 'Road Repair Project',
        'description' => 'Repair works for Barangay access road.',
        'budget' => 2500000,
        'deadline' => now()->addWeek(),
        'status' => 'open',
    ]);

    $bid = Bid::create([
        'user_id' => $bidder->id,
        'project_id' => $project->id,
        'bid_amount' => 2250000,
        'proposal_file' => 'uploads/proposals/bid.pdf',
        'status' => 'pending',
        'notes' => 'Initial submission',
    ]);

    BidderDocument::create([
        'user_id' => $bidder->id,
        'document_type' => 'PhilGEPS Certificate',
        'original_name' => 'bidder-certificate.pdf',
        'file_path' => 'uploads/bidder-documents/bidder-certificate.pdf',
        'status' => 'uploaded',
        'uploaded_at' => now(),
    ]);

    BidderDocument::create([
        'user_id' => $pendingBidder->id,
        'document_type' => 'PhilGEPS Certificate',
        'original_name' => 'pending-certificate.pdf',
        'file_path' => 'uploads/bidder-documents/pending-certificate.pdf',
        'status' => 'uploaded',
        'uploaded_at' => now(),
    ]);

    $response = $test
        ->actingAs($admin)
        ->get(route('admin.dashboard'));

    // The overview lists registrations waiting for review with their PhilGEPS certificate;
    // a bid's certificate proof is opened from bid review.
    $response->assertOk();
    $response->assertSee('Bidder registrations');
    $response->assertSee('View PhilGEPS certificate');
    $response->assertSee('uploads/bidder-documents/pending-certificate.pdf', false);
});

it('shows the bidder certificate proof in admin bid details', function () {
    $test = testCase();

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin-detail@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $bidder = User::create([
        'name' => 'Bid Detail User',
        'email' => 'bid-detail@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Detail Company',
        'registration_no' => 'REG-2001',
    ]);

    $project = Project::create([
        'title' => 'School Supplies Procurement',
        'description' => 'Supply delivery for public schools.',
        'budget' => 1500000,
        'deadline' => now()->addDays(10),
        'status' => 'open',
    ]);

    $bid = Bid::create([
        'user_id' => $bidder->id,
        'project_id' => $project->id,
        'bid_amount' => 1400000,
        'proposal_file' => 'uploads/proposals/detail.pdf',
        'status' => 'pending',
        'notes' => 'Submitted for review',
    ]);

    BidderDocument::create([
        'user_id' => $bidder->id,
        'document_type' => 'PhilGEPS Certificate',
        'original_name' => 'detail-certificate.pdf',
        'file_path' => 'uploads/bidder-documents/detail-certificate.pdf',
        'status' => 'uploaded',
        'uploaded_at' => now(),
    ]);

    $response = $test
        ->actingAs($admin)
        ->get(route('admin.bid.view', $bid));

    // Direct navigation returns to the register, where the modal is opened by the
    // client. The sealed certificate filename and URL stay out of the redirect.
    $response->assertRedirect(route('admin.bids', ['view_bid' => $bid->id]));

    $modalResponse = $test
        ->actingAs($admin)
        ->get(route('admin.bid.view', $bid), ['X-Requested-With' => 'XMLHttpRequest']);

    $modalResponse->assertOk();
    $modalResponse->assertSee('Certificate Proof');
    $modalResponse->assertSee('Received &middot; sealed until bid opening', false);
    $modalResponse->assertDontSee('detail-certificate.pdf');
    $modalResponse->assertDontSee(route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'certificate']), false);
});

it('shows uploaded approved bids on the admin dashboard and filters them in all bids', function () {
    $test = testCase();

    $admin = User::create([
        'name' => 'Admin Approved Bids',
        'email' => 'admin-approved@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $approvedBidder = User::create([
        'name' => 'Approved Bidder',
        'email' => 'approved-bidder@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Approved Builders Co.',
        'registration_no' => 'REG-3001',
    ]);

    $otherBidder = User::create([
        'name' => 'Other Bidder',
        'email' => 'other-bidder@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Other Builders Co.',
        'registration_no' => 'REG-3002',
    ]);

    $project = Project::create([
        'title' => 'Bridge Expansion Project',
        'description' => 'Bridge widening and structural strengthening.',
        'budget' => 3200000,
        'deadline' => now()->subDay(),
        'status' => 'closed',
        'bids_opened_at' => now()->subHour(),
    ]);

    $approvedBid = Bid::create([
        'user_id' => $approvedBidder->id,
        'project_id' => $project->id,
        'bid_amount' => 3000000,
        'proposal_file' => 'uploads/proposals/approved-bid.pdf',
        'status' => 'approved',
        'notes' => 'Approved with uploaded proposal',
    ]);

    Bid::create([
        'user_id' => $otherBidder->id,
        'project_id' => $project->id,
        'bid_amount' => 3050000,
        'proposal_file' => null,
        'status' => 'approved',
        'notes' => 'Approved but missing upload',
    ]);

    $dashboardResponse = $test
        ->actingAs($admin)
        ->get(route('admin.dashboard'));

    // The project appears in the procurement register; its proposals are opened from bid review.
    $dashboardResponse->assertOk();
    $dashboardResponse->assertSee('Bridge Expansion Project');

    $bidsResponse = $test
        ->actingAs($admin)
        ->get(route('admin.bids', ['status' => 'approved', 'proposal' => 'uploaded']));

    $bidsResponse->assertOk();
    $bidsResponse->assertSee('Approved Builders Co.');
    $bidsResponse->assertSee('Review bid');
    $bidsResponse->assertDontSee('Proposal: Missing');
    $bidsResponse->assertDontSee('Other Builders Co.');
    $bidsResponse->assertDontSee('Missing upload');
});

it('shows view docs and edit bid actions in the admin bid modal', function () {
    $test = testCase();

    $admin = User::create([
        'name' => 'Admin Bid Actions',
        'email' => 'admin-bid-actions@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $bidder = User::create([
        'name' => 'Action Bidder',
        'email' => 'action-bidder@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Action Builders',
        'registration_no' => 'REG-3003',
    ]);

    $project = Project::create([
        'title' => 'Municipal Hall Lighting',
        'description' => 'Lighting system upgrade for the municipal hall.',
        'budget' => 650000,
        'deadline' => now()->addDays(7),
        'status' => 'open',
    ]);

    $bid = Bid::create([
        'user_id' => $bidder->id,
        'project_id' => $project->id,
        'bid_amount' => 620000,
        'proposal_file' => 'uploads/proposals/action-bid.pdf',
        'status' => 'approved',
        'notes' => 'Ready for admin action review.',
    ]);

    $listResponse = $test
        ->actingAs($admin)
        ->get(route('admin.bids'));

    $listResponse->assertOk();
    $listResponse->assertDontSee('View Docs');
    $listResponse->assertDontSee('Edit Bid');

    $modalResponse = $test
        ->actingAs($admin)
        ->get(route('admin.bid.view', $bid), ['X-Requested-With' => 'XMLHttpRequest']);

    $modalResponse->assertOk();
    $modalResponse->assertSee('Documents');
    $modalResponse->assertSee('Internal notes (not shown to bidder)');
    $modalResponse->assertDontSee(route('admin.bid.edit', $bid), false);
    $modalResponse->assertSee('Submission sealed');
    $modalResponse->assertDontSee(route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'proposal']), false);
});

it('keeps the legacy proposal preview sealed until financial opening', function () {
    $test = testCase();

    $admin = User::create([
        'name' => 'Admin Preview User',
        'email' => 'admin-preview@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $bidder = User::create([
        'name' => 'Preview Bidder',
        'email' => 'preview-bidder@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Preview Builders',
        'registration_no' => 'REG-3004',
    ]);

    $project = Project::create([
        'title' => 'Barangay Office Upgrade',
        'description' => 'Interior upgrade and fit-out works.',
        'budget' => 880000,
        // Proposals can be previewed only after the recorded bid opening.
        'deadline' => now()->subDay(),
        'status' => 'closed',
        'bids_opened_at' => now()->subHour(),
    ]);

    $bid = Bid::create([
        'user_id' => $bidder->id,
        'project_id' => $project->id,
        'bid_amount' => 835000,
        'proposal_file' => 'uploads/proposals/preview-bid.pdf',
        'status' => 'approved',
        'notes' => 'Ready for inline preview testing.',
    ]);

    BidderDocument::create([
        'user_id' => $bidder->id,
        'document_type' => 'PhilGEPS Certificate',
        'original_name' => 'preview-certificate.pdf',
        'file_path' => 'uploads/bidder-documents/preview-certificate.pdf',
        'status' => 'uploaded',
        'uploaded_at' => now(),
    ]);

    $redirectResponse = $test
        ->actingAs($admin)
        ->get(route('admin.bid.document.preview', ['bid' => $bid, 'document' => 'proposal']));

    $redirectResponse->assertForbidden();

    $pdfResponse = $test
        ->actingAs($admin)
        ->get(route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'proposal']));

    $pdfResponse->assertForbidden();
});
