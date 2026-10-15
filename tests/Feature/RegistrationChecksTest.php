<?php

use App\Mail\BidderEmailVerificationMail;
use App\Models\AuditLog;
use App\Models\Bidder;
use App\Models\BidderDocument;
use App\Models\User;
use App\Support\BidderRegistrationRequirements;
use App\Support\RegistrationChecks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::create(['name' => 'BAC Admin', 'email' => 'checks-admin@example.org', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->payload = function (array $overrides = []) {
        $documents = [];
        foreach (BidderRegistrationRequirements::requiredDocumentKeys() as $key) {
            $documents[$key] = UploadedFile::fake()->createWithContent($key.'.pdf', '%PDF-1.4 '.$key.' '.uniqid());
        }

        return array_replace_recursive([
            'role' => 'bidder', 'company' => 'Checked Builders', 'contact_person' => 'Cora Check', 'contact_number' => '09175550001',
            'business_address' => '1 Rizal St, San Jose', 'email' => 'cora@checked-builders.example', 'password' => 'SecurePass1!',
            'registration_no' => 'DTI-CHECK-1', 'certify_documents' => '1', 'registration_documents' => $documents,
        ], $overrides);
    };
    $this->approvedBidder = function (array $user, array $profile, bool $confirmed = true) {
        $account = User::create($user + ['password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active']);
        $account->forceFill(['email_verified_at' => $confirmed ? now() : null])->save();
        Bidder::create($profile + ['user_id' => $account->id, 'approval_status' => 'approved']);

        return $account;
    };
});

it('needs the certification that the documents are authentic, and keeps it on record', function () {
    Mail::fake();

    testCase()->postJson('/register', ($this->payload)(['certify_documents' => null]))->assertStatus(422)
        ->assertJsonPath('errors.certify_documents.0', 'Please confirm that the information and documents you submit are authentic.');
    expect(User::where('email', 'cora@checked-builders.example')->exists())->toBeFalse();

    testCase()->postJson('/register', ($this->payload)())->assertOk()->assertJsonPath('ok', true);
    $user = User::where('email', 'cora@checked-builders.example')->firstOrFail();
    $log = AuditLog::where('action', 'bidder_registered')->where('auditable_id', $user->bidderProfile->id)->firstOrFail();
    expect($log->new_values['certified_documents_authentic'])->toBeTrue()->and($log->ip_address)->not->toBeNull();
});

it('emails a confirmation link; opening it confirms the address, and the BAC cannot approve before that', function () {
    Mail::fake();
    testCase()->postJson('/register', ($this->payload)())->assertOk()
        ->assertJsonPath('message', 'Registered successfully! We sent a link to your email: open it to confirm your address, then your account waits for admin approval.');

    $user = User::where('email', 'cora@checked-builders.example')->firstOrFail();
    expect($user->email_verified_at)->toBeNull()->and(RegistrationChecks::emailUnconfirmed($user))->toBeTrue();
    Mail::assertSent(BidderEmailVerificationMail::class, fn ($mail) => $mail->hasTo('cora@checked-builders.example'));

    // Not approvable while unconfirmed; the review says why.
    testCase()->actingAs($this->admin)->patchJson(route('admin.users.approve', $user))->assertStatus(422);
    expect($user->fresh()->status)->toBe('pending');
    testCase()->actingAs($this->admin)->get(route('admin.users.review', $user).'?modal=1')->assertOk()
        ->assertSee('Email address not confirmed yet')->assertSee('Resend confirmation email');

    $link = (new BidderEmailVerificationMail($user))->link;
    testCase()->get(str_replace('http://localhost', '', $link))->assertOk()->assertSee('Email confirmed');
    expect($user->fresh()->email_verified_at)->not->toBeNull();
    expect(AuditLog::where('action', 'bidder_email_confirmed')->count())->toBe(1);

    // A tampered link is refused.
    testCase()->get(str_replace('http://localhost', '', $link).'x')->assertForbidden();
});

it('lets the admin resend the confirmation email', function () {
    Mail::fake();
    testCase()->postJson('/register', ($this->payload)());
    $user = User::where('email', 'cora@checked-builders.example')->firstOrFail();
    Mail::assertSent(BidderEmailVerificationMail::class, 1);

    testCase()->actingAs($this->admin)->post(route('admin.users.resend-confirmation', $user))->assertRedirect();
    Mail::assertSent(BidderEmailVerificationMail::class, 2);
});

