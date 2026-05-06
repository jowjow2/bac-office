<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Events\BidWorkflowUpdated;
use App\Models\Assignment;
use App\Models\Bid;
use App\Models\BidTracking;
use App\Models\Project;
use App\Support\SystemNotification;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\BidderDocument;

class StaffController extends Controller
{
    public function index()
    {
        return view('dashboard.staff', $this->staffPageData());
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
        $this->ensureAssignedProject($bid->project_id);

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

        $validated = $request->validate([
            'status' => ['required', 'in:approved_for_bidding,open,closed,awarded'],
        ]);

        $project->update([
            'status' => $validated['status'],
        ]);

        SystemNotification::createForRole(
            'admin',
            'Project status updated',
            'Staff updated ' . $project->title . ' status to ' . $validated['status'] . '.',
            'project_status',
            ['project_id' => $project->id]
        );

        return redirect()
            ->back()
            ->with('success', 'Project status updated successfully.');
    }


    public function recommendBid(Request $request, Bid $bid)
    {
        $this->ensureAssignedProject($bid->project_id);

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $recommendationNote = 'Recommended by staff on ' . now()->format('M d, Y h:i A');
        $notes = trim((string) ($validated['notes'] ?? ''));

        $bid->update([
            'status' => 'approved',
            'notes' => $notes !== '' ? $notes . PHP_EOL . $recommendationNote : $recommendationNote,
        ]);

        // Update project status to closed (ready for award)
        if ($bid->project && $bid->project->status !== 'awarded') {
            $bid->project->update(['status' => 'closed']);
        }

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

        $bid->load(['project', 'user.bidderDocuments', 'tracking']);

        return response()->json([
            'ok' => true,
            'bid' => $this->bidReviewPayload($bid),
            'eligible_for_validation' => $bid->canBeValidatedByStaff(),
        ]);
    }

    protected function bidReviewPayload(Bid $bid): array
    {
        $bid->loadMissing(['project', 'user.bidderDocuments', 'tracking']);
        $statusLabel = match ($bid->status) {
            'approved' => 'Validated',
            'rejected' => 'Rejected',
            default => 'Pending',
        };

        return [
            'id' => $bid->id,
            'bidder_name' => $bid->user?->company ?: ($bid->user?->name ?? 'N/A'),
            'bidder_email' => $bid->user?->email ?? 'N/A',
            'project_title' => $bid->project?->title ?? 'N/A',
            'bid_amount' => (float) $bid->bid_amount,
            'status' => $bid->status,
            'status_label' => $statusLabel,
            'proposal_file' => filled($bid->proposal_file),
            'proposal_url' => filled($bid->proposal_file) ? route('staff.bids.proposal.preview', $bid) : null,
            'proposal_download_url' => filled($bid->proposal_file) ? route('staff.bids.proposal.download', $bid) : null,
            'eligibility_file' => filled($bid->eligibility_file),
            'eligibility_url' => filled($bid->eligibility_file) ? route('staff.bids.eligibility.preview', $bid) : null,
            'eligibility_download_url' => filled($bid->eligibility_file) ? route('staff.bids.eligibility.download', $bid) : null,
            'eligibility_status' => $bid->eligibility_status,
            'eligibility_status_label' => $bid->eligibility_status_label,
            'workflow_step' => $bid->effective_workflow_step,
            'workflow_step_label' => Bid::WORKFLOW_STEPS[$bid->effective_workflow_step] ?? $bid->workflow_step_label,
            'notes' => $bid->notes,
            'rejection_reason' => $bid->rejection_reason,
            'created_at' => $bid->created_at?->toISOString(),
            'submitted_at' => $bid->created_at?->format('M d, Y h:i A'),
            'documents_validated_at' => $bid->documents_validated_at?->format('M d, Y h:i A'),
            'disqualified_at' => $bid->disqualified_at?->format('M d, Y h:i A'),
            'can_validate' => $bid->status === 'pending' && $bid->canBeValidatedByStaff(),
            'can_reject' => $bid->status === 'pending',
            'document_checklist' => $bid->documentChecklist(),
            'workflow_timeline_steps' => $bid->workflow_timeline_steps,
        ];
    }

