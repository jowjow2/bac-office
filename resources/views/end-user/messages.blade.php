{{-- End-user office: conversations with the BAC Secretariat and the BAC admin (MessageController::endUserIndex). --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home admin-dashboard-page staff-dashboard-page bidder-dashboard-page bidder-messages-page">
    @vite(['resources/css/dashboard.css'])
    @include('partials.message-page-styles')

    @include('partials.enduser-sidebar')

    <div class="main-area">
        <x-page-header title="Messages" subtitle="Ask the BAC Secretariat or the BAC admin about your purchase requests." />

        <main class="dashboard-content dashboard-home-content">
            @php
                $activeTab = $activeTab ?? 'staff';
                $activeThreadSummaries = $activeTab === 'admin' ? $adminThreadSummaries : $staffThreadSummaries;
            @endphp

            @include('partials.messages.messenger', [
                'activeTab' => $activeTab,
                'activeThreadSummaries' => $activeThreadSummaries,
                'selectedContact' => $selectedContact,
                'conversationMessages' => $conversationMessages,
                'messageTabs' => [
                    ['key' => 'staff', 'label' => 'BAC Secretariat', 'icon' => 'fas fa-user-tie', 'url' => route('end-user.messages', ['tab' => 'staff'])],
                    ['key' => 'admin', 'label' => 'BAC Admin', 'icon' => 'fas fa-user-shield', 'url' => route('end-user.messages', ['tab' => 'admin'])],
                ],
                'messageRouteName' => 'end-user.messages',
                'tabByRole' => ['admin' => 'admin', 'staff' => 'staff'],
                'messageSyncRoute' => route('end-user.messages.conversation-sync'),
                'messageTypingRoute' => route('end-user.messages.typing'),
                'messageStoreRoute' => route('end-user.messages.store'),
                'messageStatusRoute' => route('end-user.messages.status-sync'),
                'messageDraft' => $messageDraft ?? '',
            ])
        </main>
    </div>
</div>
