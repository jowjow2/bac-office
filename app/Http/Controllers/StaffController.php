<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Assignment;
use App\Models\Bid;
use App\Models\Project;
use App\Support\BidHistory;
use App\Support\BidWorkflow;
use App\Support\ProcurementPipeline;
use App\Support\SystemNotification;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\BidderDocument;

class StaffController extends Controller
{
    /**
     * The Secretariat's overview: the projects assigned to this staff member
     * and the shared purchase request queue.
     */
    public function index(Request $request)
    {
        $projectIds = Assignment::where('staff_id', Auth::id())->pluck('project_id')->filter()->unique()->values();
        $pipeline = ProcurementPipeline::forStaff($projectIds);
        $filters = ProcurementPipeline::filtersFrom($request);

        return view('dashboard.office', [
            'role' => 'staff',
            'filters' => $filters,
            'rows' => $pipeline->paginate($filters, 12),
            'kpis' => $pipeline->kpis(),
            'buckets' => $pipeline->bucketCounts($filters),
            'upcoming' => $pipeline->upcoming(),
            'assignedCount' => $projectIds->count(),
        ]);
    }

    public function assignProjects()
    {
        return view('staff.assign-projects', $this->staffPageData());
    }

    public function reviewBids()
    {
        return view('staff.review-bids', $this->staffPageData());
    }

    public function reports()
    {
        return view('staff.reports', $this->staffPageData());
    }

