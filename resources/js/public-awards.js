// Public /awards: selecting an award posting swaps the viewed document in place, and the
// arrows or tabs page through that award's documents (Notice of Award, Notice to Proceed).
// Every card is a real link (?award=ID, ?doc=ntp), so the page works fully without this script.
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

const page = document.querySelector('[data-award-docs]');

if (page) {
    const panel = page.querySelector('#award-document');
    const viewer = page.querySelector('[data-award-viewer]');
    const frame = page.querySelector('[data-award-frame]');
    const missing = page.querySelector('[data-award-missing]');
    const missingText = page.querySelector('[data-award-missing-text]');
    const openLink = page.querySelector('[data-award-open]');
    const verifyLink = page.querySelector('[data-award-verify]');
    const bidderLink = page.querySelector('[data-award-bidder-link]');
    const bar = page.querySelector('[data-award-doc-bar]');
    const tabs = page.querySelector('[data-award-doc-tabs]');
    const count = page.querySelector('[data-award-doc-count]');
    const prev = page.querySelector('[data-award-doc-prev]');
    const next = page.querySelector('[data-award-doc-next]');
    const field = (name) => page.querySelector(`[data-award-field="${name}"]`);

    const selectedCard = page.querySelector('[data-award-card][aria-current="true"]');
    let record = selectedCard ? JSON.parse(selectedCard.dataset.award) : null;
    let index = Number(viewer?.dataset.docIndex || 0);

    const documents = () => (record && record.documents) || [];

    // The document is drawn page by page with PDF.js, so it shows the same in every browser: a browser set to
    // download PDFs, or a phone without a built-in PDF viewer, only offers an Open button inside an iframe.
    // The iframe stays as the fallback (and for visitors without this script).
    const pages = document.createElement('div');
    pages.className = 'award-pdf-pages';
    pages.hidden = true;
    pages.tabIndex = 0;
    frame?.after(pages);
    let renderToken = 0;

    const renderPdf = async (url, label) => {
        const mine = ++renderToken;
        pages.setAttribute('aria-label', label || 'Award document');
        pages.innerHTML = '<p class="award-pdf-status">Loading the document…</p>';
        pages.hidden = false;
        frame.hidden = true;
        frame.src = 'about:blank';
        try {
            const pdfjs = await import('pdfjs-dist');
            pdfjs.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;
            const pdf = await pdfjs.getDocument({ url, withCredentials: true }).promise;
            if (mine !== renderToken) return;
            pages.innerHTML = '';
            const width = Math.max(240, Math.min(pages.clientWidth - 16, 820));
            const ratio = Math.min(window.devicePixelRatio || 1, 2);
            for (let number = 1; number <= pdf.numPages; number++) {
                const pdfPage = await pdf.getPage(number);
                if (mine !== renderToken) return;
                const scale = width / pdfPage.getViewport({ scale: 1 }).width;
                const viewport = pdfPage.getViewport({ scale: scale * ratio });
                const canvas = document.createElement('canvas');
                canvas.className = 'award-pdf-page';
                canvas.width = Math.floor(viewport.width);
                canvas.height = Math.floor(viewport.height);
                canvas.style.width = `${Math.floor(viewport.width / ratio)}px`;
                canvas.setAttribute('role', 'img');
                canvas.setAttribute('aria-label', `${label || 'Document'}, page ${number} of ${pdf.numPages}`);
                pages.appendChild(canvas);
                await pdfPage.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
            }
        } catch (error) {
            if (mine !== renderToken) return;
            // Not readable here: hand it back to the browser's own viewer.
            pages.hidden = true;
            pages.innerHTML = '';
            frame.hidden = false;
            frame.src = url;
        }
    };

    const hidePdf = () => { renderToken++; pages.hidden = true; pages.innerHTML = ''; };

    const remember = () => {
        const url = new URL(window.location.href);
        url.searchParams.set('award', record.id);
        const doc = documents()[index];
        if (doc && doc.key !== 'noa') url.searchParams.set('doc', doc.key); else url.searchParams.delete('doc');
        url.hash = 'award-document';
        window.history.replaceState({}, '', url);
    };

    // Show document `i` of the current award.
    const showDocument = (i) => {
        const docs = documents();
        if (!docs.length) return;
        index = Math.max(0, Math.min(i, docs.length - 1));
        const doc = docs[index];

        field('post_title').textContent = doc.title;
        const hasFile = Boolean(doc.url);
        missing.hidden = hasFile;
        if (missingText) missingText.textContent = doc.missing || 'This document has not been published online.';
        frame.title = `${doc.label}: ${record.title}`;
        if (hasFile) {
            renderPdf(doc.url, frame.title);
        } else {
            hidePdf();
            frame.hidden = true;
            frame.src = 'about:blank';
        }
        openLink.hidden = !hasFile;
        openLink.href = doc.url || '#';

        const several = docs.length > 1;
        bar.hidden = !several;
        prev.hidden = !several;
        next.hidden = !several;
        prev.disabled = index === 0;
        next.disabled = index >= docs.length - 1;
        count.textContent = `${index + 1} of ${docs.length}`;
        tabs.innerHTML = '';
        docs.forEach((item, position) => {
            const tab = document.createElement('button');
            tab.type = 'button';
            tab.className = 'award-doc-tab';
            tab.setAttribute('role', 'tab');
            tab.dataset.awardDoc = String(position);
            tab.setAttribute('aria-selected', position === index ? 'true' : 'false');
            tab.textContent = item.label;
            tabs.appendChild(tab);
        });
        remember();
    };

    const select = (card) => {
        record = JSON.parse(card.dataset.award);

        field('title').textContent = record.title;
        field('date').textContent = record.date;
        field('date').setAttribute('datetime', record.date_iso || '');
        field('reference').textContent = record.reference;
        field('project_reference').textContent = record.project_reference || '—';
        field('winner').textContent = record.winner;
        field('amount').textContent = record.amount;
        field('status').textContent = record.status;

        const ntp = page.querySelector('[data-award-ntp]');
        if (ntp) {
            ntp.textContent = record.ntp_url ? `Issued ${record.ntp_issued} · ` : 'Not yet issued';
            if (record.ntp_url) {
                ntp.append(Object.assign(document.createElement('button'), { type: 'button', className: 'award-doc-link', textContent: 'View' }));
                ntp.lastChild.dataset.awardDocGoto = 'ntp';
            }
        }

        verifyLink.href = record.verify_url;
        bidderLink.hidden = !record.bidder_qr_url;
        bidderLink.href = record.bidder_verify_url || '#';
        document.querySelectorAll('[data-award-bidder-qr]').forEach((img) => { img.src = record.bidder_qr_url || ''; });

        page.querySelectorAll('[data-award-card]').forEach((other) => {
            const selected = other === card;
            other.classList.toggle('is-selected', selected);
            if (selected) other.setAttribute('aria-current', 'true');
            else other.removeAttribute('aria-current');
        });

        showDocument(0);
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        panel.focus({ preventScroll: true });
    };

    page.addEventListener('click', (event) => {
        const tab = event.target.closest('[data-award-doc]');
        if (tab) { showDocument(Number(tab.dataset.awardDoc)); return; }
        if (event.target.closest('[data-award-doc-prev]')) { showDocument(index - 1); return; }
        if (event.target.closest('[data-award-doc-next]')) { showDocument(index + 1); return; }
        const goto = event.target.closest('[data-award-doc-goto]');
        if (goto) {
            showDocument(documents().findIndex((doc) => doc.key === goto.dataset.awardDocGoto));
            viewer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        const card = event.target.closest('[data-award-card]');
        // Let modified clicks open the card's own link in a new tab.
        if (!card || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
        event.preventDefault();
        select(card);
    });

    // The page opens with a document: draw it.
    const initialUrl = frame?.getAttribute('src');
    if (frame && initialUrl && initialUrl !== 'about:blank' && !frame.hidden) renderPdf(initialUrl, frame.title);

    // Left and right arrow keys page the documents while the viewer has focus.
    viewer?.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') showDocument(index - 1);
        if (event.key === 'ArrowRight') showDocument(index + 1);
    });
}
