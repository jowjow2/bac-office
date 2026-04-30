<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard">
    @vite(['resources/css/dashboard.css'])

    <style>
        .projects-page,
        .projects-page * {
            font-family: 'Inter', sans-serif;
        }
    </style>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-logo-link"><h2 class="sidebar-logo">BAC-Office</h2></a>
        @include('partials.sidebar-profile')
        <ul class="sidebar-menu">

            <p class="menu-title">MAIN</p>
            <li><a href="/dashboard/admin"><span class="menu-icon-dashboard" aria-hidden="true"></span> Dashboard</a></li>
            <li><a href="{{ route('admin.projects') }}" class="active"><i class="fas fa-folder-open"></i> Project/Biddings</a></li>
            <li><a href="/admin/bidders"><span class="menu-icon-all-bids" aria-hidden="true"></span> All Bids</a></li>
            <li><a href="{{ route('admin.awards') }}"><i class="fas fa-trophy"></i> Awards & Contracts</a></li>

            <p class="menu-title">MANAGEMENT</p>
            <li><a href="{{ route('admin.users') }}"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <li><a href="{{ route('admin.assignments') }}"><i class="fas fa-tasks"></i> Staff Assignments</a></li>
            <li><a href="/admin/reports"><i class="fas fa-chart-bar"></i> Reports</a></li>

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

    <!-- MAIN AREA -->
    <div class="main-area projects-page">

        <!-- NAVBAR -->
        <header class="navbar">
            <div class="nav-left">
                <h2>Project/Biddings</h2>
                <p>Manage all projects and biddings</p>
            </div>
            <div class="nav-right">
            </div>
        </header>

        <!-- MAIN CONTENT -->
        <main class="dashboard-content">

            <div class="welcome-text" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 18px;">
                <div>
                    <h1 class="title" style="font-size: 24px; font-weight: 600; color: #111827; margin-bottom: 6px;">Projects / Biddings</h1>
                    <p class="subtitle" style="font-size: 14px;">
                        Manage all procurement projects
                    </p>
                </div>
                <button type="button" onclick="openProjectModal()" style="background: #1d4f91; color: white; padding: 11px 18px; border-radius: 8px; border: none; cursor: pointer; font-size: 13px; font-weight: 500;">
                    <i class="fas fa-plus" style="margin-right: 6px;"></i> New Project
                </button>
            </div>

            @if(session('success'))
            <div id="successAlert" style="position: fixed; top: 90px; right: 25px; background: #dcfce7; color: #166534; padding: 16px 20px; border-radius: 8px; font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; display: flex; align-items: center; gap: 10px; min-width: 280px;">
                <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                <span>{{ session('success') }}</span>
                <button onclick="closeSuccessAlert()" style="margin-left: auto; background: none; border: none; color: #166534; cursor: pointer; font-size: 16px;">&times;</button>
            </div>
            @endif

            <div class="table-container" style="background: white; border-radius: 18px; overflow: hidden; border: 1px solid #e5edf6; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);">
                <form method="GET" action="{{ route('admin.projects') }}" style="display: flex; gap: 12px; padding: 18px; border-bottom: 1px solid #edf2f7; background: #ffffff;">
                    <div style="flex: 1;">
                        <input
                            type="text"
                            name="search"
                            value="{{ $search ?? '' }}"
                            placeholder="Search projects..."
                            style="width: 100%; height: 42px; padding: 0 15px; border: 1px solid #d7e0ea; border-radius: 10px; font-size: 13px; color: #1f2937; outline: none;"
                        >
                    </div>
                    <div style="width: 150px;">
                        <select name="status" onchange="this.form.submit()" style="width: 100%; height: 42px; padding: 0 14px; border: 1px solid #d7e0ea; border-radius: 10px; font-size: 13px; color: #1f2937; background: white;">
                            <option value="">All Status</option>
                            <option value="approved_for_bidding" {{ ($status ?? '') === 'approved_for_bidding' ? 'selected' : '' }}>Approved for Bidding</option>
                            <option value="open" {{ ($status ?? '') === 'open' ? 'selected' : '' }}>Open</option>
                            <option value="closed" {{ ($status ?? '') === 'closed' ? 'selected' : '' }}>Closed</option>
                            <option value="awarded" {{ ($status ?? '') === 'awarded' ? 'selected' : '' }}>Awarded</option>
                        </select>
                    </div>
                </form>

                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid #e9eef5; background: #ffffff;">
                            <th style="text-align: left; padding: 14px 18px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Project Title</th>
                            <th style="text-align: left; padding: 14px 18px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Budget</th>
                            <th style="text-align: left; padding: 14px 18px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Deadline</th>
                            <th style="text-align: left; padding: 14px 18px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Staff</th>
                            <th style="text-align: left; padding: 14px 18px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Bids</th>
                            <th style="text-align: left; padding: 14px 18px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Status</th>
                            <th style="text-align: left; padding: 14px 18px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($projects as $project)
                        @php($projectStatusLabel = \Illuminate\Support\Str::headline($project->status))
                        <tr style="border-bottom: 1px solid #eef3f8;">
                            <td style="padding: 18px; font-size: 13px; vertical-align: top;">
                                <div style="font-size: 14px; font-weight: 600; color: #0f172a; margin-bottom: 6px;">{{ $project->title }}</div>
                                <div style="max-width: 320px; font-size: 12px; line-height: 1.5; color: #9ca3af;">
                                    {{ \Illuminate\Support\Str::limit($project->description, 72) }}
                                </div>
                            </td>
                            <td style="padding: 18px; font-size: 13px; font-weight: 500; color: #0f172a; vertical-align: top;">P{{ number_format((float) $project->budget, 2) }}</td>
                            <td style="padding: 18px; font-size: 13px; font-weight: 400; color: #334155; vertical-align: top;">{{ $project->deadline ? $project->deadline->format('Y-m-d') : 'N/A' }}</td>
                            <td style="padding: 18px; font-size: 13px; font-weight: 400; color: #0f172a; vertical-align: top;">{{ $project->assignments->first()?->staff?->name ?? 'Unassigned' }}</td>
                            <td style="padding: 18px; vertical-align: top;">
                                <span style="display: inline-flex; align-items: center; justify-content: center; min-width: 30px; height: 28px; padding: 0 10px; border-radius: 999px; background: #fff5db; color: #b7791f; font-size: 12px; font-weight: 600;">
                                    {{ $project->bids_count }}
                                </span>
                            </td>
                            <td style="padding: 18px; vertical-align: top;">
                                <span style="display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 500;
                                    @if($project->status == 'approved_for_bidding') background: #dbeafe; color: #1d4ed8;
                                    @elseif($project->status == 'open') background: #dcfce7; color: #166534;
                                    @elseif($project->status == 'awarded') background: #fef3c7; color: #b45309;
                                    @elseif($project->status == 'closed') background: #e5e7eb; color: #475569;
                                    @else background: #fef3c7; color: #92400e; @endif">
                                    {{ $projectStatusLabel }}
                                </span>
                            </td>
                            <td style="padding: 18px; vertical-align: top;">
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <button onclick="loadViewModal({{ $project->id }})" style="background: white; color: #334155; border: 1px solid #d6deea; padding: 8px 14px; border-radius: 9px; text-decoration: none; font-size: 12px; font-weight: 500; display: inline-block; cursor: pointer;">View</button>
                                <button onclick="loadEditModal({{ $project->id }})" style="background: white; color: #334155; border: 1px solid #d6deea; padding: 8px 14px; border-radius: 9px; text-decoration: none; font-size: 12px; font-weight: 500; display: inline-block; cursor: pointer;">Edit</button>
                                <form action="{{ route('admin.project.destroy', $project) }}" method="POST" style="display: inline;" onsubmit="return confirm('Delete this project? This will also remove its bids, awards, and staff assignments.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="background: #dc2626; color: white; padding: 8px 14px; border-radius: 9px; border: none; font-size: 12px; font-weight: 500; display: inline-block; cursor: pointer;">Delete</button>
                                </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" style="padding: 40px; text-align: center; color: #9ca3af;">
                                <i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                                No projects found for this search/filter.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </main>
        </div>

