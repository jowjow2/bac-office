// Keep in sync with the transition durations set on #authModal / .auth-card in login.css.
const AUTH_MODAL_ANIMATION_DURATION = 260;

let authModalIsClosing = false;
let authModalCloseTimer = null;
let authModalTransitionCleanup = null;
let authModalPreviousBodyOverflow = '';
let authModalScrollLocked = false;

function prefersReducedMotion() {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

function isAuthModalVisible(modal) {
    return !modal.classList.contains('hidden') && modal.style.display !== 'none';
}

function lockAuthModalScroll() {
    if (authModalScrollLocked) return;
    authModalPreviousBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    authModalScrollLocked = true;
}

function unlockAuthModalScroll() {
    if (!authModalScrollLocked) return;
    document.body.style.overflow = authModalPreviousBodyOverflow;
    authModalScrollLocked = false;
}

function clearAuthModalCloseTimer() {
    if (authModalCloseTimer) {
        window.clearTimeout(authModalCloseTimer);
        authModalCloseTimer = null;
    }

    if (authModalTransitionCleanup) {
        authModalTransitionCleanup();
        authModalTransitionCleanup = null;
    }
}

window.openLogin = function () {
    const modal = document.getElementById('authModal');
    const card = modal?.querySelector('.auth-card');
    if (!modal || !card) return;

    // Cancel any in-flight close animation so it can't hide the modal we're reopening.
    authModalIsClosing = false;
    clearAuthModalCloseTimer();

    const wasHidden = !isAuthModalVisible(modal);

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.style.display = 'flex';

    if (wasHidden) {
        lockAuthModalScroll();

        // Force a reflow so the enter transition plays from its closed state
        // instead of jumping straight to open (can't transition from display:none).
        void modal.offsetWidth;
    }

    // Adding a class the element already has is a no-op, so re-entering this
    // function on every tab switch (activateAuthTab always calls openLogin
    // first) never restarts the modal's own open animation.
    modal.classList.add('is-open');
};

const AUTH_MESSAGE_HIDE_DELAY = 5000;
const AUTH_MESSAGE_FADE_DURATION = 350;
const AUTH_SUCCESS_REDIRECT_DELAY = 1500;
const AUTH_FORM_ANIMATION_DURATION = 320;
const AUTH_HEADER_ANIMATION_DURATION = 260;
const PASSWORD_CODE_TTL_SECONDS = 180;

let passwordCodeTimerId = null;
let passwordCodeExpiresAt = 0;
let passwordCodeStatusPrefix = 'Code sent. Expires in';

window.activateAuthTab = function (tab = 'login') {
    window.openLogin();
    // A message from the previous step (e.g. register errors) doesn't belong to the next one.
    clearAuthMessage();
    window.switchTab(tab);
};

window.closeAuth = function () {
    const modal = document.getElementById('authModal');
    const card = modal?.querySelector('.auth-card');
    if (!modal || !card) return;
    if (authModalIsClosing || !isAuthModalVisible(modal)) return;

    authModalIsClosing = true;
    clearAuthModalCloseTimer();

    const finalizeClose = () => {
        modal.classList.remove('flex', 'is-open');
        modal.classList.add('hidden');
        modal.style.display = 'none';
        unlockAuthModalScroll();
        authModalIsClosing = false;
        authModalTransitionCleanup = null;
    };

    if (prefersReducedMotion()) {
        finalizeClose();
    } else {
        modal.classList.remove('is-open');

        const onTransitionEnd = (event) => {
            if (event.target !== modal || event.propertyName !== 'opacity') return;
            clearAuthModalCloseTimer();
            finalizeClose();
        };

        modal.addEventListener('transitionend', onTransitionEnd);
        authModalTransitionCleanup = () => modal.removeEventListener('transitionend', onTransitionEnd);

        // Safety net in case transitionend never fires (e.g. the element was
        // detached or the transition was interrupted by other style changes).
        authModalCloseTimer = window.setTimeout(() => {
            authModalTransitionCleanup?.();
            authModalTransitionCleanup = null;
            authModalCloseTimer = null;
            finalizeClose();
        }, AUTH_MODAL_ANIMATION_DURATION + 100);
    }

    stopPasswordCodeCountdown();
};

function setAuthSuccessState(active) {
    const modal = document.getElementById('authModal');
    if (!modal) return;

    modal.classList.toggle('success-state', active);

    if (active) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.style.display = 'flex';
    }
}

function setRegisterRoleFields() {
    const roleSelect = document.getElementById('registerRole');
    const bidderFields = document.getElementById('registerBidderFields');
    const staffFields = document.getElementById('registerStaffFields');
    const roleNote = document.getElementById('registerRoleNote');

    if (!roleSelect || !bidderFields || !staffFields) {
        return;
    }

    const isStaff = roleSelect.value === 'staff';
    bidderFields.classList.toggle('hidden', isStaff);
    staffFields.classList.toggle('hidden', !isStaff);

    bidderFields.querySelectorAll('input, select, textarea').forEach((input) => {
        input.required = !isStaff && input.dataset.requiredForBidder !== 'false';
    });

    staffFields.querySelectorAll('input, select, textarea').forEach((input) => {
        input.required = isStaff && input.dataset.requiredForStaff !== 'false';
    });

    if (roleNote) {
        roleNote.textContent = isStaff
            ? 'Admin approval required. Your account will be reviewed before activation.'
            : 'Admin approval required. Your account will be reviewed before activation.';
    }
}

