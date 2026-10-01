@props(['bid', 'prefix' => '₱'])
{{-- Financial component: sealed until the bid opening and a passed preliminary examination. --}}
@if($bid->isFinancialSealed())
    <span {{ $attributes->merge(['class' => 'bid-amount-sealed']) }} title="Financial component sealed"><i class="fas fa-lock" aria-hidden="true"></i> Sealed</span>
@else
    <span {{ $attributes }}>{{ $prefix }}{{ number_format((float) $bid->bid_amount, 2) }}</span>
@endif
