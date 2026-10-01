<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\BidDocument;
use App\Models\BidDocumentReviewEvent;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Stores a bidder's bid for a project the way its notice allows.
 *
 * - Online submission (the default): the bid is official on receipt. When the
 *   project has a bidding documents fee, the BAC must first have recorded the
 *   bidder's Official Receipt. Every required checklist file must be present,
 *   the deadline is checked on the server at the moment of saving, and a
 *   receipt number, timestamp and file hashes are recorded with a
 *   "Bid Submitted" event.
 * - Manual submission: the upload is saved only as a draft / supplementary
 *   record. It becomes an official bid only when the BAC Secretariat records
 *   receipt of the sealed bid (BidWorkflow), which also checks the fee.
 *
 * The entered bid amount is stored exactly as entered (two decimals).
 */
class BidSubmission
{
    public const FILE_RULES = 'mimes:pdf,doc,docx,xls,xlsx';

    public function __construct(private readonly BidWorkflow $workflow) {}

    /**
     * @param  array<string, UploadedFile>  $files  keyed by checklist requirement key
     *
     * @throws ValidationException
     */
    public function submit(Project $project, User $bidder, string $amount, array $files, ?string $notes = null, ?string $financialPassword = null): Bid
    {
        $project->loadMissing(['schedule', 'requirement']);
        $requirements = BidSubmissionRequirements::for($project);
        $electronic = $project->acceptsElectronicSubmission();

        if ($electronic && (! is_string($financialPassword) || ! preg_match('/^[0-9]{6}$/', $financialPassword))) {
            throw ValidationException::withMessages(['financial_password' => 'Set a 6-digit financial PIN.']);
        }

        $this->assertBeforeDeadline($project);
        $this->assertBiddingFeePaid($project, $bidder);
        $amount = $this->normalizeAmount($amount);
        $this->assertWithinAbc($project, $amount);

        // An official online bid may be modified until the deadline (RA 12009 IRR Sec. 55.1):
        // documents already on record count, so only the ones being replaced are uploaded.
        $existing = Bid::with('documents')->where('user_id', $bidder->id)->where('project_id', $project->id)->first();
        $modifying = $existing !== null && ! $existing->isDraft();
        if ($modifying) {
            $this->assertModifiable($existing, $electronic);
        }
        $this->assertFiles($requirements, $files, $electronic, $modifying ? $existing->documents->pluck('requirement_key')->all() : []);

        $stored = [];

        try {
            $bid = DB::transaction(function () use ($project, $bidder, $amount, $files, $notes, $financialPassword, $requirements, $electronic, &$stored) {
                $lockedProject = Project::with('schedule')->lockForUpdate()->findOrFail($project->id);
                // Checked again under the lock: the deadline is enforced at the moment of saving.
                $this->assertBeforeDeadline($lockedProject);
                // The fee gates every bid upload, including manual draft proposals.
                $this->assertBiddingFeePaid($lockedProject, $bidder);

                $bid = Bid::where('user_id', $bidder->id)->where('project_id', $project->id)->lockForUpdate()->first();
                $modifying = $bid !== null && ! $bid->isDraft();
                if ($modifying) {
                    $this->assertModifiable($bid, $electronic);
                    // What the modification supersedes; the original is kept, never handed back.
                    $superseded = [
                        'receipt_no' => $bid->receipt_no,
                        'submitted_at' => $bid->submitted_at?->toIso8601String(),
                        'bid_amount' => $bid->getRawOriginal('bid_amount'),
                    ];
                    $replaced = [];
                }

                $attributes = [
                    'bid_amount' => $amount,
                    'notes' => trim((string) $notes),
                    'submission_channel' => $electronic ? Bid::CHANNEL_ELECTRONIC : Bid::CHANNEL_MANUAL,
                    'submitted_at' => $electronic ? now() : null,
                    'financial_opening_password_hash' => $electronic ? Hash::make($financialPassword) : null,
                    'financial_password_attempts' => 0,
                    'financial_password_locked_until' => null,
                ];

                if ($bid) {
                    $bid->update($attributes);
                } else {
                    $bid = Bid::create($attributes + [
                        'user_id' => $bidder->id,
                        'project_id' => $project->id,
                        'status' => 'pending',
                        'workflow_step' => Bid::STEP_SUBMITTED,
                        'workflow_step_updated_at' => now(),
                        'workflow_step_updated_by' => $bidder->id,
                    ]);
                }

                foreach ($files as $key => $file) {
                    $item = $requirements->find($key);
                    $encryptedAt = null;
                    if ($electronic && $item['component'] === BidDocument::COMPONENT_FINANCIAL) {
                        $storedFile = app(FinancialBidFile::class)->store($file, $project->id, $bid->id, (string) $key);
                        $path = $storedFile['path'];
                        $encryptedAt = $storedFile['encrypted_at'];
                    } else {
                        $path = Uploads::store(
                            $file,
                            'bid-submissions/'.$project->id.'/'.$bid->id.'/'.$item['component'],
                            $key.'_'.bin2hex(random_bytes(6)).'.'.strtolower($file->getClientOriginalExtension())
                        );
                    }
                    $stored[] = $path;
                    $previous = $bid->documents()->where('requirement_key', $key)->first();
                    $documentAttributes = [
                        'requirement_key' => $key,
                        'component' => $item['component'],
                        'label' => $item['label'],
                        'file_path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'sha256' => hash_file('sha256', $file->getRealPath()),
                        'encrypted_at' => $encryptedAt,
                    ];

                    if ($previous && $item['component'] === BidDocument::COMPONENT_TECHNICAL) {
                        if ($modifying) {
                            $replaced[] = ['requirement' => $previous->label, 'file' => $previous->original_name, 'sha256' => $previous->sha256, 'path' => $previous->file_path];
                        }
                        // Keep the stable document row; the event stream retains every old file version.
                        $previous->update($documentAttributes);
                        $document = $previous;
                    } else {
                        if ($previous && $modifying) {
                            $replaced[] = ['requirement' => $previous->label, 'file' => $previous->original_name, 'sha256' => $previous->sha256, 'path' => $previous->file_path];
                            $previous->delete();
                        } elseif ($previous) {
                            app(FinancialBidFile::class)->delete($previous->file_path);
                            $previous->delete();
                        }
                        $document = $bid->documents()->create($documentAttributes);
                    }

                    if ($item['component'] === BidDocument::COMPONENT_TECHNICAL) {
                        $version = ((int) $document->reviewEvents()->max('version')) + 1;
                        $document->reviewEvents()->create([
                            'bid_id' => $bid->id,
                            'requirement_key' => $key,
                            'version' => $version,
                            'status' => BidDocumentReviewEvent::STATUS_PENDING,
                            'file_path' => $document->file_path,
                            'original_name' => $document->original_name,
                            'sha256' => $document->sha256,
                            'actor_id' => $bidder->id,
                            'uploaded_at' => now(),
                        ]);
                    }
                }
                $bid->unsetRelation('documents');

                $fileSummary = fn () => $bid->documents()->get()
                    ->map(fn (BidDocument $document) => [
                        'component' => $document->component,
                        'requirement' => $document->label,
                        'file' => $document->original_name,
                        'sha256' => $document->sha256,
                    ])->values()->all();

                if ($electronic && $modifying) {
                    // Labelled a modification; its receipt time is the official time of submission.
                    $count = $bid->trackings()->where('decision', 'modified')->count() + 1;
                    $bid->update(['receipt_no' => $this->receiptNumber($bid).'-M'.$count]);
                    $this->workflow->record(
                        $bid,
                        'submitted',
                        'modified',
                        $bidder->id,
                        null,
                        'Bid Modification Received',
                        'Your modified bid was received electronically. Receipt No. '.$bid->receipt_no.'. It replaces Receipt No. '.$superseded['receipt_no'].', which stays on record; the time of this receipt is your official time of submission.',
                        [
                            'channel' => Bid::CHANNEL_ELECTRONIC,
                            'receipt_no' => $bid->receipt_no,
                            'modification' => $count,
                            'previous_receipt_no' => $superseded['receipt_no'],
                            'previous_submitted_at' => $superseded['submitted_at'],
                            'replaced_files' => array_map(fn (array $file) => array_diff_key($file, ['path' => true]), $replaced),
                            'files' => $fileSummary(),
                        ]
                    );
                } elseif ($electronic) {
                    $bid->update(['receipt_no' => $this->receiptNumber($bid)]);
                    $this->workflow->recordElectronicSubmission($bid, $fileSummary());
                } else {
                    $this->workflow->record(
                        $bid,
                        'submitted',
                        'draft_saved',
                        $bidder->id,
                        null,
                        'Draft Record Saved',
                        'Saved on this website as a draft / supplementary record. It is not an official bid: submit your sealed bid to the BAC Secretariat before the deadline.',
                        notify: false
                    );
                }

                AuditLog::log($modifying ? 'bid_modified_electronic' : ($electronic ? 'bid_submitted_electronic' : 'bid_draft_saved'), $bid, $modifying ? $superseded + ['replaced_files' => $replaced] : null, [
                    'project_id' => $project->id,
                    'bid_amount' => $bid->bid_amount,
                    'receipt_no' => $bid->receipt_no,
                    'files' => array_keys($files),
                ], ['user_id' => $bidder->id]);

                return $bid;
            });
        } catch (\Throwable $exception) {
            foreach ($stored as $path) {
                app(FinancialBidFile::class)->delete($path);
            }

            throw $exception;
        }

        return $bid->fresh(['documents']);
    }

