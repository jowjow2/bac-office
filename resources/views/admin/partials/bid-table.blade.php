<div class="bid-scroll-frame">
    <div class="bid-scroll-container" tabindex="0" role="region" aria-label="Bid management table" data-bid-scroll>
        <table class="bid-management-table" data-native-scroll>
            <caption class="bid-sr-only">Procurement submissions, document status, stages, and next actions</caption>
            <colgroup>
                <col class="bid-col-project"><col class="bid-col-bidder"><col class="bid-col-submitted">
                <col class="bid-col-amount"><col class="bid-col-documents"><col class="bid-col-stage"><col class="bid-col-action">
            </colgroup>
            <thead>
                <tr>
                    <th scope="col">Project / mode</th>
                    <th scope="col">Bidder</th>
                    <th scope="col">Submitted / receipt</th>
                    <th scope="col" class="bid-numeric">Bid amount</th>
                    <th scope="col">Documents</th>
                    <th scope="col">Stage</th>
                    <th scope="col" class="bid-actions-cell">Next action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bids as $bid)
                    <x-bid-table-row :bid="$bid" :ranking="$rankings[$bid->id] ?? null" />
                @empty
                    <tr>
                        <td colspan="7" class="bid-empty">
                            <div class="bid-empty-state">
                                <i class="fas fa-inbox" aria-hidden="true"></i>
                                <strong>No bids found for these filters</strong>
                                <span>Try a different search, procurement mode, stage, or document status.</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($bids->hasPages())
<footer class="bid-pagination">
    <nav class="bid-pagination-nav" aria-label="Bid pagination">
        @if($bids->onFirstPage())
            <span class="bid-pagination-control is-disabled" aria-disabled="true"><i class="fas fa-angle-left" aria-hidden="true"></i> Previous</span>
        @else
            <a class="bid-pagination-control" href="{{ $bids->previousPageUrl() }}" rel="prev"><i class="fas fa-angle-left" aria-hidden="true"></i> Previous</a>
        @endif
        <span class="bid-pagination-pages">
            @foreach($bids->getUrlRange(1, $bids->lastPage()) as $page => $url)
                @if($page === $bids->currentPage())
                    <span class="bid-pagination-page is-current" aria-current="page">{{ $page }}</span>
                @else
                    <a class="bid-pagination-page" href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach
        </span>
        @if($bids->hasMorePages())
            <a class="bid-pagination-control" href="{{ $bids->nextPageUrl() }}" rel="next">Next <i class="fas fa-angle-right" aria-hidden="true"></i></a>
        @else
            <span class="bid-pagination-control is-disabled" aria-disabled="true">Next <i class="fas fa-angle-right" aria-hidden="true"></i></span>
        @endif
    </nav>
</footer>
@endif