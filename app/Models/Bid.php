<?php

namespace App\Models;

use App\Support\BidProgress;
use App\Support\BidSubmissionRequirements;
use App\Support\Uploads;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Concerns\MasksFutureProcurementEvents;
use Illuminate\Database\Eloquent\Model;

class Bid extends Model
{
    use MasksFutureProcurementEvents;

    protected static function booted(): void
    {
        static::addGlobalScope('known_as_of_demo_clock', function (Builder $query): void {
            $clock = app(\App\Support\ProcurementClock::class);
            if (! $clock->demoModeEnabled()) return;
            $at = $clock->now();
            $query->where('created_at', '<=', $at)
                ->where(fn (Builder $visible) => $visible->whereNull('submitted_at')->orWhere('submitted_at', '<=', $at));
        });
    }
    public function workflowStepAsOf($rawStep = null): string
    {
        $rawUpdatedAt = $this->getRawOriginal('workflow_step_updated_at');
        if ($rawUpdatedAt === null || ! \Carbon\CarbonImmutable::parse($rawUpdatedAt, config('app.timezone', 'Asia/Manila'))
                ->greaterThan(app(\App\Support\ProcurementClock::class)->now())) {
            return (string) ($rawStep ?: self::STEP_SUBMITTED);
        }

        return match (true) {
            $this->project_completed_at !== null => self::STEP_PROJECT_COMPLETED,
            $this->disqualified_at !== null => self::STEP_DISQUALIFIED,
            $this->notice_to_proceed_at !== null => self::STEP_NOTICE_TO_PROCEED,
            $this->contract_signed_at !== null => self::STEP_CONTRACT_SIGNED,
            $this->notice_of_award_at !== null => self::STEP_NOTICE_OF_AWARD,
            $this->award_decision_at !== null && $this->award_decision === self::AWARD_DECISION_APPROVED => self::STEP_AWARDED,
            $this->award_decision_at !== null && $this->award_decision === self::AWARD_DECISION_DISAPPROVED => self::STEP_NOT_AWARDED,
            $this->bac_recommended_at !== null => self::STEP_RECOMMENDED,
            $this->post_qualification_completed_at !== null => self::STEP_POST_QUALIFIED,
            $this->post_qualification_at !== null => self::STEP_POST_QUALIFICATION,
            $this->evaluated_at !== null => self::STEP_EVALUATED,
            $this->bac_evaluation_at !== null => self::STEP_FOR_BAC_EVALUATION,
            $this->documents_validated_at !== null => self::STEP_DOCUMENTS_VALIDATED,
            $this->submitted_at !== null => self::STEP_SUBMITTED,
            default => self::STEP_SUBMITTED,
        };
    }

    public function getWorkflowStepAttribute($value): string
    {
        return $this->workflowStepAsOf($value);
    }

    public function getStatusAttribute($value): ?string
    {
        $step = $this->workflowStepAsOf($this->attributes['workflow_step'] ?? null);
        $updatedAt = $this->getRawOriginal('workflow_step_updated_at');
        $isRewound = $updatedAt !== null && \Carbon\CarbonImmutable::parse($updatedAt, config('app.timezone', 'Asia/Manila'))
            ->greaterThan(app(\App\Support\ProcurementClock::class)->now());
        if (! $isRewound) return $value;

        return match ($step) {
            self::STEP_DISQUALIFIED, self::STEP_NOT_AWARDED => 'rejected',
            self::STEP_AWARDED, self::STEP_NOTICE_OF_AWARD, self::STEP_CONTRACT_SIGNED, self::STEP_NOTICE_TO_PROCEED, self::STEP_PROJECT_COMPLETED => 'awarded',
            self::STEP_DOCUMENTS_VALIDATED, self::STEP_FOR_BAC_EVALUATION, self::STEP_EVALUATED, self::STEP_POST_QUALIFICATION, self::STEP_POST_QUALIFIED, self::STEP_RECOMMENDED => 'approved',
            default => 'pending',
        };
    }
    protected function futureProcurementEventFields(): array
    {
        return [
            'created_at', 'submitted_at', 'workflow_step_updated_at', 'eligibility_reviewed_at',
            'documents_validated_at', 'bac_evaluation_at', 'approved_at', 'disqualified_at',
            'awarded_at', 'notice_of_award_at', 'notice_to_proceed_at', 'project_completed_at',
            'evaluated_at', 'post_qualification_at', 'post_qualification_completed_at',
            'bac_recommended_at', 'award_decision_at', 'contract_signed_at', 'technical_scored_at',
            'financial_opened_at',
        ];
    }
    public const ELIGIBILITY_PENDING = 'pending';
    public const ELIGIBILITY_VALID = 'valid';
    public const ELIGIBILITY_INVALID = 'invalid';