function replayAuthAnimation(element, className, duration) {
    if (!element) return;

    element.classList.remove(className);
    void element.offsetWidth;
    element.classList.add(className);

    window.setTimeout(() => {
        element.classList.remove(className);
    }, duration);
}

function animateVisibleAuthForm(form) {
    replayAuthAnimation(form, 'auth-form-enter', AUTH_FORM_ANIMATION_DURATION);
}

function clearAuthFormAnimation(...forms) {
    forms.filter(Boolean).forEach((form) => {
        form.classList.remove('auth-form-enter');
    });
}

function setButtonLoading(button, active, loadingText = 'Loading...') {
    if (!button) return;

    if (active) {
        if (!button.dataset.originalHtml) {
            button.dataset.originalHtml = button.innerHTML;
        }

        const spinner = document.createElement('span');
        spinner.className = 'auth-button-spinner';
        spinner.setAttribute('aria-hidden', 'true');

        const text = document.createElement('span');
        text.textContent = loadingText;

        button.replaceChildren(spinner, text);
        button.disabled = true;
        button.classList.add('is-loading');
        return;
    }

    if (button.dataset.originalHtml) {
        button.innerHTML = button.dataset.originalHtml;
        delete button.dataset.originalHtml;
    }

    button.disabled = false;
    button.classList.remove('is-loading');
}

function setAuthFormLoading(form, active) {
    const submitButton = form?.querySelector('button[type="submit"]');
    const loadingText = submitButton?.dataset.loadingText || 'Loading...';

    setButtonLoading(submitButton, active, loadingText);
    form?.classList.toggle('is-submitting', active);
}

function formatPasswordCodeTime(seconds) {
    const safeSeconds = Math.max(0, Math.ceil(seconds));
    const minutes = Math.floor(safeSeconds / 60);
    const remainder = String(safeSeconds % 60).padStart(2, '0');

    return `${minutes}:${remainder}`;
}


function maskVerificationEmail(email) {
    const value = String(email || '').trim();
    const [localPart, domain] = value.split('@');

    if (!localPart || !domain) {
        return 'your email';
    }

    const visible = localPart.slice(0, 1);
    return `${visible}***@${domain}`;
}

function updateLoginVerificationEmail(email) {
    const maskedEmail = document.getElementById('verifyLoginMaskedEmail');

    if (maskedEmail) {
        maskedEmail.textContent = maskVerificationEmail(email);
    }
}
function updatePasswordCodeCountdown() {
    const status = document.getElementById('forgotCodeStatus');
    const label = status?.querySelector('span');
    const timer = document.getElementById('forgotCodeTimer');
    const resendButton = document.getElementById('resendPasswordCodeButton');
    const remainingSeconds = Math.max(0, Math.ceil((passwordCodeExpiresAt - Date.now()) / 1000));
    const formattedTime = formatPasswordCodeTime(remainingSeconds);

    if (label) {
        label.textContent = remainingSeconds > 0
            ? passwordCodeStatusPrefix
            : 'Code expired. Request a new code.';
    }

    if (timer) {
        timer.textContent = formattedTime;
    }

    if (status) {
        status.classList.toggle('is-expired', remainingSeconds <= 0);
    }

    if (resendButton && !resendButton.classList.contains('is-loading')) {
        resendButton.disabled = remainingSeconds > 0;
        resendButton.textContent = remainingSeconds > 0
            ? `Resend code in ${formattedTime}`
            : 'Send New Code';
    }

    if (remainingSeconds <= 0 && passwordCodeTimerId) {
        window.clearInterval(passwordCodeTimerId);
        passwordCodeTimerId = null;
    }
}

function startPasswordCodeCountdown(seconds = PASSWORD_CODE_TTL_SECONDS, prefix = 'Code sent. Expires in') {
    if (passwordCodeTimerId) {
        window.clearInterval(passwordCodeTimerId);
    }

    passwordCodeStatusPrefix = prefix;
    passwordCodeExpiresAt = Date.now() + Math.max(1, Number(seconds) || PASSWORD_CODE_TTL_SECONDS) * 1000;
    updatePasswordCodeCountdown();
    passwordCodeTimerId = window.setInterval(updatePasswordCodeCountdown, 1000);
}

function expirePasswordCodeCountdown() {
    if (passwordCodeTimerId) {
        window.clearInterval(passwordCodeTimerId);
        passwordCodeTimerId = null;
    }

    passwordCodeExpiresAt = Date.now();
    updatePasswordCodeCountdown();
}

function stopPasswordCodeCountdown() {
    if (passwordCodeTimerId) {
        window.clearInterval(passwordCodeTimerId);
        passwordCodeTimerId = null;
    }

    passwordCodeExpiresAt = 0;
}

