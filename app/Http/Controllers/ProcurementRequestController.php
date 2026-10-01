<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestDocument;
use App\Models\User;
use App\Support\ProcurementPipeline;
use App\Support\ProcurementTimeline;
use App\Support\SystemNotification;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Procurement requests: filed by an end-user office, reviewed against the
 * PPMP/APP and budget by authorized staff (or the admin), then forwarded to
 * the BAC, which prepares the bidding project from it.
 */
class ProcurementRequestController extends Controller
{
    /* ---------------------------------------------------------------------
     | End-user office
     * ------------------------------------------------------------------- */

    public function dashboard(Request $request)
    {
        $office = Auth::user()->office;
        $requests = ProcurementRequest::forOffice($office)->latest('updated_at')->get();
        $pipeline = ProcurementPipeline::forOffice($office);
        $filters = ProcurementPipeline::filtersFrom($request);

        return view('end-user.dashboard', [
            'office' => $office,
            'counts' => [
                'drafts' => $requests->where('status', ProcurementRequest::STATUS_DRAFT)->count(),
                'returned' => $requests->where('status', ProcurementRequest::STATUS_RETURNED)->count(),
                'review' => $requests->whereIn('status', [ProcurementRequest::STATUS_SUBMITTED, ProcurementRequest::STATUS_FORWARDED])->count(),
                'procurement' => $requests->where('status', ProcurementRequest::STATUS_IN_PROCUREMENT)->count(),
            ],
            'needsAction' => $requests->whereIn('status', [ProcurementRequest::STATUS_DRAFT, ProcurementRequest::STATUS_RETURNED])->values(),
            'filters' => $filters,
            'rows' => $pipeline->paginate($filters, 10),
            'upcoming' => $pipeline->upcoming(),
        ]);
    }

    public function notifications()
    {
        return view('end-user.notifications', [
            'notifications' => SystemNotification::forUser(Auth::id(), 50),
        ]);
    }

    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q', ''));

