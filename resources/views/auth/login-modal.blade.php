@php($staffOffices = \App\Models\User::staffOfficeOptions())
@php($registerRole = old('role', 'bidder'))

<div id="authModal" class="auth-modal fixed inset-0 hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300">
    <div class="auth-card relative w-[calc(100vw-24px)] sm:w-[92vw] max-w-[460px] bg-white rounded-[2rem] shadow-2xl p-6 sm:p-10 transform transition-all duration-300 scale-95 opacity-0">
        <button type="button" class="auth-close absolute top-5 right-5 p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full transition-all z-20" onclick="closeAuth()" aria-label="Close">
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

        <div class="auth-card-header text-center mb-8">
            <div class="auth-brand" aria-label="BAC Office">
                <span class="auth-logo-frame">
                    <img src="{{ asset('Images/Logo.png') }}" alt="BAC Office logo" class="auth-logo">
                </span>
            </div>
            <h2 class="text-3xl font-black text-slate-900 tracking-tight">Sign In</h2>
         
        </div>

        <div class="tabs-wrapper mb-8">
            <style>
                #authTabs { display: flex !important; position: relative; }
                #authTabs button { position: relative; z-index: 10; }
                #tabSlider {
                    position: absolute;
                    top: 6px;
                    bottom: 6px;
                    left: 6px;
                    width: calc(50% - 6px);
                    background: white;
                    border-radius: 9999px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    transition: transform 0.3s ease;
                    pointer-events: none;
                    z-index: 0;
                }
                #tabRegister.active ~ #tabSlider { transform: translateX(100%); }
                #authModal .tabs button.active { background: transparent !important; box-shadow: none !important; }
            </style>
            <div class="tabs flex p-1.5 bg-slate-100 rounded-full" id="authTabs">
                <button type="button" id="tabLogin" class="flex-1 py-2.5 text-sm font-bold rounded-full transition-all active" onclick="activateAuthTab('login')">Sign In</button>
                <button type="button" id="tabRegister" class="flex-1 py-2.5 text-sm font-bold rounded-full transition-all" onclick="activateAuthTab('register')">Register</button>
                <div id="tabSlider"></div>
            </div>
        </div>

        <form id="loginForm" method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf
            <div class="auth-field group">
                <div class="auth-input-icon-wrap auth-login-input-wrap">
                    <span class="auth-input-icon-slot" aria-hidden="true">
                        <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="4" width="20" height="16" rx="2"/>
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                        </svg>
                    </span>
                    <input type="email" name="email" class="auth-login-input" placeholder="Email address" required autocomplete="username" autocapitalize="off" spellcheck="false">
                </div>
                <div class="field-error text-xs text-red-500 mt-1 ml-1" data-error-for="email"></div>
            </div>
            <div class="auth-field group">
                <div class="password-field auth-input-icon-wrap auth-login-input-wrap">
                    <span class="auth-input-icon-slot" aria-hidden="true">
                        <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </span>
                    <input type="password" id="loginPassword" name="password" class="auth-login-input" placeholder="Password" required autocomplete="current-password">
                    <button type="button" class="password-toggle" onclick="togglePassword('loginPassword', this)" aria-label="Show password">
                        <svg class="password-icon password-icon-show" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                        <svg class="password-icon password-icon-hide hidden" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 3l18 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M6.7 6.8C4.5 8.3 3 12 3 12s3.6 6 9 6c2.1 0 3.9-.6 5.3-1.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M14.9 5.3A10.7 10.7 0 0 1 21 12s-1.1 1.9-3.1 3.7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
                <div class="field-error text-xs text-red-500 mt-1 ml-1" data-error-for="password"></div>
            </div>
            <div class="auth-options flex items-center justify-between px-1">
                <label class="remember-option flex items-center gap-3 cursor-pointer group text-sm select-none">
                    <input type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500 transition-all" value="1" {{ old('remember') ? 'checked' : '' }}>
                    <span class="text-slate-500 group-hover:text-slate-900 transition-colors font-medium">Remember me</span>
                </label>
                <a href="{{ route('password.request') }}" class="auth-link text-sm font-bold text-orange-600 hover:text-orange-700 transition-colors" onclick="activateAuthTab('forgot'); return false;">Forgot password?</a>
            </div>
            <button type="submit" class="w-full h-14 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-bold text-lg rounded-2xl shadow-xl shadow-orange-500/25 active:scale-[0.98] transition-all">Sign In</button>
        </form>

        <form id="verifyLoginForm" method="POST" action="{{ route('login.verify-code') }}" data-resend-url="{{ route('login.resend-code') }}" class="hidden">
            @csrf
            <div class="verify-modal-panel">
                <div class="verify-modal-icon" aria-hidden="true">
                    <svg class="gmail-logo" viewBox="0 0 64 48" aria-hidden="true">
                        <path fill="#4285F4" d="M48 48h10c3.31 0 6-2.69 6-6V13.62L48 25.62V48Z"/>
                        <path fill="#34A853" d="M0 13.62V42c0 3.31 2.69 6 6 6h10V25.62L0 13.62Z"/>
                        <path fill="#FBBC04" d="M48 6v19.62l16-12V9c0-5.08-5.8-7.97-9.85-4.92L48 6Z"/>
                        <path fill="#EA4335" d="M16 25.62V6l16 12 16-12v19.62l-16 12-16-12Z"/>
                        <path fill="#C5221F" d="M0 9v4.62l16 12V6L9.85 4.08C5.8 1.03 0 3.92 0 9Z"/>
                    </svg>
                </div>
                <h3>Email Verification</h3>
                <p>Enter the 6-digit verification code sent to your Gmail account.</p>
            </div>
            <input type="hidden" name="email" id="verifyLoginEmail">
            <div class="auth-field group">
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" class="verify-code-input" placeholder="000000" required autocomplete="one-time-code">
                <div class="field-error text-xs text-red-500 mt-1 ml-1" data-error-for="code"></div>
            </div>
            <button type="submit" class="verify-code-submit">Verify Code</button>
            <div class="verify-code-actions">
                <button type="button" id="resendLoginCodeButton">Resend code</button>
                <span class="text-slate-300" aria-hidden="true">|</span>
                <button type="button" onclick="activateAuthTab('login')">Back to Sign In</button>
            </div>
        </form>

        <form id="forgotPasswordForm" method="POST" action="{{ route('password.email') }}" class="hidden space-y-4">
            @csrf
            <p class="text-slate-500 text-sm mb-2 leading-relaxed">Enter your registered Gmail and we'll send you a password reset code.</p>
            <div class="auth-field group">
                <div class="auth-input-icon-wrap auth-forgot-input-wrap">
                    <span class="auth-input-icon-slot" aria-hidden="true">
                        <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="4" width="20" height="16" rx="2"/>
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                        </svg>
                    </span>
                    <input type="email" name="email" class="auth-forgot-input" placeholder="Email address" required>
                </div>
                <div class="field-error text-xs text-red-500 mt-1" data-error-for="email"></div>
            </div>
            <button type="submit" class="w-full py-3 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-bold rounded-xl shadow-lg active:scale-[0.98] transition-all">Send Code</button>
            <div class="text-center">
                <button type="button" class="text-sm font-bold text-slate-500 hover:text-orange-600 transition-colors" onclick="activateAuthTab('login')">Back to Sign In</button>
            </div>
        </form>

        <form id="forgotVerifyForm" method="POST" action="{{ route('password.verify-code') }}" class="hidden space-y-4">
            @csrf
            <p class="text-slate-500 text-sm mb-2 leading-relaxed">Enter the 6-digit code sent to your Gmail to continue to new password.</p>
            <input type="hidden" name="email" id="forgotVerifyEmail">
            <div class="auth-field group">
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" class="verify-code-input" placeholder="000000" required autocomplete="one-time-code">
                <div class="field-error text-xs text-red-500 mt-1" data-error-for="code"></div>
            </div>
            <button type="submit" class="w-full py-3 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-bold rounded-xl shadow-lg active:scale-[0.98] transition-all">Verify Code</button>
            <div class="text-center">
                <button type="button" class="text-sm font-bold text-slate-500 hover:text-orange-600 transition-colors" onclick="activateAuthTab('forgot')">Back</button>
            </div>
        </form>

        <form id="resetPasswordForm" method="POST" action="{{ route('password.update') }}" class="hidden">
            @csrf
            <div class="reset-modal-panel">
                <div class="reset-modal-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="10" rx="2"/>
                        <path d="M7 11V8a5 5 0 0 1 10 0v3"/>
                    </svg>
                </div>
                <h3>Reset Password</h3>
                <p>Create a new password for your account.</p>
            </div>
            <input type="hidden" name="token" value="">
            <input type="hidden" name="email" id="resetPasswordEmail">

            <div class="auth-field group">
                <div class="password-field auth-input-icon-wrap auth-login-input-wrap">
                    <span class="auth-input-icon-slot" aria-hidden="true">
                        <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </span>
                    <input type="password" id="resetPasswordNew" name="password" class="auth-login-input" placeholder="New password" required autocomplete="new-password">
                    <button type="button" class="password-toggle" onclick="togglePassword('resetPasswordNew', this)" aria-label="Show password">
                        <svg class="password-icon password-icon-show" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                        <svg class="password-icon password-icon-hide hidden" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 3l18 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M6.7 6.8C4.5 8.3 3 12 3 12s3.6 6 9 6c2.1 0 3.9-.6 5.3-1.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M14.9 5.3A10.7 10.7 0 0 1 21 12s-1.1 1.9-3.1 3.7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
                <div class="field-error text-xs text-red-500 mt-1" data-error-for="password"></div>
            </div>

            <div class="auth-field group">
                <div class="password-field auth-input-icon-wrap auth-login-input-wrap">
                    <span class="auth-input-icon-slot" aria-hidden="true">
                        <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6 9 17l-5-5"/>
                        </svg>
                    </span>
                    <input type="password" id="resetPasswordConfirm" name="password_confirmation" class="auth-login-input" placeholder="Confirm password" required autocomplete="new-password">
                    <button type="button" class="password-toggle" onclick="togglePassword('resetPasswordConfirm', this)" aria-label="Show password">
                        <svg class="password-icon password-icon-show" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                        <svg class="password-icon password-icon-hide hidden" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 3l18 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M6.7 6.8C4.5 8.3 3 12 3 12s3.6 6 9 6c2.1 0 3.9-.6 5.3-1.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M14.9 5.3A10.7 10.7 0 0 1 21 12s-1.1 1.9-3.1 3.7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
                <div class="field-error text-xs text-red-500 mt-1" data-error-for="password_confirmation"></div>
            </div>

            <button type="submit" class="w-full py-3 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-bold rounded-xl shadow-lg active:scale-[0.98] transition-all">Reset Password</button>
            <div class="text-center">
                <button type="button" class="text-sm font-bold text-slate-500 hover:text-orange-600 transition-colors" onclick="activateAuthTab('login')">Back to Sign In</button>
            </div>
        </form>

        <form id="registerForm" method="POST" action="{{ route('register') }}" class="hidden space-y-3.5">
            @csrf
            <div class="auth-register-note">
                <svg class="auth-register-note-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                </svg>
                <span class="auth-register-note-text" id="registerRoleNote">New accounts require manual review and approval by the BAC Office admin.</span>
            </div>

            <div class="auth-field group">
                <select name="role" id="registerRole" class="w-full h-10 px-4 bg-slate-50 border border-slate-200 focus:ring-4 focus:ring-orange-500/10 focus:border-orange-500 outline-none transition-all rounded-xl text-sm" required onchange="toggleRegisterRole(this.value)">
                    <option value="bidder" {{ $registerRole === 'bidder' ? 'selected' : '' }}>Register as bidder</option>
                    <option value="staff" {{ $registerRole === 'staff' ? 'selected' : '' }}>Register as staff</option>
                </select>
                <div class="field-error text-[10px] text-red-500 mt-0.5 ml-1" data-error-for="role"></div>
            </div>

            <div id="registerBidderFields" class="auth-register-fieldset {{ $registerRole === 'staff' ? 'hidden' : '' }}">
                <div class="auth-field group">
                    <input type="text" name="company" value="{{ old('company') }}" class="w-full h-10 px-4 bg-slate-50 border border-slate-200 focus:ring-4 focus:ring-orange-500/10 focus:border-orange-500 outline-none transition-all rounded-xl text-sm" placeholder="Company name" autocomplete="organization" {{ $registerRole !== 'staff' ? 'required' : '' }}>
                    <div class="field-error text-[10px] text-red-500 mt-0.5 ml-1" data-error-for="company"></div>
                </div>

                <div class="auth-field group">
                    <input type="text" name="registration_no" value="{{ old('registration_no') }}" class="w-full h-10 px-4 bg-slate-50 border border-slate-200 focus:ring-4 focus:ring-orange-500/10 focus:border-orange-500 outline-none transition-all rounded-xl text-sm" placeholder="Business Reg. Number" autocomplete="off" {{ $registerRole !== 'staff' ? 'required' : '' }}>
                    <div class="field-error text-[10px] text-red-500 mt-0.5 ml-1" data-error-for="registration_no"></div>
                </div>
            </div>

            <div id="registerStaffFields" class="auth-register-fieldset {{ $registerRole === 'staff' ? '' : 'hidden' }}">
                <div class="auth-field group">
                    <input type="text" name="name" value="{{ old('name') }}" class="w-full h-10 px-4 bg-slate-50 border border-slate-200 focus:ring-4 focus:ring-orange-500/10 focus:border-orange-500 outline-none transition-all rounded-xl text-sm" placeholder="Full name" autocomplete="name" {{ $registerRole === 'staff' ? 'required' : '' }}>
                    <div class="field-error text-[10px] text-red-500 mt-0.5 ml-1" data-error-for="name"></div>
                </div>

                <div class="auth-field group">
                    <select name="office" id="registerOffice" class="w-full h-10 px-4 bg-slate-50 border border-slate-200 focus:ring-4 focus:ring-orange-500/10 focus:border-orange-500 outline-none transition-all rounded-xl text-sm" {{ $registerRole === 'staff' ? 'required' : '' }}>
                        <option value="">Select office</option>
                        @foreach($staffOffices as $office)
                            <option value="{{ $office }}" {{ old('office') === $office ? 'selected' : '' }}>{{ $office }}</option>
                        @endforeach
                    </select>
                    <div class="field-error text-[10px] text-red-500 mt-0.5 ml-1" data-error-for="office"></div>
                </div>
            </div>

            <div class="auth-field group">
                <input type="email" name="email" value="{{ old('email') }}" class="w-full h-10 px-4 bg-slate-50 border border-slate-200 focus:ring-4 focus:ring-orange-500/10 focus:border-orange-500 outline-none transition-all rounded-xl text-sm" placeholder="Email address" required autocomplete="email" autocapitalize="off" spellcheck="false">
                <div class="field-error text-[10px] text-red-500 mt-0.5 ml-1" data-error-for="email"></div>
            </div>

            <div class="auth-field group">
                <div class="relative">
                    <input type="password" id="registerPassword" name="password" class="w-full h-10 px-4 pr-10 bg-slate-50 border border-slate-200 focus:ring-4 focus:ring-orange-500/10 focus:border-orange-500 outline-none transition-all rounded-xl text-sm" placeholder="Create password" required>
                    <button type="button" class="register-password-toggle" onclick="togglePassword('registerPassword', this)" aria-label="Show password">
                        <svg class="register-password-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <div class="field-error text-[10px] text-red-500 mt-0.5 ml-1" data-error-for="password"></div>
            </div>

            <button type="submit" class="w-full py-3 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-bold rounded-xl shadow-lg active:scale-[0.98] transition-all">Create Account</button>
        </form>
        
        <script>
            function toggleRegisterRole(role) {
                const bidderFields = document.getElementById('registerBidderFields');
                const staffFields = document.getElementById('registerStaffFields');
                const roleNote = document.getElementById('registerRoleNote');
                
                if (!bidderFields || !staffFields) return;
                
                const bidderInputs = bidderFields.querySelectorAll('input, select');
                const staffInputs = staffFields.querySelectorAll('input, select');
                
                if (role === 'staff') {
                    bidderFields.classList.add('hidden');
                    staffFields.classList.remove('hidden');
                    if (roleNote) roleNote.textContent = 'Staff accounts will be verified by the system administrator.';
                    
                    bidderInputs.forEach(i => i.removeAttribute('required'));
                    staffInputs.forEach(i => i.setAttribute('required', 'required'));
                } else {
                    bidderFields.classList.remove('hidden');
                    staffFields.classList.add('hidden');
                    if (roleNote) roleNote.textContent = 'New accounts require manual review and approval by the BAC Office admin.';
                    
                    staffInputs.forEach(i => i.removeAttribute('required'));
                    bidderInputs.forEach(i => i.setAttribute('required', 'required'));
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                const roleSelect = document.getElementById('registerRole');
                if (roleSelect) toggleRegisterRole(roleSelect.value); // Initialize required attributes correctly on load
            });
        </script>
    </div>
</div>