    // Workflow step constants
    public const STEP_SUBMITTED = 'submitted';
    public const STEP_PENDING_VALIDATION = 'pending_validation';
    public const STEP_DOCUMENTS_VALIDATED = 'documents_validated';
    public const STEP_FOR_BAC_EVALUATION = 'for_bac_evaluation';
    public const STEP_APPROVED = 'approved';
    public const STEP_DISQUALIFIED = 'disqualified';
    public const STEP_AWARDED = 'awarded';
    public const STEP_NOT_AWARDED = 'not_awarded';
    public const STEP_NOTICE_OF_AWARD = 'notice_of_award';
    public const STEP_NOTICE_TO_PROCEED = 'notice_to_proceed';
    public const STEP_PROJECT_COMPLETED = 'project_completed';
    public const STEP_EVALUATED = 'evaluated';
    public const STEP_POST_QUALIFICATION = 'post_qualification';
    public const STEP_POST_QUALIFIED = 'post_qualified';
    public const STEP_RECOMMENDED = 'recommended';
    public const STEP_CONTRACT_SIGNED = 'contract_signed';

    public const POST_QUALIFICATION_PASSED = 'passed';
    public const POST_QUALIFICATION_FAILED = 'failed';

    public const AWARD_DECISION_APPROVED = 'approved';
    public const AWARD_DECISION_DISAPPROVED = 'disapproved';

    /** An approved award the HoPE later cancelled before contract signing. */
    public const AWARD_DECISION_CANCELLED = 'cancelled';

    /** Official electronic submission (project authorized for it). */
    public const CHANNEL_ELECTRONIC = 'electronic';

    /** Manual submission through the BAC Secretariat; official once receipt is recorded. */
    public const CHANNEL_MANUAL = 'manual';

    /** Bids accepted through the website before submission channels existed. */
    public const CHANNEL_LEGACY = 'website_legacy';

    // Internal workflow_step labels (admin/staff). Bidders see BidProgress
    // labels instead. STEP_APPROVED is a legacy value: it was set when
    // documents were approved, so it never means the bid won.
    public const WORKFLOW_STEPS = [
        self::STEP_SUBMITTED => 'Bid Submitted',
        self::STEP_PENDING_VALIDATION => 'Pending Validation',
        self::STEP_DOCUMENTS_VALIDATED => 'Passed Preliminary Examination',
        self::STEP_FOR_BAC_EVALUATION => 'Under Evaluation',
        self::STEP_APPROVED => 'Approved for Evaluation (legacy)',
        self::STEP_EVALUATED => 'Evaluated',
        self::STEP_POST_QUALIFICATION => 'Under Post-Qualification',
        self::STEP_POST_QUALIFIED => 'Post-Qualified',
        self::STEP_RECOMMENDED => 'Recommended for Award',
        self::STEP_DISQUALIFIED => 'Disqualified',
        self::STEP_AWARDED => 'Award Approved',
        self::STEP_NOT_AWARDED => 'Not Awarded',
        self::STEP_NOTICE_OF_AWARD => 'Notice of Award Issued',
        self::STEP_CONTRACT_SIGNED => 'Contract Signed',
        self::STEP_NOTICE_TO_PROCEED => 'Notice to Proceed Issued',
        self::STEP_PROJECT_COMPLETED => 'Project Completed',
    ];

