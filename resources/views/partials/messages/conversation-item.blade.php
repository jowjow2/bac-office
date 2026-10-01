@php
    /** @var \App\Models\User $threadUser */
    $threadUser = $thread['user'];
    $latestMessage = $thread['latest_message'] ?? null;
    $threadName = $threadUser->company ?: $threadUser->name;
    $threadRoleLabel = match ($threadUser->role) {
        'admin' => 'Admin',
        'staff' => 'Staff',
        'bidder' => 'Bidder',
        default => ucfirst($threadUser->role ?? 'User'),
    };
    $threadRouteTab = $tabByRole[$threadUser->role] ?? $activeTab;
    $threadUrl = route($messageRouteName, ['tab' => $threadRouteTab, 'user' => $threadUser->id]);
    $messageText = $latestMessage?->body
        ? \Illuminate\Support\Str::limit($latestMessage->body, 90)
        : ($latestMessage?->hasAttachment() ? 'Attachment: ' . $latestMessage->attachment_original_name : 'No messages yet. Send your first message.');
    $senderName = $latestMessage
        ? ((int) $latestMessage->sender_id === (int) auth()->id()
            ? 'You'
            : ($latestMessage->sender->company ?: $latestMessage->sender->name))
        : null;
    $threadPreview = $senderName ? $senderName . ': ' . $messageText : $messageText;
    $isOnline = (bool) ($threadUser->is_online ?? false);
    $isSelected = (int) ($selectedContact?->id ?? 0) === (int) $threadUser->id;
    $unreadCount = (int) ($thread['unread_count'] ?? 0);
    $initials = collect(preg_split('/\s+/', trim($threadName)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->implode('') ?: 'U';
    $sortTimestamp = (int) ($thread['sort_timestamp'] ?? ($latestMessage?->created_at?->timestamp ?? 0));
@endphp

<a
    href="{{ $threadUrl }}"
    class="messenger-thread {{ $isSelected ? 'is-active' : '' }}"
    data-messenger-thread
    data-conversation-id="{{ $threadUser->id }}"
    data-conversation-name="{{ $threadName }}"
    data-conversation-role="{{ $threadRoleLabel }}"
    data-conversation-email="{{ $threadUser->email }}"
    data-conversation-subtitle="{{ $threadRoleLabel }} | {{ $threadUser->email }}"
    data-conversation-initials="{{ $initials }}"
    data-conversation-online="{{ $isOnline ? '1' : '0' }}"
    data-thread-name="{{ strtolower($threadName . ' ' . $threadUser->email . ' ' . $threadRoleLabel) }}"
    data-thread-sort="{{ $sortTimestamp }}"
>
    <span class="messenger-avatar-wrap">
        <span class="messenger-avatar">{{ $initials }}</span>
        <span class="messenger-presence-dot status-dot {{ $isOnline ? 'is-online' : 'is-offline' }}" data-user-id="{{ $threadUser->id }}"></span>
    </span>

    <span class="messenger-thread-copy">
        <span class="messenger-thread-top">
            <span class="messenger-thread-name">{{ $threadName }}</span>
            <span class="messenger-thread-time" data-thread-time>{{ $latestMessage?->created_at?->shortRelativeDiffForHumans() ?? '' }}</span>
        </span>
        <span class="messenger-thread-bottom">
            <span class="messenger-thread-preview" data-thread-preview>{{ $threadPreview }}</span>
            <span class="messenger-role-pill">{{ $threadRoleLabel }}</span>
        </span>
    </span>

    <span class="messenger-unread-badge" data-thread-unread-badge @if($unreadCount <= 0) hidden style="display:none" @endif>{{ $unreadCount > 0 ? $unreadCount : '' }}</span>
    <span class="sr-only">Message {{ $threadRoleLabel }}</span>
</a>
