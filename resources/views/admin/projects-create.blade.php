<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard">
    @vite(['resources/css/dashboard.css'])

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-logo-link"><h2 class="sidebar-logo">BAC-Office</h2></a>
        @include('partials.sidebar-profile')
        <ul class="sidebar-menu">

            <p class="menu-title">MAIN</p>
            <li><a href="/dashboard/admin"><span class="menu-icon-dashboard" aria-hidden="true"></span> Dashboard</a></li>
            <li><a href="{{ route('admin.projects') }}" class="active"><i class="fas fa-folder-open"></i> Project/Biddings</a></li>
            <li><a href="/admin/bidders"><span class="menu-icon-all-bids" aria-hidden="true"></span> All Bids</a></li>
            <li><a href="{{ route('admin.awards') }}"><i class="fas fa-trophy"></i> Award & Contracts</a></li>

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
    <div class="main-area">

        <!-- NAVBAR -->
        <header class="navbar">
            <div class="nav-left">
                <h2>Create Project</h2>
                <p>Add new procurement project</p>
            </div>
        </header>

        <!-- MAIN CONTENT -->
        <main class="dashboard-content">

            <div class="welcome-text">
                <h1 class="title">Create New Project</h1>
                <p class="subtitle">
                    Fill in the details below to create a new procurement project.
                </p>
            </div>

            <div class="form-container" style="background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); max-width: 800px;">

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

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Project Title</label>
                        <input type="text" name="title" value="{{ old('title') }}" required style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Description</label>
                        <textarea name="description" rows="4" required style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; resize: vertical;">{{ old('description') }}</textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Category</label>
                            <input type="text" name="category" value="{{ old('category') }}" required style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Budget (PHP)</label>
                            <input type="number" name="budget" value="{{ old('budget') }}" required min="0" max="9999999999999.99" step="0.01" style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Status</label>
                            <select name="status" required style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; background: white;">
                                <option value="approved_for_bidding" {{ old('status', 'approved_for_bidding') == 'approved_for_bidding' ? 'selected' : '' }}>Approved for Bidding</option>
                                <option value="open" {{ old('status') == 'open' ? 'selected' : '' }}>Open</option>
                                <option value="closed" {{ old('status') == 'closed' ? 'selected' : '' }}>Closed</option>
                                <option value="awarded" {{ old('status') == 'awarded' ? 'selected' : '' }}>Awarded</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Deadline</label>
                            <input type="date" name="deadline" value="{{ old('deadline') }}" required style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
                        </div>
                    </div>

                    <div style="display: flex; gap: 12px; margin-top: 30px;">
                        <a href="{{ route('admin.projects') }}" style="padding: 10px 24px; border: 1px solid #d1d5db; border-radius: 8px; color: #374151; text-decoration: none; font-size: 14px; font-weight: 500;">Cancel</a>
                        <button type="submit" style="padding: 10px 24px; background: #1a3cff; color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer;">
                            <i class="fas fa-save"></i> Create Project
                        </button>
                    </div>
                </form>
            </div>

        </main>
    </div>
</div>