</div>

<!-- VIEW PROJECT MODAL -->
<div id="viewProjectModal" style="display: none; position: fixed; inset: 0; padding: 20px; background: rgba(15, 23, 42, 0.45); z-index: 10000; justify-content: center; align-items: center; box-sizing: border-box;">
    <div style="background: white; border-radius: 14px; width: min(680px, 100%); max-height: calc(100vh - 20px); overflow-y: auto; overflow-x: hidden; position: relative; box-shadow: 0 20px 44px rgba(15, 23, 42, 0.16); box-sizing: border-box;">
        <button onclick="closeViewModal()" style="position: absolute; top: 16px; right: 16px; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 9px; font-size: 18px; line-height: 1; cursor: pointer; color: #7c8ba1; z-index: 2;">&times;</button>
        <div id="viewModalBody">
        </div>
    </div>
</div>

<!-- EDIT PROJECT MODAL -->
<div id="editProjectModal" style="display: none; position: fixed; inset: 0; padding: 20px; background: rgba(15, 23, 42, 0.45); z-index: 10001; justify-content: center; align-items: center; box-sizing: border-box;">
    <div style="background: white; border-radius: 14px; width: min(680px, 100%); max-height: calc(100vh - 20px); overflow-y: auto; overflow-x: hidden; position: relative; box-shadow: 0 20px 44px rgba(15, 23, 42, 0.16); box-sizing: border-box;">
        <button onclick="closeEditModal()" style="position: absolute; top: 16px; right: 16px; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 9px; font-size: 18px; line-height: 1; cursor: pointer; color: #7c8ba1; z-index: 2;">&times;</button>
        <div id="editModalBody">
        </div>
    </div>
</div>

