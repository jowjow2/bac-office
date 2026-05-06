<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserPresence
{
    public const ONLINE_TTL_SECONDS = 90;

    public static function touch(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        Cache::put(self::cacheKey($userId), now()->timestamp, now()->addSeconds(self::ONLINE_TTL_SECONDS));
    }

    public static function statusesForIds(Collection $userIds): array
    {
        $ids = $userIds
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $sessionOnlineIds = collect();

        if (Schema::hasTable('sessions')) {
            $sessionOnlineIds = DB::table('sessions')
                ->whereIn('user_id', $ids)
                ->where('last_activity', '>=', now()->subSeconds(self::ONLINE_TTL_SECONDS)->timestamp)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->unique();
        }

        return $ids
            ->mapWithKeys(fn ($id) => [
                $id => Cache::has(self::cacheKey($id)) || $sessionOnlineIds->contains($id),
            ])
            ->all();
    }

    private static function cacheKey(int $userId): string
    {
        return 'presence:user:' . $userId;
    }
}
