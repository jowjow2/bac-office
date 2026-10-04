<?php

use App\Mail\LoginVerificationCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;

uses(RefreshDatabase::class);

/*
 * End-user office accounts confirm every sign-in with a 6-digit code emailed to
 * them: 5 minutes, single use, kept only as a hash, 5 tries, 60 s between resends.
 */
beforeEach(function () {
    testCase()->withoutVite();
    Mail::fake();
    $this->office = User::create([
        'name' => 'Engineering Office', 'email' => 'engineering@example.com', 'password' => Hash::make('secret123'),
        'role' => 'end_user', 'status' => 'active', 'office' => 'Municipal Engineering Office',
    ]);
    $this->codes = function (): array {
        $codes = [];
        Mail::assertSent(LoginVerificationCodeMail::class, function (LoginVerificationCodeMail $mail) use (&$codes) {
            $codes[] = $mail->code;

            return true;
        });

        return $codes;
    };
    $this->signIn = fn () => testCase()->postJson('/login', ['email' => 'engineering@example.com', 'password' => 'secret123']);
});

it('asks an end-user office for the emailed code before opening the dashboard', function () {
    ($this->signIn)()->assertOk()
        ->assertJsonPath('tab', 'verify')
        ->assertJsonPath('requires_verification', true)
        ->assertJsonPath('expires_in', 300)
        ->assertJsonPath('resend_available_in', 60);
    testCase()->assertGuest();

    // The dashboard cannot be reached by URL before the code is entered.
    testCase()->get(route('end-user.dashboard'))->assertRedirect();
    testCase()->assertGuest();

    [$code] = ($this->codes)();
    expect($code)->toMatch('/^\d{6}$/');
    // Only a hash is kept.
    expect(json_encode(session('login_verification')))->not->toContain($code)
        ->and(Hash::check($code, session('login_verification.code_hash')))->toBeTrue();

    Mail::assertSent(LoginVerificationCodeMail::class, fn ($mail) => $mail->minutes === 5
        && str_contains($mail->render(), 'This code expires in 5 minutes')
        && str_contains($mail->render(), 'SJBAC account'));

    testCase()->postJson(route('login.verify-code'), ['code' => $code])->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('redirect', route('end-user.dashboard'));
    testCase()->assertAuthenticatedAs($this->office);

    // Single use.
    auth()->logout();
    testCase()->postJson(route('login.verify-code'), ['code' => $code])->assertStatus(422)->assertJsonPath('tab', 'login');
    testCase()->assertGuest();
});

it('limits wrong codes to five, then needs a new code', function () {
    ($this->signIn)();
    [$code] = ($this->codes)();
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach ([4, 3, 2, 1] as $left) {
        testCase()->postJson(route('login.verify-code'), ['code' => $wrong])->assertStatus(422)
            ->assertJsonPath('message', "Verification code is incorrect. {$left} ".($left === 1 ? 'attempt' : 'attempts').' left.');
    }
    testCase()->postJson(route('login.verify-code'), ['code' => $wrong])->assertStatus(429)
        ->assertJsonPath('message', 'Too many incorrect codes. Press Resend code to get a new one.');

    // Even the right code no longer works.
    testCase()->postJson(route('login.verify-code'), ['code' => $code])->assertStatus(422)
        ->assertJsonPath('tab', 'verify');
    testCase()->assertGuest();
});

it('waits 60 seconds between resends, and a new code replaces the old one', function () {
    ($this->signIn)();
    [$first] = ($this->codes)();

    testCase()->postJson(route('login.resend-code'))->assertStatus(429)
        ->assertJsonPath('resend_available_in', 60)
        ->assertJsonPath('message', 'Please wait 60 seconds before requesting a new code.');

    $this->travel(61)->seconds();
    testCase()->postJson(route('login.resend-code'))->assertOk()
        ->assertJsonPath('message', 'New verification code sent to your email. The previous code no longer works.')
        ->assertJsonPath('resend_available_in', 60);
    $codes = ($this->codes)();
    $second = end($codes);
    expect($codes)->toHaveCount(2);

    if ($first !== $second) {
        testCase()->postJson(route('login.verify-code'), ['code' => $first])->assertStatus(422);
    }
    testCase()->postJson(route('login.verify-code'), ['code' => $second])->assertOk();
    testCase()->assertAuthenticatedAs($this->office);
});

it('expires a code after 5 minutes and lets the office ask for a new one', function () {
    ($this->signIn)();
    [$code] = ($this->codes)();

    $this->travel(301)->seconds();
    testCase()->postJson(route('login.verify-code'), ['code' => $code])->assertStatus(422)
        ->assertJsonPath('tab', 'verify')
        ->assertJsonPath('message', 'This code has expired. Press Resend code to get a new one.');

    testCase()->postJson(route('login.resend-code'))->assertOk();
    $codes = ($this->codes)();
    testCase()->postJson(route('login.verify-code'), ['code' => end($codes)])->assertOk();
    testCase()->assertAuthenticatedAs($this->office);
});

it('also asks for the code after Google sign-in, and keeps other roles as they were', function () {
    config()->set('services.google', ['client_id' => 'test-client.apps.googleusercontent.com', 'client_secret' => 'test-secret', 'redirect' => null]);
    $google = (new GoogleUser)->setRaw(['email' => 'engineering@example.com', 'email_verified' => true])->map(['id' => '1', 'email' => 'engineering@example.com', 'name' => 'Eng']);
    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('redirectUrl')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($google);
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

    testCase()->get(route('auth.google.callback'))->assertRedirect(route('home'))
        ->assertSessionHas('auth_tab', 'verify');
    testCase()->assertGuest();
    // The landing page opens on the code step.
    testCase()->get(route('home'))->assertOk()->assertSee('window.startLoginVerification', false)->assertSee('engineering@example.com');
    [$code] = ($this->codes)();
    testCase()->postJson(route('login.verify-code'), ['code' => $code])->assertOk();
    testCase()->assertAuthenticatedAs($this->office);

    // Admin and staff still sign in with the password alone.
    auth()->logout();
    foreach (['admin', 'staff'] as $role) {
        User::create(['name' => ucfirst($role), 'email' => "$role@example.com", 'password' => Hash::make('secret123'), 'role' => $role, 'status' => 'active', 'office' => $role === 'staff' ? 'BAC Secretariat' : null]);
        testCase()->postJson('/login', ['email' => "$role@example.com", 'password' => 'secret123'])->assertOk()
            ->assertJsonMissingPath('requires_verification');
        testCase()->assertAuthenticated();
        auth()->logout();
    }
    Mail::assertSentCount(1);
});
