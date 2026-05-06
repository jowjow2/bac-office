<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home staff-dashboard-page admin-messages-page">
    @vite(['resources/css/dashboard.css'])
    @include('partials.message-page-styles')

    @include('partials.admin-sidebar')

    <div class="main-area">
        <header class="navbar">
            <div class="nav-left">
                <div>
                    <h2>Messages</h2>
                    <p>Send updates and answer bidder or staff concerns directly.</p>
                </div>
            </div>
            <div class="nav-right">
                <div class="nav-date-chip"><span id="realtimeDate">{{ now()->format('M d, Y h:i A') }}</span></div>
                <a href="{{ route('admin.notifications') }}" class="notification-button" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    @if(($adminNotificationCount ?? 0) > 0)
                        <span class="notification-badge">{{ $adminNotificationCount }}</span>
                    @endif
                </a>
            </div>
        </header>

        <main class="dashboard-content dashboard-home-content">
            <section class="staff-messages-shell">
                @if(session('success'))
                    <div class="success-alert" style="margin-bottom: 2px;">{{ session('success') }}</div>
                @endif

                @if($errors->any())
                    <div class="warning-alert" style="margin-bottom: 2px; padding: 14px 16px; border-radius: 14px; background: #fff7ed; border: 1px solid #fdba74; color: #9a3412;">
                        {{ $errors->first() }}
                    </div>
                @endif

                @php
                    $activeTab = $activeTab ?? 'bidders';
                    $activeThreadSummaries = $activeTab === 'staff' ? $staffThreadSummaries : $bidderThreadSummaries;
                    $activeTitle = $activeTab === 'staff' ? 'Message Staff' : 'Message Bidders';
                    $activeDescription = $activeTab === 'staff'
                        ? 'Open staff conversations and send internal updates.'
                        : 'Select a bidder and open a live chat.';
                @endphp

                <nav class="staff-message-tabs" aria-label="Message categories">
                    <a href="{{ route('admin.messages', ['tab' => 'bidders']) }}" class="staff-message-tab {{ $activeTab === 'bidders' ? 'is-active' : '' }}">
                        <i class="fas fa-building-user"></i>
                        Bidders
                    </a>
                    <a href="{{ route('admin.messages', ['tab' => 'staff']) }}" class="staff-message-tab {{ $activeTab === 'staff' ? 'is-active' : '' }}">
                        <i class="fas fa-user-tie"></i>
                        Staff
                    </a>
                </nav>

                <section class="staff-messages-panel">
                    <div class="staff-messages-panel-header">
                        <div class="staff-messages-panel-title">
                            <h2>{{ $activeTitle }}</h2>
                            <p>{{ $activeDescription }}</p>
                        </div>
                        <div class="staff-message-search">
                            <i class="fas fa-search"></i>
                            <input type="text" id="adminMessageSearch" placeholder="Search conversations...">
                        </div>
                    </div>

                    <div class="staff-message-list" id="adminMessageList">
                        @forelse($activeThreadSummaries as $thread)
                            @php
                                $threadUser = $thread['user'];
                                $latestMessage = $thread['latest_message'];
                                $threadName = $threadUser->company ?: $threadUser->name;
                                $threadRoleLabel = $threadUser->role === 'staff' ? 'Staff' : 'Bidder';
                                $threadRouteTab = $threadUser->role === 'staff' ? 'staff' : 'bidders';
                                $messageAction = $threadUser->role === 'staff' ? 'Message Staff' : 'Message Bidder';
                                $messageText = $latestMessage?->body
                                    ? \Illuminate\Support\Str::limit($latestMessage->body, 80)
                                    : ($latestMessage?->hasAttachment() ? 'Attachment: ' . $latestMessage->attachment_original_name : 'Start a conversation');
                                $senderName = $latestMessage
                                    ? ((int) $latestMessage->sender_id === (int) auth()->id()
                                        ? 'You'
                                        : ($latestMessage->sender->company ?: $latestMessage->sender->name))
                                    : null;
                                $threadPreview = $senderName ? $senderName . ': ' . $messageText : $messageText;
                                $isOnline = $threadUser->is_online ?? false;
                                $isSelected = (int) ($selectedBidder?->id ?? 0) === (int) $threadUser->id;
                                $latestIsOutgoing = $latestMessage && (int) $latestMessage->sender_id === (int) auth()->id();
                                $statusLabel = ! $latestMessage
                                    ? 'Ready'
                                    : ($latestIsOutgoing ? ($latestMessage->read_at ? 'Seen' : 'Sent') : (($thread['unread_count'] ?? 0) > 0 ? 'New' : 'Received'));
                                $initials = collect(preg_split('/\s+/', trim($threadName)))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('') ?: 'U';
                            @endphp
                            <a
                                href="{{ route('admin.messages', ['tab' => $threadRouteTab, 'user' => $threadUser->id]) }}"
                                class="staff-message-contact {{ $isSelected ? 'is-active' : '' }}"
                                data-live-chat-trigger
                                data-conversation-id="{{ $threadUser->id }}"
                                data-conversation-name="{{ $threadName }}"
                                data-conversation-subtitle="{{ $threadRoleLabel }} | {{ $threadUser->email }}"
                                data-conversation-initials="{{ $initials }}"
                                data-conversation-online="{{ $isOnline ? '1' : '0' }}"
                                data-thread-name="{{ strtolower($threadName . ' ' . $threadUser->email . ' ' . $threadRoleLabel) }}"
                            >
                                <div class="staff-message-avatar-wrap">
                                    <div class="staff-message-avatar">{{ $initials }}</div>
                                    <div class="staff-message-status-dot status-dot {{ $isOnline ? 'is-online' : 'is-offline' }}" data-user-id="{{ $threadUser->id }}"></div>
                                </div>

                                <div class="staff-message-copy">
                                    <div class="staff-message-contact-top">
                                        <span class="staff-message-name">{{ $threadName }}</span>
                                        <span class="staff-message-time inbox-thread-time">{{ $latestMessage?->created_at?->shortRelativeDiffForHumans() ?? '' }}</span>
                                    </div>
                                    <p class="staff-message-preview inbox-thread-preview">{{ $threadPreview }}</p>
                                    <div class="staff-message-meta">
                                        <span class="staff-message-pill">{{ $threadRoleLabel }}</span>
                                        <span class="staff-message-pill is-presence {{ $isOnline ? 'is-online' : 'is-offline' }}" data-presence-label data-user-id="{{ $threadUser->id }}">{{ $isOnline ? 'Online' : 'Offline' }}</span>
                                        <span class="staff-message-pill {{ $statusLabel === 'New' ? 'is-new' : '' }}">{{ $statusLabel }}</span>
                                        @if(($thread['unread_count'] ?? 0) > 0)
                                            <span class="unread-count-badge">{{ $thread['unread_count'] }}</span>
                                        @endif
                                    </div>
                                </div>

                                <span class="staff-message-action">{{ $messageAction }}</span>
                            </a>
                        @empty
                            <div class="staff-message-empty">
                                <i class="fas fa-inbox"></i>
                                No {{ $activeTab === 'staff' ? 'staff' : 'bidder' }} conversations are available yet.
                            </div>
                        @endforelse
                    </div>
                </section>
            </section>
        </main>
    </div>
