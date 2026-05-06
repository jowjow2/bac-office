<?php

namespace App\Http\Controllers;

use App\Mail\BidderRejectedMail;
use App\Mail\WelcomeMail;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Award;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\Bidder;
use App\Models\BidderDocument;
use App\Models\Project;
use App\Models\User;
use App\Support\DocumentPreview;
use App\Support\Uploads;
use App\Support\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        $adminNotifications = SystemNotification::payloads($notificationItems, Auth::user());

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
            ->when(in_array($status, ['approved_for_bidding', 'open', 'closed', 'awarded', 'draft'], true), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.projects', compact('projects', 'search', 'status'));
    }

    public function createProject()
    {
        return view('admin.projects-wizard');
    }

    // Original store method (kept for backward compatibility / modal form)
    public function storeProject(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'nullable|string|max:255',
            'document_files' => 'nullable|array',
            'document_files.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:20480',
            'document_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:20480',
            'budget' => 'required|numeric|min:0|lte:9999999999999.99',
            'status' => 'required|in:draft,approved_for_bidding,open,closed,awarded',
            'deadline' => 'required|date|after:today',
        ], [
            'budget.lte' => 'Budget must not exceed 9,999,999,999,999.99.',
        ]);

        $documentFiles = $this->extractProjectDocumentFiles($request);
        unset($validated['document_files']);
        unset($validated['document_file']);

        $project = Project::create($validated);
        $this->storeProjectDocuments($project, $documentFiles);

        $redirectUrl = $validated['status'] === 'draft'
            ? route('admin.projects') . '?status=draft'
            : route('admin.projects');

        return redirect($redirectUrl)->with('success', 'Project created successfully.');
    }

    public function storeProjectWizard(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|string|in:goods,services,infrastructure,consultancy',
            'location' => 'required|string|max:255',
            'procurement_mode' => 'required|string|in:public_bidding,negotiated_procurement,shopping,small_value_procurement,direct_contracting,electronic_procurement',
            'source_of_fund' => 'required|string|max:255',
            'contract_duration' => 'required|string|max:255',
            'budget' => 'required|numeric|min:0|lte:9999999999999.99',
            'status' => 'required|in:draft,open',
            'date_posted' => 'nullable|date',
            'pre_bid_conference_date' => 'nullable|date',
            'clarification_deadline' => 'nullable|date',
            'bid_submission_deadline' => 'required|date',
            'bid_opening_date' => 'required|date',
            'evaluation_start_date' => 'nullable|date',
            'expected_award_date' => 'nullable|date',
            'eligibility_requirements' => 'nullable|string',
            'technical_requirements' => 'nullable|string',
            'financial_requirements' => 'nullable|string',
            'required_documents' => 'nullable|array',
            'required_documents.*' => 'string',
            'qualification_notes' => 'nullable|string',
            'special_instructions' => 'nullable|string',
            'project_documents' => 'nullable|array',
            'project_documents.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:20480',
        ], [
            'budget.lte' => 'Budget must not exceed 9,999,999,999,999.99.',
        ]);

        $projectDocumentFiles = $request->file('project_documents', []);

        DB::beginTransaction();
        try {
            $project = Project::create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'category' => $validated['category'],
                'location' => $validated['location'],
                'procurement_mode' => $validated['procurement_mode'],
                'source_of_fund' => $validated['source_of_fund'],
                'contract_duration' => $validated['contract_duration'],
                'budget' => $validated['budget'],
                'status' => $validated['status'],
                'created_by' => Auth::id(),
                'deadline' => $validated['bid_submission_deadline'],
            ]);

            $project->requirement()->create([
                'eligibility_requirements' => $validated['eligibility_requirements'] ?? null,
                'technical_requirements' => $validated['technical_requirements'] ?? null,
                'financial_requirements' => $validated['financial_requirements'] ?? null,
                'required_documents' => $validated['required_documents'] ?? null,
                'qualification_notes' => $validated['qualification_notes'] ?? null,
                'special_instructions' => $validated['special_instructions'] ?? null,
            ]);

            $project->schedule()->create([
                'date_posted' => $validated['date_posted'] ?? now()->format('Y-m-d'),
                'pre_bid_conference_date' => $validated['pre_bid_conference_date'] ?? null,
                'clarification_deadline' => $validated['clarification_deadline'] ?? null,
                'bid_submission_deadline' => $validated['bid_submission_deadline'],
                'bid_opening_date' => $validated['bid_opening_date'],
                'evaluation_start_date' => $validated['evaluation_start_date'] ?? null,
                'expected_award_date' => $validated['expected_award_date'] ?? null,
            ]);

            $documentTypes = $request->input('document_type', []);
            foreach ($projectDocumentFiles as $index => $file) {
                $type = $documentTypes[$index] ?? 'other';
                $filename = 'project_doc_' . $project->id . '_' . now()->format('YmdHis') . '_' . $index . '.' . $file->getClientOriginalExtension();
                $storedPath = Uploads::store($file, 'project-documents', $filename);

                $project->documents()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $storedPath,
                    'document_type' => $type,
                ]);
            }

            DB::commit();

            SystemNotification::createForUser(
                Auth::id(),
                'Project Created',
                'Project "' . $project->title . '" has been created with status: ' . $validated['status'],
                'project_created',
                ['project_id' => $project->id]
            );

            $redirectUrl = $validated['status'] === 'draft'
                ? route('admin.projects') . '?status=draft'
                : route('admin.projects');

            return redirect($redirectUrl)->with('success', 'Project created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Failed to create project: ' . $e->getMessage()]);
        }
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
        $bidderApprovalAvailable = Schema::hasTable('bidders');

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

        $user->load([
            'bidderProfile.approver',
            'registrationDocuments',
            'bidderDocuments' => fn ($query) => $query->orderBy('document_type'),
        ]);

        $registrationDocuments = $user->registrationDocuments;
        $supportingDocuments = $user->bidderDocuments
            ->reject(fn (BidderDocument $document) => str_starts_with($document->document_type, 'Registration Requirement '))
            ->values();

        return view('admin.bidder-review', compact(
            'user',
            'registrationDocuments',
            'supportingDocuments',
        ));
    }

     public function previewUserQr(User $user)
     {
         // QR code functionality removed
         return redirect()->route('admin.users.review', $user)
             ->with('warning', 'QR code feature has been disabled.');
     }

     public function downloadUserQr(User $user)
     {
         // QR code functionality removed
         return redirect()->route('admin.users.review', $user)
             ->with('warning', 'QR code feature has been disabled.');
     }

     public function resendUserQrEmail(User $user)
     {
         // QR code functionality removed
         return redirect()
             ->route('admin.users.review', $user)
             ->with('warning', 'QR code functionality has been disabled.');
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

         // Check if user status is changing from pending to active
         $wasPending = $user->status === 'pending';
         $becomingActive = $validated['status'] === 'active';

         $user->update($validated);
         $this->syncBidderProfile($user);

         // Send welcome email if user is activated from pending
         if ($wasPending && $becomingActive) {
             Mail::to($user->email)->send(new WelcomeMail($user));

             SystemNotification::createForUser(
                 $user->id,
                 'Account activated',
                 'Your ' . $user->role . ' account has been activated. You can now access your account.',
                 'account_approved'
             );
         }

         return redirect()->route('admin.users')->with('success', 'User updated successfully.');
     }

 public function approveUser(User $user)
     {
         abort_unless($user->role === 'bidder', 404);

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

         SystemNotification::createForUser(
             $user->id,
             'Account approved',
             'Your bidder account has been approved. You can now access your account.',
             'account_approved'
         );

         // Send welcome email
         Mail::to($user->email)->send(new WelcomeMail($user));

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

public function rejectUser(Request $request, User $user)
    {
        if ((int) $user->id === (int) Auth::id()) {
            return redirect()
                ->route('admin.users')
                ->withErrors(['status' => 'You cannot reject your own signed-in account.']);
        }

         abort_unless($user->role === 'bidder', 404);

         if (! Schema::hasTable('bidders')) {
             return redirect()
                 ->route('admin.users')
                 ->with('warning', 'Bidder rejection is unavailable because the bidders table is missing in the current database.');
         }

        $reason = trim((string) $request->input('rejection_reason'));
        $bidder = $this->ensureBidderProfile($user);

        DB::transaction(function () use ($user, $bidder): void {
            $user->forceFill([
                'status' => 'rejected',
            ])->save();

            $bidder->forceFill([
                'approval_status' => 'rejected',
                'approved_at' => null,
                'approved_by' => Auth::id(),
            ])->save();
        });
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
            'workflow_step' => 'sometimes|in:submitted,pending_validation,documents_validated,for_bac_evaluation,approved,disqualified,awarded,not_awarded,notice_of_award,notice_to_proceed,project_completed',
            'notes' => 'nullable|string',
        ]);

        $updateData = [
            'bid_amount' => $validated['bid_amount'],
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ];

        // Handle workflow step update if provided
        if ($request->has('workflow_step') && $request->input('workflow_step') !== $bid->workflow_step) {
            $newStep = $request->input('workflow_step');
            $updateData['workflow_step'] = $newStep;
            $updateData['workflow_step_updated_at'] = now();
            $updateData['workflow_step_updated_by'] = Auth::id();

            // Set specific timestamps and user references based on step
            switch ($newStep) {
                case Bid::STEP_DOCUMENTS_VALIDATED:
                    $updateData['documents_validated_at'] = now();
                    $updateData['documents_validated_by'] = Auth::id();
                    break;
                case Bid::STEP_FOR_BAC_EVALUATION:
                    $updateData['bac_evaluation_at'] = now();
                    $updateData['bac_evaluation_by'] = Auth::id();
                    break;
                case Bid::STEP_APPROVED:
                    $updateData['approved_at'] = now();
                    $updateData['approved_by'] = Auth::id();
                    // Also update status for backward compatibility
                    $updateData['status'] = 'approved';
                    break;
                case Bid::STEP_DISQUALIFIED:
                    $updateData['disqualified_at'] = now();
                    $updateData['disqualified_by'] = Auth::id();
                    $updateData['status'] = 'rejected';
                    break;
                case Bid::STEP_AWARDED:
                    $updateData['awarded_at'] = now();
                    $updateData['awarded_by'] = Auth::id();
                    break;
                case Bid::STEP_NOTICE_OF_AWARD:
                    $updateData['notice_of_award_at'] = now();
                    $updateData['notice_of_award_by'] = Auth::id();
                    break;
                case Bid::STEP_NOTICE_TO_PROCEED:
                    $updateData['notice_to_proceed_at'] = now();
                    $updateData['notice_to_proceed_by'] = Auth::id();
                    break;
                case Bid::STEP_PROJECT_COMPLETED:
                    $updateData['project_completed_at'] = now();
                    $updateData['project_completed_by'] = Auth::id();
                    break;
            }

            // Send notification to bidder
            $stepLabel = Bid::WORKFLOW_STEPS[$newStep] ?? $newStep;
            $notificationType = match ($newStep) {
                Bid::STEP_DOCUMENTS_VALIDATED => 'documents_validated',
                Bid::STEP_FOR_BAC_EVALUATION => 'bac_evaluation_started',
                Bid::STEP_APPROVED => 'bid_approved',
                Bid::STEP_DISQUALIFIED => 'bid_disqualified',
                Bid::STEP_AWARDED => 'bid_awarded',
                Bid::STEP_NOTICE_OF_AWARD => 'notice_of_award',
                Bid::STEP_NOTICE_TO_PROCEED => 'notice_to_proceed',
                Bid::STEP_PROJECT_COMPLETED => 'project_completed',
                default => 'workflow_update',
            };

            SystemNotification::createForUser(
                $bid->user_id,
                'Bid Status Update: ' . $stepLabel,
                'Your bid for ' . ($bid->project->title ?? 'the project') . ' has moved to: ' . $stepLabel . '.',
                $notificationType,
                ['project_id' => $bid->project_id, 'bid_id' => $bid->id, 'workflow_step' => $newStep]
            );

            event(new \App\Events\BidWorkflowUpdated($bid));
        }

        $bid->update($updateData);

        return redirect()->route('admin.bid.edit', $bid)->with('success', 'Bid updated successfully!');
    }

    public function updateBidWorkflow(Request $request, Bid $bid)
    {
        $validated = $request->validate([
            'workflow_step' => ['required', 'in:submitted,pending_validation,documents_validated,for_bac_evaluation,approved,disqualified,awarded,not_awarded,notice_of_award,notice_to_proceed,project_completed'],
            'notes' => ['nullable', 'string'],
        ]);

        $oldStep = $bid->workflow_step;
        $newStep = $validated['workflow_step'];

        // Update workflow step and relevant timestamps
        $updateData = [
            'workflow_step' => $newStep,
            'workflow_step_updated_at' => now(),
            'workflow_step_updated_by' => Auth::id(),
        ];

        // Set specific timestamps based on step
        switch ($newStep) {
            case Bid::STEP_DOCUMENTS_VALIDATED:
                $updateData['documents_validated_at'] = now();
                $updateData['documents_validated_by'] = Auth::id();
                break;
            case Bid::STEP_FOR_BAC_EVALUATION:
                $updateData['bac_evaluation_at'] = now();
                $updateData['bac_evaluation_by'] = Auth::id();
                break;
            case Bid::STEP_APPROVED:
                $updateData['approved_at'] = now();
                $updateData['approved_by'] = Auth::id();
                break;
            case Bid::STEP_DISQUALIFIED:
                $updateData['disqualified_at'] = now();
                $updateData['disqualified_by'] = Auth::id();
                break;
            case Bid::STEP_AWARDED:
                $updateData['awarded_at'] = now();
                $updateData['awarded_by'] = Auth::id();
                break;
            case Bid::STEP_NOTICE_OF_AWARD:
                $updateData['notice_of_award_at'] = now();
                $updateData['notice_of_award_by'] = Auth::id();
                break;
            case Bid::STEP_NOTICE_TO_PROCEED:
                $updateData['notice_to_proceed_at'] = now();
                $updateData['notice_to_proceed_by'] = Auth::id();
                break;
            case Bid::STEP_PROJECT_COMPLETED:
                $updateData['project_completed_at'] = now();
                $updateData['project_completed_by'] = Auth::id();
                break;
        }

        // Update bid
        $bid->update($updateData);

        // Also update main status field for backward compatibility
        $statusUpdate = match ($newStep) {
            Bid::STEP_APPROVED => 'approved',
            Bid::STEP_DISQUALIFIED => 'rejected',
            Bid::STEP_AWARDED => 'approved',
            default => $bid->status,
        };
        if ($statusUpdate) {
            $bid->update(['status' => $statusUpdate]);
        }

        // Send notification to bidder
        $stepLabel = Bid::WORKFLOW_STEPS[$newStep] ?? $newStep;
        $notificationType = match ($newStep) {
            Bid::STEP_DOCUMENTS_VALIDATED => 'documents_validated',
            Bid::STEP_FOR_BAC_EVALUATION => 'bac_evaluation_started',
            Bid::STEP_APPROVED => 'bid_approved',
            Bid::STEP_DISQUALIFIED => 'bid_disqualified',
            Bid::STEP_AWARDED => 'bid_awarded',
            Bid::STEP_NOTICE_OF_AWARD => 'notice_of_award',
            Bid::STEP_NOTICE_TO_PROCEED => 'notice_to_proceed',
            Bid::STEP_PROJECT_COMPLETED => 'project_completed',
            default => 'workflow_update',
        };

        SystemNotification::createForUser(
            $bid->user_id,
            'Bid Status Update: ' . $stepLabel,
            'Your bid for ' . ($bid->project->title ?? 'the project') . ' has moved to: ' . $stepLabel . '.',
            $notificationType,
            ['project_id' => $bid->project_id, 'bid_id' => $bid->id, 'workflow_step' => $newStep]
        );

        // Broadcast real-time update (for bidding track page)
        event(new \App\Events\BidWorkflowUpdated($bid));

        return redirect()->route('admin.bid.edit', $bid)->with('success', 'Workflow step updated to: ' . $stepLabel);
    }

     public function approveBid(Bid $bid)
     {
         $bid->update([
             'status' => 'approved',
             'workflow_step' => Bid::STEP_APPROVED,
             'workflow_step_updated_at' => now(),
             'workflow_step_updated_by' => Auth::id(),
             'documents_validated_at' => $bid->documents_validated_at ?? now(),
             'documents_validated_by' => $bid->documents_validated_by ?? Auth::id(),
             'approved_at' => now(),
             'approved_by' => Auth::id(),
         ]);

         if ($bid->project && $bid->project->status !== 'awarded') {
             $bid->project->update(['status' => 'closed']);
         }

         event(new \App\Events\BidWorkflowUpdated($bid));

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

         event(new \App\Events\BidWorkflowUpdated($bid));

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
            'status' => 'required|in:draft,approved_for_bidding,open,closed,awarded',
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

    public function publishProject(Request $request, Project $project)
    {
        if ($project->status !== 'draft') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft projects can be published.',
                ], 422);
            }
            return redirect()->route('admin.projects')->with('error', 'Only draft projects can be published.');
        }

