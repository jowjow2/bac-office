{{--
    Confirmations and toasts for every signed-in page (included by notification-live).

    Confirm before a form submits:
        <form ... data-confirm="Message" data-confirm-title="Delete this project?"
              data-confirm-button="Delete project" data-confirm-tone="danger"
              data-confirm-points="Removes its bids|Cannot be undone">
    From script:  if (await window.bacConfirm({ title, message, confirmLabel, tone, points })) { ... }
    Toasts:       window.bacToast('Saved.', 'success' | 'error' | 'info')
    The older fixed #successAlert / #errorAlert boxes are turned into toasts on load.
--}}
@once
<style>
    .bac-confirm { width: min(440px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); margin: auto; padding: 0; border: 0; border-radius: 14px; background: #fff; color: var(--ui-ink, #1b2420); box-shadow: 0 24px 60px rgba(27, 36, 32, .28); overflow: auto; }
    .bac-confirm::backdrop { background: rgba(27, 36, 32, .45); }
    .bac-confirm[open] { animation: bac-confirm-in .16s ease-out; }
    @keyframes bac-confirm-in { from { opacity: 0; transform: translateY(8px) scale(.98); } }
    .bac-confirm__body { display: grid; grid-template-columns: 40px minmax(0, 1fr); gap: 14px; padding: 22px 22px 18px; }
    .bac-confirm__icon { display: grid; width: 40px; height: 40px; place-items: center; border-radius: 50%; background: var(--ui-primary-soft, #e8f1ec); color: var(--ui-primary, #1d4f40); font-size: 17px; }
    .bac-confirm.is-danger .bac-confirm__icon { background: var(--ui-danger-soft, #fbe9e7); color: var(--ui-danger, #b42318); }
    .bac-confirm__title { margin: 2px 0 6px; font-size: 16px; font-weight: 700; line-height: 1.35; }
    .bac-confirm__message { margin: 0; color: var(--ui-ink-2, #3c4641); font-size: 13.5px; line-height: 1.55; overflow-wrap: anywhere; }
    .bac-confirm__points { display: grid; gap: 6px; margin: 12px 0 0; padding: 10px 12px; border-radius: 10px; background: var(--ui-surface-2, #f7f5ef); list-style: none; }
    .bac-confirm__points li { display: grid; grid-template-columns: 14px minmax(0, 1fr); gap: 8px; color: var(--ui-ink-2, #3c4641); font-size: 12.5px; line-height: 1.45; }
    .bac-confirm__points li::before { content: ''; width: 6px; height: 6px; margin: 6px 0 0 4px; border-radius: 50%; background: currentColor; opacity: .5; }
    .bac-confirm.is-danger .bac-confirm__points { background: var(--ui-danger-soft, #fbe9e7); }
    .bac-confirm__foot { display: flex; justify-content: flex-end; gap: 8px; padding: 14px 22px 18px; border-top: 1px solid var(--ui-line-soft, #efebe2); }
    .bac-confirm :is(.bac-confirm__btn, #bac-x#bac-x) { display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 7px !important; min-height: 38px !important; padding: 0 16px !important; border: 1px solid var(--ui-line-strong, #d9d4c7) !important; border-radius: 9px !important; background: #fff !important; color: var(--ui-ink, #1b2420) !important; -webkit-text-fill-color: currentColor !important; font: inherit !important; font-size: 13.5px !important; font-weight: 600 !important; cursor: pointer; box-shadow: none !important; }
    .bac-confirm :is(.bac-confirm__btn--go, #bac-x#bac-x) { border-color: var(--ui-primary, #1d4f40) !important; background: var(--ui-primary, #1d4f40) !important; color: #fff !important; }
    .bac-confirm.is-danger :is(.bac-confirm__btn--go, #bac-x#bac-x) { border-color: var(--ui-danger, #b42318) !important; background: var(--ui-danger, #b42318) !important; }
    .bac-confirm :is(.bac-confirm__btn:focus-visible, #bac-x#bac-x) { outline: 3px solid #9bc9b7 !important; outline-offset: 2px !important; }

    .bac-toasts { position: fixed; right: 20px; bottom: 20px; z-index: 10070; display: grid; gap: 10px; width: min(380px, calc(100vw - 32px)); pointer-events: none; }
    .bac-toast { position: relative; display: grid; grid-template-columns: 30px minmax(0, 1fr) 24px; gap: 12px; align-items: start; padding: 13px 12px 13px 14px; overflow: hidden; border: 1px solid var(--ui-line, #e6e1d6); border-left: 4px solid var(--toast-accent, var(--ui-primary, #1d4f40)); border-radius: 12px; background: #fff; box-shadow: 0 16px 36px rgba(27, 36, 32, .18); color: var(--ui-ink, #1b2420); pointer-events: auto; animation: bac-toast-in .2s ease-out; }
    .bac-toast.is-leaving { opacity: 0; transform: translateY(6px); transition: opacity .2s ease, transform .2s ease; }
    @keyframes bac-toast-in { from { opacity: 0; transform: translateY(10px); } }
    .bac-toast--success { --toast-accent: var(--ui-success, #1d6a4d); --toast-soft: var(--ui-success-soft, #e2f2ea); }
    .bac-toast--error { --toast-accent: var(--ui-danger, #b42318); --toast-soft: var(--ui-danger-soft, #fbe9e7); }
    .bac-toast--info { --toast-accent: var(--ui-info, #1f4f86); --toast-soft: var(--ui-info-soft, #e6eef8); }
    .bac-toast__icon { display: grid; width: 30px; height: 30px; place-items: center; border-radius: 50%; background: var(--toast-soft); color: var(--toast-accent); font-size: 14px; }
    .bac-toast__text { display: grid; gap: 2px; min-width: 0; padding-top: 1px; }
    .bac-toast__text strong { font-size: 13.5px; font-weight: 700; line-height: 1.3; }
    .bac-toast__text span { color: var(--ui-ink-2, #3c4641); font-size: 13px; line-height: 1.45; overflow-wrap: anywhere; }
    .bac-toasts :is(.bac-toast__close, #bac-x#bac-x) { display: grid !important; width: 24px !important; height: 24px !important; min-height: 0 !important; place-items: center !important; padding: 0 !important; border: 0 !important; border-radius: 6px !important; background: transparent !important; color: var(--ui-subtle, #78827c) !important; -webkit-text-fill-color: currentColor !important; cursor: pointer; box-shadow: none !important; }
    .bac-toasts :is(.bac-toast__close:hover, #bac-x#bac-x) { background: var(--ui-surface-2, #f7f5ef) !important; color: var(--ui-ink, #1b2420) !important; }
    .bac-toast__timer { position: absolute; left: 0; bottom: 0; height: 2px; background: var(--toast-accent); animation: bac-toast-timer linear forwards; }
    @keyframes bac-toast-timer { from { width: 100%; } to { width: 0; } }
    @media (max-width: 640px) { .bac-toasts { right: 16px; left: 16px; bottom: 16px; width: auto; } }
    @media (prefers-reduced-motion: reduce) { .bac-confirm[open], .bac-toast { animation: none; } .bac-toast__timer { display: none; } }
</style>

<script>
    (function () {
        const icons = { success: 'fa-circle-check', error: 'fa-circle-exclamation', info: 'fa-circle-info' };
        const titles = { success: 'Done', error: 'Something needs attention', info: 'Notice' };

        function stack() {
            let region = document.querySelector('.bac-toasts');
            if (!region) {
                region = document.createElement('div');
                region.className = 'bac-toasts';
                region.setAttribute('aria-live', 'polite');
                document.body.appendChild(region);
            }
            return region;
        }

        /* A toast at the bottom right; errors stay until closed. */
        window.bacToast = function (message, tone, options) {
            tone = icons[tone] ? tone : 'success';
            options = options || {};
            const persist = options.persist ?? tone === 'error';
            const toast = document.createElement('div');
            toast.className = 'bac-toast bac-toast--' + tone;
            toast.setAttribute('role', tone === 'error' ? 'alert' : 'status');
            toast.innerHTML = '<span class="bac-toast__icon" aria-hidden="true"><i class="fas ' + icons[tone] + '"></i></span>'
                + '<span class="bac-toast__text"><strong></strong><span></span></span>'
                + '<button type="button" class="bac-toast__close" aria-label="Dismiss"><i class="fas fa-xmark" aria-hidden="true"></i></button>';
            toast.querySelector('strong').textContent = options.title || titles[tone];
            toast.querySelector('.bac-toast__text span').textContent = String(message || '').trim();
            const dismiss = function () {
                toast.classList.add('is-leaving');
                setTimeout(function () { toast.remove(); }, 200);
            };
            toast.querySelector('.bac-toast__close').addEventListener('click', dismiss);
            if (!persist) {
                const ms = options.duration || 6000;
                const timer = document.createElement('span');
                timer.className = 'bac-toast__timer';
                timer.style.animationDuration = ms + 'ms';
                toast.appendChild(timer);
                let left = setTimeout(dismiss, ms);
                // Hovering keeps it up for reading.
                toast.addEventListener('mouseenter', function () { clearTimeout(left); timer.style.animationPlayState = 'paused'; });
                toast.addEventListener('mouseleave', function () { left = setTimeout(dismiss, 2500); timer.style.animationPlayState = 'running'; });
            }
            stack().appendChild(toast);
            return toast;
        };

        /* A confirmation dialog; resolves true when confirmed. */
        window.bacConfirm = function (options) {
            options = typeof options === 'string' ? { message: options } : (options || {});
            return new Promise(function (resolve) {
                const danger = options.tone === 'danger';
                const dialog = document.createElement('dialog');
                dialog.className = 'bac-confirm' + (danger ? ' is-danger' : '');
                dialog.setAttribute('aria-labelledby', 'bac-confirm-title');
                dialog.innerHTML = '<div class="bac-confirm__body">'
                    + '<span class="bac-confirm__icon" aria-hidden="true"><i class="fas ' + (danger ? 'fa-triangle-exclamation' : (options.icon || 'fa-circle-question')) + '"></i></span>'
                    + '<div><h2 class="bac-confirm__title" id="bac-confirm-title"></h2><p class="bac-confirm__message"></p></div></div>'
                    + '<div class="bac-confirm__foot"><button type="button" class="bac-confirm__btn" data-answer="no"></button><button type="button" class="bac-confirm__btn bac-confirm__btn--go" data-answer="yes"></button></div>';
                dialog.querySelector('.bac-confirm__title').textContent = options.title || 'Are you sure?';
                dialog.querySelector('.bac-confirm__message').textContent = options.message || '';
                const points = (options.points || []).filter(Boolean);
                if (points.length) {
                    const list = document.createElement('ul');
                    list.className = 'bac-confirm__points';
                    points.forEach(function (point) { const item = document.createElement('li'); item.textContent = point; list.appendChild(item); });
                    dialog.querySelector('.bac-confirm__message').after(list);
                }
                dialog.querySelector('[data-answer="no"]').textContent = options.cancelLabel || 'Cancel';
                dialog.querySelector('[data-answer="yes"]').textContent = options.confirmLabel || 'Continue';

                let answered = false;
                const finish = function (value) {
                    if (answered) return;
                    answered = true;
                    dialog.close();
                    dialog.remove();
                    resolve(value);
                };
                dialog.addEventListener('click', function (event) {
                    const button = event.target.closest('[data-answer]');
                    if (button) finish(button.dataset.answer === 'yes');
                    else if (event.target === dialog) finish(false);
                });
                dialog.addEventListener('cancel', function (event) { event.preventDefault(); finish(false); });
                document.body.appendChild(dialog);
                dialog.showModal();
                // The safe choice has the focus for a destructive action.
                dialog.querySelector(danger ? '[data-answer="no"]' : '[data-answer="yes"]').focus();
            });
        };

        /* Forms with data-confirm ask first. */
        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
            if (form.dataset.confirmed === '1') { delete form.dataset.confirmed; return; }
            event.preventDefault();
            event.stopImmediatePropagation();
            const submitter = event.submitter;
            window.bacConfirm({
                title: form.dataset.confirmTitle,
                message: form.dataset.confirm,
                confirmLabel: form.dataset.confirmButton,
                tone: form.dataset.confirmTone,
                points: (form.dataset.confirmPoints || '').split('|'),
            }).then(function (yes) {
                if (!yes) return;
                form.dataset.confirmed = '1';
                if (submitter && form.contains(submitter)) form.requestSubmit(submitter); else form.requestSubmit();
            });
        }, true);

        /* Older pages print fixed alert boxes; show them as toasts instead. */
        document.addEventListener('DOMContentLoaded', function () {
            [['#successAlert', 'success'], ['#errorAlert', 'error']].forEach(function (pair) {
                const box = document.querySelector(pair[0]);
                if (!box) return;
                const text = (box.querySelector('span') || box).textContent.trim();
                box.remove();
                if (text) window.bacToast(text, pair[1]);
            });
        });
    })();
</script>
@endonce
