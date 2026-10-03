<?php

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;

uses(RefreshDatabase::class);

/*
 * "Continue with Google" signs in the account registered with that Gmail
 * address; it never creates accounts. Google itself is faked here.
 */
beforeEach(function () {
    testCase()->withoutVite();
    config()->set('services.google', ['client_id' => 'test-client.apps.googleusercontent.com', 'client_secret' => 'test-secret', 'redirect' => null]);

    $this->google = function (string $email, bool $verified = true) {
        $user = (new GoogleUser)->setRaw(['email' => $email, 'email_verified' => $verified])->map(['id' => '1234', 'email' => $email, 'name' => 'Google User']);
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    };
});

it('shows the Google button only once the OAuth client is configured', function () {
    testCase()->get('/')->assertOk()->assertSee('Continue with Google')->assertSee('data-google-signin', false);

    config()->set('services.google', ['client_id' => null, 'client_secret' => null, 'redirect' => null]);
    testCase()->get('/')->assertOk()->assertDontSee('Continue with Google');
    testCase()->get(route('auth.google'))->assertNotFound();
});

it('sends the visitor to Google with this site\'s callback', function () {
    $location = testCase()->get(route('auth.google'))->assertRedirect()->headers->get('Location');

    expect($location)->toStartWith('https://accounts.google.com/')
        ->toContain('client_id=test-client.apps.googleusercontent.com')
        ->toContain('redirect_uri='.urlencode(route('auth.google.callback')))
        ->toContain('prompt=select_account');
});

it('signs in an approved bidder without the email code and logs it', function () {
    $bidder = User::create(['name' => 'Juan', 'email' => 'juan.bidder@gmail.com', 'password' => Hash::make('Secret-pass-1'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Juan Builders']);
    $bidder->bidderProfile()->create(['company_name' => 'Juan Builders', 'contact_person' => 'Juan', 'contact_number' => '09171234567', 'business_address' => 'San Jose', 'approval_status' => 'approved']);
    ($this->google)('Juan.Bidder@gmail.com');

    testCase()->get(route('auth.google.callback', ['code' => 'x', 'state' => 'y']))->assertRedirect(route('bidder.dashboard'));

    testCase()->assertAuthenticatedAs($bidder);
    expect(LoginLog::where('user_id', $bidder->id)->where('login_method', 'google')->where('status', 'success')->exists())->toBeTrue();
});

it('signs in BAC accounts the same way', function () {
    $admin = User::create(['name' => 'BAC Chair', 'email' => 'chair@gmail.com', 'password' => Hash::make('Secret-pass-1'), 'role' => 'admin', 'status' => 'active']);
    ($this->google)('chair@gmail.com');

    testCase()->get(route('auth.google.callback', ['code' => 'x']))->assertRedirect(route('admin.dashboard'));
    testCase()->assertAuthenticatedAs($admin);
});

it('never creates an account for an unknown Gmail address', function () {
    ($this->google)('stranger@gmail.com');

    testCase()->get(route('auth.google.callback', ['code' => 'x']))
        ->assertRedirect(route('home'))
        ->assertSessionHas('error', fn ($message) => str_contains($message, 'No SJBAC account uses stranger@gmail.com'))
        ->assertSessionHas('auth_tab', 'register');

    testCase()->assertGuest();
    expect(User::where('email', 'stranger@gmail.com')->exists())->toBeFalse();
});

it('refuses rejected or inactive accounts and unverified Google emails', function () {
    User::create(['name' => 'Rejected', 'email' => 'rejected@gmail.com', 'password' => Hash::make('Secret-pass-1'), 'role' => 'bidder', 'status' => 'rejected']);
    User::create(['name' => 'Inactive staff', 'email' => 'staff@gmail.com', 'password' => Hash::make('Secret-pass-1'), 'role' => 'staff', 'status' => 'pending']);

    ($this->google)('rejected@gmail.com');
    testCase()->get(route('auth.google.callback', ['code' => 'x']))->assertRedirect(route('home'))->assertSessionHas('error');
    testCase()->assertGuest();

    ($this->google)('staff@gmail.com');
    testCase()->get(route('auth.google.callback', ['code' => 'x']))->assertRedirect(route('home'))->assertSessionHas('error');
    testCase()->assertGuest();

    ($this->google)('rejected@gmail.com', false);
    testCase()->get(route('auth.google.callback', ['code' => 'x']))
        ->assertSessionHas('error', fn ($message) => str_contains($message, 'not verified'));
    testCase()->assertGuest();
});

it('handles a cancelled Google sign-in', function () {
    testCase()->get(route('auth.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('home'))
        ->assertSessionHas('error', 'Google sign-in was cancelled.');
});