<!-- DECLARE AWARD MODAL -->
<div id="declareWinnerModal" style="display: none; position: fixed; inset: 0; padding: 20px; background: rgba(15, 23, 42, 0.45); z-index: 10002; justify-content: center; align-items: center; box-sizing: border-box;">
    <div style="background: white; border-radius: 14px; width: min(690px, 100%); max-height: calc(100vh - 20px); overflow-y: auto; overflow-x: hidden; position: relative; box-shadow: 0 20px 44px rgba(15, 23, 42, 0.16); box-sizing: border-box;">
        <button onclick="closeDeclareWinnerModal()" style="position: absolute; top: 16px; right: 16px; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 9px; font-size: 18px; line-height: 1; cursor: pointer; color: #7c8ba1; z-index: 2;">&times;</button>
        <div id="declareWinnerModalBody">
        </div>
    </div>
</div>

<!-- CREATE PROJECT MODAL -->
<div id="projectModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
    <div style="background: white; border-radius: 12px; padding: 22px; width: 90%; max-width: 470px; max-height: 86vh; overflow-y: auto; position: relative;">

        <button onclick="closeProjectModal()" style="position: absolute; top: 12px; right: 12px; background: none; border: none; font-size: 18px; cursor: pointer; color: #64748b;">&times;</button>

        <h2 style="font-size: 16px; font-weight: 600; margin-bottom: 4px;">Create New Project</h2>
        <p style="color: #64748b; font-size: 12px; margin-bottom: 14px;">Fill in the details below to create a new procurement project.</p>

        @if($errors->any())
        <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
            <ul style="margin: 0; padding-left: 20px;">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form action="{{ route('admin.projects.store') }}" method="POST">
            @csrf

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 5px;">Project Title</label>
                <input type="text" name="title" value="{{ old('title') }}" required style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px;">
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 5px;">Description</label>
                <textarea name="description" rows="3" required style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; resize: vertical;">{{ old('description') }}</textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 5px;">Category</label>
                    <input type="text" name="category" value="{{ old('category') }}" required style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px;">
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 5px;">Budget (PHP)</label>
                    <input type="number" name="budget" value="{{ old('budget') }}" required min="0" max="9999999999999.99" step="0.01" style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 18px;">
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 5px;">Status</label>
                    <select name="status" required style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; background: white;">
                        <option value="approved_for_bidding" {{ old('status', 'approved_for_bidding') == 'approved_for_bidding' ? 'selected' : '' }}>Approved for Bidding</option>
                        <option value="open" {{ old('status') == 'open' ? 'selected' : '' }}>Open</option>
                        <option value="closed" {{ old('status') == 'closed' ? 'selected' : '' }}>Closed</option>
                        <option value="awarded" {{ old('status') == 'awarded' ? 'selected' : '' }}>Awarded</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 5px;">Deadline</label>
                    <input type="date" name="deadline" value="{{ old('deadline') }}" required style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px;">
                </div>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeProjectModal()" style="padding: 8px 16px; border: 1px solid #d1d5db; border-radius: 8px; color: #374151; background: white; cursor: pointer; font-size: 13px;">Cancel</button>
                <button type="submit" style="padding: 8px 16px; background: #1a3cff; color: white; border: none; border-radius: 8px; font-size: 13px; cursor: pointer;">
                    <i class="fas fa-save"></i> Create Project
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openProjectModal() {
        document.getElementById('projectModal').style.display = 'flex';
    }

    function closeProjectModal() {
        document.getElementById('projectModal').style.display = 'none';
    }

    document.getElementById('projectModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeProjectModal();
        }
    });

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

    function attachEditFormHandler() {
        const form = document.querySelector('#editModalBody form');
        if (form) {
            form.onsubmit = function(e) {
                e.preventDefault();
                const submitBtn = document.getElementById('editSubmitBtn');
                if (!submitBtn) return;

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

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
                        showTempMessage('Project updated successfully!', 'success');
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
                    submitBtn.innerHTML = 'Save Changes';
                });
            };
        }
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
        const hasFieldErrors = Object.keys(errors).length > 0;

        if (alertBox) {
            if (message && !hasFieldErrors) {
                alertBox.textContent = message;
                alertBox.style.display = 'block';
            } else {
                alertBox.style.display = 'none';
                alertBox.textContent = '';
            }
        }

        Object.entries(errors).forEach(([field, messages]) => {
            const input = document.querySelector(`#editModalBody [name="${field}"]`);
            const errorEl = document.querySelector(`#editModalBody [data-error-for="${field}"]`);
            const text = Array.isArray(messages) ? messages[0] : messages;

            if (input) {
                input.classList.add('input-error');
            }

            if (errorEl) {
                errorEl.textContent = text;
            }
        });
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
            if (message && !hasFieldErrors) {
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

    function showTempMessage(message, type = 'success') {
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

    document.getElementById('editProjectModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });

    document.getElementById('declareWinnerModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeclareWinnerModal();
    });
</script>
