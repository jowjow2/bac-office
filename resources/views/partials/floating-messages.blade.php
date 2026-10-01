@php
    $floatingMessagesUser = auth()->user();
    $floatingMessagesRole = $floatingMessagesUser?->role;
    $floatingMessagesConfig = match ($floatingMessagesRole) {
        'admin' => [
            'status' => route('admin.messages.status-sync'),
            'conversation' => route('admin.messages.conversation-sync'),
            'typing' => route('admin.messages.typing'),
            'store' => route('admin.messages.store'),
            'full' => route('admin.messages'),
        ],
        'staff' => [
            'status' => route('staff.messages.status-sync'),
            'conversation' => route('staff.messages.conversation-sync'),
            'typing' => route('staff.messages.typing'),
            'store' => route('staff.messages.store'),
            'full' => route('staff.messages'),
        ],
        'bidder' => [
            'status' => route('bidder.messages.status-sync'),
            'conversation' => route('bidder.messages.conversation-sync'),
            'typing' => route('bidder.messages.typing'),
            'store' => route('bidder.messages.store'),
            'full' => route('bidder.messages'),
        ],
        default => null,
    };
@endphp

@if($floatingMessagesConfig)
    <div
        class="floating-messages-widget"
        style="display: none;"
        data-floating-messages
        data-current-user-id="{{ $floatingMessagesUser->id }}"
        data-status-url="{{ $floatingMessagesConfig['status'] }}"
        data-conversation-url="{{ $floatingMessagesConfig['conversation'] }}"
        data-typing-url="{{ $floatingMessagesConfig['typing'] }}"
        data-store-url="{{ $floatingMessagesConfig['store'] }}"
        data-full-url="{{ $floatingMessagesConfig['full'] }}"
    >
        <button type="button" class="floating-messages-toggle" data-floating-messages-toggle aria-expanded="false" aria-controls="floatingMessagesPanel" aria-label="Open messages">
            <i class="fas fa-comment-dots" aria-hidden="true"></i>
            <span class="floating-messages-unread" data-floating-messages-unread hidden></span>
        </button>

        <section class="floating-messages-panel" id="floatingMessagesPanel" data-floating-messages-panel hidden aria-label="Quick messages">
            <header class="floating-messages-header">
                <div>
                    <button type="button" class="floating-messages-back" data-floating-messages-back hidden aria-label="Back to conversations"><i class="fas fa-arrow-left" aria-hidden="true"></i></button>
                    <span class="floating-messages-heading-copy">
                        <strong data-floating-messages-title data-floating-chat-name>Messages</strong>
                        <span class="floating-messages-live" data-floating-chat-status hidden></span>
                    </span>
                </div>
                <button type="button" class="floating-messages-close" data-floating-messages-close aria-label="Close messages">&times;</button>
            </header>

            <div class="floating-messages-search-wrap" data-floating-messages-list-view>
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" placeholder="Search conversations..." data-floating-messages-search>
            </div>

            <div class="floating-messages-content">
                <div class="floating-messages-list" data-floating-messages-list-view data-floating-messages-list></div>
                <div class="floating-messages-chat" data-floating-messages-chat hidden>
                    <div class="floating-messages-chat-body" data-floating-chat-body></div>
                    <div class="floating-messages-typing" data-floating-chat-typing hidden></div>
                    <form class="floating-messages-composer" data-floating-message-form>
                        <label class="floating-messages-attach" title="Attach file">
                            <i class="fas fa-paperclip" aria-hidden="true"></i>
                            <input type="file" data-floating-attachment accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx">
                        </label>
                        <input type="text" placeholder="Type a message..." autocomplete="off" data-floating-message-input>
                        <button type="submit" aria-label="Send message"><i class="fas fa-paper-plane" aria-hidden="true"></i></button>
                    </form>
                </div>
            </div>

            <a class="floating-messages-full-link" data-floating-messages-list-view href="{{ $floatingMessagesConfig['full'] }}">View all messages</a>
        </section>
    </div>
@endif




