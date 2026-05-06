<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Award;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    public function view(string $token)
    {
        $award = Award::where('qr_token', $token)->first();

        if (
            ! $award
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

        return response()->file(Storage::disk('local')->path($award->certificate_file_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $award->getCertificateFileName() . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
