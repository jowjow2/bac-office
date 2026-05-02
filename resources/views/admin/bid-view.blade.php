<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard">
    @vite(['resources/css/dashboard.css'])

    <aside class="sidebar">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-logo-link"><h2 class="sidebar-logo">BAC-Office</h2></a>
        @include('partials.sidebar-profile')
        <ul class="sidebar-menu">
            <p class="menu-title">MAIN</p>
            <li><a href="{{ route('admin.dashboard') }}"><span class="menu-icon-dashboard" aria-hidden="true"></span> Dashboard</a></li>
            <li><a href="{{ route('admin.projects') }}"><i class="fas fa-folder-open"></i> Project/Biddings</a></li>
            <li><a href="{{ route('admin.bids') }}" class="active"><span class="menu-icon-all-bids" aria-hidden="true"></span> All Bids</a></li>
            <li><a href="{{ route('admin.awards') }}"><i class="fas fa-trophy"></i> Awards & Contracts</a></li>

            <p class="menu-title">MANAGEMENT</p>
            <li><a href="{{ route('admin.users') }}"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <li><a href="{{ route('admin.assignments') }}"><i class="fas fa-tasks"></i> Staff Assignments</a></li>
            <li><a href="{{ route('admin.reports') }}"><i class="fas fa-chart-bar"></i> Reports</a></li>

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
                <h2>Bid Details</h2>
                <p>Review submitted bidder information</p>
            </div>
        </header>

        <main class="dashboard-content">
            <div class="welcome-text">
                <h1 class="title">View Bid</h1>
                <p class="subtitle">Inspect the submitted bid proposal and bidder details.</p>
            </div>

            <div class="table-container" style="background: white; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden;">
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
                    <div>
                        <h3 style="margin: 0; font-size: 22px; color: #0f172a;">Bid #{{ $bid->id }}</h3>
                        <p style="margin: 6px 0 0; color: #64748b; font-size: 14px;">Submitted {{ $bid->created_at?->format('M d, Y h:i A') }}</p>
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="{{ route('admin.bid.edit', $bid) }}" class="btn-primary" style="text-decoration: none;">Edit Bid</a>
                        <a href="{{ route('admin.bids') }}" class="btn-secondary" style="text-decoration: none;">Back to All Bids</a>
                    </div>
                </div>

                <div style="padding: 24px; display: grid; gap: 18px;">
                    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #64748b;">Project</label>
                            <div class="bid-detail-box">{{ $bid->project->title ?? 'N/A' }}</div>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #64748b;">Bidder</label>
                            <div class="bid-detail-box">{{ $bid->user->company ?: ($bid->user->name ?? 'N/A') }}</div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #64748b;">Bid Amount</label>
                            <div class="bid-detail-box">P{{ number_format((float) $bid->amount, 2) }}</div>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #64748b;">Status</label>
                            <div class="bid-detail-box">{{ ucfirst($bid->status) }}</div>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #64748b;">Proposal File</label>
                            <div class="bid-detail-box">
                                @if($bid->proposal_url)
                                    <a
                                        href="{{ route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'proposal']) }}"
                                        target="_blank"
                                        rel="noopener"
                                        style="color: #1d4ed8; text-decoration: none;"
                                    >{{ $bid->proposal_filename }}</a>
                                @else
                                    No file uploaded
                                @endif
                            </div>
                        </div>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #64748b;">Notes</label>
                        <div class="bid-detail-box" style="min-height: 100px; align-items: flex-start; white-space: pre-wrap;">{{ $bid->notes ?: 'No notes provided.' }}</div>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #64748b;">Certificate Proof</label>
                        <div class="bid-detail-box">
                            @if($bid->user->philgepsCertificate?->file_url)
                                <a
                                    href="{{ route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'certificate']) }}"
                                    target="_blank"
                                    rel="noopener"
                                    style="color: #1d4ed8; text-decoration: none;"
                                >
                                    {{ $bid->user->philgepsCertificate->display_name }}
                                </a>
                            @else
                                No PhilGEPS certificate uploaded
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
    .bid-detail-box {
        width: 100%;
        min-height: 44px;
        display: flex;
        align-items: center;
        padding: 12px 14px;
        border: 1px solid #d5deeb;
        border-radius: 10px;
        background: #fff;
        color: #111827;
        font-size: 14px;
        line-height: 1.5;
        box-sizing: border-box;
    }

    @media (max-width: 900px) {
        .dashboard-content > .table-container > div:nth-child(2) > div {
            grid-template-columns: 1fr !important;
        }
    }
</style>
