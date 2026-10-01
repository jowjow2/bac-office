{{-- Opens this award's Contract Implementation modal (partials.contract-implementation-dialogs). --}}
@if(app(\App\Support\ContractImplementationWorkflow::class)->eligible($award) && $award->contractImplementation)
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
