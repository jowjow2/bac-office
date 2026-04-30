<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Assignment;
use App\Models\Bid;
use App\Models\Project;
use App\Support\SystemNotification;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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

    public function validateBidDocuments(Bid $bid)
    {
        $this->ensureAssignedProject($bid->project_id);

        $existingNotes = trim((string) $bid->notes);
        $validationNote = 'Documents validated by staff on ' . now()->format('M d, Y h:i A');

        $bid->update([
            'status' => $bid->status === 'pending' ? 'approved' : $bid->status,
            'notes' => $existingNotes !== ''
                ? $existingNotes . PHP_EOL . $validationNote
                : $validationNote,
        ]);

        SystemNotification::createForUser(
            $bid->user_id,
            'Bid documents validated',
            'Your bid documents for ' . ($bid->project->title ?? 'the project') . ' were validated by staff.',
            'documents_validated',
            ['project_id' => $bid->project_id, 'bid_id' => $bid->id]
        );

        return redirect()
            ->back()
            ->with('success', 'Bid documents marked as validated.');
    }

    public function rejectBid(Bid $bid)
    {
        $this->ensureAssignedProject($bid->project_id);

        $existingNotes = trim((string) $bid->notes);
        $rejectionNote = 'Rejected by staff on ' . now()->format('M d, Y h:i A');

        $bid->update([
            'status' => 'rejected',
            'notes' => $existingNotes !== ''
                ? $existingNotes . PHP_EOL . $rejectionNote
                : $rejectionNote,
        ]);

        SystemNotification::createForUser(
            $bid->user_id,
            'Bid documents rejected',
            'Your bid documents for ' . ($bid->project->title ?? 'the project') . ' were rejected by staff.',
            'documents_rejected',
            ['project_id' => $bid->project_id, 'bid_id' => $bid->id]
        );

        return redirect()
            ->back()
            ->with('success', 'Bid marked as rejected.');
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
