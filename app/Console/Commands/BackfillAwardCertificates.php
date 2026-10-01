<?php

namespace App\Console\Commands;

use App\Models\Award;
use App\Models\AuditLog;
use App\Services\AwardCertificateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillAwardCertificates extends Command
{
    protected $signature = 'awards:backfill-certificates
                            {--dry-run : Report missing artifacts without changing the database or storage}
                            {--id=* : Limit the backfill to one or more award IDs}';

    protected $description = 'Backfill identity, QR tokens, and generated certificate PDFs for valid awards';

    public function handle(AwardCertificateService $certificateService): int
    {
        $query = Award::query()
            ->where(function ($query) {
                $query->where('certificate_status', Award::STATUS_VALID)
                    ->orWhere(function ($query) {
                        $query->whereNull('certificate_status')
                            ->where('status', Award::STATUS_VALID);
                    });
            })
            ->orderBy('id');

        $ids = array_values(array_filter(array_map('intval', (array) $this->option('id'))));
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $dryRun = (bool) $this->option('dry-run');
        $scanned = 0;
        $repaired = 0;
        $skipped = 0;
        $failed = 0;

        $query->chunkById(100, function ($awards) use (
            $certificateService,
            $dryRun,
            &$scanned,
            &$repaired,
            &$skipped,
            &$failed
        ) {
            foreach ($awards as $award) {
                $scanned++;

                $missing = $this->missingArtifacts($award);
                if ($missing === []) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        'Would repair award #%d (%s): %s',
                        $award->id,
                        $award->certificate_number ?: 'no reference',
                        implode(', ', $missing)
                    ));
                    continue;
                }

                try {
                    $repairedAward = $certificateService->ensureForValidAward($award);

                    try {
                        AuditLog::log('certificate_backfilled', $repairedAward, [], [
                            'certificate_number' => $repairedAward->certificate_number,
                            'qr_token_created' => filled($repairedAward->qr_token),
                            'certificate_file_path' => $repairedAward->certificate_file_path,
                        ]);
                    } catch (\Throwable $auditException) {
                        // An audit-log issue must not make a successful repair
                        // look like a failed artifact generation.
                        Log::error('Award certificate backfill audit log failed', [
                            'award_id' => $repairedAward->id,
                            'exception' => $auditException::class,
                            'message' => $auditException->getMessage(),
                            'trace' => $auditException->getTraceAsString(),
                        ]);
                    }

                    $repaired++;
                    $this->info(sprintf(
                        'Repaired award #%d: %s',
                        $repairedAward->id,
                        $repairedAward->certificate_number
                    ));
                } catch (\Throwable $exception) {
                    $failed++;
                    $this->error(sprintf('Failed award #%d: %s', $award->id, $exception->getMessage()));

                    Log::error('Award certificate backfill command item failed', [
                        'award_id' => $award->id,
                        'exception' => $exception::class,
                        'message' => $exception->getMessage(),
                        'trace' => $exception->getTraceAsString(),
                    ]);
                }
            }
        });

        $this->newLine();
        $this->table(['Scanned', 'Would repair', 'Repaired', 'Already complete', 'Failed'], [[
            $scanned,
            $dryRun ? $scanned - $skipped : 0,
            $repaired,
            $skipped,
            $failed,
        ]]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Return only the artifacts that are actually missing, including storage.
     */
    private function missingArtifacts(Award $award): array
    {
        $missing = [];

        if (blank($award->certificate_number)) {
            $missing[] = 'certificate number';
        }

        if (blank($award->qr_token)) {
            $missing[] = 'QR token';
        }

        if (! $award->hasCertificateFile()) {
            $missing[] = 'certificate PDF';
        }

        return $missing;
    }
}
