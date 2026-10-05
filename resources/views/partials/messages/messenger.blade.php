@php
    $selectedContact = $selectedContact ?? null;
    $hasSelectedContact = $selectedContact instanceof \App\Models\User;
    $conversationMessages = collect($conversationMessages ?? []);
    $activeThreadSummaries = collect($activeThreadSummaries ?? []);
    $latestMessageId = (int) ($conversationMessages->max('id') ?? 0);
@endphp

<section
    class="messenger-shell"
    data-messenger
    data-current-user-id="{{ auth()->id() }}"
    data-active-user-id="{{ $selectedContact?->id ?? 0 }}"
    data-latest-message-id="{{ $latestMessageId }}"
    data-sync-url="{{ $messageSyncRoute }}"
    data-typing-url="{{ $messageTypingRoute }}"
    data-store-url="{{ $messageStoreRoute }}"
    data-status-url="{{ $messageStatusRoute }}"
>
    @if(session('success'))
        <div class="messenger-alert messenger-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="messenger-alert messenger-alert-warning">{{ $errors->first() }}</div>
    @endif

    <div class="messenger-grid {{ $hasSelectedContact ? 'is-chat-open' : '' }}" data-messenger-grid>
        <aside class="messenger-conversations" aria-label="Conversations">
            <div class="messenger-conversation-top">
                <nav class="messenger-tabs" aria-label="Message categories">
                    @foreach($messageTabs as $tab)
                        <a href="{{ $tab['url'] }}" class="messenger-tab {{ $activeTab === $tab['key'] ? 'is-active' : '' }}">
                            <i class="{{ $tab['icon'] }}" aria-hidden="true"></i>
                            {{ $tab['label'] }}
                        </a>
                    @endforeach
                </nav>

                <label class="messenger-search">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" placeholder="Search conversations..." class="messenger-search-input" data-message-search>
                </label>
            </div>

            <div class="messenger-conversation-list" data-conversation-list>
                @forelse($activeThreadSummaries as $thread)
                    @include('partials.messages.conversation-item', [
                        'thread' => $thread,
                        'selectedContact' => $selectedContact,
                        'messageRouteName' => $messageRouteName,
                        'tabByRole' => $tabByRole,
                        'activeTab' => $activeTab,
                    ])
                @empty
                    <div class="messenger-empty-list" data-empty-list>
                        <i class="fas fa-inbox"></i>
                        <strong>No conversations available.</strong>
                    </div>
                @endforelse
            </div>
        </aside>

        @include('partials.messages.chat-window', [
            'selectedContact' => $selectedContact,
            'conversationMessages' => $conversationMessages,
            'messageStoreRoute' => $messageStoreRoute,
            'messageDraft' => $messageDraft ?? '',
        ])
    </div>
</section>

