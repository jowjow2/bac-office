/*
 * Online bid files go from the browser straight to private Vercel Blob storage
 * (@vercel/blob "upload"), then the bid form is sent with their references only.
 * The serverless function that saves the bid accepts at most 4.5 MB per request,
 * so posting a full set of documents with the form failed (413).
 *
 * Active only on forms the server marks with data-direct-upload-url (when Blob
 * storage is configured). The server issues one short-lived token per file,
 * checks every reference, and runs the usual file checks before saving.
 * Progress shows on the Submit button itself; three files upload at a time.
 */
import { upload } from '@vercel/blob/client';

const MULTIPART_FROM = 4 * 1024 * 1024;
const PARALLEL_UPLOADS = 3;

function statusOf(form) {
    return form.closest('[data-bid-dialog]')?.querySelector('[data-bid-status]') || null;
}

function say(form, text, state) {
    const status = statusOf(form);
    if (!status) return;
    status.textContent = text;
    status.classList.toggle('is-ready', state === 'ok');
    status.classList.toggle('is-error', state === 'error');
}

function submitButton(form) {
    return form.closest('[data-bid-dialog]')?.querySelector('[data-bid-submit]') || null;
}

/* The Submit button becomes the progress indicator while the files go up. */
function ensureSpinnerStyle() {
    if (document.getElementById('bid-upload-spinner-style')) return;
    const style = document.createElement('style');
    style.id = 'bid-upload-spinner-style';
    style.textContent = '.bid-upload-spinner{display:inline-block;width:14px;height:14px;margin-right:8px;vertical-align:-2px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:bid-upload-spin .7s linear infinite}@keyframes bid-upload-spin{to{transform:rotate(360deg)}}@media (prefers-reduced-motion:reduce){.bid-upload-spinner{animation-duration:2s}}[data-bid-submit][aria-busy="true"]{opacity:1!important;cursor:progress!important}';
    document.head.appendChild(style);
}

function setBusy(form, label) {
    const button = submitButton(form);
    if (!button) return;
    if (button.dataset.idleLabel === undefined) button.dataset.idleLabel = button.innerHTML;
    ensureSpinnerStyle();
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.innerHTML = `<span class="bid-upload-spinner" aria-hidden="true"></span>${label}`;
}

function reset(form) {
    form.querySelectorAll('[data-direct-reference]').forEach((input) => input.remove());
    form.querySelectorAll('[data-upload-input][data-direct-sent]').forEach((input) => {
        input.disabled = false;
        delete input.dataset.directSent;
    });
    const button = submitButton(form);
    if (button) {
        if (button.dataset.idleLabel !== undefined) {
            button.innerHTML = button.dataset.idleLabel;
            delete button.dataset.idleLabel;
        }
        button.disabled = false;
        button.removeAttribute('aria-busy');
    }
}

function hidden(form, name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    input.dataset.directReference = '';
    form.appendChild(input);
}

document.querySelectorAll('[data-bid-form][data-direct-upload-url]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        // The page's own checks run first; a blocked submit sends nothing.
        if (event.defaultPrevented) return;
        const inputs = Array.from(form.querySelectorAll('[data-upload-input]')).filter((input) => !input.disabled && input.files && input.files.length);
        if (!inputs.length) return;

        event.preventDefault();
        say(form, '');
        setBusy(form, 'Uploading… 0%');

        const token = form.querySelector('input[name="_token"]')?.value || document.querySelector('meta[name="csrf-token"]')?.content || '';
        const folder = form.dataset.directUploadFolder;
        const total = inputs.reduce((sum, input) => sum + input.files[0].size, 0);
        const loadedByFile = new Map();
        // Set when one file fails, so uploads still running add nothing to the reset form.
        let aborted = false;
        const showProgress = () => {
            const loaded = Array.from(loadedByFile.values()).reduce((sum, value) => sum + value, 0);
            setBusy(form, `Uploading… ${total ? Math.min(99, Math.round((loaded / total) * 100)) : 0}%`);
        };

        const uploadOne = async (input) => {
            const key = /documents\[([^\]]+)\]/.exec(input.name)?.[1];
            const file = input.files[0];
            if (!key) return;
            const extension = (file.name.split('.').pop() || 'pdf').toLowerCase().replace(/[^a-z0-9]/g, '');
            const blob = await upload(`${folder}/${key}.${extension}`, file, {
                access: 'private',
                handleUploadUrl: form.dataset.directUploadUrl,
                headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                multipart: file.size > MULTIPART_FROM,
                onUploadProgress: ({ loaded }) => {
                    if (aborted) return;
                    loadedByFile.set(key, loaded);
                    showProgress();
                },
            });
            if (aborted) return;
            loadedByFile.set(key, file.size);
            showProgress();
            hidden(form, `uploaded_documents[${key}]`, blob.url);
            hidden(form, `uploaded_document_names[${key}]`, file.name);
            // Sent already: leave it out of the form data (it stays attached here).
            input.disabled = true;
            input.dataset.directSent = '';
        };

        try {
            // A few files at a time: faster than one by one, gentle on slow connections.
            const queue = inputs.slice();
            await Promise.all(Array.from({ length: Math.min(PARALLEL_UPLOADS, queue.length) }, async () => {
                while (queue.length && !aborted) await uploadOne(queue.shift());
            }));
        } catch (error) {
            aborted = true;
            reset(form);
            say(form, 'Uploading your documents failed. Check your connection and press Submit again. Your files are still attached.', 'error');
            console.error('[bid upload]', error);
            return;
        }

        setBusy(form, 'Submitting…');
        // Lets the kept-files memory (bid-file-memory) clear this bid's copies once it is accepted.
        try {
            const marks = JSON.parse(sessionStorage.getItem('bac-bid-submitted') || '{}');
            marks[form.dataset.projectId] = form.dataset.owner;
            sessionStorage.setItem('bac-bid-submitted', JSON.stringify(marks));
        } catch (error) { /* ignore */ }
        // Bypass the submit listeners: the checks already passed for this submit.
        HTMLFormElement.prototype.submit.call(form);
    });

    // Back from the browser cache after a submit: make the form usable again.
    window.addEventListener('pageshow', (event) => { if (event.persisted) reset(form); });
});
