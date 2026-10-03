<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;

class SystemNotification
{
    public const IMPORTANT_TYPES = [
        'message',
        'new_bid',
        'bid_submitted',
        'bid_approved',
        'bid_rejected',
        'documents_validated',
        'documents_rejected',
        'bid_document_review',
        'staff_assignment',
        'project_assignment',
        'project_available',
        'project_status',
        'project_created',
        'bid_recommendation',
        'bid_progress',
        'award',
        'award_won',
        'award_decision',
        'account_approved',
        'account_rejected',
        'bidder_requirements_incomplete',
        'bidder_requirements_action',
        'bidder_requirements_resubmitted',
        'bidding_fee_paid',
        'procurement_request',
        'bidder_document',
        'staff_registration',
        'bidder_registration',
        'system_alert',
    ];

    public static function createForUser(int $userId, string $title, string $message, string $type = 'general', array $data = []): void
    {
        if (! self::isImportantType($type, $data)) {
            return;
        }

        $recipient = User::query()->find($userId);
        $data = self::withTargetUrl($recipient, $type, $data);

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
            ->where(function ($query) {
                $query->whereIn('type', self::IMPORTANT_TYPES)
                    ->orWhere('data->important', true);
            })
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
            ->where(function ($query) {
                $query->whereIn('type', self::IMPORTANT_TYPES)
                    ->orWhere('data->important', true);
            })
            ->whereNull('read_at')
            ->count();
    }

