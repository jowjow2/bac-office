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
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-overlay.active,
        .modal-overlay.show {
            display: flex;
        }
        .modal-content {
            background: #fff;
            border-radius: 12px;
            max-width: 720px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .modal-header {
            padding: 24px 24px 0;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .modal-title {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }
        .modal-close {
            width: 36px;
            height: 36px;
            border: none;
            background: #f3f4f6;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            font-size: 20px;
            transition: all 0.2s;
        }
        .modal-close:hover {
            background: #ef4444;
            color: white;
        }
        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }
        .bid-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .bid-detail-item {
            background: #f9fafb;
            padding: 16px;
            border-radius: 8px;
            border-left: 4px solid #2563eb;
        }
        .bid-detail-label {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .bid-detail-value {
            font-size: 15px;
            font-weight: 500;
            color: #111827;
            word-break: break-word;
        }
        .pdf-preview-container {
            background: #1a1a1a;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 16px;
        }
        .pdf-preview-container iframe {
            width: 100%;
            height: 500px;
            border: none;
        }
        .file-actions {
            display: flex;
            gap: 10px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        .file-actions .staff-button {
            padding: 8px 16px;
            font-size: 13px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-badge.pending { background: #f59e0b; color: white; }
        .status-badge.uploaded { background: #eab308; color: white; }
        .status-badge.validated { background: #10b981; color: white; }
        .status-badge.rejected { background: #ef4444; color: white; }
        .status-badge.approved { background: #10b981; color: white; }
        .document-checklist {
            margin-top: 20px;
        }
        .document-checklist h4 {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 12px;
        }
        .checklist-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }
        .checklist-item:last-child {
            border-bottom: none;
        }
        .checklist-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-size: 10px;
        }
        .checklist-icon.done {
            background: #10b981;
            color: white;
        }
        .checklist-icon.missing {
            background: #ef4444;
            color: white;
        }
        /* Confirmation Modal */
        .confirm-modal .modal-content {
            max-width: 420px;
        }
        .confirm-modal .modal-body {
            text-align: center;
        }
        .confirm-modal .confirm-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 32px;
        }
        .confirm-modal .confirm-icon.warning {
            background: #fef3c7;
            color: #d97706;
        }
        .confirm-modal .confirm-icon.danger {
            background: #fee2e2;
            color: #dc2626;
        }
        .confirm-modal h3 {
            font-size: 18px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 8px;
        }
        .confirm-modal p {
            color: #6b7280;
            margin-bottom: 20px;
        }
        .confirm-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .reject-reason {
            margin-top: 16px;
            text-align: left;
        }
        .reject-reason textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            resize: vertical;
            min-height: 80px;
            transition: border-color 0.2s;
        }
        .reject-reason textarea:focus {
            outline: none;
            border-color: #2563eb;
        }
        /* Button Loading State */
        .staff-btn-loading {
            position: relative;
            pointer-events: none;
        }
        .staff-btn-loading .btn-text {
            visibility: hidden;
        }
        .staff-btn-loading::after {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            top: 50%;
            left: 50%;
            margin-left: -9px;
            margin-top: -9px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 100px;
            right: 24px;
            z-index: 4000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .toast {
            padding: 14px 20px;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            transform: translateX(120%);
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 280px;
        }
        .toast.show {
            transform: translateX(0);
        }
        .toast.success { background: #10b981; }
        .toast.error { background: #ef4444; }
        .toast.warning { background: #f59e0b; }
        /* Responsive Table */
        @media (max-width: 900px) {
            .staff-table-wrap {
                overflow-x: auto;
            }
            .staff-table {
                min-width: 700px;
            }
        }
        @media (max-width: 640px) {
            .modal-overlay {
                padding: 10px;
            }
            .modal-content {
                width: 95%;
                max-height: 90vh;
            }
            .staff-inline-actions {
                flex-direction: column;
                gap: 8px;
            }
            .staff-inline-actions form {
                width: 100%;
            }
            .staff-inline-actions .staff-button {
                width: 100%;
            }
        }
    </style>

    @include('partials.staff-sidebar', ['activeStaffMenu' => 'review-bids'])

    <div class="main-area">
        @include('partials.staff-topbar', [
            'staffNavbarTitle' => 'Review Bids',
            'staffNavbarSubtitle' => 'Bid submissions review and validation',
        ])

        <main class="dashboard-content dashboard-home-content">
            <section class="staff-dashboard">
                @php
                    $reviewBids = $allAssignedBids->whereIn('status', ['pending', 'approved', 'rejected'])->values();
                @endphp

                @if(session('success'))
                    <div class="toast-container" id="toastContainer"></div>
                @endif

                <section class="staff-page-intro">
                    <h1 class="staff-page-title">Review Bids</h1>
                    <p class="staff-page-subtitle">Review, validate or reject bid submissions from bidders.</p>
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
                        <h2>Bid Submissions</h2>
                        <span class="staff-badge-count">{{ $reviewBids->count() }} total</span>
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
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reviewBids as $bid)
                                    @php
                                        $proposalFile = $bid->proposal_file;
                                        $eligibilityFile = $bid->eligibility_file;
                                        $isEligible = $bid->eligibility_status === \App\Models\Bid::ELIGIBILITY_VALID;
                                        $statusClass = match($bid->status) {
                                            'pending' => 'pending',
                                            'approved' => 'validated',
                                            'rejected' => 'rejected',
                                            default => 'pending',
                                        };
                                        $statusLabel = match($bid->status) {
                                            'pending' => 'Pending',
                                            'approved' => 'Approved',
                                            'rejected' => 'Rejected',
                                            default => 'Pending',
                                        };
                                    @endphp
                                    <tr data-bid-id="{{ $bid->id }}">
                                        <td>
                                            <div>
                                                <strong>{{ $bid->user ? ($bid->user->company ?: $bid->user->name) : 'N/A' }}</strong>
                                                <div style="font-size: 12px; color: #6b7280;">{{ $bid->user ? $bid->user->email : 'N/A' }}</div>
                                            </div>
                                        </td>
                                        <td class="staff-project-title">
                                            {{ $bid->project ? $bid->project->title : 'N/A' }}
                                        </td>
                                        <td><strong>&#8369;{{ number_format((float) $bid->bid_amount, 2) }}</strong></td>
                                        <td>
                                            <div class="staff-document-cell">
                                                @if($proposalFile)
                                                    <a href="{{ route('staff.bids.proposal.preview', $bid) }}" target="_blank" class="staff-document-link">
                                                        <i class="fas fa-file-contract"></i> View PDF
                                                    </a>
                                                @endif
                                                @if($eligibilityFile)
                                                    <a href="{{ route('staff.bids.eligibility.preview', $bid) }}" target="_blank" class="staff-document-link">
                                                        <i class="fas fa-file-certificate"></i> View PDF
                                                    </a>
                                                @endif
                                                <span class="staff-status-pill {{ $proposalFile ? 'approved' : 'rejected' }}">
                                                    {{ $proposalFile ? 'uploaded' : 'missing' }}
                                                </span>
                                                <div style="font-size:12px;color:#64748b;line-height:1.55;">
                                                    <div>Documents: {{ $bid->documentsAreComplete() ? 'Complete' : 'Incomplete' }}</div>
                                                    <div>Eligibility: {{ $bid->eligibility_status_label }}</div>
                                                    <div>Eligibility file: {{ $eligibilityFile ? 'uploaded' : 'missing' }}</div>
                                                    @foreach($bid->documentChecklist() as $check)
                                                        @if(! empty($check['document_id']))
                                                            <a href="{{ route('staff.bids.documents.pdf', ['bid' => $bid, 'document' => $check['document_id']]) }}" target="_blank">{{ $check['label'] }}</a>
                                                        @else
                                                            <span>{{ $check['label'] }}</span>
                                                        @endif
                                                        @if(! $loop->last)<span> · </span>@endif
                                                    @endforeach
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="staff-status-pill {{ $statusClass }}" data-bid-status="{{ $bid->id }}">{{ $statusLabel }}</span></td>
                                        <td>
                                            <div class="staff-inline-actions">
                                                <button type="button" 
                                                        class="staff-button-primary view-bid-btn" 
                                                        data-bid-id="{{ $bid->id }}"
                                                        data-bidder-name="{{ e($bid->user ? ($bid->user->company ?: $bid->user->name) : 'N/A') }}"
                                                        data-bidder-email="{{ e($bid->user ? $bid->user->email : 'N/A') }}"
                                                        data-project-title="{{ e($bid->project ? $bid->project->title : 'N/A') }}"
                                                        data-bid-amount="{{ (float) $bid->bid_amount }}"
                                                        data-proposal-url="{{ $proposalFile ? route('staff.bids.proposal.preview', $bid) : '' }}"
                                                        data-eligibility-url="{{ $eligibilityFile ? route('staff.bids.eligibility.preview', $bid) : '' }}"
                                                        data-status="{{ $bid->status }}"
                                                        data-remarks="{{ e($bid->notes ?? '') }}"
                                                        data-rejection-reason="{{ e($bid->rejection_reason ?? '') }}"
                                                        title="View full bid details">
                                                    <i class="fas fa-eye"></i> Check Bid
                                                </button>
                                                @if($bid->status === 'pending' && $isEligible && $proposalFile && $eligibilityFile)
                                                    <button type="button" 
                                                            class="staff-button-success validate-bid-btn" 
                                                            data-bid-id="{{ $bid->id }}"
                                                            title="Validate bid documents">
                                                        <i class="fas fa-check"></i> Validate
                                                    </button>
                                                @endif
                                                @if($bid->status === 'pending')
                                                    <button type="button" 
                                                            class="staff-button-danger reject-bid-btn" 
                                                            data-bid-id="{{ $bid->id }}"
                                                            title="Reject bid">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="staff-empty-cell">
                                            <i class="fas fa-folder-open" style="font-size: 48px; color: #d1d5db; margin-bottom: 12px;"></i>
                                            <h3>No pending bid submissions</h3>
                                            <p>All bids have been reviewed or there are no submissions for your assigned projects.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>
        </main>
    </div>

    <!-- Bid Details Modal -->
    <div class="modal-overlay" id="bidDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Bid Submission Details</h3>
                <button class="modal-close" onclick="closeModal('bidDetailsModal')" aria-label="Close modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="bidDetailsContent">
                <!-- Content loaded via JS -->
            </div>
        </div>
    </div>

    <!-- Confirm Modal (Validate/Reject) -->
    <div class="modal-overlay confirm-modal" id="confirmModal">
        <div class="modal-content">
            <div class="modal-body">
                <div class="confirm-icon warning" id="confirmIcon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3 id="confirmTitle">Confirm Action</h3>
                <p id="confirmMessage">Are you sure?</p>
                <div class="reject-reason" id="rejectReasonInput" style="display: none;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151;">Rejection Reason *</label>
                    <textarea id="rejectReason" placeholder="Please provide a clear reason for rejection..."></textarea>
                    <div id="rejectError" style="color: #ef4444; font-size: 12px; margin-top: 4px; display: none;">Rejection reason is required.</div>
                </div>
                <div class="confirm-actions">
                    <button class="staff-button-secondary" onclick="closeModal('confirmModal')">Cancel</button>
                    <button class="staff-button-danger" id="confirmBtn" onclick="confirmAction()">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        const bidDataCache = {};
        let pendingAction = null;

        // Fetch bid details
        function fetchBidDetails(bidId) {
            if (bidDataCache[bidId]) {
                renderBidDetails(bidDataCache[bidId]);
                return;
            }

            fetch(`/staff/bids/${bidId}/details`)
                .then(res => res.json())
                .then(data => {
                    if (data.ok) {
                        bidDataCache[bidId] = data.bid;
                        renderBidDetails(data.bid);
                    }
                })
                .catch(err => showToast('Failed to load bid details', 'error'));
        }

        function renderBidDetails(bid) {
            const content = document.getElementById('bidDetailsContent');
            const eligibilityStatus = bid.eligibility_status || 'pending';
            const eligibilityLabel = {
                'pending': 'Pending Review',
                'valid': 'Valid',
                'invalid': 'Invalid'
            }[eligibilityStatus] || 'Unknown';

            const workflowStepLabel = bid.workflow_step_label || bid.workflow_step || 'Unknown';

            const checklistHTML = (bid.document_checklist || []).map(item => {
                const iconClass = item.submitted ? 'fas fa-check' : 'fas fa-times';
                const statusClass = item.submitted ? 'done' : 'missing';
                return `
                    <div class="checklist-item">
                        <span class="checklist-icon ${statusClass}">
                            <i class="${iconClass}"></i>
                        </span>
                        <span>${item.label}</span>
                        ${item.file_name ? `<span style="margin-left: auto; color: #6b7280; font-size: 12px;">${item.file_name}</span>` : ''}
                    </div>
                `;
            }).join('');

            const filesHTML = [];
            if (bid.proposal_file) {
                filesHTML.push(`
                    <a href="{{ route('staff.bids.proposal.download', ':bidId') }}".replace(':bidId', bid.id)
                       class="staff-button-primary file-actions-btn" style="padding: 8px 14px; font-size: 13px; margin-right: 8px;">
                        <i class="fas fa-download"></i> Download Proposal
                    </a>
                    <a href="{{ route('staff.bids.proposal.preview', ':bidId') }}".replace(':bidId', bid.id)
                       target="_blank" class="staff-button-secondary file-actions-btn" style="padding: 8px 14px; font-size: 13px;">
                        <i class="fas fa-eye"></i> Preview
                    </a>
                `);
            }
            if (bid.eligibility_file) {
                filesHTML.push(`
                    <a href="{{ route('staff.bids.eligibility.download', ':bidId') }}".replace(':bidId', bid.id)
                       class="staff-button-primary file-actions-btn" style="padding: 8px 14px; font-size: 13px; margin-right: 8px;">
                        <i class="fas fa-download"></i> Download Eligibility
                    </a>
                    <a href="{{ route('staff.bids.eligibility.preview', ':bidId') }}".replace(':bidId', bid.id)
                       target="_blank" class="staff-button-secondary file-actions-btn" style="padding: 8px 14px; font-size: 13px;">
                        <i class="fas fa-eye"></i> Preview
                    </a>
                `);
            }

            content.innerHTML = `
                <div class="bid-detail-grid">
                    <div class="bid-detail-item">
                        <div class="bid-detail-label">Bidder</div>
                        <div class="bid-detail-value">${bid.user?.company || bid.user?.name || 'N/A'}</div>
                    </div>
                    <div class="bid-detail-item">
                        <div class="bid-detail-label">Project</div>
                        <div class="bid-detail-value">${bid.project?.title || 'N/A'}</div>
                    </div>
                    <div class="bid-detail-item">
                        <div class="bid-detail-label">Bid Amount</div>
                        <div class="bid-detail-value" style="color: #10b981; font-size: 18px;">&#8369;${parseFloat(bid.bid_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                    </div>
                    <div class="bid-detail-item">
                        <div class="bid-detail-label">Submitted Date</div>
                        <div class="bid-detail-value">${new Date(bid.created_at).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'})}</div>
                    </div>
                    <div class="bid-detail-item">
                        <div class="bid-detail-label">Current Status</div>
                        <div class="bid-detail-value"><span class="status-badge ${bid.status || 'pending'}">${bid.status_label || 'Pending'}</span></div>
                    </div>
                    <div class="bid-detail-item">
                        <div class="bid-detail-label">Eligibility Status</div>
                        <div class="bid-detail-value">
                            <span class="status-badge ${eligibilityStatus === 'valid' ? 'validated' : eligibilityStatus === 'invalid' ? 'rejected' : 'pending'}">
                                ${eligibilityLabel}
                            </span>
                        </div>
                    </div>
                    <div class="bid-detail-item" style="grid-column: 1 / -1;">
                        <div class="bid-detail-label">Workflow Step</div>
                        <div class="bid-detail-value">${workflowStepLabel}</div>
                    </div>
                </div>

                ${bid.notes ? `
                    <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px; border-radius: 4px; margin-bottom: 16px;">
                        <div style="font-size: 12px; font-weight: 600; color: #92400e; margin-bottom: 4px;">Notes</div>
                        <div style="font-size: 14px; color: #92400e; white-space: pre-wrap;">${bid.notes}</div>
                    </div>
                ` : ''}

                ${bid.rejection_reason ? `
                    <div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 12px; border-radius: 4px; margin-bottom: 16px;">
                        <div style="font-size: 12px; font-weight: 600; color: #991b1b; margin-bottom: 4px;">Rejection Reason</div>
                        <div style="font-size: 14px; color: #991b1b; white-space: pre-wrap;">${bid.rejection_reason}</div>
                    </div>
                ` : ''}

                ${filesHTML.length > 0 ? `
                    <div style="margin: 20px 0;">
                        <div class="bid-detail-label" style="margin-bottom: 12px;">Uploaded Files</div>
                        <div class="file-actions">
                            ${filesHTML.join('')}
                        </div>
                    </div>
                ` : ''}

                <div class="document-checklist">
                    <h4><i class="fas fa-clipboard-check"></i> Document Checklist</h4>
                    ${checklistHTML || '<p style="color: #6b7280; font-size: 14px;">No documents uploaded yet.</p>'}
                </div>

                ${bid.workflow_timeline_steps ? `
                    <div style="margin-top: 20px;">
                        <h4 style="font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 12px;">
                            <i class="fas fa-history"></i> Timeline
                        </h4>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            ${Object.entries(bid.workflow_timeline_steps).map(([key, step]) => `
                                <div style="display: flex; align-items: center; padding: 8px 12px; background: ${step.completed ? '#f0fdf4' : step.current ? '#fef3c7' : '#f9fafb'}; border-left: 3px solid ${step.completed ? '#10b981' : step.current ? '#f59e0b' : '#d1d5db'}; border-radius: 4px;">
                                    <i class="fas ${step.icon}" style="color: ${step.completed ? '#10b981' : step.current ? '#f59e0b' : '#9ca3af'}; margin-right: 10px;"></i>
                                    <div style="flex: 1;">
                                        <div style="font-size: 13px; font-weight: 500; color: #111827;">${step.label}</div>
                                        <div style="font-size: 11px; color: #6b7280;">${step.time}</div>
                                    </div>
                                    ${step.verified ? '<i class="fas fa-check-circle" style="color: #10b981;"></i>' : ''}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
            `;

            openModal('bidDetailsModal');
        }

        // Validation
        function validateBid(bidId) {
            pendingAction = { type: 'validate', bidId };
            const modal = document.getElementById('confirmModal');
            const icon = document.getElementById('confirmIcon');
            const title = document.getElementById('confirmTitle');
            const message = document.getElementById('confirmMessage');
            const reasonInput = document.getElementById('rejectReasonInput');

            icon.className = 'confirm-icon warning';
            icon.innerHTML = '<i class="fas fa-check-circle"></i>';
            title.textContent = 'Validate Bid Documents';
            message.innerHTML = 'Are you sure you want to validate the bid documents?<br><small style="color: #6b7280;">This will advance the workflow to "Documents Validated" and notify the bidder.</small>';
            reasonInput.style.display = 'none';

            openModal('confirmModal');
        }

        // Reject
        function rejectBid(bidId) {
            pendingAction = { type: 'reject', bidId };
            const modal = document.getElementById('confirmModal');
            const icon = document.getElementById('confirmIcon');
            const title = document.getElementById('confirmTitle');
            const message = document.getElementById('confirmMessage');
            const reasonInput = document.getElementById('rejectReasonInput');

            icon.className = 'confirm-icon danger';
            icon.innerHTML = '<i class="fas fa-times-circle"></i>';
            title.textContent = 'Reject Bid';
            message.innerHTML = 'Are you sure you want to reject this bid?<br><small style="color: #6b7280;">The bidder will be notified and the bid marked as rejected.</small>';
            reasonInput.style.display = 'block';

            openModal('confirmModal');
        }

        function confirmAction() {
            if (!pendingAction) return;

            if (pendingAction.type === 'reject') {
                const reason = document.getElementById('rejectReason').value.trim();
                if (!reason) {
                    document.getElementById('rejectError').style.display = 'block';
                    return;
                }
                document.getElementById('rejectError').style.display = 'none';
                doReject(pendingAction.bidId, reason);
            } else if (pendingAction.type === 'validate') {
                doValidate(pendingAction.bidId);
            }

            pendingAction = null;
            closeModal('confirmModal');
        }

        function doValidate(bidId) {
            const btn = document.querySelector(`[data-bid-id="${bidId}"].validate-bid-btn`);
            if (btn) {
                btn.classList.add('staff-btn-loading');
                btn.disabled = true;
            }

            fetch(`/staff/bids/${bidId}/validate`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    showToast('Bid validated successfully!', 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    showToast(data.message || 'Validation failed', 'error');
                    if (btn) {
                        btn.classList.remove('staff-btn-loading');
                        btn.disabled = false;
                    }
                }
            })
            .catch(err => {
                showToast('Request failed', 'error');
                if (btn) {
                    btn.classList.remove('staff-btn-loading');
                    btn.disabled = false;
                }
            });
        }

        function doReject(bidId, reason) {
            const btn = document.querySelector(`[data-bid-id="${bidId}"].reject-bid-btn`);
            if (btn) {
                btn.classList.add('staff-btn-loading');
                btn.disabled = true;
            }

            fetch(`/staff/bids/${bidId}/reject`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                body: JSON.stringify({ rejection_reason: reason })
            })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    showToast('Bid rejected successfully!', 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    showToast(data.message || 'Rejection failed', 'error');
                    if (btn) {
                        btn.classList.remove('staff-btn-loading');
                        btn.disabled = false;
                    }
                }
            })
            .catch(err => {
                showToast('Request failed', 'error');
                if (btn) {
                    btn.classList.remove('staff-btn-loading');
                    btn.disabled = false;
                }
            });
        }

        function openModal(id) {
            document.getElementById(id).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            document.body.style.overflow = '';
            document.getElementById('rejectReason').value = '';
            document.getElementById('rejectError').style.display = 'none';
        }

        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-exclamation-triangle';
            toast.innerHTML = `<i class="fas ${icon}"></i> <span>${message}</span>`;
            container.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        // Event listeners
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        document.addEventListener('click', function(e) {
            if (e.target.closest('.view-bid-btn')) {
                const bidId = e.target.closest('.view-bid-btn').dataset.bidId;
                fetchBidDetails(bidId);
            }
        });

        document.addEventListener('click', function(e) {
            if (e.target.closest('.validate-bid-btn')) {
                const bidId = e.target.closest('.validate-bid-btn').dataset.bidId;
                validateBid(bidId);
            }
        });

        document.addEventListener('click', function(e) {
            if (e.target.closest('.reject-bid-btn')) {
                const bidId = e.target.closest('.reject-bid-btn').dataset.bidId;
                rejectBid(bidId);
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }
        });

        // WebSocket - Real-time updates
        if (typeof Echo !== 'undefined') {
            Echo.private('staff.' + {{ Auth::id() }})
                .listen('BidWorkflowUpdated', (e) => {
                    showToast('Bid status updated in real-time', 'success');
                    setTimeout(() => location.reload(), 1000);
                });
        }

        // Polling fallback
        setInterval(() => {
            const pendingCount = document.querySelectorAll('[data-bid-id]').length;
            if (pendingCount > 0) {
                fetch(window.location.href + '?t=' + Date.now() + '&check=latest')
                    .then(res => res.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newTable = doc.querySelector('.staff-table tbody');
                        const currentTable = document.querySelector('.staff-table tbody');
                        if (newTable && currentTable && newTable.innerHTML !== currentTable.innerHTML) {
                            location.reload();
                        }
                    })
                    .catch(() => {});
            }
        }, 30000); // Check every 30 seconds
    </script>
    <script>
        (function () {
            const urls = {
                details: @json(route('staff.review-bids.show', ['bid' => '__BID__'])),
                validate: @json(route('staff.review-bids.validate', ['bid' => '__BID__'])),
                reject: @json(route('staff.review-bids.reject', ['bid' => '__BID__'])),
            };
            const csrfToken = @json(csrf_token());
            const cache = {};
            let action = null;

            function urlFor(type, bidId) {
                return urls[type].replace('__BID__', encodeURIComponent(bidId));
            }

            function escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, function (char) {
                    return ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;',
                    })[char];
                });
            }

            function money(value) {
                return Number(value || 0).toLocaleString('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            }

            function statusClass(status) {
                if (status === 'approved') return 'validated';
                if (status === 'rejected') return 'rejected';
                return 'pending';
            }

            function lockScroll(lock) {
                document.body.style.overflow = lock ? 'hidden' : '';
            }

            window.openModal = function (id) {
                const modal = document.getElementById(id);
                if (!modal) return;
                modal.classList.add('active', 'show');
                lockScroll(true);
            };

            window.closeModal = function (id) {
                const modal = document.getElementById(id);
                if (!modal) return;
                modal.classList.remove('active', 'show');
                if (!document.querySelector('.modal-overlay.active, .modal-overlay.show')) {
                    lockScroll(false);
                }

                const reason = document.getElementById('rejectReason');
                const error = document.getElementById('rejectError');
                if (reason) reason.value = '';
                if (error) error.style.display = 'none';
            };

            window.fetchBidDetails = async function (bidId) {
                console.log('View bid clicked:', bidId);
                if (!bidId) return;

                if (cache[bidId]) {
                    window.renderBidDetails(cache[bidId]);
                    return;
                }

                const content = document.getElementById('bidDetailsContent');
                if (content) {
                    content.innerHTML = '<p style="color:#64748b;">Loading bid details...</p>';
                }
                window.openModal('bidDetailsModal');

                try {
                    const response = await fetch(urlFor('details', bidId), {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const data = await response.json();
                    if (!response.ok || !data.ok) {
                        throw new Error(data.message || 'Unable to load bid details.');
                    }
                    cache[bidId] = data.bid;
                    window.renderBidDetails(data.bid);
                } catch (error) {
                    showToast(error.message || 'Failed to load bid details.', 'error');
                    window.closeModal('bidDetailsModal');
                }
            };

            window.renderBidDetails = function (bid) {
                const content = document.getElementById('bidDetailsContent');
                if (!content) return;

                const checklist = (bid.document_checklist || []).map(function (item) {
                    return `
                        <div class="checklist-item">
                            <span class="checklist-icon ${item.submitted ? 'done' : 'missing'}">
                                <i class="fas ${item.submitted ? 'fa-check' : 'fa-times'}"></i>
                            </span>
                            <span>${escapeHtml(item.label)}</span>
                            ${item.file_name ? `<span style="margin-left:auto;color:#6b7280;font-size:12px;">${escapeHtml(item.file_name)}</span>` : ''}
                        </div>
                    `;
                }).join('');

                const fileButtons = [
                    bid.proposal_url ? `<a href="${escapeHtml(bid.proposal_url)}" target="_blank" rel="noopener" class="staff-button-secondary" style="padding:8px 14px;font-size:13px;"><i class="fas fa-eye"></i> View Proposal</a>` : '',
                    bid.proposal_download_url ? `<a href="${escapeHtml(bid.proposal_download_url)}" class="staff-button-primary" style="padding:8px 14px;font-size:13px;"><i class="fas fa-download"></i> Download Proposal</a>` : '',
                    bid.eligibility_url ? `<a href="${escapeHtml(bid.eligibility_url)}" target="_blank" rel="noopener" class="staff-button-secondary" style="padding:8px 14px;font-size:13px;"><i class="fas fa-eye"></i> View Eligibility</a>` : '',
                    bid.eligibility_download_url ? `<a href="${escapeHtml(bid.eligibility_download_url)}" class="staff-button-primary" style="padding:8px 14px;font-size:13px;"><i class="fas fa-download"></i> Download Eligibility</a>` : '',
                ].filter(Boolean).join('');

                const disableActions = !bid.can_validate && !bid.can_reject;
                content.innerHTML = `
                    <div class="bid-detail-grid">
                        <div class="bid-detail-item">
                            <div class="bid-detail-label">Bidder / Company</div>
                            <div class="bid-detail-value">${escapeHtml(bid.bidder_name)}</div>
                        </div>
                        <div class="bid-detail-item">
                            <div class="bid-detail-label">Bidder Email</div>
                            <div class="bid-detail-value">${escapeHtml(bid.bidder_email)}</div>
                        </div>
                        <div class="bid-detail-item">
                            <div class="bid-detail-label">Project</div>
                            <div class="bid-detail-value">${escapeHtml(bid.project_title)}</div>
                        </div>
                        <div class="bid-detail-item">
                            <div class="bid-detail-label">Bid Amount</div>
                            <div class="bid-detail-value">&#8369;${money(bid.bid_amount)}</div>
                        </div>
                        <div class="bid-detail-item">
                            <div class="bid-detail-label">Current Status</div>
                            <div class="bid-detail-value">
                                <span class="status-badge ${statusClass(bid.status)}" data-modal-status>${escapeHtml(bid.status_label)}</span>
                            </div>
                        </div>
                        <div class="bid-detail-item">
                            <div class="bid-detail-label">Submitted</div>
                            <div class="bid-detail-value">${escapeHtml(bid.submitted_at || 'N/A')}</div>
                        </div>
                    </div>

                    ${(bid.notes || bid.rejection_reason) ? `
                        <div style="display:grid;gap:10px;margin-bottom:16px;">
                            ${bid.notes ? `<div style="background:#fff7ed;border-left:4px solid #f97316;padding:12px;border-radius:8px;"><strong>Staff Remarks</strong><div style="margin-top:6px;white-space:pre-wrap;">${escapeHtml(bid.notes)}</div></div>` : ''}
                            ${bid.rejection_reason ? `<div style="background:#fee2e2;border-left:4px solid #ef4444;padding:12px;border-radius:8px;"><strong>Rejection Reason</strong><div style="margin-top:6px;white-space:pre-wrap;">${escapeHtml(bid.rejection_reason)}</div></div>` : ''}
                        </div>
                    ` : ''}

                    <div style="margin:18px 0;">
                        <div class="bid-detail-label" style="margin-bottom:10px;">Submitted Documents</div>
                        <div class="file-actions">${fileButtons || '<span style="color:#64748b;">No submitted documents available.</span>'}</div>
                    </div>

                    <div class="document-checklist">
                        <h4><i class="fas fa-clipboard-check"></i> Document Checklist</h4>
                        ${checklist || '<p style="color:#6b7280;font-size:14px;">No checklist items available.</p>'}
                    </div>

                    <div class="confirm-actions" style="justify-content:flex-end;margin-top:22px;">
                        <button type="button" class="staff-button-success" data-modal-validate="${bid.id}" ${bid.can_validate ? '' : 'disabled'}>Validate</button>
                        <button type="button" class="staff-button-danger" data-modal-reject="${bid.id}" ${bid.can_reject ? '' : 'disabled'}>Reject</button>
                        ${disableActions ? '<span style="align-self:center;color:#64748b;font-size:13px;">This bid has already been reviewed.</span>' : ''}
                    </div>
                `;

                window.openModal('bidDetailsModal');
            };

            window.validateBid = function (bidId) {
                action = { type: 'validate', bidId };
                document.getElementById('confirmIcon').className = 'confirm-icon warning';
                document.getElementById('confirmIcon').innerHTML = '<i class="fas fa-check-circle"></i>';
                document.getElementById('confirmTitle').textContent = 'Validate Bid';
                document.getElementById('confirmMessage').textContent = 'Are you sure you want to validate this bid?';
                document.getElementById('rejectReasonInput').style.display = 'none';
                window.openModal('confirmModal');
            };

            window.rejectBid = function (bidId) {
                action = { type: 'reject', bidId };
                document.getElementById('confirmIcon').className = 'confirm-icon danger';
                document.getElementById('confirmIcon').innerHTML = '<i class="fas fa-times-circle"></i>';
                document.getElementById('confirmTitle').textContent = 'Reject Bid';
                document.getElementById('confirmMessage').textContent = 'Please provide a rejection reason before rejecting this bid.';
                document.getElementById('rejectReasonInput').style.display = 'block';
                window.openModal('confirmModal');
            };

            window.confirmAction = function () {
                if (!action) return;
                if (action.type === 'reject') {
                    const reason = document.getElementById('rejectReason').value.trim();
                    if (!reason) {
                        document.getElementById('rejectError').style.display = 'block';
                        return;
                    }
                    submitReviewAction(action.bidId, 'reject', { rejection_reason: reason });
                    return;
                }
                submitReviewAction(action.bidId, 'validate', {});
            };

            async function submitReviewAction(bidId, type, payload) {
                const button = document.getElementById('confirmBtn');
                button.disabled = true;
                button.classList.add('staff-btn-loading');

                try {
                    const response = await fetch(urlFor(type, bidId), {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(payload),
                    });
                    const data = await response.json();
                    if (!response.ok || !data.ok) {
                        throw new Error(data.message || 'Unable to update bid.');
                    }

                    cache[bidId] = data.bid;
                    updateRow(data.bid);
                    window.renderBidDetails(data.bid);
                    window.closeModal('confirmModal');
                    action = null;
                    showToast(data.message || 'Bid updated successfully.', 'success');
                } catch (error) {
                    showToast(error.message || 'Request failed.', 'error');
                } finally {
                    button.disabled = false;
                    button.classList.remove('staff-btn-loading');
                }
            }

            function updateRow(bid) {
                const row = document.querySelector(`tr[data-bid-id="${bid.id}"]`);
                const status = document.querySelector(`[data-bid-status="${bid.id}"]`);
                if (status) {
                    status.className = `staff-status-pill ${statusClass(bid.status)}`;
                    status.textContent = bid.status_label;
                }
                if (!row || bid.status === 'pending') return;

                row.querySelectorAll('.validate-bid-btn, .reject-bid-btn').forEach(button => button.remove());
                const viewButton = row.querySelector('.view-bid-btn');
                if (viewButton) {
                    viewButton.dataset.status = bid.status;
                    viewButton.dataset.remarks = bid.notes || '';
                    viewButton.dataset.rejectionReason = bid.rejection_reason || '';
                }
            }

            document.addEventListener('click', function (event) {
                const viewButton = event.target.closest('.view-bid-btn');
                if (viewButton) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    window.fetchBidDetails(viewButton.dataset.bidId);
                    return;
                }

                const modalValidate = event.target.closest('[data-modal-validate]');
                if (modalValidate) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    window.validateBid(modalValidate.dataset.modalValidate);
                    return;
                }

                const modalReject = event.target.closest('[data-modal-reject]');
                if (modalReject) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    window.rejectBid(modalReject.dataset.modalReject);
                    return;
                }

                const tableValidate = event.target.closest('.validate-bid-btn');
                if (tableValidate) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    window.validateBid(tableValidate.dataset.bidId);
                    return;
                }

                const tableReject = event.target.closest('.reject-bid-btn');
                if (tableReject) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    window.rejectBid(tableReject.dataset.bidId);
                }
            }, true);
        })();
    </script>
</div>
</div>
