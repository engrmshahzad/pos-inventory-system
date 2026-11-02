<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** One-click database backups (spatie/laravel-backup) stored on the local (private) disk. */
class BackupController extends Controller
{
    public const FOLDER = 'pos-backups';

    public function files(): array
    {
        $disk = Storage::disk('local');

        return collect($disk->files(self::FOLDER))
            ->filter(fn ($f) => Str::endsWith($f, '.zip'))
            ->map(fn ($f) => ['name' => basename($f), 'size' => $disk->size($f), 'date' => \Illuminate\Support\Carbon::createFromTimestamp($disk->lastModified($f))])
            ->sortByDesc('date')->values()->all();
    }

    public function store(): RedirectResponse
    {
        @set_time_limit(300);
        $exit = Artisan::call('backup:run', ['--only-db' => true, '--disable-notifications' => true]);

        if ($exit !== 0) {
            return redirect()->route('settings.index', ['tab' => 'backup'])
                ->withErrors(['action' => 'Backup failed. Check that mysqldump is available (DB_DUMP_PATH in .env). '.Str::limit(trim(Artisan::output()), 200)]);
        }

        activity('backup_created', 'Database backup created');

        return redirect()->route('settings.index', ['tab' => 'backup'])->with('success', 'Backup created.');
    }

    public function download(string $file): StreamedResponse
    {
        $path = $this->path($file);
        activity('backup_downloaded', "Backup {$file} downloaded");

        return Storage::disk('local')->download($path);
    }

    public function destroy(string $file): RedirectResponse
    {
        Storage::disk('local')->delete($this->path($file));

        return redirect()->route('settings.index', ['tab' => 'backup'])->with('success', "{$file} deleted.");
    }

    protected function path(string $file): string
    {
        abort_unless(preg_match('/^[\w\-]+\.zip$/', $file) === 1, 404);
        $path = self::FOLDER.'/'.$file;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return $path;
    }
}