        $requests = ProcurementRequest::forOffice(Auth::user()->office)
            ->when(array_key_exists((string) $status, ProcurementRequest::STATUSES), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('title', 'like', '%'.$search.'%')
                ->orWhere('reference_no', 'like', '%'.$search.'%')))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('end-user.requests.index', compact('requests', 'status', 'search'));
    }

    public function create()
    {
        return view('end-user.requests.form', ['procurementRequest' => new ProcurementRequest(['unit' => 'lot'])]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        $submit = $request->input('action') === 'submit';

        $procurementRequest = DB::transaction(function () use ($validated, $request) {
            $procurementRequest = ProcurementRequest::create($this->attributes($validated) + [
                'reference_no' => ProcurementRequest::nextReferenceNo(),
                'end_user_office' => Auth::user()->office,
                'requested_by' => Auth::id(),
                'status' => ProcurementRequest::STATUS_DRAFT,
            ]);

            $this->storeDocuments($procurementRequest, $request);

            return $procurementRequest;
        });

        AuditLog::log('procurement_request_created', $procurementRequest, null, ['reference_no' => $procurementRequest->reference_no]);

        if ($submit) {
            $this->markSubmitted($procurementRequest);

            return redirect()->route('end-user.requests.show', $procurementRequest)
                ->with('success', 'Request '.$procurementRequest->reference_no.' submitted for PPMP/APP review.');
        }

        return redirect()->route('end-user.requests.show', $procurementRequest)
            ->with('success', 'Draft '.$procurementRequest->reference_no.' saved. Submit it when it is complete.');
    }

    public function show(ProcurementRequest $procurementRequest)
    {
        $this->authorizeOffice($procurementRequest);
        $procurementRequest->load(['documents', 'requester', 'reviewer', 'project']);

        return view('end-user.requests.show', [
            'procurementRequest' => $procurementRequest,
            'timeline' => ProcurementTimeline::forRequest($procurementRequest),
        ]);
    }

    public function edit(ProcurementRequest $procurementRequest)
    {
        $this->authorizeOffice($procurementRequest);
        abort_unless($procurementRequest->isEditable(), 403, 'This request can no longer be changed.');

        return view('end-user.requests.form', ['procurementRequest' => $procurementRequest->load('documents')]);
    }

    public function update(Request $request, ProcurementRequest $procurementRequest)
    {
        $this->authorizeOffice($procurementRequest);
        abort_unless($procurementRequest->isEditable(), 403, 'This request can no longer be changed.');

        $validated = $this->validateRequest($request);
        $before = $procurementRequest->only(array_keys($this->attributes($validated)));

        DB::transaction(function () use ($procurementRequest, $validated, $request) {
            $procurementRequest->update($this->attributes($validated));
            $this->storeDocuments($procurementRequest, $request);
        });

        AuditLog::log('procurement_request_updated', $procurementRequest, $before, $procurementRequest->only(array_keys($before)));

        if ($request->input('action') === 'submit') {
            $this->markSubmitted($procurementRequest);

            return redirect()->route('end-user.requests.show', $procurementRequest)
                ->with('success', 'Request '.$procurementRequest->reference_no.' submitted for PPMP/APP review.');
        }

        return redirect()->route('end-user.requests.show', $procurementRequest)->with('success', 'Changes saved.');
    }

    public function submit(ProcurementRequest $procurementRequest)
    {
        $this->authorizeOffice($procurementRequest);

        if (! $procurementRequest->isEditable()) {
            return back()->withErrors(['request' => 'This request was already submitted.']);
        }

        // A draft can be incomplete; the review needs every detail.
        $missing = $procurementRequest->missingForSubmission();
        if ($missing !== []) {
            return redirect()->route('end-user.requests.edit', $procurementRequest)->withErrors(
                collect($missing)->map(fn (string $label) => $label.' is required before submitting.')->all()
            );
        }

        $this->markSubmitted($procurementRequest);

        return redirect()->route('end-user.requests.show', $procurementRequest)
            ->with('success', 'Request '.$procurementRequest->reference_no.' submitted for PPMP/APP review.');
    }

    public function destroyDocument(ProcurementRequest $procurementRequest, ProcurementRequestDocument $document)
    {
        $this->authorizeOffice($procurementRequest);
        abort_unless($document->procurement_request_id === $procurementRequest->id, 404);
        abort_unless($procurementRequest->isEditable(), 403, 'This request can no longer be changed.');

        Uploads::delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Attachment removed.');
    }

    /* ---------------------------------------------------------------------
     | PPMP/APP and budget review (staff and admin)
     * ------------------------------------------------------------------- */

    public function queue(Request $request)
    {
        $role = $this->role($request);
        $tab = in_array($request->query('tab'), ['review', 'bac', 'returned', 'procurement', 'all'], true) ? $request->query('tab') : 'review';
        $search = trim((string) $request->query('q', ''));

        $statuses = match ($tab) {
            'review' => [ProcurementRequest::STATUS_SUBMITTED],
            'bac' => [ProcurementRequest::STATUS_FORWARDED],
            'returned' => [ProcurementRequest::STATUS_RETURNED, ProcurementRequest::STATUS_REJECTED],
            'procurement' => [ProcurementRequest::STATUS_IN_PROCUREMENT],
            default => array_values(array_diff(array_keys(ProcurementRequest::STATUSES), [ProcurementRequest::STATUS_DRAFT])),
        };

        // The search also narrows the tab counts, so a search shows which queue holds the request.
        $matchesSearch = fn ($query) => $query->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
            ->where('title', 'like', '%'.$search.'%')
            ->orWhere('reference_no', 'like', '%'.$search.'%')
            ->orWhere('end_user_office', 'like', '%'.$search.'%')));

        $requests = ProcurementRequest::with(['project', 'requester', 'budgetConfirmer', 'documents'])
            ->whereIn('status', $statuses)
            ->tap($matchesSearch)
            ->orderByRaw('submitted_at is null')
            ->orderBy('submitted_at')
            ->paginate(15)
            ->withQueryString();

        $counts = ProcurementRequest::query()
            ->tap($matchesSearch)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('procurement.requests', [
            'routePrefix' => $role,
            'requests' => $requests,
            'tab' => $tab,
            'search' => $search,
            // Requests are filed by end-user office accounts; say so when there are none.
            'officeAccounts' => User::where('role', 'end_user')->where('status', 'active')->count(),
            'drafts' => (int) ProcurementRequest::where('status', ProcurementRequest::STATUS_DRAFT)->count(),
            'counts' => [
                'review' => (int) ($counts[ProcurementRequest::STATUS_SUBMITTED] ?? 0),
                'bac' => (int) ($counts[ProcurementRequest::STATUS_FORWARDED] ?? 0),
                'returned' => (int) (($counts[ProcurementRequest::STATUS_RETURNED] ?? 0) + ($counts[ProcurementRequest::STATUS_REJECTED] ?? 0)),
                'procurement' => (int) ($counts[ProcurementRequest::STATUS_IN_PROCUREMENT] ?? 0),
            ],
        ]);
    }

    public function review(Request $request, ProcurementRequest $procurementRequest)
    {
        $role = $this->role($request);

        if (! $procurementRequest->awaitsReview()) {
            return back()->withErrors(['decision' => 'Only a request waiting for PPMP/APP review can be reviewed.']);
        }

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['forward', 'return', 'reject'])],
            'ppmp_reference' => ['nullable', 'required_if:decision,forward', 'string', 'max:255'],
            'app_reference' => ['nullable', 'required_if:decision,forward', 'string', 'max:255'],
            'budget_available' => ['nullable', 'boolean'],
            'review_remarks' => ['nullable', Rule::requiredIf(in_array($request->input('decision'), ['return', 'reject'], true)), 'string', 'max:2000'],
        ], [
            'ppmp_reference.required_if' => 'Enter the PPMP reference that covers this request.',
            'app_reference.required_if' => 'Enter the APP reference that covers this request.',
            'review_remarks.required' => 'Tell the end-user office why the request is returned or not approved.',
        ]);

        if ($validated['decision'] === 'forward' && ! ($validated['budget_available'] ?? false)) {
            throw ValidationException::withMessages(['budget_available' => 'Confirm that the budget is available before forwarding to the BAC.']);
        }

        // Budget availability is only ever set by the reviewer ticking the box; record who and when.
        $budgetConfirmed = (bool) ($validated['budget_available'] ?? false);

        $before = $procurementRequest->only(['status', 'ppmp_reference', 'app_reference', 'budget_available', 'budget_confirmed_by', 'budget_confirmed_at', 'review_remarks']);
        $status = match ($validated['decision']) {
            'forward' => ProcurementRequest::STATUS_FORWARDED,
            'return' => ProcurementRequest::STATUS_RETURNED,
            'reject' => ProcurementRequest::STATUS_REJECTED,
        };

        $procurementRequest->update([
            'status' => $status,
            'ppmp_reference' => $validated['ppmp_reference'] ?? $procurementRequest->ppmp_reference,
            'app_reference' => $validated['app_reference'] ?? $procurementRequest->app_reference,
            'budget_available' => $budgetConfirmed,
            'budget_confirmed_by' => $budgetConfirmed ? Auth::id() : null,
            'budget_confirmed_at' => $budgetConfirmed ? now() : null,
            'review_remarks' => $validated['review_remarks'] ?? null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'forwarded_at' => $validated['decision'] === 'forward' ? now() : null,
        ]);

        AuditLog::log('procurement_request_reviewed', $procurementRequest, $before, $procurementRequest->only(array_keys($before)));

        $label = $procurementRequest->reference_no.' ('.$procurementRequest->title.')';
        $officeUsers = User::where('role', 'end_user')->where('office', $procurementRequest->end_user_office)->pluck('id');
        $url = fn () => route('end-user.requests.show', $procurementRequest);

        match ($validated['decision']) {
            'forward' => [
                SystemNotification::createForUsers($officeUsers, 'Request forwarded to the BAC', $label.' passed the PPMP/APP and budget review and was forwarded to the BAC.', 'procurement_request', ['url' => $url()]),
                SystemNotification::createForRole('admin', 'Procurement request for the BAC', $label.' from '.$procurementRequest->end_user_office.' is ready for the preparation of bidding documents.', 'procurement_request', ['url' => route('admin.requests', ['tab' => 'bac'])]),
            ],
            'return' => SystemNotification::createForUsers($officeUsers, 'Request returned for correction', $label.': '.$validated['review_remarks'], 'procurement_request', ['url' => $url()]),
            'reject' => SystemNotification::createForUsers($officeUsers, 'Request not approved', $label.': '.$validated['review_remarks'], 'procurement_request', ['url' => $url()]),
        };

        $message = match ($validated['decision']) {
            'forward' => 'Forwarded '.$procurementRequest->reference_no.' to the BAC.',
            'return' => 'Returned '.$procurementRequest->reference_no.' to the end-user office.',
            'reject' => 'Marked '.$procurementRequest->reference_no.' as not approved.',
        };

        return redirect()->route($role.'.requests', ['tab' => 'review'])->with('success', $message);
    }

    /* ---------------------------------------------------------------------
     | Helpers
     * ------------------------------------------------------------------- */

    private function markSubmitted(ProcurementRequest $procurementRequest): void
    {
        $procurementRequest->update([
            'status' => ProcurementRequest::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'review_remarks' => null,
        ]);

        AuditLog::log('procurement_request_submitted', $procurementRequest, null, ['status' => ProcurementRequest::STATUS_SUBMITTED]);

        $message = $procurementRequest->end_user_office.' submitted '.$procurementRequest->reference_no.' ('.$procurementRequest->title.') for PPMP/APP and funds review.';
        $queue = ['tab' => 'review', 'q' => $procurementRequest->reference_no];
        SystemNotification::createForRole('staff', 'Purchase request for PPMP/APP review', $message, 'procurement_request', ['url' => route('staff.requests', $queue)]);
        SystemNotification::createForRole('admin', 'Purchase request for PPMP/APP review', $message, 'procurement_request', ['url' => route('admin.requests', $queue)]);
    }

    /**
     * A draft needs only its title; submitting for the PPMP/APP and funds
     * review needs every detail.
     */
    private function validateRequest(Request $request): array
    {
        $required = $request->input('action') === 'submit' ? 'required' : 'nullable';

        // The cost field shows thousands separators (e.g. 500,000.00).
        if (is_string($request->input('estimated_cost'))) {
            $request->merge(['estimated_cost' => str_replace([',', '₱', ' '], '', $request->input('estimated_cost'))]);
        }

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => [$required, Rule::in(['goods', 'services', 'infrastructure', 'consultancy'])],
            'specifications' => [$required, 'string', 'max:20000'],
            'quantity' => [$required, 'numeric', 'gt:0', 'lte:9999999999999'],
            'unit' => [$required, 'string', 'max:40'],
            'estimated_cost' => [$required, 'numeric', 'gt:0', 'lte:9999999999999.99'],
            'fund_source' => [$required, 'string', 'max:255'],
            'delivery_period' => [$required, 'string', 'max:255'],
            'justification' => ['nullable', 'string', 'max:5000'],
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:20480'],
            'document_types' => ['nullable', 'array'],
            'document_types.*' => ['nullable', Rule::in(array_keys(ProcurementRequest::DOCUMENT_TYPES))],
            'action' => ['nullable', Rule::in(['draft', 'submit'])],
        ], [
            'specifications.required' => 'Describe the technical specifications or terms of reference.',
            'estimated_cost.gt' => 'The estimated total cost must be greater than zero.',
            'quantity.gt' => 'The quantity must be greater than zero.',
        ], [
            'estimated_cost' => 'estimated total cost',
            'fund_source' => 'source of funds',
            'delivery_period' => 'delivery or contract duration',
        ]);
    }

    private function attributes(array $validated): array
    {
        $text = fn (string $field) => filled($validated[$field] ?? null) ? trim((string) $validated[$field]) : null;

        return [
            'title' => trim($validated['title']),
            'category' => $validated['category'] ?? null,
            'specifications' => $text('specifications'),
            'quantity' => filled($validated['quantity'] ?? null) ? $validated['quantity'] : null,
            'unit' => $text('unit'),
            'estimated_cost' => filled($validated['estimated_cost'] ?? null) ? number_format((float) $validated['estimated_cost'], 2, '.', '') : null,
            'fund_source' => $text('fund_source'),
            'delivery_period' => $text('delivery_period'),
            'justification' => $text('justification'),
        ];
    }

    private function storeDocuments(ProcurementRequest $procurementRequest, Request $request): void
    {
        $types = $request->input('document_types', []);

        foreach ($request->file('documents', []) as $index => $file) {
            $type = $types[$index] ?? 'other';
            $path = Uploads::store(
                $file,
                'procurement-requests/'.$procurementRequest->id,
                $type.'_'.bin2hex(random_bytes(6)).'.'.strtolower($file->getClientOriginalExtension())
            );

            $procurementRequest->documents()->create([
                'document_type' => array_key_exists($type, ProcurementRequest::DOCUMENT_TYPES) ? $type : 'other',
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'uploaded_by' => Auth::id(),
            ]);
        }
    }

    private function authorizeOffice(ProcurementRequest $procurementRequest): void
    {
        abort_unless($procurementRequest->end_user_office === Auth::user()->office, 404);
    }

    private function role(Request $request): string
    {
        return $request->routeIs('staff.*') ? 'staff' : 'admin';
    }
}
