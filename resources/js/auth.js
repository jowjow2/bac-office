window.openLogin = function () {
    const modal = document.getElementById('authModal');
    if (modal) {
        modal.style.display = 'flex';
    }
};

const AUTH_MESSAGE_HIDE_DELAY = 5000;
const AUTH_MESSAGE_FADE_DURATION = 350;
const QR_SCANNER_IDLE_MESSAGE = 'Only approved bidder accounts with valid BAC Office QR login codes can use automatic QR sign in by camera scan or uploaded QR image.';
const QR_JSQR_SCRIPT_ID = 'bac-office-jsqr';
const QR_JSQR_SCRIPT_SRC = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js';

let qrScannerStream = null;
let qrScannerTimer = null;
let qrScannerActive = false;
let qrBarcodeDetector = null;
let qrScannerEngine = null;
let qrJsQrLoader = null;
let qrScannerCanvas = null;
let qrPendingImagePayload = null;

function getQrElements() {
    return {
        form: document.getElementById('qrLoginForm'),
        video: document.getElementById('qrScannerVideo'),
        placeholder: document.getElementById('qrScannerPlaceholder'),
        status: document.getElementById('qrScannerStatus'),
        payloadInput: document.getElementById('qrPayload'),
        imageInput: document.getElementById('qrImageFile'),
        imagePreviewCard: document.getElementById('qrImagePreviewCard'),
        imagePreview: document.getElementById('qrImagePreview'),
        imagePreviewText: document.getElementById('qrImagePreviewText'),
        confirmImageButton: document.getElementById('confirmQrImageButton'),
        passwordField: document.getElementById('qrPasswordField'),
        passwordInput: document.getElementById('qrPassword'),
        startButton: document.getElementById('startQrScannerButton'),
        stopButton: document.getElementById('stopQrScannerButton'),
    };
}

function setQrPasswordFieldVisible(visible) {
    const { passwordField, passwordInput } = getQrElements();

    if (!passwordField) return;

    passwordField.classList.toggle('hidden', !visible);

    if (!visible && passwordInput) {
        passwordInput.value = '';
    }
}

function setQrScannerStatus(message, state = '') {
    const { status } = getQrElements();
    if (!status) return;

    status.textContent = message;

    if (state) {
        status.dataset.state = state;
    } else {
        delete status.dataset.state;
    }
}

function setQrScannerPreviewActive(active) {
    const { video, placeholder } = getQrElements();
    if (video) {
        video.classList.toggle('is-active', active);
    }

    if (placeholder) {
        placeholder.classList.toggle('hidden', active);
    }
}

function clearQrImageInput() {
    const { imageInput } = getQrElements();
    if (!imageInput) return;

    imageInput.value = '';
}

window.clearQrImagePreview = function (resetStatus = false) {
    const { imagePreviewCard, imagePreview, imagePreviewText } = getQrElements();

    qrPendingImagePayload = null;

    if (imagePreviewCard) {
        imagePreviewCard.classList.add('hidden');
    }

    if (imagePreview) {
        imagePreview.removeAttribute('src');
    }

    if (imagePreviewText) {
        imagePreviewText.textContent = 'Review the uploaded QR image first, then continue secure bidder login.';
    }

    clearQrImageInput();

    if (resetStatus) {
        setQrScannerStatus(QR_SCANNER_IDLE_MESSAGE, '');
    }
};

function showQrImagePreview(dataUrl, helperText) {
    const { imagePreviewCard, imagePreview, imagePreviewText } = getQrElements();

    if (imagePreviewCard) {
        imagePreviewCard.classList.remove('hidden');
    }

    if (imagePreview) {
        imagePreview.src = dataUrl;
    }

    if (imagePreviewText) {
        imagePreviewText.textContent = helperText;
    }
}

function clearQrScannerLoop() {
    if (qrScannerTimer) {
        window.clearTimeout(qrScannerTimer);
        qrScannerTimer = null;
    }
}

function getQrScannerCanvas() {
    if (!qrScannerCanvas) {
        qrScannerCanvas = document.createElement('canvas');
    }

    return qrScannerCanvas;
}

function resetQrScannerEngine() {
    qrScannerEngine = null;
}

