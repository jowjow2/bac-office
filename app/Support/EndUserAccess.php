<?php

namespace App\Support;

use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What an end-user account sees: the purchase requests it filed itself and
 * the projects made from them. Two accounts of the same office do not see each
 * other's requests.
 *
 * Records nobody owns stay with the whole office: a request filed before
 * requesters were recorded (no requested_by), and a project the BAC created
 * without a purchase request (matched on its end-user office).
 */
class EndUserAccess
{
    /** Purchase requests the account may open. */
    public static function requests(User $user): Builder
    {
        return ProcurementRequest::query()->where(fn (Builder $query) => self::scopeRequests($query, $user));
    }

    public static function scopeRequests(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $owned) => $owned
            ->where('requested_by', $user->id)
            ->orWhere(fn (Builder $unowned) => $unowned
                ->whereNull('requested_by')
                ->where('end_user_office', (string) $user->office)));
    }

    public static function canSeeRequest(?User $user, ?ProcurementRequest $request): bool
    {
        if ($user?->role !== 'end_user' || $request === null) {
            return false;
        }

        return $request->requested_by !== null
            ? (int) $request->requested_by === (int) $user->id
            : $request->end_user_office === $user->office;
    }

    /** Projects the account follows (dashboard, infrastructure tracking). */
    public static function scopeProjects(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $visible) => $visible
            ->whereHas('procurementRequest', fn (Builder $request) => self::scopeRequests($request, $user))
            ->orWhere(fn (Builder $unowned) => $unowned
                ->whereDoesntHave('procurementRequest')
                ->where('end_user_unit', (string) $user->office)));
    }

    public static function canSeeProject(?User $user, ?Project $project): bool
    {
        if ($user?->role !== 'end_user' || $project === null) {
            return false;
        }

        $request = $project->procurementRequest;

        return $request !== null
            ? self::canSeeRequest($user, $request)
            : filled($project->end_user_unit) && $project->end_user_unit === $user->office;
    }

    /**
     * End-user accounts to notify about a request: its requester, or the
     * office's accounts when nobody filed it.
     *
     * @return Collection<int, int>
     */
    public static function recipientsForRequest(?ProcurementRequest $request): Collection
    {
        if ($request === null) {
            return collect();
        }

        if ($request->requested_by !== null) {
            return User::query()->whereKey($request->requested_by)->where('role', 'end_user')->pluck('id');
        }

        return self::officeAccounts($request->end_user_office);
    }

    /** @return Collection<int, int> */
    public static function recipientsForProject(?Project $project): Collection
    {
        if ($project === null) {
            return collect();
        }

        return $project->procurementRequest !== null
            ? self::recipientsForRequest($project->procurementRequest)
            : self::officeAccounts($project->end_user_unit);
    }

    /** @return Collection<int, int> */
    private static function officeAccounts(?string $office): Collection
    {
        if (blank($office)) {
            return collect();
        }

        return User::query()->where('role', 'end_user')->where('office', $office)->pluck('id');
    }
}
