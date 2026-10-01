@extends('layouts.public')

@section('title', $project->title . ' | Public Procurement')
@section('body_class', 'public-page')

@section('content')
    @php
        $deadline = $project->bidSubmissionDeadline()?->copy()->timezone(config('bac-office.display_timezone', 'Asia/Manila'));
        $isOpen = $project->status === 'open';
    @endphp

    <main class="public-shell bid-notice-page">
        <a href="{{ route('public.procurement') }}" class="public-back-link">Back to Procurement Opportunities</a>

        <div class="bid-notice-layout">
            @include('pages.partials.bid-notice', ['project' => $project])

            <aside class="bid-notice-aside" aria-label="Participation">
                <p class="bid-notice-aside-label">Closing date / time</p>
                <p class="bid-notice-aside-date">{{ $deadline ? $deadline->format('M d, Y h:i A') : 'To be announced' }}</p>
                <p class="bid-notice-aside-label">Approved budget (ABC)</p>
                <p class="bid-notice-aside-amount">&#8369;{{ number_format((float) $project->budget, 2) }}</p>
                @if($isOpen)
                    <a href="{{ route('login.page', ['qr_project' => $project->id]) }}" class="btn">Login to Participate</a>
                @endif
                <a href="{{ route('public.procurement') }}" class="btn btn-outline">Browse more projects</a>
            </aside>
        </div>
    </main>
@endsection