function loadJsQrLibrary() {
    if (typeof window.jsQR === 'function') {
        return Promise.resolve(true);
    }

    if (qrJsQrLoader) {
        return qrJsQrLoader;
    }

    qrJsQrLoader = new Promise((resolve) => {
        const existingScript = document.getElementById(QR_JSQR_SCRIPT_ID);

        if (existingScript) {
            existingScript.addEventListener('load', () => resolve(typeof window.jsQR === 'function'), { once: true });
            existingScript.addEventListener('error', () => resolve(false), { once: true });
            return;
        }

        const script = document.createElement('script');
        script.id = QR_JSQR_SCRIPT_ID;
        script.src = QR_JSQR_SCRIPT_SRC;
        script.async = true;
        script.onload = () => resolve(typeof window.jsQR === 'function');
        script.onerror = () => resolve(false);
        document.head.appendChild(script);
    });

    return qrJsQrLoader;
}

async function ensureQrScannerEngine() {
    if (qrScannerEngine) {
        return qrScannerEngine;
    }

    if (typeof window.BarcodeDetector !== 'undefined') {
        qrBarcodeDetector = qrBarcodeDetector || new window.BarcodeDetector({ formats: ['qr_code'] });
        qrScannerEngine = 'barcode-detector';
        return qrScannerEngine;
    }

    const jsQrReady = await loadJsQrLibrary();

    if (jsQrReady && typeof window.jsQR === 'function') {
        qrScannerEngine = 'jsqr';
        return qrScannerEngine;
    }

    return null;
}

async function detectQrPayloadFromVideo(video) {
    if (qrScannerEngine === 'barcode-detector' && qrBarcodeDetector) {
        const barcodes = await qrBarcodeDetector.detect(video);
        const match = Array.isArray(barcodes)
            ? barcodes.find((barcode) => typeof barcode.rawValue === 'string' && barcode.rawValue.trim() !== '')
            : null;

        return match?.rawValue?.trim() || null;
    }

    if (qrScannerEngine === 'jsqr' && typeof window.jsQR === 'function') {
        const width = video.videoWidth || video.clientWidth;
        const height = video.videoHeight || video.clientHeight;

        if (!width || !height) {
            return null;
        }

        const canvas = getQrScannerCanvas();
        canvas.width = width;
        canvas.height = height;

        const context = canvas.getContext('2d', { willReadFrequently: true });

        if (!context) {
            return null;
        }

        context.drawImage(video, 0, 0, width, height);

        const imageData = context.getImageData(0, 0, width, height);
        const result = window.jsQR(imageData.data, width, height, {
            inversionAttempts: 'attemptBoth',
        });

        return result?.data?.trim() || null;
    }

    return null;
}

function loadQrImageFile(file) {
    return new Promise((resolve, reject) => {
        const objectUrl = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            URL.revokeObjectURL(objectUrl);
            resolve(image);
        };

        image.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error('image_load_failed'));
        };

        image.src = objectUrl;
    });
}

function readQrImagePreview(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = () => resolve(typeof reader.result === 'string' ? reader.result : '');
        reader.onerror = () => reject(new Error('image_preview_failed'));
        reader.readAsDataURL(file);
    });
}

function readQrImageText(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = () => resolve(typeof reader.result === 'string' ? reader.result : '');
        reader.onerror = () => reject(new Error('image_text_failed'));
        reader.readAsText(file);
    });
}

function isSvgQrFile(file) {
    if (!file) {
        return false;
    }

    return file.type === 'image/svg+xml' || /\.svg$/i.test(file.name || '');
}

