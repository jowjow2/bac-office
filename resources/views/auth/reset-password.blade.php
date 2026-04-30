<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    @vite(['resources/css/app.css'])
</head>
<body class="auth-recovery-page">
    <div class="auth-recovery-shell">
        <a href="{{ url('/') }}" class="auth-recovery-back">Back to Sign In</a>

        <div class="auth-recovery-card">
            <h1>Reset Password</h1>
            <p>Create a new password for your account, then sign in again using your registered email.</p>

            @if($errors->any())
                <div class="auth-recovery-alert error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="auth-recovery-form">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="auth-recovery-field">
                    <label for="resetPasswordEmail">Registered Email</label>
                    <input
                        type="email"
                        id="resetPasswordEmail"
                        name="email"
                        value="{{ old('email', $email) }}"
                        placeholder="Enter your email"
                        required
                        autocomplete="username"
                    >
                </div>

                <div class="auth-recovery-field">
                    <label for="resetPasswordNew">New Password</label>
                    <input
                        type="password"
                        id="resetPasswordNew"
                        name="password"
                        placeholder="Enter your new password"
                        required
                        autocomplete="new-password"
                    >
                </div>

                <div class="auth-recovery-field">
                    <label for="resetPasswordConfirm">Confirm Password</label>
                    <input
                        type="password"
                        id="resetPasswordConfirm"
                        name="password_confirmation"
                        placeholder="Confirm your new password"
                        required
                        autocomplete="new-password"
                    >
                </div>

                <button type="submit" class="auth-recovery-submit">Reset Password</button>
            </form>
        </div>
    </div>
</body>
</html>
