<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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

        // Business timezone from Settings (default Asia/Karachi).
        $timezone = settings('timezone');
        if ($timezone && in_array($timezone, timezone_identifiers_list(), true) && ! $this->app->runningUnitTests()) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }
    }
}
