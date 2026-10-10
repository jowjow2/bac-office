<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard staff-role-page dashboard-home admin-dashboard-page staff-dashboard-page">
    @vite(['resources/css/dashboard.css'])
    @include('partials.staff-page-styles')

    <style>
        .assign-projects-alert-floating {
            position: fixed;
            top: 92px;
            right: 24px;
            width: min(360px, calc(100vw - 32px));
            z-index: 2400;
            box-shadow: 0 16px 34px rgba(27, 36, 32, 0.14);
            opacity: 1;
            transform: translateY(0);
            transition: opacity 0.35s ease, transform 0.35s ease;
        }

        .assign-projects-alert-floating.fade-out {
            opacity: 0;
            transform: translateY(-10px);
        }

        .staff-assigned-list {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .staff-assigned-card {
            background: #fff;
            border: 1px solid var(--ui-line);
            border-radius: var(--ui-radius-lg);
            box-shadow: var(--ui-shadow);
            overflow: hidden;
        }

        .staff-assigned-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 22px;
            border-bottom: 1px solid #e8eef6;
        }

        .staff-assigned-head h2 {
            margin: 0 0 6px;
            font-size: 15px;
            font-weight: 700;
            color: var(--ui-ink);
        }

        .staff-assigned-meta {
            font-size: 12px;
            color: var(--ui-subtle);
        }

        .staff-assigned-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .staff-assigned-body {
            padding: 18px 22px 20px;
        }

        .staff-assigned-description {
            margin: 0 0 16px;
            font-size: 12px;
            line-height: 1.6;
            color: var(--ui-muted);
        }

        .staff-assigned-kicker {
            margin: 0 0 12px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: normal;
            text-transform: none;
            color: var(--ui-ink-2);
        }

        .staff-subtable {
            width: 100%;
            border-collapse: collapse;
        }

        .staff-subtable thead th {
            padding: 12px 16px;
            background: var(--ui-surface-2);
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: normal;
            text-transform: none;
            color: var(--ui-muted);
        }

        .staff-subtable tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--ui-line-soft);
            font-size: 12px;
            color: var(--ui-ink);
            vertical-align: middle;
        }

        .staff-subtable tbody tr:last-child td {
            border-bottom: 0;
        }

        .staff-variance-positive {
            color: #b91c1c;
        }

        .staff-variance-negative {
            color: #047857;
        }

        .staff-assigned-heading { min-width: 0; }
        .staff-assigned-ref { display: flex; flex-wrap: wrap; gap: 6px 14px; margin: 0 0 4px; color: var(--ui-muted); font-size: 12px; }
        .staff-assigned-ref span:first-child { color: var(--ui-ink-2); font-family: var(--ui-mono); font-weight: 600; }
        .staff-assigned-facts { display: flex; flex-wrap: wrap; gap: 8px 26px; margin: 10px 0 0; }
        .staff-assigned-facts dt { color: var(--ui-subtle); font-size: 11.5px; font-weight: 600; }
        .staff-assigned-facts dd { margin: 2px 0 0; color: var(--ui-ink); font-size: 13px; font-weight: 600; }
        .staff-tone-pill { display: inline-flex; align-items: center; padding: 4px 11px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .staff-tone-pill.is-success { background: #e7f5ec; color: #166534; }
        .staff-tone-pill.is-warning { background: #fdf3e1; color: #92400e; }
        .staff-tone-pill.is-danger { background: #fdecec; color: #b91c1c; }
        .staff-tone-pill.is-info { background: #e8f0fb; color: #1e4f8f; }
        .staff-tone-pill.is-neutral { background: var(--ui-surface-2); color: var(--ui-muted); }
        .staff-assigned-tasks { display: grid; gap: 8px; margin-bottom: 18px; }
        .staff-task { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border: 1px solid #f1d9a8; border-radius: var(--ui-radius); background: #fffaf0; color: var(--ui-ink); font-size: 13px; text-decoration: none; }
        .staff-task > i { color: #b7791f; }
        .staff-task span { flex: 1; }
        .staff-task strong { color: var(--ui-primary); font-size: 12.5px; white-space: nowrap; }
        .staff-task.is-clear { margin: 0; border-color: var(--ui-line); background: var(--ui-surface-2); color: var(--ui-muted); }
        .staff-task.is-clear > i { color: #1f7a4d; }
        .staff-subtable small.staff-muted { display: block; margin-top: 2px; color: var(--ui-subtle); font-size: 11.5px; }
        .staff-cell-action { text-align: right; white-space: nowrap; }
        .staff-assigned-card .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); }
        .staff-reviewed-label {
            font-size: 12px;
            color: var(--ui-ink);
            white-space: nowrap;
        }
        
        @media (max-width: 768px) {
            .assign-projects-alert-floating {
                top: 84px;
                right: 16px;
            }
        }
    </style>

    @include('partials.staff-sidebar')

    <div class="main-area">
        <x-page-header title="My assigned projects" subtitle="The procurements you support as BAC Secretariat: what is waiting on you, and the bids received." />

        <main class="dashboard-content dashboard-home-content">
            <section class="staff-dashboard">


                @include('partials.schedule-warnings')
                @if(session('success'))
                    <div class="assignment-alert assignment-alert-success assign-projects-alert-floating" data-auto-hide="4000">{{ session('success') }}</div>
                @endif

                @if($errors->any())
                    <div class="assignment-alert assignment-alert-error">
                        <ul class="assignment-alert-list">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <section class="staff-assigned-list">
                    @forelse($validProjectAssignments as $assignment)
                        @php
                            $project = $assignment->project;
                            $tz = config('bac-office.display_timezone', 'Asia/Manila');
                            $portalStatus = $project->portalStatus();
                            $officialBids = $project->bids->reject(fn ($bid) => $bid->isDraft())->sortBy(fn ($bid) => $bid->submitted_at ?? $bid->created_at)->values();
                            $deadline = $project->bidSubmissionDeadline();
                            $openingAt = $project->schedule?->bid_opening_date;
                            // Technical documents a staff member still has to check (opened, latest version not yet reviewed).
                            $documentsToCheck = $project->bidsAreOpened()
                                ? $officialBids->sum(fn ($bid) => $bid->documents
                                    ->where('component', \App\Models\BidDocument::COMPONENT_TECHNICAL)
                                    ->filter(fn ($document) => $document->reviewEvents->sortByDesc('version')->first()?->status === \App\Models\BidDocumentReviewEvent::STATUS_PENDING)
                                    ->count())
                                : 0;
                            $published = in_array($project->status, \App\Models\Project::PUBLIC_STATUSES, true);
                            $tasks = array_values(array_filter([
                                $published && $project->philgeps_posted_at === null
                                    ? ['icon' => 'fa-bullhorn', 'text' => 'Record the PhilGEPS posting of the ITB / RFQ', 'url' => route('staff.procurement.show', $project), 'action' => 'Open record']
                                    : null,
                                $documentsToCheck > 0
                                    ? ['icon' => 'fa-file-circle-check', 'text' => $documentsToCheck.' bid '.\Illuminate\Support\Str::plural('document', $documentsToCheck).' to check', 'url' => route('staff.review-bids', ['project' => $project->id]), 'action' => 'Check documents']
                                    : null,
                            ]));
                        @endphp

                        <article class="staff-assigned-card">
                            <div class="staff-assigned-head">
                                <div class="staff-assigned-heading">
                                    <p class="staff-assigned-ref">
                                        <span>{{ $project->reference_no ?: 'No reference yet' }}</span>
                                        <span>{{ $project->mode()->label() }}</span>
                                        @if($assignment->role_in_project)<span>Your role: {{ $assignment->role_in_project }}</span>@endif
                                    </p>
                                    <h2>{{ $project->title }}</h2>
                                    <dl class="staff-assigned-facts">
                                        <div><dt>ABC</dt><dd>&#8369;{{ number_format((float) $project->budget, 2) }}</dd></div>
                                        <div><dt>End-user office</dt><dd>{{ $project->end_user_unit ?: '—' }}</dd></div>
                                        <div><dt>Submission deadline</dt><dd>{{ $deadline?->timezone($tz)->format('M d, Y g:i A') ?? '—' }}</dd></div>
                                        @if($openingAt)<div><dt>Bid opening</dt><dd>{{ $openingAt->timezone($tz)->format('M d, Y g:i A') }}</dd></div>@endif
                                    </dl>
                                </div>

                                <div class="staff-assigned-actions">
                                    <span class="staff-tone-pill is-{{ $portalStatus['tone'] }}">{{ $portalStatus['label'] }}</span>
                                    <a href="{{ route('staff.procurement.show', $project) }}" class="staff-button-secondary">Procurement record</a>
                                </div>
                            </div>

                            <div class="staff-assigned-body">
                                <div class="staff-assigned-tasks">
                                    <p class="staff-assigned-kicker">Your tasks</p>
                                    @forelse($tasks as $task)
                                        <a class="staff-task" href="{{ $task['url'] }}">
                                            <i class="fas {{ $task['icon'] }}" aria-hidden="true"></i>
                                            <span>{{ $task['text'] }}</span>
                                            <strong>{{ $task['action'] }} <i class="fas fa-arrow-right" aria-hidden="true"></i></strong>
                                        </a>
                                    @empty
                                        <p class="staff-task is-clear"><i class="fas fa-circle-check" aria-hidden="true"></i> <span>Nothing waiting on you for this project.</span></p>
                                    @endforelse
                                </div>

                                <p class="staff-assigned-kicker">Submitted bids ({{ $officialBids->count() }})</p>
                                <div class="staff-table-wrap">
                                    <table class="staff-subtable">
                                        <thead>
                                            <tr>
                                                <th>Bidder</th>
                                                <th>Submitted</th>
                                                <th>Amount</th>
                                                <th>Documents</th>
                                                <th>Stage</th>
                                                <th><span class="sr-only">Action</span></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($officialBids as $bid)
                                                @php $documentStatus = $bid->submissionDocumentStatus(); @endphp
                                                <tr>
                                                    <td><strong>{{ $bid->user?->company ?: ($bid->user?->name ?? 'N/A') }}</strong></td>
                                                    <td>
                                                        {{ ($bid->submitted_at ?? $bid->created_at)?->timezone($tz)->format('M d, Y g:i A') }}
                                                        <small class="staff-muted">{{ $bid->receipt_no ?: 'No receipt no.' }}</small>
                                                    </td>
                                                    <td><x-bid-amount :bid="$bid" /></td>
                                                    <td><span class="bid-document-status is-{{ $documentStatus['key'] }}">{{ $documentStatus['label'] }}</span></td>
                                                    <td><x-bid-status-badge :bid="$bid" /></td>
                                                    <td class="staff-cell-action">
                                                        <a class="staff-button-secondary" href="{{ route('staff.review-bids', ['project' => $project->id, 'view_bid' => $bid->id]) }}">Review bid</a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="staff-empty-cell">No submitted bids yet for this project.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </article>
                    @empty
                        <section class="staff-table-panel">
                            <div class="staff-empty-state">No projects are assigned to you yet. The BAC Admin assigns projects from Staff assignments.</div>
                        </section>
                    @endforelse
                </section>
            </section>
        </main>
    </div>
</div>


<script>
    (function () {
        const alert = document.querySelector('.assign-projects-alert-floating[data-auto-hide]');
        if (!alert) return;

        const delay = Number(alert.dataset.autoHide) || 4000;
        const fadeDuration = 350;

        window.setTimeout(function () {
            alert.classList.add('fade-out');

            window.setTimeout(function () {
                alert.remove();
            }, fadeDuration);
        }, delay);
    })();
</script>
