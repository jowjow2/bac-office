<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Award;
use App\Models\ContractImplementation;
use App\Models\ContractImplementationEvent;
use App\Models\Project;
use App\Support\InfrastructureImplementationWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class InfrastructureImplementationController extends Controller
{
    public function __construct(private readonly InfrastructureImplementationWorkflow $workflow) {}

    public function showAdmin(Award $award) { $this->authorizeLgu($award); return $this->show($award, 'admin'); }
    public function showStaff(Award $award) { $this->authorizeLgu($award); abort_unless(Auth::user()->role === 'staff', 403); return $this->show($award, 'staff'); }
    public function showBidder(Award $award) { $this->authorizeWinner($award); return $this->show($award, 'bidder'); }
    private function show(Award $award, string $mode) { $record = $this->workflow->ensure($award); $award->load(['project', 'bid.user']); return view('infrastructure-contracts.show', compact('award', 'record', 'mode')); }
    public function endUserIndex()
    {
        $user = Auth::user();
        $awards = Award::with(['project', 'bid.user', 'contractImplementation.events.actor'])
            ->whereHas('project', fn ($q) => $q->whereRaw('LOWER(category) = ?', ['infrastructure'])->where('end_user_unit', $user->office))
            ->whereHas('bid', fn ($q) => $q->whereNotNull('contract_signed_at')->whereNotNull('notice_to_proceed_at'))
            ->get()->filter(fn ($award) => $this->workflow->eligible($award));
        return view('infrastructure-contracts.index', compact('awards'));
    }

    public function configure(Request $request, Award $award)
    {
        $this->authorizeLgu($award);
        abort_unless(Auth::user()->role === 'admin' || Auth::user()->role === 'staff', 403);
        $data = $this->validatedTerms($request, true);
        $this->workflow->configure($award, Auth::user(), $data, $request->file('document'));
        return redirect()->route($request->routeIs('staff.*') ? 'staff.infrastructure.show' : 'admin.infrastructure.show', $award)->with('success', 'Infrastructure contract terms recorded from the signed contract.');
    }

    public function correctTerms(Request $request, Award $award)
    {
        $this->authorizeLgu($award);
        abort_unless(Auth::user()->role === 'admin', 403);
        $data = $this->validatedTerms($request, false);
        $this->workflow->correctTerms($award, Auth::user(), $data, $request->file('document'));
        return redirect()->route('admin.infrastructure.show', $award)->with('success', 'Infrastructure contract terms corrected. The change and its reason are in the activity history.');
    }

    private function validatedTerms(Request $request, bool $documentRequired): array
    {
        $data = $request->validate([
            'delivery_deadline' => ['required', 'date'],
            'delivery_location' => ['required', 'string', 'max:255'],
            'signed_contract_reference' => ['required', 'string', 'max:255'],
            'contract_items' => ['required', 'array', 'min:1', 'max:100'],
            'contract_items.*.description' => ['required', 'string', 'max:255'],
            'contract_items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'contract_items.*.unit' => ['required', 'string', 'max:50'],
            'remarks' => ['required', 'string', 'max:2000'],
            'document' => [$documentRequired ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:20480'],
        ]);
        $data['contract_items'] = collect($data['contract_items'])->map(fn ($row) => ['description' => trim($row['description']), 'quantity' => (float) $row['quantity'], 'unit' => trim($row['unit'])])->values()->all();

        return $data;
    }

    public function progress(Request $request, Award $award)
    {
        $this->authorizeWinner($award);
        $data = $request->validate(['progress_percent' => ['required', 'integer', 'min:0', 'max:100'], 'milestone' => ['required', 'string', 'max:255'], 'remarks' => ['required', 'string', 'max:2000'], 'request_inspection' => ['nullable', 'boolean'], 'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:20480']]);
        $record = $this->workflow->ensure($award);
        $this->workflow->progress($award, Auth::user(), $data, $request->file('document'), $record->status === ContractImplementation::INFRA_FOR_CORRECTION);
        return redirect()->route('bidder.awarded-contracts', ['award' => $award->id])->with('success', 'Infrastructure progress update submitted.');
    }

    public function inspect(Request $request, Award $award)
    {
        $this->authorizeEndUser($award);
        $data = $request->validate(['outcome' => ['required', Rule::in(['recommend_acceptance', 'correction'])], 'findings' => ['required', 'string', 'max:5000'], 'deficiencies' => ['nullable', 'string', 'max:5000'], 'remarks' => ['required', 'string', 'max:2000'], 'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:20480']]);
        if ($data['outcome'] === 'recommend_acceptance') $data['outcome'] = 'accepted';
        $this->workflow->inspect($award, Auth::user(), $data, $request->file('document'));
        return back()->with('success', 'Site inspection findings recorded and supplier notified.');
    }

    public function action(Request $request, Award $award)
    {
        $this->authorizeLgu($award);
        abort_unless(in_array(Auth::user()->role, ['admin', 'staff'], true), 403);
        $data = $request->validate(['action' => ['required', Rule::in(['accept', 'payment_processing', 'paid', 'complete'])], 'remarks' => ['required', 'string', 'max:2000'], 'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:20480']]);
        $this->workflow->action($award, Auth::user(), $data['action'], $data, $request->file('document'));
        return redirect()->route($request->routeIs('staff.*') ? 'staff.infrastructure.show' : 'admin.infrastructure.show', $award)->with('success', 'Infrastructure contract status updated; the winning supplier was notified.');
    }

    public function document(ContractImplementationEvent $event)
    {
        $event->loadMissing('implementation.award.project', 'implementation.award.bid');
        $award = $event->implementation?->award;
        abort_unless($award && $event->document_path && Storage::disk('local')->exists($event->document_path), 404);
        $user = Auth::user();
        $winner = $user?->role === 'bidder' && (int) $award->bid?->user_id === (int) $user->id;
        $adminOrAssigned = $user?->role === 'admin' || ($user?->role === 'staff' && Assignment::where('staff_id', $user->id)->where('project_id', $award->project_id)->exists());
        $office = $user?->role === 'end_user' && $user->office === $award->project?->end_user_unit;
        abort_unless($this->workflow->eligible($award) && ($winner || $adminOrAssigned || $office), 403);
        return Storage::disk('local')->download($event->document_path, $event->document_name ?: 'infrastructure-contract-document');
    }

    private function authorizeLgu(Award $award): void
    {
        abort_unless($this->workflow->eligible($award), 404);
        $user = Auth::user();
        abort_unless($user?->role === 'admin' || ($user?->role === 'staff' && Assignment::where('staff_id', $user->id)->where('project_id', $award->project_id)->exists()), 403);
    }
    private function authorizeEndUser(Award $award): void
    {
        abort_unless($this->workflow->eligible($award), 404);
        abort_unless(Auth::user()?->role === 'end_user' && Auth::user()->office === $award->project?->end_user_unit, 403);
    }
    private function authorizeWinner(Award $award): void
    {
        abort_unless($this->workflow->eligible($award), 404);
        abort_unless(Auth::user()?->role === 'bidder' && (int) $award->bid?->user_id === (int) Auth::id(), 403);
    }
}

