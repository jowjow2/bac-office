<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard">
    @vite(['resources/css/dashboard.css'])

    <aside class="sidebar">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-logo-link"><h2 class="sidebar-logo">BAC-Office</h2></a>
        @include('partials.sidebar-profile')
        <ul class="sidebar-menu">

            <p class="menu-title">MAIN</p>
            <li><a href="/dashboard/admin"><span class="menu-icon-dashboard" aria-hidden="true"></span> Dashboard</a></li>
            <li><a href="{{ route('admin.projects') }}"><i class="fas fa-folder-open"></i> Project/Biddings</a></li>
            <li><a href="/admin/bidders"><span class="menu-icon-all-bids" aria-hidden="true"></span> All Bids</a></li>
            <li><a href="{{ route('admin.awards') }}" class="active"><i class="fas fa-trophy"></i> Awards & Contracts</a></li>

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

    <div class="main-area">
        <header class="navbar">
            <div class="nav-left">
                <h2>Awards & Contracts</h2>
                <p>View all awarded projects and contracts</p>
            </div>
        </header>

        <main class="dashboard-content">
            <div class="welcome-text">
                <h1 class="title" style="font-size: 24px; font-weight: 600; color: #111827; margin-bottom: 6px;">Awards & Contracts</h1>
                <p class="subtitle" style="font-size: 14px;">
                    View and manage awarded contracts and procurement transparency.
                </p>
            </div>

            @if(session('success'))
            <div id="successAlert" style="position: fixed; top: 90px; right: 25px; background: #dcfce7; color: #166534; padding: 16px 20px; border-radius: 8px; font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; display: flex; align-items: center; gap: 10px; min-width: 280px;">
                <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                <span>{{ session('success') }}</span>
                <button onclick="closeSuccessAlert()" style="margin-left: auto; background: none; border: none; color: #166534; cursor: pointer; font-size: 16px;">&times;</button>
            </div>
            @endif

            <div class="actions" style="margin: 20px 0;">
                <a href="{{ route('admin.projects') }}" style="background: #6b7280; color: white; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-size: 13px; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Back to Projects
                </a>
            </div>

            <div class="table-container" style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e5e7eb;">
                            <th style="text-align: left; padding: 12px; font-size: 12px; color: #64748b; font-weight: 600;">Project</th>
                            <th style="text-align: left; padding: 12px; font-size: 12px; color: #64748b; font-weight: 600;">Bidder</th>
                            <th style="text-align: left; padding: 12px; font-size: 12px; color: #64748b; font-weight: 600;">Contract Amount</th>
                            <th style="text-align: left; padding: 12px; font-size: 12px; color: #64748b; font-weight: 600;">Contract Date</th>
                            <th style="text-align: left; padding: 12px; font-size: 12px; color: #64748b; font-weight: 600;">Status</th>
                            <th style="text-align: left; padding: 12px; font-size: 12px; color: #64748b; font-weight: 600;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($awards as $award)
                        <tr style="border-bottom: 1px solid #f3f4f6;">
                            <td style="padding: 12px; font-size: 13px; font-weight: 500;">{{ $award->project->title }}</td>
                            <td style="padding: 12px; font-size: 13px;">{{ $award->bid->user->company ?: ($award->bid->user->name ?? 'N/A') }}</td>
                            <td style="padding: 12px; font-size: 13px;">P{{ number_format((float) $award->contract_amount, 2) }}</td>
                            <td style="padding: 12px; font-size: 13px;">{{ $award->contract_date?->format('M d, Y') }}</td>
                            <td style="padding: 12px;">
                                <span style="padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 500;
                                    @if($award->status == 'active') background: #dbeafe; color: #1e40af;
                                    @else background: #f3f4f6; color: #6b7280; @endif">
                                    {{ ucfirst($award->status) }}
                                </span>
                            </td>
                            <td style="padding: 12px;">
                                <button type="button" onclick="loadAwardViewModal({{ $award->id }})" style="background: white; color: #374151; border: 1px solid #d1d5db; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;">View</button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" style="padding: 40px; text-align: center; color: #9ca3af;">
                                <i class="fas fa-trophy" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                                No awards yet. Award your first project from Projects page!
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="table-container" style="background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-top: 20px; overflow: hidden;">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 18px 20px; border-bottom: 1px solid #e5e7eb; flex-wrap: wrap;">
                    <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #111827;">Projects Ready for Award</h3>
                    <p style="margin: 0; font-size: 12px; color: #94a3b8;">Closed projects with evaluated bids</p>
                </div>

                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid #e5e7eb; background: #ffffff;">
                            <th style="text-align: left; padding: 14px 20px; font-size: 12px; color: #64748b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Project</th>
                            <th style="text-align: left; padding: 14px 20px; font-size: 12px; color: #64748b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Budget</th>
                            <th style="text-align: left; padding: 14px 20px; font-size: 12px; color: #64748b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Total Bids</th>
                            <th style="text-align: left; padding: 14px 20px; font-size: 12px; color: #64748b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Lowest Bid</th>
                            <th style="text-align: left; padding: 14px 20px; font-size: 12px; color: #64748b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($readyProjects ?? collect()) as $project)
                            @php
                                $evaluatedBids = $project->bids->whereIn('status', ['approved', 'pending']);
                                $recommendedBid = $project->bids->sortBy('bid_amount')->first();
                                $approvedCount = $project->bids->where('status', 'approved')->count();
                                $bidBadgeLabel = $approvedCount > 0 ? $approvedCount . ' approved' : $project->bids->count() . ' bids';
                                $bidBadgeStyles = $approvedCount > 0
                                    ? 'background: #fef3c7; color: #a16207;'
                                    : 'background: #e5e7eb; color: #475569;';
                            @endphp
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 18px 20px; font-size: 13px; font-weight: 500; color: #111827;">{{ $project->title }}</td>
                                <td style="padding: 18px 20px; font-size: 13px; color: #111827;">P{{ number_format((float) $project->budget, 2) }}</td>
                                <td style="padding: 18px 20px;">
                                    <span style="display: inline-flex; align-items: center; padding: 5px 12px; border-radius: 999px; font-size: 11px; font-weight: 600; {{ $bidBadgeStyles }}">
                                        {{ $bidBadgeLabel }}
                                    </span>
                                </td>
                                <td style="padding: 18px 20px; font-size: 13px; color: #111827;">
                                    {{ $recommendedBid ? 'P' . number_format((float) $recommendedBid->amount, 2) : '—' }}
                                </td>
                                <td style="padding: 18px 20px;">
                                    @if($recommendedBid)
                                        <button type="button" onclick="loadDeclareWinnerModal({{ $project->id }}, {{ $recommendedBid->id }})" style="display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 8px; background: #d97706; color: white; border: none; font-size: 12px; font-weight: 600; cursor: pointer;">Declare Winner</button>
                                    @else
                                        <span style="font-size: 12px; color: #94a3b8;">No bids</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="padding: 24px; text-align: center; color: #94a3b8; font-size: 13px;">
                                    No closed projects with bids are ready for awarding yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </main>
    </div>
