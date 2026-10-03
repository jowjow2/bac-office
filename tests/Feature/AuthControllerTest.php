<?php

use App\Mail\LoginVerificationCodeMail;
use App\Mail\PasswordResetCodeMail;
use App\Mail\BidderIncompleteRequirementsMail;
use App\Models\User;
use App\Support\BidderRegistrationRequirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');
});

function validBidderRegistrationPayload(array $overrides = []): array
{
    $documents = [];

    foreach (BidderRegistrationRequirements::requiredDocumentKeys() as $key) {
        $documents[$key] = UploadedFile::fake()->create($key . '.pdf', 64, 'application/pdf');
    }

    return array_replace_recursive([
        'role' => 'bidder',
        'company' => 'Acme Ltd',
        'contact_person' => 'Ada Buyer',
        'contact_number' => '09171234567',
        'business_address' => '123 Procurement Avenue, San Jose',
        'email' => ' NEW@EXAMPLE.COM ',
        'password' => 'SecurePass1!',
        'registration_no' => 'REG123',
        'registration_documents' => $documents,
    ], $overrides);
}

it('returns the home view for the landing page', function () {
    $response = testCase()->get('/');

    $response->assertOk();
    $response->assertViewIs('pages.home');
    $response->assertSee('id="registerForm"', false);
    $response->assertSee('Sign In');
});

it('redirects the standalone login route back to the landing page modal', function () {
    $response = testCase()->get(route('login.page'));

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('auth_tab', 'login');
});

