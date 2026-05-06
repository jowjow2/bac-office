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
                @php
                    $projectDocuments = $project->uploadedDocuments();
                    $projectModalId = 'public-project-modal-' . $project->id;
                @endphp

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

                    @if($projectDocuments->isNotEmpty())
                        @include('pages.partials.procurement-bidding-files', [
                            'project' => $project,
                            'projectDocuments' => $projectDocuments,
                            'compact' => true,
                        ])
                    @endif

                    <div class="public-card-footer">
                        <strong>P{{ number_format((float) $project->budget, 2) }}</strong>
                        <div class="public-card-actions">
                            <a href="{{ route('public.procurement.show', $project) }}" class="btn btn-outline" data-public-details-trigger="{{ $projectModalId }}">View Details</a>
                            <button type="button" class="btn login-btn-inline" onclick="openLogin()">Login to Participate</button>
                        </div>
                    </div>
                </article>

                <div id="{{ $projectModalId }}" class="public-details-modal" hidden aria-hidden="true">
                    <div class="public-details-backdrop" data-public-details-close></div>

                    <section class="public-details-dialog" role="dialog" aria-modal="true" aria-labelledby="{{ $projectModalId }}-title">
                        <button type="button" class="public-details-close" data-public-details-close aria-label="Close project details">&times;</button>

                        <div class="public-details-header">
                            <span class="public-status public-status-{{ $project->status }}">{{ ucwords(str_replace('_', ' ', $project->status)) }}</span>
                            <h2 id="{{ $projectModalId }}-title">{{ $project->title }}</h2>
                            <p>{{ $project->description ?: 'No description available yet.' }}</p>
                        </div>

                        <div class="public-details-modal-stats">
                            <div class="public-detail-stat">
                                <span>Budget</span>
                                <strong>P{{ number_format((float) $project->budget, 2) }}</strong>
                            </div>

                            <div class="public-detail-stat">
                                <span>Deadline</span>
                                <strong>{{ $project->deadline ? $project->deadline->format('M d, Y') : 'TBA' }}</strong>
                            </div>

                            <div class="public-detail-stat">
                                <span>Status</span>
                                <strong>{{ ucwords(str_replace('_', ' ', $project->status)) }}</strong>
                            </div>
                        </div>

                        @if($projectDocuments->isNotEmpty())
                            <div class="public-details-files">
                                @include('pages.partials.procurement-bidding-files', [
                                    'project' => $project,
                                    'projectDocuments' => $projectDocuments,
                                    'compact' => true,
                                    'documentLimit' => false,
                                ])
                            </div>
                        @endif

                        <div class="public-details-actions">
                            <button type="button" class="btn login-btn-inline" data-public-details-login>Login to Participate</button>
                            <button type="button" class="btn btn-outline" data-public-details-close>Close</button>
                        </div>
                    </section>
                </div>
            @empty
                <div class="public-empty-state">
                    No procurement projects matched your search yet.
                </div>
            @endforelse
        </section>
    </main>
@endsection