function setAuthHeader(tab) {
    const title = document.getElementById('authModalTitle');
    const subtitle = document.getElementById('authModalSubtitle');
    const kicker = document.getElementById('authKicker');
    const heading = document.querySelector('#authModal .auth-heading-copy');

    if (!title || !subtitle || !kicker) {
        return;
    }

    const content = {
        login: {
            kicker: 'SJBAC',
            title: 'Sign in',
            subtitle: 'Use your registered email to continue.',
        },
        register: {
            kicker: 'SJBAC',
            title: 'Register',
            subtitle: 'Submit your account details for SJBAC review.',
        },
        forgot: {
            kicker: 'Account recovery',
            title: 'Forgot password',
            subtitle: 'Get a verification code using your registered email.',
        },
        forgot_verify: {
            kicker: 'Account recovery',
            title: 'Verify code',
            subtitle: 'Enter the code sent to your email.',
        },
        reset_password: {
            kicker: 'Account recovery',
            title: 'Reset password',
            subtitle: 'Create a new password for your account.',
        },
        verify: {
            kicker: 'Email verification',
            title: 'Verify login',
            subtitle: 'Enter the code sent to your email.',
        },
    }[tab] || {
        kicker: 'SJBAC',
        title: 'Bids and Awards Committee Portal',
        subtitle: 'Secure access to procurement, bidding, and award management services.',
    };

    kicker.textContent = content.kicker;
    title.textContent = content.title;
    subtitle.textContent = content.subtitle;
    replayAuthAnimation(heading, 'auth-header-enter', AUTH_HEADER_ANIMATION_DURATION);
}

window.switchTab = function (tab) {
    const login = document.getElementById('loginForm');
    const verify = document.getElementById('verifyLoginForm');
    const register = document.getElementById('registerForm');
    const forgot = document.getElementById('forgotPasswordForm');
    const forgotVerify = document.getElementById('forgotVerifyForm');
    const resetPassword = document.getElementById('resetPasswordForm');
    const modal = document.getElementById('authModal');
    const authCard = document.querySelector('#authModal .auth-card');
    const tabLogin = document.getElementById('tabLogin');
    const tabRegister = document.getElementById('tabRegister');
    const tabs = document.getElementById('authTabs');

    if (!login || !register || !tabLogin || !tabRegister) return;
    if (!['login', 'verify', 'register', 'forgot', 'forgot_verify', 'reset_password'].includes(tab)) tab = 'login';

    clearAuthFormAnimation(login, verify, register, forgot, forgotVerify, resetPassword);
    setAuthHeader(tab);

    clearFieldErrors(login);
    if (verify) clearFieldErrors(verify);
    clearFieldErrors(register);
    if (forgot) clearFieldErrors(forgot);
    if (forgotVerify) clearFieldErrors(forgotVerify);
    if (resetPassword) clearFieldErrors(resetPassword);

    login.classList.add('hidden');
    if (verify) verify.classList.add('hidden');
    register.classList.add('hidden');
    if (forgot) forgot.classList.add('hidden');
    if (forgotVerify) forgotVerify.classList.add('hidden');
    if (resetPassword) resetPassword.classList.add('hidden');

    tabLogin.classList.remove('active');
    tabRegister.classList.remove('active');
    if (modal) {
        modal.classList.toggle('verification-state', tab === 'verify');
        modal.classList.toggle('reset-password-state', tab === 'reset_password');
        modal.classList.toggle('forgot-password-state', ['forgot', 'forgot_verify', 'reset_password'].includes(tab));
        modal.classList.toggle('forgot-verify-state', tab === 'forgot_verify');
    }

    if (authCard) {
        authCard.classList.remove('auth-card-wide');
    }

    if (tab === 'login') {
        login.classList.remove('hidden');
        animateVisibleAuthForm(login);
        tabLogin.classList.add('active');
        if (tabs) tabs.classList.remove('hidden');
    } else if (tab === 'verify' && verify) {
        verify.classList.remove('hidden');
        animateVisibleAuthForm(verify);
        tabLogin.classList.add('active');
        if (tabs) tabs.classList.add('hidden');
    } else if (tab === 'register') {
        register.classList.remove('hidden');
        animateVisibleAuthForm(register);
        tabRegister.classList.add('active');
        if (tabs) tabs.classList.remove('hidden');
        if (authCard) authCard.classList.add('auth-card-wide');
        setRegisterRoleFields();
    } else if (tab === 'forgot' && forgot) {
        forgot.classList.remove('hidden');
        animateVisibleAuthForm(forgot);
        if (tabs) tabs.classList.add('hidden');
    } else if (tab === 'forgot_verify' && forgotVerify) {
        forgotVerify.classList.remove('hidden');
        animateVisibleAuthForm(forgotVerify);
        if (tabs) tabs.classList.add('hidden');
    } else if (tab === 'reset_password' && resetPassword) {
        resetPassword.classList.remove('hidden');
        animateVisibleAuthForm(resetPassword);
        if (tabs) tabs.classList.add('hidden');
    }

    if (authCard) {
        window.requestAnimationFrame(() => {
            authCard.scrollTop = 0;
        });
    }

    if (tab !== 'forgot_verify') {
        stopPasswordCodeCountdown();
    }
};

window.togglePassword = function (inputId, button) {
    const input = document.getElementById(inputId);
    if (!input || !button) return;

    const showIcon = button.querySelector('.password-icon-show');
    const hideIcon = button.querySelector('.password-icon-hide');
    const isPassword = input.type === 'password';

    input.type = isPassword ? 'text' : 'password';

    if (showIcon && hideIcon) {
        showIcon.classList.toggle('hidden', isPassword);
        hideIcon.classList.toggle('hidden', !isPassword);
    }

    button.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    button.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
};

function clearFieldErrors(form) {
    if (!form) return;

    form.querySelectorAll('input, textarea, select').forEach((input) => {
        input.classList.remove('input-error');
    });

    form.querySelectorAll('.field-error').forEach((errorBox) => {
        errorBox.textContent = '';
    });
}

function cssEscape(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
        return window.CSS.escape(value);
    }

    return String(value).replace(/"/g, '\\"');
}

function fieldNameToErrorKey(name) {
    return String(name || '').replace(/\[([^\]]+)]/g, '.$1').replace(/\[]$/, '');
}

