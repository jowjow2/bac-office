<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
@php
    // BAC Admin: every bid, with decisions. BAC Staff: the assigned projects' bids, to check documents.
    $portal ??= 'admin';
    $isStaff = $portal === 'staff';
@endphp
<div class="admin-dashboard admin-role-page {{ $isStaff ? 'staff-role-page' : '' }} dashboard-home admin-dashboard-page admin-bids-page">
    @vite(['resources/css/dashboard.css', 'resources/css/bid-management.css', 'resources/js/bid-management.js'])

    @if($isStaff)
        @include('partials.staff-sidebar', ['activeStaffMenu' => 'review-bids'])
    @else
        @include('partials.admin-sidebar')
    @endif

    <div class="main-area bids-page">
        @if($isStaff)
            <x-page-header title="Review bids & quotations" subtitle="Submissions for the projects assigned to you. Check each technical document; the BAC Admin records the opening and every review decision.">
                <x-slot:actions>
                    <button type="button" class="ui-btn ui-btn--secondary hd-export" data-open-export-modal><i class="fas fa-file-export" aria-hidden="true"></i> Export</button>
                </x-slot:actions>
            </x-page-header>
        @else
            <x-page-header title="Bid management" subtitle="Review and evaluate all submitted bids">
                <x-slot:actions>
                    <button type="button" class="ui-btn ui-btn--secondary hd-export" data-open-export-modal><i class="fas fa-file-export" aria-hidden="true"></i> Export</button>
                </x-slot:actions>
            </x-page-header>
        @endif

        <main class="dashboard-content dashboard-home-content admin-bids-v2">
 
            @if(session('success'))
                <div id="successAlert" style="position: fixed; top: 90px; right: 25px; background: #dcfce7; color: #166534; padding: 16px 20px; border-radius: 8px; font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; display: flex; align-items: center; gap: 10px; min-width: 280px;">
                    <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                    <span>{{ session('success') }}</span>
                    <button onclick="closeSuccessAlert()" style="margin-left: auto; background: none; border: none; color: #166534; cursor: pointer; font-size: 16px;">&times;</button>
                </div>
            @endif

            @if($errors->any())
                <div class="bid-error" role="alert">{{ $errors->first() }}</div>
            @endif

            <section class="bid-summary-grid" aria-label="Submission summary">
                <div class="bid-summary-card"><span>Total in view</span><strong>{{ $summary['total'] ?? 0 }}</strong><small>Matches current filters</small></div>
                <div class="bid-summary-card is-sealed"><span>Sealed submissions</span><strong>{{ $summary['sealed'] ?? 0 }}</strong><small>Opening must be recorded</small></div>
                <div class="bid-summary-card is-ready"><span>Ready for examination</span><strong>{{ $summary['ready'] ?? 0 }}</strong><small>Opening/review available</small></div>
                <div class="bid-summary-card is-progress"><span>In evaluation workflow</span><strong>{{ $summary['decided'] ?? 0 }}</strong><small>Evaluation or post-qualification</small></div>
            </section>

            <section id="bid-management" class="admin-bids-shell admin-bids-v2"
                data-view-url="{{ route($isStaff ? 'staff.bid.view' : 'admin.bid.view', ['bid' => '__BID__']) }}"
                data-edit-url="{{ $isStaff ? route('staff.bid.view', ['bid' => '__BID__']) : route('admin.bid.edit', ['bid' => '__BID__']) }}"
                data-export-kind="bids"
                data-export-url="{{ route($isStaff ? 'staff.bids.export' : 'admin.bids.export') }}"
                data-export-modal-id="bidExportModal"
                data-export-rows-id="bidExportRows"
                data-export-toast-id="bidExportToastRegion"
                data-export-form-selector=".admin-bids-toolbar" data-register-stage="bac-review"
                data-export-status-order="{{ implode(',', array_keys($statusOptions ?? [])) }}"
                data-export-status-labels='@json($statusOptions ?? [])'>
                <div class="bid-register-heading">
                    <div>
                        <span class="bid-register-heading__eyebrow">BAC review workspace</span>
                        <h2>Submitted bids</h2>
                        <p>Open a submission to review its current stage and required documents.</p>
                    </div>
                    <span class="bid-register-heading__count">{{ $bids->total() }} {{ \Illuminate\Support\Str::plural('submission', $bids->total()) }}</span>
                </div>
                <form method="GET" action="{{ route($isStaff ? 'staff.review-bids' : 'admin.bids') }}" class="admin-bids-toolbar">
                    <input type="hidden" name="per_page" value="{{ $bids->perPage() }}">
                    <div class="admin-bids-filter-group">
                        <div class="admin-bids-toolbar-field admin-bids-toolbar-field-search admin-search-field">
                            <svg class="admin-bids-search-icon admin-search-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Search bids..." class="admin-bids-input" aria-label="Search bids">
                        </div>
                        <span class="admin-bids-filter-divider" aria-hidden="true"></span>
                        <div class="admin-bids-toolbar-field">
                            <span class="bid-filter-caption">Stage</span>
                            <select name="status" onchange="this.form.submit()" class="admin-bids-select" aria-label="Filter by stage">
                                <option value="">All Stages</option>
                                @foreach(($statusOptions ?? []) as $value => $label)
                                    <option value="{{ $value }}" @selected(($status ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <span class="admin-bids-filter-divider" aria-hidden="true"></span>
                        <div class="admin-bids-toolbar-field">
                            <span class="bid-filter-caption">Project</span>
                            <select name="project" onchange="this.form.submit()" class="admin-bids-select" aria-label="Filter by project">
                                <option value="">All Projects</option>
                                @foreach(($projects ?? collect()) as $project)
                                    <option value="{{ $project->id }}" {{ (string) ($projectFilter ?? '') === (string) $project->id ? 'selected' : '' }}>{{ $project->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <span class="admin-bids-filter-divider" aria-hidden="true"></span>
                        <div class="admin-bids-toolbar-field">
                            <span class="bid-filter-caption">Procurement</span>
                            <select name="mode" onchange="this.form.submit()" class="admin-bids-select" aria-label="Filter by procurement mode">
                                <option value="">All Procurement Modes</option>
                                @foreach(($modeOptions ?? []) as $value => $label)
                                    <option value="{{ $value }}" @selected(($modeFilter ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <span class="admin-bids-filter-divider" aria-hidden="true"></span>
                        <div class="admin-bids-toolbar-field">
                            <span class="bid-filter-caption">Documents</span>
                            <select name="document_status" onchange="this.form.submit()" class="admin-bids-select" aria-label="Filter by document status">
                                <option value="">All Document States</option>
                                @foreach(($documentStatusOptions ?? []) as $value => $label)
                                    <option value="{{ $value }}" @selected(($documentStatusFilter ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </form>
                @if($rankedProject)
                    @include('admin.partials.bid-ranking')
                @endif
                @include('admin.partials.bid-table')
            </section>
        </main>
    </div>
</div>

<div id="bidViewModal" class="admin-bid-modal-overlay" aria-hidden="true">
    <div class="admin-bid-modal-dialog" role="dialog" aria-modal="true" aria-label="Bid submission details" tabindex="-1">
        <button type="button" onclick="closeBidViewModal()" class="admin-bid-modal-close" aria-label="Close bid details">&times;</button>
        <div id="bidViewModalBody"></div>
    </div>
</div>

<div id="bidExportModal" class="bid-export-modal-overlay" hidden aria-hidden="true">
    <div class="bid-export-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="bidExportTitle" tabindex="-1">
        <header class="bid-export-modal-header">
            <div>
                <p class="bid-export-eyebrow">Bid management</p>
                <h2 id="bidExportTitle">Export bids</h2>
            </div>
            <button type="button" class="bid-export-close" data-close-export-modal aria-label="Close export dialog">&times;</button>
        </header>

        <div class="bid-export-modal-body">
            <p class="bid-export-total" data-export-total></p>

            <section class="bid-export-filter-section" aria-labelledby="bidExportFilterTitle">
                <div class="bid-export-section-label-row">
                    <h3 id="bidExportFilterTitle">Filter by status</h3>
                    <span class="bid-export-filter-tools"><span class="bid-export-filter-help">All selected</span><button type="button" class="bid-export-link" data-export-all>Select all</button><button type="button" class="bid-export-link" data-export-none>Clear</button></span>
                </div>
                <div class="bid-export-status-chips" data-export-status-chips role="group" aria-label="Export status filters"></div>
            </section>

            <p class="bid-export-summary" data-export-summary aria-live="polite"></p>
            <p class="bid-export-warning" data-export-warning role="status" hidden>
                <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                <span></span>
            </p>

            <section class="bid-export-preview-section" aria-labelledby="bidExportPreviewTitle">
                <div class="bid-export-section-label-row">
                    <h3 id="bidExportPreviewTitle">Preview</h3>
                    <span class="bid-export-preview-count" data-export-preview-count></span>
                </div>
                <div class="bid-export-preview-frame">
                    <table class="bid-export-preview-table">
                        <thead>
                            <tr><th scope="col">Bidder</th><th scope="col" class="is-numeric">Bid Amount</th><th scope="col">Status</th></tr>
                        </thead>
                        <tbody data-export-preview-body></tbody>
                    </table>
                    <p class="bid-export-empty" data-export-empty hidden>No bids match the selected statuses.</p>
                </div>
            </section>
        </div>

        <footer class="bid-export-modal-footer">
            <button type="button" class="bid-export-button bid-export-button-secondary" data-close-export-modal>Cancel</button>
            <button type="button" class="bid-export-button bid-export-button-primary" data-confirm-export disabled>Confirm export</button>
        </footer>
    </div>
</div>

<script type="application/json" id="bidExportRows">@json($exportRows ?? [])</script>
<div id="bidExportToastRegion" class="bid-export-toast-region" aria-live="polite" aria-atomic="true"></div>
