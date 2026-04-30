<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home">
    @vite(['resources/css/dashboard.css'])
    @include('partials.staff-page-styles')

    <style>
        .review-bids-alert-floating {
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

        .review-bids-alert-floating.fade-out {
            opacity: 0;
            transform: translateY(-10px);
        }

        @media (max-width: 768px) {
            .review-bids-alert-floating {
                top: 84px;
                right: 16px;
            }
        }
    </style>

    @include('partials.staff-sidebar', ['activeStaffMenu' => 'review-bids'])

    <div class="main-area">
        @include('partials.staff-topbar', [
            'staffNavbarTitle' => 'Review Bids',
            'staffNavbarSubtitle' => 'Bid submissions review',
        ])

        <main class="dashboard-content dashboard-home-content">
            <section class="staff-dashboard">
                @php
                    $reviewPendingBids = $allAssignedBids->where('status', 'pending')->values();
                @endphp
                <section class="staff-page-intro">
                    <h1 class="staff-page-title">Review Bids</h1>
                    <p class="staff-page-subtitle">Validate or reject pending bid documents before admin review.</p>
                </section>

                @if(session('success'))
                    <div class="assignment-alert assignment-alert-success review-bids-alert-floating" data-auto-hide="4000">{{ session('success') }}</div>
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

                <section class="staff-table-panel">
                    <div class="staff-table-header">
                        <h2>Pending Bid Submissions</h2>
                    </div>

                    <div class="staff-table-wrap">
                        <table class="staff-table">
                            <thead>
                                <tr>
                                    <th>Bidder</th>
                                    <th>Project</th>
                                    <th>Bid Amount</th>
                                    <th>Documents</th>
                                    <th>Status</th>
                                    <th>Review</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reviewPendingBids as $bid)
                                    <tr>
                                        <td>{{ $bid->user->company ?: ($bid->user->name ?? 'N/A') }}</td>
                                        <td class="staff-project-title">{{ $bid->project->title ?? 'N/A' }}</td>
                                        <td>P{{ number_format((float) $bid->bid_amount, 2) }}</td>
                                        <td>
                                            <div class="staff-document-cell">
                                                @if($bid->proposal_file)
                                                    <a href="{{ route('staff.bids.proposal.download', $bid) }}" class="staff-document-link">Download proposal</a>
                                                @endif
                                                <span class="staff-status-pill {{ $bid->proposal_file ? 'approved' : 'rejected' }}">
                                                    {{ $bid->proposal_file ? 'uploaded' : 'missing' }}
                                                </span>
                                            </div>
                                        </td>
                                        <td><span class="staff-status-pill {{ $bid->status }}">{{ $bid->status }}</span></td>
                                        <td>
                                            <div class="staff-inline-actions">
                                                <form method="POST" action="{{ route('staff.bids.validate', $bid) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="staff-button-success">Validate</button>
                                                </form>
                                                <form method="POST" action="{{ route('staff.bids.reject', $bid) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="staff-button-danger">Reject</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="staff-empty-cell">No pending bid submissions found for your assigned projects.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>
        </main>
    </div>
</div>

<script>
    (function () {
        const alert = document.querySelector('.review-bids-alert-floating[data-auto-hide]');
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
