document.addEventListener('DOMContentLoaded', () => {
    if (window.bacDashboardInitialized) return;
    window.bacDashboardInitialized = true;

    initDashboardSidebar();
    initDashboardModalState();
    initResponsiveDashboardTables();
    initFloatingMessages();
});

function initFloatingMessages() {
    if (document.querySelector('[data-messenger]')) return;

    document.querySelectorAll('[data-floating-messages]').forEach((root) => {
        if (root.dataset.floatingMessagesReady === '1') return;
        root.dataset.floatingMessagesReady = '1';

        const toggle = root.querySelector('[data-floating-messages-toggle]');
        const panel = root.querySelector('[data-floating-messages-panel]');
        const close = root.querySelector('[data-floating-messages-close]');
        const listView = root.querySelectorAll('[data-floating-messages-list-view]');
        const list = root.querySelector('[data-floating-messages-list]');
        const search = root.querySelector('[data-floating-messages-search]');
        const chat = root.querySelector('[data-floating-messages-chat]');
        const back = root.querySelector('[data-floating-messages-back]');
        const chatBody = root.querySelector('[data-floating-chat-body]');
        const chatName = root.querySelector('[data-floating-chat-name]');
        const chatStatus = root.querySelector('[data-floating-chat-status]');
        const typing = root.querySelector('[data-floating-chat-typing]');
        const form = root.querySelector('[data-floating-message-form]');
        const input = root.querySelector('[data-floating-message-input]');
        const attachment = root.querySelector('[data-floating-attachment]');
        const unreadBadge = root.querySelector('[data-floating-messages-unread]');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const config = {
            currentUserId: Number(root.dataset.currentUserId || 0),
            statusUrl: root.dataset.statusUrl || '',
            conversationUrl: root.dataset.conversationUrl || '',
            typingUrl: root.dataset.typingUrl || '',
            storeUrl: root.dataset.storeUrl || '',
        };

        root.style.removeProperty('display');

        const allowedAttachmentExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
        const imageAttachmentExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        const maxAttachmentSize = 10 * 1024 * 1024;

        let threads = [];
        let activeThread = null;
        let lastMessageId = 0;
        let renderedMessageIds = new Set();
        let conversationTimer = null;
        let typingTimer = null;
        let typingActive = false;
        let lastTypingSentAt = 0;

        const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[character]));
        const escapeAttribute = (value) => escapeHtml(value).replace(/"/g, '&quot;');
        const formatCount = (count) => count > 9 ? '9+' : String(count);
        const extensionFor = (name) => String(name || '').split('.').pop().toLowerCase();
        const isImageAttachment = (name) => imageAttachmentExtensions.includes(extensionFor(name));

        function setPanelOpen(open) {
            if (!panel || !toggle) return;

            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Close messages' : 'Open messages');

            if (open) {
                refreshStatus(true);
                window.setTimeout(() => activeThread ? input?.focus() : search?.focus(), 80);
            }
        }

        function setFloatingUnreadBadge(count, syncGlobal = false) {
            const numericCount = Math.max(0, Number(count) || 0);

            if (unreadBadge) {
                unreadBadge.hidden = numericCount < 1;
                unreadBadge.textContent = numericCount > 0 ? formatCount(numericCount) : '';
            }

            if (!syncGlobal) return;

            document.querySelectorAll('[data-message-badge]').forEach((badge) => {
                if (numericCount > 0) {
                    badge.textContent = String(numericCount);
                    badge.hidden = false;
                    badge.style.display = 'inline-flex';
                } else {
                    badge.textContent = '';
                    badge.hidden = true;
                    badge.style.display = 'none';
                }
            });

            if (typeof window.BacUpdateMessageBadges === 'function') {
                window.BacUpdateMessageBadges(numericCount);
            }

            document.dispatchEvent(new CustomEvent('bac:message-count-updated', {
                detail: { count: numericCount },
            }));
        }

        function unreadCountFromThreads() {
            return threads.reduce((total, thread) => total + Number(thread.unread_count || 0), 0);
        }

        function renderThreads() {
            if (!list) return;

            const query = (search?.value || '').trim().toLowerCase();
            const visible = threads.filter((thread) => `${thread.name || ''} ${thread.email || ''} ${thread.role || ''} ${thread.preview || ''}`.toLowerCase().includes(query));

            list.innerHTML = visible.length ? visible.map((thread) => `
                <button type="button" class="floating-message-thread ${activeThread?.user_id === thread.user_id ? 'is-active' : ''}" data-floating-thread-id="${escapeAttribute(thread.user_id)}">
                    <span class="floating-message-presence ${thread.is_online ? 'is-online' : 'is-offline'}" aria-hidden="true"></span>
                    <span class="floating-message-thread-copy">
                        <strong>${escapeHtml(thread.name || 'Conversation')}</strong>
                        <span>${escapeHtml(thread.preview || 'No messages yet')}</span>
                    </span>
                    <span class="floating-message-thread-meta">
                        <small>${escapeHtml(thread.time || '')}</small>
                        ${Number(thread.unread_count || 0) > 0 ? `<b>${formatCount(Number(thread.unread_count || 0))}</b>` : ''}
                    </span>
                </button>
            `).join('') : '<div class="floating-messages-empty">No conversations available.</div>';

            list.querySelectorAll('[data-floating-thread-id]').forEach((button) => {
                button.addEventListener('click', () => openThread(Number(button.dataset.floatingThreadId)));
            });
        }

        function renderMessage(message) {
            const outgoing = Number(message.sender_id) === config.currentUserId || Boolean(message.is_outgoing);
            const state = outgoing ? (message.read_at ? 'Seen' : 'Sent') : '';
            const attachmentHtml = renderAttachment(message.attachment);

            return `<article class="floating-message ${outgoing ? 'is-outgoing' : 'is-incoming'}" data-floating-message-id="${escapeAttribute(message.id)}">
                <div class="floating-message-bubble">${message.body ? `<p>${escapeHtml(message.body)}</p>` : ''}${attachmentHtml}</div>
                <small>${escapeHtml(message.created_time || message.created_at || '')}${state ? ` <span data-floating-message-status="${escapeAttribute(message.id)}">${state}</span>` : ''}</small>
            </article>`;
        }

        function renderAttachment(file) {
            if (!file) return '';

            if (file.kind === 'image') {
                return `<a class="floating-message-attachment is-image" href="${escapeAttribute(file.url || '#')}" target="_blank" rel="noopener">
                    <img src="${escapeAttribute(file.url || '')}" alt="${escapeAttribute(file.name || 'Attachment')}">
                </a>`;
            }

            return `<a class="floating-message-attachment" href="${escapeAttribute(file.download_url || file.url || '#')}" target="_blank" rel="noopener">
                <i class="fas fa-paperclip" aria-hidden="true"></i> ${escapeHtml(file.name || 'Attachment')}
            </a>`;
        }

        function appendMessages(messages, replace = false) {
            if (!chatBody) return;

            if (replace) {
                chatBody.innerHTML = '';
                renderedMessageIds = new Set();
            }

            (messages || []).forEach((message) => {
                if (renderedMessageIds.has(String(message.id))) return;

                chatBody.insertAdjacentHTML('beforeend', renderMessage(message));
                renderedMessageIds.add(String(message.id));
                lastMessageId = Math.max(lastMessageId, Number(message.id) || 0);
            });

            if (renderedMessageIds.size === 0) {
                chatBody.innerHTML = '<div class="floating-messages-empty">No messages yet. Send your first message.</div>';
            }

            if ((messages || []).length || replace) {
                chatBody.scrollTop = chatBody.scrollHeight;
            }
        }

        async function syncConversation(initial = false) {
            if (!activeThread || !config.conversationUrl) return;

            const url = new URL(config.conversationUrl, window.location.origin);
            url.searchParams.set('user', activeThread.user_id);
            url.searchParams.set('after_id', initial ? '0' : String(lastMessageId));

            try {
                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) return;

                const data = await response.json();
                if (data.ok === false) return;

                appendMessages(data.messages || [], initial);
                updateReadStates(data.read_states || []);
                updateActiveCounterpart(data.counterpart || null);
                setTyping(data.typing || null);
                setFloatingUnreadBadge(data.unread_messages_count, true);

                if (Number(data.latest_message_id || 0) > 0) {
                    lastMessageId = Math.max(lastMessageId, Number(data.latest_message_id || 0));
                }

                await refreshStatus(false);
            } catch (_) {}
        }

        function updateReadStates(readStates) {
            (readStates || []).forEach((state) => {
                const status = chatBody?.querySelector(`[data-floating-message-status="${CSS.escape(String(state.id))}"]`);
                if (status) status.textContent = state.read_at ? 'Seen' : 'Sent';
            });
        }

        function setConversationStatus(isOnline) {
            if (!chatStatus) return;

            chatStatus.hidden = false;
            chatStatus.textContent = isOnline ? 'Online' : 'Offline';
            chatStatus.classList.toggle('is-online', isOnline);
            chatStatus.classList.toggle('is-offline', !isOnline);
        }

        function resetConversationStatus() {
            if (!chatStatus) return;

            chatStatus.classList.remove('is-online', 'is-offline');
            chatStatus.textContent = '';
            chatStatus.hidden = true;
        }

        function updateActiveCounterpart(counterpart) {
            if (!counterpart) return;

            if (chatName) chatName.textContent = counterpart.name || activeThread?.name || 'Conversation';
            setConversationStatus(Boolean(counterpart.is_online));

            if (activeThread) {
                activeThread = {
                    ...activeThread,
                    name: counterpart.name || activeThread.name,
                    is_online: Boolean(counterpart.is_online),
                };
            }
        }

        function restartConversationSync() {
            window.clearInterval(conversationTimer);
            conversationTimer = window.setInterval(() => syncConversation(false), 2500);
        }

        function openThread(userId) {
            activeThread = threads.find((thread) => Number(thread.user_id) === userId);
            if (!activeThread) return;

            threads = threads.map((thread) => Number(thread.user_id) === userId ? { ...thread, unread_count: 0 } : thread);
            setFloatingUnreadBadge(unreadCountFromThreads(), true);
            renderThreads();

            root.classList.add('is-floating-chat-open');
            listView.forEach((node) => { node.hidden = true; });
            if (back) back.hidden = false;
            if (chat) chat.hidden = false;
            if (chatName) chatName.textContent = activeThread.name || 'Conversation';
            setConversationStatus(Boolean(activeThread.is_online));

            lastMessageId = 0;
            renderedMessageIds = new Set();
            appendMessages([], true);
            syncConversation(true);
            restartConversationSync();
            window.setTimeout(() => input?.focus(), 80);
        }

        function showThreadList() {
            activeThread = null;
            window.clearInterval(conversationTimer);
            root.classList.remove('is-floating-chat-open');
            if (back) back.hidden = true;
            if (chat) chat.hidden = true;
            if (chatName) chatName.textContent = 'Messages';
            resetConversationStatus();
            listView.forEach((node) => { node.hidden = false; });
            setTyping(null);
            renderThreads();
        }

        async function refreshStatus(renderList = true) {
            if (!config.statusUrl) return;

            try {
                const response = await fetch(config.statusUrl, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) return;

                const data = await response.json();
                if (data.ok === false) return;

                const statuses = data.statuses || {};
                threads = (data.threads || []).map((thread) => ({
                    ...thread,
                    is_online: Boolean(statuses[thread.user_id]),
                }));

                const unreadCount = Number.isFinite(Number(data.unread_messages_count))
                    ? Number(data.unread_messages_count)
                    : unreadCountFromThreads();
                setFloatingUnreadBadge(unreadCount, true);

                if (activeThread) {
                    const freshThread = threads.find((thread) => Number(thread.user_id) === Number(activeThread.user_id));
                    if (freshThread) {
                        activeThread = { ...freshThread, unread_count: 0 };
                        if (chatName) chatName.textContent = activeThread.name || 'Conversation';
                        setConversationStatus(Boolean(activeThread.is_online));
                    }
                }

                if (renderList && !activeThread) renderThreads();
            } catch (_) {}
        }

        function setTyping(payload) {
            if (!typing) return;

            typing.hidden = !payload?.is_typing;
            typing.textContent = payload?.label || '';
        }

        async function sendTyping(isTyping) {
            if (!activeThread || !config.typingUrl || (typingActive === isTyping && Date.now() - lastTypingSentAt < 1200)) return;

            typingActive = isTyping;
            lastTypingSentAt = Date.now();

            try {
                await fetch(config.typingUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ recipient_id: activeThread.user_id, is_typing: isTyping }),
                });
            } catch (_) {}
        }

        function validateAttachment(file) {
            if (!file) return '';

            if (!allowedAttachmentExtensions.includes(extensionFor(file.name))) {
                return 'Only JPG, JPEG, PNG, WEBP, PDF, DOC, DOCX, XLS, and XLSX attachments are allowed.';
            }

            if (file.size > maxAttachmentSize) {
                return 'Attachments must be 10MB or smaller.';
            }

            return '';
        }

        toggle?.addEventListener('click', () => setPanelOpen(panel?.hidden));
        close?.addEventListener('click', () => setPanelOpen(false));
        back?.addEventListener('click', showThreadList);
        search?.addEventListener('input', renderThreads);

        document.addEventListener('bac:message-count-updated', (event) => {
            setFloatingUnreadBadge(Number(event.detail?.count || 0));
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && panel && !panel.hidden) setPanelOpen(false);
        });

        input?.addEventListener('input', () => {
            window.clearTimeout(typingTimer);
            if (!activeThread) return;

            sendTyping(input.value.trim() !== '');
            typingTimer = window.setTimeout(() => sendTyping(false), 1800);
        });

        attachment?.addEventListener('change', () => {
            const file = attachment.files?.[0] || null;
            const validationMessage = validateAttachment(file);
            if (validationMessage) {
                alert(validationMessage);
                attachment.value = '';
            }
        });

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!activeThread || !config.storeUrl || (!input?.value.trim() && !attachment?.files?.length)) return;

            const file = attachment?.files?.[0] || null;
            const validationMessage = validateAttachment(file);
            if (validationMessage) {
                alert(validationMessage);
                if (attachment) attachment.value = '';
                return;
            }

            const button = form.querySelector('button[type="submit"]');
            const formData = new FormData();
            formData.append('recipient_id', activeThread.user_id);
            formData.append('body', input?.value.trim() || '');
            if (file) formData.append('attachment', file);

            if (button) button.disabled = true;

            try {
                const response = await fetch(config.storeUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                });
                const data = await response.json();
                if (!response.ok || data.ok === false) throw new Error(data.message || 'Message failed to send.');

                appendMessages([{ ...data.message, is_outgoing: true }]);
                if (input) input.value = '';
                if (attachment) attachment.value = '';
                await sendTyping(false);
                await refreshStatus(false);
            } catch (error) {
                alert(error.message || 'Message failed to send.');
            } finally {
                if (button) button.disabled = false;
                input?.focus();
            }
        });

        refreshStatus(false);
        window.setInterval(() => refreshStatus(!activeThread && panel && !panel.hidden), 5000);
    });
}
function initDashboardSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const navLeft = document.querySelector('.navbar .nav-left');

    if (!sidebar || typeof window.matchMedia !== 'function') {
        return;
    }

    const mobileBreakpoint = window.matchMedia('(max-width: 768px)');
    const sidebarId = sidebar.id || 'dashboardSidebar';
    sidebar.id = sidebarId;

    let toggleButton = document.querySelector('[data-dashboard-sidebar-toggle]');

    if (!toggleButton) {
        if (!navLeft) {
            return;
        }

        toggleButton = document.createElement('button');
        toggleButton.type = 'button';
        toggleButton.className = 'dashboard-menu-toggle';
        toggleButton.dataset.dashboardSidebarToggle = 'true';
        toggleButton.setAttribute('aria-controls', sidebarId);
        toggleButton.setAttribute('aria-expanded', 'false');
        toggleButton.setAttribute('aria-label', 'Open navigation menu');
        toggleButton.innerHTML = '<i class="fas fa-bars" aria-hidden="true"></i>';
        navLeft.insertBefore(toggleButton, navLeft.firstChild);
    }

    let backdrop = document.querySelector('.dashboard-sidebar-backdrop');

    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'dashboard-sidebar-backdrop';
        backdrop.hidden = true;
        document.body.appendChild(backdrop);
    }

    const closeSidebar = () => {
        document.body.classList.remove('dashboard-sidebar-open');
        toggleButton.setAttribute('aria-expanded', 'false');
        toggleButton.setAttribute('aria-label', 'Open navigation menu');
        backdrop.hidden = true;
    };

    const openSidebar = () => {
        if (!mobileBreakpoint.matches) {
            return;
        }

        document.body.classList.add('dashboard-sidebar-open');
        toggleButton.setAttribute('aria-expanded', 'true');
        toggleButton.setAttribute('aria-label', 'Close navigation menu');
        backdrop.hidden = false;
    };

    const toggleSidebar = () => {
        if (document.body.classList.contains('dashboard-sidebar-open')) {
            closeSidebar();
            return;
        }

        openSidebar();
    };

    toggleButton.addEventListener('click', toggleSidebar);
    backdrop.addEventListener('click', closeSidebar);

    sidebar.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', () => {
            if (mobileBreakpoint.matches) {
                closeSidebar();
            }
        });
    });

    const handleViewportChange = (event) => {
        if (!event.matches) {
            closeSidebar();
        }
    };

    if (typeof mobileBreakpoint.addEventListener === 'function') {
        mobileBreakpoint.addEventListener('change', handleViewportChange);
    } else if (typeof mobileBreakpoint.addListener === 'function') {
        mobileBreakpoint.addListener(handleViewportChange);
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains('dashboard-sidebar-open')) {
            closeSidebar();
        }
    });

    closeSidebar();
}

