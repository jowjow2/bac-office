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
    Storage::fake('local');
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
        'category' => 'goods',
        'procurement_mode' => 'small_value_procurement',
        'budget' => 40000,
        'deadline' => now()->addWeek(),
        'status' => 'open',
        'submission_mode' => Project::SUBMISSION_ELECTRONIC,
        'electronic_submission_authority' => 'BAC Resolution No. 2026-001',
        'electronic_submission_authorized_at' => now(),
    ]);

    // One file per checklist requirement of this project (SVP below ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â±50,000).
    $response = testCase()
        ->actingAs($bidder)
        ->post(route('bidder.bids.store', $project), [
            'bid_amount' => '38500',
            'documents' => [
                'philgeps' => UploadedFile::fake()->create('philgeps.pdf', 88, 'application/pdf'),
                'mayors_permit' => UploadedFile::fake()->create('permit.pdf', 64, 'application/pdf'),
                'financial_bid_form' => UploadedFile::fake()->create('quotation.pdf', 96, 'application/pdf'),
            ],
            'notes' => 'Storage-backed upload test.',
            'financial_password' => '482913',
            'financial_password_confirmation' => '482913',
        ]);

    $response->assertRedirect(route('bidder.available-projects'));

    $bid = Bid::with('documents')->firstOrFail();

    expect($bid->documents)->toHaveCount(3);
    foreach ($bid->documents as $document) {
        $expectedPrefix = $document->component === 'financial' ? "bid-financial/{$project->id}/{$bid->id}/" : "bid-submissions/{$project->id}/{$bid->id}/{$document->component}/";
        expect($document->file_path)->toStartWith($expectedPrefix);
        Storage::disk($document->component === 'financial' ? 'local' : 'public')->assertExists($document->file_path);
    }
});
