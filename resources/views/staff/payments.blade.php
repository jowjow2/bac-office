<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard staff-role-page dashboard-home admin-dashboard-page staff-dashboard-page">
    @vite(['resources/css/dashboard.css', 'resources/css/bid-management.css'])
    @include('partials.staff-page-styles')

    @include('partials.staff-sidebar')

    <div class="main-area">
        <x-page-header title="Bidding fee payments" subtitle="Record bidding documents fees paid at the BAC office">
            <x-slot:actions>
                @php $exportFormats = [
                    ['label' => 'Payment records (CSV)', 'hint' => 'Every Official Receipt, opens in Excel or Sheets', 'url' => route('staff.payments.export'), 'icon' => 'fa-file-csv'],
                    ['label' => 'Collection report (PDF)', 'hint' => 'Totals by project and day for a period. Print or save as PDF', 'url' => route('staff.payments.report'), 'icon' => 'fa-file-pdf', 'open' => true],
                ]; @endphp
                <a href="{{ route('staff.payments.export') }}" class="ui-btn ui-btn--secondary hd-export" data-export-dialog data-export-title="Export payments" data-export-noun="payment" data-export-formats="{{ json_encode($exportFormats) }}"><i class="fas fa-file-export" aria-hidden="true"></i> Export</a>
                <button type="button" class="ui-btn ui-btn--primary pay-record" data-fee-open-record><i class="fas fa-plus" aria-hidden="true"></i> Record payment</button>
            </x-slot:actions>
        </x-page-header>
        @include('partials.export-dialog')

        <main class="dashboard-content dashboard-home-content">
            @include('partials.payments.board')
        </main>
    </div>
</div>
