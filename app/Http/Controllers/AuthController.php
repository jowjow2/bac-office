<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\LoginAudit;
use App\Support\QrLoginService;
use App\Support\SystemNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    protected function authResponse(
        Request $request,
        bool $ok,
        string $message,
        string $tab = 'login',
        int $status = 200,
        ?string $redirect = null,
        array $errors = [],
        array $extra = []
    ) {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(array_merge([
                'ok' => $ok,
                'message' => $message,
                'tab' => $tab,
                'redirect' => $redirect,
                'errors' => $errors,
            ], $extra), $status);
        }

        if ($ok && $redirect) {
            return redirect()->to($redirect)->with('success', $message);
        }

        $flashKey = $ok ? 'success' : 'error';

        return back()
            ->withInput($request->except(['password']))
            ->with($flashKey, $message)
            ->with('auth_tab', $tab);
    }

    public function showLoginPage(Request $request)
    {
        if (Auth::check()) {
            return redirect()->to($this->redirectForUser(Auth::user()));
        }

        return redirect()
            ->route('home')
            ->with('auth_tab', (string) $request->query('auth_tab', 'login'));
    }

    public function register(Request $request)
    {
        if (! $this->authTablesAvailable()) {
            return $this->authResponse(
                $request,
                false,
                'Registration is temporarily unavailable because the application database is not connected yet.',
                'register',
                503
            );
        }

        $request->merge([
            'email' => strtolower(trim((string) $request->email)),
            'company' => trim((string) $request->company),
            'registration_no' => trim((string) $request->registration_no),
        ]);

        $validator = Validator::make($request->all(), [
            'company' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:6'],
            'registration_no' => ['required'],
        ], [
            'company.required' => 'Company is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 6 characters.',
            'registration_no.required' => 'Registration number is required.',
        ]);

        if ($validator->fails()) {
            return $this->authResponse(
                $request,
                false,
                $validator->errors()->first(),
                'register',
                422,
                null,
                $validator->errors()->toArray()
            );
        }

        if (User::where('email', $request->email)->exists()) {
            return $this->authResponse($request, false, 'An account with this email already exists. Please sign in instead.', 'register', 422, null, [
                'email' => ['An account with this email already exists.'],
            ]);
        }

        User::create([
            'name' => $request->company,
            'company' => $request->company,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'registration_no' => $request->registration_no,
            'role' => 'bidder',
            'status' => 'pending',
        ]);

        SystemNotification::createForRole(
            'admin',
            'New bidder registration',
            $request->company . ' registration is pending approval.',
            'bidder_registration',
            ['email' => $request->email]
        );

        return $this->authResponse(
            $request,
            true,
            'Registered successfully! Your account is pending admin approval.',
            'register'
        );
    }

    public function login(Request $request)
    {
        if (! $this->authTablesAvailable()) {
            return $this->authResponse(
                $request,
                false,
                'Login is temporarily unavailable because the application database is not connected yet.',
                'login',
                503
            );
        }

        $request->merge([
            'email' => strtolower(trim((string) $request->email)),
        ]);

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required'],
            'remember' => ['nullable', 'boolean'],
        ], [
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
        ]);

        if ($validator->fails()) {
            return $this->authResponse($request, false, $validator->errors()->first(), 'login', 422, null, $validator->errors()->toArray());
        }

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return $this->authResponse($request, false, 'No account found with that email. Please register first.', 'login', 422, null, [
                'email' => ['No account found with that email.'],
            ]);
        }

        if (! Hash::check($request->password, $user->password)) {
            return $this->authResponse($request, false, 'Account exists, but the password is incorrect.', 'login', 422, null, [
                'password' => ['Password is incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            return $this->authResponse($request, false, 'Your account already exists but is not active yet. Please wait for admin approval.', 'login', 422);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return $this->authResponse(
            $request,
            true,
            'Login successful.',
            'login',
            200,
            $this->redirectForUser($user)
        );
    }

    public function loginWithQr(Request $request, QrLoginService $qrLoginService)
    {
        if (! $this->qrLoginAvailable()) {
            return $this->authResponse(
                $request,
                false,
                'QR login is not available yet because the required database tables have not been migrated.',
                'qr',
                503
            );
        }

        $validator = Validator::make($request->all(), [
            'qr_payload' => ['required', 'string'],
            'password' => ['nullable', 'string'],
        ], [
            'qr_payload.required' => 'Scan a valid QR login code first.',
        ]);

        if ($validator->fails()) {
            return $this->authResponse(
                $request,
                false,
                $validator->errors()->first(),
                'qr',
                422,
                null,
                $validator->errors()->toArray()
            );
        }

        $plainToken = $qrLoginService->extractPlainToken((string) $request->input('qr_payload'));

        if (! $plainToken) {
            LoginAudit::record($request, null, 'qr', 'failed', 'invalid_payload');

            return $this->authResponse($request, false, 'Unable to read that QR login code. Please scan again.', 'qr', 422, null, [
                'qr_payload' => ['Unable to read that QR login code. Please scan again.'],
            ]);
        }

        $token = $qrLoginService->findToken($plainToken);

        if (! $token) {
            LoginAudit::record($request, null, 'qr', 'failed', 'token_not_found');

            return $this->authResponse($request, false, 'This QR login code is invalid. Please use a valid bidder QR code.', 'qr', 422, null, [
                'qr_payload' => ['This QR login code is invalid.'],
            ]);
        }

        $user = $token->user;

        if (! $user || $user->role !== 'bidder') {
            LoginAudit::record($request, $user, 'qr', 'failed', 'not_bidder');

            return $this->authResponse($request, false, 'QR login is only available for registered bidder accounts.', 'qr', 422);
        }

        if (! $user->isApprovedBidder()) {
            LoginAudit::record($request, $user, 'qr', 'failed', 'account_not_approved');

            return $this->authResponse($request, false, 'QR login is only available for approved bidder accounts.', 'qr', 422);
        }

        if (! $token->is_active || $token->revoked_at !== null) {
            LoginAudit::record($request, $user, 'qr', 'failed', 'token_revoked');

            return $this->authResponse($request, false, 'This QR login code has been revoked. Please contact the BAC Office for a new one.', 'qr', 422);
        }

        if ($token->is_expired) {
            LoginAudit::record($request, $user, 'qr', 'failed', 'token_expired');

            return $this->authResponse($request, false, 'This QR login code has expired. Please contact the BAC Office for a new one.', 'qr', 422);
        }

        if ($token->activated_at === null && $this->qrLoginRequiresPasswordConfirmation()) {
            $password = (string) $request->input('password', '');

            if ($password === '') {
                return $this->authResponse(
                    $request,
                    false,
                    'Please confirm your password to activate this QR login code for the first time.',
                    'qr',
                    422,
                    null,
                    [],
                    ['requires_password_confirmation' => true]
                );
            }

            if (! Hash::check($password, (string) $user->password)) {
                LoginAudit::record($request, $user, 'qr', 'failed', 'password_confirmation_failed');

                return $this->authResponse(
                    $request,
                    false,
                    'Password confirmation failed. Please enter your current account password.',
                    'qr',
                    422,
                    null,
                    ['password' => ['Password confirmation failed.']],
                    ['requires_password_confirmation' => true]
                );
            }

            $qrLoginService->activateAndMarkUsed($token);
        } else {
            $qrLoginService->activateAndMarkUsed($token);
        }

        Auth::login($user);
        $request->session()->regenerate();

        LoginAudit::record($request, $user, 'qr', 'success');

        return $this->authResponse(
            $request,
            true,
            'QR login successful.',
            'qr',
            200,
            route('bidder.dashboard')
        );
    }

    public function showForgotPasswordForm()
    {
        return redirect()->route('home')->with('auth_tab', 'forgot');
    }

    public function sendPasswordResetLink(Request $request)
    {
        if (! $this->passwordResetAvailable()) {
            return $this->authResponse(
                $request,
                false,
                'Password reset is temporarily unavailable because the application database is not connected yet.',
                'forgot',
                503
            );
        }

        $request->merge([
            'email' => strtolower(trim((string) $request->email)),
        ]);

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        if ($validator->fails()) {
            return $this->authResponse($request, false, $validator->errors()->first(), 'forgot', 422, null, $validator->errors()->toArray());
        }

        $status = Password::sendResetLink([
            'email' => $request->email,
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            return $this->authResponse($request, true, 'Password reset link sent. Please check your email.', 'login');
        }

        return $this->authResponse($request, false, __($status), 'forgot', 422, null, [
            'email' => [__($status)],
        ]);
    }

    public function showResetPasswordForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request)
    {
        if (! $this->passwordResetAvailable()) {
            return back()->withErrors([
                'email' => 'Password reset is temporarily unavailable because the application database is not connected yet.',
            ]);
        }

        $validated = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 6 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        $status = Password::reset(
            [
                'email' => strtolower(trim($validated['email'])),
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $validated['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('home')
                ->with('success', 'Your password has been reset. Please sign in.')
                ->with('auth_tab', 'login');
        }

        return back()
            ->withInput($request->only(['email']))
            ->withErrors(['email' => __($status)]);
    }

    public function logout()
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('home');
    }

    protected function redirectForUser(User $user): string
    {
        return match ($user->role) {
            'admin' => route('admin.dashboard'),
            'staff' => route('staff.dashboard'),
            default => route('bidder.dashboard'),
        };
    }

    protected function qrLoginAvailable(): bool
    {
        try {
            return Schema::hasTable('bidders')
                && Schema::hasTable('qr_login_tokens')
                && Schema::hasTable('login_logs');
        } catch (Throwable) {
            return false;
        }
    }

    protected function authTablesAvailable(): bool
    {
        try {
            return Schema::hasTable('users');
        } catch (Throwable) {
            return false;
        }
    }

    protected function passwordResetAvailable(): bool
    {
        try {
            return Schema::hasTable('users') && Schema::hasTable('password_reset_tokens');
        } catch (Throwable) {
            return false;
        }
    }

    protected function qrLoginRequiresPasswordConfirmation(): bool
    {
        return (bool) config('bac-office.qr_login.require_password_on_first_use', false);
    }
}
