<?php

use App\Models\BidderDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
});

function crBidder(array $overrides = []): User
{
    $user = User::create(array_replace([
        'name' => 'CR Bidder',
        'email' => 'cr-bidder@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'pending',
        'company' => 'CR Builders',
        'registration_no' => 'REG-CR-1',
    ], $overrides));

    $user->bidderProfile()->create([
        'company_name' => $user->company,
        'contact_person' => $user->name,
        'contact_number' => '09171234567',
        'business_address' => 'BAC Avenue',
        'approval_status' => 'pending',
        'review_status' => 'new',
    ]);

    return $user;
}

function crAdmin(): User
{
    return User::create([
        'name' => 'CR Admin',
        'email' => 'cr-admin@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);
}

it('shows Action Required with a disabled submit button until the flagged document is replaced', function () {
    $admin = crAdmin();
    $bidder = crBidder();
    $bidder->bidderProfile()->update(['review_status' => 'needs_action', 'review_message' => 'Please replace the expired permit.']);
    $requirementRequest = $bidder->bidderProfile->requirementRequests()->create([
        'document_type' => 'Business Permit',
        'reason' => 'Permit expired.',
        'status' => 'open',
        'requested_by' => $admin->id,
        'requested_at' => now(),
    ]);

    $response = testCase()->actingAs($bidder)->get(route('bidder.company-profile'));
    $response->assertOk();
    $response->assertSee('Action Required');
    $response->assertSee('Your account has limited access');
    $response->assertSee('Permit expired.');
    $response->assertSee('Submit Corrections for Review');
    $response->assertSee('disabled', false);

    // Now upload a newer version after the request was made.
    testCase()->travel(1)->minutes();
    BidderDocument::create([
        'user_id' => $bidder->id,
        'document_type' => 'Business Permit',
        'original_name' => 'permit-new.pdf',
        'file_path' => 'bidder-documents/permit-new.pdf',
        'status' => 'uploaded',
        'is_current' => true,
        'uploaded_at' => now(),
    ]);

    $response2 = testCase()->actingAs($bidder)->get(route('bidder.company-profile'));
    $response2->assertOk();
    $response2->assertSee('Replaced');
    $response2->assertDontSee('Replace every flagged document above');
});

it('shows the approved banner and locks company info editing once active', function () {
    $bidder = crBidder(['status' => 'active']);
    $bidder->bidderProfile()->update(['approval_status' => 'approved']);

    $response = testCase()->actingAs($bidder)->get(route('bidder.company-profile'));
    $response->assertOk();
    $response->assertSee('Approved');
    $response->assertSee('Locked');
    $response->assertDontSee('id="openCompanyEditModal"', false);
});

it('shows the progress tracker with the current stage highlighted', function () {
    $bidder = crBidder();
    $bidder->bidderProfile()->update(['review_status' => 'for_re_evaluation']);

    $response = testCase()->actingAs($bidder)->get(route('bidder.company-profile'));
    $response->assertOk();
    $response->assertSee('Resubmitted');
    $response->assertSee('is-current', false);
});
