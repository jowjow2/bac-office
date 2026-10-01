@php
    $selectedContact = $selectedContact ?? null;
    $hasSelectedContact = $selectedContact instanceof \App\Models\User;
    $conversationMessages = collect($conversationMessages ?? []);
    $selectedName = $hasSelectedContact ? ($selectedContact->company ?: $selectedContact->name) : '';
    $selectedRoleLabel = $hasSelectedContact ? match ($selectedContact->role) {
        'admin' => 'Admin',
        'staff' => 'Staff',
        'bidder' => 'Bidder',
        default => ucfirst($selectedContact->role ?? 'User'),
    } : '';
    $selectedInitials = $hasSelectedContact
        ? (collect(preg_split('/\s+/', trim($selectedName)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
            ->implode('') ?: 'U')
        : 'U';
    $selectedIsOnline = $hasSelectedContact ? (bool) ($selectedContact->is_online ?? false) : false;
    $latestOutgoingMessageId = (int) ($conversationMessages
        ->where('sender_id', auth()->id())
        ->max('id') ?? 0);
@endphp

<section class="messenger-chat {{ $hasSelectedContact ? '' : 'is-empty' }}" data-chat-panel>
    <div class="messenger-empty-chat" data-empty-chat @if($hasSelectedContact) hidden @endif>
        <i class="fas fa-comments"></i>
        <strong>Select a conversation to start messaging.</strong>
        <p>Choose a bidder or staff conversation from the list to view messages.</p>
    </div>

    <header class="messenger-chat-header" data-chat-header @unless($hasSelectedContact) hidden @endunless>
        <div class="messenger-chat-person">
            <button type="button" class="messenger-mobile-back" data-chat-back aria-label="Back to conversations">
                <i class="fas fa-arrow-left"></i>
            </button>
            <span class="messenger-chat-avatar">
                <span data-chat-initials>{{ $selectedInitials }}</span>
                <span class="messenger-presence-dot {{ $selectedIsOnline ? 'is-online' : 'is-offline' }}" data-chat-presence></span>
            </span>
            <span class="messenger-chat-title-wrap">
                <h3 class="messenger-chat-title" data-chat-title>{{ $selectedName ?: 'Conversation' }}</h3>
                <span class="messenger-chat-subtitle" data-chat-subtitle>{{ $selectedRoleLabel ? $selectedRoleLabel . ' | ' . $selectedContact?->email : 'Secure SJBAC messaging' }}</span>
                <span class="messenger-chat-status {{ $selectedIsOnline ? 'is-online' : '' }}" data-chat-status>
                    <span class="messenger-status-dot"></span>
                    <span data-chat-status-text>{{ $selectedIsOnline ? 'Active now' : 'Offline' }}</span>
                </span>
            </span>
        </div>
    </header>

    <div class="messenger-chat-body" data-chat-body @unless($hasSelectedContact) hidden @endunless>
        <div class="messenger-message-stack" data-message-stack>
            @if($hasSelectedContact && $conversationMessages->isEmpty())
                <div class="messenger-no-messages" data-no-messages>
                    <i class="fas fa-paper-plane"></i>
                    <strong>No messages yet. Send your first message.</strong>
                </div>
            @else
                @foreach($conversationMessages as $message)
                    @include('partials.messages.message-bubble', [
                        'message' => $message,
                        'latestOutgoingMessageId' => $latestOutgoingMessageId,
                    ])
                @endforeach
            @endif
        </div>
    </div>

    <div class="messenger-typing" data-chat-typing hidden>
        <span class="messenger-typing-dots" aria-hidden="true"><span></span><span></span><span></span></span>
        <span data-chat-typing-text>Typing...</span>
    </div>

    <footer class="messenger-composer" data-chat-composer @unless($hasSelectedContact) hidden @endunless>
        <div class="messenger-attachment-preview" data-attachment-preview hidden></div>
        <form class="messenger-input-row" data-message-form action="{{ $messageStoreRoute }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="recipient_id" data-recipient-input value="{{ $selectedContact?->id }}">
            <label class="messenger-attach-button" title="Attach photo or document">
                <i class="fas fa-paperclip"></i>
                <input
                    type="file"
                    name="attachment"
                    class="messenger-file-input"
                    data-attachment-input
                    accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,image/jpeg,image/png,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                >
            </label>
            <textarea class="messenger-input" name="body" rows="1" placeholder="Type a message..." data-message-input></textarea>
            <button type="submit" class="messenger-send-button" data-send-button aria-label="Send message">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
    </footer>
</section>