    protected function bidActionResponse(Request $request, Bid $bid, string $message)
    {
        $bid->refresh()->load(['project', 'user.bidderDocuments', 'tracking']);

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

    public function previewBidProposal(Bid $bid)
    {
        $this->ensureAssignedProject($bid->project_id);

        abort_unless(filled($bid->proposal_file), 404);

        return Uploads::inline($bid->proposal_file, $bid->proposal_filename, 'application/pdf');
    }

    public function downloadBidEligibility(Bid $bid)
    {
        $this->ensureAssignedProject($bid->project_id);

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

        abort_unless(filled($bid->eligibility_file), 404);

        return Uploads::inline($bid->eligibility_file, $bid->eligibility_filename, 'application/pdf');
    }

    public function streamBidderDocumentPdf(Bid $bid, BidderDocument $document)
    {
        $this->ensureAssignedProject($bid->project_id);

        abort_unless($document->user_id === $bid->user_id, 403);

        return Uploads::inline($document->file_path, $document->display_name, 'application/pdf');
    }

    public function validateBidDocuments(Request $request, Bid $bid)
    {
        $this->ensureAssignedProject($bid->project_id);

        if (!$bid->canBeValidatedByStaff()) {
            return $this->bidActionErrorResponse(
                $request,
                'Cannot validate bid. Please ensure documents are complete and eligibility is valid.'
            );
        }

        $bid->update([
            'status' => 'approved',
            'workflow_step' => Bid::STEP_DOCUMENTS_VALIDATED,
            'workflow_step_updated_at' => now(),
            'workflow_step_updated_by' => Auth::id(),
            'documents_validated_at' => now(),
            'documents_validated_by' => Auth::id(),
        ]);

        BidTracking::create([
            'bid_id' => $bid->id,
            'bidder_id' => $bid->user_id,
            'project_id' => $bid->project_id,
            'status_title' => 'Documents Validated by Staff',
            'status_description' => 'Your submitted bid documents were validated and forwarded for BAC evaluation.',
            'status_type' => 'validated',
            'created_by' => Auth::id(),
        ]);

        SystemNotification::createForUser(
            $bid->user_id,
            'Bid documents validated',
            'Your bid documents for ' . ($bid->project->title ?? 'the project') . ' were validated by staff. Workflow advanced to: Documents Validated.',
            'documents_validated',
            ['project_id' => $bid->project_id, 'bid_id' => $bid->id]
        );

        BidWorkflowUpdated::dispatch($bid);

        return $this->bidActionResponse(
            $request,
            $bid,
            'Bid documents marked as validated. Workflow advanced to Documents Validated.'
        );
    }

    public function updateBidEligibility(Bid $bid)
    {
        $this->ensureAssignedProject($bid->project_id);

        $validated = request()->validate([
            'eligibility_status' => ['required', 'in:valid,invalid'],
        ]);

        $bid->update([
            'eligibility_status' => $validated['eligibility_status'],
            'eligibility_reviewed_at' => now(),
            'eligibility_reviewed_by' => Auth::id(),
            'workflow_step' => $validated['eligibility_status'] === 'valid' ? Bid::STEP_DOCUMENTS_VALIDATED : $bid->workflow_step,
            'workflow_step_updated_at' => now(),
            'workflow_step_updated_by' => Auth::id(),
        ]);

        $statusLabel = $validated['eligibility_status'] === 'valid' ? 'Valid' : 'Invalid';

        BidTracking::create([
            'bid_id' => $bid->id,
            'bidder_id' => $bid->user_id,
            'project_id' => $bid->project_id,
            'status_title' => 'Eligibility Marked as ' . $statusLabel,
            'status_description' => 'Eligibility status set to: ' . $statusLabel,
            'status_type' => $validated['eligibility_status'] === 'valid' ? 'validated' : 'rejected',
            'created_by' => Auth::id(),
        ]);

        if ($validated['eligibility_status'] === 'valid') {
            $bid->load(['project', 'user', 'tracking']);
            SystemNotification::createForUser(
                $bid->user_id,
                'Eligibility confirmed valid',
                'Your bid eligibility for ' . ($bid->project->title ?? 'the project') . ' has been confirmed valid. Your bid has moved to Documents Validated.',
                'eligibility_valid',
                ['project_id' => $bid->project_id, 'bid_id' => $bid->id]
            );

            BidWorkflowUpdated::dispatch($bid);

            return redirect()
                ->back()
                ->with('success', 'Eligibility confirmed valid. Your bid has moved to Documents Validated.');
        }

        SystemNotification::createForUser(
            $bid->user_id,
            'Eligibility marked invalid',
            'Your bid eligibility for ' . ($bid->project->title ?? 'the project') . ' has been marked invalid.',
            'eligibility_invalid',
            ['project_id' => $bid->project_id, 'bid_id' => $bid->id]
        );

        return redirect()
            ->back()
            ->with('success', 'Eligibility marked as invalid.');
    }

    public function evaluateBid(Request $request, Bid $bid)
    {
        $this->ensureAssignedProject($bid->project_id);

        $validated = $request->validate([
            'evaluation_status' => ['required', 'in:documents_validated,for_bac_evaluation,approved,disqualified'],
            'remarks' => ['nullable', 'string'],
            'action' => ['sometimes', 'in:save,reject'],
        ]);

        $action = $validated['action'] ?? 'save';
        $evaluationStatus = $validated['evaluation_status'];

        if ($action === 'reject' && empty(trim((string) ($validated['remarks'] ?? '')))) {
            return response()->json([
                'ok' => false,
                'message' => 'Remarks are required to reject a bid.',
            ], 422);
        }

        $workflowStepMap = [
            'documents_validated' => Bid::STEP_DOCUMENTS_VALIDATED,
            'for_bac_evaluation' => Bid::STEP_FOR_BAC_EVALUATION,
            'approved' => Bid::STEP_APPROVED,
            'disqualified' => Bid::STEP_DISQUALIFIED,
        ];

        $bid->update([
            'workflow_step' => $workflowStepMap[$evaluationStatus],
            'workflow_step_updated_at' => now(),
            'workflow_step_updated_by' => Auth::id(),
            'documents_validated_at' => $evaluationStatus === 'documents_validated' ? now() : $bid->documents_validated_at,
            'documents_validated_by' => $evaluationStatus === 'documents_validated' ? Auth::id() : $bid->documents_validated_by,
            'bac_evaluation_at' => $evaluationStatus === 'for_bac_evaluation' ? now() : $bid->bac_evaluation_at,
            'bac_evaluation_by' => $evaluationStatus === 'for_bac_evaluation' ? Auth::id() : $bid->bac_evaluation_by,
            'approved_at' => $evaluationStatus === 'approved' ? now() : $bid->approved_at,
            'approved_by' => $evaluationStatus === 'approved' ? Auth::id() : $bid->approved_by,
            'disqualified_at' => $evaluationStatus === 'disqualified' ? now() : $bid->disqualified_at,
            'disqualified_by' => $evaluationStatus === 'disqualified' ? Auth::id() : $bid->disqualified_by,
        ]);

        // Update project status to closed (ready for award) when bid is approved
        if ($evaluationStatus === 'approved' && $bid->project && $bid->project->status !== 'awarded') {
            $bid->project->update(['status' => 'closed']);
        }

        $remarks = trim((string) ($validated['remarks'] ?? ''));
        $existingNotes = trim((string) $bid->notes);
        $newNotes = $existingNotes !== '' ? $existingNotes . PHP_EOL . $remarks : $remarks;
        
        if ($remarks !== '') {
            $bid->update(['notes' => $newNotes]);
        }

        $statusLabel = $workflowStepMap[$evaluationStatus];

        BidTracking::create([
            'bid_id' => $bid->id,
            'bidder_id' => $bid->user_id,
            'project_id' => $bid->project_id,
            'status_title' => 'BAC Evaluation ' . ($action === 'reject' ? 'Rejected' : 'Saved'),
            'status_description' => 'Workflow step set to: ' . Bid::WORKFLOW_STEPS[$workflowStepMap[$evaluationStatus]] . ($remarks ? ' | Remarks: ' . $remarks : ''),
            'status_type' => $action === 'reject' ? 'rejected' : 'evaluation',
            'created_by' => Auth::id(),
        ]);

        BidWorkflowUpdated::dispatch($bid);

        return response()->json([
            'ok' => true,
            'bid' => $bid,
            'message' => 'Bid evaluation saved successfully.',
        ]);
    }

    public function rejectBid(Request $request, Bid $bid)
    {
        $this->ensureAssignedProject($bid->project_id);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:3'],
        ]);

