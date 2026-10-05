@extends('layouts.portal')

@section('title', 'My purchase requests')
@section('subtitle', 'The purchase requests you filed for '.auth()->user()->office.'.')

@section('actions')
    <a href="{{ route('end-user.requests.create') }}" class="ui-btn ui-btn--primary"><i class="fas fa-plus" aria-hidden="true"></i> New purchase request</a>
@endsection

@section('content')
@php $statuses = \App\Models\ProcurementRequest::STATUSES; @endphp
<div class="ui-page">
    <section class="ui-card">
        <nav class="ui-tabs ui-tabs--inset" aria-label="Filter by status">
            <a class="ui-tab" href="{{ route('end-user.requests.index', array_filter(['q' => $search, 'year' => $year])) }}" @if(! $status) aria-current="page" @endif>All</a>
            @foreach($statuses as $key => $label)
                <a class="ui-tab" href="{{ route('end-user.requests.index', array_filter(['status' => $key, 'q' => $search, 'year' => $year])) }}" @if($status === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('end-user.requests.index') }}" class="ui-toolbar" role="search">
            @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <label class="ui-search">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <span class="sr-only">Search requests</span>
                <input type="search" name="q" value="{{ $search }}" class="ui-input" placeholder="Search by title or PR number">
            </label>
            @if($years->count() > 1)
                <div class="ui-field">
                    <label for="request-year" class="sr-only">Year</label>
                    <select id="request-year" name="year" class="ui-input" data-autosubmit>
                        <option value="">All years</option>
                        @foreach($years as $option)
                            <option value="{{ $option }}" @selected($year === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button type="submit" class="ui-btn ui-btn--secondary">Search</button>
        </form>

        @if($requests->isEmpty())
            <div class="ui-empty">
                <i class="fas fa-file-signature" aria-hidden="true"></i>
                <strong>{{ $search !== '' || $status ? 'No requests match these filters' : 'No requests yet' }}</strong>
                <span>{{ $search !== '' || $status ? 'Try another status or search.' : 'Start with what your office needs; you can save a draft and submit it later.' }}</span>
                @unless($search !== '' || $status)
                    <a href="{{ route('end-user.requests.create') }}" class="ui-btn ui-btn--primary ui-btn--sm ui-mt-sm"><i class="fas fa-plus" aria-hidden="true"></i> New purchase request</a>
                @endunless
            </div>
        @else
            <div class="ui-table-wrap">
                <table class="ui-table ui-table--stack">
                    <thead>
                        <tr>
                            <th scope="col">Request</th>
                            <th scope="col" class="is-num">Estimated total cost</th>
                            <th scope="col">Status</th>
                            <th scope="col">Updated</th>
                            <th scope="col" class="is-actions"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $item)
                            <tr>
                                <td data-label="Request">
                                    <a class="ui-cell-title" href="{{ route('end-user.requests.show', $item) }}">{{ $item->title }}</a>
                                    <span class="ui-cell-sub"><span class="ui-mono">{{ $item->reference_no }}</span> &middot; {{ $item->quantityLabel() }}</span>
                                </td>
                                <td data-label="Estimated total cost" class="is-num">{{ $item->estimated_cost !== null ? '₱'.number_format((float) $item->estimated_cost, 2) : '—' }}</td>
                                <td data-label="Status"><span class="ui-badge ui-badge--{{ $item->statusTone() }}">{{ $item->statusLabel() }}</span></td>
                                <td data-label="Updated" class="is-nowrap">{{ $item->updated_at->timezone(config('bac-office.display_timezone'))->format('M d, Y') }}</td>
                                <td data-label="Actions" class="is-actions">
                                    @if($item->isEditable())
                                        <a class="ui-btn ui-btn--secondary ui-btn--sm" href="{{ route('end-user.requests.edit', $item) }}">Edit<span class="sr-only"> {{ $item->reference_no }}</span></a>
                                    @else
                                        <a class="ui-btn ui-btn--ghost ui-btn--sm" href="{{ route('end-user.requests.show', $item) }}">Track</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <nav class="ui-pager" aria-label="Pages">
                <span>Showing {{ $requests->firstItem() }}–{{ $requests->lastItem() }} of {{ $requests->total() }}</span>
                <span class="ui-actions">
                    <a class="ui-btn ui-btn--secondary ui-btn--sm" href="{{ $requests->previousPageUrl() ?? '#' }}" @if($requests->onFirstPage()) aria-disabled="true" tabindex="-1" @endif>Previous</a>
                    <a class="ui-btn ui-btn--secondary ui-btn--sm" href="{{ $requests->nextPageUrl() ?? '#' }}" @unless($requests->hasMorePages()) aria-disabled="true" tabindex="-1" @endunless>Next</a>
                </span>
            </nav>
        @endif
    </section>
</div>
@endsection
