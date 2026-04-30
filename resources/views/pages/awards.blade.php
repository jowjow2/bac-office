@extends('layouts.public')

@section('title', 'Awards & Contracts')
@section('body_class', 'public-page')

@section('content')
    <main class="public-shell">
        <section class="public-page-hero">
            <p class="public-page-kicker">Awards & Contracts</p>
            <h1>Recently awarded procurement projects</h1>
            <p>
                See awarded contracts, project titles, winning bidders, and contract values published through BAC-Office.
            </p>
        </section>

        <section class="public-results-bar">
            <p>
                @if($query !== '')
                    Results for "<strong>{{ $query }}</strong>"
                @else
                    Showing the latest awarded contracts
                @endif
            </p>
            <span>{{ $awards->count() }} award{{ $awards->count() === 1 ? '' : 's' }}</span>
        </section>

        <section class="public-card-grid">
            @forelse($awards as $award)
                @php
                    $winner = $award->bid?->user?->company ?: ($award->bid?->user?->name ?? 'N/A');
                @endphp
                <article class="public-card">
                    <div class="public-card-meta">
                        <span class="public-status public-status-awarded">{{ ucfirst($award->status) }}</span>
                        <span>{{ $award->contract_date?->format('M d, Y') ?? 'TBA' }}</span>
                    </div>

                    <h2>{{ $award->project?->title ?? 'Untitled Project' }}</h2>
                    <p>Winning bidder: {{ $winner }}</p>

                    <div class="public-card-footer">
                        <strong>P{{ number_format((float) $award->contract_amount, 2) }}</strong>
                        <a href="{{ route('public.procurement') }}" class="btn btn-outline">View Projects</a>
                    </div>
                </article>
            @empty
                <div class="public-empty-state">
                    No awarded contracts matched your search yet.
                </div>
            @endforelse
        </section>
    </main>
@endsection
