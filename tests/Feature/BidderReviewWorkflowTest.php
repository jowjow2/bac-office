<?php

use App\Mail\BidderRequirementsActionMail;
use App\Mail\BidderRejectedMail;
use App\Mail\LoginVerificationCodeMail;
use App\Models\BidderDocument;
use App\Models\BidderRequirementRequest;
use App\Models\User;
use App\Support\BidderRegistrationRequirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');
});

function workflowBidder(array $overrides = []): User
{
    $user = User::create(array_replace([
        'name' => 'Workflow Bidder',
        'email' => 'workflow-bidder@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'pending',
        'company' => 'Workflow Builders',
        'registration_no' => 'REG-WORKFLOW-1',
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

function workflowAdmin(): User
{
    return User::create([
        'name' => 'Workflow Admin',
        'email' => 'workflow-admin@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);
}

it('blocks pending-review bidder login and portal access', function () {
    Mail::fake();
    $bidder = workflowBidder();

    $response = testCase()->postJson('/login', [
        'email' => $bidder->email,
        'password' => 'password',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('tab', 'login')
        ->assertJsonPath('message', 'Your bidder registration is pending admin approval.');
    testCase()->assertGuest();
    Mail::assertNotSent(LoginVerificationCodeMail::class);

    testCase()->actingAs($bidder)->get(route('bidder.dashboard'))->assertForbidden();
    testCase()->actingAs($bidder)->get(route('bidder.company-profile'))->assertForbidden();
});

it('allows only the correction portal for Needs Action bidders', function () {
    $bidder = workflowBidder();
    $bidder->bidderProfile()->update([
        'review_status' => 'needs_action',
        'review_message' => 'Please replace the expired business permit.',
    ]);

    expect($bidder->fresh()->canLoginAsBidder())->toBeTrue();

    testCase()->actingAs($bidder)->get(route('bidder.company-profile'))
        ->assertOk()
        ->assertSee('Please replace the expired business permit.');
    testCase()->actingAs($bidder)->get(route('bidder.dashboard'))
        ->assertRedirect(route('bidder.company-profile'));
    testCase()->actingAs($bidder)->get(route('bidder.notifications'))
        ->assertRedirect(route('bidder.company-profile'));
    testCase()->actingAs($bidder)->get(route('bidder.messages'))
        ->assertRedirect(route('bidder.company-profile'));
    testCase()->actingAs($bidder)->get(route('bidder.available-projects'))
        ->assertForbidden();
});

it('keeps For Re-evaluation bidders on restricted portal access', function () {
    Mail::fake();
    $bidder = workflowBidder();
    $bidder->bidderProfile()->update(['review_status' => 'for_re_evaluation']);

    expect($bidder->fresh()->canLoginAsBidder())->toBeTrue();

    testCase()->postJson('/login', [
        'email' => $bidder->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('tab', 'verify');
    Mail::assertSent(LoginVerificationCodeMail::class, 1);

    testCase()->actingAs($bidder)->get(route('bidder.company-profile'))->assertOk();
    testCase()->actingAs($bidder)->get(route('bidder.dashboard'))
        ->assertRedirect(route('bidder.company-profile'));
    testCase()->actingAs($bidder)->get(route('bidder.available-projects'))
        ->assertForbidden();
});

it('requests selected corrections, records the request, and avoids duplicate mail on repeat submission', function () {
    Mail::fake();
    $admin = workflowAdmin();
    $bidder = workflowBidder();
    $payload = [
        'document_types' => ['Business Permit', 'DTI/SEC Registration'],
        'reason' => 'Please upload current, legible copies.',
    ];

    $response = testCase()->actingAs($admin)->postJson(route('admin.users.requirements', $bidder), $payload);
    $response->assertOk()->assertJsonPath('ok', true);

    $bidder->refresh();
    expect($bidder->status)->toBe('pending');
    expect($bidder->bidderProfile->review_status)->toBe('needs_action');
    expect($bidder->canLoginAsBidder())->toBeTrue();
    expect(BidderRequirementRequest::where('bidder_id', $bidder->bidderProfile->id)->count())->toBe(2);
    Mail::assertSent(BidderRequirementsActionMail::class, 1);

    testCase()->actingAs($admin)->postJson(route('admin.users.requirements', $bidder), $payload)->assertOk();
    Mail::assertSent(BidderRequirementsActionMail::class, 1);
});

it('keeps document versions and moves a bidder to re-evaluation after resubmission', function () {
    $bidder = workflowBidder();
    $bidder->bidderProfile()->update([
        'review_status' => 'needs_action',
        'review_message' => 'Expired permit.',
    ]);
    $requester = $bidder->bidderProfile->requirementRequests()->create([
        'document_type' => 'Business Permit',
        'reason' => 'Expired permit.',
        'status' => 'open',
        'requested_by' => workflowAdmin()->id,
        'requested_at' => now(),
    ]);

    testCase()->actingAs($bidder)->post(route('bidder.documents.store'), [
        'document_type' => 'Business Permit',
        'document_file' => UploadedFile::fake()->create('permit-old.pdf', 64, 'application/pdf'),
    ])->assertRedirect(route('bidder.company-profile'));
    $first = BidderDocument::firstOrFail();

    testCase()->actingAs($bidder)->post(route('bidder.documents.store'), [
        'document_type' => 'Business Permit',
        'document_file' => UploadedFile::fake()->create('permit-new.pdf', 64, 'application/pdf'),
    ])->assertRedirect(route('bidder.company-profile'));

    $second = BidderDocument::latest('id')->firstOrFail();
    expect($first->fresh()->is_current)->toBeFalse();
    expect($second->version)->toBe(2);
    expect($second->supersedes_id)->toBe($first->id);

    testCase()->actingAs($bidder)->post(route('bidder.requirements.reevaluate'))->assertRedirect(route('bidder.company-profile'));
    expect($bidder->fresh()->bidderProfile->review_status)->toBe('for_re_evaluation');
    expect($requester->fresh()->status)->toBe('submitted');
});

it('approves a complete bidder once and sends one approval email', function () {
    Mail::fake();
    $admin = workflowAdmin();
    $bidder = workflowBidder();

    foreach (BidderRegistrationRequirements::documents() as $document) {
        BidderDocument::create([
            'user_id' => $bidder->id,
            'document_type' => $document['document_type'],
            'original_name' => $document['label'].'.pdf',
            'file_path' => 'bidder-documents/'.str()->random(8).'.pdf',
            'status' => 'uploaded',
            'uploaded_at' => now(),
        ]);
    }

    $route = route('admin.users.approve', $bidder);
    testCase()->actingAs($admin)->patchJson($route)->assertOk()->assertJsonPath('user.status', 'approved');
    testCase()->actingAs($admin)->patchJson($route)->assertOk();

    $bidder->refresh();
    expect($bidder->status)->toBe('active');
    expect($bidder->bidderProfile->approval_status)->toBe('approved');
    expect($bidder->bidderProfile->approved_by)->toBe($admin->id);
    expect($bidder->canLoginAsBidder())->toBeTrue();
    Mail::assertSent(\App\Mail\WelcomeMail::class, 1);
});

it('rejects a bidder with a reason and disables login', function () {
    Mail::fake();
    $admin = workflowAdmin();
    $bidder = workflowBidder();

    testCase()->actingAs($admin)->patchJson(route('admin.users.reject', $bidder), [
        'rejection_reason' => 'Registration documents could not be verified.',
    ])->assertOk();

    $bidder->refresh();
    expect($bidder->status)->toBe('rejected');
    expect($bidder->bidderProfile->rejection_reason)->toBe('Registration documents could not be verified.');
    expect($bidder->canLoginAsBidder())->toBeFalse();
    Mail::assertSent(BidderRejectedMail::class, 1);
});