    public const REQUIRED_DOCUMENT_CHECKS = [
        [
            'key' => 'business_permit',
            'label' => 'Business Permit',
            'document_types' => ['Business Permit'],
        ],
        [
            'key' => 'philgeps_registration',
            'label' => 'PhilGEPS Registration',
            'document_types' => ['PhilGEPS Certificate'],
        ],
        [
            'key' => 'mayors_permit',
            'label' => "Mayor's Permit",
            'document_types' => ["Mayor's Permit", 'Business Permit'],
        ],
        [
            'key' => 'tax_clearance',
            'label' => 'Tax Clearance',
            'document_types' => ['Tax Clearance', 'Audited Financial Statement'],
        ],
        [
            'key' => 'eligibility_file',
            'label' => 'Eligibility Document',
            'bid_file' => 'eligibility',
        ],
        [
            'key' => 'proposal_file',
            'label' => 'Proposal File',
            'bid_file' => 'proposal',
        ],
        [
            'key' => 'other_required_attachments',
            'label' => 'Other required attachments',
            'document_types' => ['DTI/SEC Registration', 'PCAB License'],
        ],
    ];

    protected $fillable = [
        'project_id',
        'user_id',
        'bid_amount',
        'proposal_file',
        'eligibility_file',
        'status',
        'eligibility_status',
        'eligibility_reviewed_at',
        'eligibility_reviewed_by',
        'workflow_step',
        'workflow_step_updated_at',
        'workflow_step_updated_by',
        'documents_validated_at',
        'documents_validated_by',
        'bac_evaluation_at',
        'bac_evaluation_by',
        'approved_at',
        'approved_by',
        'disqualified_at',
        'disqualified_by',
        'awarded_at',
        'awarded_by',
        'notice_of_award_at',
        'notice_of_award_by',
        'notice_to_proceed_at',
        'notice_to_proceed_by',
        'project_completed_at',
        'project_completed_by',
        'evaluated_at',
        'evaluated_by',
        'post_qualification_at',
        'post_qualification_by',
        'post_qualification_result',
        'post_qualification_completed_at',
        'bac_recommended_at',
        'bac_recommended_by',
        'award_decision',
        'award_decision_at',
        'award_decision_by',
        'performance_security_at',
        'contract_signed_at',
        'contract_signed_by',
        'disqualified_stage',
        'notes',
        'rejection_reason',
        'submission_channel',
        'submitted_at',
        'financial_opening_password_hash',
        'financial_password_attempts',
        'financial_password_locked_until',
        'receipt_no',
        'submission_received_by',
        'bac_resolution_no',
        'bac_resolution_date',
    ];

    protected $hidden = ['financial_opening_password_hash'];

