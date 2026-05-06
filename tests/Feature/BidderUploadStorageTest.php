<?php

use App\Models\Bid;
use App\Models\BidderDocument;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');
});

it('stores bidder documents on the configured uploads disk', function () {
    $bidder = User::create([
        'name' => 'Bidder User',
        'email' => 'storage-bidder@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Storage Bidder Inc.',
        'registration_no' => 'REG-3001',
    ]);

    $response = testCase()
        ->actingAs($bidder)
        ->post(route('bidder.documents.store'), [
            'document_type' => 'PhilGEPS Certificate',
            'document_file' => UploadedFile::fake()->create('certificate.pdf', 64, 'application/pdf'),
        ]);

    $response->assertRedirect(route('bidder.company-profile'));

    $document = BidderDocument::firstOrFail();

    expect($document->file_path)->toStartWith('bidder-documents/');
    Storage::disk('public')->assertExists($document->file_path);
});

it('stores bid proposals on the configured uploads disk', function () {
    $bidder = User::create([
        'name' => 'Proposal Bidder',
        'email' => 'proposal-bidder@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Proposal Bidder Inc.',
        'registration_no' => 'REG-3002',
    ]);

    $project = Project::create([
        'title' => 'Storage Ready Project',
        'description' => 'Testing cloud-safe proposal uploads.',
        'budget' => 500000,
        'deadline' => now()->addWeek(),
        'status' => 'open',
    ]);

    $response = testCase()
        ->actingAs($bidder)
        ->post(route('bidder.bids.store', $project), [
            'bid_amount' => 450000,
            'eligibility_file' => UploadedFile::fake()->create('eligibility.pdf', 88, 'application/pdf'),
            'proposal_file' => UploadedFile::fake()->create('proposal.pdf', 96, 'application/pdf'),
            'notes' => 'Storage-backed upload test.',
        ]);

    $response->assertRedirect(route('bidder.available-projects'));

    $bid = Bid::firstOrFail();

    expect($bid->eligibility_file)->toStartWith('eligibility-documents/');
    expect($bid->proposal_file)->toStartWith('proposals/');
    Storage::disk('public')->assertExists($bid->eligibility_file);
    Storage::disk('public')->assertExists($bid->proposal_file);
});
