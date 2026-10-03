@php
    $staffOffices = \App\Models\User::staffOfficeOptions();
    $registerRole = old('role', 'bidder');
    $bidderRequirementDocuments = \App\Support\BidderRegistrationRequirements::documents();
    $requiredBidderDocumentCount = count(array_filter($bidderRequirementDocuments, fn ($document) => (bool) ($document['required'] ?? false)));
    $bidderInformationRequirements = ['Company name', 'Business registration number', 'Email address', 'Contact person', 'Contact number', 'Business address'];
    $bidderDocumentIcons = [
        'business_permit' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 3V1h6v2M8 9h8M8 13h8M8 17h5"/>',
        'registration_certificate' => '<path d="M3 21h18M5 21V9h14v12M3 9l9-6 9 6M9 13h6M9 17h6"/>',
        'bir_certificate' => '<path d="M6 2h9l4 4v16H6zM15 2v5h5M9 11h6M9 15h6M9 19h4"/>',
        'mayors_permit' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 3V1h6v2M8 9h8M8 13h8M8 17h5"/>',
        'philgeps_registration' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.2 2.5 3.3 5.5 3.3 9s-1.1 6.5-3.3 9c-2.2-2.5-3.3-5.5-3.3-9S9.8 5.5 12 3Z"/>',
        'tax_clearance' => '<path d="M6 2h9l4 4v16H6zM15 2v5h5M9 12l2 2 4-4M9 18h6"/>',
        'omnibus_sworn_statement' => '<path d="M6 3h9l4 4v14H6zM15 3v5h5M9 13c1.5-2 3.5 2 5 0M9 18c2-1 3.5-1 5 0"/>',
        'authorized_representative_id' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="10" r="2"/><path d="M5.5 16c.7-2 4.3-2 5 0M13 9h5M13 13h5M13 17h3"/>',
        'other_supporting_documents' => '<path d="M7 7V4a2 2 0 0 1 2-2h8l4 4v12a2 2 0 0 1-2 2h-5M17 2v5h5M3 8h9a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H3z"/>',
    ];
    $mailIcon = '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>';
    $lockIcon = '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>';
@endphp

