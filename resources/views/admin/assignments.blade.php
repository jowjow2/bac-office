<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard admin-role-page">
    @vite(['resources/css/dashboard.css'])

     <style>
         .assignments-ui {
             font-family: var(--ui-font);
         }

         .assignments-ui .fas,
         .assignments-ui .far,
         .assignments-ui .fab,
         .assignments-ui .fa-solid,
         .assignments-ui .fa-regular,
         .assignments-ui .fa-brands {
             font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands" !important;
         }

         .assignments-ui .fas,
         .assignments-ui .far,
         .assignments-ui .fa-solid,
         .assignments-ui .fa-regular {
             font-weight: 700 !important;
         }

         .assignments-ui {
             font-size: 13px;
         }

        .assignments-ui .title {
            font-size: 24px;
            font-weight: 600;
            color: var(--ui-ink);
            margin-bottom: 6px;
        }

        .assignments-ui .subtitle,
        .assignments-ui .assignment-staff-head p,
        .assignments-ui .assignment-modal-header p {
            font-size: 14px;
            color: var(--ui-muted);
        }

        .assignments-ui .assignment-staff-head h2,
        .assignments-ui .assignment-modal-header h3 {
            font-size: 14px;
            font-weight: 600;
            color: var(--ui-ink);
        }

        .assignments-ui .report-date,
        .assignments-ui .assignment-chip-title,
        .assignments-ui .field-group label,
        .assignments-ui .form-select,
        .assignments-ui .form-input {
            font-size: 13px;
        }

        .assignments-ui .field-group label {
            letter-spacing: normal;
            text-transform: none;
            color: var(--ui-muted);
            font-size: 12px;
            font-weight: 600;
        }

        .assignments-ui .status-pill {
            font-size: 11px;
            font-weight: 500;
            border-radius: 999px;
        }

        .assignments-ui .assignment-chip-remove,
        .assignments-ui .btn-secondary,
        .assignments-ui .btn-primary {
            font-size: 12px;
        }

        .assignments-ui .btn-primary {
            font-weight: 600;
        }

        .assignments-ui .assignment-open-btn,
        .assignments-ui .assignment-modal-actions .btn-primary {
            background: #f59e0b;
            border: 1px solid #f59e0b;
            color: #ffffff;
        }

        .assignments-ui .assignment-open-btn:hover,
        .assignments-ui .assignment-modal-actions .btn-primary:hover {
            background: #d97706;
            border-color: #d97706;
        }

        .assignments-ui .btn-secondary,
        .assignments-ui .assignment-chip-remove {
            font-weight: 500;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-staff-head {
            align-items: center !important;
            padding: 20px 24px !important;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-staff-head h2 {
            margin: 0 0 5px !important;
            color: #0f2b57 !important;
            font-size: 17px !important;
            font-weight: 600 !important;
            line-height: 1.3 !important;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-staff-head p {
            margin: 0 !important;
            color: var(--ui-muted) !important;
            font-size: 13px !important;
            line-height: 1.45 !important;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-project-strip {
            min-height: 0 !important;
            padding: 16px 24px 18px !important;
            align-items: center !important;
            background: var(--ui-surface-2) !important;
            border-top-color: var(--ui-line) !important;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-chip {
            min-height: 42px !important;
            padding: 7px 10px 7px 14px !important;
            border-color: var(--ui-line-strong) !important;
            border-radius: 10px !important;
            background: #ffffff !important;
            box-shadow: 0 2px 6px rgba(27, 36, 32, 0.04) !important;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-chip-title {
            color: var(--ui-primary-hover) !important;
            font-size: 13px !important;
            font-weight: 500 !important;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-chip form {
            margin: 0 !important;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-chip-remove {
            width: 32px !important;
            height: 32px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0 !important;
            border: 1px solid var(--ui-line-strong) !important;
            border-radius: 999px !important;
            background: var(--ui-surface-2) !important;
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
            font-size: 11px !important;
            line-height: 1 !important;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-chip-remove:hover,
        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-chip-remove:focus-visible {
            border-color: var(--ui-subtle) !important;
            background: var(--ui-line) !important;
            color: var(--ui-ink-2) !important;
            -webkit-text-fill-color: var(--ui-ink-2) !important;
        }

        @media (max-width: 720px) {
            body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-staff-head {
                align-items: stretch !important;
                flex-direction: column !important;
                gap: 14px !important;
            }

            body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-open-btn {
                width: 100% !important;
            }

            body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-project-strip {
                padding: 14px 16px 16px !important;
            }

            body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-chip {
                width: 100% !important;
                justify-content: space-between !important;
            }
        }

        .assignments-ui .empty-state {
            font-size: 13px;
            color: var(--ui-subtle);
        }

        .assignments-ui #assignmentSuccessAlert {
            position: fixed;
            top: 92px;
            right: 28px;
            z-index: 1100;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 280px;
            padding: 14px 18px;
            border-radius: var(--ui-radius-lg);
            background: #dcfce7;
            color: #166534;
            box-shadow: 0 12px 28px rgba(27, 36, 32, 0.12);
            opacity: 1;
            transition: opacity 0.4s ease, transform 0.4s ease;
        }

        .assignments-ui #assignmentSuccessAlert.fade-out {
            opacity: 0;
            transform: translateY(-10px);
        }

        /* assignment-modal-final-fix */
        .assignments-ui .assignment-modal {
            padding: 24px !important;
            background: rgba(27, 36, 32, 0.58) !important;
            backdrop-filter: blur(6px) !important;
            -webkit-backdrop-filter: blur(6px) !important;
            z-index: 1200 !important;
        }

        .assignments-ui .assignment-modal.show {
            display: flex !important;
        }

        .assignments-ui .assignment-modal-backdrop {
            position: absolute !important;
            inset: 0 !important;
            background: transparent !important;
        }

        .assignments-ui .assignment-modal-dialog {
            width: min(560px, calc(100vw - 32px)) !important;
            max-width: 560px !important;
            max-height: calc(100dvh - 48px) !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
            border: 1px solid var(--ui-line) !important;
            border-radius: var(--ui-radius-lg) !important;
            background: #ffffff !important;
            color: var(--ui-ink) !important;
            box-shadow: 0 24px 60px rgba(27, 36, 32, 0.26) !important;
        }

        .assignments-ui .assignment-modal-header {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 16px !important;
            min-height: 92px !important;
            margin: 0 !important;
            padding: 20px 22px !important;
            border-bottom: 1px solid var(--ui-line) !important;
            background: #ffffff !important;
        }

        .assignments-ui .assignment-modal-header > div {
            min-width: 0 !important;
        }

        .assignments-ui .assignment-modal-header h3 {
            margin: 0 0 6px !important;
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
            font-size: 18px !important;
            font-weight: 700 !important;
            line-height: 1.2 !important;
        }

        .assignments-ui .assignment-modal-header p {
            margin: 0 !important;
            overflow: hidden !important;
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
            font-size: 13px !important;
            line-height: 1.4 !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }

        .assignments-ui .assignment-modal-close {
            width: 38px !important;
            height: 38px !important;
            flex: 0 0 38px !important;
            border: 1px solid var(--ui-line-strong) !important;
            border-radius: var(--ui-radius-lg) !important;
            background: #ffffff !important;
            color: var(--ui-ink-2) !important;
            -webkit-text-fill-color: var(--ui-ink-2) !important;
        }

        .assignments-ui .assignment-modal-close:hover,
        .assignments-ui .assignment-modal-close:focus-visible {
            background: var(--ui-surface-2) !important;
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
        }

        .assignments-ui .assignment-modal-form {
            padding: 24px 26px 26px !important;
            background: #ffffff !important;
            color: var(--ui-ink) !important;
        }

        .assignments-ui .assignment-modal-form .field-group label {
            margin-bottom: 10px !important;
            color: var(--ui-ink-2) !important;
            -webkit-text-fill-color: var(--ui-ink-2) !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            letter-spacing: 0.04em !important;
        }

        .assignments-ui .assignment-modal-form .form-select {
            width: 100% !important;
            min-height: 36px !important;
            padding: 0 16px !important;
            border: 1px solid var(--ui-line-strong) !important;
            border-radius: var(--ui-radius) !important;
            background: #ffffff !important;
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
            font-size: 13px !important;
        }

        .assignments-ui .assignment-modal-actions {
            display: flex !important;
            justify-content: flex-end !important;
            gap: 12px !important;
            margin-top: 16px !important;
            padding-top: 20px !important;
            border-top: 1px solid var(--ui-line) !important;
            background: #ffffff !important;
        }

        .assignments-ui .assignment-modal-actions .btn-secondary,
        .assignments-ui .assignment-modal-actions .btn-primary {
            min-width: 96px !important;
            height: 36px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0 18px !important;
            border-radius: var(--ui-radius) !important;
            font-size: 12.5px !important;
            font-weight: 600 !important;
            line-height: 1 !important;
        }

        .assignments-ui .assignment-modal-actions .btn-secondary {
            border: 1px solid var(--ui-line-strong) !important;
            background: #ffffff !important;
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
        }

        .assignments-ui .assignment-modal-actions .btn-primary {
            border: 1px solid #0ea5e9 !important;
            background: #0ea5e9 !important;
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
        }

        .assignments-ui .assignment-modal-actions .btn-primary:hover,
        .assignments-ui .assignment-modal-actions .btn-primary:focus-visible {
            border-color: var(--ui-info) !important;
            background: var(--ui-info) !important;
        }

        /* Staff assignment workspace */
        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignments-page {
            background: #f5f7f6;
        }

        .assignments-ui .assignment-overview {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 20px;
            padding: 22px 26px;
            border: 1px solid #dce6e1;
            border-radius: 14px;
            background: #fff;
        }

        .assignments-ui .assignment-overview-eyebrow,
        .assignments-ui .assignment-card-label {
            margin: 0 0 6px;
            color: #4c6c60;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .assignments-ui .assignment-overview h2 {
            margin: 0 0 4px;
            color: #173b31;
            font-size: 21px;
            line-height: 1.25;
        }

        .assignments-ui .assignment-overview p:last-child {
            margin: 0;
            color: #62736b;
            font-size: 13px;
        }

        .assignments-ui .assignment-overview-stats {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }

        .assignments-ui .assignment-overview-stat {
            min-width: 120px;
            padding: 11px 16px;
            border: 1px solid #dce6e1;
            border-radius: 10px;
            background: #f8faf9;
        }

        .assignments-ui .assignment-overview-stat strong {
            display: block;
            color: #173b31;
            font-size: 22px;
            line-height: 1.1;
        }

        .assignments-ui .assignment-overview-stat span {
            display: block;
            margin-top: 4px;
            color: #66776e;
            font-size: 11px;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-staff-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            align-items: start;
            gap: 18px;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-staff-card {
            min-width: 0;
            border: 1px solid #dce6e1 !important;
            border-radius: 14px !important;
            box-shadow: 0 6px 20px rgba(21, 57, 45, .04) !important;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-staff-head {
            gap: 14px !important;
            padding: 21px 22px 18px !important;
        }

        .assignments-ui .assignment-staff-identity {
            display: flex;
            align-items: center;
            gap: 13px;
            min-width: 0;
        }

        .assignments-ui .assignment-avatar {
            display: grid;
            place-items: center;
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            border-radius: 12px;
            background: #e5f1eb;
            color: #1d5946;
            font-size: 16px;
            font-weight: 800;
        }

        .assignments-ui .assignment-staff-details {
            min-width: 0;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-staff-head h2 {
            margin-bottom: 3px !important;
            color: #19382e !important;
            font-size: 17px !important;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-staff-head p {
            overflow-wrap: anywhere;
            color: #66776e !important;
            font-size: 12px !important;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-open-btn {
            flex: 0 0 auto;
            min-height: 38px;
            padding: 8px 13px !important;
            border-radius: 8px !important;
            font-size: 12px !important;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-project-strip {
            display: block !important;
            padding: 17px 22px 20px !important;
            background: #fff !important;
        }

        .assignments-ui .assignment-card-label {
            margin-bottom: 11px;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-chip {
            display: flex !important;
            width: 100% !important;
            min-height: 66px !important;
            justify-content: space-between !important;
            gap: 14px !important;
            margin-top: 8px;
            padding: 11px 12px !important;
            border-color: #e0e8e3 !important;
            border-radius: 9px !important;
            background: #f9fbfa !important;
            box-shadow: none !important;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-chip-main {
            display: flex;
            min-width: 0;
            flex: 1;
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-chip-title {
            color: #203d32 !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            line-height: 1.35;
        }

        .assignments-ui .assignment-chip-meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            color: #65766d;
            font-size: 11px;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-chip-remove {
            width: auto !important;
            height: 32px !important;
            gap: 6px;
            padding: 0 10px !important;
            border-radius: 7px !important;
            background: #fff !important;
            font-size: 11px !important;
            white-space: nowrap;
        }

        .assignments-ui .assignment-empty {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 14px;
            border: 1px dashed #cddbd2;
            border-radius: 9px;
            background: #f9fbfa;
            color: #687b70;
            font-size: 12px;
        }

        .assignments-ui .assignment-empty i {
            color: #749584;
            font-size: 15px;
        }

        body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-modal-actions .btn-primary {
            border-color: #1d4f40 !important;
            background: #1d4f40 !important;
        }

        @media (max-width: 1050px) {
            body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-staff-list {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .assignments-ui .assignment-overview {
                align-items: stretch;
                flex-direction: column;
                padding: 19px;
            }

            .assignments-ui .assignment-overview-stats {
                width: 100%;
            }

            .assignments-ui .assignment-overview-stat {
                min-width: 0;
                flex: 1;
            }

            body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-staff-head {
                padding: 18px !important;
            }

            body .admin-dashboard.admin-role-page .main-area.assignments-ui .assignment-project-strip {
                padding: 16px 18px 18px !important;
            }
        }
    </style>

    @include('partials.admin-sidebar')

    <div class="main-area assignments-ui">
        <x-page-header title="Staff assignments" subtitle="Assign staff to projects" />

        <main class="dashboard-content assignments-page">


            @if(session('success'))
                <div id="assignmentSuccessAlert" class="assignment-alert assignment-alert-success">
                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                    {{ session('success') }}
                </div>
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

            @php
                $assignmentTotal = $staffMembers->sum(fn ($staff) => $staff->assignments->count());
            @endphp

            <section class="assignment-overview" aria-label="Assignment summary">
                <div>
                    <p class="assignment-overview-eyebrow">Team coverage</p>
                    <h2>Project assignments</h2>
                    <p>Manage the projects each staff member can work on.</p>
                </div>
                <div class="assignment-overview-stats">
                    <div class="assignment-overview-stat"><strong>{{ $staffMembers->count() }}</strong><span>Active staff</span></div>
                    <div class="assignment-overview-stat"><strong>{{ $assignmentTotal }}</strong><span>Assignments</span></div>
                </div>
            </section>

            <section class="assignment-staff-list" aria-label="Staff and assigned projects">
                @forelse($staffMembers as $staff)
                    <article class="assignment-staff-card">
                        <div class="assignment-staff-head">
                            <div class="assignment-staff-identity">
                                <div class="assignment-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($staff->name, 0, 1)) }}</div>
                                <div class="assignment-staff-details">
                                    <h2>{{ $staff->name }}</h2>
                                    <p>{{ $staff->email }}@if($staff->office) &middot; {{ $staff->office }}@endif</p>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="btn-primary assignment-open-btn"
                                data-modal-target="assignment-modal-{{ $staff->id }}"
                            >
                                <i class="fas fa-plus" aria-hidden="true"></i> Assign project
                            </button>
                        </div>

                        <div class="assignment-project-strip">
                            <p class="assignment-card-label">Assigned projects &middot; {{ $staff->assignments->count() }}</p>
                            @forelse($staff->assignments as $assignment)
                                <div class="assignment-chip">
                                    <div class="assignment-chip-main">
                                        <span class="assignment-chip-title">{{ $assignment->project->title ?? 'N/A' }}</span>
                                        <span class="assignment-chip-meta">
                                            @if($assignment->project?->reference_no)
                                                <span>{{ $assignment->project->reference_no }}</span>
                                            @endif
                                            <span class="status-pill status-{{ $assignment->project->status ?? 'open' }}">{{ ucfirst($assignment->project->status ?? 'open') }}</span>
                                        </span>
                                    </div>

                                    <form action="{{ route('admin.assignments.destroy', $assignment) }}" method="POST" data-confirm-title="Remove this assignment?" data-confirm="{{ $assignment->project->title ?? 'This project' }} will no longer be assigned to this staff member." data-confirm-button="Remove assignment" data-confirm-tone="danger">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="assignment-chip-remove" aria-label="Remove {{ $assignment->project->title ?? 'project' }} from {{ $staff->name }}">
                                            <i class="fas fa-times" aria-hidden="true"></i> Remove
                                        </button>
                                    </form>
                                </div>
                            @empty
                                <div class="assignment-empty"><i class="far fa-folder-open" aria-hidden="true"></i><span>No projects assigned yet. Use “Assign project” to get started.</span></div>
                            @endforelse
                        </div>
                    </article>

                    <div class="assignment-modal" id="assignment-modal-{{ $staff->id }}" aria-hidden="true">
                        <div class="assignment-modal-backdrop" data-modal-close="assignment-modal-{{ $staff->id }}"></div>
                        <div class="assignment-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="assignment-modal-title-{{ $staff->id }}">
                            <div class="assignment-modal-header">
                                <div>
                                    <h3 id="assignment-modal-title-{{ $staff->id }}">Assign Project</h3>
                                    <p>
                                        {{ $staff->name }} &middot; {{ $staff->email }}
                                        @if($staff->office)
                                            &middot; {{ $staff->office }}
                                        @endif
                                    </p>
                                </div>
                                <button type="button" class="assignment-modal-close" data-modal-close="assignment-modal-{{ $staff->id }}" aria-label="Close">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>

                            <form action="{{ route('admin.assignments.store') }}" method="POST" class="assignment-modal-form">
                                @csrf
                                <input type="hidden" name="staff_id" value="{{ $staff->id }}">

                                <div class="assignment-form-grid" style="margin-bottom: 12px;">
                                    <div class="field-group">
                                        <label for="assignment-project-{{ $staff->id }}">Select project</label>
                                        <select id="assignment-project-{{ $staff->id }}" name="project_id" class="form-select" required {{ $staff->available_projects->isEmpty() ? 'disabled' : '' }}>
                                            @if($staff->available_projects->isEmpty())
                                                <option value="">No projects available</option>
                                            @endif
                                            @foreach($staff->available_projects as $project)
                                                <option value="{{ $project->id }}">{{ $project->title }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="assignment-modal-actions" style="border-top: 1px solid #edf2f7; padding-top: 18px; margin-top: 10px;">
                                    <button type="button" class="btn-secondary" data-modal-close="assignment-modal-{{ $staff->id }}">Cancel</button>
                                    <button type="submit" class="btn-primary" {{ $staff->available_projects->isEmpty() ? 'disabled' : '' }}>Assign project</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="panel">
                        <div class="empty-state">No active staff accounts available for assignments.</div>
                    </div>
                @endforelse
            </section>
        </main>
    </div>
</div>

<script>
    document.querySelectorAll('[data-modal-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            const modal = document.getElementById(button.dataset.modalTarget);
            if (!modal) return;

            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            const modal = document.getElementById(button.dataset.modalClose);
            if (!modal) return;

            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        const successAlert = document.getElementById('assignmentSuccessAlert');
        if (!successAlert) return;

        setTimeout(function () {
            successAlert.classList.add('fade-out');

            setTimeout(function () {
                successAlert.style.display = 'none';
            }, 400);
        }, 4000);
    });
</script>
