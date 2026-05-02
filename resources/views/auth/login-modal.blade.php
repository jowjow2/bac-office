<div id="authModal" class="auth-modal">
    <div class="auth-card">
        <button type="button" class="auth-close" onclick="closeAuth()" aria-label="Close">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
        </button>

        <div id="authMessage"></div>

        @if(session('success'))
            <div class="alert success auth-session-alert" data-auto-hide="5000">
                <span class="alert-icon" aria-hidden="true">
                    <span class="alert-loader"></span>
                    <svg class="alert-check" viewBox="0 0 24 24">
                        <path d="M20 6L9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span class="alert-text">{{ session('success') }}</span>
            </div>
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const successTab = '{{ session('auth_tab', 'register') }}';
                    activateAuthTab(successTab === 'register' ? 'login' : successTab);
                });
            </script>
        @endif

        @if(session('error'))
            <div class="alert error">{{ session('error') }}</div>
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    activateAuthTab('{{ session('auth_tab', 'login') }}');
                });
            </script>
        @endif

        @if(session('auth_tab') && !session('success') && !session('error'))
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    activateAuthTab('{{ session('auth_tab', 'login') }}');
                });
            </script>
        @endif

        <div class="auth-card-header">
            <div class="auth-card-logo">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V7L12 2z" fill="currentColor" opacity="0.18"/>
                    <path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V7L12 2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <span class="auth-eyebrow">Official BAC Portal</span>
            <h2>Welcome Back</h2>
            <p class="auth-subtitle">Sign in to the Bids and Awards Committee procurement system</p>
        </div>

        <div class="tabs" id="authTabs">
            <button type="button" id="tabLogin" class="active" onclick="switchTab('login')">Sign In</button>
            <button type="button" id="tabQr" onclick="activateAuthTab('qr')">Scan QR Code</button>
            <button type="button" id="tabRegister" onclick="switchTab('register')">Register</button>
        </div>

        <form id="loginForm" method="POST" action="{{ route('login') }}">
            @csrf
            <div class="auth-field">
                <div class="auth-input-icon-wrap">
                    <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="4" width="20" height="16" rx="2"/>
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                    </svg>
                    <input type="email" name="email" placeholder="Email address" required autocomplete="username" autocapitalize="off" spellcheck="false">
                </div>
                <div class="field-error" data-error-for="email"></div>
            </div>
            <div class="auth-field">
                <div class="password-field auth-input-icon-wrap">
                    <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="11" width="18" height="11" rx="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input type="password" id="loginPassword" name="password" placeholder="Password" required autocomplete="current-password">
                    <button type="button" class="password-toggle" onclick="togglePassword('loginPassword', this)" aria-label="Show password">
                        <svg class="password-icon password-icon-show" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                        <svg class="password-icon password-icon-hide hidden" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 3l18 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M10.6 10.7A3 3 0 0 0 12 15a3 3 0 0 0 2.3-.9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M6.7 6.8C4.5 8.3 3 12 3 12s3.6 6 9 6c2.1 0 3.9-.6 5.3-1.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M14.9 5.3A10.7 10.7 0 0 1 21 12s-1.1 1.9-3.1 3.7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
                <div class="field-error" data-error-for="password"></div>
            </div>
            <div class="auth-options">
                <label class="remember-option">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                    <span>Remember me</span>
                </label>
                <a href="{{ route('password.request') }}" class="auth-link" onclick="switchTab('forgot'); return false;">Forgot password?</a>
            </div>
            <button type="submit">Sign In</button>
        </form>

        <form id="qrLoginForm" method="POST" action="{{ route('login.qr') }}" class="hidden">
            @csrf
            <div class="auth-helper-card">
                <strong>Bidder Quick Access</strong>
                <span>Approved bidders can scan their QR Code to securely access their bidder dashboard.</span>
                <span class="auth-helper-note">QR login is available only for approved bidder accounts.</span>
            </div>

            <div class="qr-scanner-panel">
                <div class="qr-scanner-preview">
                    <video id="qrScannerVideo" playsinline muted></video>
                    <div id="qrScannerPlaceholder" class="qr-scanner-placeholder">
                        Start the camera or upload a saved QR image. Once a valid bidder QR code is detected, the system will automatically continue the login process.
                    </div>
                </div>

                <div class="qr-scanner-actions">
                    <button type="button" id="startQrScannerButton" class="auth-secondary-button" onclick="startQrScanner()">Start Camera</button>
                    <button type="button" id="stopQrScannerButton" class="auth-secondary-button hidden" onclick="stopQrScanner(true)">Stop Camera</button>
                    <label for="qrImageFile" class="auth-secondary-button qr-upload-button">Upload QR Image</label>
                    <input type="file" id="qrImageFile" accept="image/*" class="hidden">
                </div>

                <div id="qrImagePreviewCard" class="qr-image-preview-card hidden">
                    <div class="qr-image-preview-frame">
                        <img id="qrImagePreview" src="" alt="Uploaded QR preview">
                    </div>
                    <div class="qr-image-preview-copy">
                        <strong>Uploaded QR preview</strong>
                        <span id="qrImagePreviewText">Review the uploaded QR image first, then continue secure bidder login.</span>
                    </div>
                    <div class="qr-scanner-actions qr-image-preview-actions">
                        <button type="button" id="confirmQrImageButton" class="auth-secondary-button qr-confirm-button">Confirm QR Image</button>
                        <button type="button" id="clearQrImageButton" class="auth-secondary-button" onclick="clearQrImagePreview(true)">Choose Another Image</button>
                    </div>
                </div>

                <div id="qrScannerStatus" class="qr-scanner-status">
                    Only approved bidder accounts with valid BAC Office QR login codes can use automatic QR sign in by camera scan or uploaded QR image.
                </div>
            </div>

            <div class="auth-field">
                <label for="qrPayload">QR Code Data</label>
                <textarea id="qrPayload" name="qr_payload" rows="3" placeholder="Scanned QR code data will appear here automatically, or you can paste it here manually."></textarea>
                <div class="field-error" data-error-for="qr_payload"></div>
            </div>

            <div id="qrPasswordField" class="auth-field hidden">
                <label for="qrPassword">Confirm Password</label>
                <div class="password-field auth-input-icon-wrap">
                    <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="11" width="18" height="11" rx="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input type="password" id="qrPassword" name="password" placeholder="Enter your current password for first-time QR activation" autocomplete="current-password">
                    <button type="button" class="password-toggle" onclick="togglePassword('qrPassword', this)" aria-label="Show password">
                        <svg class="password-icon password-icon-show" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                        <svg class="password-icon password-icon-hide hidden" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 3l18 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M10.6 10.7A3 3 0 0 0 12 15a3 3 0 0 0 2.3-.9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M6.7 6.8C4.5 8.3 3 12 3 12s3.6 6 9 6c2.1 0 3.9-.6 5.3-1.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M14.9 5.3A10.7 10.7 0 0 1 21 12s-1.1 1.9-3.1 3.7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
                <div class="field-error" data-error-for="password"></div>
            </div>

            <button type="submit">Continue with QR Login</button>
            <div class="auth-secondary-actions">
                <button type="button" class="auth-link-button" onclick="switchTab('login')">Login Manually</button>
            </div>
        </form>

        <form id="forgotPasswordForm" method="POST" action="{{ route('password.email') }}" class="hidden">
            @csrf
            <p class="auth-helper-text">Enter the email you used to register and we will send a password reset link.</p>
            <div class="auth-field">
                <div class="auth-input-icon-wrap">
                    <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="4" width="20" height="16" rx="2"/>
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                    </svg>
                    <input type="email" name="email" placeholder="Registered email address" autocomplete="email" required>
                </div>
                <div class="field-error" data-error-for="email"></div>
            </div>
            <div class="auth-secondary-actions">
                <button type="button" class="auth-link-button" onclick="switchTab('login')">Back to Sign In</button>
            </div>
            <button type="submit">Send Reset Link</button>
        </form>

        <form id="registerForm" method="POST" action="{{ route('register') }}" class="hidden">
            @csrf
            <div class="auth-register-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 16v-4M12 8h.01"/>
                </svg>
                <span>Registration is for bidders only. New accounts require admin approval before you can access the portal.</span>
            </div>
            <div class="auth-field">
                <div class="auth-input-icon-wrap">
                    <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/>
                        <path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/>
                        <path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/>
                        <path d="M10 6h4M10 10h4M10 14h4M10 18h4"/>
                    </svg>
                    <input type="text" name="company" placeholder="Company name" required autocomplete="organization">
                </div>
                <div class="field-error" data-error-for="company"></div>
            </div>
            <div class="auth-field">
                <div class="auth-input-icon-wrap">
                    <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="4" width="20" height="16" rx="2"/>
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                    </svg>
                    <input type="email" name="email" placeholder="Email address" required autocomplete="email">
                </div>
                <div class="field-error" data-error-for="email"></div>
            </div>
            <div class="auth-field">
                <div class="password-field auth-input-icon-wrap">
                    <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="11" width="18" height="11" rx="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input type="password" id="registerPassword" name="password" placeholder="Create a password" required autocomplete="new-password">
                    <button type="button" class="password-toggle" onclick="togglePassword('registerPassword', this)" aria-label="Show password">
                        <svg class="password-icon password-icon-show" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                        <svg class="password-icon password-icon-hide hidden" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 3l18 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M10.6 10.7A3 3 0 0 0 12 15a3 3 0 0 0 2.3-.9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M6.7 6.8C4.5 8.3 3 12 3 12s3.6 6 9 6c2.1 0 3.9-.6 5.3-1.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M14.9 5.3A10.7 10.7 0 0 1 21 12s-1.1 1.9-3.1 3.7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
                <div class="field-error" data-error-for="password"></div>
            </div>
            <div class="auth-field">
                <div class="auth-input-icon-wrap">
                    <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="5" width="20" height="14" rx="2"/>
                        <circle cx="9" cy="12" r="2"/>
                        <path d="M14 9.5h4M14 12.5h4M14 15.5h2"/>
                    </svg>
                    <input type="text" name="registration_no" placeholder="Business registration number" required autocomplete="off">
                </div>
                <div class="field-error" data-error-for="registration_no"></div>
            </div>
            <button type="submit">Create Account</button>
        </form>
    </div>
</div>
