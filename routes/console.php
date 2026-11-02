<?php

use Illuminate\Support\Facades\Schedule;

// Run by `php artisan schedule:run` every minute (cron / Task Scheduler, set up in Phase 7).
Schedule::command('pos:low-stock-alerts')->dailyAt('08:00');
Schedule::command('backup:run --only-db --disable-notifications')->dailyAt('23:30');
Schedule::command('backup:clean --disable-notifications')->dailyAt('23:45');
