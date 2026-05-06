<?php

use App\Mail\LoginVerificationCodeMail;
use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
});

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
    $response->assertJsonPath('errors.email.0', 'Email is required.');
    $response->assertJsonPath('errors.password.0', 'Password is required.');
    $response->assertJsonPath('errors.registration_no.0', 'Registration number is required.');
});

it('returns a validation error when email format is invalid', function () {
    $response = testCase()->postJson('/register', [
        'company' => 'Acme',
        'email' => 'not-an-email',
        'password' => 'password123',
        'registration_no' => 'REG123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('ok', false);
    $response->assertJsonPath('errors.email.0', 'Please enter a valid email address.');
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

    $response = $test->postJson('/register', [
        'company' => 'Acme Ltd',
        'email' => ' NEW@EXAMPLE.COM ',
        'password' => 'password123',
        'registration_no' => 'REG123',
    ]);

    $response->assertOk();
    $response->assertJsonPath('ok', true);
    $response->assertJsonPath('tab', 'register');
    $response->assertJsonPath('message', 'Registered successfully! Your account is pending admin approval.');

    $test->assertDatabaseHas('users', [
        'name' => 'Acme Ltd',
        'company' => 'Acme Ltd',
        'email' => 'new@example.com',
        'registration_no' => 'REG123',
        'role' => 'bidder',
        'status' => 'pending',
    ]);

    $user = User::where('email', 'new@example.com')->firstOrFail();

    expect(Hash::check('password123', $user->password))->toBeTrue();

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
        'password' => 'password123',
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

    expect(Hash::check('password123', $user->password))->toBeTrue();

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

it('requires an office selection when staff register', function () {
    $test = testCase();

    $response = $test->postJson('/register', [
        'role' => 'staff',
        'name' => 'Staff Without Office',
        'email' => 'no.office.public.staff@example.com',
        'password' => 'password123',
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
