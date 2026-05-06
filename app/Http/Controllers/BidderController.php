<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Award;
use App\Models\BidderDocument;
use App\Models\Bid;
use App\Models\Project;
use App\Models\User;
use App\Support\DocumentPreview;
use App\Support\Uploads;
use App\Support\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BidderController extends Controller
{
    public function index(Request $request)
    {
        return view('dashboard.bidder', $this->bidderPageData($request));
    }

    public function availableProjects(Request $request)
    {
        return view('bidder.available-projects', $this->bidderPageData($request));
    }

    public function myBids(Request $request)
    {
        return view('bidder.my-bids', $this->bidderPageData($request));
    }

    public function awardedContracts(Request $request)
    {
        return view('bidder.awarded-contracts', $this->bidderPageData($request));
    }

    public function companyProfile(Request $request)
    {
        return view('bidder.company-profile', $this->bidderPageData($request));
    }

    public function notifications(Request $request)
    {
        return view('bidder.notifications', $this->bidderPageData($request));
    }

    public function previewProjectDocument(Project $project, string $document)
    {
        abort_unless(in_array($project->status, Project::PUBLIC_STATUSES, true), 404);

        $project->loadMissing('documents');
        $documentMeta = $this->projectDocumentMeta($project, $document);

        abort_unless(filled($documentMeta['path']), 404);

        return redirect()->route('bidder.project.document.pdf', ['project' => $project, 'document' => $document]);
    }

    public function streamProjectDocumentPdf(Project $project, string $document)
    {
        abort_unless(in_array($project->status, Project::PUBLIC_STATUSES, true), 404);

        $project->loadMissing('documents');
        $documentMeta = $this->projectDocumentMeta($project, $document);

        abort_unless(filled($documentMeta['path']), 404);

        return $this->streamDocumentPdfPreview(
            $documentMeta['path'],
            $documentMeta['display_name'],
            $documentMeta['label']
        );
    }

    public function markAllNotificationsRead(Request $request)
    {
        SystemNotification::markAllRead(Auth::id());

        return redirect()
            ->route('bidder.notifications');
    }

    public function updateProfile(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'company' => ['required', 'string', 'max:255'],
            'registration_no' => ['required', 'string', 'max:255'],
        ]);

        $user->update([
            'company' => trim($validated['company']),
            'registration_no' => trim($validated['registration_no']),
        ]);

        return redirect()
            ->route('bidder.dashboard')
            ->with('success', 'Company profile updated successfully.');
    }

    public function uploadDocument(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'document_type' => ['required', 'string', 'in:PhilGEPS Certificate,DTI/SEC Registration,Business Permit,Audited Financial Statement,PCAB License'],
            'document_file' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:20480'],
        ]);

        $file = $request->file('document_file');
        $filename = 'bidder_doc_' . $user->id . '_' . now()->format('YmdHis') . '.' . $file->getClientOriginalExtension();
        $storedPath = Uploads::store($file, 'bidder-documents', $filename);

        BidderDocument::updateOrCreate(
            [
                'user_id' => $user->id,
                'document_type' => $validated['document_type'],
            ],
            [
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'status' => 'uploaded',
                'uploaded_at' => now(),
            ]
        );

        SystemNotification::createForRole(
            'admin',
            'New bidder document uploaded',
            ($user->company ?: $user->name) . ' uploaded ' . $validated['document_type'] . ' for review.',
            'bidder_document',
            ['user_id' => $user->id, 'document_type' => $validated['document_type']]
        );

        return redirect()
            ->route('bidder.company-profile')
            ->with('success', $validated['document_type'] . ' uploaded successfully.');
    }

    public function submitBid(Request $request, Project $project)
    {
        abort_unless($project->status === 'open', 403);

        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'bid_amount' => ['required', 'numeric', 'min:0'],
            'eligibility_file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
            'proposal_file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
            'notes' => ['nullable', 'string'],
        ]);

        $eligibilityPath = null;
        $proposalPath = null;

        if ($request->hasFile('eligibility_file')) {
            $file = $request->file('eligibility_file');
            $filename = 'eligibility_' . Auth::id() . '_' . $project->id . '_' . now()->format('YmdHis') . '.' . $file->getClientOriginalExtension();
            $eligibilityPath = Uploads::store($file, 'eligibility-documents', $filename);
        }

        if ($request->hasFile('proposal_file')) {
            $file = $request->file('proposal_file');
            $filename = 'proposal_' . Auth::id() . '_' . $project->id . '_' . now()->format('YmdHis') . '.' . $file->getClientOriginalExtension();
            $proposalPath = Uploads::store($file, 'proposals', $filename);
        }

        Bid::updateOrCreate(
            [
                'user_id' => $user->id,
                'project_id' => $project->id,
            ],
            [
                'bid_amount' => $validated['bid_amount'],
                'proposal_file' => $proposalPath,
                'eligibility_file' => $eligibilityPath,
                'status' => 'pending',
                'notes' => trim((string) ($validated['notes'] ?? '')),
            ]
        );

        SystemNotification::createForRole(
            'admin',
            'New bid submitted',
            ($user->company ?: $user->name) . ' submitted a bid for ' . $project->title . '.',
            'new_bid',
            ['project_id' => $project->id]
        );

        $staffIds = $project->assignments()->pluck('staff_id');
        SystemNotification::createForUsers(
            $staffIds,
            'New bid submitted',
            ($user->company ?: $user->name) . ' submitted a bid for ' . $project->title . '.',
            'new_bid',
            ['project_id' => $project->id]
        );

        return redirect()
            ->route('bidder.available-projects')
            ->with('success', 'Bid submitted successfully.');
    }

    protected function bidderPageData(Request $request): array
    {
        /** @var User $user */
        $user = Auth::user();

        $availableProjects = Project::with(['documents', 'requirement'])
            ->withCount('bids')
            ->where('status', 'open')
            ->latest()
            ->get();

        $myBids = Bid::with(['project.awards', 'award'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $awardedProjects = Award::with(['project', 'bid.user'])
            ->whereHas('bid', fn ($query) => $query->where('user_id', $user->id))
            ->latest()
            ->get();
        $awardedProjects->each->ensureCertificateIdentity();

        $awaitingAwardBids = $myBids
            ->filter(function ($bid) {
                return $bid->project
                    && $bid->project->status === 'closed'
                    && $bid->project->awards->isEmpty();
            })
            ->unique('project_id')
            ->values();

        $bidderDocuments = BidderDocument::where('user_id', $user->id)
            ->orderBy('document_type')
            ->get()
            ->keyBy('document_type');

        $pendingBids = $myBids->where('status', 'pending')->count();
        $approvedBids = $myBids->where('status', 'approved')->count();
        $rejectedBids = $myBids->where('status', 'rejected')->count();
        $profileComplete = filled($user->company) && filled($user->registration_no);

        $bidderNotificationItems = SystemNotification::forUser($user->id, 30);
        $bidderNotifications = SystemNotification::payloads($bidderNotificationItems, $user);

        return compact(
            'user',
            'availableProjects',
            'myBids',
            'awardedProjects',
            'awaitingAwardBids',
            'bidderDocuments',
            'pendingBids',
            'approvedBids',
            'rejectedBids',
            'profileComplete',
            'bidderNotifications'
        ) + [
            'bidderNotificationCount' => $bidderNotificationItems->whereNull('read_at')->count(),
        ];
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
}
