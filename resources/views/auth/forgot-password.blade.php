<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    @vite(['resources/css/app.css'])
</head>
<body class="auth-recovery-page">
    <div class="auth-recovery-shell">
        <a href="{{ url('/') }}" class="auth-recovery-back">Back to Sign In</a>

        <div class="auth-recovery-card">
            <h1>Forgot Password?</h1>
            <p>Enter the email address you registered with and we will send you a password reset link.</p>

            @if(session('status'))
                <div class="auth-recovery-alert success">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="auth-recovery-alert error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="auth-recovery-form">
                @csrf
                <div class="auth-recovery-field">
                    <label for="forgotPasswordEmail">Registered Email</label>
                    <input
                        type="email"
                        id="forgotPasswordEmail"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="Enter your email"
                        required
                        autocomplete="email"
                    >
                </div>

                <button type="submit" class="auth-recovery-submit">Send Reset Link</button>
            </form>
        </div>
    </div>
</body>
</html>