    protected $casts = [
        'financial_password_locked_until' => 'datetime',
        'bid_amount' => 'decimal:2',
        'financial_opened_at' => 'datetime',
        'financial_opened_by' => 'integer',
        'technical_score' => 'decimal:4',
        'technical_scored_at' => 'datetime',
        'technical_scored_by' => 'integer',
        'bac_resolution_date' => 'date',
        'eligibility_reviewed_at' => 'datetime',
        'workflow_step_updated_at' => 'datetime',
        'documents_validated_at' => 'datetime',
        'bac_evaluation_at' => 'datetime',
        'approved_at' => 'datetime',
        'disqualified_at' => 'datetime',
        'awarded_at' => 'datetime',
        'notice_of_award_at' => 'datetime',
        'notice_to_proceed_at' => 'datetime',
        'project_completed_at' => 'datetime',
        'evaluated_at' => 'datetime',
        'post_qualification_at' => 'datetime',
        'post_qualification_completed_at' => 'datetime',
        'bac_recommended_at' => 'datetime',
        'award_decision_at' => 'datetime',
        'performance_security_at' => 'date',
        'contract_signed_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    /**
     * Bids the BAC recommended and the HoPE approved that do not have an award
     * record (certificate) yet. Only these can be "declared the winner".
     */
    public function scopeAwaitingAwardRecord(Builder $query): Builder
    {
        // Approved, Notice of Award not issued yet. The approval already handed
        // off an award record, so the award itself is not the test.
        return $query->whereNotNull('bac_recommended_at')
            ->where('award_decision', self::AWARD_DECISION_APPROVED)
            ->whereNull('disqualified_at')
            ->whereNull('notice_of_award_at');
    }

    public function getAmountAttribute(): mixed
    {
        return $this->bid_amount;
    }

    public function setAmountAttribute(string|int|float|null $value): void
    {
        $this->attributes['bid_amount'] = $value;
    }

    public function getProposalUrlAttribute(): ?string
    {
        return Uploads::url($this->proposal_file);
    }

    public function getProposalFilenameAttribute(): ?string
    {
        return Uploads::fileName($this->proposal_file);
    }

    public function getProposalExtensionAttribute(): ?string
    {
        return Uploads::extension($this->proposal_file);
    }

    public function getProposalIsPdfAttribute(): bool
    {
        return $this->proposal_extension === 'pdf';
    }

    public function getEligibilityUrlAttribute(): ?string
    {
        return Uploads::url($this->eligibility_file);
    }

    public function getEligibilityFilenameAttribute(): ?string
    {
        return Uploads::fileName($this->eligibility_file);
    }

    public function getEligibilityExtensionAttribute(): ?string
    {
        return Uploads::extension($this->eligibility_file);
    }

    public function getEligibilityStatusLabelAttribute(): string
    {
        return match ($this->eligibility_status) {
            self::ELIGIBILITY_VALID => 'Valid',
            self::ELIGIBILITY_INVALID => 'Invalid',
            default => 'Pending Review',
        };
    }

    public function getWorkflowStepLabelAttribute(): string
    {
        return self::WORKFLOW_STEPS[$this->workflow_step] ?? 'Unknown';
    }

    /**
     * Bidder-facing progress built only from recorded BAC / LGU actions.
     */
    public function progress(): BidProgress
    {
        return BidProgress::for($this);
    }

    /**
     * Compact timeline for the staff review modal and broadcasts. Derived from
     * BidProgress so every screen agrees on which milestones really happened.
     */
    public function getWorkflowTimelineSteps(): array
    {
        $steps = [];

        foreach ($this->progress()->stages() as $stage) {
            $state = $stage['state'];

            $steps[$stage['key']] = [
                'label' => $stage['label'],
                'completed' => $state === 'done',
                'current' => $state === 'current',
                'state' => $state,
                'icon' => self::STAGE_ICONS[$stage['key']] ?? 'fa-circle',
                'time' => $stage['at'] ?? $stage['description'],
                'status' => match ($state) {
                    'done' => 'completed',
                    'failed' => 'rejected',
                    default => 'pending',
                },
                'verified' => $state === 'done',
            ];
        }

        return $steps;
    }

    // Accessor for broadcast/API
    public function getWorkflowTimelineStepsAttribute(): array
    {
        return $this->getWorkflowTimelineSteps();
    }

    private const STAGE_ICONS = [
        BidProgress::STAGE_SUBMITTED => 'fa-file-signature',
        BidProgress::STAGE_PRELIMINARY => 'fa-folder-open',
        BidProgress::STAGE_EVALUATION => 'fa-scale-balanced',
        BidProgress::STAGE_POST_QUALIFICATION => 'fa-user-check',
        BidProgress::STAGE_RECOMMENDATION => 'fa-gavel',
        BidProgress::STAGE_AWARD_APPROVAL => 'fa-stamp',
        BidProgress::STAGE_NOTICE_OF_AWARD => 'fa-envelope-open-text',
        BidProgress::STAGE_CONTRACT_SIGNED => 'fa-file-contract',
        BidProgress::STAGE_NOTICE_TO_PROCEED => 'fa-person-digging',
    ];

    /**
     * Where each requirement listed on a project (Project wizard > Required
     * Documents) can be found among the bidder's documents or this bid's files.
     */
    public const REQUIREMENT_EVIDENCE = [
        'Business Permit' => ['document_types' => ['Business Permit', "Mayor's Permit"]],
        "Mayor's Permit" => ['document_types' => ["Mayor's Permit", 'Business Permit']],
        'PhilGEPS Registration' => ['document_types' => ['PhilGEPS Certificate']],
        'DTI/SEC Registration' => ['document_types' => ['DTI/SEC Registration']],
        'Tax Clearance' => ['document_types' => ['Tax Clearance']],
        'Omnibus Sworn Statement' => ['document_types' => ['Omnibus Sworn Statement'], 'bid_files' => ['eligibility']],
        'Technical Proposal' => ['bid_files' => ['proposal']],
        'Financial Proposal' => ['bid_files' => ['proposal']],
        'Company Profile' => ['document_types' => ['Company Profile']],
        'PCAB License' => ['document_types' => ['PCAB License']],
        'Other BAC Required Documents' => ['document_types' => ['Other Supporting Documents'], 'bid_files' => ['eligibility']],
    ];

    /**
     * Requirements to check at preliminary examination. Uses the project's own
     * required documents; REQUIRED_DOCUMENT_CHECKS is only the fallback for
     * projects created without a requirements list.
     *
     * "submitted" only means a file exists. Whether it complies is decided by
     * the reviewer, who must verify each item before a bid can pass.
     */
    public function documentChecklist(): array
    {
        if (in_array($this->submission_channel, [self::CHANNEL_ELECTRONIC, self::CHANNEL_MANUAL], true)) {
            return $this->submissionChecklist();
        }

        // Legacy bids (two files + bidder profile documents).
        $documents = $this->user?->relationLoaded('bidderDocuments')
            ? $this->user->bidderDocuments
            : ($this->user?->bidderDocuments()->get() ?? collect());

        $documents = $documents
            ->filter(fn ($document): bool => ($document->is_current ?? true) && ($document->review_status ?? null) !== 'needs_action')
            ->values();

        return collect($this->requirementDefinitions())
            ->map(function (array $check) use ($documents) {
                $matchedDocument = $documents->first(
                    fn ($document) => in_array($document->document_type, $check['document_types'] ?? [], true)
                );

                if ($matchedDocument) {
                    return $this->checklistRow($check, true, $matchedDocument->display_name, $matchedDocument->document_type, $matchedDocument->id);
                }

                foreach ($check['bid_files'] ?? [] as $bidFile) {
                    if ($bidFile === 'proposal' && filled($this->proposal_file)) {
                        return $this->checklistRow($check, true, $this->proposal_filename, 'Proposal File', null, 'proposal');
                    }

                    if ($bidFile === 'eligibility' && filled($this->eligibility_file)) {
                        return $this->checklistRow($check, true, $this->eligibility_filename, 'Eligibility Document', null, 'eligibility');
                    }
                }

                return $this->checklistRow($check, false, null, null, null, ($check['bid_files'] ?? [null])[0] ?? null);
            })
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string, document_types?: array, bid_files?: array}>
     */
    public function requirementDefinitions(): array
    {
        $project = $this->project;
        $requirement = $project?->relationLoaded('requirement') ? $project->requirement : $project?->requirement()->first();
        $required = collect($requirement?->required_documents ?? [])->filter()->unique()->values();

        if ($required->isEmpty()) {
            return collect(self::REQUIRED_DOCUMENT_CHECKS)
                ->map(fn (array $check) => [
                    'key' => $check['key'],
                    'label' => $check['label'],
                    'document_types' => $check['document_types'] ?? [],
                    'bid_files' => isset($check['bid_file']) ? [$check['bid_file']] : [],
                ])
                ->all();
        }

        return $required
            ->map(fn (string $label) => [
                'key' => \Illuminate\Support\Str::slug($label, '_'),
                'label' => $label,
                'document_types' => self::REQUIREMENT_EVIDENCE[$label]['document_types'] ?? [$label],
                'bid_files' => self::REQUIREMENT_EVIDENCE[$label]['bid_files'] ?? [],
            ])
            ->all();
    }

    private function checklistRow(array $check, bool $submitted, ?string $fileName, ?string $documentType, ?int $documentId, ?string $bidFile = null): array
    {
        return [
            'key' => $check['key'],
            'label' => $check['label'],
            'submitted' => $submitted,
            'file_name' => $fileName,
            'document_type' => $documentType,
            'document_id' => $documentId,
            'component' => $bidFile === 'proposal' || str_contains(strtolower($check['label']), 'financial') ? 'financial' : 'technical',
            'is_proposal' => $bidFile === 'proposal',
            'is_eligibility_file' => $bidFile === 'eligibility',
        ];
    }

    /**
     * Checklist from the project's bid submission requirements
     * (BidSubmissionRequirements), with evidence from this bid's files.
     *
     * Electronic bids: "submitted" is whether the file was received.
     * Manual bids: the official documents are in the sealed envelopes held by
     * the BAC Secretariat, so presence is unknown to the system (null) and is
     * confirmed by the reviewer; any website upload is only a draft copy.
     */
    private function submissionChecklist(): array
    {
        $files = $this->relationLoaded('documents') ? $this->documents : $this->documents()->get();
        $files = $files->keyBy('requirement_key');
        $manual = $this->submission_channel === self::CHANNEL_MANUAL;

        return BidSubmissionRequirements::for($this->project)->items()
            ->map(function (array $item) use ($files, $manual) {
                $file = $files->get($item['key']);

                return [
                    'key' => $item['key'],
                    'label' => $item['label'],
                    'component' => $item['component'],
                    'required' => $item['required'],
                    'condition' => $item['condition'],
                    // A missing-document revision request creates a placeholder row. It becomes
                    // submitted only after the bidder uploads the replacement.
                    'submitted' => $manual ? null : ($file !== null && filled($file->file_path)),
                    'file_name' => $file?->original_name,
                    'document_type' => $manual ? 'Sealed envelope (BAC Secretariat)' : ($file ? 'Electronic submission' : null),
                    'document_id' => null,
                    'bid_document_id' => $file?->id,
                    'is_proposal' => false,
                    'is_eligibility_file' => false,
                ];
            })
            ->all();
    }

    /** Technical contents stay sealed until the scheduled opening is recorded. */
    public function isSealed(): bool
    {
        return $this->isDraft() || ! ($this->project?->bidsAreOpened() ?? false);
    }

    /** The financial component stays sealed until technical approval and recorded password opening. */
    public function isFinancialSealed(): bool
    {
        if ($this->isSealed()) return true;
        if (! $this->documents_validated_at || ! $this->documents_validated_by
            || $this->disqualified_at !== null || $this->status === 'rejected') return true;
        return $this->financial_opened_at === null || $this->financial_opened_by === null;
    }

    public function reviewChecklist(): array
    {
        return array_map(function (array $item): array {
            $sealed = ($item['component'] ?? 'technical') === 'financial'
                ? $this->isFinancialSealed() : $this->isSealed();
            $item['sealed'] = $sealed;
            if ($sealed) {
                $item['file_name'] = null;
                $item['document_id'] = null;
                $item['bid_document_id'] = null;
                $item['document_type'] = 'Sealed component';
            }
            return $item;
        }, $this->documentChecklist());
    }

    public function financialOpenedByUser()
    {
        return $this->belongsTo(User::class, 'financial_opened_by');
    }

    /**
     * A reviewer may see the presence/count of a sealed submission, but not
     * its price, contents, original filenames or download URL.
     *
     * @return array{key:string,label:string,required:int,received:int,missing:int,unknown:int,missing_labels:array<int,string>}
     */
    public function submissionDocumentStatus(): array
    {
        if ($this->isDraft()) {
            return [
                'key' => 'draft',
                'label' => 'Draft - not an official submission',
                'required' => 0,
                'received' => 0,
                'missing' => 0,
                'unknown' => 0,
                'missing_labels' => [],
            ];
        }

        $required = collect($this->documentChecklist())->filter(fn (array $item) => $item['required'] ?? true)->values();
        $missing = $required->filter(fn (array $item) => $item['submitted'] === false)->values();
        $unknown = $required->filter(fn (array $item) => $item['submitted'] === null)->values();
        $received = $required->filter(fn (array $item) => $item['submitted'] === true)->count();

        if ($unknown->isNotEmpty()) {
            return [
                'key' => 'sealed_envelope',
                'label' => 'Sealed envelope - verify at opening',
                'required' => $required->count(),
                'received' => $received,
                'missing' => $missing->count(),
                'unknown' => $unknown->count(),
                'missing_labels' => $missing->pluck('label')->values()->all(),
            ];
        }

        if ($missing->isNotEmpty()) {
            return [
                'key' => 'incomplete',
                'label' => 'Incomplete - '.$missing->count().' required '.str('upload')->plural($missing->count()).' missing',
                'required' => $required->count(),
                'received' => $received,
                'missing' => $missing->count(),
                'unknown' => 0,
                'missing_labels' => $missing->pluck('label')->values()->all(),
            ];
        }

        return [
            'key' => $this->isSealed() ? 'received_sealed' : 'complete',
            'label' => $this->isSealed() ? 'Complete - files sealed until opening' : 'Complete - all required uploads received',
            'required' => $required->count(),
            'received' => $received,
            'missing' => 0,
            'unknown' => 0,
            'missing_labels' => [],
        ];
    }

    public function hasSubmittedComponent(string $component): bool
    {
        return collect($this->documentChecklist())
            ->where('component', $component)
            ->contains(fn (array $item) => $item['submitted'] === true || $item['submitted'] === null);
    }

    /**
     * Official bid: submitted online, or (before submission became online-only)
     * recorded as received by the BAC Secretariat. Rows without a channel are
     * bids created before channels existed and stay submitted.
     */
    public function isOfficiallySubmitted(): bool
    {
        return $this->submission_channel === null || $this->submitted_at !== null || filled($this->receipt_no);
    }

    /** A draft saved before submission became online-only; not an official bid. */
    public function isDraft(): bool
    {
        return ! $this->isOfficiallySubmitted();
    }

    /**
     * An official online bid the bidder may still modify (RA 12009 IRR Sec. 55.1):
     * the BAC has recorded nothing on it yet. The deadline is checked separately.
     */
    public function isModifiableOnline(): bool
    {
        return ! $this->isDraft()
            && $this->submission_channel === self::CHANNEL_ELECTRONIC
            && in_array($this->workflow_step ?: self::STEP_SUBMITTED, [self::STEP_SUBMITTED, self::STEP_PENDING_VALIDATION], true)
            && $this->financial_opened_at === null;
    }

    /**
     * Whether the bidder can modify this bid right now: online, before the
     * deadline, and nothing past receipt recorded on it (for the bidder pages).
     */
    public function canBeModifiedNow(): bool
    {
        $project = $this->project;
        if (! $project || ! $project->isOpenForBidding() || ! $project->acceptsElectronicSubmission() || ! $this->isModifiableOnline()) {
            return false;
        }

        $progress = $this->progress()->toArray();

        return $progress['outcome'] === null
            && $progress['current']['tone'] === 'active'
            && in_array($progress['current']['key'], [BidProgress::STAGE_SUBMITTED, BidProgress::STAGE_PRELIMINARY], true);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BidDocument::class);
    }

