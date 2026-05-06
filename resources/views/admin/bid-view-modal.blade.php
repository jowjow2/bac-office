@php
    $budget = (float) ($bid->project?->budget ?? 0);
    $amount = (float) $bid->amount;
    $variance = $budget > 0 ? (($amount - $budget) / $budget) * 100 : null;
    $varianceColor = is_null($variance) ? '#64748b' : ($variance <= 0 ? '#047857' : '#dc2626');
    $statusValue = strtolower((string) ($bid->status ?? 'pending'));
    $statusLabel = match ($statusValue) {
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'validated' => 'Validated',
        default => 'Pending',
    };
    $statusClass = match ($statusValue) {
        'approved' => 'is-approved',
        'rejected' => 'is-rejected',
        'validated' => 'is-validated',
        'pending' => 'is-pending',
        default => 'is-default',
    };
    $bidderName = $bid->user?->company ?: ($bid->user?->name ?? 'N/A');
    $proposalPreviewUrl = $bid->proposal_url ? route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'proposal']) : null;
    $certificatePreviewUrl = $bid->user?->philgepsCertificate?->file_url
        ? route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'certificate'])
        : null;
    $latestStaffTracking = $bid->relationLoaded('trackings')
        ? $bid->trackings->first(fn ($tracking) => in_array($tracking->status_type, ['validated', 'rejected'], true))
        : null;
@endphp

<div class="view-bid-modal-shell">
    <div class="view-bid-modal-header">
        <div>
            <p>Bid Details</p>
            <h2>{{ $bidderName }}</h2>
        </div>
        <span class="admin-bids-status-pill {{ $statusClass }}">{{ $statusLabel }}</span>
    </div>

    <div class="view-bid-modal-body">
        <section class="view-bid-section">
            <h3>Submission</h3>
            <div class="view-bid-grid view-bid-grid-two">
                <div class="view-bid-field">
                    <label>Bidder / Company</label>
                    <div class="view-bid-value">{{ $bidderName }}</div>
                </div>

                <div class="view-bid-field">
                    <label>Email</label>
                    <div class="view-bid-value">{{ $bid->user?->email ?? 'N/A' }}</div>
                </div>

                <div class="view-bid-field">
                    <label>Project</label>
                    <div class="view-bid-value">{{ $bid->project?->title ?? 'N/A' }}</div>
                </div>

                <div class="view-bid-field">
                    <label>Submitted</label>
                    <div class="view-bid-value nowrap">{{ $bid->created_at?->format('M d, Y h:i A') ?? 'N/A' }}</div>
                </div>
            </div>
        </section>

        <section class="view-bid-section">
            <h3>Financials</h3>
            <div class="view-bid-grid view-bid-grid-three">
                <div class="view-bid-field">
                    <label>Bid Amount</label>
                    <div class="view-bid-value nowrap">&#8369;{{ number_format($amount, 2) }}</div>
                </div>

                <div class="view-bid-field">
                    <label>Project Budget</label>
                    <div class="view-bid-value nowrap">&#8369;{{ number_format($budget, 2) }}</div>
                </div>

                <div class="view-bid-field">
                    <label>Variance</label>
                    <div class="view-bid-value nowrap" style="color: {{ $varianceColor }};">
                        {{ is_null($variance) ? 'N/A' : number_format($variance, 1) . '%' }}
                    </div>
                </div>
            </div>
        </section>

        <section class="view-bid-section">
            <h3>Documents</h3>
            <div class="view-bid-document-list">
                <div class="view-bid-document-row">
                    <div>
                        <strong>Proposal File</strong>
                        <span>{{ $bid->proposal_filename ?: 'No proposal uploaded' }}</span>
                    </div>
                    @if($proposalPreviewUrl)
                        <a href="{{ $proposalPreviewUrl }}" target="_blank" rel="noopener" class="view-bid-link">
                            <i class="fas fa-file-lines" aria-hidden="true"></i>
                            Open file
                        </a>
                    @else
                        <span class="view-bid-muted">Unavailable</span>
                    @endif
                </div>

                <div class="view-bid-document-row">
                    <div>
                        <strong>Certificate Proof</strong>
                        <span>{{ $bid->user?->philgepsCertificate?->display_name ?: 'No certificate proof uploaded' }}</span>
                    </div>
                    @if($certificatePreviewUrl)
                        <a href="{{ $certificatePreviewUrl }}" target="_blank" rel="noopener" class="view-bid-link">
                            <i class="fas fa-certificate" aria-hidden="true"></i>
                            Open file
                        </a>
                    @else
                        <span class="view-bid-muted">Unavailable</span>
                    @endif
                </div>
            </div>
        </section>

        <section class="view-bid-section">
            <h3>Review Notes</h3>
            <div class="view-bid-note-box">
                <label>Staff Validation Result</label>
                <p>
                    @if($latestStaffTracking)
                        <strong>{{ $latestStaffTracking->status_title }}</strong><br>
                        {{ $latestStaffTracking->status_description ?: 'No staff remarks provided.' }}
                    @elseif($bid->documents_validated_at)
                        <strong>Documents Validated by Staff</strong><br>
                        Validated on {{ $bid->documents_validated_at->format('M d, Y h:i A') }}.
                    @else
                        No staff validation result yet.
                    @endif
                </p>
            </div>

            <div class="view-bid-note-box">
                <label>Admin Remarks / Rejection Reason</label>
                <p>{{ $bid->rejection_reason ?: ($bid->notes ?: 'No remarks provided.') }}</p>
            </div>
        </section>

        <div class="view-bid-actions">
            <button type="button" onclick="closeBidViewModal()" class="btn-secondary">Close</button>
            <a href="{{ route('admin.bid.view', $bid) }}" class="btn-secondary">View Docs</a>
            <a href="{{ route('admin.bid.edit', $bid) }}" class="btn-primary">Edit Bid</a>
        </div>
    </div>
</div>
