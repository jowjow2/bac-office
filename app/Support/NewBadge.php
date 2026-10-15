<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The "New" mark on the public Procurement and Awards pages: something posted in the last few days, and not
 * anything that was already there when the mark was introduced.
 */
final class NewBadge
{
    public static function isNew(?CarbonInterface $postedAt): bool
    {
        if ($postedAt === null) {
            return false;
        }
        $zone = config('bac-office.display_timezone', 'Asia/Manila');
        $from = \Illuminate\Support\Carbon::parse((string) config('bac-office.new_badge_from', '2026-10-15 17:00'), $zone);
        $days = max(1, (int) config('bac-office.new_badge_days', 3));

        return $postedAt->greaterThanOrEqualTo($from) && $postedAt->greaterThan(now()->subDays($days));
    }
}
