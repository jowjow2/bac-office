@extends('layouts.public')

@section('title', $project->title . ' | Public Procurement')
@section('body_class', 'public-page')

@section('content')
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
            </article>

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
