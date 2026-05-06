@extends('layouts.public')

@section('title', 'Login')
@section('body_class', 'public-page auth-login-page')

@section('content')
    <main class="login-page-shell">
        <div class="login-page-grid">
            <section class="login-page-panel">
                <p class="login-page-kicker">BAC Office Secure Access</p>
                <h1>Sign In</h1>
                <p>Access your account to continue.</p>

                <div class="login-page-actions">
                    <button type="button" class="btn" onclick="activateAuthTab('login')">Sign In</button>
                </div>

                <p class="login-page-note">
                    New users must register for an account.
                </p>
            </section>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            activateAuthTab('login');
        });
    </script>
@endsection