{{--
    Sign in / Register modal. auth.js drives it through the ids, the data-* hooks and
    the state classes it toggles (hidden, active, is-open, auth-card-wide,
    success-state, verification-state, forgot-*-state, input-error, has-file).
    Styles: resources/css/login.css ("Auth modal").
--}}
<div id="authModal" class="auth-modal hidden" role="dialog" aria-modal="true" aria-labelledby="authModalTitle">
    <div class="auth-card">
        <button type="button" class="auth-close" onclick="closeAuth()" aria-label="Close">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>

        <aside class="auth-side">
            <div class="auth-side-brand">
                <img src="{{ asset('Images/Logo.png') }}" alt="" class="auth-side-logo">
                <div>
                    <strong>SJBAC Procurement Portal</strong>
                    <span>Municipality of San Jose, Occidental Mindoro</span>
                </div>
            </div>

            <div class="auth-heading-copy">
                <span class="auth-kicker" id="authKicker">SJBAC</span>
                <h2 id="authModalTitle">Sign in</h2>
                <p id="authModalSubtitle">Use your registered email to continue.</p>
            </div>
        </aside>

        <div class="auth-main">
            <div id="authMessage"></div>

            @if(session('success'))
                <div class="alert success auth-session-alert" data-auto-hide="5000">
                    <span class="alert-icon" aria-hidden="true">
                        <span class="alert-loader"></span>
                        <svg class="alert-check" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
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

            <div class="auth-tabs" id="authTabs" role="tablist" aria-label="Account">
                <button type="button" id="tabLogin" class="auth-tab active" role="tab" onclick="activateAuthTab('login')">Sign In</button>
                <button type="button" id="tabRegister" class="auth-tab" role="tab" onclick="activateAuthTab('register')">Register</button>
            </div>

            {{-- Sign in --}}
            <form id="loginForm" method="POST" action="{{ route('login') }}" class="auth-form" novalidate>
                @csrf
                @if(session('scanned_project_title'))
                    {{-- Reached from "Login to Participate" or a project QR code. --}}
                    <div class="auth-participate-note" role="status">
                        <strong>Participating in: {{ session('scanned_project_title') }}</strong>
                        @if(session('scanned_project_reference'))<span>Reference No. {{ session('scanned_project_reference') }}</span>@endif
                        @if(session('scanned_project_signed_in_as'))
                            <span>You are signed in as {{ session('scanned_project_signed_in_as') }}. Log in with your bidder account to take part.</span>
                        @else
                            <span>Log in with your bidder account. You will be taken to this project after signing in.</span>
                        @endif
                    </div>
                @endif

                <div class="auth-field">
                    <label for="loginEmail" class="auth-label">Email address</label>
                    <div class="auth-input-wrap">
                        <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $mailIcon !!}</svg>
                        <input type="email" id="loginEmail" name="email" class="auth-input" placeholder="name@example.com" required autocomplete="username" autocapitalize="off" spellcheck="false">
                    </div>
                    <div class="field-error" data-error-for="email"></div>
                </div>

                <div class="auth-field">
                    <div class="auth-label-row">
                        <label for="loginPassword" class="auth-label">Password</label>
                        <a href="{{ route('password.request') }}" class="auth-link" onclick="activateAuthTab('forgot'); return false;">Forgot password?</a>
                    </div>
                    <div class="auth-input-wrap">
                        <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $lockIcon !!}</svg>
                        <input type="password" id="loginPassword" name="password" class="auth-input" placeholder="Password" required autocomplete="current-password">
                        @include('auth.partials.password-toggle', ['target' => 'loginPassword'])
                    </div>
                    <div class="field-error" data-error-for="password"></div>
                </div>

                <label class="auth-check">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                    <span>Keep me signed in on this device</span>
                </label>

                <button type="submit" class="auth-button" data-loading-text="Signing in...">
                    <span>Sign in</span>
                </button>

                <p class="auth-switch">Don't have an account? <button type="button" onclick="activateAuthTab('register')">Register as a bidder</button></p>
            </form>

            {{-- Bidder login: emailed code --}}
            <form id="verifyLoginForm" method="POST" action="{{ route('login.verify-code') }}" data-resend-url="{{ route('login.resend-code') }}" class="auth-form hidden">
                @csrf
                <div class="auth-step">
                    <span class="auth-step-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 13V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v12c0 1.1.9 2 2 2h8"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/><path d="m16 19 2 2 4-4"/></svg>
                    </span>
                    <h3>Check your email</h3>
                    <p>We sent a 6-digit code to <strong id="verifyLoginMaskedEmail">your email</strong>.</p>
                </div>
                <input type="hidden" name="email" id="verifyLoginEmail">
                <div class="auth-field">
                    <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" class="auth-input auth-code-input" placeholder="000000" required autocomplete="one-time-code" aria-label="6-digit verification code">
                    <div class="field-error" data-error-for="code"></div>
                </div>
                <button type="submit" class="auth-button" data-loading-text="Verifying...">Verify and sign in</button>
                <div class="auth-inline-actions">
                    <button type="button" id="resendLoginCodeButton">Resend code</button>
                    <button type="button" onclick="activateAuthTab('login')">Back to sign in</button>
                </div>
            </form>

            {{-- Forgot password: request a code --}}
            <form id="forgotPasswordForm" method="POST" action="{{ route('password.email') }}" class="auth-form hidden">
                @csrf
                <p class="auth-intro">Enter your registered email and we'll send you a code to reset your password.</p>
                <div class="auth-field">
                    <label for="forgotEmail" class="auth-label">Email address</label>
                    <div class="auth-input-wrap">
                        <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $mailIcon !!}</svg>
                        <input type="email" id="forgotEmail" name="email" class="auth-input" placeholder="name@example.com" required autocomplete="email">
                    </div>
                    <div class="field-error" data-error-for="email"></div>
                </div>
                <button type="submit" class="auth-button" data-loading-text="Sending code...">Send code</button>
                <div class="auth-inline-actions">
                    <button type="button" onclick="activateAuthTab('login')">Back to sign in</button>
                </div>
            </form>

            {{-- Forgot password: enter the code --}}
            <form id="forgotVerifyForm" method="POST" action="{{ route('password.verify-code') }}" data-resend-url="{{ route('password.email') }}" class="auth-form hidden">
                @csrf
                <p class="auth-intro">Enter the 6-digit code sent to your email to set a new password.</p>
                <input type="hidden" name="email" id="forgotVerifyEmail">
                <div class="auth-code-status" id="forgotCodeStatus" role="status" aria-live="polite">
                    <span>Code expires in</span>
                    <strong id="forgotCodeTimer">03:00</strong>
                </div>
                <div class="auth-field">
                    <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" class="auth-input auth-code-input" placeholder="000000" required autocomplete="one-time-code" aria-label="6-digit reset code">
                    <div class="field-error" data-error-for="code"></div>
                </div>
                <button type="submit" class="auth-button" data-loading-text="Verifying code...">Verify code</button>
                <div class="auth-inline-actions">
                    <button type="button" id="resendPasswordCodeButton" disabled>Resend code in 03:00</button>
                    <button type="button" onclick="activateAuthTab('forgot')">Back</button>
                </div>
            </form>

            {{-- Forgot password: new password --}}
            <form id="resetPasswordForm" method="POST" action="{{ route('password.update') }}" class="auth-form hidden">
                @csrf
                <input type="hidden" name="token" value="">
                <input type="hidden" name="email" id="resetPasswordEmail">
                <div class="auth-field">
                    <label for="resetPasswordNew" class="auth-label">New password</label>
                    <div class="auth-input-wrap">
                        <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $lockIcon !!}</svg>
                        <input type="password" id="resetPasswordNew" name="password" class="auth-input" placeholder="New password" required autocomplete="new-password" aria-describedby="resetPasswordHelp">
                        @include('auth.partials.password-toggle', ['target' => 'resetPasswordNew'])
                    </div>
                    <p id="resetPasswordHelp" class="auth-help">At least 8 characters with uppercase and lowercase letters, a number and a special character.</p>
                    <div class="field-error" data-error-for="password"></div>
                </div>
                <div class="auth-field">
                    <label for="resetPasswordConfirm" class="auth-label">Confirm password</label>
                    <div class="auth-input-wrap">
                        <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        <input type="password" id="resetPasswordConfirm" name="password_confirmation" class="auth-input" placeholder="Confirm password" required autocomplete="new-password">
                        @include('auth.partials.password-toggle', ['target' => 'resetPasswordConfirm'])
                    </div>
                    <div class="field-error" data-error-for="password_confirmation"></div>
                </div>
                <button type="submit" class="auth-button">Reset password</button>
                <div class="auth-inline-actions">
                    <button type="button" onclick="activateAuthTab('login')">Back to sign in</button>
                </div>
            </form>

            {{-- Register --}}
            {{-- With Blob storage, auth.js uploads the documents straight to it first (requests over 4.5 MB fail with 413). --}}
            <form id="registerForm" method="POST" action="{{ route('register') }}" enctype="multipart/form-data" novalidate class="auth-form auth-register hidden"
                @if(\App\Support\VercelBlob::enabled())
                    data-direct-upload-url="{{ route('register.upload-token') }}"
                    data-direct-upload-folder="{{ \App\Http\Controllers\AuthController::registrationUploadFolder(request()) }}"
                @endif>
                @csrf
                <div class="auth-field">
                    <label for="registerRole" class="auth-label">I am registering as</label>
                    <select name="role" id="registerRole" class="auth-input" required onchange="toggleRegisterRole(this.value)">
                        <option value="bidder" {{ $registerRole === 'bidder' ? 'selected' : '' }}>Bidder / supplier</option>
                        <option value="staff" {{ $registerRole === 'staff' ? 'selected' : '' }}>LGU staff</option>
                    </select>
                    <div class="field-error" data-error-for="role"></div>
                </div>

                <div id="registerBidderFields" class="auth-register-group {{ $registerRole === 'staff' ? 'hidden' : '' }}">
                    <details class="auth-requirements">
                        <summary>What you need to register</summary>
                        <div class="auth-requirements-body">
                            <div>
                                <h4>Bidder information</h4>
                                <ul>@foreach($bidderInformationRequirements as $requirement)<li>{{ $requirement }}</li>@endforeach</ul>
                            </div>
                            <div>
                                <h4>Documents (PDF, JPG or PNG)</h4>
                                <ul>@foreach($bidderRequirementDocuments as $document)<li>{{ $document['label'] }}</li>@endforeach</ul>
                            </div>
                        </div>
                    </details>

                    <p class="auth-section-title">Company details</p>
                    <div class="auth-grid">
                        <div class="auth-field">
                            <label for="registerCompany" class="auth-label">Company name</label>
                            <input type="text" id="registerCompany" name="company" value="{{ old('company') }}" class="auth-input" placeholder="e.g. Juan Dela Cruz Construction" autocomplete="organization" data-required-for-bidder="true" {{ $registerRole !== 'staff' ? 'required' : '' }}>
                            <div class="field-error" data-error-for="company"></div>
                        </div>
                        <div class="auth-field">
                            <label for="registerRegistrationNo" class="auth-label">Business registration no.</label>
                            <input type="text" id="registerRegistrationNo" name="registration_no" value="{{ old('registration_no') }}" class="auth-input" placeholder="DTI / SEC / CDA no." autocomplete="off" data-required-for-bidder="true" {{ $registerRole !== 'staff' ? 'required' : '' }}>
                            <div class="field-error" data-error-for="registration_no"></div>
                        </div>
                        <div class="auth-field">
                            <label for="registerContactPerson" class="auth-label">Contact person</label>
                            <input type="text" id="registerContactPerson" name="contact_person" value="{{ old('contact_person') }}" class="auth-input" placeholder="Authorized representative" autocomplete="name" data-required-for-bidder="true" {{ $registerRole !== 'staff' ? 'required' : '' }}>
                            <div class="field-error" data-error-for="contact_person"></div>
                        </div>
                        <div class="auth-field">
                            <label for="registerContactNumber" class="auth-label">Contact number</label>
                            <input type="tel" id="registerContactNumber" name="contact_number" value="{{ old('contact_number') }}" class="auth-input" placeholder="09XX XXX XXXX" autocomplete="tel" inputmode="tel" pattern="[0-9+\-\s()]{7,15}" maxlength="15" title="Enter a valid contact number (7-15 digits)" data-required-for-bidder="true" {{ $registerRole !== 'staff' ? 'required' : '' }}>
                            <div class="field-error" data-error-for="contact_number"></div>
                        </div>
                        <div class="auth-field auth-grid-full">
                            <label for="registerBusinessAddress" class="auth-label">Business address</label>
                            <textarea id="registerBusinessAddress" name="business_address" class="auth-input" rows="2" placeholder="Street, barangay, municipality, province" autocomplete="street-address" data-required-for-bidder="true" {{ $registerRole !== 'staff' ? 'required' : '' }}>{{ old('business_address') }}</textarea>
                            <div class="field-error" data-error-for="business_address"></div>
                        </div>
                    </div>

                    <section class="auth-documents" aria-labelledby="registerDocumentsTitle">
                        <div class="auth-documents-head">
                            <p class="auth-section-title" id="registerDocumentsTitle">Eligibility documents</p>
                            <span class="auth-documents-progress" data-register-upload-progress>0 of {{ $requiredBidderDocumentCount }} required documents uploaded.</span>
                        </div>
                        <ul class="auth-document-list">
                            @foreach($bidderRequirementDocuments as $key => $document)
                                @php $inputId = 'registrationDocument' . \Illuminate\Support\Str::studly($key); @endphp
                                <li class="auth-document" data-register-upload-field>
                                    <input type="file" id="{{ $inputId }}" name="registration_documents[{{ $key }}]" class="register-file-input"
                                           accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                           data-file-label="{{ $document['label'] }}" data-required-for-bidder="{{ $document['required'] ? 'true' : 'false' }}"
                                           aria-labelledby="{{ $inputId }}Label" aria-describedby="{{ $inputId }}Help"
                                           {{ $registerRole !== 'staff' && $document['required'] ? 'required' : '' }}>
                                    <div class="register-upload-card">
                                        <span class="auth-document-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $bidderDocumentIcons[$key] ?? $bidderDocumentIcons['other_supporting_documents'] !!}</svg>
                                            <span class="auth-document-check" data-upload-check hidden>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                            </span>
                                        </span>
                                        <span class="auth-document-text">
                                            <strong id="{{ $inputId }}Label">{{ $document['label'] }}@if($document['required'])<span class="auth-required" aria-hidden="true"> *</span>@else <em>(if requested)</em>@endif</strong>
                                            <small id="{{ $inputId }}Help" data-upload-empty>PDF, JPG or PNG &middot; max 10 MB</small>
                                            <small class="auth-document-file" data-upload-selected hidden><span data-upload-filename></span> &middot; <span data-upload-filesize></span></small>
                                        </span>
                                        <span class="auth-document-actions">
                                            {{-- .has-file (set by auth.js) swaps Upload for Replace / Remove. --}}
                                            <button type="button" class="auth-button-small auth-document-upload" data-upload-trigger>Upload</button>
                                            <button type="button" class="auth-button-small is-quiet auth-document-replace" data-upload-trigger>Replace</button>
                                            <button type="button" class="auth-button-small is-quiet auth-document-remove" data-upload-remove>Remove</button>
                                        </span>
                                    </div>
                                    <div class="field-error" data-error-for="registration_documents.{{ $key }}"></div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                </div>

                <div id="registerStaffFields" class="auth-register-group {{ $registerRole === 'staff' ? '' : 'hidden' }}">
                    <div class="auth-grid">
                        <div class="auth-field">
                            <label for="registerStaffName" class="auth-label">Full name</label>
                            <input type="text" id="registerStaffName" name="name" value="{{ old('name') }}" class="auth-input" placeholder="Full name" autocomplete="name" {{ $registerRole === 'staff' ? 'required' : '' }}>
                            <div class="field-error" data-error-for="name"></div>
                        </div>
                        <div class="auth-field">
                            <label for="registerOffice" class="auth-label">Office</label>
                            <select name="office" id="registerOffice" class="auth-input" {{ $registerRole === 'staff' ? 'required' : '' }}>
                                <option value="">Select office</option>
                                @foreach($staffOffices as $office)
                                    <option value="{{ $office }}" {{ old('office') === $office ? 'selected' : '' }}>{{ $office }}</option>
                                @endforeach
                            </select>
                            <div class="field-error" data-error-for="office"></div>
                        </div>
                    </div>
                </div>

                <p class="auth-section-title">Account</p>
                <div class="auth-grid">
                    <div class="auth-field">
                        <label for="registerEmail" class="auth-label">Email address</label>
                        <input type="email" id="registerEmail" name="email" value="{{ old('email') }}" class="auth-input" placeholder="name@example.com" required autocomplete="email" autocapitalize="off" spellcheck="false">
                        <div class="field-error" data-error-for="email"></div>
                    </div>
                    <div class="auth-field">
                        <label for="registerPassword" class="auth-label">Create password</label>
                        <div class="auth-input-wrap is-plain">
                            <input type="password" id="registerPassword" name="password" class="auth-input" placeholder="Create password" required autocomplete="new-password" aria-describedby="registerPasswordHelp">
                            @include('auth.partials.password-toggle', ['target' => 'registerPassword'])
                        </div>
                        <div class="field-error" data-error-for="password"></div>
                    </div>
                    <p id="registerPasswordHelp" class="auth-help auth-grid-full">At least 8 characters with uppercase and lowercase letters, a number and a special character.</p>
                </div>

                <div class="auth-register-submit">
                    <p>Your account is reviewed by the BAC Secretariat before you can bid.</p>
                    <button type="submit" class="auth-button" data-loading-text="Submitting...">Submit registration</button>
                </div>
            </form>

            <script>
                function setRegisterGroupRequired(inputs, active, dataKey) {
                    inputs.forEach(function (input) {
                        const optional = input.dataset[dataKey] === 'false';
                        if (active && !optional) input.setAttribute('required', 'required');
                        else input.removeAttribute('required');
                    });
                }

                function toggleRegisterRole(role) {
                    const bidderFields = document.getElementById('registerBidderFields');
                    const staffFields = document.getElementById('registerStaffFields');
                    if (!bidderFields || !staffFields) return;

                    const bidderInputs = bidderFields.querySelectorAll('input, select, textarea');
                    const staffInputs = staffFields.querySelectorAll('input, select, textarea');
                    const isStaff = role === 'staff';

                    bidderFields.classList.toggle('hidden', isStaff);
                    staffFields.classList.toggle('hidden', !isStaff);
                    setRegisterGroupRequired(bidderInputs, !isStaff, 'requiredForBidder');
                    setRegisterGroupRequired(staffInputs, isStaff, 'requiredForStaff');
                }

                document.addEventListener('DOMContentLoaded', function () {
                    const roleSelect = document.getElementById('registerRole');
                    if (roleSelect) toggleRegisterRole(roleSelect.value);
                });
            </script>
        </div>
    </div>
</div>
