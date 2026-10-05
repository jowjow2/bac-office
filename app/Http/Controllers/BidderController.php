<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Award;
use App\Models\BidderDocument;
use App\Models\BidderRequirementRequest;
use App\Models\Bid;
use App\Models\Project;
use App\Models\User;
use App\Models\AuditLog;
use App\Support\BidSubmission;
use App\Support\DocumentPreview;
use App\Support\Uploads;
use App\Support\BidderRegistrationRequirements;
use App\Support\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class BidderController extends Controller
{
    /**
     * Bidder overview: registration status, open opportunities, the status
     * of each submission, and the deadlines coming up.
     */
    public function index(Request $request)
    {
        $data = $this->bidderPageData($request);
        $myBids = $data['myBids'];
        $myProjectIds = $myBids->pluck('project_id')->unique();

        $opportunities = $data['availableProjects']
            ->load(['biddingFeePayments' => fn ($payments) => $payments->where('user_id', Auth::id())])
            ->sortBy(fn (Project $project) => $project->bidSubmissionDeadline()?->getTimestamp() ?? PHP_INT_MAX)
            ->values();

        // Official submissions still waiting for a result (not closed, not awarded).
        $awaiting = $myBids->reject(fn (Bid $bid) => $bid->isDraft())
            ->filter(fn (Bid $bid) => ! $bid->progress()->isClosed() && $bid->notice_of_award_at === null)
            ->count();

        $upcoming = collect();
        $until = now()->addDays(21)->endOfDay();
        $watched = $opportunities->concat(
            Project::with('schedule')->whereIn('id', $myProjectIds)->whereNull('archived_at')->get()
        )->unique('id');

        foreach ($watched as $project) {
            $mode = $project->mode();
            foreach ([
                ['Pre-bid conference', $project->schedule?->pre_bid_conference_date, true],
                ['Deadline for clarifications', $project->schedule?->clarification_deadline, true],
                [$mode->deadlineLabel(), $project->bidSubmissionDeadline(), true],
                [$mode->openingLabel(), $project->bids_opened_at ? null : $project->schedule?->bid_opening_date, true],
            ] as [$title, $at, $time]) {
                if ($at instanceof \Carbon\CarbonInterface && $at->between(now(), $until)) {
                    $upcoming->push([
                        'at' => $at,
                        'title' => $title,
                        'reference' => $project->reference_no ?: 'Project #'.$project->id,
                        'detail' => $project->title,
                        'url' => route('bidder.opportunities.show', $project),
                        'time' => $time,
                    ]);
                }
            }
        }

        return view('dashboard.bidder', array_merge($data, [
            // Shown on the first dashboard visit after signing in, then gone.
            'welcome' => (bool) $request->session()->pull('bidder_welcome', false),
            'opportunities' => $opportunities,
            'awaitingResults' => $awaiting,
            'upcoming' => $upcoming->sortBy(fn (array $event) => $event['at']->getTimestamp())->take(8)->values(),
        ]));
    }

    /**
     * One opportunity, with everything a supplier needs before submitting:
     * mode, documents, the optional bidding documents fee, bid security,
     * submission method, requirements, dates and their own submission status.
     */
    public function showOpportunity(Project $project)
    {
        /** @var User $user */
        $user = Auth::user();
        $project->load(['schedule', 'requirement', 'documents', 'proceedings', 'biddingFeePayments' => fn ($payments) => $payments->where('user_id', $user->id)]);

        $myBid = Bid::where('project_id', $project->id)->where('user_id', $user->id)->latest('id')->first();

        // Visible when posted and still open, or when this bidder already took part.
        abort_unless($project->archived_at === null && ($project->isOpenForBidding() || $myBid !== null), 404);

        $requirements = \App\Support\BidSubmissionRequirements::for($project);

        return view('bidder.opportunity', [
            'project' => $project,
            'mode' => $project->mode(),
            'myBid' => $myBid,
            'progress' => $myBid?->progress()->toArray(),
            'requirements' => $requirements,
            'feePayment' => $project->biddingFeePaymentFor($user),
            'bulletins' => $project->proceedings->where('type', \App\Models\ProjectProceeding::TYPE_BID_BULLETIN)->sortByDesc('occurred_at')->values(),
        ]);
    }

    public function availableProjects(Request $request)
    {
        return view('bidder.available-projects', array_merge(
            $this->bidderPageData($request),
            $this->availableProjectsListing($request),
        ));
    }

    /**
     * Server-side tab / search / sort / pagination for the Available Projects list.
     *
     * The browsable set is everything still open for bidding plus anything this bidder
     * has already bid on, so the "Already bid" and "Closed" tabs have something to show
     * once a deadline has passed.
     */
    protected function availableProjectsListing(Request $request): array
    {
        /** @var User $user */
        $user = Auth::user();

        $tab = in_array($request->query('tab'), ['closing', 'mine', 'closed'], true)
            ? $request->query('tab')
            : 'all';
        $sort = in_array($request->query('sort'), ['budget', 'newest'], true)
            ? $request->query('sort')
            : 'closing';
        $search = trim((string) $request->query('q', ''));

        $myBidProjectIds = Bid::where('user_id', $user->id)->pluck('project_id')->unique();
        $soonThreshold = now()->addDays(4)->endOfDay();

        $base = fn () => Project::query()
            ->whereNull('archived_at')
            ->where(function ($query) use ($myBidProjectIds) {
                $query->openForBidding();

                if ($myBidProjectIds->isNotEmpty()) {
                    $query->orWhereIn('id', $myBidProjectIds);
                }
            });

        // Summary counts describe the whole browsable set, not the filtered page.
        $summaryProjects = $base()->with('schedule')->get();
        $openCount = $summaryProjects->filter->isOpenForBidding()->count();
        $closingSoonCount = $summaryProjects
            ->filter(fn (Project $project) => $project->isOpenForBidding()
                && $project->bidSubmissionDeadline()?->lessThanOrEqualTo($soonThreshold))
            ->count();
        $totalCount = $summaryProjects->count();

        $query = $base()
            ->with([
                'documents',
                'schedule',
                'requirement',
                'biddingFeePayments' => fn ($payments) => $payments->where('user_id', $user->id),
            ])
            ->withCount('bids');

        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $like = '%' . $search . '%';
                $inner->where('title', 'like', $like)
                    ->orWhere('reference_no', 'like', $like)
                    ->orWhere('end_user_unit', 'like', $like);
            });
        }

        match ($tab) {
            'closing' => $query->openForBidding()->where('deadline', '<=', $soonThreshold),
            'mine' => $query->whereIn('id', $myBidProjectIds->all() ?: [0]),
            'closed' => $query->where(function ($inner) {
                $inner->where('status', '!=', 'open')
                    ->orWhere('deadline', '<=', now())
                    ->orWhereNull('deadline');
            }),
            default => null,
        };

        match ($sort) {
            'budget' => $query->orderByDesc('budget'),
            'newest' => $query->orderByDesc('created_at'),
            default => $query->orderByRaw('deadline is null')->orderBy('deadline'),
        };

        $projects = $query->paginate(10)->withQueryString();

        return [
            'listProjects' => $projects,
            'listTab' => $tab,
            'listSort' => $sort,
            'listSearch' => $search,
            'listOpenCount' => $openCount,
            'listClosingSoonCount' => $closingSoonCount,
            'listTotalCount' => $totalCount,
            'listMyBidProjectIds' => $myBidProjectIds,
        ];
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
        // Published and its publication time reached (server clock, Philippine time).
        abort_unless($project->isPubliclyVisible(), 404);

        $project->loadMissing('documents');
        $documentMeta = $this->projectDocumentMeta($project, $document);

        abort_unless(filled($documentMeta['path']), 404);

        return redirect()->route('bidder.project.document.pdf', ['project' => $project, 'document' => $document]);
    }

    public function streamProjectDocumentPdf(Project $project, string $document)
    {
        // Published and its publication time reached (server clock, Philippine time).
        abort_unless($project->isPubliclyVisible(), 404);

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
            'registration_no' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) use ($user) {
                if (User::registrationNumberTaken($value, $user->id)) {
                    $fail('Another bidder is already registered with this business registration number. Contact the BAC Secretariat if this is your business.');
                }
            }],
        ]);

        $user->update([
            'company' => trim($validated['company']),
            'registration_no' => trim($validated['registration_no']),
        ]);
        if ($user->bidderProfile) {
            $user->bidderProfile->forceFill(['company_name' => trim($validated['company'])])->save();
        }

        return redirect()->route('bidder.company-profile')->with('success', 'Company profile updated successfully.');
    }
    public function uploadDocument(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $documentTypes = array_values(array_unique(array_merge(
            BidderRegistrationRequirements::documentTypes(),
            ['PhilGEPS Certificate', 'DTI/SEC registration', 'Audited Financial Statement', 'PCAB License']
        )));
        $validated = $request->validate([
            'document_type' => ['required', 'string', Rule::in($documentTypes)],
            'document_file' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:20480'],
        ]);

        $file = $request->file('document_file');

        $folder = 'bidder-documents';
        $filename = 'bidder_doc_' . $user->id . '_' . now()->format('YmdHis') . '_' . \Illuminate\Support\Str::random(8) . '.' . strtolower($file->getClientOriginalExtension());
        $storedPath = Uploads::store($file, $folder, $filename);

        DB::transaction(function () use ($user, $validated, $file, $storedPath): void {
            $previous = BidderDocument::where('user_id', $user->id)
                ->where('document_type', $validated['document_type'])
                ->where('is_current', true)
                ->lockForUpdate()
                ->latest('id')
                ->first();
            if ($previous) {
                $previous->forceFill(['is_current' => false])->save();
            }
            $document = new BidderDocument();
            $document->forceFill([
                'user_id' => $user->id,
                'document_type' => $validated['document_type'],
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'status' => 'uploaded',
                'version' => ($previous?->version ?: 0) + 1,
                'is_current' => true,
                'review_status' => $user->status === 'pending' ? 'for_review' : 'uploaded',
                'supersedes_id' => $previous?->id,
                'uploaded_at' => now(),
            ])->save();
            AuditLog::log($previous && $user->status === 'pending' ? 'bidder_document_resubmitted' : 'bidder_document_uploaded', $document, $previous ? ['supersedes_id' => $previous->id, 'version' => $previous->version] : null, ['document_type' => $document->document_type, 'version' => $document->version], ['user_id' => $user->id]);
        });

        SystemNotification::createForRole('admin', 'Bidder document uploaded', ($user->company ?: $user->name) . ' uploaded ' . $validated['document_type'] . ' for review.', 'bidder_document', ['user_id' => $user->id, 'document_type' => $validated['document_type']]);
        SystemNotification::createForRole('staff', 'Bidder document uploaded', ($user->company ?: $user->name) . ' uploaded ' . $validated['document_type'] . ' for review.', 'bidder_document', ['user_id' => $user->id, 'document_type' => $validated['document_type']]);

        return redirect()->route('bidder.company-profile')->with('success', $validated['document_type'] . ' uploaded successfully.');
    }

    public function previewDocument(BidderDocument $document)
    {
        abort_unless((int) $document->user_id === (int) Auth::id(), 404);

        return redirect()->route('bidder.document.pdf', ['document' => $document]);
    }

    public function streamOwnDocumentPdf(BidderDocument $document)
    {
        abort_unless((int) $document->user_id === (int) Auth::id(), 404);

        return $this->streamDocumentPdfPreview(
            $document->file_path,
            $document->display_name,
            $document->document_type
        );
    }

    public function submitForReevaluation(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->status === 'pending', 409, 'Only pending bidder registrations can be submitted for re-evaluation.');
        $submitted = DB::transaction(function () use ($user, $request): bool {
            $bidder = $user->bidderProfile()->lockForUpdate()->firstOrFail();
            if ($bidder->review_status === 'for_re_evaluation') {
                return false;
            }
            $requests = $bidder->requirementRequests()->where('status', 'open')->get();
            if ($requests->isEmpty()) {
                abort(422, 'There are no open document corrections to submit.');
            }
            $now = now();
            $requests->each(fn ($requirement) => $requirement->forceFill(['status' => 'submitted'])->save());
            BidderDocument::where('user_id', $user->id)->where('is_current', true)->whereIn('document_type', $requests->pluck('document_type'))->where('review_status', 'needs_action')->update(['review_status' => 'for_re_evaluation']);
            $bidder->forceFill(['approval_status' => 'pending', 'review_status' => 'for_re_evaluation', 'review_requested_at' => $now, 'review_requested_by' => $user->id])->save();
            AuditLog::log('bidder_resubmitted_for_review', $bidder, ['review_status' => 'needs_action'], ['review_status' => 'for_re_evaluation', 'document_types' => $requests->pluck('document_type')->values()->all()], ['user_id' => $user->id, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
            return true;
        });
        if ($submitted) {
            $message = ($user->company ?: $user->name) . ' submitted bidder requirements for re-evaluation.';
            SystemNotification::createForRole('admin', 'Bidder requirements resubmitted', $message, 'bidder_requirements_resubmitted', ['user_id' => $user->id]);
            SystemNotification::createForRole('staff', 'Bidder requirements resubmitted', $message, 'bidder_requirements_resubmitted', ['user_id' => $user->id]);
        }
        $message = $submitted ? 'Your documents were submitted for re-evaluation.' : 'Your documents are already awaiting re-evaluation.';
        if ($request->expectsJson()) return response()->json(['ok' => true, 'message' => $message]);
        return redirect()->route('bidder.company-profile')->with('success', $message);
    }
    public function submitBid(Request $request, Project $project)
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isApprovedBidder()) {
            return redirect()
                ->route('bidder.available-projects')
                ->withErrors(['bidder_status' => 'Your bidder account is not authorized to participate in procurement at this time.']);
        }

        // A manual-submission project takes sealed paper bids at the BAC Secretariat only;
        // nothing is filed online. The Secretariat records the envelopes when they are handed in.
        if (! $project->acceptsElectronicSubmission()) {
            return redirect()
                ->route('bidder.available-projects')
                ->withErrors(['submission' => 'This project takes sealed paper bids only. Bring your sealed envelopes to the BAC Secretariat; nothing is submitted online.']);
        }

        $financialPassword = $request->input('financial_password');
        $financialPasswordConfirmation = $request->input('financial_password_confirmation');
        $request->request->remove('financial_password');
        $request->request->remove('financial_password_confirmation');
        $request->query->remove('financial_password');
        $request->query->remove('financial_password_confirmation');
        $request->json()->remove('financial_password');
        $request->json()->remove('financial_password_confirmation');
        $validationData = $request->all();
        $validationData['financial_password'] = $financialPassword;
        $validationData['financial_password_confirmation'] = $financialPasswordConfirmation;
        $electronic = $project->acceptsElectronicSubmission();
        $maxKb = (int) config('bac-office.registration.max_document_size_kb', 20480);

        // Files the browser uploaded straight to Blob storage (bidUploadToken) arrive as
        // references; fetch them so they get exactly the same checks and storage as posted files.
        try {
            [$blobFiles, $blobUrls, $tempPaths] = $this->pullDirectBidUploads($request, $project, $user);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('bidder.available-projects', ['bid_project' => $project->id])
                ->withErrors($exception->errors())
                ->withInput($request->except('documents', 'uploaded_documents', 'uploaded_document_names'));
        }
        $documentFiles = $blobFiles + $request->file('documents', []);
        $validationData['documents'] = $documentFiles;
        $validated = validator($validationData, [
            'bid_amount' => ['required', 'string', 'max:30'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', BidSubmission::FILE_RULES, 'max:'.$maxKb],
            'notes' => ['nullable', 'string', 'max:5000'],
            'financial_password' => [Rule::requiredIf($electronic), 'nullable', 'string', 'digits:6', 'confirmed'],
        ], [
            'financial_password.required' => 'Set a 6-digit financial PIN.',
            'financial_password.digits' => 'The financial PIN must be exactly 6 digits.',
            'financial_password.confirmed' => 'The two financial PINs do not match.',
        ]);
        try {
            $validated = $validated->validate();
            unset($validated['financial_password'], $validated['financial_password_confirmation']);

            $bid = app(BidSubmission::class)->submit(
                $project,
                $user,
                (string) $validated['bid_amount'],
                $documentFiles,
                $validated['notes'] ?? null, $financialPassword
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('bidder.available-projects', ['bid_project' => $project->id])
                ->withErrors($exception->errors())
                ->withInput($request->except('documents', 'uploaded_documents', 'uploaded_document_names'));
        } finally {
            // The bid keeps its own stored copies; the temporary direct uploads go.
            \App\Support\VercelBlob::discardUploads($blobUrls, $tempPaths);
        }

        if ($bid->isDraft()) {
            return redirect()
                ->route('bidder.available-projects')
                ->with('success', 'Draft record saved. This is NOT an official bid: submit your sealed bid to the BAC Secretariat before the deadline.');
        }

        $modified = str_contains((string) $bid->receipt_no, '-M');
        $title = $modified ? 'Bid modified' : 'New bid submitted';
        $message = ($user->company ?: $user->name) . ($modified ? ' modified its online bid for ' : ' submitted an online bid for ') . $project->title . ' (Receipt No. ' . $bid->receipt_no . ').';
        SystemNotification::createForRole('admin', $title, $message, 'new_bid', ['project_id' => $project->id]);
        SystemNotification::createForUsers($project->assignments()->pluck('staff_id'), $title, $message, 'new_bid', ['project_id' => $project->id]);

        return redirect()
            ->route('bidder.available-projects')
            ->with('success', ($modified ? 'Bid modification received. It replaces your earlier submission. Receipt No. ' : 'Bid submitted online. Receipt No. ') . $bid->receipt_no . ' (' . $bid->submitted_at->timezone(config('bac-office.display_timezone'))->format('M d, Y h:i:s A') . ').');
    }

    /** Folder in Blob storage for one bidder's direct uploads to one project. */
    private function bidUploadFolder(User $user, Project $project): string
    {
        return 'bid-uploads/'.$user->id.'/'.$project->id;
    }

    /**
     * Issues the short-lived token @vercel/blob "upload" asks for, so a bid file
     * goes from the browser straight to private Blob storage instead of through
     * this function (whose request body Vercel limits to 4.5 MB). Only for an
     * approved bidder with a verified fee, on an open online project, into that
     * bidder's own folder.
     */
    public function bidUploadToken(Request $request, Project $project)
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless(\App\Support\VercelBlob::enabled(), 404);

        $refusal = match (true) {
            ! $user->isApprovedBidder() => 'Your bidder account is not authorized to participate in procurement at this time.',
            ! $project->isOpenForBidding() => 'Bid submission for this project is closed.',
            ! $project->acceptsElectronicSubmission() => 'This project does not take online bids.',
            ! $project->hasPaidBiddingFee($user) => 'Pay the bidding documents fee first.',
            $request->input('type') !== 'blob.generate-client-token' => 'Unsupported upload request.',
            default => null,
        };
        if ($refusal !== null) {
            return response()->json(['error' => $refusal], 403);
        }

        $pathname = (string) $request->input('payload.pathname');
        $folder = $this->bidUploadFolder($user, $project);
        if (! preg_match('#^'.preg_quote($folder, '#').'/[A-Za-z0-9._-]{1,150}\.(pdf|doc|docx|xls|xlsx)$#i', $pathname)) {
            return response()->json(['error' => 'Use PDF, DOC, DOCX, XLS or XLSX files.'], 422);
        }

        $maxKb = (int) config('bac-office.registration.max_document_size_kb', 20480);

        return response()->json([
            'type' => 'blob.generate-client-token',
            'clientToken' => \App\Support\VercelBlob::clientToken($pathname, $maxKb * 1024, []),
        ]);
    }

    /**
     * Turns the references of files uploaded straight to Blob storage into
     * temporary files the normal bid checks and storage can use.
     *
     * @return array{0: array<string, \Illuminate\Http\UploadedFile>, 1: list<string>, 2: list<string>}
     *
     * @throws ValidationException
     */
    private function pullDirectBidUploads(Request $request, Project $project, User $user): array
    {
        return \App\Support\VercelBlob::pullUploads(
            (array) $request->input('uploaded_documents', []),
            (array) $request->input('uploaded_document_names', []),
            $this->bidUploadFolder($user, $project),
            'documents'
        );
    }

    protected function bidderPageData(Request $request): array
    {
        /** @var User $user */
        $user = Auth::user();

        $availableProjects = Project::with(['documents', 'requirement', 'schedule'])
            ->withCount('bids')
            ->openForBidding()
            ->latest()
            ->get();

        $myBids = Bid::with(['project.awards', 'award', 'documents.reviewEvents.actor'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        // This supplier's bids the HoPE approved before the hand-off existed get their award record now (idempotent).
        Bid::where('user_id', $user->id)->awaitingAwardRecord()->whereDoesntHave('award')->get()->each(function (Bid $bid) {
            try {
                app(\App\Support\BidWorkflow::class)->handOffApprovedAward($bid);
            } catch (\Illuminate\Validation\ValidationException) {
                // Another bidder's award is in force on that project.
            }
        });

        $awardedProjects = Award::with(['project', 'bid.user', 'bid.project.awards', 'bid.award', 'contractImplementation.events.actor'])
            ->whereHas('bid', fn ($query) => $query->where('user_id', $user->id))
            ->latest()
            ->get();
        $awardedProjects->each->ensureCertificateIdentity();
        $implementationWorkflow = app(\App\Support\ContractImplementationWorkflow::class);
        $awardedProjects->each(function ($award) use ($implementationWorkflow) {
            if ($implementationWorkflow->eligible($award)) {
                $award->setRelation('contractImplementation', $implementationWorkflow->ensure($award)->load('events.actor'));
            }
        });

        $awaitingAwardBids = $myBids
            ->filter(function ($bid) {
                return $bid->project
                    && $bid->project->status === 'closed'
                    && $bid->project->awards->isEmpty();
            })
            ->unique('project_id')
            ->values();

        $bidderDocuments = BidderDocument::where('user_id', $user->id)
            ->where('is_current', true)
            ->orderBy('document_type')
            ->get()
            ->keyBy('document_type');
        $bidderDocumentHistory = BidderDocument::where('user_id', $user->id)->latest('id')->get();
        $registrationDocuments = $bidderDocuments->filter(fn (BidderDocument $document): bool => in_array($document->document_type, BidderRegistrationRequirements::documentTypes(), true));
        $registrationRequirementOptions = collect(BidderRegistrationRequirements::documents())->values()->all();
        $missingRegistrationRequirements = collect($registrationRequirementOptions)
            ->filter(fn (array $document): bool => ($document['required'] ?? false) && ! $registrationDocuments->has($document['document_type']))
            ->pluck('label')->values()->all();
        $registrationRequirementsComplete = $missingRegistrationRequirements === [];
        $bidderProfile = $user->bidderProfile;
        $bidderProfile?->ensureQrIdentity();
        $registrationRequests = $bidderProfile ? $bidderProfile->requirementRequests()->whereIn('status', ['open', 'submitted'])->latest()->get() : collect();
        $reviewStatus = $user->status === 'pending' ? ($bidderProfile?->review_status ?: 'new') : null;

        // Single authoritative account status for the profile page banner. Suspension
        // (a sanction record) takes priority over the plain registration review status.
        $activeSanction = $bidderProfile?->activeSanction;
        $accountStatusKey = match (true) {
            (bool) $activeSanction => 'suspended',
            $user->status === 'rejected' => 'rejected',
            $user->status === 'active' => 'approved',
            $reviewStatus === 'needs_action' => 'needs_action',
            $reviewStatus === 'for_re_evaluation' => 'resubmitted',
            default => 'under_review',
        };

        // A flagged document counts as "replaced" only once the bidder has uploaded a
        // newer version after the correction was requested — derived from real upload
        // timestamps, not a separate UI-only flag.
        $openCorrections = $registrationRequests
            ->where('status', 'open')
            ->map(function (BidderRequirementRequest $requirementRequest) use ($registrationRequirementOptions, $bidderDocuments): array {
                $option = collect($registrationRequirementOptions)->firstWhere('document_type', $requirementRequest->document_type);
                $document = $bidderDocuments->get($requirementRequest->document_type);
                $replaced = (bool) ($document?->uploaded_at && $requirementRequest->requested_at
                    && $document->uploaded_at->gt($requirementRequest->requested_at));

                return [
                    'document_type' => $requirementRequest->document_type,
                    'label' => $option['label'] ?? $requirementRequest->document_type,
                    'reason' => $requirementRequest->reason,
                    'requested_at' => $requirementRequest->requested_at,
                    'document' => $document,
                    'replaced' => $replaced,
                ];
            })
            ->values();
        $allCorrectionsReplaced = $openCorrections->isNotEmpty() && $openCorrections->every(fn (array $correction) => $correction['replaced']);

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
            'bidderDocumentHistory',
            'registrationDocuments',
            'registrationRequirementOptions',
            'missingRegistrationRequirements',
            'registrationRequirementsComplete',
            'registrationRequests',
            'reviewStatus',
            'activeSanction',
            'accountStatusKey',
            'openCorrections',
            'allCorrectionsReplaced',
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

        $projectDocument = $project->officialDocuments()->get((int) $document);

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
