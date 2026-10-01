<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Award;
use App\Models\ContractImplementation;
use App\Models\ContractImplementationEvent;
use App\Support\ContractImplementationWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ContractImplementationController extends Controller
{
    public function __construct(private readonly ContractImplementationWorkflow $workflow) {}

    public function configure(Request $request, Award $award)
    {
        $this->authorizeLgu($award);
        $validated = $request->validate([
            'delivery_deadline' => ['required', 'date'],
            'delivery_location' => ['required', 'string', 'max:255'],
            'signed_contract_reference' => ['required', 'string', 'max:255'],
            'contract_items' => ['required', 'array', 'min:1', 'max:100'],
            'contract_items.*.description' => ['required', 'string', 'max:255'],
            'contract_items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'contract_items.*.unit' => ['required', 'string', 'max:50'],
            // Unit prices from the signed contract: the basis of liquidated damages (IRR Sec. 71.1.4).
            'contract_items.*.unit_price' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            ...self::warrantyRules(true),
            'remarks' => ['required', 'string', 'max:2000'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:20480'],
        ], [
            'contract_items.*.unit_price.required' => 'Enter the unit price of every item from the signed contract.',
        ]);

        $items = collect($validated['contract_items'])->values()->map(fn ($item) => [
            'description' => trim($item['description']),
            'quantity' => (float) $item['quantity'],
            'unit' => trim($item['unit']),
            'unit_price' => round((float) $item['unit_price'], 2),
        ])->all();
        $validated['contract_items'] = $items;
        $this->workflow->configure($award, Auth::user(), $validated, $request->file('document'));

        return $this->back($request, $award, 'Delivery terms from the signed contract were recorded.');
    }

    public function supplierDelivery(Request $request, Award $award)
    {
        abort_unless(Auth::user()?->role === 'bidder' && (int) $award->bid?->user_id === (int) Auth::id(), 403);
        $validated = $request->validate([
            'delivered_on' => ['required', 'date'],
            'delivery_reference' => ['required', 'string', 'max:100'],
            'remarks' => ['required', 'string', 'max:2000'],
            'delivered_quantities' => ['required', 'array', 'min:1', 'max:100'],
            'delivered_quantities.*' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:20480'],
        ]);
        $implementation = $this->workflow->ensure($award);
        $correction = $implementation->status === ContractImplementation::FOR_CORRECTION;
        $this->workflow->supplierDelivery($award, Auth::user(), $validated, $request->file('document'), $correction);

        return redirect()->route('bidder.awarded-contracts', ['award' => $award->id, 'contract' => $award->id])->with('success', 'Delivery details submitted for LGU receipt and inspection.');
    }

    public function action(Request $request, Award $award)
    {
        $this->authorizeLgu($award);
        $action = $request->input('action');
        $allowed = ['extend', 'receive', 'inspect', 'payment_processing', 'paid', 'complete'];
        abort_unless(in_array($action, $allowed, true), 422);

        $common = [
            'remarks' => ['required', 'string', 'max:2000'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:20480'],
        ];

        if ($action === 'extend') {
            $validated = $request->validate($common + [
                'new_deadline' => ['required', 'date'],
                'requested_on' => ['required', 'date'],
                'approved_on' => ['required', 'date'],
                'approval_reference' => ['required', 'string', 'max:255'],
                'keeps_liquidated_damages' => ['nullable', 'boolean'],
            ]);
            $this->workflow->extendDeadline($award, Auth::user(), $validated, $request->file('document'));
        } elseif ($action === 'receive') {
            $validated = $request->validate($common + [
                'actual_received_on' => ['required', 'date'],
                'received_quantities' => ['required', 'array', 'min:1', 'max:100'],
                'received_quantities.*' => ['required', 'numeric', 'min:0', 'max:999999999'],
            ]);
            $this->workflow->recordReceipt($award, Auth::user(), $validated, $request->file('document'));
        } elseif ($action === 'inspect') {
            $validated = $request->validate($common + [
                'outcome' => ['required', Rule::in(['accepted', 'for_correction'])],
                'inspected_on' => ['required', 'date'],
                'iar_number' => ['required', 'string', 'max:100'],
                'inspection_findings' => ['required', 'string', 'max:5000'],
                'accepted_quantities' => ['required', 'array', 'min:1', 'max:100'],
                'accepted_quantities.*' => ['required', 'numeric', 'min:0', 'max:999999999'],
                'deficiencies' => ['nullable', 'string', 'max:5000'],
                'correction_request' => ['required_if:outcome,for_correction', 'nullable', 'string', 'max:5000'],
            ]);
            $this->workflow->inspect($award, Auth::user(), $validated, $request->file('document'));
        } elseif ($action === 'complete') {
            $validated = $request->validate($common + [
                'warranty_released_on' => ['required', 'date'],
                'no_defects' => ['accepted'],
                'consumed' => ['nullable', 'boolean'],
                ...self::warrantyRules(false),
            ], [
                'no_defects.accepted' => 'Confirm that the supplies are free from defects and every contract condition was met.',
            ]);
            $this->workflow->advance($award, Auth::user(), $action, $validated, $request->file('document'));
        } else {
            $validated = $request->validate($common);
            $this->workflow->advance($award, Auth::user(), $action, $validated, $request->file('document'));
        }

        return $this->back($request, $award, 'Contract implementation status updated.');
    }

    public function document(ContractImplementationEvent $event)
    {
        $event->loadMissing('implementation.award.project', 'implementation.award.bid');
        $implementation = $event->implementation;
        $award = $implementation?->award;
        abort_unless($award && $event->document_path && Storage::disk('local')->exists($event->document_path), 404);

        $user = Auth::user();
        $isWinner = $user?->role === 'bidder' && (int) $award->bid?->user_id === (int) $user->id;
        $isAdmin = $user?->role === 'admin';
        $isAssignedStaff = $user?->role === 'staff'
            && Assignment::where('staff_id', $user->id)->where('project_id', $award->project_id)->exists();
        abort_unless($isWinner || $isAdmin || $isAssignedStaff, 403);

        return Storage::disk('local')->download($event->document_path, $event->document_name ?: 'contract-implementation-document');
    }

    /** Warranty terms of the signed contract (IRR Sec. 90.1); the workflow checks the minimum period. */
    private static function warrantyRules(bool $required): array
    {
        $presence = $required ? 'required' : 'nullable';

        return [
            'supply_type' => [$presence, Rule::in(array_keys(ContractImplementation::SUPPLY_TYPES))],
            'warranty_months' => [$presence, 'integer', 'min:1', 'max:120'],
            'warranty_security' => [$presence, Rule::in(array_keys(ContractImplementation::WARRANTY_SECURITIES))],
            'warranty_percent' => ['nullable', 'numeric', 'min:1', 'max:5'],
        ];
    }

    private function authorizeLgu(Award $award): void
    {
        abort_unless($this->workflow->eligible($award), 404);
        $user = Auth::user();
        $allowed = $user?->role === 'admin'
            || ($user?->role === 'staff' && Assignment::where('staff_id', $user->id)->where('project_id', $award->project_id)->exists());
        abort_unless($allowed, 403);
    }

    private function back(Request $request, Award $award, string $message)
    {
        // ?contract= reopens this contract's modal on the page.
        if ($request->routeIs('admin.*')) {
            return redirect()->route('admin.awards.index', ['contract' => $award->id])->with('success', $message);
        }
        if ($request->routeIs('staff.*')) {
            return redirect()->route('staff.procurement.show', $award->project_id)->with('success', $message);
        }

        return redirect()->route('bidder.awarded-contracts', ['award' => $award->id, 'contract' => $award->id])->with('success', $message);
    }
}
