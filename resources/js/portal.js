/*
 * SJBAC portal behaviour: navigation drawer, notification bell, toasts,
 * dialogs, auto-submitting filters and stepped forms with a review step.
 * Works on the rebuilt portal pages and on older pages that share the
 * portal sidebar.
 */

import { groupDigits } from './money-input';

const body = document.body;

/* ---------- Navigation drawer (small screens) ---------- */

function setupNavigation() {
    const sidebar = document.getElementById('portalSidebar');
    if (!sidebar) return;

    // Older pages have their own header: reuse its menu button, or add one.
    const toggleSelector = '[data-portal-nav-toggle], [data-dashboard-sidebar-toggle]';
    if (!document.querySelector(toggleSelector)) {
        const host = document.querySelector('.navbar .nav-left') || document.querySelector('.navbar');
        if (host) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'portal-menu-btn';
            button.dataset.portalNavToggle = '';
            button.setAttribute('aria-controls', 'portalSidebar');
            button.setAttribute('aria-expanded', 'false');
            button.setAttribute('aria-label', 'Open navigation');
            button.innerHTML = '<i class="fas fa-bars" aria-hidden="true"></i>';
            host.insertBefore(button, host.firstChild);
        }
    }

    const toggles = document.querySelectorAll(toggleSelector);
    toggles.forEach((toggle) => toggle.setAttribute('aria-controls', 'portalSidebar'));

    const setOpen = (open) => {
        body.classList.toggle('portal-nav-open', open);
        toggles.forEach((toggle) => toggle.setAttribute('aria-expanded', open ? 'true' : 'false'));
        if (open) {
            sidebar.querySelector('a, button')?.focus();
        }
    };

    toggles.forEach((toggle) => toggle.addEventListener('click', () => setOpen(!body.classList.contains('portal-nav-open'))));

    document.querySelectorAll('[data-portal-nav-close]').forEach((closer) => closer.addEventListener('click', () => {
        setOpen(false);
        toggles[0]?.focus();
    }));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && body.classList.contains('portal-nav-open')) {
            setOpen(false);
            toggles[0]?.focus();
        }
    });

    window.matchMedia('(min-width: 769px)').addEventListener?.('change', (query) => {
        if (query.matches) setOpen(false);
    });
}

/* ---------- Notification bell ---------- */

function setupBell() {
    const bell = document.querySelector('[data-portal-bell]');
    if (!bell) return;

    const toggle = bell.querySelector('[data-portal-bell-toggle]');
    const panel = bell.querySelector('.portal-bell__panel');

    const setOpen = (open) => {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        setOpen(panel.hidden);
    });

    document.addEventListener('click', (event) => {
        if (!panel.hidden && !bell.contains(event.target)) setOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
            toggle.focus();
        }
    });
}

/* ---------- Toasts and dialogs ---------- */

function setupToasts() {
    document.querySelectorAll('[data-ui-autohide]').forEach((toast) => {
        window.setTimeout(() => {
            toast.classList.add('is-hiding');
            window.setTimeout(() => toast.remove(), 360);
        }, 6000);
    });
}

function setupDialogs() {
    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-dialog-open]');
        if (opener) {
            const dialog = document.getElementById(opener.dataset.dialogOpen);
            if (dialog && typeof dialog.showModal === 'function') {
                dialog.showModal();
                dialog.querySelector('input:not([type=hidden]), select, textarea')?.focus();
            }
            return;
        }

        const closer = event.target.closest('[data-dialog-close]');
        if (closer) {
            closer.closest('dialog')?.close();
            return;
        }

        if (event.target.tagName === 'DIALOG') event.target.close();
    });

    // Reopen a dialog whose form came back with validation errors.
    document.querySelectorAll('dialog[data-open-on-load]').forEach((dialog) => {
        if (typeof dialog.showModal === 'function') dialog.showModal();
    });
}

/* ---------- Filters ---------- */

function setupAutoSubmit() {
    document.querySelectorAll('[data-autosubmit]').forEach((field) => {
        field.addEventListener('change', () => field.form?.requestSubmit());
    });
}

/* ---------- Inline validation ---------- */

function fieldMessage(field) {
    const id = field.getAttribute('aria-describedby')?.split(' ').find((candidate) => candidate.endsWith('-error'));
    return id ? document.getElementById(id) : null;
}

function validateField(field) {
    if (!field.willValidate) return true;

    const valid = field.checkValidity();
    field.setAttribute('aria-invalid', valid ? 'false' : 'true');

    const message = fieldMessage(field);
    if (message && message.dataset.client !== undefined) {
        message.textContent = valid ? '' : field.validationMessage;
        message.hidden = valid;
    }

    return valid;
}

function setupInlineValidation(root = document) {
    root.querySelectorAll('form[data-validate] :is(input, select, textarea)').forEach((field) => {
        field.addEventListener('blur', () => {
            if (field.value !== '' || field.getAttribute('aria-invalid') === 'true') validateField(field);
        });
        field.addEventListener('input', () => {
            if (field.getAttribute('aria-invalid') === 'true') validateField(field);
        });
    });
}

/* ---------- Peso amount inputs ---------- */