function fieldKeyToBracketName(field) {
    return String(field || '').replace(/\.([^.]+)/g, '[$1]');
}

function findFormField(form, field) {
    const baseField = field.includes('.') ? field.split('.')[0] : field;
    const candidates = [
        field,
        fieldKeyToBracketName(field),
        baseField,
        `${baseField}[]`,
    ].filter(Boolean);

    for (const candidate of candidates) {
        const input = form.querySelector(`[name="${cssEscape(candidate)}"]`);

        if (input) {
            return input;
        }
    }

    return null;
}

function showFieldErrors(form, errors) {
    if (!form || !errors) return;

    Object.entries(errors).forEach(([field, messages]) => {
        const baseField = field.includes('.') ? field.split('.')[0] : field;
        const input = findFormField(form, field);
        const errorBox = form.querySelector(`[data-error-for="${field}"]`) || form.querySelector(`[data-error-for="${baseField}"]`);
        const message = Array.isArray(messages) ? messages[0] : messages;

        if (input) {
            input.classList.add('input-error');
        }

        if (errorBox) {
            errorBox.textContent = message || '';
        }
    });
}

function validateLoginForm(form) {
    if (!form) return true;

    clearAuthMessage();
    clearFieldErrors(form);

    const errors = {};
    const emailInput = form.querySelector('[name="email"]');
    const passwordInput = form.querySelector('[name="password"]');
    const email = String(emailInput?.value || '').trim();
    const password = String(passwordInput?.value || '');

    if (email === '') {
        addClientFieldError(errors, 'email', 'Email is required.');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        addClientFieldError(errors, 'email', 'Please enter a valid email address.');
    }

    if (password.trim() === '') {
        addClientFieldError(errors, 'password', 'Password is required.');
    }

    if (Object.keys(errors).length === 0) {
        return true;
    }

    showFieldErrors(form, errors);
    form.querySelector('.input-error')?.focus();

    return false;
}

const REGISTER_ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];
const REGISTER_FIELD_LABELS = {
    company: 'Company name',
    registration_no: 'Business registration number',
    contact_person: 'Contact person',
    contact_number: 'Contact number',
    business_address: 'Business address',
    email: 'Email address',
    password: 'Password',
};

function addClientFieldError(errors, key, message) {
    if (!errors[key]) {
        errors[key] = [message];
    }
}

function isInactiveRegisterField(input) {
    return Boolean(input.closest('.hidden'));
}

function fileHasAllowedType(file) {
    const extension = (file.name.split('.').pop() || '').toLowerCase();

    return REGISTER_ALLOWED_EXTENSIONS.includes(extension);
}

function formatRegisterUploadFileSize(bytes) {
    const size = Number(bytes) || 0;

    if (size < 1024) {
        return `${size} B`;
    }

    const units = ['KB', 'MB', 'GB'];
    let value = size / 1024;
    let unitIndex = 0;

    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }

    const displayValue = value >= 10 || unitIndex === 0
        ? Math.round(value)
        : Math.round(value * 10) / 10;

    return `${displayValue} ${units[unitIndex]}`;
}

function getRegisterUploadInputs(form) {
    return Array.from(form?.querySelectorAll('.register-file-input') || []);
}

function updateRegisterUploadCard(input) {
    const field = input?.closest('[data-register-upload-field]');
    if (!input || !field) return;

    const file = input.files?.[0] || null;
    const hasFile = Boolean(file);
    const card = field.querySelector('.register-upload-card');
    const emptyState = field.querySelector('[data-upload-empty]');
    const selectedState = field.querySelector('[data-upload-selected]');
    const checkIcon = field.querySelector('[data-upload-check]');
    const filename = field.querySelector('[data-upload-filename]');
    const filesize = field.querySelector('[data-upload-filesize]');
    const label = input.dataset.fileLabel || 'Document';

    field.classList.toggle('has-file', hasFile);

    if (emptyState) emptyState.hidden = hasFile;
    if (selectedState) selectedState.hidden = !hasFile;
    if (checkIcon) checkIcon.hidden = !hasFile;

    if (filename) filename.textContent = file?.name || '';
    if (filesize) filesize.textContent = file ? formatRegisterUploadFileSize(file.size) : '';

    if (card) {
        card.setAttribute('aria-label', hasFile ? `${label} selected: ${file.name}` : `${label}. No file selected.`);
    }
}

function updateRegisterUploadProgress(form) {
    const progress = form?.querySelector('[data-register-upload-progress]');
    if (!progress) return;

    const requiredInputs = getRegisterUploadInputs(form)
        .filter((input) => input.dataset.requiredForBidder !== 'false');
    const uploadedCount = requiredInputs
        .filter((input) => (input.files || []).length > 0)
        .length;
    const totalCount = requiredInputs.length;
    const noun = totalCount === 1 ? 'document' : 'documents';

    progress.textContent = `${uploadedCount} of ${totalCount} required ${noun} uploaded.`;
}

function refreshRegisterUploadUI(form) {
    getRegisterUploadInputs(form).forEach(updateRegisterUploadCard);
    updateRegisterUploadProgress(form);
}

