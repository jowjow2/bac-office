<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard admin-role-page">
    @vite(['resources/css/dashboard.css'])

    @include('partials.admin-sidebar')

    <div class="main-area">
        <x-page-header title="Notifications" subtitle="System alerts and updates" />

        <main class="dashboard-content">


            @include('partials.notifications.center')
        </main>
    </div>
</div>
