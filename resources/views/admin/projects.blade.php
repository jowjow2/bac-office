<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard">
    @vite(['resources/css/dashboard.css'])

    @include('partials.admin-sidebar')

    <!-- MAIN AREA -->
    <div class="main-area projects-page">

        <!-- PAGE HEADER -->
        <header class="page-header" style="padding: 24px; border-bottom: 1px solid #e5e7eb; background: #ffffff; margin-bottom: 0;">
            <div style="max-width: 1400px; margin: 0 auto;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                    <div>
                        <div class="projects-title-row">
                            <button type="button" class="dashboard-menu-toggle projects-menu-toggle" data-dashboard-sidebar-toggle aria-controls="dashboardSidebar" aria-expanded="false" aria-label="Open navigation menu">
                                <i class="fas fa-bars" aria-hidden="true"></i>
                            </button>
                            <h1 style="font-size: 24px; font-weight: 700; color: #111827; margin: 0;">Project/Biddings</h1>
                        </div>
                        <p style="font-size: 14px; color: #6b7280; margin: 4px 0 0;">Manage all projects and biddings</p>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <a href="{{ route('admin.projects', ['status' => 'draft']) }}" style="height: 42px; padding: 0 16px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; border: 1px solid {{ ($status ?? '') === 'draft' ? '#f59e0b' : '#e5e7eb' }}; background: {{ ($status ?? '') === 'draft' ? '#fff7ed' : '#ffffff' }}; color: {{ ($status ?? '') === 'draft' ? '#c2410c' : '#374151' }}; transition: all 0.2s;">
                            <i class="fas fa-file-alt"></i>
                            Drafts
                            <span style="min-width: 22px; height: 22px; padding: 0 7px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; background: {{ ($status ?? '') === 'draft' ? '#fed7aa' : '#f3f4f6' }}; color: {{ ($status ?? '') === 'draft' ? '#9a3412' : '#6b7280' }}; font-size: 11px; font-weight: 800;">{{ $draftProjectsCount ?? 0 }}</span>
                        </a>
                        <a href="{{ route('admin.projects.create') }}" style="height: 42px; padding: 0 18px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; border: 1px solid #111827; background: #111827; color: #ffffff; box-shadow: 0 8px 18px rgba(15, 23, 42, 0.14); transition: background 0.2s;" onmouseover="this.style.background='#374151'; this.style.borderColor='#374151';" onmouseout="this.style.background='#111827'; this.style.borderColor='#111827';">
                            <i class="fas fa-plus"></i>
                            Create Project
                        </a>
                    </div>
                </div>

                <!-- Filters row -->
                <form method="GET" action="{{ route('admin.projects') }}" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin-top: 16px;">
                    <div style="flex: 1; min-width: 200px;">
                        <input
                            type="text"
                            name="search"
                            value="{{ $search ?? '' }}"
                            placeholder="Search projects by title, description..."
                            style="width: 100%; height: 42px; padding: 0 15px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 13px; color: #111827; outline: none; background: #ffffff; transition: border-color 0.2s, box-shadow 0.2s;"
                            onfocus="this.style.borderColor='#f59e0b'; this.style.boxShadow='0 0 0 3px rgba(245,158,11,0.1)';"
                            onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none';"
                        >
                    </div>
                    <div style="width: 180px; position: relative; display: inline-block;">
                        <select name="status" onchange="this.form.submit()" style="width: 100%; height: 42px; padding: 0 14px; padding-right: 36px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 13px; color: #111827; background: #ffffff; cursor: pointer; outline: none; appearance: none; transition: all 0.2s ease;" 
                            onfocus="this.style.borderColor='#f59e0b'; this.style.boxShadow='0 0 0 3px rgba(245,158,11,0.1)';" 
                            onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none';">
                            <option value="">All Status</option>
                            <option value="draft" {{ ($status ?? '') === 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="approved_for_bidding" {{ ($status ?? '') === 'approved_for_bidding' ? 'selected' : '' }}>Approved for Bidding</option>
                            <option value="open" {{ ($status ?? '') === 'open' ? 'selected' : '' }}>Open</option>
                            <option value="closed" {{ ($status ?? '') === 'closed' ? 'selected' : '' }}>Closed</option>
                            <option value="awarded" {{ ($status ?? '') === 'awarded' ? 'selected' : '' }}>Awarded</option>
                        </select>
                        <div style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; display: flex; align-items: center; justify-content: center; width: 20px; height: 20px;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="width: 100%; height: 100%;">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </div>
                        <style>
                            select[name="status"]:hover {
                                border-color: #f59e0b;
                            }
                        </style>
                    </div>
                    <button type="submit" style="height: 42px; padding: 0 20px; background: #111827; color: white; border: none; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap; transition: background 0.2s;" onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#111827'">
                        <i class="fas fa-search" style="margin-right: 6px;"></i> Search
                    </button>
                </form>
            </div>
        </header>

        <!-- MAIN CONTENT -->
        <main class="dashboard-content" style="padding: 24px;">

            @if(session('success'))
            <div id="successAlert" style="position: fixed; top: 90px; right: 25px; background: #dcfce7; color: #166534; padding: 16px 20px; border-radius: 10px; font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; display: flex; align-items: center; gap: 10px; min-width: 280px; border: 1px solid #bbf7d0;">
                <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                <span>{{ session('success') }}</span>
                <button onclick="closeSuccessAlert()" style="margin-left: auto; background: none; border: none; color: #166534; cursor: pointer; font-size: 18px; padding: 0; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;">&times;</button>
            </div>
            @endif

            <!-- PROJECTS CARD -->
            <div class="content-card" style="background: #ffffff; border-radius: 16px; border: 1px solid #e5e7eb; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06); overflow: hidden;">

                @if($projects->count() > 0)
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                            <th style="text-align: left; padding: 14px 20px; font-size: 11px; color: #6b7280; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;">Project Title</th>
                            <th style="text-align: left; padding: 14px 20px; font-size: 11px; color: #6b7280; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;">Budget</th>
                            <th style="text-align: left; padding: 14px 20px; font-size: 11px; color: #6b7280; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;">Deadline</th>
                            <th style="text-align: left; padding: 14px 20px; font-size: 11px; color: #6b7280; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;">Staff</th>
                            <th style="text-align: left; padding: 14px 20px; font-size: 11px; color: #6b7280; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;">Bids</th>
                            <th style="text-align: left; padding: 14px 20px; font-size: 11px; color: #6b7280; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;">Status</th>
                            <th style="text-align: left; padding: 14px 20px; font-size: 11px; color: #6b7280; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($projects as $project)
                        @php($projectStatusLabel = \Illuminate\Support\Str::headline($project->status))
                        @php($projectDocuments = $project->uploadedDocuments())
                        <tr style="border-bottom: 1px solid #e5e7eb; transition: background 0.15s;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='#ffffff'">
                            <td data-label="Project Title" style="padding: 18px 20px; font-size: 13px; vertical-align: top;">
                                <div style="font-size: 14px; font-weight: 600; color: #111827; margin-bottom: 6px; line-height: 1.4;">{{ $project->title }}</div>
                                <div style="max-width: 400px; font-size: 12px; line-height: 1.5; color: #6b7280;">
                                    {{ \Illuminate\Support\Str::limit($project->description, 80) }}
                                </div>
                                @if($projectDocuments->isNotEmpty())
                                <div style="margin-top: 10px;" data-project-files-wrap="{{ $project->id }}">
                                    <button type="button" data-project-files-trigger="{{ $project->id }}" onclick="loadProjectFilesModal({{ $project->id }})" style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; border: 1px solid #e5e7eb; border-radius: 999px; background: #f9fafb; color: #f59e0b; font-size: 11px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                                        <i class="fas fa-paperclip" style="font-size: 9px;"></i>
                                        {{ $projectDocuments->count() }} {{ \Illuminate\Support\Str::plural('file', $projectDocuments->count()) }} attached
                                    </button>
                                </div>
                                @endif
                            </td>
                            <td data-label="Budget" style="padding: 18px 20px; font-size: 13px; font-weight: 600; color: #111827; vertical-align: top;">P{{ number_format((float) $project->budget, 2) }}</td>
                            <td data-label="Deadline" style="padding: 18px 20px; font-size: 13px; font-weight: 400; color: #374151; vertical-align: top;">{{ $project->deadline ? $project->deadline->format('M d, Y') : 'N/A' }}</td>
                            <td data-label="Staff" style="padding: 18px 20px; font-size: 13px; font-weight: 400; color: #111827; vertical-align: top;">{{ $project->assignments->first()?->staff?->name ?? '<span style=\"color:#9ca3af\">Unassigned</span>' }}</td>
                            <td data-label="Bids" style="padding: 18px 20px; vertical-align: top;">
                                <span style="display: inline-flex; align-items: center; justify-content: center; min-width: 28px; height: 26px; padding: 0 10px; border-radius: 999px; background: #fef3c7; color: #b45309; font-size: 11px; font-weight: 700;">
                                    {{ $project->bids_count }}
                                </span>
                            </td>
                            <td data-label="Status" style="padding: 18px 20px; vertical-align: top;">
                                <span style="display: inline-flex; align-items: center; padding: 5px 12px; border-radius: 999px; font-size: 11px; font-weight: 600;
                                    @if($project->status == 'draft') background: #fef3c7; color: #92400e;
                                    @elseif($project->status == 'approved_for_bidding') background: #dbeafe; color: #1d4ed8;
                                    @elseif($project->status == 'open') background: #dcfce7; color: #166534;
                                    @elseif($project->status == 'awarded') background: #fef3c7; color: #b45309;
                                    @elseif($project->status == 'closed') background: #f3f4f6; color: #6b7280;
                                    @else background: #fef3c7; color: #92400e; @endif">
                                    {{ $projectStatusLabel }}
                                </span>
                            </td>
                            <td data-label="Actions" style="padding: 18px 20px; vertical-align: top;">
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <button onclick="loadViewModal({{ $project->id }})" class="action-btn view" style="background: #f9fafb; color: #374151; border: 1px solid #e5e7eb; padding: 7px 13px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 500; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'; this.style.borderColor='#d1d5db'" onmouseout="this.style.background='#f9fafb'; this.style.borderColor='#e5e7eb'">View</button>
                                    <button onclick="loadEditModal({{ $project->id }})" class="action-btn edit" style="background: #f9fafb; color: #374151; border: 1px solid #e5e7eb; padding: 7px 13px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 500; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'; this.style.borderColor='#d1d5db'" onmouseout="this.style.background='#f9fafb'; this.style.borderColor='#e5e7eb'">Edit</button>
                                    @if($project->status === 'draft')
                                    <button onclick="publishDraft({{ $project->id }})" class="action-btn publish" style="background: #dbeafe; color: #1d4ed8; border: 1px solid #93c5fd; padding: 7px 13px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 500; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#bfdbfe'; this.style.borderColor='#60a5fa'" onmouseout="this.style.background='#dbeafe'; this.style.borderColor='#93c5fd'">
                                        <i class="fas fa-paper-plane" style="margin-right: 4px;"></i> Publish
                                    </button>
                                    @endif
                                    <form action="{{ route('admin.project.destroy', $project) }}" method="POST" style="display: inline;" onsubmit="return confirm('Delete this project? This will also remove its bids, awards, and staff assignments.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="action-btn delete" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 7px 13px; border-radius: 8px; border: none; font-size: 12px; font-weight: 500; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#fee2e2'; this.style.borderColor='#fca5a5'" onmouseout="this.style.background='#fef2f2'; this.style.borderColor='#fecaca'">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" style="padding: 60px 20px; text-align: center; color: #9ca3af; background: #fafafa;">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 16px;">
                                    <i class="fas fa-folder-open" style="font-size: 56px; color: #d1d5db;"></i>
                                    <div>
                                        <p style="font-size: 15px; font-weight: 500; color: #6b7280; margin: 0 0 4px;">No projects found</p>
                                        <p style="font-size: 13px; color: #9ca3af; margin: 0;">Try adjusting your search or filter criteria</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                @else
                <!-- Empty State -->
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 80px 24px; text-align: center; background: #fafafa;">
                    <div style="width: 72px; height: 72px; background: #fef3c7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                        <i class="fas fa-folder-open" style="font-size: 32px; color: #f59e0b;"></i>
                    </div>
                    <h3 style="font-size: 16px; font-weight: 600; color: #111827; margin: 0 0 6px;">No projects yet</h3>
                    <p style="font-size: 13px; color: #6b7280; margin: 0 0 20px; max-width: 320px; line-height: 1.5;">Get started by creating your first procurement project.</p>
                    <a href="{{ route('admin.projects.create') }}" class="btn-primary" style="background: #111827; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background 0.2s;" onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#111827'">
                        <i class="fas fa-plus"></i> Create Project
                    </a>
                </div>
                @endif

            </div>

            <!-- PAGINATION (if needed) -->
            @if(isset($projects) && $projects->hasPages())
            <div style="margin-top: 20px; display: flex; justify-content: center;">
                {{ $projects->links() }}
            </div>
            @endif

        </main>
    </div>

</div>

<!-- PROJECT FILES MODAL -->
<div id="projectFilesModal" style="display: none; position: fixed; inset: 0; padding: 20px; background: rgba(15, 23, 42, 0.45); z-index: 10000; justify-content: center; align-items: center; box-sizing: border-box;">
    <div style="background: white; border-radius: 14px; width: min(680px, 100%); max-height: calc(100vh - 20px); overflow-y: auto; overflow-x: hidden; position: relative; box-shadow: 0 20px 44px rgba(15, 23, 42, 0.16); box-sizing: border-box;">
        <button onclick="closeProjectFilesModal()" style="position: absolute; top: 16px; right: 16px; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 9px; font-size: 18px; line-height: 1; cursor: pointer; color: #7c8ba1; z-index: 2;">&times;</button>
        <div id="projectFilesModalBody">
        </div>
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

        <form action="{{ route('admin.projects.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 5px;">Project Title</label>
                <input type="text" name="title" value="{{ old('title') }}" required style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px;">
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 5px;">Description</label>
                <textarea name="description" rows="3" required style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; resize: vertical;">{{ old('description') }}</textarea>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 5px;">Upload Files</label>
                <input type="file" name="document_files[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; background: white;">
                <div style="margin-top: 5px; font-size: 11px; color: #6b7280;">You can upload multiple PDF, DOC, DOCX, JPG, JPEG, or PNG files. Limit is per file at 20MB.</div>
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

            <div style="display: grid; grid-template-columns: 1fr; gap: 14px; margin-bottom: 18px;">
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 5px;">Deadline</label>
                    <input type="date" name="deadline" value="{{ old('deadline') }}" required style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px;">
                </div>
            </div>

            <input type="hidden" name="status" id="projectModalStatus" value="draft">

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeProjectModal()" style="padding: 8px 16px; border: 1px solid #d1d5db; border-radius: 8px; color: #374151; background: white; cursor: pointer; font-size: 13px;">Cancel</button>
                <button type="button" onclick="document.getElementById('projectModalStatus').value='draft'; this.form.submit();" style="padding: 8px 16px; border: 1px solid #d1d5db; border-radius: 8px; color: #374151; background: white; cursor: pointer; font-size: 13px;">
                    <i class="fas fa-file-alt"></i> Save as Draft
                </button>
                <button type="button" onclick="document.getElementById('projectModalStatus').value='open'; this.form.submit();" style="padding: 8px 16px; background: #1a3cff; color: white; border: none; border-radius: 8px; font-size: 13px; cursor: pointer;">
                    <i class="fas fa-paper-plane"></i> Publish Project
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

        @if($errors->any())
            openProjectModal();
        @endif
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
                        syncProjectFilesTrigger(currentProjectId, result.data.remaining_count);
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

    function syncProjectFilesTrigger(projectId, remainingCount) {
        const wrap = document.querySelector(`[data-project-files-wrap="${projectId}"]`);
        const trigger = document.querySelector(`[data-project-files-trigger="${projectId}"]`);

        if (!wrap || !trigger) return;

        if (remainingCount <= 0) {
            wrap.remove();
            return;
        }

        const label = `${remainingCount} ${remainingCount === 1 ? 'file' : 'files'} attached`;
        trigger.innerHTML = `<i class="fas fa-paperclip" style="font-size: 10px;"></i> ${label}`;
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
            const normalizedField = field.replace(/\.\d+$/, '');
            const input = document.querySelector(`#editModalBody [name="${field}"]`)
                || document.querySelector(`#editModalBody [name="${normalizedField}"]`)
                || document.querySelector(`#editModalBody [name="${normalizedField}[]"]`);
            const errorEl = document.querySelector(`#editModalBody [data-error-for="${field}"]`)
                || document.querySelector(`#editModalBody [data-error-for="${normalizedField}"]`);
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

    document.getElementById('projectFilesModal').addEventListener('click', function(e) {
        if (e.target === this) closeProjectFilesModal();
    });

    document.getElementById('editProjectModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });

    document.getElementById('declareWinnerModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeclareWinnerModal();
    });

    function publishDraft(projectId) {
        if (!confirm('Publish this draft project? It will become available for bidding.')) {
            return;
        }

        const button = event.target.closest('button');
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
            const data = await response.json();
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

</script>
