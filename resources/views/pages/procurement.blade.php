@extends('layouts.public')

@section('title', 'Public Procurement')
@section('body_class', 'public-page')

@section('content')
    @php
        $categoryFilters = [
            'goods' => 'Goods',
            'services' => 'Services',
            'infrastructure' => 'Infrastructure',
            'consultancy' => 'Consultancy',
        ];
        $totalCategoryCount = $categoryCounts->sum();
        $tz = config('bac-office.display_timezone', 'Asia/Manila');
        $now = now($tz);
        // Per-project values shared by the list rows and their detail dialogs.
        $row = function ($project) use ($tz, $now) {
            $projectModalId = 'public-project-modal-' . $project->id;
            $projectCategoryLabel = $project->category
                ? (string) \Illuminate\Support\Str::of($project->category)->replace('_', ' ')->title()
                : 'Uncategorized';
            $statusLabel = ucwords(str_replace('_', ' ', $project->status));
            $deadline = $project->bidSubmissionDeadline()?->copy()->timezone($tz);
            $projectLoginUrl = route('login.page', ['qr_project' => $project->id]);
            $projectViewUrl = route('public.procurement.show', $project);
            // Time left to submit, from the recorded submission deadline.
            $timeLeft = match (true) {
                $project->status !== 'open' => null,
                ! $deadline => 'Deadline to be announced',
                $deadline->isPast() => 'Submission closed',
                $deadline->isSameDay($now) => 'Closes today',
                default => 'Closes in '.($days = (int) ceil($now->diffInHours($deadline) / 24)).' '.\Illuminate\Support\Str::plural('day', $days),
            };

            return compact('projectModalId', 'projectCategoryLabel', 'statusLabel', 'deadline', 'projectLoginUrl', 'projectViewUrl', 'timeLeft');
        };
    @endphp

    <main class="public-shell proc-register">
        <header class="board-bar">
            <div>
                <p class="board-eyebrow">Bids and Awards Committee &middot; San Jose, Occidental Mindoro</p>
                <h1>Procurement Opportunities</h1>
            </div>
            <form action="{{ route('public.procurement') }}" method="GET" class="board-search" role="search">
                <label for="proc-q" class="sr-only">Search procurement</label>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                <input id="proc-q" type="search" name="q" value="{{ $query }}" placeholder="Search project title or description" autocomplete="off">
                @if($category !== '')<input type="hidden" name="category" value="{{ $category }}">@endif
                <button type="submit" class="btn">Search</button>
            </form>
        </header>

        <nav class="proc-tabs" aria-label="Filter by category">
            <a href="{{ route('public.procurement', array_filter(['q' => $query ?: null])) }}" @class(['proc-tab', 'is-active' => $category === '']) @if($category === '') aria-current="true" @endif>
                All <span class="proc-tab-count">{{ $totalCategoryCount }}</span>
            </a>
            @foreach($categoryFilters as $slug => $label)
                <a href="{{ route('public.procurement', array_filter(['q' => $query ?: null, 'category' => $slug])) }}" data-category="{{ $slug }}" @class(['proc-tab', 'is-active' => $category === $slug]) @if($category === $slug) aria-current="true" @endif>
                    {{ $label }} <span class="proc-tab-count">{{ $categoryCounts->get($slug, 0) }}</span>
                </a>
            @endforeach
        </nav>

        <p class="board-results">
            {{ $projects->count() }} project{{ $projects->count() === 1 ? '' : 's' }}
            @if($category !== '') in <strong>{{ $categoryFilters[$category] ?? $category }}</strong>@endif
            @if($query !== '') matching "<strong>{{ $query }}</strong>"@endif
            @if($query !== '' || $category !== '')
                <a href="{{ route('public.procurement') }}">Clear filters</a>
            @endif
        </p>

        @if($projects->isEmpty())
            <div class="public-empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/><path d="M8 14h8"/></svg>
                <p>No procurement projects matched your search yet.</p>
            </div>
        @else
            <ol class="proc-list">
                @foreach($projects as $project)
                    @php extract($row($project)); @endphp
                    <li class="proc-item" data-category="{{ strtolower((string) $project->category) }}">
                        <div class="proc-date" aria-label="Bid submission deadline">
                            <span class="proc-date-label">Deadline</span>
                            @if($deadline)
                                <strong>{{ $deadline->format('d') }}</strong>
                                <span>{{ strtoupper($deadline->format('M Y')) }}</span>
                                <small>{{ $deadline->format('h:i A') }}</small>
                            @else
                                <strong>—</strong>
                                <span>TBA</span>
                            @endif
                        </div>

                        <div class="proc-main">
                            <p class="proc-topline">
                                @if($project->reference_no)<span class="proc-ref">{{ $project->reference_no }}</span>@endif
                                <span class="public-status public-status-{{ $project->status }}">{{ $statusLabel }}</span>
                                @if($timeLeft)<span class="proc-left {{ $timeLeft === 'Submission closed' ? 'is-closed' : '' }}">{{ $timeLeft }}</span>@endif
                            </p>
                            <h2><a href="{{ $projectViewUrl }}" data-public-details-trigger="{{ $projectModalId }}">{{ $project->title }}</a></h2>
                            <p class="proc-meta">
                                <span class="proc-category" data-category="{{ strtolower((string) $project->category) }}">{{ $projectCategoryLabel }}</span>
                                <span>{{ $project->modeLabel() }}</span>
                                @if($project->location)<span>{{ $project->location }}</span>@endif
                            </p>
                            @if($project->description)
                                <p class="proc-desc">{{ $project->description }}</p>
                            @endif
                        </div>

                        <div class="proc-side">
                            <span>Approved budget (ABC)</span>
                            <strong>&#8369;{{ number_format((float) $project->budget, 2) }}</strong>
                            <a href="{{ $projectViewUrl }}" class="btn-outline" data-public-details-trigger="{{ $projectModalId }}">View details</a>
                        </div>
                    </li>

                @endforeach
            </ol>

            @foreach($projects as $project)
                @php extract($row($project)); @endphp
                <div id="{{ $projectModalId }}" class="public-details-modal" hidden aria-hidden="true">
                    <div class="public-details-backdrop" data-public-details-close></div>

                    <section class="public-details-dialog bid-notice-dialog" role="dialog" aria-modal="true" aria-labelledby="bid-notice-dialog-{{ $project->id }}-title">
                        <button type="button" class="public-details-close" data-public-details-close aria-label="Close bid notice">&times;</button>

                        @include('pages.partials.bid-notice', ['project' => $project, 'compact' => true])

                        <footer class="bid-notice-actions">
                            <button type="button" class="btn btn-outline" data-public-details-close>Close</button>
                            <a href="{{ $projectViewUrl }}" class="btn btn-outline">Full notice page</a>
                            @if($project->status === 'open')
                                <a href="{{ $projectLoginUrl }}" class="btn">Login to Participate</a>
                            @endif
                        </footer>
                    </section>
                </div>
            @endforeach
        @endif
    </main>
@endsection
