@extends('layouts.public')

@section('title', 'Home')
@section('body_class', 'public-page home-page')

@push('pre_app_styles')
    @vite('resources/css/home.css')
@endpush

@section('content')
    <main class="home-shell">
        <section class="home-page-hero" data-home-reveal="up">
            <div class="home-hero-copy">
                <p class="home-hero-kicker">San Jose Bids and Awards Committee</p>
                <h1>Welcome to BAC Office</h1>
                <p class="home-hero-lead">
                    Your gateway to efficient procurement management, public bidding updates, and BAC-Office highlights.
                </p>

                <div class="home-hero-actions">
                    <a href="{{ route('public.procurement') }}" class="btn">Browse Procurement</a>
                    <a href="{{ route('public.awards') }}" class="btn btn-outline">View Awards</a>
                </div>
            </div>
        </section>

        <div class="home-content-shell">
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
        </div>
    </main>
@endsection
