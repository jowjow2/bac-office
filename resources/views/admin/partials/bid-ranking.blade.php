@php
    /** Abstract-of-bids ranking for the project chosen in the filter (App\Support\BidRanking). */
    $basis = app(\App\Support\BidRanking::class)->basis($rankedProject);
    $projectResults = collect($rankings)->only(\App\Models\Bid::where('project_id', $rankedProject->id)->pluck('id')->all());
    $counts = $projectResults->countBy('status');
    $pendingCount = ($counts[\App\Support\BidRanking::SEALED] ?? 0) + ($counts[\App\Support\BidRanking::PENDING] ?? 0);
    $abc = (float) ($rankedProject->budget ?? 0);
    $rule = match ($basis['key']) {
        'mearb' => 'Combined rating: '.($rankedProject->quality_price_ratio !== null ? $rankedProject->quality_price_ratio.'% technical score + '.(100 - $rankedProject->quality_price_ratio).'% price score' : 'set the quality-price ratio to rate bids').', highest first.',
        'marb', 'quality_based' => 'Technical score from the recorded evaluation, highest first.',
        'lowest_quotation' => 'Opened quotations from lowest to highest price, within the ABC.',
        default => 'Opened financial components from lowest to highest calculated price, within the ABC. Rank 1 is post-qualified next.',
    };
    $notRanked = collect([
        \App\Support\BidRanking::SEALED => 'still sealed',
        \App\Support\BidRanking::PENDING => 'awaiting score',
        \App\Support\BidRanking::ABOVE_ABC => 'above the ABC',
        \App\Support\BidRanking::BELOW_MINIMUM => 'below minimum score',
        \App\Support\BidRanking::DISQUALIFIED => 'disqualified',
    ])->filter(fn ($label, $status) => ($counts[$status] ?? 0) > 0)
        ->map(fn ($label, $status) => $counts[$status].' '.$label);
@endphp
<section class="bid-ranking {{ $projectRanking->isEmpty() ? 'is-waiting' : '' }}" aria-labelledby="bid-ranking-title">
    <header class="bid-ranking__head">
        <div>
            <p class="bid-ranking__eyebrow">Ranking &middot; {{ $rankedProject->reference_no ?: 'Project #'.$rankedProject->id }}</p>
            <h2 id="bid-ranking-title">{{ $basis['title'] }}</h2>
            <p class="bid-ranking__rule">{{ $rule }}</p>
        </div>
        <div class="bid-ranking__meta">
            <span class="bid-ranking__abc">ABC &#8369;{{ number_format($abc, 2) }}</span>
            @if($pendingCount > 0 && $projectRanking->isNotEmpty())
                <span class="bid-ranking__provisional"><i class="fas fa-hourglass-half" aria-hidden="true"></i> Provisional &middot; {{ $pendingCount }} not yet opened or scored</span>
            @endif
        </div>
    </header>

    @if($projectRanking->isEmpty())
        <div class="bid-ranking__waiting">
            <span class="bid-ranking__waiting-icon" aria-hidden="true"><i class="fas {{ $basis['uses_price'] ? 'fa-lock' : 'fa-list-check' }}"></i></span>
            <div>
                <strong>{{ $basis['uses_price'] ? 'Financial bids are still sealed' : 'Technical scoring is still in progress' }}</strong>
                <p>{{ $basis['uses_price'] ? 'Review technical documents first. After approval and PIN verification, opened prices appear here in rank order.' : 'Record the required technical scores to show the ranking.' }}</p>
            </div>
            @if($pendingCount > 0)<span class="bid-ranking__waiting-count">{{ $pendingCount }} pending</span>@endif
        </div>
    @else
        <ol class="bid-ranking__list">
            @foreach($projectRanking as $rankedBid)
                @php
                    $result = $rankings[$rankedBid->id];
                    $name = $rankedBid->user?->company ?: ($rankedBid->user?->name ?? 'N/A');
                    $priceOpen = ! $rankedBid->isFinancialSealed();
                    $variance = $priceOpen && $abc > 0 ? (((float) $rankedBid->amount - $abc) / $abc) * 100 : null;
                @endphp
                <li class="bid-ranking__row {{ $result['rank'] === 1 ? 'is-top' : '' }}">
                    <span class="bid-ranking__no" aria-label="Rank {{ $result['rank'] }}">{{ $result['rank'] }}</span>
                    <div class="bid-ranking__bidder">
                        <strong title="{{ $name }}">{{ $name }}</strong>
                        <span>{{ $result['rank'] === 1 ? $basis['top'] : 'Rank '.$result['rank'].' of '.$result['of'] }}{{ $result['tied'] ? ' · tie, apply the tie-breaking method' : '' }}</span>
                    </div>
                    <div class="bid-ranking__figure">
                        @if($basis['key'] === 'mearb')
                            <strong>{{ number_format((float) $result['score'], 2) }}</strong>
                            <span>Rating &middot; tech {{ rtrim(rtrim((string) $rankedBid->technical_score, '0'), '.') }}</span>
                        @elseif($basis['uses_score'])
                            <strong>{{ rtrim(rtrim((string) $rankedBid->technical_score, '0'), '.') }}</strong>
                            <span>Technical score</span>
                        @else
                            <strong>&#8369;{{ number_format((float) $rankedBid->amount, 2) }}</strong>
                            <span>{{ $variance !== null ? number_format($variance, 2).'% vs ABC' : 'Calculated price' }}</span>
                        @endif
                    </div>
                    <div class="bid-ranking__stage"><x-bid-status-badge :bid="$rankedBid" /></div>
                    <button type="button" class="bid-control bid-ranking__action" data-bid-review="{{ $rankedBid->id }}">
                        <i class="fas fa-arrow-right" aria-hidden="true"></i> Review
                    </button>
                </li>
            @endforeach
        </ol>
    @endif

    @if($notRanked->isNotEmpty() && $projectRanking->isNotEmpty())
        <p class="bid-ranking__foot">Not ranked: {{ $notRanked->implode(' · ') }}.</p>
    @endif
</section>
