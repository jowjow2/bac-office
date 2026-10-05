{{--
    The procurement register table (admin, staff, end-user dashboards).
    Expects: $rows (LengthAwarePaginator of ProcurementPipeline rows),
    $filters, $routeName, and optional $title / $showOffice.
--}}
@use('App\Support\ProcurementPipeline')
@php
    $bucketLabel = $filters['stage'] ? ProcurementPipeline::BUCKETS[$filters['stage']]['label'] : null;
    $flagLabel = $filters['flag'] ? ProcurementPipeline::FLAGS[$filters['flag']] : null;
    $heading = $title ?? ($flagLabel ?? $bucketLabel ?? ($filters['state'] === 'active' ? 'Active procurements' : ProcurementPipeline::STATES[$filters['state']]));
    $hasFilters = filled($filters['q']) || $filters['stage'] || $filters['mode'] || $filters['flag'] || $filters['state'] !== 'active';
    $showOffice = $showOffice ?? true;
@endphp

<section class="ui-card" aria-labelledby="register-title">
    <header class="ui-card__head">
        <div>
            <h2 class="ui-card__title" id="register-title">{{ $heading }} · <span class="ui-num">{{ $rows->total() }}</span></h2>
            <p class="ui-card__desc">Sorted by the next scheduled date. Open a reference for its full record.</p>
        </div>
        @if($hasFilters)
            <a href="{{ route($routeName) }}" class="ui-btn ui-btn--ghost ui-btn--sm"><i class="fas fa-xmark" aria-hidden="true"></i> Clear filters</a>
        @endif
    </header>

    {{-- The procurement stages as filters of this list (overview only). --}}
    @if($stageTabs ?? false)
        @php
            $keep = array_filter(['mode' => $filters['mode'], 'q' => $filters['q'] ?: null, 'state' => $filters['state'] !== 'active' ? $filters['state'] : null]);
            $allCount = array_sum(array_map('intval', $buckets));
        @endphp
        <nav class="ui-stage-tabs" aria-label="Filter by stage">
            <a href="{{ route($routeName, $keep) }}" class="ui-stage-tab" @unless($filters['stage']) aria-current="true" @endunless>All <span>{{ number_format($allCount) }}</span></a>
            @foreach(ProcurementPipeline::BUCKETS as $key => $bucket)
                @php $count = (int) ($buckets[$key] ?? 0); @endphp
                <a href="{{ route($routeName, $keep + ['stage' => $key]) }}" class="ui-stage-tab {{ $count === 0 ? 'is-empty' : '' }}" title="{{ $bucket['hint'] }}" @if($filters['stage'] === $key) aria-current="true" @endif>{{ $bucket['label'] }} <span>{{ number_format($count) }}</span></a>
            @endforeach
        </nav>
    @endif

    <form method="GET" action="{{ route($routeName) }}" class="ui-toolbar" role="search" aria-label="Filter the register">
        <div class="ui-search">
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
            <label for="register-q" class="sr-only">Search</label>
            <input type="search" id="register-q" name="q" value="{{ $filters['q'] }}" class="ui-input" placeholder="Search reference, PhilGEPS no., title, office">
        </div>
        <div class="ui-field">
            <label for="register-mode" class="sr-only">Mode of procurement</label>
            <select id="register-mode" name="mode" class="ui-input" data-autosubmit>
                <option value="">All modes</option>
                @foreach(ProcurementPipeline::modeOptions() as $value => $label)
                    <option value="{{ $value }}" @selected($filters['mode'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="ui-field">
            <label for="register-state" class="sr-only">Status</label>
            <select id="register-state" name="state" class="ui-input" data-autosubmit>
                @foreach(ProcurementPipeline::STATES as $value => $label)
                    <option value="{{ $value }}" @selected($filters['state'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @if($filters['stage'])
            <input type="hidden" name="stage" value="{{ $filters['stage'] }}">
        @endif
        @if($filters['flag'])
            <input type="hidden" name="flag" value="{{ $filters['flag'] }}">
        @endif
        <button type="submit" class="ui-btn ui-btn--secondary">Apply</button>
    </form>

    <div class="ui-table-wrap">
        <table class="ui-table ui-table--stack">
            <caption class="sr-only">{{ $heading }}</caption>
            <thead>
                <tr>
                    <th scope="col">Reference</th>
                    <th scope="col">Description</th>
                    @if($showOffice)
                        <th scope="col">End-user</th>
                    @endif
                    <th scope="col">Mode</th>
                    <th scope="col" class="is-num">ABC (₱)</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td data-label="Reference">
                            @if($row['url'])
                                <a href="{{ $row['url'] }}" class="ui-ref">{{ $row['reference'] }}</a>
                            @else
                                <span class="ui-mono">{{ $row['reference'] }}</span>
                            @endif
                        </td>
                        <td data-label="Description">
                            <span class="ui-cell-title">{{ $row['title'] }}</span>
                            <span class="ui-cell-sub">{{ $row['stage_label'] }}@if($row['office']) · {{ $row['office'] }}@endif</span>
                        </td>
                        @if($showOffice)
                            <td data-label="End-user">{{ $row['end_user'] ?: '—' }}</td>
                        @endif
                        <td data-label="Mode">
                            @if($row['mode_family'])
                                <span class="ui-mode ui-mode--{{ $row['mode_family'] }}" title="{{ $row['mode_label'] }} · {{ $row['legal_basis'] }}">{{ $row['mode_short'] }}</span>
                            @else
                                <span class="ui-muted" title="Mode of procurement not set yet">—</span>
                            @endif
                        </td>
                        <td data-label="ABC (₱)" class="is-num">{{ $row['abc'] > 0 ? number_format($row['abc'], 2) : '—' }}</td>
                        <td data-label="Status"><span class="ui-pill ui-pill--{{ $row['status']['tone'] }}">{{ $row['status']['label'] }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showOffice ? 6 : 5 }}">
                            <div class="ui-empty">
                                <i class="fas fa-folder-open" aria-hidden="true"></i>
                                <strong>No records match</strong>
                                <span>{{ $hasFilters ? 'Try another stage, mode or search term.' : 'Purchase requests and projects will appear here.' }}</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($rows->hasPages())
        <nav class="ui-pager" aria-label="Register pages">
            <span>Showing {{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ $rows->total() }}</span>
            <span class="ui-actions">
                @if($rows->onFirstPage())
                    <span class="ui-btn ui-btn--secondary ui-btn--sm" aria-disabled="true">Previous</span>
                @else
                    <a href="{{ $rows->previousPageUrl() }}" class="ui-btn ui-btn--secondary ui-btn--sm" rel="prev">Previous</a>
                @endif
                @if($rows->hasMorePages())
                    <a href="{{ $rows->nextPageUrl() }}" class="ui-btn ui-btn--secondary ui-btn--sm" rel="next">Next</a>
                @else
                    <span class="ui-btn ui-btn--secondary ui-btn--sm" aria-disabled="true">Next</span>
                @endif
            </span>
        </nav>
    @endif
</section>
