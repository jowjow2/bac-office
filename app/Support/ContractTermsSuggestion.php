<?php

namespace App\Support;

use App\Models\Award;
use Illuminate\Support\Carbon;

/**
 * Starting values for a goods contract's terms form, taken from the project,
 * its purchase request and the award. They are only suggestions: the BAC
 * checks each one against the signed contract before saving.
 */
class ContractTermsSuggestion
{
    /**
     * @return array{
     *     deadline: ?Carbon, deadline_hint: ?string, location: ?string, reference: ?string,
     *     items: list<array{description: string, quantity: string, unit: string, unit_price: string}>,
     *     prices_hint: ?string
     * }
     */
    public static function for(Award $award): array
    {
        $award->loadMissing(['project.procurementRequest', 'project.requirement', 'bid']);
        $project = $award->project;
        $tz = config('bac-office.display_timezone', 'Asia/Manila');
        $ntpOn = $award->ntp_issued_on?->copy() ?? $award->bid?->notice_to_proceed_at?->copy()->timezone($tz)->startOfDay();

        // The delivery period: the project's contract duration, the purchase request's
        // delivery period, else a "within N days" line in the project's requirements.
        $period = collect([$project?->contract_duration, $project?->procurementRequest?->delivery_period])
            ->first(fn ($text) => preg_match('/\d+\s*(calendar\s+)?(day|week|month|year)/i', (string) $text));
        if (blank($period)) {
            $period = collect([
                $project?->requirement?->technical_requirements,
                $project?->requirement?->special_instructions,
            ])->filter()->map(fn ($text) => preg_match('/within\s+(\d+\s*(?:calendar\s+)?(?:day|week|month|year)s?)/i', (string) $text, $m) ? $m[1] : null)->filter()->first();
        }
        [$deadline, $deadlineHint] = [null, null];
        if ($ntpOn && preg_match('/(\d+)\s*(calendar\s+)?(day|week|month|year)s?/i', (string) $period, $duration)) {
            $deadline = match (strtolower($duration[3])) {
                'week' => $ntpOn->copy()->addWeeks((int) $duration[1]),
                'month' => $ntpOn->copy()->addMonthsNoOverflow((int) $duration[1]),
                'year' => $ntpOn->copy()->addYearsNoOverflow((int) $duration[1]),
                default => $ntpOn->copy()->addDays((int) $duration[1]),
            };
            $deadlineHint = 'Notice to Proceed ('.$ntpOn->format('M d, Y').') + '.trim($period).'.';
        }

        // Delivered to the end-user office, in the project's location.
        $location = collect([$project?->end_user_unit, $project?->location])->filter(fn ($part) => filled($part))->unique()->implode(', ');

        [$items, $pricesHint] = self::items($award);

        return [
            'deadline' => $deadline,
            'deadline_hint' => $deadlineHint,
            'location' => $location !== '' ? $location : null,
            'reference' => $project?->reference_no,
            'items' => $items,
            'prices_hint' => $pricesHint,
        ];
    }

    /**
     * The purchase request's items, with unit prices that add up to the contract price.
     *
     * @return array{0: list<array{description: string, quantity: string, unit: string, unit_price: string}>, 1: ?string}
     */
    private static function items(Award $award): array
    {
        $project = $award->project;
        $contractPrice = (float) $award->contract_amount;
        $rows = $project?->procurementRequest?->itemRows() ?? [];
        $number = fn (float $value, int $decimals) => rtrim(rtrim(number_format($value, $decimals, '.', ''), '0'), '.');
        $peso = '₱'.number_format($contractPrice, 2);

        if ($rows === []) {
            return [[[
                'description' => (string) ($project?->title ?? ''),
                'quantity' => '1',
                'unit' => 'lot',
                'unit_price' => $contractPrice > 0 ? $number($contractPrice, 2) : '',
            ]], $contractPrice > 0 ? 'One lot at the contract price of '.$peso.'. Split it into the items of the signed contract.' : null];
        }

        $estimate = array_sum(array_column($rows, 'total'));
        $single = count($rows) === 1 && $rows[0]['quantity'] > 0;
        // Scale the estimates to the contract price; one item takes the whole price.
        $factor = $contractPrice > 0 && $estimate > 0 ? $contractPrice / $estimate : null;

        $items = array_map(fn (array $row) => [
            'description' => $row['description'],
            'quantity' => $number($row['quantity'], 3),
            'unit' => $row['unit'],
            'unit_price' => match (true) {
                $single && $contractPrice > 0 => $number(round($contractPrice / $row['quantity'], 2), 2),
                $factor !== null && $row['unit_cost'] > 0 => $number(round($row['unit_cost'] * $factor, 2), 2),
                default => '',
            },
        ], $rows);

        $hint = match (true) {
            $contractPrice <= 0 => null,
            $single => 'Unit price is the contract price of '.$peso.' divided by the quantity.',
            $factor !== null => 'Unit prices are the purchase request estimates scaled to the contract price of '.$peso.'. Replace them with the prices in the signed contract.',
            default => null,
        };

        return [array_values($items), $hint];
    }
}
