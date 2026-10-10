<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A short fingerprint of the records a page shows. The notification feed,
 * polled every few seconds on every portal page, returns it; when it changes
 * the page reloads itself (partials.notification-live). There is no socket
 * server on Vercel, so this is how pages stay live.
 *
 * Time matters as much as edits: a bid deadline or a scheduled publication
 * passing changes what a page shows without any row changing, so the counts
 * of passed deadlines are part of the fingerprint too.
 */
class LiveVersion
{
    /** Page (route name) => what it shows. Pages not listed are not refreshed. */
    private const ROUTE_SCOPES = [
        'admin.dashboard' => 'pipeline',
        'staff.dashboard' => 'pipeline',
        'admin.projects' => 'projects',
        'admin.procurement.show' => 'pipeline',
        'staff.procurement.show' => 'pipeline',
        'admin.bids' => 'bids',
        'staff.review-bids' => 'bids',
        'admin.awards.index' => 'awards',
        'staff.assign-projects' => 'awards',
        'admin.requests' => 'requests',
        'staff.requests' => 'requests',
        'admin.users' => 'users',
        'admin.payments' => 'payments',
        'staff.payments' => 'payments',
        'bidder.available-projects' => 'bidder-projects',
        'bidder.dashboard' => 'bidder-bids',
        'bidder.my-bids' => 'bidder-bids',
        'bidder.awarded-contracts' => 'bidder-bids',
        // Pages that were not refreshed before: everything with a list or a status on it.
        'admin.assignments' => 'assignments',
        'admin.reports' => 'reports',
        'staff.reports' => 'reports',
        'admin.audit-logs' => 'audit',
        'admin.project.view' => 'projects',
        'admin.bid.view' => 'bids',
        'staff.bid.view' => 'bids',
        'staff.review-bids.show' => 'bids',
        'admin.award.view' => 'awards',
        'admin.users.review' => 'users',
        'staff.users.review' => 'users',
        'bidder.company-profile' => 'bidder-profile',
        'bidder.opportunities.show' => 'bidder-projects',
    ];

    /** Scope => tables (and time checks) it follows. */
    private const SCOPES = [
        'pipeline' => ['projects', 'project_schedules', 'bids', 'awards', 'procurement_requests', 'deadlines'],
        'projects' => ['projects', 'project_schedules', 'bids', 'deadlines'],
        'bids' => ['bids', 'bid_documents', 'projects', 'deadlines'],
        'awards' => ['awards', 'contract_implementations', 'bids'],
        'requests' => ['procurement_requests', 'projects'],
        'users' => ['users', 'bidders'],
        'payments' => ['bidding_fee_payments', 'projects'],
        'bidder-projects' => ['projects', 'project_schedules', 'project_documents', 'bidding_fee_payments', 'own_bids', 'deadlines'],
        'bidder-bids' => ['own_bids', 'awards', 'contract_implementations', 'deadlines'],
        'assignments' => ['assignments', 'projects', 'users'],
        'reports' => ['projects', 'project_schedules', 'bids', 'awards', 'bidding_fee_payments', 'procurement_requests', 'users', 'deadlines'],
        'audit' => ['audit_logs'],
        'bidder-profile' => ['own_profile'],
    ];

    public static function scopeForRoute(?string $routeName): ?string
    {
        return $routeName !== null ? (self::ROUTE_SCOPES[$routeName] ?? null) : null;
    }

    public static function for(?string $scope, ?User $user): ?string
    {
        if ($scope === null || ! isset(self::SCOPES[$scope]) || $user === null) {
            return null;
        }

        $parts = [];
        foreach (self::SCOPES[$scope] as $source) {
            $parts[] = match ($source) {
                'deadlines' => self::deadlines(),
                'own_bids' => self::table('bids', fn ($query) => $query->where('user_id', $user->id)),
                'own_profile' => self::table('bidders', fn ($query) => $query->where('user_id', $user->id)).'|'.self::table('users', fn ($query) => $query->where('id', $user->id)),
                default => self::table($source),
            };
        }

        return substr(sha1(implode('|', $parts)), 0, 16);
    }

    /** Row count and latest change of a table: an insert, edit or delete moves one of them. */
    private static function table(string $table, ?callable $scope = null): string
    {
        $query = DB::table($table);
        if ($scope) {
            $scope($query);
        }
        $row = $query->selectRaw('count(*) as total, max(updated_at) as latest')->first();

        return $table.':'.($row->total ?? 0).':'.($row->latest ?? '-');
    }

    /** How many deadlines, openings and publication times have passed. */
    private static function deadlines(): string
    {
        $now = now()->toDateTimeString();
        $row = DB::table('projects')->selectRaw(
            'sum(case when deadline is not null and deadline <= ? then 1 else 0 end) as closed, '
            .'sum(case when published_at is not null and published_at <= ? then 1 else 0 end) as published',
            [$now, $now]
        )->first();
        $schedules = DB::table('project_schedules')->selectRaw(
            'sum(case when bid_submission_deadline is not null and bid_submission_deadline <= ? then 1 else 0 end) as closed, '
            .'sum(case when bid_opening_date is not null and bid_opening_date <= ? then 1 else 0 end) as opened',
            [$now, $now]
        )->first();

        return 'time:'.($row->closed ?? 0).':'.($row->published ?? 0).':'.($schedules->closed ?? 0).':'.($schedules->opened ?? 0);
    }
}
