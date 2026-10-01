<?php

namespace App\Models;

use App\Support\ProcurementMode;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Concerns\MasksFutureProcurementEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dated BAC record on a project, with its supporting document: pre-bid
 * conference, clarification, bid bulletin, BAC resolution, request for
 * reconsideration, inspection and acceptance — and, for the alternative
 * modes, the RFQs sent to suppliers, negotiations and the Abstract of
 * Quotations.
 */
class ProjectProceeding extends Model
{    use MasksFutureProcurementEvents;

    protected function futureProcurementEventFields(): array { return ['created_at', 'occurred_at']; }

    protected static function booted(): void
    {
        static::addGlobalScope('known_as_of_demo_clock', function (Builder $query): void {
            $clock = app(\App\Support\ProcurementClock::class);
            if ($clock->demoModeEnabled()) $query->where('created_at', '<=', $clock->now());
        });
    }

    public const TYPE_PRE_BID = 'pre_bid_conference';

    public const TYPE_CLARIFICATION = 'clarification';

    public const TYPE_BID_BULLETIN = 'bid_bulletin';

    public const TYPE_BAC_RESOLUTION = 'bac_resolution';

    public const TYPE_RECONSIDERATION = 'request_for_reconsideration';

    public const TYPE_INSPECTION = 'inspection';

    public const TYPE_ACCEPTANCE = 'acceptance';

    public const TYPE_RFQ_ISSUED = 'rfq_issued';

    public const TYPE_NEGOTIATION = 'negotiation';

    public const TYPE_ABSTRACT = 'abstract_of_quotations';

    /** Before publication; mandatory above the ABC threshold (RA 12009 IRR Sec. 49.1). */
    public const TYPE_PRE_PROCUREMENT = 'pre_procurement_conference';

    /** 7-day posting at a conspicuous place, certified by the head of the BAC Secretariat (Sec. 50.3.1(a)). */
    public const TYPE_POSTING_CERTIFICATE = 'posting_certificate';

    /** COA representative and at least two observers invited (Sec. 43.1). */
    public const TYPE_OBSERVERS = 'observers_invited';

    /** Video recording / livestream of a conference or the bid opening (Sec. 38). */
    public const TYPE_VIDEO_RECORDING = 'video_recording';

    /** Types that can be recorded while the project is still a draft. */
    public const PRE_PUBLICATION_TYPES = [self::TYPE_PRE_PROCUREMENT];

    public const TYPES = [
        self::TYPE_PRE_PROCUREMENT => 'Pre-procurement conference',
        self::TYPE_POSTING_CERTIFICATE => 'Certificate of posting (conspicuous place, 7 days)',
        self::TYPE_OBSERVERS => 'COA and observers invited',
        self::TYPE_VIDEO_RECORDING => 'Video recording / livestream',
        self::TYPE_PRE_BID => 'Pre-bid conference',
        self::TYPE_CLARIFICATION => 'Clarification',
        self::TYPE_BID_BULLETIN => 'Supplemental / Bid Bulletin',
        self::TYPE_BAC_RESOLUTION => 'BAC resolution',
        self::TYPE_RECONSIDERATION => 'Request for reconsideration / protest',
        self::TYPE_INSPECTION => 'Inspection',
        self::TYPE_ACCEPTANCE => 'Acceptance',
        self::TYPE_RFQ_ISSUED => 'RFQs sent to suppliers',
        self::TYPE_NEGOTIATION => 'Negotiation',
        self::TYPE_ABSTRACT => 'Abstract of Quotations',
    ];

    /** Types recorded through the general "record proceeding" form (competitive bidding). */
    public const GENERAL_TYPES = [
        self::TYPE_PRE_PROCUREMENT,
        self::TYPE_POSTING_CERTIFICATE,
        self::TYPE_OBSERVERS,
        self::TYPE_PRE_BID,
        self::TYPE_CLARIFICATION,
        self::TYPE_BID_BULLETIN,
        self::TYPE_VIDEO_RECORDING,
        self::TYPE_BAC_RESOLUTION,
        self::TYPE_RECONSIDERATION,
    ];

    /** Every type the general form accepts, across modes. */
    public const RECORDABLE_TYPES = [
        self::TYPE_PRE_PROCUREMENT,
        self::TYPE_POSTING_CERTIFICATE,
        self::TYPE_OBSERVERS,
        self::TYPE_VIDEO_RECORDING,
        self::TYPE_PRE_BID,
        self::TYPE_CLARIFICATION,
        self::TYPE_BID_BULLETIN,
        self::TYPE_BAC_RESOLUTION,
        self::TYPE_RECONSIDERATION,
        self::TYPE_RFQ_ISSUED,
        self::TYPE_NEGOTIATION,
        self::TYPE_ABSTRACT,
    ];

    public const RECONSIDERATION_OUTCOMES = [
        'pending' => 'Pending',
        'granted' => 'Granted',
        'denied' => 'Denied',
    ];

    protected $fillable = [
        'project_id',
        'type',
        'title',
        'occurred_at',
        'reference_no',
        'recipients_count',
        'summary',
        'outcome',
        'file_path',
        'original_name',
        'recorded_by',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'recipients_count' => 'integer',
    ];

    /**
     * Proceedings the BAC can record for a project of this mode, in the
     * order they usually happen.
     *
     * @return array<string, string> type => label
     */
    public static function typesFor(ProcurementMode $mode): array
    {
        $types = match (true) {
            $mode->isCompetitive() => self::GENERAL_TYPES,
            $mode->isRfq() => [self::TYPE_RFQ_ISSUED, self::TYPE_PRE_BID, self::TYPE_CLARIFICATION, self::TYPE_ABSTRACT, self::TYPE_BAC_RESOLUTION, self::TYPE_RECONSIDERATION],
            $mode->isNegotiated() => [self::TYPE_RFQ_ISSUED, self::TYPE_NEGOTIATION, self::TYPE_ABSTRACT, self::TYPE_BAC_RESOLUTION, self::TYPE_RECONSIDERATION],
            default => [self::TYPE_RFQ_ISSUED, self::TYPE_NEGOTIATION, self::TYPE_BAC_RESOLUTION],
        };

        return collect($types)->mapWithKeys(fn (string $type) => [$type => self::labelFor($type, $mode)])->all();
    }

    public static function labelFor(string $type, ?ProcurementMode $mode = null): string
    {
        if ($type === self::TYPE_RFQ_ISSUED && $mode !== null) {
            return match (true) {
                $mode->isDirectContracting() => 'RFQ / pro-forma invoice sent to the supplier',
                $mode->isNegotiated() => 'Invitations to negotiate sent',
                default => self::TYPES[$type],
            };
        }

        if ($type === self::TYPE_ABSTRACT && $mode?->isNegotiated()) {
            return 'Abstract of offers';
        }

        return self::TYPES[$type] ?? 'Proceeding';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function typeLabel(): string
    {
        return self::labelFor((string) $this->type, $this->relationLoaded('project') && $this->project ? $this->project->mode() : null);
    }
}
