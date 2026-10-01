@extends('layouts.portal')

@section('title', 'Purchase requests overview')
@section('subtitle', $office.' · File purchase requests and follow each one through the PPMP/APP check, bidding or RFQ, award and acceptance.')

@section('actions')
    <a href="{{ route('end-user.requests.create') }}" class="ui-btn ui-btn--primary"><i class="fas fa-plus" aria-hidden="true"></i> New purchase request</a>
@endsection

@section('content')
    <section class="ui-kpis" aria-label="Request summary">
        <a class="ui-kpi" href="{{ route('end-user.requests.index', ['status' => 'draft']) }}">
            <span class="ui-kpi__label">Drafts</span>
            <span class="ui-kpi__value">{{ $counts['drafts'] }}</span>
            <span class="ui-kpi__foot">Not yet submitted</span>
        </a>
        <a class="ui-kpi {{ $counts['returned'] > 0 ? 'ui-kpi--warning' : '' }}" href="{{ route('end-user.requests.index', ['status' => 'returned']) }}">
            <span class="ui-kpi__label">Returned for correction</span>
            <span class="ui-kpi__value">{{ $counts['returned'] }}</span>
            <span class="ui-kpi__foot">See the reviewer's remarks</span>
        </a>
        <a class="ui-kpi" href="{{ route('end-user.requests.index', ['status' => 'submitted']) }}">
            <span class="ui-kpi__label">In review / with the BAC</span>
            <span class="ui-kpi__value">{{ $counts['review'] }}</span>
            <span class="ui-kpi__foot">PPMP/APP and funds check</span>
        </a>
        <a class="ui-kpi" href="{{ route('end-user.requests.index', ['status' => 'in_procurement']) }}">
            <span class="ui-kpi__label">In procurement</span>
            <span class="ui-kpi__value">{{ $counts['procurement'] }}</span>
            <span class="ui-kpi__foot">Bidding, RFQ, award or delivery</span>
        </a>
    </section>

    <section class="ui-card" aria-labelledby="eu-action-title">
        <header class="ui-card__head">
            <div>
                <h2 class="ui-card__title" id="eu-action-title">Needs your action</h2>
                <p class="ui-card__desc">Drafts to complete and requests returned by the reviewer.</p>
            </div>
        </header>
        @if($needsAction->isEmpty())
            <div class="ui-empty">
                <i class="fas fa-circle-check" aria-hidden="true"></i>
                <strong>Nothing to do right now</strong>
                <span>Requests that need changes will appear here.</span>
            </div>
        @else
            <ul class="ui-feed">
                @foreach($needsAction as $item)
                    <li class="ui-feed__item ui-feed__item--{{ $item->status === 'returned' ? 'danger' : 'info' }}">
                        <span class="ui-feed__dot" aria-hidden="true"></span>
                        <div class="ui-row">
                            <div class="ui-row__main">
                                <p class="ui-feed__title"><a class="ui-link" href="{{ route('end-user.requests.show', $item) }}">{{ $item->title }}</a></p>
                                <p class="ui-feed__detail"><span class="ui-mono">{{ $item->reference_no }}</span> · <span class="ui-badge ui-badge--{{ $item->statusTone() }}">{{ $item->statusLabel() }}</span></p>
                                @if($item->status === 'returned' && $item->review_remarks)
                                    <p class="ui-feed__detail">Reviewer: {{ $item->review_remarks }}</p>
                                @endif
                            </div>
                            <a class="ui-btn ui-btn--secondary ui-btn--sm" href="{{ route('end-user.requests.edit', $item) }}">{{ $item->status === 'returned' ? 'Correct' : 'Continue' }}<span class="sr-only"> {{ $item->reference_no }}</span></a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div class="ui-grid ui-grid--sidebar">
        @include('partials.portal.register', ['routeName' => 'end-user.dashboard', 'title' => 'My requests and procurements', 'showOffice' => false])
        @include('partials.portal.upcoming', ['upcomingTitle' => 'Coming up for your office'])
    </div>
@endsection
