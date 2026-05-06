@extends('layouts.public')

@section('title', 'Home')
@section('body_class', 'public-page home-page')

@push('pre_app_styles')
    @vite('resources/css/home.css')
@endpush

@section('content')
    <main class="home-shell">
        <section
            class="home-page-hero"
            data-home-reveal="up"
            style="background-image: linear-gradient(180deg, rgba(2, 6, 23, 0.15) 0%, rgba(2, 6, 23, 0.35) 50%, rgba(2, 6, 23, 0.6) 100%), radial-gradient(circle at 30% 50%, rgba(59, 130, 246, 0.12) 0%, transparent 50%), radial-gradient(circle at 70% 50%, rgba(139, 92, 246, 0.08) 0%, transparent 50%), url('{{ asset('Images/image-background.png') }}');"
        >
            <div class="home-hero-copy">
                <p class="home-hero-kicker">SAN JOSE Occidental Mindoro</p>
                <h1>Bids and Awards Committee Portal</h1>
                <p class="home-hero-lead">
                    Ensuring transparency, accountability, and efficiency in public 
                </p>

                <div class="home-hero-actions">
                    <a href="{{ route('public.procurement') }}" class="btn">Browse Procurement</a>
                    <a href="{{ route('public.awards') }}" class="btn btn-outline">View Awards</a>
                </div>
            </div>
        </section>

        <div class="home-content-shell">
            <section class="home-quick-links" data-home-reveal="up" aria-label="Quick links">
                <a href="{{ route('public.procurement') }}" class="home-quick-link">
                    <span>Procurement</span>
                    <strong>Projects & bidding files</strong>
                </a>

                <a href="{{ route('public.awards') }}" class="home-quick-link">
                    <span>Awards</span>
                    <strong>Contracts and winning bidders</strong>
                </a>

                <a href="{{ url('/about') }}" class="home-quick-link">
                    <span>About BAC</span>
                    <strong>Mandate and legal basis</strong>
                </a>

                <a href="{{ url('/contact') }}" class="home-quick-link">
                    <span>Contact</span>
                    <strong>BAC Office assistance</strong>
                </a>

                <button type="button" class="home-quick-link home-quick-link-button" onclick="openLogin(); switchTab('register');">
                    <span>Bidder Portal</span>
                    <strong>Login or register</strong>
                </button>
            </section>

            {{-- Rated BAC & Performance Feature --}}
            <section class="home-section" data-home-reveal="up">
                <div class="home-section-header">
                    <div>
                        <p class="public-page-kicker">Transparency & Performance</p>
                        <h2>Rated BAC Metrics</h2>
                        <p>Monitoring the integrity and efficiency of procurement activities through data-driven ratings.</p>
                    </div>
                </div>

                <div class="home-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem;">
                    <article class="home-stat-card">
                        <span style="color: #64748b; font-weight: 600;">Committee Rating</span>
                        <strong>4.9 / 5.0</strong>
                        <div class="bac-rating-stars" style="color: #fbbf24; margin-top: 5px;">★★★★★</div>
                        <p style="font-size: 0.85rem; margin-top: 8px; color: #cbd5e1;">Procurement Cycle Efficiency</p>
                    </article>

                    <article class="home-stat-card">
                        <span style="color: #64748b; font-weight: 600;">Bidder Compliance</span>
                        <strong>94.2%</strong>
                        <p style="font-size: 0.85rem; margin-top: 8px; color: #cbd5e1;">Average Documentation Accuracy</p>
                    </article>

                    <article class="home-stat-card">
                        <span style="color: #64748b; font-weight: 600;">Award Transparency</span>
                        <strong>100%</strong>
                        <p style="font-size: 0.85rem; margin-top: 8px; color: #cbd5e1;">Public Posting Compliance</p>
                    </article>

                    <article class="home-stat-card">
                        <span style="color: #64748b; font-weight: 600;">Reliability Index</span>
                        <strong>High</strong>
                        <p style="font-size: 0.85rem; margin-top: 8px; color: #cbd5e1;">Verified Contract Completion</p>
                    </article>
                </div>
            </section>

            <section class="home-stats-grid" data-home-reveal="up" aria-label="Procurement summary">
                <article class="home-stat-card">
                    <span>Public Projects</span>
                    <strong>{{ number_format($publicProjectsCount ?? 0) }}</strong>
                </article>

                <article class="home-stat-card">
                    <span>Open Opportunities</span>
                    <strong>{{ number_format($openProjectsCount ?? 0) }}</strong>
                </article>

                <article class="home-stat-card">
                    <span>Awarded Contracts</span>
                    <strong>{{ number_format($awardedContractsCount ?? 0) }}</strong>
                </article>

                <article class="home-stat-card">
                    <span>Total Awarded Value</span>
                    <strong>P{{ number_format((float) ($totalAwardedValue ?? 0), 2) }}</strong>
                </article>
            </section>

            <section class="home-section" data-home-reveal="up">
                <div class="home-section-header">
                    <div>
                        <p class="public-page-kicker">Invitation to Bid</p>
                        <h2>Procurement Advisories</h2>
                        <p>Access the latest opportunities, bidding documents, and technical specifications for active projects.</p>
                    </div>

                    <a href="{{ route('public.procurement') }}" class="btn btn-outline">Browse All</a>
                </div>

                <div class="home-procurement-grid">
                    @forelse(($latestProjects ?? collect()) as $project)
                        @php
                            $projectDocuments = $project->uploadedDocuments();
                        @endphp

                        <article class="home-procurement-card">
                            <div class="public-card-meta">
                                <span class="public-status public-status-{{ $project->status }}">{{ ucwords(str_replace('_', ' ', $project->status)) }}</span>
                                <span>{{ $project->deadline ? $project->deadline->format('M d, Y') : 'TBA' }}</span>
                            </div>

                            <h3>{{ $project->title }}</h3>
                            <p>{{ \Illuminate\Support\Str::limit($project->description ?: 'No description available yet.', 105) }}</p>

                            @if($projectDocuments->isNotEmpty())
                                @include('pages.partials.procurement-bidding-files', [
                                    'project' => $project,
                                    'projectDocuments' => $projectDocuments,
                                    'compact' => true,
                                    'documentLimit' => 1,
                                ])
                            @endif

                            <div class="home-procurement-footer">
                                <strong>P{{ number_format((float) $project->budget, 2) }}</strong>
                                <a href="{{ route('public.procurement.show', $project) }}" class="btn btn-outline">View Details</a>
                            </div>
                        </article>
                    @empty
                        <div class="public-empty-state">No public procurement opportunities are posted yet.</div>
                    @endforelse
                </div>
            </section>

            <section class="home-split-section" data-home-reveal="up">
                <article class="home-panel">
                    <div class="home-section-header home-section-header-compact">
                        <div>
                            <p class="public-page-kicker">What's New</p>
                            <h2>BAC advisories</h2>
                        </div>
                    </div>

                    <div class="home-announcement-list">
                        <div class="home-announcement-item">
                            <span>Bidder Reminder</span>
                            <strong>Prepare eligibility and registration documents before submission.</strong>
                        </div>

                        <div class="home-announcement-item">
                            <span>Project Updates</span>
                            <strong>Check procurement deadlines and uploaded bidding files regularly.</strong>
                        </div>

                        <div class="home-announcement-item">
                            <span>Public Monitoring</span>
                            <strong>Awards and contract notices are published for transparency.</strong>
                        </div>
                    </div>
                </article>

                <article class="home-panel">
                    <div class="home-section-header home-section-header-compact">
                        <div>
                            <p class="public-page-kicker">Awards</p>
                            <h2>Recent contracts</h2>
                        </div>
                        <a href="{{ route('public.awards') }}" class="home-text-link">View awards</a>
                    </div>

                    <div class="home-awards-list">
                        @forelse(($latestAwards ?? collect()) as $award)
                            @php
                                $winner = $award->bid?->user?->company ?: ($award->bid?->user?->name ?? 'N/A');
                            @endphp

                            <div class="home-award-item">
                                <span>{{ $award->contract_date?->format('M d, Y') ?? 'TBA' }}</span>
                                <strong>{{ $award->project?->title ?? 'Untitled Project' }}</strong>
                                <p>{{ $winner }} · P{{ number_format((float) $award->contract_amount, 2) }}</p>
                            </div>
                        @empty
                            <div class="home-award-item">
                                <span>Updates</span>
                                <strong>No awarded contracts posted yet.</strong>
                                <p>Published award notices will appear here once available.</p>
                            </div>
                        @endforelse
                    </div>
                </article>
            </section>

            <section class="home-section" data-home-reveal="up">
                <div class="home-section-header">
                    <div>
                        <p class="public-page-kicker">How to Participate</p>
                        <h2>Basic bidder flow</h2>
                        <p>Follow the core steps for reviewing procurement opportunities and joining BAC bidding activities.</p>
                    </div>
                </div>

                <div class="home-process-grid">
                    <article class="home-process-card">
                        <span>01</span>
                        <h3>Browse opportunities</h3>
                        <p>Open the procurement list and review project details, deadlines, ABC, and bidding files.</p>
                    </article>

                    <article class="home-process-card">
                        <span>02</span>
                        <h3>Prepare documents</h3>
                        <p>Complete business, eligibility, PhilGEPS, and project-specific requirements before submission.</p>
                    </article>

                    <article class="home-process-card">
                        <span>03</span>
                        <h3>Login or register</h3>
                        <p>Use a bidder account to participate and submit bid information through the BAC portal.</p>
                    </article>

                    <article class="home-process-card">
                        <span>04</span>
                        <h3>Track awards</h3>
                        <p>Monitor bid status, awards, and contract postings once the procurement process is completed.</p>
                    </article>
                </div>
            </section>

            <section class="home-legal-strip" data-home-reveal="up">
                <div>
                    <p class="public-page-kicker">Legal Basis</p>
                    <h2>Procurement references</h2>
                    <p>Access official procurement laws, implementing rules, and policy resources.</p>
                </div>

                <div class="home-legal-actions">
                    <a href="https://www.dbm.gov.ph/index.php/republic-act-ra-no-12009-new-government-procurement-act-ngpa" target="_blank" rel="noopener">RA 12009 / NGPA</a>
                    <a href="https://www.gppb.gov.ph/wp-content/uploads/2023/06/Republic-Act-No.-9184.pdf" target="_blank" rel="noopener">RA 9184</a>
                    <a href="https://www.gppb.gov.ph/ra-9184-and-2016-revised-irr/" target="_blank" rel="noopener">RA 9184 IRR</a>
                    <a href="https://www.philgeps.gov.ph/" target="_blank" rel="noopener">PhilGEPS</a>
                </div>
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
                    </div>

                    <button type="button" class="carousel-btn right" aria-label="Next slide">&rsaquo;</button>
                </div>
            </section>

            <section class="home-contact-strip" data-home-reveal="up">
                <div>
                    <p class="public-page-kicker">Contact BAC Office</p>
                    <h2>Need procurement assistance?</h2>
                    <p>BAC Office, Municipal Government of San Jose, Occidental Mindoro</p>
                </div>

                <div class="home-contact-actions">
                    <a href="mailto:bacoffice@sanjose.gov.ph">bacoffice@sanjose.gov.ph</a>
                    <a href="tel:+63430000000">(043) 000-0000</a>
                    <a href="{{ url('/contact') }}" class="btn">Contact Us</a>
                </div>
            </section>
        </div>
    </main>
@endsection
