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

    <section class="ui-kpis" aria-label="Key figures">
        <a href="{{ route($routeName) }}" class="ui-kpi">
            <span class="ui-kpi__label">Open procurements</span>
            <span class="ui-kpi__value">{{ number_format($kpis['open']['count']) }}</span>
            <span class="ui-kpi__foot">{{ Format::pesoShort($kpis['open']['abc']) }} total ABC</span>
        </a>
        <a href="{{ $flagUrl('posting') }}" class="ui-kpi {{ $kpis['awaiting_posting']['count'] > 0 ? 'ui-kpi--warning' : '' }}">
            <span class="ui-kpi__label">Awaiting PhilGEPS posting record</span>
            <span class="ui-kpi__value">{{ number_format($kpis['awaiting_posting']['count']) }}</span>
            <span class="ui-kpi__foot">ITB or RFQ posting date not recorded</span>
        </a>
        <a href="{{ $flagUrl('week') }}" class="ui-kpi">
            <span class="ui-kpi__label">Deadlines this week</span>
            <span class="ui-kpi__value">{{ number_format($kpis['openings_week']['count']) }}</span>
            <span class="ui-kpi__foot">Bid and quotation deadlines, next 7 days</span>
        </a>
        <a href="{{ $flagUrl('late') }}" class="ui-kpi {{ $kpis['past_period']['count'] > 0 ? 'ui-kpi--danger' : '' }}">
            <span class="ui-kpi__label">Past IRR award period</span>
            <span class="ui-kpi__value">{{ number_format($kpis['past_period']['count']) }}</span>
            <span class="ui-kpi__foot">{{ $kpis['past_period']['count'] > 0 ? 'Needs BAC action' : 'Bid opening to award within the limit' }}</span>
        </a>
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
