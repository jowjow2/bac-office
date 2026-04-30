@extends('layouts.public')

@section('title', 'Home')
@section('body_class', 'home-page')

@push('pre_app_styles')
    @vite('resources/css/home.css')
@endpush

@section('content')
    @php
        $featuredProject = $latestProjects->first();
        $projectFeed = $latestProjects->take(3);
        $featuredAward = $latestAwards->first();
        $portalLanes = [
            [
                'eyebrow' => 'Procurement notices',
                'title' => 'Review active invitations to bid',
                'description' => 'Open public project pages with approved budgets, posting dates, deadlines, and status updates.',
                'action' => 'Browse opportunities',
                'url' => route('public.procurement'),
                'accent' => 'amber',
            ],
            [
                'eyebrow' => 'Award notices',
                'title' => 'Track published contract results',
                'description' => 'Review recent awards, winning suppliers, and recorded contract amounts in one public listing.',
                'action' => 'View awards',
                'url' => route('public.awards'),
                'accent' => 'navy',
            ],
            [
                'eyebrow' => 'Bidder portal',
                'title' => 'Sign in when you are ready to participate',
                'description' => 'Registered suppliers can manage company records, upload requirements, and submit bids online.',
                'action' => 'Bidder login',
                'url' => null,
                'accent' => 'sky',
            ],
            [
                'eyebrow' => 'Public assistance',
                'title' => 'Find BAC-Office information and support links',
                'description' => 'Direct visitors to BAC-Office background, contact channels, and guidance for public inquiries.',
                'action' => 'Contact BAC-Office',
                'url' => url('/contact'),
                'accent' => 'sand',
            ],
        ];
        $howItWorksSteps = [
            [
                'number' => '01',
                'title' => 'Review posted opportunities',
                'description' => 'Start with the public procurement list to check which projects are currently open or recently published.',
            ],
            [
                'number' => '02',
                'title' => 'Read schedules and requirements',
                'description' => 'Open the notice details to confirm the approved budget, scope of work, and relevant procurement dates.',
            ],
            [
                'number' => '03',
                'title' => 'Use the bidder portal',
                'description' => 'Registered companies can sign in to maintain documents, monitor submissions, and take action securely.',
            ],
            [
                'number' => '04',
                'title' => 'Monitor notices and awards',
                'description' => 'Follow award postings and other outcomes so suppliers and the public can stay informed after bidding.',
            ],
        ];
    @endphp

    <main class="home-main">
        <section class="hero" id="homepage-hero">
            <div class="hero-aura hero-aura-left"></div>
            <div class="hero-aura hero-aura-right"></div>

            <div class="hero-shell" data-home-reveal="up">
                <div class="hero-copy">
                    <div class="hero-brand-lockup">
                        <div class="hero-brand-mark">
                            <img src="{{ asset('Images/Logo2.png') }}" alt="BAC-Office logo">
                        </div>

                        <div class="hero-brand-copy">
                            <p class="hero-kicker">Bids and Awards Committee</p>
                            <strong>Official BAC-Office Procurement Portal</strong>
                        </div>
                    </div>

                    <h1 class="hero-title">
                        Public procurement notices, award updates, and bidder access in one clear official portal.
                    </h1>

                    <p class="hero-subtitle">
                        Review current opportunities, check recent contract awards, and move into the bidder portal
                        from one clear starting point for public procurement information.
                    </p>

                    <div class="hero-buttons">
                        <a href="{{ route('public.procurement') }}" class="hero-btn primary">
                            Browse Opportunities
                        </a>

                        <a href="{{ route('public.awards') }}" class="hero-btn secondary">
                            View Award Notices
                        </a>

                        <button type="button" class="hero-btn tertiary" onclick="openLogin()">
                            Bidder Login
                        </button>
                    </div>

                    <div class="hero-ribbon" aria-label="Homepage quick highlights">
                        <span>Open invitations to bid</span>
                        <span>Published award notices</span>
                        <span>Secure supplier access</span>
                    </div>

                    <div class="hero-trust-strip">
                        <article class="hero-trust-card">
                            <span>Public projects</span>
                            <strong>{{ number_format($publicProjectsCount) }}</strong>
                            <p>Projects currently visible through the public procurement pages.</p>
                        </article>

                        <article class="hero-trust-card">
                            <span>Open opportunities</span>
                            <strong>{{ number_format($openProjectsCount) }}</strong>
                            <p>Live opportunities still available for supplier review and participation.</p>
                        </article>

                        <article class="hero-trust-card">
                            <span>Award notices</span>
                            <strong>{{ number_format($awardedContractsCount) }}</strong>
                            <p>Published contract outcomes already recorded in the portal.</p>
                        </article>
                    </div>
                </div>

                <aside class="hero-showcase" aria-label="BAC-Office homepage showcase">
                    <article class="showcase-card showcase-primary">
                        <div class="showcase-header">
                            <div>
                                <p class="hero-panel-label">Current public notice</p>
                                <h2>{{ $featuredProject?->title ?? 'No active public notice yet' }}</h2>
                            </div>

                            @if($featuredProject)
                                <span class="public-status public-status-{{ $featuredProject->status }}">
                                    {{ ucwords(str_replace('_', ' ', $featuredProject->status)) }}
                                </span>
                            @endif
                        </div>

                        <p class="showcase-copy">
                            @if($featuredProject)
                                {{ \Illuminate\Support\Str::limit($featuredProject->description ?: 'Project details will appear once the procurement entry is published.', 180) }}
                            @else
                                The most recent public procurement notice will appear here with budget, deadline, and bid activity.
                            @endif
                        </p>

                        <div class="showcase-metrics">
                            <div>
                                <span>Budget</span>
                                <strong>
                                    @if($featuredProject && $featuredProject->budget !== null)
                                        P{{ number_format((float) $featuredProject->budget, 2) }}
                                    @else
                                        TBA
                                    @endif
                                </strong>
                            </div>

                            <div>
                                <span>Deadline</span>
                                <strong>
                                    {{ $featuredProject && $featuredProject->deadline ? $featuredProject->deadline->format('M d, Y') : 'To be announced' }}
                                </strong>
                            </div>

                            <div>
                                <span>Submitted bids</span>
                                <strong>{{ number_format($featuredProject?->bids_count ?? 0) }}</strong>
                            </div>
                        </div>

                        @if($featuredProject)
                            <a href="{{ route('public.procurement.show', $featuredProject) }}" class="hero-inline-link">
                                Open notice details
                            </a>
                        @else
                            <a href="{{ route('public.procurement') }}" class="hero-inline-link">
                                Browse public notices
                            </a>
                        @endif
                    </article>

                    <div class="showcase-secondary-grid">
                        <article class="showcase-card showcase-stats">
                            <p class="hero-panel-label">Live overview</p>

                            <div class="showcase-stat-list">
                                <div>
                                    <span>Public postings</span>
                                    <strong>{{ number_format($publicProjectsCount) }}</strong>
                                </div>

                                <div>
                                    <span>Recorded awards</span>
                                    <strong>{{ number_format($awardedContractsCount) }}</strong>
                                </div>

                                <div>
                                    <span>Total award value</span>
                                    <strong>P{{ number_format($totalAwardedValue, 2) }}</strong>
                                </div>
                            </div>
                        </article>

                        <article class="showcase-card showcase-award">
                            <p class="hero-panel-label">Latest award notice</p>

                            <h3>{{ $featuredAward?->project?->title ?? 'Awaiting published awards' }}</h3>

                            <p>
                                @if($featuredAward && $featuredAward->bid && $featuredAward->bid->user)
                                    {{ $featuredAward->bid->user->company ?: $featuredAward->bid->user->name }}
                                @else
                                    The latest winning supplier and contract amount will appear here once an award is recorded.
                                @endif
                            </p>

                            <div class="showcase-award-footer">
                                <strong>
                                    @if($featuredAward)
                                        P{{ number_format((float) $featuredAward->contract_amount, 2) }}
                                    @else
                                        No award yet
                                    @endif
                                </strong>
                                <a href="{{ route('public.awards') }}" class="hero-inline-link">Open awards register</a>
                            </div>
                        </article>
                    </div>
                </aside>
            </div>
        </section>

        <section class="home-section home-section-lanes" data-home-reveal="up">
            <div class="section-heading">
                <p class="section-kicker">Quick access</p>
                <h2>Public services and shortcuts for suppliers, observers, and BAC-Office visitors.</h2>
                <p>
                    Each section below points visitors to a clear next step instead of leaving them on a generic system
                    homepage.
                </p>
            </div>

            <div class="portal-lanes">
                @foreach($portalLanes as $lane)
                    <article class="portal-lane portal-lane-{{ $lane['accent'] }}">
                        <p class="feature-eyebrow">{{ $lane['eyebrow'] }}</p>
                        <h3>{{ $lane['title'] }}</h3>
                        <p>{{ $lane['description'] }}</p>

                        @if($lane['url'])
                            <a href="{{ $lane['url'] }}" class="feature-link">{{ $lane['action'] }}</a>
                        @else
                            <button type="button" class="feature-link feature-link-button" onclick="openLogin()">
                                {{ $lane['action'] }}
                            </button>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="home-section home-section-showcase" data-home-reveal="up" id="homepage-carousel">
            <div class="section-heading section-heading-centered">
                <p class="section-kicker">Office highlights</p>
                <h2>Recent BAC-Office activity and documentation.</h2>
                <p>
                    Browse images that highlight committee activity, procurement coordination, and office
                    documentation with proper captions and context.
                </p>
            </div>

            <div class="media-stage">
                <div class="media-stage-copy">
                    <p class="media-stage-kicker">Gallery overview</p>
                    <h3>Give visitors a quick visual view of BAC-Office operations.</h3>
                    <p>
                        Featured images can highlight procurement events, bid openings, meetings, and documented
                        project activity across the BAC-Office.
                    </p>

                    <div class="media-stage-points">
                        <span>Bid openings and conferences</span>
                        <span>Meetings and inspection records</span>
                        <span>Procurement-related milestones</span>
                    </div>
                </div>

                <div class="carousel-shell">
                    <div class="carousel-container">
                        <button type="button" class="carousel-btn left" aria-label="Previous slide">&lsaquo;</button>

                        <div class="carousel-frame">
                            <img id="carouselImage" src="{{ asset('Images/slider6.png') }}" alt="BAC Office activity highlights">

                            <div class="carousel-overlay">
                                <div class="carousel-overlay-copy">
                                    <span id="carouselStepIndicator" class="carousel-step-indicator">01 / 06</span>
                                    <h3 id="carouselCaptionTitle">Bid openings and committee activity</h3>
                                    <p id="carouselCaptionMeta">
                                        BAC-Office events, documentation, and procurement-related activity presented with clearer context.
                                    </p>
                                </div>

                                <div id="carouselDots" class="carousel-dots" aria-label="Carousel pagination"></div>
                            </div>
                        </div>

                        <button type="button" class="carousel-btn right" aria-label="Next slide">&rsaquo;</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="home-section" data-home-reveal="up">
            <div class="section-header-row">
                <div class="section-heading">
                    <p class="section-kicker">Current opportunities</p>
                    <h2>Current procurement opportunities</h2>
                    <p>
                        Review the newest public project postings, then open the full notice for the approved budget,
                        schedule, and supporting details.
                    </p>
                </div>

                <a href="{{ route('public.procurement') }}" class="btn btn-outline section-action-link">
                    View all opportunities
                </a>
            </div>

            <div class="opportunity-layout">
                <article class="opportunity-spotlight">
                    <div class="opportunity-spotlight-header">
                        <p class="hero-panel-label">Lead notice</p>

                        @if($featuredProject)
                            <span class="public-status public-status-{{ $featuredProject->status }}">
                                {{ ucwords(str_replace('_', ' ', $featuredProject->status)) }}
                            </span>
                        @endif
                    </div>

                    <h3>{{ $featuredProject?->title ?? 'No public projects yet' }}</h3>

                    <p>
                        @if($featuredProject)
                            {{ \Illuminate\Support\Str::limit($featuredProject->description ?: 'Project details will appear here once published.', 210) }}
                        @else
                            Once public procurement projects are available, this spotlight card will feature the newest
                            publicly visible notice.
                        @endif
                    </p>

                    <div class="opportunity-spotlight-metrics">
                        <div>
                            <span>Budget</span>
                            <strong>
                                @if($featuredProject && $featuredProject->budget !== null)
                                    P{{ number_format((float) $featuredProject->budget, 2) }}
                                @else
                                    TBA
                                @endif
                            </strong>
                        </div>

                        <div>
                            <span>Deadline</span>
                            <strong>
                                {{ $featuredProject && $featuredProject->deadline ? $featuredProject->deadline->format('M d, Y') : 'TBA' }}
                            </strong>
                        </div>

                        <div>
                            <span>Submitted bids</span>
                            <strong>{{ number_format($featuredProject?->bids_count ?? 0) }}</strong>
                        </div>
                    </div>

                    <a href="{{ $featuredProject ? route('public.procurement.show', $featuredProject) : route('public.procurement') }}" class="hero-btn primary">
                        {{ $featuredProject ? 'Open public notice' : 'Browse procurement' }}
                    </a>
                </article>

                <div class="opportunity-feed">
                    @forelse($projectFeed as $index => $project)
                        <article class="feed-card">
                            <div class="feed-card-rank">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</div>

                            <div class="feed-card-content">
                                <div class="home-card-meta">
                                    <span class="public-status public-status-{{ $project->status }}">
                                        {{ ucwords(str_replace('_', ' ', $project->status)) }}
                                    </span>
                                    <span>
                                        {{ $project->deadline ? $project->deadline->format('M d, Y') : 'TBA' }}
                                    </span>
                                </div>

                                <h3>{{ $project->title }}</h3>
                                <p>{{ \Illuminate\Support\Str::limit($project->description ?: 'Project details will appear here once published.', 110) }}</p>

                                <div class="feed-card-footer">
                                    <strong>
                                        {{ $project->budget !== null ? 'P' . number_format((float) $project->budget, 2) : 'TBA' }}
                                    </strong>
                                    <a href="{{ route('public.procurement.show', $project) }}" class="home-card-link">
                                        Open notice
                                    </a>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="home-empty-state">
                            No public procurement projects have been posted yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="home-section" data-home-reveal="up">
            <div class="section-header-row">
                <div class="section-heading">
                    <p class="section-kicker">Award notices</p>
                    <h2>Recent contract awards</h2>
                    <p>
                        Published award notices are listed here so the public and participating suppliers can follow
                        procurement outcomes clearly.
                    </p>
                </div>

                <a href="{{ route('public.awards') }}" class="btn btn-outline section-action-link">
                    View all awards
                </a>
            </div>

            <div class="award-announcement-list">
                @forelse($latestAwards as $award)
                    @php
                        $awardee = $award->bid && $award->bid->user
                            ? ($award->bid->user->company ?: $award->bid->user->name)
                            : 'Bidder information pending';
                    @endphp

                    <article class="award-announcement">
                        <div class="award-announcement-marker">
                            <span class="public-status public-status-{{ $award->status }}">
                                {{ ucfirst($award->status) }}
                            </span>
                        </div>

                        <div class="award-announcement-copy">
                            <div class="award-announcement-meta">
                                <span>{{ $award->contract_date ? $award->contract_date->format('M d, Y') : 'Date pending' }}</span>
                                <span>Published award notice</span>
                            </div>

                            <h3>{{ $award->project->title ?? 'Awarded project' }}</h3>
                            <p>{{ $awardee }}</p>
                        </div>

                        <div class="award-announcement-value">
                            <span>Contract amount</span>
                            <strong>P{{ number_format((float) $award->contract_amount, 2) }}</strong>
                            <a href="{{ route('public.awards') }}" class="home-card-link">View notice</a>
                        </div>
                    </article>
                @empty
                    <div class="home-empty-state">
                        Award notices will appear here after contracts are recorded in the system.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="home-section" data-home-reveal="up">
            <div class="section-heading">
                <p class="section-kicker">How to participate</p>
                <h2>A simple public path from notice review to bidder participation.</h2>
                <p>
                    Visitors can begin with public information, then move into secure bidder actions only when they are
                    ready to participate.
                </p>
            </div>

            <div class="process-ribbon">
                @foreach($howItWorksSteps as $step)
                    <article class="process-card">
                        <span class="process-number">{{ $step['number'] }}</span>
                        <h3>{{ $step['title'] }}</h3>
                        <p>{{ $step['description'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="home-cta" data-home-reveal="up">
            <div class="home-cta-copy">
                <p class="section-kicker">Ready to continue?</p>
                <h2>Start with the public procurement pages, then sign in only when you are ready to act.</h2>
                <p>
                    The homepage keeps notices and award information upfront while still giving registered bidders a
                    direct path into the portal.
                </p>
            </div>

            <div class="home-cta-actions">
                <a href="{{ route('public.procurement') }}" class="hero-btn primary">
                    Browse Opportunities
                </a>

                <button type="button" class="hero-btn secondary" onclick="openLogin()">
                    Bidder Login
                </button>
            </div>
        </section>
    </main>
@endsection
