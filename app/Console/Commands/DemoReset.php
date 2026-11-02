<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/** Wipe everything and load fresh demo data (3 branches, 50 products, 60 days of sales…). Never in production. */
class DemoReset extends Command
{
    protected $signature = 'pos:demo-reset {--force : Skip the confirmation question} {--keep-files : Keep uploaded images and backups}';

    protected $description = 'Reset the database to fresh demo data';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to reset demo data in production (APP_ENV=production).');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This DELETES all data and loads demo data. Continue?')) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        if (! $this->option('keep-files')) {
            foreach (['avatars', 'products', 'brands', 'branding', 'expenses'] as $dir) {
                Storage::disk('public')->deleteDirectory($dir);
            }
            Storage::disk('local')->deleteDirectory('exports');
        }

        $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
        Cache::flush();

        $this->newLine();
        $this->info('Demo data ready. Logins (password: password):');
        $this->table(['Role', 'Email'], [
            ['Owner', 'owner@pos.test'],
            ['Manager HYD / KHI / SUK', 'manager.hyd@pos.test, manager.khi@pos.test, manager.suk@pos.test'],
            ['Cashier HYD / KHI / SUK', 'cashier.hyd@pos.test, cashier.khi@pos.test, cashier.suk@pos.test'],
        ]);
        $this->line('Manager / owner approval PIN: 2468');

        return self::SUCCESS;
    }
}