function extractQrPayloadFromSvgMarkup(markup) {
    if (typeof markup !== 'string' || markup.trim() === '') {
        return null;
    }

    const readPayloadFromElement = (element) => {
        if (!element || typeof element.getAttribute !== 'function') {
            return null;
        }

        const directPayload = element.getAttribute('data-bac-office-qr-payload');

        if (typeof directPayload === 'string' && directPayload.trim() !== '') {
            return directPayload.trim();
        }

        if (typeof element.querySelector === 'function') {
            const metadataNode = element.querySelector('metadata[data-bac-office-qr-payload]');
            const metadataPayload = metadataNode?.getAttribute?.('data-bac-office-qr-payload');

            if (typeof metadataPayload === 'string' && metadataPayload.trim() !== '') {
                return metadataPayload.trim();
            }
        }

        return null;
    };

    if (typeof window.DOMParser !== 'undefined') {
        try {
            const document = new window.DOMParser().parseFromString(markup, 'image/svg+xml');
            const parsedPayload = readPayloadFromElement(document?.documentElement);

            if (parsedPayload) {
                return parsedPayload;
            }
        } catch (error) {
            // Fall back to regex parsing for browsers with limited SVG DOM parsing support.
        }
    }

    const directAttributeMatch = markup.match(/data-bac-office-qr-payload=(['"])(.*?)\1/i);

    return directAttributeMatch?.[2]?.trim() || null;
}

async function extractQrPayloadFromSvgFile(file) {
    if (!isSvgQrFile(file)) {
        return null;
    }

    try {
        const markup = await readQrImageText(file);
        return extractQrPayloadFromSvgMarkup(markup);
    } catch (error) {
        return null;
    }
}

async function detectQrPayloadFromImage(image) {
    if (qrScannerEngine === 'barcode-detector' && qrBarcodeDetector) {
        const barcodes = await qrBarcodeDetector.detect(image);
        const match = Array.isArray(barcodes)
            ? barcodes.find((barcode) => typeof barcode.rawValue === 'string' && barcode.rawValue.trim() !== '')
            : null;

        return match?.rawValue?.trim() || null;
    }

    if (qrScannerEngine === 'jsqr' && typeof window.jsQR === 'function') {
        const width = image.naturalWidth || image.width;
        const height = image.naturalHeight || image.height;

        if (!width || !height) {
            return null;
        }

        const canvas = getQrScannerCanvas();
        canvas.width = width;
        canvas.height = height;

        const context = canvas.getContext('2d', { willReadFrequently: true });

        if (!context) {
            return null;
        }

        context.drawImage(image, 0, 0, width, height);

        const imageData = context.getImageData(0, 0, width, height);
        const result = window.jsQR(imageData.data, width, height, {
            inversionAttempts: 'attemptBoth',
        });

        return result?.data?.trim() || null;
    }

    return null;
}

function stopQrScannerTracks() {
    if (!qrScannerStream) {
        return;
    }

    qrScannerStream.getTracks().forEach((track) => track.stop());
    qrScannerStream = null;
}

async function submitDetectedQrPayload(payload) {
    const { form, payloadInput } = getQrElements();
    if (!form || !payloadInput) return;

    payloadInput.value = payload;
    setQrScannerStatus('QR code detected. Continuing secure bidder login...', 'success');
    window.stopQrScanner(false);
    await submitAuthForm(form, 'qr');
}

async function scanQrFrame() {
    const { video } = getQrElements();

    if (!qrScannerActive || !video || !qrScannerEngine) {
        return;
    }

    if (video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) {
        qrScannerTimer = window.setTimeout(scanQrFrame, 180);
        return;
    }

    try {
        const payload = await detectQrPayloadFromVideo(video);

        if (payload) {
            await submitDetectedQrPayload(payload);
            return;
        }
    } catch (error) {
        if (qrScannerEngine === 'barcode-detector') {
            const jsQrReady = await loadJsQrLibrary();

            if (jsQrReady && typeof window.jsQR === 'function') {
                qrScannerEngine = 'jsqr';
                setQrScannerStatus('Camera is active. Switched to compatibility mode for QR scanning.', '');
            } else {
                setQrScannerStatus('Camera is active, but this browser could not read the QR code automatically. You may paste the QR data below instead.', 'error');
            }
        } else {
            setQrScannerStatus('Camera is active, but this browser could not read the QR code automatically. You may paste the QR data below instead.', 'error');
        }
    }

    qrScannerTimer = window.setTimeout(scanQrFrame, 220);
}

window.startQrScanner = async function () {
    const { video, startButton, stopButton } = getQrElements();

    if (!video || !startButton || !stopButton) {
        return;
    }

    if (!navigator.mediaDevices?.getUserMedia) {
        setQrScannerStatus('This browser does not allow camera access for QR login. Please use manual login instead.', 'error');
        return;
    }

    if (!window.isSecureContext && !['localhost', '127.0.0.1'].includes(window.location.hostname)) {
        setQrScannerStatus('Automatic QR scanning requires a secure HTTPS page. Please open the BAC Office public login link and try again.', 'error');
        return;
    }

    try {
        window.stopQrScanner(false);
        window.clearQrImagePreview(false);

        const scannerEngine = await ensureQrScannerEngine();

        if (!scannerEngine) {
            setQrScannerStatus('Automatic QR scanning is not supported in this browser yet. You may paste the QR data below or use manual login.', 'error');
            return;
        }

        qrScannerStream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: { ideal: 'environment' },
            },
            audio: false,
        });

        video.srcObject = qrScannerStream;
        await video.play();

        qrScannerActive = true;
        startButton.classList.add('hidden');
        stopButton.classList.remove('hidden');
        setQrScannerPreviewActive(true);
        setQrScannerStatus(
            scannerEngine === 'jsqr'
                ? 'Scanning in compatibility mode... point the camera at the bidder QR code.'
                : 'Scanning... point the camera at the bidder QR code.',
            ''
        );
        scanQrFrame();
    } catch (error) {
        window.stopQrScanner(false);
        setQrScannerStatus('Unable to start the camera for QR scanning. Please allow camera access or use manual login.', 'error');
    }
};

