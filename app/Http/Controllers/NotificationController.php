<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Support\SystemNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min(max((int) $request->query('limit', 10), 1), 30);
        $notifications = SystemNotification::forUser($user?->id, $limit);

        return response()->json([
            'ok' => true,
            'unread_count' => SystemNotification::unreadCount($user?->id),
            'notifications' => SystemNotification::payloads($notifications, $user),
        ]);
    }

    public function read(Request $request, UserNotification $notification): JsonResponse|RedirectResponse
    {
        $this->ensureOwner($notification);
        SystemNotification::markRead(Auth::id(), $notification->id);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'unread_count' => SystemNotification::unreadCount(Auth::id()),
            ]);
        }

        return redirect()->back();
    }

    public function readAll(Request $request): JsonResponse|RedirectResponse
    {
        SystemNotification::markAllRead(Auth::id());

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'unread_count' => 0,
            ]);
        }

        return redirect()->back();
    }

    public function open(UserNotification $notification): RedirectResponse
    {
        $this->ensureOwner($notification);
        SystemNotification::markRead(Auth::id(), $notification->id);

        return redirect()->to(SystemNotification::targetUrl($notification, Auth::user()));
    }

    protected function ensureOwner(UserNotification $notification): void
    {
        abort_unless((int) $notification->user_id === (int) Auth::id(), 403);
    }
}
