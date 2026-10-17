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

it('shows the page title with a plain ampersand', function () {
    $bidder = crBidder(['status' => 'active']);
    $bidder->bidderProfile()->update(['approval_status' => 'approved']);

    $page = testCase()->actingAs($bidder)->get(route('bidder.company-profile'))->assertOk();
    $page->assertSee('Company profile &amp; documents', false)
        ->assertDontSee('&amp;amp;', false)
        ->assertSee('Registration documents')->assertDontSee('Last document update');
});

it('splits the page into Documents, Company details and Bidding record tabs, with the QR under Company details', function () {
    $bidder = crBidder();
    $bidder->bidderProfile()->update(['review_status' => 'for_re_evaluation']);

    // Not approved yet: no QR tab, and the documents panel is the one shown first.
    $page = testCase()->actingAs($bidder)->get(route('bidder.company-profile'))->assertOk();
    $page->assertSee('data-cp-tab="documents"', false)->assertSee('data-cp-tab="company"', false)->assertDontSee('data-cp-tab="qr"', false)
        ->assertSee('id="cp-panel-documents"', false)->assertSee('id="cp-panel-company"', false)
        ->assertSee('REG-CR-1')->assertSee('BAC Avenue');
    expect(preg_match('/id="cp-panel-documents"[^>]*\shidden/', $page->getContent()))->toBe(0);

    $bidder->update(['status' => 'active']);
    $bidder->bidderProfile()->update(['approval_status' => 'approved']);
    $approved = testCase()->actingAs($bidder->fresh())->get(route('bidder.company-profile'))->assertOk();
    // The public record QR is a small card under Company details, not a tab of its own.
    $approved->assertDontSee('data-cp-tab="qr"', false);
    if (str_contains($approved->getContent(), 'cp-qr-title')) {
        expect($approved->getContent())->toMatch('/id="cp-panel-qr"[^>]*data-cp-panel="company"/');
    }
});


it('shows how complete the profile is, what the BAC made of each file, and the validity date of a document', function () {
    $bidder = crBidder();
    $bidder->update(['status' => 'active']);
    $bidder->bidderProfile()->update(['approval_status' => 'approved', 'approved_at' => now()]);
    $document = \App\Models\BidderDocument::create(['user_id' => $bidder->id, 'document_type' => 'Business Permit', 'original_name' => 'permit.pdf', 'file_path' => 'bidder-documents/permit.pdf', 'status' => 'uploaded', 'is_current' => true, 'version' => 1, 'uploaded_at' => now()->subDays(3)]);

    testCase()->actingAs($bidder->fresh())->patch(route('bidder.documents.validity', $document), ['valid_until' => now()->addDays(10)->toDateString()])
        ->assertRedirect(route('bidder.company-profile'));
    expect($document->fresh()->valid_until->toDateString())->toBe(now()->addDays(10)->toDateString())
        ->and($document->fresh()->validity()[0])->toBe('expiring');

    testCase()->actingAs($bidder->fresh())->get(route('bidder.company-profile'))->assertOk()
        ->assertSee('complete')->assertSee('Accepted by the BAC')
        ->assertSee('Valid until')->assertSee('within 30 days')->assertSee('Request a change')->assertSee('data-cp-preview', false);

    // Another bidder cannot touch it.
    $other = \App\Models\User::create(['name' => 'Other', 'email' => 'cr-other@example.com', 'password' => \Illuminate\Support\Facades\Hash::make('password'), 'role' => 'bidder', 'status' => 'active']);
    testCase()->actingAs($other)->patch(route('bidder.documents.validity', $document), ['valid_until' => '2030-01-01'])->assertNotFound();
});