function formatPeso(amount) {
    return '₱' + amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function setupMoneyInputs(root = document) {
    root.querySelectorAll('input[data-money]').forEach((field) => {
        const quantity = document.querySelector(field.dataset.moneyQuantity || null);
        const unit = document.querySelector(field.dataset.moneyUnit || null);
        const summary = document.querySelector(field.dataset.moneySummary || null);

        const amount = () => {
            const value = Number(field.value.replace(/,/g, ''));
            return field.value.trim() !== '' && Number.isFinite(value) ? value : null;
        };

        const updateSummary = () => {
            if (!summary) return;
            const total = amount();
            const count = quantity ? Number(quantity.value) : NaN;
            // Only the per-unit price adds anything; the total is already in the field.
            if (!total || !(count > 1)) {
                summary.hidden = true;
                return;
            }

            const unitLabel = escapeHtml(unit?.value.trim() || 'unit');
            summary.innerHTML = 'About <strong>' + formatPeso(total / count) + '</strong> per ' + unitLabel
                + ' (' + count.toLocaleString('en-PH') + ' ' + unitLabel + ')';
            summary.hidden = false;
        };

        field.addEventListener('input', () => {
            groupDigits(field);
            updateSummary();
        });
        field.addEventListener('blur', () => {
            const total = amount();
            if (total !== null) field.value = total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        });
        [quantity, unit].forEach((input) => input?.addEventListener('input', updateSummary));
        updateSummary();
    });
}

function escapeHtml(value) {
    return value.replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
}

/* ---------- Stepped forms with a review step ---------- */

function reviewValue(field) {
    // A non-field block (e.g. an item table) provides its own summary text.
    if (!['INPUT', 'SELECT', 'TEXTAREA'].includes(field.tagName)) {
        return field.dataset.reviewSummary || '';
    }

    if (field.type === 'file') {
        return Array.from(field.files || []).map((file) => file.name).join(', ');
    }

    if (field.tagName === 'SELECT') {
        return field.selectedOptions[0]?.value ? field.selectedOptions[0].textContent.trim() : '';
    }

    if (field.type === 'checkbox' || field.type === 'radio') {
        return field.checked ? (field.dataset.reviewValue || 'Yes') : '';
    }

    const value = field.value.trim();
    if (value === '') return '';

    if (field.dataset.reviewFormat === 'money' && !Number.isNaN(Number(value.replace(/,/g, '')))) {
        return formatPeso(Number(value.replace(/,/g, '')));
    }

    return (field.dataset.reviewPrefix || '') + value;
}

function setupSteppedForms() {
    document.querySelectorAll('form[data-stepped]').forEach((form) => {
        const panels = Array.from(form.querySelectorAll('[data-step]'));
        const markers = Array.from(form.querySelectorAll('[data-step-marker]'));
        const review = form.querySelector('[data-review]');
        if (panels.length === 0) return;

        let current = Math.max(0, panels.findIndex((panel) => panel.querySelector('[aria-invalid="true"], .ui-error:not([hidden]):not(:empty)')));

        const show = (index) => {
            current = Math.min(Math.max(index, 0), panels.length - 1);
            panels.forEach((panel, i) => { panel.hidden = i !== current; });
            markers.forEach((marker, i) => {
                marker.classList.toggle('is-current', i === current);
                marker.classList.toggle('is-done', i < current);
                if (i === current) marker.setAttribute('aria-current', 'step'); else marker.removeAttribute('aria-current');
            });

            if (panels[current].hasAttribute('data-step-review') && review) buildReview();

            const heading = panels[current].querySelector('legend, h2, h3');
            if (heading) {
                heading.setAttribute('tabindex', '-1');
                heading.focus({ preventScroll: true });
            }
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        };

        const validatePanel = (panel) => {
            const fields = Array.from(panel.querySelectorAll('input, select, textarea')).filter((field) => !field.disabled);
            const invalid = fields.filter((field) => !validateField(field));
            if (invalid.length > 0) {
                invalid[0].focus();
                return false;
            }
            return true;
        };

        const buildReview = () => {
            const rows = [];
            panels.forEach((panel) => {
                panel.querySelectorAll('[data-review-label]').forEach((field) => {
                    if (field.type === 'radio' && !field.checked) return;
                    const value = reviewValue(field);
                    const required = field.required ?? field.hasAttribute('data-review-required');
                    rows.push({ label: field.dataset.reviewLabel, value, missing: required && value === '' });
                });
            });

            review.innerHTML = '';
            rows.forEach((row) => {
                const wrap = document.createElement('div');
                const dt = document.createElement('dt');
                const dd = document.createElement('dd');
                dt.textContent = row.label;
                dd.textContent = row.value !== '' ? row.value : (row.missing ? 'Required — not provided' : '—');
                if (row.missing) dd.classList.add('is-missing');
                wrap.append(dt, dd);
                review.append(wrap);
            });
        };

        form.addEventListener('click', (event) => {
            const next = event.target.closest('[data-step-next]');
            const prev = event.target.closest('[data-step-prev]');
            const jump = event.target.closest('[data-step-go]');

            if (next) {
                event.preventDefault();
                if (validatePanel(panels[current])) show(current + 1);
            } else if (prev) {
                event.preventDefault();
                show(current - 1);
            } else if (jump) {
                event.preventDefault();
                const target = Number(jump.dataset.stepGo);
                if (target < current || panels.slice(current, target).every(validatePanel)) show(target);
            }
        });

        // Final submit: every step must be valid.
        form.addEventListener('submit', (event) => {
            const submitter = event.submitter;
            if (submitter?.hasAttribute('formnovalidate')) return;

            const firstInvalid = panels.findIndex((panel) => !validatePanel(panel));
            if (firstInvalid !== -1) {
                event.preventDefault();
                show(firstInvalid);
                validatePanel(panels[firstInvalid]);
            }
        });

        form.setAttribute('novalidate', '');
        show(current);
    });
}

// For forms loaded into a page after it starts (e.g. the Edit Project modal).
window.BacPortal = { setupMoneyInputs };

document.addEventListener('DOMContentLoaded', () => {
    setupNavigation();
    setupBell();
    setupToasts();
    setupDialogs();
    setupAutoSubmit();
    setupInlineValidation();
    setupMoneyInputs();
    setupSteppedForms();
});
