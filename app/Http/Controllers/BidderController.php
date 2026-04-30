<?php

namespace App\Http\Controllers;

use App\Models\Award;
use App\Models\BidderDocument;
use App\Models\Bid;
use App\Models\Project;
use App\Models\User;
use App\Support\Uploads;
use App\Support\SystemNotification;
use App\Support\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BidderController extends Controller
{
    public function index(Request $request, QrCodeService $qrCodes)
    {
        return view('dashboard.bidder', $this->bidderPageData($request, $qrCodes));
    }

    public function availableProjects(Request $request, QrCodeService $qrCodes)
    {
        return view('bidder.available-projects', $this->bidderPageData($request, $qrCodes) + [
            'scanProjectId' => $request->integer('scan_project') ?: null,
        ]);
    }

    public function myBids(Request $request, QrCodeService $qrCodes)
    {
        return view('bidder.my-bids', $this->bidderPageData($request, $qrCodes));
    }

    public function awardedContracts(Request $request, QrCodeService $qrCodes)
    {
        return view('bidder.awarded-contracts', $this->bidderPageData($request, $qrCodes));
    }

    public function companyProfile(Request $request, QrCodeService $qrCodes)
    {
        return view('bidder.company-profile', $this->bidderPageData($request, $qrCodes));
    }

    public function notifications(Request $request, QrCodeService $qrCodes)
    {
        return view('bidder.notifications', $this->bidderPageData($request, $qrCodes));
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
            'proposal_file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
            'notes' => ['nullable', 'string'],
        ]);

        $proposalPath = null;

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

    protected function bidderPageData(Request $request, QrCodeService $qrCodes): array
    {
        /** @var User $user */
        $user = Auth::user();

        $availableProjects = Project::withCount('bids')
            ->where('status', 'open')
            ->latest()
            ->get()
            ->map(function (Project $project) use ($request, $qrCodes) {
                $publicUrl = $this->publicProjectUrl($request, $project);
                $publicPath = route('public.procurement.show', $project, false);
                $scanUrl = $this->publicProjectScanUrl($request, $project);
                $scanPath = route('public.procurement.scan', $project, false);

                $project->setAttribute('public_url', $publicUrl);
                $project->setAttribute('public_path', $publicPath);
                $project->setAttribute('scan_url', $scanUrl);
                $project->setAttribute('scan_path', $scanPath);
                $project->setAttribute('qr_code_data_uri', $qrCodes->toDataUri($scanUrl, 120));

                return $project;
            });

        $myBids = Bid::with(['project.awards', 'award'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $awardedProjects = Award::with(['project', 'bid.user'])
            ->whereHas('bid', fn ($query) => $query->where('user_id', $user->id))
            ->latest()
            ->get();

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
        $bidderNotifications = $bidderNotificationItems
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

    protected function publicProjectUrl(Request $request, Project $project): string
    {
        return rtrim($request->root(), '/') . route('public.procurement.show', $project, false);
    }

    protected function publicProjectScanUrl(Request $request, Project $project): string
    {
        return rtrim($request->root(), '/') . route('public.procurement.scan', $project, false);
    }
}

