{{--
    The Feedback button of the public pages opens this dialog instead of leaving the page. The form posts in the
    background (JSON); without JavaScript the button is still a link to the Feedback page.
    Included once by layouts/public.
--}}
@php $fbmKinds = \App\Models\Feedback::CATEGORIES; @endphp
<dialog id="feedbackModal" class="fbm" aria-labelledby="fbmTitle">
    <div class="fbm-card">
        <header class="fbm-head">
            <div>
                <h2 id="fbmTitle">How is the portal working for you?</h2>
                <p>Tell the BAC what to improve, what is not working, or what you liked. It takes about a minute.</p>
            </div>
            <button type="button" class="fbm-close" data-fbm-close aria-label="Close">&times;</button>
        </header>

        <div class="fbm-thanks" data-fbm-thanks hidden role="status">
            <svg viewBox="0 0 24 24" width="34" height="34" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#1f7a4d"/><path d="M7 12.5l3.2 3.2L17 9" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <strong>Thank you for your feedback.</strong>
            <p>The BAC reads every message. If you left an email, we may reply to it.</p>
            <button type="button" class="fbm-btn fbm-btn--primary" data-fbm-close>Close</button>
        </div>

        <form method="POST" action="{{ route('feedback.store') }}" class="fbm-form" data-fbm-form novalidate>
            @csrf
            <input type="hidden" name="page_url" value="">
            {{-- Only bots fill this in. --}}
            <div class="fbm-trap" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

            <p class="fbm-alert" data-fbm-alert role="alert" hidden></p>

            <fieldset class="fbm-field">
                <legend>How would you rate the portal? <span class="fbm-req" aria-hidden="true">*</span></legend>
                <div class="fbm-stars" role="radiogroup" aria-label="Rating">
                    @for($star = 5; $star >= 1; $star--)
                        <input type="radio" name="rating" id="fbm-star-{{ $star }}" value="{{ $star }}">
                        <label for="fbm-star-{{ $star }}" title="{{ $star }} {{ $star === 1 ? 'star' : 'stars' }}"><svg viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true"><path fill="currentColor" d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3 6.1 20.6l1.3-6.6L2.5 9.4l6.6-.8z"/></svg><span class="fbm-sr">{{ $star }} {{ $star === 1 ? 'star' : 'stars' }}</span></label>
                    @endfor
                </div>
                <span class="fbm-error" data-fbm-error="rating"></span>
            </fieldset>

            <fieldset class="fbm-field">
                <legend>What kind of feedback is it? <span class="fbm-req" aria-hidden="true">*</span></legend>
                <div class="fbm-kinds">
                    @foreach($fbmKinds as $key => $kind)
                        <label class="fbm-kind">
                            <input type="radio" name="category" value="{{ $key }}">
                            <span>{{ $kind['label'] }}</span>
                        </label>
                    @endforeach
                </div>
                <span class="fbm-error" data-fbm-error="category"></span>
            </fieldset>

            <div class="fbm-field">
                <label for="fbm-message">Your message <span class="fbm-req" aria-hidden="true">*</span></label>
                <textarea id="fbm-message" name="message" rows="4" maxlength="2000" placeholder="What happened, or what would you like to see?" required></textarea>
                <span class="fbm-error" data-fbm-error="message"></span>
            </div>

            <div class="fbm-row">
                <div class="fbm-field">
                    <label for="fbm-name">Name <span class="fbm-opt">(optional)</span></label>
                    <input id="fbm-name" type="text" name="name" maxlength="120" value="{{ auth()->user()?->name }}" autocomplete="name">
                </div>
                <div class="fbm-field">
                    <label for="fbm-email">Email <span class="fbm-opt">(optional, for a reply)</span></label>
                    <input id="fbm-email" type="email" name="email" maxlength="190" value="{{ auth()->user()?->email }}" autocomplete="email">
                    <span class="fbm-error" data-fbm-error="email"></span>
                </div>
            </div>

            <p class="fbm-note">We do not keep your IP address. Your name and email are used only to reply. See the <a href="{{ route('privacy') }}">Privacy Policy</a>.</p>

            <footer class="fbm-foot">
                <button type="button" class="fbm-btn" data-fbm-close>Cancel</button>
                <button type="submit" class="fbm-btn fbm-btn--primary" data-fbm-submit>Send feedback</button>
            </footer>
        </form>
    </div>
