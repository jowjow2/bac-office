<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard staff-role-page dashboard-home admin-dashboard-page staff-dashboard-page">
    @vite(['resources/css/dashboard.css'])
    @include('partials.staff-page-styles')

    @include('partials.staff-sidebar')

    <div class="main-area">
        <x-page-header title="Bidding fee payments" subtitle="Record bidding documents fees paid at the BAC office" />

        <main class="dashboard-content dashboard-home-content">
            @include('partials.payments.board')
        </main>
    </div>
</div>
