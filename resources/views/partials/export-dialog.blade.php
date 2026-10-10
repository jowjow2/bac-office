{{--
    One export dialog for every page that downloads a file. Add `data-export-dialog` to the link that starts
    the export (its href is the file when there is only one format):

      data-export-title="Export payments"     dialog title
      data-export-count="12"                  optional: number of records included
      data-export-noun="payment"              what one record is called (singular)
      data-export-note="Filtered by …"        optional: one line about what is included
      data-export-formats='[{"label":"Excel (CSV)","hint":"…","url":"…","icon":"fa-file-csv","open":false}]'

    Include this partial once per page.
--}}
@once
<style>
    .xd-overlay { position: fixed; inset: 0; z-index: 400; display: grid; place-items: center; padding: 16px; background: rgba(15, 25, 21, .55); backdrop-filter: blur(2px); animation: xd-fade .18s ease both; }
    .xd-overlay[hidden] { display: none; }
    .xd-dialog { width: min(460px, 100%); overflow: hidden; border-radius: 16px; background: #fff; box-shadow: 0 24px 70px rgba(15, 25, 21, .35); font-family: var(--ui-font, 'Inter', system-ui, sans-serif); animation: xd-rise .26s cubic-bezier(.2, .8, .2, 1) both; }
    .xd-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 20px 22px 4px; }
    .xd-head h2 { margin: 0; color: #1b2420; font-size: 18px; font-weight: 700; letter-spacing: -.01em; }
    .xd-close { display: grid; width: 32px; height: 32px; flex: 0 0 32px; place-items: center; border: 1px solid #dfe6e1; border-radius: 9px; background: #fff; color: #6b7a72; font-size: 18px; line-height: 1; cursor: pointer; }
    .xd-close:hover { background: #f4f7f5; color: #1b2420; }
    .xd-body { padding: 10px 22px 18px; }
    .xd-summary { display: flex; align-items: center; gap: 12px; padding: 14px; border: 1px solid #dfe6e1; border-radius: 12px; background: #f7faf8; }
    .xd-summary__icon { display: grid; width: 40px; height: 40px; flex: 0 0 40px; place-items: center; border-radius: 10px; background: #e3f0ea; color: #1f5c45; font-size: 17px; }
    .xd-summary strong { display: block; color: #1b2420; font-size: 14.5px; }
    .xd-summary span { display: block; margin-top: 2px; color: #6b7a72; font-size: 12.5px; line-height: 1.4; }
    .xd-summary.is-empty .xd-summary__icon { background: #eef1ef; color: #8b9890; }
    .xd-label { margin: 16px 0 8px; color: #6b7a72; font-size: 11.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .xd-formats { display: grid; gap: 8px; }
    .xd-format { display: flex; align-items: center; gap: 12px; padding: 11px 13px; border: 1px solid #dfe6e1; border-radius: 11px; background: #fff; cursor: pointer; }
    .xd-format:hover { border-color: #9fc4b3; }
    .xd-format input { position: absolute; opacity: 0; pointer-events: none; }
    .xd-format i { color: #6b7a72; font-size: 17px; width: 20px; text-align: center; }
    .xd-format b { display: block; color: #1b2420; font-size: 13.5px; }
    .xd-format small { display: block; margin-top: 1px; color: #6b7a72; font-size: 12px; }
    .xd-format:has(input:checked) { border-color: #1f5c45; background: #e8f3ee; }
    .xd-format:has(input:checked) i { color: #1f5c45; }
    .xd-format:has(input:focus-visible) { outline: 2px solid #1f5c45; outline-offset: 2px; }
    .xd-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 22px; border-top: 1px solid #dfe6e1; background: #f7faf8; }
    .xd-btn { height: 38px; padding: 0 18px; border: 1px solid #cfd8d3; border-radius: 9px; background: #fff; color: #2d3a34; font: 600 13.5px/1 inherit; cursor: pointer; }
    .xd-btn--primary { border-color: #1f5c45; background: #1f5c45; color: #fff; }
    .xd-btn--primary:hover { background: #184a38; }
    .xd-btn:disabled { border-color: #dfe6e1; background: #eef1ef; color: #9aa6a0; cursor: not-allowed; }
    body.xd-open { overflow: hidden; }
    /* Header buttons (Export, Report) beside the page title. */
    body .main-area a.xd-hbtn { display: inline-flex !important; align-items: center !important; gap: 8px !important; height: 38px !important; padding: 0 16px !important; border: 1px solid var(--ui-line-strong, #d5ddd8) !important; border-radius: 9px !important; background: #fff !important; color: var(--ui-ink-2, #2d3a34) !important; -webkit-text-fill-color: var(--ui-ink-2, #2d3a34) !important; font: 600 13.5px/1 var(--ui-font, inherit) !important; text-decoration: none !important; }
    body .main-area a.xd-hbtn:hover { border-color: var(--ui-primary, #1f5c45) !important; background: var(--ui-primary-soft, #e8f3ee) !important; color: var(--ui-primary, #1f5c45) !important; -webkit-text-fill-color: var(--ui-primary, #1f5c45) !important; }
    body .main-area a.xd-hbtn i { color: inherit !important; -webkit-text-fill-color: currentColor !important; font-size: 12px !important; }
    @keyframes xd-fade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes xd-rise { from { opacity: 0; transform: translateY(12px) scale(.985); } to { opacity: 1; transform: none; } }
    @media (max-width: 520px) { .xd-overlay { align-items: end; padding: 0; } .xd-dialog { width: 100%; border-radius: 16px 16px 0 0; } }
    @media (prefers-reduced-motion: reduce) { .xd-overlay, .xd-dialog { animation: none; } }
</style>
<script>
    (function () {
        let overlay = null, trigger = null, chosen = null;

        const el = (tag, cls, html) => { const node = document.createElement(tag); if (cls) node.className = cls; if (html !== undefined) node.innerHTML = html; return node; };
        const esc = (text) => String(text ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

        const close = () => {
            if (!overlay) return;
            overlay.hidden = true;
            document.body.classList.remove('xd-open');
            if (trigger && trigger.isConnected) trigger.focus();
        };

        const open = (link) => {
            trigger = link;
            const data = link.dataset;
            const title = data.exportTitle || 'Export';
            const noun = data.exportNoun || 'record';
            const hasCount = data.exportCount !== undefined && data.exportCount !== '';
            const count = hasCount ? parseInt(data.exportCount, 10) : null;
            let formats = [];
            try { formats = JSON.parse(data.exportFormats || '[]'); } catch (e) { formats = []; }
            if (!formats.length) formats = [{ label: 'CSV file', hint: 'Opens in Excel, Sheets or any spreadsheet', url: link.href, icon: 'fa-file-csv' }];
            chosen = formats[0];

            overlay?.remove();
            overlay = el('div', 'xd-overlay');
            overlay.setAttribute('role', 'presentation');
            const empty = count === 0;
            const headline = hasCount
                ? (empty ? 'Nothing to export yet' : count.toLocaleString() + ' ' + noun + (count === 1 ? '' : 's') + ' will be exported')
                : 'The current report will be exported';
            overlay.innerHTML =
                '<div class="xd-dialog" role="dialog" aria-modal="true" aria-labelledby="xdTitle" tabindex="-1">' +
                    '<div class="xd-head"><h2 id="xdTitle">' + esc(title) + '</h2><button type="button" class="xd-close" data-xd-close aria-label="Close">&times;</button></div>' +
                    '<div class="xd-body">' +
                        '<div class="xd-summary' + (empty ? ' is-empty' : '') + '"><div class="xd-summary__icon"><i class="fas fa-file-export" aria-hidden="true"></i></div>' +
                            '<div><strong>' + esc(headline) + '</strong>' + (data.exportNote ? '<span>' + esc(data.exportNote) + '</span>' : '') + '</div></div>' +
                        (formats.length > 1 ? '<p class="xd-label">Format</p><div class="xd-formats"></div>' : '') +
                    '</div>' +
                    '<div class="xd-foot"><button type="button" class="xd-btn" data-xd-close>Cancel</button><button type="button" class="xd-btn xd-btn--primary" data-xd-go' + (empty ? ' disabled' : '') + '></button></div>' +
                '</div>';

            const go = overlay.querySelector('[data-xd-go]');
            const label = () => { go.textContent = empty ? 'Nothing to export' : (formats.length > 1 ? 'Export as ' + chosen.label.replace(/\s*\(.*\)$/, '') : 'Download ' + chosen.label.replace(/\s*\(.*\)$/, '')); };
            const list = overlay.querySelector('.xd-formats');
            if (list) {
                formats.forEach((format, index) => {
                    const row = el('label', 'xd-format',
                        '<input type="radio" name="xd-format"' + (index === 0 ? ' checked' : '') + '><i class="fas ' + esc(format.icon || 'fa-file') + '" aria-hidden="true"></i>' +
                        '<span><b>' + esc(format.label) + '</b>' + (format.hint ? '<small>' + esc(format.hint) + '</small>' : '') + '</span>');
                    row.querySelector('input').addEventListener('change', () => { chosen = format; label(); });
                    list.append(row);
                });
            }
            label();

            overlay.addEventListener('click', (event) => {
                if (event.target === overlay || event.target.closest('[data-xd-close]')) close();
            });
            go.addEventListener('click', () => {
                close();
                if (chosen.open) window.open(chosen.url, '_blank', 'noopener');
                else window.location.href = chosen.url;
            });
            document.body.append(overlay);
            document.body.classList.add('xd-open');
            (empty ? overlay.querySelector('[data-xd-close].xd-btn') : go).focus();
        };

        document.addEventListener('click', (event) => {
            const link = event.target.closest('[data-export-dialog]');
            if (!link || link.getAttribute('aria-disabled') === 'true') return;
            event.preventDefault();
            open(link);
        });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && overlay && !overlay.hidden) close(); });
    })();
</script>
@endonce