function clearRegisterUploadInput(input) {
    if (!input) return;

    input.value = '';
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

function validateRegisterForm(form) {
    if (!form) return true;

    clearAuthMessage();
    clearFieldErrors(form);

    const errors = {};
    const controls = Array.from(form.querySelectorAll('input, textarea, select'))
        .filter((input) => !input.disabled && input.type !== 'hidden' && !isInactiveRegisterField(input));

    controls.forEach((input) => {
        const key = fieldNameToErrorKey(input.name);
        const label = input.dataset.fileLabel || REGISTER_FIELD_LABELS[key] || input.placeholder || 'This field';

        if (input.type === 'file') {
            const files = Array.from(input.files || []);

            if (input.required && files.length === 0) {
                addClientFieldError(errors, key, `${label} must be uploaded.`);
                return;
            }

            if (files.some((file) => !fileHasAllowedType(file))) {
                addClientFieldError(errors, key, `${label} must be a PDF, JPG, JPEG, or PNG file.`);
            }

            return;
        }

        const value = String(input.value || '').trim();

        if (input.required && value === '') {
            addClientFieldError(errors, key, `${label} is required.`);
            return;
        }

        if (input.type === 'email' && value !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            addClientFieldError(errors, key, 'Please enter a valid email address.');
        }

        if (input.name === 'password' && value !== '' && value.length < 6) {
            addClientFieldError(errors, key, 'Password must be at least 6 characters.');
        }
    });

    if (Object.keys(errors).length === 0) {
        return true;
    }

    showFieldErrors(form, errors);
    showAuthMessage('error', 'Please complete the highlighted registration fields.');

    const firstErrorKey = Object.keys(errors)[0];
    const firstInput = findFormField(form, firstErrorKey);

    if (firstInput) {
        const uploadFocusTarget = firstInput.type === 'file'
            ? firstInput.closest('[data-register-upload-field]')?.querySelector('[data-upload-trigger], .register-upload-card')
            : null;
        const focusTarget = uploadFocusTarget || firstInput;

        focusTarget.focus({ preventScroll: true });
        focusTarget.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }

    return false;
}

function scheduleAuthAlertFade(alert, onHidden, delay = AUTH_MESSAGE_HIDE_DELAY) {
    if (!alert) return;

    window.clearTimeout(window.authMessageTimeout);
    window.clearTimeout(window.authMessageFadeTimeout);

    window.authMessageTimeout = window.setTimeout(() => {
        alert.classList.add('fade-out');

        window.authMessageFadeTimeout = window.setTimeout(() => {
            if (typeof onHidden === 'function') {
                onHidden();
            }
        }, AUTH_MESSAGE_FADE_DURATION);
    }, delay);
}

function renderAuthMessage(type, message) {
    const box = document.getElementById('authMessage');
    if (!box) return null;

    const alert = document.createElement('div');
    alert.className = `alert ${type}`;

    if (type === 'success') {
        const icon = document.createElement('span');
        icon.className = 'alert-icon';
        icon.innerHTML = `
            <span class="alert-loader" aria-hidden="true"></span>
            <svg class="alert-check" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M20 6L9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        `;

        const text = document.createElement('span');
        text.className = 'alert-text';
        text.textContent = message;

        alert.append(icon, text);
    } else {
        alert.textContent = message;
    }

    box.replaceChildren(alert);

    return alert;
}

function showAuthMessage(type, message, options = {}) {
    const alert = renderAuthMessage(type, message);
    if (!alert) return;

    if (type === 'success') {
        setAuthSuccessState(true);
    } else {
        setAuthSuccessState(false);
    }

    if (options.autoHideMs) {
        scheduleAuthAlertFade(alert, () => {
            clearAuthMessage();

            if (options.restoreModalOnHide !== false) {
                setAuthSuccessState(false);
            }

            if (typeof options.onHidden === 'function') {
                options.onHidden();
            }
        }, options.autoHideMs);
        return;
    }

    window.clearTimeout(window.authMessageTimeout);
    window.clearTimeout(window.authMessageFadeTimeout);
}

function clearAuthMessage() {
    const box = document.getElementById('authMessage');
    if (!box) return;
    box.innerHTML = '';
}

async function maybeStoreLoginCredential(form) {
    if (!form || !window.PasswordCredential || !navigator.credentials?.store) {
        return;
    }

    const email = form.querySelector('[name="email"]')?.value?.trim();
    const password = form.querySelector('[name="password"]')?.value ?? '';

    if (!email || password.trim() === '') {
        return;
    }

    try {
        const credential = new PasswordCredential({
            id: email,
            password: password,
            name: email,
        });

        await navigator.credentials.store(credential);
    } catch (error) {
        // Some browsers block or ignore password storage requests; login should continue normally.
    }
}

/*
 * Registration documents go from the browser straight to private Blob storage
 * (@vercel/blob "upload"); the form then carries their references only. A
 * serverless request takes at most 4.5 MB, which a full set of eligibility
 * documents exceeds (413). The server issues one token per file, accepts only
 * this browser's folder and runs the usual file checks before saving.
 */
async function uploadRegistrationDocuments(form, formData, csrfToken) {
    const inputs = Array.from(form.querySelectorAll('input[type="file"][name^="registration_documents["]'))
        .filter((input) => input.files && input.files.length);
    if (!inputs.length || formData.get('role') !== 'bidder') return;

    // Loaded only here, so the sign-in page stays light.
    const { upload } = await import('@vercel/blob/client');
    const total = inputs.reduce((sum, input) => sum + input.files[0].size, 0);
    const loadedByKey = new Map();
    let done = 0;
    const showProgress = () => {
        const loaded = Array.from(loadedByKey.values()).reduce((sum, value) => sum + value, 0);
        const percent = total ? Math.min(99, Math.round((loaded / total) * 100)) : 0;
        renderAuthMessage('info', `Uploading your documents… ${percent}% (${done} of ${inputs.length} done)`);
    };

    const uploadOne = async (input) => {
        const key = /registration_documents\[([^\]]+)\]/.exec(input.name)?.[1];
        const file = input.files[0];
        if (!key) return;
        const extension = (file.name.split('.').pop() || 'pdf').toLowerCase().replace(/[^a-z0-9]/g, '');
        const blob = await upload(`${form.dataset.directUploadFolder}/${key}.${extension}`, file, {
            access: 'private',
            handleUploadUrl: form.dataset.directUploadUrl,
            headers: { 'X-CSRF-TOKEN': csrfToken || '', Accept: 'application/json' },
            multipart: file.size > 4 * 1024 * 1024,
            onUploadProgress: ({ loaded }) => {
                loadedByKey.set(key, loaded);
                showProgress();
            },
        });
        loadedByKey.set(key, file.size);
        done += 1;
        showProgress();
        formData.delete(input.name);
        formData.append(`uploaded_registration_documents[${key}]`, blob.url);
        formData.append(`uploaded_registration_document_names[${key}]`, file.name);
    };

    // Four files at a time instead of one after another.
    const queue = inputs.slice();
    showProgress();
    await Promise.all(Array.from({ length: Math.min(4, queue.length) }, async () => {
        while (queue.length) await uploadOne(queue.shift());
    }));
    renderAuthMessage('info', 'Documents uploaded. Submitting your registration…');
}

