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
                <h2>Edit Bid</h2>
                <p>Update bid details and review status</p>
            </div>
        </header>

        <main class="dashboard-content">
            <div class="welcome-text">
                <h1 class="title">Edit Bid</h1>
                <p class="subtitle">Adjust bid amount, status, and reviewer notes.</p>
            </div>

            @if ($errors->any())
                <div style="margin-bottom: 18px; background: #fee2e2; color: #991b1b; padding: 14px 16px; border-radius: 12px; border: 1px solid #fecaca;">
                    <strong style="display: block; margin-bottom: 6px;">Please fix the following:</strong>
                    <ul style="margin: 0; padding-left: 18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="table-container" style="background: white; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden;">
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                    <h3 style="margin: 0; font-size: 22px; color: #0f172a;">Bid #{{ $bid->id }}</h3>
                    <p style="margin: 6px 0 0; color: #64748b; font-size: 14px;">{{ $bid->project->title ?? 'N/A' }} • {{ $bid->user->company ?: ($bid->user->name ?? 'N/A') }}</p>
                </div>

                <form action="{{ route('admin.bid.update', $bid) }}" method="POST" style="padding: 24px; display: grid; gap: 18px;">
                    @csrf
                    @method('PUT')

                    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px;">
                        <div>
                            <label class="bid-field-label">Bid Amount (P)</label>
                            <input type="number" step="0.01" min="0" name="bid_amount" value="{{ old('bid_amount', $bid->amount) }}" class="bid-field-input">
                        </div>
                        <div>
                            <label class="bid-field-label">Status</label>
                            <select name="status" class="bid-field-input">
                                @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $bid->status) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="bid-field-label">Proposal File</label>
                        <div class="bid-detail-box">
                            @if($bid->proposal_url)
                                <a href="{{ route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'proposal']) }}" target="_blank" rel="noopener" style="color: #1d4ed8; text-decoration: none;">{{ $bid->proposal_filename }}</a>
                            @else
                                No file uploaded
                            @endif
                        </div>
                    </div>

                    <div>
                        <label class="bid-field-label">Notes</label>
                        <textarea name="notes" rows="5" class="bid-field-input" style="resize: vertical; min-height: 110px;">{{ old('notes', $bid->notes) }}</textarea>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
                        <a href="{{ route('admin.bid.view', $bid) }}" class="btn-secondary" style="text-decoration: none;">Cancel</a>
                        <button type="submit" class="btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</div>

<style>
    .bid-field-label {
        display: block;
        margin-bottom: 8px;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: #64748b;
    }

    .bid-field-input {
        width: 100%;
        min-height: 44px;
        padding: 12px 14px;
        border: 1px solid #d5deeb;
        border-radius: 10px;
        background: #fff;
        color: #111827;
        font-size: 14px;
        line-height: 1.5;
        box-sizing: border-box;
        font-family: inherit;
    }

    .bid-field-input:focus {
        outline: none;
        border-color: #93c5fd;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }

    .bid-detail-box {
        width: 100%;
        min-height: 44px;
        display: flex;
        align-items: center;
        padding: 12px 14px;
        border: 1px solid #d5deeb;
        border-radius: 10px;
        background: #f8fafc;
        color: #111827;
        font-size: 14px;
        line-height: 1.5;
        box-sizing: border-box;
    }

    @media (max-width: 900px) {
        .dashboard-content form > div:first-child {
            grid-template-columns: 1fr !important;
        }
    }
</style>
