// Public /awards: selecting an award posting swaps the viewed Notice of Award in place.
// Every card is a real link (?award=ID), so the page works fully without this script.
const page = document.querySelector('[data-award-docs]');

if (page) {
    const panel = page.querySelector('#award-document');
    const frame = page.querySelector('[data-award-frame]');
    const missing = page.querySelector('[data-award-missing]');
    const openLink = page.querySelector('[data-award-open]');
    const verifyLink = page.querySelector('[data-award-verify]');
    const bidderLink = page.querySelector('[data-award-bidder-link]');
    const field = (name) => page.querySelector(`[data-award-field="${name}"]`);

    const select = (card) => {
        const record = JSON.parse(card.dataset.award);

        field('post_title').textContent = record.post_title;
        field('title').textContent = record.title;
        field('date').textContent = record.date;
        field('date').setAttribute('datetime', record.date_iso || '');
        field('reference').textContent = record.reference;
        field('project_reference').textContent = record.project_reference || '—';
        field('winner').textContent = record.winner;
        field('amount').textContent = record.amount;
        field('status').textContent = record.status;

        // Only a published file is ever loaded; otherwise the "not available" state shows.
        const hasDocument = Boolean(record.document_url);
        frame.hidden = !hasDocument;
        missing.hidden = hasDocument;
        frame.title = `Notice of Award: ${record.title}`;
        frame.src = hasDocument ? record.document_url : 'about:blank';
        openLink.hidden = !hasDocument;
        openLink.href = record.document_url || '#';
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

        const url = new URL(window.location.href);
        url.searchParams.set('award', record.id);
        url.hash = 'award-document';
        window.history.replaceState({}, '', url);

        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        panel.focus({ preventScroll: true });
    };

    page.addEventListener('click', (event) => {
        const card = event.target.closest('[data-award-card]');
        // Let modified clicks open the card's own link in a new tab.
        if (!card || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
        event.preventDefault();
        select(card);
    });
}
