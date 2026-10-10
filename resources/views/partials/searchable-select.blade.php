{{-- Adds a search box above every <select data-searchable="Placeholder"> so a long list can be narrowed by typing. --}}
<style>
    .ss-search { width: 100%; margin-bottom: 6px; padding: 8px 10px; border: 1px solid #d2cbbb; border-radius: 8px; background: #fff; font: inherit; }
    .ss-search:focus { outline: 2px solid rgba(29, 79, 64, .35); outline-offset: 1px; }
</style>
<script>
    (function () {
        function enhance(select) {
            if (select.dataset.searchReady) return;
            select.dataset.searchReady = '1';
            const all = Array.from(select.options).map(function (option) { return option.cloneNode(true); });
            const search = document.createElement('input');
            search.type = 'search';
            search.className = 'ss-search';
            search.placeholder = select.dataset.searchable;
            search.autocomplete = 'off';
            search.setAttribute('aria-label', select.dataset.searchable);
            select.parentNode.insertBefore(search, select);

            search.addEventListener('input', function () {
                const terms = search.value.toLowerCase().split(/\s+/).filter(Boolean);
                const keep = select.value;
                select.innerHTML = '';
                let shown = 0;
                all.forEach(function (option, index) {
                    const text = option.textContent.toLowerCase();
                    if (index > 0 && option.value !== keep && !terms.every(function (term) { return text.indexOf(term) !== -1; })) return;
                    const copy = option.cloneNode(true);
                    copy.selected = option.value === keep;
                    select.appendChild(copy);
                    if (index > 0) shown++;
                });
                if (terms.length && shown === 0) {
                    const none = document.createElement('option');
                    none.value = '';
                    none.disabled = true;
                    none.textContent = 'No match';
                    select.appendChild(none);
                }
            });
        }

        function scan(root) { (root.querySelectorAll ? root.querySelectorAll('select[data-searchable]') : []).forEach(enhance); }
        scan(document);
        // The project view is loaded into a dialog after the page, so watch for it.
        if (!window.__searchableObserver) {
            window.__searchableObserver = new MutationObserver(function (records) {
                records.forEach(function (record) { record.addedNodes.forEach(function (node) { if (node.nodeType === 1) { if (node.matches && node.matches('select[data-searchable]')) enhance(node); scan(node); } }); });
            });
            window.__searchableObserver.observe(document.body, { childList: true, subtree: true });
        }
    })();
</script>
