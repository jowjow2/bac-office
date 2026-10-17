// Download only the payment QR; its SVG includes the white quiet zone needed for scanning.
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-payment-qr-download]');
    if (!button || button.disabled) return;
    const card = button.closest('.sb-paycard');
    const status = card.querySelector('[data-payment-qr-status]');
    const label = button.querySelector('span');
    button.disabled = true;
    label.textContent = 'Preparing your QR…';
    try {
        const details = JSON.parse(button.dataset.paymentDetails);
        const image = new Image();
        image.src = card.querySelector('[data-payment-qr-image]').src;
        await image.decode();
        const canvas = document.createElement('canvas');
        canvas.width = 1024;
        canvas.height = 1024;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.imageSmoothingEnabled = false;
        ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        if (!blob) throw new Error('Your browser could not create the image. Please try another browser.');
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'SJBAC-payment-QR-' + String(details.reference).replace(/[^a-z0-9_-]/gi, '-') + '.png';
        document.body.append(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 30000);
        status.textContent = 'Payment QR downloaded. Show it at the BAC office and pay before the deadline.';
    } catch (error) {
        status.textContent = 'The QR could not be downloaded. Check your connection, refresh this page, and try again.';
    } finally {
        button.disabled = false;
        label.textContent = 'Download Payment QR';
    }
});
