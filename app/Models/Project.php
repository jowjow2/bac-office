<?php

namespace App\Models;

use App\Models\Concerns\MasksFutureProcurementEvents;
use App\Support\ProcurementMode;
use App\Support\Uploads;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Project extends Model
{
    use MasksFutureProcurementEvents;

    protected function futureProcurementEventFields(): array
    {
        return ['bids_opened_at', 'failed_bidding_at', 'completed_at', 'published_at', 'philgeps_posted_at', 'archived_at'];
    }

    public function getStatusAttribute($value): ?string
    {
        $rawStatus = $this->attributes['status'] ?? $value;
        $at = app(\App\Support\ProcurementClock::class)->now();
        $rawOpening = $this->attributes['bids_opened_at'] ?? null;
        $rawFailure = $this->attributes['failed_bidding_at'] ?? null;
        $openingIsFuture = $rawOpening && Carbon::parse($rawOpening, config('app.timezone', 'Asia/Manila'))->isAfter($at);
        $failureIsFuture = $rawFailure && Carbon::parse($rawFailure, config('app.timezone', 'Asia/Manila'))->isAfter($at);
        $deadlineIsFuture = $this->bidSubmissionDeadline()?->isAfter($at) ?? false;

        if ($rawStatus === 'awarded' && $this->exists) {
            $noticeAt = \Illuminate\Support\Facades\DB::table('bids')->where('project_id', $this->getKey())->whereNotNull('notice_of_award_at')->max('notice_of_award_at');
            if ($noticeAt && Carbon::parse($noticeAt, config('app.timezone', 'Asia/Manila'))->isAfter($at)) {
                return $deadlineIsFuture ? 'open' : 'closed';
            }
        }
        if ($rawStatus === 'closed' && ($failureIsFuture || $openingIsFuture) && $deadlineIsFuture) return 'open';

        return $rawStatus;
    }
    public function getBidsOpenedByAttribute($value): ?int
    {
        $rawOpening = $this->attributes['bids_opened_at'] ?? null;
        if ($rawOpening && Carbon::parse($rawOpening, config('app.timezone', 'Asia/Manila'))->isAfter(app(\App\Support\ProcurementClock::class)->now())) {
            return null;
        }

        return $value === null ? null : (int) $value;
    }
    public const PUBLIC_STATUSES = ['open', 'closed', 'awarded'];

    protected $fillable = [
        'title',
        'slug',
        'reference_no',
        'description',
        'category',
        'location',
        'end_user_unit',
        'procurement_mode',
        'award_criterion',
        'evaluation_criteria',
        'quality_price_ratio',
        'evaluation_procedure',
        'bid_opening_venue',
        'financial_opening_stage',
        'financial_opening_at',
        'minimum_technical_score',
        'opening_documents_reference',
        'negotiation_ground',
        'source_of_fund',
        'contract_duration',
        'budget',
        'status',
        'published_at',
        'published_by',
        'archived_at',
        'created_by',
        'failed_bidding_at',
        'failed_bidding_by',
        'failed_bidding_reason',
        'rebid_project_id',
        'bids_opened_at',
        'bids_opened_by',
        'philgeps_reference_no',
        'submission_mode',
        'submission_venue',
        'electronic_submission_authority',
        'electronic_submission_authorized_at',
        'electronic_submission_authorized_by',
        'bidding_documents_fee',
        'bidding_fee_mode',
        'bidding_fee_reason',
        'payment_venue',
        'procurement_request_id',
        'legal_basis',
        'philgeps_url',
        'philgeps_posted_at',
        'philgeps_posted_recorded_by',
        'bid_security_required',
        'bid_security_notes',
        'completed_at',
        // Legacy single document fields
        'document_path',
        'document_original_name',
        // Backward compatibility deadline field
        'deadline',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'required_documents' => 'array',
        'evaluation_criteria' => 'array',
        'quality_price_ratio' => 'integer',
        'deadline' => 'datetime',
        'archived_at' => 'datetime',
        'failed_bidding_at' => 'datetime',
        'bids_opened_at' => 'datetime',
        'financial_opening_at' => 'datetime',
        'minimum_technical_score' => 'decimal:4',
        'electronic_submission_authorized_at' => 'datetime',
        'bidding_documents_fee' => 'decimal:2',
        'philgeps_posted_at' => 'date',
        'published_at' => 'datetime',
        'bid_security_required' => 'boolean',
        'completed_at' => 'datetime',
    ];

    /**
     * The law the procurement is conducted under. Projects started under
     * RA 9184 stay under its rules; new ones follow RA 12009 and its IRR
     * approved by GPPB Resolution No. 02-2025.
     */
    public const AWARD_CRITERIA = [
        'mearb' => 'Most Economically Advantageous Responsive Bid (MEARB)',
        'marb' => 'Most Advantageous Responsive Bid (MARB)',
        'lowest_calculated_bid' => 'Lowest calculated bid / applicable price-based criterion',
        'most_advantageous_bid' => 'Most advantageous bid under the bidding documents',
        'quality_based' => 'Quality-based or quality-and-cost criterion',
        'custom' => 'Other criterion stated in the project bidding documents',
    ];

    public const FINANCIAL_OPENING_STAGES = [
        'at_opening' => 'At recorded bid opening',
        'after_preliminary' => 'After preliminary examination',
        'after_evaluation_start' => 'When detailed evaluation begins',
    ];

    public const LEGAL_BASES = [
        'ra_12009' => 'RA 12009 (IRR approved by GPPB Resolution No. 02-2025)',
        'ra_9184' => 'RA 9184 (2016 Revised IRR)',
    ];

    /** Consulting services: how bids are evaluated (RA 12009 IRR Sec. 50.2(g)). */
    public const EVALUATION_PROCEDURES = [
        'qbe' => 'Quality-Based Evaluation (QBE)',
        'qcbe' => 'Quality-Cost Based Evaluation (QCBE)',
    ];

    /**
     * ABC above which the pre-procurement conference is mandatory before the
     * Invitation to Bid is published: RA 12009 IRR Sec. 49.1; RA 9184 IRR
     * Sec. 20.1 (optional at or below these amounts).
     */
    public const PRE_PROCUREMENT_THRESHOLDS = [
        'ra_12009' => ['goods' => 5000000, 'services' => 5000000, 'infrastructure' => 10000000, 'consultancy' => 2000000],
        'ra_9184' => ['goods' => 2000000, 'services' => 2000000, 'infrastructure' => 5000000, 'consultancy' => 1000000],
    ];

    /** ABC above which conferences are video-recorded and the opening livestreamed (RA 12009 IRR Sec. 38.3). */
    public const VIDEO_RECORDING_THRESHOLDS = [
        'goods' => 10000000, 'services' => 10000000, 'infrastructure' => 20000000, 'consultancy' => 5000000,
    ];

    protected $attributes = [
        'submission_mode' => self::SUBMISSION_ELECTRONIC,
    ];

    public const SUBMISSION_MANUAL = 'manual';

    public const SUBMISSION_ELECTRONIC = 'electronic';

    /** Upload directories and file prefixes that belong to bidders, never to the BAC. */
    private const BIDDER_UPLOAD_SEGMENTS = ['proposals', 'eligibility-documents', 'bidder-documents', 'bidder-registration-documents', 'certificates', 'bid-submissions'];

    private const BIDDER_FILE_PREFIXES = ['proposal_', 'eligibility_'];

    // Legacy deadline attribute casting (backward compatibility)
    protected $dates = [
        'deadline',
    ];

    // A project has many bids
    public function bids()
    {
        return $this->hasMany(Bid::class);
    }

    public function awards()
    {
        return $this->hasMany(Award::class);
    }

    /**
     * The new bidding round opened after this project's bidding failed.
     */
    public function rebidProject()
    {
        return $this->belongsTo(Project::class, 'rebid_project_id');
    }

    public function isFailedBidding(): bool
    {
        return $this->failed_bidding_at !== null;
    }

    public function biddingFeeAmendments(): HasMany
    {
        return $this->hasMany(BiddingFeeAmendment::class)->latest('id');
    }

    public function biddingFeeWaived(): bool
    {
        return $this->bidding_fee_mode === \App\Support\BiddingDocumentsFee::MODE_WAIVED;
    }

    public function biddingFeePayments(): HasMany
    {
        return $this->hasMany(BiddingFeePayment::class);
    }

    public function procurementRequest()
    {
        return $this->belongsTo(ProcurementRequest::class);
    }

    public function publishedBy()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isPublishedLocally(): bool
    {
        // Existing open/closed/awarded rows predate the local publication
        // timestamp. Keep them visible while new publications record it.
        return $this->published_at !== null || in_array($this->status, self::PUBLIC_STATUSES, true);
    }

    public function proceedings(): HasMany
    {
        return $this->hasMany(ProjectProceeding::class)->orderBy('occurred_at')->orderBy('id');
    }

    /**
     * Bids are submitted the way the project notice allows: online (official
     * on receipt) or manually as sealed bids at the BAC Secretariat, where the
     * website upload is only a draft until the Secretariat records receipt.
     */
    public function acceptsElectronicSubmission(): bool
    {
        return $this->submission_mode !== self::SUBMISSION_MANUAL;
    }

    public function submissionMethodLabel(): string
    {
        return $this->acceptsElectronicSubmission()
            ? 'Online, through this system'
            : 'Manual, sealed bids at the BAC Secretariat';
    }

    public function legalBasisLabel(): string
    {
        return $this->mode()->legalBasisLabel();
    }

    /** The procurement mode and the rules of the project's legal basis. */
    public function mode(): ProcurementMode
    {
        return ProcurementMode::of($this);
    }

    /**
     * Award criteria the Invitation to Bid may state for this project
     * (RA 12009 IRR Sec. 50.2(d) and (g)): LCRB, MEARB or MARB for goods and
     * infrastructure, the highest rated bid for consulting services. RA 9184
     * projects keep the lowest calculated / highest rated bid.
     *
     * @return array<string, string> key => label
     */
    public function awardCriteriaOptions(): array
    {
        return $this->mode()->isCompetitive()
            ? self::awardCriteriaFor($this->mode()->isRa12009(), $this->category)
            : [];
    }

    /** @return array<string, string> award criteria of a competitive bidding under the legal basis and category */
    public static function awardCriteriaFor(bool $ra12009, ?string $category): array
    {
        $consulting = $category === 'consultancy';

        return match (true) {
            $consulting => ['quality_based' => $ra12009 ? 'Highest Rated Responsive Bid (HRRB)' : 'Highest Rated Bid (quality-based / quality-cost based)'],
            $ra12009 => [
                'lowest_calculated_bid' => 'Lowest Calculated Responsive Bid (LCRB)',
                'mearb' => 'Most Economically Advantageous Responsive Bid (MEARB)',
                'marb' => 'Most Advantageous Responsive Bid (MARB)',
            ],
            default => ['lowest_calculated_bid' => 'Lowest Calculated Responsive Bid (LCRB)'],
        };
    }

    public function awardCriterionLabel(): ?string
    {
        return $this->award_criterion
            ? ($this->awardCriteriaOptions()[$this->award_criterion] ?? self::AWARD_CRITERIA[$this->award_criterion] ?? $this->award_criterion)
            : null;
    }

    /** MEARB and MARB state their criteria and weights in the notice (Sec. 50.2(e), (f)). */
    public function usesWeightedCriteria(): bool
    {
        return in_array($this->award_criterion, ['mearb', 'marb'], true);
    }

    /** The pre-procurement conference is mandatory before publication above the ABC threshold. */
    public function preProcurementConferenceRequired(): bool
    {
        $threshold = self::PRE_PROCUREMENT_THRESHOLDS[$this->mode()->isRa12009() ? 'ra_12009' : 'ra_9184'][$this->category ?: 'goods'] ?? null;

        return $this->mode()->isCompetitive() && $threshold !== null && (float) $this->budget > $threshold;
    }

    /** Video recording of conferences and livestreaming of the opening (RA 12009 IRR Sec. 38). */
    public function videoRecordingRequired(): bool
    {
        $threshold = self::VIDEO_RECORDING_THRESHOLDS[$this->category ?: 'goods'] ?? null;

        return $this->mode()->isCompetitive() && $this->mode()->isRa12009() && $threshold !== null && (float) $this->budget > $threshold;
    }

    public function modeLabel(): string
    {
        return $this->mode()->label();
    }

    /**
     * Competitive bidding is the only mode in this application that uses a
     * sealed submission followed by an explicit BAC opening event. RFQs,
     * negotiated offers and direct quotations follow the configured mode's
     * deadline/review rules instead of inheriting a competitive-bidding gate.
     */
    public function requiresRecordedBidOpening(): bool
    {
        return $this->mode()->isCompetitive();
    }

    public function submissionDeadlinePassed(?Carbon $at = null): bool
    {
        $deadline = $this->bidSubmissionDeadline();

        return $deadline !== null && $deadline->isPast($at ?? now(config('bac-office.display_timezone', 'Asia/Manila')));
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Status shown in the portal, including the states that are not stored
     * in the status column (failed bidding, completed).
     *
     * @return array{label: string, tone: string}
     */
    public function portalStatus(): array
    {
        return match (true) {
            $this->isFailedBidding() => ['label' => 'Failed bidding', 'tone' => 'danger'],
            $this->isCompleted() => ['label' => 'Completed', 'tone' => 'success'],
            default => match ($this->status) {
                'draft' => ['label' => 'Draft', 'tone' => 'neutral'],
                'approved_for_bidding' => ['label' => 'Approved for bidding', 'tone' => 'info'],
                'open' => match (true) {
                    $this->isScheduledForPublication() => ['label' => 'Scheduled for publication', 'tone' => 'info'],
                    // The deadline closed submissions; the status column changes at the opening.
                    $this->submissionDeadlinePassed() => $this->requiresRecordedBidOpening()
                        ? ['label' => 'Submission closed · awaiting opening', 'tone' => 'warning']
                        : ['label' => 'Submission closed', 'tone' => 'warning'],
                    default => ['label' => 'Open for bidding', 'tone' => 'success'],
                },
                'closed' => ['label' => 'Bidding closed', 'tone' => 'warning'],
                'awarded' => ['label' => 'Awarded', 'tone' => 'success'],
                default => ['label' => ucfirst(str_replace('_', ' ', (string) $this->status)), 'tone' => 'neutral'],
            },
        };
    }

    /**
     * When the project has a bidding documents fee, the bidder pays it at the
     * BAC office first and the BAC records the Official Receipt; only then is
     * the bid accepted. The fee and the bid security are separate: the bid
     * security is a document of the bid itself.
     */
    public function requiresBiddingFee(): bool
    {
        return (float) $this->bidding_documents_fee > 0;
    }

    public function biddingFeePaymentFor(User|int $bidder): ?BiddingFeePayment
    {
        $userId = $bidder instanceof User ? $bidder->id : $bidder;

        // Pending or rejected payments, or one without an OR reference, never count.
        return $this->relationLoaded('biddingFeePayments')
            ? $this->biddingFeePayments->first(fn (BiddingFeePayment $payment) => (int) $payment->user_id === (int) $userId && $payment->isVerified())
            : $this->biddingFeePayments()->where('user_id', $userId)
                ->where('status', BiddingFeePayment::STATUS_VERIFIED)
                ->whereNotNull('verified_at')
                ->whereNotNull('or_number')->where('or_number', '!=', '')
                ->first();
    }

    public function hasPaidBiddingFee(User|int $bidder): bool
    {
        return ! $this->requiresBiddingFee() || $this->biddingFeePaymentFor($bidder) !== null;
    }

    public function paymentVenueLabel(): string
    {
        return $this->payment_venue ?: 'BAC Secretariat Office';
    }

    /** Category codes used in the BAC solicitation number. */
    private const REFERENCE_CATEGORY_CODES = ['goods' => 'G', 'services' => 'GS', 'infrastructure' => 'I', 'consultancy' => 'C'];

    /**
     * Next BAC solicitation number, e.g. SJ-BAC-2026-I-004 (per year and category).
     */
    public static function nextReferenceNo(?string $category, ?\Carbon\CarbonInterface $at = null): string
    {
        $year = ($at ?? now())->format('Y');
        $prefix = 'SJ-BAC-'.$year.'-'.(self::REFERENCE_CATEGORY_CODES[$category] ?? 'X').'-';
        $sequence = self::where('reference_no', 'like', $prefix.'%')->count() + 1;

        do {
            $reference = $prefix.str_pad((string) $sequence++, 3, '0', STR_PAD_LEFT);
        } while (self::where('reference_no', $reference)->exists());

        return $reference;
    }

    /**
     * What still prevents posting this project, following the rules of its
     * mode and legal basis (see App\Support\ProcurementMode). Empty when it
     * can be published.
     *
     * Competitive bidding: Invitation to Bid posted on PhilGEPS for 7
     * calendar days before the deadline; a pre-bid conference at or above
     * the ABC threshold (₱3M under RA 12009 IRR Sec. 51.1, ₱1M under RA 9184
     * IRR Sec. 22.1), at least 12 calendar days before the deadline — and,
     * under RA 12009, not earlier than 7 calendar days after the posting;
     * bids opened immediately after the deadline, the same day.
     *
     * Alternative modes: the RFQ is posted for 3 calendar days only when the
     * rules require it (SVP above ₱200,000 under RA 12009, above ₱50,000
     * under RA 9184; Negotiated Procurement after two failed biddings). SVP
     * is capped by the LGU ceiling of RA 12009 IRR Sec. 34.2, and Shopping
     * only exists under RA 9184. No bid opening schedule is imposed.
     *
     * @return array<string, string> field => message
     */
    public function publicationBlockers(?Carbon $at = null, ?Carbon $publicationAt = null): array
    {
        $at ??= now(config('app.timezone', 'Asia/Manila'));
        $deadline = $this->bidSubmissionDeadline();
        $posted = $this->postingDateForReview($publicationAt);
        $budget = (float) $this->budget;
        $mode = $this->mode();
        $deadlineLabel = $mode->deadlineLabel();
        $blockers = [];

        if (blank($this->title) || blank($this->description)) {
            $blockers['title'] = 'Enter the project title and description.';
        }
        if ($budget <= 0) {
            $blockers['budget'] = 'Enter the Approved Budget for the Contract (ABC).';
        }
        if (blank($this->category) || blank($this->procurement_mode)) {
            $blockers['category'] = 'Select the procurement category and mode of procurement.';
        } elseif (! $mode->isAvailableUnderBasis()) {
            $blockers['procurement_mode'] = $mode->label().' is not a mode of procurement under RA 12009. Use Small Value Procurement or another mode of the 2025 IRR.';
        }

        $ceiling = $mode->svpCeiling();
        if ($ceiling !== null && $budget > $ceiling) {
            $blockers['budget'] = 'The ABC exceeds the Small Value Procurement ceiling of ₱'.number_format($ceiling, 2).' for this LGU (RA 12009 IRR Sec. 34.2). Use competitive bidding.';
        }

        if ($mode->isNegotiated() && $mode->negotiationGround() === null) {
            $blockers['negotiation_ground'] = 'Select the ground for Negotiated Procurement (for example, two failed biddings or an emergency).';
        }

        if ($deadline === null || ! $deadline->isAfter($at)) {
            $blockers['bid_submission_deadline'] = "Set a ".lcfirst($deadlineLabel)." in the future.";
        }

        if (in_array($this->status, ['draft', 'approved_for_bidding'], true) && $posted === null) {
            $blockers['date_posted'] = 'Set the local BAC publication date before publishing this project.';
        } elseif ($posted !== null && $posted->isFuture()) {
            $blockers['date_posted'] = 'The local BAC publication date cannot be in the future.';
        }

        if ($mode->isCompetitive()) {
            $hasInvitation = $this->officialDocuments()->contains(fn (ProjectDocument $document) => $document->document_type === 'invitation_to_bid');
            if (! $hasInvitation) {
                $blockers['project_documents'] = 'Upload the Invitation to Bid as an official project document.';
            }

            // Invitation to Bid contents (RA 12009 IRR Sec. 50.2).
            $criteria = $this->awardCriteriaOptions();
            if (! array_key_exists((string) $this->award_criterion, $criteria)) {
                $blockers['award_criterion'] = 'State the award criterion in the Invitation to Bid: '.implode(', ', $criteria).' (IRR Sec. 50.2(d)).';
            } elseif ($this->usesWeightedCriteria()) {
                $weights = collect($this->evaluation_criteria ?? [])->filter(fn ($row) => filled($row['name'] ?? null));
                if ($weights->isEmpty() || abs($weights->sum(fn ($row) => (float) ($row['weight'] ?? 0)) - 100) > 0.01) {
                    $blockers['evaluation_criteria'] = 'List the '.strtoupper($this->award_criterion).' evaluation criteria with weights totalling 100% (IRR Sec. 50.2(e), (f)).';
                }
                if ($this->award_criterion === 'mearb' && ($this->quality_price_ratio < 1 || $this->quality_price_ratio > 99)) {
                    $blockers['quality_price_ratio'] = 'State the MEARB quality-price ratio, for example 70% technical / 30% price (IRR Sec. 50.2(e)).';
                }
            }
            if ($this->category === 'consultancy' && ! array_key_exists((string) $this->evaluation_procedure, self::EVALUATION_PROCEDURES)) {
                $blockers['evaluation_procedure'] = 'State whether consulting bids are evaluated by QBE or QCBE (IRR Sec. 50.2(g)).';
            }
            if (blank($this->bid_opening_venue)) {
                $blockers['bid_opening_venue'] = 'State the place of the bid opening (IRR Sec. 50.2(h)).';
            }
            if ($this->acceptsElectronicSubmission() && blank($this->electronic_submission_authority)) {
                $blockers['electronic_submission_authority'] = 'Electronic bids need the certification of the official managing the LGU IT system, submitted to the GPPB-TSO before posting (IRR Sec. 50.3.3). Record it, or accept manual sealed bids.';
            }
            if ($this->preProcurementConferenceRequired() && ! $this->proceedings()->where('type', ProjectProceeding::TYPE_PRE_PROCUREMENT)->exists()) {
                $blockers['pre_procurement_conference'] = 'Record the pre-procurement conference before publishing: it is mandatory for this ABC (IRR Sec. 49.1).';
            }
        }

        // Impossible schedules block; the legal periods are only warnings (scheduleWarnings).
        $blockers += $this->scheduleConflicts($posted);

        return $blockers;
    }

    /**
     * What makes the schedule impossible, for posting and for every later
     * change: a missing bid opening or required pre-bid conference, or dates
     * out of order (opening before the deadline, pre-bid after it). Any day
     * or hour the BAC sets is accepted; the legal periods between the dates
     * are warnings (scheduleWarnings), not blockers.
     *
     * @return array<string, string> field => message
     */
    public function scheduleConflicts(?Carbon $posted = null): array
    {
        return $this->scheduleReview($posted)['conflicts'];
    }

    /**
     * Legal periods the schedule does not meet. The BAC may still go ahead
     * with its dates; the deviation is shown and recorded in the audit log.
     *
     * @return array<string, string> field => message
     */
    public function scheduleWarnings(?Carbon $posted = null): array
    {
        return $this->scheduleReview($posted)['warnings'];
    }

    /** The warnings in one sentence for a flash message, or null when the schedule meets every period. */
    public function scheduleWarningNote(?Carbon $publicationAt = null): ?string
    {
        $warnings = $this->scheduleWarnings($this->postingDateForReview($publicationAt));

        return $warnings === [] ? null : 'Schedule warning: '.implode(' ', $warnings).' The BAC\'s dates were kept and this is recorded in the audit log.';
    }

    /** The publication date the warnings are measured from: recorded, or now when publishing. */
    public function postingDateForReview(?Carbon $publicationAt = null): ?Carbon
    {
        $schedule = $this->relationLoaded('schedule') ? $this->schedule : $this->schedule()->first();

        return $this->published_at
            ? Carbon::parse($this->published_at)->startOfDay()
            : ($publicationAt?->copy()->timezone(config('app.timezone', 'Asia/Manila'))->startOfDay()
                ?? ($schedule?->date_posted ? Carbon::parse($schedule->date_posted)->startOfDay() : null));
    }

    /** @return array{conflicts: array<string, string>, warnings: array<string, string>} */
    private function scheduleReview(?Carbon $posted): array
    {
        $schedule = $this->relationLoaded('schedule') ? $this->schedule : $this->schedule()->first();
        $deadline = $this->bidSubmissionDeadline();
        $opening = $schedule?->bid_opening_date;
        $preBid = $schedule?->pre_bid_conference_date;
        $mode = $this->mode();
        $deadlineLabel = lcfirst($mode->deadlineLabel());
        $conflicts = [];
        $warnings = [];

        $minimumPostingDays = $mode->minimumPostingDays();
        if ($posted !== null && $deadline !== null && $minimumPostingDays > 0 && $posted->copy()->addDays($minimumPostingDays)->isAfter($deadline)) {
            $warnings['date_posted'] = "The {$deadlineLabel} is less than {$minimumPostingDays} calendar days after the local BAC publication.";
        }

        if ($mode->isCompetitive()) {
            if ($opening === null) {
                $conflicts['bid_opening_date'] = 'Set the bid opening schedule.';
            } elseif ($deadline !== null && $opening->lessThan($deadline)) {
                $conflicts['bid_opening_date'] = 'The bid opening cannot be before the submission deadline.';
            } elseif ($deadline !== null && ! $opening->isSameDay($deadline)) {
                $warnings['bid_opening_date'] = 'The bid opening is not on the same day as the submission deadline.';
            }

            if ($deadline !== null && ($mode->requiresPrebid() || $preBid !== null)) {
                if ($preBid === null) {
                    $conflicts['pre_bid_conference_date'] = 'Schedule a pre-bid conference: required for an ABC of ₱'.number_format($mode->prebidThreshold(), 0).' or more.';
                } elseif ($preBid->greaterThanOrEqualTo($deadline)) {
                    $conflicts['pre_bid_conference_date'] = 'The pre-bid conference must be before the submission deadline.';
                } elseif ($preBid->copy()->addDays(12)->isAfter($deadline)) {
                    $warnings['pre_bid_conference_date'] = 'The pre-bid conference is less than 12 calendar days before the submission deadline.';
                } elseif ($mode->isRa12009() && $posted !== null && $preBid->copy()->startOfDay()->lessThan($posted->copy()->addDays(7))) {
                    $warnings['pre_bid_conference_date'] = 'The pre-bid conference is less than 7 calendar days after the local BAC publication (RA 12009 IRR Sec. 51.2).';
                }
            }
        } elseif ($opening !== null && $deadline !== null && $opening->lessThan($deadline)) {
            $conflicts['bid_opening_date'] = 'The '.strtolower($mode->openingLabel()).' cannot be before the '.$deadlineLabel.'.';
        }

        return ['conflicts' => $conflicts, 'warnings' => $warnings];
    }

    public function electronicSubmissionAuthorizedByUser()
    {
        return $this->belongsTo(User::class, 'electronic_submission_authorized_by');
    }

    /**
     * Files that can be presented as this project's official bidding
     * documents. A file stored among bidder uploads (proposals, eligibility
     * files, certificates...) or named for another project is never treated
     * as an official document, even if it is linked to this project.
     */
    public function officialDocuments(): Collection
    {
        return $this->uploadedDocuments()
            ->filter(fn (ProjectDocument $document) => $this->isOfficialDocument($document))
            ->values();
    }

    /**
     * What the public website may post, as on PhilGEPS: the bid notice and the
     * bidding documents. Files typed "Other" stay with registered bidders and the BAC.
     */
    public const PUBLIC_DOCUMENT_TYPES = [
        'invitation_to_bid', 'bidding_documents', 'terms_of_reference', 'technical_specifications',
        'bill_of_quantities', 'project_plans', 'supplemental_bulletin',
    ];

    public function publicDocuments(): Collection
    {
        return $this->officialDocuments()
            ->filter(fn (ProjectDocument $document) => in_array($document->document_type, self::PUBLIC_DOCUMENT_TYPES, true))
            ->values();
    }

    public function unverifiedDocuments(): Collection
    {
        return $this->uploadedDocuments()
            ->reject(fn (ProjectDocument $document) => $this->isOfficialDocument($document))
            ->values();
    }

    public function isOfficialDocument(ProjectDocument $document): bool
    {
        $path = str_replace('\\', '/', (string) $document->file_path);

        if ($path === '' || ((int) $document->project_id !== 0 && (int) $document->project_id !== (int) $this->id)) {
            return false;
        }

        $segments = explode('/', strtolower($path));
        $filename = (string) end($segments);

        if (array_intersect($segments, self::BIDDER_UPLOAD_SEGMENTS) !== []) {
            return false;
        }

        foreach (self::BIDDER_FILE_PREFIXES as $prefix) {
            if (str_starts_with($filename, $prefix)) {
                return false;
            }
        }

        // Files the system names for a project carry its id: project_{id}_ / project_doc_{id}_.
        if (preg_match('/^project(?:_doc)?_(\d+)_/', $filename, $matches) && (int) $matches[1] !== (int) $this->id) {
            return false;
        }

        return true;
    }

    public function bidsOpenedByUser()
    {
        return $this->belongsTo(User::class, 'bids_opened_by');
    }

    /**
     * Proposals and bid amounts are sealed until the authorized bid opening
     * has been recorded.
     */
    public function bidsAreOpened(): bool
    {
        return $this->bids_opened_at !== null
            && $this->bids_opened_at->lessThanOrEqualTo(now('Asia/Manila'));
    }

    /**
     * Why the bids cannot be opened yet, or null when opening is allowed:
     * the submission deadline and the scheduled opening time must have passed.
     */
    public function bidOpeningBlocker(?Carbon $at = null): ?string
    {
        $at ??= now(config('app.timezone', 'Asia/Manila'));

        if (! $this->requiresRecordedBidOpening()) {
            return 'This procurement mode uses quotation/offer review after its deadline; no competitive bid-opening event is required.';
        }

        if ($this->bidsAreOpened() && $this->bids_opened_by !== null) {
            return 'Bids for this project were already opened.';
        }

        if ($this->archived_at !== null) {
            return 'Archived projects cannot be opened for bidding.';
        }

        if ($this->failed_bidding_at !== null) {
            return 'Bids cannot be opened after failure of bidding has been declared.';
        }

        // Opened at its scheduled time: the BAC can still record that it conducted the opening.
        if ($this->bidsAreOpened()) {
            return null;
        }

        if (in_array($this->status, ['draft', 'approved_for_bidding'], true)) {
            return 'Bidding has not started for this project.';
        }

        if ($this->status !== 'open' && ! ($this->status === 'closed' && $this->bids_opened_at !== null && $this->bids_opened_by === null)) {
            return 'Only a project that is open for bidding can be opened.';
        }

        $deadline = $this->bidSubmissionDeadline();
        if ($deadline === null) {
            return 'Set the bid submission deadline before opening bids.';
        }

        if ($deadline->isAfter($at)) {
            return 'Bids cannot be opened before the submission deadline (' . $deadline->timezone(config('bac-office.display_timezone'))->format('M d, Y h:i A') . ').';
        }

        $schedule = $this->relationLoaded('schedule') ? $this->schedule : $this->schedule()->first();
        $openingAt = $schedule?->bid_opening_date;

        if ($openingAt !== null && $openingAt->isAfter($at)) {
            return 'Bids cannot be opened before the scheduled bid opening (' . $openingAt->timezone(config('bac-office.display_timezone'))->format('M d, Y h:i A') . ').';
        }

        return null;
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class, 'project_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class)->orderBy('id');
    }

    public function requirement()
    {
        return $this->hasOne(ProjectRequirement::class);
    }

    public function schedule()
    {
        return $this->hasOne(ProjectSchedule::class);
    }

    public function scopeVisibleToPublic(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now(config('app.timezone', 'Asia/Manila'));
        return $query->where(function (Builder $archived) use ($at): void {
                $archived->whereNull('archived_at')->orWhere('archived_at', '>', $at);
            })->whereIn('status', self::PUBLIC_STATUSES)->publicationReached($at);
    }
    /**
     * The publication time has come, by the server clock in Philippine time:
     * published_at when recorded, else 12:00 AM of the schedule's date posted.
     * Projects with neither (older records) count as published.
     */
    public function scopePublicationReached(Builder $query, ?Carbon $at = null): Builder
    {
        $at = ($at ?? now())->copy()->timezone(config('app.timezone', 'Asia/Manila'));

        return $query->where(function (Builder $publication) use ($at): void {
            $publication->where('published_at', '<=', $at->toDateTimeString())
                ->orWhere(fn (Builder $unrecorded) => $unrecorded->whereNull('published_at')
                    ->whereDoesntHave('schedule', fn (Builder $schedule) => $schedule->whereDate('date_posted', '>', $at->toDateString())));
        });
    }

    /** When the project is (or was) posted publicly: published_at, else 12:00 AM PH time of the date posted. */
    public function publicationTime(): ?Carbon
    {
        $zone = config('app.timezone', 'Asia/Manila');
        $publishedAt = $this->getRawOriginal('published_at') ?? ($this->attributes['published_at'] ?? null);
        if ($publishedAt) {
            return Carbon::parse($publishedAt)->timezone($zone);
        }
        $schedule = $this->relationLoaded('schedule') ? $this->schedule : $this->schedule()->first();

        return $schedule?->date_posted ? Carbon::parse($schedule->date_posted->toDateString(), $zone)->startOfDay() : null;
    }

    /** Published but its publication time is still ahead: hidden from the public, shown to the BAC as Scheduled. */
    public function isScheduledForPublication(?Carbon $at = null): bool
    {
        $time = $this->publicationTime();

        return in_array($this->status, self::PUBLIC_STATUSES, true) && $time !== null && $time->isAfter($at ?? now());
    }

    /** Listed, searchable and downloadable by the public (and bidders) now. */
    public function isPubliclyVisible(?Carbon $at = null): bool
    {
        return $this->archived_at === null
            && in_array($this->status, self::PUBLIC_STATUSES, true)
            && ! $this->isScheduledForPublication($at);
    }

    public function scopeOpenForBidding(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now(config('app.timezone', 'Asia/Manila'));

        return $query
            ->where(function (Builder $status) use ($at): void {
                $status->where('status', 'open')
                    ->orWhere(fn (Builder $beforeOpening) => $beforeOpening->where('status', 'closed')
                        ->whereNotNull('bids_opened_at')->where('bids_opened_at', '>', $at))
                    ->orWhere(fn (Builder $beforeAward) => $beforeAward->where('status', 'awarded')
                        ->whereHas('bids', fn (Builder $bid) => $bid->whereNotNull('notice_of_award_at')->where('notice_of_award_at', '>', $at)));
            })
            ->where(fn (Builder $archived) => $archived->whereNull('archived_at')->orWhere('archived_at', '>', $at))
            ->publicationReached($at)
            ->where(function (Builder $deadlineQuery) use ($at): void {
                $deadlineQuery
                    ->where('deadline', '>', $at)
                    ->orWhere(function (Builder $scheduleQuery) use ($at): void {
                        $scheduleQuery->whereNull('deadline')->whereHas('schedule', function (Builder $schedule) use ($at): void {
                            $schedule->whereNotNull('bid_submission_deadline')->where('bid_submission_deadline', '>', $at);
                        });
                    });
            });
    }
    public function bidSubmissionDeadline(): ?Carbon
    {
        if ($this->deadline) {
            return $this->deadline;
        }

        $schedule = $this->relationLoaded('schedule')
            ? $this->schedule
            : $this->schedule()->first();

        return $schedule?->bid_submission_deadline;
    }

    public function isOpenForBidding(?Carbon $at = null): bool
    {
        $deadline = $this->bidSubmissionDeadline();

        return $this->status === 'open'
            && $this->archived_at === null
            && ! $this->isScheduledForPublication($at)
            && $deadline !== null
            && $deadline->isAfter($at ?? now());
    }

    public function scopeHasApprovedBids(Builder $query): Builder
    {
        return $query->whereHas('bids', function ($query) {
            $query->where('status', 'approved');
        });
    }

    public function scopeReadyForAward(Builder $query): Builder
    {
        // Ready only once the BAC has recommended a bidder for award.
        // An approved bid waiting for its Notice of Award (the approval's award record may already exist).
        return $query->whereHas('bids', fn ($query) => $query->awaitingAwardRecord())
            ->whereNull('failed_bidding_at');
    }

    public function getDeadlineAttribute(mixed $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        // If already a Carbon instance, return as-is
        if ($value instanceof Carbon) {
            return $value;
        }

        // Parse string to Carbon
        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getDocumentUrlAttribute(): ?string
    {
        return $this->firstUploadedDocument()?->file_url;
    }

    public function getDocumentFilenameAttribute(): ?string
    {
        return $this->firstUploadedDocument()?->display_name;
    }

    public function uploadedDocuments(): Collection
    {
        $documents = $this->relationLoaded('documents')
            ? $this->documents
            : $this->documents()->get();

        if (! filled($this->document_path)) {
            return $documents->values();
        }

        $legacyDocument = new ProjectDocument([
            'project_id' => $this->id,
            'file_path' => $this->document_path,
            'original_name' => $this->document_original_name,
        ]);

        return collect([$legacyDocument])
            ->concat($documents)
            ->unique('file_path')
            ->values();
    }

    public function firstUploadedDocument(): ?ProjectDocument
    {
        return $this->uploadedDocuments()->first();
    }
}
