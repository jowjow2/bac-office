<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;

/**
 * Tells the bidders about a new procurement the moment it becomes visible.
 * A project published now is announced straight away; one scheduled for later
 * is announced when its time arrives. Nothing runs at that moment on the
 * server, so the polled notification feed sweeps for publications that have
 * just come due (like BiddingClosedNotices), and each project is announced once.
 */
class PublicationNotices
{
    public const ACTION = 'publication_notified';

    private const EVERY = 10;

    /** Publications older than this are not announced (e.g. right after this ships). */
    private const LOOKBACK_HOURS = 24;

    /** Announce the project if it is visible now and was not announced before. */
    public static function announceIfDue(Project $project): bool
    {
        $at = $project->publicationTime();
        if ($at === null || $at->isAfter(now())) {
            return false;
        }

        $announced = false;
        Cache::lock('live:publication:'.$project->id, 10)->get(function () use ($project, &$announced) {
            $already = AuditLog::query()->where('auditable_type', Project::class)->where('auditable_id', $project->id)->where('action', self::ACTION)->exists();
            if ($already) {
                return;
            }
            AuditLog::log(self::ACTION, $project, null, ['published_at' => $project->publicationTime()?->toIso8601String()], ['user_id' => null]);
            app(ProjectPublication::class)->notifyBidders($project);
            $announced = true;
        });

        return $announced;
    }

    public static function sweep(): void
    {
        if (! Cache::add('live:publication-sweep', true, self::EVERY)) {
            return;
        }

        $now = now();
        Project::query()
            ->with('schedule')
            ->whereNull('archived_at')
            ->where('status', 'open')
            ->whereNotNull('published_at')
            ->whereBetween('published_at', [$now->copy()->subHours(self::LOOKBACK_HOURS), $now])
            ->whereNotExists(fn ($query) => $query->from('audit_logs')
                ->whereColumn('audit_logs.auditable_id', 'projects.id')
                ->where('audit_logs.auditable_type', Project::class)
                ->where('audit_logs.action', self::ACTION))
            ->get()
            ->each(fn (Project $project) => self::announceIfDue($project));
    }
}
