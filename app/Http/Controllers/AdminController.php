<?php

namespace App\Http\Controllers;

use App\Mail\BidderApprovedQrMail;
use App\Mail\BidderRejectedMail;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Award;
use App\Models\Assignment;
use App\Models\Bid;
use App\Models\Bidder;
use App\Models\BidderDocument;
use App\Models\Project;
use App\Models\QrLoginToken;
use App\Models\User;
use App\Support\DocumentPreview;
use App\Support\QrLoginService;
use App\Support\Uploads;
use App\Support\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
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

        $uploadedApprovedBids = Bid::with(['project', 'user'])
            ->where('status', 'approved')
            ->whereNotNull('proposal_file')
            ->where('proposal_file', '!=', '')
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
            'uploadedApprovedBids',
            'recentProjects'
        ));
    }

    public function projects(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $projects = Project::withCount('bids')
            ->with(['assignments.staff', 'documents'])
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
            'document_files' => 'nullable|array',
            'document_files.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:20480',
            'document_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:20480',
            'budget' => 'required|numeric|min:0|lte:9999999999999.99',
            'status' => 'required|in:approved_for_bidding,open,closed,awarded',
            'deadline' => 'required|date|after:today',
        ], [
            'budget.lte' => 'Budget must not exceed 9,999,999,999,999.99.',
        ]);

        $documentFiles = $this->extractProjectDocumentFiles($request);
        unset($validated['document_files']);
        unset($validated['document_file']);

        $project = Project::create($validated);
        $this->storeProjectDocuments($project, $documentFiles);

        return redirect()->route('admin.projects')->with('success', 'Project created successfully!');
    }

    public function allBids(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $projectFilter = trim((string) $request->query('project', ''));
        $proposalFilter = trim((string) $request->query('proposal', ''));

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
            ->when($proposalFilter === 'uploaded', function ($query) {
                $query->whereNotNull('proposal_file')->where('proposal_file', '!=', '');
            })
            ->when($proposalFilter === 'missing', function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->whereNull('proposal_file')->orWhere('proposal_file', '');
                });
            })
            ->latest()
            ->get();

        $projects = Project::orderBy('title')->get(['id', 'title']);

        return view('admin.bids', compact('bids', 'projects', 'search', 'status', 'projectFilter', 'proposalFilter'));
    }
    public function users(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $filter = trim((string) $request->query('filter', 'all'));
        $bidderApprovalAvailable = $this->bidderApprovalFeaturesAvailable();

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
                    ->orWhere('username', 'like', "%{$search}%")
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

        return view('admin.users', compact('users', 'search', 'filter', 'roleCounts', 'statusCounts', 'bidderApprovalAvailable'));
    }

    public function reviewUser(User $user)
    {
        abort_unless($user->role === 'bidder', 404);

        if (! $this->bidderApprovalFeaturesAvailable()) {
            return redirect()
                ->route('admin.users')
                ->with('warning', 'Bidder review tools are unavailable because the bidder approval table is missing in the current database.');
        }

        $user->load([
            'bidderProfile.approver',
            'registrationDocuments',
            'bidderDocuments' => fn ($query) => $query->orderBy('document_type'),
            'qrLoginTokens' => fn ($query) => $query->latest(),
            'loginLogs' => fn ($query) => $query->latest('created_at')->limit(20),
        ]);

        $registrationDocuments = $user->registrationDocuments;
        $supportingDocuments = $user->bidderDocuments
            ->reject(fn (BidderDocument $document) => str_starts_with($document->document_type, 'Registration Requirement '))
            ->values();
        $latestQrToken = $user->qrLoginTokens->first();
        $qrPreviewDataUri = null;

        if ($latestQrToken) {
            $existingQrArtifacts = app(QrLoginService::class)->buildArtifactsForToken($latestQrToken, 220);
            $qrPreviewDataUri = is_array($existingQrArtifacts) ? ($existingQrArtifacts['data_uri'] ?? null) : null;
        }

        return view('admin.bidder-review', compact(
            'user',
            'registrationDocuments',
            'supportingDocuments',
            'latestQrToken',
            'qrPreviewDataUri',
        ));
    }

    public function previewUserQr(User $user, QrLoginService $qrLoginService)
    {
        abort_unless($user->role === 'bidder', 404);
        abort_unless($this->bidderApprovalFeaturesAvailable(), 404);
        abort_unless($user->isApprovedBidder(), 403);

        $qrIssue = $this->resolveBidderQrIssue($user, $qrLoginService);
        $filename = 'bac-office-bidder-' . $user->id . '-qr.svg';

        return response($qrIssue['svg'], 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadUserQr(User $user, QrLoginService $qrLoginService)
    {
        abort_unless($user->role === 'bidder', 404);
        abort_unless($this->bidderApprovalFeaturesAvailable(), 404);
        abort_unless($user->isApprovedBidder(), 403);

        $qrIssue = $this->resolveBidderQrIssue($user, $qrLoginService);
        $filename = 'bac-office-bidder-' . $user->id . '-qr.svg';

        return response($qrIssue['svg'], 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function previewBidderDocument(User $user, BidderDocument $document)
    {
        abort_unless($user->role === 'bidder', 404);
        abort_unless((int) $document->user_id === (int) $user->id, 404);

        return redirect()->route('admin.user.document.pdf', ['user' => $user, 'document' => $document]);
    }

    public function streamBidderDocumentPdf(User $user, BidderDocument $document)
    {
        abort_unless($user->role === 'bidder', 404);
        abort_unless((int) $document->user_id === (int) $user->id, 404);

        return $this->streamDocumentPdfPreview(
            $document->file_path,
            $document->display_name,
            $document->document_type
        );
    }

    public function storeUser(Request $request)
    {
        $validated = $this->validateUser($request);

        $user = User::create($validated);
        $this->syncBidderProfile($user);

        return redirect()->route('admin.users')->with('success', 'User created successfully.');
    }

    public function updateUser(Request $request, User $user)
    {
        $validated = $this->validateUser($request, $user);

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        $user->update($validated);
        $this->syncBidderProfile($user);

        return redirect()->route('admin.users')->with('success', 'User updated successfully.');
    }

public function approveUser(User $user)
    {
        abort_unless($user->role === 'bidder', 404);

        if (! $this->bidderApprovalFeaturesAvailable()) {
            return redirect()
                ->route('admin.users')
                ->with('warning', 'Bidder approval is unavailable because the bidder approval table is missing in the current database.');
        }

        DB::transaction(function () use ($user): void {
            $bidder = $this->ensureBidderProfile($user);

            $user->forceFill([
                'status' => 'active',
                'company' => $bidder->company_name,
            ])->save();

            $bidder->forceFill([
                'approval_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => Auth::id(),
            ])->save();
        });

        $user->refresh()->load('bidderProfile');
        $this->sendBidderApprovedQrEmail($user, app(QrLoginService::class));

        SystemNotification::createForUser(
            $user->id,
            'Account approved',
            'Your bidder account has been approved. You can now scan your QR code or sign in with your password.',
            'account_approved'
        );

        $redirect = redirect()
            ->route('admin.users.review', $user)
            ->with('success', 'Bidder approved successfully.');

        if (config('mail.default') === 'log') {
            $redirect->with(
                'warning',
                'QR email delivery is currently set to log mode. The message was written to storage/logs/laravel.log instead of being sent to the bidder inbox.'
            );
        }

        return $redirect;
    }

    public function resendUserQrEmail(User $user, QrLoginService $qrLoginService)
    {
        abort_unless($user->role === 'bidder', 404);

        if (! $this->bidderApprovalFeaturesAvailable()) {
            return redirect()
                ->route('admin.users.review', $user)
                ->with('warning', 'QR email resend is unavailable because the bidder approval tables are missing in the current database.');
        }

        if (! $user->isApprovedBidder()) {
            return redirect()
                ->route('admin.users.review', $user)
                ->with('warning', 'Only approved bidder accounts can receive QR login emails.');
        }

        $user->loadMissing('bidderProfile');
        $this->sendBidderApprovedQrEmail($user, $qrLoginService);

        $redirect = redirect()
            ->route('admin.users.review', $user)
            ->with('success', 'QR email resent successfully.');

        if (config('mail.default') === 'log') {
            $redirect->with(
                'warning',
                'QR email delivery is currently set to log mode. The message was written to storage/logs/laravel.log instead of being sent to the bidder inbox.'
            );
        }

        return $redirect;
    }

public function rejectUser(Request $request, User $user)
    {
        if ((int) $user->id === (int) Auth::id()) {
            return redirect()
                ->route('admin.users')
                ->withErrors(['status' => 'You cannot reject your own signed-in account.']);
        }

        abort_unless($user->role === 'bidder', 404);

        if (! $this->bidderApprovalFeaturesAvailable()) {
            return redirect()
                ->route('admin.users')
                ->with('warning', 'Bidder rejection is unavailable because the bidder approval table is missing in the current database.');
        }

        $reason = trim((string) $request->input('rejection_reason'));
        $bidder = null;

        DB::transaction(function () use ($user, &$bidder): void {
            $bidder = $this->ensureBidderProfile($user);

            $user->forceFill([
                'status' => 'rejected',
            ])->save();

            $bidder->forceFill([
                'approval_status' => 'rejected',
                'approved_at' => null,
                'approved_by' => Auth::id(),
            ])->save();
        });

        app(QrLoginService::class)->revokeAllForUser($user);

        Mail::to($user->email)->send(new BidderRejectedMail($user, $bidder, $reason !== '' ? $reason : null));

        SystemNotification::createForUser(
            $user->id,
            'Account rejected',
            'Your bidder account registration was rejected. Please check your email for the BAC Office update.',
            'account_rejected'
        );

        return redirect()
            ->route('admin.users.review', $user)
            ->with('success', 'Bidder registration rejected and email notification sent.');
    }

public function destroyUser(User $user)
    {
        if ((int) $user->id === (int) Auth::id()) {
            return redirect()
                ->route('admin.users')
                ->withErrors(['delete' => 'You cannot delete your own account while signed in.']);
        }

        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return redirect()
                ->route('admin.users')
                ->withErrors(['delete' => 'You cannot delete the last remaining admin account.']);
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

    public function previewBidDocument(Bid $bid, string $document)
    {
        $bid->loadMissing('user.philgepsCertificate');
        $documentMeta = $this->bidDocumentMeta($bid, $document);

        abort_unless(filled($documentMeta['path']), 404);

        return redirect()->route('admin.bid.document.pdf', ['bid' => $bid, 'document' => $document]);
    }

    public function streamBidDocumentPdf(Bid $bid, string $document)
    {
        $bid->loadMissing(['project', 'user.philgepsCertificate']);
        $documentMeta = $this->bidDocumentMeta($bid, $document);

        abort_unless(filled($documentMeta['path']), 404);

        return $this->streamDocumentPdfPreview(
            $documentMeta['path'],
            $documentMeta['display_name'],
            $documentMeta['label']
        );
    }

    public function streamProjectDocumentPdf(Project $project, string $document)
    {
        $project->loadMissing('documents');
        $documentMeta = $this->projectDocumentMeta($project, $document);

        abort_unless(filled($documentMeta['path']), 404);

        return $this->streamDocumentPdfPreview(
            $documentMeta['path'],
            $documentMeta['display_name'],
            $documentMeta['label']
        );
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
        $project->load(['assignments.staff', 'documents']);

        return view('admin.project-view', compact('project'));
    }

    public function projectFiles(Project $project)
    {
        $project->load('documents');

        return view('admin.project-files', compact('project'));
    }

    public function destroyProjectDocument(Request $request, Project $project, string $document)
    {
        $deletedDocument = $this->deleteProjectDocument($project, $document);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Project file deleted successfully.',
                'deleted_name' => $deletedDocument['display_name'],
                'remaining_count' => $deletedDocument['remaining_count'],
            ]);
        }

        return redirect()
            ->route('admin.projects')
            ->with('success', 'Project file deleted successfully.');
    }

    public function editProject(Project $project)
    {
        $project->loadCount('bids');
        $project->load(['assignments', 'documents']);

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
            'document_files' => 'nullable|array',
            'document_files.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:20480',
            'document_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:20480',
            'budget' => 'required|numeric|min:0|lte:9999999999999.99',
            'status' => 'required|in:approved_for_bidding,open,closed,awarded',
            'deadline' => 'required|date',
            'staff_id' => 'nullable|exists:users,id',
        ], [
            'budget.lte' => 'Budget must not exceed 9,999,999,999,999.99.',
        ]);

        $staffId = $validated['staff_id'] ?? null;
        $documentFiles = $this->extractProjectDocumentFiles($request);
        unset($validated['staff_id']);
        unset($validated['document_files']);
        unset($validated['document_file']);

        $project->update($validated);
        $this->storeProjectDocuments($project, $documentFiles);

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
            'username' => ['nullable', 'string', 'min:4', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user?->id)],
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
        ], [
            'username.regex' => 'Username may only contain letters, numbers, dots, dashes, and underscores.',
        ]);
    }

    protected function bidDocumentMeta(Bid $bid, string $document): array
    {
        return match ($document) {
            'proposal' => [
                'label' => 'Proposal File',
                'path' => $bid->proposal_file,
                'display_name' => $bid->proposal_filename,
            ],
            'certificate' => [
                'label' => 'Certificate Proof',
                'path' => $bid->user->philgepsCertificate?->file_path,
                'display_name' => $bid->user->philgepsCertificate?->display_name,
            ],
            default => abort(404),
        };
    }

    protected function projectDocumentMeta(Project $project, string $document): array
    {
        abort_unless(ctype_digit($document), 404);

        $projectDocument = $project->uploadedDocuments()->values()->get((int) $document);

        abort_unless($projectDocument !== null, 404);

        return [
            'label' => 'Project File',
            'path' => $projectDocument->file_path,
            'display_name' => $projectDocument->display_name,
        ];
    }

    protected function deleteProjectDocument(Project $project, string $document): array
    {
        $project->loadMissing('documents');
        $documentMeta = $this->projectDocumentMeta($project, $document);
        $path = $documentMeta['path'];

        DB::transaction(function () use ($project, $path) {
            $projectDocument = $project->documents()->where('file_path', $path)->first();

            if ($projectDocument) {
                $projectDocument->delete();
            }

            if ($project->document_path === $path) {
                $nextDocument = $project->documents()
                    ->where('file_path', '!=', $path)
                    ->orderBy('id')
                    ->first();

                $project->forceFill([
                    'document_path' => $nextDocument?->file_path,
                    'document_original_name' => $nextDocument?->original_name,
                ])->save();
            }
        });

        Uploads::delete($path);

        $project->refresh()->load('documents');

        return [
            'display_name' => $documentMeta['display_name'] ?? 'document',
            'remaining_count' => $project->uploadedDocuments()->count(),
        ];
    }

    protected function streamDocumentPdfPreview(string $path, ?string $displayName, string $documentLabel)
    {
        $resolvedDisplayName = Uploads::fileName($path, $displayName) ?? 'document';
        $pdfFilename = $this->pdfPreviewFilename($resolvedDisplayName);

        if (Uploads::extension($path, $resolvedDisplayName) === 'pdf') {
            $contents = Uploads::contents($path);

            if (is_string($contents) && $contents !== '') {
                return response($contents, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $pdfFilename . '"',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }
        }

        $preview = DocumentPreview::forUpload($path, $resolvedDisplayName);

        return Pdf::loadView('admin.bid-document-pdf', [
            'preview' => $preview,
            'documentLabel' => $documentLabel,
        ])->setPaper('a4')->stream($pdfFilename, ['Attachment' => false]);
    }

    protected function pdfPreviewFilename(string $displayName): string
    {
        $baseName = pathinfo($displayName, PATHINFO_FILENAME) ?: 'document';
        $safeName = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $baseName), '-');

        return ($safeName !== '' ? $safeName : 'document') . '.pdf';
    }

    protected function ensureBidderProfile(User $user): Bidder
    {
        $registrationDocumentPath = $user->registrationDocuments()->orderBy('id')->value('file_path');

        return $user->bidderProfile()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'company_name' => $user->company ?: $user->name,
                'contact_number' => 'Not provided',
                'business_address' => 'Not provided',
                'document_path' => $registrationDocumentPath,
                'approval_status' => $user->status === 'active' ? 'approved' : 'pending',
                'approved_at' => $user->status === 'active' ? now() : null,
                'approved_by' => $user->status === 'active' ? Auth::id() : null,
            ]
        );
    }

    protected function syncBidderProfile(User $user): void
    {
        if ($user->role !== 'bidder' || ! $this->bidderApprovalFeaturesAvailable()) {
            return;
        }

        $bidder = $this->ensureBidderProfile($user);

        $bidder->forceFill([
            'company_name' => $user->company ?: $bidder->company_name ?: $user->name,
            'approval_status' => $user->status === 'active'
                ? 'approved'
                : ($user->status === 'rejected' ? 'rejected' : 'pending'),
        ])->save();
    }

    protected function resolveBidderQrIssue(User $user, QrLoginService $qrLoginService): array
    {
        $activeToken = $user->qrLoginTokens()
            ->active()
            ->latest()
            ->first();

        if ($activeToken instanceof QrLoginToken) {
            $existingArtifacts = $qrLoginService->buildArtifactsForToken($activeToken);

            if (is_array($existingArtifacts)) {
                return $existingArtifacts;
            }
        }

        return $qrLoginService->issueForUser($user);
    }

    protected function sendBidderApprovedQrEmail(User $user, QrLoginService $qrLoginService): array
    {
        $user->loadMissing('bidderProfile');

        $qrIssue = $qrLoginService->issueForUser($user);

        Mail::to($user->email)->send(new BidderApprovedQrMail(
            $user,
            $user->bidderProfile,
            $qrIssue['svg'],
            $qrIssue['data_uri'],
            $qrIssue['login_url'],
        ));

        return $qrIssue;
    }

    protected function bidderApprovalFeaturesAvailable(): bool
    {
        return Schema::hasTable('bidders')
            && Schema::hasTable('qr_login_tokens')
            && Schema::hasTable('login_logs');
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

    private function extractProjectDocumentFiles(Request $request): array
    {
        $files = [];

        foreach ((array) $request->file('document_files', []) as $file) {
            if ($file) {
                $files[] = $file;
            }
        }

        $singleFile = $request->file('document_file');
        if ($singleFile) {
            $files[] = $singleFile;
        }

        return $files;
    }

    private function storeProjectDocuments(Project $project, array $files): void
    {
        if ($files === []) {
            return;
        }

        $timestamp = now();
        $documentsToCreate = [];
        $firstStoredDocument = null;

        foreach ($files as $file) {
            $filename = 'project_' . $project->id . '_' . $timestamp->format('YmdHis') . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $storedPath = Uploads::store($file, 'project-documents', $filename);

            $document = [
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            $documentsToCreate[] = $document;
            $firstStoredDocument ??= $document;
        }

        $project->documents()->createMany($documentsToCreate);

        if (! filled($project->document_path) && $firstStoredDocument !== null) {
            $project->forceFill([
                'document_path' => $firstStoredDocument['file_path'],
                'document_original_name' => $firstStoredDocument['original_name'],
            ])->save();
        }

        $project->unsetRelation('documents');
    }

}