function initDashboardModalState() {
    if (window.bacDashboardModalStateInitialized || typeof MutationObserver === 'undefined') {
        return;
    }

    window.bacDashboardModalStateInitialized = true;

    const modalSelector = [
        '#awardViewModal',
        '#declareWinnerModal',
        '#projectFilesModal',
        '#viewProjectModal',
        '#editProjectModal',
        '#createUserModal',
        '#editUserModal',
        '#userReviewModal',
        '#bidViewModal',
        '#createModalOverlay',
        '.admin-bid-modal-overlay',
        '.assignment-modal',
        '.modal-overlay',
        '.user-modal',
        '.user-review-modal',
        '.bidder-modal-overlay',
        '.bidder-scanner-modal-overlay',
        '.message-chat-modal',
    ].join(', ');

    const isModalVisible = (modal) => {
        if (!modal || modal.hidden) {
            return false;
        }

        const style = window.getComputedStyle(modal);
        return style.display !== 'none' && style.visibility !== 'hidden' && Number(style.opacity || 1) !== 0;
    };

    const syncModalState = () => {
        const hasOpenModal = Array.from(document.querySelectorAll(modalSelector)).some(isModalVisible);
        document.body.classList.toggle('bac-modal-open', hasOpenModal);
    };

    const scheduleSync = () => window.setTimeout(syncModalState, 0);

    document.addEventListener('click', scheduleSync, true);
    document.addEventListener('keydown', scheduleSync, true);
    document.addEventListener('submit', scheduleSync, true);

    const observer = new MutationObserver((mutations) => {
        const shouldSync = mutations.some((mutation) => (
            mutation.type === 'attributes'
            || Array.from(mutation.addedNodes).some((node) => (
                node.nodeType === Node.ELEMENT_NODE
                && (node.matches?.(modalSelector) || node.querySelector?.(modalSelector))
            ))
        ));

        if (shouldSync) {
            syncModalState();
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'style', 'hidden', 'aria-hidden'],
    });

    syncModalState();
}

