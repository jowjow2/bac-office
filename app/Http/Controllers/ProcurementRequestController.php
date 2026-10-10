<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestDocument;
use App\Models\Project;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\ProcurementPipeline;
use App\Support\ProcurementTimeline;
use App\Support\SystemNotification;
use App\Support\Uploads;
use App\Support\VercelBlob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Procurement requests: recorded by the BAC admin from an office's signed hard copy, reviewed against the
 * PPMP/APP and budget by authorized staff (or the admin), then forwarded to
 * the BAC, which prepares the bidding project from it.
 */
class ProcurementRequestController extends Controller
{
    /* ---------------------------------------------------------------------
     | End-user office
     * ------------------------------------------------------------------- */

    /** The Purchase Request form to print and sign (the paper copy for the records). */
    public function printForm(ProcurementRequest $procurementRequest)
    {
        return view('procurement.request-print', [
            'procurementRequest' => $procurementRequest->load('requester'),
            'rows' => $procurementRequest->itemRows(),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Recording a purchase request (admin)
     |
     | End-user offices hand the BAC a signed hard copy; the admin records it
     | here for its office. It goes straight to the PPMP/APP review and has
     | no individual owner, so every account of that office can follow it.
     * ------------------------------------------------------------------- */

    /** The queue, with the recording form open as a dialog over it. */
    public function adminCreate(Request $request)
    {
        return $this->queue($request)->with([
            'recordForm' => true,
            'procurementRequest' => new ProcurementRequest(['unit' => 'lot']),
            'adminMode' => true,
            'offices' => User::assignableEndUserOffices(),
        ]);
    }

    public function adminStore(Request $request)
    {
        // A recorded request is complete: it is checked like a submitted one.
        $request->merge(['action' => 'submit']);
        $validated = $this->validateRequest($request);
        $request->validate([
            'end_user_office' => ['required', 'string', Rule::in(User::assignableEndUserOffices())],
        ], [
            'end_user_office.required' => 'Choose the end-user office that filed the request.',
            'end_user_office.in' => 'Choose an office from the list.',
        ]);

        try {
            $procurementRequest = DB::transaction(function () use ($validated, $request) {
                $procurementRequest = ProcurementRequest::create($this->attributes($validated) + [
                    'reference_no' => ProcurementRequest::nextReferenceNo(),
                    'end_user_office' => (string) $request->input('end_user_office'),
                    'requested_by' => null,
                    'status' => ProcurementRequest::STATUS_DRAFT,
                ]);

                $this->storeDocuments($procurementRequest, $request);

                return $procurementRequest;
            });
        } catch (\RuntimeException $exception) {
            return $this->uploadFailed($request, $exception);
        }

        AuditLog::log('procurement_request_recorded', $procurementRequest, null, ['reference_no' => $procurementRequest->reference_no, 'office' => $procurementRequest->end_user_office, 'recorded_by' => Auth::id()]);
        // The BAC admin records it, so there is no one to forward it to: it is ready to become a project.
        $procurementRequest->update([
            'status' => ProcurementRequest::STATUS_FORWARDED,
            'submitted_at' => now(),
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'forwarded_at' => now(),
            'review_remarks' => null,
        ]);
        AuditLog::log('procurement_request_forwarded', $procurementRequest, null, ['status' => ProcurementRequest::STATUS_FORWARDED, 'recorded_by' => Auth::id()]);

        return redirect()->route('admin.requests', ['tab' => 'bac'])
            ->with('success', 'Recorded '.$procurementRequest->reference_no.' for '.$procurementRequest->end_user_office.'. It is ready: use Prepare procurement to turn it into a project.');
    }

    /** A recorded request can be corrected until a project has been made from it. */
    private function editable(ProcurementRequest $procurementRequest): bool
    {
        return $procurementRequest->status !== ProcurementRequest::STATUS_IN_PROCUREMENT && ! $procurementRequest->project()->exists();
    }

    public function adminEdit(Request $request, ProcurementRequest $procurementRequest)
    {
        if (! $this->editable($procurementRequest)) {
            return redirect()->route('admin.requests', ['tab' => 'all'])
                ->with('error', $procurementRequest->reference_no.' already has a procurement project, so it can no longer be edited here. Edit the project instead.');
        }

        $procurementRequest->load('documents');

        return $this->queue($request)->with([
            'recordForm' => true,
            'procurementRequest' => $procurementRequest,
            'adminMode' => true,
            'offices' => User::assignableEndUserOffices(),
        ]);
    }

    public function adminUpdate(Request $request, ProcurementRequest $procurementRequest)
    {
        if (! $this->editable($procurementRequest)) {
            return redirect()->route('admin.requests', ['tab' => 'all'])
                ->with('error', $procurementRequest->reference_no.' already has a procurement project, so it can no longer be edited here.');
        }

        $request->merge(['action' => 'submit']);
        $validated = $this->validateRequest($request);
        $request->validate([
            'end_user_office' => ['required', 'string', Rule::in(User::assignableEndUserOffices())],
            'remove_documents' => ['nullable', 'array'],
            'remove_documents.*' => ['integer'],
        ], [
            'end_user_office.required' => 'Choose the end-user office that filed the request.',
            'end_user_office.in' => 'Choose an office from the list.',
        ]);

        $fields = ['title', 'category', 'specifications', 'quantity', 'unit', 'estimated_cost', 'fund_source', 'delivery_period', 'justification', 'end_user_office', 'items'];
        $before = $procurementRequest->only($fields);
        $removed = $procurementRequest->documents()->whereIn('id', array_map('intval', (array) $request->input('remove_documents', [])))->get();
        $removedPaths = $removed->pluck('file_path')->filter()->all();

        try {
            DB::transaction(function () use ($procurementRequest, $validated, $request, $removed) {
                $procurementRequest->update($this->attributes($validated) + ['end_user_office' => (string) $request->input('end_user_office')]);
                $removed->each->delete();
                $this->storeDocuments($procurementRequest, $request);
            });
        } catch (\RuntimeException $exception) {
            return $this->uploadFailed($request, $exception);
        }

        // Files go only after the records changed, so a failed save keeps them.
        foreach ($removedPaths as $path) {
            try {
                Uploads::delete($path);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        AuditLog::log('procurement_request_updated', $procurementRequest, $before, $procurementRequest->fresh()->only($fields) + ['edited_by' => Auth::id(), 'removed_documents' => $removed->pluck('original_name')->all()]);

        return redirect()->route('admin.requests', ['tab' => $procurementRequest->awaitsBac() ? 'bac' : 'all', 'q' => $procurementRequest->reference_no])
            ->with('success', 'Saved the changes to '.$procurementRequest->reference_no.'.');
    }

    /* ---------------------------------------------------------------------
     | PPMP/APP and budget review (staff and admin)
     * ------------------------------------------------------------------- */

    public function queue(Request $request)
    {
        $role = $this->role($request);
        $search = trim((string) $request->query('q', ''));

        // The search also narrows the tab counts, so a search shows which queue holds the request.
        $matchesSearch = fn ($query) => $query->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
            ->where('title', 'like', '%'.$search.'%')
            ->orWhere('reference_no', 'like', '%'.$search.'%')
            ->orWhere('end_user_office', 'like', '%'.$search.'%')));

        $counts = ProcurementRequest::query()
            ->tap($matchesSearch)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $tabCounts = [
            'review' => (int) ($counts[ProcurementRequest::STATUS_SUBMITTED] ?? 0),
            'bac' => (int) ($counts[ProcurementRequest::STATUS_FORWARDED] ?? 0),
            'returned' => (int) (($counts[ProcurementRequest::STATUS_RETURNED] ?? 0) + ($counts[ProcurementRequest::STATUS_REJECTED] ?? 0)),
            'procurement' => (int) ($counts[ProcurementRequest::STATUS_IN_PROCUREMENT] ?? 0),
        ];

        // Without a chosen tab, open the first queue that has something waiting.
        $tab = $request->query('tab');
        if (! in_array($tab, ['review', 'bac', 'returned', 'procurement', 'all'], true)) {
            $tab = collect(['review', 'bac', 'returned'])->first(fn ($key) => $tabCounts[$key] > 0) ?? 'all';
        }

        $statuses = match ($tab) {
            'review' => [ProcurementRequest::STATUS_SUBMITTED],
            'bac' => [ProcurementRequest::STATUS_FORWARDED],
            'returned' => [ProcurementRequest::STATUS_RETURNED, ProcurementRequest::STATUS_REJECTED],
            'procurement' => [ProcurementRequest::STATUS_IN_PROCUREMENT],
            default => array_values(array_diff(array_keys(ProcurementRequest::STATUSES), [ProcurementRequest::STATUS_DRAFT])),
        };

        $requests = ProcurementRequest::with(['project', 'requester', 'budgetConfirmer', 'documents'])
            ->whereIn('status', $statuses)
            ->tap($matchesSearch)
            ->orderByRaw('submitted_at is null')
            ->orderBy('submitted_at')
            ->paginate(15)
            ->withQueryString();
        return view('procurement.requests', [
            'routePrefix' => $role,
            'requests' => $requests,
            'tab' => $tab,
            'search' => $search,
            'drafts' => (int) ProcurementRequest::where('status', ProcurementRequest::STATUS_DRAFT)->count(),
            'counts' => $tabCounts,
        ]);
    }

    /**
     * The BAC admin removes a purchase request filed by mistake or as a test.
     * Never one that already has a procurement project. The reason is kept in
     * the audit log with a copy of the request, and the filing office is told.
     */
    public function destroy(Request $request, ProcurementRequest $procurementRequest)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'reason.required' => 'Enter why this purchase request is being deleted.',
        ]);

        if ($procurementRequest->status === ProcurementRequest::STATUS_IN_PROCUREMENT || $procurementRequest->project()->exists()) {
            return back()->with('error', $procurementRequest->reference_no.' already has a procurement project, so it cannot be deleted. Delete or archive the project first.');
        }

        $procurementRequest->load('documents');
        $reference = $procurementRequest->reference_no;
        $snapshot = $procurementRequest->only(['reference_no', 'title', 'end_user_office', 'category', 'estimated_cost', 'status', 'submitted_at', 'requested_by'])
            + ['documents' => $procurementRequest->documents->pluck('original_name')->all()];
        $files = $procurementRequest->documents->pluck('file_path')->filter()->all();

        DB::transaction(function () use ($procurementRequest, $snapshot, $validated) {
            AuditLog::log('procurement_request_deleted', $procurementRequest, $snapshot, ['reason' => trim($validated['reason'])]);
            $procurementRequest->documents()->delete();
            $procurementRequest->delete();
        });

        // Files go only after the records are gone, so a failed delete keeps them.
        foreach ($files as $path) {
            try {
                Uploads::delete($path);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return redirect()->route('admin.requests', array_filter(['tab' => $request->input('tab')]))
            ->with('success', $reference.' was deleted. The reason is kept in the audit log.');
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

        $adminUrl = route('admin.requests', ['tab' => $validated['decision'] === 'forward' ? 'bac' : 'all', 'q' => $procurementRequest->reference_no]);
        match ($validated['decision']) {
            'forward' => SystemNotification::createForRole('admin', 'Procurement request for the BAC', $label.' from '.$procurementRequest->end_user_office.' is ready for the preparation of bidding documents.', 'procurement_request', ['url' => $adminUrl]),
            'return' => SystemNotification::createForRole('admin', 'Request returned to the office', $label.' was returned: '.$validated['review_remarks'].' Ask '.$procurementRequest->end_user_office.' for a corrected hard copy.', 'procurement_request', ['url' => $adminUrl]),
            'reject' => SystemNotification::createForRole('admin', 'Request not approved', $label.' was not approved: '.$validated['review_remarks'], 'procurement_request', ['url' => $adminUrl]),
        };

        $message = match ($validated['decision']) {
            'forward' => 'Forwarded '.$procurementRequest->reference_no.' to the BAC.',
            'return' => 'Returned '.$procurementRequest->reference_no.' with your remarks. The BAC admin is told.',
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
        try {
            SystemNotification::createForRole('staff', 'Purchase request for PPMP/APP review', $message, 'procurement_request', ['url' => route('staff.requests', $queue)]);
            SystemNotification::createForRole('admin', 'Purchase request for PPMP/APP review', $message, 'procurement_request', ['url' => route('admin.requests', $queue)]);
        } catch (Throwable $exception) {
            // The request is already submitted; a notification error must not turn it into a failed form submission.
            Log::warning('Purchase request submitted, but a review notification failed.', [
                'request_id' => $procurementRequest->id,
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * A draft needs only its title; submitting for the PPMP/APP and funds
     * review needs every detail.
     */
    private function validateRequest(Request $request): array
    {
        $required = $request->input('action') === 'submit' ? 'required' : 'nullable';

        if ($request->has('items')) {
            return $this->validateItemizedRequest($request, $required);
        }

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

    /**
     * The form lists items (description, quantity, unit, estimated unit cost);
     * the request's quantity, unit and estimated total cost are derived from
     * them. Rows left completely empty are ignored.
     */
    private function validateItemizedRequest(Request $request, string $required): array
    {
        $number = fn ($value) => is_string($value) ? str_replace([',', '₱', ' '], '', $value) : $value;
        $items = collect((array) $request->input('items', []))
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => [
                'description' => trim((string) ($item['description'] ?? '')),
                'quantity' => $number($item['quantity'] ?? ''),
                'unit' => trim((string) ($item['unit'] ?? '')),
                'unit_cost' => $number($item['unit_cost'] ?? ''),
            ])
            ->reject(fn (array $item) => $item['description'] === '' && (string) $item['quantity'] === '' && $item['unit'] === '' && (string) $item['unit_cost'] === '')
            ->values()
            ->all();
        $request->merge(['items' => $items]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => [$required, Rule::in(['goods', 'services', 'infrastructure', 'consultancy'])],
            'specifications' => [$required, 'string', 'max:20000'],
            'items' => [$required, 'array', $required === 'required' ? 'min:1' : 'min:0', 'max:100'],
            'items.*.description' => [$required, 'string', 'max:500'],
            'items.*.quantity' => [$required, 'numeric', 'gt:0', 'lte:9999999999999'],
            'items.*.unit' => [$required, 'string', 'max:40'],
            'items.*.unit_cost' => [$required, 'numeric', 'min:0', 'lte:9999999999999.99'],
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
            'items.required' => 'Add at least one item to procure.',
            'items.min' => 'Add at least one item to procure.',
            'items.*.description.required' => 'Describe item :position.',
            'items.*.quantity.required' => 'Enter the quantity of item :position.',
            'items.*.quantity.gt' => 'The quantity of item :position must be greater than zero.',
            'items.*.quantity.numeric' => 'The quantity of item :position must be a number.',
            'items.*.unit.required' => 'Enter the unit of item :position (e.g. piece, box, lot).',
            'items.*.unit_cost.required' => 'Enter the estimated unit cost of item :position.',
            'items.*.unit_cost.numeric' => 'The unit cost of item :position must be an amount in pesos.',
            'items.*.unit_cost.min' => 'The unit cost of item :position cannot be negative.',
        ], [
            'fund_source' => 'source of funds',
            'delivery_period' => 'delivery or contract duration',
        ]);

        $items = array_map(fn (array $item) => [
            'description' => $item['description'] ?? '',
            'quantity' => isset($item['quantity']) && $item['quantity'] !== '' ? round((float) $item['quantity'], 2) : null,
            'unit' => $item['unit'] ?? '',
            'unit_cost' => isset($item['unit_cost']) && $item['unit_cost'] !== '' ? round((float) $item['unit_cost'], 2) : null,
        ], $validated['items'] ?? []);
        $total = round(array_sum(array_map(fn (array $item) => (float) $item['quantity'] * (float) $item['unit_cost'], $items)), 2);

        if ($required === 'required' && $total <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'The estimated total cost must be greater than zero. Enter the unit cost of each item.']);
        }

        // One item keeps its own quantity and unit; several are one lot.
        $single = count($items) === 1 ? $items[0] : null;
        $validated['items'] = $items;
        $validated['quantity'] = $items === [] ? null : ($single ? $single['quantity'] : 1);
        $validated['unit'] = $items === [] ? null : ($single ? $single['unit'] : 'lot');
        $validated['estimated_cost'] = $items === [] ? null : $total;

        return $validated;
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
        ] + (array_key_exists('items', $validated) && ProcurementRequest::storesItems()
            ? ['items' => $validated['items'] === [] ? null : $validated['items']]
            : []);
    }

    /** Nothing was saved (the transaction rolled back): back to the form with what was typed. */
    private function uploadFailed(Request $request, \RuntimeException $exception)
    {
        report($exception);

        return back()
            ->withInput($request->except('documents'))
            ->withErrors(['documents' => 'The attachment could not be uploaded right now, so nothing was saved. Try again in a few minutes, or save without the attachment and add it later.']);
    }

    private function storeDocuments(ProcurementRequest $procurementRequest, Request $request): void
    {
        $types = $request->input('document_types', []);

        foreach ($request->file('documents', []) as $index => $file) {
            $type = $types[$index] ?? 'other';
            $directory = 'procurement-requests/'.$procurementRequest->id;
            $filename = $type.'_'.bin2hex(random_bytes(6)).'.'.strtolower($file->getClientOriginalExtension());
            $path = VercelBlob::enabled()
                ? VercelBlob::store($file, $directory, $filename)
                : Uploads::store($file, $directory, $filename);

            $procurementRequest->documents()->create([
                'document_type' => array_key_exists($type, ProcurementRequest::DOCUMENT_TYPES) ? $type : 'other',
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'uploaded_by' => Auth::id(),
            ]);
        }
    }

    private function role(Request $request): string
    {
        return $request->routeIs('staff.*') ? 'staff' : 'admin';
    }
}
