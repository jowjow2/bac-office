<?php

namespace App\Providers;

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
        });
    }
}
