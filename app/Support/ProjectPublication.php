<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProjectPublication
{
    /**
     * Publish a project in the BAC system. This does not assert or record any
     * external PhilGEPS posting.
     */
    public function publish(Project $project, User $actor, ?CarbonInterface $publishedAt = null): Project
    {
        $clock = app(ProcurementClock::class);
        $recordedAt = $project->getRawOriginal('published_at');
        if ($recordedAt && \Carbon\CarbonImmutable::parse($recordedAt, $clock->timezone())->greaterThan($clock->now())) {
            throw ValidationException::withMessages(['publication' => 'This project has a future publication event. It will become visible when the scheduled time arrives.']);
        }
        if ($project->publicationTime()?->isAfter($clock->now())) {
            throw ValidationException::withMessages(['publication' => 'The scheduled publication time has not arrived.']);
        }
        if ($recordedAt) return $project;
        $publishedAt = ($publishedAt?->copy() ?? now(config('app.timezone', 'Asia/Manila')))
            ->timezone(config('app.timezone', 'Asia/Manila'));

        return DB::transaction(function () use ($project, $actor, $publishedAt): Project {
            $before = [
                'status' => $project->status,
                'published_at' => $project->published_at,
                'published_by' => $project->published_by,
                'date_posted' => $project->schedule?->date_posted,
            ];

            $project->forceFill([
                'status' => 'open',
                'published_at' => $publishedAt,
                'published_by' => $actor->id,
                'reference_no' => $project->reference_no ?: Project::nextReferenceNo($project->category),
            ])->save();

            $schedule = $project->relationLoaded('schedule')
                ? $project->schedule
                : $project->schedule()->first();
            $schedule ??= $project->schedule()->make();
            $schedule->forceFill([
                'date_posted' => $publishedAt->toDateString(),
            ])->save();

            // Legal periods the BAC chose not to meet are kept with the publication record.
            $warnings = $project->setRelation('schedule', $schedule)->scheduleWarnings($project->postingDateForReview());

            AuditLog::log(
                'project_published_locally',
                $project,
                $before,
                [
                    'status' => $project->status,
                    'published_at' => $project->published_at,
                    'published_by' => $project->published_by,
                    'date_posted' => $schedule->date_posted,
                    'philgeps_posted_at' => $project->philgeps_posted_at,
                ] + ($warnings === [] ? [] : ['schedule_warnings' => array_values($warnings)]),
                ['user_id' => $actor->id]
            );

            return $project;
        });
    }
}