    /**
     * @throws ValidationException
     */
    public function assertBeforeDeadline(Project $project): void
    {
        $deadline = $project->bidSubmissionDeadline();

        if (! $project->isOpenForBidding()) {
            $when = $deadline ? ' on '.$deadline->copy()->timezone(config('bac-office.display_timezone'))->format('M d, Y h:i A') : '';

            throw ValidationException::withMessages([
                'deadline' => 'Bid submission for this project closed'.$when.'. Late bids are not accepted.',
            ]);
        }
    }

    /**
     * The bidding documents fee is paid over the counter at the BAC; the bid
     * is accepted only after the BAC records the bidder's Official Receipt.
     *
     * @throws ValidationException
     */
    public function assertBiddingFeePaid(Project $project, User $bidder): void
    {
        if ($project->hasPaidBiddingFee($bidder)) {
            return;
        }

        throw ValidationException::withMessages([
            'payment' => 'Pay the bidding documents fee of ₱'.number_format((float) $project->bidding_documents_fee, 2)
                .' at the '.$project->paymentVenueLabel().' first. You can submit your bid once the BAC records your Official Receipt.',
        ]);
    }

    /**
     * Two decimals, exactly as entered (commas removed); never recomputed.
     *
     * @throws ValidationException
     */
    public function normalizeAmount(string $amount): string
    {
        $clean = str_replace([',', ' '], '', trim($amount));

        if (! preg_match('/^\d{1,13}(\.\d{1,2})?$/', $clean) || (float) $clean <= 0) {
            throw ValidationException::withMessages(['bid_amount' => 'Enter your bid price as an amount with up to two decimal places.']);
        }

        [$whole, $decimals] = array_pad(explode('.', $clean), 2, '');

        return (ltrim($whole, '0') ?: '0').'.'.str_pad($decimals, 2, '0');
    }

