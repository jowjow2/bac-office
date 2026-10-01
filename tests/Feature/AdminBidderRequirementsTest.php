<?php

use App\Mail\BidderIncompleteRequirementsMail;
use App\Mail\BidderRequirementsActionMail;
use App\Models\BidderDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
});

it('shows incomplete requirements and emails the bidder from the review modal', function () {
    $test = testCase();
    Mail::fake();

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin-requirements@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $bidder = User::create([
        'name' => 'Bidder Contact',
        'email' => 'bidder-requirements@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'pending',
        'company' => 'Incomplete Company',
        'registration_no' => 'REG-INCOMPLETE-1',
    ]);

    $bidder->bidderProfile()->create([
        'company_name' => 'Incomplete Company',
        'contact_person' => 'Bidder Contact',
        'contact_number' => '09171234567',
        'business_address' => '123 Test Street',
        'approval_status' => 'pending',
    ]);

    BidderDocument::create([
        'user_id' => $bidder->id,
        'document_type' => 'PhilGEPS Certificate',
        'original_name' => 'philgeps.pdf',
        'file_path' => 'bidder-documents/philgeps.pdf',
        'status' => 'uploaded',
        'uploaded_at' => now(),
    ]);

    $reviewResponse = $test
        ->actingAs($admin)
        ->get(route('admin.users.review', $bidder) . '?modal=1');

    $reviewResponse
        ->assertOk()
        ->assertSee('Incomplete')
        ->assertSee('Valid Business Permit')
        ->assertSee('Approve Bidder');

    $response = $test
        ->actingAs($admin)
        ->postJson(route('admin.users.requirements.incomplete-email', $bidder));

    $response
        ->assertOk()
        ->assertJsonPath('ok', true);

    Mail::assertSent(BidderIncompleteRequirementsMail::class, function (BidderIncompleteRequirementsMail $mail) {
        return $mail->companyName === 'Incomplete Company'
            && in_array('Valid Business Permit', $mail->missingRequirements, true);
    });

    $test->assertDatabaseHas('user_notifications', [
        'user_id' => $bidder->id,
        'type' => 'bidder_requirements_incomplete',
    ]);
});

it('sends a needs-action email with the correct requirements and resubmission link when requesting corrections', function () {
    $test = testCase();
    Mail::fake();

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin-needs-action@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $bidder = User::create([
        'name' => 'Bidder Contact',
        'email' => 'bidder-needs-action@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'pending',
        'company' => 'Needs Action Company',
        'registration_no' => 'REG-NEEDS-ACTION-1',
    ]);

    $bidder->bidderProfile()->create([
        'company_name' => 'Needs Action Company',
        'contact_person' => 'Bidder Contact',
        'contact_number' => '09171234567',
        'business_address' => '123 Test Street',
        'approval_status' => 'pending',
        'review_status' => 'under_review',
    ]);

    BidderDocument::create([
        'user_id' => $bidder->id,
        'document_type' => 'Business Permit',
        'original_name' => 'permit.pdf',
        'file_path' => 'bidder-documents/permit.pdf',
        'status' => 'uploaded',
        'uploaded_at' => now(),
    ]);

    $response = $test
        ->actingAs($admin)
        ->postJson(route('admin.users.requirements', $bidder), [
            'document_types' => ['Business Permit'],
            'reason' => 'The business permit on file has expired. Please upload a current copy.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('mail_sent', true);

    $resubmissionUrl = route('bidder.company-profile');

    Mail::assertSent(BidderRequirementsActionMail::class, function (BidderRequirementsActionMail $mail) use ($bidder, $resubmissionUrl, $admin) {
        return $mail->hasTo($bidder->email)
            && $mail->companyName === 'Needs Action Company'
            && in_array('Valid Business Permit', $mail->requirements, true)
            && $mail->reason === 'The business permit on file has expired. Please upload a current copy.'
            && $mail->resubmissionUrl === $resubmissionUrl
            && $mail->adminReplyToEmail === $admin->email;
    });

    $bidder->refresh();
    $bidder->bidderProfile()->first();

    $test->assertDatabaseHas('bidders', [
        'user_id' => $bidder->id,
        'review_status' => 'needs_action',
    ]);

    $test->assertDatabaseHas('bidder_requirement_requests', [
        'document_type' => 'Business Permit',
        'status' => 'open',
    ]);

    $test->assertDatabaseHas('audit_logs', [
        'action' => 'bidder_requirements_notification_sent',
    ]);
});

it('does not send a second email when the same requirements request is resubmitted unchanged', function () {
    $test = testCase();
    Mail::fake();

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin-dedupe@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $bidder = User::create([
        'name' => 'Bidder Contact',
        'email' => 'bidder-dedupe@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'pending',
        'company' => 'Dedupe Company',
        'registration_no' => 'REG-DEDUPE-1',
    ]);

    $bidder->bidderProfile()->create([
        'company_name' => 'Dedupe Company',
        'contact_person' => 'Bidder Contact',
        'contact_number' => '09171234567',
        'business_address' => '123 Test Street',
        'approval_status' => 'pending',
        'review_status' => 'under_review',
    ]);

    $payload = [
        'document_types' => ['Business Permit'],
        'reason' => 'Please upload a valid, unexpired business permit.',
    ];

    $test->actingAs($admin)->postJson(route('admin.users.requirements', $bidder), $payload)->assertOk();

    // Simulate a double submission (e.g. double-clicking the submit button) with the identical payload.
    $test->actingAs($admin)->postJson(route('admin.users.requirements', $bidder), $payload)->assertOk();

    Mail::assertSent(BidderRequirementsActionMail::class, 1);
});