</div>

@include('partials.message-chat-modal', [
    'messageSyncRoute' => route('admin.messages.conversation-sync'),
    'messageTypingRoute' => route('admin.messages.typing'),
    'messageStoreRoute' => route('admin.messages.store'),
    'messageStatusRoute' => route('admin.messages.status-sync'),
    'initialChatUserId' => request()->query('user'),
    'messageChatSubtitle' => 'BAC Office conversation',
    'messageChatEmptyTitle' => 'Select a contact',
    'messageChatEmptyText' => 'Choose a contact from the list to open a live conversation.',
])

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('adminMessageSearch');
        const list = document.getElementById('adminMessageList');

        if (searchInput && list) {
            searchInput.addEventListener('input', function () {
                const query = searchInput.value.toLowerCase().trim();

                list.querySelectorAll('.staff-message-contact').forEach(function (item) {
                    const haystack = item.dataset.threadName || '';
                    const preview = item.querySelector('.staff-message-preview')?.textContent.toLowerCase() || '';
                    item.style.display = haystack.includes(query) || preview.includes(query) ? 'grid' : 'none';
                });
            });
        }

        function updateDate() {
            const dateElement = document.getElementById('realtimeDate');
            if (!dateElement) return;

            const now = new Date();
            const options = { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true };
            dateElement.textContent = now.toLocaleDateString('en-US', options);
        }

        updateDate();
        setInterval(updateDate, 60000);
    });
</script>
