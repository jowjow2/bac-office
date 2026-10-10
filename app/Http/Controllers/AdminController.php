<?php

namespace App\Http\Controllers;

use App\Mail\BidderRejectedMail;
use App\Mail\BidderIncompleteRequirementsMail;
use App\Mail\BidderRequirementsActionMail;
use App\Mail\WelcomeMail;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Award;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\Bidder;
use App\Models\BidderSanction;
use App\Models\BidderDocument;
use App\Models\BidderRequirementRequest;
use App\Models\Project;
use App\Models\User;
use App\Support\BidderRegistrationRequirements;
use App\Support\BidHistory;
use App\Support\BidProgress;
use App\Support\BidWorkflow;
use App\Support\DocumentPreview;
use App\Support\ProcurementPipeline;
use App\Support\Uploads;
use App\Support\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * Procurement overview: KPIs, the pipeline by stage, the searchable
     * register of every purchase request and project, upcoming activities
     * and bidder registrations waiting for review.
     */
    public function dashboard(Request $request)
    {
        $pipeline = ProcurementPipeline::forAdmin();
        $filters = ProcurementPipeline::filtersFrom($request);

        $pendingRegistrations = User::where('role', 'bidder')
            ->where('status', 'pending')
            ->with('philgepsCertificate')
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard.office', [
            'role' => 'admin',
            'filters' => $filters,
            'rows' => $pipeline->paginate($filters, 12),
            'kpis' => $kpis = $pipeline->kpis(),
            // What is waiting for this person, in place of bare totals.
            'actions' => \App\Support\DashboardActions::for('admin', $kpis),
            'buckets' => $pipeline->bucketCounts($filters),
            'upcoming' => $pipeline->upcoming(),
            'pendingRegistrations' => $pendingRegistrations,
            'pendingRegistrationsCount' => User::where('role', 'bidder')->where('status', 'pending')->count(),
        ]);
    }

    public function projects(Request $request)
    {
        $search = $this->requestString($request, 'search');
        $status = $this->requestString($request, 'status');
        $showArchived = $request->boolean('archived');

        $projectStatusCounts = Project::query()
            ->whereNull('archived_at')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $projectTotals = [
            'all' => Project::whereNull('archived_at')->count(),
            'draft' => (int) $projectStatusCounts->get('draft', 0),
            'approved_for_bidding' => (int) $projectStatusCounts->get('approved_for_bidding', 0),
            'open' => (int) $projectStatusCounts->get('open', 0),
            'closed' => (int) $projectStatusCounts->get('closed', 0),
            'awarded' => (int) $projectStatusCounts->get('awarded', 0),
        ];

        $projectsQuery = $this->filteredProjectsQuery($search, $status, $showArchived);
        $exportRows = $this->exportRowsForProjects((clone $projectsQuery)->latest()->get());
        $projects = $projectsQuery
            ->latest()
            ->paginate(10)
            ->withQueryString();
        // For assigning staff straight from an "Unassigned" chip in the list.
        $assignableStaff = User::where('role', 'staff')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'office']);

        // Purchase requests forwarded to the BAC that have no project yet: where projects start.
        $waitingRequestsQuery = \App\Models\ProcurementRequest::query()
            ->where('status', \App\Models\ProcurementRequest::STATUS_FORWARDED)
            ->whereDoesntHave('project');
        $waitingRequestsCount = (clone $waitingRequestsQuery)->count();
        $waitingRequests = $waitingRequestsQuery->orderBy('forwarded_at')->orderBy('id')->take(5)->get();
        $archivedCount = Project::whereNotNull('archived_at')->count();

        return view('admin.projects', compact(
            'projects',
            'search',
            'status',
            'projectTotals',
            'showArchived',
            'exportRows',
            'assignableStaff',
            'waitingRequests',
            'waitingRequestsCount',
            'archivedCount'
        ));
    }

    public function exportProjects(Request $request)
    {
        $search = $this->requestString($request, 'search');
        $status = $this->requestString($request, 'status');
        $showArchived = $request->boolean('archived');
        $selectedStatuses = collect($request->input('statuses', []))
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => in_array($value, ['draft', 'approved_for_bidding', 'open', 'closed', 'awarded'], true))
            ->values()
            ->all();


        $projects = $this->filteredProjectsQuery($search, $status, $showArchived)
            ->latest()
            ->get();

        if ($request->has('statuses')) {
            $projects = $projects
                ->filter(fn (Project $project) => in_array($this->projectExportStatus($project)['key'], $selectedStatuses, true))
                ->values();
        }

        return response()->streamDownload(function () use ($projects) {
            $stream = fopen('php://output', 'w');
            $safeText = static fn ($value) => preg_match('/^[\s]*[=+@-]|^[\t\r\n]/u', (string) $value)
                ? "'".(string) $value : (string) $value;
            fputcsv($stream, ['Reference No.', 'Project', 'Procurement Mode', 'Budget (PHP)', 'Submission Deadline', 'Bid Opening', 'Staff', 'Bids', 'Status'], ',', '"', '');
            foreach ($projects as $project) {
                $staff = $project->assignments->first()?->staff?->name ?? 'Unassigned';
                $status = $this->projectExportStatus($project);
                fputcsv($stream, [
                    $safeText($project->reference_no),
                    $safeText($project->title),
                    $safeText($project->mode()->label()),
                    number_format((float) $project->budget, 2, '.', ''),
                    $project->deadline?->format('Y-m-d H:i') ?? '',
                    $project->schedule?->bid_opening_date?->format('Y-m-d H:i') ?? '',
                    $safeText($staff),
                    (int) $project->bids_count,
                    $safeText($status['label']),
                ], ',', '"', '');
            }
            fclose($stream);
        }, 'projects-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function requestString(Request $request, string $key, ?string $default = null): string
    {
        $value = $request->input($key, $default);

        return is_scalar($value) ? trim((string) $value) : (string) $default;
    }

    private function filteredProjectsQuery(string $search, string $status, bool $showArchived)
    {
        return Project::withCount('bids')
            ->with(['assignments.staff', 'schedule', 'documents', 'bids:id,project_id,bid_amount,financial_opened_at,financial_opened_by'])
            ->when($showArchived, function ($query) {
                $query->whereNotNull('archived_at');
            }, function ($query) {
                $query->whereNull('archived_at');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['approved_for_bidding', 'open', 'closed', 'awarded', 'draft'], true), function ($query) use ($status) {
                $query->where('status', $status);
            });
    }

    private function exportRowsForProjects(Collection $projects): array
    {
        return $projects->map(function (Project $project): array {
            $status = $this->projectExportStatus($project);

            return [
                'title' => $project->title,
                'budget_label' => 'PHP '.number_format((float) $project->budget, 2),
                'status' => $status['key'],
                'status_label' => $status['label'],
                'status_class' => $status['class'],
            ];
        })->values()->all();
    }

    private function projectExportStatus(Project $project): array
    {
        $status = in_array($project->status, ['draft', 'approved_for_bidding', 'open', 'closed', 'awarded'], true)
            ? $project->status
            : 'draft';

        return [
            'key' => $status,
            'label' => Str::headline($status),
            'class' => str_replace('_', '-', $status),
        ];
    }

    public function createProject(Request $request)
    {
        $procurementRequest = null;

        if ($request->filled('request')) {
            $procurementRequest = \App\Models\ProcurementRequest::find($request->integer('request'));
            abort_unless($procurementRequest && $procurementRequest->awaitsBac() && ! $procurementRequest->project()->exists(), 404);

            // Start the wizard from the request unless the form is being redisplayed after an error.
            if (old('procurement_request_id') === null) {
                session()->now('_old_input', [
                    'procurement_request_id' => $procurementRequest->id,
                    'title' => $procurementRequest->title,
                    'description' => $procurementRequest->specifications
                        .($procurementRequest->justification ? "\n\nPurpose: ".$procurementRequest->justification : ''),
                    'category' => $procurementRequest->category,
                    'end_user_unit' => $procurementRequest->end_user_office,
                    // The LGU itself; the BAC narrows it to the barangay or site when needed.
                    'location' => Str::after((string) config('bac-office.procuring_entity'), 'Municipality of '),
                    'legal_basis' => \App\Support\ProcurementMode::RA_12009,
                    'source_of_fund' => $procurementRequest->fund_source,
                    'contract_duration' => $procurementRequest->delivery_period,
                    'budget' => number_format((float) $procurementRequest->estimated_cost, 2, '.', ''),
                ]);
            }
        }

        return view('admin.projects-wizard', ['procurementRequest' => $procurementRequest]);
    }

    /**
     * Award-related Invitation to Bid details, normalized: weighted criteria
     * only for MEARB/MARB, the quality-price ratio only for MEARB, the
     * evaluation procedure only for consulting services, and the opening
     * venue only for competitive bidding.
     *
     * @return array{evaluation_criteria:?array,quality_price_ratio:?int,evaluation_procedure:?string,bid_opening_venue:?string}
     */
    private static function invitationDetails(array $validated, bool $competitive): array
    {
        $criterion = $validated['award_criterion'] ?? null;
        $weighted = $competitive && in_array($criterion, ['mearb', 'marb'], true);
        $rows = collect($validated['evaluation_criteria'] ?? [])
            ->filter(fn ($row) => is_array($row) && filled($row['name'] ?? null))
            ->map(fn (array $row) => ['name' => trim($row['name']), 'weight' => round((float) ($row['weight'] ?? 0), 2)])
            ->values()
            ->all();

        return [
            'evaluation_criteria' => $weighted && $rows !== [] ? $rows : null,
            'quality_price_ratio' => $weighted && $criterion === 'mearb' && filled($validated['quality_price_ratio'] ?? null) ? (int) $validated['quality_price_ratio'] : null,
            'evaluation_procedure' => $competitive && ($validated['category'] ?? null) === 'consultancy' ? ($validated['evaluation_procedure'] ?? null) : null,
            'bid_opening_venue' => $competitive && filled($validated['bid_opening_venue'] ?? null) ? trim($validated['bid_opening_venue']) : null,
        ];
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
            'document_type' => ['nullable', Rule::in(['invitation_to_bid', 'bidding_documents', 'terms_of_reference', 'technical_specifications', 'bill_of_quantities', 'project_plans', 'supplemental_bulletin', 'other'])],
            'document_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:20480',
            'budget' => 'required|numeric|min:0|lte:9999999999999.99',
            'award_criterion' => ['nullable', Rule::in(array_keys(Project::AWARD_CRITERIA))],
            // Posting for bidding goes through Publish (RA 9184 posting checks);
            // closed/awarded come only from bid opening and the Notice of Award.
            'status' => 'required|in:draft,approved_for_bidding',
            'deadline' => 'required|date|after:today',
        ], [
            'budget.lte' => 'Budget must not exceed 9,999,999,999,999.99.',
            'status.in' => 'Create the project as Draft or Approved for Bidding, then publish it once it is ready for posting.',
        ]);

        $documentType = $validated['document_type'] ?? null;
        unset($validated['document_type']);

        $documentFiles = $this->extractProjectDocumentFiles($request);
        unset($validated['document_files']);
        unset($validated['document_file']);

        $project = Project::create($validated + ['reference_no' => Project::nextReferenceNo($validated['category'] ?? null)]);
        // Competitive bidding starts at the ABC schedule's maximum fee.
        $project->forceFill(\App\Support\BiddingDocumentsFee::resolve($project, ['bidding_fee_mode' => \App\Support\BiddingDocumentsFee::MODE_SCHEDULE]))->save();
        $this->storeProjectDocuments($project, $documentFiles, $documentType);

        $redirectUrl = $validated['status'] === 'draft'
            ? route('admin.projects') . '?status=draft'
            : route('admin.projects');

        return redirect($redirectUrl)->with('success', 'Project created successfully.');
    }

    public function storeProjectWizard(Request $request)
    {
        $status = $request->input('status') === 'open' ? 'open' : 'draft';
        $publishRule = $status === 'open' ? 'required' : 'nullable';
        // Every mode keeps submissions sealed until its scheduled opening.
        $competitive = \App\Support\ProcurementMode::familyOf($request->input('procurement_mode')) === \App\Support\ProcurementMode::FAMILY_COMPETITIVE;
        $openingRule = $status === 'open' ? 'required' : 'nullable';
        $confirmationRule = $status === 'open' ? 'accepted' : 'nullable';

        $validated = $request->validate([
            'title' => "{$publishRule}|string|max:255",
            'description' => "{$publishRule}|string",
            'category' => "{$publishRule}|string|in:goods,services,infrastructure,consultancy",
            'location' => "{$publishRule}|string|max:255",
            'procurement_mode' => [$publishRule, 'string', Rule::in(\App\Support\ProcurementMode::keys())],
            'award_criterion' => ['nullable', Rule::in(array_keys(Project::AWARD_CRITERIA))],
            'evaluation_procedure' => ['nullable', Rule::in(array_keys(Project::EVALUATION_PROCEDURES))],
            'evaluation_criteria' => 'nullable|array|max:20',
            'evaluation_criteria.*.name' => 'nullable|string|max:255',
            'evaluation_criteria.*.weight' => 'nullable|numeric|min:0|max:100',
            'quality_price_ratio' => 'nullable|integer|min:1|max:99',
            'bid_opening_venue' => 'nullable|string|max:500',
            // Held before the Invitation to Bid (RA 12009 IRR Sec. 49.1); recorded with the project.
            'pre_procurement_conference_at' => 'nullable|date|before_or_equal:now',
            'pre_procurement_reference' => 'nullable|string|max:100',
            'negotiation_ground' => ['nullable', Rule::in(array_keys(\App\Support\ProcurementMode::NEGOTIATION_GROUNDS))],
            'source_of_fund' => "{$publishRule}|string|max:255",
            'contract_duration' => "{$publishRule}|string|max:255",
            'budget' => "{$publishRule}|numeric|min:0|lte:9999999999999.99",
            'status' => 'required|in:draft,open',
            'confirm_correct' => $confirmationRule,
            'date_posted' => 'nullable|date',
            'pre_bid_conference_date' => 'nullable|date',
            'clarification_deadline' => 'nullable|date',
            'bid_submission_deadline' => "{$publishRule}|date",
            'bid_opening_date' => "{$openingRule}|date",
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
            // Notice details (Invitation to Bid / PhilGEPS posting).
            'philgeps_reference_no' => 'nullable|string|max:100',
            'end_user_unit' => 'nullable|string|max:255',
            // How bids are submitted per the notice; the bidding documents fee is paid at the BAC.
            'submission_mode' => ['nullable', Rule::in([Project::SUBMISSION_MANUAL, Project::SUBMISSION_ELECTRONIC])],
            'submission_venue' => 'nullable|string|max:255',
            'bidding_documents_fee' => 'nullable|numeric|min:0|lte:9999999999999.99',
            'bidding_fee_mode' => ['nullable', Rule::in(\App\Support\BiddingDocumentsFee::MODES)],
            'bidding_fee_reason' => 'nullable|string|max:2000',
            'payment_venue' => 'nullable|string|max:255',
            'electronic_submission_authority' => 'nullable|string|max:255',
            'legal_basis' => ['nullable', Rule::in(array_keys(Project::LEGAL_BASES))],
            'philgeps_url' => 'nullable|url|max:500',
            'bid_security_required' => 'nullable|boolean',
            'bid_security_notes' => 'nullable|string|max:2000',
            'procurement_request_id' => ['nullable', 'integer', Rule::exists('procurement_requests', 'id')],
        ], [
            'budget.lte' => 'Budget must not exceed 9,999,999,999,999.99.',
            'bidding_documents_fee.lte' => 'Bidding documents fee must not exceed 9,999,999,999,999.99.',
        ]);

        if ($status === 'open') {
            $submissionDeadline = \Carbon\Carbon::parse($validated['bid_submission_deadline']);
            $openingDate = filled($validated['bid_opening_date'] ?? null) ? \Carbon\Carbon::parse($validated['bid_opening_date']) : null;
            $scheduleErrors = [];

            if ($openingDate !== null && $openingDate->lessThanOrEqualTo($submissionDeadline)) {
                $scheduleErrors['bid_opening_date'] = $competitive
                    ? 'Bid opening must be after the bid submission deadline.'
                    : 'The opening of quotations must be after the quotation deadline.';
            }
            $openingDate ??= $submissionDeadline;

            foreach ([
                'clarification_deadline' => 'Clarification deadline must be before the bid submission deadline.',
                'pre_bid_conference_date' => 'Pre-bid conference must be before the bid submission deadline.',
            ] as $field => $message) {
                if (!empty($validated[$field]) && \Carbon\Carbon::parse($validated[$field])->greaterThanOrEqualTo($submissionDeadline)) {
                    $scheduleErrors[$field] = $message;
                }
            }

            if (!empty($validated['evaluation_start_date']) && \Carbon\Carbon::parse($validated['evaluation_start_date'])->lessThan($openingDate)) {
                $scheduleErrors['evaluation_start_date'] = 'Evaluation must start on or after bid opening.';
            }

            if (!empty($validated['expected_award_date'])) {
                $minimumAwardDate = !empty($validated['evaluation_start_date'])
                    ? \Carbon\Carbon::parse($validated['evaluation_start_date'])
                    : $openingDate;
                if (\Carbon\Carbon::parse($validated['expected_award_date'])->lessThan($minimumAwardDate)) {
                    $scheduleErrors['expected_award_date'] = 'Expected award date must be on or after evaluation.';
                }
            }

            if ($scheduleErrors !== []) {
                return back()->withInput()->withErrors($scheduleErrors);
            }
        }
        $projectDocumentFiles = $request->file('project_documents', []);

        $procurementRequest = null;
        if (! empty($validated['procurement_request_id'])) {
            $procurementRequest = \App\Models\ProcurementRequest::findOrFail($validated['procurement_request_id']);
            if (! $procurementRequest->awaitsBac() || $procurementRequest->project()->exists()) {
                return back()->withInput()->withErrors([
                    'procurement_request_id' => 'Only a request forwarded to the BAC that has no project yet can be used.',
                ]);
            }
        }

        if (filled($validated['bid_security_notes'] ?? null) && ! ($validated['bid_security_required'] ?? false)) {
            $validated['bid_security_notes'] = null;
        }

        // Invitation to Bid details kept only where they apply (IRR Sec. 50.2(e)-(h)).
        $invitation = self::invitationDetails($validated, $competitive);

        // Bidding documents fee: the ABC schedule's maximum unless the BAC lowers or waives it.
        $feeProbe = (new Project)->forceFill([
            'procurement_mode' => $validated['procurement_mode'] ?? null,
            'legal_basis' => $validated['legal_basis'] ?? 'ra_12009',
            'budget' => $validated['budget'] ?? 0,
        ]);
        $feeAttributes = \App\Support\BiddingDocumentsFee::resolve(
            $feeProbe,
            $request->only(['bidding_fee_mode', 'bidding_documents_fee', 'bidding_fee_reason']) + ['bidding_fee_mode' => null]
        );

        DB::beginTransaction();
        try {
            $project = Project::create([
                'title' => $validated['title'] ?? 'Untitled Draft Project',
                'description' => $validated['description'] ?? '',
                'category' => $validated['category'] ?? null,
                'location' => $validated['location'] ?? null,
                'procurement_mode' => $validated['procurement_mode'] ?? null,
                'award_criterion' => $validated['award_criterion'] ?? null,
                'negotiation_ground' => ($validated['procurement_mode'] ?? null) === 'negotiated_procurement' ? ($validated['negotiation_ground'] ?? null) : null,
                'source_of_fund' => $validated['source_of_fund'] ?? null,
                'contract_duration' => $validated['contract_duration'] ?? null,
                'budget' => $validated['budget'] ?? 0,
                // Posted only after the posting checks of its mode and legal basis pass.
                'status' => 'draft',
                'created_by' => Auth::id(),
                'deadline' => $validated['bid_submission_deadline'] ?? null,
                'reference_no' => Project::nextReferenceNo($validated['category'] ?? null),
                'philgeps_reference_no' => $validated['philgeps_reference_no'] ?? null,
                'end_user_unit' => $validated['end_user_unit'] ?? null,
                'procurement_request_id' => $procurementRequest?->id,
                'legal_basis' => $validated['legal_basis'] ?? 'ra_12009',
                'philgeps_url' => $validated['philgeps_url'] ?? null,
                'bid_security_required' => (bool) ($validated['bid_security_required'] ?? false),
                'bid_security_notes' => $validated['bid_security_notes'] ?? null,
                'submission_mode' => $validated['submission_mode'] ?? Project::SUBMISSION_ELECTRONIC,
                'submission_venue' => $validated['submission_venue'] ?? null,
                'bidding_documents_fee' => $feeAttributes['bidding_documents_fee'] ?? null,
                'bidding_fee_mode' => $feeAttributes['bidding_fee_mode'] ?? null,
                'bidding_fee_reason' => $feeAttributes['bidding_fee_reason'] ?? null,
                'payment_venue' => filled($validated['payment_venue'] ?? null) ? trim($validated['payment_venue']) : null,
                'electronic_submission_authority' => filled($validated['electronic_submission_authority'] ?? null) ? trim($validated['electronic_submission_authority']) : null,
                'electronic_submission_authorized_at' => filled($validated['electronic_submission_authority'] ?? null) ? now() : null,
                'electronic_submission_authorized_by' => filled($validated['electronic_submission_authority'] ?? null) ? Auth::id() : null,
            ] + $invitation);

            $project->requirement()->create([
                'eligibility_requirements' => $validated['eligibility_requirements'] ?? null,
                'technical_requirements' => $validated['technical_requirements'] ?? null,
                'financial_requirements' => $validated['financial_requirements'] ?? null,
                'required_documents' => $validated['required_documents'] ?? null,
                'qualification_notes' => $validated['qualification_notes'] ?? null,
                'special_instructions' => $validated['special_instructions'] ?? null,
            ]);

            $project->schedule()->create([
                'date_posted' => $validated['date_posted'] ?? null,
                'pre_bid_conference_date' => $validated['pre_bid_conference_date'] ?? null,
                'clarification_deadline' => $validated['clarification_deadline'] ?? null,
                'bid_submission_deadline' => $validated['bid_submission_deadline'] ?? null,
                'bid_opening_date' => $validated['bid_opening_date'] ?? null,
                'evaluation_start_date' => $validated['evaluation_start_date'] ?? null,
                'expected_award_date' => $validated['expected_award_date'] ?? null,
            ]);

            if ($procurementRequest) {
                $procurementRequest->update(['status' => \App\Models\ProcurementRequest::STATUS_IN_PROCUREMENT]);
                AuditLog::log('procurement_request_converted', $procurementRequest, ['status' => \App\Models\ProcurementRequest::STATUS_FORWARDED], [
                    'status' => \App\Models\ProcurementRequest::STATUS_IN_PROCUREMENT,
                    'project_id' => $project->id,
                ]);
            }

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

            if ($competitive && filled($validated['pre_procurement_conference_at'] ?? null)) {
                app(\App\Support\ProcurementLifecycle::class)->recordProceeding($project, Auth::user(), [
                    'type' => \App\Models\ProjectProceeding::TYPE_PRE_PROCUREMENT,
                    'title' => 'Pre-procurement conference',
                    'occurred_at' => \Carbon\Carbon::parse($validated['pre_procurement_conference_at'], config('app.timezone', 'Asia/Manila')),
                    'reference_no' => $validated['pre_procurement_reference'] ?? null,
                ], null);
            }

            $blockers = [];
            if ($status === 'open') {
                $project->load(['schedule', 'documents']);
                $publicationAt = now(config('app.timezone', 'Asia/Manila'));
                $blockers = $project->publicationBlockers(now(config('app.timezone', 'Asia/Manila')), $publicationAt);
                if ($blockers === []) {
                    app(\App\Support\ProjectPublication::class)->publish($project, Auth::user(), $publicationAt);
                }
            }

            DB::commit();

            SystemNotification::createForUser(
                Auth::id(),
                'Project Created',
                'Project "' . $project->title . '" (' . $project->reference_no . ') has been created with status: ' . $project->status,
                'project_created',
                ['project_id' => $project->id]
            );

            if ($blockers !== []) {
                return redirect(route('admin.projects') . '?status=draft')
                    ->with('error', 'Saved as draft (' . $project->reference_no . '), not yet published in the BAC system: ' . implode(' ', $blockers));
            }

            $redirectUrl = $project->status === 'draft'
                ? route('admin.projects') . '?status=draft'
                : route('admin.projects');
            $warnings = $project->status === 'open' ? $project->fresh('schedule')->scheduleWarningList() : [];

            return redirect($redirectUrl)->with('success', 'Project created successfully.')->with('schedule_warnings', $warnings);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create project from wizard.', [
                'status' => $status,
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->withErrors(['error' => 'Failed to create project: ' . $e->getMessage()]);
        }
    }
    public function allBids(Request $request)
    {
        return $this->bidsPage($request, 'admin');
    }

    /**
     * The bid register: every bid for the BAC Admin, or only the assigned projects'
     * bids for BAC Staff ($projectIds), on the same page and review modal.
     */
    public function bidsPage(Request $request, string $portal, ?array $projectIds = null)
    {
        $search = $this->requestString($request, 'search');
        $status = $this->requestString($request, 'status');
        $projectFilter = $this->requestString($request, 'project');
        $modeFilter = $this->requestString($request, 'mode');
        // `proposal` is retained as a backwards-compatible query alias.
        $documentStatusFilter = $this->requestString($request, 'document_status') ?: $this->requestString($request, 'proposal');
        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $allBids = $this->bidsForStatusFilter(
            $this->filteredBidsQuery($search, $projectFilter, $modeFilter, $projectIds)->latest()->orderByDesc('id')->get(),
            $status
        );
        $allBids = $this->bidsForDocumentStatusFilter($allBids, $documentStatusFilter);

        $page = max(1, (int) $request->query('page', 1));
        $bids = new \Illuminate\Pagination\LengthAwarePaginator(
            $allBids->forPage($page, $perPage)->values(),
            $allBids->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        if ($bids->currentPage() > $bids->lastPage()) {
            return redirect()->route($portal === 'admin' ? 'admin.bids' : 'staff.review-bids', array_merge($request->except('page'), ['page' => $bids->lastPage()]));
        }

        // Abstract-of-bids ranking per project, computed over all of the project's bids.
        $rankedProject = $projectFilter !== '' && ctype_digit($projectFilter)
            ? Project::query()->when($projectIds !== null, fn ($query) => $query->whereIn('id', $projectIds))->find((int) $projectFilter)
            : null;
        $rankings = app(\App\Support\BidRanking::class)->forProjects(
            collect($bids->items())->pluck('project_id')->push($rankedProject?->id)
        );
        $projectRanking = $rankedProject
            ? Bid::with('user')->where('project_id', $rankedProject->id)->get()
                ->filter(fn (Bid $bid) => ($rankings[$bid->id]['status'] ?? null) === \App\Support\BidRanking::RANKED)
                ->sortBy(fn (Bid $bid) => $rankings[$bid->id]['rank'])->values()
            : collect();

        $exportRows = $this->exportRowsForBids($allBids);
        $projects = Project::query()->when($projectIds !== null, fn ($query) => $query->whereIn('id', $projectIds))
            ->orderBy('title')->get(['id', 'reference_no', 'title', 'procurement_mode']);
        $modeOptions = collect(\App\Support\ProcurementMode::MODES)
            ->mapWithKeys(fn (array $mode, string $key) => [$key => $mode['label']])
            ->all();
        $documentStatusOptions = [
            'complete' => 'Complete',
            'incomplete' => 'Incomplete',
            'sealed' => 'Received / sealed',
            'manual' => 'Manual envelope',
            'draft' => 'Draft only',
        ];
        $statusOptions = BidProgress::ADMIN_STAGES;
        $summary = [
            'total' => $allBids->count(),
            'sealed' => $allBids->filter(fn (Bid $bid) => $bid->isSealed())->count(),
            'ready' => $allBids->filter(fn (Bid $bid) => ! $bid->isSealed() && $bid->progress()->adminStage() === BidProgress::STAGE_PRELIMINARY)->count(),
            'decided' => $allBids->filter(fn (Bid $bid) => in_array($bid->progress()->adminStage(), [BidProgress::STAGE_EVALUATION, BidProgress::STAGE_POST_QUALIFICATION, BidProgress::STAGE_RECOMMENDATION], true))->count(),
        ];

        return view('admin.bids', compact(
            'bids', 'projects', 'search', 'status', 'projectFilter', 'modeFilter',
            'documentStatusFilter', 'exportRows', 'statusOptions', 'modeOptions',
            'documentStatusOptions', 'summary', 'rankings', 'rankedProject', 'projectRanking', 'portal'
        ));
    }

    public function exportBids(Request $request)
    {
        return $this->bidsExport($request);
    }

    public function bidsExport(Request $request, ?array $projectIds = null)
    {
        $search = $this->requestString($request, 'search');
        $status = $this->requestString($request, 'status');

        $projectFilter = $this->requestString($request, 'project');
        $modeFilter = $this->requestString($request, 'mode');
        $documentStatusFilter = $this->requestString($request, 'document_status') ?: $this->requestString($request, 'proposal');
        $selectedStatuses = collect($request->input('statuses', []))
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => array_key_exists($value, BidProgress::ADMIN_STAGES))
            ->values()
            ->all();

        $bids = $this->bidsForStatusFilter(
            $this->filteredBidsQuery($search, $projectFilter, $modeFilter, $projectIds)->latest()->orderByDesc('id')->get(),
            $status
        );
        $bids = $this->bidsForDocumentStatusFilter($bids, $documentStatusFilter);

        if ($request->has('statuses')) {
            $bids = $bids
                ->filter(fn (Bid $bid) => in_array($bid->progress()->adminStage(), $selectedStatuses, true))
                ->values();
        }

        return $this->streamBidsCsv($bids);
    }

    private function filteredBidsQuery(string $search, string $projectFilter, string $modeFilter, ?array $projectIds = null)
    {
        return Bid::with(['project.awards', 'project.rebidProject', 'project.schedule', 'project.requirement', 'award', 'user.philgepsCertificate', 'documents'])
            ->when($projectIds !== null, fn ($query) => $query->whereIn('project_id', $projectIds))
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
            ->when($projectFilter !== '' && ctype_digit($projectFilter), function ($query) use ($projectFilter) {
                $query->where('project_id', (int) $projectFilter);
            })
            ->when($modeFilter !== '' && array_key_exists($modeFilter, \App\Support\ProcurementMode::MODES), function ($query) use ($modeFilter) {
                $query->whereHas('project', fn ($projectQuery) => $projectQuery->where('procurement_mode', $modeFilter));
            });
    }

    private function bidsForDocumentStatusFilter(Collection $bids, string $filter): Collection
    {
        if ($filter === '') {
            return $bids->values();
        }

        return $bids->filter(function (Bid $bid) use ($filter): bool {
            $status = $bid->submissionDocumentStatus()['key'];

            return match ($filter) {
                'uploaded' => filled($bid->proposal_file),
                'missing' => blank($bid->proposal_file),
                'complete' => in_array($status, ['complete', 'received_sealed'], true),
                'incomplete' => $status === 'incomplete',
                'sealed' => $status === 'received_sealed',
                'manual' => $status === 'sealed_envelope',
                'draft' => $status === 'draft',
                default => true,
            };
        })->values();
    }

    /**
     * Keep only bids whose current stage matches the ?status= filter. Old
     * values (pending/approved/rejected/awarded) map onto the stages they covered.
     */
    private function bidsForStatusFilter(Collection $bids, string $status): Collection
    {
        $stages = BidProgress::stagesForFilter($status);

        return $stages === null
            ? $bids->values()
            : $bids->filter(fn (Bid $bid) => in_array($bid->progress()->adminStage(), $stages, true))->values();
    }

    private function exportRowsForBids(Collection $bids): array
    {
        return $bids->map(function (Bid $bid): array {
            $status = $bid->progress()->adminStatus();

            $documentStatus = $bid->submissionDocumentStatus();

            return [
                'bidder' => $bid->user?->company ?: ($bid->user?->name ?? 'N/A'),
                'amount_label' => $bid->isFinancialSealed() ? 'Sealed' : 'PHP ' . number_format((float) $bid->amount, 2),
                'status' => $status['key'],
                'status_label' => $status['label'],
                'status_class' => $status['class'],
                'has_proposal' => in_array($documentStatus['key'], ['complete', 'received_sealed'], true),
                'mode_label' => $bid->project?->mode()->label() ?? 'Not specified',
                'document_status_label' => $documentStatus['label'],
                'receipt_no' => $bid->receipt_no ?: '—',
                'submitted_at' => ($bid->submitted_at ?? $bid->created_at)?->timezone(config('bac-office.display_timezone'))->format('Y-m-d H:i:s'),
            ];
        })->values()->all();
    }

    /**
     * CSV of bids. Amounts of bids that are still sealed are not exported.
     */
    private function streamBidsCsv(Collection $bids)
    {
        return response()->streamDownload(function () use ($bids) {
            $stream = fopen('php://output', 'w');
            // Prevent user-controlled spreadsheet cells from becoming formulas.
            $safeText = static fn ($value) => preg_match('/^[\s]*[=+@-]|^[\t\r\n]/u', (string) $value)
                ? "'".(string) $value : (string) $value;
            fputcsv($stream, ['Bid ID', 'Bidder', 'Email', 'Project', 'Bid Amount (PHP)', 'Budget (PHP)', 'Variance (%)', 'Submitted', 'Status', 'Project Reference', 'Procurement Mode', 'Receipt / Reference No.', 'Document Status'], ',', '"', '');
            foreach ($bids as $bid) {
                $budget = (float) ($bid->project?->budget ?? 0);
                $sealed = $bid->isFinancialSealed();
                $variance = ! $sealed && $budget > 0 ? round(((float) $bid->amount - $budget) / $budget * 100, 1) : '';
                fputcsv($stream, [
                    $bid->id,
                    $safeText($bid->user?->company ?: $bid->user?->name),
                    $safeText($bid->user?->email),
                    $safeText($bid->project?->title),
                    $sealed ? 'Sealed' : $bid->amount,
                    $budget,
                    $variance,
                    $bid->created_at?->toDateTimeString(),
                    $bid->progress()->adminStatus()['label'],
                    $safeText($bid->project?->reference_no),
                    $safeText($bid->project?->mode()->label()),
                    $safeText($bid->receipt_no),
                    $safeText($bid->submissionDocumentStatus()['label']),
                ], ',', '"', '');
            }
            fclose($stream);
        }, 'bids-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Bulk actions are limited to export: decisions are recorded one bid at a
     * time, because each needs its own document verification and reason.
     */
    public function bulkBids(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['export'])],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:bids,id'],
        ]);

        $bids = Bid::with(['project.awards', 'project.rebidProject', 'award', 'user'])
            ->whereIn('id', $data['ids'])
            ->orderBy('id')
            ->get();

        return $this->streamBidsCsv($bids);
    }

    public function users(Request $request)
    {
        $search = $this->requestString($request, 'search');
        $filter = $this->requestString($request, 'filter', 'all');
        $bidderApprovalAvailable = Schema::hasTable('bidders');
        $bidderSanctionsAvailable = Schema::hasTable('bidder_sanctions');

        $users = User::query()
            ->when($bidderApprovalAvailable, function ($query) use ($bidderSanctionsAvailable) {
                $query->with([
                    'bidderProfile' => function ($profileQuery) use ($bidderSanctionsAvailable) {
                        if ($bidderSanctionsAvailable) {
                            $profileQuery->with(['activeSanction.creator', 'sanctions.creator']);
                        }
                    },
                ]);
            })
            ->when($filter === 'admin', fn ($query) => $query->where('role', 'admin'))
            ->when($filter === 'staff', fn ($query) => $query->where('role', 'staff'))
            // End-user office accounts were retired; any left in the database stay out of the list.
            ->where('role', '!=', 'end_user')
            ->when($filter === 'bidder', fn ($query) => $query->where('role', 'bidder'))
            ->when($filter === 'pending' && $bidderApprovalAvailable, fn ($query) => $query->where('role', 'bidder')->whereHas('bidderProfile', fn ($profileQuery) => $profileQuery->where('approval_status', 'pending')))
            ->when($filter === 'pending' && ! $bidderApprovalAvailable, fn ($query) => $query->where('status', 'pending'))
            ->when(in_array($filter, [BidderSanction::TYPE_SUSPENDED, BidderSanction::TYPE_BLACKLISTED], true) && $bidderSanctionsAvailable, function ($query) use ($filter) {
                $query->where('role', 'bidder')
                    ->whereHas('bidderProfile.activeSanction', fn ($sanctionQuery) => $sanctionQuery->where('type', $filter));
            })
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
            ->paginate(25)
            ->withQueryString();

        $roleCounts = [
            'admin' => User::where('role', 'admin')->count(),
            'staff' => User::where('role', 'staff')->count(),
            'bidder' => User::where('role', 'bidder')->count(),
            'all' => User::where('role', '!=', 'end_user')->count(),
        ];

        $statusCounts = [
            'active' => User::where('status', 'active')->count(),
            'pending' => User::where('status', 'pending')->count(),
            'rejected' => User::where('status', 'rejected')->count(),
            'suspended' => $bidderSanctionsAvailable ? BidderSanction::active()->where('type', BidderSanction::TYPE_SUSPENDED)->count() : 0,
            'blacklisted' => $bidderSanctionsAvailable ? BidderSanction::active()->where('type', BidderSanction::TYPE_BLACKLISTED)->count() : 0,
        ];

        $contactColumnsReady = User::contactColumnsAvailable();

        return view('admin.users', compact('users', 'search', 'filter', 'roleCounts', 'statusCounts', 'bidderApprovalAvailable', 'bidderSanctionsAvailable', 'contactColumnsReady'));
    }

    public function reviewUser(User $user)
    {
        abort_unless($user->role === 'bidder', 404);

        $user->load([
            'bidderDocuments',
            'loginLogs' => fn ($query) => $query->latest('created_at')->take(10),
            'bidderProfile.approver',
            'bidderProfile.reviewer',
        ]);

        if (Schema::hasTable('bidder_sanctions')) {
            $user->load(['bidderProfile.activeSanction.creator', 'bidderProfile.sanctions.creator', 'bidderProfile.sanctions.lifter']);
        }

        $bidder = $user->bidderProfile;
        if ($user->status === 'pending' && $bidder && in_array($bidder->review_status, [null, 'new'], true)) {
            $bidder->forceFill([
                'review_status' => 'under_review',
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
            ])->save();
            AuditLog::log('bidder_review_started', $bidder, ['review_status' => 'new'], ['review_status' => 'under_review'], ['ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
        }
        $registrationDocumentTypes = BidderRegistrationRequirements::documentTypes();
        $registrationDocuments = $user->bidderDocuments
            ->filter(fn (BidderDocument $document): bool => ($document->is_current ?? true)
                && (in_array($document->document_type, $registrationDocumentTypes, true)
                    || str_starts_with($document->document_type, 'Registration Requirement ')))
            ->sortBy('document_type')
            ->values();
        $supportingDocuments = $user->bidderDocuments
            ->reject(fn (BidderDocument $document) => ! ($document->is_current ?? true)
                || str_starts_with($document->document_type, 'Registration Requirement ')
                || in_array($document->document_type, $registrationDocumentTypes, true))
            ->sortBy('document_type')
            ->values();
        $missingRegistrationRequirements = $this->missingBidderRegistrationRequirements($registrationDocuments);
        $registrationRequirementsComplete = $missingRegistrationRequirements === [];
        $bidder = $user->bidderProfile;
        $openRequirementRequests = $bidder
            ? $bidder->requirementRequests()->where('status', 'open')->latest()->get()
            : collect();
        $registrationRequirementOptions = collect(BidderRegistrationRequirements::documents())
            ->map(fn (array $document, string $key): array => $document + ['key' => $key])
            ->values()
            ->all();

        return view('admin.bidder-review', compact(
            'user',
            'registrationDocuments',
            'supportingDocuments',
            'missingRegistrationRequirements',
            'registrationRequirementsComplete',
            'openRequirementRequests',
            'registrationRequirementOptions',
        ));
    }

    protected function missingBidderRegistrationRequirements(Collection $registrationDocuments): array
    {
        $uploadedTypes = $registrationDocuments
            ->pluck('document_type')
            ->filter()
            ->unique()
            ->values();

        return collect(BidderRegistrationRequirements::documents())
            ->filter(fn (array $document): bool => ($document['required'] ?? false)
                && ! $uploadedTypes->contains($document['document_type']))
            ->pluck('label')
            ->values()
            ->all();
    }

    public function userLoginActivity(User $user)
    {
        abort_unless($user->role === 'bidder', 404);

        $loginLogs = $user->loginLogs()
            ->latest('created_at')
            ->take(10)
            ->get();

        return view('admin.partials.bidder-login-activity-rows', compact('loginLogs'));
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
        [$validated, $profile] = $this->splitUserProfile($this->validateUser($request));

        $user = User::create($validated);
        $this->syncBidderProfile($user, $profile);

        return redirect()->route('admin.users')->with('success', 'User created successfully.');
    }

     public function updateUser(Request $request, User $user)
     {
         [$validated, $profile] = $this->splitUserProfile($this->validateUser($request, $user));

         if (blank($validated['password'] ?? null)) {
             unset($validated['password']);
         }

         $oldStatus = $user->status;

         // Check if user status is changing from pending to active
         $wasPending = $user->status === 'pending';
         $becomingActive = $validated['status'] === 'active';

         $user->update($validated);
         $this->syncBidderProfile($user, $profile);

         if ($oldStatus !== $user->status) {
             AuditLog::log('user_status_changed', $user, ['status' => $oldStatus], ['status' => $user->status], [
                 'ip_address' => $request->ip(),
                 'user_agent' => $request->userAgent(),
             ]);
         }

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

    public function approveUser(Request $request, User $user)
    {
        abort_unless($user->role === 'bidder', 404);

        $approvedNow = DB::transaction(function () use ($user, $request): bool {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $bidder = $this->ensureBidderProfile($lockedUser);

            if ($lockedUser->status === 'active' && $bidder->approval_status === 'approved') {
                return false;
            }

            if (Schema::hasTable('bidder_sanctions') && $bidder->hasActiveProcurementSanction()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'bidder_status' => 'Lift the active procurement sanction before approving this bidder.',
                ]);
            }

            $currentDocuments = BidderDocument::where('user_id', $lockedUser->id)
                ->where('is_current', true)
                ->get();
            $missing = $this->missingBidderRegistrationRequirements($currentDocuments);
            $needsAction = $currentDocuments->filter(fn (BidderDocument $document): bool => $document->review_status === 'needs_action');
            if ($missing !== [] || $needsAction->isNotEmpty()) {
                $message = $missing !== []
                    ? 'This bidder cannot be approved until all required documents are present: '.implode(', ', $missing)
                    : 'This bidder cannot be approved while one or more documents are marked for correction.';
                throw \Illuminate\Validation\ValidationException::withMessages(['requirements' => $message]);
            }

            $oldStatus = $lockedUser->bidderProcurementStatus();
            $lockedUser->forceFill([
                'status' => 'active',
                'company' => $bidder->company_name,
            ])->save();
            $bidder->forceFill([
                'approval_status' => 'approved',
                'review_status' => null,
                'review_message' => null,
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
                'review_requested_at' => null,
                'review_requested_by' => null,
                'rejection_reason' => null,
                'approved_at' => now(),
                'approved_by' => Auth::id(),
            ])->save();
            $bidder->requirementRequests()
                ->whereIn('status', ['open', 'submitted'])
                ->update(['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => Auth::id()]);

            AuditLog::log('bidder_approved', $bidder, ['approval_status' => $oldStatus], [
                'approval_status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now()->toISOString(),
            ], ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

            return true;
        });

        $user->refresh()->load('bidderProfile');
        if ($approvedNow) {
            SystemNotification::createForUser($user->id, 'Account approved', 'Your bidder account has been approved. You can now access the full SJBAC Portal.', 'account_approved');
            try {
                Mail::to($user->email)->send(new WelcomeMail($user));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $approvedNow ? 'Bidder approved successfully.' : 'Bidder is already approved.', 'user' => ['id' => $user->id, 'status' => 'approved', 'status_label' => 'Approved']]);
        }

        return redirect()->route(Auth::user()?->role === 'staff' ? 'staff.users.review' : 'admin.users.review', $user)->with('success', $approvedNow ? 'Bidder approved successfully.' : 'Bidder is already approved.');
    }

    public function requestBidderRequirements(Request $request, User $user)
    {
        abort_unless($user->role === 'bidder', 404);
        $validated = $request->validate([
            'document_types' => ['required', 'array', 'min:1'],
            'document_types.*' => ['required', Rule::in(BidderRegistrationRequirements::documentTypes())],
            'reason' => ['required', 'string', 'max:5000'],
        ]);
        $types = array_values(array_unique($validated['document_types']));
        $reason = trim($validated['reason']);

        $changed = DB::transaction(function () use ($user, $request, $types, $reason): bool {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedUser->status === 'pending', 409, 'Only pending bidder registrations can be sent back for corrections.');
            $bidder = $this->ensureBidderProfile($lockedUser);
            $currentDocuments = BidderDocument::where('user_id', $lockedUser->id)->where('is_current', true)->get()->keyBy('document_type');
            $existing = $bidder->requirementRequests()->where('status', 'open')->get()->keyBy('document_type');
            $selectedTypes = collect($types)->sort()->values()->all();
            $existingTypes = $existing->keys()->sort()->values()->all();
            $hasChange = $existingTypes !== $selectedTypes;

            foreach ($existing as $existingType => $existingRequest) {
                if (! in_array($existingType, $types, true)) {
                    $existingRequest->forceFill(['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => Auth::id()])->save();
                }
            }

            foreach ($types as $documentType) {
                $requestRow = $existing->get($documentType);
                if ($requestRow && trim((string) $requestRow->reason) === $reason) {
                    continue;
                }
                $hasChange = true;
                $requestRow = $requestRow ?: new BidderRequirementRequest();
                $requestRow->forceFill([
                    'bidder_id' => $bidder->id,
                    'document_type' => $documentType,
                    'document_id' => $currentDocuments->get($documentType)?->id,
                    'reason' => $reason,
                    'status' => 'open',
                    'requested_by' => Auth::id(),
                    'requested_at' => now(),
                ])->save();
                if ($currentDocuments->get($documentType)) {
                    $currentDocuments->get($documentType)->forceFill(['review_status' => 'needs_action', 'review_note' => $reason, 'reviewed_at' => now(), 'reviewed_by' => Auth::id()])->save();
                }
            }
            if (! $hasChange) {
                return false;
            }
            $bidder->forceFill([
                'approval_status' => 'pending',
                'review_status' => 'needs_action',
                'review_message' => $reason,
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
                'review_requested_at' => now(),
                'review_requested_by' => Auth::id(),
            ])->save();
            AuditLog::log('bidder_requirements_requested', $bidder, ['review_status' => $bidder->getOriginal('review_status')], ['document_types' => $types, 'reason' => $reason, 'review_status' => 'needs_action'], ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
            return true;
        });

        $user->refresh()->load('bidderProfile');
        $mailSent = null;
        $mailError = null;
        if ($changed) {
            $name = $user->bidderProfile?->contact_person ?: ($user->name ?: 'Bidder');
            $company = $user->bidderProfile?->company_name ?: ($user->company ?: 'your company');
            $labels = collect(BidderRegistrationRequirements::documents())->filter(fn (array $document) => in_array($document['document_type'], $types, true))->pluck('label')->values()->all();

            [$mailSent, $mailError] = $this->sendBidderRequirementsActionEmail($user, $name, $company, $labels, $reason, $user->bidderProfile?->review_requested_at);

            AuditLog::log('bidder_requirements_notification_'.($mailSent ? 'sent' : 'failed'), $user->bidderProfile, null, [
                'requested_by' => Auth::id(),
                'bidder_id' => $user->id,
                'document_types' => $types,
                'reason' => $reason,
                'email_to' => $user->email,
                'email_sent' => $mailSent,
                'email_error' => $mailError,
            ], ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

            SystemNotification::createForUser($user->id, 'Registration requirements need action', 'Please log in to the SJBAC Portal to correct: '.implode(', ', $labels).'.', 'bidder_requirements_action', ['reason' => $reason, 'document_types' => $types]);
        }

        if ($changed && ! $mailSent) {
            $message = 'Requirements were saved, but the notification email could not be sent to the bidder. You can retry sending it below.';
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'mail_sent' => false,
                    'message' => $message,
                    'retry_url' => route(Auth::user()?->role === 'staff' ? 'staff.users.requirements.resend' : 'admin.users.requirements.resend', $user),
                ]);
            }
            return redirect()->route(Auth::user()?->role === 'staff' ? 'staff.users.review' : 'admin.users.review', $user)->with('warning', $message);
        }

        $message = $changed ? 'Requirements request sent to the bidder.' : 'The same requirements request was already sent.';
        if ($request->expectsJson()) return response()->json(['ok' => true, 'mail_sent' => $mailSent, 'message' => $message]);
        return redirect()->route(Auth::user()?->role === 'staff' ? 'staff.users.review' : 'admin.users.review', $user)->with('success', $message);
    }

    /**
     * Resend the "needs action" notification email for a bidder's currently open
     * requirement requests, without re-validating or changing the request itself.
     * Used as the safe retry path when the original send attempt failed.
     */
    public function resendBidderRequirementsNotification(Request $request, User $user)
    {
        abort_unless($user->role === 'bidder', 404);

        $user->load('bidderProfile');
        $bidder = $user->bidderProfile;
        abort_unless($bidder, 404);

        $openTypes = $bidder->requirementRequests()->where('status', 'open')->pluck('document_type')->values()->all();
        if ($openTypes === []) {
            $message = 'This bidder does not have an open requirements request to resend.';
            if ($request->expectsJson()) return response()->json(['ok' => false, 'message' => $message], 422);
            return redirect()->route(Auth::user()?->role === 'staff' ? 'staff.users.review' : 'admin.users.review', $user)->with('warning', $message);
        }

        $reason = trim((string) $bidder->review_message) ?: 'Please review and correct the flagged requirements.';
        $labels = collect(BidderRegistrationRequirements::documents())->filter(fn (array $document) => in_array($document['document_type'], $openTypes, true))->pluck('label')->values()->all();
        $name = $bidder->contact_person ?: ($user->name ?: 'Bidder');
        $company = $bidder->company_name ?: ($user->company ?: 'your company');

        [$mailSent, $mailError] = $this->sendBidderRequirementsActionEmail($user, $name, $company, $labels, $reason, $bidder->review_requested_at);

        AuditLog::log('bidder_requirements_notification_'.($mailSent ? 'resent' : 'resend_failed'), $bidder, null, [
            'requested_by' => Auth::id(),
            'bidder_id' => $user->id,
            'document_types' => $openTypes,
            'reason' => $reason,
            'email_to' => $user->email,
            'email_sent' => $mailSent,
            'email_error' => $mailError,
        ], ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

        $message = $mailSent
            ? 'Notification email resent to the bidder.'
            : 'Unable to resend the notification email. Please check the mail configuration and try again.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => $mailSent,
                'mail_sent' => $mailSent,
                'message' => $message,
                'retry_url' => $mailSent ? null : route(Auth::user()?->role === 'staff' ? 'staff.users.requirements.resend' : 'admin.users.requirements.resend', $user),
            ], $mailSent ? 200 : 422);
        }

        return redirect()->route(Auth::user()?->role === 'staff' ? 'staff.users.review' : 'admin.users.review', $user)->with($mailSent ? 'success' : 'warning', $message);
    }

    /**
     * Send the bidder "needs action" requirements email, catching and logging
     * any transport failure instead of letting it bubble up as a 500.
     *
     * @return array{0: bool, 1: string|null} [sent, errorMessage]
     */
    private function sendBidderRequirementsActionEmail(User $user, string $name, string $company, array $labels, string $reason, ?\DateTimeInterface $requestedAt): array
    {
        try {
            Mail::to($user->email)->send(new BidderRequirementsActionMail(
                $name,
                $company,
                $labels,
                $reason,
                $requestedAt instanceof \Carbon\Carbon ? $requestedAt : now(),
                Auth::user()?->email,
                route('bidder.company-profile'),
            ));

            return [true, null];
        } catch (\Throwable $exception) {
            report($exception);
            Log::error('Failed to send bidder requirements action email.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            return [false, $exception->getMessage()];
        }
    }

    public function sendIncompleteRequirementsEmail(Request $request, User $user)
 {
     abort_unless($user->role === 'bidder', 404);

     $user->load(['registrationDocuments', 'bidderProfile']);
     $missingRequirements = $this->missingBidderRegistrationRequirements($user->registrationDocuments);

     if ($missingRequirements === []) {
         $message = 'All required bidder requirements are already complete.';

         if ($request->expectsJson()) {
             return response()->json([
                 'ok' => false,
                 'message' => $message,
             ], 422);
         }

         return redirect()
             ->route('admin.users.review', $user)
             ->with('warning', $message);
     }

     try {
         $companyName = $user->bidderProfile?->company_name ?: ($user->company ?: 'your company');
         $bidderName = $user->bidderProfile?->contact_person ?: ($user->name ?: $companyName);

         Mail::to($user->email)->send(new BidderIncompleteRequirementsMail(
             $bidderName,
             $companyName,
             $missingRequirements,
         ));

         SystemNotification::createForUser(
             $user->id,
             'Incomplete registration requirements',
             'The SJBAC sent you a list of missing registration requirements. Please check your email.',
             'bidder_requirements_incomplete'
         );
     } catch (\Throwable $exception) {
         report($exception);
         $message = 'The incomplete requirements email could not be sent. Please check the mail configuration.';

         if ($request->expectsJson()) {
             return response()->json([
                 'ok' => false,
                 'message' => $message,
             ], 503);
         }

         return redirect()
             ->route('admin.users.review', $user)
             ->with('warning', $message);
     }

     $message = 'Incomplete requirements email sent to the bidder.';

     if ($request->expectsJson()) {
         return response()->json([
             'ok' => true,
             'message' => $message,
             'user' => [
                 'id' => $user->id,
                 'status' => $user->status,
                 'status_label' => ucfirst((string) $user->status),
             ],
         ]);
     }

     return redirect()
         ->route('admin.users.review', $user)
         ->with('success', $message);
 }

    public function rejectUser(Request $request, User $user)
    {
        if ((int) $user->id === (int) Auth::id()) {
            return redirect()->route('admin.users')->withErrors(['status' => 'You cannot reject your own signed-in account.']);
        }
        abort_unless($user->role === 'bidder', 404);
        abort_unless(Schema::hasTable('bidders'), 503, 'Bidder rejection is unavailable because the bidders table is missing.');

        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:5000']]);
        $reason = trim($validated['rejection_reason']);
        $rejectedNow = DB::transaction(function () use ($user, $reason, $request): bool {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $bidder = $this->ensureBidderProfile($lockedUser);
            if ($lockedUser->status === 'rejected' && $bidder->approval_status === 'rejected' && $bidder->rejection_reason === $reason) {
                return false;
            }
            $oldStatus = $lockedUser->bidderProcurementStatus();
            $lockedUser->forceFill(['status' => 'rejected'])->save();
            $bidder->forceFill([
                'approval_status' => 'rejected',
                'review_status' => null,
                'review_message' => null,
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
                'approved_at' => null,
                'approved_by' => null,
                'rejection_reason' => $reason,
            ])->save();
            AuditLog::log('bidder_rejected', $bidder, ['approval_status' => $oldStatus], ['approval_status' => 'rejected', 'rejection_reason' => $reason], ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
            return true;
        });

        $user->refresh()->load('bidderProfile');
        if ($rejectedNow) {
            try { Mail::to($user->email)->send(new BidderRejectedMail($user, $user->bidderProfile, $reason)); } catch (\Throwable $exception) { report($exception); }
            SystemNotification::createForUser($user->id, 'Account rejected', 'Your bidder registration was rejected. Please check the reason sent to your registered email.', 'account_rejected');
        }
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $rejectedNow ? 'Bidder registration rejected.' : 'Bidder is already rejected.', 'user' => ['id' => $user->id, 'status' => 'rejected', 'status_label' => 'Rejected', 'rejection_reason' => $reason]]);
        }
        return redirect()->route(Auth::user()?->role === 'staff' ? 'staff.users.review' : 'admin.users.review', $user)->with('success', $rejectedNow ? 'Bidder registration rejected and email notification sent.' : 'Bidder is already rejected.');
    }
    public function sanctionUser(Request $request, User $user)
    {
        abort_unless($user->role === 'bidder', 404);

        if (! Schema::hasTable('bidders') || ! Schema::hasTable('bidder_sanctions')) {
            return redirect()
                ->route('admin.users')
                ->with('warning', 'Bidder sanctions are unavailable because the required database table is missing.');
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in([BidderSanction::TYPE_SUSPENDED, BidderSanction::TYPE_BLACKLISTED])],
            'reason' => ['required', 'string', 'max:5000'],
            'reference_number' => ['required', 'string', 'max:255'],
            'effective_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'authorized_by' => ['required', 'string', 'max:255'],
        ]);

        $sanction = null;

        DB::transaction(function () use ($user, $validated, &$sanction): void {
            $bidder = $this->ensureBidderProfile($user);
            $oldStatus = $user->bidderProcurementStatus();
            $oldSanctions = $bidder->sanctions()->whereNull('lifted_at')->get();

            foreach ($oldSanctions as $oldSanction) {
                $oldSanction->forceFill([
                    'lifted_at' => now(),
                    'lifted_by' => Auth::id(),
                ])->save();
            }

            $sanction = $bidder->sanctions()->create([
                'type' => $validated['type'],
                'previous_approval_status' => $bidder->approval_status,
                'reason' => trim($validated['reason']),
                'reference_number' => trim($validated['reference_number']),
                'effective_date' => $validated['effective_date'],
                'end_date' => $validated['end_date'] ?? null,
                'authorized_by' => trim($validated['authorized_by']),
                'created_by' => Auth::id(),
            ]);
            AuditLog::log('bidder_status_changed', $bidder, [
                'approval_status' => $oldStatus,
                'active_sanctions' => $oldSanctions->map->only(['id', 'type', 'reference_number'])->values()->all(),
            ], [
                'approval_status' => $validated['type'],
                'sanction_id' => $sanction->id,
                'reason' => $sanction->reason,
                'reference_number' => $sanction->reference_number,
                'effective_date' => $sanction->effective_date?->toDateString(),
                'end_date' => $sanction->end_date?->toDateString(),
                'authorized_by' => $sanction->authorized_by,
            ]);
        });

        $label = $validated['type'] === BidderSanction::TYPE_BLACKLISTED ? 'blacklisted' : 'suspended';

        SystemNotification::createForUser(
            $user->id,
            'Procurement status updated',
            'Your bidder account has been ' . $label . ' for procurement participation. Reference: ' . $validated['reference_number'] . '.',
            'bidder_sanction',
            ['sanction_id' => $sanction?->id, 'type' => $validated['type']]
        );

        return redirect()
            ->route('admin.users', ['filter' => $validated['type']])
            ->with('success', 'Bidder ' . $label . ' with sanction record saved.');
    }

    public function liftBidderSanction(Request $request, User $user)
    {
        abort_unless($user->role === 'bidder', 404);

        if (! Schema::hasTable('bidders') || ! Schema::hasTable('bidder_sanctions')) {
            return redirect()
                ->route('admin.users')
                ->with('warning', 'Bidder sanctions are unavailable because the required database table is missing.');
        }

        $bidder = $this->ensureBidderProfile($user);
        $sanction = $bidder->activeSanction()->first();

        if (! $sanction) {
            return redirect()
                ->route('admin.users')
                ->with('warning', 'This bidder has no active procurement sanction to lift.');
        }

        DB::transaction(function () use ($user, $bidder, $sanction): void {
            $oldStatus = $user->bidderProcurementStatus();

            $sanction->forceFill([
                'lifted_at' => now(),
                'lifted_by' => Auth::id(),
            ])->save();

            $restoredStatus = $sanction->previous_approval_status ?: ($user->status === 'active' ? 'approved' : 'pending');

            if (in_array($restoredStatus, [BidderSanction::TYPE_SUSPENDED, BidderSanction::TYPE_BLACKLISTED], true)) {
                $restoredStatus = $user->status === 'active' ? 'approved' : 'pending';
            }

            $bidder->forceFill([
                'approval_status' => $restoredStatus,
            ])->save();

            AuditLog::log('bidder_status_changed', $bidder, [
                'approval_status' => $oldStatus,
                'sanction_id' => $sanction->id,
            ], [
                'approval_status' => $restoredStatus,
                'sanction_id' => $sanction->id,
                'lifted_at' => $sanction->lifted_at?->toDateTimeString(),
            ]);
        });

        SystemNotification::createForUser(
            $user->id,
            'Procurement sanction lifted',
            'Your bidder procurement sanction has been lifted by the SJBAC.',
            'bidder_sanction_lifted',
            ['sanction_id' => $sanction->id]
        );

        return redirect()
            ->route('admin.users', ['filter' => 'bidder'])
            ->with('success', 'Bidder procurement sanction lifted.');
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
        // Assigned from the Projects list ("Unassigned" chip): go back there.
        $fromProjects = $request->input('return') === 'projects';
        $back = fn () => $fromProjects ? redirect()->back() : redirect()->route('admin.assignments');

        if ($staff->role !== 'staff') {
            return $back()
                ->withErrors(['staff_id' => 'Only staff users can be assigned to projects.']);
        }

        $exists = Assignment::where('staff_id', $validated['staff_id'])
            ->where('project_id', $validated['project_id'])
            ->exists();

        if ($exists) {
            return $back()
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

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Staff assigned successfully.',
                'staff_name' => $staff->name,
            ]);
        }

        return $back()->with('success', $fromProjects ? $staff->name.' assigned to '.$projectTitle.'.' : 'Staff assigned successfully.');
    }

    public function destroyAssignment(Assignment $assignment)
    {
        $assignment->delete();

        return redirect()->route('admin.assignments')->with('success', 'Staff assignment removed successfully.');
    }

    public function viewBid(Request $request, Bid $bid)
    {
        app(\App\Support\BidOpening::class)->openScheduledTechnical($bid->project()->with('schedule')->firstOrFail());
        $bid->load([
            'project.awards', 'project.rebidProject', 'project.requirement', 'project.schedule', 'project.bidsOpenedByUser',
            'user.philgepsCertificate', 'user.bidderDocuments', 'award', 'trackings.creator', 'documents.reviewEvents.actor',
        ]);

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            $workflow = $this->bidWorkflow();

            return view('admin.bid-view-modal', [
                'bid' => $bid,
                'status' => $bid->progress()->adminStatus(),
                'actions' => $workflow->availableActions($bid, Auth::user()),
                // Masks file names/IDs of any component that is still sealed.
                'checklist' => $bid->reviewChecklist(),
                'evaluationCriteria' => $workflow->evaluationCriteria($bid),
                'history' => BidHistory::for($bid)->forAdmin(),
                'openingBlocker' => $bid->project?->bidOpeningBlocker(),
                'ranking' => app(\App\Support\BidRanking::class)->forProjects([$bid->project_id])[$bid->id] ?? null,
            ]);
        }

        return redirect()->route(Auth::user()?->role === 'staff' ? 'staff.review-bids' : 'admin.bids', ['view_bid' => $bid->id]);
    }

    public function previewBidDocument(Bid $bid, string $document)
    {
        app(\App\Support\BidOpening::class)->openScheduledTechnical($bid->project()->with('schedule')->firstOrFail());
        $bid->loadMissing(['project', 'user.philgepsCertificate']);
        $documentMeta = $this->bidDocumentMeta($bid, $document);
        abort_if($bid->isSealed(), 403, 'This bid is sealed until the authorized bid opening is recorded.');
        abort_if($document === 'proposal' && $bid->isFinancialSealed(), 403, 'The financial component is sealed until its opening is recorded.');

        abort_unless(filled($documentMeta['path']), 404);

        return redirect()->route('admin.bid.document.pdf', ['bid' => $bid, 'document' => $document]);
    }

    public function streamBidDocumentPdf(Bid $bid, string $document)
    {
        app(\App\Support\BidOpening::class)->openScheduledTechnical($bid->project()->with('schedule')->firstOrFail());
        $bid->loadMissing(['project', 'user.philgepsCertificate']);
        $documentMeta = $this->bidDocumentMeta($bid, $document);

        abort_unless(filled($documentMeta['path']), 404);
        // Every bid-submission file is gated server-side by its own component:
        // the proposal (price offer) is financial, the certificate is technical.
        abort_if($bid->isSealed(), 403, 'This bid is sealed until the authorized bid opening is recorded.');
        abort_if($document === 'proposal' && $bid->isFinancialSealed(), 403, 'The financial component is sealed until its opening is recorded.');
        if ($document === 'proposal' && $bid->proposal_file_encrypted_at) {
            return app(\App\Support\FinancialBidFile::class)->response($bid->proposal_file, $bid->proposal_filename, $bid->proposal_file_encrypted_at);
        }

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

    public function editBid(Request $request, Bid $bid)
    {
        $bid->load(['project.awards', 'project.rebidProject', 'user', 'award']);

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.bid-edit-modal', compact('bid'));
        }

        return redirect()->route('admin.bids', ['edit_bid' => $bid->id]);
    }

    public function updateBid(Request $request, Bid $bid)
    {
        // Only internal notes are editable. The submitted amount is the bidder's
        // financial offer, and stage/status changes go through recordBidDecision
        // so the event history and the bidder track stay consistent.
        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $bid->update(['notes' => $validated['notes'] ?? null]);

        return redirect()->route('admin.bids')->with('success', 'Internal notes saved.');
    }

    /**
     * Record one stage decision from the Review Bid modal.
     */
    public function recordBidDecision(Request $request, Bid $bid)
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(array_keys(BidWorkflow::ACTION_DEFINITIONS))],
            'reason' => 'nullable|string|max:2000',
            'verified_requirements' => 'nullable|array',
            'verified_requirements.*' => 'string|max:100',
            'failed_requirements' => 'nullable|array',
            'failed_requirements.*' => 'string|max:100',
            'performance_security_at' => 'nullable|date|before_or_equal:today',
            // Manual submission receipt (BAC Secretariat logbook).
            'receipt_no' => 'nullable|string|max:60',
            'received_at' => 'nullable|date',
            // BAC resolution recommending the award.
            'bac_resolution_no' => 'nullable|string|max:100',
            'bac_resolution_date' => 'nullable|date|before_or_equal:today',
            // Report, minutes or resolution supporting the decision.
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            // Notice of Award (signed PDF) and contract signing date.
            'notice_file' => 'nullable|file|mimes:pdf|max:5120',
            'contract_date' => 'nullable|date|before_or_equal:today',
            // Notice to Proceed: signed PDF, issuance date, optional PhilGEPS posting.
            'ntp_file' => 'nullable|file|mimes:pdf|max:5120',
            'ntp_issued_on' => 'nullable|date',
            'ntp_philgeps_posted_on' => 'nullable|date',
            'ntp_philgeps_reference' => 'nullable|string|max:500',
            'evaluation_result' => 'nullable|in:responsive,nonresponsive',
            'evaluation_findings' => 'nullable|string|max:5000',
            'criterion_results' => 'nullable|array',
            'criterion_results.*' => 'nullable|string|max:1000',
            'post_qualification_findings' => 'nullable|string|max:5000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $validated['notice_file'] = $request->file('notice_file');
        $validated['ntp_file'] = $request->file('ntp_file');
        $validated['supporting_document'] = $request->file('supporting_document');

        if (filled($validated['received_at'] ?? null)) {
            // datetime-local input is in the BAC's local time.
            $validated['received_at'] = \Illuminate\Support\Carbon::parse($validated['received_at'], config('bac-office.display_timezone'))
                ->timezone(config('app.timezone'))
                ->toDateTimeString();
        }

        $this->bidWorkflow()->apply($bid, $validated['action'], Auth::user(), $validated);

        return redirect()
            ->route('admin.bids', ['view_bid' => $bid->id])
            ->with('success', 'Recorded: ' . BidWorkflow::label($validated['action']) . '.');
    }

    /**
     * Record the authorized bid opening for the bid's project.
     */
    public function openProjectBids(Request $request, Project $project)
    {
        $this->bidWorkflow()->openBids($project, Auth::user());

        return redirect()
            ->route('admin.bids', array_filter(['view_bid' => $request->integer('bid') ?: null]))
            ->with('success', 'Bid opening recorded for ' . $project->title . '.');
    }

    /** Award criterion and opening rules from the project's bidding documents. */
    public function configureBidOpening(Request $request, Project $project)
    {
        app(\App\Support\BidOpening::class)->configure($project, Auth::user(), $request->only([
            'award_criterion', 'opening_documents_reference', 'minimum_technical_score',
        ]));

        return redirect()
            ->route('admin.bids', array_filter(['view_bid' => $request->integer('bid') ?: null]))
            ->with('success', 'Opening rules recorded for ' . $project->title . '.');
    }

    /** MEARB/MARB technical score, recorded once before the financial opening. */
    public function recordBidTechnicalScore(Request $request, Bid $bid)
    {
        app(\App\Support\BidOpening::class)->recordScore($bid, Auth::user(), $request->only(['technical_score', 'technical_score_basis']));
        event(new \App\Events\BidWorkflowUpdated($bid->fresh()));

        return redirect()->route('admin.bids', ['view_bid' => $bid->id])->with('success', 'Technical score recorded.');
    }

    /** The actual financial opening of one bid, recorded by the BAC Admin. */
    public function openBidFinancial(Request $request, Bid $bid)
    {
        $password = $request->input('opening_password');
        // Never let Laravel flash or log the submitted secret.
        $request->request->remove('opening_password');
        $request->query->remove('opening_password');
        $request->json()->remove('opening_password');
        app(\App\Support\BidOpening::class)->openFinancial(
            $bid, Auth::user(), is_string($password) ? $password : null,
            // Paper bids: the amount read from the sealed envelope.
            is_scalar($request->input('bid_amount')) ? (string) $request->input('bid_amount') : null
        );
        event(new \App\Events\BidWorkflowUpdated($bid->fresh()));

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Financial component opening recorded.']);
        }

        return redirect()->route('admin.bids', ['view_bid' => $bid->id])->with('success', 'Financial component opening recorded.');
    }

    /**
     * A registered bidder hands in a sealed paper bid without having saved it
     * online: the BAC records the receipt (BidWorkflow::receiveWalkInSealedBid).
     */
    public function receiveSealedBid(Request $request, Project $project)
    {
        // The form sits in the project view dialog of the Projects list: the outcome comes back there as a message.
        $fail = fn (string $message) => back()->with('error', 'Sealed bid not recorded: '.$message);
        $validator = validator($request->all(), [
            'bidder_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'bidder')],
            'receipt_no' => ['required', 'string', 'max:100'],
            'received_at' => ['required', 'date'],
        ], [
            'bidder_id.required' => 'Choose the bidder who handed in the sealed bid.',
            'receipt_no.required' => 'Enter the receipt / logbook number issued by the BAC Secretariat.',
            'received_at.required' => 'Enter the date and time the sealed bid was received.',
        ]);
        if ($validator->fails()) {
            return $fail($validator->errors()->first());
        }
        $validated = $validator->validated();

        $bidder = User::with('bidderProfile')->findOrFail($validated['bidder_id']);
        try {
            $bid = $this->bidWorkflow()->receiveWalkInSealedBid($project, $bidder, Auth::user(), [
                'receipt_no' => $validated['receipt_no'],
                'received_at' => \Illuminate\Support\Carbon::parse($validated['received_at'], config('app.timezone', 'Asia/Manila')),
            ]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return $fail(collect($exception->errors())->flatten()->first());
        }

        return back()->with('success', 'Sealed bid of '.($bidder->company ?: $bidder->name).' recorded as received ('.$bid->receipt_no.').');
    }

    public function declareFailedBidding(Request $request, Project $project)
    {
        $validated = $request->validate([
            'failed_bidding_reason' => 'required|string|min:5|max:2000',
            'rebid_project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
        ]);

        $this->bidWorkflow()->declareFailedBidding(
            $project,
            Auth::id(),
            $validated['failed_bidding_reason'],
            isset($validated['rebid_project_id']) ? (int) $validated['rebid_project_id'] : null
        );

        AuditLog::log('failed_bidding_declared', $project, [], [
            'reason' => $validated['failed_bidding_reason'],
            'rebid_project_id' => $validated['rebid_project_id'] ?? null,
        ]);

        return redirect()->route('admin.projects')->with('success', 'Failure of bidding recorded for ' . $project->title . '.');
    }

    /**
     * Online submission settings: the bidding documents fee bidders pay at the
     * BAC office before submitting, where they pay it, and the optional LGU
     * authority reference. The fee is fixed once a payment is recorded or the
     * submission deadline has passed.
     */
    /**
     * Why the bidding documents fee can no longer change: bidders already paid
     * it, or the submission deadline has passed. Null while it can change.
     */
    public static function feeLockReason(Project $project): ?string
    {
        $deadline = $project->bidSubmissionDeadline();

        return match (true) {
            $project->biddingFeePayments()->exists() => 'Payments were already recorded for this project, so the bidding documents fee can no longer be changed.',
            $deadline !== null && $deadline->isPast() => 'The bidding documents fee cannot be changed after the submission deadline.',
            default => null,
        };
    }

    public function updateSubmissionSettings(Request $request, Project $project)
    {
        $validated = $request->validate([
            'submission_mode' => ['nullable', Rule::in([Project::SUBMISSION_MANUAL, Project::SUBMISSION_ELECTRONIC])],
            'submission_venue' => 'nullable|string|max:255',
            'bidding_documents_fee' => 'nullable|numeric|min:0|lte:9999999999999.99',
            'payment_venue' => 'nullable|string|max:255',
            'philgeps_reference_no' => 'nullable|string|max:100',
            'philgeps_url' => 'nullable|url|max:500',
            'electronic_submission_authority' => 'nullable|string|max:255',
            'legal_basis' => ['nullable', Rule::in(array_keys(Project::LEGAL_BASES))],
            'bid_security_required' => 'nullable|boolean',
            'bid_security_notes' => 'nullable|string|max:2000',
            'bidding_fee_mode' => ['nullable', Rule::in(\App\Support\BiddingDocumentsFee::MODES)],
            'bidding_fee_reason' => 'nullable|string|max:2000',
            'bidding_fee_amendment_reference' => 'nullable|string|max:255',
            'bidding_fee_amendment_reason' => 'nullable|string|max:2000',
        ], [
            'bidding_documents_fee.lte' => 'Bidding documents fee must not exceed 9,999,999,999,999.99.',
        ]);

        // Same fee rules as the project form; a request without the fee leaves it unchanged.
        $feeAttributes = \App\Support\BiddingDocumentsFee::resolve($project, $request->only(['bidding_fee_mode', 'bidding_documents_fee', 'bidding_fee_reason']));
        $feeAmendment = \App\Support\BiddingDocumentsFee::assertAmendable($project, $feeAttributes, $request->all(), self::feeLockReason($project));
        $feeBefore = $project->only(['bidding_documents_fee', 'bidding_fee_mode']);

        // Fields left out of the request keep their current value.
        $mode = $validated['submission_mode'] ?? $project->submission_mode ?? Project::SUBMISSION_ELECTRONIC;
        $deadline = $project->bidSubmissionDeadline();
        if ($mode !== $project->submission_mode && $deadline !== null && $deadline->isPast()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'submission_mode' => 'The submission method cannot be changed after the submission deadline.',
            ]);
        }

        $bidSecurityRequired = $request->has('bid_security_required')
            ? (bool) ($validated['bid_security_required'] ?? false)
            : (bool) $project->bid_security_required;

        $authority = filled($validated['electronic_submission_authority'] ?? null) ? trim($validated['electronic_submission_authority']) : null;
        $authorityChanged = $authority !== $project->electronic_submission_authority;
        $before = $project->only(['submission_mode', 'submission_venue', 'bidding_documents_fee', 'payment_venue', 'philgeps_reference_no', 'philgeps_url', 'electronic_submission_authority', 'legal_basis', 'bid_security_required', 'bid_security_notes']);

        $project->update([
            'submission_mode' => $mode,
            'submission_venue' => $request->has('submission_venue') ? ($validated['submission_venue'] ?? null) : $project->submission_venue,
            'philgeps_url' => $request->has('philgeps_url') ? ($validated['philgeps_url'] ?? null) : $project->philgeps_url,
            'legal_basis' => $validated['legal_basis'] ?? $project->legal_basis,
            'bid_security_required' => $bidSecurityRequired,
            'bid_security_notes' => $bidSecurityRequired
                ? ($request->has('bid_security_notes') ? ($validated['bid_security_notes'] ?? null) : $project->bid_security_notes)
                : null,
            'payment_venue' => $request->has('payment_venue')
                ? (filled($validated['payment_venue'] ?? null) ? trim($validated['payment_venue']) : null)
                : $project->payment_venue,
            'philgeps_reference_no' => $validated['philgeps_reference_no'] ?? null,
            'electronic_submission_authority' => $authority,
            'electronic_submission_authorized_at' => $authority === null ? null : ($authorityChanged ? now() : $project->electronic_submission_authorized_at),
            'electronic_submission_authorized_by' => $authority === null ? null : ($authorityChanged ? Auth::id() : $project->electronic_submission_authorized_by),
        ] + $feeAttributes);
        if ($feeAmendment !== null) {
            \App\Support\BiddingDocumentsFee::recordAmendment($project, $feeBefore, $feeAmendment, Auth::user());
        }

        AuditLog::log('bid_submission_settings_updated', $project, $before, $project->only(array_keys($before)));

        return redirect()->route('admin.projects')->with('success', 'Submission settings saved for ' . $project->title . '.');
    }

    /**
     * One file of a bid's technical or financial component. The technical
     * component opens at the recorded bid opening; the financial component
     * only for a bid that passed preliminary examination.
     */
    public function streamBidComponentFile(Bid $bid, \App\Models\BidDocument $bidDocument)
    {
        app(\App\Support\BidOpening::class)->openScheduledTechnical($bid->project()->with('schedule')->firstOrFail());
        abort_unless($bidDocument->bid_id === $bid->id, 404);
        $bid->loadMissing(['project.awards', 'award']);

        $sealed = $bidDocument->component === \App\Models\BidDocument::COMPONENT_FINANCIAL
            ? $bid->isFinancialSealed()
            : $bid->isSealed();
        abort_if($sealed, 403, 'This component is sealed.');

        if ($bidDocument->component === \App\Models\BidDocument::COMPONENT_FINANCIAL) {
            return app(\App\Support\FinancialBidFile::class)->response($bidDocument);
        }

        return $this->streamDocumentPdfPreview($bidDocument->file_path, $bidDocument->original_name, $bidDocument->label);
    }

    /**
     * Manual status changes may not skip the procurement steps: "open" needs
     * the posting checks, "closed" is set by bid opening or failed bidding,
     * and "awarded" only exists once a Notice of Award was issued.
     */
    protected function projectStatusChangeError(Project $project, string $status, ?string $deadline = null, ?\App\Models\ProjectSchedule $schedule = null): ?string
    {
        if ($status === $project->status) {
            return null;
        }

        return match ($status) {
            'awarded' => $project->awards()->exists()
                ? null
                : 'A project becomes Awarded only when the Notice of Award is issued.',
            'open' => (function () use ($project, $deadline, $schedule) {
                $candidate = $project->replicate()->forceFill(['deadline' => $deadline ?? $project->deadline]);
                $candidate->id = $project->id;
                $candidate->setRelation('schedule', $schedule ?? $project->schedule()->first());
                $blockers = $candidate->publicationBlockers();

                return $blockers === [] ? null : 'Not ready for posting: ' . implode(' ', $blockers);
            })(),
            default => null,
        };
    }

    /**
     * A schedule change may not undo a stage already reached: once the bids
     * are opened (or the bidding failed, was awarded or completed) the dates
     * are history, a passed deadline cannot be moved to reopen submissions,
     * and a held pre-bid conference keeps its date. A changed schedule must
     * also hang together (Project::scheduleConflicts).
     *
     * @return array<string, string> field => message
     */
    protected function scheduleChangeErrors(Project $project, string $deadline, \App\Models\ProjectSchedule $schedule): array
    {
        $zone = config('app.timezone', 'Asia/Manila');
        $newDeadline = \Carbon\Carbon::parse($deadline, $zone);
        $oldDeadline = $project->bidSubmissionDeadline();
        $changed = array_filter([
            // The form works to the minute; stored times may carry seconds.
            'deadline' => $oldDeadline === null || ! $oldDeadline->copy()->startOfMinute()->equalTo($newDeadline->copy()->startOfMinute()),
            'bid_opening_date' => $schedule->isDirty('bid_opening_date'),
            'pre_bid_conference_date' => $schedule->isDirty('pre_bid_conference_date'),
            'date_posted' => $schedule->isDirty('date_posted'),
        ]);

        // Drafts are checked in full when they are published.
        if ($changed === [] || in_array($project->status, ['draft', 'approved_for_bidding'], true)) {
            return [];
        }

        $lockedBy = match (true) {
            $project->isCompleted() => 'the project is completed',
            $project->failed_bidding_at !== null => 'a failure of bidding was declared',
            $project->status === 'awarded' || $project->awards()->exists() => 'the contract was awarded',
            $project->archived_at !== null => 'the project is archived',
            $project->bidsAreOpened() => 'the bids were opened on '.$project->bids_opened_at->timezone($zone)->format('M d, Y h:i A'),
            default => null,
        };
        if ($lockedBy !== null) {
            return [array_key_first($changed) => "The schedule can no longer change: {$lockedBy}."];
        }

        $errors = [];
        if (isset($changed['deadline']) && $oldDeadline !== null && ! $oldDeadline->isAfter(now($zone))) {
            $errors['deadline'] = 'Submissions closed on '.$oldDeadline->timezone($zone)->format('M d, Y h:i A').'. The deadline cannot be moved after it has passed, since that would reopen or rewrite the submission period.';
        }
        if (isset($changed['pre_bid_conference_date']) && $project->proceedings()->where('type', \App\Models\ProjectProceeding::TYPE_PRE_BID)->exists()) {
            $errors['pre_bid_conference_date'] = 'The pre-bid conference was already held and recorded; its date cannot change.';
        }

        $candidate = $project->replicate()->forceFill(['deadline' => $newDeadline]);
        $candidate->id = $project->id;
        $candidate->exists = true;
        $candidate->setRelation('schedule', $schedule);
        foreach ($candidate->scheduleConflicts($project->publicationTime()?->copy()->startOfDay()) as $field => $message) {
            $errors[$field === 'bid_submission_deadline' ? 'deadline' : $field] ??= $message;
        }

        return $errors;
    }

    /**
     * After a schedule change: what the new dates already require happens now
     * (bid opening when its time has passed), and everyone taking part is told.
     * Returns the legal periods the new dates miss (shown as warnings).
     *
     * @return list<string>
     */
    protected function applyScheduleChange(Project $project, array $before): array
    {
        $after = [
            'deadline' => $project->bidSubmissionDeadline()?->toDateTimeString(),
            'bid_opening_date' => $project->schedule?->bid_opening_date?->toDateTimeString(),
        ];
        if ($after === $before || in_array($project->status, ['draft', 'approved_for_bidding'], true)) {
            return [];
        }

        // Legal periods the BAC chose not to meet are kept with the change.
        $warnings = $project->scheduleWarnings($project->postingDateForReview());
        AuditLog::log('project_schedule_changed', $project, $before, $after + ($warnings === [] ? [] : ['schedule_warnings' => array_values($warnings)]));

        $zone = config('bac-office.display_timezone', 'Asia/Manila');
        $format = fn (?string $moment) => $moment ? \Carbon\Carbon::parse($moment)->timezone($zone)->format('M d, Y h:i A') : 'not set';
        $message = 'Schedule updated for '.$project->title.': '.lcfirst($project->mode()->deadlineLabel()).' '.$format($after['deadline'])
            .', '.lcfirst($project->mode()->openingLabel()).' '.$format($after['bid_opening_date']).'.';
        $recipients = Bid::where('project_id', $project->id)->pluck('user_id')
            ->merge(\App\Models\BiddingFeePayment::where('project_id', $project->id)->pluck('user_id'))
            ->merge($project->assignments()->pluck('staff_id'))
            ->filter()->unique();
        SystemNotification::createForUsers($recipients, 'Schedule updated', $message, 'project_status', ['project_id' => $project->id]);

        app(\App\Support\BidOpening::class)->openScheduledTechnical($project);

        return array_values($warnings);
    }

    protected function bidWorkflow(): BidWorkflow
    {
        return app(BidWorkflow::class);
    }

    public function viewProject(Project $project)
    {
        $project->loadCount('bids');
        $project->load(['assignments.staff', 'documents', 'bids:id,project_id,bid_amount,financial_opened_at,financial_opened_by', 'rebidProject:id,title']);

        $staffMembers = User::where('role', 'staff')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        // Candidate projects to link as the new bidding round after a failure.
        $rebidCandidates = Project::whereKeyNot($project->id)
            ->whereNull('archived_at')
            ->whereNull('failed_bidding_at')
            ->latest()
            ->limit(50)
            ->get(['id', 'title', 'reference_no']);

        // Manual projects: sealed paper bids received, and the approved bidders who may hand one in.
        $sealedBids = collect();
        $sealedBidders = collect();
        if (! $project->acceptsElectronicSubmission()) {
            $sealedBids = $project->bids()->with('user:id,name,company')->where('submission_channel', Bid::CHANNEL_MANUAL)
                ->whereNotNull('receipt_no')->orderBy('submitted_at')->get();
            $sealedBidders = User::with('bidderProfile')->where('role', 'bidder')->where('status', 'active')->orderBy('company')->orderBy('name')->get()
                ->filter(fn (User $bidder) => $bidder->isApprovedBidder())
                ->reject(fn (User $bidder) => $sealedBids->contains('user_id', $bidder->id))
                ->map(fn (User $bidder) => ['id' => $bidder->id, 'label' => $bidder->company ?: $bidder->name, 'fee_paid' => ! $project->requiresBiddingFee() || $project->hasPaidBiddingFee($bidder)])
                ->values();
        }

        return view('admin.project-view', compact('project', 'staffMembers', 'rebidCandidates', 'sealedBids', 'sealedBidders'));
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
        $project->load(['assignments', 'documents', 'schedule']);

        $staffMembers = User::where('role', 'staff')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $currentAssignment = $project->assignments->first();

        return view('admin.project-edit', compact('project', 'staffMembers', 'currentAssignment'));
    }

    public function updateProject(Request $request, Project $project)
    {
        // The edit form shows peso amounts with thousands separators (e.g. 2,500,000.00).
        foreach (['budget', 'bidding_documents_fee'] as $amount) {
            if (is_string($request->input($amount))) {
                $request->merge([$amount => str_replace([',', '₱', ' '], '', $request->input($amount))]);
            }
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'document_files' => 'nullable|array',
            'document_files.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:20480',
            'document_type' => ['nullable', Rule::in(['invitation_to_bid', 'bidding_documents', 'terms_of_reference', 'technical_specifications', 'bill_of_quantities', 'project_plans', 'supplemental_bulletin', 'other'])],
            'document_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:20480',
            'budget' => 'required|numeric|min:0|lte:9999999999999.99',
            'award_criterion' => ['nullable', Rule::in(array_keys(Project::AWARD_CRITERIA))],
            'source_of_fund' => 'nullable|string|max:255',
            'contract_duration' => 'nullable|string|max:255',
            'status' => 'required|in:draft,approved_for_bidding,open,closed,awarded',
            'deadline' => 'required|date',
            'staff_id' => 'nullable|exists:users,id',
            // Schedule (Philippine time): lets a draft be corrected before posting.
            'date_posted' => 'nullable|date',
            'pre_bid_conference_date' => 'nullable|date',
            'bid_opening_date' => 'nullable|date',
            // Invitation to Bid details (RA 12009 IRR Sec. 50.2, 50.3.3).
            'bid_opening_venue' => 'nullable|string|max:500',
            'evaluation_procedure' => ['nullable', Rule::in(array_keys(Project::EVALUATION_PROCEDURES))],
            'evaluation_criteria' => 'nullable|array|max:20',
            'evaluation_criteria.*.name' => 'nullable|string|max:255',
            'evaluation_criteria.*.weight' => 'nullable|numeric|min:0|max:100',
            'quality_price_ratio' => 'nullable|integer|min:1|max:99',
            'electronic_submission_authority' => 'nullable|string|max:255',
            'bidding_documents_fee' => 'nullable|numeric|min:0|lte:9999999999999.99',
            'bidding_fee_mode' => ['nullable', Rule::in(\App\Support\BiddingDocumentsFee::MODES)],
            'bidding_fee_reason' => 'nullable|string|max:2000',
            'bidding_fee_amendment_reference' => 'nullable|string|max:255',
            'bidding_fee_amendment_reason' => 'nullable|string|max:2000',
            'payment_venue' => 'nullable|string|max:255',
        ], [
            'budget.lte' => 'Budget must not exceed 9,999,999,999,999.99.',
            'bidding_documents_fee.lte' => 'Bidding documents fee must not exceed 9,999,999,999,999.99.',
        ]);

        // The fee: the ABC schedule's maximum (following a draft's ABC), or lower / waived
        // with a reason; after publication only through a recorded amendment.
        unset($validated['bidding_documents_fee'], $validated['bidding_fee_mode'], $validated['bidding_fee_reason'], $validated['payment_venue'],
            $validated['bidding_fee_amendment_reference'], $validated['bidding_fee_amendment_reason']);
        $feeAttributes = \App\Support\BiddingDocumentsFee::resolve(
            $project,
            $request->only(['bidding_fee_mode', 'bidding_documents_fee', 'bidding_fee_reason']),
            $validated['budget']
        );
        $feeAmendment = \App\Support\BiddingDocumentsFee::assertAmendable($project, $feeAttributes, $request->all(), self::feeLockReason($project));
        $feeBefore = $project->only(['bidding_documents_fee', 'bidding_fee_mode']);
        $validated += $feeAttributes;
        if ($request->has('bidding_documents_fee') || $request->has('bidding_fee_mode')) {
            $validated['payment_venue'] = filled($request->input('payment_venue')) ? trim($request->input('payment_venue')) : $project->payment_venue;
        }

        // Only when the edit form sends them, so other callers keep the stored values.
        $invitationFields = ['bid_opening_venue', 'evaluation_procedure', 'evaluation_criteria', 'quality_price_ratio', 'electronic_submission_authority'];
        $sendsInvitation = $request->has('bid_opening_venue');
        foreach ($invitationFields as $field) {
            unset($validated[$field]);
        }
        if ($sendsInvitation) {
            $details = self::invitationDetails($request->only($invitationFields) + [
                'award_criterion' => $validated['award_criterion'] ?? $project->award_criterion,
                'category' => $project->category,
            ], $project->mode()->isCompetitive());
            $validated += $details;
            if ($request->has('electronic_submission_authority')) {
                $authority = trim((string) $request->input('electronic_submission_authority'));
                if ($authority !== (string) $project->electronic_submission_authority) {
                    $validated += [
                        'electronic_submission_authority' => $authority !== '' ? $authority : null,
                        'electronic_submission_authorized_at' => $authority !== '' ? now() : null,
                        'electronic_submission_authorized_by' => $authority !== '' ? Auth::id() : null,
                    ];
                }
            }
        }

        $staffId = $validated['staff_id'] ?? null;
        $documentType = $validated['document_type'] ?? null;
        $documentFiles = $this->extractProjectDocumentFiles($request);
        unset($validated['staff_id'], $validated['document_type']);
        unset($validated['document_files']);
        unset($validated['document_file']);

        // Keep the schedule in step with the project; fields not sent stay as they are.
        $schedule = $project->schedule()->firstOrNew();
        $scheduleData = ['bid_submission_deadline' => $validated['deadline']];
        foreach (['date_posted', 'pre_bid_conference_date', 'bid_opening_date'] as $field) {
            if ($request->has($field)) {
                $scheduleData[$field] = $validated[$field] ?? null;
            }
        }
        $schedule->fill($scheduleData);
        unset($validated['date_posted'], $validated['pre_bid_conference_date'], $validated['bid_opening_date']);

        $scheduleBefore = [
            'deadline' => $project->bidSubmissionDeadline()?->toDateTimeString(),
            'bid_opening_date' => $schedule->getOriginal('bid_opening_date')?->toDateTimeString(),
        ];
        if ($scheduleErrors = $this->scheduleChangeErrors($project, $validated['deadline'], $schedule)) {
            if ($request->ajax() || $request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json(['success' => false, 'message' => implode(' ', $scheduleErrors), 'errors' => array_map(fn ($message) => [$message], $scheduleErrors)], 422);
            }

            return back()->withInput()->withErrors($scheduleErrors);
        }

        $statusError = ($validated['status'] === 'open' && $project->status !== 'open')
            ? 'Use the Publish to BAC System action to make a draft visible to bidders.'
            : $this->projectStatusChangeError($project, $validated['status'], $validated['deadline'], $schedule);

        if ($statusError) {
            if ($request->ajax() || $request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json(['success' => false, 'message' => $statusError, 'errors' => ['status' => [$statusError]]], 422);
            }

            return back()->withInput()->withErrors(['status' => $statusError]);
        }

        $project->update($validated);
        if ($feeAmendment !== null) {
            \App\Support\BiddingDocumentsFee::recordAmendment($project, $feeBefore, $feeAmendment, Auth::user());
        }

        $schedule->project_id = $project->id;
        $schedule->save();
        $warnings = $this->applyScheduleChange($project->fresh(['schedule']), $scheduleBefore);
        $this->storeProjectDocuments($project, $documentFiles, $documentType);

        if ($staffId) {
            Assignment::updateOrCreate(
                ['project_id' => $project->id],
                ['staff_id' => $staffId]
            );
        } else {
            Assignment::where('project_id', $project->id)->delete();
        }

        if ($request->ajax() || $request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            // Shown by the list after it reloads.
            session()->flash('schedule_warnings', $warnings);

            return response()->json(['success' => true, 'message' => 'Project updated successfully!', 'warnings' => $warnings]);
        }

        return redirect()->route('admin.projects')->with('success', 'Project updated successfully!')->with('schedule_warnings', $warnings);
    }

    public function publishProject(Request $request, Project $project)
    {
        if (! in_array($project->status, ['draft', 'approved_for_bidding'], true)) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft or approved-for-bidding projects can be published.',
                ], 422);
            }
            return redirect()->route('admin.projects')->with('error', 'Only draft or approved-for-bidding projects can be published.');
        }

        $project->loadMissing(['schedule', 'documents']);
        $publicationAt = now(config('app.timezone', 'Asia/Manila'));
        $blockers = $project->publicationBlockers(now(config('app.timezone', 'Asia/Manila')), $publicationAt);
        if ($blockers !== []) {
            $message = 'Not yet ready for local BAC publication: ' . implode(' ', $blockers);
            $errors = array_map(static fn ($error) => [$error], $blockers);
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message, 'errors' => $errors], 422);
            }
            return redirect()->route('admin.projects')->withErrors($blockers)->with('error', $message);
        }

        app(\App\Support\ProjectPublication::class)->publish($project, Auth::user(), $publicationAt);
        $warnings = $project->fresh('schedule')->scheduleWarningList();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Project published successfully! It is now ready for bidding.',
                'warnings' => $warnings,
            ]);
        }

        return redirect()->route('admin.projects')->with('success', 'Project published successfully!')->with('schedule_warnings', $warnings);
    }

    public function destroyProject(Request $request, Project $project)
    {
        $project->loadMissing('procurementRequest');
        $procurementRequest = $project->procurementRequest;
        $requestReturnedToBac = $procurementRequest?->status === \App\Models\ProcurementRequest::STATUS_IN_PROCUREMENT;

        DB::transaction(function () use ($project, $procurementRequest, $requestReturnedToBac): void {
            if ($requestReturnedToBac && $procurementRequest) {
                $before = $procurementRequest->only(['status']);
                $procurementRequest->update(['status' => \App\Models\ProcurementRequest::STATUS_FORWARDED]);
                AuditLog::log('procurement_project_deleted', $procurementRequest, $before, [
                    'status' => \App\Models\ProcurementRequest::STATUS_FORWARDED,
                    'deleted_project_id' => $project->id,
                    'deleted_project_title' => $project->title,
                ]);
            }

            $project->delete();
        });

        $message = $requestReturnedToBac
            ? 'Project deleted successfully. Its purchase request was returned to the Forwarded to BAC queue.'
            : 'Project deleted successfully.';

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->route('admin.projects')->with('success', $message);
    }

    public function archiveProject(Request $request, Project $project)
    {
        if ($project->archived_at) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project is already archived.',
                ], 422);
            }

            return redirect()->route('admin.projects')->with('error', 'Project is already archived.');
        }

        $project->forceFill(['archived_at' => now()])->save();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Project archived successfully.',
            ]);
        }

        return redirect()->route('admin.projects')->with('success', 'Project archived successfully.');
    }

    public function awards()
    {
        // Bids the HoPE approved before the hand-off existed get their award record now (idempotent).
        Bid::awaitingAwardRecord()->whereDoesntHave('award')->get()->each(function (Bid $bid) {
            try {
                $this->bidWorkflow()->handOffApprovedAward($bid);
            } catch (\Illuminate\Validation\ValidationException) {
                // Another bidder's award is in force; the BAC resolves that through a cancellation.
            }
        });

        $awards = Award::with(['project', 'bidder', 'approver', 'canceller', 'bid.user', 'bid.project', 'contractImplementation.events.actor'])->latest()->get();
        $implementationWorkflow = app(\App\Support\ContractImplementationWorkflow::class);
        $awards->each(function ($award) use ($implementationWorkflow) {
            if ($implementationWorkflow->eligible($award)) {
                $award->setRelation('contractImplementation', $implementationWorkflow->ensure($award)->load('events.actor'));
            }
        });
        
        // Ready for the award record once the HoPE approved the BAC-recommended
        // bid; the lowest bid alone is not a recommendation or an approval.
        $readyProjects = Project::with([
                'bids' => function ($query) {
                    $query->with('user')->awaitingAwardRecord();
                },
            ])
            ->withCount(['bids as evaluated_bids_count' => fn ($query) => $query->whereNotNull('evaluated_at')])
            ->where('status', '!=', 'awarded')
            ->whereNull('failed_bidding_at')
            // Approvals already handed off are listed as awards; this lists only what could not be.
            ->whereDoesntHave('awards', fn ($query) => $query->whereNull('cancelled_at'))
            ->whereHas('bids', fn ($query) => $query->awaitingAwardRecord())
            ->latest('updated_at')
            ->get();

        return view('admin.awards', compact('awards', 'readyProjects'));
    }

    /** Every award with its relations, newest first; the register behind the export and the report. */
    private function awardRegister()
    {
        return Award::with(['project', 'bidder', 'bid.user', 'bid.project'])->latest()->get();
    }

    private function awardStageLabel(Award $award): string
    {
        if ($award->isCancelled()) {
            return 'Award cancelled';
        }
        if ($award->bid?->project_completed_at) {
            return 'Completed';
        }
        $status = $award->bid?->progress()->adminStatus();

        return match ($status['key'] ?? null) {
            'notice_to_proceed' => 'NTP issued',
            'contract_signed' => 'Contract signed',
            'notice_of_award' => 'NOA issued',
            'award_approval' => 'Award approved',
            default => $status['label'] ?? 'Awarded',
        };
    }

    public function exportAwards(Request $request)
    {
        $awards = $this->awardRegister();
        $zone = config('bac-office.display_timezone', 'Asia/Manila');
        $safe = static fn ($value) => preg_match('/^[\s]*[=+@-]|^[\t\r\n]/u', (string) $value) ? "'".(string) $value : (string) $value;

        return response()->streamDownload(function () use ($awards, $safe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Project reference', 'Project', 'Procurement mode', 'Winning bidder', 'Contract amount (PHP)', 'Award (NOA) date', 'Contract date', 'Contract stage', 'Certificate no.', 'Certificate status'], ',', '"', '');
            foreach ($awards as $award) {
                fputcsv($out, [
                    $safe($award->project?->reference_no),
                    $safe($award->project?->title),
                    $safe($award->project?->mode()->label()),
                    $safe($award->bidder?->company ?: ($award->bidder?->name ?? $award->bid?->user?->name)),
                    number_format((float) $award->contract_amount, 2, '.', ''),
                    $award->awardDate()?->format('Y-m-d'),
                    $award->contract_date?->format('Y-m-d'),
                    $this->awardStageLabel($award),
                    $safe($award->certificate_number),
                    $safe($award->certificate_status ?: $award->status),
                ], ',', '"', '');
            }
            fclose($out);
        }, 'awards-'.now($zone)->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    /** Printable awards and contracts report for a period (by award date). */
    public function awardsReport(Request $request)
    {
        $zone = config('bac-office.display_timezone', 'Asia/Manila');
        $all = ! $request->hasAny(['from', 'to']) || $request->boolean('all');
        $today = now($zone)->startOfDay();
        $parse = function (?string $value, \Carbon\Carbon $fallback) use ($zone): \Carbon\Carbon {
            try {
                return $value ? \Carbon\Carbon::parse($value, $zone)->startOfDay() : $fallback;
            } catch (\Throwable) {
                return $fallback;
            }
        };
        $from = $parse($request->query('from'), $today->copy()->startOfYear());
        $to = $parse($request->query('to'), $today);
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $awards = $this->awardRegister()->filter(function (Award $award) use ($all, $from, $to) {
            if ($all) {
                return true;
            }
            $date = ($award->awardDate() ?? $award->created_at)?->copy()->startOfDay();

            return $date !== null && $date->betweenIncluded($from, $to);
        })->values();

        $inForce = $awards->reject(fn (Award $award) => $award->isCancelled())->values();
        $rows = $awards->map(fn (Award $award) => [
            'title' => $award->project?->title ?? 'Untitled project',
            'reference' => $award->project?->reference_no,
            'bidder' => $award->bidder?->company ?: ($award->bidder?->name ?? $award->bid?->user?->name ?? 'N/A'),
            'date' => $award->awardDate(),
            'stage' => $this->awardStageLabel($award),
            'cancelled' => $award->isCancelled(),
            'amount' => (float) $award->contract_amount,
        ]);
        $byMode = $inForce->groupBy(fn (Award $award) => $award->project?->mode()->label() ?? 'Other')->map(fn ($group, $mode) => [
            'mode' => $mode,
            'count' => $group->count(),
            'amount' => (float) $group->sum('contract_amount'),
        ])->sortByDesc('amount')->values();

        return view('admin.awards-report', [
            'embed' => $request->boolean('embed'),
            'zone' => $zone,
            'all' => $all,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'inForce' => $inForce,
            'byMode' => $byMode,
            'contractValue' => (float) $inForce->sum('contract_amount'),
            'signedCount' => $inForce->filter(fn (Award $award) => $award->contract_date !== null || $award->bid?->contract_signed_at !== null)->count(),
            'ntpCount' => $inForce->filter(fn (Award $award) => $award->hasPublishedNoticeToProceed())->count(),
            'user' => Auth::user(),
        ]);
    }

    public function viewAward(Request $request, Award $award)
    {
        $award->load(['project', 'bid.user']);

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.award-view-modal', compact('award'));
        }

        return view('admin.award-view', compact('award'));
    }
    public function reports(Request $request)
    {
        return view('admin.reports', $this->buildReportsData($request));
    }

    public function exportReportsCsv(Request $request)
    {
        $report = $this->buildReportsData($request);
        $filename = 'bac-office-reports-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['SJBAC Report Analytics']);
            fputcsv($handle, ['Generated At', now()->format('M d, Y h:i A')]);
            fputcsv($handle, ['Date From', $report['filters']['date_from'] ?: 'All dates']);
            fputcsv($handle, ['Date To', $report['filters']['date_to'] ?: 'All dates']);
            fputcsv($handle, ['Status', $report['filters']['status_label']]);
            fputcsv($handle, ['Procurement Type', $report['filters']['procurement_type_label']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Analytics Summary']);
            fputcsv($handle, ['Metric', 'Value']);
            foreach ($report['summaryCards'] as $card) {
                fputcsv($handle, [$card['label'], $card['display'], $card['note']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Procurement Pipeline']);
            fputcsv($handle, ['Stage', 'Projects reached', 'Projects at this stage']);
            foreach ($report['pipeline']['stages'] as $stage) {
                fputcsv($handle, [$stage['label'], $stage['reached'], $stage['here']]);
            }
            fputcsv($handle, ['Failed bidding', $report['pipeline']['failed'], '']);
            fputcsv($handle, []);

            fputcsv($handle, ['Procurement Status Distribution']);
            fputcsv($handle, ['Status', 'Projects']);
            foreach ($report['procurementStatusDistribution'] as $row) {
                fputcsv($handle, [$row['label'], $row['value']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Monthly Procurement Activity']);
            fputcsv($handle, ['Month', 'Projects', 'Bids', 'Awards']);
            foreach ($report['monthlyActivity'] as $row) {
                fputcsv($handle, [$row['label'], $row['projects'], $row['bids'], $row['awards']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Bids per Project']);
            fputcsv($handle, ['Project', 'Bids']);
            foreach ($report['bidsPerProject'] as $row) {
                fputcsv($handle, [$row['label'], $row['value']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['ABC vs Winning Bid Amount']);
            fputcsv($handle, ['Project', 'ABC', 'Winning Bid']);
            foreach ($report['abcVsWinning'] as $row) {
                fputcsv($handle, [$row['label'], $row['abc'], $row['winning']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Bid Result Distribution']);
            fputcsv($handle, ['Result', 'Bids']);
            foreach ($report['bidResultDistribution'] as $row) {
                fputcsv($handle, [$row['label'], $row['value']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Bidder Participation']);
            fputcsv($handle, ['Bidder', 'Bids']);
            foreach ($report['bidderParticipation'] as $row) {
                fputcsv($handle, [$row['label'], $row['value']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Monitoring']);
            fputcsv($handle, ['Upcoming Deadlines', $report['monitoring']['upcoming_deadlines']['count']]);
            fputcsv($handle, ['Needs Action', $report['monitoring']['needs_action']['count']]);
            foreach ($report['monitoring']['needs_action']['items'] as $row) {
                fputcsv($handle, ['', $row['project']->title, $row['reason']]);
            }
            fputcsv($handle, ['Pending Bidder Validations', $report['monitoring']['pending_bidder_validations']['count']]);
            fputcsv($handle, ['Projects Awaiting BAC Evaluation', $report['monitoring']['awaiting_bac_evaluation']['count']]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function printReports(Request $request)
    {
        return Pdf::loadView('admin.reports-print', $this->buildReportsData($request))
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
        $bids = $project->bids()
            ->with('user')
            ->awaitingAwardRecord()
            ->get();
        $selectedBidId = $request->integer('bid');

        return view('admin.award-create', compact('project', 'bids', 'selectedBidId'));
    }

    public function storeAward(Request $request)
    {
        $project = Project::findOrFail($request->input('project_id'));

        return $this->declareWinner($request, $project);
    }

    /**
     * Issue the Notice of Award from the Awards page: the HoPE-approved bid
     * receives the signed NOA, which creates the award record. Same action as
     * "Issue Notice of Award" in the Review Bid modal (BidWorkflow).
     */
    public function declareWinner(Request $request, Project $project)
    {
        $validated = $request->validate([
            'bid_id' => ['required', 'integer', 'exists:bids,id'],
            'notes' => 'nullable|string',
            'certificate_file' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ], [
            'certificate_file.required' => 'Attach the signed Notice of Award (PDF).',
        ]);

        $bid = Bid::where('project_id', $project->id)->findOrFail($validated['bid_id']);

        try {
            $this->bidWorkflow()->apply($bid, BidWorkflow::NOTICE_OF_AWARD, Auth::user(), [
                'notice_file' => $request->file('certificate_file'),
                'notes' => $validated['notes'] ?? null,
            ]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $errors = $exception->errors();
            if (isset($errors['milestone'])) {
                // Keep the field the award form shows errors under.
                $errors['bid_id'] = $errors['milestone'];
            }

            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        $message = 'Notice of Award issued. The award record and QR-verifiable document were created.';

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['success' => true, 'message' => $message, 'redirect' => route('admin.awards.index')]);
        }

        return redirect()->route('admin.awards.index')->with('success', $message);
    }

    /**
     * Cancel an award before contract signing, by the HoPE, with the reason and
     * authority on record (BidWorkflow::cancelAward). Never deletes the award.
     */
    /** The bidder's actual receipt of the NTP and its PhilGEPS posting, entered by hand after issuance. */
    public function updateNoticeToProceed(Request $request, Award $award)
    {
        $validated = $request->validate([
            'ntp_received_on' => 'nullable|date',
            'ntp_philgeps_posted_on' => 'nullable|date',
            'ntp_philgeps_reference' => 'nullable|string|max:500',
        ]);

        $this->bidWorkflow()->updateNoticeToProceedRecord($award, Auth::user(), $validated);

        return redirect()->route('admin.awards.index')
            ->with('success', 'Notice to Proceed record updated for '.($award->project?->title ?? 'the project').'.');
    }

    public function cancelAward(Request $request, Award $award)
    {
        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:2000'],
            'cancellation_reference' => ['required', 'string', 'max:255'],
            'supporting_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ], [
            'cancellation_reason.required' => 'Enter why the award is cancelled; the winning bidder sees this reason.',
            'cancellation_reference.required' => 'Enter the HoPE memorandum or BAC resolution that authorizes the cancellation.',
        ]);

        $this->bidWorkflow()->cancelAward($award, Auth::user(), $validated['cancellation_reason'], $validated['cancellation_reference'], $request->file('supporting_document'));

        return redirect()->route('admin.awards.index')
            ->with('success', 'Award cancelled for '.($award->project?->title ?? 'the project').'. The project is back with the BAC for its next decision.');
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

            $award = app(\App\Services\AwardCertificateService::class)
                ->ensureForValidAward($award);

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
                'exception' => $e::class,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
                'exception' => $e::class,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
                'exception' => $e::class,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
                // An account may keep an office that is no longer on the list (older records), e.g. to reset its password.
                Rule::in(array_merge(
                    User::staffOfficeOptions(),
                    $user && $user->role === $request->input('role') && filled($user->office) ? [$user->office] : [],
                )),
            ],
            'password' => $passwordRule,
            'position' => [
                Rule::excludeIf($request->input('role') === 'bidder'),
                'nullable',
                'string',
                'max:120',
            ],
            'contact_number' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+()\-.\s\/]+$/'],
            'business_address' => [
                Rule::excludeIf($request->input('role') !== 'bidder'),
                'nullable',
                'string',
                'max:500',
            ],
            'company' => [
                Rule::excludeIf($request->input('role') !== 'bidder'),
                'required',
                'string',
                'max:255',
            ],
            'registration_no' => [
                Rule::excludeIf($request->input('role') !== 'bidder'),
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($user) {
                    if (User::registrationNumberTaken($value, $user?->id)) {
                        $fail('Another bidder already uses this business registration number.');
                    }
                },
            ],
        ], [
            'username.regex' => 'Username may only contain letters, numbers, dots, dashes, and underscores.',
            'contact_number.regex' => 'Enter a contact number using digits, spaces, +, ( ) or dashes only.',
            'company.required' => 'Enter the bidder\'s company name.',
        ]);
    }

    /**
     * Splits validated user input into the users-table columns and the bidder
     * profile fields (contact number, business address) that live on bidders.
     * Position and contact number wait for their migration when it is not applied yet.
     *
     * @return array{0: array, 1: array}
     */
    protected function splitUserProfile(array $validated): array
    {
        $profile = [
            'contact_number' => $validated['contact_number'] ?? null,
            'business_address' => $validated['business_address'] ?? null,
        ];
        unset($validated['business_address']);

        if (! User::contactColumnsAvailable()) {
            unset($validated['position'], $validated['contact_number']);
        } elseif (($validated['role'] ?? null) === 'bidder') {
            $validated['position'] = null;
        }

        return [$validated, $profile];
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
                'contact_person' => $user->name,
                'contact_number' => 'Not provided',
                'business_address' => 'Not provided',
                'document_path' => $registrationDocumentPath,
                'approval_status' => $user->status === 'active' ? 'approved' : 'pending',
                'review_status' => $user->status === 'active' ? null : 'new',
                'review_message' => null,
                'rejection_reason' => null,
                'approved_at' => $user->status === 'active' ? now() : null,
                'approved_by' => $user->status === 'active' ? Auth::id() : null,
            ]
        );
    }

     protected function syncBidderProfile(User $user, array $profile = []): void
     {
         if ($user->role !== 'bidder' || ! Schema::hasTable('bidders')) {
             return;
         }

        $bidder = $this->ensureBidderProfile($user);
        $updates = [
            'company_name' => $user->company ?: $bidder->company_name ?: $user->name,
            'contact_person' => $bidder->contact_person ?: $user->name,
        ];
        // Contact number and business address from the admin's create/edit dialog.
        foreach (['contact_number', 'business_address'] as $field) {
            if (array_key_exists($field, $profile)) {
                $updates[$field] = filled($profile[$field]) ? trim((string) $profile[$field]) : $bidder->{$field};
            }
        }

        $hasActiveSanction = Schema::hasTable('bidder_sanctions')
            && $bidder->activeSanction()->exists();

        if (! $hasActiveSanction) {
            $updates['approval_status'] = $user->status === 'active'
                ? 'approved'
                : ($user->status === 'rejected' ? 'rejected' : 'pending');
        }

        $bidder->forceFill($updates)->save();
    }





    protected function parseReportDate(mixed $value): ?\Carbon\Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function buildReportsData(Request $request): array
    {
        $statusLabels = [
            'draft' => 'Draft',
            'approved_for_bidding' => 'Approved for bidding',
            'open' => 'Open',
            'closed' => 'Closed',
            'awarded' => 'Awarded',
        ];
        $procurementTypeLabels = [
            'public_bidding' => 'Public bidding',
            'negotiated_procurement' => 'Negotiated procurement',
            'shopping' => 'Shopping',
            'small_value_procurement' => 'Small value procurement',
            'direct_contracting' => 'Direct contracting',
            'electronic_procurement' => 'Electronic procurement',
        ];

        $dateFrom = $this->parseReportDate($request->input('date_from'));
        $dateTo = $this->parseReportDate($request->input('date_to'));
        if ($dateFrom && $dateTo && $dateFrom->greaterThan($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $requestedStatus = $request->input('status');
        $selectedStatus = is_string($requestedStatus) && array_key_exists($requestedStatus, $statusLabels)
            ? $requestedStatus
            : null;
        $requestedProcurementType = $request->input('procurement_type');
        $selectedProcurementType = is_string($requestedProcurementType) && array_key_exists($requestedProcurementType, $procurementTypeLabels)
            ? $requestedProcurementType
            : null;

        $projects = Project::query()
            ->with(['bids.user', 'awards.bid', 'schedule', 'assignments'])
            ->when($dateFrom, fn ($query) => $query->where('created_at', '>=', $dateFrom->copy()->startOfDay()))
            ->when($dateTo, fn ($query) => $query->where('created_at', '<=', $dateTo->copy()->endOfDay()))
            ->when($selectedStatus, fn ($query) => $query->where('status', $selectedStatus))
            ->when($selectedProcurementType, fn ($query) => $query->where('procurement_mode', $selectedProcurementType))
            ->orderByDesc('created_at')
            ->get();

        // Drafts saved by bidders are not bids: every count uses official submissions.
        $bids = $projects->flatMap(fn ($project) => $project->bids)->reject(fn (Bid $bid) => $bid->isDraft())->values();
        $awards = $projects->flatMap(fn ($project) => $project->awards)->values();
        $bidderUsers = User::query()
            ->where('role', 'bidder')
            ->with(['bidderProfile.activeSanction'])
            ->when($dateFrom, fn ($query) => $query->where('created_at', '>=', $dateFrom->copy()->startOfDay()))
            ->when($dateTo, fn ($query) => $query->where('created_at', '<=', $dateTo->copy()->endOfDay()))
            ->orderBy('name')
            ->get();

        $blacklistedBidders = $bidderUsers->filter(
            fn ($user) => $user->bidderProfile?->activeSanction?->type === BidderSanction::TYPE_BLACKLISTED
        )->values();
        $pendingBidders = $bidderUsers->filter(
            fn ($user) => $user->bidderProfile?->approval_status === 'pending'
        )->values();
        $summaryCards = $this->buildReportKpis($projects, $bids);
        $charts = $this->buildReportCharts($projects, $bids, $awards, $dateFrom, $dateTo);
        $monitoring = $this->buildReportMonitoring($projects, $pendingBidders);
        $today = now(config('bac-office.display_timezone', 'Asia/Manila'))->startOfDay();
        $presetQuery = array_filter([
            'status' => $selectedStatus,
            'procurement_type' => $selectedProcurementType,
        ]);
        $datePresets = collect([
            'month' => ['This month', $today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'quarter' => ['This quarter', $today->copy()->startOfQuarter(), $today->copy()->endOfQuarter()],
            'year' => ['This year (FY '.$today->year.')', $today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'last12' => ['Last 12 months', $today->copy()->startOfMonth()->subMonths(11), $today->copy()->endOfMonth()],
        ])->map(fn ($preset) => [
            'label' => $preset[0],
            'url' => route('admin.reports', $presetQuery + ['date_from' => $preset[1]->toDateString(), 'date_to' => $preset[2]->toDateString()]),
            'active' => $dateFrom?->toDateString() === $preset[1]->toDateString() && $dateTo?->toDateString() === $preset[2]->toDateString(),
        ])->all();
        $filters = [
            'date_from' => $dateFrom?->format('Y-m-d') ?: '',
            'date_to' => $dateTo?->format('Y-m-d') ?: '',
            'status' => $selectedStatus ?: '',
            'status_label' => $selectedStatus ? $statusLabels[$selectedStatus] : 'All statuses',
            'procurement_type' => $selectedProcurementType ?: '',
            'procurement_type_label' => $selectedProcurementType ? $procurementTypeLabels[$selectedProcurementType] : 'All procurement types',
        ];

        return [
            'filters' => $filters,
            'filterQuery' => array_filter([
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'status' => $filters['status'],
                'procurement_type' => $filters['procurement_type'],
            ], fn ($value) => $value !== ''),
            'statusOptions' => $statusLabels,
            'procurementTypeOptions' => $procurementTypeLabels,
            'datePresets' => $datePresets,
            'summaryCards' => $summaryCards,
            'monitoring' => $monitoring,
            'pipeline' => $this->buildReportPipeline($projects),
            'upcomingSchedule' => $this->buildReportUpcomingSchedule($projects),
            'bidderTotals' => ['registered' => $bidderUsers->count(), 'blacklisted' => $blacklistedBidders->count()],
        ] + $charts;
    }

    /**
     * Headline figures: volume, competition, savings against the ABC, failed
     * biddings, and how long procurement takes. Each card says how it is
     * computed; "—" when there is nothing to measure yet.
     *
     * @return list<array{label: string, value: ?float, format: string, note: string, icon: string, tone: string}>
     */
    protected function buildReportKpis(Collection $projects, Collection $bids): array
    {
        $now = now(config('bac-office.display_timezone', 'Asia/Manila'));
        $officialBids = $bids->groupBy('project_id');
        $closed = $projects->filter(fn (Project $project) => $project->submissionDeadlinePassed() || $project->bids_opened_at !== null);
        $reachedOpening = $projects->filter(fn (Project $project) => $project->bids_opened_at !== null || $project->isFailedBidding());

        // Savings: ABC minus the awarded contract amount, over awarded projects.
        $awarded = $projects->map(function (Project $project) {
            $award = $project->awards->sortByDesc('contract_date')->first();
            $amount = $award?->contract_amount ?? $award?->bid?->bid_amount;

            return $award && $amount !== null && (float) $project->budget > 0
                ? ['abc' => (float) $project->budget, 'amount' => (float) $amount]
                : null;
        })->filter();
        $abcTotal = $awarded->sum('abc');
        $savings = $abcTotal - $awarded->sum('amount');

        // Days from publication to the Notice of Award.
        $daysToAward = $projects->map(function (Project $project) {
            $notice = $project->bids->pluck('notice_of_award_at')->filter()->min();
            $published = $project->publicationTime();

            return $notice && $published ? max(0, $published->copy()->startOfDay()->diffInDays($notice->copy()->startOfDay())) : null;
        })->filter(fn ($days) => $days !== null);

        $display = fn (array $card) => $card + ['display' => $card['value'] === null ? '—' : match ($card['format']) {
            'peso' => '₱'.number_format($card['value'], 2),
            'percent' => number_format($card['value'], 1).'%',
            'decimal' => number_format($card['value'], 1),
            default => number_format($card['value']),
        }];

        return array_map($display, [
            ['label' => 'Projects', 'value' => $projects->count(), 'format' => 'int', 'note' => 'In the selected range', 'icon' => 'fa-folder-open', 'tone' => 'blue'],
            ['label' => 'Accepting bids now', 'value' => $projects->filter(fn (Project $project) => $project->isOpenForBidding($now))->count(), 'format' => 'int', 'note' => 'Published, before the deadline', 'icon' => 'fa-bolt', 'tone' => 'green'],
            ['label' => 'Official bids', 'value' => $bids->count(), 'format' => 'int', 'note' => 'Drafts not counted', 'icon' => 'fa-gavel', 'tone' => 'violet'],
            ['label' => 'Awarded', 'value' => $projects->filter(fn (Project $project) => $project->awards->isNotEmpty())->count(), 'format' => 'int', 'note' => 'Notice of Award issued', 'icon' => 'fa-trophy', 'tone' => 'gold'],
            ['label' => 'Savings vs ABC', 'value' => $awarded->isEmpty() ? null : $savings, 'format' => 'peso', 'note' => $awarded->isEmpty() ? 'No awards yet' : number_format($abcTotal > 0 ? $savings / $abcTotal * 100 : 0, 1).'% below the ABC of awarded projects', 'icon' => 'fa-piggy-bank', 'tone' => 'green'],
            ['label' => 'Bidders per project', 'value' => $closed->isEmpty() ? null : round($closed->sum(fn (Project $project) => $officialBids->get($project->id)?->count() ?? 0) / $closed->count(), 1), 'format' => 'decimal', 'note' => 'Average, projects past the deadline', 'icon' => 'fa-users', 'tone' => 'sky'],
            ['label' => 'Failed bidding rate', 'value' => $reachedOpening->isEmpty() ? null : round($reachedOpening->filter(fn (Project $project) => $project->isFailedBidding())->count() / $reachedOpening->count() * 100, 1), 'format' => 'percent', 'note' => 'Of projects that reached opening', 'icon' => 'fa-circle-xmark', 'tone' => 'red'],
            ['label' => 'Days to award', 'value' => $daysToAward->isEmpty() ? null : round($daysToAward->avg()), 'format' => 'int', 'note' => 'Average, publication to Notice of Award', 'icon' => 'fa-stopwatch', 'tone' => 'gold'],
        ]);
    }

    /**
     * How far each project has gone, stage by stage: "reached" counts every
     * project at or past the stage, "here" the ones currently stopped there.
     *
     * @return array{stages: list<array{key: string, label: string, reached: int, here: int}>, failed: int, total: int}
     */
    protected function buildReportPipeline(Collection $projects): array
    {
        $stages = [
            'published' => 'Published',
            'closed' => 'Submission closed',
            'opened' => 'Bids opened',
            'evaluated' => 'Evaluated',
            'post_qualified' => 'Post-qualified',
            'notice_of_award' => 'Notice of Award',
            'contract' => 'Contract signed',
            'ntp' => 'Notice to Proceed',
            'completed' => 'Completed',
        ];
        $keys = array_keys($stages);
        $any = fn (Project $project, string $field) => $project->bids->contains(fn (Bid $bid) => $bid->{$field} !== null);
        $furthest = $projects->reject(fn (Project $project) => $project->isFailedBidding())->map(function (Project $project) use ($any) {
            return match (true) {
                $project->isCompleted() => 'completed',
                $any($project, 'notice_to_proceed_at') => 'ntp',
                $any($project, 'contract_signed_at') => 'contract',
                $any($project, 'notice_of_award_at') || $project->awards->isNotEmpty() => 'notice_of_award',
                $any($project, 'post_qualification_completed_at') => 'post_qualified',
                $any($project, 'evaluated_at') => 'evaluated',
                $project->bidsAreOpened() => 'opened',
                in_array($project->status, Project::PUBLIC_STATUSES, true) && $project->submissionDeadlinePassed() => 'closed',
                in_array($project->status, Project::PUBLIC_STATUSES, true) => 'published',
                default => null,
            };
        })->filter()->countBy();

        return [
            'stages' => collect($stages)->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'reached' => collect($keys)->slice(array_search($key, $keys, true))->sum(fn ($later) => $furthest->get($later, 0)),
                'here' => $furthest->get($key, 0),
            ])->values()->all(),
            'failed' => $projects->filter(fn (Project $project) => $project->isFailedBidding())->count(),
            'total' => $projects->count(),
        ];
    }

    /**
     * Pre-bid conferences, submission deadlines and bid openings in the next
     * 14 days (Philippine time), grouped by day.
     *
     * @return list<array{date: \Carbon\Carbon, events: list<array{time: \Carbon\Carbon, type: string, label: string, project: Project}>}>
     */
    protected function buildReportUpcomingSchedule(Collection $projects): array
    {
        $zone = config('bac-office.display_timezone', 'Asia/Manila');
        $now = now($zone);
        $until = $now->copy()->addDays(14)->endOfDay();

        return $projects
            ->filter(fn (Project $project) => $project->archived_at === null && ! $project->isFailedBidding() && in_array($project->status, Project::PUBLIC_STATUSES, true))
            ->flatMap(fn (Project $project) => collect([
                ['pre_bid', 'Pre-bid conference', $project->schedule?->pre_bid_conference_date],
                ['deadline', $project->mode()->deadlineLabel(), $project->bidSubmissionDeadline()],
                ['opening', $project->mode()->openingLabel(), $project->schedule?->bid_opening_date],
            ])->filter(fn ($event) => $event[2] !== null && $event[2]->between($now, $until))
                ->map(fn ($event) => ['time' => $event[2]->copy()->timezone($zone), 'type' => $event[0], 'label' => $event[1], 'project' => $project]))
            ->sortBy(fn ($event) => $event['time']->getTimestamp())
            ->groupBy(fn ($event) => $event['time']->toDateString())
            ->map(fn ($events) => ['date' => $events->first()['time']->copy()->startOfDay(), 'events' => $events->values()->all()])
            ->values()
            ->all();
    }

    protected function buildReportCharts(Collection $projects, Collection $bids, Collection $awards, ?\Carbon\Carbon $dateFrom, ?\Carbon\Carbon $dateTo): array
    {
        $projectCharts = $this->buildReportProjectCharts($projects, $bids, $awards, $dateFrom, $dateTo);
        $bidCharts = $this->buildReportBidCharts($bids);
        $chartMaxima = [
            'status' => max(1, (int) (collect($projectCharts['procurementStatusDistribution'])->max('value') ?: 0)),
            'activity' => max(1, (int) (collect($projectCharts['monthlyActivity'])->flatMap(fn ($row) => [$row['projects'], $row['bids'], $row['awards']])->max() ?: 0)),
            'bids_per_project' => max(1, (int) (collect($projectCharts['bidsPerProject'])->max('value') ?: 0)),
            'abc' => max(1, (int) (collect($projectCharts['abcVsWinning'])->map(fn ($row) => max($row['abc'], $row['winning']))->max() ?: 0)),
            'bid_result' => max(1, (int) (collect($bidCharts['bidResultDistribution'])->max('value') ?: 0)),
            'bidder' => max(1, (int) (collect($bidCharts['bidderParticipation'])->max('value') ?: 0)),
        ];

        return $projectCharts + $bidCharts + ['chartMaxima' => $chartMaxima];
    }

    protected function buildReportProjectCharts(Collection $projects, Collection $bids, Collection $awards, ?\Carbon\Carbon $dateFrom, ?\Carbon\Carbon $dateTo): array
    {
        // Where each project stands now, by its saved schedule and recorded decisions.
        $phases = [
            'draft' => ['Draft', '#94a3b8'],
            'approved_for_bidding' => ['Approved for bidding', '#8b5cf6'],
            'scheduled' => ['Scheduled for publication', '#0ea5e9'],
            'accepting' => ['Accepting bids', '#10b981'],
            'awaiting_opening' => ['Submission closed · awaiting opening', '#eab308'],
            'evaluation' => ['Bids opened · evaluation', '#64748b'],
            'awarded' => ['Awarded', '#f59e0b'],
            'completed' => ['Completed', '#047857'],
            'failed' => ['Failed bidding', '#ef4444'],
        ];
        $phaseOf = fn (Project $project): string => match (true) {
            $project->isFailedBidding() => 'failed',
            $project->isCompleted() => 'completed',
            $project->status === 'awarded' || $project->awards->isNotEmpty() => 'awarded',
            $project->status === 'closed' || $project->bidsAreOpened() => 'evaluation',
            $project->status === 'open' && $project->isScheduledForPublication() => 'scheduled',
            $project->status === 'open' && $project->submissionDeadlinePassed() => 'awaiting_opening',
            $project->status === 'open' => 'accepting',
            $project->status === 'approved_for_bidding' => 'approved_for_bidding',
            default => 'draft',
        };
        $phaseCounts = $projects->map($phaseOf)->countBy();
        $procurementStatusDistribution = collect($phases)->map(fn ($phase, $key) => [
            'key' => $key,
            'label' => $phase[0],
            'value' => $phaseCounts->get($key, 0),
            'color' => $phase[1],
        ])->values()->all();

        $activityEnd = ($dateTo ?: now())->copy()->endOfMonth();
        $activityStart = ($dateFrom ?: $activityEnd->copy()->startOfMonth()->subMonths(11))->copy()->startOfMonth();
        if ($activityStart->diffInMonths($activityEnd) > 11) {
            $activityStart = $activityEnd->copy()->startOfMonth()->subMonths(11);
        }
        $monthlyActivity = [];
        for ($cursor = $activityStart->copy(); $cursor->lessThanOrEqualTo($activityEnd); $cursor->addMonth()) {
            $key = $cursor->format('Y-m');
            $monthlyActivity[] = [
                'key' => $key,
                'label' => $cursor->format('M Y'),
                'projects' => $projects->filter(fn ($project) => $project->created_at?->format('Y-m') === $key)->count(),
                'bids' => $bids->filter(fn ($bid) => $bid->created_at?->format('Y-m') === $key)->count(),
                'awards' => $awards->filter(function ($award) use ($key) {
                    $date = $award->contract_date ?: $award->created_at;
                    return $date?->format('Y-m') === $key;
                })->count(),
            ];
        }

        $officialBidCounts = $bids->countBy('project_id');
        $bidsPerProject = $projects
            ->map(fn ($project) => [
                'label' => Str::limit((string) $project->title, 34),
                'full_label' => $project->title,
                'value' => $officialBidCounts->get($project->id, 0),
            ])
            ->filter(fn ($row) => $row['value'] > 0)
            ->sortByDesc('value')
            ->take(8)
            ->values()
            ->all();

        $abcVsWinning = $projects->map(function ($project) {
            $award = $project->awards->sortByDesc('contract_date')->first();
            $winningBid = $award?->bid ?: $project->bids->first(
                fn ($bid) => $bid->status === 'awarded' || $bid->workflow_step === Bid::STEP_AWARDED
            );
            $winningAmount = $award?->contract_amount ?? $winningBid?->bid_amount;
            if ($project->budget === null || $winningAmount === null) {
                return null;
            }

            return [
                'label' => Str::limit((string) $project->title, 28),
                'full_label' => $project->title,
                'abc' => (float) $project->budget,
                'winning' => (float) $winningAmount,
            ];
        })->filter()->sortByDesc('abc')->take(8)->values()->all();

        return compact('procurementStatusDistribution', 'monthlyActivity', 'bidsPerProject', 'abcVsWinning');
    }

    protected function buildReportBidCharts(Collection $bids): array
    {
        $resultLabels = [
            'awarded' => 'Awarded',
            'approved' => 'Approved',
            'pending' => 'Pending review',
            'rejected' => 'Rejected',
            'disqualified' => 'Disqualified',
        ];
        $resultCounts = array_fill_keys(array_keys($resultLabels), 0);
        foreach ($bids as $bid) {
            $workflow = $bid->workflow_step ?: $bid->status;
            $result = match (true) {
                $workflow === Bid::STEP_AWARDED || $bid->status === 'awarded' => 'awarded',
                $workflow === Bid::STEP_DISQUALIFIED || $bid->status === 'rejected' => 'disqualified',
                $bid->status === 'approved' => 'approved',
                default => 'pending',
            };
            $resultCounts[$result]++;
        }
        $bidResultDistribution = collect($resultLabels)->map(function ($label, $key) use ($resultCounts) {
            return [
                'key' => $key,
                'label' => $label,
                'value' => $resultCounts[$key],
                'color' => match ($key) {
                    'awarded' => '#f59e0b',
                    'approved' => '#10b981',
                    'rejected', 'disqualified' => '#ef4444',
                    default => '#94a3b8',
                },
            ];
        })->values()->all();

        $bidderParticipation = $bids
            ->groupBy('user_id')
            ->map(function ($bidGroup) {
                $bidder = $bidGroup->first()?->user;
                $label = $bidder?->company ?: ($bidder?->name ?: 'Unknown bidder');
                return [
                    'label' => Str::limit($label, 30),
                    'full_label' => $label,
                    'value' => $bidGroup->count(),
                ];
            })
            ->sortByDesc('value')
            ->take(8)
            ->values()
            ->all();

        return compact('bidResultDistribution', 'bidderParticipation');
    }

    protected function buildReportMonitoring(Collection $projects, Collection $pendingBidders): array
    {
        $projectDeadline = fn ($project) => $project->deadline ?: $project->schedule?->bid_submission_deadline;
        $monitoringProjects = $projects->filter(fn ($project) => ! in_array($project->status, ['closed', 'awarded'], true));
        $upcomingDeadlines = $monitoringProjects
            ->map(fn ($project) => ['project' => $project, 'deadline' => $projectDeadline($project)])
            ->filter(fn ($row) => $row['deadline'] && $row['deadline']->greaterThanOrEqualTo(today()) && $row['deadline']->lessThanOrEqualTo(today()->addDays(30)))
            ->sortBy(fn ($row) => $row['deadline'])
            ->values();
        // What is waiting on the BAC, by the saved schedule and the recorded decisions.
        $needsAction = $projects
            ->reject(fn (Project $project) => $project->archived_at !== null || $project->isFailedBidding() || $project->isCompleted())
            ->map(function (Project $project) {
                $bids = $project->bids;
                $awardDue = $project->bidsAreOpened() && $project->awards->isEmpty() && ! $bids->contains(fn (Bid $bid) => $bid->notice_of_award_at !== null)
                    ? $project->mode()->awardDueDate($project->bids_opened_at)
                    : null;
                $reason = match (true) {
                    $project->status === 'open' && $project->submissionDeadlinePassed() && ! $project->bidsAreOpened()
                        => ['Submission closed · awaiting opening', 'warning'],
                    $awardDue !== null && $awardDue->isPast()
                        => ['Past the award period ('.$awardDue->timezone(config('bac-office.display_timezone'))->format('M d').')', 'danger'],
                    $bids->contains(fn (Bid $bid) => $bid->contract_signed_at !== null && $bid->notice_to_proceed_at === null)
                        => ['Contract signed · Notice to Proceed pending', 'warning'],
                    $bids->contains(fn (Bid $bid) => $bid->notice_of_award_at !== null && $bid->contract_signed_at === null)
                        => ['Notice of Award issued · contract not signed', 'warning'],
                    $project->isOpenForBidding() && $project->assignments->isEmpty()
                        => ['Open for bidding · no staff assigned', 'info'],
                    default => null,
                };

                return $reason ? ['project' => $project, 'reason' => $reason[0], 'tone' => $reason[1]] : null;
            })
            ->filter()
            ->sortBy(fn ($row) => ['danger' => 0, 'warning' => 1, 'info' => 2][$row['tone']])
            ->values();
        $awaitingEvaluation = $projects
            ->map(function ($project) {
                $evaluationBids = $project->bids->filter(fn ($bid) => $bid->workflow_step === Bid::STEP_FOR_BAC_EVALUATION);
                return ['project' => $project, 'bids' => $evaluationBids->count()];
            })
            ->filter(fn ($row) => $row['bids'] > 0)
            ->sortByDesc('bids')
            ->values();

        return [
            'upcoming_deadlines' => [
                'count' => $upcomingDeadlines->count(),
                'items' => $upcomingDeadlines->take(6),
            ],
            'needs_action' => [
                'count' => $needsAction->count(),
                'items' => $needsAction->take(8),
            ],
            'pending_bidder_validations' => [
                'count' => $pendingBidders->count(),
                'items' => $pendingBidders->take(6)->values(),
            ],
            'awaiting_bac_evaluation' => [
                'count' => $awaitingEvaluation->count(),
                'items' => $awaitingEvaluation->take(6),
            ],
        ];
    }

    protected function legacyBuildReportsData(): array
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

    private function storeProjectDocuments(Project $project, array $files, ?string $documentType = null): void
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
                'document_type' => $documentType ?: 'other',
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
