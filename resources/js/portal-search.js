/**
 * Search the whole portal (admin and staff): the Search button in the page header, or Ctrl+K / Cmd+K anywhere.
 * Results come from /portal/search grouped by kind; arrows move, Enter opens, Esc closes.
 */
export function setupPortalSearch() {
    const trigger = document.querySelector('[data-portal-search]');
    if (!trigger) return;
    const url = trigger.dataset.portalSearch;

    const dialog = document.createElement('dialog');
    dialog.className = 'portal-search';
    dialog.setAttribute('aria-label', 'Search the portal');
    dialog.innerHTML = '<div class="portal-search__box">'
        + '<label class="portal-search__field"><i class="fas fa-magnifying-glass" aria-hidden="true"></i>'
        + '<span class="sr-only">Search</span><input type="search" placeholder="Search projects, purchase requests, bidders, OR no. or awards" autocomplete="off" data-ps-input></label>'
        + '<div class="portal-search__results" role="listbox" data-ps-results><p class="portal-search__hint">Type at least 2 letters.</p></div>'
        + '<footer class="portal-search__foot"><span><kbd>↑</kbd><kbd>↓</kbd> move</span><span><kbd>Enter</kbd> open</span><span><kbd>Esc</kbd> close</span></footer>'
        + '</div>';
    document.body.append(dialog);
    const input = dialog.querySelector('[data-ps-input]');
    const results = dialog.querySelector('[data-ps-results]');
    let timer = null;
    let controller = null;
    let active = -1;

    const escape = (text) => String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const links = () => Array.from(results.querySelectorAll('a.portal-search__item'));
    const paint = () => links().forEach((link, index) => {
        link.classList.toggle('is-active', index === active);
        if (index === active) link.scrollIntoView({ block: 'nearest' });
    });

    const open = () => {
        if (!dialog.open) dialog.showModal();
        input.select();
    };
    const close = () => { if (dialog.open) dialog.close(); };

    async function search() {
        const term = input.value.trim();
        if (term.length < 2) {
            results.innerHTML = '<p class="portal-search__hint">Type at least 2 letters.</p>';
            active = -1;
            return;
        }
        if (controller) controller.abort();
        controller = new AbortController();
        results.classList.add('is-loading');
        try {
            const response = await fetch(url + '?q=' + encodeURIComponent(term), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', signal: controller.signal });
            const data = await response.json();
            const groups = (data && data.groups) || [];
            results.innerHTML = groups.length === 0
                ? '<p class="portal-search__hint">Nothing found for “' + escape(term) + '”.</p>'
                : groups.map((group) => '<section><h3><i class="fas ' + escape(group.icon) + '" aria-hidden="true"></i> ' + escape(group.label) + '</h3>'
                    + group.items.map((item) => '<a class="portal-search__item" role="option" href="' + escape(item.url) + '"><strong>' + escape(item.title) + '</strong><small>' + escape(item.meta) + '</small></a>').join('')
                    + '</section>').join('');
            active = links().length ? 0 : -1;
            paint();
        } catch (error) {
            if (error.name !== 'AbortError') results.innerHTML = '<p class="portal-search__hint">Search is unavailable right now.</p>';
        } finally {
            results.classList.remove('is-loading');
        }
    }

    trigger.addEventListener('click', open);
    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            open();
        }
    });
    input.addEventListener('input', () => { window.clearTimeout(timer); timer = window.setTimeout(search, 220); });
    input.addEventListener('keydown', (event) => {
        const items = links();
        if (event.key === 'ArrowDown' && items.length) { event.preventDefault(); active = (active + 1) % items.length; paint(); }
        else if (event.key === 'ArrowUp' && items.length) { event.preventDefault(); active = (active - 1 + items.length) % items.length; paint(); }
        else if (event.key === 'Enter' && items[active]) { event.preventDefault(); window.location.href = items[active].href; }
    });
    dialog.addEventListener('click', (event) => { if (event.target === dialog) close(); });
}
