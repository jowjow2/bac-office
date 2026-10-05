<?php

namespace App\Http\Controllers;

use App\Models\BidTracking;
use App\Models\Assignment;
use App\Models\ProcurementRequestDocument;
use App\Models\Project;
use App\Models\ProjectProceeding;
use App\Support\ProcurementLifecycle;
use App\Support\EndUserAccess;
use App\Support\ProcurementTimeline;
use App\Support\Uploads;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The procurement detail page for the BAC (admin and staff): progress
 * timeline, current stage, next action, key dates, documents and decision
 * history, plus the project-level records (PhilGEPS posting, proceedings,
 * inspection and acceptance).
 */
class ProcurementLifecycleController extends Controller
{
    public function __construct(private readonly ProcurementLifecycle $lifecycle) {}

    public function show(Request $request, Project $project)
    {
        $this->authorizeProject($project);
        $role = $this->role($request);
        $timeline = ProcurementTimeline::forProject($project);

        // Who changed what on this procurement: project and bid audit entries.
        $bidIds = $project->bids->pluck('id');
        $auditTrail = \App\Models\AuditLog::with('user:id,name,role')
            ->where(function ($query) use ($project, $bidIds) {
                $query->where(fn ($q) => $q->where('auditable_type', Project::class)->where('auditable_id', $project->id))
                    ->orWhere(fn ($q) => $q->where('auditable_type', \App\Models\Bid::class)->whereIn('auditable_id', $bidIds));
            })
            ->latest()
            ->limit(25)
            ->get();

        $contractAward = $project->awards()
            ->with(['bid.user', 'contractImplementation.events.actor'])
            ->whereHas('bid', fn ($query) => $query->whereNotNull('notice_to_proceed_at')->whereNotNull('contract_signed_at'))
            ->latest()
            ->first();
        $implementationWorkflow = app(\App\Support\ContractImplementationWorkflow::class);
        if ($contractAward && $implementationWorkflow->eligible($contractAward)) {
            $contractAward->setRelation('contractImplementation', $implementationWorkflow->ensure($contractAward)->load('events.actor'));
        } else {
            $contractAward = null;
        }

        return view('procurement.show', [
            'routePrefix' => $role,
            'project' => $project,
            'mode' => $project->mode(),
            'current' => $timeline->current(),
            'auditTrail' => $auditTrail,
            // Before publication only the pre-procurement conference can be recorded; after it, everything else.
            'proceedingTypes' => collect(ProjectProceeding::typesFor($project->mode()))
                ->filter(fn ($label, string $type) => in_array($type, ProjectProceeding::PRE_PUBLICATION_TYPES, true) !== $project->isPublishedLocally())
                ->all(),
            'compliance' => \App\Support\PostingCompliance::for($project),
            'timeline' => $timeline,
            'stages' => $timeline->stages(),
            'nextAction' => $timeline->nextAction(),
            'keyDates' => $timeline->keyDates(),
            'history' => $timeline->history(),
            'documents' => $timeline->documents(),
            'bids' => $timeline->officialBids()->load('user'),
            'contractedBid' => $timeline->contractedBid(),
            'contractAward' => $contractAward,
        ]);
    }

    public function recordPublication(Request $request, Project $project)
    {
        $this->authorizeProject($project);
        $validated = $request->validate([
            'philgeps_reference_no' => ['required', 'string', 'max:100'],
            'philgeps_url' => ['nullable', 'url', 'max:500'],
            'philgeps_posted_at' => ['required', 'date'],
        ], [], ['philgeps_posted_at' => 'PhilGEPS posting date', 'philgeps_url' => 'PhilGEPS link']);

        $this->lifecycle->recordPublication($project, Auth::user(), $validated);

        return $this->back($request, $project, 'PhilGEPS posting recorded.');
    }

    public function recordProceeding(Request $request, Project $project)
    {
        $this->authorizeProject($project);
        $validated = $request->validate([
            'type' => ['required', Rule::in(ProjectProceeding::RECORDABLE_TYPES)],
            'title' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'recipients_count' => ['nullable', 'integer', 'min:1', 'max:500'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'outcome' => ['nullable', Rule::in(array_keys(ProjectProceeding::RECONSIDERATION_OUTCOMES))],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:20480'],
        ], [], ['occurred_at' => 'date']);

        $validated['occurred_at'] = $this->localTime($validated['occurred_at']);
        $proceeding = $this->lifecycle->recordProceeding($project, Auth::user(), $validated, $request->file('document'));

        return $this->back($request, $project, $proceeding->typeLabel().' recorded.');
    }

    public function recordInspection(Request $request, Project $project)
    {
        $this->authorizeProject($project);
        $validated = $this->validateCloseout($request, 'inspection report number');
        $this->lifecycle->recordInspection($project, Auth::user(), $validated, $request->file('document'));

        return $this->back($request, $project, 'Inspection recorded.');
    }

    public function recordAcceptance(Request $request, Project $project)
    {
        $this->authorizeProject($project);
        $validated = $this->validateCloseout($request, 'IAR / acceptance number');
        $this->lifecycle->recordAcceptance($project, Auth::user(), $validated, $request->file('document'));

        return $this->back($request, $project, 'Acceptance recorded. The procurement is complete.');
    }

    /* Files: stored privately and streamed only to users who may see them. */

    public function proceedingFile(ProjectProceeding $proceeding)
    {
        $this->authorizeProject($proceeding->project);
        abort_unless($proceeding->file_path && Storage::disk('local')->exists($proceeding->file_path), 404);

        return $this->privateDisk()->response($proceeding->file_path, $proceeding->original_name);
    }

    public function decisionFile(BidTracking $tracking)
    {
        $tracking->loadMissing('project');
        $this->authorizeProject($tracking->project);
        abort_unless($tracking->attachment_path && Storage::disk('local')->exists($tracking->attachment_path), 404);

        return $this->privateDisk()->response($tracking->attachment_path, $tracking->attachment_name);
    }

    public function requestFile(ProcurementRequestDocument $document)
    {
        $user = Auth::user();
        $document->loadMissing('request');
        $allowed = in_array($user?->role, ['admin', 'staff'], true)
            || EndUserAccess::canSeeRequest($user, $document->request);

        abort_unless($allowed, 403);

        return Uploads::download($document->file_path, $document->original_name);
    }

    private function privateDisk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        return $disk;
    }

    private function authorizeProject(?Project $project): void
    {
        $user = Auth::user();
        $allowed = $user?->role === 'admin'
            || ($user?->role === 'staff' && $project !== null && Assignment::where('staff_id', $user->id)->where('project_id', $project->id)->exists())
            || EndUserAccess::canSeeProject($user, $project);

        abort_unless($allowed, 403);
    }

    private function validateCloseout(Request $request, string $referenceLabel): array
    {
        $validated = $request->validate([
            'occurred_at' => ['required', 'date'],
            'reference_no' => ['required', 'string', 'max:100'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:20480'],
        ], [], ['occurred_at' => 'date', 'reference_no' => $referenceLabel]);

        $validated['occurred_at'] = $this->localTime($validated['occurred_at']);

        return $validated;
    }

    /** Date/time inputs are in the BAC's local time. */
    private function localTime(string $value): string
    {
        return Carbon::parse($value, config('bac-office.display_timezone'))
            ->timezone(config('app.timezone'))
            ->toDateTimeString();
    }

    private function back(Request $request, Project $project, string $message)
    {
        return redirect()->route($this->role($request).'.procurement.show', $project)->with('success', $message);
    }

    private function role(Request $request): string
    {
        return $request->routeIs('staff.*') ? 'staff' : 'admin';
    }
}
