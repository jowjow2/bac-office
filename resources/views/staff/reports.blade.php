{{--
    Staff reports: the projects assigned to this member of the BAC Secretariat
    (StaffController::staffPageData), on the portal layout like the overview.
--}}
@extends('layouts.portal')

@section('title', 'Reports')
@section('subtitle', 'FY '.now()->year.' · Projects assigned to you')

@section('actions')
    @if($totalAssignedProjects > 0)
        <a href="{{ route('staff.reports.export.csv') }}" class="ui-btn ui-btn--secondary"><i class="fas fa-file-csv" aria-hidden="true"></i> Export CSV</a>
        <a href="{{ route('staff.reports.print') }}" class="ui-btn ui-btn--primary"><i class="fas fa-file-pdf" aria-hidden="true"></i> Download PDF</a>
    @endif
@endsection

@section('content')
@php
    $peso = fn ($value) => '₱'.number_format((float) $value, 2);
    $awardsByProject = $activeAwards->groupBy('project_id');
    $savingsRate = $awardedBudget > 0 ? $governmentSavings / $awardedBudget * 100 : null;
    $bidderCount = $bidderPerformance->count();
    // Projects by where they stand, for the status bar.
    $statusGroups = $assignedProjects->groupBy(fn ($project) => $project->portalStatus()['label'])
        ->map(fn ($projects) => ['count' => $projects->count(), 'tone' => $projects->first()->portalStatus()['tone']])
        ->sortByDesc('count');
    // A colour per status (bad news always red), so two statuses never look alike.
    $palette = ['#1d4f40', '#1f4f86', '#b7791f', '#0f766e', '#6b5b95', '#78827c'];
    $statusColor = []; $next = 0;
    foreach ($statusGroups as $label => $group) {
        $statusColor[$label] = $group['tone'] === 'danger' ? '#b42318' : $palette[$next++ % count($palette)];
    }
@endphp

