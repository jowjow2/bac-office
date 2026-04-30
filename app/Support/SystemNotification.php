<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;

class SystemNotification
{
    public static function createForUser(int $userId, string $title, string $message, string $type = 'general', array $data = []): void
    {
        UserNotification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'data' => $data,
        ]);
    }

    public static function createForUsers(iterable $userIds, string $title, string $message, string $type = 'general', array $data = []): void
    {
        collect($userIds)
            ->filter()
            ->unique()
            ->each(function ($userId) use ($title, $message, $type, $data) {
                self::createForUser((int) $userId, $title, $message, $type, $data);
            });
    }

    public static function createForRole(string $role, string $title, string $message, string $type = 'general', array $data = []): void
    {
        $userIds = User::where('role', $role)->pluck('id');

        self::createForUsers($userIds, $title, $message, $type, $data);
    }

    public static function forUser(?int $userId, int $limit = 20): Collection
    {
        if (! $userId) {
            return collect();
        }

        return UserNotification::where('user_id', $userId)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public static function unreadCount(?int $userId): int
    {
        if (! $userId) {
            return 0;
        }

        return UserNotification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public static function markAllRead(?int $userId): void
    {
        if (! $userId) {
            return;
        }

        UserNotification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public static function markRead(?int $userId, int $notificationId): void
    {
        if (! $userId) {
            return;
        }

        UserNotification::where('user_id', $userId)
            ->whereKey($notificationId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
