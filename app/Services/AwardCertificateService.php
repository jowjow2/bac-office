<?php

namespace App\Services;

use App\Models\Award;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Creates the missing, system-generated certificate artifact for an award.
 *
 * Uploaded certificates remain untouched. This service is intentionally used
 * by the backfill command only when a valid award has no readable certificate
 * file, so running the command is safe and idempotent.
 */
class AwardCertificateService
{
    /**
     * Ensure a valid award has its public identity and a certificate PDF.
     */
    public function ensureForValidAward(Award $award): Award
    {
        $status = strtolower((string) ($award->certificate_status ?: $award->status));

        if ($status !== Award::STATUS_VALID) {
            throw new \InvalidArgumentException('Only valid awards can receive a certificate artifact.');
        }

        $award->ensureCertificateIdentity();
        $award = Award::query()
            ->with(['project', 'bid.user'])
            ->findOrFail($award->getKey());

        if ($award->hasCertificateFile()) {
            return $award;
        }

        $generatedPath = null;

        try {
            $generatedPath = $this->generateCertificatePdf($award);

            $award->forceFill([
                'certificate_file_path' => $generatedPath,
                'certificate_status' => Award::STATUS_VALID,
                'certificate_uploaded_at' => $award->certificate_uploaded_at ?: now(),
            ])->save();

            $award->refresh();

            if (! $award->hasCertificateFile()) {
                throw new \RuntimeException('Generated certificate was not readable from local storage.');
            }

            return $award;
        } catch (Throwable $exception) {
            if ($generatedPath && Storage::disk('local')->exists($generatedPath)) {
                Storage::disk('local')->delete($generatedPath);
            }

            Log::error('Award certificate backfill failed', [
                'award_id' => $award->getKey(),
                'certificate_number' => $award->certificate_number,
                'project_id' => $award->project_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    /**
     * Render a safe, public-facing certificate snapshot from live award data.
     */
    protected function generateCertificatePdf(Award $award): string
    {
        $projectDirectory = $award->project_id ?: 'unassigned';
        $fileName = ($award->certificate_number ?: 'BAC-AWD-' . $award->getKey())
            . '-generated-' . Str::lower(Str::random(12)) . '.pdf';
        $path = 'certificates/' . $projectDirectory . '/' . $fileName;

        $contents = Pdf::loadView('pages.award-certificate-backfill', [
            'award' => $award,
        ])->setPaper('a4', 'portrait')->output();

        if (! is_string($contents) || $contents === '') {
            throw new \RuntimeException('The certificate PDF renderer returned empty output.');
        }

        if (! Storage::disk('local')->put($path, $contents)) {
            throw new \RuntimeException('The generated certificate PDF could not be stored.');
        }

        if (! Storage::disk('local')->exists($path)) {
            throw new \RuntimeException('The generated certificate PDF is missing after storage.');
        }

        return $path;
    }
}
