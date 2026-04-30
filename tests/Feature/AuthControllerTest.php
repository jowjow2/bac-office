<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
});

it('returns the home view for the landing page', function () {
    $response = testCase()->get('/');

    $response->assertOk();
    $response->assertViewIs('pages.home');
    $response->assertSee('id="registerForm"', false);
});

it('returns a role-based redirect when login credentials are valid', function () {
    $test = testCase();

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
    $response->assertJsonPath('message', 'Login successful.');
    $response->assertJsonPath('redirect', route('bidder.dashboard'));

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

    $response->assertRedirect('/');
    $test->assertGuest();
});