it('flags a registration that shares a number, company, address, domain or file with another bidder', function () {
    Mail::fake();
    $first = ($this->approvedBidder)(['name' => 'Ana Rivera', 'email' => 'ana@sharedco.example', 'company' => 'Checked Builders', 'registration_no' => 'DTI-OTHER-9'],
        ['company_name' => 'Checked Builders', 'contact_person' => 'Ana Rivera', 'contact_number' => '0917 555 0001', 'business_address' => '1 Rizal St., San Jose']);

    // The same file under both accounts: the fingerprint is part of the stored name.
    $fingerprint = str_repeat('ab12', 5);
    BidderDocument::create(['user_id' => $first->id, 'document_type' => 'Valid Business Permit', 'original_name' => 'permit.pdf', 'file_path' => 'bidder-registration-documents/'.$first->id.'/registration_'.$first->id.'_business_permit_'.$fingerprint.'_AbCdEfGhIjKl.pdf', 'status' => 'uploaded', 'uploaded_at' => now()]);

    testCase()->postJson('/register', ($this->payload)(['email' => 'cora@sharedco.example']))->assertOk();
    $user = User::where('email', 'cora@sharedco.example')->firstOrFail();
    BidderDocument::create(['user_id' => $user->id, 'document_type' => 'Valid Business Permit', 'original_name' => 'copy.pdf', 'file_path' => 'bidder-registration-documents/'.$user->id.'/registration_'.$user->id.'_business_permit_'.$fingerprint.'_ZyXwVuTsRqPo.pdf', 'status' => 'uploaded', 'uploaded_at' => now()]);

    $whats = collect(RegistrationChecks::sharedWithOthers($user->fresh('bidderProfile')))->pluck('what')->all();
    expect($whats)->toContain('the same contact number', 'the same company name', 'the same business address', 'an email on the same domain (@sharedco.example)', 'the same document file uploaded');

    testCase()->actingAs($this->admin)->get(route('admin.users.review', $user).'?modal=1')->assertOk()
        ->assertSee('Shares details with other bidder accounts')->assertSee('(Active)', false)->assertSee(route('admin.users.review', $first->id), false);
});

it('says so when nothing is shared', function () {
    Mail::fake();
    testCase()->postJson('/register', ($this->payload)())->assertOk();
    $user = User::where('email', 'cora@checked-builders.example')->firstOrFail();
    expect(RegistrationChecks::sharedWithOthers($user))->toBe([]);
    testCase()->actingAs($this->admin)->get(route('admin.users.review', $user).'?modal=1')->assertOk()->assertSee('No other bidder account has the same');
});

it('does not ask accounts registered before the rule started to confirm their email', function () {
    $old = ($this->approvedBidder)(['name' => 'Old Bidder', 'email' => 'old@bidder.example', 'company' => 'Old Co', 'registration_no' => 'OLD-1'], ['company_name' => 'Old Co', 'contact_person' => 'Old', 'contact_number' => '09170000009', 'business_address' => 'x'], false);
    $old->forceFill(['created_at' => '2026-09-01 08:00:00'])->save();
    expect(RegistrationChecks::emailUnconfirmed($old->fresh()))->toBeFalse();
});

it('lets a bidder register with the email of a retired end-user office account', function () {
    Mail::fake();
    $retired = User::create(['name' => 'Old Office', 'email' => 'cora@checked-builders.example', 'password' => Hash::make('password'), 'role' => 'end_user', 'status' => 'active']);

    testCase()->postJson('/register', ($this->payload)())->assertOk()->assertJsonPath('ok', true);

    expect(User::where('email', 'cora@checked-builders.example')->first()->role)->toBe('bidder')
        ->and($retired->fresh()->email)->toBe('retired-'.$retired->id.'-cora@checked-builders.example');

    // A real account with that email still blocks a second registration.
    testCase()->postJson('/register', ($this->payload)(['registration_no' => 'DTI-OTHER-2']))->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'An account with this email already exists.');
});
