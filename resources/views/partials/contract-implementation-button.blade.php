{{-- Contract implementation status for an award: Infrastructure links to its tracking page;
     Goods opens its Contract Implementation modal (partials.contract-implementation-dialogs). --}}
@if(app(\App\Support\InfrastructureImplementationWorkflow::class)->eligible($award))
    @php
        $infraRecord = $award->contractImplementation;
        $infraStatus = $infraRecord?->status;
        $infraTone = match (true) {
            $infraStatus === \App\Models\ContractImplementation::INFRA_FOR_CORRECTION => 'warning',
            in_array($infraStatus, [\App\Models\ContractImplementation::INFRA_ACCEPTED, \App\Models\ContractImplementation::INFRA_PAID, \App\Models\ContractImplementation::INFRA_COMPLETED], true) => 'success',
            default => 'info',
        };
        $infraRoute = match (auth()->user()?->role) {
            'bidder' => 'bidder.infrastructure.show',
            'staff' => 'staff.infrastructure.show',
            default => 'admin.infrastructure.show',
        };
    @endphp
    <a class="ci-open" href="{{ route($infraRoute, $award) }}">
        <i class="fas fa-helmet-safety" aria-hidden="true"></i>
        <span class="ci-open-text">
            <span>Infrastructure tracking</span>
            <span class="ci-open-status is-{{ $infraTone }}">@if($infraStatus === \App\Models\ContractImplementation::INFRA_COMPLETED)<i class="fas fa-check" aria-hidden="true"></i> @endif{{ $infraRecord?->isConfigured() ? $infraRecord->label() : 'Awaiting contract terms' }}</span>
        </span>
    </a>
@elseif(app(\App\Support\ContractImplementationWorkflow::class)->eligible($award) && $award->contractImplementation)
    @php
        $ciStatus = $award->contractImplementation->status;
        $ciTone = match ($ciStatus) {
            \App\Models\ContractImplementation::FOR_CORRECTION => 'warning',
            \App\Models\ContractImplementation::ACCEPTED, \App\Models\ContractImplementation::PAID, \App\Models\ContractImplementation::COMPLETED => 'success',
            default => 'info',
        };
    @endphp
    <button type="button" class="ci-open" data-ci-open="{{ $award->id }}" aria-haspopup="dialog" aria-controls="ci-dialog-{{ $award->id }}">
        <i class="fas fa-truck-ramp-box" aria-hidden="true"></i>
        <span class="ci-open-text">
            <span>Contract implementation</span>
            <span class="ci-open-status is-{{ $ciTone }}">{{ $award->contractImplementation->label() }}</span>
        </span>
    </button>
@endif
