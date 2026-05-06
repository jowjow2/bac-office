@extends('layouts.public')

@section('title', $project->title . ' | Public Procurement')
@section('body_class', 'public-page')

@section('content')
    @php
        $projectDocuments = $project->uploadedDocuments();
    @endphp

    <main class="public-shell">
        <a href="{{ route('public.procurement') }}" class="public-back-link">Back to Procurement</a>

        <section class="public-page-hero">
            <div class="public-detail-hero-copy">
                <p class="public-page-kicker">{{ ucwords(str_replace('_', ' ', $project->status)) }}</p>
                <h1>{{ $project->title }}</h1>
                <p>{{ $project->description ?: 'No description available yet.' }}</p>
            </div>
        </section>

        <section class="public-detail-grid">
            <div class="public-detail-stack">
                <article class="public-detail-card">
                    <h2>Project Overview</h2>

                    <div class="public-detail-stats">
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

                        <div class="public-detail-stat">
                            <span>Bids Received</span>
                            <strong>{{ $project->bids_count }}</strong>
                        </div>
                    </div>

                    @if($project->category || $project->location || $project->procurement_mode || $project->source_of_fund || $project->contract_duration)
                    <div class="public-detail-info" style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                        @if($project->category)
                        <p><strong>Category:</strong> {{ $project->category }}</p>
                        @endif
                        @if($project->location)
                        <p><strong>Location:</strong> {{ $project->location }}</p>
                        @endif
                        @if($project->procurement_mode)
                        <p><strong>Procurement Mode:</strong> {{ $project->procurement_mode }}</p>
                        @endif
                        @if($project->source_of_fund)
                        <p><strong>Source of Fund:</strong> {{ $project->source_of_fund }}</p>
                        @endif
                        @if($project->contract_duration)
                        <p><strong>Contract Duration:</strong> {{ $project->contract_duration }}</p>
                        @endif
                    </div>
                    @endif
                </article>

                <article class="public-detail-card public-doc-card">
                    @include('pages.partials.procurement-bidding-files', [
                        'project' => $project,
                        'projectDocuments' => $projectDocuments,
                    ])
                </article>
            </div>

            <article class="public-detail-card">
                <h2>Participation</h2>
                <p>
                    Sign in with a bidder account to participate in BAC-Office procurement opportunities and track project updates.
                </p>

                <div class="public-detail-actions">
                    <button type="button" class="btn login-btn-inline" onclick="openLogin()">Login to Participate</button>
                    <a href="{{ route('public.procurement') }}" class="btn btn-outline">Browse More Projects</a>
                </div>
            </article>
        </section>
    </main>
@endsection
