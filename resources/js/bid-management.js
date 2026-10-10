const root = document.getElementById('bid-management') || document.getElementById('project-management');

if (root && root.dataset.bidManagementReady !== '1') {
    root.dataset.bidManagementReady = '1';

    const dashboard = root.closest('.admin-dashboard');
    const scroll = root.querySelector('[data-bid-scroll]');
    if (scroll) {
        const frame = scroll.parentElement;
        const updateScroll = () => {
            const overflow = scroll.scrollWidth > scroll.clientWidth + 1;
            frame.style.setProperty('--bid-action-width', scroll.querySelector('th.bid-actions-cell').offsetWidth + 'px');
            frame.classList.toggle('has-more-right', overflow && scroll.scrollLeft < scroll.scrollWidth - scroll.clientWidth - 2);
            const hint = root.querySelector('[data-scroll-hint]');
            if (hint) hint.hidden = !overflow;
        };
        scroll.addEventListener('scroll', updateScroll, { passive: true });
        new ResizeObserver(updateScroll).observe(scroll);
        updateScroll();
    }

    const exportKind = root.dataset.exportKind || 'bids';
    const exportModal = document.getElementById(root.dataset.exportModalId || 'bidExportModal');
    const exportRowsNode = document.getElementById(root.dataset.exportRowsId || 'bidExportRows');
    const exportTotal = exportModal.querySelector('[data-export-total]');
    const exportChips = exportModal.querySelector('[data-export-status-chips]');
    const exportSummary = exportModal.querySelector('[data-export-summary]');
    const exportFilterHelp = exportModal.querySelector('.bid-export-filter-help');
    const exportWarning = exportModal.querySelector('[data-export-warning]');
    const exportPreviewBody = exportModal.querySelector('[data-export-preview-body]');
    const exportPreviewTable = exportModal.querySelector('.bid-export-preview-table');
    const exportEmpty = exportModal.querySelector('[data-export-empty]');
    const exportPreviewCount = exportModal.querySelector('[data-export-preview-count]');
    const exportConfirm = exportModal.querySelector('[data-confirm-export]');
    const exportToastRegion = document.getElementById(root.dataset.exportToastId || 'bidExportToastRegion');
    const exportStatusOrder = (root.dataset.exportStatusOrder || 'awarded,approved,pending,disqualified')
        .split(',')
        .map(status => status.trim())
        .filter(Boolean);
    const exportItemLabel = exportKind === 'projects' ? 'project' : 'bid';
    const exportItemLabelPlural = count => count === 1 ? exportItemLabel : exportItemLabel + 's';
    let exportRows = [];
    let selectedExportStatuses = new Set();
    let exportReturnFocus = null;
    let exportToast = null;
    let exportToastTimer = null;


    try {
        exportRows = JSON.parse(exportRowsNode?.textContent || '[]');
    } catch (error) {
        exportRows = [];
    }

    // Stage labels come from the server (BidProgress::ADMIN_STAGES) so the
    // export chips use the same wording as the table and filter.
    let serverStatusLabels = {};
    try {
        serverStatusLabels = JSON.parse(root.dataset.exportStatusLabels || '{}');
    } catch (error) {
        serverStatusLabels = {};
    }

    const exportStatuses = () => exportStatusOrder;
    const exportStatusLabel = status => serverStatusLabels[status] || ({
        awarded: 'Awarded',
        approved: 'Approved',
        pending: 'Pending',
        disqualified: 'Disqualified',
        open: 'Open',
        closed: 'Closed',
        approved_for_bidding: 'Approved for Bidding',
        draft: 'Draft'
    })[status] || status;

    const renderExportPreview = () => {
        const rows = exportRows.filter(row => selectedExportStatuses.has(row.status));
        exportPreviewBody.replaceChildren();
        rows.forEach(row => {
            const tr = document.createElement('tr');
            const primary = document.createElement('td');
            const amount = document.createElement('td');
            const status = document.createElement('td');
            primary.textContent = exportKind === 'projects' ? row.title : row.bidder;
            amount.textContent = exportKind === 'projects' ? row.budget_label : row.amount_label;
            amount.className = 'is-numeric';
            status.textContent = row.status_label;
            status.className = 'bid-export-preview-status is-' + row.status_class;
            tr.append(primary, amount, status);
            exportPreviewBody.append(tr);
        });

        exportTotal.textContent = 'Exporting from all ' + exportRows.length + ' ' + exportItemLabelPlural(exportRows.length) + ' currently in this list.';
        exportSummary.textContent = rows.length + ' ' + exportItemLabelPlural(rows.length) + ' will be exported.';
        exportPreviewCount.textContent = rows.length + ' ' + exportItemLabelPlural(rows.length);
        exportPreviewTable.hidden = rows.length === 0;
        exportEmpty.hidden = rows.length !== 0;
        exportConfirm.disabled = rows.length === 0;

        if (exportKind === 'projects') {
            exportWarning.hidden = true;
        } else {
            const missing = rows.filter(row => !row.has_proposal).length;
            exportWarning.hidden = missing === 0;
            exportWarning.querySelector('span').textContent = missing + ' of these are missing a proposal file.';
        }
    };

    const renderExportChips = () => {
        const counts = exportRows.reduce((result, row) => {
            result[row.status] = (result[row.status] || 0) + 1;
            return result;
        }, {});
        exportChips.replaceChildren();
        const statuses = exportStatuses();
        exportFilterHelp.textContent = selectedExportStatuses.size === statuses.length ? 'All selected' : `${selectedExportStatuses.size} selected`;
        statuses.forEach(status => {
            const chip = document.createElement('button');
            const label = document.createElement('span');
            const count = document.createElement('strong');
            const selected = selectedExportStatuses.has(status);
            chip.type = 'button';
            chip.className = `bid-export-status-chip is-${status}${selected ? ' is-selected' : ''}`;
            chip.dataset.status = status;
            chip.setAttribute('aria-pressed', selected ? 'true' : 'false');
            label.textContent = exportStatusLabel(status);
            count.textContent = counts[status] || 0;
            chip.append(label, count);
            exportChips.append(chip);
        });
    };

    const closeExportModal = () => {
        exportModal.hidden = true;
        exportModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('bid-export-modal-open');
        dashboard.inert = false;
        if (exportReturnFocus?.isConnected) exportReturnFocus.focus();
    };

    const openExportModal = trigger => {
        exportReturnFocus = trigger;
        selectedExportStatuses = new Set(exportStatuses());
        renderExportChips();
        renderExportPreview();
        exportModal.hidden = false;
        exportModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('bid-export-modal-open');
        dashboard.inert = true;
        exportModal.querySelector('[data-close-export-modal]').focus();
    };

    const dismissExportToast = () => {
        if (exportToastTimer) clearTimeout(exportToastTimer);
        exportToastTimer = null;
        exportToast?.remove();
        exportToast = null;
    };

    const showExportToast = (message, type = 'loading', autoDismiss = 0) => {
        dismissExportToast();
        const toast = document.createElement('div');
        const icon = document.createElement('i');
        const text = document.createElement('span');
        const close = document.createElement('button');
        toast.className = `bid-export-toast is-${type}`;
        icon.className = type === 'loading' ? 'fas fa-spinner fa-spin' : type === 'success' ? 'fas fa-check-circle' : 'fas fa-circle-exclamation';
        icon.setAttribute('aria-hidden', 'true');
        text.textContent = message;
        close.type = 'button';
        close.className = 'bid-export-toast-close';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.innerHTML = '&times;';
        close.addEventListener('click', dismissExportToast);
        toast.append(icon, text, close);
        exportToastRegion.append(toast);
        exportToast = toast;
        if (autoDismiss) exportToastTimer = setTimeout(dismissExportToast, autoDismiss);
        return toast;
    };

    const confirmExport = async () => {
        const count = exportRows.filter(row => selectedExportStatuses.has(row.status)).length;
        if (!count) return;

        closeExportModal();
        showExportToast('Exporting ' + count + ' ' + exportItemLabelPlural(count) + '\u2026');
        exportConfirm.disabled = true;
        const exportForm = root.querySelector(root.dataset.exportFormSelector || '.admin-bids-toolbar');
        const params = new URLSearchParams(new FormData(exportForm));
        params.delete('per_page');
        selectedExportStatuses.forEach(status => params.append('statuses[]', status));

        try {
            const response = await fetch(root.dataset.exportUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/csv'
                },
                body: params
            });
            if (!response.ok) throw new Error('Export request failed');
            const blob = await response.blob();
            const disposition = response.headers.get('Content-Disposition') || '';
            const filename = disposition.match(/filename="?([^";]+)"?/i)?.[1] || 'selected-' + exportItemLabelPlural(2) + '.csv';
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.append(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
            showExportToast('Export complete \u2014 ' + count + ' ' + exportItemLabelPlural(count) + ' downloaded as .csv', 'success', 3500);
        } catch (error) {
            showExportToast('Export failed. Please try again.', 'error', 5000);
        } finally {
            exportConfirm.disabled = false;
        }
    };

    exportChips.addEventListener('click', event => {
        const chip = event.target.closest('[data-status]');
        if (!chip) return;
        const status = chip.dataset.status;
        if (selectedExportStatuses.has(status)) selectedExportStatuses.delete(status);
        else selectedExportStatuses.add(status);
        renderExportChips();
        renderExportPreview();
    });
    exportModal.querySelectorAll('[data-close-export-modal]').forEach(button => button.addEventListener('click', closeExportModal));
    exportModal.addEventListener('click', event => {
        if (event.target === exportModal) closeExportModal();
    });
    exportConfirm.addEventListener('click', confirmExport);
    // The trigger may sit in the page header, outside the toolbar root.
    document.querySelectorAll('[data-open-export-modal]').forEach(button => button.addEventListener('click', event => openExportModal(event.currentTarget)));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !exportModal.hidden) closeExportModal();
    });

    // Menus are placed outside the scroll container so they cannot be clipped.
    let loadModal = null;
    let reviewRefreshTimer = null;
    let activeMenu = null;
    let menuTrigger = null;
    const closeMenu = (restoreFocus = false) => {
        if (!activeMenu) return;
        activeMenu.hidden = true;
        menuTrigger.setAttribute('aria-expanded', 'false');
        if (restoreFocus) menuTrigger.focus();
        activeMenu = null;
    };
    const positionPopup = (popup, trigger) => {
        const rect = trigger.getBoundingClientRect();
        popup.style.left = Math.max(8, Math.min(rect.right - popup.offsetWidth, window.innerWidth - popup.offsetWidth - 8)) + 'px';
        const top = rect.bottom + 6;
        popup.style.top = Math.max(8, top + popup.offsetHeight > window.innerHeight - 8 ? rect.top - popup.offsetHeight - 6 : top) + 'px';
    };
    root.querySelectorAll('.bid-menu-toggle').forEach(trigger => {
        const menu = document.getElementById(trigger.getAttribute('aria-controls'));
        document.body.append(menu);
        trigger.addEventListener('click', () => {
            const wasOpen = activeMenu === menu;
            closeMenu();
            if (wasOpen) return;
            activeMenu = menu;
            menuTrigger = trigger;
            menu.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            positionPopup(menu, trigger);
            menu.querySelector('button:not(:disabled)')?.focus();
        });
    });
    document.addEventListener('click', event => {
        if (activeMenu && !activeMenu.contains(event.target) && !menuTrigger.contains(event.target)) closeMenu();
        const review = event.target.closest('[data-bid-review]');
        if (review && typeof loadModal === 'function') {
            const trigger = activeMenu ? menuTrigger : review;
            closeMenu();
            loadModal(review.dataset.bidReview, false, review.dataset.reviewSection, trigger);
        }
    });
    document.addEventListener('keydown', event => {
        if (!activeMenu) return;
        const items = [...activeMenu.querySelectorAll('button:not(:disabled)')];
        const current = items.indexOf(document.activeElement);
        if (event.key === 'Escape') {
            event.preventDefault();
            closeMenu(true);
        } else if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1
                : (current + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
            items[next]?.focus();
        } else if (event.key === 'Tab') closeMenu(true);
    });
    window.addEventListener('resize', () => closeMenu());
    scroll?.addEventListener('scroll', () => closeMenu(), { passive: true });
    document.addEventListener('scroll', event => {
        if (activeMenu && !activeMenu.contains(event.target)) closeMenu();
    }, true);

    const tooltip = document.createElement('div');
    tooltip.id = 'bid-tooltip';
    tooltip.className = 'bid-tooltip';
    tooltip.role = 'tooltip';
    tooltip.hidden = true;
    document.body.append(tooltip);
    let tooltipTrigger = null;
    const hideTooltip = () => {
        tooltip.hidden = true;
        tooltipTrigger?.removeAttribute('aria-describedby');
        tooltipTrigger = null;
    };
    const showTooltip = target => {
        hideTooltip();
        tooltipTrigger = target;
        tooltip.textContent = target.dataset.bidTooltip;
        tooltip.hidden = false;
        target.setAttribute('aria-describedby', tooltip.id);
        positionPopup(tooltip, target);
    };
    document.addEventListener('pointerover', event => {
        const target = event.target.closest('[data-bid-tooltip]');
        if (target) showTooltip(target);
    });
    document.addEventListener('pointerout', event => {
        if (tooltipTrigger && !tooltipTrigger.contains(event.relatedTarget)) hideTooltip();
    });
    document.addEventListener('focusin', event => {
        if (event.target.matches('[data-bid-tooltip]')) showTooltip(event.target);
    });
    document.addEventListener('focusout', hideTooltip);
    document.addEventListener('click', event => {
        const target = event.target.closest('button[data-bid-tooltip]');
        if (target) showTooltip(target);
    });
    document.addEventListener('scroll', hideTooltip, true);
    window.addEventListener('resize', hideTooltip);



    window.closeSuccessAlert = () => document.getElementById('successAlert')?.remove();
    if (document.getElementById('successAlert')) setTimeout(window.closeSuccessAlert, 5000);

    const modal = document.getElementById('bidViewModal');
    if (modal) {
        const dialog = modal.querySelector('[role="dialog"]');
        const body = document.getElementById('bidViewModalBody');
    let returnFocus = null;
    let request = null;

    loadModal = async function loadModal(id, edit = false, section = '', trigger = document.activeElement) {
        if (!/^\d+$/.test(String(id))) return;
        if (!modal.classList.contains('show')) returnFocus = trigger;
        request?.abort();
        request = new AbortController();
        const currentRequest = request;
        hideTooltip();
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('admin-bid-modal-open');
        dashboard.inert = true;
        body.setAttribute('aria-busy', 'true');
        body.innerHTML = '<div class="admin-bid-modal-loading" role="status">Loading ' + (edit ? 'edit form' : 'bid review') + '...</div>';
        modal.querySelector('.admin-bid-modal-close').focus();
        try {
            const url = (edit ? root.dataset.editUrl : root.dataset.viewUrl).replace('__BID__', id);
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: currentRequest.signal
            });
            if (response.redirected) {
                const destination = new URL(response.url, window.location.href);
                if (destination.pathname.endsWith('/login')) {
                    window.location.assign(destination.href);
                    return;
                }
                throw new Error('REDIRECTED');
            }
            if (!response.ok) throw new Error('HTTP_' + response.status);
            const html = await response.text();
            if (request !== currentRequest) return;
            body.innerHTML = html;
            clearInterval(reviewRefreshTimer);
            const openedReview = body.querySelector('[data-bid-review-modal]');
            if (openedReview && openedReview.dataset.technicalOpen === '0') {
                reviewRefreshTimer = setInterval(async () => {
                    if (!modal.classList.contains('show')) { clearInterval(reviewRefreshTimer); return; }
                    try {
                        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                        if (!response.ok) return;
                        const refreshed = document.createElement('div');
                        refreshed.innerHTML = await response.text();
                        const latest = refreshed.querySelector('[data-bid-review-modal]');
                        if (latest?.dataset.technicalOpen === '1') {
                            body.innerHTML = refreshed.innerHTML;
                            clearInterval(reviewRefreshTimer);
                        }
                    } catch (_) {}
                }, 10000);
            }
            if (section) {
                const target = body.querySelector('[data-review-target="' + section + '"]');
                const panel = target?.closest('[data-br-panel]');
                if (panel) selectReviewTab(panel.dataset.brPanel);
                target?.scrollIntoView({ block: 'nearest' });
                target?.focus({ preventScroll: true });
            }
        } catch (error) {
            if (error.name === 'AbortError') return;
            if (request === currentRequest) {
                console.error('Unable to load bid review modal.', error);
                const message = error.message === 'HTTP_404'
                    ? 'This bid is no longer available.'
                    : error.message === 'HTTP_403'
                        ? 'Your account is not allowed to review this bid.'
                        : error.message === 'HTTP_500'
                            ? 'The server could not load this bid. Refresh the page and try again.'
                            : error.message === 'REDIRECTED'
                                ? 'The bid request was redirected. Refresh the page and sign in again.'
                                : 'Check your connection, then refresh the page and try again.';
                body.innerHTML = '<div class="admin-bid-modal-error" role="alert"><strong>Unable to load this bid.</strong><p>' + message + '</p></div>';
            }
        } finally {
            if (request === currentRequest) body.removeAttribute('aria-busy');
        }
    };
    window.loadBidViewModal = id => loadModal(id);
    window.loadBidEditModal = id => loadModal(id, true);
    window.closeBidViewModal = () => {
        request?.abort();
        request = null;
        hideTooltip();
        clearInterval(reviewRefreshTimer);
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('admin-bid-modal-open');
        dashboard.inert = false;
        body.innerHTML = '';
        body.removeAttribute('aria-busy');
        if (returnFocus?.isConnected) returnFocus.focus();
    };
    modal.addEventListener('click', event => {
        if (event.target === modal) window.closeBidViewModal();
    });

    // Review Bid tabs and the in-modal document viewer (the modal HTML is injected, so delegate).
    const selectReviewTab = (name, focus = false) => {
        body.querySelectorAll('[data-br-tab]').forEach(tab => {
            const active = tab.dataset.brTab === name;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
            if (active && focus) tab.focus();
        });
        body.querySelectorAll('[data-br-panel]').forEach(panel => { panel.hidden = panel.dataset.brPanel !== name; });
    };
    body.addEventListener('click', event => {
        const passwordToggle = event.target.closest('[data-br-password-toggle]');
        if (passwordToggle) {
            const input = passwordToggle.closest('.br-password-control')?.querySelector('input[name="opening_password"]');
            if (input) {
                input.type = input.type === 'password' ? 'text' : 'password';
                passwordToggle.textContent = input.type === 'password' ? 'Show' : 'Hide';
                passwordToggle.setAttribute('aria-label', input.type === 'password' ? 'Show financial password' : 'Hide financial password');
            }
            return;
        }
        const actionSummary = event.target.closest('.br-decision > summary');
        if (actionSummary) {
            event.preventDefault();
            const selected = actionSummary.parentElement;
            body.querySelectorAll('.br-decision').forEach(action => { action.open = action === selected; });
            const proceedButton = body.querySelector('[data-br-proceed]');
            const nextStep = body.querySelector('[data-br-next-step]');
            const blockedReason = body.querySelector('#br-proceed-reason');
            if (proceedButton) {
                proceedButton.dataset.brProceed = selected.dataset.brAction;
                proceedButton.disabled = false;
                delete proceedButton.dataset.submitting;
                proceedButton.setAttribute('aria-describedby', 'br-next-action');
            }
            if (nextStep) nextStep.textContent = `Next step: ${selected.dataset.brNextLabel || 'Selected action'}`;
            if (blockedReason) blockedReason.hidden = true;
            return;
        }
        const proceed = event.target.closest('[data-br-proceed]');
        if (proceed) {
            if (proceed.disabled || proceed.dataset.submitting === '1') return;
            const target = [...body.querySelectorAll('[data-br-action]')]
                .find(el => el.dataset.brAction === proceed.dataset.brProceed);
            if (!target) return;
            if (proceed.dataset.brProceed === 'financial-opening') {
                const passwordForm = target.querySelector('form[action*="/open-financial"]');
                if (passwordForm && passwordForm.reportValidity()) {
                    proceed.dataset.submitting = '1';
                    proceed.disabled = true;
                    passwordForm.requestSubmit();
                }
                return;
            }
            if (target.matches('details')) {
                const form = target.querySelector('form.br-form[action*="/decisions"]');
                body.querySelectorAll('.br-decision').forEach(action => { action.open = action === target; });
                if (form) {
                    if (!form.reportValidity()) return;
                    proceed.dataset.submitting = '1';
                    proceed.disabled = true;
                    form.dataset.brFooterSubmit = '1';
                    form.requestSubmit();
                    return;
                }
            }
            if (target.matches('details')) target.open = true;
            const panel = target.closest('[data-br-panel]');
            if (panel) selectReviewTab(panel.dataset.brPanel);
            target.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            (target.querySelector('summary, input:not([type="hidden"]):not(:disabled), select, textarea, button') || target)
                .focus({ preventScroll: true });
            return;
        }
        const tab = event.target.closest('[data-br-tab]');
        if (tab) {
            selectReviewTab(tab.dataset.brTab);
            return;
        }
        const preview = event.target.closest('[data-br-preview]');
        if (!preview) return;
        const viewer = body.querySelector('[data-br-viewer]');
        const frame = viewer?.querySelector('[data-br-viewer-frame]');
        if (!frame) return;
        frame.src = preview.dataset.brPreview;
        frame.title = preview.dataset.brPreviewTitle || 'Document preview';
        viewer.querySelector('[data-br-viewer-title]').textContent = preview.dataset.brPreviewTitle || 'Document';
        viewer.querySelector('[data-br-viewer-name]').textContent = preview.dataset.brPreviewName || '';
        viewer.querySelector('[data-br-viewer-open]').href = preview.dataset.brPreview;
        body.querySelectorAll('.br-file.is-active').forEach(row => row.classList.remove('is-active'));
        preview.closest('.br-file')?.classList.add('is-active');
        viewer.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });
    body.addEventListener('keydown', event => {
        const tab = event.target.closest('[data-br-tab]');
        if (!tab || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        const tabs = [...body.querySelectorAll('[data-br-tab]')];
        const index = tabs.indexOf(tab);
        const next = event.key === 'Home' ? 0
            : event.key === 'End' ? tabs.length - 1
            : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
        event.preventDefault();
        selectReviewTab(tabs[next].dataset.brTab, true);
    });
    modal.addEventListener('submit', async event => {
        if (event.defaultPrevented) return;
        const form = event.target.closest('form.br-form');
        if (!form) return;

        if (form.matches('[data-br-financial-form]')) {
            event.preventDefault();
            if (form.dataset.brSubmitInFlight === '1') return;
            form.dataset.brSubmitInFlight = '1';
            const input = form.querySelector('input[name="opening_password"]');
            const notice = form.querySelector('[data-br-financial-error]');
            const submitButton = form.querySelector('button[type="submit"]');
            const proceedButton = body.querySelector('[data-br-proceed="financial-opening"]');
            if (notice) {
                notice.hidden = true;
                notice.textContent = '';
            }
            input?.removeAttribute('aria-invalid');
            if (submitButton) submitButton.disabled = true;
            if (proceedButton) proceedButton.disabled = true;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form)
                });
                if (response.redirected) throw new Error('Session expired. Sign in again, then reopen this bid.');
                if (response.status === 419 || response.status === 401) {
                    throw new Error('Session expired. Sign in again, then reopen this bid.');
                }
                const result = response.headers.get('content-type')?.includes('application/json')
                    ? await response.json() : {};
                if (!response.ok) {
                    const validation = result.errors || {};
                    const message = response.status === 422
                        ? validation.opening?.[0] || validation.opening_password?.[0] || result.message
                        : 'The financial password could not be verified. Try again.';
                    throw new Error(message || 'The financial password could not be verified. Try again.');
                }
                const bidId = body.querySelector('[data-bid-review-modal]')?.dataset.bidId;
                if (!bidId) throw new Error('The financial opening was recorded. Reopen the bid to continue.');
                await loadModal(bidId, false, 'opening');
                return;
            } catch (error) {
                if (notice && form.isConnected) {
                    notice.textContent = error.message || 'Could not verify the financial password. Try again.';
                    notice.hidden = false;
                    input?.setAttribute('aria-invalid', 'true');
                    if (input) input.value = '';
                    input?.focus();
                }
            } finally {
                delete form.dataset.brSubmitInFlight;
                if (submitButton) submitButton.disabled = false;
                if (proceedButton) {
                    proceedButton.disabled = false;
                    delete proceedButton.dataset.submitting;
                }
            }
            return;
        }

        const actionUrl = new URL(form.action, window.location.href);
        if (!/\/decisions\/?$/.test(actionUrl.pathname)) return;
        if (form.dataset.brFooterSubmit !== '1') {
            event.preventDefault();
            return;
        }
        if (form.dataset.brCsrfFresh === '1') {
            delete form.dataset.brCsrfFresh;
            delete form.dataset.brSubmitInFlight;
            delete form.dataset.brFooterSubmit;
            return;
        }
        if (form.dataset.brSubmitInFlight === '1') {
            event.preventDefault();
            return;
        }

        event.preventDefault();
        form.dataset.brSubmitInFlight = '1';
        const proceedButton = body.querySelector('[data-br-proceed]');
        if (proceedButton) proceedButton.disabled = true;
        let notice = form.querySelector('[data-br-submit-error]');
        if (notice) notice.remove();

        try {
            const refreshPath = actionUrl.pathname.replace(/\/decisions\/?$/, '');
            const response = await fetch(`${window.location.origin}${refreshPath}`, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok || response.redirected) throw new Error('Session expired');

            const html = await response.text();
            const refreshed = new DOMParser().parseFromString(html, 'text/html');
            const token = refreshed.querySelector('input[name="_token"]')?.value;
            const currentToken = form.querySelector('input[name="_token"]');
            if (!token || !currentToken) throw new Error('Session expired');

            currentToken.value = token;
            form.dataset.brCsrfFresh = '1';
            form.requestSubmit();
        } catch (_) {
            delete form.dataset.brSubmitInFlight;
            delete form.dataset.brFooterSubmit;
            if (proceedButton) {
                proceedButton.disabled = false;
                delete proceedButton.dataset.submitting;
            }
            notice = document.createElement('p');
            notice.className = 'br-blocked';
            notice.setAttribute('role', 'alert');
            notice.dataset.brSubmitError = '1';
            notice.textContent = 'Your session expired or changed before this decision was saved. Sign in again, reopen the bid, and submit the decision. No decision was recorded.';
            form.prepend(notice);
        }
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            hideTooltip();
            if (modal.classList.contains('show')) window.closeBidViewModal();
        }
        if (event.key !== 'Tab' || !modal.classList.contains('show')) return;
        const focusable = [...dialog.querySelectorAll('button, a[href], input, select, textarea, iframe, [tabindex]')]
            .filter(el => !el.disabled && el.tabIndex >= 0 && el.getClientRects().length);
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
            event.preventDefault(); last?.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault(); first?.focus();
        }
    });

    const params = new URLSearchParams(window.location.search);
    const viewId = params.get('view_bid');
    const editId = params.get('edit_bid');
    if (viewId || editId) {
        loadModal(viewId || editId, !viewId);
        params.delete('view_bid');
        params.delete('edit_bid');
        const query = params.toString();
        window.history.replaceState({}, '', window.location.pathname + (query ? '?' + query : '') + window.location.hash);
    }
    }
}
