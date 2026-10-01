<?php

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

it('allows Needs Action bidders to replace only explicitly requested documents', function () {
    $bidder = User::create([
        'name' => 'Restricted Bidder',
        'email' => 'restricted-bidder@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'pending',
        'company' => 'Restricted Builders',
        'registration_no' => 'REG-RESTRICTED-1',
    ]);

    $profile = $bidder->bidderProfile()->create([
        'company_name' => $bidder->company,
        'contact_person' => $bidder->name,
        'contact_number' => '09171234567',
        'business_address' => 'BAC Avenue',
        'approval_status' => 'pending',
        'review_status' => 'needs_action',
        'review_message' => 'Please replace the permit.',
    ]);

    $profile->requirementRequests()->create([
        'document_type' => 'Business Permit',
        'reason' => 'The permit is expired.',
        'status' => 'open',
        'requested_at' => now(),
    ]);

    testCase()->actingAs($bidder)->post(route('bidder.documents.store'), [
        'document_type' => 'PCAB License',
        'document_file' => UploadedFile::fake()->create('pcab.pdf', 64, 'application/pdf'),
    ])->assertRedirect(route('bidder.company-profile'))
        ->assertSessionHasErrors('document_type');

    testCase()->assertDatabaseCount('bidder_documents', 0);
});
