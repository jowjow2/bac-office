<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Award;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    /**
     * Display a fresh, public, read-only award verification record.
     */
    public function verify(Award $award)
    {
        // Re-query on every request so the verification page never relies on
        // data embedded in the QR code or a previously loaded model instance.
        $award = Award::query()
            ->with(['project', 'bid.user'])
            ->findOrFail($award->getKey());

        // Not public before its Notice of Award, nor before its award date (server clock, Philippine time).
        // A revoked certificate still verifies, as revoked.
        abort_if($award->awaitsNoticeOfAward() || $award->isScheduledForPublication(), 404);

        $verificationStatus = strtolower((string) ($award->certificate_status ?: $award->status ?: 'unknown'));
        $verifiedAt = now();

        AuditLog::log('certificate_verified', $award, [], [
            'status' => $verificationStatus,
        ]);

        return response()
            ->view('pages.award-verification', compact('award', 'verificationStatus', 'verifiedAt'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function view(string $token)
    {
        $award = Award::where('qr_token', $token)->first();

        if (
            ! $award
            || ! $award->isPubliclyVisible()
            || ($award->certificate_status ?: $award->status) !== Award::STATUS_VALID
            || blank($award->certificate_file_path)
            || ! Storage::disk('local')->exists($award->certificate_file_path)
        ) {
            return response('Invalid or unavailable certificate.', 404, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);
        }

        AuditLog::log('certificate_viewed_through_qr', $award, [], [
            'qr_token' => $award->qr_token,
        ]);

        // Contents, not a file path: the disk may be remote (private Blob storage on Vercel).
        return response(Storage::disk('local')->get($award->certificate_file_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $award->getCertificateFileName() . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
