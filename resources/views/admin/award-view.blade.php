<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard">
    @vite(['resources/css/dashboard.css'])

    <aside class="sidebar">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-logo-link"><h2 class="sidebar-logo">BAC-Office</h2></a>
        @include('partials.sidebar-profile')
        <ul class="sidebar-menu">
            <p class="menu-title">MAIN</p>
            <li><a href="{{ route('admin.dashboard') }}"><span class="menu-icon-dashboard" aria-hidden="true"></span> Dashboard</a></li>
            <li><a href="{{ route('admin.projects') }}"><i class="fas fa-folder-open"></i> Project/Biddings</a></li>
            <li><a href="{{ route('admin.bids') }}"><span class="menu-icon-all-bids" aria-hidden="true"></span> All Bids</a></li>
            <li><a href="{{ route('admin.awards') }}" class="active"><i class="fas fa-trophy"></i> Awards & Contracts</a></li>

            <p class="menu-title">MANAGEMENT</p>
            <li><a href="{{ route('admin.users') }}"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <li><a href="{{ route('admin.assignments') }}"><i class="fas fa-tasks"></i> Staff Assignments</a></li>
            <li><a href="{{ route('admin.reports') }}"><i class="fas fa-chart-bar"></i> Reports</a></li>

            <p class="menu-title">SYSTEM</p>
            <li><a href="{{ route('admin.notifications') }}"><i class="fas fa-bell"></i> Notifications</a></li>

            <li>
                <form action="{{ route('logout') }}" method="POST" class="sidebar-form">
                    @csrf
                    <button type="submit" class="sidebar-logout"><i class="fas fa-sign-out-alt"></i> Logout</button>
                </form>
            </li>
        </ul>
    </aside>

    <div class="main-area">
        <header class="navbar">
            <div class="nav-left">
                <h2>Award Details</h2>
                <p>Review awarded contract information</p>
            </div>
        </header>

        <main class="dashboard-content">
            <div class="welcome-text">
                <h1 class="title">View Award</h1>
                <p class="subtitle">Inspect the awarded contract and winning bidder details.</p>
            </div>

            <div style="background: white; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden;">
                @include('admin.award-view-modal')
            </div>
        </main>
    </div>
</div>