        $existingNotes = trim((string) $bid->notes);
        $rejectionNote = 'Rejected by staff on ' . now()->format('M d, Y h:i A');
        $reason = trim($validated['rejection_reason']);

        $bid->update([
            'status' => 'rejected',
            'workflow_step' => Bid::STEP_DISQUALIFIED,
            'workflow_step_updated_at' => now(),
            'workflow_step_updated_by' => Auth::id(),
            'disqualified_at' => now(),
            'disqualified_by' => Auth::id(),
            'notes' => $existingNotes !== ''
                ? $existingNotes . PHP_EOL . $rejectionNote . PHP_EOL . 'Reason: ' . $reason
                : $rejectionNote . PHP_EOL . 'Reason: ' . $reason,
            'rejection_reason' => $reason,
        ]);

        BidTracking::create([
            'bid_id' => $bid->id,
            'bidder_id' => $bid->user_id,
            'project_id' => $bid->project_id,
            'status_title' => 'Documents Rejected by Staff',
            'status_description' => $reason,
            'status_type' => 'rejected',
            'created_by' => Auth::id(),
        ]);

        SystemNotification::createForUser(
            $bid->user_id,
            'Bid documents rejected',
            'Your bid documents for ' . ($bid->project->title ?? 'the project') . ' were rejected by staff.',
            'documents_rejected',
            ['project_id' => $bid->project_id, 'bid_id' => $bid->id]
        );

        BidWorkflowUpdated::dispatch($bid);

        return $this->bidActionResponse($request, $bid, 'Bid marked as rejected.');
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
        $totalBidAmount = (float) $allAssignedBids->sum('bid_amount');
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