it('sends a verification code before allowing bidder login', function () {
    $test = testCase();
    Mail::fake();

    $user = User::create([
        'name' => 'Bidder User',
        'email' => 'user@example.com',
        'password' => Hash::make('secret123'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Example Company',
        'registration_no' => 'REG-1001',
    ]);

    $response = $test->postJson('/login', [
        'email' => ' USER@EXAMPLE.COM ',
        'password' => 'secret123',
    ]);

    $response->assertOk();
    $response->assertJsonPath('ok', true);
    $response->assertJsonPath('tab', 'verify');
    $response->assertJsonPath('requires_verification', true);
    $response->assertJsonPath('email', 'user@example.com');

    $test->assertGuest();

    $code = null;
    Mail::assertSent(LoginVerificationCodeMail::class, function (LoginVerificationCodeMail $mail) use ($user, &$code) {
        $code = $mail->code;

        return $mail->user->is($user);
    });

    $verifyResponse = $test->postJson('/login/verify-code', [
        'code' => $code,
    ]);

    $verifyResponse->assertOk();
    $verifyResponse->assertJsonPath('ok', true);
    $verifyResponse->assertJsonPath('message', 'Login successful.');
    $verifyResponse->assertJsonPath('redirect', route('bidder.dashboard'));

    $test->assertAuthenticatedAs($user);
});

it('returns an error when the password is incorrect', function () {
    $test = testCase();

    User::create([
        'name' => 'Bidder User',
        'email' => 'user@example.com',
        'password' => Hash::make('secret123'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Example Company',
        'registration_no' => 'REG-1001',
    ]);

    $response = $test->postJson('/login', [
        'email' => 'user@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('ok', false);
    $response->assertJsonPath('tab', 'login');
    $response->assertJsonPath('message', 'Account exists, but the password is incorrect.');
    $response->assertJsonPath('errors.password.0', 'Password is incorrect.');

    $test->assertGuest();
});

it('shows the auth modal on the landing page instead of a standalone register route', function () {
    $response = testCase()->get('/');

    $response->assertOk();
    $response->assertSee('id="authModal"', false);
    $response->assertSee('Register');
    $response->assertSee('action="' . route('register') . '"', false);
});

it('validates registration input and returns errors when fields are missing', function () {
    $response = testCase()->postJson('/register', []);

    $response->assertStatus(422);
    $response->assertJsonPath('ok', false);
    $response->assertJsonPath('tab', 'register');
    $response->assertJsonPath('errors.company.0', 'Company is required.');
    $response->assertJsonPath('errors.contact_person.0', 'Contact person is required.');
    $response->assertJsonPath('errors.contact_number.0', 'Contact number is required.');
    $response->assertJsonPath('errors.business_address.0', 'Business address is required.');
    $response->assertJsonPath('errors.email.0', 'Email is required.');
    $response->assertJsonPath('errors.password.0', 'Password is required.');
    $response->assertJsonPath('errors.registration_no.0', 'Registration number is required.');
    $response->assertJsonFragment(['Valid Business Permit must be uploaded.']);
});

it('emails a bidder when required registration documents are missing', function () {
    $test = testCase();
    Mail::fake();

    $payload = validBidderRegistrationPayload();
    unset(
        $payload['registration_documents']['business_permit'],
        $payload['registration_documents']['philgeps_registration']
    );

    $response = $test->postJson('/register', $payload);

    $response->assertStatus(422);
    $response->assertJsonFragment(['Valid Business Permit must be uploaded.']);

    Mail::assertSent(BidderIncompleteRequirementsMail::class, function (BidderIncompleteRequirementsMail $mail) {
        return $mail->bidderName === 'Ada Buyer'
            && $mail->companyName === 'Acme Ltd'
            && in_array('Valid Business Permit', $mail->missingRequirements, true)
            && in_array('PhilGEPS Registration', $mail->missingRequirements, true);
    });

    $test->assertDatabaseMissing('users', [
        'email' => 'new@example.com',
    ]);
});

it('returns a validation error when email format is invalid', function () {
    $response = testCase()->postJson('/register', [
        'company' => 'Acme',
        'email' => 'not-an-email',
        'password' => 'SecurePass1!',
        'registration_no' => 'REG123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('ok', false);
    $response->assertJsonPath('errors.email.0', 'Please enter a valid email address.');
});

it('requires a strong password when registering', function () {
    $response = testCase()->postJson('/register', validBidderRegistrationPayload([
        'password' => 'password123',
    ]));

    $response->assertStatus(422);
    $response->assertJsonPath('ok', false);
    $response->assertJsonPath('tab', 'register');
    $response->assertJsonPath('errors.password.0', 'Password must include uppercase and lowercase letters.');
    $response->assertJsonFragment(['Password must include at least one special character.']);
});

it('creates a new user with a hashed password and notifies admins when registration is valid', function () {
    $test = testCase();

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $response = $test->postJson('/register', validBidderRegistrationPayload());

    $response->assertOk();
    $response->assertJsonPath('ok', true);
    $response->assertJsonPath('tab', 'register');
    $response->assertJsonPath('message', 'Registered successfully! Your account is pending admin approval.');

    $test->assertDatabaseHas('users', [
        'name' => 'Ada Buyer',
        'company' => 'Acme Ltd',
        'email' => 'new@example.com',
        'registration_no' => 'REG123',
        'role' => 'bidder',
        'status' => 'pending',
    ]);

    $user = User::where('email', 'new@example.com')->firstOrFail();

    expect(Hash::check('SecurePass1!', $user->password))->toBeTrue();

    $test->assertDatabaseHas('bidders', [
        'user_id' => $user->id,
        'company_name' => 'Acme Ltd',
        'contact_person' => 'Ada Buyer',
        'contact_number' => '09171234567',
        'approval_status' => 'pending',
    ]);

    foreach (BidderRegistrationRequirements::requiredDocumentKeys() as $key) {
        $test->assertDatabaseHas('bidder_documents', [
            'user_id' => $user->id,
            'document_type' => BidderRegistrationRequirements::documents()[$key]['document_type'],
        ]);
    }

    $test->assertDatabaseHas('user_notifications', [
        'user_id' => $admin->id,
        'title' => 'New bidder registration',
        'type' => 'bidder_registration',
    ]);
});

it('creates a pending staff account with an office selection', function () {
    $test = testCase();

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin-staff-registration@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $response = $test->postJson('/register', [
        'role' => 'staff',
        'name' => 'Procurement Staff',
        'email' => ' STAFF@EXAMPLE.COM ',
        'password' => 'SecurePass1!',
        'office' => 'Procurement Office',
    ]);

    $response->assertOk();
    $response->assertJsonPath('ok', true);
    $response->assertJsonPath('tab', 'register');

    $test->assertDatabaseHas('users', [
        'name' => 'Procurement Staff',
        'email' => 'staff@example.com',
        'role' => 'staff',
        'status' => 'pending',
        'office' => 'Procurement Office',
    ]);

    $user = User::where('email', 'staff@example.com')->firstOrFail();

    expect(Hash::check('SecurePass1!', $user->password))->toBeTrue();

    $test->assertDatabaseHas('user_notifications', [
        'user_id' => $admin->id,
        'title' => 'New staff registration',
        'type' => 'staff_registration',
    ]);
});

it('sends a password reset code before allowing a new password', function () {
    $test = testCase();
    Mail::fake();

    $user = User::create([
        'name' => 'Reset Bidder',
        'email' => 'reset@example.com',
        'password' => Hash::make('old-password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Reset Company',
        'registration_no' => 'REG-RESET',
    ]);

    $response = $test->postJson('/forgot-password', [
        'email' => ' RESET@EXAMPLE.COM ',
    ]);

    $response->assertOk();
    $response->assertJsonPath('ok', true);
    $response->assertJsonPath('tab', 'forgot_verify');
    $response->assertJsonPath('requires_password_code', true);
    $response->assertJsonPath('password_code_expires_in', 180);

    $code = null;
    Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use ($user, &$code) {
        $code = $mail->code;

        return $mail->user->is($user);
    });

    $verifyResponse = $test->postJson('/forgot-password/verify-code', [
        'email' => 'reset@example.com',
        'code' => $code,
    ]);

    $verifyResponse->assertOk();
    $verifyResponse->assertJsonPath('ok', true);
    $verifyResponse->assertJsonPath('tab', 'reset_password');
    $verifyResponse->assertJsonPath('redirect', null);
    $verifyResponse->assertJsonPath('password_reset_verified', true);
    $verifyResponse->assertJsonPath('email', 'reset@example.com');

    $resetResponse = $test->postJson('/reset-password', [
        'email' => 'reset@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $resetResponse->assertOk();
    $resetResponse->assertJsonPath('ok', true);
    $resetResponse->assertJsonPath('tab', 'login');

    $user->refresh();
    expect(Hash::check('new-password', $user->password))->toBeTrue();
});

it('expires password reset codes after three minutes', function () {
    $test = testCase();

    User::create([
        'name' => 'Expired Reset Bidder',
        'email' => 'expired.reset@example.com',
        'password' => Hash::make('old-password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Expired Reset Company',
        'registration_no' => 'REG-EXPIRED-RESET',
    ]);

    DB::table('password_reset_tokens')->insert([
        'email' => 'expired.reset@example.com',
        'token' => Hash::make('123456'),
        'created_at' => now()->subSeconds(181),
    ]);

    $response = $test->postJson('/forgot-password/verify-code', [
        'email' => 'expired.reset@example.com',
        'code' => '123456',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('ok', false);
    $response->assertJsonPath('tab', 'forgot_verify');
    $response->assertJsonPath('password_code_expired', true);
    $response->assertJsonPath('errors.code.0', 'Verification code expired. Please request a new code.');

    $test->assertDatabaseMissing('password_reset_tokens', [
        'email' => 'expired.reset@example.com',
    ]);
});

it('does not expose mail server errors when a password reset code cannot be sent', function () {
    $test = testCase();

    User::create([
        'name' => 'Reset Bidder',
        'email' => 'reset@example.com',
        'password' => Hash::make('old-password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Reset Company',
        'registration_no' => 'REG-RESET',
    ]);

    Mail::shouldReceive('to')
        ->once()
        ->with('reset@example.com')
        ->andThrow(new RuntimeException('Failed to authenticate on SMTP server with username "reset@example.com".'));

    $response = $test->postJson('/forgot-password', [
        'email' => 'reset@example.com',
    ]);

    $response->assertStatus(503);
    $response->assertJsonPath('ok', false);
    $response->assertJsonPath('tab', 'forgot');
    $response->assertJsonPath('message', 'We could not send the password reset code right now. Please try again later or contact the SJBAC admin.');
    $response->assertJsonPath('errors', []);

    expect($response->getContent())
        ->not->toContain('SMTP')
        ->not->toContain('reset@example.com');

    $test->assertDatabaseMissing('password_reset_tokens', [
        'email' => 'reset@example.com',
    ]);
});

it('allows a local password reset test code when mail cannot be sent', function () {
    $test = testCase();
    config()->set('app.env', 'local');

    User::create([
        'name' => 'Local Reset Bidder',
        'email' => 'local.reset@example.com',
        'password' => Hash::make('old-password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Local Reset Company',
        'registration_no' => 'REG-LOCAL-RESET',
    ]);

    Mail::shouldReceive('to')
        ->once()
        ->with('local.reset@example.com')
        ->andThrow(new RuntimeException('Failed to authenticate on SMTP server.'));

    $response = $test->postJson('/forgot-password', [
        'email' => 'local.reset@example.com',
    ]);

    $response->assertOk();
    $response->assertJsonPath('ok', true);
    $response->assertJsonPath('tab', 'forgot_verify');
    $response->assertJsonPath('requires_password_code', true);
    $response->assertJsonPath('password_code_expires_in', 180);

    $code = $response->json('dev_password_reset_code');
    expect($code)->toMatch('/^\d{6}$/');

    $verifyResponse = $test->postJson('/forgot-password/verify-code', [
        'email' => 'local.reset@example.com',
        'code' => $code,
    ]);

    $verifyResponse->assertOk();
    $verifyResponse->assertJsonPath('ok', true);
    $verifyResponse->assertJsonPath('tab', 'reset_password');
});

it('requires an office selection when staff register', function () {
    $test = testCase();

    $response = $test->postJson('/register', [
        'role' => 'staff',
        'name' => 'Staff Without Office',
        'email' => 'no.office.public.staff@example.com',
        'password' => 'SecurePass1!',
        'office' => '',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('ok', false);
    $response->assertJsonPath('tab', 'register');
    $response->assertJsonPath('errors.office.0', 'Office is required for staff registration.');

    $test->assertDatabaseMissing('users', [
        'email' => 'no.office.public.staff@example.com',
    ]);
});

it('treats an active bidder as allowed when the bidders table is unavailable', function () {
    Schema::dropIfExists('bidders');

    $user = User::create([
        'name' => 'Legacy Bidder',
        'email' => 'legacy@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Legacy Company',
        'registration_no' => 'REG-LEGACY-1',
    ]);

    expect($user->isApprovedBidder())->toBeTrue();
});

it('logs out the user and redirects to the landing page', function () {
    $test = testCase();

    $user = User::create([
        'name' => 'Bidder User',
        'email' => 'logout@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Logout Company',
        'registration_no' => 'REG-2001',
    ]);

    $response = $test
        ->actingAs($user)
        ->post('/logout');

    $response->assertRedirect(route('home'));
    $test->assertGuest();
});

it('shows the actual password reset code lifetime in the email', function () {
    $user = new User(['name' => 'Reset Bidder', 'email' => 'reset@example.com']);
    $mail = new PasswordResetCodeMail($user, '123456', 180);

    $mail->assertSeeInHtml('This code expires in 3 minutes.');
    $mail->assertDontSeeInHtml('10 minutes');
});

it('answers clearly instead of a server error when the verification code cannot be emailed', function () {
    // The real mailer, pointed at a server that refuses the connection.
    config()->set('mail.default', 'smtp');
    config()->set('mail.mailers.smtp.host', '127.0.0.1');
    config()->set('mail.mailers.smtp.port', 1);
    config()->set('mail.mailers.smtp.timeout', 2);

    User::create([
        'name' => 'Bidder User', 'email' => 'mailfail@example.com', 'password' => Hash::make('secret123'),
        'role' => 'bidder', 'status' => 'active', 'company' => 'Example Company', 'registration_no' => 'REG-1002',
    ]);

    testCase()->postJson('/login', ['email' => 'mailfail@example.com', 'password' => 'secret123'])
        ->assertStatus(503)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('message', 'We could not send your verification code right now. Please try again in a few minutes, or contact the BAC Secretariat.');

    testCase()->assertGuest();
    expect(session()->has('bidder_login_verification'))->toBeFalse();
});