    /**
     * Bids above the Approved Budget for the Contract are not accepted.
     *
     * @throws ValidationException
     */
    public function assertWithinAbc(Project $project, string $amount): void
    {
        $abc = number_format((float) $project->budget, 2, '.', '');

        if ((float) $abc > 0 && $this->compareDecimals($amount, $abc) > 0) {
            throw ValidationException::withMessages([
                'bid_amount' => 'Your bid price of ₱'.number_format((float) $amount, 2).' exceeds the Approved Budget for the Contract of ₱'
                    .number_format((float) $abc, 2).'. Bids above the ABC are not accepted.',
            ]);
        }
    }

    /**
     * An official bid can be modified only online, before the deadline (checked
     * separately), and only while the BAC has recorded no decision on it.
     *
     * @throws ValidationException
     */
    private function assertModifiable(Bid $bid, bool $electronic): void
    {
        if (! $electronic || $bid->submission_channel !== Bid::CHANNEL_ELECTRONIC) {
            throw ValidationException::withMessages(['bid' => 'You already submitted an official bid for this project. To modify a sealed bid, bring a sealed modification marked "Modification" to the BAC Secretariat before the deadline.']);
        }
        if (! $bid->isModifiableOnline()) {
            throw ValidationException::withMessages(['bid' => 'This bid can no longer be modified.']);
        }
    }

    /**
     * @param  list<string>  $onRecord  requirement keys already filed (a modification keeps them)
     *
     * @throws ValidationException
     */
    private function assertFiles(BidSubmissionRequirements $requirements, array $files, bool $electronic, array $onRecord = []): void
    {
        $errors = [];

        foreach ($files as $key => $file) {
            if ($requirements->find((string) $key) === null) {
                $errors['documents.'.$key] = 'This file does not match any requirement of this project.';
            }
        }

        // A manual-submission draft may be partial: the sealed bid is the official copy.
        if ($electronic) {
            $missing = $requirements->items()
                ->where('required', true)
                ->reject(fn (array $item) => isset($files[$item['key']]) || in_array($item['key'], $onRecord, true));

            foreach ($missing as $item) {
                $errors['documents.'.$item['key']] = 'Required: '.$item['label'].'.';
            }

            if ($missing->isNotEmpty()) {
                $errors['documents'] = 'Missing required documents: '.$missing->pluck('label')->implode('; ').'.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function compareDecimals(string $left, string $right): int
    {
        if (function_exists('bccomp')) {
            return bccomp($left, $right, 2);
        }

        return (int) round(((float) $left - (float) $right) * 100) <=> 0;
    }

    private function receiptNumber(Bid $bid): string
    {
        return sprintf('BAC-%s-%04d-E%05d', now()->format('Y'), $bid->project_id, $bid->id);
    }
}
