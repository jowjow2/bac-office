/**
 * Live filters. A GET form marked data-live-filter updates the page as the person types or chooses, without a
 * page reload: the parts of the page that show the records are marked data-live-region="name", and the same
 * regions are taken from the freshly fetched page and swapped in. Links marked data-live-link (tabs, pagers)
 * do the same. If anything fails the browser simply goes to the address, as a normal form or link would.
 */
export function setupLiveFilters() {
    const forms = Array.from(document.querySelectorAll('form[data-live-filter]'));
    if (forms.length === 0) return;

    let timer = null;
    let controller = null;

    const regions = () => Array.from(document.querySelectorAll('[data-live-region]'));

    const addressOf = (form) => {
        const url = new URL(form.getAttribute('action') || window.location.pathname, window.location.origin);
        new FormData(form).forEach((value, key) => {
            if (typeof value === 'string' && value !== '') url.searchParams.append(key, value);
        });

        return url;
    };

    // After a tab or pager link: put the address's values back into the filter fields.
    const syncFields = (url) => {
        forms.forEach((form) => {
            Array.from(form.elements).forEach((field) => {
                if (!field.name || field.type === 'submit' || field.type === 'button') return;
                const value = url.searchParams.get(field.name);
                if (field.type === 'checkbox' || field.type === 'radio') field.checked = value !== null && field.value === value;
                else field.value = value ?? (field.tagName === 'SELECT' ? field.options[0]?.value ?? '' : '');
            });
        });
    };

    async function go(url) {
        if (controller) controller.abort();
        controller = new AbortController();
        const mine = controller;
        regions().forEach((region) => region.classList.add('is-live-loading'));
        window.history.replaceState(window.history.state, '', url.pathname + url.search);

        try {
            // Pages with their own in-place refresh (bid management) use it: it knows how to rebind its menus.
            if (typeof window.bacLiveRefresh === 'function') {
                try {
                    if (await window.bacLiveRefresh()) return finish();
                } catch (error) {
                    // The page's own refresh does not cover this page: swap the regions below.
                }
            }

            const response = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                credentials: 'same-origin',
                signal: mine.signal,
            });
            if (!response.ok || new URL(response.url).pathname !== window.location.pathname) throw new Error('response');

            const next = new DOMParser().parseFromString(await response.text(), 'text/html');
            const pairs = regions().map((current) => [current, next.querySelector('[data-live-region="' + current.dataset.liveRegion + '"]')]);
            if (pairs.length === 0 || pairs.some((pair) => !pair[1])) throw new Error('regions');

            pairs.forEach(([current, incoming]) => { current.innerHTML = incoming.innerHTML; });
            finish();
        } catch (error) {
            if (error && error.name === 'AbortError') return;
            window.location.href = url.toString();
        }
    }

    function finish() {
        regions().forEach((region) => region.classList.remove('is-live-loading'));
        document.dispatchEvent(new CustomEvent('bac:live-refreshed'));
    }

    // Phones: a "Filters" button folds the dropdowns away (portal-shell.css), with how many are set.
    forms.forEach((form) => {
        const selects = Array.from(form.querySelectorAll('select'));
        if (selects.length === 0) return;
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'ui-filters-toggle';
        toggle.setAttribute('aria-expanded', 'false');
        const paint = () => {
            const set = selects.filter((select) => select.selectedIndex > 0 && select.value !== '').length;
            toggle.innerHTML = '<i class="fas fa-sliders" aria-hidden="true"></i> Filters' + (set ? ' <b>' + set + '</b>' : '');
        };
        paint();
        toggle.addEventListener('click', () => {
            const open = form.classList.toggle('is-filters-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        form.addEventListener('change', paint);
        const search = form.querySelector('.ui-search, .admin-bids-toolbar-field-search, .projects-search-control, .ul-search > input[type="search"], .fee-search, .alog-search');
        const anchor = search && search.closest('form') === form ? search : null;
        if (anchor && anchor.parentElement) anchor.parentElement.insertBefore(toggle, anchor.nextSibling);
        else form.prepend(toggle);
        form.classList.add('has-filter-toggle');
    });

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            window.clearTimeout(timer);
            go(addressOf(form));
        });
        form.addEventListener('input', (event) => {
            const field = event.target;
            if (!(field instanceof HTMLInputElement) || ['checkbox', 'radio', 'date', 'hidden', 'file'].includes(field.type)) return;
            window.clearTimeout(timer);
            timer = window.setTimeout(() => go(addressOf(form)), 350);
        });
        form.addEventListener('change', (event) => {
            const field = event.target;
            if (field instanceof HTMLInputElement && !['checkbox', 'radio', 'date'].includes(field.type)) return;
            window.clearTimeout(timer);
            go(addressOf(form));
        });
    });

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a[data-live-link], [data-live-region] a[href*="page="]');
        if (!link || link.target === '_blank') return;
        const url = new URL(link.href, window.location.origin);
        if (url.pathname !== window.location.pathname) return;
        event.preventDefault();
        syncFields(url);
        go(url);
    });
}