@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-messenger]').forEach(initMessenger);
        });

        function initMessenger(root) {
            const config = {
                currentUserId: Number(root.dataset.currentUserId || 0),
                activeUserId: Number(root.dataset.activeUserId || 0),
                latestMessageId: Number(root.dataset.latestMessageId || 0),
                syncUrl: root.dataset.syncUrl || '',
                typingUrl: root.dataset.typingUrl || '',
                storeUrl: root.dataset.storeUrl || '',
                statusUrl: root.dataset.statusUrl || '',
            };

            const grid = root.querySelector('[data-messenger-grid]');
            const list = root.querySelector('[data-conversation-list]');
            const searchInput = root.querySelector('[data-message-search]');
            const emptyChat = root.querySelector('[data-empty-chat]');
            const chatPanel = root.querySelector('[data-chat-panel]');
            const chatHeader = root.querySelector('[data-chat-header]');
            const chatBody = root.querySelector('[data-chat-body]');
            const messageStack = root.querySelector('[data-message-stack]');
            const composer = root.querySelector('[data-chat-composer]');
            const typing = root.querySelector('[data-chat-typing]');
            const typingText = root.querySelector('[data-chat-typing-text]');
            const title = root.querySelector('[data-chat-title]');
            const subtitle = root.querySelector('[data-chat-subtitle]');
            const initials = root.querySelector('[data-chat-initials]');
            const chatPresence = root.querySelector('[data-chat-presence]');
            const chatStatus = root.querySelector('[data-chat-status]');
            const chatStatusText = root.querySelector('[data-chat-status-text]');
            const form = root.querySelector('[data-message-form]');
            const recipientInput = root.querySelector('[data-recipient-input]');
            const messageInput = root.querySelector('[data-message-input]');
            const sendButton = root.querySelector('[data-send-button]');
            const fileInput = root.querySelector('[data-attachment-input]');
            const attachmentPreview = root.querySelector('[data-attachment-preview]');
            const backButton = root.querySelector('[data-chat-back]');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || form?.querySelector('input[name="_token"]')?.value
                || '';
            const allowedAttachmentExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
            const imageAttachmentExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            const maxAttachmentSize = 10 * 1024 * 1024;

            let activeUserId = config.activeUserId;
            let lastMessageId = config.latestMessageId;
            let renderedMessageIds = new Set(Array.from(root.querySelectorAll('[data-message-id]')).map(function (node) {
                return String(node.dataset.messageId);
            }));
            let syncTimer = null;
            let statusTimer = null;
            let typingStopTimer = null;
            let typingActive = false;
            let lastTypingSentAt = 0;
            let selectedAttachment = null;
            let selectedAttachmentPreviewUrl = null;

            bindThreads();
            scrollToBottom();
            pollStatus();
            statusTimer = window.setInterval(pollStatus, 5000);

            if (activeUserId > 0) {
                restartConversationSync();
            }

            searchInput?.addEventListener('input', function () {
                const query = searchInput.value.toLowerCase().trim();

                getThreads().forEach(function (item) {
                    const haystack = item.dataset.threadName || '';
                    const preview = item.querySelector('[data-thread-preview]')?.textContent.toLowerCase() || '';
                    item.style.display = haystack.includes(query) || preview.includes(query) ? 'grid' : 'none';
                });
            });

            backButton?.addEventListener('click', function () {
                grid?.classList.remove('is-chat-open');
            });

            // Phones: keep the open chat sized to the visible screen, so the message
            // box stays above the keyboard and the latest message stays in view.
            const phoneLayout = window.matchMedia('(max-width: 820px)');
            const fitChatToScreen = function () {
                if (!grid) return;
                const viewport = window.visualViewport;
                const nearBottom = chatBody ? chatBody.scrollHeight - chatBody.scrollTop - chatBody.clientHeight < 120 : false;
                grid.style.setProperty('--m-vv-height', `${viewport ? viewport.height : window.innerHeight}px`);
                grid.style.setProperty('--m-vv-top', `${viewport ? viewport.offsetTop : 0}px`);
                document.documentElement.classList.toggle('messenger-chat-fullscreen', phoneLayout.matches && grid.classList.contains('is-chat-open'));
                if (nearBottom) scrollToBottom();
            };
            window.visualViewport?.addEventListener('resize', fitChatToScreen);
            window.visualViewport?.addEventListener('scroll', fitChatToScreen);
            window.addEventListener('resize', fitChatToScreen);
            if (grid) new MutationObserver(fitChatToScreen).observe(grid, { attributes: true, attributeFilter: ['class'] });
            messageInput?.addEventListener('focus', function () {
                // After the keyboard has opened.
                window.setTimeout(function () { fitChatToScreen(); scrollToBottom(); }, 300);
            });
            fitChatToScreen();

            messageInput?.addEventListener('input', function () {
                messageInput.style.height = 'auto';
                messageInput.style.height = `${messageInput.scrollHeight}px`;
                notifyTyping();
            });

            messageInput?.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    form?.requestSubmit();
                }
            });

            fileInput?.addEventListener('change', function () {
                const file = fileInput.files?.[0] || null;

                if (!file) {
                    clearAttachment();
                    return;
                }

                const validationMessage = validateAttachment(file);
                if (validationMessage) {
                    alert(validationMessage);
                    clearAttachment();
                    return;
                }

                selectedAttachment = file;
                renderAttachmentPreview(file);
            });

            form?.addEventListener('submit', async function (event) {
                event.preventDefault();
                if (!activeUserId || !messageInput || !sendButton) return;

                const text = messageInput.value.trim();
                if ((!text && !selectedAttachment) || sendButton.disabled) return;

                const formData = new FormData(form);
                formData.set('recipient_id', String(activeUserId));

                const tempId = `temp-${Date.now()}`;
                const tempAttachment = selectedAttachment
                    ? {
                        kind: isImageAttachment(selectedAttachment.name) ? 'image' : 'file',
                        name: selectedAttachment.name,
                        size_label: formatFileSize(selectedAttachment.size),
                        url: selectedAttachmentPreviewUrl || '',
                        download_url: '#',
                    }
                    : null;

                messageInput.value = '';
                messageInput.style.height = 'auto';
                sendButton.disabled = true;
                clearAttachment(false);
                sendTyping(false);
                removeNoMessages();
                messageStack?.insertAdjacentHTML('beforeend', renderMessage({
                    id: tempId,
                    body: text,
                    sender_name: 'You',
                    sender_id: config.currentUserId,
                    created_time: 'Just now',
                    attachment: tempAttachment,
                    is_outgoing: true,
                    read_at: null,
                }, true));
                scrollToBottom();

                try {
                    const response = await fetch(config.storeUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    const data = await response.json();
                    if (!response.ok || !data.ok) {
                        throw new Error(firstError(data) || 'Message failed to send.');
                    }

                    messageStack?.querySelector(`[data-message-id="${tempId}"]`)?.remove();
                    appendMessage(data.message);
                    updateThreadPreview(activeUserId, data.message, true);
                    updateMessageBadges(data.unread_messages_count);
                    revokeSelectedAttachmentUrl();
                } catch (error) {
                    const statusNode = messageStack?.querySelector(`[data-live-message-status="${tempId}"]`);
                    if (statusNode) {
                        statusNode.hidden = false;
                        statusNode.textContent = error.message || 'Failed to send';
                        statusNode.style.color = '#ef4444';
                    }
                } finally {
                    sendButton.disabled = false;
                    messageInput.focus();
                }
            });

            function bindThreads() {
                getThreads().forEach(function (trigger) {
                    if (trigger.dataset.messengerBound === '1') return;
                    trigger.dataset.messengerBound = '1';
                    trigger.addEventListener('click', function (event) {
                        event.preventDefault();
                        openConversation(trigger, true);
                    });
                });
            }

            function openConversation(trigger, updateUrl) {
                const userId = Number(trigger.dataset.conversationId || 0);
                if (!userId) return;

                activeUserId = userId;
                root.dataset.activeUserId = String(userId);
                lastMessageId = 0;
                renderedMessageIds = new Set();

                showChatChrome(trigger);
                getThreads().forEach(function (item) {
                    item.classList.toggle('is-active', item === trigger);
                });
                setThreadUnread(trigger, 0);
                setLoadingConversation();

                if (recipientInput) {
                    recipientInput.value = String(userId);
                }

                if (updateUrl && trigger.href) {
                    window.history.replaceState({}, '', trigger.href);
                }

                syncConversation(true);
                restartConversationSync();
                window.setTimeout(function () {
                    messageInput?.focus();
                }, 80);
            }

            function showChatChrome(trigger) {
                chatPanel?.classList.remove('is-empty');
                grid?.classList.add('is-chat-open');
                if (emptyChat) emptyChat.hidden = true;
                if (chatHeader) chatHeader.hidden = false;
                if (chatBody) chatBody.hidden = false;
                if (composer) composer.hidden = false;

                const online = trigger.dataset.conversationOnline === '1';
                if (title) title.textContent = trigger.dataset.conversationName || 'Conversation';
                if (subtitle) subtitle.textContent = trigger.dataset.conversationSubtitle || 'Secure SJBAC messaging';
                if (initials) initials.textContent = trigger.dataset.conversationInitials || initialsFromName(trigger.dataset.conversationName);
                updateHeaderPresence(online);
            }

            function setLoadingConversation() {
                if (!messageStack) return;
                messageStack.innerHTML = `
                    <div class="messenger-no-messages" data-no-messages>
                        <i class="fas fa-circle-notch fa-spin"></i>
                        <strong>Loading conversation...</strong>
                    </div>
                `;
                setTypingVisible(false, '');
            }

            function restartConversationSync() {
                window.clearInterval(syncTimer);
                syncTimer = window.setInterval(function () {
                    syncConversation(false);
                }, 2200);
            }

            async function syncConversation(reset) {
                if (!activeUserId || !config.syncUrl) return;

                const url = new URL(config.syncUrl, window.location.origin);
                url.searchParams.set('user', String(activeUserId));
                url.searchParams.set('after_id', reset ? '0' : String(lastMessageId));

                try {
                    const response = await fetch(url.toString(), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) return;

                    const data = await response.json();
                    if (!data.ok) return;

                    if (data.counterpart) {
                        updateHeaderFromCounterpart(data.counterpart);
                    }

                    updateTyping(data.typing);
                    updateReadStates(data.read_states || []);
                    updateMessageBadges(data.unread_messages_count);

                    if (reset && messageStack) {
                        messageStack.innerHTML = '';
                        renderedMessageIds = new Set();
                    }

                    (data.messages || []).forEach(appendMessage);
                    lastMessageId = Math.max(lastMessageId, Number(data.latest_message_id || 0));

                    if (renderedMessageIds.size === 0) {
                        renderNoMessages();
                    }
                } catch (error) {
                    if (reset && messageStack) {
                        messageStack.innerHTML = `
                            <div class="messenger-no-messages" data-no-messages>
                                <i class="fas fa-triangle-exclamation"></i>
                                <strong>Unable to load messages</strong>
                            </div>
                        `;
                    }
                }
            }

            async function pollStatus() {
                if (!config.statusUrl) return;

                try {
                    const response = await fetch(config.statusUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) return;

                    const data = await response.json();
                    Object.entries(data.statuses || {}).forEach(function ([userId, isOnline]) {
                        updateThreadPresence(userId, Boolean(isOnline));
                    });

                    (data.threads || []).forEach(updateThreadFromPayload);
                    sortThreads();
                } catch (error) {
                    // Best-effort polling; the page remains usable if it misses a beat.
                }
            }

            function appendMessage(message) {
                if (!message || !messageStack || renderedMessageIds.has(String(message.id))) return;

                removeNoMessages();
                const outgoing = Boolean(message.is_outgoing) || Number(message.sender_id) === config.currentUserId;
                if (outgoing) {
                    hideOutgoingStates();
                }

                messageStack.insertAdjacentHTML('beforeend', renderMessage(message));
                renderedMessageIds.add(String(message.id));
                lastMessageId = Math.max(lastMessageId, Number(message.id) || 0);
                refreshLatestOutgoingState();
                scrollToBottom();
            }

            function renderMessage(message, isTemporary = false) {
                const outgoing = Boolean(message.is_outgoing) || Number(message.sender_id) === config.currentUserId;
                const statusText = isTemporary
                    ? 'Sending...'
                    : (outgoing ? (message.read_at ? 'Seen' : 'Sent') : '');

                return `
                    <article class="messenger-message ${outgoing ? 'is-outgoing' : 'is-incoming'}" data-message-id="${escapeAttribute(String(message.id))}">
                        <span class="messenger-message-author">${outgoing ? 'You' : escapeHtml(message.sender_name || 'User')}</span>
                        <div class="messenger-message-bubble">
                            ${message.body ? `<p class="messenger-message-text">${escapeHtml(message.body)}</p>` : ''}
                            ${renderAttachment(message.attachment)}
                        </div>
                        <div class="messenger-message-meta">${escapeHtml(message.created_time || message.created_at || '')}</div>
                        ${outgoing ? `<div class="messenger-message-state" data-live-message-status="${escapeAttribute(String(message.id))}">${escapeHtml(statusText)}</div>` : ''}
                    </article>
                `;
            }

            function renderAttachment(attachment) {
                if (!attachment) return '';

                if (attachment.kind === 'image') {
                    return `
                        <div class="messenger-attachment">
                            <a href="${escapeAttribute(attachment.url || '#')}" target="_blank" rel="noopener">
                                <img src="${escapeAttribute(attachment.url || '')}" alt="${escapeAttribute(attachment.name || 'Attachment')}" class="messenger-attachment-image">
                            </a>
                        </div>
                    `;
                }

                return `
                    <div class="messenger-attachment">
                        <a href="${escapeAttribute(attachment.download_url || attachment.url || '#')}" class="messenger-file-link" target="_blank" rel="noopener">
                            <span class="messenger-file-icon"><i class="fas fa-file-lines"></i></span>
                            <span class="messenger-file-info">
                                <span class="messenger-file-name">${escapeHtml(attachment.name || 'Attachment')}</span>
                                <span class="messenger-file-size">${escapeHtml(attachment.size_label || '')}</span>
                            </span>
                            <i class="fas fa-download"></i>
                        </a>
                    </div>
                `;
            }

            function updateThreadFromPayload(thread) {
                if (!thread || !thread.user_id) return;

                const item = findThread(thread.user_id);
                if (!item) return;

                const preview = item.querySelector('[data-thread-preview]');
                const time = item.querySelector('[data-thread-time]');
                if (preview) preview.textContent = thread.preview || 'No messages yet. Send your first message.';
                if (time) time.textContent = thread.time || '';
                item.dataset.threadSort = String(thread.sort_timestamp || 0);
                setThreadUnread(item, Number(thread.user_id) === Number(activeUserId) ? 0 : Number(thread.unread_count || 0));
            }

            function updateThreadPreview(userId, message, outgoing) {
                const item = findThread(userId);
                if (!item) return;

                const preview = item.querySelector('[data-thread-preview]');
                const time = item.querySelector('[data-thread-time]');
                const text = message.body || (message.attachment ? `Attachment: ${message.attachment.name || 'File'}` : 'Message sent');
                if (preview) preview.textContent = outgoing ? `You: ${text}` : text;
                if (time) time.textContent = 'now';
                item.dataset.threadSort = String(Math.floor(Date.now() / 1000));
                sortThreads();
            }

            function updateThreadPresence(userId, isOnline) {
                const item = findThread(userId);
                if (item) {
                    item.dataset.conversationOnline = isOnline ? '1' : '0';
                }

                root.querySelectorAll(`.status-dot[data-user-id="${userId}"], .messenger-presence-dot[data-user-id="${userId}"]`).forEach(function (dot) {
                    dot.classList.toggle('is-online', isOnline);
                    dot.classList.toggle('is-offline', !isOnline);
                });

                if (Number(userId) === Number(activeUserId)) {
                    updateHeaderPresence(isOnline);
                }
            }

            function setThreadUnread(item, count) {
                const badge = item?.querySelector('[data-thread-unread-badge]');
                if (!badge) return;

                if (count > 0) {
                    badge.textContent = String(count);
                    badge.hidden = false;
                    badge.style.display = 'inline-flex';
                } else {
                    badge.textContent = '';
                    badge.hidden = true;
                    badge.style.display = 'none';
                }
            }

            function sortThreads() {
                if (!list) return;
                getThreads()
                    .sort(function (a, b) {
                        return Number(b.dataset.threadSort || 0) - Number(a.dataset.threadSort || 0);
                    })
                    .forEach(function (item) {
                        list.appendChild(item);
                    });
            }

            function updateHeaderFromCounterpart(counterpart) {
                const roleLabel = counterpart.role_label || titleCase(counterpart.role || 'User');
                if (title) title.textContent = counterpart.name || 'Conversation';
                if (subtitle) subtitle.textContent = `${roleLabel} | ${counterpart.email || ''}`;
                if (initials) initials.textContent = counterpart.initials || initialsFromName(counterpart.name);
                updateHeaderPresence(Boolean(counterpart.is_online));
            }

            function updateHeaderPresence(isOnline) {
                chatPresence?.classList.toggle('is-online', isOnline);
                chatPresence?.classList.toggle('is-offline', !isOnline);
                chatStatus?.classList.toggle('is-online', isOnline);
                if (chatStatusText) chatStatusText.textContent = isOnline ? 'Active now' : 'Offline';
            }

            function updateReadStates(readStates) {
                readStates.forEach(function (state) {
                    const node = messageStack?.querySelector(`[data-live-message-status="${state.id}"]`);
                    if (node) {
                        node.textContent = state.read_at ? 'Seen' : 'Sent';
                    }
                });

                refreshLatestOutgoingState();
            }

            function refreshLatestOutgoingState() {
                const states = Array.from(messageStack?.querySelectorAll('.messenger-message.is-outgoing [data-live-message-status]') || [])
                    .filter(function (node) {
                        return !String(node.dataset.liveMessageStatus || '').startsWith('temp-');
                    });
                if (!states.length) return;

                const latest = states.reduce(function (carry, node) {
                    return Number(node.dataset.liveMessageStatus || 0) > Number(carry.dataset.liveMessageStatus || 0) ? node : carry;
                }, states[0]);

                states.forEach(function (node) {
                    node.hidden = node !== latest;
                });
                latest.hidden = false;
            }

            function hideOutgoingStates() {
                messageStack?.querySelectorAll('.messenger-message.is-outgoing [data-live-message-status]').forEach(function (node) {
                    node.hidden = true;
                });
            }

            function renderNoMessages() {
                if (!messageStack) return;
                messageStack.innerHTML = `
                    <div class="messenger-no-messages" data-no-messages>
                        <i class="fas fa-paper-plane"></i>
                        <strong>No messages yet. Send your first message.</strong>
                    </div>
                `;
            }

            function removeNoMessages() {
                messageStack?.querySelector('[data-no-messages]')?.remove();
            }

            function updateTyping(payload) {
                setTypingVisible(Boolean(payload?.is_typing), payload?.label || '');
            }

            function setTypingVisible(isVisible, label) {
                if (!typing) return;
                typing.hidden = !isVisible;
                if (typingText) typingText.textContent = label || 'Typing...';
            }

            function notifyTyping() {
                if (!activeUserId) return;

                const now = Date.now();
                if (!typingActive || now - lastTypingSentAt > 1500) {
                    sendTyping(true);
                }

                window.clearTimeout(typingStopTimer);
                typingStopTimer = window.setTimeout(function () {
                    sendTyping(false);
                }, 1800);
            }

            async function sendTyping(isTyping) {
                if (!activeUserId || !config.typingUrl) return;

                typingActive = isTyping;
                lastTypingSentAt = Date.now();

                try {
                    await fetch(config.typingUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            recipient_id: activeUserId,
                            is_typing: isTyping,
                        }),
                    });
                } catch (error) {
                    // Typing state is best-effort.
                }
            }

            function updateMessageBadges(count) {
                const numericCount = Number(count);
                if (!Number.isFinite(numericCount)) return;

                if (typeof window.BacUpdateMessageBadges === 'function') {
                    window.BacUpdateMessageBadges(numericCount);
                    return;
                }

                document.dispatchEvent(new CustomEvent('bac:message-count-updated', {
                    detail: { count: numericCount },
                }));

                document.querySelectorAll('[data-message-badge]').forEach(function (badge) {
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
            }

            function renderAttachmentPreview(file) {
                if (!attachmentPreview) return;

                revokeSelectedAttachmentUrl();
                selectedAttachmentPreviewUrl = isImageAttachment(file.name) ? URL.createObjectURL(file) : null;
                attachmentPreview.hidden = false;
                attachmentPreview.innerHTML = `
                    ${selectedAttachmentPreviewUrl
                        ? `<img src="${selectedAttachmentPreviewUrl}" alt="" class="messenger-attachment-thumb">`
                        : `<span class="messenger-attachment-icon"><i class="fas fa-file-lines"></i></span>`}
                    <span class="messenger-attachment-info">
                        <span class="messenger-attachment-name">${escapeHtml(file.name)}</span>
                        <span class="messenger-attachment-size">${formatFileSize(file.size)}</span>
                    </span>
                    <button type="button" class="messenger-remove-attachment" aria-label="Remove attachment">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                attachmentPreview.querySelector('.messenger-remove-attachment')?.addEventListener('click', function () {
                    clearAttachment();
                });
            }

            function clearAttachment(revokePreview = true) {
                selectedAttachment = null;
                if (fileInput) fileInput.value = '';
                if (attachmentPreview) {
                    attachmentPreview.hidden = true;
                    attachmentPreview.innerHTML = '';
                }
                if (revokePreview) {
                    revokeSelectedAttachmentUrl();
                }
            }

            function revokeSelectedAttachmentUrl() {
                if (selectedAttachmentPreviewUrl) {
                    URL.revokeObjectURL(selectedAttachmentPreviewUrl);
                    selectedAttachmentPreviewUrl = null;
                }
            }

            function validateAttachment(file) {
                const extension = getFileExtension(file.name);
                if (!allowedAttachmentExtensions.includes(extension)) {
                    return 'Only JPG, JPEG, PNG, WEBP, PDF, DOC, DOCX, XLS, and XLSX attachments are allowed.';
                }

                if (file.size > maxAttachmentSize) {
                    return 'Attachments must be 10MB or smaller.';
                }

                return '';
            }

            function getThreads() {
                return Array.from(root.querySelectorAll('[data-messenger-thread]'));
            }

            function findThread(userId) {
                return root.querySelector(`[data-messenger-thread][data-conversation-id="${Number(userId)}"]`);
            }

            function scrollToBottom() {
                if (!chatBody) return;
                requestAnimationFrame(function () {
                    chatBody.scrollTop = chatBody.scrollHeight;
                });
            }

            function isImageAttachment(name) {
                return imageAttachmentExtensions.includes(getFileExtension(name));
            }

            function getFileExtension(name) {
                return (String(name || '').split('.').pop() || '').toLowerCase();
            }

            function formatFileSize(bytes) {
                if (!bytes) return '';
                if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
                return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
            }

            function firstError(data) {
                if (data?.errors) {
                    return Object.values(data.errors).flat()[0];
                }

                return data?.message || '';
            }

            function initialsFromName(name) {
                return String(name || 'U')
                    .trim()
                    .split(/\s+/)
                    .slice(0, 2)
                    .map(function (part) { return part.charAt(0).toUpperCase(); })
                    .join('') || 'U';
            }

            function titleCase(text) {
                return String(text || '')
                    .replace(/[_-]+/g, ' ')
                    .replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text == null ? '' : String(text);
                return div.innerHTML;
            }

            function escapeAttribute(text) {
                return escapeHtml(text).replace(/"/g, '&quot;');
            }
        }
    </script>
@endonce
