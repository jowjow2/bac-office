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

    $response->assertOk();
    $response->assertSee('Certificate Proof');
    $response->assertSee('View Proof');
    $response->assertSee('View PhilGEPS certificate');
    $response->assertSee(route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'certificate']), false);
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

    $response->assertOk();
    $response->assertSee('Certificate Proof');
    $response->assertSee('detail-certificate.pdf');
    $response->assertSee(route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'certificate']), false);
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
        'deadline' => now()->addDays(14),
        'status' => 'open',
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

    $dashboardResponse->assertOk();
    $dashboardResponse->assertSee('Uploaded Approved Bids');
    $dashboardResponse->assertSee('Approved Builders Co.');
    $dashboardResponse->assertSee('Bridge Expansion Project');
    $dashboardResponse->assertSee('View Proposal');
    $dashboardResponse->assertSee(route('admin.bid.document.pdf', ['bid' => $approvedBid, 'document' => 'proposal']), false);

    $bidsResponse = $test
        ->actingAs($admin)
        ->get(route('admin.bids', ['status' => 'approved', 'proposal' => 'uploaded']));

    $bidsResponse->assertOk();
    $bidsResponse->assertSee('Approved Builders Co.');
    $bidsResponse->assertSee('View proposal');
    $bidsResponse->assertSee(route('admin.bid.document.pdf', ['bid' => $approvedBid, 'document' => 'proposal']), false);
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
    $modalResponse->assertSee('View Docs');
    $modalResponse->assertSee('Edit Bid');
    $modalResponse->assertSee(route('admin.bid.view', $bid), false);
    $modalResponse->assertSee(route('admin.bid.edit', $bid), false);
    $modalResponse->assertSee(route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'proposal']), false);
});

it('streams only the clicked bid document as an inline pdf', function () {
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
        'deadline' => now()->addDays(9),
        'status' => 'open',
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

    $redirectResponse->assertRedirect(route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'proposal']));

    $pdfResponse = $test
        ->actingAs($admin)
        ->get(route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'proposal']));

    $pdfResponse->assertOk();
    $pdfResponse->assertHeader('Content-Type', 'application/pdf');
    expect($pdfResponse->headers->get('Content-Disposition'))->toContain('inline');
    expect($pdfResponse->headers->get('Content-Disposition'))->not->toContain('attachment');
});
