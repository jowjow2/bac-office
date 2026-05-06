window.openLogin = function () {
    const modal = document.getElementById('authModal');
    const card = modal?.querySelector('.auth-card');
    if (modal && card) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.style.display = 'flex';
        
        // Force a reflow to ensure the transition plays
        modal.offsetHeight;
        
        modal.classList.add('opacity-100');
        modal.classList.remove('opacity-0');
        
        card.classList.add('opacity-100', 'scale-100');
        card.classList.remove('opacity-0', 'scale-95');
        
    }
};

const AUTH_MESSAGE_HIDE_DELAY = 5000;
const AUTH_MESSAGE_FADE_DURATION = 350;

window.activateAuthTab = function (tab = 'login') {
    window.openLogin();
    window.switchTab(tab);
};

window.closeAuth = function () {
    const modal = document.getElementById('authModal');
    const card = modal?.querySelector('.auth-card');
    if (modal && card) {
        modal.classList.remove('opacity-100');
        modal.classList.add('opacity-0');
        
        card.classList.remove('opacity-100', 'scale-100');
        card.classList.add('opacity-0', 'scale-95');
        
        window.setTimeout(() => {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }, 300);
    }

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
        input.required = !isStaff;
    });

    staffFields.querySelectorAll('input, select, textarea').forEach((input) => {
        input.required = isStaff;
    });

    if (roleNote) {
        roleNote.textContent = isStaff
            ? 'New staff accounts require office selection and BAC Office admin activation.'
            : 'New bidder accounts require manual review and approval by the BAC Office admin.';
    }
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
    }

    if (authCard) {
        authCard.classList.remove('auth-card-wide');
    }

    if (tab === 'login') {
        login.classList.remove('hidden');
        tabLogin.classList.add('active');
        if (tabs) tabs.classList.remove('hidden');
    } else if (tab === 'verify' && verify) {
        verify.classList.remove('hidden');
        tabLogin.classList.add('active');
        if (tabs) tabs.classList.add('hidden');
    } else if (tab === 'register') {
        register.classList.remove('hidden');
        tabRegister.classList.add('active');
        if (tabs) tabs.classList.remove('hidden');
        setRegisterRoleFields();
    } else if (tab === 'forgot' && forgot) {
        forgot.classList.remove('hidden');
        if (tabs) tabs.classList.add('hidden');
    } else if (tab === 'forgot_verify' && forgotVerify) {
        forgotVerify.classList.remove('hidden');
        if (tabs) tabs.classList.add('hidden');
    } else if (tab === 'reset_password' && resetPassword) {
        resetPassword.classList.remove('hidden');
        if (tabs) tabs.classList.add('hidden');
    }

    if (authCard) {
        window.requestAnimationFrame(() => {
            authCard.scrollTop = 0;
        });
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

function showFieldErrors(form, errors) {
    if (!form || !errors) return;

    Object.entries(errors).forEach(([field, messages]) => {
        const baseField = field.includes('.') ? field.split('.')[0] : field;
        const input = form.querySelector(`[name="${field}"]`) || form.querySelector(`[name="${baseField}"]`) || form.querySelector(`[name="${baseField}[]"]`);
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

async function submitAuthForm(form, fallbackTab) {
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

        const data = await response.json();

        switchTab(data.tab || fallbackTab);
        const activeForm = formByTab[data.tab || fallbackTab] || loginForm;

        if (data.errors && activeForm) {
            showFieldErrors(activeForm, data.errors);
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
            }

            if (data.requires_verification || data.requires_password_code || data.password_reset_verified) {
                showAuthMessage('info', data.message || 'Verification code sent.');
            } else {
                showAuthMessage('success', data.message || 'Success.', {
                    autoHideMs: AUTH_MESSAGE_HIDE_DELAY,
                    restoreModalOnHide: !data.redirect,
                    onHidden: form.id === 'forgotPasswordForm'
                        ? () => switchTab('login')
                        : undefined,
                });
            }
        } else if (!data.errors || Object.keys(data.errors).length === 0) {
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
                const delay = form.id === 'forgotVerifyForm' ? 250 : AUTH_MESSAGE_HIDE_DELAY + AUTH_MESSAGE_FADE_DURATION;

                window.setTimeout(() => {
                    window.location.href = data.redirect;
                }, delay);
            }
        }
    } catch (error) {
        switchTab(fallbackTab);
        showAuthMessage('error', 'Something went wrong. Please try again.');
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
            const codeInput = verifyLoginForm.querySelector('[name="code"]');
            if (codeInput) {
                codeInput.value = '';
                window.requestAnimationFrame(() => codeInput.focus());
            }

            showAuthMessage('info', data.message || 'New verification code sent.');
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

        registerForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitAuthForm(registerForm, 'register');
        });

        registerForm.querySelectorAll('input, textarea, select').forEach((input) => {
            const eventName = input.tagName === 'SELECT' ? 'change' : 'input';

            input.addEventListener(eventName, function () {
                if (input.id === 'registerRole') {
                    setRegisterRoleFields();
                }

                input.classList.remove('input-error');
                const key = input.name.replace('[]', '');
                const errorBox = registerForm.querySelector(`[data-error-for="${key}"]`);
                if (errorBox) errorBox.textContent = '';
            });
        });

        registerForm.querySelectorAll('input[type="file"]').forEach((input) => {
            input.addEventListener('change', function () {
                input.classList.remove('input-error');
                const key = input.name.replace('[]', '');
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
        forgotVerifyForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitAuthForm(forgotVerifyForm, 'forgot_verify');
        });

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
