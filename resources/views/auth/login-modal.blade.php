<div id="authModal" class="auth-modal">
    <div class="auth-card">
        <span class="close" onclick="closeAuth()">&times;</span>

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
                    openLogin();
                    switchTab(successTab === 'register' ? 'login' : successTab);
                });
            </script>
        @endif

        @if(session('error'))
            <div class="alert error">{{ session('error') }}</div>
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    openLogin();
                    switchTab('{{ session('auth_tab', 'login') }}');
                });
            </script>
        @endif

        @if(session('auth_tab') && !session('success') && !session('error'))
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    openLogin();
                    switchTab('{{ session('auth_tab', 'login') }}');
                });
            </script>
        @endif

        <h2>BAC System</h2>

        <div class="tabs" id="authTabs">
            <button type="button" id="tabLogin" class="active" onclick="switchTab('login')">Sign In</button>
            <button type="button" id="tabRegister" onclick="switchTab('register')">Register</button>
        </div>

        <form id="loginForm" method="POST" action="{{ route('login') }}">
            @csrf
            <div class="auth-field">
                <input type="email" name="email" placeholder="Email" required autocomplete="username" autocapitalize="off" spellcheck="false">
                <div class="field-error" data-error-for="email"></div>
            </div>
            <div class="auth-field">
                <div class="password-field">
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

        <form id="forgotPasswordForm" method="POST" action="{{ route('password.email') }}" class="hidden">
            @csrf
            <p class="auth-helper-text">Enter the email you used to register and we will send a password reset link.</p>
            <div class="auth-field">
                <input type="email" name="email" placeholder="Registered Email" autocomplete="email" required>
                <div class="field-error" data-error-for="email"></div>
            </div>
            <div class="auth-secondary-actions">
                <button type="button" class="auth-link-button" onclick="switchTab('login')">Back to Sign In</button>
            </div>
            <button type="submit">Send Reset Link</button>
        </form>

        <form id="registerForm" method="POST" action="{{ route('register') }}" class="hidden">
            @csrf
            <div class="auth-field">
                <input type="text" name="company" placeholder="Company" required autocomplete="organization">
                <div class="field-error" data-error-for="company"></div>
            </div>
            <div class="auth-field">
                <input type="email" name="email" placeholder="Email" required autocomplete="email">
                <div class="field-error" data-error-for="email"></div>
            </div>
            <div class="auth-field">
                <div class="password-field">
                    <input type="password" id="registerPassword" name="password" placeholder="Password" required autocomplete="new-password">
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
                <input type="text" name="registration_no" placeholder="Registration No" required autocomplete="off">
                <div class="field-error" data-error-for="registration_no"></div>
            </div>
            <button type="submit">Register</button>
        </form>
    </div>
</div>
