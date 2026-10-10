<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\ContractImplementation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Reminds the BAC and the supplier before a warranty ends, and when it has ended, so the retention
 * money is not forgotten. Nothing runs at those dates on the server, so the polled notification
 * feed sweeps for warranties that have just entered those windows, like BiddingClosedNotices,
 * and each reminder is sent once per contract.
 */
class WarrantyNotices
{
    public const SOON = 'warranty_ending_notified';
    public const ENDED = 'warranty_ended_notified';

    /** Days before the end when the reminder goes out. */
    public const WINDOW_DAYS = 30;

    private const EVERY = 30;

    public static function sweep(): void
    {
        if (! Schema::hasTable('contract_implementations') || ! Cache::add('live:warranty-sweep', true, self::EVERY)) {
            return;
        }

        $today = now(config('bac-office.display_timezone', 'Asia/Manila'))->startOfDay();

        ContractImplementation::query()
            ->with('award.project', 'award.bid')
            ->whereNotNull('accepted_on')
            ->whereNotNull('warranty_months')
            ->whereNull('warranty_released_on')
            ->get()
            ->each(function (ContractImplementation $record) use ($today) {
                $ends = $record->warrantyEndsOn();
                if ($ends === null || ! $record->award) {
                    return;
                }
                $left = (int) $today->diffInDays($ends->copy()->startOfDay(), false);
                if ($left < 0) {
                    self::announce($record, self::ENDED, 'Warranty ended', 'The warranty of '.self::name($record).' ended on '.$ends->format('M d, Y').'. If no defect was found, the retention money can be released and the contract completed.');
                } elseif ($left <= self::WINDOW_DAYS) {
                    self::announce($record, self::SOON, 'Warranty ending soon', 'The warranty of '.self::name($record).' ends in '.$left.' '.\Illuminate\Support\Str::plural('day', $left).' ('.$ends->format('M d, Y').'). Report any defect now so it can be corrected under the warranty.');
                }
            });
    }

    private static function name(ContractImplementation $record): string
    {
        return $record->award->project?->title ?? 'the contract';
    }

    private static function announce(ContractImplementation $record, string $marker, string $title, string $message): void
    {
        $exists = fn () => AuditLog::query()->where('auditable_type', ContractImplementation::class)->where('auditable_id', $record->id)->where('action', $marker)->exists();
        if ($exists()) {
            return;
        }

        Cache::lock('live:warranty:'.$marker.':'.$record->id, 10)->get(function () use ($record, $marker, $title, $message, $exists) {
            if ($exists()) {
                return;
            }
            AuditLog::log($marker, $record, null, ['ends_on' => $record->warrantyEndsOn()?->toDateString()], ['user_id' => null]);

            $award = $record->award;
            $data = ['important' => true, 'award_id' => $award->id, 'kind' => 'contract_implementation'];
            try {
                SystemNotification::createForRole('admin', $title, $message, 'system_alert', $data + ['url' => route('admin.awards.index', ['contract' => $award->id])]);
                $staff = Assignment::query()->where('project_id', $award->project_id)->pluck('staff_id')->unique()->filter()->values();
                if ($staff->isNotEmpty()) {
                    SystemNotification::createForUsers($staff, $title, $message, 'system_alert', $data + ['url' => route('staff.assign-projects')]);
                }
                $supplier = (int) ($award->bid?->user_id ?? $award->bidder_id);
                if ($supplier > 0) {
                    SystemNotification::createForUser($supplier, $title, $message, 'system_alert', $data + ['url' => route('bidder.awarded-contracts', ['award' => $award->id, 'contract' => $award->id])]);
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        });
    }
}
