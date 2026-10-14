<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The "Which period" choices every export dialog offers (Today, This month, This year, All time), each with how
 * many records it holds, and the ?from=&to= range a download is limited to.
 */
final class ExportPeriods
{
    /**
     * @param  callable(?string, ?string): \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query  the records for a range
     * @param  callable(array): string  $url  the download link for the given query parameters
     * @param  callable(mixed): string|null  $hint  a line under a period that has records (default: how many)
     * @return list<array<string, mixed>>
     */
    public static function choices(string $noun, callable $query, callable $url, ?callable $hint = null): array
    {
        $zone = config('bac-office.display_timezone', 'Asia/Manila');
        $today = now($zone)->startOfDay();
        $choices = [];

        foreach ([['Today', $today, $today], ['This month', $today->copy()->startOfMonth(), $today], ['This year', $today->copy()->startOfYear(), $today], ['All time', null, null]] as [$label, $from, $to]) {
            $builder = $query($from?->toDateString(), $to?->toDateString());
            $count = (clone $builder)->count();
            $choices[] = [
                'label' => $label,
                'hint' => $count === 0 ? 'No '.$noun.'s recorded' : ($hint ? $hint($builder, $count) : $count.' '.$noun.($count === 1 ? '' : 's')),
                'count' => $count,
                'icon' => 'fa-calendar-days',
                'url' => $url($from ? ['from' => $from->toDateString(), 'to' => $to->toDateString()] : []),
            ];
        }

        return $choices;
    }

    /** @return array{0: ?string, 1: ?string} the valid ?from= and ?to= dates (Y-m-d), or null for an open end */
    public static function range(Request $request): array
    {
        $edge = fn (string $name) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($name, '')) ? (string) $request->query($name) : null;

        return [$edge('from'), $edge('to')];
    }

    /** "-from-…-to-…" for a file name. */
    public static function suffix(?string $from, ?string $to): string
    {
        return ($from ? '-from-'.$from : '').($to ? '-to-'.$to : '');
    }
}
