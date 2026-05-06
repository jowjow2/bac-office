<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@php($isReviewModal = request()->boolean('modal'))
@unless($isReviewModal)
    @include('partials.dashboard-viewport')
    @vite(['resources/css/dashboard.css'])
@endunless

<style>
    .bidder-review-page {
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

@unless($isReviewModal)
    @include('partials.admin-sidebar')
@endunless

<div class="{{ $isReviewModal ? 'bidder-review-page bidder-review-modal-content' : 'main-area bidder-review-page' }}">
    @unless($isReviewModal)
        <header class="navbar">
            <div class="nav-left">
                <div>
                    <h2>Bidder Review</h2>
<p>Review registration documents, approval status, and login activity.</p>
                </div>
            </div>
            <div class="nav-right review-inline-actions">
                <a href="{{ route('admin.messages', ['user' => $user->id]) }}" class="review-primary"><i class="fas fa-comments"></i> Message Bidder</a>
                <a href="{{ route('admin.users') }}" class="review-secondary"><i class="fas fa-arrow-left"></i> Back to Users</a>
            </div>
        </header>
    @endunless

        <main class="{{ $isReviewModal ? 'bidder-review-modal-body' : 'dashboard-content' }}">
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

                </div>
            </div>
        </main>
    </div>
</div>
