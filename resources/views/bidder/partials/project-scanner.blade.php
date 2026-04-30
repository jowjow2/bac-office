@php
    $scannerProjects = ($availableProjects ?? collect())
        ->map(function ($project) {
            return [
                'id' => $project->id,
                'title' => $project->title,
                'public_url' => $project->public_url,
                'public_path' => $project->public_path,
                'scan_url' => $project->scan_url,
                'scan_path' => $project->scan_path,
            ];
        })
        ->values();
@endphp

<input
    type="file"
    id="bidderProjectScannerQuickInput"
    accept="image/*"
    capture="environment"
    data-scanner-quick-input
    tabindex="-1"
    aria-hidden="true"
    style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;pointer-events:none;"
>

<div class="bidder-scanner-modal-overlay" id="bidderProjectScanner">
    <div class="bidder-scanner-modal" role="dialog" aria-modal="true" aria-labelledby="bidderProjectScannerTitle">
        <div class="bidder-scanner-modal-header">
            <div>
                <h2 id="bidderProjectScannerTitle">Scan Project QR</h2>
                <p>Use your camera to scan a BAC-Office project QR code and jump straight to the bidder action.</p>
            </div>

            <button type="button" class="bidder-scanner-close" data-scanner-close aria-label="Close project scanner">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>

        <div class="bidder-scanner-modal-body">
            <div class="bidder-scanner-grid">
                <div class="bidder-scanner-preview" data-scanner-preview data-state="idle">
                    <video data-scanner-video autoplay playsinline muted></video>
                    <div class="bidder-scanner-preview-state" data-scanner-preview-state>
                        <span class="bidder-scanner-preview-badge" data-scanner-preview-badge>Phone-friendly scan</span>
                        <strong data-scanner-preview-title>Open your phone camera to capture a project QR.</strong>
                        <p data-scanner-preview-message>
                            If live preview is blocked on this phone, use the camera button below and we will decode the QR photo for you.
                        </p>
                        <div class="bidder-scanner-preview-actions">
                            <button type="button" class="bidder-scanner-camera-action bidder-scanner-camera-action-website" data-scanner-secure-upgrade>
                                <i class="fas fa-globe" aria-hidden="true"></i>
                                Use Website Camera
                            </button>
                            <button type="button" class="bidder-scanner-camera-action" data-scanner-direct-capture>
                                <i class="fas fa-camera" aria-hidden="true"></i>
                                Open Phone Camera
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bidder-scanner-sidebar">
                    <div class="bidder-scanner-note">
                        <strong>How it works</strong>
                        <p>Point your phone camera at a BAC-Office project QR. If the project is open for bidders, we will open the matching bidder project action for you.</p>
                    </div>

                    <p class="bidder-scanner-status" data-scanner-status data-tone="info">
                        Allow camera access, then align the QR code inside the frame.
                    </p>

                    <div class="bidder-scanner-fallback" data-scanner-fallback hidden>
                        <strong>Quick fallback on mobile</strong>
                        <p>
                            Live camera scanning needs HTTPS or localhost. You are currently using
                            <span class="bidder-scanner-origin" data-scanner-origin></span>.
                        </p>
                        <ul class="bidder-scanner-fallback-list">
                            <li>Tap a project QR image or the <strong>Open page</strong> link on this device.</li>
                            <li>Use your phone camera app to scan a QR shown on another screen. The QR now opens the matching project flow directly.</li>
                            <li>Upload a QR screenshot or photo, or paste the QR link below.</li>
                        </ul>

                        <div class="bidder-scanner-fallback-actions">
                            <button type="button" class="bidder-scanner-camera-action bidder-scanner-camera-action-website" data-scanner-secure-upgrade>
                                <i class="fas fa-globe" aria-hidden="true"></i>
                                Use Website Camera
                            </button>
                            <button type="button" class="bidder-scanner-camera-action bidder-scanner-camera-action-secondary" data-scanner-direct-capture>
                                <i class="fas fa-camera" aria-hidden="true"></i>
                                Open Phone Camera
                            </button>

                            <label class="bidder-scanner-upload" for="bidderProjectScannerImage">
                                <input
                                    id="bidderProjectScannerImage"
                                    type="file"
                                    accept="image/*"
                                    capture="environment"
                                    data-scanner-image-input
                                >
                                <span><i class="fas fa-image" aria-hidden="true"></i> Upload QR Image</span>
                            </label>
                        </div>

                        <p class="bidder-scanner-upload-meta" data-scanner-image-name>No QR image selected yet.</p>
                    </div>

                    <form class="bidder-scanner-manual" data-scanner-manual-form>
                        <label for="bidderProjectScannerInput">Paste QR link instead</label>
                        <input
                            id="bidderProjectScannerInput"
                            type="text"
                            class="bidder-scanner-input"
                            data-scanner-manual-input
                            placeholder="https://your-app.test/procurement/projects/1"
                        >
                        <button type="submit" class="btn-primary bidder-scanner-submit">Open Project</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const scannerModal = document.getElementById('bidderProjectScanner');
        if (!scannerModal) {
            return;
        }

        const scannerProjects = @json($scannerProjects);
        const scannerOpenButtons = document.querySelectorAll('[data-scanner-open]');
        const scannerCloseButtons = scannerModal.querySelectorAll('[data-scanner-close]');
        const scannerPreview = scannerModal.querySelector('[data-scanner-preview]');
        const scannerVideo = scannerModal.querySelector('[data-scanner-video]');
        const scannerPreviewBadge = scannerModal.querySelector('[data-scanner-preview-badge]');
        const scannerPreviewTitle = scannerModal.querySelector('[data-scanner-preview-title]');
        const scannerPreviewMessage = scannerModal.querySelector('[data-scanner-preview-message]');
        const scannerStatus = scannerModal.querySelector('[data-scanner-status]');
        const scannerFallback = scannerModal.querySelector('[data-scanner-fallback]');
        const scannerOrigin = scannerModal.querySelector('[data-scanner-origin]');
        const scannerImageInput = scannerModal.querySelector('[data-scanner-image-input]');
        const scannerImageName = scannerModal.querySelector('[data-scanner-image-name]');
        const scannerManualForm = scannerModal.querySelector('[data-scanner-manual-form]');
        const scannerManualInput = scannerModal.querySelector('[data-scanner-manual-input]');
        const scannerQuickInput = document.querySelector('[data-scanner-quick-input]');
        const scannerDirectCaptureButtons = scannerModal.querySelectorAll('[data-scanner-direct-capture]');
        const scannerSecureUpgradeButtons = scannerModal.querySelectorAll('[data-scanner-secure-upgrade]');
        const availableProjectsUrl = @json(route('bidder.available-projects'));
        const cameraAllowedOrigin = window.isSecureContext || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        const qrDetectionSupported = 'BarcodeDetector' in window;
        const mobileScannerCandidate = /Android|iPhone|iPad|iPod/i.test(window.navigator.userAgent || '')
            || window.matchMedia('(pointer: coarse)').matches
            || (window.navigator.maxTouchPoints > 0 && window.innerWidth <= 1024);
        const scannerPageUrl = new URL(window.location.href);
        const openScannerOnLoad = scannerPageUrl.searchParams.get('open_scanner') === '1';

        let scannerStream = null;
        let scannerDetector = null;
        let scannerAnimationFrame = null;
        let scannerActive = false;
        let scannerLastScanAt = 0;

        if (scannerVideo) {
            scannerVideo.setAttribute('playsinline', 'true');
            scannerVideo.setAttribute('webkit-playsinline', 'true');
            scannerVideo.muted = true;
        }

        function setScannerStatus(message, tone) {
            scannerStatus.textContent = message;
            scannerStatus.dataset.tone = tone || 'info';
        }

        function setScannerPreviewState(state, options = {}) {
            if (!scannerPreview) {
                return;
            }

            scannerPreview.dataset.state = state;

            if (scannerPreviewBadge && options.badge) {
                scannerPreviewBadge.textContent = options.badge;
            }

            if (scannerPreviewTitle && options.title) {
                scannerPreviewTitle.textContent = options.title;
            }

            if (scannerPreviewMessage && options.message) {
                scannerPreviewMessage.textContent = options.message;
            }

            scannerDirectCaptureButtons.forEach(function (button) {
                button.hidden = !options.showCaptureAction;
            });

            scannerSecureUpgradeButtons.forEach(function (button) {
                button.hidden = !options.showSecureUpgradeAction;
            });
        }

        function resetScannerPreview() {
            setScannerPreviewState('idle', {
                badge: cameraAllowedOrigin ? 'QR scanner' : 'Secure website camera',
                title: cameraAllowedOrigin
                    ? 'Allow camera access to scan a BAC-Office project QR.'
                    : 'Open the secure website camera for live scanning.',
                message: cameraAllowedOrigin
                    ? 'Hold the QR steady inside the frame. On phones, you can still use camera photo capture if live preview does not start.'
                    : 'This page is not in a secure browser context yet. Tap Use Website Camera to reopen this same page over HTTPS, then the live camera scanner will run inside the website.',
                showCaptureAction: canOfferDirectCameraCapture(),
                showSecureUpgradeAction: canOfferSecureUpgrade(),
            });
        }

        function setScannerFallbackVisible(isVisible) {
            if (!scannerFallback) {
                return;
            }

            scannerFallback.hidden = !isVisible;
        }

        function setScannerImageName(message) {
            if (!scannerImageName) {
                return;
            }

            scannerImageName.textContent = message;
        }

        function resetScannerInputs() {
            if (scannerImageInput) {
                scannerImageInput.value = '';
            }

            if (scannerQuickInput) {
                scannerQuickInput.value = '';
            }
        }

        function prefersDirectCameraCapture() {
            return Boolean(scannerQuickInput) && mobileScannerCandidate;
        }

        function canOfferDirectCameraCapture() {
            return Boolean(scannerQuickInput) && (mobileScannerCandidate || !cameraAllowedOrigin);
        }

        function canOfferSecureUpgrade() {
            return window.location.protocol === 'http:'
                && !['localhost', '127.0.0.1', '::1'].includes(window.location.hostname);
        }

        function buildSecureScannerUrl() {
            const secureUrl = new URL(window.location.href);
            secureUrl.protocol = 'https:';

            if (secureUrl.port === '80') {
                secureUrl.port = '';
            }

            secureUrl.searchParams.set('open_scanner', '1');

            return secureUrl.toString();
        }

        function openSecureScanner() {
            if (!canOfferSecureUpgrade()) {
                return false;
            }

            stopScanner();
            window.location.href = buildSecureScannerUrl();
            return true;
        }

        function openDirectCameraCapture() {
            if (!scannerQuickInput) {
                return false;
            }

            stopScanner();
            scannerModal.classList.remove('show');
            setScannerPreviewState('capture', {
                badge: 'Opening camera',
                title: 'Opening your phone camera...',
                message: 'Take a clear photo of the BAC-Office QR code and we will open the matching project for you.',
                showCaptureAction: false,
                showSecureUpgradeAction: false,
            });
            setScannerImageName('Opening your phone camera. If nothing happens, try again or use Upload QR Image.');

            try {
                if (typeof scannerQuickInput.showPicker === 'function') {
                    scannerQuickInput.showPicker();
                } else {
                    scannerQuickInput.click();
                }
                return true;
            } catch (error) {
                return false;
            }
        }

        function openScannerModal() {
            scannerModal.classList.add('show');
        }

        function revealScannerFallback(message, tone) {
            openScannerModal();
            setScannerFallbackVisible(true);
            setScannerStatus(message, tone || 'warning');
            setScannerPreviewState('fallback', {
                badge: canOfferSecureUpgrade() ? 'Secure website camera' : 'Phone camera fallback',
                title: canOfferSecureUpgrade() ? 'Use the secure page for the website camera.' : 'Live preview is unavailable here.',
                message: canOfferSecureUpgrade()
                    ? 'Tap Use Website Camera to reopen this page over HTTPS and start the live scanner on the website itself, or use the phone camera fallback below.'
                    : 'Use Open Phone Camera for a fresh QR photo, or upload/paste the QR below.',
                showCaptureAction: canOfferDirectCameraCapture(),
                showSecureUpgradeAction: canOfferSecureUpgrade(),
            });
        }

        function createScannerDetector() {
            if (!qrDetectionSupported) {
                return null;
            }

            if (!scannerDetector) {
                scannerDetector = new BarcodeDetector({ formats: ['qr_code'] });
            }

            return scannerDetector;
        }

        function normalisePath(value) {
            try {
                const parsed = new URL(value, window.location.origin);

                return parsed.pathname.replace(/\/+$/, '') || '/';
            } catch (error) {
                return String(value || '').trim().replace(/\/+$/, '') || '/';
            }
        }

        function findScannerProject(value) {
            let scannedPath = normalisePath(value);
            let scannedProjectId = '';

            try {
                const parsed = new URL(value, window.location.origin);
                scannedPath = parsed.pathname.replace(/\/+$/, '') || '/';
                scannedProjectId = parsed.searchParams.get('scan_project') || '';
            } catch (error) {
                scannedProjectId = '';
            }

            if (scannedProjectId === '') {
                const scannedSegments = scannedPath.split('/').filter(Boolean);
                scannedProjectId = scannedSegments.length ? scannedSegments[scannedSegments.length - 1] : '';
            }

            return scannerProjects.find(function (project) {
                const projectPath = normalisePath(project.public_path);
                const scanPath = normalisePath(project.scan_path);

                if (projectPath === scannedPath || scanPath === scannedPath) {
                    return true;
                }

                return scannedProjectId !== ''
                    && scannedProjectId === String(project.id)
                    && (
                        scannedPath.indexOf('/procurement/projects/') !== -1
                        || scannedPath.indexOf('/scan') !== -1
                    );
            });
        }

        function stopScanner() {
            scannerActive = false;

            if (scannerAnimationFrame) {
                window.cancelAnimationFrame(scannerAnimationFrame);
                scannerAnimationFrame = null;
            }

            if (scannerStream) {
                scannerStream.getTracks().forEach(function (track) {
                    track.stop();
                });
                scannerStream = null;
            }

            if (scannerVideo) {
                scannerVideo.pause();
                scannerVideo.srcObject = null;
            }
        }

        function closeScannerModal() {
            stopScanner();
            scannerModal.classList.remove('show');
            setScannerFallbackVisible(false);
            setScannerImageName('No QR image selected yet.');
            resetScannerInputs();
            resetScannerPreview();
        }

        function openScannerProject(project) {
            stopScanner();
            setScannerStatus('Project found. Opening the bidder page for this project...', 'success');
            window.location.href = availableProjectsUrl + '?scan_project=' + encodeURIComponent(project.id);
        }

        function handleScannedValue(value) {
            const project = findScannerProject(value);

            if (!project) {
                setScannerStatus('That QR code is not a recognized BAC-Office project QR for bidder actions. Try another project code.', 'error');
                setScannerFallbackVisible(true);
                setScannerPreviewState('fallback', {
                    badge: 'Need another QR',
                    title: 'That code does not match a BAC-Office project.',
                    message: 'Use a project QR from this system, or open your phone camera again to capture a clearer photo.',
                    showCaptureAction: canOfferDirectCameraCapture(),
                    showSecureUpgradeAction: canOfferSecureUpgrade(),
                });
                return;
            }

            openScannerProject(project);
        }

        async function handleScannerImage(file, options = {}) {
            if (!file) {
                return;
            }

            setScannerImageName(file.name);
            if (options.showFallbackImmediately) {
                setScannerFallbackVisible(true);
            }

            if (!qrDetectionSupported || typeof window.createImageBitmap !== 'function') {
                if (options.revealFallbackOnFailure) {
                    revealScannerFallback('QR image scanning is not supported in this browser yet. Use Chrome or Edge, or paste the QR link below.', 'warning');
                } else {
                    setScannerStatus('QR image upload is not supported in this browser yet. Use Chrome or Edge, or paste the QR link below.', 'warning');
                }

                resetScannerInputs();
                return;
            }

            try {
                const detector = createScannerDetector();
                const imageBitmap = await window.createImageBitmap(file);
                const results = await detector.detect(imageBitmap);

                if (typeof imageBitmap.close === 'function') {
                    imageBitmap.close();
                }

                if (results.length > 0 && results[0].rawValue) {
                    handleScannedValue(results[0].rawValue);
                    return;
                }

                if (options.revealFallbackOnFailure) {
                    revealScannerFallback('No QR code was detected in that image. Try another photo or paste the QR link below.', 'warning');
                } else {
                    setScannerStatus('No QR code was detected in that image. Try a clearer screenshot or paste the QR link below.', 'warning');
                }
            } catch (error) {
                if (options.revealFallbackOnFailure) {
                    revealScannerFallback('We could not read that QR image. Try another photo or paste the QR link below.', 'error');
                } else {
                    setScannerStatus('We could not read that QR image. Try another screenshot or paste the QR link below.', 'error');
                }
            } finally {
                resetScannerInputs();
            }
        }

        function scannerStreamConstraints() {
            return [
                {
                    video: {
                        facingMode: { exact: 'environment' },
                        width: { ideal: 1920 },
                        height: { ideal: 1080 },
                    },
                    audio: false,
                },
                {
                    video: {
                        facingMode: { ideal: 'environment' },
                        width: { ideal: 1920 },
                        height: { ideal: 1080 },
                    },
                    audio: false,
                },
                {
                    video: {
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                    },
                    audio: false,
                },
            ];
        }

        async function requestScannerStream() {
            let lastError = null;

            for (const constraints of scannerStreamConstraints()) {
                try {
                    return await navigator.mediaDevices.getUserMedia(constraints);
                } catch (error) {
                    lastError = error;
                }
            }

            throw lastError;
        }

        async function scanFrame(timestamp) {
            if (!scannerActive || !scannerDetector) {
                return;
            }

            if (timestamp - scannerLastScanAt < 220) {
                scannerAnimationFrame = window.requestAnimationFrame(scanFrame);
                return;
            }

            scannerLastScanAt = timestamp;

            try {
                const results = await scannerDetector.detect(scannerVideo);

                if (results.length > 0 && results[0].rawValue) {
                    handleScannedValue(results[0].rawValue);
                    return;
                }
            } catch (error) {
                setScannerStatus('The camera is active, but QR detection failed. Hold the code steady or paste the link below.', 'warning');
            }

            scannerAnimationFrame = window.requestAnimationFrame(scanFrame);
        }

        async function startScanner() {
            stopScanner();
            scannerManualInput.value = '';
            setScannerFallbackVisible(false);
            setScannerImageName('No QR image selected yet.');
            scannerLastScanAt = 0;
            setScannerPreviewState('loading', {
                badge: 'Starting scanner',
                title: 'Preparing the camera preview...',
                message: 'Allow camera access when your phone asks for permission.',
                showCaptureAction: canOfferDirectCameraCapture(),
                showSecureUpgradeAction: false,
            });

            if (!cameraAllowedOrigin) {
                setScannerStatus('The website camera needs HTTPS or localhost on mobile. Use the secure website camera button below, or use the phone camera fallback.', 'warning');
                setScannerFallbackVisible(true);
                setScannerPreviewState('fallback', {
                    badge: 'HTTPS needed',
                    title: 'Website camera stays blocked on HTTP mobile pages.',
                    message: 'Tap Use Website Camera to reopen this page over HTTPS and start the live scanner here on the website, or use the fallback tools below.',
                    showCaptureAction: canOfferDirectCameraCapture(),
                    showSecureUpgradeAction: canOfferSecureUpgrade(),
                });
                return;
            }

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                setScannerStatus('This browser cannot access the camera. Paste the QR link below instead.', 'warning');
                setScannerFallbackVisible(true);
                setScannerPreviewState('fallback', {
                    badge: 'Camera unavailable',
                    title: 'This browser cannot open a live camera preview.',
                    message: 'Use Open Phone Camera or upload a QR image instead.',
                    showCaptureAction: canOfferDirectCameraCapture(),
                    showSecureUpgradeAction: canOfferSecureUpgrade(),
                });
                return;
            }

            if (!qrDetectionSupported) {
                setScannerStatus('Live QR scanning is not supported in this browser. Use Chrome or Edge, or paste the QR link below.', 'warning');
                setScannerFallbackVisible(true);
                setScannerPreviewState('fallback', {
                    badge: 'Browser limitation',
                    title: 'This browser cannot decode live QR video yet.',
                    message: 'Use Open Phone Camera, Chrome, Edge, or paste the QR link instead.',
                    showCaptureAction: canOfferDirectCameraCapture(),
                    showSecureUpgradeAction: canOfferSecureUpgrade(),
                });
                return;
            }

            try {
                createScannerDetector();
                scannerStream = await requestScannerStream();

                scannerVideo.srcObject = scannerStream;
                await new Promise(function (resolve) {
                    if (scannerVideo.readyState >= 1) {
                        resolve();
                        return;
                    }

                    scannerVideo.onloadedmetadata = function () {
                        resolve();
                    };
                });
                await scannerVideo.play();

                scannerActive = true;
                setScannerStatus('Camera ready. Point the rear phone camera at a BAC-Office project QR code.', 'info');
                setScannerPreviewState('live', {
                    badge: 'Live camera',
                    title: 'Camera ready',
                    message: 'Point the rear phone camera at the project QR code.',
                    showCaptureAction: false,
                    showSecureUpgradeAction: false,
                });
                scannerAnimationFrame = window.requestAnimationFrame(scanFrame);
            } catch (error) {
                setScannerStatus('Camera access was blocked or unavailable. Allow phone camera access, then try again, or use the upload / paste fallback below.', 'error');
                setScannerFallbackVisible(true);
                setScannerPreviewState('fallback', {
                    badge: 'Camera blocked',
                    title: 'The live preview could not start.',
                    message: 'Use Open Phone Camera for a direct QR photo, or upload/paste the QR below.',
                    showCaptureAction: canOfferDirectCameraCapture(),
                    showSecureUpgradeAction: canOfferSecureUpgrade(),
                });
            }
        }

        if (scannerOrigin) {
            scannerOrigin.textContent = window.location.origin;
        }

        if (scannerManualInput && !scannerManualInput.value) {
            scannerManualInput.placeholder = scannerProjects[0]?.scan_url || (window.location.origin + '/procurement/projects/1/scan');
        }

        resetScannerPreview();

        if (openScannerOnLoad) {
            openScannerModal();
            startScanner();

            const cleanUrl = new URL(window.location.href);
            cleanUrl.searchParams.delete('open_scanner');
            window.history.replaceState({}, document.title, cleanUrl.toString());
        }

        scannerOpenButtons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();

                if (!cameraAllowedOrigin) {
                    revealScannerFallback('The website camera needs the secure version of this page on mobile. Use Website Camera below to reopen this page over HTTPS, or use the phone camera fallback.', 'warning');
                    return;
                }

                openScannerModal();
                startScanner();
            });
        });

        scannerSecureUpgradeButtons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();

                if (!openSecureScanner()) {
                    revealScannerFallback('We could not build the secure website camera link from this page. Use Open Phone Camera below instead.', 'warning');
                }
            });
        });

        scannerDirectCaptureButtons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();

                if (!openDirectCameraCapture()) {
                    revealScannerFallback('We could not open the phone camera. Try Upload QR Image below instead.', 'warning');
                }
            });
        });

        scannerCloseButtons.forEach(function (button) {
            button.addEventListener('click', closeScannerModal);
        });

        scannerModal.addEventListener('click', function (event) {
            if (event.target === scannerModal) {
                closeScannerModal();
            }
        });

        scannerManualForm.addEventListener('submit', function (event) {
            event.preventDefault();

            const value = scannerManualInput.value.trim();

            if (value === '') {
                setScannerStatus('Paste a BAC-Office project QR link first.', 'warning');
                return;
            }

            handleScannedValue(value);
        });

        scannerImageInput?.addEventListener('change', function (event) {
            const file = event.target.files && event.target.files[0];
            handleScannerImage(file, {
                showFallbackImmediately: true,
            });
        });

        scannerQuickInput?.addEventListener('change', function (event) {
            const file = event.target.files && event.target.files[0];
            handleScannerImage(file, {
                revealFallbackOnFailure: true,
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && scannerModal.classList.contains('show')) {
                closeScannerModal();
            }
        });
    })();
</script>
