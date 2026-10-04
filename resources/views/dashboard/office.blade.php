{{--
    Procurement overview for the BAC (admin) and the BAC Secretariat
    (staff): KPIs, pipeline by stage, the register and the next activities.
--}}
@extends('layouts.portal')

@use('App\Support\Format')

@php
    $isAdmin = $role === 'admin';
    $routeName = $isAdmin ? 'admin.dashboard' : 'staff.dashboard';
    $flagUrl = fn (string $flag) => route($routeName, ['flag' => $flag]);
@endphp

@section('title', 'Procurement overview')
@section('subtitle', 'FY '.now()->year.' · '.config('bac-office.procuring_entity').' · RA 12009 and its 2025 IRR; RA 9184 for earlier projects')

@section('actions')
    @if($isAdmin)
        <a href="{{ route('admin.requests') }}" class="ui-btn ui-btn--secondary">Purchase requests</a>
        <a href="{{ route('admin.projects.create') }}" class="ui-btn ui-btn--primary"><i class="fas fa-plus" aria-hidden="true"></i> New procurement</a>
    @else
        <a href="{{ route('staff.requests') }}" class="ui-btn ui-btn--secondary">Purchase requests</a>
        <a href="{{ route('staff.review-bids') }}" class="ui-btn ui-btn--primary">Review bids &amp; quotations</a>
    @endif
@endsection

@section('content')
    @unless($isAdmin)
        @if(($assignedCount ?? 0) === 0)
            <div class="ui-alert" role="status">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                <span>No projects are assigned to you yet. The BAC chair assigns projects under Staff assignments; the purchase request queue is shared by the whole Secretariat.</span>
            </div>
        @endif
    @endunless

    {{-- What is waiting for this person (DashboardActions); nothing is shown for a zero. --}}
    <section class="ui-card" aria-labelledby="actions-title">
        <header class="ui-card__head ui-card__head--plain">
            <h2 class="ui-card__title" id="actions-title">Needs your action</h2>
            <a href="{{ route($routeName) }}" class="ui-card__aside ui-link">{{ number_format($kpis['open']['count']) }} open {{ \Illuminate\Support\Str::plural('procurement', $kpis['open']['count']) }} · {{ Format::pesoShort($kpis['open']['abc']) }} total ABC</a>
        </header>
        <div class="ui-card__body">
            @if($actions === [])
                <p class="ui-allclear"><i class="fas fa-circle-check" aria-hidden="true"></i> All caught up. Nothing is waiting for you right now.</p>
            @else
                <ul class="ui-todo">
                    @foreach($actions as $action)
                        <li>
                            <a href="{{ $action['url'] }}" class="ui-todo__item ui-todo__item--{{ $action['tone'] }}">
                                <span class="ui-todo__icon" aria-hidden="true"><i class="fas {{ $action['icon'] }}"></i></span>
                                <span class="ui-todo__count">{{ number_format($action['count']) }}</span>
                                <span class="ui-todo__text">
                                    <strong>{{ $action['label'] }}</strong>
                                    <small>{{ $action['hint'] }}</small>
                                </span>
                                <i class="fas fa-chevron-right ui-todo__go" aria-hidden="true"></i>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    @include('partials.portal.pipeline', ['routeName' => $routeName])

    <div class="ui-grid ui-grid--sidebar">
        @include('partials.portal.register', ['routeName' => $routeName])

        <div class="ui-stack">
            @include('partials.portal.upcoming')

            @if($isAdmin)
                <section class="ui-card" aria-labelledby="registrations-title">
                    <header class="ui-card__head ui-card__head--plain">
                        <h2 class="ui-card__title" id="registrations-title">Bidder registrations · {{ $pendingRegistrationsCount }}</h2>
                        <a href="{{ route('admin.users') }}" class="ui-link">Review</a>
                    </header>
                    <div class="ui-card__body">
                        @forelse($pendingRegistrations as $registration)
                            <div class="ui-upcoming__item">
                                <span class="ui-upcoming__date" aria-hidden="true"><i class="fas fa-building"></i></span>
                                <div>
                                    <a href="{{ route('admin.users.review', $registration) }}" class="ui-upcoming__title ui-link">{{ $registration->company ?: $registration->name }}</a>
                                    <p class="ui-upcoming__meta">{{ $registration->email }}</p>
                                    @if($registration->philgepsCertificate?->file_url)
                                        <p class="ui-upcoming__meta"><a href="{{ $registration->philgepsCertificate->file_url }}" target="_blank" rel="noopener">View PhilGEPS certificate</a></p>
                                    @else
                                        <p class="ui-upcoming__meta">No PhilGEPS certificate uploaded</p>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="ui-hint">No registrations are waiting for review.</p>
                        @endforelse
                    </div>
                </section>
            @endif

            <section class="ui-card" aria-labelledby="inbox-title">
                <header class="ui-card__head ui-card__head--plain">
                    <h2 class="ui-card__title" id="inbox-title">Messages</h2>
                </header>
                <div class="ui-card__body">
                    <p class="ui-hint">{{ $isAdmin ? 'Coordinate with the Secretariat, end-user offices and bidders.' : 'Contact admin and bidders about your assigned procurements.' }}</p>
                    <p class="ui-mt-sm"><a href="{{ route($isAdmin ? 'admin.messages' : 'staff.messages') }}" class="ui-btn ui-btn--secondary ui-btn--sm">Open messages</a></p>
                </div>
            </section>
        </div>
    </div>
@endsection