    public function documentsAreComplete(): bool
    {
        return collect($this->documentChecklist())
            ->filter(fn (array $item) => $item['required'] ?? true)
            ->every(fn (array $item) => $item['submitted'] === true);
    }

    public function getDocumentsReviewStatusAttribute(): string
    {
        return $this->documentsAreComplete() ? 'complete' : 'incomplete';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function award(): HasOne
    {
        return $this->hasOne(Award::class);
    }

    public function trackings(): HasMany
    {
        return $this->hasMany(BidTracking::class, 'bid_id')->latest();
    }

    public function tracking(): HasMany
    {
        return $this->trackings();
    }

    // Workflow step updater relationships
    public function workflowStepUpdater()
    {
        return $this->belongsTo(User::class, 'workflow_step_updated_by');
    }

    public function documentsValidator()
    {
        return $this->belongsTo(User::class, 'documents_validated_by');
    }

    public function bacEvaluator()
    {
        return $this->belongsTo(User::class, 'bac_evaluation_by');
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function disqualifiedByUser()
    {
        return $this->belongsTo(User::class, 'disqualified_by');
    }

    public function awardedByUser()
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function noticeOfAwardByUser()
    {
        return $this->belongsTo(User::class, 'notice_of_award_by');
    }

    public function noticeToProceedByUser()
    {
        return $this->belongsTo(User::class, 'notice_to_proceed_by');
    }

    public function projectCompletedByUser()
    {
        return $this->belongsTo(User::class, 'project_completed_by');
    }

    // Helper to determine if bid is active (still in progress)
    public function isActive(): bool
    {
        return !in_array($this->workflow_step, [self::STEP_DISQUALIFIED, self::STEP_NOT_AWARDED, self::STEP_PROJECT_COMPLETED], true);
    }

    // Helper to check if bid is eligible for bidding (documents validated, not disqualified)
    public function isEligibleForBidding(): bool
    {
        return in_array($this->workflow_step, [
            self::STEP_DOCUMENTS_VALIDATED,
            self::STEP_FOR_BAC_EVALUATION,
            self::STEP_APPROVED,
            self::STEP_EVALUATED,
            self::STEP_POST_QUALIFICATION,
            self::STEP_POST_QUALIFIED,
            self::STEP_RECOMMENDED,
            self::STEP_AWARDED,
            self::STEP_NOTICE_OF_AWARD,
            self::STEP_CONTRACT_SIGNED,
            self::STEP_NOTICE_TO_PROCEED,
            self::STEP_PROJECT_COMPLETED,
        ], true);
    }
}
