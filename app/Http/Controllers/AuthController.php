<?php

namespace App\Http\Controllers;

use App\Mail\LoginVerificationCodeMail;
use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use App\Support\SystemNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
            'role' => strtolower(trim((string) $request->input('role', 'bidder'))),
            'name' => trim((string) $request->name),
            'company' => trim((string) $request->company),
            'office' => trim((string) $request->office),
            'registration_no' => trim((string) $request->registration_no),
        ]);

        $validator = Validator::make($request->all(), [
            'role' => ['required', Rule::in(['bidder', 'staff'])],
            'name' => [
                Rule::excludeIf($request->input('role') !== 'staff'),
                'required',
                'string',
                'max:255',
            ],
            'company' => [
                Rule::excludeIf($request->input('role') !== 'bidder'),
                'required',
                'string',
                'max:255',
            ],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:6'],
            'office' => [
                Rule::excludeIf($request->input('role') !== 'staff'),
                'required',
                'string',
                'max:255',
                Rule::in(User::staffOfficeOptions()),
            ],
            'registration_no' => [
                Rule::excludeIf($request->input('role') !== 'bidder'),
                'required',
                'string',
                'max:255',
            ],
        ], [
            'role.required' => 'Account type is required.',
            'role.in' => 'Please select a valid account type.',
            'name.required' => 'Name is required for staff registration.',
            'company.required' => 'Company is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 6 characters.',
            'office.required' => 'Office is required for staff registration.',
            'office.in' => 'Please select a valid staff office.',
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

        if ($request->input('role') === 'staff') {
            User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'staff',
                'status' => 'pending',
                'office' => $request->office,
            ]);

            SystemNotification::createForRole(
                'admin',
                'New staff registration',
                $request->name . ' selected ' . $request->office . ' and is pending activation.',
                'staff_registration',
                [
                    'email' => $request->email,
                    'office' => $request->office,
                ]
            );
        } else {
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
        }

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

        if ($user->role === 'bidder') {
            $this->issueBidderLoginVerificationCode($request, $user, $request->boolean('remember'));

            return $this->authResponse(
                $request,
                true,
                'Verification code sent to your Gmail account. Please enter the code to continue.',
                'verify',
                200,
                null,
                [],
                [
                    'requires_verification' => true,
                    'email' => $user->email,
                ]
            );
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

    public function resendLoginCode(Request $request)
    {
        $pending = $request->session()->get('bidder_login_verification');

        if (! is_array($pending) || empty($pending['user_id'])) {
            return $this->authResponse($request, false, 'Please sign in again to request a new verification code.', 'login', 422);
        }

        $user = User::find($pending['user_id']);

        if (! $user || $user->role !== 'bidder' || $user->status !== 'active') {
            $request->session()->forget('bidder_login_verification');

            return $this->authResponse($request, false, 'Your bidder account is not available for login.', 'login', 422);
        }

        $this->issueBidderLoginVerificationCode($request, $user, (bool) ($pending['remember'] ?? false));

        return $this->authResponse(
            $request,
            true,
            'New verification code sent to your Gmail account.',
            'verify',
            200,
            null,
            [],
            [
                'requires_verification' => true,
                'email' => $user->email,
            ]
        );
    }

    public function verifyLoginCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => 'Verification code is required.',
            'code.digits' => 'Please enter the 6-digit verification code.',
        ]);

        if ($validator->fails()) {
            return $this->authResponse($request, false, $validator->errors()->first(), 'verify', 422, null, $validator->errors()->toArray());
        }

        $pending = $request->session()->get('bidder_login_verification');

        if (! is_array($pending) || empty($pending['user_id']) || empty($pending['code_hash']) || empty($pending['expires_at'])) {
            return $this->authResponse($request, false, 'Please sign in again to request a new verification code.', 'login', 422);
        }

        if (now()->timestamp > (int) $pending['expires_at']) {
            $request->session()->forget('bidder_login_verification');

            return $this->authResponse($request, false, 'Verification code expired. Please sign in again.', 'login', 422);
        }

        if (! Hash::check((string) $request->input('code'), (string) $pending['code_hash'])) {
            return $this->authResponse($request, false, 'Verification code is incorrect.', 'verify', 422, null, [
                'code' => ['Verification code is incorrect.'],
            ]);
        }

        $user = User::find($pending['user_id']);

        if (! $user || $user->role !== 'bidder' || $user->status !== 'active') {
            $request->session()->forget('bidder_login_verification');

            return $this->authResponse($request, false, 'Your bidder account is not available for login.', 'login', 422);
        }

        $remember = (bool) ($pending['remember'] ?? false);
        $request->session()->forget('bidder_login_verification');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return $this->authResponse(
            $request,
            true,
            'Login successful.',
            'verify',
            200,
            $this->redirectForUser($user)
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

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return $this->authResponse($request, false, 'No account found with that email.', 'forgot', 422, null, [
                'email' => ['No account found with that email.'],
            ]);
        }

        $code = (string) random_int(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Hash::make($code),
                'created_at' => now(),
            ]
        );

        Mail::to($user->email)->send(new PasswordResetCodeMail($user, $code));

        return $this->authResponse(
            $request,
            true,
            'Password reset code sent to your Gmail account.',
            'forgot_verify',
            200,
            null,
            [],
            [
                'requires_password_code' => true,
                'email' => $user->email,
            ]
        );
    }

    public function verifyPasswordResetCode(Request $request)
    {
        if (! $this->passwordResetAvailable()) {
            return $this->authResponse($request, false, 'Password reset is temporarily unavailable because the application database is not connected yet.', 'forgot', 503);
        }

        $request->merge([
            'email' => strtolower(trim((string) $request->email)),
        ]);

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ], [
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'code.required' => 'Verification code is required.',
            'code.digits' => 'Please enter the 6-digit verification code.',
        ]);

        if ($validator->fails()) {
            return $this->authResponse($request, false, $validator->errors()->first(), 'forgot_verify', 422, null, $validator->errors()->toArray());
        }

        $reset = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (! $reset || ! Hash::check((string) $request->input('code'), (string) $reset->token)) {
            return $this->authResponse($request, false, 'Verification code is incorrect.', 'forgot_verify', 422, null, [
                'code' => ['Verification code is incorrect.'],
            ]);
        }

        if (! $reset->created_at || now()->diffInMinutes($reset->created_at) > 10) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return $this->authResponse($request, false, 'Verification code expired. Please request a new code.', 'forgot', 422);
        }

        $request->session()->put('verified_password_reset_email', $request->email);

        return $this->authResponse(
            $request,
            true,
            'Code verified. Create your new password.',
            'reset_password',
            200,
            null,
            [],
            [
                'password_reset_verified' => true,
                'email' => $request->email,
            ]
        );
    }

    public function showResetPasswordForm(Request $request, ?string $token = null)
    {
        $email = (string) $request->query('email', '');

        if ($token === null) {
            $email = (string) $request->session()->get('verified_password_reset_email', '');

            if ($email === '') {
                return redirect()
                    ->route('home')
                    ->with('error', 'Please verify your password reset code first.')
                    ->with('auth_tab', 'forgot');
            }
        }

        return view('auth.reset-password', [
            'token' => $token ?? '',
            'email' => $email,
        ]);
    }

    public function resetPassword(Request $request)
    {
        if (! $this->passwordResetAvailable()) {
            if ($request->ajax() || $request->expectsJson()) {
                return $this->authResponse(
                    $request,
                    false,
                    'Password reset is temporarily unavailable because the application database is not connected yet.',
                    'reset_password',
                    503,
                    null,
                    ['email' => ['Password reset is temporarily unavailable because the application database is not connected yet.']]
                );
            }

            return back()->withErrors([
                'email' => 'Password reset is temporarily unavailable because the application database is not connected yet.',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'token' => ['nullable'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 6 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        if ($validator->fails()) {
            if ($request->ajax() || $request->expectsJson()) {
                return $this->authResponse($request, false, $validator->errors()->first(), 'reset_password', 422, null, $validator->errors()->toArray());
            }

            return back()
                ->withInput($request->only(['email']))
                ->withErrors($validator);
        }

        $validated = $validator->validated();
        $email = strtolower(trim($validated['email']));
        $verifiedEmail = (string) $request->session()->get('verified_password_reset_email', '');

        if ($verifiedEmail !== '' && hash_equals($verifiedEmail, $email)) {
            $user = User::where('email', $email)->firstOrFail();
            $this->resetUserPassword($user, $validated['password']);
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            $request->session()->forget('verified_password_reset_email');

            if ($request->ajax() || $request->expectsJson()) {
                return $this->authResponse($request, true, 'Your password has been reset. Please sign in.', 'login');
            }

            return redirect()
                ->route('home')
                ->with('success', 'Your password has been reset. Please sign in.')
                ->with('auth_tab', 'login');
        }

        $status = Password::reset(
            [
                'email' => $email,
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $validated['token'] ?? '',
            ],
            function (User $user, string $password): void {
                $this->resetUserPassword($user, $password);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            if ($request->ajax() || $request->expectsJson()) {
                return $this->authResponse($request, true, 'Your password has been reset. Please sign in.', 'login');
            }

            return redirect()
                ->route('home')
                ->with('success', 'Your password has been reset. Please sign in.')
                ->with('auth_tab', 'login');
        }

        if ($request->ajax() || $request->expectsJson()) {
            return $this->authResponse($request, false, __($status), 'reset_password', 422, null, [
                'email' => [__($status)],
            ]);
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

    protected function issueBidderLoginVerificationCode(Request $request, User $user, bool $remember): void
    {
        $code = (string) random_int(100000, 999999);

        $request->session()->put('bidder_login_verification', [
            'user_id' => $user->id,
            'remember' => $remember,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        Mail::to($user->email)->send(new LoginVerificationCodeMail($user, $code));
    }

    protected function resetUserPassword(User $user, string $password): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
            'remember_token' => Str::random(60),
        ])->save();

        event(new PasswordReset($user));
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

}
