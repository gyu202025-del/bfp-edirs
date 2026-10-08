<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
    \Illuminate\Support\Facades\View::composer('partials.navbar', function ($view) {
        if (auth()->check()) {
            $notifications = \App\Models\Notification::where('user_id', auth()->id())
                ->latest('created_at')->limit(5)->get();
            $unreadCount = \App\Models\Notification::where('user_id', auth()->id())
                ->where('status', 'Unread')->count();
        } else {
            $notifications = collect();
            $unreadCount = 0;
        }

        $view->with(['navNotifications' => $notifications, 'navUnreadCount' => $unreadCount]);
    });
}
}
