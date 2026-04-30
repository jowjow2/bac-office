<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard">
    @vite(['resources/css/dashboard.css'])

    <style>
        .bids-page,
        .bids-page * {
            font-family: 'Inter', sans-serif;
        }
    </style>

    <aside class="sidebar">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-logo-link"><h2 class="sidebar-logo">BAC-Office</h2></a>
        @include('partials.sidebar-profile')
        <ul class="sidebar-menu">
            <p class="menu-title">MAIN</p>
            <li><a href="{{ route('admin.dashboard') }}"><span class="menu-icon-dashboard" aria-hidden="true"></span> Dashboard</a></li>
            <li><a href="{{ route('admin.projects') }}"><i class="fas fa-folder-open"></i> Project/Biddings</a></li>
            <li><a href="{{ route('admin.bids') }}" class="active"><span class="menu-icon-all-bids" aria-hidden="true"></span> All Bids</a></li>
            <li><a href="{{ route('admin.awards') }}"><i class="fas fa-trophy"></i> Awards & Contracts</a></li>

            <p class="menu-title">MANAGEMENT</p>
            <li><a href="{{ route('admin.users') }}"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <li><a href="{{ route('admin.assignments') }}"><i class="fas fa-tasks"></i> Staff Assignments</a></li>
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

    <div class="main-area bids-page">
        <header class="navbar">
            <div class="nav-left">
                <h2>Bid Management</h2>
                <p>Review and evaluate all submitted bids</p>
            </div>
            <div class="nav-right"></div>
        </header>

        <main class="dashboard-content">
            <div class="welcome-text" style="margin-bottom: 18px;">
                <h1 class="title" style="font-size: 24px; font-weight: 600; color: #111827; margin-bottom: 6px;">Bid Management</h1>
                <p class="subtitle" style="font-size: 14px;">Review and evaluate all submitted bids</p>
            </div>

            @if(session('success'))
                <div id="successAlert" style="position: fixed; top: 90px; right: 25px; background: #dcfce7; color: #166534; padding: 16px 20px; border-radius: 8px; font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; display: flex; align-items: center; gap: 10px; min-width: 280px;">
                    <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                    <span>{{ session('success') }}</span>
                    <button onclick="closeSuccessAlert()" style="margin-left: auto; background: none; border: none; color: #166534; cursor: pointer; font-size: 16px;">&times;</button>
                </div>
            @endif

            <div class="table-container" style="background: white; border-radius: 18px; overflow: hidden; border: 1px solid #e5edf6; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);">
                <form method="GET" action="{{ route('admin.bids') }}" style="display: flex; gap: 12px; padding: 18px; border-bottom: 1px solid #edf2f7; background: #ffffff; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 260px;">
                        <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Search bids..." style="width: 100%; height: 42px; padding: 0 15px; border: 1px solid #d7e0ea; border-radius: 10px; font-size: 13px; color: #1f2937; outline: none;">
                    </div>
                    <div style="width: 150px;">
                        <select name="status" onchange="this.form.submit()" style="width: 100%; height: 42px; padding: 0 14px; border: 1px solid #d7e0ea; border-radius: 10px; font-size: 13px; color: #1f2937; background: white;">
                            <option value="">All Status</option>
                            <option value="pending" {{ ($status ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ ($status ?? '') === 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="rejected" {{ ($status ?? '') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>
                    <div style="width: 180px;">
                        <select name="project" onchange="this.form.submit()" style="width: 100%; height: 42px; padding: 0 14px; border: 1px solid #d7e0ea; border-radius: 10px; font-size: 13px; color: #1f2937; background: white;">
                            <option value="">All Projects</option>
                            @foreach(($projects ?? collect()) as $project)
                                <option value="{{ $project->id }}" {{ (string) ($projectFilter ?? '') === (string) $project->id ? 'selected' : '' }}>{{ $project->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>

                <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                    <thead>
                        <tr style="border-bottom: 1px solid #e9eef5; background: #ffffff;">
                            <th style="text-align: left; padding: 14px 18px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Bidder</th>
                            <th style="text-align: left; padding: 14px 18px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Project</th>
                            <th style="text-align: left; padding: 14px 18px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Bid Amount</th>
                            <th style="text-align: left; padding: 14px 18px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Budget</th>
                            <th style="text-align: left; padding: 14px 18px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Variance</th>
                            <th style="text-align: left; padding: 14px 18px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Submitted</th>
                            <th style="text-align: left; padding: 14px 18px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Status</th>
                            <th style="text-align: left; padding: 14px 18px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                            @forelse($bids as $bid)
                            @php
                                $budget = (float) ($bid->project->budget ?? 0);
                                $amount = (float) $bid->amount;
                                $variance = $budget > 0 ? (($amount - $budget) / $budget) * 100 : null;
                                $varianceColor = is_null($variance) ? '#64748b' : ($variance <= 0 ? '#047857' : '#dc2626');
                                $bidderName = $bid->user->company ?: ($bid->user->name ?? 'N/A');
                                $certificateProof = $bid->user->philgepsCertificate;
                                $certificateProofUrl = $certificateProof?->file_url;
                            @endphp
                            <tr style="border-bottom: 1px solid #eef3f8;">
                                <td style="padding: 18px; font-size: 13px; vertical-align: top;">
                                    <div style="font-size: 14px; font-weight: 600; color: #0f172a; margin-bottom: 6px;">{{ $bidderName }}</div>
                                    <div style="font-size: 12px; line-height: 1.5; color: #9ca3af;">{{ $bid->user->email ?? 'N/A' }}</div>
                                    @if($certificateProofUrl)
                                        <a href="{{ $certificateProofUrl }}" target="_blank" rel="noopener" style="display: inline-flex; margin-top: 8px; font-size: 12px; font-weight: 600; color: #1d4ed8; text-decoration: none;">View certificate proof</a>
                                    @else
                                        <div style="margin-top: 8px; font-size: 12px; color: #94a3b8;">No certificate proof uploaded</div>
                                    @endif
                                </td>
                                <td style="padding: 18px; font-size: 13px; font-weight: 500; color: #0f172a; line-height: 1.5; vertical-align: top;">{{ $bid->project->title ?? 'N/A' }}</td>
                                <td style="padding: 18px; font-size: 13px; font-weight: 500; color: #0f172a; vertical-align: top;">P{{ number_format($amount, 2) }}</td>
                                <td style="padding: 18px; font-size: 13px; font-weight: 500; color: #94a3b8; vertical-align: top;">P{{ number_format($budget, 2) }}</td>
                                <td style="padding: 18px; font-size: 13px; font-weight: 500; color: {{ $varianceColor }}; vertical-align: top;">{{ is_null($variance) ? 'N/A' : number_format($variance, 1) . '%' }}</td>
                                <td style="padding: 18px; font-size: 13px; font-weight: 400; color: #334155; vertical-align: top;">{{ $bid->created_at?->format('Y-m-d') }}</td>
                                <td style="padding: 18px; vertical-align: top;">
                                    <span style="display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 600;
                                        @if($bid->status == 'pending') background: #fef3c7; color: #a16207;
                                        @elseif($bid->status == 'approved') background: #dcfce7; color: #166534;
                                        @elseif($bid->status == 'rejected') background: #fee2e2; color: #991b1b;
                                        @else background: #e5e7eb; color: #475569; @endif">
                                        {{ strtolower($bid->status) }}
                                    </span>
                                </td>
                                <td style="padding: 18px; vertical-align: top;">
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                        <button type="button" onclick="loadBidViewModal({{ $bid->id }})" style="background: white; color: #334155; border: 1px solid #d6deea; padding: 8px 14px; border-radius: 9px; font-size: 12px; font-weight: 500; display: inline-block; cursor: pointer;">Details</button>
                                        @if($bid->status === 'pending')
                                            <form action="{{ route('admin.bid.approve', $bid) }}" method="POST" style="display: inline;">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" style="background: #16a34a; color: white; border: none; padding: 8px 14px; border-radius: 9px; font-size: 12px; font-weight: 600; cursor: pointer;">Approve</button>
                                            </form>
                                            <form action="{{ route('admin.bid.reject', $bid) }}" method="POST" style="display: inline;">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" style="background: #dc2626; color: white; border: none; padding: 8px 14px; border-radius: 9px; font-size: 12px; font-weight: 600; cursor: pointer;">Reject</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" style="padding: 40px; text-align: center; color: #9ca3af;">
                                    <i class="fas fa-gavel" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                                    No bids found for this search/filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<div id="bidViewModal" style="display: none; position: fixed; inset: 0; padding: 20px; background: rgba(15, 23, 42, 0.45); z-index: 10000; justify-content: center; align-items: center; box-sizing: border-box;">
    <div style="background: white; border-radius: 14px; width: min(680px, 100%); max-height: calc(100vh - 20px); overflow-y: auto; overflow-x: hidden; position: relative; box-shadow: 0 20px 44px rgba(15, 23, 42, 0.16); box-sizing: border-box;">
        <button onclick="closeBidViewModal()" style="position: absolute; top: 16px; right: 16px; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 9px; font-size: 18px; line-height: 1; cursor: pointer; color: #7c8ba1; z-index: 2;">&times;</button>
        <div id="bidViewModalBody"></div>
    </div>
</div>

<script>
    function loadBidViewModal(id) {
        document.getElementById('bidViewModal').style.display = 'flex';
        document.getElementById('bidViewModalBody').innerHTML = '<div style="padding: 28px; color: #64748b; font-size: 14px;">Loading bid details...</div>';

        fetch(`/admin/bids/${id}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.text())
            .then(html => {
                document.getElementById('bidViewModalBody').innerHTML = html;
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('bidViewModalBody').innerHTML = '<div style="padding: 28px; color: #b91c1c; font-size: 14px;">Error loading bid details.</div>';
            });
    }

    function closeBidViewModal() {
        document.getElementById('bidViewModal').style.display = 'none';
    }

    function closeSuccessAlert() {
        const alert = document.getElementById('successAlert');
        if (alert) {
            alert.style.display = 'none';
        }
    }

    document.getElementById('bidViewModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeBidViewModal();
        }
    });

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
</script>
