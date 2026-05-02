@extends('layouts.public')

@section('title', 'Login')
@section('body_class', 'public-page auth-login-page')

@section('content')
    <main class="login-page-shell">
        <div class="login-page-grid">
            <section class="login-page-panel">
                <p class="login-page-kicker">BAC Office Secure Access</p>
                <h1>Bidder Quick Access</h1>
                <p>
                    Approved bidders can scan their QR Code to securely access their bidder dashboard.
                </p>

                <div class="login-page-actions">
                    <button type="button" class="btn" onclick="activateAuthTab('qr')">Scan QR Code</button>
                    <button type="button" class="btn btn-outline" onclick="activateAuthTab('login')">Login Manually</button>
                </div>

                <p class="login-page-note">
                    QR login is available only for approved bidder accounts.
                </p>

                <div class="login-page-bullets">
                    <div class="login-page-bullet">
                        <strong>Approved bidders only</strong>
                        <span>QR quick access is reserved for bidder accounts that have already been reviewed and approved by the BAC Office.</span>
                    </div>
                    <div class="login-page-bullet">
                        <strong>Scan or sign in manually</strong>
                        <span>Use the QR Code issued to your approved bidder account, or continue with your registered email and password.</span>
                    </div>
                    <div class="login-page-bullet">
                        <strong>One-time verification</strong>
                        <span>Once your bidder account is approved, scanning your QR Code can sign you in directly to the bidder dashboard.</span>
                    </div>
                </div>
            </section>

            <aside class="login-page-sidebar">
                <h2>How bidder quick access works</h2>
                <p>Follow these steps after your bidder registration has already been approved by the BAC Office.</p>

                <div class="login-page-steps">
                    <div class="login-page-step">
                        <span class="login-page-step-number">1</span>
                        <div>
                            <strong>Receive approval</strong>
                            <span>Wait for BAC Office to approve your bidder account before QR quick access becomes available.</span>
                        </div>
                    </div>
                    <div class="login-page-step">
                        <span class="login-page-step-number">2</span>
                        <div>
                            <strong>Use your QR Code</strong>
                            <span>Scan the QR Code issued to your approved account to begin secure bidder dashboard access.</span>
                        </div>
                    </div>
                    <div class="login-page-step">
                        <span class="login-page-step-number">3</span>
                        <div>
                            <strong>Scan and sign in</strong>
                            <span>Open the secure BAC Office login page, scan your approved bidder QR Code, and continue straight to your dashboard.</span>
                        </div>
                    </div>
                    <div class="login-page-step">
                        <span class="login-page-step-number">4</span>
                        <div>
                            <strong>Login manually anytime</strong>
                            <span>You can still use your registered email and password whenever you prefer manual sign in.</span>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            activateAuthTab(@json($authTab ?? 'login'));
        });
    </script>
@endsection
