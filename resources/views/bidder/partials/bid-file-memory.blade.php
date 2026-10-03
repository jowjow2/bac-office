{{--
    Keeps the files attached in the Submit Bid form in this browser (IndexedDB)
    until the bid is submitted, the file is removed, or the bidder signs out,
    so a reload (e.g. a submission sent back with an error) or reopening the
    page does not make the bidder attach everything again. Files never leave
    the device until the form is submitted. Kept for 3 days at most.
--}}
<script>
    (function () {
        if (!window.indexedDB || typeof DataTransfer === 'undefined') return;

        const DB_NAME = 'bac-bid-files';
        const STORE = 'files';
        const MAX_AGE = 3 * 24 * 60 * 60 * 1000;

        function open() {
            return new Promise(function (resolve, reject) {
                const request = indexedDB.open(DB_NAME, 1);
                request.onupgradeneeded = function () { request.result.createObjectStore(STORE); };
                request.onsuccess = function () { resolve(request.result); };
                request.onerror = function () { reject(request.error); };
            });
        }
        function run(mode, action) {
            return open().then(function (db) {
                return new Promise(function (resolve, reject) {
                    const tx = db.transaction(STORE, mode);
                    const result = action(tx.objectStore(STORE));
                    tx.oncomplete = function () { db.close(); resolve(result && 'result' in result ? result.result : undefined); };
                    tx.onerror = function () { db.close(); reject(tx.error); };
                });
            });
        }
        const keyOf = function (form, input) {
            const match = /documents\[([^\]]+)\]/.exec(input.name || '');
            return match ? [form.dataset.owner, form.dataset.projectId, match[1]].join(':') : null;
        };
        const save = function (key, file) {
            return run('readwrite', function (store) { store.put({ name: file.name, type: file.type, lastModified: file.lastModified, blob: file, savedAt: Date.now() }, key); });
        };
        const forget = function (key) { return run('readwrite', function (store) { store.delete(key); }); };
        const forgetProject = function (owner, projectId) {
            return run('readwrite', function (store) {
                const prefix = owner + ':' + projectId + ':';
                store.openCursor().onsuccess = function (event) {
                    const cursor = event.target.result;
                    if (!cursor) return;
                    if (String(cursor.key).startsWith(prefix)) cursor.delete();
                    cursor.continue();
                };
            });
        };

        // A bid sent last time and not returned with errors was accepted: its kept files are done.
        let submitted = {};
        try { submitted = JSON.parse(sessionStorage.getItem('bac-bid-submitted') || '{}'); sessionStorage.removeItem('bac-bid-submitted'); } catch (error) { submitted = {}; }
        Object.keys(submitted).forEach(function (projectId) {
            const form = document.querySelector('[data-bid-form][data-project-id="' + projectId + '"]');
            // Projects whose form is on the page are handled below, before their files are restored.
            if (!form) forgetProject(submitted[projectId], projectId).catch(function () {});
        });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setUp);
        } else {
            setUp();
        }

        function setUp() {
        const forms = Array.from(document.querySelectorAll('[data-bid-form][data-owner][data-project-id]'))
            .filter(function (form) { return form.dataset.paymentLocked !== 'true'; });
        if (!forms.length) return;

        forms.forEach(function (form) {
            const owner = form.dataset.owner;
            const projectId = form.dataset.projectId;
            const returnedWithErrors = Boolean(form.querySelector('[data-sb-errors]'));
            const inputs = Array.from(form.querySelectorAll('[data-upload-input]'));

            const ready = submitted[projectId] && !returnedWithErrors
                ? forgetProject(owner, projectId)
                : Promise.resolve();

            ready.then(function () {
                // Put the kept files back into their requirement rows.
                return Promise.all(inputs.map(function (input) {
                    const key = keyOf(form, input);
                    if (!key || (input.files && input.files.length)) return null;
                    return run('readonly', function (store) { return store.get(key); }).then(function (entry) {
                        if (!entry) return;
                        if (Date.now() - (entry.savedAt || 0) > MAX_AGE) return forget(key);
                        const transfer = new DataTransfer();
                        transfer.items.add(new File([entry.blob], entry.name, { type: entry.type, lastModified: entry.lastModified }));
                        input.files = transfer.files;
                        input.dataset.restored = '1';
                        input.dataset.restoring = '1';
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                        delete input.dataset.restoring;
                    });
                }));
            }).then(function () {
                const restored = inputs.filter(function (input) { return input.dataset.restored === '1'; }).length;
                const status = form.closest('[data-bid-dialog]')?.querySelector('[data-bid-status]');
                if (restored && status && !status.textContent.includes('kept')) {
                    status.textContent = restored + ' attached ' + (restored === 1 ? 'file was' : 'files were') + ' kept from before. ' + status.textContent;
                }
            }).catch(function () { /* Best effort: the form works without it. */ });

            // Remember each attached file; forget it when removed.
            form.addEventListener('change', function (event) {
                const input = event.target.closest('[data-upload-input]');
                const key = input ? keyOf(form, input) : null;
                if (!key || input.dataset.restoring === '1') return;
                const file = input.files && input.files[0];
                (file ? save(key, file) : forget(key)).catch(function () {});
            });
            // "Remove" stops its click from bubbling, so listen while it travels down.
            form.addEventListener('click', function (event) {
                const remove = event.target.closest('[data-upload-remove]');
                const input = remove ? remove.closest('[data-upload-box]')?.querySelector('[data-upload-input]') : null;
                const key = input ? keyOf(form, input) : null;
                if (key) forget(key).catch(function () {});
            }, true);

            form.addEventListener('submit', function (event) {
                // After the page's own checks ran: a submit blocked as incomplete sent nothing.
                setTimeout(function () {
                    if (event.defaultPrevented) return;
                    try {
                        const marks = JSON.parse(sessionStorage.getItem('bac-bid-submitted') || '{}');
                        marks[projectId] = owner;
                        sessionStorage.setItem('bac-bid-submitted', JSON.stringify(marks));
                    } catch (error) { /* ignore */ }
                }, 0);
            });
        });
        }
    })();
</script>
