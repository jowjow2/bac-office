<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home admin-dashboard-page staff-dashboard-page bidder-dashboard-page bidder-messages-page">
    @vite(['resources/css/dashboard.css'])
    @include('partials.message-page-styles')

    @include('partials.bidder-sidebar')

    <div class="main-area">
        <x-page-header title="BAC messages" subtitle="Chat with the SJBAC team about your account and bids." />

        <main class="dashboard-content dashboard-home-content">
            @php
                $activeTab = $activeTab ?? 'admin';
                $activeThreadSummaries = $activeTab === 'staff' ? $staffThreadSummaries : $adminThreadSummaries;
            @endphp

            @include('partials.messages.messenger', [
                'activeTab' => $activeTab,
                'activeThreadSummaries' => $activeThreadSummaries,
                'selectedContact' => $selectedAdmin,
                'conversationMessages' => $conversationMessages,
                'messageTabs' => [
                    ['key' => 'admin', 'label' => 'Admin', 'icon' => 'fas fa-user-shield', 'url' => route('bidder.messages', ['tab' => 'admin'])],
                    ['key' => 'staff', 'label' => 'Staff', 'icon' => 'fas fa-user-tie', 'url' => route('bidder.messages', ['tab' => 'staff'])],
                ],
                'messageRouteName' => 'bidder.messages',
                'tabByRole' => ['admin' => 'admin', 'staff' => 'staff'],
                'messageSyncRoute' => route('bidder.messages.conversation-sync'),
                'messageTypingRoute' => route('bidder.messages.typing'),
                'messageStoreRoute' => route('bidder.messages.store'),
                'messageStatusRoute' => route('bidder.messages.status-sync'),
            ])
        </main>
    </div>
</div>