function initResponsiveDashboardTables() {
    syncResponsiveDashboardTables();

    if (window.bacResponsiveTableObserver || typeof MutationObserver === 'undefined') {
        return;
    }

    window.bacResponsiveTableObserver = new MutationObserver((mutations) => {
        const shouldSync = mutations.some((mutation) => (
            Array.from(mutation.addedNodes).some((node) => (
                node.nodeType === Node.ELEMENT_NODE
                && (
                    node.matches?.('table, tbody, tr, td')
                    || node.querySelector?.('table, tbody, tr, td')
                )
            ))
        ));

        if (shouldSync) {
            syncResponsiveDashboardTables();
        }
    });

    window.bacResponsiveTableObserver.observe(document.body, {
        childList: true,
        subtree: true,
    });
}

function syncResponsiveDashboardTables() {
    const tables = document.querySelectorAll([
        '.dashboard-table',
        '.staff-table',
        '.staff-subtable',
        '.bidder-table',
        '.data-table',
        '.report-table',
        '.admin-bids-table',
        '.content-card table',
        '.table-container table',
        '.admin-dashboard table',
    ].join(', '));

    tables.forEach((table) => {
        if (table.hasAttribute('data-native-scroll')) return;

        const headers = Array.from(table.querySelectorAll('thead th')).map((header) => (
            header.textContent.replace(/\s+/g, ' ').trim()
        ));

        if (headers.length === 0) {
            return;
        }

        table.classList.add('dashboard-responsive-table');
        table.parentElement?.classList.add('dashboard-table-fit-wrap');

        const actionIndex = headers.findIndex((header) => /^actions?$/i.test(header));

        if (actionIndex >= 0) {
            table.classList.add('dashboard-table-has-actions');
        }

        table.querySelectorAll('tbody tr').forEach((row) => {
            Array.from(row.children).forEach((cell, index) => {
                if (!(cell instanceof HTMLTableCellElement)) {
                    return;
                }

                if (cell.colSpan > 1) {
                    cell.dataset.tableEmpty = 'true';
                    return;
                }

                if (!cell.dataset.label && headers[index]) {
                    cell.dataset.label = headers[index];
                }

                if (index === actionIndex) {
                    cell.classList.add('dashboard-table-action-cell');
                }
            });
        });
    });
}