</dialog>

<style>
    .fbm { width: min(560px, calc(100vw - 24px)); max-height: calc(100dvh - 24px); margin: auto; padding: 0; border: 0; border-radius: 16px; background: #fff; color: #1b2420; box-shadow: 0 24px 70px rgba(15, 25, 21, .35); font-family: inherit; overflow: hidden; }
    .fbm[open] { display: flex; animation: fbm-rise .22s cubic-bezier(.2, .8, .2, 1) both; }
    .fbm::backdrop { background: rgba(15, 25, 21, .55); backdrop-filter: blur(2px); }
    .fbm-card { display: flex; flex-direction: column; width: 100%; min-height: 0; }
    .fbm-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 20px 22px 8px; }
    .fbm-head h2 { margin: 0; font-size: 19px; letter-spacing: -.01em; }
    .fbm-head p { margin: 4px 0 0; color: #6b7a72; font-size: 13.5px; line-height: 1.5; }
    .fbm.is-done .fbm-head > div { display: none; }
    .fbm.is-done .fbm-head { justify-content: flex-end; padding-bottom: 0; }
    .fbm-close { flex: 0 0 34px; width: 34px; height: 34px; border: 1px solid #dfe6e1; border-radius: 9px; background: #fff; color: #6b7a72; font-size: 20px; line-height: 1; cursor: pointer; }
    .fbm-close:hover { background: #f4f7f5; color: #1b2420; }
    .fbm-form { display: grid; gap: 16px; padding: 12px 22px 20px; overflow-y: auto; }
    .fbm-field { display: grid; gap: 6px; margin: 0; padding: 0; border: 0; min-width: 0; }
    .fbm-field > label, .fbm-field > legend { padding: 0; font-size: 13.5px; font-weight: 600; }
    .fbm-req { color: #b42318; }
    .fbm-opt { color: #6b7a72; font-weight: 400; font-size: 12px; }
    .fbm-field input[type="text"], .fbm-field input[type="email"], .fbm-field textarea { width: 100%; padding: 9px 11px; border: 1px solid #d2cbbb; border-radius: 10px; background: #fff; font: inherit; font-size: 14px; color: #1b2420; }
    .fbm-field textarea { resize: vertical; min-height: 90px; }
    .fbm-field input:focus-visible, .fbm-field textarea:focus-visible { outline: 2px solid rgba(29, 79, 64, .4); outline-offset: 1px; }
    .fbm-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .fbm-error { color: #b42318; font-size: 12.5px; }
    .fbm-error:empty { display: none; }
    .fbm-alert { margin: 0; padding: 9px 12px; border-radius: 10px; background: #fdecea; color: #b42318; font-size: 13px; }
    .fbm-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
    .fbm-stars { display: inline-flex; flex-direction: row-reverse; justify-content: flex-end; gap: 4px; }
    .fbm-stars input { position: absolute; opacity: 0; pointer-events: none; }
    .fbm-stars label { color: #cfd6d2; font-size: 30px; line-height: 1; cursor: pointer; transition: color .12s ease, transform .12s ease; }
    .fbm-stars svg { display: block; width: 1em; height: 1em; }
    .fbm-stars label:hover, .fbm-stars label:hover ~ label, .fbm-stars input:checked ~ label { color: #e0a82e; }
    .fbm-stars label:hover { transform: scale(1.1); }
    .fbm-stars input:focus-visible + label { outline: 2px solid rgba(29, 79, 64, .5); outline-offset: 3px; border-radius: 4px; }
    .fbm-kinds { display: flex; flex-wrap: wrap; gap: 8px; }
    .fbm-kind { position: relative; cursor: pointer; }
    .fbm-kind input { position: absolute; opacity: 0; pointer-events: none; }
    .fbm-kind span { display: inline-flex; align-items: center; padding: 8px 14px; border: 1px solid #d2cbbb; border-radius: 999px; background: #fff; color: #34413b; font-size: 13.5px; font-weight: 600; }
    .fbm-kind input:checked + span { border-color: #1d4f40; background: #e4f0ea; color: #1d4f40; }
    .fbm-kind input:focus-visible + span { outline: 2px solid rgba(29, 79, 64, .5); outline-offset: 2px; }
    .fbm-note { margin: 0; color: #6b7a72; font-size: 12px; line-height: 1.55; }
    .fbm-note a { color: #1d4f40; }
    .fbm-foot { display: flex; justify-content: flex-end; gap: 8px; padding-top: 2px; }
    .fbm-btn { min-height: 40px; padding: 0 18px; border: 1px solid #d2cbbb; border-radius: 10px; background: #fff; color: #1b2420; font: inherit; font-size: 14px; font-weight: 600; cursor: pointer; }
    .fbm-btn:hover { background: #f4f7f5; }
    .fbm-btn--primary { border-color: #1d4f40; background: #1d4f40; color: #fff; }
    .fbm-btn--primary:hover { background: #174035; }
    .fbm-btn[disabled] { opacity: .6; cursor: progress; }
    .fbm-trap { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
    .fbm-thanks { display: grid; justify-items: center; gap: 8px; padding: 26px 22px 28px; text-align: center; }
    .fbm-thanks[hidden] { display: none; }
    .fbm-thanks strong { font-size: 17px; }
    .fbm-thanks p { margin: 0 0 8px; color: #55615b; line-height: 1.55; }
    body.fbm-open { overflow: hidden; }
    @keyframes fbm-rise { from { opacity: 0; transform: translateY(12px) scale(.98); } to { opacity: 1; transform: none; } }
    @media (max-width: 560px) { .fbm-row { grid-template-columns: 1fr; } .fbm-stars label { font-size: 34px; } }
    @media (prefers-reduced-motion: reduce) { .fbm[open] { animation: none; } }
    @media print { .fbm { display: none !important; } }
</style>

<script>
    (function () {
        const dialog = document.getElementById('feedbackModal');
        if (!dialog || typeof dialog.showModal !== 'function') return; // the link to the Feedback page stays the fallback

        const form = dialog.querySelector('[data-fbm-form]');
        const thanks = dialog.querySelector('[data-fbm-thanks]');
        const alertBox = dialog.querySelector('[data-fbm-alert]');
        const submit = dialog.querySelector('[data-fbm-submit]');
        let opener = null;

        const clearErrors = () => {
            alertBox.hidden = true;
            alertBox.textContent = '';
            dialog.querySelectorAll('[data-fbm-error]').forEach((node) => { node.textContent = ''; });
        };
        const open = (trigger) => {
            opener = trigger || null;
            clearErrors();
            thanks.hidden = true;
            form.hidden = false;
            dialog.classList.remove('is-done');
            form.querySelector('[name="page_url"]').value = window.location.pathname;
            dialog.showModal();
            document.body.classList.add('fbm-open');
            form.querySelector('input[name="rating"]:checked, #fbm-star-5')?.focus();
        };
        const close = () => { if (dialog.open) dialog.close(); };

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-feedback-open]');
            if (trigger) {
                event.preventDefault();
                open(trigger);
                return;
            }
            if (event.target.closest('[data-fbm-close]') && dialog.contains(event.target)) close();
            else if (event.target === dialog) close();
        });
        dialog.addEventListener('close', () => {
            document.body.classList.remove('fbm-open');
            if (opener && opener.isConnected) opener.focus();
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors();
            submit.disabled = true;
            submit.textContent = 'Sending...';
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form),
                    credentials: 'same-origin',
                });
                const data = await response.json().catch(() => ({}));
                if (response.ok && data.ok !== false) {
                    form.reset();
                    form.hidden = true;
                    thanks.hidden = false;
                    dialog.classList.add('is-done');
                    thanks.querySelector('button')?.focus();
                    return;
                }
                const errors = data.errors || {};
                let shown = 0;
                Object.keys(errors).forEach((field) => {
                    const node = dialog.querySelector('[data-fbm-error="' + field + '"]');
                    if (node) { node.textContent = errors[field][0]; shown += 1; }
                });
                if (!shown) {
                    alertBox.textContent = data.message || 'Your feedback could not be sent. Please try again.';
                    alertBox.hidden = false;
                } else {
                    dialog.querySelector('[data-fbm-error]:not(:empty)')?.scrollIntoView({ block: 'nearest' });
                }
            } catch (error) {
                alertBox.textContent = 'Check your connection, then try again.';
                alertBox.hidden = false;
            } finally {
                submit.disabled = false;
                submit.textContent = 'Send feedback';
            }
        });
    })();
</script>
