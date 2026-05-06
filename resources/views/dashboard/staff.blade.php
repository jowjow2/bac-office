<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home staff-dashboard staff-dashboard-page">
    @vite(['resources/css/dashboard.css'])
    @include('partials.staff-page-styles')

    @include('partials.staff-sidebar', ['activeStaffMenu' => 'dashboard'])

    <div class="main-area">
        @include('partials.staff-topbar', [
            'staffNavbarTitle' => 'Dashboard',
            'staffNavbarSubtitle' => 'Overview & Analytics',
        ])

        <main class="dashboard-content dashboard-home-content">

                @if(session('success'))
                    <div class="assignment-alert assignment-alert-success">{{ session('success') }}</div>
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

                <section class="staff-stats-grid">
                    <article class="staff-stat-card">
                        <div class="staff-stat-icon blue"><i class="fas fa-chart-column"></i></div>
                        <div class="staff-stat-copy">
                            <strong>{{ $totalAssignedProjects }}</strong>
                            <h3>Assigned Projects</h3>
                            <p>Active oversight</p>
                        </div>
                    </article>

                    <article class="staff-stat-card">
                        <div class="staff-stat-icon gold"><i class="fas fa-square-check"></i></div>
                        <div class="staff-stat-copy">
                            <strong>{{ $pendingBids }}</strong>
                            <h3>Bids to Review</h3>
                            <p>Pending validation</p>
                        </div>
                    </article>

                    <article class="staff-stat-card">
                        <div class="staff-stat-icon green"><i class="far fa-circle-check"></i></div>
                        <div class="staff-stat-copy">
                            <strong>{{ $openProjects }}</strong>
                            <h3>Open Projects</h3>
                            <p>Accepting bids</p>
                        </div>
                    </article>

                    <article class="staff-stat-card">
                        <div class="staff-stat-icon blue"><i class="fas fa-comments"></i></div>
                        <div class="staff-stat-copy">
                            <strong>Messages</strong>
                            <h3>Contact admin and bidders</h3>
                            <p><a href="{{ route('staff.messages') }}" class="staff-view-all">Open Messages</a></p>
                        </div>
                    </article>
                </section>

                <section class="staff-table-panel">
                    <div class="staff-table-header">
                        <h2>My Assigned Projects</h2>
                        <a href="{{ route('staff.assign-projects') }}" class="staff-view-all">View All</a>
                    </div>

                    <div class="staff-table-wrap">
                        <table class="staff-table">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Budget</th>
                                    <th>Deadline</th>
                                    <th>Bids</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignedProjects as $project)
                                    <tr>
                                        <td class="staff-project-title">{{ $project->title }}</td>
                                        <td>P{{ number_format((float) $project->budget, 2) }}</td>
                                        <td>{{ $project->deadline ? $project->deadline->format('Y-m-d') : 'N/A' }}</td>
                                        <td>{{ $project->bids_count }}</td>
                                        <td><span class="staff-status-pill {{ $project->status }}">{{ str($project->status)->replace('_', ' ') }}</span></td>
                                        <td><a href="{{ route('staff.assign-projects') }}" class="staff-action-button">Manage</a></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="staff-empty-cell">No assigned projects yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
        </main>
    </div>
</div>
