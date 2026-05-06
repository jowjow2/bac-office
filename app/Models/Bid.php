<?php

namespace App\Models;

use App\Support\Uploads;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class Bid extends Model
{
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

    public const WORKFLOW_STEPS = [
        self::STEP_SUBMITTED => 'Bid Submitted',
        self::STEP_PENDING_VALIDATION => 'Pending Validation',
        self::STEP_DOCUMENTS_VALIDATED => 'Documents Validated',
        self::STEP_FOR_BAC_EVALUATION => 'For BAC Evaluation',
        self::STEP_APPROVED => 'Approved',
        self::STEP_DISQUALIFIED => 'Disqualified',
        self::STEP_AWARDED => 'Awarded',
        self::STEP_NOT_AWARDED => 'Not Awarded',
        self::STEP_NOTICE_OF_AWARD => 'Notice of Award Issued',
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
        'notes',
        'rejection_reason',
    ];

    protected $casts = [
        'bid_amount' => 'decimal:2',
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
    ];

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
     * Determine the effective workflow step based on bid data (for backward compatibility)
     */
    public function getEffectiveWorkflowStepAttribute(): string
    {
        // If workflow_step is explicitly set and not default, use it
        if ($this->workflow_step && $this->workflow_step !== self::STEP_SUBMITTED) {
            return $this->workflow_step;
        }

        // Fallback: derive from legacy fields
        if ($this->status === 'approved') {
            if ($this->award && in_array($this->award->status, [Award::STATUS_VALID, 'active'], true)) {
                return self::STEP_AWARDED;
            }
            return self::STEP_APPROVED;
        }

        if ($this->status === 'rejected') {
            return self::STEP_DISQUALIFIED;
        }

        if ($this->eligibility_status === self::ELIGIBILITY_VALID) {
            return self::STEP_DOCUMENTS_VALIDATED;
        }

        if ($this->eligibility_status === self::ELIGIBILITY_INVALID) {
            return self::STEP_DISQUALIFIED;
        }

        return self::STEP_PENDING_VALIDATION;
    }

    /**
     * Get the complete timeline steps for display
     */
    public function getWorkflowTimelineSteps(): array
    {
        $step = $this->effective_workflow_step;
        $orderedSteps = array_keys(self::WORKFLOW_STEPS);
        $currentIndex = array_search($step, $orderedSteps);

        $completedSteps = [];
        if ($currentIndex !== false) {
            $completedSteps = array_slice($orderedSteps, 0, $currentIndex);
        }

        $steps = [];
        foreach (self::WORKFLOW_STEPS as $key => $label) {
            $isCompleted = in_array($key, $completedSteps, true);
            $isCurrent = $key === $step;

            // Determine if this step was verified by an admin or staff
            $verified = false;
            if ($isCompleted || $isCurrent) {
                $verifier = null;
                switch ($key) {
                    case self::STEP_DOCUMENTS_VALIDATED:
                        $verifier = $this->documentsValidator;
                        break;
                    case self::STEP_FOR_BAC_EVALUATION:
                        $verifier = $this->bacEvaluator;
                        break;
                    case self::STEP_APPROVED:
                        $verifier = $this->approvedByUser;
                        break;
                    case self::STEP_DISQUALIFIED:
                        $verifier = $this->disqualifiedByUser;
                        break;
                    case self::STEP_AWARDED:
                        $verifier = $this->awardedByUser;
                        break;
                    case self::STEP_NOTICE_OF_AWARD:
                        $verifier = $this->noticeOfAwardByUser;
                        break;
                    case self::STEP_NOTICE_TO_PROCEED:
                        $verifier = $this->noticeToProceedByUser;
                        break;
                    case self::STEP_PROJECT_COMPLETED:
                        $verifier = $this->projectCompletedByUser;
                        break;
                }
                if ($key === self::STEP_PENDING_VALIDATION && $isCompleted) {
                    $verified = true;
                }

                if ($verifier && in_array($verifier->role, ['admin', 'staff'], true)) {
                    $verified = true;
                }
            }

            $steps[$key] = [
                'label' => $label,
                'completed' => $isCompleted || $isCurrent,
                'current' => $isCurrent,
                'icon' => $this->getWorkflowStepIcon($key),
                'time' => $this->getWorkflowStepTime($key),
                'status' => $this->getWorkflowStepStatusBadge($key),
                'verified' => $verified,
            ];
        }

        return $steps;
    }

    // Accessor for broadcast/API
    public function getWorkflowTimelineStepsAttribute(): array
    {
        return $this->getWorkflowTimelineSteps();
    }

    private function getWorkflowStepIcon(string $step): string
    {
        return match ($step) {
            self::STEP_SUBMITTED => 'fa-file-signature',
            self::STEP_PENDING_VALIDATION => 'fa-clock',
            self::STEP_DOCUMENTS_VALIDATED => 'fa-check-circle',
            self::STEP_FOR_BAC_EVALUATION => 'fa-users',
            self::STEP_APPROVED => 'fa-check-double',
            self::STEP_DISQUALIFIED => 'fa-circle-xmark',
            self::STEP_AWARDED => 'fa-trophy',
            self::STEP_NOT_AWARDED => 'fa-times-circle',
            self::STEP_NOTICE_OF_AWARD => 'fa-envelope-open-text',
            self::STEP_NOTICE_TO_PROCEED => 'fa-file-contract',
            self::STEP_PROJECT_COMPLETED => 'fa-flag-checkered',
            default => 'fa-question-circle',
        };
    }

    private function getWorkflowStepTime(string $step): string
    {
        return match ($step) {
            self::STEP_SUBMITTED => $this->created_at?->diffForHumans() ?? 'Recently',
            self::STEP_PENDING_VALIDATION => 'Pending document review',
            self::STEP_DOCUMENTS_VALIDATED => $this->documents_validated_at?->diffForHumans() ?? 'Awaiting validation',
            self::STEP_FOR_BAC_EVALUATION => $this->bac_evaluation_at?->diffForHumans() ?? 'Under BAC evaluation',
            self::STEP_APPROVED => $this->approved_at?->diffForHumans() ?? 'Approved',
            self::STEP_DISQUALIFIED => $this->disqualified_at?->diffForHumans() ?? 'Disqualified',
            self::STEP_AWARDED => $this->awarded_at?->diffForHumans() ?? ($this->award?->created_at?->diffForHumans() ?? 'Awarded'),
            self::STEP_NOT_AWARDED => 'Not awarded',
            self::STEP_NOTICE_OF_AWARD => $this->notice_of_award_at?->diffForHumans() ?? 'Notice issued',
            self::STEP_NOTICE_TO_PROCEED => $this->notice_to_proceed_at?->diffForHumans() ?? 'Notice issued',
            self::STEP_PROJECT_COMPLETED => $this->project_completed_at?->diffForHumans() ?? 'Completed',
            default => '-',
        };
    }

    private function getWorkflowStepStatusBadge(string $step): ?string
    {
        if ($step === self::STEP_SUBMITTED) {
            return 'submitted';
        }

        if (in_array($step, [self::STEP_PENDING_VALIDATION, self::STEP_FOR_BAC_EVALUATION], true)) {
            return 'pending';
        }

        if (in_array($step, [self::STEP_DOCUMENTS_VALIDATED, self::STEP_APPROVED, self::STEP_AWARDED, self::STEP_NOTICE_OF_AWARD, self::STEP_NOTICE_TO_PROCEED, self::STEP_PROJECT_COMPLETED], true)) {
            return 'completed';
        }

        if ($step === self::STEP_DISQUALIFIED || $step === self::STEP_NOT_AWARDED) {
            return $this->status === 'rejected' ? 'rejected' : 'pending';
        }

        return null;
    }

    public function documentChecklist(): array
    {
        $documents = $this->user?->relationLoaded('bidderDocuments')
            ? $this->user->bidderDocuments
            : ($this->user?->bidderDocuments()->get() ?? collect());

        return collect(self::REQUIRED_DOCUMENT_CHECKS)
            ->map(function (array $check) use ($documents) {
                if (($check['bid_file'] ?? null) === 'proposal') {
                    return [
                        'key' => $check['key'],
                        'label' => $check['label'],
                        'submitted' => filled($this->proposal_file),
                        'file_name' => $this->proposal_filename,
                        'document_type' => 'Proposal File',
                        'document_id' => null,
                        'is_proposal' => true,
                        'is_eligibility_file' => false,
                    ];
                }

                if (($check['bid_file'] ?? null) === 'eligibility') {
                    return [
                        'key' => $check['key'],
                        'label' => $check['label'],
                        'submitted' => filled($this->eligibility_file),
                        'file_name' => $this->eligibility_filename,
                        'document_type' => 'Eligibility Document',
                        'document_id' => null,
                        'is_proposal' => false,
                        'is_eligibility_file' => true,
                    ];
                }

                $matchedDocument = $documents->first(function ($document) use ($check) {
                    return in_array($document->document_type, $check['document_types'] ?? [], true);
                });

                return [
                    'key' => $check['key'],
                    'label' => $check['label'],
                    'submitted' => $matchedDocument !== null,
                    'file_name' => $matchedDocument?->display_name,
                    'document_type' => $matchedDocument?->document_type,
                    'document_id' => $matchedDocument?->id,
                    'is_proposal' => false,
                    'is_eligibility_file' => false,
                ];
            })
            ->all();
    }

    public function documentsAreComplete(): bool
    {
        return collect($this->documentChecklist())
            ->every(fn (array $item) => (bool) $item['submitted']);
    }

    public function getDocumentsReviewStatusAttribute(): string
    {
        return $this->documentsAreComplete() ? 'complete' : 'incomplete';
    }

    public function canBeValidatedByStaff(): bool
    {
        return $this->documentsAreComplete()
            && $this->eligibility_status === self::ELIGIBILITY_VALID;
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
        return $this->workflow_step === self::STEP_DOCUMENTS_VALIDATED
            || $this->workflow_step === self::STEP_FOR_BAC_EVALUATION
            || $this->workflow_step === self::STEP_APPROVED
            || $this->workflow_step === self::STEP_AWARDED
            || $this->workflow_step === self::STEP_NOTICE_OF_AWARD
            || $this->workflow_step === self::STEP_NOTICE_TO_PROCEED
            || $this->workflow_step === self::STEP_PROJECT_COMPLETED;
    }
}