    public function exportReportsCsv()
    {
        $report = $this->staffPageData();
        $filename = 'staff-reports-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Staff Reports & Analytics']);
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
            foreach ($report['assignedProjects'] as $project) {
                fputcsv($handle, [
                    $project->title,
                    $project->budget,
                    $project->bids_count,
                    $project->status === 'awarded' ? 'Yes' : 'No',
                    $project->status,
                ]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Bidder Performance']);
            fputcsv($handle, ['Bidder', 'Total Bids', 'Approved', 'Won']);
            foreach ($report['bidderPerformance'] as $bidder) {
                fputcsv($handle, [
                    $bidder['bidder'],
                    $bidder['total_bids'],
                    $bidder['approved'],
                    $bidder['won'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function printReports()
    {
        return Pdf::loadView('staff.reports-print', $this->staffPageData())
            ->setPaper('a4')
            ->download('staff-reports-' . now()->format('Y-m-d') . '.pdf');
    }

    public function notifications()
    {
        return view('staff.notifications', $this->staffPageData());
    }

    public function downloadBidProposal(Bid $bid)
    {
        app(\App\Support\BidOpening::class)->openScheduledTechnical($bid->project()->with('schedule')->firstOrFail());
        $this->ensureAssignedProject($bid->project_id);
        $this->ensureBidOpened($bid);
        abort_if($bid->isFinancialSealed(), 403, 'The financial component is sealed.');

        abort_unless(filled($bid->proposal_file), 404);

        $extension = pathinfo((string) $bid->proposal_file, PATHINFO_EXTENSION);
        $projectSlug = Str::slug($bid->project->title ?? 'project');
        $bidderSlug = Str::slug($bid->user->company ?: ($bid->user->name ?? 'bidder'));
        $downloadName = $projectSlug . '-' . $bidderSlug . '-proposal.' . $extension;

        return Uploads::download($bid->proposal_file, $downloadName);
    }

    public function markAllNotificationsRead(Request $request)
    {
        SystemNotification::markAllRead(Auth::id());

        return redirect()
            ->route('staff.notifications');
    }

    public function updateProjectStatus(Request $request, Project $project)
    {
        $this->ensureAssignedProject($project->id);

        // Closed / awarded are set by bid opening, failed bidding and the
        // Notice of Award, never by hand.
        $validated = $request->validate([
            'status' => ['required', 'in:approved_for_bidding,open'],
        ]);
        $publicationAt = now(config('app.timezone', 'Asia/Manila'));

        if ($validated['status'] === 'open' && $project->status !== 'open') {
            $project->loadMissing(['schedule', 'documents']);
            if ($blockers = $project->publicationBlockers(now(config('app.timezone', 'Asia/Manila')), $publicationAt)) {
                return redirect()->back()->withErrors(['status' => 'Not ready for posting: ' . implode(' ', $blockers)]);
            }
        }

        if ($validated['status'] === 'open') {
            app(\App\Support\ProjectPublication::class)->publish($project, Auth::user(), $publicationAt);
            $warning = $project->fresh('schedule')->scheduleWarningNote();
        } else {
            $project->update(['status' => $validated['status']]);
        }

        SystemNotification::createForRole(
            'admin',
            'Project status updated',
            'Staff updated ' . $project->title . ' status to ' . $validated['status'] . '.',
            'project_status',
            ['project_id' => $project->id]
        );

        return redirect()
            ->back()
            ->with('success', 'Project status updated successfully.'.(isset($warning) && $warning ? ' '.$warning : ''));
    }


    /**
     * Record the public bid opening for an assigned project (after the
     * deadline and the scheduled opening). This closes bidding.
     */
    public function openProjectBids(Project $project)
    {
        abort_unless(Auth::user()?->role === 'admin', 403, 'Only BAC Admin may record the bid-opening event.');
        $this->ensureAssignedProject($project->id);

        app(BidWorkflow::class)->openBids($project, Auth::user());

        return redirect()->back()->with('success', 'Bid opening recorded for ' . $project->title . '.');
    }

    public function recommendBid(Request $request, Bid $bid)
    {
        $this->ensureAssignedProject($bid->project_id);

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
            'bac_resolution_no' => ['required', 'string', 'max:100'],
            'bac_resolution_date' => ['required', 'date', 'before_or_equal:today'],
            'supporting_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ], [
            'bac_resolution_no.required' => 'Enter the number of the BAC resolution recommending the award.',
        ]);

        // Only a post-qualified bidder can be recommended; BidWorkflow enforces it.
        app(BidWorkflow::class)->apply($bid, BidWorkflow::RECOMMEND, Auth::user(), [
            'bac_resolution_no' => $validated['bac_resolution_no'],
            'bac_resolution_date' => $validated['bac_resolution_date'],
            'supporting_document' => $request->file('supporting_document'),
        ]);
        $this->appendInternalNote($bid, trim((string) ($validated['notes'] ?? '')));

        SystemNotification::createForRole(
            'admin',
            'Winning bidder recommended',
            'Staff recommended a bidder for ' . ($bid->project->title ?? 'a project') . '.',
            'bid_recommendation',
            ['project_id' => $bid->project_id, 'bid_id' => $bid->id]
        );

        return redirect()
            ->back()
            ->with('success', 'Winning bidder recommendation saved.');
    }

    public function getBidDetails(Bid $bid)
    {
        $this->ensureAssignedProject($bid->project_id);

        $bid->load(['project.awards', 'project.requirement', 'award', 'user.bidderDocuments', 'trackings.creator']);

        return response()->json([
            'ok' => true,
            'bid' => $this->bidReviewPayload($bid),
            'eligible_for_validation' => app(BidWorkflow::class)->guardError($bid, BidWorkflow::PASS_PRELIMINARY, Auth::user()) === null,
        ]);
    }

    protected function bidReviewPayload(Bid $bid): array
    {
        $bid->loadMissing(['project.awards', 'project.requirement', 'award', 'user.bidderDocuments', 'trackings.creator']);
        $workflow = app(BidWorkflow::class);
        $status = $bid->progress()->adminStatus();
        $sealed = $bid->isSealed();
        $canFailPrelim = $workflow->guardError($bid, BidWorkflow::FAIL_PRELIMINARY, Auth::user()) === null;
        $checklist = $bid->reviewChecklist();

        return [
            'id' => $bid->id,
            'bidder_name' => $bid->user?->company ?: ($bid->user?->name ?? 'N/A'),
            'bidder_email' => $bid->user?->email ?? 'N/A',
            'project_title' => $bid->project?->title ?? 'N/A',
            // The financial offer stays sealed until the bid opening.
            'bid_amount' => $bid->isFinancialSealed() ? null : (float) $bid->bid_amount,
            'sealed' => $sealed,
            'financial_sealed' => $bid->isFinancialSealed(),
            'financial_opened_at' => $bid->financial_opened_at?->timezone('Asia/Manila')->toIso8601String(),
            'financial_opened_by' => $bid->financial_opened_by,
            'status' => $status['key'],
            'status_label' => $status['label'],
            'proposal_file' => filled($bid->proposal_file),
            'proposal_url' => ! $bid->isFinancialSealed() && filled($bid->proposal_file) ? route('staff.bids.proposal.preview', $bid) : null,
            'proposal_download_url' => ! $bid->isFinancialSealed() && filled($bid->proposal_file) ? route('staff.bids.proposal.download', $bid) : null,
            'eligibility_file' => filled($bid->eligibility_file),
            'eligibility_url' => ! $sealed && filled($bid->eligibility_file) ? route('staff.bids.eligibility.preview', $bid) : null,
            'eligibility_download_url' => ! $sealed && filled($bid->eligibility_file) ? route('staff.bids.eligibility.download', $bid) : null,
            'eligibility_status' => $bid->eligibility_status,
            'eligibility_status_label' => $bid->eligibility_status_label,
            'workflow_step' => $status['key'],
            'workflow_step_label' => $status['label'],
            'notes' => $bid->notes,
            'rejection_reason' => $bid->rejection_reason,
            'created_at' => $bid->created_at?->toISOString(),
            'submitted_at' => ($bid->submitted_at ?? $bid->created_at)?->timezone(config('bac-office.display_timezone'))->format('M d, Y h:i A'),
            'documents_validated_at' => $bid->documents_validated_at?->format('M d, Y h:i A'),
            'disqualified_at' => $bid->disqualified_at?->format('M d, Y h:i A'),
            'can_validate' => $workflow->guardError($bid, BidWorkflow::PASS_PRELIMINARY, Auth::user()) === null && $bid->documentsAreComplete(),
            'can_reject' => $canFailPrelim || $workflow->guardError($bid, BidWorkflow::DISQUALIFY, Auth::user()) === null,
            'can_decide' => Auth::user()?->role === 'admin',
            'available_actions' => $workflow->availableActions($bid, Auth::user()),
            'document_checklist' => $checklist,
            'document_status' => $bid->submissionDocumentStatus(),
            'receipt_no' => $bid->receipt_no,
            'opening_at' => $bid->project?->bids_opened_at?->timezone(config('bac-office.display_timezone'))->toIso8601String(),
            'opening_actor' => $bid->project?->bidsOpenedByUser?->name,
            'workflow_timeline_steps' => $bid->workflow_timeline_steps,
            'history' => BidHistory::for($bid)->forAdmin(),
        ];
    }

    protected function bidActionResponse(Request $request, Bid $bid, string $message)
    {
        $bid->refresh();
        $bid->unsetRelation('trackings');

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'bid' => $this->bidReviewPayload($bid),
            ]);
        }

        return redirect()
            ->back()
            ->with('success', $message);
    }

    protected function bidActionErrorResponse(Request $request, string $message, int $status = 422)
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
            ], $status);
        }

        return redirect()
            ->back()
            ->with('warning', $message);
    }

    /**
     * Run one BidWorkflow action and answer in the format the staff screens expect.
     */
    protected function runWorkflowAction(Request $request, Bid $bid, string $action, array $input, string $successMessage)
    {
        abort_unless(Auth::user()?->role === 'admin', 403, 'BAC Staff may prepare and view reviews but cannot record workflow decisions.');
        try {
            app(BidWorkflow::class)->apply($bid, $action, Auth::user(), $input);
        } catch (ValidationException $exception) {
            return $this->bidActionErrorResponse($request, collect($exception->errors())->flatten()->first() ?? 'Unable to record this decision.');
        }

        return $this->bidActionResponse($request, $bid, $successMessage);
    }

    protected function appendInternalNote(Bid $bid, string $note): void
    {
        if ($note === '') {
            return;
        }

        $existingNotes = trim((string) $bid->fresh()->notes);
        $bid->update(['notes' => $existingNotes !== '' ? $existingNotes . PHP_EOL . $note : $note]);
    }

    protected function ensureBidOpened(Bid $bid): void
    {
        abort_if($bid->isSealed(), 403, 'This bid is sealed until the bid opening is recorded.');
    }

    public function previewBidProposal(Bid $bid)
    {
        app(\App\Support\BidOpening::class)->openScheduledTechnical($bid->project()->with('schedule')->firstOrFail());
        $this->ensureAssignedProject($bid->project_id);
        $this->ensureBidOpened($bid);
        abort_if($bid->isFinancialSealed(), 403, 'The financial component is sealed.');

        abort_unless(filled($bid->proposal_file), 404);

        return Uploads::inline($bid->proposal_file, $bid->proposal_filename, 'application/pdf');
    }

    public function downloadBidEligibility(Bid $bid)
    {
        $this->ensureAssignedProject($bid->project_id);
        $this->ensureBidOpened($bid);

        abort_unless(filled($bid->eligibility_file), 404);

        $extension = pathinfo((string) $bid->eligibility_file, PATHINFO_EXTENSION);
        $projectSlug = Str::slug($bid->project->title ?? 'project');
        $bidderSlug = Str::slug($bid->user->company ?: ($bid->user->name ?? 'bidder'));
        $downloadName = $projectSlug . '-eligibility-' . $bidderSlug . '.' . $extension;

        return Uploads::download($bid->eligibility_file, $downloadName);
    }

    public function previewBidEligibility(Bid $bid)
    {
        $this->ensureAssignedProject($bid->project_id);
        $this->ensureBidOpened($bid);

        abort_unless(filled($bid->eligibility_file), 404);

        return Uploads::inline($bid->eligibility_file, $bid->eligibility_filename, 'application/pdf');
    }

    public function streamBidderDocumentPdf(Bid $bid, BidderDocument $document)
    {
        $this->ensureAssignedProject($bid->project_id);
        $this->ensureBidOpened($bid);

        abort_unless($document->user_id === $bid->user_id, 403);

        return Uploads::inline($document->file_path, $document->display_name, 'application/pdf');
    }

    /**
     * Passed preliminary examination. Requires every project requirement to
     * be verified (verified_requirements[]); an uploaded file alone is not a pass.
     */
    public function validateBidDocuments(Request $request, Bid $bid)
    {
        $this->ensureAssignedProject($bid->project_id);

        $validated = $request->validate([
            'verified_requirements' => ['nullable', 'array'],
            'verified_requirements.*' => ['string', 'max:100'],
        ]);

        return $this->runWorkflowAction($request, $bid, BidWorkflow::PASS_PRELIMINARY, $validated, 'Passed preliminary examination recorded.');
    }

    public function updateBidEligibility(Request $request, Bid $bid)
    {
        $this->ensureAssignedProject($bid->project_id);

        $validated = $request->validate([
            'eligibility_status' => ['required', 'in:valid,invalid'],
            'reason' => ['required_if:eligibility_status,invalid', 'nullable', 'string', 'max:2000'],
            'verified_requirements' => ['nullable', 'array'],
            'verified_requirements.*' => ['string', 'max:100'],
        ]);

        return $validated['eligibility_status'] === 'valid'
            ? $this->runWorkflowAction($request, $bid, BidWorkflow::PASS_PRELIMINARY, $validated, 'Passed preliminary examination recorded.')
            : $this->runWorkflowAction($request, $bid, BidWorkflow::FAIL_PRELIMINARY, $validated, 'Failed preliminary examination recorded.');
    }

    public function evaluateBid(Request $request, Bid $bid)
    {
        $this->ensureAssignedProject($bid->project_id);

        $validated = $request->validate([
            'evaluation_status' => ['required', 'in:documents_validated,for_bac_evaluation,approved,disqualified'],
            'remarks' => ['nullable', 'string'],
            'action' => ['sometimes', 'in:save,reject'],
            'verified_requirements' => ['nullable', 'array'],
            'verified_requirements.*' => ['string', 'max:100'],
        ]);

        $remarks = trim((string) ($validated['remarks'] ?? ''));
        $isAdverse = $validated['evaluation_status'] === 'disqualified' || ($validated['action'] ?? 'save') === 'reject';

        if ($isAdverse && $remarks === '') {
            return response()->json(['ok' => false, 'message' => 'Remarks are required to reject a bid.'], 422);
        }

        // Existing modal values mapped onto workflow actions. "approved" means
        // the evaluation was completed - never an award approval.
        $action = match (true) {
            $isAdverse => $bid->progress()->facts()['prelim_passed'] ? BidWorkflow::DISQUALIFY : BidWorkflow::FAIL_PRELIMINARY,
            $validated['evaluation_status'] === 'documents_validated' => BidWorkflow::PASS_PRELIMINARY,
            $validated['evaluation_status'] === 'for_bac_evaluation' => BidWorkflow::START_EVALUATION,
            default => BidWorkflow::EVALUATE,
        };

        $request->headers->set('Accept', 'application/json');
        $response = $this->runWorkflowAction($request, $bid, $action, [
            // For adverse decisions the remarks are the bidder-visible reason.
            'reason' => $isAdverse ? $remarks : null,
            'verified_requirements' => $validated['verified_requirements'] ?? [],
        ], 'Bid evaluation saved successfully.');

        if ($response->getStatusCode() === 200 && ! $isAdverse) {
            $this->appendInternalNote($bid, $remarks);
        }

        return $response;
    }

    public function rejectBid(Request $request, Bid $bid)
    {
        $this->ensureAssignedProject($bid->project_id);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:3'],
        ]);

        $action = $bid->progress()->facts()['prelim_passed'] ? BidWorkflow::DISQUALIFY : BidWorkflow::FAIL_PRELIMINARY;

        return $this->runWorkflowAction($request, $bid, $action, ['reason' => $validated['rejection_reason']], 'Bid marked as disqualified.');
    }

    public function requestBidClarification(Bid $bid)
    {
        $this->ensureAssignedProject($bid->project_id);

        SystemNotification::createForUser(
            $bid->user_id,
            'Bid clarification requested',
            'Staff has requested clarification for your bid on ' . ($bid->project->title ?? 'the project') . '.',
            'clarification_requested',
            ['project_id' => $bid->project_id, 'bid_id' => $bid->id]
        );

        return redirect()
            ->back()
            ->with('success', 'Clarification request sent to bidder.');
    }

    protected function ensureAssignedProject(int $projectId): void
    {
        abort_unless(
            Assignment::where('staff_id', Auth::id())
                ->where('project_id', $projectId)
                ->exists(),
            403
        );
    }

    protected function staffPageData(): array
    {
        $assignments = Assignment::with(['project' => function ($query) {
                $query->withCount('bids');
            }, 'project.bids.user'])
            ->where('staff_id', Auth::id())
            ->latest()
            ->get();

        $projectIds = $assignments
            ->pluck('project_id')
            ->filter()
            ->unique()
            ->values();

        $assignedProjects = Project::withCount('bids')
            ->whereIn('id', $projectIds)
            ->latest()
            ->get();

        $allAssignedBids = Bid::with(['project', 'user'])
            ->whereIn('project_id', $projectIds)
            ->latest()
            ->get();

        $latestBids = $allAssignedBids
            ->take(8)
            ->values();

        $validProjectAssignments = $assignments
            ->filter(fn ($assignment) => $assignment->project !== null)
            ->values();

        $totalAssignedProjects = $assignedProjects->count();
        $openProjects = $assignedProjects->where('status', 'open')->count();
        $closedProjects = $assignedProjects->where('status', 'closed')->count();
        $awardedProjects = $assignedProjects->where('status', 'awarded')->count();
        $pendingBids = $allAssignedBids->where('status', 'pending')->count();
        $validatedDocuments = $allAssignedBids->filter(fn ($bid) => filled($bid->proposal_file))->count();
        $recommendedBids = $allAssignedBids->where('status', 'approved')->count();
        $totalBidAmount = (float) $allAssignedBids->filter(fn ($bid) => ! $bid->isFinancialSealed())->sum('bid_amount');
        $totalBudgetAllocated = (float) $assignedProjects->sum(fn ($project) => (float) $project->budget);
        $totalAwardedAmount = (float) $assignedProjects
            ->where('status', 'awarded')
            ->sum(fn ($project) => (float) $project->budget);
        $governmentSavings = max(0, $totalBudgetAllocated - $totalAwardedAmount);
        $bidParticipation = $allAssignedBids->count();
        $bidderPerformance = $allAssignedBids
            ->groupBy(fn ($bid) => $bid->user->company ?: ($bid->user->name ?? 'Unknown Bidder'))
            ->map(function ($bids, $bidderName) {
                return [
                    'bidder' => $bidderName,
                    'total_bids' => $bids->count(),
                    'approved' => $bids->where('status', 'approved')->count(),
                    'won' => $bids->where('status', 'approved')->count(),
                ];
            })
            ->sortByDesc('total_bids')
            ->values();

        $staffNotificationItems = SystemNotification::forUser(Auth::id(), 30);
        $staffNotifications = $staffNotificationItems
            ->map(function ($notification) {
                return [
                    'title' => $notification->message,
                    'meta' => $notification->title,
                    'time' => $notification->created_at?->diffForHumans() ?? 'Just now',
                    'is_read' => $notification->read_at !== null,
                ];
            })
            ->values();

        return compact(
            'assignments',
            'validProjectAssignments',
            'assignedProjects',
            'allAssignedBids',
            'latestBids',
            'totalAssignedProjects',
            'openProjects',
            'closedProjects',
            'awardedProjects',
            'pendingBids',
            'validatedDocuments',
            'recommendedBids',
            'totalBidAmount',
            'totalBudgetAllocated',
            'totalAwardedAmount',
            'governmentSavings',
            'bidParticipation',
            'bidderPerformance',
            'staffNotifications'
        ) + [
            'staffNotificationCount' => $staffNotificationItems->whereNull('read_at')->count(),
        ];
    }
}
