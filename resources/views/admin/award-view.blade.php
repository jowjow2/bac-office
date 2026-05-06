<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard">
    @vite(['resources/css/dashboard.css'])

    @include('partials.admin-sidebar')

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
