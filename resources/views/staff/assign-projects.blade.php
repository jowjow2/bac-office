<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home">
    @vite(['resources/css/dashboard.css'])
    @include('partials.staff-page-styles')

    <style>
        .assign-projects-alert-floating {
            position: fixed;
            top: 92px;
            right: 24px;
            width: min(360px, calc(100vw - 32px));
            z-index: 2400;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.14);
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
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
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
            color: #0f172a;
        }

        .staff-assigned-meta {
            font-size: 12px;
            color: #94a3b8;
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
            color: #64748b;
        }

        .staff-assigned-kicker {
            margin: 0 0 12px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #475569;
        }

        .staff-subtable {
            width: 100%;
            border-collapse: collapse;
        }

        .staff-subtable thead th {
            padding: 12px 16px;
            background: #f8fafc;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #64748b;
        }

        .staff-subtable tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid #eef2f7;
            font-size: 12px;
            color: #0f172a;
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

        .staff-reviewed-label {
            font-size: 12px;
            color: #0f172a;
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
        @include('partials.staff-topbar', [
            'staffNavbarTitle' => 'Assigned Projects',
            'staffNavbarSubtitle' => 'Assigned project management',
        ])

        <main class="dashboard-content dashboard-home-content">
            <section class="staff-dashboard">


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
                            $submittedBids = $project->bids->sortByDesc('bid_amount');
                        @endphp

                        <article class="staff-assigned-card">
                            <div class="staff-assigned-head">
                                <div>
                                    <h2>{{ $project->title }}</h2>
                                    <div class="staff-assigned-meta">
                                        Budget: P{{ number_format((float) $project->budget, 2) }}
                                        &bull;
                                        Deadline: {{ $project->deadline ? $project->deadline->format('Y-m-d') : 'N/A' }}
                                    </div>
                                </div>

                                <div class="staff-assigned-actions">
                                    <span class="staff-status-pill {{ $project->status }}">{{ $project->status }}</span>
                                    @if($project->status === 'open')
                                        <form method="POST" action="{{ route('staff.projects.status', $project) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="closed">
                                            <button type="submit" class="staff-button-secondary">Close Bidding</button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            <div class="staff-assigned-body">
                                <p class="staff-assigned-description">{{ $project->description ?: 'No project description available.' }}</p>
                                <p class="staff-assigned-kicker">Submitted Bids ({{ $submittedBids->count() }})</p>

                                <div class="staff-table-wrap">
                                    <table class="staff-subtable">
                                        <thead>
                                            <tr>
                                                <th>Bidder</th>
                                                <th>Amount</th>
                                                <th>Variance</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($submittedBids as $bid)
                                                @php
                                                    $variance = $project->budget > 0
                                                        ? (((float) $bid->bid_amount - (float) $project->budget) / (float) $project->budget) * 100
                                                        : 0;
                                                @endphp
                                                <tr>
                                                    <td>{{ $bid->user->company ?: ($bid->user->name ?? 'N/A') }}</td>
                                                    <td>P{{ number_format((float) $bid->bid_amount, 2) }}</td>
                                                    <td class="{{ $variance <= 0 ? 'staff-variance-negative' : 'staff-variance-positive' }}">
                                                        {{ $variance > 0 ? '+' : '' }}{{ number_format($variance, 1) }}%
                                                    </td>
                                                    <td>
                                                        <span class="staff-status-pill {{ $bid->status }}">{{ $bid->status }}</span>
                                                    </td>
                                                    <td>
                                                        @if($bid->status === 'pending')
                                                            <form method="POST" action="{{ route('staff.bids.validate', $bid) }}">
                                                                @csrf
                                                                @method('PATCH')
                                                                <button type="submit" class="staff-button-success">Validate</button>
                                                            </form>
                                                        @else
                                                            <span class="staff-reviewed-label">&#10003; Reviewed</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="staff-empty-cell">No submitted bids yet for this project.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </article>
                    @empty
                        <section class="staff-table-panel">
                            <div class="staff-empty-state">No assigned projects available.</div>
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