window.stopQrScanner = function (resetStatus = true, clearImagePreview = true) {
    const { video, startButton, stopButton } = getQrElements();

    qrScannerActive = false;
    clearQrScannerLoop();
    stopQrScannerTracks();
    resetQrScannerEngine();

    if (video) {
        video.pause();
        video.srcObject = null;
    }

    setQrScannerPreviewActive(false);

    if (startButton) {
        startButton.classList.remove('hidden');
    }

    if (stopButton) {
        stopButton.classList.add('hidden');
    }

    if (resetStatus) {
        setQrScannerStatus(QR_SCANNER_IDLE_MESSAGE, '');
    }

    if (clearImagePreview) {
        window.clearQrImagePreview(false);
    } else {
        clearQrImageInput();
    }
};

window.activateAuthTab = function (tab = 'login') {
    openLogin();
    switchTab(tab);

    if (tab === 'qr') {
        window.setTimeout(() => {
            window.startQrScanner();
        }, 80);
    }
};

window.showQrQuickAccess = function () {
    window.activateAuthTab('qr');
};

window.hideQrQuickAccess = function () {
    window.stopQrScanner(true);
    setQrPasswordFieldVisible(false);
};

window.closeAuth = function () {
    const modal = document.getElementById('authModal');
    if (modal) {
        modal.style.display = 'none';
    }

    window.hideQrQuickAccess();
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
    const qr = document.getElementById('qrLoginForm');
    const register = document.getElementById('registerForm');
    const forgot = document.getElementById('forgotPasswordForm');
    const authCard = document.querySelector('#authModal .auth-card');
    const tabLogin = document.getElementById('tabLogin');
    const tabQr = document.getElementById('tabQr');
    const tabRegister = document.getElementById('tabRegister');
    const tabs = document.getElementById('authTabs');

    if (!login || !qr || !register || !tabLogin || !tabQr || !tabRegister) return;

    if (tab !== 'qr') {
        window.stopQrScanner(true);
    }

    if (tab !== 'qr') {
        setQrPasswordFieldVisible(false);
    }

    clearFieldErrors(login);
    clearFieldErrors(qr);
    clearFieldErrors(register);
    if (forgot) clearFieldErrors(forgot);

    login.classList.add('hidden');
    qr.classList.add('hidden');
    register.classList.add('hidden');
    if (forgot) forgot.classList.add('hidden');

    tabLogin.classList.remove('active');
    tabQr.classList.remove('active');
    tabRegister.classList.remove('active');

    if (authCard) {
        authCard.classList.toggle('auth-card-wide', tab === 'qr');
    }

    if (tab === 'login') {
        login.classList.remove('hidden');
        tabLogin.classList.add('active');
        if (tabs) tabs.classList.remove('hidden');
    } else if (tab === 'qr') {
        qr.classList.remove('hidden');
        tabQr.classList.add('active');
        if (tabs) tabs.classList.remove('hidden');
    } else if (tab === 'register') {
        register.classList.remove('hidden');
        tabRegister.classList.add('active');
        if (tabs) tabs.classList.remove('hidden');
    } else if (tab === 'forgot' && forgot) {
        forgot.classList.remove('hidden');
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
    const qrLoginForm = document.getElementById('qrLoginForm');
    const registerForm = document.getElementById('registerForm');
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    const formByTab = {
        login: loginForm,
        qr: qrLoginForm,
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

        if (form.id === 'qrLoginForm') {
            setQrPasswordFieldVisible(Boolean(data.requires_password_confirmation));
        }

        if (data.errors && activeForm) {
            showFieldErrors(activeForm, data.errors);
        }

        if (data.ok) {
            clearFieldErrors(loginForm);
            clearFieldErrors(qrLoginForm);
            clearFieldErrors(registerForm);
            if (forgotPasswordForm) clearFieldErrors(forgotPasswordForm);

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

            if (form.id === 'registerForm') {
                form.reset();
            }

            if (form.id === 'qrLoginForm') {
                setQrPasswordFieldVisible(false);
            }

            showAuthMessage('success', data.message || 'Success.', {
                autoHideMs: AUTH_MESSAGE_HIDE_DELAY,
                restoreModalOnHide: !data.redirect,
                onHidden: form.id === 'forgotPasswordForm'
                    ? () => switchTab('login')
                    : undefined,
            });
        } else if (form.id === 'qrLoginForm' && data.message) {
            showAuthMessage('error', data.message);
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

async function handleQrImageSelection(file) {
    if (!file) {
        return;
    }

    if (!file.type.startsWith('image/')) {
        setQrScannerStatus('Please select a valid QR code image file.', 'error');
        window.clearQrImagePreview(false);
        return;
    }

    window.stopQrScanner(false, false);
    setQrScannerStatus('Reading uploaded QR image...', '');

    try {
        const [image, previewDataUrl, svgPayload] = await Promise.all([
            loadQrImageFile(file),
            readQrImagePreview(file),
            extractQrPayloadFromSvgFile(file),
        ]);
        let payload = svgPayload;
        let payloadSource = svgPayload ? 'svg-metadata' : '';

        if (!payload) {
            const scannerEngine = await ensureQrScannerEngine();

            if (!scannerEngine) {
                setQrScannerStatus('Automatic QR image reading is not supported in this browser yet. Upload the original BAC Office QR file or use camera scan/manual login.', 'error');
                window.clearQrImagePreview(false);
                return;
            }

            payload = await detectQrPayloadFromImage(image);
        }

        if (!payload && qrScannerEngine === 'barcode-detector') {
            const jsQrReady = await loadJsQrLibrary();

            if (jsQrReady && typeof window.jsQR === 'function') {
                qrScannerEngine = 'jsqr';
                payload = await detectQrPayloadFromImage(image);
            }
        }

        if (!payload) {
            setQrScannerStatus('No readable QR code was found in that image. Upload the original BAC Office QR file, try a clearer photo, or use the camera scanner.', 'error');
            window.clearQrImagePreview(false);
            return;
        }

        qrPendingImagePayload = payload;
        showQrImagePreview(
            previewDataUrl,
            payloadSource === 'svg-metadata'
                ? 'Original BAC Office QR file verified. Confirm this preview to continue direct bidder login.'
                : 'Valid bidder QR code detected from the uploaded image. Confirm this preview to continue direct login.'
        );
        setQrScannerStatus(
            payloadSource === 'svg-metadata'
                ? 'BAC Office QR file verified. Review the preview, then tap Confirm QR Image to continue.'
                : 'QR image verified. Review the preview, then tap Confirm QR Image to continue.',
            'success'
        );
    } catch (error) {
        setQrScannerStatus('Unable to open that QR image. Please try another photo.', 'error');
        window.clearQrImagePreview(false);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const loginForm = document.getElementById('loginForm');
    const qrLoginForm = document.getElementById('qrLoginForm');
    const registerForm = document.getElementById('registerForm');
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    const qrImageFileInput = document.getElementById('qrImageFile');
    const confirmQrImageButton = document.getElementById('confirmQrImageButton');
    const sessionSuccessAlerts = document.querySelectorAll('.auth-session-alert[data-auto-hide]');

    setQrScannerStatus(QR_SCANNER_IDLE_MESSAGE, '');

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

    if (qrLoginForm) {
        qrLoginForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitAuthForm(qrLoginForm, 'qr');
        });

        qrLoginForm.querySelectorAll('input, textarea').forEach((input) => {
            input.addEventListener('input', function () {
                input.classList.remove('input-error');
                const errorBox = qrLoginForm.querySelector(`[data-error-for="${input.name}"]`);
                if (errorBox) errorBox.textContent = '';
            });
        });
    }

    if (qrImageFileInput) {
        qrImageFileInput.addEventListener('change', function (event) {
            const file = event.target.files?.[0];
            handleQrImageSelection(file);
        });
    }

    if (confirmQrImageButton) {
        confirmQrImageButton.addEventListener('click', async function () {
            if (!qrPendingImagePayload) {
                setQrScannerStatus('Upload and verify a QR image first before continuing.', 'error');
                return;
            }

            await submitDetectedQrPayload(qrPendingImagePayload);
        });
    }

    if (registerForm) {
        registerForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitAuthForm(registerForm, 'register');
        });

        registerForm.querySelectorAll('input, textarea').forEach((input) => {
            input.addEventListener('input', function () {
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
});

window.addEventListener('click', function (event) {
    const modal = document.getElementById('authModal');
    if (modal && event.target === modal) {
        closeAuth();
    }
});
