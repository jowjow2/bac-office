<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Award;
use App\Models\Assignment;
use App\Models\Bid;
use App\Models\Project;
use App\Models\User;
use App\Support\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard()
    {
        $totalUsers = User::count();
        $totalProjects = Project::count();
        $totalBids = Bid::count();
        $activeProjects = Project::where('status', 'open')->count();
        $awardedProjects = Award::count();
        $registeredBidders = User::where('role', 'bidder')->count();
        $staffMembers = User::where('role', 'staff')->count();
        $adminUsers = User::where('role', 'admin')->count();
        $pendingBids = Bid::where('status', 'pending')->count();
        $approvedBids = Bid::where('status', 'approved')->count();
        $rejectedBids = Bid::where('status', 'rejected')->count();
        $approvedForBiddingProjects = Project::where('status', 'approved_for_bidding')->count();
        $closedProjects = Project::where('status', 'closed')->count();
        $pendingRegistrationsCount = User::where('role', 'bidder')
            ->where('status', 'pending')
            ->count();

        $pendingRegistrations = User::where('role', 'bidder')
            ->where('status', 'pending')
            ->with('philgepsCertificate')
            ->latest()
            ->take(5)
            ->get();

        $notificationItems = SystemNotification::forUser(Auth::id(), 5);
        $unreadNotificationsCount = SystemNotification::unreadCount(Auth::id());
        $adminNotifications = $notificationItems->map(function ($notification) {
            return [
                'title' => $notification->title,
                'message' => $notification->message,
                'time' => $notification->created_at?->diffForHumans() ?? 'Recently',
                'is_read' => $notification->read_at !== null,
            ];
        })->values();

        $totalAwardedAmount = (float) Award::sum('contract_amount');
        $totalBudgetAllocated = (float) Project::sum('budget');

        $latestBids = Bid::with(['project', 'user.philgepsCertificate'])
            ->latest()
            ->take(5)
            ->get();

        $recentProjects = Project::withCount('bids')
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard.admin', compact(
            'totalUsers',
            'totalProjects',
            'totalBids',
            'activeProjects',
            'awardedProjects',
            'registeredBidders',
            'staffMembers',
            'adminUsers',
            'pendingBids',
            'approvedBids',
            'rejectedBids',
            'approvedForBiddingProjects',
            'closedProjects',
            'pendingRegistrationsCount',
            'pendingRegistrations',
            'adminNotifications',
            'unreadNotificationsCount',
            'totalAwardedAmount',
            'totalBudgetAllocated',
            'latestBids',
            'recentProjects'
        ));
    }

    public function projects(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $projects = Project::withCount('bids')
            ->with(['assignments.staff'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['approved_for_bidding', 'open', 'closed', 'awarded'], true), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest()
            ->get();

        return view('admin.projects', compact('projects', 'search', 'status'));
    }

    public function storeProject(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'budget' => 'required|numeric|min:0|lte:9999999999999.99',
            'status' => 'required|in:approved_for_bidding,open,closed,awarded',
            'deadline' => 'required|date|after:today',
        ], [
            'budget.lte' => 'Budget must not exceed 9,999,999,999,999.99.',
        ]);

        Project::create($validated);

        return redirect()->route('admin.projects')->with('success', 'Project created successfully!');
    }

    public function allBids(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $projectFilter = trim((string) $request->query('project', ''));

        $bids = Bid::with(['project', 'user.philgepsCertificate', 'award'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->whereHas('project', function ($projectQuery) use ($search) {
                            $projectQuery->where('title', 'like', "%{$search}%");
                        })
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('company', 'like', "%{$search}%");
                        });
                });
            })
            ->when(in_array($status, ['pending', 'approved', 'rejected'], true), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($projectFilter !== '' && ctype_digit($projectFilter), function ($query) use ($projectFilter) {
                $query->where('project_id', (int) $projectFilter);
            })
            ->latest()
            ->get();

        $projects = Project::orderBy('title')->get(['id', 'title']);

        return view('admin.bids', compact('bids', 'projects', 'search', 'status', 'projectFilter'));
    }
    public function users(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $filter = trim((string) $request->query('filter', 'all'));

        $users = User::query()
            ->when($filter === 'admin', fn ($query) => $query->where('role', 'admin'))
            ->when($filter === 'staff', fn ($query) => $query->where('role', 'staff'))
            ->when($filter === 'bidder', fn ($query) => $query->where('role', 'bidder'))
            ->when($filter === 'pending', fn ($query) => $query->where('status', 'pending'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('role', 'like', "%{$search}%")
                    ->orWhere('office', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('registration_no', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->get();

        $roleCounts = [
            'admin' => User::where('role', 'admin')->count(),
            'staff' => User::where('role', 'staff')->count(),
            'bidder' => User::where('role', 'bidder')->count(),
        ];

        $statusCounts = [
            'active' => User::where('status', 'active')->count(),
            'pending' => User::where('status', 'pending')->count(),
            'rejected' => User::where('status', 'rejected')->count(),
        ];

        return view('admin.users', compact('users', 'search', 'filter', 'roleCounts', 'statusCounts'));
    }

    public function storeUser(Request $request)
    {
        $validated = $this->validateUser($request);

        User::create($validated);

        return redirect()->route('admin.users')->with('success', 'User created successfully.');
    }

    public function updateUser(Request $request, User $user)
    {
        $validated = $this->validateUser($request, $user);

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        $user->update($validated);

        return redirect()->route('admin.users')->with('success', 'User updated successfully.');
    }

    public function approveUser(User $user)
    {
        $user->update(['status' => 'active']);

        SystemNotification::createForUser(
            $user->id,
            'Account approved',
            'Your bidder account has been approved. You can now sign in and participate in bidding.',
            'account_approved'
        );

        return redirect()->route('admin.users')->with('success', 'User approved successfully.');
    }

    public function rejectUser(User $user)
    {
        if ((int) $user->id === (int) Auth::id()) {
            return redirect()
                ->route('admin.users')
                ->withErrors(['status' => 'You cannot reject your own signed-in account.']);
        }

        $user->update(['status' => 'rejected']);

        SystemNotification::createForUser(
            $user->id,
            'Account rejected',
            'Your bidder account registration was rejected. Please contact the administrator for details.',
            'account_rejected'
        );

        return redirect()->route('admin.users')->with('success', 'User rejected successfully.');
    }

    public function destroyUser(User $user)
    {
        if ((int) $user->id === (int) Auth::id()) {
            return redirect()
                ->route('admin.users')
                ->withErrors(['delete' => 'You cannot delete your own account while signed in.']);
        }

        $user->delete();

        return redirect()->route('admin.users')->with('success', 'User deleted successfully.');
    }

    public function assignments()
    {
        $staffMembers = User::where('role', 'staff')
            ->where('status', 'active')
            ->with(['assignments.project'])
            ->orderBy('name')
            ->get();

        $projects = Project::orderBy('title')->get();

        $assignments = Assignment::with(['staff', 'project'])
            ->latest()
            ->get();

        $staffMembers = $staffMembers->map(function (User $staff) use ($projects) {
            $assignedProjectIds = $staff->assignments
                ->pluck('project_id')
                ->all();

            $staff->setAttribute(
                'available_projects',
                $projects->whereNotIn('id', $assignedProjectIds)->values()
            );

            return $staff;
        });

        return view('admin.assignments', compact('staffMembers', 'projects', 'assignments'));
    }

    public function storeAssignment(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'exists:users,id'],
            'project_id' => ['required', 'exists:projects,id'],
            'role_in_project' => ['nullable', 'string', 'max:255'],
        ]);

        $staff = User::findOrFail($validated['staff_id']);

        if ($staff->role !== 'staff') {
            return redirect()
                ->route('admin.assignments')
                ->withErrors(['staff_id' => 'Only staff users can be assigned to projects.']);
        }

        $exists = Assignment::where('staff_id', $validated['staff_id'])
            ->where('project_id', $validated['project_id'])
            ->exists();

        if ($exists) {
            return redirect()
                ->route('admin.assignments')
                ->withErrors(['staff_id' => 'This staff member is already assigned to the selected project.']);
        }

        $project = Project::findOrFail($validated['project_id']);
        $assignment = Assignment::create($validated);

        $projectTitle = $project->title ?: 'a project';
        $roleInProject = trim((string) ($validated['role_in_project'] ?? ''));

        SystemNotification::createForUser(
            $staff->id,
            'New project assignment',
            $roleInProject !== ''
                ? 'You have been assigned to ' . $projectTitle . ' as ' . $roleInProject . '.'
                : 'You have been assigned to ' . $projectTitle . '.',
            'staff_assignment',
            [
                'project_id' => $project->id,
                'assignment_id' => $assignment->id,
            ]
        );

        return redirect()->route('admin.assignments')->with('success', 'Staff assigned successfully.');
    }

    public function destroyAssignment(Assignment $assignment)
    {
        $assignment->delete();

        return redirect()->route('admin.assignments')->with('success', 'Staff assignment removed successfully.');
    }

    public function viewBid(Request $request, Bid $bid)
    {
        $bid->load(['project', 'user.philgepsCertificate', 'award']);

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.bid-view-modal', compact('bid'));
        }

        return view('admin.bid-view', compact('bid'));
    }

    public function editBid(Bid $bid)
    {
        $bid->load(['project', 'user']);

        return view('admin.bid-edit', compact('bid'));
    }

    public function updateBid(Request $request, Bid $bid)
    {
        $validated = $request->validate([
            'bid_amount' => 'required|numeric|min:0',
            'status' => 'required|in:pending,approved,rejected',
            'notes' => 'nullable|string',
        ]);

        $bid->update($validated);

        return redirect()->route('admin.bids')->with('success', 'Bid updated successfully!');
    }

    public function approveBid(Bid $bid)
    {
        $bid->update(['status' => 'approved']);

        SystemNotification::createForUser(
            $bid->user_id,
            'Bid approved',
            'Your bid for ' . ($bid->project->title ?? 'the project') . ' has been approved for evaluation.',
            'bid_approved',
            ['project_id' => $bid->project_id, 'bid_id' => $bid->id]
        );

        return redirect()->route('admin.bids')->with('success', 'Bid approved successfully.');
    }

    public function rejectBid(Bid $bid)
    {
        $bid->update(['status' => 'rejected']);

        SystemNotification::createForUser(
            $bid->user_id,
            'Bid rejected',
            'Your bid for ' . ($bid->project->title ?? 'the project') . ' has been rejected.',
            'bid_rejected',
            ['project_id' => $bid->project_id, 'bid_id' => $bid->id]
        );

        return redirect()->route('admin.bids')->with('success', 'Bid rejected successfully.');
    }
    public function viewProject(Project $project)
    {
        $project->loadCount('bids');
        $project->load(['assignments.staff']);

        return view('admin.project-view', compact('project'));
    }

    public function editProject(Project $project)
    {
        $project->loadCount('bids');
        $project->load(['assignments']);

        $staffMembers = User::where('role', 'staff')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $currentAssignment = $project->assignments->first();

        return view('admin.project-edit', compact('project', 'staffMembers', 'currentAssignment'));
    }

    public function updateProject(Request $request, Project $project)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'budget' => 'required|numeric|min:0|lte:9999999999999.99',
            'status' => 'required|in:approved_for_bidding,open,closed,awarded',
            'deadline' => 'required|date',
            'staff_id' => 'nullable|exists:users,id',
        ], [
            'budget.lte' => 'Budget must not exceed 9,999,999,999,999.99.',
        ]);

        $staffId = $validated['staff_id'] ?? null;
        unset($validated['staff_id']);

        $project->update($validated);

        if ($staffId) {
            Assignment::updateOrCreate(
                ['project_id' => $project->id],
                ['staff_id' => $staffId]
            );
        } else {
            Assignment::where('project_id', $project->id)->delete();
        }

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['success' => true, 'message' => 'Project updated successfully!']);
        }

        return redirect()->route('admin.projects')->with('success', 'Project updated successfully!');
    }

    public function destroyProject(Request $request, Project $project)
    {
        $project->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Project deleted successfully.',
            ]);
        }

        return redirect()->route('admin.projects')->with('success', 'Project deleted successfully.');
    }

    public function awards()
    {
        $awards = Award::with(['project', 'bid.user'])->latest()->get();
        $readyProjects = Project::with([
                'bids' => function ($query) {
                    $query->with('user')->orderBy('bid_amount');
                },
            ])
            ->where('status', 'closed')
            ->whereDoesntHave('awards')
            ->whereHas('bids')
            ->latest('updated_at')
            ->get();

        return view('admin.awards', compact('awards', 'readyProjects'));
    }

    public function viewAward(Request $request, Award $award)
    {
        $award->load(['project', 'bid.user']);

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.award-view-modal', compact('award'));
        }

        return view('admin.award-view', compact('award'));
    }
    public function reports()
    {
        return view('admin.reports', $this->buildReportsData());
    }

    public function exportReportsCsv()
    {
        $report = $this->buildReportsData();
        $filename = 'bac-office-reports-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['BAC Office Reports & Analytics']);
            fputcsv($handle, ['Generated At', now()->format('M d, Y h:i A')]);
            fputcsv($handle, []);

            fputcsv($handle, ['KPI Summary']);
            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Total Budget Allocated', $report['totalBudgetAllocated']]);
            fputcsv($handle, ['Total Awarded', $report['totalAwardedAmount']]);
            fputcsv($handle, ['Government Savings', $report['governmentSavings']]);
            fputcsv($handle, ['Bid Participation', $report['bidParticipation']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Project Summary Report']);
            fputcsv($handle, ['Project', 'Budget', 'Bids', 'Awarded', 'Status']);
            foreach ($report['projectSummary'] as $project) {
                fputcsv($handle, [
                    $project->title,
                    $project->budget,
                    $project->bids_count,
                    $project->awarded_amount ?: 0,
                    $project->status,
                ]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Bidder Performance']);
            fputcsv($handle, ['Bidder', 'Total Bids', 'Approved', 'Won']);
            foreach ($report['bidderPerformance'] as $bidder) {
                fputcsv($handle, [
                    $bidder->bidder_name,
                    $bidder->total_bids,
                    $bidder->approved_bids,
                    $bidder->won_bids,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function printReports()
    {
        return Pdf::loadView('admin.reports-print', $this->buildReportsData())
            ->setPaper('a4')
            ->download('admin-reports-' . now()->format('Y-m-d') . '.pdf');
    }

    public function notifications(Request $request)
    {
        $notificationItems = SystemNotification::forUser(Auth::id(), 30);
        $notifications = $notificationItems
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'time' => $notification->created_at?->diffForHumans() ?? 'Recently',
                    'is_read' => $notification->read_at !== null,
                ];
            })
            ->all();

        return view('admin.notifications', [
            'notifications' => $notifications,
            'unreadNotificationsCount' => $notificationItems->whereNull('read_at')->count(),
        ]);
    }

    public function markNotificationRead(Request $request, string $notificationId)
    {
        SystemNotification::markRead(Auth::id(), (int) $notificationId);

        return redirect()->route('admin.notifications');
    }

    public function markAllNotificationsRead(Request $request)
    {
        SystemNotification::markAllRead(Auth::id());

        return redirect()->route('admin.notifications');
    }

    public function createAward(Request $request, Project $project)
    {
        $project->load('bids.user');
        $bids = $project->bids
            ->whereIn('status', ['approved', 'pending'])
            ->sortBy('bid_amount')
            ->values();
        $selectedBidId = $request->integer('bid');

        return view('admin.award-create', compact('project', 'bids', 'selectedBidId'));
    }

    public function storeAward(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'bid_id' => 'required|exists:bids,id',
            'contract_amount' => 'required|numeric|min:0',
            'contract_date' => 'required|date',
            'status' => 'required|in:active,completed',
            'notes' => 'nullable|string',
        ]);

        $bid = Bid::with('project')->findOrFail($validated['bid_id']);

        if ((int) $bid->project_id !== (int) $validated['project_id']) {
            if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'message' => 'The selected bid does not belong to this project.',
                    'errors' => [
                        'bid_id' => ['The selected bid does not belong to this project.'],
                    ],
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['bid_id' => 'The selected bid does not belong to this project.']);
        }

        if (Award::where('project_id', $validated['project_id'])->exists()) {
            if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'message' => 'This project already has an award record.',
                ], 422);
            }

            return redirect()
                ->route('admin.awards')
                ->with('success', 'This project already has an award record.');
        }

        Award::create($validated);
        $project = Project::findOrFail($validated['project_id']);
        $project->update(['status' => 'awarded']);
        $bid->update(['status' => 'approved']);

        SystemNotification::createForUser(
            $bid->user_id,
            'Contract awarded',
            'Congratulations! Your bid for ' . $project->title . ' has been declared the winning bid.',
            'award_won',
            ['project_id' => $project->id, 'bid_id' => $bid->id]
        );

        $otherBidderIds = Bid::where('project_id', $validated['project_id'])
            ->whereKeyNot($bid->id)
            ->pluck('user_id');

        Bid::where('project_id', $validated['project_id'])
            ->whereKeyNot($bid->id)
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);

        SystemNotification::createForUsers(
            $otherBidderIds,
            'Award decision released',
            'The project ' . $project->title . ' has already been awarded to another bidder.',
            'award_decision',
            ['project_id' => $project->id]
        );

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'success' => true,
                'message' => 'Award created successfully!',
            ]);
        }

        return redirect()->route('admin.awards')->with('success', 'Award created successfully!');
    }

    protected function validateUser(Request $request, ?User $user = null): array
    {
        $passwordRule = $user
            ? ['nullable', 'string', 'min:6']
            : ['required', 'string', 'min:6'];

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(['admin', 'staff', 'bidder'])],
            'status' => ['required', Rule::in(['active', 'pending', 'rejected'])],
            'office' => [
                Rule::excludeIf($request->input('role') !== 'staff'),
                'required',
                'string',
                'max:255',
                Rule::in(User::staffOfficeOptions()),
            ],
            'password' => $passwordRule,
            'company' => ['nullable', 'string', 'max:255'],
            'registration_no' => ['nullable', 'string', 'max:255'],
        ]);
    }

    protected function buildReportsData(): array
    {
        $totalUsers = User::count();
        $totalProjects = Project::count();
        $totalBids = Bid::count();
        $totalAwards = Award::count();
        $totalAssignments = Assignment::count();

        $projectStatusCounts = [
            'approved_for_bidding' => Project::where('status', 'approved_for_bidding')->count(),
            'open' => Project::where('status', 'open')->count(),
            'closed' => Project::where('status', 'closed')->count(),
            'awarded' => Project::where('status', 'awarded')->count(),
        ];

        $bidStatusCounts = [
            'pending' => Bid::where('status', 'pending')->count(),
            'approved' => Bid::where('status', 'approved')->count(),
            'rejected' => Bid::where('status', 'rejected')->count(),
        ];

        $userRoleCounts = [
            'admin' => User::where('role', 'admin')->count(),
            'staff' => User::where('role', 'staff')->count(),
            'bidder' => User::where('role', 'bidder')->count(),
        ];

        $userStatusCounts = [
            'active' => User::where('status', 'active')->count(),
            'pending' => User::where('status', 'pending')->count(),
            'rejected' => User::where('status', 'rejected')->count(),
        ];

        $projectsWithAssignments = Assignment::distinct('project_id')->count('project_id');
        $totalContractAmount = (float) Award::sum('contract_amount');
        $averageBidAmount = (float) Bid::avg('bid_amount');
        $totalBudgetAllocated = (float) Project::sum('budget');
        $awardedProjectBudget = (float) Project::where('status', 'awarded')->sum('budget');
        $governmentSavings = max($awardedProjectBudget - $totalContractAmount, 0);

        $projectSummary = Project::query()
            ->withCount('bids')
            ->leftJoin('awards', 'awards.project_id', '=', 'projects.id')
            ->select('projects.*', DB::raw('COALESCE(awards.contract_amount, 0) as awarded_amount'))
            ->orderByDesc('projects.created_at')
            ->take(8)
            ->get();

        $bidderPerformance = User::query()
            ->where('role', 'bidder')
            ->leftJoin('bids', 'bids.user_id', '=', 'users.id')
            ->leftJoin('awards', 'awards.bid_id', '=', 'bids.id')
            ->selectRaw("
                users.id,
                COALESCE(NULLIF(users.company, ''), users.name) as bidder_name,
                COUNT(DISTINCT bids.id) as total_bids,
                SUM(CASE WHEN bids.status = 'approved' THEN 1 ELSE 0 END) as approved_bids,
                SUM(CASE WHEN awards.id IS NOT NULL THEN 1 ELSE 0 END) as won_bids
            ")
            ->groupBy('users.id', 'users.company', 'users.name')
            ->orderByDesc('total_bids')
            ->orderBy('bidder_name')
            ->take(8)
            ->get();

        $recentAwards = Award::with(['project', 'bid.user'])
            ->latest()
            ->take(5)
            ->get();

        $recentBids = Bid::with(['project', 'user'])
            ->latest()
            ->take(5)
            ->get();

        $staffWorkload = User::where('role', 'staff')
            ->withCount('assignments')
            ->orderByDesc('assignments_count')
            ->orderBy('name')
            ->take(5)
            ->get();

        return compact(
            'totalUsers',
            'totalProjects',
            'totalBids',
            'totalAwards',
            'totalAssignments',
            'projectStatusCounts',
            'bidStatusCounts',
            'userRoleCounts',
            'userStatusCounts',
            'projectsWithAssignments',
            'totalContractAmount',
            'averageBidAmount',
            'projectSummary',
            'bidderPerformance',
            'recentAwards',
            'recentBids',
            'staffWorkload',
            'totalBudgetAllocated',
            'governmentSavings'
        ) + [
            'totalAwardedAmount' => $totalContractAmount,
            'bidParticipation' => $totalBids,
        ];
    }

}
