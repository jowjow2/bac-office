@props(['bid'])
@php
    $sealed = $bid->isSealed();
    $closed = $bid->progress()->isClosed();
    $reviewLabel = $sealed ? 'View sealed submission' : ($bid->project?->mode()?->isCompetitive() ? 'Review bid' : 'Review quotation');
@endphp
<div class="bid-actions">
    <button type="button" class="bid-control bid-review-button" data-bid-review="{{ $bid->id }}">
        <i class="fas fa-eye" aria-hidden="true"></i> {{ $reviewLabel }}
    </button>
    <button type="button" class="bid-control bid-menu-toggle" aria-label="Actions for bid {{ $bid->id }}" aria-haspopup="menu" aria-expanded="false" aria-controls="bid-menu-{{ $bid->id }}">
        <i class="fas fa-ellipsis" aria-hidden="true"></i>
    </button>
    <div id="bid-menu-{{ $bid->id }}" class="bid-actions-menu" role="menu" aria-label="Bid {{ $bid->id }} actions" hidden>
        <button type="button" role="menuitem" data-bid-review="{{ $bid->id }}">View details</button>
        <button type="button" role="menuitem" data-bid-review="{{ $bid->id }}" data-review-section="proposal" @disabled(!$bid->proposal_url || $bid->isFinancialSealed())>View proposal</button>
        @unless($closed)
            <button type="button" role="menuitem" data-bid-review="{{ $bid->id }}" data-review-section="{{ $sealed ? 'opening' : 'decision' }}">{{ $sealed ? 'Record bid opening' : 'Record decision' }}</button>
        @endunless
        <button type="button" role="menuitem" data-bid-review="{{ $bid->id }}" data-review-section="history">Activity history</button>
    </div>
</div>
