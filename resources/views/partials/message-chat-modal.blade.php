@php
    $initialChatUserId = (int) ($initialChatUserId ?? 0);
@endphp

<style>
    .messages-page .messages-layout,
    .bidder-messages-page .bidder-messages-layout {
        grid-template-columns: minmax(280px, 760px);
        height: auto;
        min-height: 0;
    }

    .messages-page .conversation-panel,
    .bidder-messages-page .conversation-panel {
        display: none !important;
    }

    .messages-page .inbox-panel,
    .bidder-messages-page .inbox-panel {
        min-height: min(720px, calc(100vh - 220px));
    }

    body.message-modal-open {
        overflow: hidden;
    }

    .message-chat-modal {
        position: fixed;
        inset: 0;
        z-index: 12000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: rgba(2, 6, 23, 0.78);
        backdrop-filter: blur(10px);
    }

    .message-chat-modal.is-open {
        display: flex;
    }

    .message-chat-dialog {
        width: min(980px, 100%);
        height: min(760px, calc(100vh - 48px));
        display: grid;
        grid-template-rows: auto minmax(0, 1fr) auto;
        overflow: hidden;
        border-radius: 22px;
        background: #07090d;
        border: 1px solid rgba(255, 255, 255, 0.09);
        box-shadow: 0 30px 80px rgba(0, 0, 0, 0.48);
        color: #f8fafc;
    }

    .message-chat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 20px;
        background: linear-gradient(180deg, #11151c 0%, #0b0e13 100%);
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .message-chat-person {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .message-chat-avatar {
        position: relative;
        width: 46px;
        height: 46px;
        flex: 0 0 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: #1f2937;
        color: #ffffff;
        font-weight: 800;
        border: 1px solid rgba(255, 255, 255, 0.12);
    }

    .message-chat-presence {
        position: absolute;
        right: 1px;
        bottom: 1px;
        width: 12px;
        height: 12px;
        border-radius: 999px;
        border: 2px solid #07090d;
        background: #64748b;
    }

    .message-chat-presence.is-online {
        background: #22c55e;
    }

    .staff-message-pill.is-presence {
        gap: 5px;
        background: #f1f5f9;
        color: #64748b;
    }

    .staff-message-pill.is-presence::before {
        content: "";
        width: 6px;
        height: 6px;
        border-radius: 999px;
        background: currentColor;
    }

    .staff-message-pill.is-presence.is-online {
        background: #dcfce7;
        color: #15803d;
    }

    .staff-message-pill.is-presence.is-offline {
        background: #f1f5f9;
        color: #64748b;
    }

    .message-chat-title-wrap {
        min-width: 0;
    }

    .message-chat-title {
        margin: 0;
        color: #ffffff;
        font-size: 17px;
        font-weight: 800;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .message-chat-subtitle,
    .message-chat-status {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 4px;
        color: #a7b0c0;
        font-size: 12px;
        line-height: 1.4;
    }

    .message-chat-status-dot {
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: #64748b;
    }

    .message-chat-status.is-online .message-chat-status-dot {
        background: #22c55e;
    }

    .message-chat-close {
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.07);
        color: #dbe4f0;
        cursor: pointer;
        transition: background 0.18s ease, color 0.18s ease;
    }

    .message-chat-close:hover {
        background: rgba(249, 115, 22, 0.16);
        color: #ffffff;
    }

    .message-chat-body {
        min-height: 0;
        overflow-y: auto;
        scroll-behavior: smooth;
        padding: 22px;
        background:
            radial-gradient(circle at top left, rgba(249, 115, 22, 0.08), transparent 32%),
            #07090d;
    }

    .message-chat-body::-webkit-scrollbar {
        width: 8px;
    }

    .message-chat-body::-webkit-scrollbar-thumb {
        background: #293241;
        border-radius: 999px;
    }

    .message-chat-empty,
    .message-chat-loading {
        min-height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 10px;
        text-align: center;
        color: #94a3b8;
    }

    .message-chat-loading i,
    .message-chat-empty i {
        color: #f97316;
        font-size: 30px;
    }

    .live-message {
        width: 100%;
        display: flex;
        flex-direction: column;
        margin-bottom: 14px;
        animation: liveMessageIn 0.18s ease;
    }

    .live-message.is-outgoing {
        align-items: flex-end;
    }

    .live-message.is-incoming {
        align-items: flex-start;
    }

    .live-message-author {
        margin: 0 4px 5px;
        color: #93a0b2;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .live-message-bubble {
        max-width: min(72%, 620px);
        padding: 11px 14px;
        border-radius: 18px;
        color: #f8fafc;
        background: #171b23;
        border: 1px solid rgba(255, 255, 255, 0.07);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18);
    }

    .live-message.is-outgoing .live-message-bubble {
        background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%);
        color: #ffffff;
        border-color: rgba(251, 146, 60, 0.42);
        border-bottom-right-radius: 6px;
    }

    .live-message.is-incoming .live-message-bubble {
        background: #161a22;
        color: #e5edf7;
        border-bottom-left-radius: 6px;
    }

    .live-message-text {
        margin: 0;
        font-size: 14px;
        line-height: 1.6;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    .live-message-meta,
    .live-message-status {
        margin: 5px 5px 0;
        color: #8793a5;
        font-size: 11px;
        line-height: 1.4;
    }

    .live-message.is-outgoing .live-message-status {
        text-align: right;
    }

    .live-message-attachment {
        margin-top: 9px;
    }

    .live-message-attachment-image {
        display: block;
        width: min(260px, 100%);
        max-height: 240px;
        border-radius: 14px;
        object-fit: cover;
        border: 1px solid rgba(255, 255, 255, 0.18);
    }

    .live-message-file {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: min(280px, 100%);
        padding: 10px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.08);
        color: #ffffff;
        text-decoration: none;
    }

    .live-message-file-icon,
    .live-attachment-icon {
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: rgba(249, 115, 22, 0.18);
        color: #fb923c;
    }

    .live-message-file-info,
    .live-attachment-info {
        min-width: 0;
        flex: 1;
    }

    .live-message-file-name,
    .live-attachment-name {
        display: block;
        color: #ffffff;
        font-size: 12px;
        font-weight: 800;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .live-message-file-size,
    .live-attachment-size {
        display: block;
        margin-top: 2px;
        color: #a7b0c0;
        font-size: 11px;
    }

    .message-chat-typing {
        display: flex;
        align-items: center;
        gap: 8px;
        min-height: 28px;
        padding: 0 22px 8px;
        color: #cbd5e1;
        font-size: 12px;
        background: #07090d;
    }

    .message-chat-typing.is-hidden {
        visibility: hidden;
    }

    .typing-dots {
        display: inline-flex;
        gap: 3px;
    }

    .typing-dots span {
        width: 5px;
        height: 5px;
        border-radius: 999px;
        background: #fb923c;
        animation: typingPulse 1s infinite ease-in-out;
    }

    .typing-dots span:nth-child(2) {
        animation-delay: 0.12s;
    }

    .typing-dots span:nth-child(3) {
        animation-delay: 0.24s;
    }

    .message-chat-composer {
        padding: 12px 16px 16px;
        background: #0b0e13;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
    }

    .live-attachment-preview {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
        padding: 9px 10px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.07);
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .live-attachment-preview.is-hidden {
        display: none;
    }

    .live-attachment-image {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        object-fit: cover;
        border: 1px solid rgba(255, 255, 255, 0.14);
    }

    .live-attachment-remove {
        width: 30px;
        height: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.08);
        color: #cbd5e1;
        cursor: pointer;
    }

    .live-attachment-remove:hover {
        color: #ffffff;
        background: rgba(239, 68, 68, 0.22);
    }

    .message-chat-form {
        display: flex;
        align-items: flex-end;
        gap: 10px;
    }

    .message-chat-attach,
    .message-chat-send {
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        border: 0;
        cursor: pointer;
    }

    .message-chat-attach {
        background: rgba(255, 255, 255, 0.08);
        color: #dbe4f0;
    }

    .message-chat-attach:hover {
        color: #ffffff;
        background: rgba(249, 115, 22, 0.18);
    }

    .message-chat-file {
        display: none;
    }

    .message-chat-input {
        min-height: 42px;
        max-height: 130px;
        flex: 1;
        resize: none;
        padding: 11px 14px;
        border-radius: 18px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        outline: none;
        background: #12161e;
        color: #f8fafc;
        font: inherit;
        font-size: 14px;
        line-height: 1.45;
    }

    .message-chat-input::placeholder {
        color: #788397;
    }

    .message-chat-input:focus {
        border-color: rgba(249, 115, 22, 0.6);
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.12);
    }

    .message-chat-send {
        background: #f97316;
        color: #ffffff;
        box-shadow: 0 12px 24px rgba(249, 115, 22, 0.22);
    }

    .message-chat-send:disabled {
        background: #374151;
        color: #94a3b8;
        cursor: not-allowed;
        box-shadow: none;
    }

    @keyframes liveMessageIn {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes typingPulse {
        0%, 80%, 100% { opacity: 0.35; transform: translateY(0); }
        40% { opacity: 1; transform: translateY(-2px); }
    }

    @media (max-width: 900px) {
        .messages-page .messages-layout,
        .bidder-messages-page .bidder-messages-layout {
            grid-template-columns: 1fr;
        }

        .message-chat-modal {
            padding: 14px;
        }

        .message-chat-dialog {
            height: min(760px, calc(100vh - 28px));
            border-radius: 18px;
        }

        .live-message-bubble {
            max-width: 86%;
        }
    }

    @media (max-width: 560px) {
        .message-chat-modal {
            padding: 0;
        }

        .message-chat-dialog {
            width: 100%;
            height: 100vh;
            border-radius: 0;
            border-left: 0;
            border-right: 0;
        }

        .message-chat-header {
            padding: 14px;
        }

        .message-chat-body {
            padding: 16px 12px;
        }

        .live-message-bubble {
            max-width: 92%;
        }

        .message-chat-form {
            gap: 8px;
        }

        .message-chat-attach,
        .message-chat-send {
            width: 38px;
            height: 38px;
            flex-basis: 38px;
        }
    }
</style>

<div
    id="messageChatModal"
    class="message-chat-modal"
    aria-hidden="true"
    data-current-user-id="{{ auth()->id() }}"
    data-sync-url="{{ $messageSyncRoute }}"
    data-typing-url="{{ $messageTypingRoute }}"
    data-store-url="{{ $messageStoreRoute }}"
    data-status-url="{{ $messageStatusRoute }}"
    data-initial-user-id="{{ $initialChatUserId }}"
>
    <section class="message-chat-dialog" role="dialog" aria-modal="true" aria-labelledby="messageChatTitle">
        <header class="message-chat-header">
            <div class="message-chat-person">
                <div class="message-chat-avatar">
                    <span id="messageChatInitials">U</span>
                    <span id="messageChatPresence" class="message-chat-presence"></span>
                </div>
                <div class="message-chat-title-wrap">
                    <h3 id="messageChatTitle" class="message-chat-title">Conversation</h3>
                    <div id="messageChatSubtitle" class="message-chat-subtitle">{{ $messageChatSubtitle ?? 'Secure BAC Office messaging' }}</div>
                    <div id="messageChatStatus" class="message-chat-status">
                        <span class="message-chat-status-dot"></span>
                        <span id="messageChatStatusText">Offline</span>
                    </div>
                </div>
            </div>

            <button type="button" id="messageChatClose" class="message-chat-close" aria-label="Close conversation">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div id="messageChatBody" class="message-chat-body">
            <div class="message-chat-empty">
                <i class="fas fa-comments"></i>
                <strong>{{ $messageChatEmptyTitle ?? 'Select a conversation' }}</strong>
                <span>{{ $messageChatEmptyText ?? 'Choose a contact from the list to start messaging.' }}</span>
            </div>
        </div>

        <div id="messageChatTyping" class="message-chat-typing is-hidden">
            <span class="typing-dots" aria-hidden="true"><span></span><span></span><span></span></span>
            <span id="messageChatTypingText">Typing...</span>
        </div>

        <footer class="message-chat-composer">
            <div id="liveAttachmentPreview" class="live-attachment-preview is-hidden"></div>
            <form id="liveMessageForm" class="message-chat-form" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="recipient_id" id="liveRecipientId" value="">
                <label for="liveMessageAttachment" class="message-chat-attach" title="Attach photo or document">
                    <i class="fas fa-paperclip"></i>
                </label>
                <input
                    type="file"
                    id="liveMessageAttachment"
                    name="attachment"
                    class="message-chat-file"
                    accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,image/jpeg,image/png,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                >
                <textarea id="liveMessageInput" name="body" class="message-chat-input" rows="1" placeholder="Type a message..."></textarea>
                <button type="submit" id="liveMessageSend" class="message-chat-send" aria-label="Send message">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </footer>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('messageChatModal');
        if (!modal) return;

        const config = {
            currentUserId: Number(modal.dataset.currentUserId || 0),
            syncUrl: modal.dataset.syncUrl,
            typingUrl: modal.dataset.typingUrl,
            storeUrl: modal.dataset.storeUrl,
            statusUrl: modal.dataset.statusUrl,
            initialUserId: Number(modal.dataset.initialUserId || 0),
        };

        const triggers = Array.from(document.querySelectorAll('[data-live-chat-trigger]'));
        const closeButton = document.getElementById('messageChatClose');
        const body = document.getElementById('messageChatBody');
        const form = document.getElementById('liveMessageForm');
        const recipientInput = document.getElementById('liveRecipientId');
        const messageInput = document.getElementById('liveMessageInput');
        const sendButton = document.getElementById('liveMessageSend');
        const fileInput = document.getElementById('liveMessageAttachment');
        const attachmentPreview = document.getElementById('liveAttachmentPreview');
        const title = document.getElementById('messageChatTitle');
        const subtitle = document.getElementById('messageChatSubtitle');
        const initials = document.getElementById('messageChatInitials');
        const presence = document.getElementById('messageChatPresence');
        const status = document.getElementById('messageChatStatus');
        const statusText = document.getElementById('messageChatStatusText');
        const typing = document.getElementById('messageChatTyping');
        const typingText = document.getElementById('messageChatTypingText');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || form?.querySelector('input[name="_token"]')?.value
            || '';
        const allowedAttachmentExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
        const imageAttachmentExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        const maxAttachmentSize = 10 * 1024 * 1024;

        let activeUserId = 0;
        let lastMessageId = 0;
        let renderedMessageIds = new Set();
        let syncTimer = null;
        let statusTimer = null;
        let listStatusTimer = null;
        let typingStopTimer = null;
        let lastTypingSentAt = 0;
        let typingActive = false;
        let selectedAttachment = null;
        let selectedAttachmentPreviewUrl = null;

        triggers.forEach((trigger) => {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                openConversation(trigger, true);
            });
        });

        closeButton?.addEventListener('click', closeConversation);

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeConversation();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                closeConversation();
            }
        });

        if (config.initialUserId > 0) {
            const initialTrigger = triggers.find((trigger) => Number(trigger.dataset.conversationId) === config.initialUserId);
            if (initialTrigger) {
                openConversation(initialTrigger, false);
            }
        }

        pollStatus();
        listStatusTimer = window.setInterval(pollStatus, 5000);

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
            if (!activeUserId || !body || !messageInput || !sendButton) return;

            const text = messageInput.value.trim();
            if ((!text && !selectedAttachment) || sendButton.disabled) return;

            const formData = new FormData(form);
            formData.set('recipient_id', String(activeUserId));

            const tempId = `temp-${Date.now()}`;
            const tempAttachment = selectedAttachment
                ? {
                    kind: isImageAttachment(selectedAttachment) ? 'image' : 'file',
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

            body.querySelector('.message-chat-empty, .message-chat-loading')?.remove();
            body.insertAdjacentHTML('beforeend', renderMessage({
                id: tempId,
                body: text,
                sender_name: 'You',
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

                body.querySelector(`[data-message-id="${tempId}"]`)?.remove();
                appendMessage(data.message);
                updateThreadPreview(activeUserId, data.message);
                revokeSelectedAttachmentUrl();
            } catch (error) {
                const statusNode = body.querySelector(`[data-live-message-status="${tempId}"]`);
                if (statusNode) {
                    statusNode.textContent = error.message || 'Failed to send';
                    statusNode.style.color = '#fca5a5';
                }
            } finally {
                sendButton.disabled = false;
                messageInput.focus();
            }
        });

        function openConversation(trigger, updateUrl) {
            activeUserId = Number(trigger.dataset.conversationId || 0);
            if (!activeUserId || !body || !recipientInput) return;

            recipientInput.value = String(activeUserId);
            lastMessageId = 0;
            renderedMessageIds = new Set();
            clearAttachment();
            setTypingVisible(false, '');

            title.textContent = trigger.dataset.conversationName || 'Conversation';
            subtitle.textContent = trigger.dataset.conversationSubtitle || 'Secure BAC Office messaging';
            initials.textContent = trigger.dataset.conversationInitials || initialsFromName(title.textContent);
            updateHeaderPresence(trigger.dataset.conversationOnline === '1');

            triggers.forEach((item) => item.classList.toggle('is-active', item === trigger));
            trigger.querySelector('.unread-count-badge')?.remove();

            body.innerHTML = `
                <div class="message-chat-loading">
                    <i class="fas fa-circle-notch fa-spin"></i>
                    <span>Loading conversation...</span>
                </div>
            `;

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('message-modal-open');
            messageInput?.focus();

            if (updateUrl && trigger.href) {
                window.history.replaceState({}, '', trigger.href);
            }

            syncConversation(true);
            pollStatus();
            restartTimers();
        }

        function closeConversation() {
            sendTyping(false);
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('message-modal-open');
            window.clearInterval(syncTimer);
            window.clearInterval(statusTimer);
            window.clearTimeout(typingStopTimer);
            syncTimer = null;
            statusTimer = null;
        }

        function restartTimers() {
            window.clearInterval(syncTimer);
            window.clearInterval(statusTimer);
            syncTimer = window.setInterval(() => syncConversation(false), 2200);
            statusTimer = window.setInterval(pollStatus, 10000);
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

                updateHeaderPresence(Boolean(data.counterpart?.is_online));
                updateTyping(data.typing);
                updateReadStates(data.read_states || []);

                if (reset) {
                    body.innerHTML = '';
                    renderedMessageIds = new Set();
                }

                (data.messages || []).forEach(appendMessage);
                lastMessageId = Math.max(lastMessageId, Number(data.latest_message_id || 0));

                if (renderedMessageIds.size === 0) {
                    renderEmptyConversation();
                }
            } catch (error) {
                if (reset) {
                    body.innerHTML = `
                        <div class="message-chat-empty">
                            <i class="fas fa-triangle-exclamation"></i>
                            <strong>Unable to load messages</strong>
                            <span>Please try opening this conversation again.</span>
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
                Object.entries(data.statuses || {}).forEach(([userId, isOnline]) => {
                    const trigger = triggers.find((item) => Number(item.dataset.conversationId) === Number(userId));
                    if (trigger) {
                        trigger.dataset.conversationOnline = isOnline ? '1' : '0';
                        trigger.dataset.conversationStatusText = isOnline ? 'Online' : 'Offline';
                    }

                    const dot = document.querySelector(`.status-dot[data-user-id="${userId}"]`);
                    if (dot) {
                        dot.classList.toggle('is-online', Boolean(isOnline));
                        dot.classList.toggle('is-offline', !isOnline);
                        dot.title = isOnline ? 'Online' : 'Offline';
                        dot.setAttribute('aria-label', isOnline ? 'Online' : 'Offline');
                    }

                    document.querySelectorAll(`[data-presence-label][data-user-id="${userId}"]`).forEach((label) => {
                        label.textContent = isOnline ? 'Online' : 'Offline';
                        label.classList.toggle('is-online', Boolean(isOnline));
                        label.classList.toggle('is-offline', !isOnline);
                    }

                    if (Number(userId) === Number(activeUserId)) {
                        updateHeaderPresence(Boolean(isOnline));
                    }
                });
            } catch (error) {
                // Keep the current visual state if polling hiccups.
            }
        }

        function appendMessage(message) {
            if (!message || renderedMessageIds.has(String(message.id)) || !body) return;

            body.querySelector('.message-chat-empty, .message-chat-loading')?.remove();
            body.insertAdjacentHTML('beforeend', renderMessage(message));
            renderedMessageIds.add(String(message.id));
            lastMessageId = Math.max(lastMessageId, Number(message.id) || 0);
            scrollToBottom();
        }

        function renderMessage(message, isTemporary = false) {
            const outgoing = Boolean(message.is_outgoing) || Number(message.sender_id) === config.currentUserId;
            const statusText = isTemporary
                ? 'Sending...'
                : (outgoing ? (message.read_at ? 'Seen' : 'Sent') : '');

            return `
                <article class="live-message ${outgoing ? 'is-outgoing' : 'is-incoming'}" data-message-id="${escapeAttribute(String(message.id))}">
                    <span class="live-message-author">${outgoing ? 'You' : escapeHtml(message.sender_name || 'BAC')}</span>
                    <div class="live-message-bubble">
                        ${message.body ? `<p class="live-message-text">${escapeHtml(message.body)}</p>` : ''}
                        ${renderChatAttachment(message.attachment)}
                    </div>
                    <div class="live-message-meta">${escapeHtml(message.created_time || message.created_at || '')}</div>
                    ${outgoing ? `<div class="live-message-status" data-live-message-status="${escapeAttribute(String(message.id))}">${statusText}</div>` : ''}
                </article>
            `;
        }

        function renderChatAttachment(attachment) {
            if (!attachment) return '';

            if (attachment.kind === 'image') {
                return `
                    <div class="live-message-attachment">
                        <a href="${escapeAttribute(attachment.url || '#')}" target="_blank" rel="noopener">
                            <img src="${escapeAttribute(attachment.url || '')}" alt="${escapeAttribute(attachment.name || 'Attachment')}" class="live-message-attachment-image">
                        </a>
                    </div>
                `;
            }

            return `
                <div class="live-message-attachment">
                    <a href="${escapeAttribute(attachment.download_url || attachment.url || '#')}" class="live-message-file" target="_blank" rel="noopener">
                        <span class="live-message-file-icon"><i class="fas fa-file-lines"></i></span>
                        <span class="live-message-file-info">
                            <span class="live-message-file-name">${escapeHtml(attachment.name || 'Attachment')}</span>
                            <span class="live-message-file-size">${escapeHtml(attachment.size_label || '')}</span>
                        </span>
                        <i class="fas fa-download"></i>
                    </a>
                </div>
            `;
        }

        function renderEmptyConversation() {
            body.innerHTML = `
                <div class="message-chat-empty">
                    <i class="fas fa-comments"></i>
                    <strong>No messages yet</strong>
                    <span>Start the conversation using the composer below.</span>
                </div>
            `;
        }

        function updateReadStates(readStates) {
            readStates.forEach((state) => {
                const node = body.querySelector(`[data-live-message-status="${state.id}"]`);
                if (node) {
                    node.textContent = state.read_at ? 'Seen' : 'Sent';
                }
            });
        }

        function updateTyping(payload) {
            setTypingVisible(Boolean(payload?.is_typing), payload?.label || '');
        }

        function setTypingVisible(isVisible, label) {
            typing.classList.toggle('is-hidden', !isVisible);
            typingText.textContent = label || 'Typing...';
        }

        function notifyTyping() {
            if (!activeUserId) return;

            const now = Date.now();
            if (!typingActive || now - lastTypingSentAt > 1500) {
                sendTyping(true);
            }

            window.clearTimeout(typingStopTimer);
            typingStopTimer = window.setTimeout(() => sendTyping(false), 1800);
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

        function updateHeaderPresence(isOnline) {
            presence.classList.toggle('is-online', isOnline);
            status.classList.toggle('is-online', isOnline);
            statusText.textContent = isOnline ? 'Active now' : 'Offline';
        }

        function updateThreadPreview(userId, message) {
            const trigger = triggers.find((item) => Number(item.dataset.conversationId) === Number(userId));
            if (!trigger) return;

            const preview = trigger.querySelector('.inbox-thread-preview');
            const time = trigger.querySelector('.inbox-thread-time');

            if (preview) {
                preview.textContent = message.body || (message.attachment ? `Attachment: ${message.attachment.name || 'File'}` : 'Message sent');
            }

            if (time) {
                time.textContent = 'now';
            }
        }

        function scrollToBottom() {
            if (!body) return;
            body.scrollTop = body.scrollHeight;
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

        function renderAttachmentPreview(file) {
            if (!attachmentPreview) return;

            revokeSelectedAttachmentUrl();
            selectedAttachmentPreviewUrl = isImageAttachment(file) ? URL.createObjectURL(file) : null;
            attachmentPreview.classList.remove('is-hidden');
            attachmentPreview.innerHTML = `
                ${selectedAttachmentPreviewUrl
                    ? `<img src="${selectedAttachmentPreviewUrl}" alt="" class="live-attachment-image">`
                    : `<span class="live-attachment-icon"><i class="fas fa-file-lines"></i></span>`}
                <span class="live-attachment-info">
                    <span class="live-attachment-name">${escapeHtml(file.name)}</span>
                    <span class="live-attachment-size">${formatFileSize(file.size)}</span>
                </span>
                <button type="button" class="live-attachment-remove" aria-label="Remove attachment">
                    <i class="fas fa-times"></i>
                </button>
            `;
            attachmentPreview.querySelector('.live-attachment-remove')?.addEventListener('click', () => clearAttachment());
        }

        function clearAttachment(revokePreview = true) {
            selectedAttachment = null;
            if (fileInput) fileInput.value = '';
            if (attachmentPreview) {
                attachmentPreview.classList.add('is-hidden');
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

        function getFileExtension(name) {
            return (name.split('.').pop() || '').toLowerCase();
        }

        function isImageAttachment(file) {
            return imageAttachmentExtensions.includes(getFileExtension(file.name));
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
                .map((part) => part.charAt(0).toUpperCase())
                .join('') || 'U';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text == null ? '' : String(text);
            return div.innerHTML;
        }

        function escapeAttribute(text) {
            return escapeHtml(text).replace(/"/g, '&quot;');
        }
    });
</script>
