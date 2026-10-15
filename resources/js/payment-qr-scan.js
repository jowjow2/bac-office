import jsQR from 'jsqr';

/**
 * Scans a bidder's "Verified Bidding Record" QR code (a link ending in the bidder's token) with the camera, or from a
 * photo of the code, and hands the token back. Used by the bidding fee payments page.
 */
window.feeQrScan = function (options) {
    const { video, canvas, onToken, onStatus } = options;
    let stream = null;
    let frame = null;
    let stopped = false;

    const tokenFrom = (text) => {
        const match = String(text || '').trim().match(/\/bidder\/verify\/([A-Za-z0-9_-]{8,})/);

        return match ? match[1] : null;
    };

    const decode = (image, width, height) => {
        canvas.width = width;
        canvas.height = height;
        const context = canvas.getContext('2d', { willReadFrequently: true });
        context.drawImage(image, 0, 0, width, height);
        const found = jsQR(context.getImageData(0, 0, width, height).data, width, height, { inversionAttempts: 'attemptBoth' });

        return found ? found.data : null;
    };

    const handle = (text) => {
        const token = tokenFrom(text);
        if (token) {
            stop();
            onToken(token);

            return true;
        }
        onStatus('That QR code is not a bidder record QR. Scan the Verified Bidding Record QR from the bidder\'s profile.', 'warn');

        return false;
    };

    const tick = () => {
        if (stopped) return;
        if (video.readyState >= video.HAVE_ENOUGH_DATA && video.videoWidth) {
            const scale = Math.min(1, 720 / video.videoWidth);
            const text = decode(video, Math.round(video.videoWidth * scale), Math.round(video.videoHeight * scale));
            if (text && handle(text)) return;
        }
        frame = window.requestAnimationFrame(tick);
    };

    function stop() {
        stopped = true;
        if (frame) window.cancelAnimationFrame(frame);
        if (stream) stream.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
    }

    return {
        async start() {
            stopped = false;
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                onStatus('This browser cannot open the camera here. Upload a photo of the QR code instead.', 'warn');

                return false;
            }
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
                video.srcObject = stream;
                await video.play();
                onStatus('Hold the bidder\'s QR code in front of the camera.', 'info');
                frame = window.requestAnimationFrame(tick);

                return true;
            } catch (error) {
                onStatus('The camera could not be opened. Allow camera access for this site, or upload a photo of the QR code instead.', 'warn');

                return false;
            }
        },
        stop,
        // A photo or screenshot of the code.
        async fromFile(file) {
            if (!file) return;
            const url = URL.createObjectURL(file);
            try {
                const image = new Image();
                await new Promise((resolve, reject) => { image.onload = resolve; image.onerror = reject; image.src = url; });
                const scale = Math.min(1, 1200 / Math.max(image.width, image.height));
                const text = decode(image, Math.round(image.width * scale), Math.round(image.height * scale));
                if (!text) {
                    onStatus('No QR code was found in that picture. Try a sharper, closer photo.', 'warn');

                    return;
                }
                handle(text);
            } catch (error) {
                onStatus('That picture could not be read.', 'warn');
            } finally {
                URL.revokeObjectURL(url);
            }
        },
    };
};
