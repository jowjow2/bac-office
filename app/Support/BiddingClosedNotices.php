<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\BiddingFeePayment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Tells the bidders and the BAC when a project's submission deadline passes.
 * Nothing happens at that moment on the server (no job runs at the deadline),
 * so the polled notification feed sweeps for deadlines that have just passed,
 * at most every few seconds across all users, and notifies each project once.
 */
class BiddingClosedNotices
{
    public const ACTION = 'bidding_closed_notified';

    /** Seconds between sweeps, whoever triggers them. */
    private const EVERY = 10;

    /** Deadlines older than this are not announced (e.g. right after this feature ships). */
    private const LOOKBACK_HOURS = 24;

    public static function sweep(): void
    {
        if (! Cache::add('live:bidding-closed-sweep', true, self::EVERY)) {
            return;
        }

        $now = now();
        $since = $now->copy()->subHours(self::LOOKBACK_HOURS);
        $projects = Project::query()
            ->with('schedule')
            ->whereNull('archived_at')
            ->whereNotIn('status', ['draft', 'approved_for_bidding'])
            ->where(function ($query) use ($now, $since) {
                $query->whereBetween('deadline', [$since, $now])
                    ->orWhere(fn ($inner) => $inner->whereNull('deadline')
                        ->whereHas('schedule', fn ($schedule) => $schedule->whereBetween('bid_submission_deadline', [$since, $now])));
            })
            ->whereNotExists(fn ($query) => $query->from('audit_logs')
                ->whereColumn('audit_logs.auditable_id', 'projects.id')
                ->where('audit_logs.auditable_type', Project::class)
                ->where('audit_logs.action', self::ACTION))
            ->get();

        foreach ($projects as $project) {
            // One request announces a project; a concurrent sweep skips it.
            Cache::lock('live:bidding-closed:'.$project->id, 10)->get(function () use ($project) {
                $already = AuditLog::query()->where('auditable_type', Project::class)->where('auditable_id', $project->id)->where('action', self::ACTION)->exists();
                if (! $already) {
                    self::announce($project);
                }
            });
        }
    }

    private static function announce(Project $project): void
    {
        AuditLog::log(self::ACTION, $project, null, ['deadline' => $project->bidSubmissionDeadline()?->toIso8601String()], ['user_id' => null]);

        $tz = config('bac-office.display_timezone', 'Asia/Manila');
        $name = $project->title.($project->reference_no ? ' ('.$project->reference_no.')' : '');
        $opening = $project->schedule?->bid_opening_date?->copy()->timezone($tz);
        $openingText = $opening ? ' Bid opening: '.$opening->format('M d, Y h:i A').'.' : '';
        $data = ['important' => true, 'project_id' => $project->id];

        $bids = Bid::query()->where('project_id', $project->id)->get();
        $official = $bids->reject(fn (Bid $bid) => $bid->isDraft());

        // Bidders who bid, saved a draft, or paid the bidding documents fee.
        $payers = Schema::hasTable('bidding_fee_payments')
            ? BiddingFeePayment::query()->where('project_id', $project->id)->pluck('user_id')
            : collect();
        $bidders = $bids->pluck('user_id')->merge($payers)->unique()->filter();
        foreach ($bidders as $userId) {
            $hasBid = $official->contains('user_id', $userId);
            SystemNotification::createForUser((int) $userId, 'Bidding closed',
                'Submissions for '.$name.' closed. '.($hasBid ? 'Your bid is on record.'.$openingText : 'No official bid from you was recorded for this project.'),
                'project_status', $data + ['url' => route($hasBid ? 'bidder.my-bids' : 'bidder.available-projects', [], false)]);
        }

        // The BAC: admins and the staff assigned to the project.
        $count = $official->count();
        $summary = 'Submissions for '.$name.' closed with '.$count.' '.\Illuminate\Support\Str::plural('bid', $count).' received.'
            .($count > 0 ? ' Next: the bid opening.'.$openingText : ' Consider declaring a failure of bidding.');
        SystemNotification::createForUsers(User::where('role', 'admin')->where('status', 'active')->pluck('id'), 'Bidding closed', $summary, 'project_status',
            $data + ['url' => route('admin.bids', ['project' => $project->id], false)]);
        $staff = \App\Models\Assignment::query()->where('project_id', $project->id)->pluck('staff_id');
        SystemNotification::createForUsers($staff, 'Bidding closed', $summary, 'project_status',
            $data + ['url' => route('staff.review-bids', ['project' => $project->id], false)]);
    }
}