async function submitAuthForm(form, fallbackTab) {
    if (form.id === 'loginForm' && !validateLoginForm(form)) {
        return;
    }

    if (form.id === 'registerForm' && !validateRegisterForm(form)) {
        return;
    }

    const formData = new FormData(form);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const loginForm = document.getElementById('loginForm');
    const verifyLoginForm = document.getElementById('verifyLoginForm');
    const registerForm = document.getElementById('registerForm');
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    const forgotVerifyForm = document.getElementById('forgotVerifyForm');
    const resetPasswordForm = document.getElementById('resetPasswordForm');
    const formByTab = {
        login: loginForm,
        verify: verifyLoginForm,
        register: registerForm,
        forgot: forgotPasswordForm,
        forgot_verify: forgotVerifyForm,
        reset_password: resetPasswordForm,
    };

    clearAuthMessage();
    clearFieldErrors(form);
    setAuthFormLoading(form, true);

    try {
        if (form.id === 'registerForm' && form.dataset.directUploadUrl) {
            await uploadRegistrationDocuments(form, formData, csrfToken);
        }
    } catch (error) {
        console.error('[registration upload]', error);
        showAuthMessage('error', 'Uploading your documents failed. Check your connection and press Submit registration again. Your files are still attached.');
        setAuthFormLoading(form, false);
        return;
    }

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: formData,
        });

        if (response.status === 413) {
            switchTab(fallbackTab);
            showAuthMessage('error', 'The attached files are too large to send together. Use smaller files (scanned PDFs at a lower resolution) and try again.');
            return;
        }

        const data = await response.json();

        switchTab(data.tab || fallbackTab);
        const activeForm = formByTab[data.tab || fallbackTab] || loginForm;

        if (data.errors && activeForm) {
            showFieldErrors(activeForm, data.errors);
        }

        if (data.password_code_expired) {
            expirePasswordCodeCountdown();
        }

        if (data.ok) {
            clearFieldErrors(loginForm);
            if (verifyLoginForm) clearFieldErrors(verifyLoginForm);
            clearFieldErrors(registerForm);
            if (forgotPasswordForm) clearFieldErrors(forgotPasswordForm);
            if (forgotVerifyForm) clearFieldErrors(forgotVerifyForm);
            if (resetPasswordForm) clearFieldErrors(resetPasswordForm);

            if (form.id === 'loginForm' && !data.requires_verification) {
                await maybeStoreLoginCredential(form);
            }

            if (data.requires_verification && verifyLoginForm) {
                const verifyEmail = document.getElementById('verifyLoginEmail');
                const codeInput = verifyLoginForm.querySelector('[name="code"]');
                if (verifyEmail) verifyEmail.value = data.email || '';
                updateLoginVerificationEmail(data.email || '');
                if (codeInput) {
                    codeInput.value = '';
                    window.requestAnimationFrame(() => codeInput.focus());
                }
            }

            if (data.requires_password_code && forgotVerifyForm) {
                const emailInput = forgotPasswordForm?.querySelector('[name="email"]');
                const verifyEmail = document.getElementById('forgotVerifyEmail');
                const codeInput = forgotVerifyForm.querySelector('[name="code"]');

                if (verifyEmail) {
                    verifyEmail.value = data.email || emailInput?.value?.trim() || '';
                }

                if (codeInput) {
                    codeInput.value = '';
                    window.requestAnimationFrame(() => codeInput.focus());
                }

                startPasswordCodeCountdown(
                    data.password_code_expires_in || PASSWORD_CODE_TTL_SECONDS,
                    data.dev_password_reset_code
                        ? `Local test code: ${data.dev_password_reset_code}. Expires in`
                        : 'Code sent. Expires in'
                );
            }

            if (data.password_reset_verified && resetPasswordForm) {
                const resetEmail = document.getElementById('resetPasswordEmail');
                const newPasswordInput = resetPasswordForm.querySelector('[name="password"]');

                if (resetEmail) {
                    resetEmail.value = data.email || document.getElementById('forgotVerifyEmail')?.value || '';
                }

                resetPasswordForm.querySelectorAll('input[type="password"]').forEach((input) => {
                    input.value = '';
                });

                if (newPasswordInput) {
                    window.requestAnimationFrame(() => newPasswordInput.focus());
                }

                stopPasswordCodeCountdown();
            }

            if (form.id === 'forgotPasswordForm' && loginForm) {
                const forgotEmail = form.querySelector('[name="email"]')?.value?.trim() ?? '';
                const loginEmail = loginForm.querySelector('[name="email"]');
                if (loginEmail) {
                    loginEmail.value = forgotEmail;
                }
            }

            if (form.id === 'registerForm') {
                form.reset();
                setRegisterRoleFields();
                window.requestAnimationFrame(() => refreshRegisterUploadUI(form));
            }

            if (data.requires_verification) {
                clearAuthMessage();
            } else if (data.requires_password_code || data.password_reset_verified) {
                clearAuthMessage();
            } else {
                showAuthMessage('success', data.message || 'Success.', {
                    autoHideMs: AUTH_MESSAGE_HIDE_DELAY,
                    restoreModalOnHide: !data.redirect,
                    onHidden: form.id === 'forgotPasswordForm'
                        ? () => switchTab('login')
                        : undefined,
                });
            }
        } else {
            showAuthMessage('error', data.message || 'Something went wrong.');
        }

        if (data.ok) {
            if (form.id === 'registerForm') {
                switchTab('login');
            }

            if (form.id === 'resetPasswordForm') {
                form.reset();
                switchTab('login');
            }

            if (data.redirect) {
                // Long enough for the success check to appear (login.css .alert-check), no longer.
                const delay = form.id === 'forgotVerifyForm' ? 250 : AUTH_SUCCESS_REDIRECT_DELAY;

                window.setTimeout(() => {
                    window.location.href = data.redirect;
                }, delay);
            }
        }
    } catch (error) {
        switchTab(fallbackTab);
        showAuthMessage('error', 'Something went wrong. Please try again.');
    } finally {
        setAuthFormLoading(form, false);
    }
}

