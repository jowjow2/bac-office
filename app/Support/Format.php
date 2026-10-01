<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Display formats shared by the portal pages. */
final class Format
{
    /** ₱1,234,567.89 */
    public static function peso(float|int|string|null $amount, int $decimals = 2): string
    {
        return '₱'.number_format((float) $amount, $decimals);
    }

    /** ₱24.6M, ₱850K, ₱9,500 */
    public static function pesoShort(float|int|string|null $amount): string
    {
        $value = (float) $amount;

        return match (true) {
            $value >= 1_000_000_000 => '₱'.rtrim(rtrim(number_format($value / 1_000_000_000, 2), '0'), '.').'B',
            $value >= 1_000_000 => '₱'.rtrim(rtrim(number_format($value / 1_000_000, 1), '0'), '.').'M',
            $value >= 100_000 => '₱'.number_format($value / 1000).'K',
            default => '₱'.number_format($value),
        };
    }

    /** Date (and time) in the LGU's timezone. */
    public static function date(?CarbonInterface $date, bool $withTime = false): string
    {
        if ($date === null) {
            return '—';
        }

        return $date->copy()->timezone(config('bac-office.display_timezone'))->format($withTime ? 'M d, Y · g:i A' : 'M d, Y');
    }

    public static function local(?CarbonInterface $date): ?CarbonInterface
    {
        return $date?->copy()->timezone(config('bac-office.display_timezone'));
    }
}
