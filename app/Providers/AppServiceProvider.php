<?php

namespace App\Providers;

use App\Models\Message;
use App\Support\SystemNotification;
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

        View::composer(['admin.*', 'dashboard.admin'], function ($view) {
            $view->with('unreadNotificationsCount', SystemNotification::unreadCount(auth()->id()));
            $view->with('adminUnreadMessagesCount', $this->unreadMessageCountForRole('admin'));
        });

        View::composer(['bidder.*', 'dashboard.bidder'], function ($view) {
            $view->with('bidderUnreadMessagesCount', $this->unreadMessageCountForRole('bidder'));
            $view->with('bidderNotificationCount', SystemNotification::unreadCount(auth()->id()));
        });
    }

    protected function unreadMessageCountForRole(string $role): int
    {
        $user = auth()->user();

        if (! $user || $user->role !== $role) {
            return 0;
        }

        return Message::query()
            ->where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }
}
