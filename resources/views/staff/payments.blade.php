<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard staff-role-page dashboard-home admin-dashboard-page staff-dashboard-page">
    @vite(['resources/css/dashboard.css', 'resources/css/bid-management.css'])
    @include('partials.staff-page-styles')

    @include('partials.staff-sidebar')

    <div class="main-area">
        <x-page-header title="Bidding fee payments" subtitle="Record bidding documents fees paid at the BAC office">
            <x-slot:actions>
                <a href="{{ route('staff.payments.report') }}" class="ui-btn ui-btn--secondary hd-export"><i class="fas fa-chart-column" aria-hidden="true"></i> Report</a>
                <button type="button" class="ui-btn ui-btn--primary pay-record" data-fee-open-record><i class="fas fa-plus" aria-hidden="true"></i> Record payment</button>
            </x-slot:actions>
        </x-page-header>

        <main class="dashboard-content dashboard-home-content">
            @include('partials.payments.board')
        </main>
    </div>
</div>
