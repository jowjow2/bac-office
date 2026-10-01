<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\BiddingFeeAmendment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Bidding documents fee of a competitive bidding, from its approved ABC.
 *
 * GPPB Circular No. 02-2026, Sec. 5.2 sets these as maximum rates. The BAC may
 * charge less or waive the fee, with a recorded reason, never more. No separate
 * San Jose rate is applied: none has an approved basis in this system.
 *
 * Modes stored on the project:
 * - schedule: the maximum for the current ABC (follows the ABC while in draft)
 * - reduced:  a lower amount set by the BAC, with a reason
 * - waived:   no fee, with a reason
 * A null mode is a fee entered before the schedule existed, or a project that
 * is not competitive bidding (its fee stays as entered).
 */
final class BiddingDocumentsFee
{
    public const BASIS = 'GPPB Circular No. 02-2026, Sec. 5.2';

    public const MODE_SCHEDULE = 'schedule';

    public const MODE_REDUCED = 'reduced';

    public const MODE_WAIVED = 'waived';

    public const MODES = [self::MODE_SCHEDULE, self::MODE_REDUCED, self::MODE_WAIVED];

    /** [upper ABC limit in centavos (inclusive; null = no limit), maximum fee, bracket label] */
    private const SCHEDULE = [
        [50_000_000, '500.00', 'ABC up to ₱500,000'],
        [100_000_000, '1000.00', 'ABC above ₱500,000 up to ₱1 million'],
        [500_000_000, '5000.00', 'ABC above ₱1 million up to ₱5 million'],
        [1_000_000_000, '10000.00', 'ABC above ₱5 million up to ₱10 million'],
        [5_000_000_000, '25000.00', 'ABC above ₱10 million up to ₱50 million'],
        [50_000_000_000, '50000.00', 'ABC above ₱50 million up to ₱500 million'],
        [null, '75000.00', 'ABC above ₱500 million'],
    ];

    /**
     * The schedule's maximum fee and bracket for an ABC; null when no ABC is set.
     *
     * @return array{maximum: string, bracket: string}|null
     */
    public static function bracketFor(string|int|float|null $abc): ?array
    {
        $centavos = self::centavos($abc);
        if ($centavos === null || $centavos <= 0) {
            return null;
        }

        foreach (self::SCHEDULE as [$limit, $maximum, $label]) {
            if ($limit === null || $centavos <= $limit) {
                return ['maximum' => $maximum, 'bracket' => $label];
            }
        }

        return null;
    }

    public static function maximumFor(string|int|float|null $abc): ?string
    {
        return self::bracketFor($abc)['maximum'] ?? null;
    }

    /** The whole schedule, for showing and for the form's live calculation. */
    public static function schedule(): array
    {
        return array_map(fn (array $row) => ['limit' => $row[0] === null ? null : $row[0] / 100, 'maximum' => (float) $row[1], 'bracket' => $row[2]], self::SCHEDULE);
    }

    public static function appliesTo(Project $project): bool
    {
        return $project->mode()->isCompetitive();
    }

