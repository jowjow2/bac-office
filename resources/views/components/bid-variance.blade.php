@props(['bid'])
@php
    $budget = (float) ($bid->project?->budget ?? 0);
    // The variance reveals the price, so it follows the financial seal.
    $variance = $budget > 0 && ! $bid->isFinancialSealed() ? (((float) $bid->amount - $budget) / $budget) * 100 : null;
    $color = match (true) {
        $variance === null => 'muted',
        $variance <= 0 => 'green',
        $variance <= 10 => 'amber',
        default => 'red',
    };
@endphp
<span class="bid-variance is-{{ $color }}">
    {{ $variance === null ? 'N/A' : number_format($variance, 1) . '%' }}
    @if($variance !== null && ($variance > 500 || $variance < -90))
        <button type="button" class="bid-icon-button bid-variance-warning" data-bid-tooltip="Unusual variance — verify budget/bid amount" aria-label="Unusual variance — verify budget/bid amount">
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
        </button>
    @endif
</span>
