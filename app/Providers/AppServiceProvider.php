<?php

namespace App\Providers;

use App\Models\Message;
use App\Support\PortalNavigation;
use App\Support\SystemNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            $host = request()->getHost();

            $isLocalAssetHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true)
                || str_ends_with($host, '.test')
                || str_ends_with($host, '.localhost');

            if (! $isLocalAssetHost) {
                Vite::useHotFile(storage_path('framework/vite.remote.disabled.hot'));
            }
        }

        // Unread counts shown on each role's notifications page.
        View::composer('admin.notifications', fn ($view) => $view->with('unreadNotificationsCount', SystemNotification::unreadCount(Auth::id())));
        View::composer('bidder.notifications', fn ($view) => $view->with('bidderNotificationCount', SystemNotification::unreadCount(Auth::id())));
        View::composer('staff.notifications', fn ($view) => $view->with('staffNotificationCount', SystemNotification::unreadCount(Auth::id())));

        // The portal shell (sidebar, page header bell) used on every role's pages.
        View::composer('partials.portal.sidebar', function ($view) {
            $user = Auth::user();
            $messages = $user ? $this->unreadMessageCountForRole((string) $user->role) : 0;
            $notifications = $user ? SystemNotification::unreadCount($user->id) : 0;

            $view->with('portalNavigation', $user ? PortalNavigation::for($user, $messages, $notifications) : []);
        });

        View::composer('components.portal-bell', function ($view) {
            $user = Auth::user();

            $view->with([
                'portalUnreadNotifications' => $user ? SystemNotification::unreadCount($user->id) : 0,
                'portalNotifications' => $user ? SystemNotification::payloads(SystemNotification::forUser($user->id, 6), $user) : collect(),
            ]);
        });
    }

    protected function unreadMessageCountForRole(string $role): int
    {
        $user = Auth::user();

        if (! $user || $user->role !== $role) {
            return 0;
        }

        return Message::query()
            ->where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }
}