</div>

<div id="awardViewModal" style="display: none; position: fixed; inset: 0; padding: 20px; background: rgba(15, 23, 42, 0.45); z-index: 10000; justify-content: center; align-items: center; box-sizing: border-box;">
    <div style="background: white; border-radius: 14px; width: min(720px, 100%); max-height: calc(100vh - 20px); overflow-y: auto; overflow-x: hidden; position: relative; box-shadow: 0 20px 44px rgba(15, 23, 42, 0.16); box-sizing: border-box;">
        <button onclick="closeAwardViewModal()" style="position: absolute; top: 16px; right: 16px; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 9px; font-size: 18px; line-height: 1; cursor: pointer; color: #7c8ba1; z-index: 2;">&times;</button>
        <div id="awardViewModalBody"></div>
    </div>
</div>

<div id="declareWinnerModal" style="display: none; position: fixed; inset: 0; padding: 20px; background: rgba(15, 23, 42, 0.45); z-index: 10001; justify-content: center; align-items: center; box-sizing: border-box;">
    <div style="background: white; border-radius: 18px; width: min(690px, 100%); max-height: calc(100vh - 20px); overflow-y: auto; overflow-x: hidden; position: relative; box-shadow: 0 24px 48px rgba(15, 23, 42, 0.16); box-sizing: border-box;">
        <button onclick="closeDeclareWinnerModal()" style="position: absolute; top: 16px; right: 16px; width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 10px; font-size: 20px; line-height: 1; cursor: pointer; color: #7c8ba1; z-index: 2;">&times;</button>
        <div id="declareWinnerModalBody"></div>
    </div>
</div>

<script>
    function loadAwardViewModal(id) {
        document.getElementById('awardViewModal').style.display = 'flex';
        document.getElementById('awardViewModalBody').innerHTML = '<div style="padding: 28px; color: #64748b; font-size: 14px;">Loading award details...</div>';

        fetch(`/admin/awards/${id}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.text())
            .then(html => {
                document.getElementById('awardViewModalBody').innerHTML = html;
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('awardViewModalBody').innerHTML = '<div style="padding: 28px; color: #b91c1c; font-size: 14px;">Error loading award details.</div>';
            });
    }

    function closeAwardViewModal() {
        document.getElementById('awardViewModal').style.display = 'none';
    }

    function loadDeclareWinnerModal(projectId, bidId) {
        document.getElementById('declareWinnerModal').style.display = 'flex';
        document.getElementById('declareWinnerModalBody').innerHTML = '<div style="padding: 30px; color: #64748b; font-size: 14px;">Loading award form...</div>';

        fetch(`/admin/projects/${projectId}/award?bid=${bidId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.text())
            .then(html => {
                document.getElementById('declareWinnerModalBody').innerHTML = html;
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('declareWinnerModalBody').innerHTML = '<div style="padding: 30px; color: #b91c1c; font-size: 14px;">Error loading award form.</div>';
            });
    }

    function closeDeclareWinnerModal() {
        document.getElementById('declareWinnerModal').style.display = 'none';
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

    function closeSuccessAlert() {
        const alert = document.getElementById('successAlert');
        if (alert) alert.style.display = 'none';
    }

    document.getElementById('awardViewModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAwardViewModal();
        }
    });

    document.getElementById('declareWinnerModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeclareWinnerModal();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const successAlert = document.getElementById('successAlert');
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.transition = 'opacity 0.5s';
                successAlert.style.opacity = '0';
                setTimeout(() => successAlert.style.display = 'none', 500);
            }, 5000);
        }
    });
</script>
