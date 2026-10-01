<?php

namespace App\Support;

use App\Models\ContractImplementation;
use Illuminate\Support\Carbon;

/**
 * Liquidated damages for a Goods contract (RA 12009 IRR Sec. 71.1.4):
 * one-tenth of one percent (0.1%) of the cost of the delayed goods for every
 * day of delay, until the goods are finally delivered and accepted.
 *
 * Accepted quantities are dated by the actual receipt they came from, so the
 * LGU's own inspection time is not charged to the supplier. Quantities not
 * accepted yet keep accruing up to the given date. At 10% of the contract
 * price the Procuring Entity may rescind the contract.
 */
class LiquidatedDamages
{
    public const DAILY_RATE = 0.001;

    public const RESCISSION_SHARE = 0.10;

    /**
     * @return array{deadline: ?Carbon, priced: bool, items: list<array>, total: float, share: float, max_days: int, accruing: bool, may_rescind: bool}
     */
    public static function for(ContractImplementation $implementation, float $contractPrice, ?Carbon $asOf = null): array
    {
        $deadline = $implementation->damagesDeadline()?->copy()->startOfDay();
        $items = array_values($implementation->contract_items ?? []);
        $asOf = ($asOf ?? today('Asia/Manila'))->copy()->startOfDay();
        $priced = $items !== [] && collect($items)->every(fn ($item) => is_numeric($item['unit_price'] ?? null));

        $rows = [];
        foreach ($items as $index => $item) {
            $rows[$index] = [
                'description' => $item['description'],
                'unit' => $item['unit'],
                'unit_price' => is_numeric($item['unit_price'] ?? null) ? (float) $item['unit_price'] : null,
                'contract_quantity' => (float) $item['quantity'],
                'accepted' => 0.0,
                'delayed_quantity' => 0.0,
                'days' => 0,
                'amount' => 0.0,
                'accruing' => false,
            ];
        }

        if ($deadline === null || $rows === []) {
            return self::result($deadline, $priced, $rows, $contractPrice);
        }

        // Walk the history: each inspection's newly accepted quantity belongs to the latest receipt.
        $receivedOn = null;
        foreach ($implementation->events as $event) {
            if ($event->action === 'actual_receipt') {
                $receivedOn = Carbon::parse($event->details['actual_received_on'] ?? $event->occurred_at)->startOfDay();
            }
            if ($event->action !== 'inspection' || $receivedOn === null) {
                continue;
            }
            foreach ($event->details['accepted_quantities'] ?? [] as $index => $row) {
                if (! isset($rows[$index])) {
                    continue;
                }
                $increment = max(0, (float) $row['quantity'] - $rows[$index]['accepted']);
                $rows[$index]['accepted'] += $increment;
                if ($increment > 0 && $receivedOn->gt($deadline)) {
                    self::charge($rows[$index], $increment, (int) $deadline->diffInDays($receivedOn));
                }
            }
        }

        // Anything still not accepted keeps accruing.
        foreach ($rows as $index => $row) {
            $remaining = max(0, $row['contract_quantity'] - $row['accepted']);
            if ($remaining > 0 && $asOf->gt($deadline)) {
                self::charge($rows[$index], $remaining, (int) $deadline->diffInDays($asOf));
                $rows[$index]['accruing'] = true;
            }
        }

        return self::result($deadline, $priced, $rows, $contractPrice);
    }

    private static function charge(array &$row, float $quantity, int $days): void
    {
        $row['delayed_quantity'] += $quantity;
        $row['days'] = max($row['days'], $days);
        if ($row['unit_price'] !== null) {
            $row['amount'] += round($row['unit_price'] * $quantity * self::DAILY_RATE * $days, 2);
        }
    }

    private static function result(?Carbon $deadline, bool $priced, array $rows, float $contractPrice): array
    {
        $total = round(array_sum(array_column($rows, 'amount')), 2);
        $share = $contractPrice > 0 ? $total / $contractPrice : 0.0;

        return [
            'deadline' => $deadline,
            'priced' => $priced,
            'items' => array_values($rows),
            'total' => $total,
            'share' => $share,
            'max_days' => (int) max([0, ...array_column($rows, 'days')]),
            'accruing' => collect($rows)->contains('accruing', true),
            'may_rescind' => $share >= self::RESCISSION_SHARE,
        ];
    }
}
