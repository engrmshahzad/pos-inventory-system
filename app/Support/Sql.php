<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Date-part expressions that work on MySQL/MariaDB (app) and SQLite (tests). */
class Sql
{
    protected static function sqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    public static function day(string $column): string
    {
        return self::sqlite() ? "strftime('%Y-%m-%d', {$column})" : "DATE({$column})";
    }

    public static function month(string $column): string
    {
        return self::sqlite() ? "strftime('%Y-%m', {$column})" : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    public static function hour(string $column): string
    {
        return self::sqlite() ? "CAST(strftime('%H', {$column}) AS INTEGER)" : "HOUR({$column})";
    }

    /** Whole days between $column and today. */
    public static function ageDays(string $column): string
    {
        return self::sqlite() ? "CAST(julianday('now') - julianday({$column}) AS INTEGER)" : "DATEDIFF(CURDATE(), {$column})";
    }
}
