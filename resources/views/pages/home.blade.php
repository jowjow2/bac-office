@extends('layouts.public')

@section('title', 'Home')
@section('body_class', 'public-page home-page')

@push('pre_app_styles')
    @vite('resources/css/home.css')
@endpush

@section('content')
    @php
        $featuredProject = $latestProjects->first();
        $projectFeed = $latestProjects->take(3);
        $featuredAward = $latestAwards->first();
        $featuredAwardee = $featuredAward && $featuredAward->bid && $featuredAward->bid->user
            ? ($featuredAward->bid->user->company ?: $featuredAward->bid->user->name)
            : 'Award notice details will appear here once a contract has been recorded.';
    @endphp

    <main class="public-shell home-shell">
        <section class="public-page-hero home-page-hero" data-home-reveal="up">
            <p class="public-page-kicker">Home</p>
            <h1>BAC-Office public procurement and awards portal</h1>
            <p>
                Browse active procurement opportunities, review published award notices, and use the bidder portal
                when you are ready to participate.
            </p>

            <div class="home-hero-actions">
                <a href="{{ route('public.procurement') }}" class="btn">Browse Procurement</a>
                <a href="{{ route('public.awards') }}" class="btn btn-outline">View Awards</a>
                <button type="button" class="login-btn" onclick="openLogin()">Login</button>
            </div>
        </section>

        <section class="home-stats-grid public-card-grid" data-home-reveal="up">
            <article class="public-card home-stat-card">
                <div class="public-card-meta">
                    <span class="public-status public-status-open">Projects</span>
                </div>
                <h2>{{ number_format($publicProjectsCount) }}</h2>
                <p>Public procurement projects currently visible through BAC-Office.</p>
            </article>

            <article class="public-card home-stat-card">
                <div class="public-card-meta">
                    <span class="public-status public-status-open">Open</span>
                </div>
                <h2>{{ number_format($openProjectsCount) }}</h2>
                <p>Opportunities still open for review and bidder participation.</p>
            </article>

            <article class="public-card home-stat-card">
                <div class="public-card-meta">
                    <span class="public-status public-status-awarded">Awards</span>
                </div>
                <h2>{{ number_format($awardedContractsCount) }}</h2>
                <p>Published awards and contracts already recorded in the system.</p>
            </article>
        </section>

        <section class="home-highlight-grid" data-home-reveal="up">
            <article class="public-detail-card home-highlight-card">
                <p class="public-page-kicker">Featured Procurement</p>
                <h2>{{ $featuredProject?->title ?? 'No public procurement notice yet' }}</h2>
                <p>
                    @if($featuredProject)
                        {{ \Illuminate\Support\Str::limit($featuredProject->description ?: 'Project details will appear here after publication.', 180) }}
                    @else
                        Newly published procurement notices will appear here together with budget and deadline details.
                    @endif
                </p>

                <div class="home-highlight-stats">
                    <div class="public-detail-stat">
                        <span>Budget</span>
                        <strong>
                            @if($featuredProject && $featuredProject->budget !== null)
                                P{{ number_format((float) $featuredProject->budget, 2) }}
                            @else
                                TBA
                            @endif
                        </strong>
                    </div>

                    <div class="public-detail-stat">
                        <span>Deadline</span>
                        <strong>{{ $featuredProject && $featuredProject->deadline ? $featuredProject->deadline->format('M d, Y') : 'TBA' }}</strong>
                    </div>

                    <div class="public-detail-stat">
                        <span>Status</span>
                        <strong>{{ $featuredProject ? ucwords(str_replace('_', ' ', $featuredProject->status)) : 'Pending' }}</strong>
                    </div>
                </div>

                <div class="home-highlight-actions">
                    <a href="{{ $featuredProject ? route('public.procurement.show', $featuredProject) : route('public.procurement') }}" class="btn">
                        Open Procurement
                    </a>
                </div>
            </article>

            <article class="public-detail-card home-highlight-card">
                <p class="public-page-kicker">Latest Award Notice</p>
                <h2>{{ $featuredAward?->project?->title ?? 'No award notice yet' }}</h2>
                <p>{{ $featuredAwardee }}</p>

                <div class="home-highlight-stats">
                    <div class="public-detail-stat">
                        <span>Contract Amount</span>
                        <strong>
                            @if($featuredAward)
                                P{{ number_format((float) $featuredAward->contract_amount, 2) }}
                            @else
                                TBA
                            @endif
                        </strong>
                    </div>

                    <div class="public-detail-stat">
                        <span>Date</span>
                        <strong>{{ $featuredAward && $featuredAward->contract_date ? $featuredAward->contract_date->format('M d, Y') : 'Pending' }}</strong>
                    </div>
                </div>

                <div class="home-highlight-actions">
                    <a href="{{ route('public.awards') }}" class="btn btn-outline">Open Awards</a>
                </div>
            </article>
        </section>

        <section class="public-detail-card home-slider-panel" data-home-reveal="up">
            <div class="home-slider-header">
                <div>
                    <p class="public-page-kicker">Office Highlights</p>
                    <h2>BAC-Office activity slider</h2>
                    <p>
                        Browse documented office activity, procurement events, and BAC-Office visual highlights.
                    </p>
                </div>
            </div>

            <div class="carousel-container">
                <button type="button" class="carousel-btn left" aria-label="Previous slide">&lsaquo;</button>

                <div class="carousel-frame">
                    <img id="carouselImage" src="{{ asset('Images/slider1.png') }}" alt="BAC Office activity highlights">

                    <div class="carousel-overlay">
                        <div class="carousel-overlay-copy">
                            <span id="carouselStepIndicator" class="carousel-step-indicator">01 / 06</span>
                            <h3 id="carouselCaptionTitle">Bid opening and committee activity</h3>
                            <p id="carouselCaptionMeta">
                                Bid openings, committee sessions, and procurement activities presented with clear public-facing captions.
                            </p>
                        </div>

                        <div id="carouselDots" class="carousel-dots" aria-label="Carousel pagination"></div>
                    </div>
                </div>

                <button type="button" class="carousel-btn right" aria-label="Next slide">&rsaquo;</button>
            </div>
        </section>

        <section data-home-reveal="up">
            <div class="public-results-bar home-results-bar">
                <p>Showing the latest procurement projects</p>
                <a href="{{ route('public.procurement') }}" class="public-card-link">View all procurement</a>
            </div>

            <section class="public-card-grid">
                @forelse($projectFeed as $project)
                    <article class="public-card">
                        <div class="public-card-meta">
                            <span class="public-status public-status-{{ $project->status }}">{{ ucwords(str_replace('_', ' ', $project->status)) }}</span>
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
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="public-empty-state">
                        No public procurement notices have been posted yet.
                    </div>
                @endforelse
            </section>
        </section>
    </main>
@endsection
