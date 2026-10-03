<?php

namespace App\Support;

use App\Models\Project;
use Carbon\CarbonInterface;

/**
 * The procurement mode of a project and the rules that follow from it and
 * from the project's legal basis.
 *
 * Current reference: the IRR of RA 12009 approved by GPPB Resolution
 * No. 02-2025 (effective 25 February 2025). GPPB Public Advisory No. 09-2026
 * withdrew the TSO-issued "1st Edition" IRR; it is not used here. Projects
 * recorded under RA 9184 keep the 2016 Revised IRR rules, and so do older
 * records whose legal basis was never set.
 *
 * Families:
 * - competitive: Competitive (Public) Bidding — Invitation to Bid, pre-bid,
 *   sealed bids, opening, evaluation, post-qualification.
 * - rfq: Small Value Procurement (and Shopping under RA 9184) — Request for
 *   Quotation, quotations, Abstract of Quotations.
 * - negotiated: Negotiated Procurement — invitation to negotiate, offers.
 * - direct: Direct Contracting — RFQ or pro-forma invoice to one supplier.
 */
final class ProcurementMode
{
    public const FAMILY_COMPETITIVE = 'competitive';

    public const FAMILY_RFQ = 'rfq';

    public const FAMILY_NEGOTIATED = 'negotiated';

    public const FAMILY_DIRECT = 'direct';

    public const RA_12009 = 'ra_12009';

    public const RA_9184 = 'ra_9184';

    /**
     * Modes the system supports. `bases` lists the legal bases under which a
     * mode exists: Shopping was not carried over into RA 12009.
     */
    public const MODES = [
        'public_bidding' => [
            'label' => 'Competitive Bidding',
            'label_ra_9184' => 'Public Bidding',
            'short' => 'Competitive',
            'family' => self::FAMILY_COMPETITIVE,
            'bases' => [self::RA_12009, self::RA_9184],
        ],
        'small_value_procurement' => [
            'label' => 'Small Value Procurement',
            'short' => 'SVP',
            'family' => self::FAMILY_RFQ,
            'bases' => [self::RA_12009, self::RA_9184],
        ],
        'negotiated_procurement' => [
            'label' => 'Negotiated Procurement',
            'short' => 'Negotiated',
            'family' => self::FAMILY_NEGOTIATED,
            'bases' => [self::RA_12009, self::RA_9184],
        ],
        'direct_contracting' => [
            'label' => 'Direct Contracting',
            'short' => 'Direct',
            'family' => self::FAMILY_DIRECT,
            'bases' => [self::RA_12009, self::RA_9184],
        ],
        'shopping' => [
            'label' => 'Shopping',
            'short' => 'Shopping',
            'family' => self::FAMILY_RFQ,
            'bases' => [self::RA_9184],
        ],
        // Older records: competitive bidding with electronic submission.
        'electronic_procurement' => [
            'label' => 'Competitive Bidding (electronic submission)',
            'label_ra_9184' => 'Public Bidding (electronic submission)',
            'short' => 'Competitive',
            'family' => self::FAMILY_COMPETITIVE,
            'bases' => [self::RA_12009, self::RA_9184],
            'legacy' => true,
        ],
    ];

    /** Grounds for Negotiated Procurement that change how it is run. */
    public const NEGOTIATION_GROUNDS = [
        'two_failed_biddings' => 'Two failed biddings',
        'emergency' => 'Emergency cases',
        'other' => 'Other ground allowed by the IRR',
    ];

    /** Section references for the grounds, per legal basis. */
    private const NEGOTIATION_SECTIONS = [
        self::RA_12009 => ['two_failed_biddings' => 'Sec. 35.1', 'emergency' => 'Sec. 35.2'],
        self::RA_9184 => ['two_failed_biddings' => 'Sec. 53.1', 'emergency' => 'Sec. 53.2'],
    ];

    /**
     * RA 12009 IRR Section 34.2: SVP ceiling for LGUs by income class
     * (1st to 5th), and ₱100,000 for barangays.
     */
    private const SVP_LGU_CEILINGS = [
        'province' => [1 => 2000000, 2 => 2000000, 3 => 2000000, 4 => 1600000, 5 => 1200000],
        'city' => [1 => 2000000, 2 => 2000000, 3 => 1600000, 4 => 1200000, 5 => 800000],
        'municipality' => [1 => 400000, 2 => 400000, 3 => 400000, 4 => 200000, 5 => 200000],
    ];

