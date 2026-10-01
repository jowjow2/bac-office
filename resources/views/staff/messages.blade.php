<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard staff-role-page dashboard-home admin-dashboard-page staff-dashboard staff-dashboard-page staff-messages-page">
    @vite(['resources/css/dashboard.css'])
    @include('partials.message-page-styles')

    @include('partials.staff-sidebar')

    <div class="main-area">
        <x-page-header title="Messages" subtitle="Send updates and communicate with admin or bidders" />

        <main class="dashboard-content dashboard-home-content">
            @php
                $activeTab = $activeTab ?? 'admin';
                $activeThreadSummaries = $activeTab === 'bidders' ? $bidderThreadSummaries : $adminThreadSummaries;
            @endphp

            @include('partials.messages.messenger', [
                'activeTab' => $activeTab,
                'activeThreadSummaries' => $activeThreadSummaries,
                'selectedContact' => $selectedContact,
                'conversationMessages' => $conversationMessages,
                'messageTabs' => [
                    ['key' => 'admin', 'label' => 'Admin', 'icon' => 'fas fa-user-shield', 'url' => route('staff.messages', ['tab' => 'admin'])],
                    ['key' => 'bidders', 'label' => 'Bidders', 'icon' => 'fas fa-building-user', 'url' => route('staff.messages', ['tab' => 'bidders'])],
                ],
                'messageRouteName' => 'staff.messages',
                'tabByRole' => ['admin' => 'admin', 'bidder' => 'bidders'],
                'messageSyncRoute' => route('staff.messages.conversation-sync'),
                'messageTypingRoute' => route('staff.messages.typing'),
                'messageStoreRoute' => route('staff.messages.store'),
                'messageStatusRoute' => route('staff.messages.status-sync'),
            ])
        </main>
    </div>
</div>