async function resendPasswordResetCode(button) {
    const forgotVerifyForm = document.getElementById('forgotVerifyForm');
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const url = forgotVerifyForm?.dataset?.resendUrl || forgotPasswordForm?.action;
    const email = document.getElementById('forgotVerifyEmail')?.value
        || forgotPasswordForm?.querySelector('[name="email"]')?.value?.trim();

    if (!forgotVerifyForm || !url || !button || !email) {
        return;
    }

    const formData = new FormData();
    const tokenInput = forgotVerifyForm.querySelector('[name="_token"]') || forgotPasswordForm?.querySelector('[name="_token"]');

    if (tokenInput?.value) {
        formData.append('_token', tokenInput.value);
    }

    formData.append('email', email);
    clearAuthMessage();
    clearFieldErrors(forgotVerifyForm);
    setButtonLoading(button, true, 'Sending code...');

    try {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: formData,
        });

        const data = await response.json();

        if (data.ok && data.requires_password_code) {
            const verifyEmail = document.getElementById('forgotVerifyEmail');
            const codeInput = forgotVerifyForm.querySelector('[name="code"]');

            if (verifyEmail) {
                verifyEmail.value = data.email || email;
            }

            if (codeInput) {
                codeInput.value = '';
                window.requestAnimationFrame(() => codeInput.focus());
            }

            switchTab('forgot_verify');
            startPasswordCodeCountdown(
                data.password_code_expires_in || PASSWORD_CODE_TTL_SECONDS,
                data.dev_password_reset_code
                    ? `Local test code: ${data.dev_password_reset_code}. Expires in`
                    : 'New code sent. Expires in'
            );
            clearAuthMessage();
        } else {
            if (data.errors) {
                showFieldErrors(forgotVerifyForm, data.errors);
            }
            showAuthMessage('error', data.message || 'Unable to send a new code. Please try again.');
        }
    } catch (error) {
        showAuthMessage('error', 'Unable to send a new code. Please try again.');
    } finally {
        setButtonLoading(button, false);
        updatePasswordCodeCountdown();
    }
}

