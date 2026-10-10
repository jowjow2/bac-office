/*
 * Create procurement project wizard (resources/views/admin/projects-wizard.blade.php).
 *
 * The form posts to admin.projects.wizard.store exactly as before:
 * status=draft saves a draft project, status=open asks the server to publish
 * it in this BAC system. Everything here is presentation and early feedback;
 * the server repeats every check.
 */

import { groupDigits } from './money-input';

const root = document.querySelector('[data-pw]');
const form = document.getElementById('projectWizardForm');

if (root && form) {
    const rules = JSON.parse(document.getElementById('pwRules')?.textContent || '{}');
    const $ = (selector, scope = root) => scope.querySelector(selector);
    const $$ = (selector, scope = root) => Array.from(scope.querySelectorAll(selector));
    const byId = (id) => document.getElementById(id);

    const STEP_NAMES = ['Project info', 'Requirements', 'Documents', 'Dates', 'Review'];
    const TOTAL = STEP_NAMES.length;
    const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
    const MAX_BYTES = (rules.maxUploadKb || 20480) * 1024;

    let current = 1;
    let submitting = false;
    let dirty = false;

    document.body.classList.add('pw-open');

    /* ------------------------------------------------------------------ */
    /* Field errors                                                        */
    /* ------------------------------------------------------------------ */

    const errorKey = (field) => field.dataset.errorKey || (field.name || field.id || '').replace(/\[\]$/, '').replace(/_choice$/, '').replace(/_display$/, '');

    /** A schedule warning under a field: shown, but it does not block the step. */
    function setWarning(key, message) {
        let el = byId(`${key}-warning`);
        if (!el && message) {
            el = document.createElement('span');
            el.className = 'ui-warning';
            el.id = `${key}-warning`;
            el.setAttribute('role', 'status');
            (byId(`${key}-error`) || byId(key))?.insertAdjacentElement('afterend', el);
        }
        if (!el) return;
        el.textContent = message ? `Warning: ${message} You can still continue with this date.` : '';
        el.hidden = !message;
    }

    function setError(field, message) {
        const key = typeof field === 'string' ? field : errorKey(field);
        const el = byId(`${key}-error`);
        if (el) {
            el.textContent = message || '';
            el.hidden = !message;
        }
        const control = typeof field === 'string'
            ? byId(`${key}_choice`) || byId(`${key}_display`) || byId(key)
            : field;
        control?.setAttribute('aria-invalid', message ? 'true' : 'false');
        return !message;
    }

    form.addEventListener('input', (event) => {
        const field = event.target;
        if (field.getAttribute('aria-invalid') === 'true' && !field.matches('[data-pw-date]')) {
            setError(field, '');
        }
    });

    /* ------------------------------------------------------------------ */
    /* Money (ABC)                                                         */
    /* ------------------------------------------------------------------ */

    const moneyInput = $('[data-pw-money="budget"]');
    const moneyTarget = moneyInput ? byId(moneyInput.dataset.pwMoney) : null;

    function parseMoney(value) {
        const cleaned = String(value || '').replace(/[^\d.]/g, '');
        const [whole = '', ...rest] = cleaned.split('.');
        const decimals = rest.join('').slice(0, 2);
        if (whole === '' && decimals === '') return '';
        return `${whole.replace(/^0+(?=\d)/, '') || '0'}.${decimals.padEnd(2, '0')}`;
    }

    const formatPeso = (raw, decimals = 2) => (raw === '' || raw === null || Number.isNaN(Number(raw)))
        ? ''
        : Number(raw).toLocaleString('en-PH', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

    // A peso field shown with thousands separators; the plain amount goes to its hidden field.
    function bindMoney(display, onInput) {
        const target = display ? byId(display.dataset.pwMoney) : null;
        if (!display || !target) return () => {};

        const sync = (reformat) => {
            const raw = parseMoney(display.value);
            target.value = raw;
            if (reformat) display.value = formatPeso(raw);
        };
        display.addEventListener('input', () => {
            groupDigits(display);
            sync(false);
            onInput?.();
        });
        display.addEventListener('blur', () => sync(true));

        return sync;
    }

    const syncMoney = bindMoney(moneyInput, () => syncMode());

    const abc = () => Number(moneyTarget?.value || 0);

    /* ------------------------------------------------------------------ */
    /* Select + "Other" fields (source of funds, contract duration)        */
    /* ------------------------------------------------------------------ */

    function syncChoice(group) {
        const select = $('[data-pw-choice-select]', group);
        const extra = $('[data-pw-choice-extra]', group);
        const input = $('[data-pw-choice-input]', group);
        const hidden = $('[data-pw-choice-value]', group);
        const isOther = select.value === group.dataset.pwChoiceOther;
        extra.hidden = !isOther;
        input.required = isOther;
        hidden.value = isOther ? input.value.trim() : select.value;
    }

    $$('[data-pw-choice]').forEach((group) => {
        $('[data-pw-choice-select]', group).addEventListener('change', () => {
            syncChoice(group);
            if (!$('[data-pw-choice-extra]', group).hidden) $('[data-pw-choice-input]', group).focus();
        });
        $('[data-pw-choice-input]', group).addEventListener('input', () => syncChoice(group));
        syncChoice(group);
    });

    /* ------------------------------------------------------------------ */
    /* Mode of procurement                                                 */
    /* ------------------------------------------------------------------ */

    const modeSelect = byId('procurement_mode');
    const basisSelect = byId('legal_basis');
    const groundSelect = byId('negotiation_ground');

    const basis = () => basisSelect?.value || 'ra_9184';
    const family = () => modeSelect?.selectedOptions[0]?.dataset.family || '';
    const isCompetitive = () => family() === 'competitive';
    const noun = () => ({ competitive: 'bids', negotiated: 'offers' }[family()] || 'quotations');
    const peso0 = (value) => `₱${Number(value).toLocaleString('en-PH', { maximumFractionDigits: 0 })}`;

    function prebidRequired() {
        return isCompetitive() && abc() >= (rules.prebidThreshold?.[basis()] ?? Infinity);
    }

    function postingDays() {
        if (isCompetitive()) return rules.postingDays?.competitive ?? 7;
        if (family() === 'rfq') return abc() > (rules.rfqPostingFloor?.[basis()] ?? Infinity) ? (rules.postingDays?.rfq ?? 3) : 0;
        if (family() === 'negotiated') return groundSelect?.value === 'two_failed_biddings' ? (rules.postingDays?.rfq ?? 3) : 0;
        return 0;
    }

    /*
     * Suggested mode for this ABC. Competitive bidding is the general rule; under
     * RA 12009 an ABC within this LGU's Small Value Procurement ceiling can use
     * SVP (IRR Sec. 34.2). Negotiated procurement and direct contracting need
     * their own legal ground, so they are never suggested.
     */
    let modeChosenByUser = Boolean(modeSelect?.value);

    function recommendMode() {
        const amount = abc();
        if (!(amount > 0)) return null;
        const ra12009 = basis() === 'ra_12009';
        if (ra12009 && rules.svpCeiling && amount <= rules.svpCeiling) {
            const floor = rules.rfqPostingFloor?.[basis()];
            return {
                mode: 'small_value_procurement',
                reason: `The ABC of ${peso0(amount)} is within this LGU's ${peso0(rules.svpCeiling)} Small Value Procurement ceiling (IRR Sec. 34.2). `
                    + `Faster: a Request for Quotation to at least three suppliers${amount > floor ? ', posted for 3 calendar days' : ', with no posting period'}. Competitive bidding is still allowed.`,
            };
        }
        return {
            mode: 'public_bidding',
            reason: ra12009 && rules.svpCeiling
                ? `The ABC of ${peso0(amount)} is above the ${peso0(rules.svpCeiling)} Small Value Procurement ceiling, so competitive bidding applies. Negotiated procurement or direct contracting need their own legal ground.`
                : 'Competitive bidding is the general rule. Use an alternative mode only when its legal ground applies.',
        };
    }

    function syncRecommendation() {
        const box = $('[data-pw-recommend]');
        if (!box || !modeSelect) return;
        const suggestion = recommendMode();
        // Fill the mode until the BAC picks one themselves.
        if (suggestion && !modeChosenByUser && modeSelect.value !== suggestion.mode) {
            modeSelect.value = suggestion.mode;
        }
        box.hidden = !suggestion;
        if (!suggestion) return;
        const label = rules.modes?.[suggestion.mode]?.[basis() === 'ra_9184' ? 'labelRa9184' : 'label'] || suggestion.mode;
        const applied = modeSelect.value === suggestion.mode;
        box.classList.toggle('is-applied', applied);
        $('[data-pw-recommend-title]', box).textContent = applied ? `Recommended: ${label} (selected)` : `Recommended: ${label}`;
        $('[data-pw-recommend-reason]', box).textContent = suggestion.reason;
        const apply = $('[data-pw-recommend-apply]', box);
        apply.hidden = applied;
        apply.textContent = `Use ${label}`;
        apply.dataset.mode = suggestion.mode;
    }

    $('[data-pw-recommend-apply]')?.addEventListener('click', (event) => {
        modeSelect.value = event.currentTarget.dataset.mode;
        modeChosenByUser = true;
        syncMode();
    });
    modeSelect?.addEventListener('change', () => { modeChosenByUser = Boolean(modeSelect.value); });

    function syncMode() {
        if (!modeSelect) return;
        syncRecommendation();        Array.from(modeSelect.options).forEach((option) => {
            if (!option.value) return;
            const available = (option.dataset.bases || '').split(' ').includes(basis());
            option.hidden = !available;
            option.disabled = !available;
            const labels = rules.modes?.[option.value];
            if (labels) option.textContent = basis() === 'ra_9184' ? labels.labelRa9184 : labels.label;
        });
        if (modeSelect.selectedOptions[0]?.disabled) modeSelect.value = '';

        const fam = family();
        const negotiation = $('[data-pw-negotiation]');
        negotiation.hidden = fam !== 'negotiated';
        groundSelect.required = fam === 'negotiated';

        const ra12009 = basis() === 'ra_12009';
        let text;
        if (!fam) {
            text = 'Competitive bidding and the alternative modes follow different steps and dates. Choose a mode to see what applies.';
        } else if (fam === 'competitive') {
            const threshold = rules.prebidThreshold?.[basis()];
            text = `Invitation to Bid; ${postingDays()} calendar days between publication and the bid deadline. Pre-bid conference ${prebidRequired() ? 'required' : 'optional'} for this ABC (required from ${peso0(threshold)}). Bids are opened right after the deadline, the same day. Publishing needs an Invitation to Bid document.`;
        } else if (fam === 'rfq') {
            const floor = rules.rfqPostingFloor?.[basis()];
            text = `Request for Quotation to at least three suppliers; one quotation is enough to evaluate. ${abc() > floor ? `Publish at least ${postingDays()} calendar days before the quotation deadline.` : `No minimum posting period at ${peso0(floor)} and below.`}`;
            if (ra12009 && modeSelect.value === 'small_value_procurement' && rules.svpCeiling) {
                text += ` SVP ceiling for this LGU: ${peso0(rules.svpCeiling)}.`;
            }
        } else if (fam === 'negotiated') {
            text = 'Negotiate with capable suppliers on equal terms. A posting period applies only after two failed biddings.';
        } else {
            text = 'RFQ or pro-forma invoice sent to the identified direct supplier; no posting period.';
        }
        $('[data-pw-mode-rule]').textContent = text;

        // Dates step wording.
        $('[data-pw-deadline-label]').textContent = { competitive: 'Deadline for submission of bids', negotiated: 'Deadline for submission of offers' }[fam] || (fam ? 'Deadline for submission of bids' : 'Deadline for submission');
        $('[data-pw-opening-label]').textContent = fam === 'competitive' || !fam ? 'Bid opening' : (fam === 'negotiated' ? 'Opening of offers' : 'Opening of bids');
        $('[data-pw-opening-required]').hidden = false;
        $('[data-pw-opening-rule]').textContent = fam === 'competitive'
            ? 'Same day as the deadline, right after it.'
            : `Required. After the ${$('[data-pw-deadline-label]').textContent.toLowerCase()}.`;
        const days = postingDays();
        $('[data-pw-deadline-rule]').textContent = days
            ? `At least ${days} calendar days after publication in this system.`
            : 'Must be in the future.';

        const required = prebidRequired();
        // The pre-bid conference only appears when the law asks for it for this ABC (or when one is already set).
        const prebidRow = byId('pre_bid_conference_date')?.closest('li');
        if (prebidRow) prebidRow.hidden = !required && !byId('pre_bid_conference_date').value;
        $('[data-pw-prebid-required]').hidden = !required;
        $('[data-pw-prebid-optional]').hidden = required;
        $('[data-pw-prebid-rule]').textContent = fam === 'competitive'
            ? `${required ? 'Required for this ABC:' : `Optional below ${peso0(rules.prebidThreshold?.[basis()])}. If held:`} at least ${rules.prebidDaysBeforeDeadline} calendar days before the deadline${basis() === 'ra_12009' ? ` and at least ${rules.prebidDaysAfterPublication} days after publication` : ''}.`
            : 'Optional, at the BAC\'s discretion. If held, before the deadline.';

        const docRule = $('[data-pw-doc-rule]');
        docRule.textContent = fam === 'competitive'
            ? 'To publish competitive bidding, include the Invitation to Bid. Other documents are optional.'
            : 'Documents are optional for this mode. Attach the RFQ, specifications or terms of reference if you have them.';

        refreshDateHints();
        syncAward();
        if (dateSuggestionsInitialized && !applyingDateSuggestions) suggestProjectDates();
    }

    [modeSelect, basisSelect, groundSelect].forEach((field) => field?.addEventListener('change', syncMode));

    /* ------------------------------------------------------------------ */
    /* Award criterion and weighted criteria (IRR Sec. 50.2(d)-(g))        */
    /* ------------------------------------------------------------------ */

    const criterionSelect = $('[data-pw-criterion]');
    const categorySelect = byId('category');
    const procedureSelect = byId('evaluation_procedure');
    const criteriaList = $('[data-pw-criteria-items]');
    const qprInput = byId('quality_price_ratio');
    const weighted = () => isCompetitive() && ['mearb', 'marb'].includes(criterionSelect?.value);
    const consulting = () => isCompetitive() && categorySelect?.value === 'consultancy';

    function allowedCriteria() {
        if (!isCompetitive()) return {};
        const set = rules.awardCriteria?.[basis()] || {};
        return (consulting() ? set.consultancy : set.default) || {};
    }

    function criteriaRows() {
        return $$('[data-pw-criteria-item]').map((item) => ({
            name: $('input[type="text"]', item).value.trim(),
            weight: Number($('[data-pw-criteria-weight]', item).value || 0),
        })).filter((row) => row.name);
    }

    function updateCriteriaTotal() {
        const total = Math.round(criteriaRows().reduce((sum, row) => sum + row.weight, 0) * 100) / 100;
        const label = $('[data-pw-criteria-total]');
        if (label) label.textContent = `Total: ${total}%`;
        const technical = Number(qprInput?.value);
        const hint = $('[data-pw-qpr-hint]');
        if (hint) hint.textContent = technical >= 1 && technical <= 99 ? `${technical}% technical / ${100 - technical}% price.` : 'MEARB only. The price weight is the rest.';
    }

    function syncAward() {
        if (!criterionSelect) return;
        const allowed = allowedCriteria();
        $('[data-pw-award]').hidden = !isCompetitive();
        Array.from(criterionSelect.options).forEach((option) => {
            if (!option.value) return;
            const available = option.value in allowed;
            option.hidden = !available;
            option.disabled = !available;
            if (available) option.textContent = allowed[option.value];
        });
        if (criterionSelect.selectedOptions[0]?.disabled) criterionSelect.value = '';
        // A single allowed criterion (RA 9184 goods, consulting) is preselected.
        if (isCompetitive() && !criterionSelect.value && Object.keys(allowed).length === 1) criterionSelect.value = Object.keys(allowed)[0];
        // Goods and infrastructure: the lowest calculated responsive bid is the usual criterion.
        if (isCompetitive() && !criterionSelect.value && !consulting() && 'lowest_calculated_bid' in allowed) criterionSelect.value = 'lowest_calculated_bid';

        $('[data-pw-consulting]').hidden = !consulting();
        $('[data-pw-weighted]').hidden = !weighted();
        $('[data-pw-weighted-label]').textContent = weighted() ? `(${criterionSelect.value.toUpperCase()})` : '(MEARB / MARB)';
        $('[data-pw-qpr]').hidden = !(weighted() && criterionSelect.value === 'mearb');
        $('[data-pw-opening-venue]').hidden = !isCompetitive();

        // Pre-procurement conference: mandatory above the ABC threshold (IRR Sec. 49.1).
        const preproc = $('[data-pw-preproc]');
        preproc.hidden = !isCompetitive();
        $('[data-pw-preproc-required]').hidden = !preProcRequired();
        $('[data-pw-preproc-optional]').hidden = preProcRequired();
        const threshold = preProcThreshold();
        $('[data-pw-preproc-rule]').textContent = threshold
            ? `${preProcRequired() ? 'Required for this ABC' : `Optional at ${peso0(threshold)} and below`}; held before the Invitation to Bid is published (IRR Sec. ${basis() === 'ra_12009' ? '49.1' : '20.1'}). Recorded with the project.`
            : 'Held before the Invitation to Bid is published. Recorded with the project.';
        updateCriteriaTotal();
    }

    function preProcThreshold() {
        return rules.preProcurementThresholds?.[basis()]?.[categorySelect?.value || 'goods'] ?? null;
    }

    function preProcRequired() {
        const threshold = preProcThreshold();
        return isCompetitive() && threshold !== null && abc() > threshold;
    }

    [criterionSelect, categorySelect].forEach((field) => field?.addEventListener('change', syncAward));
    qprInput?.addEventListener('input', updateCriteriaTotal);

    let criteriaCounter = $$('[data-pw-criteria-item]').length;
    $('[data-pw-criteria-add]')?.addEventListener('click', () => {
        const item = $('[data-pw-criteria-item]').cloneNode(true);
        $$('input', item).forEach((input) => {
            input.value = '';
            input.name = input.name.replace(/\[\d+\]/, `[${criteriaCounter}]`);
        });
        criteriaCounter += 1;
        criteriaList.appendChild(item);
        $('input', item).focus();
        dirty = true;
        updateCriteriaTotal();
    });

    criteriaList?.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-pw-criteria-remove]');
        if (!remove) return;
        const item = remove.closest('[data-pw-criteria-item]');
        if ($$('[data-pw-criteria-item]').length === 1) {
            $$('input', item).forEach((input) => { input.value = ''; });
        } else {
            item.remove();
        }
        dirty = true;
        updateCriteriaTotal();
    });
    criteriaList?.addEventListener('input', updateCriteriaTotal);

    /* ------------------------------------------------------------------ */
    /* Requirements: extra rows, submission options                        */
    /* ------------------------------------------------------------------ */

    const extraList = $('[data-pw-extra-items]');
    const extraTemplate = $('[data-pw-extra-template]');
    let extraCounter = $$('[data-pw-extra-item]').length;

    $('[data-pw-extra-add]')?.addEventListener('click', () => {
        const item = extraTemplate.content.firstElementChild.cloneNode(true);
        const input = $('input', item);
        const label = $('label', item);
        input.id = `extra-requirement-${extraCounter++}`;
        label.htmlFor = input.id;
        extraList.appendChild(item);
        input.focus();
        dirty = true;
    });

    extraList?.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-pw-extra-remove]');
        if (!remove) return;
        const item = remove.closest('[data-pw-extra-item]');
        const next = item.nextElementSibling || item.previousElementSibling;
        item.remove();
        ($('input', next || document.createElement('div')) || $('[data-pw-extra-add]')).focus();
        dirty = true;
    });

    /* ------------------------------------------------------------------ */
    /* Requirement notes: only the ones the BAC adds take up space         */
    /* ------------------------------------------------------------------ */

    const notesAdd = $('[data-pw-notes-add]');

    function autogrow(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = `${textarea.scrollHeight + 2}px`;
    }

    function syncNotesAdd() {
        if (notesAdd) notesAdd.hidden = $$('[data-pw-note-add]').every((button) => button.hidden);
    }

    $$('[data-pw-note-add]').forEach((button) => {
        button.addEventListener('click', () => {
            const note = $(`[data-pw-note="${button.dataset.pwNoteAdd}"]`);
            note.hidden = false;
            button.hidden = true;
            syncNotesAdd();
            const input = $('textarea', note);
            autogrow(input);
            input.focus();
        });
    });

    $$('[data-pw-note-remove]').forEach((button) => {
        button.addEventListener('click', async () => {
            const note = button.closest('[data-pw-note]');
            const input = $('textarea', note);
            // A hidden note is still submitted, so removing it clears its text.
            if (input.value.trim() && !(await (window.bacConfirm ? window.bacConfirm({ title: 'Remove this note?', message: 'The note and its text are removed.', confirmLabel: 'Remove', tone: 'danger' }) : Promise.resolve(window.confirm('Remove this note and its text?'))))) return;
            input.value = '';
            note.hidden = true;
            const add = $(`[data-pw-note-add="${note.dataset.pwNote}"]`);
            add.hidden = false;
            syncNotesAdd();
            add.focus();
            dirty = true;
        });
    });

    $$('[data-pw-autogrow]').forEach((textarea) => {
        textarea.addEventListener('input', () => autogrow(textarea));
        if (!textarea.closest('[hidden]')) autogrow(textarea);
    });

    const submissionMode = $('[data-pw-submission-mode]');
    const fee = $('[data-pw-fee]');
    const security = $('[data-pw-security]');

    /* ------------------------------------------------------------------ */
    /* Requirement suggestions: the standard documents for the mode,       */
    /* extras often asked for the category, and suggested note text.       */
    /* The BAC can change or remove anything suggested.                    */
    /* ------------------------------------------------------------------ */

    const standardSets = JSON.parse(byId('pwStandardRequirements')?.textContent || '{}');
    const durationValue = () => (byId('contract_duration')?.value || '').trim();
    const peso2 = (value) => `₱${Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    function syncAutoDocs() {
        const list = $('[data-pw-auto-docs-list]');
        if (!list) return;
        const mode = modeSelect?.value;
        const category = categorySelect?.value || 'goods';
        const band = abc() > 500000 ? 'high' : (abc() > 50000 ? 'mid' : 'low');
        const items = mode ? (standardSets[basis()]?.[category]?.[mode]?.[band] || []) : [];
        $('[data-pw-auto-docs-title]').textContent = items.length
            ? `Included automatically for ${selectedText('procurement_mode')} · ${selectedText('category') || 'Goods'} (${items.length})`
            : 'Choose the mode and category in step 1 to see the standard documents.';
        list.innerHTML = '';
        ['technical', 'financial'].forEach((component) => {
            const group = items.filter((item) => item.component === component);
            if (!group.length) return;
            const block = document.createElement('div');
            block.className = 'pw-auto-docs__group';
            const heading = document.createElement('h4');
            heading.textContent = component === 'technical' ? 'Technical / eligibility' : 'Financial';
            const ul = document.createElement('ul');
            group.forEach((item) => {
                const li = document.createElement('li');
                li.textContent = item.label + (item.required ? '' : ' (if applicable)');
                li.classList.toggle('is-optional', !item.required);
                if (item.condition) {
                    const small = document.createElement('small');
                    small.textContent = item.condition;
                    li.appendChild(small);
                }
                ul.appendChild(li);
            });
            block.append(heading, ul);
            list.appendChild(block);
        });
    }

    const extraSuggestions = {
        goods: ['Product brochure or technical data sheet', 'Certificate of warranty', 'List of after-sales service centers'],
        services: ['Manpower deployment plan', 'List of equipment and tools to be used'],
        infrastructure: ['Construction schedule and S-curve', 'Manpower schedule', 'Equipment utilization schedule', 'Construction methods'],
        consultancy: ['Sample outputs of similar engagements'],
    };

    function extraValues() {
        return $$('[data-pw-extra-item] input').map((input) => input.value.trim().toLowerCase());
    }

    function syncExtraSuggestions() {
        const box = $('[data-pw-extra-suggest]');
        const chips = $('[data-pw-extra-suggest-chips]');
        if (!box || !chips) return;
        const taken = extraValues();
        const options = (extraSuggestions[categorySelect?.value || 'goods'] || []).filter((label) => !taken.includes(label.toLowerCase()));
        chips.innerHTML = '';
        options.forEach((label) => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'pw-suggest__chip';
            chip.dataset.pwExtraSuggestion = label;
            chip.innerHTML = '<i class="fas fa-plus" aria-hidden="true"></i>';
            chip.append(document.createTextNode(label));
            chips.appendChild(chip);
        });
        box.hidden = options.length === 0;
    }

    $('[data-pw-extra-suggest]')?.addEventListener('click', (event) => {
        const chip = event.target.closest('[data-pw-extra-suggestion]');
        if (!chip) return;
        $('[data-pw-extra-add]').click();
        const inputs = $$('[data-pw-extra-item] input');
        inputs[inputs.length - 1].value = chip.dataset.pwExtraSuggestion;
        syncExtraSuggestions();
    });
    extraList?.addEventListener('input', syncExtraSuggestions);
    extraList?.addEventListener('click', () => window.setTimeout(syncExtraSuggestions));

    // Suggested note text; null when the note does not apply.
    function noteSuggestion(field) {
        const category = categorySelect?.value || 'goods';
        const duration = durationValue();
        if (field === 'technical_requirements' && duration) {
            return {
                goods: `Deliver within ${duration}. Items must conform to the technical specifications.`,
                services: `Render the services within ${duration}, as stated in the technical specifications.`,
                infrastructure: `Complete the works within ${duration}.`,
                consultancy: `Complete the services within ${duration}, as stated in the terms of reference.`,
            }[category];
        }
        if (field === 'financial_requirements' && isCompetitive() && category !== 'consultancy' && abc() > 0) {
            return `Net Financial Contracting Capacity (NFCC) at least equal to the ABC of ${peso2(abc())}, or a committed line of credit from a Universal or Commercial Bank, as stated in the bidding documents.`;
        }
        if (field === 'eligibility_requirements' && isCompetitive() && category === 'infrastructure') {
            return 'Valid PCAB license for the type and cost of the works; each joint venture partner must hold one.';
        }
        return null;
    }

    function syncNoteSuggestions() {
        ['technical_requirements', 'financial_requirements', 'eligibility_requirements'].forEach((field) => {
            const note = $(`[data-pw-note="${field}"]`);
            const input = byId(field);
            if (!note || !input || input.dataset.pwDismissed === '1') return;
            const suggestion = noteSuggestion(field);
            const auto = input.dataset.pwAuto === '1';
            // Only fill an empty note, or keep updating text the script filled and nobody edited.
            if (suggestion && (input.value.trim() === '' || auto)) {
                input.value = suggestion;
                input.dataset.pwAuto = '1';
                note.hidden = false;
                $(`[data-pw-note-add="${field}"]`).hidden = true;
            } else if (!suggestion && auto) {
                input.value = '';
                delete input.dataset.pwAuto;
                note.hidden = true;
                $(`[data-pw-note-add="${field}"]`).hidden = false;
            }
            $('[data-pw-note-suggested]', note).hidden = input.dataset.pwAuto !== '1';
            if (!note.hidden) autogrow(input);
        });
        syncNotesAdd();
    }

    // A note the BAC removed stays removed.
    $$('[data-pw-note-remove]').forEach((button) => button.addEventListener('click', () => {
        const note = button.closest('[data-pw-note]');
        if (note.hidden) $('textarea', note).dataset.pwDismissed = '1';
    }));

    $$('[data-pw-note] textarea').forEach((input) => input.addEventListener('input', () => {
        delete input.dataset.pwAuto;
        const tag = $('[data-pw-note-suggested]', input.closest('[data-pw-note]'));
        if (tag) tag.hidden = true;
    }));

    // Bid security: required in competitive bidding; for the other modes the BAC
    // turns it on when the RFQ asks for one. The amounts follow the ABC.
    let securityTouched = false;
    security?.addEventListener('change', () => { securityTouched = true; });
    const securityNotes = byId('bid_security_notes');
    securityNotes?.addEventListener('input', () => { delete securityNotes.dataset.pwAuto; });

    function syncSecuritySuggestion() {
        if (!security) return;
        if (!securityTouched && modeSelect?.value) security.checked = isCompetitive();
        if (!securityNotes) return;
        const amount = abc();
        const suggestion = isCompetitive() && amount > 0
            ? `Bid Securing Declaration; or cash, cashier's or manager's check, bank draft or guarantee of at least ${peso2(amount * 0.02)} (2% of the ABC); or a surety bond of at least ${peso2(amount * 0.05)} (5% of the ABC).`
            : '';
        if (securityNotes.value.trim() === '' || securityNotes.dataset.pwAuto === '1') {
            securityNotes.value = suggestion;
            if (suggestion) securityNotes.dataset.pwAuto = '1'; else delete securityNotes.dataset.pwAuto;
        }
    }

    function syncRequirementSuggestions() {
        syncAutoDocs();
        syncExtraSuggestions();
        syncNoteSuggestions();
        syncSecuritySuggestion();
    }

    [modeSelect, basisSelect, categorySelect].forEach((field) => field?.addEventListener('change', () => { syncRequirementSuggestions(); syncSubmission(); }));
    moneyInput?.addEventListener('input', syncRequirementSuggestions);
    byId('contract_duration')?.addEventListener('change', syncRequirementSuggestions);
    $$('[data-pw-choice-select], [data-pw-choice-input]').forEach((field) => ['change', 'input'].forEach((type) => field.addEventListener(type, () => window.setTimeout(syncRequirementSuggestions))));

    // Bidding documents fee: competitive bidding uses the ABC schedule's maximum
    // (GPPB Circular No. 02-2026, Sec. 5.2) unless the BAC lowers or waives it with
    // a reason. The server applies the same rules; this only shows them.
    const feeBlock = $('[data-pw-fee-block]');
    const feeSchedule = JSON.parse(feeBlock?.dataset.pwFeeSchedule || '[]');
    const feeModes = $$('[data-pw-fee-mode]');
    const feeMaximum = (amount) => {
        if (!(amount > 0)) return null;
        return feeSchedule.find((row) => row.limit === null || Math.round(amount * 100) <= Math.round(row.limit * 100)) || null;
    };

    let syncFeeMoney = null;

    function syncFee() {
        if (!feeBlock) return 0;
        const competitive = isCompetitive();
        const mode = feeModes.find((radio) => radio.checked)?.value || 'schedule';
        const bracket = feeMaximum(abc());
        const calc = $('[data-pw-fee-calc]');
        const max = $('[data-pw-fee-max]');

        $('[data-pw-fee-competitive]').hidden = !competitive;
        feeModes.forEach((radio) => { radio.disabled = !competitive; });
        if (calc) {
            calc.textContent = bracket
                ? `ABC ₱${formatPeso(abc())}: ${bracket.bracket.replace(/^ABC /, '')}, so the maximum fee is ₱${formatPeso(bracket.maximum)}.`
                : 'Enter the ABC in step 1 to compute the maximum fee.';
        }
        if (max) max.textContent = bracket ? `(₱${formatPeso(bracket.maximum)})` : '';

        // Alternative modes: no fee unless the BAC chooses to charge one.
        const altCharge = $('[data-pw-fee-alt-charge]');
        $('[data-pw-fee-alt]').hidden = competitive;
        const charging = !competitive && Boolean(altCharge?.checked);
        if (!competitive && !charging && fee.value) {
            fee.value = '';
            const display = $('[data-pw-money="bidding_documents_fee"]');
            if (display) display.value = '';
        }
        const showAmount = charging || (competitive && mode === 'reduced');
        const showReason = competitive && mode !== 'schedule';
        $('[data-pw-fee-amount]').hidden = !showAmount;
        $('[data-pw-fee-reason]').hidden = !showReason;
        $('#bidding_fee_reason').disabled = !showReason;
        $('[data-pw-fee-amount-label]').textContent = competitive ? 'Lower fee' : 'Fee amount';
        $('[data-pw-fee-amount-hint]').textContent = competitive
            ? `Must be less than the ₱${bracket ? formatPeso(bracket.maximum) : '—'} maximum.`
            : 'Leave blank when the notice charges no fee. Bidders pay at the BAC office before submitting.';
        // The schedule's maximum is computed by the server; do not send a stale amount.
        if (competitive && mode !== 'reduced') fee.value = '';
        else if (!fee.value) syncFeeMoney?.(false);

        if (!competitive) return Number(fee.value) || 0;
        if (mode === 'waived') return 0;
        return mode === 'reduced' ? Number(fee.value) || 0 : (bracket?.maximum || 0);
    }

    function syncSubmission() {
        $$('[data-pw-show-when]').forEach((el) => { el.hidden = el.dataset.pwShowWhen !== submissionMode.value; });
        $('[data-pw-fee-venue]').hidden = !(syncFee() > 0);
        $('[data-pw-security-notes]').hidden = !security.checked;
    }

    syncFeeMoney = bindMoney($('[data-pw-money="bidding_documents_fee"]'), () => syncSubmission());

    [submissionMode, security, ...feeModes, $('[data-pw-fee-alt-charge]')].forEach((field) => {
        field?.addEventListener('input', syncSubmission);
        field?.addEventListener('change', syncSubmission);
    });
    // The fee follows the ABC and the mode of procurement.
    moneyInput?.addEventListener('input', syncSubmission);
    modeSelect?.addEventListener('change', syncSubmission);

    /* ------------------------------------------------------------------ */
    /* Documents                                                           */
    /* ------------------------------------------------------------------ */

    const docList = $('[data-pw-docs]');
    const docTemplate = $('[data-pw-doc]').cloneNode(true);
    let docCounter = 1;

    const sizeLabel = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);

    function renderDoc(row) {
        const input = $('[data-pw-doc-file]', row);
        const file = input.files?.[0];
        const name = $('[data-pw-doc-name]', row);
        const state = $('[data-pw-doc-state]', row);
        $('[data-pw-doc-choose-label]', row).textContent = file ? 'Replace file' : 'Choose file';
        name.textContent = file ? `${file.name} · ${sizeLabel(file.size)}` : 'No file selected';
        state.hidden = !file;
        state.textContent = 'Selected — uploads when you save';
        state.className = 'ui-pill ui-pill--warning';
    }

    function docError(row, message) {
        const el = $('[data-pw-doc-error]', row);
        el.textContent = message || '';
        el.hidden = !message;
        row.classList.toggle('is-invalid', Boolean(message));
        return !message;
    }

    function checkDocFile(row) {
        const input = $('[data-pw-doc-file]', row);
        const file = input.files?.[0];
        if (!file) return docError(row, '');
        const extension = file.name.split('.').pop().toLowerCase();
        if (!ALLOWED_EXTENSIONS.includes(extension)) {
            input.value = '';
            renderDoc(row);
            return docError(row, `“${file.name}” is not an allowed type. Use PDF, Word, Excel, JPG or PNG.`);
        }
        if (file.size > MAX_BYTES) {
            input.value = '';
            renderDoc(row);
            return docError(row, `“${file.name}” is ${sizeLabel(file.size)}; the limit is ${sizeLabel(MAX_BYTES)}.`);
        }
        return docError(row, '');
    }

    function addDoc() {
        const row = docTemplate.cloneNode(true);
        const index = docCounter++;
        const type = $('[data-pw-doc-type]', row);
        const input = $('[data-pw-doc-file]', row);
        type.id = `document-type-${index}`;
        type.value = '';
        $('label.ui-label', row).htmlFor = type.id;
        input.id = `document-file-${index}`;
        input.value = '';
        $('[data-pw-doc-choose]', row).htmlFor = input.id;
        docError(row, '');
        renderDoc(row);
        docList.appendChild(row);
        type.focus();
        dirty = true;
    }

    $('[data-pw-doc-add]')?.addEventListener('click', addDoc);

    docList?.addEventListener('change', (event) => {
        const row = event.target.closest('[data-pw-doc]');
        if (!row) return;
        if (event.target.matches('[data-pw-doc-file]')) {
            checkDocFile(row);
            renderDoc(row);
        }
        if (event.target.matches('[data-pw-doc-type]') && event.target.value) docError(row, '');
    });

    docList?.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-pw-doc-remove]');
        if (!remove) return;
        const row = remove.closest('[data-pw-doc]');
        if ($$('[data-pw-doc]').length === 1) {
            $('[data-pw-doc-type]', row).value = '';
            $('[data-pw-doc-file]', row).value = '';
            docError(row, '');
            renderDoc(row);
            $('[data-pw-doc-type]', row).focus();
        } else {
            const next = row.nextElementSibling || row.previousElementSibling;
            row.remove();
            $('[data-pw-doc-type]', next)?.focus();
        }
        dirty = true;
    });

    const docRows = () => $$('[data-pw-doc]').map((row) => ({
        row,
        type: $('[data-pw-doc-type]', row),
        file: $('[data-pw-doc-file]', row).files?.[0] || null,
    }));

    function validateDocs(forPublish) {
        let firstInvalid = null;
        docRows().forEach(({ row, type, file }) => {
            if (file && !type.value) {
                docError(row, 'Choose the document type for this file.');
                firstInvalid ??= type;
            } else if (!file && type.value && forPublish) {
                docError(row, 'Choose a file, or remove this row.');
                firstInvalid ??= $('[data-pw-doc-choose]', row);
            } else {
                checkDocFile(row);
            }
        });

        const needsInvitation = forPublish && isCompetitive();
        const hasInvitation = docRows().some(({ type, file }) => file && type.value === 'invitation_to_bid');
        const docMessage = needsInvitation && !hasInvitation ? 'Publishing competitive bidding needs an Invitation to Bid file.' : '';
        setError('project_documents', docMessage);
        if (docMessage && !firstInvalid) firstInvalid = $('[data-pw-doc-add]');

        return firstInvalid;
    }

    // Rows without a file are not sent, so file and type arrays stay aligned.
    function prepareDocsForSubmit() {
        docRows().forEach(({ row, file }) => {
            $$('select, input', row).forEach((field) => { field.disabled = !file; });
        });
    }

    function restoreDocsAfterSubmit() {
        $$('[data-pw-doc] select, [data-pw-doc] input').forEach((field) => { field.disabled = false; });
    }

    /* ------------------------------------------------------------------ */
    /* Dates (Philippine Standard Time)                                    */
    /* ------------------------------------------------------------------ */

    const pad = (n) => String(n).padStart(2, '0');
    let dateSuggestionsInitialized = false;
    let applyingDateSuggestions = false;
    const suggestedDateValues = Object.create(null);

    function phNow() {
        const d = new Date(Date.now() + 8 * 3600 * 1000);
        return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}T${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}`;
    }

    const datePart = (value) => (value || '').slice(0, 10);

    function addDays(date, days) {
        const [y, m, d] = date.split('-').map(Number);
        const t = new Date(Date.UTC(y, m - 1, d + days));
        return `${t.getUTCFullYear()}-${pad(t.getUTCMonth() + 1)}-${pad(t.getUTCDate())}`;
    }

    function addDaysDateTime(value, days) {
        return `${addDays(datePart(value), days)}${value.slice(10)}`;
    }

    function weekday(date) {
        const [y, m, d] = date.split('-').map(Number);
        return new Date(Date.UTC(y, m - 1, d)).getUTCDay();
    }

    function phLabel(value) {
        if (!value) return '';
        const [date, time] = value.split('T');
        const [y, m, d] = date.split('-').map(Number);
        if (!y || !m || !d) return '';
        let text = `${DAYS[weekday(date)]}, ${MONTHS[m - 1]} ${d}, ${y}`;
        if (time) {
            const [h, min] = time.split(':').map(Number);
            text += ` · ${((h + 11) % 12) + 1}:${pad(min)} ${h >= 12 ? 'PM' : 'AM'}`;
        }
        return text;
    }

    const val = (id) => byId(id)?.value || '';

    function refreshDateHints() {
        $$('[data-pw-when]').forEach((el) => {
            const text = phLabel(val(el.dataset.pwWhen));
            el.textContent = text ? `${text}${text.includes('·') ? ' PST' : ''}` : '';
        });
    }

    $$('[data-pw-date]').forEach((input) => input.addEventListener('change', () => {
        refreshDateHints();
        if (input.id === 'date_posted' && dateSuggestionsInitialized && !applyingDateSuggestions) suggestProjectDates();
        if (current === 4) scheduleErrors(true);
    }));

    /**
     * The schedule checks the server applies when publishing; returns field => message.
     * Impossible schedules are errors and block; the legal periods between the dates
     * are warnings: shown, recorded on the server, but the BAC's dates are kept.
     */
    function scheduleErrors(show) {
        const errors = {};
        const warnings = {};
        const now = phNow();
        const today = datePart(now);
        const deadline = val('bid_submission_deadline');
        const opening = val('bid_opening_date');
        const prebid = val('pre_bid_conference_date');
        const clarification = val('clarification_deadline');
        const evaluation = val('evaluation_start_date');
        const award = val('expected_award_date');
        const deadlineName = $('[data-pw-deadline-label]').textContent.toLowerCase();
        const openingName = $('[data-pw-opening-label]').textContent.toLowerCase();

        // Any day and time the BAC chooses; only the periods between the dates are checked.
        if (!deadline) {
            errors.bid_submission_deadline = `Set the ${deadlineName}.`;
        } else if (deadline <= now) {
            errors.bid_submission_deadline = `The ${deadlineName} must be in the future.`;
        } else if (postingDays() && datePart(deadline) < addDays(today, postingDays())) {
            warnings.bid_submission_deadline = `Publishing today, the ${deadlineName} should be on or after ${phLabel(addDays(today, postingDays()))} (${postingDays()} calendar days).`;
        }

        if (!opening) {
            errors.bid_opening_date = `Set the ${openingName}.`;
        } else if (opening && deadline && opening <= deadline) {
            errors.bid_opening_date = `The ${openingName} must be after the ${deadlineName}.`;
        } else if (opening && deadline && isCompetitive() && datePart(opening) !== datePart(deadline)) {
            warnings.bid_opening_date = 'The bid opening should be on the same day as the deadline, right after it.';
        }

        if (prebidRequired() && !prebid) {
            errors.pre_bid_conference_date = `A pre-bid conference is required for an ABC of ${peso0(rules.prebidThreshold?.[basis()])} or more.`;
        } else if (prebid && deadline && prebid >= deadline) {
            errors.pre_bid_conference_date = `The pre-bid conference must be before the ${deadlineName}.`;
        } else if (prebid && deadline && isCompetitive() && addDaysDateTime(prebid, rules.prebidDaysBeforeDeadline) > deadline) {
            warnings.pre_bid_conference_date = `It should be at least ${rules.prebidDaysBeforeDeadline} calendar days before the ${deadlineName}.`;
        } else if (prebid && isCompetitive() && basis() === 'ra_12009' && datePart(prebid) < addDays(today, rules.prebidDaysAfterPublication)) {
            warnings.pre_bid_conference_date = `Publishing today, it should be on or after ${phLabel(addDays(today, rules.prebidDaysAfterPublication))} (${rules.prebidDaysAfterPublication} days after publication).`;
        }

        if (clarification && deadline && clarification >= deadline) {
            errors.clarification_deadline = `Clarifications must close before the ${deadlineName}.`;
        }

        const openingOrDeadline = opening || deadline;
        if (evaluation && openingOrDeadline && `${evaluation}T00:00` < openingOrDeadline) {
            errors.evaluation_start_date = `Evaluation starts after the ${opening ? openingName : deadlineName} (${phLabel(datePart(openingOrDeadline))}); choose a later day.`;
        }
        if (award) {
            const minimum = evaluation ? `${evaluation}T00:00` : openingOrDeadline;
            if (minimum && `${award}T00:00` < minimum) {
                errors.expected_award_date = evaluation ? 'The expected award is on or after the evaluation start.' : `The expected award is after the ${openingName}.`;
            }
        }

        if (show) {
            ['bid_submission_deadline', 'bid_opening_date', 'pre_bid_conference_date', 'clarification_deadline', 'evaluation_start_date', 'expected_award_date']
                .forEach((key) => {
                    setError(key, errors[key] || '');
                    setWarning(key, errors[key] ? '' : (warnings[key] || ''));
                });
        }
        return errors;
    }

    /* ------------------------------------------------------------------ */