    private const SVP_BARANGAY_CEILING = 100000;

    /** RA 12009 IRR Sec. 34.3(b): no RFQ posting needed at ₱200,000 and below. */
    private const RFQ_POSTING_FLOOR_RA_12009 = 200000;

    /** RA 9184 2016 IRR, Annex H: posting for alternative modes above ₱50,000. */
    private const RFQ_POSTING_FLOOR_RA_9184 = 50000;

    /** Pre-bid conference required at or above this ABC: RA 12009 IRR Sec. 51.1. */
    private const PREBID_THRESHOLD_RA_12009 = 3000000;

    /** Pre-bid conference required at or above this ABC: RA 9184 IRR Sec. 22.1. */
    private const PREBID_THRESHOLD_RA_9184 = 1000000;

    private function __construct(
        private readonly string $key,
        private readonly string $basis,
        private readonly bool $basisRecorded,
        private readonly float $abc,
        private readonly ?string $negotiationGround,
    ) {}

    public static function of(Project $project): self
    {
        $key = array_key_exists((string) $project->procurement_mode, self::MODES)
            ? (string) $project->procurement_mode
            : 'public_bidding';

        return new self(
            $key,
            $project->legal_basis === self::RA_12009 ? self::RA_12009 : self::RA_9184,
            filled($project->legal_basis),
            (float) $project->budget,
            $project->negotiation_ground ?: null,
        );
    }

    /**
     * Mode options for forms: key => label (current IRR names), excluding
     * legacy values and modes that do not exist under the given basis.
     *
     * @return array<string, string>
     */
    public static function options(?string $basis = self::RA_12009): array
    {
        return collect(self::MODES)
            ->reject(fn (array $mode) => $mode['legacy'] ?? false)
            ->filter(fn (array $mode) => $basis === null || in_array($basis, $mode['bases'], true))
            ->map(fn (array $mode, string $key) => $basis === self::RA_9184 ? ($mode['label_ra_9184'] ?? $mode['label']) : $mode['label'])
            ->all();
    }

    /** @return list<string> every stored mode value the system accepts */
    public static function keys(): array
    {
        return array_keys(self::MODES);
    }

    public static function labelFor(?string $key): string
    {
        return self::MODES[$key]['label'] ?? 'Competitive Bidding';
    }

