<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard">
    @vite(['resources/css/dashboard.css'])

    <style>
        .assignments-ui,
        .assignments-ui * {
            font-family: 'Inter', sans-serif;
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
            font-weight: 900 !important;
        }

        .assignments-ui {
            font-size: 13px;
        }

        .assignments-ui .title {
            font-size: 24px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 6px;
        }

        .assignments-ui .subtitle,
        .assignments-ui .nav-left p,
        .assignments-ui .assignment-staff-head p,
        .assignments-ui .assignment-modal-header p {
            font-size: 14px;
            color: #6b7280;
        }

        .assignments-ui .assignment-staff-head h2,
        .assignments-ui .assignment-modal-header h3 {
            font-size: 14px;
            font-weight: 600;
            color: #111827;
        }

        .assignments-ui .report-date,
        .assignments-ui .assignment-chip-title,
        .assignments-ui .field-group label,
        .assignments-ui .form-select,
        .assignments-ui .form-input {
            font-size: 13px;
        }

        .assignments-ui .field-group label {
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #6b7280;
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

        .assignments-ui .empty-state {
            font-size: 13px;
            color: #9ca3af;
        }

        .assignments-ui .assignment-modal-dialog {
            width: min(430px, calc(100vw - 32px));
            padding: 0;
            border-radius: 14px;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.18);
        }

        .assignments-ui .assignment-modal-header {
            align-items: center;
            margin-bottom: 0;
            padding: 15px 16px;
            border-bottom: 1px solid #edf2f7;
        }

        .assignments-ui .assignment-modal-close {
            width: 28px;
            height: 28px;
            border: none;
            border-radius: 8px;
            background: #f1f5f9;
            color: #7c8ba1;
        }

        .assignments-ui .assignment-modal-form {
            padding: 14px 16px;
        }

        .assignments-ui .assignment-modal-form .form-select {
            min-height: 36px;
            border-radius: 8px;
            border: 1px solid #d6dfeb;
            font-size: 12px;
        }

        .assignments-ui .assignment-modal {
            display: none;
            align-items: center;
            justify-content: center;
        }

        .assignments-ui .assignment-modal.show {
            display: flex;
        }

        .assignments-ui .assignment-modal-actions {
            justify-content: center;
            gap: 10px;
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
            border-radius: 10px;
            background: #dcfce7;
            color: #166534;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
            opacity: 1;
            transition: opacity 0.4s ease, transform 0.4s ease;
        }

        .assignments-ui #assignmentSuccessAlert.fade-out {
            opacity: 0;
            transform: translateY(-10px);
        }
    </style>

    <aside class="sidebar">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-logo-link"><h2 class="sidebar-logo">BAC-Office</h2></a>
        @include('partials.sidebar-profile')
        <ul class="sidebar-menu">
            <p class="menu-title">MAIN</p>
            <li><a href="{{ route('admin.dashboard') }}"><span class="menu-icon-dashboard" aria-hidden="true"></span> Dashboard</a></li>
            <li><a href="{{ route('admin.projects') }}"><i class="fas fa-folder-open"></i> Project/Biddings</a></li>
            <li><a href="{{ route('admin.bids') }}"><span class="menu-icon-all-bids" aria-hidden="true"></span> All Bids</a></li>
            <li><a href="{{ route('admin.awards') }}"><i class="fas fa-trophy"></i> Awards & Contracts</a></li>

            <p class="menu-title">MANAGEMENT</p>
            <li><a href="{{ route('admin.users') }}"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <li><a href="{{ route('admin.assignments') }}" class="active"><i class="fas fa-tasks"></i> Staff Assignments</a></li>
            <li><a href="{{ route('admin.reports') }}"><i class="fas fa-chart-bar"></i> Reports</a></li>

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

    <div class="main-area assignments-ui">
        <header class="navbar">
            <div class="nav-left">
                <h2>Staff Assignments</h2>
                <p>Assign staff to projects</p>
            </div>

            <div class="nav-right">
                <div class="report-toolbar">
                    <a href="{{ route('admin.notifications') }}" class="notification-button" aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                        @if(($unreadNotificationsCount ?? 0) > 0)
                            <span class="notification-badge">{{ $unreadNotificationsCount }}</span>
                        @endif
                    </a>
                </div>
            </div>
        </header>

        <main class="dashboard-content assignments-page">
            <section class="assignments-page-header">
                <h1 class="title">Staff Assignments</h1>
                <p class="subtitle">Assign staff members to procurement projects</p>
            </section>

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

            <section class="assignment-staff-list">
                @forelse($staffMembers as $staff)
                    <article class="assignment-staff-card">
                        <div class="assignment-staff-head">
                            <div>
                                <h2>{{ $staff->name }}</h2>
                                <p>
                                    {{ $staff->email }}
                                    @if($staff->office)
                                        &middot; {{ $staff->office }}
                                    @endif
                                    &middot; {{ $staff->assignments->count() }} project(s) assigned
                                </p>
                            </div>

                            <button
                                type="button"
                                class="btn-primary assignment-open-btn"
                                data-modal-target="assignment-modal-{{ $staff->id }}"
                            >
                                <i class="fas fa-plus"></i> Assign Project
                            </button>
                        </div>

                        <div class="assignment-project-strip">
                            @forelse($staff->assignments as $assignment)
                                <div class="assignment-chip">
                                    <div class="assignment-chip-main">
                                        <span class="assignment-chip-title">{{ $assignment->project->title ?? 'N/A' }}</span>
                                        <span class="status-pill status-{{ $assignment->project->status ?? 'open' }}">
                                            {{ $assignment->project->status ?? 'open' }}
                                        </span>
                                    </div>

                                    <form action="{{ route('admin.assignments.destroy', $assignment) }}" method="POST" onsubmit="return confirm('Remove this assignment?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="assignment-chip-remove" aria-label="Remove assignment">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                </div>
                            @empty
                                <div class="empty-state">No projects assigned yet.</div>
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
                                        <label>Select Project</label>
                                        <select name="project_id" class="form-select" required>
                                            @foreach($staff->available_projects as $project)
                                                <option value="{{ $project->id }}">{{ $project->title }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="assignment-modal-actions" style="border-top: 1px solid #edf2f7; padding-top: 18px; margin-top: 10px;">
                                    <button type="button" class="btn-secondary" data-modal-close="assignment-modal-{{ $staff->id }}">Cancel</button>
                                    <button type="submit" class="btn-primary">Assign</button>
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
