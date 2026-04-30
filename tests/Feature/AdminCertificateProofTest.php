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

    Bid::create([
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
    $response->assertSee('uploads/bidder-documents/bidder-certificate.pdf', false);
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
    $response->assertSee('uploads/bidder-documents/detail-certificate.pdf', false);
});
