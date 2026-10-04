<?php

namespace App\Http\Controllers;

use App\Models\Award;
use App\Models\AuditLog;
use App\Support\QrSvg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicAwardController extends Controller
{
    public function index(Request $request)
    {
        $query = trim((string) $request->query('q', ''));

        try {
            $awards = Schema::hasTable('awards')
                ? Award::query()
                    ->with(['project', 'bid.user.bidderProfile'])
                    // Posted once the Notice of Award is issued; revoked or cancelled awards are withdrawn.
                    ->publiclyPosted()
                    ->when($query !== '', function ($builder) use ($query) {
                        $builder->where(function ($nested) use ($query) {
                            $nested->where('certificate_number', 'like', "%{$query}%")
                                ->orWhereHas('project', function ($projectQuery) use ($query) {
                                    $projectQuery->where('title', 'like', "%{$query}%")
                                        ->orWhere('description', 'like', "%{$query}%")
                                        ->orWhere('reference_no', 'like', "%{$query}%");
                                })->orWhereHas('bid.user', function ($userQuery) use ($query) {
                                    $userQuery->where('name', 'like', "%{$query}%")
                                        ->orWhere('company', 'like', "%{$query}%");
                                });
                        });
                    })
                    ->orderByRaw('COALESCE(notice_of_award_date, contract_date) DESC')
                    ->orderByDesc('id')
                    ->get()
                : collect();

            if (Schema::hasColumn('awards', 'certificate_number')) {
                $awards->each->ensureCertificateIdentity();
            }
        } catch (\Throwable) {
            $awards = collect();
        }

        $records = $awards->map(fn (Award $award) => $this->publicRecord($award))->values();
        $selected = $records->firstWhere('id', (int) $request->query('award')) ?? $records->first();

        // Postings below the viewer, ten per page like the municipal posting board.
        $page = max(1, (int) $request->query('page', 1));
        $postings = new \Illuminate\Pagination\LengthAwarePaginator(
            $records->forPage($page, 10)->values(), $records->count(), 10, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Awards & Contracts → Notice to Proceed: the published NTP of each posted award.
        $noticesToProceed = $awards->filter(fn (Award $award) => $award->hasPublishedNoticeToProceed())
            ->sortByDesc(fn (Award $award) => $award->ntp_issued_on->toDateString())
            ->map(fn (Award $award) => [
                'title' => $award->project?->title ?? 'Untitled project',
                'project_reference' => $award->project?->reference_no,
                'winner' => $this->winnerName($award),
                'issued_on' => $award->ntp_issued_on->format('F d, Y'),
                'view_url' => $award->noticeToProceedUrl(),
                'download_url' => $award->noticeToProceedUrl(download: true),
            ])->values();

        return view('pages.awards', compact('awards', 'records', 'postings', 'selected', 'query', 'noticesToProceed'));
    }

    /**
     * What the public page shows for one award. The document URL exists only
     * for a valid certificate with an uploaded file, so nothing is ever faked.
     *
     * @return array<string, mixed>
     */
    private function publicRecord(Award $award): array
    {
        $profile = $award->bid?->user?->bidderProfile;
        $profile?->ensureQrIdentity();
        $status = strtolower((string) ($award->certificate_status ?: $award->status ?: ''));

        return [
            'id' => $award->id,
            'title' => $award->project?->title ?? 'Untitled project',
            'project_reference' => $award->project?->reference_no,
            'reference' => $award->certificate_number ?: 'Not assigned',
            'date' => $award->awardDate()?->format('F d, Y') ?? 'Date to be announced',
            'date_short' => $award->awardDate()?->format('M d, Y') ?? 'TBA',
            'date_code' => $award->awardDate()?->format('m-d-Y'),
            'date_iso' => $award->awardDate()?->toDateString(),
            'winner' => $this->winnerName($award),
            'amount' => '₱'.number_format((float) $award->contract_amount, 2),
            'status' => match ($status) {
                Award::STATUS_EXPIRED => 'Certificate expired',
                Award::STATUS_VALID => 'Valid',
                default => 'Awarded',
            },
            'document_url' => $award->isCertificateViewable() ? route('public.awards.document', $award->qr_token) : null,
            // The signed Notice to Proceed, once issued and published.
            'ntp_issued' => $award->hasPublishedNoticeToProceed() ? $award->ntp_issued_on->format('F d, Y') : null,
            'ntp_url' => $award->noticeToProceedUrl(),
            // What the viewer pages through: the Notice of Award, then the Notice to Proceed.
            'documents' => array_values(array_filter([
                [
                    'key' => 'noa',
                    'label' => 'Notice of Award',
                    'title' => trim(($award->awardDate()?->format('m-d-Y') ? $award->awardDate()->format('m-d-Y').' – ' : '').'Notice of Award'),
                    'url' => $award->isCertificateViewable() ? route('public.awards.document', $award->qr_token) : null,
                    'missing' => 'The signed Notice of Award for this record has not been published online.',
                ],
                $award->hasPublishedNoticeToProceed() ? [
                    'key' => 'ntp',
                    'label' => 'Notice to Proceed',
                    'title' => $award->ntp_issued_on->format('m-d-Y').' – Notice to Proceed',
                    'url' => $award->noticeToProceedUrl(),
                    'missing' => null,
                ] : null,
            ])),
            'verify_url' => route('certificate.verify', $award),
            'bidder_verify_url' => $profile?->verificationUrl(),
            'bidder_qr_url' => $profile?->tokenQrUrl(),
        ];
    }

    /**
     * The published Notice to Proceed PDF (public). Only this document is
     * served; the bid and its files stay private.
     */
    public function noticeToProceed(Request $request, string $token)
    {
        $award = Award::where('qr_token', $token)->first();
        abort_unless($award && $award->isPubliclyVisible() && $award->hasPublishedNoticeToProceed(), 404, 'Invalid or unavailable Notice to Proceed.');
        abort_unless(Storage::disk('local')->exists($award->ntp_file_path), 404, 'Invalid or unavailable Notice to Proceed.');

        $name = 'Notice-to-Proceed-'.Str::slug($award->project?->reference_no ?: 'award-'.$award->id).'.pdf';

        return response(Storage::disk('local')->get($award->ntp_file_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Display the award certificate PDF by secure token (public)
     * URL: /certificate/{token}
     */
    public function showByToken(string $token)
    {
        $award = Award::where('qr_token', $token)->first();
        abort_unless($award, 404, 'Invalid or unavailable certificate.');
        // Not before its award date (server clock, Philippine time).
        abort_unless($award->isPubliclyVisible(), 404, 'Invalid or unavailable certificate.');

        $award->ensureCertificateIdentity();

        // Check certificate validity
        if (!$award->isCertificateViewable()) {
            abort(404, 'Invalid or unavailable certificate.');
        }

        // Check file exists
        if (!$award->hasCertificateFile()) {
            abort(404, 'Invalid or unavailable certificate.');
        }

        // Audit log: certificate viewed
        AuditLog::log('certificate_viewed', $award, [], [
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $filePath = $award->certificate_file_path;
        $fileName = $award->getCertificateFileName();

        // Security headers and inline display
        // Contents, not a file path: the disk may be remote (private Blob storage on Vercel).
        return response(
            Storage::disk('local')->get($filePath),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=0, no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }

    /**
     * Generate QR code image by secure token (public)
     * URL: /qr/{token}.svg
     */
    public function qrByToken(string $token)
    {
        $award = Award::where('qr_token', $token)->first();
        abort_unless($award, 404);

        abort_unless(filled($award->qr_token), 404);

        try {
            // QR code contains only the public verification URL. The page reloads
            // the award record by ID each time it is opened.
            $svg = QrSvg::render($award->verificationUrl(), 360);
        } catch (\Throwable $exception) {
            Log::error('Award QR code generation failed', [
                'award_id' => $award->getKey(),
                'certificate_number' => $award->certificate_number,
                'qr_token_present' => filled($award->qr_token),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            abort(500, 'The award QR code could not be generated.');
        }

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function winnerName(Award $award): string
    {
        return $award->bid?->user?->company
            ?: ($award->bid?->user?->name ?? 'N/A');
    }
}
