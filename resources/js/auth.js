window.openLogin = function () {
    const modal = document.getElementById('authModal');
    if (modal) {
        modal.style.display = 'flex';
    }
};

const AUTH_MESSAGE_HIDE_DELAY = 5000;
const AUTH_MESSAGE_FADE_DURATION = 350;

window.closeAuth = function () {
    const modal = document.getElementById('authModal');
    if (modal) modal.style.display = 'none';
};

function setAuthSuccessState(active) {
    const modal = document.getElementById('authModal');
    if (!modal) return;

    modal.classList.toggle('success-state', active);

    if (active) {
        modal.style.display = 'flex';
    }
}

window.switchTab = function (tab) {
    const login = document.getElementById('loginForm');
    const register = document.getElementById('registerForm');
    const forgot = document.getElementById('forgotPasswordForm');
    const tabLogin = document.getElementById('tabLogin');
    const tabRegister = document.getElementById('tabRegister');
    const tabs = document.getElementById('authTabs');

    if (!login || !register || !forgot || !tabLogin || !tabRegister) return;

    clearFieldErrors(login);
    clearFieldErrors(register);
    clearFieldErrors(forgot);

    login.classList.add('hidden');
    register.classList.add('hidden');
    forgot.classList.add('hidden');

    if (tab === 'login') {
        login.classList.remove('hidden');
        tabLogin.classList.add('active');
        tabRegister.classList.remove('active');
        if (tabs) tabs.classList.remove('hidden');
    } else if (tab === 'register') {
        register.classList.remove('hidden');
        tabRegister.classList.add('active');
        tabLogin.classList.remove('active');
        if (tabs) tabs.classList.remove('hidden');
    } else {
        forgot.classList.remove('hidden');
        tabLogin.classList.remove('active');
        tabRegister.classList.remove('active');
        if (tabs) tabs.classList.add('hidden');
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

    form.querySelectorAll('input').forEach((input) => {
        input.classList.remove('input-error');
    });

    form.querySelectorAll('.field-error').forEach((errorBox) => {
        errorBox.textContent = '';
    });
}

function scheduleClearFieldErrors(form) {
    return form;
}

function showFieldErrors(form, errors) {
    if (!form || !errors) return;

    Object.entries(errors).forEach(([field, messages]) => {
        const input = form.querySelector(`[name="${field}"]`);
        const errorBox = form.querySelector(`[data-error-for="${field}"]`);
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
    const registerForm = document.getElementById('registerForm');
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    const formByTab = {
        login: loginForm,
        register: registerForm,
        forgot: forgotPasswordForm,
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
            clearFieldErrors(registerForm);
            clearFieldErrors(forgotPasswordForm);

            if (form.id === 'loginForm') {
                await maybeStoreLoginCredential(form);
            }

            if (form.id === 'forgotPasswordForm' && loginForm) {
                const forgotEmail = form.querySelector('[name="email"]')?.value?.trim() ?? '';
                const loginEmail = loginForm.querySelector('[name="email"]');
                if (loginEmail) {
                    loginEmail.value = forgotEmail;
                }
            }

            showAuthMessage('success', data.message || 'Success.', {
                autoHideMs: AUTH_MESSAGE_HIDE_DELAY,
                restoreModalOnHide: !data.redirect,
                onHidden: form.id === 'forgotPasswordForm'
                    ? () => switchTab('login')
                    : undefined,
            });
        } else if (!data.errors || Object.keys(data.errors).length === 0) {
            showAuthMessage('error', data.message || 'Something went wrong.');
        }

        if (data.ok) {
            if (form.id === 'registerForm') {
                switchTab('login');
            }

            if (data.redirect) {
                window.setTimeout(() => {
                    window.location.href = data.redirect;
                }, AUTH_MESSAGE_HIDE_DELAY + AUTH_MESSAGE_FADE_DURATION);
            }
        }
    } catch (error) {
        switchTab(fallbackTab);
        showAuthMessage('error', 'Something went wrong. Please try again.');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
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

    if (registerForm) {
        registerForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitAuthForm(registerForm, 'register');
        });

        registerForm.querySelectorAll('input').forEach((input) => {
            input.addEventListener('input', function () {
                input.classList.remove('input-error');
                const errorBox = registerForm.querySelector(`[data-error-for="${input.name}"]`);
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

});

window.addEventListener('click', function (event) {
    const modal = document.getElementById('authModal');
    if (modal && event.target === modal) {
        modal.style.display = 'none';
    }
});