    /**
     * The fee attributes a save should write, from the form input, the project's
     * stored values and its new ABC (null = unchanged). Returns [] when the
     * save leaves the fee alone. Call it before the project is updated.
     *
     * Input keys: bidding_fee_mode, bidding_documents_fee, bidding_fee_reason,
     * and after publication bidding_fee_amendment_reference / _reason.
     *
     * @throws ValidationException
     */
    public static function resolve(Project $project, array $input, string|int|float|null $abc = null): array
    {
        $abc ??= $project->budget;
        $abcChanged = self::compare((string) $abc, (string) ($project->budget ?? '0')) !== 0;
        $sendsFee = array_key_exists('bidding_fee_mode', $input) || array_key_exists('bidding_documents_fee', $input);

        if (! self::appliesTo($project)) {
            if (! $sendsFee) {
                return [];
            }
            $fee = self::amount($input['bidding_documents_fee'] ?? null);

            return ['bidding_documents_fee' => $fee, 'bidding_fee_mode' => null, 'bidding_fee_reason' => null];
        }

        $currentMode = $project->bidding_fee_mode;
        // A new ABC moves a schedule fee with it (and a legacy fee is not touched unless sent).
        if (! $sendsFee && ! ($abcChanged && $currentMode === self::MODE_SCHEDULE)) {
            // A lower fee must stay within the maximum of a new, smaller ABC.
            $newMaximum = self::maximumFor($abc);
            if ($abcChanged && $currentMode === self::MODE_REDUCED && $newMaximum !== null
                && self::compare(self::amount($project->bidding_documents_fee) ?? '0.00', $newMaximum) > 0) {
                throw ValidationException::withMessages([
                    'bidding_documents_fee' => 'The lower fee of ₱'.number_format((float) $project->bidding_documents_fee, 2).' is above ₱'.number_format((float) $newMaximum, 2).', the maximum for the new ABC. Set the fee again.',
                ]);
            }

            return [];
        }

        $maximum = self::maximumFor($abc);
        $fee = self::amount($input['bidding_documents_fee'] ?? null);
        $reason = trim((string) ($input['bidding_fee_reason'] ?? ''));
        $mode = $input['bidding_fee_mode'] ?? null;

        // An amount-only form resending the stored fee (e.g. other settings saved) leaves it alone.
        if ($mode === null && ! $abcChanged && $project->exists
            && self::compare($fee ?? '0.00', self::amount($project->bidding_documents_fee) ?? '0.00') === 0) {
            return [];
        }
        if ($mode === null && ! $sendsFee) {
            $mode = $currentMode;
        }

        if ($mode !== null && ! in_array($mode, self::MODES, true)) {
            throw ValidationException::withMessages(['bidding_fee_mode' => 'Choose how the bidding documents fee is set.']);
        }

        // Forms that only send an amount: the maximum (or blank) follows the schedule.
        if ($mode === null) {
            $mode = match (true) {
                ! $sendsFee => self::MODE_SCHEDULE,
                $fee === null => self::MODE_SCHEDULE,
                (float) $fee === 0.0 => self::MODE_WAIVED,
                $maximum !== null && self::compare($fee, $maximum) === 0 => self::MODE_SCHEDULE,
                default => self::MODE_REDUCED,
            };
        }

        if ($mode === self::MODE_SCHEDULE) {
            return ['bidding_documents_fee' => $maximum, 'bidding_fee_mode' => self::MODE_SCHEDULE, 'bidding_fee_reason' => null];
        }

        // These are maximum rates: never more, whatever else is wrong.
        if ($mode === self::MODE_REDUCED && $fee !== null && $maximum !== null && self::compare($fee, $maximum) > 0) {
            throw ValidationException::withMessages([
                'bidding_documents_fee' => 'The fee cannot be higher than ₱'.number_format((float) $maximum, 2).', the maximum for this ABC under '.self::BASIS.'.',
            ]);
        }

        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'bidding_fee_reason' => $mode === self::MODE_WAIVED
                    ? 'Record why the bidding documents fee is waived (at least 10 characters).'
                    : 'Record why a fee lower than the maximum is charged (at least 10 characters).',
            ]);
        }

        if ($mode === self::MODE_WAIVED) {
            return ['bidding_documents_fee' => '0.00', 'bidding_fee_mode' => self::MODE_WAIVED, 'bidding_fee_reason' => $reason];
        }

        if ($maximum === null) {
            throw ValidationException::withMessages(['bidding_documents_fee' => 'Set the ABC first: the maximum fee comes from it.']);
        }
        if ($fee === null || (float) $fee <= 0) {
            throw ValidationException::withMessages(['bidding_documents_fee' => 'Enter the lower fee, or choose to waive it.']);
        }
        if (self::compare($fee, $maximum) > 0) {
            throw ValidationException::withMessages([
                'bidding_documents_fee' => 'The fee cannot be higher than ₱'.number_format((float) $maximum, 2).', the maximum for this ABC under '.self::BASIS.'.',
            ]);
        }
        if (self::compare($fee, $maximum) === 0) {
            return ['bidding_documents_fee' => $maximum, 'bidding_fee_mode' => self::MODE_SCHEDULE, 'bidding_fee_reason' => null];
        }

        return ['bidding_documents_fee' => $fee, 'bidding_fee_mode' => self::MODE_REDUCED, 'bidding_fee_reason' => $reason];
    }

    /**
     * Whether the resolved attributes change what bidders were told.
     */
    public static function changes(Project $project, array $attributes): bool
    {
        if ($attributes === []) {
            return false;
        }

        // Bidders see the amount (a waiver is ₱0.00), so only the amount counts.
        return self::compare(self::amount($project->bidding_documents_fee) ?? '0.00', $attributes['bidding_documents_fee'] ?? '0.00') !== 0;
    }

    /**
     * After publication a fee change is an amendment: it needs its reason and the
     * reference of the issuance that announces it (e.g. a Supplemental Bid
     * Bulletin), and it cannot happen once bidders paid or the deadline passed.
     *
     * @throws ValidationException
     */
    public static function assertAmendable(Project $project, array $attributes, array $input, ?string $lockReason): ?array
    {
        if (! self::changes($project, $attributes)) {
            return null;
        }
        if ($lockReason !== null) {
            throw ValidationException::withMessages(['bidding_documents_fee' => $lockReason]);
        }
        if (! $project->isPublishedLocally()) {
            return null;
        }

        $reference = trim((string) ($input['bidding_fee_amendment_reference'] ?? ''));
        $reason = trim((string) ($input['bidding_fee_amendment_reason'] ?? ''));
        $errors = [];
        if ($reference === '') {
            $errors['bidding_fee_amendment_reference'] = 'This project is already published. Enter the reference of the issuance that amends the fee (e.g. Supplemental Bid Bulletin No.).';
        }
        if (mb_strlen($reason) < 10) {
            $errors['bidding_fee_amendment_reason'] = 'Record why the published bidding documents fee is amended (at least 10 characters).';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['reference' => $reference, 'reason' => $reason];
    }

    /** Record an amendment after the project has saved its new fee. */
    public static function recordAmendment(Project $project, array $before, array $amendment, ?User $actor): BiddingFeeAmendment
    {
        $record = BiddingFeeAmendment::create([
            'project_id' => $project->id,
            'previous_fee' => $before['bidding_documents_fee'],
            'previous_mode' => $before['bidding_fee_mode'],
            'new_fee' => $project->bidding_documents_fee,
            'new_mode' => $project->bidding_fee_mode,
            'reference' => $amendment['reference'],
            'reason' => $amendment['reason'],
            'amended_by' => $actor?->id,
        ]);

        AuditLog::log('bidding_fee_amended', $project, $before, [
            'bidding_documents_fee' => $project->bidding_documents_fee,
            'bidding_fee_mode' => $project->bidding_fee_mode,
            'reference' => $amendment['reference'],
            'reason' => $amendment['reason'],
        ]);

        return $record;
    }

    /**
     * How the fee was arrived at, for the admin forms and the Invitation to Bid.
     *
     * @return array{fee: ?string, mode: ?string, text: string, maximum: ?string, bracket: ?string, reason: ?string}
     */
    public static function describe(Project $project): array
    {
        $bracket = self::appliesTo($project) ? self::bracketFor($project->budget) : null;
        $fee = $project->bidding_documents_fee !== null ? number_format((float) $project->bidding_documents_fee, 2, '.', '') : null;
        $peso = fn (?string $amount) => '₱'.number_format((float) $amount, 2);
        $mode = $project->bidding_fee_mode;

        $text = match (true) {
            $mode === self::MODE_WAIVED => 'Waived by the BAC'.($bracket ? ' (the maximum for this ABC is '.$peso($bracket['maximum']).' under '.self::BASIS.')' : '').'.',
            $mode === self::MODE_REDUCED && $bracket !== null => $peso($fee).', lower than the '.$peso($bracket['maximum']).' maximum for an '.$bracket['bracket'].' ('.self::BASIS.').',
            $mode === self::MODE_SCHEDULE && $bracket !== null => $peso($fee).', the maximum for an '.$bracket['bracket'].' ('.self::BASIS.').',
            $mode === self::MODE_SCHEDULE => 'Computed from the ABC once it is set ('.self::BASIS.').',
            (float) $fee > 0 => $peso($fee).'.',
            default => 'No fee.',
        };

        return [
            'fee' => $fee,
            'mode' => $mode,
            'text' => $text,
            'maximum' => $bracket['maximum'] ?? null,
            'bracket' => $bracket['bracket'] ?? null,
            'reason' => in_array($mode, [self::MODE_REDUCED, self::MODE_WAIVED], true) ? $project->bidding_fee_reason : null,
        ];
    }

    private static function amount(mixed $value): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        $clean = str_replace([',', '₱', ' '], '', (string) $value);
        if (! is_numeric($clean) || (float) $clean < 0) {
            throw ValidationException::withMessages(['bidding_documents_fee' => 'Enter the bidding documents fee as an amount.']);
        }

        return number_format((float) $clean, 2, '.', '');
    }

    private static function centavos(string|int|float|null $amount): ?int
    {
        $text = is_string($amount) ? str_replace([',', ' '], '', trim($amount)) : number_format((float) $amount, 2, '.', '');
        if (! preg_match('/^-?\d+(\.\d+)?$/', $text)) {
            return null;
        }
        // Exact from the digits (no float rounding at large ABCs); extra decimals are cut.
        [$whole, $decimals] = array_pad(explode('.', ltrim($text, '-')), 2, '');
        $value = (int) $whole * 100 + (int) str_pad(substr($decimals, 0, 2), 2, '0');

        return str_starts_with($text, '-') ? -$value : $value;
    }

    private static function compare(string $left, string $right): int
    {
        return self::centavos($left) <=> self::centavos($right);
    }
}
