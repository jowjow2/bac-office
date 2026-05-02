@extends('layouts.public')

@section('title', 'Public Procurement')
@section('body_class', 'public-page')

@section('content')
    <main class="public-shell">
        <section class="public-page-hero">
            <p class="public-page-kicker">Procurement</p>
            <h1>Open and tracked procurement projects</h1>
            <p>
                Browse BAC-Office projects, budgets, deadlines, and current procurement status in one public-facing list.
            </p>
        </section>

        <section class="public-results-bar">
            <p>
                @if($query !== '')
                    Results for "<strong>{{ $query }}</strong>"
                @else
                    Showing the latest procurement projects
                @endif
            </p>
            <span>{{ $projects->count() }} project{{ $projects->count() === 1 ? '' : 's' }}</span>
        </section>

        <section class="public-card-grid">
            @forelse($projects as $project)
                <article class="public-card">
                    <div class="public-card-meta">
                        <span class="public-status public-status-{{ $project->status }}">{{ ucfirst($project->status) }}</span>
                        <span>
                            Deadline:
                            {{ $project->deadline ? $project->deadline->format('M d, Y') : 'TBA' }}
                        </span>
                    </div>

                    <h2>{{ $project->title }}</h2>
                    <p>{{ $project->description ?: 'No description available yet.' }}</p>

                    <div class="public-card-footer">
                        <strong>P{{ number_format((float) $project->budget, 2) }}</strong>
                        <div class="public-card-actions">
                            <a href="{{ route('public.procurement.show', $project) }}" class="btn btn-outline">View Details</a>
                            <button type="button" class="btn login-btn-inline" onclick="openLogin()">Login to Participate</button>
                        </div>
                    </div>
                </article>
            @empty
                <div class="public-empty-state">
                    No procurement projects matched your search yet.
                </div>
            @endforelse
        </section>
    </main>
@endsection