async function resendLoginCode(button) {
    const verifyLoginForm = document.getElementById('verifyLoginForm');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const url = verifyLoginForm?.dataset?.resendUrl;

    if (!verifyLoginForm || !url || !button) {
        return;
    }

    clearAuthMessage();
    clearFieldErrors(verifyLoginForm);

    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Sending...';

    try {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: new FormData(verifyLoginForm),
        });

        const data = await response.json();
        switchTab(data.tab || 'verify');

        if (data.ok) {
            updateLoginVerificationEmail(data.email || document.getElementById('verifyLoginEmail')?.value || '');
            const codeInput = verifyLoginForm.querySelector('[name="code"]');
            if (codeInput) {
                codeInput.value = '';
                window.requestAnimationFrame(() => codeInput.focus());
            }

            clearAuthMessage();
        } else if (data.errors) {
            showFieldErrors(verifyLoginForm, data.errors);
        } else {
            showAuthMessage('error', data.message || 'Unable to resend code.');
        }
    } catch (error) {
        switchTab('verify');
        showAuthMessage('error', 'Unable to resend code. Please try again.');
    } finally {
        button.disabled = false;
        button.textContent = originalText;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const loginForm = document.getElementById('loginForm');
    const verifyLoginForm = document.getElementById('verifyLoginForm');
    const registerForm = document.getElementById('registerForm');
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    const forgotVerifyForm = document.getElementById('forgotVerifyForm');
    const resetPasswordForm = document.getElementById('resetPasswordForm');
    const sessionSuccessAlerts = document.querySelectorAll('.auth-session-alert[data-auto-hide]');

    if (sessionSuccessAlerts.length > 0) {
        setAuthSuccessState(true);
    }

    sessionSuccessAlerts.forEach((alert) => {
        const delay = Number(alert.dataset.autoHide) || AUTH_MESSAGE_HIDE_DELAY;
        scheduleAuthAlertFade(alert, () => {
            alert.remove();
            setAuthSuccessState(false);
        }, delay);
    });

    if (loginForm) {
        loginForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitAuthForm(loginForm, 'login');
        });

        loginForm.querySelectorAll('input').forEach((input) => {
            input.addEventListener('input', function () {
                input.classList.remove('input-error');
                const errorBox = loginForm.querySelector(`[data-error-for="${input.name}"]`);
                if (errorBox) errorBox.textContent = '';
            });
        });
    }

    if (verifyLoginForm) {
        const resendButton = document.getElementById('resendLoginCodeButton');

        verifyLoginForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitAuthForm(verifyLoginForm, 'verify');
        });

        if (resendButton) {
            resendButton.addEventListener('click', function () {
                resendLoginCode(resendButton);
            });
        }

        verifyLoginForm.querySelectorAll('input').forEach((input) => {
            input.addEventListener('input', function () {
                if (input.name === 'code') {
                    input.value = input.value.replace(/\D/g, '').slice(0, 6);
                }

                input.classList.remove('input-error');
                const errorBox = verifyLoginForm.querySelector(`[data-error-for="${input.name}"]`);
                if (errorBox) errorBox.textContent = '';
            });
        });
    }

    if (registerForm) {
        setRegisterRoleFields();
        refreshRegisterUploadUI(registerForm);

        registerForm.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-upload-trigger]');
            const removeButton = event.target.closest('[data-upload-remove]');
            const control = trigger || removeButton;
            const field = control?.closest('[data-register-upload-field]');
            const input = field?.querySelector('.register-file-input');

            if (!control || !field || !input || !registerForm.contains(control)) {
                return;
            }

            if (removeButton) {
                clearRegisterUploadInput(input);
                return;
            }

            input.click();
        });

        registerForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitAuthForm(registerForm, 'register');
        });

        registerForm.querySelectorAll('input, textarea, select').forEach((input) => {
            const eventName = input.tagName === 'SELECT' ? 'change' : 'input';

            input.addEventListener(eventName, function () {
                if (input.id === 'registerRole') {
                    setRegisterRoleFields();
                    refreshRegisterUploadUI(registerForm);
                }

                input.classList.remove('input-error');
                const key = fieldNameToErrorKey(input.name);
                const errorBox = registerForm.querySelector(`[data-error-for="${key}"]`);
                if (errorBox) errorBox.textContent = '';
            });
        });

        registerForm.querySelectorAll('input[type="file"]').forEach((input) => {
            input.addEventListener('change', function () {
                input.classList.remove('input-error');
                updateRegisterUploadCard(input);
                updateRegisterUploadProgress(registerForm);

                const key = fieldNameToErrorKey(input.name);
                const errorBox = registerForm.querySelector(`[data-error-for="${key}"]`);
                if (errorBox) errorBox.textContent = '';
            });
        });
    }

    if (forgotPasswordForm) {
        forgotPasswordForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitAuthForm(forgotPasswordForm, 'forgot');
        });

        forgotPasswordForm.querySelectorAll('input').forEach((input) => {
            input.addEventListener('input', function () {
                input.classList.remove('input-error');
                const errorBox = forgotPasswordForm.querySelector(`[data-error-for="${input.name}"]`);
                if (errorBox) errorBox.textContent = '';
            });
        });
    }

    if (forgotVerifyForm) {
        const resendPasswordButton = document.getElementById('resendPasswordCodeButton');

        forgotVerifyForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitAuthForm(forgotVerifyForm, 'forgot_verify');
        });

        if (resendPasswordButton) {
            resendPasswordButton.addEventListener('click', function () {
                resendPasswordResetCode(resendPasswordButton);
            });
        }

        forgotVerifyForm.querySelectorAll('input').forEach((input) => {
            input.addEventListener('input', function () {
                if (input.name === 'code') {
                    input.value = input.value.replace(/\D/g, '').slice(0, 6);
                }

                input.classList.remove('input-error');
                const errorBox = forgotVerifyForm.querySelector(`[data-error-for="${input.name}"]`);
                if (errorBox) errorBox.textContent = '';
            });
        });
    }

    if (resetPasswordForm) {
        resetPasswordForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitAuthForm(resetPasswordForm, 'reset_password');
        });

        resetPasswordForm.querySelectorAll('input').forEach((input) => {
            input.addEventListener('input', function () {
                input.classList.remove('input-error');
                const errorBox = resetPasswordForm.querySelector(`[data-error-for="${input.name}"]`);
                if (errorBox) errorBox.textContent = '';
            });
        });
    }
});

window.addEventListener('click', function (event) {
    const modal = document.getElementById('authModal');
    if (modal && event.target === modal) {
        closeAuth();
    }
});

window.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape' && event.key !== 'Esc') return;

    const modal = document.getElementById('authModal');
    if (modal && isAuthModalVisible(modal)) {
        closeAuth();
    }
});
