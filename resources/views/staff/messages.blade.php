<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home staff-dashboard staff-dashboard-page staff-messages-page">
    @vite(['resources/css/dashboard.css'])
    @include('partials.staff-page-styles')

    <style>
        .staff-messages-shell {
            display: grid;
            gap: 16px;
        }

        .staff-message-tabs {
            display: inline-flex;
            width: fit-content;
            gap: 6px;
            padding: 5px;
            border: 1px solid #dbe4f0;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
        }

        .staff-message-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 36px;
            padding: 0 14px;
            border-radius: 10px;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }

        .staff-message-tab.is-active {
            background: #fff7ed;
            color: #c2410c;
        }

        .staff-messages-panel {
            background: #ffffff;
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .staff-messages-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .staff-messages-panel-title h2 {
            margin: 0;
            color: #0f172a;
            font-size: 15px;
            font-weight: 800;
        }

        .staff-messages-panel-title p {
            margin: 4px 0 0;
            color: #94a3b8;
            font-size: 12px;
        }

        .staff-message-search {
            position: relative;
            width: min(320px, 100%);
        }

        .staff-message-search i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 12px;
        }

        .staff-message-search input {
            width: 100%;
            height: 40px;
            padding: 0 14px 0 35px;
            border: 1px solid #dbe4f0;
            border-radius: 12px;
            color: #0f172a;
            background: #f8fafc;
            font-size: 13px;
            outline: none;
        }

        .staff-message-search input:focus {
            border-color: #f97316;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        }

        .staff-message-list {
            display: grid;
        }

        .staff-message-contact {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: center;
            gap: 14px;
            padding: 16px 20px;
            border-bottom: 1px solid #eef2f7;
            color: inherit;
            text-decoration: none;
            background: #ffffff;
            transition: background 0.18s ease;
        }

        .staff-message-contact:hover,
        .staff-message-contact.is-active {
            background: #fff7ed;
        }

        .staff-message-contact:last-child {
            border-bottom: 0;
        }

        .staff-message-avatar-wrap {
            position: relative;
        }

        .staff-message-avatar {
            width: 48px;
            height: 48px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #e2e8f0;
            color: #334155;
            font-size: 15px;
            font-weight: 800;
        }

        .staff-message-status-dot {
            position: absolute;
            right: 1px;
            bottom: 1px;
            width: 12px;
            height: 12px;
            border-radius: 999px;
            border: 2px solid #ffffff;
            background: #94a3b8;
        }

        .staff-message-status-dot.is-online {
            background: #22c55e;
        }

        .staff-message-copy {
            min-width: 0;
        }

        .staff-message-contact-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 5px;
        }

        .staff-message-name {
            color: #0f172a;
            font-size: 14px;
            font-weight: 800;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .staff-message-time {
            flex: 0 0 auto;
            color: #94a3b8;
            font-size: 11px;
        }

        .staff-message-preview {
            margin: 0 0 8px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .staff-message-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .staff-message-pill {
            display: inline-flex;
            align-items: center;
            min-height: 22px;
            padding: 0 9px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #475569;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .staff-message-pill.is-new {
            background: #ffedd5;
            color: #c2410c;
        }

        .staff-message-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 0 14px;
            border-radius: 10px;
            border: 1px solid #fed7aa;
            background: #fff7ed;
            color: #c2410c;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .staff-message-empty {
            padding: 44px 20px;
            color: #94a3b8;
            font-size: 13px;
            line-height: 1.6;
            text-align: center;
        }

        .staff-message-empty i {
            display: block;
            margin-bottom: 12px;
            color: #cbd5e1;
            font-size: 34px;
        }

        @media (max-width: 760px) {
            .staff-messages-panel-header {
                flex-direction: column;
                align-items: stretch;
            }

            .staff-message-tabs,
            .staff-message-tab {
                width: 100%;
            }

            .staff-message-tab {
                justify-content: center;
            }

            .staff-message-contact {
                grid-template-columns: auto minmax(0, 1fr);
            }

            .staff-message-action {
                grid-column: 1 / -1;
                width: 100%;
            }
        }

        @media (max-width: 520px) {
            .staff-message-contact {
                padding: 14px 16px;
                gap: 12px;
            }

            .staff-message-avatar {
                width: 42px;
                height: 42px;
            }
        }
    </style>

    @include('partials.staff-sidebar')

    <div class="main-area">
        @include('partials.staff-topbar', [
            'staffNavbarTitle' => 'Messages',
            'staffNavbarSubtitle' => 'Send updates and communicate with admin or bidders',
        ])

        <main class="dashboard-content dashboard-home-content">
            <section class="staff-dashboard staff-messages-shell">
                @if(session('success'))
                    <div class="assignment-alert assignment-alert-success">{{ session('success') }}</div>
                @endif

                @if($errors->any())
                    <div class="assignment-alert assignment-alert-error">
                        <ul class="assignment-alert-list">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @php
                    $activeTab = $activeTab ?? 'admin';
                    $activeThreadSummaries = $activeTab === 'bidders' ? $bidderThreadSummaries : $adminThreadSummaries;
                    $activeTitle = $activeTab === 'bidders' ? 'Message Bidder' : 'Message Admin';
                    $activeDescription = $activeTab === 'bidders'
                        ? 'Select a bidder and open a live chat.'
                        : 'Send updates or questions to an admin.';
                @endphp

                <nav class="staff-message-tabs" aria-label="Message categories">
                    <a href="{{ route('staff.messages', ['tab' => 'admin']) }}" class="staff-message-tab {{ $activeTab === 'admin' ? 'is-active' : '' }}">
                        <i class="fas fa-user-shield"></i>
                        Admin
                    </a>
                    <a href="{{ route('staff.messages', ['tab' => 'bidders']) }}" class="staff-message-tab {{ $activeTab === 'bidders' ? 'is-active' : '' }}">
                        <i class="fas fa-building-user"></i>
                        Bidders
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
                            <input type="text" id="staffMessageSearch" placeholder="Search conversations...">
                        </div>
                    </div>

                    <div class="staff-message-list" id="staffMessageList">
                        @forelse($activeThreadSummaries as $thread)
                            @php
                                $threadUser = $thread['user'];
                                $latestMessage = $thread['latest_message'];
                                $threadName = $threadUser->company ?: $threadUser->name;
                                $threadRoleLabel = $threadUser->role === 'admin' ? 'Admin' : 'Bidder';
                                $threadRouteTab = $threadUser->role === 'bidder' ? 'bidders' : 'admin';
                                $messageAction = $threadUser->role === 'admin' ? 'Message Admin' : 'Message Bidder';
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
                                $isSelected = (int) ($selectedContact?->id ?? 0) === (int) $threadUser->id;
                                $latestIsOutgoing = $latestMessage && (int) $latestMessage->sender_id === (int) auth()->id();
                                $statusLabel = ! $latestMessage
                                    ? 'Ready'
                                    : ($latestIsOutgoing ? ($latestMessage->read_at ? 'Seen' : 'Sent') : (($thread['unread_count'] ?? 0) > 0 ? 'New' : 'Received'));
                            @endphp
                            <a
                                href="{{ route('staff.messages', ['tab' => $threadRouteTab, 'user' => $threadUser->id]) }}"
                                class="staff-message-contact {{ $isSelected ? 'is-active' : '' }}"
                                data-live-chat-trigger
                                data-conversation-id="{{ $threadUser->id }}"
                                data-conversation-name="{{ $threadName }}"
                                data-conversation-subtitle="{{ $threadRoleLabel }} | {{ $threadUser->email }}"
                                data-conversation-initials="{{ collect(preg_split('/\s+/', trim($threadName)))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('') ?: 'U' }}"
                                data-conversation-online="{{ $isOnline ? '1' : '0' }}"
                                data-thread-name="{{ strtolower($threadName . ' ' . $threadUser->email . ' ' . $threadRoleLabel) }}"
                            >
                                <div class="staff-message-avatar-wrap">
                                    <div class="staff-message-avatar">
                                        {{ collect(preg_split('/\s+/', trim($threadName)))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('') ?: 'U' }}
                                    </div>
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
                                No {{ $activeTab === 'bidders' ? 'bidder' : 'admin' }} contacts are available yet.
                            </div>
                        @endforelse
                    </div>
                </section>
            </section>
        </main>
    </div>
</div>

@include('partials.message-chat-modal', [
    'messageSyncRoute' => route('staff.messages.conversation-sync'),
    'messageTypingRoute' => route('staff.messages.typing'),
    'messageStoreRoute' => route('staff.messages.store'),
    'messageStatusRoute' => route('staff.messages.status-sync'),
    'initialChatUserId' => request()->query('user'),
    'messageChatSubtitle' => 'Staff conversation',
    'messageChatEmptyTitle' => 'Select a contact',
    'messageChatEmptyText' => 'Choose an admin or bidder to open a live conversation.',
])

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('staffMessageSearch');
        const list = document.getElementById('staffMessageList');

        if (!searchInput || !list) return;

        searchInput.addEventListener('input', function () {
            const query = searchInput.value.toLowerCase().trim();

            list.querySelectorAll('.staff-message-contact').forEach(function (item) {
                const haystack = item.dataset.threadName || '';
                const preview = item.querySelector('.staff-message-preview')?.textContent.toLowerCase() || '';
                item.style.display = haystack.includes(query) || preview.includes(query) ? 'grid' : 'none';
            });
        });
    });
</script>
