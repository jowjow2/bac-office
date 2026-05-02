<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
@vite(['resources/css/dashboard.css'])

<style>
    .bidder-review-page,
    .bidder-review-page * {
        font-family: 'Inter', sans-serif;
    }

    .bidder-review-page .review-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(300px, 0.85fr);
        gap: 18px;
    }

    .bidder-review-page .review-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 20px;
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
        padding: 22px;
    }

    .bidder-review-page .review-card h3 {
        margin: 0 0 14px;
        font-size: 20px;
        color: #0f172a;
    }

    .bidder-review-page .review-meta-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .bidder-review-page .review-meta {
        padding: 14px;
        border-radius: 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .bidder-review-page .review-meta span {
        display: block;
        margin-bottom: 6px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.06em;
        color: #64748b;
        text-transform: uppercase;
    }

    .bidder-review-page .review-meta strong,
    .bidder-review-page .review-meta p {
        margin: 0;
        color: #111827;
        font-size: 14px;
        line-height: 1.7;
    }

    .bidder-review-page .review-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 5px 12px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .bidder-review-page .review-pill.pending {
        background: #fef3c7;
        color: #92400e;
    }

    .bidder-review-page .review-pill.approved,
    .bidder-review-page .review-pill.active {
        background: #dcfce7;
        color: #166534;
    }

    .bidder-review-page .review-pill.rejected,
    .bidder-review-page .review-pill.revoked {
        background: #fee2e2;
        color: #991b1b;
    }

    .bidder-review-page .review-list {
        display: grid;
        gap: 12px;
    }

    .bidder-review-page .review-list-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        padding: 14px 16px;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .bidder-review-page .review-list-item strong {
        display: block;
        color: #0f172a;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .bidder-review-page .review-list-item span {
        color: #64748b;
        font-size: 12px;
    }

    .bidder-review-page .review-actions,
    .bidder-review-page .review-inline-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .bidder-review-page .review-primary,
    .bidder-review-page .review-secondary,
    .bidder-review-page .review-danger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border-radius: 12px;
        padding: 11px 16px;
        border: none;
        cursor: pointer;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
    }

    .bidder-review-page .review-primary {
        background: #1d4f91;
        color: #ffffff;
    }

    .bidder-review-page .review-secondary {
        background: #ffffff;
        border: 1px solid #d1d5db;
        color: #334155;
    }

    .bidder-review-page .review-danger {
        background: #b91c1c;
        color: #ffffff;
    }

    .bidder-review-page textarea,
    .bidder-review-page input[type="text"] {
        width: 100%;
        border-radius: 14px;
        border: 1px solid #d1d5db;
        padding: 12px 14px;
        font-size: 14px;
        color: #111827;
        background: #f8fafc;
    }

    .bidder-review-page textarea {
        min-height: 110px;
        resize: vertical;
    }

    .bidder-review-page table {
        width: 100%;
        border-collapse: collapse;
    }

    .bidder-review-page table th,
    .bidder-review-page table td {
        text-align: left;
        padding: 12px 10px;
        border-bottom: 1px solid #edf2f7;
        font-size: 13px;
    }

    .bidder-review-page table th {
        color: #64748b;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    @media (max-width: 980px) {
        .bidder-review-page .review-grid,
        .bidder-review-page .review-meta-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="admin-dashboard bidder-review-page">
    <aside class="sidebar">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-logo-link"><h2 class="sidebar-logo">BAC-Office</h2></a>
        @include('partials.sidebar-profile')
        <ul class="sidebar-menu">
            <p class="menu-title">MAIN</p>
            <li><a href="{{ route('admin.dashboard') }}"><span class="menu-icon-dashboard" aria-hidden="true"></span> Dashboard</a></li>
            <li><a href="{{ route('admin.projects') }}"><i class="fas fa-folder-open"></i> Project/Biddings</a></li>
            <li><a href="{{ route('admin.bids') }}"><span class="menu-icon-all-bids" aria-hidden="true"></span> All Bids</a></li>
            <li><a href="{{ route('admin.awards') }}"><i class="fas fa-trophy"></i> Awards & Contracts</a></li>

            <p class="menu-title">MANAGEMENT</p>
            <li><a href="{{ route('admin.users') }}" class="active"><i class="fas fa-users-cog"></i> Manage Users</a></li>
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
                <div>
                    <h2>Bidder Review</h2>
<p>Review registration documents, approval status, and login activity.</p>
                </div>
            </div>
            <div class="nav-right">
                <a href="{{ route('admin.users') }}" class="review-secondary"><i class="fas fa-arrow-left"></i> Back to Users</a>
            </div>
        </header>

        <main class="dashboard-content">
            @if(session('success'))
                <div class="success-alert" style="margin-bottom: 18px;">{{ session('success') }}</div>
            @endif

            @if(session('warning'))
                <div class="warning-alert" style="margin-bottom: 18px; padding: 14px 16px; border-radius: 14px; background: #fff7ed; border: 1px solid #fdba74; color: #9a3412;">
                    {{ session('warning') }}
                </div>
            @endif

            @if($errors->any())
                <div class="error-alert" style="margin-bottom: 18px;">
                    <ul style="margin: 0; padding-left: 18px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="welcome-text" style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; margin-bottom:18px;">
                <div>
                    <h1 class="title" style="font-size: 28px; font-weight: 700; color: #0f172a; margin-bottom: 6px;">{{ $user->name }}</h1>
                    <p class="subtitle" style="font-size: 14px; color: #64748b;">{{ $user->email }} @if($user->username) • {{ '@' . $user->username }} @endif</p>
                </div>
                <div class="review-inline-actions">
                    <span class="review-pill {{ $user->bidderProfile?->approval_status ?? $user->status }}">{{ ucfirst($user->bidderProfile?->approval_status ?? $user->status) }}</span>
                    <span class="review-pill {{ $user->status }}">{{ ucfirst($user->status) }}</span>
                </div>
            </div>

            <div class="review-grid">
                <div style="display:grid; gap:18px;">
                    <section class="review-card">
                        <h3>Bidder Profile</h3>
                        <div class="review-meta-grid">
                            <div class="review-meta">
                                <span>Company Name</span>
                                <strong>{{ $user->bidderProfile?->company_name ?? $user->company ?? 'N/A' }}</strong>
                            </div>
                            <div class="review-meta">
                                <span>Contact Number</span>
                                <p>{{ $user->bidderProfile?->contact_number ?? 'N/A' }}</p>
                            </div>
                            <div class="review-meta" style="grid-column: 1 / -1;">
                                <span>Business Address</span>
                                <p>{{ $user->bidderProfile?->business_address ?? 'N/A' }}</p>
                            </div>
                            <div class="review-meta">
                                <span>Approved At</span>
                                <p>{{ $user->bidderProfile?->approved_at?->format('M d, Y h:i A') ?? 'Not yet approved' }}</p>
                            </div>
                            <div class="review-meta">
                                <span>Approved By</span>
                                <p>{{ $user->bidderProfile?->approver?->name ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="review-card">
                        <h3>Registration Documents</h3>
                        <div class="review-list">
                            @forelse($registrationDocuments as $document)
                                <div class="review-list-item">
                                    <div>
                                        <strong>{{ $document->document_type }}</strong>
                                        <span>{{ $document->display_name }} • Uploaded {{ $document->uploaded_at?->format('M d, Y h:i A') ?? $document->created_at?->format('M d, Y h:i A') }}</span>
                                    </div>
                                    <a href="{{ route('admin.user.document.preview', ['user' => $user, 'document' => $document]) }}" target="_blank" class="review-secondary">Preview File</a>
                                </div>
                            @empty
                                <div class="review-list-item">
                                    <div>
                                        <strong>No registration documents found.</strong>
                                        <span>The bidder has not uploaded any registration requirements yet.</span>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    </section>

                    <section class="review-card">
                        <h3>Additional Bidder Documents</h3>
                        <div class="review-list">
                            @forelse($supportingDocuments as $document)
                                <div class="review-list-item">
                                    <div>
                                        <strong>{{ $document->document_type }}</strong>
                                        <span>{{ $document->display_name }}</span>
                                    </div>
                                    <a href="{{ route('admin.user.document.preview', ['user' => $user, 'document' => $document]) }}" target="_blank" class="review-secondary">Preview File</a>
                                </div>
                            @empty
                                <div class="review-list-item">
                                    <div>
                                        <strong>No additional bidder documents uploaded.</strong>
                                        <span>Supporting profile documents will appear here when available.</span>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    </section>

                    <section class="review-card">
                        <h3>Recent Login Activity</h3>
                        <div style="overflow-x:auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>IP Address</th>
                                        <th>Device</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($user->loginLogs as $log)
                                        <tr>
                                            <td>{{ $log->created_at?->format('M d, Y h:i A') ?? 'N/A' }}</td>
                                            <td>{{ strtoupper($log->login_method) }}</td>
                                            <td>{{ ucfirst($log->status) }}@if($log->failure_reason) ({{ str_replace('_', ' ', $log->failure_reason) }}) @endif</td>
                                            <td>{{ $log->ip_address ?? 'N/A' }}</td>
                                            <td>{{ \Illuminate\Support\Str::limit($log->user_agent ?? 'N/A', 60) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" style="color:#64748b;">No login activity recorded yet for this bidder.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <div style="display:grid; gap:18px;">
<section class="review-card">
                        <h3>Approval Actions</h3>
                        <p style="margin: 0 0 16px; color: #64748b; font-size: 14px; line-height: 1.7;">
                            Approving this bidder activates dashboard access and sends a confirmation to the registered email.
                        </p>

                        <div class="review-actions" style="margin-bottom: 16px;">
                            <form action="{{ route('admin.users.approve', $user) }}" method="POST" style="flex:1 1 220px;">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="review-primary" style="width:100%;">
                                    <i class="fas fa-check-circle"></i> Approve Bidder
                                </button>
                            </form>
                        </div>

                        <form action="{{ route('admin.users.reject', $user) }}" method="POST" style="display:grid; gap:12px;">
                            @csrf
                            @method('PATCH')
                            <textarea name="rejection_reason" placeholder="Optional rejection reason to include in the email update."></textarea>
                            <button type="submit" class="review-danger">
                                <i class="fas fa-times-circle"></i> Reject Bidder
                            </button>
                        </form>
                    </section>

<section class="review-card">
                        <h3>QR Login Security</h3>
                        <div class="review-list">
                            @if(config('mail.default') === 'log')
                                <div class="review-list-item" style="border-color:#fdba74; background:#fff7ed;">
                                    <div>
                                        <strong>Email delivery is in local log mode</strong>
                                        <span>QR approval emails are currently written to <code>storage/logs/laravel.log</code> instead of being sent to Gmail. Switch the mailer to SMTP to deliver them externally.</span>
                                    </div>
                                </div>
                            @endif
                            @if($user->isApprovedBidder())
                                <div class="review-list-item" style="align-items:flex-start;">
                                    <div style="width:100%;">
                                        <strong>Current QR code</strong>
                                        <span>Use the buttons below to view or download the bidder QR code directly from the admin panel.</span>
                                        @if($qrPreviewDataUri)
                                            <div style="margin-top:14px; padding:18px; border-radius:18px; background:#ffffff; border:1px solid #e2e8f0; text-align:center;">
                                                <img
                                                    src="{{ $qrPreviewDataUri }}"
                                                    alt="Bidder QR Code"
                                                    style="width:220px; max-width:100%; height:auto; display:block; margin:0 auto 14px;"
                                                >
                                                <div class="review-inline-actions" style="justify-content:center;">
                                                    <a href="{{ route('admin.users.qr.preview', $user) }}" target="_blank" class="review-secondary">
                                                        <i class="fas fa-eye"></i> View QR
                                                    </a>
                                                    <a href="{{ route('admin.users.qr.download', $user) }}" class="review-primary">
                                                        <i class="fas fa-download"></i> Download QR
                                                    </a>
                                                    <form action="{{ route('admin.users.qr.resend', $user) }}" method="POST" style="margin:0;">
                                                        @csrf
                                                        <button type="submit" class="review-secondary">
                                                            <i class="fas fa-paper-plane"></i> Resend QR Email
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        @else
                                            <div class="review-inline-actions" style="margin-top:14px;">
                                                <a href="{{ route('admin.users.qr.preview', $user) }}" target="_blank" class="review-secondary">
                                                    <i class="fas fa-eye"></i> View QR
                                                </a>
                                                <a href="{{ route('admin.users.qr.download', $user) }}" class="review-primary">
                                                    <i class="fas fa-download"></i> Download QR
                                                </a>
                                                <form action="{{ route('admin.users.qr.resend', $user) }}" method="POST" style="margin:0;">
                                                    @csrf
                                                    <button type="submit" class="review-secondary">
                                                        <i class="fas fa-paper-plane"></i> Resend QR Email
                                                    </button>
                                                </form>
                                            </div>
                                            <span style="display:block; margin-top:12px;">If this bidder was approved before QR storage was enabled, opening the QR will securely generate a fresh active code.</span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                            <div class="review-list-item">
                                <div>
                                    <strong>Latest QR login code</strong>
                                    <span>
                                        @if($latestQrToken)
                                            {{ $latestQrToken->is_active ? 'Active' : 'Inactive' }}
                                            @if($latestQrToken->expires_at)
                                                • Expires {{ $latestQrToken->expires_at->format('M d, Y h:i A') }}
                                            @endif
                                        @else
                                            No QR login code has been issued for this bidder yet.
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <div class="review-list-item">
                                <div>
                                    <strong>Private storage</strong>
                                    <span>Uploaded registration documents are stored through the configured uploads disk and are previewed only through protected server routes.</span>
                                </div>
                            </div>
                            <div class="review-list-item">
                                <div>
                                    <strong>Password authentication</strong>
                                    <span>Bidders sign in with their registered email/username and password.</span>
                                </div>
                            </div>
                            <div class="review-list-item">
                                <div>
                                    <strong>Account approval required</strong>
                                    <span>All bidder accounts require admin approval before accessing the dashboard.</span>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>
</div>