function nextWorkingDay(date, inclusive = true) {
        let candidate = inclusive ? date : addDays(date, 1);
        while ([0, 6].includes(weekday(candidate))) candidate = addDays(candidate, 1);
        return candidate;
    }

    function setSuggestedDate(id, value, changed) {
        const input = byId(id);
        if (!input || !value) return;
        if (input.value && input.value !== suggestedDateValues[id]) return;
        if (input.value === value) {
            suggestedDateValues[id] = value;
            return;
        }
        input.value = value;
        suggestedDateValues[id] = value;
        changed.add(input);
    }

    function clearSuggestedDate(id, changed) {
        const input = byId(id);
        if (!input || !suggestedDateValues[id] || input.value !== suggestedDateValues[id]) return;
        input.value = '';
        delete suggestedDateValues[id];
        changed.add(input);
    }

    function suggestProjectDates() {
        if (applyingDateSuggestions) return;
        applyingDateSuggestions = true;
        dateSuggestionsInitialized = true;
        const changed = new Set();
        const today = datePart(phNow());
        setSuggestedDate('date_posted', today, changed);
        const publication = val('date_posted') || today;
        const baseDate = publication > today ? publication : today;
        let earliestDeadline = addDays(baseDate, postingDays());

        if (prebidRequired()) {
            const publicationGap = basis() === 'ra_12009' ? (rules.prebidDaysAfterPublication || 0) : 0;
            let prebidDate = addDays(baseDate, publicationGap);
            if (prebidDate <= today) prebidDate = addDays(today, 1);
            prebidDate = nextWorkingDay(prebidDate);
            const deadlineAfterPrebid = addDays(prebidDate, rules.prebidDaysBeforeDeadline || 12);
            if (deadlineAfterPrebid > earliestDeadline) earliestDeadline = deadlineAfterPrebid;
            setSuggestedDate('pre_bid_conference_date', `${prebidDate}T10:00`, changed);
        } else {
            clearSuggestedDate('pre_bid_conference_date', changed);
        }

        let deadlineDate = nextWorkingDay(earliestDeadline);
        let deadlineTime = '10:00';
        if (deadlineDate === today) {
            const [, hourText, minuteText] = phNow().match(/T(\d{2}):(\d{2})/);
            const nextHour = Math.max(8, Math.ceil((Number(hourText) * 60 + Number(minuteText) + 1) / 60));
            if (nextHour >= 17) deadlineDate = nextWorkingDay(addDays(deadlineDate, 1));
            else deadlineTime = `${String(nextHour).padStart(2, '0')}:00`;
        }
        setSuggestedDate('bid_submission_deadline', `${deadlineDate}T${deadlineTime}`, changed);

        const deadline = val('bid_submission_deadline');
        const [hour, minute] = (deadline.slice(11, 16) || '10:00').split(':').map(Number);
        const openingMinute = Math.min(hour * 60 + minute + 30, 17 * 60);
        const openingTime = `${String(Math.floor(openingMinute / 60)).padStart(2, '0')}:${String(openingMinute % 60).padStart(2, '0')}`;
        setSuggestedDate('bid_opening_date', `${datePart(deadline)}T${openingTime}`, changed);

        changed.forEach((input) => {
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
        applyingDateSuggestions = false;
        refreshDateHints();
        scheduleErrors(true);
        const message = $('[data-pw-schedule-suggestion]');
        if (message) {
            message.hidden = false;
            message.textContent = 'Suggested using this project\'s procurement mode, legal basis, ABC and current BAC rules. Check the dates and meeting details before publishing. Expected award remains a planning decision.';
        }
    }

    $('[data-pw-suggest-dates]')?.addEventListener('click', suggestProjectDates);
    /* Step validation                                                     */
    /* ------------------------------------------------------------------ */

    const isEmpty = (field) => (field.type === 'checkbox' ? !field.checked : !String(field.value || '').trim());

    // show=false is the silent pre-publish check used by the review readiness list.
    function validateStep(step, show = true, forPublish = false) {
        const invalid = [];
        const fail = (field, message, key) => {
            if (show) setError(key || field, message);
            invalid.push(field);
        };

        if (step === 1) {
            $$('[data-pw-choice]').forEach(syncChoice);
            syncMoney(true);
            [['title', 'Enter the project title.'], ['description', 'Describe the project.'], ['category', 'Choose the category.'], ['location', 'Enter the location.'], ['procurement_mode', 'Choose the mode of procurement.']]
                .forEach(([id, message]) => {
                    const field = byId(id);
                    if (isEmpty(field)) fail(field, message); else if (show) setError(field, '');
                });
            if (family() === 'negotiated' && !groundSelect.value) fail(groundSelect, 'Choose the ground for Negotiated Procurement.');
            else if (show) setError(groundSelect, '');

            if (!moneyTarget.value || abc() <= 0) {
                fail(moneyInput, 'Enter the ABC.', 'budget');
            } else if (abc() > 9999999999999.99) {
                fail(moneyInput, 'The ABC is too large.', 'budget');
            } else if (basis() === 'ra_12009' && modeSelect.value === 'small_value_procurement' && rules.svpCeiling && abc() > rules.svpCeiling) {
                fail(moneyInput, `The ABC exceeds the Small Value Procurement ceiling of ${peso0(rules.svpCeiling)} for this LGU. Use competitive bidding.`, 'budget');
            } else if (show) {
                setError('budget', '');
            }

            [['source_of_fund', 'Choose the source of funds.'], ['contract_duration', 'Choose the contract duration.']].forEach(([key, message]) => {
                const group = $(`[data-pw-choice="${key}"]`);
                const select = $('[data-pw-choice-select]', group);
                const input = $('[data-pw-choice-input]', group);
                if (!select.value) fail(select, message, key);
                else if (input.required && !input.value.trim()) fail(input, 'Specify it.', key);
                else if (show) setError(key, '');
            });

            const url = byId('philgeps_url');
            if (url.value && !url.checkValidity()) fail(url, 'Enter a full link starting with https://');
            else if (show) setError(url, '');

            if (isCompetitive() && !(criterionSelect.value in allowedCriteria())) fail(criterionSelect, 'Choose the award criterion stated in the Invitation to Bid.');
            else if (show) setError(criterionSelect, '');
            if (consulting() && !procedureSelect.value) fail(procedureSelect, 'Choose QBE or QCBE.');
            else if (show) setError(procedureSelect, '');
        }

        if (step === 2) {
            // The fee field accepts only digits, so any amount it holds is zero or more.
            if (show) { setError('bidding_documents_fee', ''); setError('bidding_fee_reason', ''); }
            const feeMode = feeModes.find((radio) => radio.checked)?.value || 'schedule';
            if (isCompetitive() && feeMode !== 'schedule') {
                const bracket = feeMaximum(abc());
                const feeDisplay = byId('bidding_documents_fee_display');
                if (feeMode === 'reduced' && !(Number(fee.value) > 0)) {
                    fail(feeDisplay, 'Enter the lower fee, or choose to waive it.', 'bidding_documents_fee');
                } else if (feeMode === 'reduced' && bracket && Number(fee.value) >= bracket.maximum) {
                    fail(feeDisplay, `The lower fee must be less than the ₱${formatPeso(bracket.maximum)} maximum. To charge the maximum, choose that option.`, 'bidding_documents_fee');
                }
                if (byId('bidding_fee_reason').value.trim().length < 10) {
                    fail(byId('bidding_fee_reason'), feeMode === 'waived' ? 'Record why the fee is waived (at least 10 characters).' : 'Record why a lower fee is charged (at least 10 characters).');
                }
            }

            if (weighted()) {
                const rows = criteriaRows();
                const total = rows.reduce((sum, row) => sum + row.weight, 0);
                if (!rows.length || Math.abs(total - 100) > 0.01) {
                    fail($('input', criteriaList), `List the ${criterionSelect.value.toUpperCase()} criteria with weights totalling 100% (now ${Math.round(total * 100) / 100}%).`, 'evaluation_criteria');
                } else if (show) setError('evaluation_criteria', '');
                const technical = Number(qprInput.value);
                if (criterionSelect.value === 'mearb' && !(technical >= 1 && technical <= 99)) fail(qprInput, 'Enter the technical weight, 1 to 99%.');
                else if (show) setError(qprInput, '');
            } else if (show) {
                setError('evaluation_criteria', '');
                setError(qprInput, '');
            }

            const authority = byId('electronic_submission_authority');
            if (isCompetitive() && submissionMode.value === 'electronic' && !authority.value.trim()) {
                fail(authority, 'Record the IT certification for electronic bids, or choose manual sealed bids.');
            } else if (show) setError(authority, '');
        }

        if (step === 3) {
            // Moving on only checks the rows; the Invitation to Bid is needed to publish,
            // since it usually quotes the dates set in the next step.
            const first = show ? validateDocs(forPublish) : null;
            if (!show) {
                const pending = docRows().some(({ type, file }) => (file && !type.value) || (!file && type.value));
                const needsInvitation = isCompetitive() && !docRows().some(({ type, file }) => file && type.value === 'invitation_to_bid');
                if (pending || needsInvitation) invalid.push($('[data-pw-doc-add]'));
            } else if (first) {
                invalid.push(first);
            }
        }

        if (step === 4) {
            const errors = scheduleErrors(show);
            Object.keys(errors).forEach((key) => invalid.push(byId(key)));
            const venue = byId('bid_opening_venue');
            if (isCompetitive() && !venue.value.trim()) fail(venue, 'Enter the place of the bid opening.');
            else if (show) setError(venue, '');

            const preproc = byId('pre_procurement_conference_at');
            const held = preproc.value ? new Date(`${preproc.value}:00+08:00`) : null;
            if (preProcRequired() && !held) fail(preproc, 'Record when the pre-procurement conference was held: it is mandatory for this ABC.');
            else if (held && held > new Date()) fail(preproc, 'Record the conference only after it has been held.');
            else if (show) setError(preproc, '');
        }

        if (step === 5) {
            const confirm = $('[data-pw-confirm]');
            if (!confirm.checked) fail(confirm, 'Confirm the details before publishing.', 'confirm_correct');
            else if (show) setError('confirm_correct', '');
        }

        return invalid;
    }

    /* ------------------------------------------------------------------ */
    /* Navigation                                                          */
    /* ------------------------------------------------------------------ */

    const body = $('[data-pw-body]');

    function focusField(field) {
        if (!field) return;
        const target = field.matches?.('[type=hidden]') ? null : field;
        (target || body).focus({ preventScroll: true });
        (target || body).scrollIntoView?.({ block: 'center', behavior: 'smooth' });
    }

    function show(step, { focusHeading = true } = {}) {
        current = Math.min(Math.max(step, 1), TOTAL);

        $$('[data-pw-step]').forEach((panel) => { panel.hidden = Number(panel.dataset.pwStep) !== current; });
        $$(`[data-pw-step="${current}"] [data-pw-autogrow]`).forEach((textarea) => { if (!textarea.closest('[hidden]')) autogrow(textarea); });
        $$('[data-pw-marker]').forEach((marker) => {
            const n = Number(marker.dataset.pwMarker);
            marker.classList.toggle('is-current', n === current);
            marker.classList.toggle('is-done', n < current);
            const button = $('button', marker);
            if (n === current) button.setAttribute('aria-current', 'step'); else button.removeAttribute('aria-current');
            $('[data-pw-marker-state]', marker).textContent = n === current ? ' (current step)' : (n < current ? ' (completed)' : '');
        });

        $('[data-pw-back]').hidden = current === 1;
        $('[data-pw-next]').hidden = current === TOTAL;
        $('[data-pw-publish]').hidden = current !== TOTAL;
        $('[data-pw-position]').textContent = `Step ${current} of ${TOTAL} · ${STEP_NAMES[current - 1]}`;

        if (current === 4) {
            if (!dateSuggestionsInitialized) suggestProjectDates();
            refreshDateHints();
        }
        if (current === TOTAL) buildReview();

        body.scrollTop = 0;
        if (focusHeading) {
            const heading = $(`[data-pw-step="${current}"] .pw-step__title`);
            heading?.setAttribute('tabindex', '-1');
            heading?.focus({ preventScroll: true });
        }
    }

    /** Move forward only through steps that pass; stop at the first problem. */
    function goTo(target) {
        if (target <= current) {
            show(target);
            return;
        }
        for (let step = current; step < target; step += 1) {
            const invalid = validateStep(step);
            if (invalid.length) {
                if (step !== current) show(step, { focusHeading: false });
                focusField(invalid[0]);
                return;
            }
        }
        show(target);
    }

    $('[data-pw-next]').addEventListener('click', () => goTo(current + 1));
    $('[data-pw-back]').addEventListener('click', () => show(current - 1));
    root.addEventListener('click', (event) => {
        const go = event.target.closest('[data-pw-go]');
        if (go) goTo(Number(go.dataset.pwGo));
    });

    /* ------------------------------------------------------------------ */
    /* Review                                                              */
    /* ------------------------------------------------------------------ */

    const selectedText = (id) => {
        const select = byId(id);
        return select?.value ? select.selectedOptions[0].textContent.trim() : '';
    };

    function row(label, value, missing) {
        const wrap = document.createElement('div');
        const dt = document.createElement('dt');
        const dd = document.createElement('dd');
        dt.textContent = label;
        dd.textContent = value || (missing ? 'Not provided' : '—');
        if (!value && missing) dd.classList.add('is-missing');
        wrap.append(dt, dd);
        return wrap;
    }

    function fill(step, rows) {
        const list = $(`[data-pw-review-list="${step}"]`);
        list.replaceChildren(...rows.map(([label, value, missing]) => row(label, value, missing)));
    }

    function buildReview() {
        $$('[data-pw-choice]').forEach(syncChoice);
        syncMoney(true);

        fill(1, [
            ['Title', val('title'), true],
            ['Category', selectedText('category'), true],
            ['Location', val('location'), true],
            ['End-user office', val('end_user_unit')],
            ['Mode', selectedText('procurement_mode') + (family() === 'negotiated' && groundSelect.value ? ` — ${selectedText('negotiation_ground')}` : ''), true],
            ['Legal basis', selectedText('legal_basis')],
            ...(isCompetitive() ? [['Award criterion', criterionSelect.value ? selectedText('award_criterion') : '', true]] : []),
            ...(consulting() ? [['Evaluation procedure', procedureSelect.value ? selectedText('evaluation_procedure') : '', true]] : []),
            ['ABC', moneyTarget.value ? `₱${formatPeso(moneyTarget.value)}` : '', true],
            ['Source of funds', val('source_of_fund'), true],
            ['Contract duration', val('contract_duration'), true],
            ['PhilGEPS record', val('philgeps_reference_no') ? `${val('philgeps_reference_no')} (external)` : 'None — not required to publish here'],
        ]);

        const required = $$('input[name="required_documents[]"]').filter((input) => (input.type === 'checkbox' ? input.checked : input.value.trim()))
            .map((input) => (input.type === 'checkbox' ? input.nextElementSibling.textContent.trim() : input.value.trim()));
        fill(2, [
            ['Required documents', required.join(', ') || 'None selected'],
            // Only the requirement notes that were added.
            ...[
                ['Eligibility', val('eligibility_requirements')],
                ['Technical', val('technical_requirements')],
                ['Financial', val('financial_requirements')],
                ['Qualification notes', val('qualification_notes')],
                ['Special instructions', val('special_instructions')],
            ].filter(([, value]) => value.trim() !== ''),
            ...(weighted() ? [['Evaluation criteria', criteriaRows().map((row) => `${row.name} ${row.weight}%`).join(', '), true]] : []),
            ...(weighted() && criterionSelect.value === 'mearb' ? [['Quality-price ratio', qprInput.value ? `${qprInput.value}% technical / ${100 - Number(qprInput.value)}% price` : '', true]] : []),
            ['Submission', submissionMode.value === 'manual' ? `Manual, sealed — ${val('submission_venue')}` : `Online, through this system${val('electronic_submission_authority') ? ` — ${val('electronic_submission_authority')}` : ''}`],
            ['Bidding documents fee', (() => {
                const amount = syncFee();
                const mode = feeModes.find((radio) => radio.checked)?.value || 'schedule';
                if (isCompetitive() && mode === 'waived') return `Waived: ${val('bidding_fee_reason')}`;
                if (!(amount > 0)) return 'None';
                const note = !isCompetitive() ? '' : (mode === 'reduced' ? ` (lower than the maximum: ${val('bidding_fee_reason')})` : ' (maximum for the ABC)');
                return `₱${formatPeso(amount)}${note} — ${val('payment_venue')}`;
            })()],
            ['Bid security', security.checked ? (val('bid_security_notes') || 'Required') : 'Not required'],
        ]);

        const docs = docRows().filter(({ file }) => file);
        fill(3, docs.length
            ? docs.map(({ type, file }) => [type.value ? type.selectedOptions[0].textContent : 'Type not chosen', `${file.name} (${sizeLabel(file.size)}) — uploads when you save`])
            : [['Files', 'No files selected']]);

        const dated = (id) => {
            const text = phLabel(val(id));
            return text ? `${text}${text.includes('·') ? ' PST' : ''}` : '';
        };
        fill(4, [
            ...(isCompetitive() ? [['Pre-procurement conference', dated('pre_procurement_conference_at') ? `${dated('pre_procurement_conference_at')}${val('pre_procurement_reference') ? ` — ${val('pre_procurement_reference')}` : ''}` : (preProcRequired() ? '' : 'Not held (optional at this ABC)'), preProcRequired()]] : []),
            ['Publication', `On publishing: ${phLabel(rules.today)}`],
            ...(prebidRequired() || val('pre_bid_conference_date') ? [['Pre-bid conference', dated('pre_bid_conference_date'), prebidRequired()]] : []),
            [$('[data-pw-deadline-label]').textContent, dated('bid_submission_deadline'), true],
            [$('[data-pw-opening-label]').textContent, dated('bid_opening_date'), true],
            ...(isCompetitive() ? [['Place of bid opening', val('bid_opening_venue'), true]] : []),
        ]);

        // Readiness: the same checks as Next, without moving the user.
        const list = $('[data-pw-readiness]');
        const items = [];
        for (let step = 1; step <= 4; step += 1) {
            const problems = validateStep(step, false);
            items.push({ step, ok: problems.length === 0 });
        }
        list.replaceChildren(...items.map(({ step, ok }) => {
            const li = document.createElement('li');
            li.className = ok ? 'is-ok' : 'is-issue';
            li.innerHTML = `<i class="fas ${ok ? 'fa-circle-check' : 'fa-circle-exclamation'}" aria-hidden="true"></i>`;
            const text = document.createElement('span');
            text.textContent = ok ? `${STEP_NAMES[step - 1]}: ready` : `${STEP_NAMES[step - 1]}: needs attention before publishing`;
            li.appendChild(text);
            if (!ok) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'ui-btn ui-btn--ghost ui-btn--sm';
                button.dataset.pwGo = String(step);
                button.textContent = 'Fix';
                li.appendChild(button);
            }
            return li;
        }));
    }

    /* ------------------------------------------------------------------ */
    /* Save draft / publish                                                */
    /* ------------------------------------------------------------------ */

    const actionButtons = () => $$('.pw__footer button, .pw__footer a, [data-pw-leave-draft], [data-pw-publish-confirm]', document);

    function setBusy(kind) {
        submitting = true;
        root.setAttribute('aria-busy', 'true');
        actionButtons().forEach((button) => {
            button.setAttribute('aria-disabled', 'true');
            if (button.tagName === 'BUTTON') button.disabled = true;
        });
        const label = kind === 'draft' ? $('[data-pw-draft-label]') : $('[data-pw-publish-label]');
        label.textContent = kind === 'draft' ? 'Saving draft…' : 'Publishing…';
        label.closest('button').classList.add('is-busy');
    }

    function clearBusy() {
        submitting = false;
        root.removeAttribute('aria-busy');
        actionButtons().forEach((button) => {
            button.removeAttribute('aria-disabled');
            if (button.tagName === 'BUTTON') button.disabled = false;
        });
        $('[data-pw-draft-label]').textContent = 'Save as draft';
        $('[data-pw-publish-label]').textContent = 'Publish in the BAC system';
        $$('.is-busy').forEach((el) => el.classList.remove('is-busy'));
        restoreDocsAfterSubmit();
    }

    function submitAs(status) {
        if (submitting) return;
        $$('[data-pw-choice]').forEach(syncChoice);
        syncMoney(true);
        byId('projectStatus').value = status;
        prepareDocsForSubmit();
        setBusy(status === 'open' ? 'publish' : 'draft');
        form.submit();
    }

    function saveDraft() {
        // A draft needs nothing, except that each attached file has a type.
        const pending = docRows().find(({ file, type }) => file && !type.value);
        if (pending) {
            show(3, { focusHeading: false });
            validateDocs(false);
            focusField(pending.type);
            return;
        }
        submitAs('draft');
    }

    $('[data-pw-draft]').addEventListener('click', saveDraft);

    const publishDialog = byId('pwPublishDialog');

    $('[data-pw-publish]').addEventListener('click', () => {
        for (let step = 1; step <= TOTAL; step += 1) {
            const invalid = validateStep(step, true, true);
            if (invalid.length) {
                if (step !== current) show(step, { focusHeading: false });
                focusField(invalid[0]);
                return;
            }
        }

        const summary = $('[data-pw-publish-summary]', publishDialog);
        summary.replaceChildren(
            row('Project', val('title')),
            row('Mode', selectedText('procurement_mode')),
            row('ABC', `₱${formatPeso(moneyTarget.value)}`),
            row($('[data-pw-deadline-label]').textContent, `${phLabel(val('bid_submission_deadline'))} PST`),
            row('Files to upload', String(docRows().filter(({ file }) => file).length)),
        );
        publishDialog.showModal();
        $('[data-pw-publish-cancel]', publishDialog).focus();
    });

    $('[data-pw-publish-cancel]', publishDialog).addEventListener('click', () => publishDialog.close());
    $('[data-pw-publish-confirm]', publishDialog).addEventListener('click', () => {
        publishDialog.close();
        submitAs('open');
    });

    // Returning with the browser's back button restores the page from cache.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) clearBusy();
    });

    /* ------------------------------------------------------------------ */
    /* Leaving with unsaved changes                                        */
    /* ------------------------------------------------------------------ */

    const leaveDialog = byId('pwLeaveDialog');
    let leaveTarget = null;

    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('change', () => { dirty = true; });

    $$('[data-pw-exit]').forEach((link) => link.addEventListener('click', (event) => {
        if (!dirty || submitting) return;
        event.preventDefault();
        leaveTarget = link.href;
        leaveDialog.showModal();
        $('[data-pw-leave-stay]', leaveDialog).focus();
    }));

    $('[data-pw-leave-stay]', leaveDialog).addEventListener('click', () => leaveDialog.close());
    $('[data-pw-leave-draft]', leaveDialog).addEventListener('click', () => {
        leaveDialog.close();
        saveDraft();
    });
    $('[data-pw-leave-go]', leaveDialog).addEventListener('click', () => {
        dirty = false;
        window.location.assign(leaveTarget || '/');
    });

    window.addEventListener('beforeunload', (event) => {
        if (!dirty || submitting) return;
        event.preventDefault();
        event.returnValue = '';
    });

    /* ------------------------------------------------------------------ */
    /* Start                                                               */
    /* ------------------------------------------------------------------ */

    const description = byId('description');
    const counter = $('[data-pw-count="description"]');
    const syncCount = () => { counter.textContent = String(description.value.length); };
    description.addEventListener('input', syncCount);
    syncCount();

    syncMoney(Boolean(moneyInput?.value));
    syncMode();
    syncRequirementSuggestions();
    syncSubmission();
    $$('[data-pw-doc]').forEach(renderDoc);

    const firstStep = Number(root.dataset.firstStep || 1);
    show(firstStep, { focusHeading: false });
    const firstInvalid = $(`[data-pw-step="${firstStep}"] [aria-invalid="true"]`);
    if (firstInvalid) focusField(firstInvalid);
    else if ($('[data-pw-server-errors]')) $('[data-pw-server-errors]').focus?.();
    else byId('title')?.focus({ preventScroll: true });
}
