<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home">
    @vite(['resources/css/dashboard.css'])

    <aside class="sidebar">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-logo-link"><h2 class="sidebar-logo">BAC-Office</h2></a>
        @include('partials.sidebar-profile')
        <ul class="sidebar-menu">
            <p class="menu-title">MAIN</p>
            <li><a href="{{ route('admin.dashboard') }}" class="active"><span class="menu-icon-dashboard" aria-hidden="true"></span> Dashboard</a></li>
            <li><a href="{{ route('admin.projects') }}"><i class="fas fa-folder-open"></i> Project/Biddings</a></li>
            <li><a href="{{ route('admin.bids') }}"><span class="menu-icon-all-bids" aria-hidden="true"></span> All Bids</a></li>
            <li><a href="{{ route('admin.awards') }}"><i class="fas fa-trophy"></i> Awards & Contracts</a></li>

            <p class="menu-title">MANAGEMENT</p>
            <li><a href="{{ route('admin.users') }}"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <li><a href="{{ route('admin.assignments') }}"><i class="fas fa-tasks"></i> Staff Assignments</a></li>
            <li><a href="/admin/reports"><i class="fas fa-chart-bar"></i> Reports</a></li>

            <p class="menu-title">SYSTEM</p>
            <li>
                <a href="{{ route('admin.notifications') }}">
                    <i class="fas fa-bell"></i> Notifications
                    @if(($unreadNotificationsCount ?? 0) > 0)
                        <span class="notification-badge">{{ $unreadNotificationsCount }}</span>
                    @endif
                </a>
            </li>

            <li>
                <form action="{{ route('logout') }}" method="POST" class="sidebar-form">
                    @csrf
                    <button type="submit" class="sidebar-logout"><i class="fas fa-sign-out-alt"></i> Logout</button>
                </form>
            </li>
        </ul>
    </aside>

    <div class="main-area">
        <header class="navbar">
            <div class="nav-left">
                <h2>Admin Dashboard</h2>
                <p>Procurement monitoring, bid control, and award tracking</p>
            </div>

            <div class="nav-right">
                <div class="nav-icons">
                    @php
                        $navbarUser = auth()->user();
                        $navbarName = $navbarUser->name ?? 'System Administrator';
                        $navbarRole = ucfirst($navbarUser->role ?? 'admin');
                        $navbarInitials = collect(preg_split('/\s+/', trim($navbarName)))
                            ->filter()
                            ->take(2)
                            ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
                            ->implode('');
                    @endphp
                    <span id="realtimeDate"></span>
                    <div class="notification-menu">
                        <button type="button" class="notification-button" id="notificationToggle">
                            <i class="fas fa-bell"></i>
                            @if($unreadNotificationsCount > 0)
                                <span class="notification-badge">{{ $unreadNotificationsCount }}</span>
                            @endif
                        </button>

                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-header">
                                <strong>Notifications</strong>
                                <span>{{ $unreadNotificationsCount }} unread</span>
                            </div>

                            @forelse($adminNotifications as $notification)
                                <a href="{{ route('admin.notifications') }}" class="notification-item">
                                    <div class="notification-item-title">{{ $notification['title'] }}</div>
                                    <div class="notification-item-meta">
                                        {{ $notification['message'] }}
                                        <br>
                                        {{ $notification['time'] }}
                                    </div>
                                </a>
                            @empty
                                <div class="notification-empty">No notifications right now.</div>
                            @endforelse

                            <a href="{{ route('admin.notifications') }}" class="notification-footer">Open Notifications</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="dashboard-content dashboard-home-content">
            <section class="dashboard-home-intro">
                <h1 class="dashboard-home-title">Welcome back, Admin <span class="dashboard-wave">👋</span></h1>
                <p class="dashboard-home-subtitle">Here's what's happening across all procurement projects.</p>
            </section>

            <section class="dashboard-summary-grid">
                <article class="dashboard-summary-card">
                    <div class="summary-icon summary-icon-blue">
                        <i class="fas fa-chart-column"></i>
                    </div>
                    <div class="summary-copy">
                        <strong>{{ $totalProjects }}</strong>
                        <h3>Total Projects</h3>
                        <p>+{{ $activeProjects }} open projects</p>
                    </div>
                </article>

                <article class="dashboard-summary-card">
                    <div class="summary-icon summary-icon-gold">
                        <i class="fas fa-square-check"></i>
                    </div>
                    <div class="summary-copy">
                        <strong>{{ $totalBids }}</strong>
                        <h3>Active Bids</h3>
                        <p>Across {{ $activeProjects }} open projects</p>
                    </div>
                </article>

                <article class="dashboard-summary-card">
                    <div class="summary-icon summary-icon-green">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="summary-copy">
                        <strong>{{ $registeredBidders }}</strong>
                        <h3>Active Bidders</h3>
                        <p>{{ $pendingRegistrationsCount }} pending approval</p>
                    </div>
                </article>

                <article class="dashboard-summary-card">
                    <div class="summary-icon summary-icon-sky">
                        <i class="fas fa-award"></i>
                    </div>
                    <div class="summary-copy">
                        <strong>{{ $awardedProjects }}</strong>
                        <h3>Awarded Contracts</h3>
                        <p>P{{ number_format($totalAwardedAmount, 2) }}</p>
                    </div>
                </article>
            </section>

            <section class="dashboard-main-grid">
                <article class="dashboard-panel dashboard-status-panel">
                    <div class="dashboard-panel-header">
                        <div>
                            <h2>Project Status Overview</h2>
                            <p>Current procurement pipeline</p>
                        </div>
                    </div>

                    @php
                        $approvedForBiddingPercent = $totalProjects > 0 ? round(($approvedForBiddingProjects / $totalProjects) * 100) : 0;
                        $openPercent = $totalProjects > 0 ? round(($activeProjects / $totalProjects) * 100) : 0;
                        $closedPercent = $totalProjects > 0 ? round(($closedProjects / $totalProjects) * 100) : 0;
                        $awardedPercent = $totalProjects > 0 ? round(($awardedProjects / $totalProjects) * 100) : 0;
                    @endphp

                    <div class="status-overview-list">
                        <div class="status-overview-item">
                            <div class="status-overview-meta">
                                <span>Approved for Bidding</span>
                                <strong>{{ $approvedForBiddingProjects }} projects ({{ $approvedForBiddingPercent }}%)</strong>
                            </div>
                            <div class="status-progress">
                                <span class="status-progress-bar status-progress-approved-bidding" style="width: {{ $approvedForBiddingPercent }}%;"></span>
                            </div>
                        </div>

                        <div class="status-overview-item">
                            <div class="status-overview-meta">
                                <span>Open</span>
                                <strong>{{ $activeProjects }} projects ({{ $openPercent }}%)</strong>
                            </div>
                            <div class="status-progress">
                                <span class="status-progress-bar status-progress-open" style="width: {{ $openPercent }}%;"></span>
                            </div>
                        </div>

                        <div class="status-overview-item">
                            <div class="status-overview-meta">
                                <span>Closed</span>
                                <strong>{{ $closedProjects }} projects ({{ $closedPercent }}%)</strong>
                            </div>
                            <div class="status-progress">
                                <span class="status-progress-bar status-progress-closed" style="width: {{ $closedPercent }}%;"></span>
                            </div>
                        </div>

                        <div class="status-overview-item">
                            <div class="status-overview-meta">
                                <span>Awarded</span>
                                <strong>{{ $awardedProjects }} projects ({{ $awardedPercent }}%)</strong>
                            </div>
                            <div class="status-progress">
                                <span class="status-progress-bar status-progress-awarded" style="width: {{ $awardedPercent }}%;"></span>
                            </div>
                        </div>
                    </div>

                    <div class="status-tag-list">
                        @forelse($recentProjects as $project)
                            <span class="status-tag">{{ $project->title }}</span>
                        @empty
                            <span class="status-tag">No projects yet</span>
                        @endforelse
                    </div>
                </article>

                <article class="dashboard-panel dashboard-table-panel">
                    <div class="dashboard-panel-header">
                        <div>
                            <h2>Recent Bid Activity</h2>
                            <p>Latest submissions</p>
                        </div>
                        <a href="{{ route('admin.bids') }}" class="dashboard-panel-button">View All</a>
                    </div>

                    <div class="dashboard-table-wrap">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Bidder</th>
                                    <th>Project</th>
                                    <th>Amount</th>
                                    <th>Certificate Proof</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($latestBids as $bid)
                                    @php
                                        $certificateProof = $bid->user->philgepsCertificate;
                                        $certificateProofUrl = $certificateProof?->file_url
                                            ? route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'certificate'])
                                            : null;
                                    @endphp
                                    <tr>
                                        <td>{{ $bid->user->company ?: ($bid->user->name ?? 'N/A') }}</td>
                                        <td>{{ $bid->project->title ?? 'N/A' }}</td>
                                        <td>P{{ number_format((float) $bid->bid_amount, 2) }}</td>
                                        <td>
                                            @if($certificateProofUrl)
                                                <a href="{{ $certificateProofUrl }}" target="_blank" rel="noopener" style="color: #1d4ed8; text-decoration: none; font-weight: 600;">View Proof</a>
                                            @else
                                                <span class="dashboard-badge dashboard-badge-pending" style="background: #e5e7eb; color: #475569;">Missing</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="dashboard-badge dashboard-badge-{{ $bid->status }}">
                                                {{ ucfirst($bid->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="empty-cell">No bid activity yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>

            <section class="dashboard-main-grid dashboard-lower-grid">
                <article class="dashboard-panel">
                    <div class="dashboard-panel-header">
                        <div>
                            <h2>Pending Approvals</h2>
                            <p>Requires admin action</p>
                        </div>
                        <a href="{{ route('admin.users') }}" class="dashboard-panel-button">Manage</a>
                    </div>

                    <div class="approval-list">
                        @forelse($pendingRegistrations as $registration)
                            @php
                                $registrationCertificateUrl = $registration->philgepsCertificate?->file_url;
                            @endphp
                            <div class="approval-item">
                                <div class="approval-item-main">
                                    <strong>{{ $registration->company ?: $registration->name }}</strong>
                                    <p>{{ $registration->email }}</p>
                                    @if($registrationCertificateUrl)
                                        <a href="{{ $registrationCertificateUrl }}" target="_blank" rel="noopener" style="display: inline-flex; margin-top: 6px; font-size: 12px; font-weight: 600; color: #1d4ed8; text-decoration: none;">View PhilGEPS certificate</a>
                                    @else
                                        <p style="margin-top: 6px; font-size: 12px; color: #94a3b8;">No PhilGEPS certificate uploaded</p>
                                    @endif
                                </div>
                                <span class="dashboard-badge dashboard-badge-pending">Pending</span>
                            </div>
                        @empty
                            <div class="empty-state">No pending bidder registrations.</div>
                        @endforelse
                    </div>
                </article>

                <article class="dashboard-panel">
                    <div class="dashboard-panel-header">
                        <div>
                            <h2>Budget Utilization</h2>
                            <p>Allocated vs awarded</p>
                        </div>
                    </div>

                    @php
                        $budgetPercent = $totalBudgetAllocated > 0 ? min(100, round(($totalAwardedAmount / $totalBudgetAllocated) * 100)) : 0;
                    @endphp

                    <div class="budget-figure">P{{ number_format($totalAwardedAmount, 2) }}</div>
                    <p class="budget-caption">Awarded out of P{{ number_format($totalBudgetAllocated, 2) }} total project budget</p>

                    <div class="status-progress budget-progress">
                        <span class="status-progress-bar status-progress-open" style="width: {{ $budgetPercent }}%;"></span>
                    </div>

                    <div class="budget-stats">
                        <div class="budget-stat">
                            <span>Approved Bids</span>
                            <strong>{{ $approvedBids }}</strong>
                        </div>
                        <div class="budget-stat">
                            <span>Rejected Bids</span>
                            <strong>{{ $rejectedBids }}</strong>
                        </div>
                        <div class="budget-stat">
                            <span>Staff Members</span>
                            <strong>{{ $staffMembers }}</strong>
                        </div>
                    </div>
                </article>
            </section>

            <section class="dashboard-main-grid dashboard-lower-grid">
                <article class="dashboard-panel dashboard-table-panel" style="grid-column: 1 / -1;">
                    <div class="dashboard-panel-header">
                        <div>
                            <h2>Uploaded Approved Bids</h2>
                            <p>Approved bid submissions with uploaded proposal files</p>
                        </div>
                        <a href="{{ route('admin.bids', ['status' => 'approved', 'proposal' => 'uploaded']) }}" class="dashboard-panel-button">View All</a>
                    </div>

                    <div class="dashboard-table-wrap">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Bidder</th>
                                    <th>Project</th>
                                    <th>Amount</th>
                                    <th>Proposal</th>
                                    <th>Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($uploadedApprovedBids as $bid)
                                    <tr>
                                        <td>{{ $bid->user->company ?: ($bid->user->name ?? 'N/A') }}</td>
                                        <td>{{ $bid->project->title ?? 'N/A' }}</td>
                                        <td>P{{ number_format((float) $bid->bid_amount, 2) }}</td>
                                        <td>
                                            @if($bid->proposal_url)
                                                <a href="{{ route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'proposal']) }}" target="_blank" rel="noopener" style="color: #1d4ed8; text-decoration: none; font-weight: 600;">View Proposal</a>
                                            @else
                                                <span class="dashboard-badge dashboard-badge-pending" style="background: #e5e7eb; color: #475569;">Missing</span>
                                            @endif
                                        </td>
                                        <td>{{ $bid->created_at?->format('M d, Y') ?? 'N/A' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="empty-cell">No approved bids with uploaded proposals yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        </main>
    </div>
</div>

<script>
    function updateRealtimeDate() {
        const dateElement = document.getElementById('realtimeDate');
        if (!dateElement) return;

        const now = new Date();
        dateElement.textContent = now.toLocaleString('en-PH', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
        });
    }

    updateRealtimeDate();
    setInterval(updateRealtimeDate, 1000);

    const notificationToggle = document.getElementById('notificationToggle');
    const notificationDropdown = document.getElementById('notificationDropdown');

    if (notificationToggle && notificationDropdown) {
        notificationToggle.addEventListener('click', function (event) {
            event.stopPropagation();
            notificationDropdown.classList.toggle('show');
        });

        document.addEventListener('click', function (event) {
            if (!notificationDropdown.contains(event.target) && !notificationToggle.contains(event.target)) {
                notificationDropdown.classList.remove('show');
            }
        });
    }
</script>
