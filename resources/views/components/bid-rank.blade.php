@props(['ranking' => null, 'compact' => false])
@php
    // Abstract-of-bids position from App\Support\BidRanking; nothing when not computed.
    $status = $ranking['status'] ?? null;
    $isTop = $status === \App\Support\BidRanking::RANKED && $ranking['rank'] === 1;
    $tone = match ($status) {
        \App\Support\BidRanking::RANKED => $isTop ? 'is-top' : 'is-ranked',
        \App\Support\BidRanking::ABOVE_ABC, \App\Support\BidRanking::BELOW_MINIMUM, \App\Support\BidRanking::DISQUALIFIED => 'is-out',
        default => 'is-waiting',
    };
    $title = $status === \App\Support\BidRanking::RANKED
        ? (($isTop ? $ranking['basis']['top'].' · ' : '').$ranking['basis']['measure'].' · rank '.$ranking['rank'].' of '.$ranking['of'].($ranking['tied'] ? ' (tie)' : '').($ranking['provisional'] ? ' · provisional: '.$ranking['pending_count'].' still sealed or unscored' : ''))
        : ($ranking['label'] ?? '');
@endphp
@if($status && $status !== \App\Support\BidRanking::SEALED)
    <span {{ $attributes->class(['bid-rank', $tone]) }} title="{{ $title }}">
        @if($status === \App\Support\BidRanking::RANKED)
            <span class="bid-rank__no">#{{ $ranking['rank'] }}</span>
            @if($compact)
                {{-- Narrow table cell: position only; the full wording is in the title. --}}
                <span class="bid-rank__text">of {{ $ranking['of'] }}{{ $ranking['tied'] ? ' · tie' : '' }}</span>
            @else
                <span class="bid-rank__text">{{ $isTop ? $ranking['basis']['top'] : 'of '.$ranking['of'] }}{{ $ranking['tied'] ? ' · tie' : '' }}{{ $ranking['provisional'] ? ' · provisional' : '' }}</span>
            @endif
        @else
            <span class="bid-rank__text">{{ $ranking['label'] }}</span>
        @endif
    </span>
@endif