$project->update(['status' => 'approved_for_bidding']);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Project published successfully! It is now ready for bidding.',
            ]);
        }

        return redirect()->route('admin.projects')->with('success', 'Project published successfully!');
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
        $awards = Award::with(['project', 'bidder', 'bid'])->latest()->get();
        
        $readyProjects = Project::with([
                'bids' => function ($query) {
                    $query->with('user')
                        ->whereIn('status', ['approved', 'evaluated'])
                        ->orderBy('bid_amount');
                },
            ])
            ->where('status', '!=', 'awarded')
            ->whereDoesntHave('awards')
            ->whereHas('bids', function ($query) {
                $query->whereIn('status', ['approved', 'evaluated']);
            })
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
        $notifications = SystemNotification::payloads($notificationItems, Auth::user())->all();

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
            ->whereIn('status', ['approved', 'evaluated'])
            ->sortBy('bid_amount')
            ->values();
        $selectedBidId = $request->integer('bid');

        return view('admin.award-create', compact('project', 'bids', 'selectedBidId'));
    }

    public function storeAward(Request $request)
    {
        $project = Project::findOrFail($request->input('project_id'));

        return $this->declareWinner($request, $project);
    }

    public function declareWinner(Request $request, Project $project)
    {
        $validated = $request->validate([
            'bid_id' => 'nullable|exists:bids,id',
            'notes' => 'nullable|string',
            'certificate_file' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf,application/x-pdf',
                'max:5120',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $extension = strtolower((string) $value->getClientOriginalExtension());
                    if ($extension !== 'pdf') {
                        $fail('The certificate must be a PDF file.');
                        return;
                    }

                    $handle = @fopen($value->getRealPath(), 'rb');
                    $signature = $handle ? fread($handle, 4) : '';
                    if ($handle) {
                        fclose($handle);
                    }

                    if ($signature !== '%PDF') {
                        $fail('The certificate file is not a valid PDF document.');
                    }
                },
            ],
        ]);

        $storedPath = null;

        try {
            DB::beginTransaction();

            $project = Project::with([
                'bids' => function ($query) {
                    $query->with('user')
                        ->whereIn('status', ['approved', 'evaluated'])
                        ->orderBy('bid_amount');
                },
            ])->lockForUpdate()->findOrFail($project->id);

            if ($project->status === 'awarded' || Award::where('project_id', $project->id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'project_id' => 'This project already has an award record.',
                ]);
            }

            $winningBid = $project->bids->first();

            if (! $winningBid) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'bid_id' => 'No approved or evaluated bid is available for this project.',
                ]);
            }

            $certificateFile = $request->file('certificate_file');
            $storedPath = $certificateFile->storeAs(
                'certificates/' . $project->id,
                Str::random(40) . '.pdf',
                'local'
            );

            if (! $storedPath || ! Storage::disk('local')->exists($storedPath)) {
                throw new \RuntimeException('The certificate PDF could not be stored.');
            }

            $qrToken = Award::newQrToken();

            $award = Award::create([
                'project_id' => $project->id,
                'bid_id' => $winningBid->id,
                'bidder_id' => $winningBid->user_id,
                'contract_amount' => $winningBid->bid_amount,
                'contract_date' => now()->toDateString(),
                'status' => Award::STATUS_VALID,
                'notes' => $validated['notes'] ?? null,
                'certificate_file_path' => $storedPath,
                'qr_token' => $qrToken,
                'certificate_status' => Award::STATUS_VALID,
                'certificate_uploaded_at' => now(),
            ]);

            $winningBid->update([
                'status' => 'awarded',
                'workflow_step' => Bid::STEP_AWARDED,
                'workflow_step_updated_at' => now(),
                'workflow_step_updated_by' => Auth::id(),
                'awarded_at' => now(),
                'awarded_by' => Auth::id(),
            ]);

            $project->update(['status' => 'awarded']);

            Bid::where('project_id', $project->id)
                ->whereKeyNot($winningBid->id)
                ->where('status', 'pending')
                ->update(['status' => 'rejected']);

            SystemNotification::createForUser(
                $winningBid->user_id,
                'Contract awarded',
                'Congratulations! Your bid for ' . $project->title . ' has been declared the winning bid.',
                'award_won',
                ['project_id' => $project->id, 'bid_id' => $winningBid->id, 'award_id' => $award->id]
            );

            $otherBidderIds = Bid::where('project_id', $project->id)
                ->whereKeyNot($winningBid->id)
                ->pluck('user_id');

            SystemNotification::createForUsers(
                $otherBidderIds,
                'Award decision released',
                'The project ' . $project->title . ' has already been awarded to another bidder.',
                'award_decision',
                ['project_id' => $project->id]
            );

            AuditLog::log('winner_declared', $award, [], [
                'project_id' => $project->id,
                'bid_id' => $winningBid->id,
                'bidder_id' => $winningBid->user_id,
                'contract_amount' => $winningBid->bid_amount,
            ]);

            AuditLog::log('certificate_uploaded', $award, [], [
                'file_path' => $storedPath,
                'file_size' => $certificateFile->getSize(),
                'qr_token' => $qrToken,
            ]);

            DB::commit();

            if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => true,
                    'message' => 'Award declared successfully! Certificate stored securely.',
                    'redirect' => route('admin.awards.index'),
                ]);
            }

            return redirect()->route('admin.awards.index')->with('success', 'Award declared successfully! Certificate stored securely.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            if ($storedPath && Storage::disk('local')->exists($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $e;
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            if ($storedPath && Storage::disk('local')->exists($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            Log::error('Award creation failed', [
                'project_id' => $project->id ?? null,
                'bid_id' => $validated['bid_id'] ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to declare award. Please try again or contact support.',
                    'error' => config('app.debug') ? $e->getMessage() : null,
                ], 500);
            }

            return back()
                ->withInput()
                ->withErrors(['award' => 'Failed to declare award. Please try again or contact support.'])
                ->with('error', 'Failed to declare award. Please try again or contact support.');
        }
    }

    public function uploadCertificate(Request $request, Award $award)
    {
        return $this->replaceCertificate($request, $award);
    }

    public function replaceCertificate(Request $request, Award $award)
    {
        $validated = $request->validate([
            'certificate_file' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf,application/x-pdf',
                'max:5120',
            ],
        ]);

        $storedPath = null;

        try {
            DB::beginTransaction();

            $oldPath = $award->certificate_file_path;
            $storedPath = $request->file('certificate_file')->storeAs(
                'certificates/' . $award->project_id,
                Str::random(40) . '.pdf',
                'local'
            );

            if (! $storedPath || ! Storage::disk('local')->exists($storedPath)) {
                throw new \RuntimeException('The replacement certificate PDF could not be stored.');
            }

            $award->forceFill([
                'certificate_file_path' => $storedPath,
                'certificate_status' => Award::STATUS_VALID,
                'status' => Award::STATUS_VALID,
                'certificate_uploaded_at' => now(),
                'certificate_revoked_at' => null,
                'certificate_revoked_by' => null,
            ])->save();

            if ($oldPath && Storage::disk('local')->exists($oldPath)) {
                Storage::disk('local')->delete($oldPath);
            }

            AuditLog::log('certificate_replaced', $award, [
                'file_path' => $oldPath,
            ], [
                'file_path' => $storedPath,
            ]);

            DB::commit();

            if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json(['success' => true, 'message' => 'Certificate replaced successfully.']);
            }

            return back()->with('success', 'Certificate replaced successfully.');
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            if ($storedPath && Storage::disk('local')->exists($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            Log::error('Certificate replacement failed', [
                'award_id' => $award->id,
                'error' => $e->getMessage(),
            ]);

            if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json(['success' => false, 'message' => 'Failed to replace certificate.'], 500);
            }

            return back()->withErrors(['certificate_file' => 'Failed to replace certificate.']);
        }
    }

    public function revokeCertificate(Request $request, Award $award)
    {
        try {
            DB::transaction(function () use ($award, $request): void {
                $oldValues = [
                    'certificate_status' => $award->certificate_status,
                    'status' => $award->status,
                ];

                $award->forceFill([
                    'certificate_status' => Award::STATUS_REVOKED,
                    'status' => Award::STATUS_REVOKED,
                    'certificate_revoked_at' => now(),
                    'certificate_revoked_by' => Auth::id(),
                ])->save();

                AuditLog::log('certificate_revoked', $award, $oldValues, [
                    'certificate_status' => Award::STATUS_REVOKED,
                    'revoked_by' => Auth::id(),
                ], [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            });

            if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json(['success' => true, 'message' => 'Certificate revoked successfully.']);
            }

            return back()->with('success', 'Certificate revoked successfully.');
        } catch (\Throwable $e) {
            Log::error('Certificate revocation failed', [
                'award_id' => $award->id,
                'error' => $e->getMessage(),
            ]);

            if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json(['success' => false, 'message' => 'Failed to revoke certificate.'], 500);
            }

            return back()->withErrors(['award' => 'Failed to revoke certificate.']);
        }
    }

    public function regenerateQrToken(Request $request, Award $award)
    {
        try {
            DB::transaction(function () use ($award, $request): void {
                $oldToken = $award->qr_token;
                $newToken = Award::newQrToken();

                $award->forceFill(['qr_token' => $newToken])->save();

                AuditLog::log('qr_token_regenerated', $award, [
                    'qr_token' => $oldToken,
                ], [
                    'qr_token' => $newToken,
                ], [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            });

            if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json(['success' => true, 'message' => 'QR token regenerated successfully.']);
            }

            return back()->with('success', 'QR token regenerated successfully.');
        } catch (\Throwable $e) {
            Log::error('QR token regeneration failed', [
                'award_id' => $award->id,
                'error' => $e->getMessage(),
            ]);

            if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json(['success' => false, 'message' => 'Failed to regenerate QR token.'], 500);
            }

            return back()->withErrors(['award' => 'Failed to regenerate QR token.']);
        }
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
        ])->setPaper('a4')->stream($pdfFilename);
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
         if ($user->role !== 'bidder' || ! Schema::hasTable('bidders')) {
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
