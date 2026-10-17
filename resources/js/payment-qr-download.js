// A full-resolution PNG keeps the project details and scannable QR together on a phone or printout.
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
        canvas.width = 1200;
        canvas.height = 3200;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#1d4f40';
        ctx.fillRect(0, 0, canvas.width, 18);
        let y = 90;
        const text = (value, size, weight = '400', color = '#1b2420') => {
            ctx.font = `${weight} ${size}px Arial, sans-serif`;
            ctx.fillStyle = color;
            const words = String(value || '').split(/\s+/);
            let line = '';
            for (const word of words) {
                // Break long references or company names too; never clip user-provided text.
                for (const part of word.match(/.{1,35}/gu) || ['']) {
                    const next = line ? line + ' ' + part : part;
                    if (line && ctx.measureText(next).width > 1032) {
                        ctx.fillText(line, 84, y); y += size * 1.4; line = part;
                    } else line = next;
                }
            }
            if (line) { ctx.fillText(line, 84, y); y += size * 1.4; }
        };
        text('SJBAC Procurement', 34, '700', '#1d4f40');
        text('Bids & Awards Committee · San Jose, Occidental Mindoro', 22, '400', '#66716b');
        y += 24;
        text('BIDDING DOCUMENTS PAYMENT QR', 30, '700');
        y += 12;
        text(details.project, 29, '600');
        text(details.reference, 25, '600', '#1d4f40');
        y += 20;
        ctx.imageSmoothingEnabled = false;
        ctx.drawImage(image, 344, y, 512, 512);
        y += 560;
        const field = (title, value) => { text(title, 18, '700', '#66716b'); text(value, 28, '600'); y += 13; };
        field('BIDDER', details.name);
        field('COMPANY', details.company);
        field('PROJECT REFERENCE', details.reference);
        field('AMOUNT TO PAY', details.fee);
        field('PAY AT', details.venue);
        field('SUBMISSION DEADLINE', details.deadline);
        y += 10;
        text('Show this QR at the BAC office. Payment is confirmed only after', 22, '400', '#66716b');
        text('the BAC records your Official Receipt. This is not a receipt.', 22, '400', '#66716b');
        // Crop the unused bottom while preserving tall slips with long project/company names.
        if (y > canvas.height - 40) throw new Error('The payment slip is too long. Please ask the BAC for assistance.');
        const output = document.createElement('canvas');
        output.width = canvas.width;
        output.height = Math.ceil(y + 60);
        output.getContext('2d').drawImage(canvas, 0, 0);
        const blob = await new Promise((resolve) => output.toBlob(resolve, 'image/png'));
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
        status.textContent = error.message?.includes('payment slip') ? error.message : 'The QR could not be downloaded. Check your connection, refresh this page, and try again.';
    } finally {
        button.disabled = false;
        label.textContent = 'Download Payment QR';
    }
});
