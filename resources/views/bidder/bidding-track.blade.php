<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home bidder-bidding-track-page">
    @vite(['resources/css/dashboard.css', 'resources/js/dashboard.js', 'resources/js/app.js'])

    @include('partials.bidder-sidebar')

    <div class="main-area">
        <header class="navbar">
            <div class="nav-left">
                <h2>Live Bidding Track</h2>
                <p>Track your bid validation, approval, and award status in real time.</p>
            </div>

            <div class="nav-right">
                <div class="bidding-live-meta">
                    <span class="live-badge">
                        <span class="live-dot"></span>
                        Live Updating
                    </span>
                    <span class="last-updated-text" id="last-updated-text">Updated just now</span>
                </div>
                <a href="{{ route('bidder.notifications') }}" class="notification-button" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    @if(($bidderNotificationCount ?? 0) > 0)
                        <span class="notification-badge">{{ $bidderNotificationCount }}</span>
                    @endif
                </a>
            </div>
        </header>

        <main class="dashboard-content dashboard-home-content bidding-track-content">

            <!-- Main Content -->
            @if($bids->isEmpty())
                <!-- Empty State -->
                <div class="empty-bidding-state">
                    <div class="empty-icon">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <h3>You haven't submitted any bids yet.</h3>
                    <p class="empty-subtitle">Browse available projects to start bidding and track their progress here.</p>
                    <a href="{{ route('bidder.available-projects') }}" class="btn-primary">
                        Find Projects
                    </a>
                </div>
            @else
                <!-- Bids List -->
                <div class="bidding-track-list" id="bidding-track-container">
                    @foreach($bids as $bid)
                        @php
                            $steps = $bid->workflow_timeline_steps ?? [];
                        @endphp

                        <div class="bidding-track-card" data-bid-id="{{ $bid->id }}">
                            <!-- Card Header -->
                            <div class="bidding-track-card-top">
                                <div class="bidding-track-project">
                                    <h3 class="bidding-track-project-title">{{ $bid->project?->title ?? 'Unknown Project' }}</h3>
                                    <p class="bidding-track-budget">Budget: <span>&#8369;{{ number_format($bid->project?->budget ?? 0, 2) }}</span></p>
                                </div>
                                <div class="bidding-track-current">
                                    <span class="current-status-label">
                                        Current: {{ \App\Models\Bid::WORKFLOW_STEPS[$bid->effective_workflow_step] ?? 'Pending' }}
                                    </span>
                                    @if($bid->workflow_step_updated_by)
                                        <span class="last-updated-time">Updated by {{ $bid->workflowStepUpdater?->name ?? 'System' }}</span>
                                    @else
                                        <span class="last-updated-time">Updated {{ $bid->updated_at->diffForHumans() }}</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Timeline Section -->
                            <div class="bidding-track-timeline-scroll">
                                <div class="bidding-track-timeline">
                                    @foreach($steps as $key => $step)
                                        @php
                                            $label = $step['label'] ?? '';
                                            $isCompleted = $step['completed'] ?? false;
                                            $isCurrent = $step['current'] ?? false;
                                            $isFailed = ($step['status'] ?? '') === 'rejected';

                                            // Determine step class and icon
                                            if ($isFailed) {
                                                $stepClass = 'step-rejected';
                                                $icon = 'fas fa-times';
                                            } elseif ($isCurrent) {
                                                $stepClass = 'step-pending';
                                                $icon = 'fas fa-circle-notch fa-spin';
                                            } elseif ($isCompleted) {
                                                if ($label === 'Bid Submitted' || $label === 'Documents Validated' || $label === 'Awarded' || $label === 'Notice of Award Issued' || $label === 'Notice to Proceed Issued' || $label === 'Project Completed') {
                                                    $stepClass = 'step-completed';
                                                } elseif ($label === 'Approved') {
                                                    $stepClass = 'step-approved';
                                                } else {
                                                    $stepClass = 'step-completed';
                                                }
                                                $icon = 'fas fa-check';
                                            } else {
                                                $stepClass = 'step-inactive';
                                                $icon = 'fas fa-circle';
                                            }

                                            // Add verified class if step was verified by admin/staff
                                            if (!empty($step['verified'])) {
                                                $stepClass .= ' step-verified';
                                            }

                                            // Determine badge text and description
                                            switch($label) {
                                                case 'Bid Submitted':
                                                    $badgeText = 'Submitted';
                                                    $descText = 'Your bid has been submitted.';
                                                    break;
                                                case 'Pending Validation':
                                                    $badgeText = 'Pending';
                                                    $descText = 'Documents are waiting for validation.';
                                                    break;
                                                case 'Documents Validated':
                                                    $badgeText = 'Completed';
                                                    $descText = 'Eligibility documents have been validated.';
                                                    break;
                                                case 'For BAC Evaluation':
                                                    $badgeText = 'Pending';
                                                    $descText = 'Bid is under BAC evaluation.';
                                                    break;
                                                case 'Approved':
                                                    $badgeText = 'Approved';
                                                    $descText = 'Your bid passed evaluation.';
                                                    break;
                                                case 'Disqualified':
                                                    $badgeText = ($step['status'] ?? '') === 'rejected' ? 'Rejected' : 'Pending';
                                                    $descText = ($step['status'] ?? '') === 'rejected' ? 'Bid did not meet requirements.' : 'Awaiting final decision.';
                                                    break;
                                                case 'Awarded':
                                                    $badgeText = 'Completed';
                                                    $descText = 'Project was awarded.';
                                                    break;
                                                case 'Not Awarded':
                                                    $badgeText = ($step['status'] ?? '') === 'rejected' ? 'Rejected' : 'Pending';
                                                    $descText = ($step['status'] ?? '') === 'rejected' ? 'Project was awarded to another bidder.' : 'Awaiting award decision.';
                                                    break;
                                                case 'Notice of Award Issued':
                                                    $badgeText = 'Completed';
                                                    $descText = 'Notice of Award has been issued.';
                                                    break;
                                                case 'Notice to Proceed Issued':
                                                    $badgeText = 'Completed';
                                                    $descText = 'Notice to Proceed has been issued.';
                                                    break;
                                                case 'Project Completed':
                                                    $badgeText = 'Completed';
                                                    $descText = 'Project has been completed.';
                                                    break;
                                                default:
                                                    $badgeText = 'Pending';
                                                    $descText = $step['time'] ?? 'Pending...';
                                            }
                                        @endphp
                                        
                                        <!-- Timeline Step -->
                                        <div class="bidding-step {{ $stepClass }}">
                                            <div class="bidding-step-icon">
                                                <i class="{{ $icon }}"></i>
                                            </div>
                                            <div class="bidding-step-content">
                                                <h4 class="bidding-step-title">{{ $label }}</h4>
                                                <p class="bidding-step-desc">{{ $descText }}</p>
                                            </div>
                                            <span class="bidding-step-badge">{{ $badgeText }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </main>
    </div>
</div>

<script>
    (function () {
        const trackPage = document.querySelector('.bidder-bidding-track-page');
        if (!trackPage) return;

        function updateTimeline(bidId, stepsData) {
            const card = document.querySelector(`.bidding-track-card[data-bid-id="${bidId}"]`);
            if (!card) return;

            const stepsEl = card.querySelector('.bidding-track-timeline');
            if (!stepsEl) return;

            let html = '';
            const stepKeys = Object.keys(stepsData);

            stepKeys.forEach((key, idx) => {
                const step = stepsData[key];
                const label = step.label || '';
                const isCompleted = step.completed;
                const isCurrent = step.current;
                const isFailed = step.status === 'rejected';

                let stepClass, icon, badgeText, descText;

                if (isFailed) {
                    stepClass = 'step-rejected';
                    icon = 'fas fa-times';
                    badgeText = 'Rejected';
                    descText = (label === 'Disqualified') ? 'Bid did not meet requirements.' : ((label === 'Not Awarded') ? 'Project was awarded to another bidder.' : (step.time || 'Rejected'));
                } else if (isCurrent) {
                    stepClass = 'step-pending';
                    icon = 'fas fa-circle-notch fa-spin';
                    badgeText = 'Pending';
                    if (label === 'Bid Submitted') descText = 'Your bid has been submitted.';
                    else if (label === 'Pending Validation') descText = 'Documents are waiting for validation.';
                    else if (label === 'For BAC Evaluation') descText = 'Bid is under BAC evaluation.';
                    else descText = step.time || 'Pending...';
                } else if (isCompleted) {
                    icon = 'fas fa-check';
                    if (label === 'Bid Submitted') {
                        stepClass = 'step-submitted';
                        badgeText = 'Submitted';
                        descText = 'Your bid has been submitted.';
                    } else if (label === 'Approved') {
                        stepClass = 'step-approved';
                        badgeText = 'Approved';
                        descText = 'Your bid passed evaluation.';
                    } else {
                        stepClass = 'step-completed';
                        badgeText = 'Completed';
                        switch(label) {
                            case 'Documents Validated': descText = 'Eligibility documents have been validated.'; break;
                            case 'Awarded': descText = 'Project was awarded.'; break;
                            case 'Notice of Award Issued': descText = 'Notice of Award has been issued.'; break;
                            case 'Notice to Proceed Issued': descText = 'Notice to Proceed has been issued.'; break;
                            case 'Project Completed': descText = 'Project has been completed.'; break;
                            default: descText = step.time || 'Completed';
                        }
                    }
                } else {
                    stepClass = 'step-inactive';
                    icon = 'fas fa-circle';
                    badgeText = 'Pending';
                    if (label === 'Pending Validation') descText = 'Documents are waiting for validation.';
                    else if (label === 'For BAC Evaluation') descText = 'Bid is under BAC evaluation.';
                    else descText = step.time || 'Pending...';
                }

                // Add verified class if step was verified by admin/staff
                if (step.verified) {
                    stepClass += ' step-verified';
                }

                html += `
                    <div class="bidding-step ${stepClass}">
                        <div class="bidding-step-icon">
                            <i class="${icon}"></i>
                        </div>
                        <div class="bidding-step-content">
                            <h4 class="bidding-step-title">${label}</h4>
                            <p class="bidding-step-desc">${descText}</p>
                        </div>
                        <span class="bidding-step-badge">${badgeText}</span>
                    </div>
                `;
            });

            stepsEl.innerHTML = html;
            
            const statusLabel = card.querySelector('.current-status-label');
            if (statusLabel) {
                const currentStepKey = stepKeys.find(k => stepsData[k].current);
                if (currentStepKey) statusLabel.innerText = 'Current: ' + (stepsData[currentStepKey].label || 'Pending');
            }

            const updateTime = card.querySelector('.last-updated-time');
            if (updateTime) updateTime.innerText = 'Updated just now';
            
            const headerUpdate = document.getElementById('last-updated-text');
            if (headerUpdate) {
                const now = new Date();
                const timeStr = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                headerUpdate.innerHTML = `Updated: <span style="font-weight:600;color:#059669">${timeStr}</span>`;
            }
        }

        // WebSocket listener
        if (typeof Echo !== 'undefined') {
            Echo.private('bidding-track.' + {{ Auth::id() }})
                .listen('BidWorkflowUpdated', (e) => {
                    updateTimeline(e.id, e.timeline_steps);
                });
        }

        window.addEventListener('bac:bidding-track-updated', function (event) {
            const bids = event.detail?.bids || [];
            bids.forEach(function (bid) {
                updateTimeline(bid.id, bid.timeline_steps);
            });
        });

        // Polling fallback for real-time updates.
        setInterval(() => {
            fetch('{{ route("bidder.bidding-track.data") }}?t=' + Date.now())
                .then(res => res.json())
                .then(data => {
                    if (data.ok) {
                        data.bids.forEach(bid => {
                            updateTimeline(bid.id, bid.timeline_steps);
                        });
                    }
                })
                .catch(() => {});
        }, 2000);
    })();
</script>
