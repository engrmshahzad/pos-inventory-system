<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.app');

        // Every new password: 8+ characters with letters and numbers (owner/manager accounts also need mixed case).
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Idle timeout from Settings (database sessions expire after this many minutes without activity).
        if ($minutes = (int) settings('session_timeout')) {
            config(['session.lifetime' => max(5, $minutes)]);
        }

        // Business timezone from Settings (default Asia/Karachi).
        $timezone = settings('timezone');
        if ($timezone && in_array($timezone, timezone_identifiers_list(), true) && ! $this->app->runningUnitTests()) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }
    }
}