    public static function familyOf(?string $key): string
    {
        return self::MODES[$key]['family'] ?? self::FAMILY_COMPETITIVE;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function family(): string
    {
        return self::MODES[$this->key]['family'];
    }

    public function label(): string
    {
        $mode = self::MODES[$this->key];

        return $this->basis === self::RA_9184 ? ($mode['label_ra_9184'] ?? $mode['label']) : $mode['label'];
    }

    public function shortLabel(): string
    {
        return self::MODES[$this->key]['short'];
    }

    public function isCompetitive(): bool
    {
        return $this->family() === self::FAMILY_COMPETITIVE;
    }

    public function isAlternative(): bool
    {
        return ! $this->isCompetitive();
    }

    public function isRfq(): bool
    {
        return $this->family() === self::FAMILY_RFQ;
    }

    public function isNegotiated(): bool
    {
        return $this->family() === self::FAMILY_NEGOTIATED;
    }

    public function isDirectContracting(): bool
    {
        return $this->family() === self::FAMILY_DIRECT;
    }

    public function negotiationGround(): ?string
    {
        return $this->isNegotiated() ? $this->negotiationGround : null;
    }

    public function negotiationGroundLabel(): ?string
    {
        $ground = $this->negotiationGround();
        if ($ground === null) {
            return null;
        }

        $section = self::NEGOTIATION_SECTIONS[$this->basis][$ground] ?? null;

        return self::NEGOTIATION_GROUNDS[$ground].($section ? ' ('.$section.')' : '');
    }

    /** The legal basis whose rules apply: ra_12009 or ra_9184. */
    public function legalBasis(): string
    {
        return $this->basis;
    }

    public function isRa12009(): bool
    {
        return $this->basis === self::RA_12009;
    }

    public function legalBasisLabel(): string
    {
        if (! $this->basisRecorded) {
            return 'Not recorded (RA 9184 rules applied)';
        }

        return $this->isRa12009() ? 'RA 12009 (2025 IRR, GPPB Res. No. 02-2025)' : 'RA 9184 (2016 Revised IRR)';
    }

    public function legalBasisShort(): string
    {
        return $this->isRa12009() ? 'RA 12009' : 'RA 9184';
    }

    public function isAvailableUnderBasis(): bool
    {
        return in_array($this->basis, self::MODES[$this->key]['bases'], true);
    }

    // ------------------------------------------------------------------
    // Posting (PhilGEPS, website, conspicuous place)
    // ------------------------------------------------------------------

    /** Whether the notice (ITB or RFQ) must be posted on PhilGEPS before the deadline. */
    public function requiresPosting(): bool
    {
        return match ($this->family()) {
            self::FAMILY_COMPETITIVE => true,
            self::FAMILY_RFQ => $this->abc > $this->rfqPostingFloor(),
            self::FAMILY_NEGOTIATED => $this->negotiationGround === 'two_failed_biddings',
            default => false,
        };
    }

    /** Minimum calendar days the notice stays posted before the deadline. */
    public function minimumPostingDays(): int
    {
        if (! $this->requiresPosting()) {
            return 0;
        }

        return $this->isCompetitive() ? 7 : 3;
    }

    public function rfqPostingFloor(): float
    {
        return $this->isRa12009() ? self::RFQ_POSTING_FLOOR_RA_12009 : self::RFQ_POSTING_FLOOR_RA_9184;
    }

    public function postingRule(): string
    {
        if ($this->isCompetitive()) {
            return $this->isRa12009()
                ? 'Invitation to Bid posted on PhilGEPS, the LGU website and a conspicuous place for 7 calendar days (RA 12009 IRR Sec. 50.3.1).'
                : 'Invitation to Bid posted on PhilGEPS and the LGU premises for 7 calendar days (RA 9184 IRR Sec. 21.2).';
        }

        if (! $this->requiresPosting()) {
            return match (true) {
                $this->isDirectContracting() => 'No posting: the RFQ or pro-forma invoice goes to the identified direct supplier (Sec. '.($this->isRa12009() ? '31.3' : '50').').',
                $this->isNegotiated() => 'No posting required for this ground of Negotiated Procurement.',
                default => 'No posting required: ABC is ₱'.number_format($this->rfqPostingFloor(), 0).' or below'.($this->isRa12009() ? ' (RA 12009 IRR Sec. 34.3(b)).' : ' (RA 9184 IRR Annex H).'),
            };
        }

        return $this->isRa12009()
            ? 'RFQ posted on PhilGEPS, the LGU website and a conspicuous place for at least 3 calendar days (RA 12009 IRR Sec. 50.3.2).'
            : 'RFQ posted on PhilGEPS for at least 3 calendar days (RA 9184 IRR Annex H).';
    }

    // ------------------------------------------------------------------
    // Pre-bid conference
    // ------------------------------------------------------------------

    public function prebidThreshold(): float
    {
        return $this->isRa12009() ? self::PREBID_THRESHOLD_RA_12009 : self::PREBID_THRESHOLD_RA_9184;
    }

    /** Required for competitive bidding at or above the threshold; otherwise at the BAC's discretion. */
    public function requiresPrebid(): bool
    {
        return $this->isCompetitive() && $this->abc >= $this->prebidThreshold();
    }

    public function prebidRule(): string
    {
        if (! $this->isCompetitive()) {
            return 'A pre-bid conference is optional, at the BAC\'s discretion.';
        }

        $section = $this->isRa12009() ? 'RA 12009 IRR Sec. 51' : 'RA 9184 IRR Sec. 22';

        return 'Required for an ABC of ₱'.number_format($this->prebidThreshold(), 0).' or more; at least 12 calendar days before the submission deadline'
            .($this->isRa12009() ? ' and not earlier than 7 calendar days after local BAC publication; any external PhilGEPS posting is recorded separately' : '').' ('.$section.').';
    }

    // ------------------------------------------------------------------
    // Ceilings
    // ------------------------------------------------------------------

    /** SVP ceiling for this LGU, when the rules define one (RA 12009 IRR Sec. 34.2). */
    public function svpCeiling(): ?float
    {
        if ($this->key !== 'small_value_procurement' || ! $this->isRa12009()) {
            return null;
        }

        return self::lguSvpCeiling();
    }

    /**
     * The same publication and schedule checks as Project::publicationBlockers(),
     * as data for the create-project wizard, so it can explain problems beside
     * the affected fields before the server refuses to publish.
     *
     * @return array<string, mixed>
     */
    public static function clientRules(): array
    {
        return [
            'modes' => collect(self::MODES)->map(fn (array $mode, string $key) => [
                'family' => $mode['family'],
                'bases' => $mode['bases'],
                'label' => $mode['label'],
                'labelRa9184' => $mode['label_ra_9184'] ?? $mode['label'],
            ])->all(),
            'prebidThreshold' => [self::RA_12009 => self::PREBID_THRESHOLD_RA_12009, self::RA_9184 => self::PREBID_THRESHOLD_RA_9184],
            'rfqPostingFloor' => [self::RA_12009 => self::RFQ_POSTING_FLOOR_RA_12009, self::RA_9184 => self::RFQ_POSTING_FLOOR_RA_9184],
            'svpCeiling' => self::lguSvpCeiling(),
            'postingDays' => ['competitive' => 7, 'rfq' => 3],
            'prebidDaysBeforeDeadline' => 12,
            'prebidDaysAfterPublication' => 7,
            // Award criteria per legal basis and category (Project::awardCriteriaFor, IRR Sec. 50.2(d), (g)).
            'awardCriteria' => collect([self::RA_12009 => true, self::RA_9184 => false])->map(fn (bool $ra12009) => [
                'default' => Project::awardCriteriaFor($ra12009, 'goods'),
                'consultancy' => Project::awardCriteriaFor($ra12009, 'consultancy'),
            ])->all(),
            'preProcurementThresholds' => Project::PRE_PROCUREMENT_THRESHOLDS,
        ];
    }

    /** The RA 12009 SVP ceiling for the configured LGU type and income class. */
    public static function lguSvpCeiling(): float
    {
        $type = (string) config('bac-office.lgu.type', 'municipality');
        $class = max(1, min(5, (int) config('bac-office.lgu.income_class', 1)));

        if ($type === 'barangay') {
            return self::SVP_BARANGAY_CEILING;
        }

        return (float) (self::SVP_LGU_CEILINGS[$type][$class] ?? self::SVP_LGU_CEILINGS['municipality'][$class]);
    }

    // ------------------------------------------------------------------
    // Periods
    // ------------------------------------------------------------------

    /**
     * Latest date the award should be made, counted from the bid opening:
     * 60 calendar days (RA 12009 IRR Sec. 67.1); three months under RA 9184
     * (Sec. 38.1). Only competitive bidding.
     */
    public function awardDueDate(?CarbonInterface $opening): ?CarbonInterface
    {
        if ($opening === null || ! $this->isCompetitive()) {
            return null;
        }

        return $this->isRa12009() ? $opening->copy()->addDays(60) : $opening->copy()->addMonths(3);
    }

    public function awardPeriodLabel(): string
    {
        return $this->isRa12009() ? '60 calendar days (RA 12009 IRR Sec. 67.1)' : '3 months (RA 9184 IRR Sec. 38.1)';
    }

    // ------------------------------------------------------------------
    // Vocabulary
    // ------------------------------------------------------------------

    /** What suppliers submit: bid, quotation or offer. */
    public function submissionNoun(bool $plural = false): string
    {
        $noun = match ($this->family()) {
            self::FAMILY_COMPETITIVE => 'bid',
            self::FAMILY_NEGOTIATED => 'offer',
            default => 'quotation',
        };

        return $plural ? $noun.'s' : $noun;
    }

    public function noticeLabel(): string
    {
        return match ($this->family()) {
            self::FAMILY_COMPETITIVE => 'Invitation to Bid',
            self::FAMILY_NEGOTIATED => 'Invitation to negotiate / RFQ',
            self::FAMILY_DIRECT => 'RFQ or pro-forma invoice',
            default => 'Request for Quotation',
        };
    }

    public function deadlineLabel(): string
    {
        return match ($this->family()) {
            self::FAMILY_COMPETITIVE => 'Bid submission deadline',
            self::FAMILY_NEGOTIATED => 'Deadline for offers',
            default => 'Quotation deadline',
        };
    }

    public function openingLabel(): string
    {
        return $this->isCompetitive() ? 'Bid opening' : 'Opening of '.$this->submissionNoun(true);
    }
}
