<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home admin-dashboard-page bidder-dashboard-page bidder-notifications-page">
    @vite(['resources/css/dashboard.css'])
    @include('partials.bidder-sidebar')
    <div class="main-area">
        <x-page-header title="Notifications" subtitle="System alerts and updates" />
        <main class="dashboard-content dashboard-home-content">
            @include('partials.notifications.center')
        </main>
    </div>
</div>
