<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard admin-role-page dashboard-home staff-dashboard-page admin-messages-page">
    @vite(['resources/css/dashboard.css'])
    @include('partials.message-page-styles')

    @include('partials.admin-sidebar')

    <div class="main-area">
        <x-page-header title="Messages" subtitle="Send updates and answer bidder, staff or end-user office concerns directly." />

        <main class="dashboard-content dashboard-home-content">
            @php
                $activeTab = $activeTab ?? 'bidders';
                $activeThreadSummaries = ['staff' => $staffThreadSummaries, 'offices' => $officeThreadSummaries ?? collect()][$activeTab] ?? $bidderThreadSummaries;
            @endphp

            @include('partials.messages.messenger', [
                'activeTab' => $activeTab,
                'activeThreadSummaries' => $activeThreadSummaries,
                'selectedContact' => $selectedBidder,
                'conversationMessages' => $conversationMessages,
                'messageTabs' => [
                    ['key' => 'bidders', 'label' => 'Bidders', 'icon' => 'fas fa-building-user', 'url' => route('admin.messages', ['tab' => 'bidders'])],
                    ['key' => 'staff', 'label' => 'Staff', 'icon' => 'fas fa-user-tie', 'url' => route('admin.messages', ['tab' => 'staff'])],
                    ['key' => 'offices', 'label' => 'End-user offices', 'icon' => 'fas fa-building', 'url' => route('admin.messages', ['tab' => 'offices'])],
                ],
                'messageRouteName' => 'admin.messages',
                'tabByRole' => ['bidder' => 'bidders', 'staff' => 'staff', 'end_user' => 'offices'],
                'messageSyncRoute' => route('admin.messages.conversation-sync'),
                'messageTypingRoute' => route('admin.messages.typing'),
                'messageStoreRoute' => route('admin.messages.store'),
                'messageStatusRoute' => route('admin.messages.status-sync'),
            ])
        </main>
    </div>
</div>
