/*
 * Purchase request attachments go from the browser straight to private Vercel Blob storage
 * (@vercel/blob "upload"), and the form is then sent with their references only. The serverless
 * function that saves the request accepts at most 4.5 MB per request, so posting a 20 MB
 * attachment with the form failed (413).
 *
 * Active only on a form the server marks with data-direct-upload-url (when Blob storage is
 * configured). The server issues one short-lived token per file, checks every reference and
 * runs the usual file checks before saving.
 */
import { upload } from '@vercel/blob/client';

const MULTIPART_FROM = 4 * 1024 * 1024;
const PARALLEL_UPLOADS = 3;

function tell(message) {
    if (typeof window.bacToast === 'function') window.bacToast(message, 'error');
    else window.alert(message);
}

function hidden(form, name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    input.dataset.directReference = '';
    form.appendChild(input);
}

function undo(form, button) {
    form.querySelectorAll('[data-direct-reference]').forEach((node) => node.remove());
    form.querySelectorAll('input[type="file"][data-direct-sent]').forEach((input) => {
        input.disabled = false;
        delete input.dataset.directSent;
    });
    if (button) {
        if (button.dataset.idleLabel !== undefined) {
            button.innerHTML = button.dataset.idleLabel;
            delete button.dataset.idleLabel;
        }
        button.disabled = false;
        button.removeAttribute('aria-busy');
    }
}

document.querySelectorAll('form[data-direct-upload-url]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        // The page's own checks run first; a blocked submit sends nothing.
        if (event.defaultPrevented || form.dataset.directUploadDone === '1') return;

        // Row position matters: it pairs the file with its "document type" box on the server.
        const inputs = Array.from(form.querySelectorAll('input[type="file"][name="documents[]"]'));
        const chosen = inputs.map((input, index) => ({ input, index, file: input.files && input.files[0] })).filter((item) => item.file);
        if (!chosen.length) return;

        event.preventDefault();
        const button = event.submitter || form.querySelector('button[type="submit"]');
        if (button) {
            if (button.dataset.idleLabel === undefined) button.dataset.idleLabel = button.innerHTML;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        let done = 0;
        const progress = () => { if (button) button.textContent = `Uploading files… ${done} of ${chosen.length}`; };
        progress();

        try {
            const queue = chosen.slice();
            await Promise.all(Array.from({ length: Math.min(PARALLEL_UPLOADS, queue.length) }, async () => {
                while (queue.length) {
                    const item = queue.shift();
                    const extension = (item.file.name.split('.').pop() || 'pdf').toLowerCase().replace(/[^a-z0-9]/g, '');
                    const blob = await upload(`${form.dataset.directUploadFolder}/${item.index}.${extension}`, item.file, {
                        access: 'private',
                        handleUploadUrl: form.dataset.directUploadUrl,
                        headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                        multipart: item.file.size > MULTIPART_FROM,
                    });
                    hidden(form, `uploaded_documents[r${item.index}]`, blob.url);
                    hidden(form, `uploaded_document_names[r${item.index}]`, item.file.name);
                    done += 1;
                    progress();
                }
            }));
        } catch (error) {
            console.error('[request upload]', error);
            undo(form, button);
            tell('Uploading the attachment failed. Check your connection and save again. Your other entries are still in the form.');
            return;
        }

        // The files are already in storage: they must not travel with the form as well.
        chosen.forEach(({ input }) => {
            input.dataset.directSent = '1';
            input.disabled = true;
        });
        if (button && button.name) hidden(form, button.name, button.value);
        form.dataset.directUploadDone = '1';
        form.submit();
    });
});