    public static function markAllRead(?int $userId): void
    {
        if (! $userId) {
            return;
        }

        UserNotification::where('user_id', $userId)
            ->where(function ($query) {
                $query->whereIn('type', self::IMPORTANT_TYPES)
                    ->orWhere('data->important', true);
            })
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

    public static function payload(UserNotification $notification, ?User $viewer = null): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'type' => $notification->type,
            // A date ahead of the clock (e.g. made under a local demo clock, then copied) shows as a date, not "2 weeks from now".
            'time' => $notification->created_at === null ? 'Recently'
                : ($notification->created_at->isFuture() ? $notification->created_at->format('M d, Y') : $notification->created_at->diffForHumans()),
            'created_at' => $notification->created_at?->toISOString(),
            'is_read' => $notification->read_at !== null,
            'url' => self::targetUrl($notification, $viewer),
            // Lets an open page react live (e.g. unlock a bid once the fee is recorded).
            'project_id' => is_numeric(Arr::get($notification->data ?? [], 'project_id')) ? (int) Arr::get($notification->data, 'project_id') : null,
        ];
    }

    public static function payloads(Collection $notifications, ?User $viewer = null): Collection
    {
        return $notifications
            ->map(fn (UserNotification $notification) => self::payload($notification, $viewer))
            ->values();
    }

    public static function targetUrl(UserNotification $notification, ?User $viewer = null): string
    {
        $data = $notification->data ?? [];
        $storedUrl = Arr::get($data, 'url') ?: Arr::get($data, 'target_url');

        if (filled($storedUrl)) {
            return self::localUrl((string) $storedUrl);
        }

        return self::localUrl(self::resolveTargetUrl($viewer ?? $notification->user, $notification->type, $data));
    }

    /**
     * A link to one of this app's own pages, as a path on the current site.
     * Notifications saved with a full address (e.g. http://localhost:8000/...)
     * keep working after the data moves to another host, such as production.
     * Links to other sites are left as they are.
     */
    public static function localUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['host'])) {
            return $url;
        }

        $path = ($parts['path'] ?? '') !== '' ? $parts['path'] : '/';
        try {
            app('router')->getRoutes()->match(\Illuminate\Http\Request::create($path, 'GET'));
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            return $url;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }

    public static function isImportantType(string $type, array $data = []): bool
    {
        return in_array($type, self::IMPORTANT_TYPES, true)
            || (bool) Arr::get($data, 'important', false);
    }

    protected static function withTargetUrl(?User $recipient, string $type, array $data): array
    {
        if (! filled(Arr::get($data, 'url')) && ! filled(Arr::get($data, 'target_url'))) {
            $data['url'] = self::resolveTargetUrl($recipient, $type, $data);
        }
        // Saved as a path, so the link works on whichever host shows it.
        foreach (['url', 'target_url'] as $key) {
            if (is_string($data[$key] ?? null)) {
                $data[$key] = self::localUrl($data[$key]);
            }
        }

        return $data;
    }

    protected static function resolveTargetUrl(?User $recipient, string $type, array $data): string
    {
        $role = $recipient?->role;

        if ($type === 'message') {
            $senderId = (int) Arr::get($data, 'sender_id', 0);
            $senderRole = $senderId > 0 ? User::query()->whereKey($senderId)->value('role') : null;

            return match ($role) {
                'admin' => route('admin.messages', array_filter([
                    'user' => $senderId ?: null,
                    'tab' => $senderRole === 'staff' ? 'staff' : 'bidders',
                ])),
                'staff' => route('staff.messages', array_filter([
                    'user' => $senderId ?: null,
                    'tab' => $senderRole === 'bidder' ? 'bidders' : 'admin',
                ])),
                'bidder' => route('bidder.messages', array_filter([
                    'user' => $senderId ?: null,
                    'tab' => $senderRole === 'staff' ? 'staff' : 'admin',
                ])),
                default => route('home'),
            };
        }

        return match ($role) {
            'admin' => self::adminTargetUrl($type, $data),
            'staff' => self::staffTargetUrl($type, $data),
            'bidder' => self::bidderTargetUrl($type, $data),
            default => route('home'),
        };
    }

    protected static function adminTargetUrl(string $type, array $data): string
    {
        return match ($type) {
            'new_bid', 'bid_submitted', 'bid_recommendation', 'bid_document_review' => route('admin.bids'),
            'staff_assignment', 'project_assignment' => route('admin.assignments'),
            'award', 'award_won', 'award_decision' => route('admin.awards.index'),
            'staff_registration', 'account_approved', 'account_rejected' => route('admin.users'),
            'bidder_registration', 'bidder_requirements_action', 'bidder_requirements_resubmitted', 'bidder_document' => Arr::get($data, 'user_id') ? route('admin.users.review', ['user' => Arr::get($data, 'user_id')]) : route('admin.users'),
            'project_available', 'project_status', 'project_created' => route('admin.projects'),
            default => route('admin.notifications'),
        };
    }

    protected static function staffTargetUrl(string $type, array $data): string
    {
        return match ($type) {
            'new_bid', 'bid_submitted', 'documents_validated', 'documents_rejected', 'bid_approved', 'bid_rejected', 'bid_document_review' => route('staff.review-bids'),
            'staff_assignment', 'project_assignment' => route('staff.assign-projects'),
            'project_available', 'project_status' => route('staff.assign-projects'),
            'bidder_registration', 'bidder_requirements_action', 'bidder_requirements_resubmitted', 'bidder_document' => Arr::get($data, 'user_id') ? route('staff.users.review', ['user' => Arr::get($data, 'user_id')]) : route('staff.notifications'),
            default => route('staff.notifications'),
        };
    }

    protected static function bidderTargetUrl(string $type, array $data): string
    {
        return match ($type) {
            'project_available' => route('bidder.available-projects'),
            'bid_approved', 'bid_rejected', 'documents_validated', 'documents_rejected', 'new_bid', 'bid_submitted', 'bid_document_review' => route('bidder.my-bids'),
            'award', 'award_won', 'award_decision' => route('bidder.awarded-contracts'),
            'account_approved', 'account_rejected', 'staff_registration', 'bidder_registration', 'bidder_requirements_incomplete', 'bidder_requirements_action' => route('bidder.company-profile'),
            default => route('bidder.notifications'),
        };
    }
}
