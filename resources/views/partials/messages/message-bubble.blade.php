@php
    /** @var \App\Models\Message $message */
    $isOutgoing = (int) $message->sender_id === (int) auth()->id();
    $senderName = $isOutgoing ? 'You' : ($message->sender?->company ?: ($message->sender?->name ?? 'User'));
    $showState = $isOutgoing && (int) $message->id === (int) ($latestOutgoingMessageId ?? 0);
@endphp

<article
    class="messenger-message {{ $isOutgoing ? 'is-outgoing' : 'is-incoming' }}"
    data-message-id="{{ $message->id }}"
>
    <span class="messenger-message-author">{{ $senderName }}</span>
    <div class="messenger-message-bubble">
        @if(filled($message->body))
            <p class="messenger-message-text">{{ $message->body }}</p>
        @endif

        @if($message->hasAttachment())
            <div class="messenger-attachment">
                @if($message->isImageAttachment())
                    <a href="{{ route('messages.attachment', $message) }}" target="_blank" rel="noopener">
                        <img src="{{ route('messages.attachment', $message) }}" alt="{{ $message->attachment_original_name ?: 'Attachment' }}" class="messenger-attachment-image">
                    </a>
                @else
                    <a href="{{ route('messages.attachment', ['message' => $message, 'download' => 1]) }}" class="messenger-file-link" target="_blank" rel="noopener">
                        <span class="messenger-file-icon"><i class="fas fa-file-lines"></i></span>
                        <span class="messenger-file-info">
                            <span class="messenger-file-name">{{ $message->attachment_original_name ?: 'Attachment' }}</span>
                            <span class="messenger-file-size">{{ $message->formattedAttachmentSize() }}</span>
                        </span>
                        <i class="fas fa-download"></i>
                    </a>
                @endif
            </div>
        @endif
    </div>
    <div class="messenger-message-meta">{{ $message->created_at?->format('g:i A') }}</div>
    @if($showState)
        <div class="messenger-message-state" data-live-message-status="{{ $message->id }}">{{ $message->read_at ? 'Seen' : 'Sent' }}</div>
    @elseif($isOutgoing)
        <div class="messenger-message-state" data-live-message-status="{{ $message->id }}" hidden></div>
    @endif
</article>
