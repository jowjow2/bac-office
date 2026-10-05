<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard admin-role-page">
    @vite(['resources/css/dashboard.css', 'resources/css/bid-management.css', 'resources/css/admin-projects.css', 'resources/js/bid-management.js'])

    @include('partials.admin-sidebar')

    <!-- MAIN AREA -->
    <div class="main-area projects-page">

        <!-- PAGE HEADER -->
        <x-page-header class="portal-header--bar" title="Projects & biddings" subtitle="Create, publish and track procurement projects" />

        <!-- MAIN CONTENT -->
        @php
            $projectStatusOptions = [
                '' => ['label' => 'All Status', 'metric' => 'All Projects', 'icon' => 'fas fa-chart-column'],
                'draft' => ['label' => 'Draft', 'metric' => 'Drafts', 'icon' => 'fas fa-file-alt'],
                'approved_for_bidding' => ['label' => 'Approved for Bidding', 'metric' => 'For Bidding', 'icon' => 'fas fa-stamp'],
                'open' => ['label' => 'Open', 'metric' => 'Open', 'icon' => 'fas fa-lock-open'],
                'closed' => ['label' => 'Closed', 'metric' => 'Closed', 'icon' => 'fas fa-lock'],
                'awarded' => ['label' => 'Awarded', 'metric' => 'Awarded', 'icon' => 'fas fa-trophy'],
            ];
        @endphp

        @php
            // Nothing to count, search or export yet: the page shows how to start instead.
            $isEmptyAll = ($projectTotals['all'] ?? 0) === 0 && ! ($showArchived ?? false) && ($search ?? '') === '' && ($status ?? '') === '';
        @endphp

        <main class="dashboard-content projects-content">
            @unless($isEmptyAll)
            <section class="projects-command-panel" aria-label="Projects overview">
                <div class="projects-summary-grid" aria-label="Project status summary">
                    @foreach(['' => 'all', 'draft' => 'draft', 'open' => 'open', 'closed' => 'closed', 'awarded' => 'awarded'] as $statusKey => $totalKey)
                        @php
                            $statusOption = $projectStatusOptions[$statusKey];
                        @endphp
                        <x-project-stat-card
                            :status-key="$statusKey"
                            :total-key="$totalKey"
                            :metric="$statusOption['metric']"
                            :icon="$statusOption['icon']"
                            :count="$projectTotals[$totalKey] ?? 0"
                            :active="($status ?? '') === $statusKey"
                        />
                    @endforeach
                </div>
            </section>
            @endunless

            {{-- With no project yet, Create Project and Archived Projects sit inside the start card below. --}}
            @unless($isEmptyAll)
            <section
                id="project-management"
                class="projects-toolbar"
                aria-label="Project filters and actions"
                data-export-kind="projects"
                data-export-url="{{ route('admin.projects.export') }}"
                data-export-modal-id="projectExportModal"
                data-export-rows-id="projectExportRows"
                data-export-toast-id="projectExportToastRegion"
                data-export-form-selector=".projects-filter-form"
                data-export-status-order="awarded,open,closed,approved_for_bidding,draft"
            >
                <div class="projects-toolbar-actions">
                    <a href="{{ route('admin.projects.create') }}" class="projects-create-link">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        <span>Create Project</span>
                    </a>
                    <a href="{{ ($showArchived ?? false) ? route('admin.projects') : route('admin.projects', ['archived' => 1]) }}" class="projects-archive-link {{ ($showArchived ?? false) ? 'is-active' : '' }}" title="{{ ($showArchived ?? false) ? 'Return to active projects' : 'View archived projects' }}">
                        <i class="fas fa-box-archive" aria-hidden="true"></i>
                        <span>{{ ($showArchived ?? false) ? 'Active Projects' : 'Archived Projects' }}</span>
                        @if(! ($showArchived ?? false) && $archivedCount > 0)<span class="projects-count-chip">{{ $archivedCount }}</span>@endif
                    </a>
                </div>

                @unless($isEmptyAll)
                <form method="GET" action="{{ route('admin.projects') }}" class="projects-filter-form">
                    <div class="projects-filter-group">
                        <div class="projects-search-control">
                            <label class="projects-search-field">
                                <span class="sr-only">Search projects</span>
                                <button type="submit" class="projects-search-icon" aria-label="Search projects"><svg class="projects-search-icon-svg" viewBox="0 0 24 24" focusable="false"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg></button>
                                <input
                                    type="text"
                                    name="search"
                                    value="{{ $search ?? '' }}"
                                    placeholder="Search projects"
                                    class="projects-search-input"
                                >
                            </label>
                        </div>
                        <label class="projects-status-field">
                            <span class="sr-only">Filter by status</span>
                            <select name="status" onchange="this.form.submit()" class="projects-status-select">
                                <option value="">All Status</option>
                                <option value="draft" {{ ($status ?? '') === 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="approved_for_bidding" {{ ($status ?? '') === 'approved_for_bidding' ? 'selected' : '' }}>Approved for Bidding</option>
                                <option value="open" {{ ($status ?? '') === 'open' ? 'selected' : '' }}>Open</option>
                                <option value="closed" {{ ($status ?? '') === 'closed' ? 'selected' : '' }}>Closed</option>
                                <option value="awarded" {{ ($status ?? '') === 'awarded' ? 'selected' : '' }}>Awarded</option>
                            </select>
                            <i class="fas fa-chevron-down" aria-hidden="true"></i>
                        </label>
                        <div class="projects-export-action">
                            <button type="button" class="bid-control" data-open-export-modal>
                                <i class="fas fa-file-export" aria-hidden="true"></i>
                                <span>Export</span>
                            </button>
                        </div>
                    </div>
                    @if(($status ?? '') !== '' || ($search ?? '') !== '')
                        <a href="{{ route('admin.projects') }}{{ ($showArchived ?? false) ? '?archived=1' : '' }}" class="projects-clear-filters-link">
                            <i class="fas fa-xmark" aria-hidden="true"></i>
                            <span>Clear filters</span>
                        </a>
                    @endif
                    @if(($showArchived ?? false))
                        <input type="hidden" name="archived" value="1">
                    @endif
                </form>
                @endunless
            </section>
            @endunless

            @if(! $isEmptyAll && ! ($showArchived ?? false) && $waitingRequestsCount > 0)
                {{-- Purchase requests forwarded to the BAC that have no project yet. --}}
                <a class="projects-waiting" href="{{ $waitingRequestsCount === 1 ? route('admin.projects.create', ['request' => $waitingRequests->first()->id]) : route('admin.requests', ['tab' => 'bac']) }}">
                    <span class="projects-waiting__icon" aria-hidden="true"><i class="fas fa-inbox"></i></span>
                    <span class="projects-waiting__text"><strong>{{ $waitingRequestsCount }} {{ \Illuminate\Support\Str::plural('purchase request', $waitingRequestsCount) }}</strong> forwarded to the BAC {{ $waitingRequestsCount === 1 ? 'is' : 'are' }} waiting for a project.</span>
                    <span class="projects-waiting__go">Prepare procurement <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
                </a>
            @endif

            @if(($status ?? '') !== '' || ($search ?? '') !== '' || ($showArchived ?? false))
                <div class="projects-active-filter-bar" aria-label="Active project filters">
                    <span class="projects-active-filter-label">Active filters</span>

                    @if(($status ?? '') !== '')
                        <span class="projects-active-filter-chip">
                            <span>Status: {{ $projectStatusOptions[$status]['label'] ?? \Illuminate\Support\Str::headline($status) }}</span>
                            <a href="{{ route('admin.projects') }}" aria-label="Clear status filter" title="Clear status filter">
                                <i class="fas fa-times" aria-hidden="true"></i>
                            </a>
                        </span>
                    @endif

                    @if(($search ?? '') !== '')
                        <span class="projects-active-filter-chip">
                            <span>Search: {{ $search }}</span>
                            <a href="{{ route('admin.projects') }}" aria-label="Clear search filter" title="Clear search filter">
                                <i class="fas fa-times" aria-hidden="true"></i>
                            </a>
                        </span>
                    @endif

                    @if(($showArchived ?? false))
                        <span class="projects-active-filter-chip">
                            <span>View: Archived Projects</span>
                            <a href="{{ route('admin.projects') }}" aria-label="Return to active projects" title="Return to active projects">
                                <i class="fas fa-times" aria-hidden="true"></i>
                            </a>
                        </span>
                    @endif
                </div>
            @endif

            @include('partials.schedule-warnings')

            @if(session('success'))
            <div id="successAlert" style="position: fixed; top: 90px; right: 25px; background: #dcfce7; color: #166534; padding: 14px 18px; border-radius: 10px; font-size: 14px; line-height: 1.5; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; display: flex; align-items: center; gap: 10px; min-width: 280px; max-width: min(440px, calc(100vw - 32px)); border: 1px solid #bbf7d0;">
                <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                <span>{{ session('success') }}</span>
                <button onclick="closeSuccessAlert()" style="margin-left: auto; background: none; border: none; color: #166534; cursor: pointer; font-size: 18px; padding: 0; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;">&times;</button>
            </div>
            @endif

            {{-- Why a project was saved as draft / not posted (stays until closed). --}}
            @if(session('error'))
            <div id="errorAlert" role="alert" style="position: fixed; top: 90px; right: 25px; max-width: 520px; background: #fef2f2; color: #991b1b; padding: 16px 20px; border-radius: 10px; font-size: 14px; line-height: 1.5; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1001; display: flex; align-items: flex-start; gap: 10px; border: 1px solid #fecaca;">
                <i class="fas fa-circle-exclamation" style="font-size: 18px; margin-top: 2px;"></i>
                <span>{{ session('error') }}</span>
                <button type="button" onclick="this.parentElement.remove()" aria-label="Close" style="margin-left: auto; background: none; border: none; color: #991b1b; cursor: pointer; font-size: 18px; padding: 0; width: 20px; height: 20px;">&times;</button>
            </div>
            @endif

            <!-- PROJECTS CARD -->
            <section class="projects-table-panel" aria-label="Project list">
                @if($projects->count() > 0)
                    <div class="projects-table-scroll">
                        <table class="projects-table">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Budget</th>
                                    <th>Deadline</th>
                                    <th>Staff</th>
                                    <th>Bids</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($projects as $project)
                                    <x-project-table-row :project="$project" />
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                @if($isEmptyAll)
                    {{-- No project at all yet: where projects come from, and the requests waiting for one. --}}
                    <div class="projects-start">
                        <div class="projects-start__intro">
                            <span class="projects-start__icon" aria-hidden="true"><i class="fas fa-folder-plus"></i></span>
                            <h2>Start your first procurement</h2>
                            <p>A project usually starts from a purchase request that the BAC Secretariat forwarded to the BAC. The wizard fills in the mode of procurement, the requirements and the schedule from it.</p>
                            <ol class="projects-start__steps">
                                <li><strong>Purchase request</strong><span>Filed by the end-user office, checked and forwarded by the Secretariat.</span></li>
                                <li><strong>Prepare the procurement</strong><span>Mode, ABC, requirements and schedule.</span></li>
                                <li><strong>Publish</strong><span>Bidders see it under Opportunities.</span></li>
                            </ol>
                            <div class="projects-start__actions">
                                <a href="{{ route('admin.projects.create') }}" class="projects-create-link">
                                    <i class="fas fa-plus" aria-hidden="true"></i>
                                    <span>Create Project</span>
                                </a>
                                <a href="{{ route('admin.projects', ['archived' => 1]) }}" class="projects-archive-link" title="View archived projects">
                                    <i class="fas fa-box-archive" aria-hidden="true"></i>
                                    <span>Archived Projects</span>
                                    @if($archivedCount > 0)<span class="projects-count-chip">{{ $archivedCount }}</span>@endif
                                </a>
                            </div>
                        </div>
                        <div class="projects-start__queue">
                            <div class="projects-start__queue-head">
                                <h3>Waiting for a project <span>{{ $waitingRequestsCount }}</span></h3>
                                <a href="{{ route('admin.requests', ['tab' => 'bac']) }}">View all</a>
                            </div>
                            @forelse($waitingRequests as $waiting)
                                <a class="projects-start__request" href="{{ route('admin.projects.create', ['request' => $waiting->id]) }}">
                                    <span class="projects-start__request-main">
                                        <code>{{ $waiting->reference_no }}</code>
                                        <strong>{{ $waiting->title }}</strong>
                                        <small>{{ $waiting->end_user_office }} · &#8369;{{ number_format((float) $waiting->estimated_cost, 2) }}</small>
                                    </span>
                                    <span class="projects-start__go">Prepare <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
                                </a>
                            @empty
                                <p class="projects-start__none"><i class="fas fa-inbox" aria-hidden="true"></i> No purchase request is waiting. End-user offices file them, and the Secretariat forwards them to the BAC.</p>
                            @endforelse
                            <a class="projects-start__blank" href="{{ route('admin.projects.create') }}"><i class="fas fa-plus" aria-hidden="true"></i> Create a project without a purchase request</a>
                        </div>
                    </div>
                @else
                    <div class="projects-empty-state">
                        <span class="projects-empty-icon"><i class="fas fa-folder-open" aria-hidden="true"></i></span>
                        <h3>{{ (($search ?? '') !== '' || ($status ?? '') !== '' || ($showArchived ?? false)) ? 'No projects found' : 'No projects yet' }}</h3>
                        <p>{{ (($search ?? '') !== '' || ($status ?? '') !== '' || ($showArchived ?? false)) ? 'Change the search or status filter to widen the list.' : 'Create the first procurement project to start the pipeline.' }}</p>
                        @if(($search ?? '') !== '' || ($status ?? '') !== '' || ($showArchived ?? false))
                            <a href="{{ route('admin.projects') }}" class="projects-empty-action projects-empty-action--secondary">Clear filters</a>
                        @else
                            <a href="{{ route('admin.projects.create') }}" class="projects-empty-action">
                                <i class="fas fa-plus" aria-hidden="true"></i>
                                <span>Create Project</span>
                            </a>
                        @endif
                    </div>
                    @endif
                @endif
            </section>

            @if(isset($projects) && $projects->total() > 0)
                <p class="projects-pagination-summary">Showing {{ $projects->firstItem() }}&ndash;{{ $projects->lastItem() }} of {{ $projects->total() }} projects</p>
            @endif

            <!-- PAGINATION (if needed) -->
            @if(isset($projects) && $projects->hasPages())
            <div class="projects-pagination">
                <nav class="projects-pagination-nav" aria-label="Project pagination">
                    @if($projects->onFirstPage())
                        <span class="projects-pagination-control is-disabled" aria-disabled="true">
                            <i class="fas fa-angle-left" aria-hidden="true"></i>
                            Previous
                        </span>
                    @else
                        <a class="projects-pagination-control" href="{{ $projects->previousPageUrl() }}" rel="prev">
                            <i class="fas fa-angle-left" aria-hidden="true"></i>
                            Previous
                        </a>
                    @endif

                    <div class="projects-pagination-pages">
                        @foreach($projects->getUrlRange(1, $projects->lastPage()) as $page => $url)
                            @if($page === $projects->currentPage())
                                <span class="projects-pagination-page is-current" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="projects-pagination-page" href="{{ $url }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    </div>

                    @if($projects->hasMorePages())
                        <a class="projects-pagination-control" href="{{ $projects->nextPageUrl() }}" rel="next">
                            Next
                            <i class="fas fa-angle-right" aria-hidden="true"></i>
                        </a>
                    @else
                        <span class="projects-pagination-control is-disabled" aria-disabled="true">
                            Next
                            <i class="fas fa-angle-right" aria-hidden="true"></i>
                        </span>
                    @endif
                </nav>
            </div>
            @endif

        </main>
    </div>

</div>

{{-- Quick "Assign staff" from an Unassigned chip in the list (project-table-row). --}}
<dialog class="portal-signout projects-assign" id="quickAssignDialog" aria-labelledby="quickAssignTitle" @if($errors->has('staff_id') && old('return') === 'projects') data-open-on-load @endif>
    <form method="POST" action="{{ route('admin.assignments.store') }}" class="portal-signout__form">
        @csrf
        <input type="hidden" name="return" value="projects">
        <input type="hidden" name="project_id" value="{{ old('project_id') }}" data-quick-assign-project>
        <span class="portal-signout__icon" aria-hidden="true"><i class="fas fa-user-plus"></i></span>
        <h2 id="quickAssignTitle">Assign staff</h2>
        <p data-quick-assign-title>{{ old('project_id') ? optional(\App\Models\Project::find(old('project_id')))->title : '' }}</p>
        @if($assignableStaff->isEmpty())
            <p class="projects-assign__empty">No active staff accounts yet. Approve a staff registration in Suppliers &amp; users first.</p>
            <div class="portal-signout__actions">
                <button type="button" class="portal-signout__button" data-dialog-close>Close</button>
                <a href="{{ route('admin.users') }}" class="portal-signout__button is-primary" style="display:grid;place-items:center;text-decoration:none">Suppliers &amp; users</a>
            </div>
        @else
            <label class="projects-assign__field">
                <span>Staff member</span>
                <select name="staff_id" required>
                    <option value="">Choose a staff member</option>
                    @foreach($assignableStaff as $member)
                        <option value="{{ $member->id }}" @selected((string) old('staff_id') === (string) $member->id)>{{ $member->name }}{{ $member->office ? ' · '.$member->office : '' }}</option>
                    @endforeach
                </select>
            </label>
            <label class="projects-assign__field">
                <span>Role in the project <em>(optional)</em></span>
                <input type="text" name="role_in_project" maxlength="255" value="{{ old('role_in_project') }}" placeholder="e.g. BAC Secretariat, Evaluator">
            </label>
            @error('staff_id')<p class="projects-assign__error" role="alert">{{ $message }}</p>@enderror
            <div class="portal-signout__actions">
                <button type="button" class="portal-signout__button" data-dialog-close>Cancel</button>
                <button type="submit" class="portal-signout__button is-primary">Assign</button>
            </div>
        @endif
    </form>
</dialog>
<style>
    .projects-assign .portal-signout__form { text-align: left; justify-items: stretch; }
    .projects-assign :is(.portal-signout__icon, h2) { justify-self: center; }
    .projects-assign h2 { text-align: center; }
    .projects-assign p[data-quick-assign-title] { margin-bottom: 6px; color: var(--ui-ink-2); font-weight: 600; text-align: center; }
    .projects-assign__field { display: grid; gap: 5px; margin-top: 6px; color: var(--ui-ink-2); font-size: 12.5px; font-weight: 600; }
    .projects-assign__field em { color: var(--ui-subtle); font-style: normal; font-weight: 500; }
    .projects-assign__field :is(select, input) { height: 40px; padding: 0 10px; border: 1px solid var(--ui-line-strong); border-radius: var(--ui-radius); background: var(--ui-surface); color: var(--ui-ink); font: 400 13px var(--ui-font); }
    .projects-assign__field :is(select, input):focus { outline: none; border-color: var(--ui-primary); box-shadow: var(--ui-focus); }
    .projects-assign__error { color: var(--ui-danger); font-size: 12.5px; }
    .projects-assign__empty { text-align: center; }
</style>
<script>
    // An Unassigned chip opens the dialog for its project.
    document.addEventListener('click', function (event) {
        const chip = event.target.closest('[data-quick-assign]');
        const dialog = document.getElementById('quickAssignDialog');
        if (!chip || !dialog || typeof dialog.showModal !== 'function') return;
        const project = dialog.querySelector('[data-quick-assign-project]');
        if (project) project.value = chip.dataset.quickAssign;
        dialog.querySelector('[data-quick-assign-title]').textContent = chip.dataset.projectTitle || '';
        dialog.showModal();
        dialog.querySelector('select')?.focus();
    });
</script>

<!-- PROJECT FILES MODAL -->
<div id="projectFilesModal" style="display: none; position: fixed; inset: 0; padding: 20px; background: rgba(15, 23, 42, 0.45); z-index: 10000; justify-content: center; align-items: center; box-sizing: border-box;">
    <div style="background: white; border-radius: 14px; width: min(680px, 100%); max-height: calc(100vh - 20px); overflow: hidden; position: relative; box-shadow: 0 20px 44px rgba(15, 23, 42, 0.16); box-sizing: border-box;">
        <button onclick="closeProjectFilesModal()" style="position: absolute; top: 16px; right: 16px; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 9px; font-size: 18px; line-height: 1; cursor: pointer; color: #7c8ba1; z-index: 2;">&times;</button>
        <div id="projectFilesModalBody">
        </div>
    </div>
</div>

<!-- VIEW PROJECT MODAL -->
<div id="viewProjectModal" style="display: none;">
    <div>
        <button onclick="closeViewModal()">&times;</button>
        <div id="viewModalBody">
        </div>
    </div>
</div>

<!-- EDIT PROJECT MODAL -->
<div id="editProjectModal" style="display: none; position: fixed; inset: 0; padding: 20px; background: rgba(15, 23, 42, 0.45); z-index: 10001; justify-content: center; align-items: center; box-sizing: border-box;">
    <div style="background: #fffdfa !important; border: 1px solid #e7e5e4 !important; border-radius: 18px; width: min(880px, 100%) !important; max-width: 100% !important; max-height: calc(100dvh - 24px) !important; overflow: hidden; position: relative; box-shadow: 0 28px 80px rgba(15, 23, 42, .22); box-sizing: border-box;">
        <button onclick="closeEditModal()" style="position: absolute; top: 16px; right: 16px; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 9px; font-size: 18px; line-height: 1; cursor: pointer; color: #7c8ba1; z-index: 2;">&times;</button>
        <div id="editModalBody">
        </div>
    </div>
</div>

<!-- DECLARE AWARD MODAL -->
<div id="declareWinnerModal" style="display: none; position: fixed; inset: 0; padding: 20px; background: rgba(15, 23, 42, 0.45); z-index: 10002; justify-content: center; align-items: center; box-sizing: border-box;">
    <div style="background: white; border-radius: 14px; width: min(690px, 100%); max-height: calc(100vh - 20px); overflow: hidden; position: relative; box-shadow: 0 20px 44px rgba(15, 23, 42, 0.16); box-sizing: border-box;">
        <button onclick="closeDeclareWinnerModal()" style="position: absolute; top: 16px; right: 16px; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 9px; font-size: 18px; line-height: 1; cursor: pointer; color: #7c8ba1; z-index: 2;">&times;</button>
        <div id="declareWinnerModalBody">
        </div>
    </div>
</div>

<div id="projectExportModal" class="bid-export-modal-overlay" hidden aria-hidden="true">
    <div class="bid-export-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="projectExportTitle" tabindex="-1">
        <header class="bid-export-modal-header">
            <div>
                <p class="bid-export-eyebrow">Projects &amp; Biddings</p>
                <h2 id="projectExportTitle">Export projects</h2>
            </div>
            <button type="button" class="bid-export-close" data-close-export-modal aria-label="Close export dialog">&times;</button>
        </header>

        <div class="bid-export-modal-body">
            <p class="bid-export-total" data-export-total></p>

            <section class="bid-export-filter-section" aria-labelledby="projectExportFilterTitle">
                <div class="bid-export-section-label-row">
                    <h3 id="projectExportFilterTitle">Filter by status</h3>
                    <span class="bid-export-filter-help">All selected</span>
                </div>
                <div class="bid-export-status-chips" data-export-status-chips role="group" aria-label="Export status filters"></div>
            </section>

            <p class="bid-export-summary" data-export-summary aria-live="polite"></p>
            <p class="bid-export-warning" data-export-warning role="status" hidden>
                <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                <span></span>
            </p>

            <section class="bid-export-preview-section" aria-labelledby="projectExportPreviewTitle">
                <div class="bid-export-section-label-row">
                    <h3 id="projectExportPreviewTitle">Preview</h3>
                    <span class="bid-export-preview-count" data-export-preview-count></span>
                </div>
                <div class="bid-export-preview-frame">
                    <table class="bid-export-preview-table">
                        <thead>
                            <tr><th scope="col">Project</th><th scope="col" class="is-numeric">Budget</th><th scope="col">Status</th></tr>
                        </thead>
                        <tbody data-export-preview-body></tbody>
                    </table>
                    <p class="bid-export-empty" data-export-empty hidden>No projects match the selected statuses.</p>
                </div>
            </section>
        </div>

        <footer class="bid-export-modal-footer">
            <button type="button" class="bid-export-button bid-export-button-secondary" data-close-export-modal>Cancel</button>
            <button type="button" class="bid-export-button bid-export-button-primary" data-confirm-export disabled>Confirm export</button>
        </footer>

    </div>
</div>

<script type="application/json" id="projectExportRows">@json($exportRows ?? [])</script>
<div id="projectExportToastRegion" class="bid-export-toast-region" aria-live="polite" aria-atomic="true"></div>

<script>

    function closeSuccessAlert() {
        const alert = document.getElementById('successAlert');
        if (alert) {
            alert.style.display = 'none';
        }
    }

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

    let currentProjectId = null;

    function loadProjectFilesModal(id) {
        currentProjectId = id;
        document.getElementById('projectFilesModal').style.display = 'flex';
        document.getElementById('projectFilesModalBody').innerHTML = '<div style="padding: 28px; color: #64748b; font-size: 14px;">Loading project files...</div>';

        fetch(`/admin/projects/${id}/files`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('projectFilesModalBody').innerHTML = html;
                attachProjectFilesFormHandlers();
            })
            .catch(error => {
                document.getElementById('projectFilesModalBody').innerHTML = '<p style="color: red; padding: 24px;">Error loading project files.</p>';
                console.error('Error:', error);
            });
    }

    function closeProjectFilesModal() {
        document.getElementById('projectFilesModal').style.display = 'none';
        document.getElementById('projectFilesModalBody').innerHTML = '';
    }

    function attachProjectFilesFormHandlers() {
        document.querySelectorAll('#projectFilesModalBody [data-project-file-delete-form]').forEach(function(form) {
            form.onsubmit = function(e) {
                e.preventDefault();

                const submitBtn = form.querySelector('button[type="submit"]');
                if (!submitBtn) return;

                submitBtn.disabled = true;
                submitBtn.textContent = 'Deleting...';
                setProjectFilesAlert('', 'error', false);

                fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    }
                })
                .then(async response => {
                    const data = await response.json();
                    return { ok: response.ok, data };
                })
                .then(result => {
                    if (result.ok && result.data.success) {
                        showTempMessage(result.data.message || 'Project file deleted successfully!', 'success');
                        loadProjectFilesModal(currentProjectId);
                    } else {
                        setProjectFilesAlert(result.data.message || 'Unable to delete this project file.', 'error', true);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    setProjectFilesAlert('Unable to delete this project file.', 'error', true);
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Delete';
                });
            };
        });
    }

    function setProjectFilesAlert(message = '', type = 'error', visible = true) {
        const alertBox = document.getElementById('projectFilesAlert');
        if (!alertBox) return;

        alertBox.classList.remove('is-success', 'is-error');

        if (!visible || !message) {
            alertBox.style.display = 'none';
            alertBox.textContent = '';
            return;
        }

        alertBox.classList.add(type === 'success' ? 'is-success' : 'is-error');
        alertBox.textContent = message;
        alertBox.style.display = 'block';
    }

    function loadViewModal(id) {
        currentProjectId = id;
        document.getElementById('viewProjectModal').style.display = 'flex';

        fetch(`/admin/projects/${id}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('viewModalBody').innerHTML = html;
            })
            .catch(error => {
                document.getElementById('viewModalBody').innerHTML = '<p style="color: red;">Error loading project details.</p>';
                console.error('Error:', error);
            });
    }

    function closeViewModal() {
        document.getElementById('viewProjectModal').style.display = 'none';
    }

    function loadEditModal(id) {
        currentProjectId = id;
        document.getElementById('editProjectModal').style.display = 'flex';
        document.body.classList.add('bac-modal-open');

        fetch(`/admin/projects/${id}/edit`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('editModalBody').innerHTML = html;
                attachEditFormHandler();
            })
            .catch(error => {
                document.getElementById('editModalBody').innerHTML = '<p style="color: red;">Error loading edit form.</p>';
                console.error('Error:', error);
            });
    }

    function closeEditModal() {
        document.getElementById('editProjectModal').style.display = 'none';
        document.body.classList.remove('bac-modal-open');
    }

    function loadDeclareWinnerModal(projectId, bidId = null) {
        currentProjectId = projectId;
        document.getElementById('declareWinnerModal').style.display = 'flex';
        document.getElementById('declareWinnerModalBody').innerHTML = '<div style="padding: 28px; color: #64748b; font-size: 14px;">Loading award form...</div>';

        const url = bidId
            ? `/admin/projects/${projectId}/award?bid=${bidId}`
            : `/admin/projects/${projectId}/award`;

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            }
        })
            .then(response => response.text())
            .then(html => {
                document.getElementById('declareWinnerModalBody').innerHTML = html;
                attachAwardFormHandler();
            })
            .catch(error => {
                document.getElementById('declareWinnerModalBody').innerHTML = '<p style="color: red; padding: 24px;">Error loading award form.</p>';
                console.error('Error:', error);
            });
    }

    function closeDeclareWinnerModal() {
        document.getElementById('declareWinnerModal').style.display = 'none';
        document.getElementById('declareWinnerModalBody').innerHTML = '';
    }

    function selectDeclareWinnerOption(option) {
        document.querySelectorAll('#declareWinnerModal [data-bid-option]').forEach(function(item) {
            item.classList.remove('is-selected');
            const radio = item.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = false;
            }
        });

        option.classList.add('is-selected');

        const radio = option.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
        }

        const amountField = document.getElementById('awardContractAmount');
        if (amountField) {
            amountField.value = option.dataset.bidAmount || '';
        }
    }

    function syncSourceOfFundFields(root = document) {
        const scope = root || document;
        const fields = scope.matches && scope.matches('[data-source-of-fund-field]')
            ? [scope]
            : Array.from(scope.querySelectorAll('[data-source-of-fund-field]'));

        fields.forEach(function(field) {
            const hidden = field.querySelector('[data-source-of-fund-value]');
            const select = field.querySelector('[data-source-of-fund-select]');
            const otherWrap = field.querySelector('[data-source-of-fund-other-wrap]');
            const other = field.querySelector('[data-source-of-fund-other]');

            if (!hidden || !select) return;

            const isOther = select.value === 'Other';
            if (otherWrap) otherWrap.hidden = !isOther;
            hidden.value = isOther ? ((other && other.value.trim()) || select.value) : select.value;
        });
    }

    function initSourceOfFundFields(root = document) {
        const scope = root || document;
        const fields = scope.matches && scope.matches('[data-source-of-fund-field]')
            ? [scope]
            : Array.from(scope.querySelectorAll('[data-source-of-fund-field]'));

        fields.forEach(function(field) {
            if (field.dataset.sourceOfFundReady === 'true') return;
            field.dataset.sourceOfFundReady = 'true';

            const select = field.querySelector('[data-source-of-fund-select]');
            const other = field.querySelector('[data-source-of-fund-other]');

            if (select) select.addEventListener('change', function() { syncSourceOfFundFields(field); });
            if (other) other.addEventListener('input', function() { syncSourceOfFundFields(field); });
        });

        syncSourceOfFundFields(scope);
    }
    function syncContractDurationFields(root = document) {
        const scope = root || document;
        const fields = scope.matches && scope.matches('[data-contract-duration-field]')
            ? [scope]
            : Array.from(scope.querySelectorAll('[data-contract-duration-field]'));

        fields.forEach(function(field) {
            const hidden = field.querySelector('[data-contract-duration-value]');
            const select = field.querySelector('[data-contract-duration-select]');
            const customWrap = field.querySelector('[data-contract-duration-custom-wrap]');
            const custom = field.querySelector('[data-contract-duration-custom]');

            if (!hidden || !select) return;

            const isCustom = select.value === 'Custom';
            if (customWrap) customWrap.hidden = !isCustom;
            hidden.value = isCustom ? ((custom && custom.value.trim()) || select.value) : select.value;
        });
    }

    function initContractDurationFields(root = document) {
        const scope = root || document;
        const fields = scope.matches && scope.matches('[data-contract-duration-field]')
            ? [scope]
            : Array.from(scope.querySelectorAll('[data-contract-duration-field]'));

        fields.forEach(function(field) {
            if (field.dataset.contractDurationReady === 'true') return;
            field.dataset.contractDurationReady = 'true';

            const select = field.querySelector('[data-contract-duration-select]');
            const custom = field.querySelector('[data-contract-duration-custom]');

            if (select) select.addEventListener('change', function() { syncContractDurationFields(field); });
            if (custom) custom.addEventListener('input', function() { syncContractDurationFields(field); });
        });

        syncContractDurationFields(scope);
    }

    function attachEditFormHandler() {
        const form = document.querySelector('#editModalBody form');
        if (form) {
            initSourceOfFundFields(form);
            initContractDurationFields(form);
            window.BacPortal?.setupMoneyInputs(form);
            initEditEvaluationFields(form);
            initEditFeeVenue(form);
            initEditFileNames(form);

            const publishBtn = form.querySelector('#editPublishBtn');
            if (publishBtn) {
                publishBtn.onclick = function() {
                    publishEditProject(currentProjectId, publishBtn);
                };
            }

            form.onsubmit = function(e) {
                e.preventDefault();
                const submitBtn = document.getElementById('editSubmitBtn');
                if (!submitBtn) return;

                const submitLabel = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

                clearEditFormErrors();

                syncSourceOfFundFields(form);
                syncContractDurationFields(form);

                const formData = new FormData(form);
                formData.append('_method', 'PUT');

                fetch(`/admin/projects/${currentProjectId}`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                })
                .then(async response => {
                    const data = await response.json();
                    return { ok: response.ok, status: response.status, data };
                })
                .then(data => {
                    clearEditFormErrors();

                    if (data.ok && data.data.success) {
                        closeEditModal();
                        showTempMessage(data.data.message || 'Project updated successfully!', 'success');
                        refreshTable();
                    } else {
                        renderEditFormErrors(data.data.errors || {}, data.data.message || 'Please check the form and try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    renderEditFormErrors({}, 'Update failed. Please try again.');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitLabel;
                });
            };
        }
    }

    // MEARB / MARB fields show only for those criteria; the weights show their running total.
    function initEditEvaluationFields(form) {
        const criterion = form.querySelector('[data-pe-criterion]');
        const weights = Array.from(form.querySelectorAll('[data-pe-weight]'));
        const total = form.querySelector('[data-pe-weight-total]');

        const syncCriterion = () => {
            const value = criterion ? criterion.value : '';
            form.querySelectorAll('[data-pe-weighted]').forEach(el => { el.hidden = !['mearb', 'marb'].includes(value); });
            form.querySelectorAll('[data-pe-mearb]').forEach(el => { el.hidden = value !== 'mearb'; });
        };
        const syncTotal = () => {
            if (!total) return;
            const sum = weights.reduce((carry, input) => carry + (Number(input.value) || 0), 0);
            total.textContent = `${Math.round(sum * 100) / 100}%`;
            total.closest('tr')?.classList.toggle('is-off', sum > 0 && Math.abs(sum - 100) > 0.01);
        };

        criterion?.addEventListener('change', syncCriterion);
        weights.forEach(input => input.addEventListener('input', syncTotal));
        syncCriterion();
        syncTotal();
    }

    // "Where to pay" shows only when there is a fee.
    // Bidding documents fee: competitive bidding shows the ABC schedule's maximum
    // (GPPB Circular No. 02-2026, Sec. 5.2); a lower fee or a waiver needs a reason.
    // The server applies the same rules.
    function initEditFeeVenue(form) {
        const section = form.querySelector('[data-pe-fee-section]');
        const fee = form.querySelector('[data-pe-fee]');
        const venue = form.querySelector('[data-pe-fee-venue]');
        if (!section || !fee || !venue) return;

        const competitive = section.dataset.peFeeCompetitive === '1';
        const schedule = JSON.parse(section.dataset.peFeeSchedule || '[]');
        const budget = form.querySelector('[name="budget"]');
        const modes = Array.from(form.querySelectorAll('[data-pe-fee-mode]'));
        const amountField = form.querySelector('[data-pe-fee-amount]');
        const reasonField = form.querySelector('[data-pe-fee-reason]');
        const calc = form.querySelector('[data-pe-fee-calc]');
        const max = form.querySelector('[data-pe-fee-max]');
        const peso = (value) => Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const amount = (input) => Number(String(input?.value || '').replace(/[^0-9.]/g, '')) || 0;

        const sync = () => {
            if (!competitive) {
                venue.hidden = !(amount(fee) > 0);
                return;
            }
            const abc = amount(budget);
            const bracket = abc > 0 ? schedule.find((row) => row.limit === null || Math.round(abc * 100) <= Math.round(row.limit * 100)) : null;
            const mode = modes.find((radio) => radio.checked)?.value || 'schedule';
            if (calc) {
                calc.textContent = bracket
                    ? `ABC ₱${peso(abc)}: ${bracket.bracket.replace(/^ABC /, '')}, so the maximum fee is ₱${peso(bracket.maximum)}.`
                    : 'Set the ABC to compute the maximum fee.';
            }
            if (max) max.textContent = bracket ? `(₱${peso(bracket.maximum)})` : '';
            if (amountField) amountField.hidden = mode !== 'reduced';
            if (reasonField) reasonField.hidden = mode === 'schedule';
            const charged = mode === 'waived' ? 0 : (mode === 'reduced' ? amount(fee) : (bracket?.maximum || 0));
            venue.hidden = !(charged > 0);
        };

        [fee, budget, ...modes].forEach((input) => {
            input?.addEventListener('input', sync);
            input?.addEventListener('change', sync);
        });
        sync();
    }

    function initEditFileNames(form) {
        const input = form.querySelector('[data-pe-files]');
        const names = form.querySelector('[data-pe-file-names]');
        if (!input || !names) return;

        input.addEventListener('change', () => {
            const files = Array.from(input.files || []).map(file => file.name);
            names.textContent = files.length ? files.join(', ') : 'No files chosen';
        });
    }

    function attachAwardFormHandler() {
        const form = document.querySelector('#declareWinnerModalBody form');
        if (!form) return;

        form.onsubmit = function(e) {
            e.preventDefault();

            const submitBtn = document.querySelector('#declareWinnerModalBody .declare-award-primary');
            if (!submitBtn) return;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            clearAwardFormErrors();

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                }
            })
            .then(async response => {
                const data = await response.json();
                return { ok: response.ok, data };
            })
            .then(result => {
                if (result.ok && result.data.success) {
                    closeDeclareWinnerModal();
                    showTempMessage(result.data.message || 'Award created successfully!', 'success');
                    setTimeout(refreshTable, 450);
                } else {
                    renderAwardFormErrors(result.data.errors || {}, result.data.message || 'Please check the form and try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                renderAwardFormErrors({}, 'Award creation failed. Please try again.');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Declare Winner';
            });
        };
    }

    function clearEditFormErrors() {
        const alertBox = document.getElementById('editFormAlert');
        if (alertBox) {
            alertBox.style.display = 'none';
            alertBox.textContent = '';
        }

        document.querySelectorAll('#editModalBody .input-error').forEach(field => {
            field.classList.remove('input-error');
        });

        document.querySelectorAll('#editModalBody [data-error-for]').forEach(errorEl => {
            errorEl.textContent = '';
        });
    }

    function renderEditFormErrors(errors = {}, message = '') {
        const alertBox = document.getElementById('editFormAlert');
        const unplaced = [];
        let placed = 0;
        let firstInvalid = null;

        Object.entries(errors).forEach(([field, messages]) => {
            const normalizedField = field.replace(/\.\d+$/, '');
            const input = document.querySelector(`#editModalBody [name="${field}"]`)
                || document.querySelector(`#editModalBody [name="${normalizedField}"]`)
                || document.querySelector(`#editModalBody [name="${normalizedField}[]"]`);
            const errorEl = document.querySelector(`#editModalBody [data-error-for="${field}"]`)
                || document.querySelector(`#editModalBody [data-error-for="${normalizedField}"]`);
            const text = Array.isArray(messages) ? messages[0] : messages;

            if (input) {
                input.classList.add('input-error');
                firstInvalid = firstInvalid || input;
            }

            if (errorEl) {
                errorEl.textContent = text;
                placed++;
            } else if (text) {
                unplaced.push(text);
            }
        });

        // Messages shown beside their fields are not repeated in the banner.
        if (unplaced.length) {
            message = [...new Set(unplaced)].join(' ');
        } else if (placed) {
            message = placed === 1 ? 'Please correct the highlighted field.' : `Please correct the ${placed} highlighted fields.`;
        }

        if (alertBox) {
            alertBox.textContent = message || '';
            alertBox.style.display = message ? 'block' : 'none';
        }

        if (firstInvalid && firstInvalid.type !== 'hidden') {
            firstInvalid.focus({ preventScroll: true });
            firstInvalid.scrollIntoView({ block: 'center' });
        } else if (alertBox && message) {
            alertBox.scrollIntoView({ block: 'nearest' });
        }
    }

    function clearAwardFormErrors() {
        const alertBox = document.getElementById('awardFormAlert');
        if (alertBox) {
            alertBox.style.display = 'none';
            alertBox.textContent = '';
        }

        document.querySelectorAll('#declareWinnerModalBody .input-error').forEach(field => {
            field.classList.remove('input-error');
        });

        document.querySelectorAll('#declareWinnerModalBody [data-error-for]').forEach(errorEl => {
            errorEl.textContent = '';
        });
    }

    function renderAwardFormErrors(errors = {}, message = '') {
        const alertBox = document.getElementById('awardFormAlert');
        const hasFieldErrors = Object.keys(errors).length > 0;

        if (alertBox) {
            if (message) {
                alertBox.textContent = message;
                alertBox.style.display = 'block';
            } else {
                alertBox.style.display = 'none';
                alertBox.textContent = '';
            }
        }

        Object.entries(errors).forEach(([field, messages]) => {
            const text = Array.isArray(messages) ? messages[0] : messages;
            const errorEl = document.querySelector(`#declareWinnerModalBody [data-error-for="${field}"]`);

            if (field === 'bid_id') {
                const optionsWrap = document.querySelector('#declareWinnerModalBody .declare-award-options');
                if (optionsWrap) {
                    optionsWrap.classList.add('input-error');
                }
            } else {
                const input = document.querySelector(`#declareWinnerModalBody [name="${field}"]`);
                if (input) {
                    input.classList.add('input-error');
                }
            }

            if (errorEl) {
                errorEl.textContent = text;
            }
        });
    }

    function refreshTable() {
        location.reload();
    }

    function closeProjectActionMenus() {
        document.querySelectorAll('.projects-action-menu.is-open').forEach(menu => {
            menu.classList.remove('is-open');
            const trigger = menu.querySelector('.projects-action-more');
            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function toggleProjectActionMenu(button) {
        const menu = button.closest('.projects-action-menu');
        if (!menu) return;

        const willOpen = !menu.classList.contains('is-open');
        closeProjectActionMenus();
        menu.classList.toggle('is-open', willOpen);
        button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.projects-action-menu')) {
            closeProjectActionMenus();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeProjectActionMenus();
        }
    });

    function showTempMessage(message, type = 'success') {
        if (window.bacToast) {
            window.bacToast(message, type === 'success' ? 'success' : 'error');
            return;
        }
        const alertDiv = document.createElement('div');
        alertDiv.id = 'tempMessage';
        alertDiv.style.cssText = `
            position: fixed; top: 90px; right: 25px;
            background: ${type === 'success' ? '#dcfce7' : '#fee2e2'};
            color: ${type === 'success' ? '#166534' : '#991b1b'};
            padding: 16px 20px; border-radius: 8px; font-size: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 10002;
            display: flex; align-items: center; gap: 10px; min-width: 280px;
        `;
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}" style="font-size: 18px;"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: inherit; cursor: pointer; font-size: 16px;">&times;</button>
        `;
        document.body.appendChild(alertDiv);

        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    document.getElementById('viewProjectModal').addEventListener('click', function(e) {
        if (e.target === this) closeViewModal();
    });

    document.getElementById('projectFilesModal').addEventListener('click', function(e) {
        if (e.target === this) closeProjectFilesModal();
    });

    document.getElementById('editProjectModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });

    document.getElementById('declareWinnerModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeclareWinnerModal();
    });

    async function publishEditProject(projectId, button) {
        if (!await window.bacConfirm({ title: 'Publish this project?', message: 'It becomes visible to eligible bidders in the BAC system. The PhilGEPS posting is recorded separately.', confirmLabel: 'Publish', icon: 'fa-bullhorn' })) {
            return;
        }

        if (!button) return;

        clearEditFormErrors();
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publishing...';

        fetch(`/admin/projects/${projectId}/publish`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            }
        })
        .then(async response => {
            const data = await response.json().catch(() => ({
                message: 'Failed to publish project. Please check the form.',
                errors: {},
            }));
            return { ok: response.ok, data };
        })
        .then(result => {
            if (result.ok && result.data.success) {
                closeEditModal();
                showTempMessage(result.data.message || 'Project published in the BAC system.', 'success');
                setTimeout(refreshTable, 450);
            } else {
                renderEditFormErrors(result.data.errors || {}, result.data.message || 'Project cannot be published yet.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            renderEditFormErrors({}, 'Publication failed. Please try again.');
        })
        .finally(() => {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-bullhorn" aria-hidden="true"></i> Publish to BAC System';
        });
    }
    async function publishDraft(projectId, button) {
        if (!await window.bacConfirm({ title: 'Publish this draft?', message: 'The project becomes available for bidding.', confirmLabel: 'Publish', icon: 'fa-paper-plane' })) {
            return;
        }

        if (!button) {
            showTempMessage('Unable to publish project from this button. Please refresh and try again.', 'error');
            return;
        }

        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publishing...';

        fetch(`/admin/projects/${projectId}/publish`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            }
        })
        .then(async response => {
            const data = await response.json().catch(() => ({
                message: 'Failed to publish project. Please refresh and try again.',
            }));
            return { ok: response.ok, data };
        })
        .then(result => {
            if (result.ok && result.data.success) {
                showTempMessage(result.data.message || 'Project published successfully!', 'success');
                setTimeout(() => {
                    window.location.href = '/admin/projects';
                }, 1000);
            } else {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-paper-plane"></i> Publish';
                showTempMessage(result.data.message || 'Failed to publish project.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-paper-plane"></i> Publish';
            showTempMessage('Failed to publish project.', 'error');
        });
    }


    // Inline staff assignment for the View Project summary modal.
    document.addEventListener('click', function (event) {
        const toggle = event.target.closest('[data-view-project-assign-toggle]');
        if (toggle) {
            const section = toggle.closest('.view-project-summary-section');
            const panel = section ? section.querySelector('[data-view-project-assign-panel]') : null;

            if (panel) {
                panel.hidden = false;
                toggle.setAttribute('aria-expanded', 'true');
            }

            return;
        }

        const cancel = event.target.closest('[data-view-project-assign-cancel]');
        if (cancel) {
            const panel = cancel.closest('[data-view-project-assign-panel]');
            const section = cancel.closest('.view-project-summary-section');
            const toggleButton = section ? section.querySelector('[data-view-project-assign-toggle]') : null;

            if (panel) {
                panel.hidden = true;
            }

            if (toggleButton) {
                toggleButton.setAttribute('aria-expanded', 'false');
            }
        }
    });

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('[data-view-project-assign-form]');
        if (!form) {
            return;
        }

        event.preventDefault();

        const select = form.querySelector('select[name="staff_id"]');
        const submitButton = form.querySelector('.view-project-assign-save');
        const error = form.querySelector('[data-view-project-assign-error]');
        const selectedOption = select ? select.options[select.selectedIndex] : null;

        if (!select || !select.value) {
            if (error) {
                error.textContent = 'Please select a staff member.';
                error.classList.add('is-visible');
            }
            return;
        }

        if (error) {
            error.textContent = '';
            error.classList.remove('is-visible');
        }

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';
        }

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        })
            .then(async function (response) {
                const data = await response.json().catch(function () {
                    return {};
                });

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Unable to assign staff.');
                }

                return data;
            })
            .then(function (data) {
                const modal = form.closest('#viewProjectModal');
                const staffValue = modal ? modal.querySelector('[data-view-project-staff-value]') : null;
                const panel = form.closest('[data-view-project-assign-panel]');
                const toggleButton = modal ? modal.querySelector('[data-view-project-assign-toggle]') : null;

                if (staffValue) {
                    staffValue.textContent = data.staff_name || selectedOption.textContent.trim();
                }

                if (toggleButton) {
                    toggleButton.remove();
                }

                if (panel) {
                    panel.remove();
                }

                if (typeof showTempMessage === 'function') {
                    showTempMessage(data.message || 'Staff assigned successfully.', 'success');
                }
            })
            .catch(function (assignmentError) {
                if (error) {
                    error.textContent = assignmentError.message;
                    error.classList.add('is-visible');
                }
            })
            .finally(function () {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Save';
                }
            });
    });

</script>
