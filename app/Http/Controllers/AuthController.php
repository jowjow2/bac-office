<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use App\Support\SystemNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    protected function authResponse(Request $request, bool $ok, string $message, string $tab = 'login', int $status = 200, ?string $redirect = null, array $errors = [])
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'ok' => $ok,
                'message' => $message,
                'tab' => $tab,
                'redirect' => $redirect,
                'errors' => $errors,
            ], $status);
        }

        $flashKey = $ok ? 'success' : 'error';

        return back()
            ->withInput($request->except(['password']))
            ->with($flashKey, $message)
            ->with('auth_tab', $tab);
    }

    public function register(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->email)),
            'company' => trim((string) $request->company),
            'registration_no' => trim((string) $request->registration_no),
        ]);

        $validator = Validator::make($request->all(), [
            'company' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6',
            'registration_no' => 'required',
        ], [
            'company.required' => 'Company is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 6 characters.',
            'registration_no.required' => 'Registration number is required.',
        ]);

        if ($validator->fails()) {
            return $this->authResponse($request, false, $validator->errors()->first(), 'register', 422, null, $validator->errors()->toArray());
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

        return $this->authResponse($request, true, 'Registered successfully! Your account is pending admin approval.', 'register');
    }

    public function login(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->email)),
        ]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
            'remember' => 'nullable|boolean',
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

        if ($user->role === 'admin') {
            return $this->authResponse($request, true, 'Login successful.', 'login', 200, route('admin.dashboard'));
        } elseif ($user->role === 'staff') {
            return $this->authResponse($request, true, 'Login successful.', 'login', 200, route('staff.dashboard'));
        } else {
            return $this->authResponse($request, true, 'Login successful.', 'login', 200, route('bidder.dashboard'));
        }
    }

    public function showForgotPasswordForm()
    {
        return redirect('/')
            ->with('auth_tab', 'forgot');
    }

    public function sendPasswordResetLink(Request $request)
    {
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
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect('/')
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

        return redirect('/');
    }
}
