<?php

use App\Models\Branch;
use App\Models\Setting;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

if (! function_exists('isOwner')) {
    function isOwner(): bool
    {
        return (bool) Auth::user()?->isOwner();
    }
}

if (! function_exists('currentBranchId')) {
    /**
     * Branch the current request is scoped to.
     * Owner: the branch picked in the switcher (null = All Branches).
     * Everyone else: always their own branch; request input is never trusted.
     */
    function currentBranchId(): ?int
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        if ($user->isOwner()) {
            $id = session('current_branch_id');

            return $id ? (int) $id : null;
        }

        return $user->branch_id;
    }
}

if (! function_exists('currentBranch')) {
    function currentBranch(): ?Branch
    {
        $id = currentBranchId();

        if (! $id) {
            return null;
        }

        // Cached in the container so it lives for one request only.
        $key = "current-branch.{$id}";
        if (! app()->bound($key)) {
            app()->instance($key, Branch::withTrashed()->find($id));
        }

        return app($key);
    }
}

if (! function_exists('settings')) {
    /**
     * settings('currency_symbol', 'Rs') reads one cached value; settings() returns them all.
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        try {
            $all = Setting::allCached();
        } catch (\Throwable) {
            $all = []; // e.g. before migrations have run
        }

        if ($key === null) {
            return $all;
        }

        if (array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '') {
            return $all[$key];
        }

        return $default ?? \App\Support\SettingsDefinition::default($key);
    }
}

if (! function_exists('formatNumber')) {
    /**
     * Thousand separators; "indian" format groups as 1,25,000.
     */
    function formatNumber(float|int|string|null $number, int $decimals = 0, ?string $style = null): string
    {
        $number = (float) $number;
        $style ??= settings('number_format', 'international');

        if ($style !== 'indian') {
            return number_format($number, $decimals);
        }

        $negative = $number < 0;
        $fixed = number_format(abs($number), $decimals, '.', '');
        [$int, $fraction] = array_pad(explode('.', $fixed), 2, null);

        if (strlen($int) > 3) {
            $lastThree = substr($int, -3);
            $rest = substr($int, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $int = $rest.','.$lastThree;
        }

        return ($negative ? '-' : '').$int.($fraction !== null ? '.'.$fraction : '');
    }
}

if (! function_exists('money')) {
    function money(float|int|string|null $amount, bool $withDecimals = false): string
    {
        $amount = (float) $amount;
        $decimals = $withDecimals || floor($amount) != $amount ? 2 : 0;
        $formatted = formatNumber(abs($amount), $decimals);

        return ($amount < 0 ? '-' : '').settings('currency_symbol', 'Rs').' '.$formatted;
    }
}

if (! function_exists('fmtDate')) {
    /** Date in the format chosen in Settings (optionally with time). */
    function fmtDate(mixed $date, bool $withTime = false): string
    {
        if (! $date) {
            return '—';
        }

        $date = $date instanceof \DateTimeInterface ? $date : \Illuminate\Support\Carbon::parse($date);

        return $date->format(settings('date_format', 'd M Y').($withTime ? ', h:i A' : ''));
    }
}

if (! function_exists('activity')) {
    function activity(string $action, string $description, ?Model $subject = null, array $properties = []): void
    {
        ActivityLogger::log($action, $description, $subject, $properties);
    }
}
