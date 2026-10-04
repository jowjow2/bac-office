<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard staff-role-page dashboard-home admin-dashboard-page staff-dashboard staff-dashboard-page">
    @vite(['resources/css/dashboard.css'])

    @include('partials.staff-sidebar')

    <div class="main-area">
        <x-page-header title="Notifications" subtitle="Staff activity alerts" />

        <main class="dashboard-content dashboard-home-content">
            @include('partials.notifications.center')
        </main>
    </div>
</div>
