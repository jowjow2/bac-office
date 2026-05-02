<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard">
    @php($staffOffices = \App\Models\User::staffOfficeOptions())
    @vite(['resources/css/dashboard.css'])

    <style>
        .users-page,
        .users-page * {
            font-family: 'Inter', sans-serif;
        }

        .users-page {
            font-size: 14px;
        }

        .users-page .user-search-input::placeholder {
            color: #9ca3af;
        }

        .users-page .btn-primary,
        #createUserModal .btn-primary,
        #editUserModal .btn-primary {
            background: #1d4f91;
            color: #ffffff;
            border: 1px solid #1d4f91;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 10px 24px rgba(29, 79, 145, 0.18);
        }

        .users-page .btn-primary:hover,
        #createUserModal .btn-primary:hover,
        #editUserModal .btn-primary:hover {
            background: #163b6d;
            border-color: #163b6d;
        }

        .users-page .btn-secondary,
        #createUserModal .btn-secondary,
        #editUserModal .btn-secondary {
            border: 1px solid #d1d5db;
            color: #374151;
            background: #ffffff;
            font-size: 13px;
            font-weight: 500;
        }

        .users-page .btn-secondary:hover,
        #createUserModal .btn-secondary:hover,
        #editUserModal .btn-secondary:hover {
            background: #f8fafc;
        }

        .user-modal {
            display: none;
            position: fixed;
            inset: 0;
            padding: 20px;
            background: rgba(15, 23, 42, 0.42);
            justify-content: center;
            align-items: center;
            z-index: 10000;
        }

        .user-modal-card {
            width: min(620px, 100%);
            max-height: calc(100vh - 24px);
            overflow-y: auto;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 24px 52px rgba(15, 23, 42, 0.18);
            position: relative;
        }

        .user-modal-header {
            padding: 24px 24px 18px;
            border-bottom: 1px solid #edf2f7;
        }

        .user-modal-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #111827;
        }

        .user-modal-header p {
            margin: 8px 0 0;
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
        }

        .user-modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 34px;
            height: 34px;
            border: none;
            border-radius: 10px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
        }

        .user-modal-form {
            padding: 20px 24px 0;
        }

        .user-modal-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .user-modal-field {
            margin-bottom: 16px;
        }

        .user-modal-field label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }

        .user-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin: 0 -24px;
            padding: 16px 24px 20px;
            border-top: 1px solid #edf2f7;
            background: #ffffff;
        }

        .user-modal-alert {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 13px;
        }

        .user-modal-alert ul {
            margin: 0;
            padding-left: 18px;
        }

        .users-page .user-action-button {
            background: #ffffff;
            color: #374151;
            border: 1px solid #d1d5db;
            padding: 7px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.18s ease, border-color 0.18s ease, color 0.18s ease;
        }

        .users-page .user-action-button:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #111827;
        }

        @media (max-width: 720px) {
            .user-modal-grid {
                grid-template-columns: 1fr;
            }

            .user-modal {
                padding: 12px;
            }

            .user-modal-header,
            .user-modal-form {
                padding-left: 16px;
                padding-right: 16px;
            }

            .user-modal-actions {
                margin: 0 -16px;
                padding-left: 16px;
                padding-right: 16px;
                flex-direction: column;
            }

            .user-modal-actions .btn-primary,
            .user-modal-actions .btn-secondary {
                width: 100%;
                justify-content: center;
            }
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
            <li><a href="{{ route('admin.users') }}" class="active"><i class="fas fa-users-cog"></i> Manage Users</a></li>
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

    <div class="main-area users-page">
        <header class="navbar">
            <div class="nav-left">
                <h2>Manage Users</h2>
                <p>Approve bidder registrations and maintain account access</p>
            </div>
            <div class="nav-right">
            </div>

        </header>

        <main class="dashboard-content">
            <div class="welcome-text" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 18px;">
                <div>
                    <h1 class="title" style="font-size: 24px; font-weight: 600; color: #111827; margin-bottom: 6px;">User Management</h1>
                    <p class="subtitle" style="font-size: 14px;">Manage admin, staff, and bidder accounts</p>
                </div>
                <button type="button" onclick="openCreateUserModal()" style="background: #1d4f91; color: white; padding: 11px 18px; border-radius: 8px; border: none; cursor: pointer; font-size: 13px; font-weight: 600;">
                    <i class="fas fa-plus" style="margin-right: 6px;"></i> Add User
                </button>
            </div>

            @if(session('success'))
                <div id="successAlert" style="position: fixed; top: 90px; right: 25px; background: #dcfce7; color: #166534; padding: 16px 20px; border-radius: 8px; font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; display: flex; align-items: center; gap: 10px; min-width: 280px;">
                    <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                    <span>{{ session('success') }}</span>
                    <button onclick="closeSuccessAlert()" style="margin-left: auto; background: none; border: none; color: #166534; cursor: pointer; font-size: 16px;">&times;</button>
                </div>
            @endif

            @if(session('warning'))
                <div class="error-alert" style="margin-bottom: 20px; background: #fff7ed; border-color: #fdba74; color: #9a3412;">
                    {{ session('warning') }}
                </div>
            @endif

            @if(!($bidderApprovalAvailable ?? false))
                <div class="error-alert" style="margin-bottom: 20px; background: #eff6ff; border-color: #93c5fd; color: #1d4ed8;">
                    Bidder review and approval actions are temporarily unavailable because the bidder approval table is not present in the current database.
                </div>
            @endif

            @if($errors->any() && !old('editing_user_id'))
                <div class="error-alert" style="margin-bottom: 20px;">
                    <ul style="margin: 0; padding-left: 18px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div style="margin: 20px 0 14px;">
                <div style="display: inline-flex; gap: 4px; padding: 4px; background: #eef2f7; border-radius: 12px; flex-wrap: wrap;">
                    <a href="{{ route('admin.users', ['filter' => 'all', 'search' => $search !== '' ? $search : null]) }}"
                       style="padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; text-decoration: none; {{ ($filter ?? 'all') === 'all' ? 'background: white; color: #1d4f91; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08); font-weight: 600;' : 'color: #64748b;' }}">
                        All Users
                    </a>
                    <a href="{{ route('admin.users', ['filter' => 'admin', 'search' => $search !== '' ? $search : null]) }}"
                       style="padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; text-decoration: none; {{ ($filter ?? 'all') === 'admin' ? 'background: white; color: #1d4f91; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08); font-weight: 600;' : 'color: #64748b;' }}">
                        Admin
                    </a>
                    <a href="{{ route('admin.users', ['filter' => 'staff', 'search' => $search !== '' ? $search : null]) }}"
                       style="padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; text-decoration: none; {{ ($filter ?? 'all') === 'staff' ? 'background: white; color: #1d4f91; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08); font-weight: 600;' : 'color: #64748b;' }}">
                        Staff
                    </a>
                    <a href="{{ route('admin.users', ['filter' => 'bidder', 'search' => $search !== '' ? $search : null]) }}"
                       style="padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; text-decoration: none; {{ ($filter ?? 'all') === 'bidder' ? 'background: white; color: #1d4f91; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08); font-weight: 600;' : 'color: #64748b;' }}">
                        Bidders
                    </a>
                    <a href="{{ route('admin.users', ['filter' => 'pending', 'search' => $search !== '' ? $search : null]) }}"
                       style="padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; text-decoration: none; {{ ($filter ?? 'all') === 'pending' ? 'background: white; color: #1d4f91; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08); font-weight: 600;' : 'color: #64748b;' }}">
                        Pending
                    </a>
                </div>
            </div>

            <div class="table-container" style="background: white; border-radius: 16px; box-shadow: 0 10px 24px rgba(15,23,42,0.06); overflow: hidden; border: 1px solid #e9eef5;">
                <form method="GET" action="{{ route('admin.users') }}" style="padding: 18px 20px; border-bottom: 1px solid #edf2f7; background: #ffffff;">
                    <input type="hidden" name="filter" value="{{ $filter ?? 'all' }}">
                    <div style="display:flex; gap:10px; align-items:center;">
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Search users..."
                            class="search-box user-search-input"
                            style="width: 100%; height: 40px; padding: 0 15px; border: 1px solid #d7e0ea; border-radius: 10px; font-size: 13px; color: #1f2937; outline: none;"
                        >
                        @if($search !== '')
                            <a href="{{ route('admin.users', ['filter' => ($filter ?? 'all') !== 'all' ? $filter : null]) }}" class="btn-secondary">Clear</a>
                        @endif
                    </div>
                </form>

                <div style="overflow-x:auto; padding: 0 20px 20px;">
                <table style="width: 100%; border-collapse: collapse; min-width: 980px;">
                    <thead>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <th style="text-align: left; padding: 14px 12px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Name</th>
                            <th style="text-align: left; padding: 14px 12px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Email</th>
                            <th style="text-align: left; padding: 14px 12px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Role</th>
                            <th style="text-align: left; padding: 14px 12px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Office</th>
                            <th style="text-align: left; padding: 14px 12px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Status</th>
                            <th style="text-align: left; padding: 14px 12px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Company</th>
                            <th style="text-align: left; padding: 14px 12px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Registration No.</th>
                            <th style="text-align: left; padding: 14px 12px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Created</th>
                            <th style="text-align: left; padding: 14px 12px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 12px; font-size: 14px; font-weight: 600; color: #111827;">
                                    <div>{{ $user->name }}</div>
                                    @if($user->username)
                                        <div style="margin-top: 4px; font-size: 12px; font-weight: 500; color: #64748b;">{{ '@' . $user->username }}</div>
                                    @endif
                                </td>
                                <td style="padding: 12px; font-size: 13px; color: #6b7280;">{{ $user->email }}</td>
                                <td style="padding: 12px;">
                                    <span style="padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: 500;
                                        @if($user->role === 'admin') background: #ede9fe; color: #7c3aed;
                                        @elseif($user->role === 'staff') background: #dcfce7; color: #15803d;
                                        @else background: #fef3c7; color: #b45309; @endif">
                                        {{ ucfirst($user->role) }}
                                    </span>
                                </td>
                                <td style="padding: 12px; font-size: 13px; color: #111827;">
                                    {{ $user->role === 'staff' ? ($user->office ?: 'Unassigned') : 'N/A' }}
                                </td>
                                <td style="padding: 12px;">
                                    <span style="padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: 500;
                                        @if($user->status === 'active') background: #dcfce7; color: #166534;
                                        @elseif($user->status === 'pending') background: #fef3c7; color: #92400e;
                                        @else background: #fee2e2; color: #991b1b; @endif">
                                        {{ ucfirst($user->status) }}
                                    </span>
                                </td>
                                <td style="padding: 12px; font-size: 13px; color: #111827;">{{ $user->company ?: 'N/A' }}</td>
                                <td style="padding: 12px; font-size: 13px; color: #111827;">{{ $user->registration_no ?: 'N/A' }}</td>
                                <td style="padding: 12px; font-size: 13px; color: #6b7280;">{{ $user->created_at?->format('M d, Y') ?? 'N/A' }}</td>
                                <td style="padding: 12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                    @if($user->role === 'bidder' && ($bidderApprovalAvailable ?? false))
                                        <a href="{{ route('admin.users.review', $user) }}" class="user-action-button">
                                            Review
                                        </a>
                                    @endif

                                    @if($user->role === 'bidder' && $user->status === 'pending' && ($bidderApprovalAvailable ?? false))
                                        <form method="POST" action="{{ route('admin.users.approve', $user) }}" style="display:inline;">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" style="background: #15803d; color: #ffffff; border: 1px solid #15803d; padding: 7px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer;">
                                                Approve
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.users.reject', $user) }}" style="display:inline;">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" style="background: #b91c1c; color: #ffffff; border: 1px solid #b91c1c; padding: 7px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer;">
                                                Reject
                                            </button>
                                        </form>
                                    @endif

                                    @if($user->role === 'bidder' && !($bidderApprovalAvailable ?? false))
                                        <span style="font-size: 12px; color: #64748b;">Review unavailable</span>
                                    @endif

                                    <button
                                        type="button"
                                        onclick="openEditUserModal(this)"
                                        class="user-action-button"
                                        data-id="{{ $user->id }}"
                                        data-name="{{ e($user->name) }}"
                                        data-email="{{ e($user->email) }}"
                                        data-username="{{ e($user->username ?? '') }}"
                                        data-role="{{ $user->role }}"
                                        data-status="{{ $user->status }}"
                                        data-office="{{ e($user->office ?? '') }}"
                                        data-company="{{ e($user->company ?? '') }}"
                                        data-registration="{{ e($user->registration_no ?? '') }}"
                                    >
                                        Edit
                                    </button>

                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Remove this user account?');" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            style="background: transparent; color: #6b7280; border: none; padding: 6px 4px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer;"
                                            {{ auth()->id() === $user->id ? 'disabled' : '' }}
                                        >
                                            Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" style="padding: 40px; text-align: center; color: #9ca3af;">
                                    <i class="fas fa-users" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                                    No users matched your search.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
        </main>
    </div>
</div>

<div id="createUserModal" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
    <div style="background: white; border-radius: 12px; padding: 30px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; position: relative;">
        <button onclick="closeCreateUserModal()" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">&times;</button>

        <h2 style="font-size: 20px; font-weight: 600; margin-bottom: 5px;">Create User</h2>
        <p style="color: #64748b; font-size: 14px; margin-bottom: 20px;">Add a new account and assign the correct role.</p>

        <form action="{{ route('admin.users.store') }}" method="POST">
            @csrf
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="form-input">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required class="form-input">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">Role</label>
                    <select name="role" id="create_role" class="form-select" required>
                        <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                        <option value="staff" {{ old('role') === 'staff' ? 'selected' : '' }}>Staff</option>
                        <option value="bidder" {{ old('role', 'bidder') === 'bidder' ? 'selected' : '' }}>Bidder</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">Username</label>
                <input type="text" name="username" value="{{ old('username') }}" class="form-input" placeholder="Optional for admin and staff">
            </div>

            <div id="createOfficeField" style="margin-bottom: 16px; display: {{ old('role') === 'staff' ? 'block' : 'none' }};">
                <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">Office</label>
                <select name="office" id="create_office" class="form-select">
                    <option value="">Select office</option>
                    @foreach($staffOffices as $office)
                        <option value="{{ $office }}" {{ old('office') === $office ? 'selected' : '' }}>{{ $office }}</option>
                    @endforeach
                </select>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">Password</label>
                <input type="password" name="password" required class="form-input">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">Status</label>
                    <select name="status" class="form-select" required>
                        <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="pending" {{ old('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="rejected" {{ old('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">Registration No.</label>
                    <input type="text" name="registration_no" value="{{ old('registration_no') }}" class="form-input">
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">Company</label>
                <input type="text" name="company" value="{{ old('company') }}" class="form-input">
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeCreateUserModal()" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create User</button>
            </div>
        </form>
    </div>
</div>

<div id="editUserModal" class="user-modal">
    <div class="user-modal-card">
        <button type="button" onclick="closeEditUserModal()" class="user-modal-close" aria-label="Close">&times;</button>

        <div class="user-modal-header">
            <h2>Edit User</h2>
            <p>Update details, status, or reset the password for this account.</p>
        </div>

        <form id="editUserForm" method="POST" class="user-modal-form">
            @csrf
            @method('PUT')
            <input type="hidden" name="editing_user_id" id="edit_user_id" value="">

            @if($errors->any() && old('editing_user_id'))
                <div class="user-modal-alert">
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="user-modal-field">
                <label>Name</label>
                <input type="text" name="name" id="edit_name" required class="form-input">
            </div>

            <div class="user-modal-grid">
                <div class="user-modal-field">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email" required class="form-input">
                </div>
                <div class="user-modal-field">
                    <label>Role</label>
                    <select name="role" id="edit_role" class="form-select" required>
                        <option value="admin">Admin</option>
                        <option value="staff">Staff</option>
                        <option value="bidder">Bidder</option>
                    </select>
                </div>
            </div>

            <div class="user-modal-field">
                <label>Username</label>
                <input type="text" name="username" id="edit_username" class="form-input" placeholder="Optional for admin and staff">
            </div>

            <div class="user-modal-field" id="editOfficeField" style="display: none;">
                <label>Office</label>
                <select name="office" id="edit_office" class="form-select">
                    <option value="">Select office</option>
                    @foreach($staffOffices as $office)
                        <option value="{{ $office }}">{{ $office }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-modal-grid">
                <div class="user-modal-field">
                    <label>Status</label>
                    <select name="status" id="edit_status" class="form-select" required>
                        <option value="active">Active</option>
                        <option value="pending">Pending</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="user-modal-field">
                    <label>Registration No.</label>
                    <input type="text" name="registration_no" id="edit_registration_no" class="form-input">
                </div>
            </div>

            <div class="user-modal-field">
                <label>Company</label>
                <input type="text" name="company" id="edit_company" class="form-input">
            </div>

            <div class="user-modal-field" style="margin-bottom: 20px;">
                <label>New Password</label>
                <input type="password" name="password" class="form-input" placeholder="Leave blank to keep current password">
            </div>

            <div class="user-modal-actions">
                <button type="button" onclick="closeEditUserModal()" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update User</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreateUserModal() {
        document.getElementById('createUserModal').style.display = 'flex';
        toggleOfficeField('create_role', 'createOfficeField', 'create_office');
    }

    function closeCreateUserModal() {
        document.getElementById('createUserModal').style.display = 'none';
    }

    function openEditUserModal(source) {
        const data = source && source.dataset
            ? {
                id: source.dataset.id,
                name: source.dataset.name || '',
                email: source.dataset.email || '',
                username: source.dataset.username || '',
                role: source.dataset.role || 'bidder',
                status: source.dataset.status || 'active',
                office: source.dataset.office || '',
                company: source.dataset.company || '',
                registration: source.dataset.registration || '',
            }
            : (source || {});

        const userId = data.id;
        if (!userId) return;

        document.getElementById('editUserForm').action = `/admin/users/${userId}`;
        document.getElementById('edit_user_id').value = userId;
        document.getElementById('edit_name').value = data.name || '';
        document.getElementById('edit_email').value = data.email || '';
        document.getElementById('edit_username').value = data.username || '';
        document.getElementById('edit_role').value = data.role || 'bidder';
        document.getElementById('edit_status').value = data.status || 'active';
        document.getElementById('edit_office').value = data.office || '';
        document.getElementById('edit_company').value = data.company || '';
        document.getElementById('edit_registration_no').value = data.registration || '';
        toggleOfficeField('edit_role', 'editOfficeField', 'edit_office');
        document.getElementById('editUserModal').style.display = 'flex';
    }

    function closeEditUserModal() {
        document.getElementById('editUserModal').style.display = 'none';
        document.getElementById('edit_user_id').value = '';
    }

    function closeSuccessAlert() {
        const alert = document.getElementById('successAlert');
        if (alert) {
            alert.style.display = 'none';
        }
    }

    document.getElementById('createUserModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeCreateUserModal();
        }
    });

    document.getElementById('editUserModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeEditUserModal();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        const createRole = document.getElementById('create_role');
        const editRole = document.getElementById('edit_role');

        if (createRole) {
            createRole.addEventListener('change', function () {
                toggleOfficeField('create_role', 'createOfficeField', 'create_office');
            });
        }

        if (editRole) {
            editRole.addEventListener('change', function () {
                toggleOfficeField('edit_role', 'editOfficeField', 'edit_office');
            });
        }

        toggleOfficeField('create_role', 'createOfficeField', 'create_office');
        toggleOfficeField('edit_role', 'editOfficeField', 'edit_office');

        const successAlert = document.getElementById('successAlert');
        if (successAlert) {
            setTimeout(function () {
                successAlert.style.transition = 'opacity 0.5s ease';
                successAlert.style.opacity = '0';
                setTimeout(function () {
                    successAlert.style.display = 'none';
                }, 500);
            }, 5000);
        }

        @if($errors->any())
            @if(old('editing_user_id'))
                openEditUserModal({
                    id: @json(old('editing_user_id')),
                    name: @json(old('name')),
                    email: @json(old('email')),
                    username: @json(old('username', '')),
                    role: @json(old('role', 'bidder')),
                    status: @json(old('status', 'active')),
                    office: @json(old('office', '')),
                    company: @json(old('company', '')),
                    registration: @json(old('registration_no', ''))
                });
            @else
                openCreateUserModal();
            @endif
        @endif
    });

    function toggleOfficeField(roleSelectId, fieldId, officeSelectId) {
        const roleSelect = document.getElementById(roleSelectId);
        const field = document.getElementById(fieldId);
        const officeSelect = document.getElementById(officeSelectId);

        if (!roleSelect || !field || !officeSelect) {
            return;
        }

        const isStaff = roleSelect.value === 'staff';
        field.style.display = isStaff ? 'block' : 'none';
        officeSelect.required = isStaff;
    }
</script>