<div class="ui-page sr-page">
    @if($totalAssignedProjects === 0)
        <section class="ui-card sr-empty">
            <span class="sr-empty__icon" aria-hidden="true"><i class="fas fa-chart-column"></i></span>
            <h2>No projects are assigned to you yet</h2>
            <p>Reports fill in as the projects assigned to you move through bidding: their ABC, the bids received, the awards and the savings. The BAC chair assigns projects under Staff assignments.</p>
            <a href="{{ route('staff.assign-projects') }}" class="ui-btn ui-btn--secondary">My assigned projects</a>
        </section>
    @else
        <section class="ui-kpis" aria-label="Key figures">
            <div class="ui-kpi">
                <span class="ui-kpi__label">Total ABC</span>
                <span class="ui-kpi__value ui-kpi__value--money">{{ $peso($totalBudgetAllocated) }}</span>
                <span class="ui-kpi__foot">{{ $totalAssignedProjects }} assigned {{ \Illuminate\Support\Str::plural('project', $totalAssignedProjects) }}</span>
            </div>
            <div class="ui-kpi">
                <span class="ui-kpi__label">Total awarded</span>
                <span class="ui-kpi__value ui-kpi__value--money">{{ $peso($totalAwardedAmount) }}</span>
                <span class="ui-kpi__foot">{{ $activeAwards->count() }} {{ \Illuminate\Support\Str::plural('award', $activeAwards->count()) }} in force</span>
            </div>
            <div class="ui-kpi {{ $governmentSavings > 0 ? 'sr-kpi--good' : '' }}">
                <span class="ui-kpi__label">Government savings</span>
                <span class="ui-kpi__value ui-kpi__value--money">{{ $peso($governmentSavings) }}</span>
                <span class="ui-kpi__foot">{{ $savingsRate !== null ? number_format($savingsRate, 1).'% below the ABC of awarded projects' : 'Shown once a project is awarded' }}</span>
            </div>
            <div class="ui-kpi">
                <span class="ui-kpi__label">Bids received</span>
                <span class="ui-kpi__value">{{ number_format($bidParticipation) }}</span>
                <span class="ui-kpi__foot">From {{ $bidderCount }} {{ \Illuminate\Support\Str::plural('bidder', $bidderCount) }}; drafts excluded</span>
            </div>
        </section>

        <section class="ui-card" aria-labelledby="sr-status-title">
            <header class="ui-card__head ui-card__head--plain">
                <h2 class="ui-card__title" id="sr-status-title">Where your projects stand</h2>
            </header>
            <div class="ui-card__body">
                <div class="sr-bar" role="img" aria-label="Projects by status">
                    @foreach($statusGroups as $label => $group)
                        <span class="sr-bar__part" style="flex-grow: {{ $group['count'] }}; background: {{ $statusColor[$label] }}" title="{{ $label }}: {{ $group['count'] }}"></span>
                    @endforeach
                </div>
                <ul class="sr-legend">
                    @foreach($statusGroups as $label => $group)
                        <li><span class="sr-dot" style="background: {{ $statusColor[$label] }}" aria-hidden="true"></span>{{ $label }} <strong>{{ $group['count'] }}</strong></li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section class="ui-card" aria-labelledby="sr-projects-title">
            <header class="ui-card__head">
                <div>
                    <h2 class="ui-card__title" id="sr-projects-title">Project summary</h2>
                    <p class="ui-card__desc">ABC, bids, award and savings of each assigned project.</p>
                </div>
            </header>
            <div class="ui-table-wrap">
                <table class="ui-table ui-table--stack">
                    <thead>
                        <tr>
                            <th scope="col">Project</th>
                            <th scope="col" class="is-num">ABC (₱)</th>
                            <th scope="col" class="is-num">Bids</th>
                            <th scope="col" class="is-num">Awarded (₱)</th>
                            <th scope="col" class="is-num">Savings (₱)</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($assignedProjects as $project)
                            @php
                                $award = $awardsByProject->get($project->id)?->first();
                                $awarded = $award ? (float) $award->contract_amount : null;
                                $status = $project->portalStatus();
                            @endphp
                            <tr>
                                <td data-label="Project">
                                    <span class="ui-cell-title">{{ $project->title }}</span>
                                    <span class="ui-cell-sub"><span class="ui-mono">{{ $project->reference_no ?: 'No reference' }}</span> &middot; {{ $project->mode()->label() }}</span>
                                </td>
                                <td data-label="ABC (₱)" class="is-num">{{ number_format((float) $project->budget, 2) }}</td>
                                <td data-label="Bids" class="is-num">{{ $project->bids_count }}</td>
                                <td data-label="Awarded (₱)" class="is-num">{{ $awarded !== null ? number_format($awarded, 2) : '—' }}</td>
                                <td data-label="Savings (₱)" class="is-num {{ $awarded !== null ? 'sr-good' : '' }}">{{ $awarded !== null ? number_format(max(0, (float) $project->budget - $awarded), 2) : '—' }}</td>
                                <td data-label="Status"><span class="ui-pill ui-pill--{{ $status['tone'] }}">{{ $status['label'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="sr-total">
                            <th scope="row">Total</th>
                            <td class="is-num">{{ number_format($totalBudgetAllocated, 2) }}</td>
                            <td class="is-num">{{ $assignedProjects->sum('bids_count') }}</td>
                            <td class="is-num">{{ number_format($totalAwardedAmount, 2) }}</td>
                            <td class="is-num sr-good">{{ number_format($governmentSavings, 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <section class="ui-card" aria-labelledby="sr-bidders-title">
            <header class="ui-card__head">
                <div>
                    <h2 class="ui-card__title" id="sr-bidders-title">Bidder performance</h2>
                    <p class="ui-card__desc">Official bids on your projects, how many passed the preliminary examination, and how many won.</p>
                </div>
            </header>
            @if($bidderPerformance->isEmpty())
                <div class="ui-card__body"><p class="ui-empty-line"><i class="fas fa-inbox" aria-hidden="true"></i> No official bids on your assigned projects yet.</p></div>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table ui-table--stack">
                        <thead>
                            <tr>
                                <th scope="col">Bidder</th>
                                <th scope="col" class="is-num">Bids</th>
                                <th scope="col" class="is-num">Passed preliminary</th>
                                <th scope="col" class="is-num">Won</th>
                                <th scope="col">Pass rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bidderPerformance as $bidder)
                                @php $rate = $bidder['total_bids'] > 0 ? round($bidder['passed'] / $bidder['total_bids'] * 100) : 0; @endphp
                                <tr>
                                    <td data-label="Bidder"><span class="ui-cell-title">{{ $bidder['bidder'] }}</span></td>
                                    <td data-label="Bids" class="is-num">{{ $bidder['total_bids'] }}</td>
                                    <td data-label="Passed preliminary" class="is-num">{{ $bidder['passed'] }}</td>
                                    <td data-label="Won" class="is-num">@if($bidder['won'] > 0)<span class="ui-pill ui-pill--success">{{ $bidder['won'] }} won</span>@else — @endif</td>
                                    <td data-label="Pass rate">
                                        <span class="sr-rate"><span class="sr-rate__bar"><span style="width: {{ $rate }}%"></span></span>{{ $rate }}%</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
</div>

<style>
    .sr-page { display: grid; gap: 16px; }
    .sr-empty { display: grid; justify-items: center; gap: 8px; padding: 48px 24px; text-align: center; }
    .sr-empty__icon { display: grid; width: 52px; height: 52px; place-items: center; border-radius: 14px; background: var(--ui-primary-soft); color: var(--ui-primary); font-size: 20px; }
    .sr-empty h2 { margin: 6px 0 0; color: var(--ui-ink); font-size: 18px; }
    .sr-empty p { max-width: 520px; margin: 0 0 8px; color: var(--ui-muted); font-size: 13.5px; line-height: 1.6; }
    .sr-kpi--good { border-color: var(--ui-success-line, #b7dcc7); }
    .sr-kpi--good .ui-kpi__value { color: var(--ui-success); }
    .sr-bar { display: flex; gap: 3px; height: 12px; overflow: hidden; border-radius: 999px; background: var(--ui-line-soft); }
    .sr-bar__part { min-width: 6px; }
    .sr-legend { display: flex; flex-wrap: wrap; gap: 8px 18px; margin: 12px 0 0; padding: 0; list-style: none; color: var(--ui-ink-2); font-size: 13px; }
    .sr-legend li { display: inline-flex; align-items: center; gap: 7px; }
    .sr-legend strong { color: var(--ui-ink); }
    .sr-dot { width: 10px; height: 10px; border-radius: 50%; }
    :is(.sr-bar__part, .sr-dot).is-success { background: var(--ui-success); }
    :is(.sr-bar__part, .sr-dot).is-info { background: var(--ui-info); }
    :is(.sr-bar__part, .sr-dot).is-warning { background: var(--ui-warning); }
    :is(.sr-bar__part, .sr-dot).is-danger { background: var(--ui-danger); }
    :is(.sr-bar__part, .sr-dot).is-neutral { background: #b9b3a6; }
    .sr-good { color: var(--ui-success); font-weight: 600; }
    .sr-total th, .sr-total td { border-top: 2px solid var(--ui-line); background: var(--ui-surface-2); color: var(--ui-ink); font-weight: 700; }
    .sr-rate { display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; font-variant-numeric: tabular-nums; }
    .sr-rate__bar { width: 70px; height: 6px; overflow: hidden; border-radius: 999px; background: var(--ui-line-soft); }
    .sr-rate__bar > span { display: block; height: 100%; background: var(--ui-primary); }
    /* On phones the cards above already give the totals. */
    @media (max-width: 768px) { .sr-page .ui-table--stack tfoot { display: none !important; } }
</style>
@endsection
