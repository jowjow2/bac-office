<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home admin-dashboard-page admin-bids-page">
    @vite(['resources/css/dashboard.css'])

    @include('partials.admin-sidebar')

    <div class="main-area bids-page">
        <header class="navbar">
            <div class="nav-left">
                <h2>Bid Management</h2>
                <p>Review and evaluate all submitted bids</p>
            </div>
            <div class="nav-right"></div>
        </header>

        <main class="dashboard-content dashboard-home-content">
 
            @if(session('success'))
                <div id="successAlert" style="position: fixed; top: 90px; right: 25px; background: #dcfce7; color: #166534; padding: 16px 20px; border-radius: 8px; font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; display: flex; align-items: center; gap: 10px; min-width: 280px;">
                    <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                    <span>{{ session('success') }}</span>
                    <button onclick="closeSuccessAlert()" style="margin-left: auto; background: none; border: none; color: #166534; cursor: pointer; font-size: 16px;">&times;</button>
                </div>
            @endif

            <section class="admin-bids-shell">
                <form method="GET" action="{{ route('admin.bids') }}" class="admin-bids-toolbar">
                    <div class="admin-bids-toolbar-field admin-bids-toolbar-field-search">
                        <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Search bids..." class="admin-bids-input">
                    </div>
                    <div class="admin-bids-toolbar-field">
                        <select name="status" onchange="this.form.submit()" class="admin-bids-select">
                            <option value="">All Status</option>
                            <option value="pending" {{ ($status ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ ($status ?? '') === 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="rejected" {{ ($status ?? '') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>
                    <div class="admin-bids-toolbar-field">
                        <select name="project" onchange="this.form.submit()" class="admin-bids-select">
                            <option value="">All Projects</option>
                            @foreach(($projects ?? collect()) as $project)
                                <option value="{{ $project->id }}" {{ (string) ($projectFilter ?? '') === (string) $project->id ? 'selected' : '' }}>{{ $project->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="admin-bids-toolbar-field">
                        <select name="proposal" onchange="this.form.submit()" class="admin-bids-select">
                            <option value="">All Uploads</option>
                            <option value="uploaded" {{ ($proposalFilter ?? '') === 'uploaded' ? 'selected' : '' }}>Uploaded</option>
                            <option value="missing" {{ ($proposalFilter ?? '') === 'missing' ? 'selected' : '' }}>Missing</option>
                        </select>
                    </div>
                </form>

                <div class="admin-bids-table-wrap">
                    <table class="dashboard-table admin-bids-table">
                        <colgroup>
                            <col class="admin-bids-col-bidder">
                            <col class="admin-bids-col-project">
                            <col class="admin-bids-col-amount">
                            <col class="admin-bids-col-budget">
                            <col class="admin-bids-col-variance">
                            <col class="admin-bids-col-submitted">
                            <col class="admin-bids-col-proposal">
                            <col class="admin-bids-col-status">
                            <col class="admin-bids-col-actions">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Bidder</th>
                                <th>Project</th>
                                <th>Bid Amount</th>
                                <th>Budget</th>
                                <th>Variance</th>
                                <th>Submitted</th>
                                <th>Proposal</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($bids as $bid)
                            @php
                                $budget = (float) ($bid->project?->budget ?? 0);
                                $amount = (float) $bid->amount;
                                $variance = $budget > 0 ? (($amount - $budget) / $budget) * 100 : null;
                                $varianceColor = is_null($variance) ? '#64748b' : ($variance <= 0 ? '#047857' : '#dc2626');
                                $bidderName = $bid->user?->company ?: ($bid->user?->name ?? 'N/A');
                                $bidderEmail = $bid->user?->email ?? 'N/A';
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
                                $certificateProof = $bid->user?->philgepsCertificate;
                                $certificateProofUrl = $certificateProof?->file_url
                                    ? route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'certificate'])
                                    : null;
                            @endphp
                            <tr>
                                <td class="admin-bids-cell-top">
                                    <div class="admin-bids-bidder-name">{{ $bidderName }}</div>
                                    <div class="admin-bids-bidder-meta">{{ $bidderEmail }}</div>
                                    @if($certificateProofUrl)
                                        <a href="{{ $certificateProofUrl }}" target="_blank" rel="noopener" class="admin-bids-inline-link">View certificate proof</a>
                                    @else
                                        <div class="admin-bids-muted-copy">No certificate proof uploaded</div>
                                    @endif
                                </td>
                                <td class="admin-bids-cell-top admin-bids-project-cell">{{ $bid->project?->title ?? 'N/A' }}</td>
                                <td class="admin-bids-cell-top admin-bids-money nowrap">&#8369;{{ number_format($amount, 2) }}</td>
                                <td class="admin-bids-cell-top admin-bids-money admin-bids-secondary-value nowrap">&#8369;{{ number_format($budget, 2) }}</td>
                                <td class="admin-bids-cell-top admin-bids-variance nowrap" style="color: {{ $varianceColor }};">{{ is_null($variance) ? 'N/A' : number_format($variance, 1) . '%' }}</td>
                                <td class="admin-bids-cell-top admin-bids-date-cell nowrap">{{ $bid->created_at?->format('Y-m-d') }}</td>
                                <td class="admin-bids-cell-top admin-bids-proposal-cell">
                                    @if($bid->proposal_url)
                                        <a href="{{ route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'proposal']) }}" target="_blank" rel="noopener" class="admin-bids-inline-link admin-bids-proposal-link">
                                            <i class="fas fa-file-lines" aria-hidden="true"></i>
                                            <span>View proposal</span>
                                        </a>
                                    @else
                                        <span class="admin-bids-muted-copy">No proposal uploaded</span>
                                    @endif
                                </td>
                                <td class="admin-bids-cell-top admin-bids-status-cell">
                                    <span class="admin-bids-status-pill {{ $statusClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="admin-bids-cell-top">
                                    <div class="admin-bids-actions">
                                        <button type="button" onclick="loadBidViewModal({{ $bid->id }})" class="admin-bids-btn admin-bids-btn-secondary">
                                            <i class="fas fa-eye" aria-hidden="true"></i>
                                            <span>Details</span>
                                        </button>
                                        @if($bid->status === 'pending')
                                            <form action="{{ route('admin.bid.approve', $bid) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="admin-bids-btn admin-bids-btn-approve">Approve</button>
                                            </form>
                                            <form action="{{ route('admin.bid.reject', $bid) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="admin-bids-btn admin-bids-btn-reject">Reject</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="admin-bids-empty">
                                    <i class="fas fa-gavel"></i>
                                    No bids found for this search/filter.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="admin-bids-mobile-list">
                    @forelse($bids as $bid)
                            @php
                                $budget = (float) ($bid->project?->budget ?? 0);
                                $amount = (float) $bid->amount;
                                $variance = $budget > 0 ? (($amount - $budget) / $budget) * 100 : null;
                                $varianceColor = is_null($variance) ? '#64748b' : ($variance <= 0 ? '#047857' : '#dc2626');
                                $bidderName = $bid->user?->company ?: ($bid->user?->name ?? 'N/A');
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
                                $certificateProof = $bid->user?->philgepsCertificate;
                                $certificateProofUrl = $certificateProof?->file_url
                                    ? route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'certificate'])
                                    : null;
                        @endphp
                        <article class="admin-bids-mobile-card">
                            <div class="admin-bids-mobile-head">
                                <div>
                                    <h3>{{ $bidderName }}</h3>
                                    <p>{{ $bid->user?->email ?? 'N/A' }}</p>
                                </div>
                                <span class="admin-bids-status-pill {{ $statusClass }}">{{ $statusLabel }}</span>
                            </div>

                            <div class="admin-bids-mobile-project">{{ $bid->project?->title ?? 'N/A' }}</div>

                            <div class="admin-bids-mobile-grid">
                                <div class="admin-bids-mobile-item">
                                    <span>Bid Amount</span>
                                    <strong>&#8369;{{ number_format($amount, 2) }}</strong>
                                </div>
                                <div class="admin-bids-mobile-item">
                                    <span>Budget</span>
                                    <strong class="admin-bids-secondary-value">&#8369;{{ number_format($budget, 2) }}</strong>
                                </div>
                                <div class="admin-bids-mobile-item">
                                    <span>Variance</span>
                                    <strong style="color: {{ $varianceColor }};">{{ is_null($variance) ? 'N/A' : number_format($variance, 1) . '%' }}</strong>
                                </div>
                                <div class="admin-bids-mobile-item">
                                    <span>Submitted</span>
                                    <strong>{{ $bid->created_at?->format('Y-m-d') }}</strong>
                                </div>
                            </div>

                            <div class="admin-bids-mobile-links">
                                @if($bid->proposal_url)
                                    <a href="{{ route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'proposal']) }}" target="_blank" rel="noopener" class="admin-bids-inline-link admin-bids-proposal-link">
                                        <i class="fas fa-file-lines" aria-hidden="true"></i>
                                        <span>View proposal</span>
                                    </a>
                                @else
                                    <span class="admin-bids-muted-copy">No proposal uploaded</span>
                                @endif

                                @if($certificateProofUrl)
                                    <a href="{{ $certificateProofUrl }}" target="_blank" rel="noopener" class="admin-bids-inline-link">View certificate proof</a>
                                @else
                                    <span class="admin-bids-muted-copy">No certificate proof uploaded</span>
                                @endif
                            </div>

                            <div class="admin-bids-actions admin-bids-mobile-actions">
                                <button type="button" onclick="loadBidViewModal({{ $bid->id }})" class="admin-bids-btn admin-bids-btn-secondary">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                    <span>Details</span>
                                </button>
                                @if($bid->status === 'pending')
                                    <form action="{{ route('admin.bid.approve', $bid) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="admin-bids-btn admin-bids-btn-approve">Approve</button>
                                    </form>
                                    <form action="{{ route('admin.bid.reject', $bid) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="admin-bids-btn admin-bids-btn-reject">Reject</button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="admin-bids-empty admin-bids-empty-mobile">
                            <i class="fas fa-gavel"></i>
                            No bids found for this search/filter.
                        </div>
                    @endforelse
                </div>
            </section>
        </main>
    </div>
</div>

<div id="bidViewModal" class="admin-bid-modal-overlay" aria-hidden="true">
    <div class="admin-bid-modal-dialog" role="dialog" aria-modal="true" aria-label="Bid details">
        <button type="button" onclick="closeBidViewModal()" class="admin-bid-modal-close" aria-label="Close bid details">&times;</button>
        <div id="bidViewModalBody"></div>
    </div>
</div>

<script>
    function loadBidViewModal(id) {
        const modal = document.getElementById('bidViewModal');
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('admin-bid-modal-open');
        document.getElementById('bidViewModalBody').innerHTML = '<div class="admin-bid-modal-loading">Loading bid details...</div>';

        fetch(`/admin/bids/${id}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.text())
            .then(html => {
                document.getElementById('bidViewModalBody').innerHTML = html;
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('bidViewModalBody').innerHTML = '<div class="admin-bid-modal-error">Error loading bid details.</div>';
            });
    }

    function closeBidViewModal() {
        const modal = document.getElementById('bidViewModal');
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('admin-bid-modal-open');
        document.getElementById('bidViewModalBody').innerHTML = '';
    }

    function closeSuccessAlert() {
        const alert = document.getElementById('successAlert');
        if (alert) {
            alert.style.display = 'none';
        }
    }

    document.getElementById('bidViewModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeBidViewModal();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const successAlert = document.getElementById('successAlert');
        if (successAlert) {
            setTimeout(function() {
                successAlert.style.transition = 'opacity 0.5s ease';
                successAlert.style.opacity = '0';
                setTimeout(function() {
                    successAlert.style.display = 'none';
                }, 500);
            }, 5000);
        }
    });
</script>
